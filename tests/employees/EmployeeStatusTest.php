<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmployeeStatusTest extends TestCase
{
    private PDO $db;
    private array $config;
    private array $adminActor;

    protected function setUp(): void
    {
        $dbCfg = $GLOBALS['testDbConfig'];
        $this->config = $GLOBALS['authConfig'];

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $dbCfg['host'],
            $dbCfg['dbname'],
            $dbCfg['charset']
        );
        $this->db = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $GLOBALS['authDb'] = $this->db;
        $GLOBALS['authConfig'] = $this->config;
        $GLOBALS['employeeDb'] = $this->db;

        $this->adminActor = [
            'employee_id' => 'EMP-00001',
            'employee_name' => 'Admin Utama',
            'employee_role' => 'admin',
            'employee_username' => 'admin_utama',
        ];
        $GLOBALS['apiActor'] = $this->adminActor;

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE auth_refresh_tokens');
        $this->db->exec('TRUNCATE TABLE auth_sessions');
        $this->db->exec('TRUNCATE TABLE auth_login_attempts');
        $this->db->exec('TRUNCATE TABLE employees');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $_GET = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'PATCH',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => $this->config['app_origin'],
            'HTTP_X_CSRF_TOKEN' => 'valid-csrf-token',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $_COOKIE = [
            'csrf_token' => 'valid-csrf-token',
        ];
    }

    private function seedEmployee(
        string $id = 'EMP-00003',
        string $role = 'apoteker',
        string $username = 'rina_apt',
        int $isActive = 1
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO employees (employee_id, employee_name, employee_role, employee_phone, employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            'Rina Kusuma',
            $role,
            '081234567890',
            $username,
            password_hash('PasswordAman123!', PASSWORD_DEFAULT),
            $isActive,
        ]);
    }

    private function seedActiveSession(string $employeeId, string $sessionId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO auth_sessions (session_id, employee_id, created_at, last_seen_at, absolute_expires_at, user_agent, ip_address)
             VALUES (?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 7 DAY), ?, ?)'
        );
        $stmt->execute([$sessionId, $employeeId, 'PHPUnit', '127.0.0.1']);
    }

    private function runStatus(array $payload, array $actor): AuthResponseException
    {
        try {
            handleStatusEmployee($this->db, $actor, json_encode($payload, JSON_THROW_ON_ERROR));
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_jika_csrf_tidak_valid(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-salah';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runStatus(['is_active' => false], $this->adminActor);
        $this->assertSame(403, $res->statusCode);
    }

    public function test_menolak_metode_selain_patch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runStatus(['is_active' => false], $this->adminActor);
        $this->assertSame(405, $res->statusCode);
    }

    public function test_menolak_non_admin_dengan_403(): void
    {
        $_GET['id'] = 'EMP-00003';
        $nonAdmin = [
            'employee_id' => 'EMP-00002',
            'employee_name' => 'Kasir',
            'employee_role' => 'kasir',
            'employee_username' => 'kasir_1',
        ];

        $res = $this->runStatus(['is_active' => false], $nonAdmin);
        $this->assertSame(403, $res->statusCode);
    }

    public function test_validasi_is_active_harus_boolean_atau_0_1(): void
    {
        $_GET['id'] = 'EMP-00003';

        $resString = $this->runStatus(['is_active' => 'true'], $this->adminActor);
        $this->assertSame(422, $resString->statusCode);
        $this->assertSame('is_active harus boolean.', $resString->payload['message']);

        $resNull = $this->runStatus([], $this->adminActor);
        $this->assertSame(422, $resNull->statusCode);
    }

    public function test_mencegah_admin_menonaktifkan_akun_sendiri(): void
    {
        $_GET['id'] = 'EMP-00001';

        $res = $this->runStatus(['is_active' => false], $this->adminActor);
        $this->assertSame(422, $res->statusCode);
        $this->assertSame('Anda tidak dapat menonaktifkan akun sendiri.', $res->payload['message']);
    }

    public function test_mengembalikan_404_jika_pegawai_tidak_ditemukan(): void
    {
        $_GET['id'] = 'EMP-40404';

        $res = $this->runStatus(['is_active' => false], $this->adminActor);
        $this->assertSame(404, $res->statusCode);
        $this->assertSame('Pegawai tidak ditemukan.', $res->payload['message']);
    }

    public function test_menonaktifkan_pegawai_mencabut_sesi_aktif(): void
    {
        $this->seedEmployee(id: 'EMP-00003', isActive: 1);
        $sessionId = authUuidV4();
        $this->seedActiveSession('EMP-00003', $sessionId);

        $_GET['id'] = 'EMP-00003';

        $res = $this->runStatus(['is_active' => false], $this->adminActor);

        $this->assertSame(200, $res->statusCode);
        $this->assertSame('success', $res->payload['status']);
        $this->assertSame('Pegawai dinonaktifkan.', $res->payload['message']);

        $isActive = (int) $this->db->query(
            "SELECT is_active FROM employees WHERE employee_id = 'EMP-00003'"
        )->fetchColumn();
        $this->assertSame(0, $isActive);

        $session = $this->db->query(
            "SELECT revoked_at, revoked_reason FROM auth_sessions WHERE session_id = '{$sessionId}'"
        )->fetch();
        $this->assertNotNull($session['revoked_at']);
        $this->assertSame('employee_deactivated', $session['revoked_reason']);
    }

    public function test_mengaktifkan_kembali_pegawai_nonaktif(): void
    {
        $this->seedEmployee(id: 'EMP-00003', isActive: 0);
        $_GET['id'] = 'EMP-00003';

        $res = $this->runStatus(['is_active' => 1], $this->adminActor);

        $this->assertSame(200, $res->statusCode);
        $this->assertSame('success', $res->payload['status']);
        $this->assertSame('Pegawai diaktifkan.', $res->payload['message']);

        $isActive = (int) $this->db->query(
            "SELECT is_active FROM employees WHERE employee_id = 'EMP-00003'"
        )->fetchColumn();
        $this->assertSame(1, $isActive);
    }
}