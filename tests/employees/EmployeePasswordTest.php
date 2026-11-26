<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmployeePasswordTest extends TestCase
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
            'REQUEST_METHOD' => 'POST',
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
        string $password = 'SandiLama12345!',
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
            password_hash($password, PASSWORD_DEFAULT),
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

        $tokenStmt = $this->db->prepare(
            'INSERT INTO auth_refresh_tokens (session_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 DAY))'
        );
        $tokenStmt->execute([$sessionId, hash('sha256', 'refresh-' . $sessionId)]);
    }

    private function runPassword(array $payload, array $actor): AuthResponseException
    {
        try {
            handlePasswordEmployee($this->db, $actor, json_encode($payload, JSON_THROW_ON_ERROR));
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_jika_csrf_tidak_valid(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-salah';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runPassword(['new_password' => 'SandiBaruKuat123!'], $this->adminActor);
        $this->assertSame(403, $res->statusCode);
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

        $res = $this->runPassword(['new_password' => 'SandiBaruKuat123!'], $nonAdmin);
        $this->assertSame(403, $res->statusCode);
    }

    public function test_menolak_metode_selain_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runPassword(['new_password' => 'SandiBaruKuat123!'], $this->adminActor);
        $this->assertSame(405, $res->statusCode);
    }

    public function test_admin_tidak_dapat_mengganti_sandi_akun_sendiri(): void
    {
        $_GET['id'] = 'EMP-00001';

        $res = $this->runPassword(['new_password' => 'SandiBaruKuat123!'], $this->adminActor);
        $this->assertSame(422, $res->statusCode);
        $this->assertSame(
            'Admin tidak dapat mengganti sandi akunnya sendiri lewat endpoint ini. Minta admin lain.',
            $res->payload['message']
        );
    }

    public function test_validasi_panjang_sandi_kurang_dari_12_atau_lebih_dari_72_byte(): void
    {
        $_GET['id'] = 'EMP-00003';

        $tooShort = $this->runPassword(['new_password' => 'Pendek123!'], $this->adminActor);
        $this->assertSame(422, $tooShort->statusCode);
        $this->assertSame('Sandi baru harus 12–72 byte.', $tooShort->payload['message']);

        $tooLong = $this->runPassword(['new_password' => str_repeat('a', 73)], $this->adminActor);
        $this->assertSame(422, $tooLong->statusCode);
    }

    public function test_mengembalikan_404_jika_pegawai_tidak_ditemukan(): void
    {
        $_GET['id'] = 'EMP-40404';

        $res = $this->runPassword(['new_password' => 'SandiBaruKuat123!'], $this->adminActor);
        $this->assertSame(404, $res->statusCode);
        $this->assertSame('Pegawai tidak ditemukan.', $res->payload['message']);
    }

    public function test_mengembalikan_409_jika_target_pegawai_nonaktif(): void
    {
        $this->seedEmployee(id: 'EMP-00003', isActive: 0);
        $_GET['id'] = 'EMP-00003';

        $res = $this->runPassword(['new_password' => 'SandiBaruKuat123!'], $this->adminActor);
        $this->assertSame(409, $res->statusCode);
        $this->assertSame('Akun nonaktif tidak dapat diubah sandinya.', $res->payload['message']);
    }

    public function test_berhasil_mengganti_sandi_dan_mencabut_semua_sesi_aktif(): void
    {
        $this->seedEmployee(id: 'EMP-00003', password: 'SandiLama12345!', isActive: 1);
        $session1 = authUuidV4();
        $session2 = authUuidV4();
        $this->seedActiveSession('EMP-00003', $session1);
        $this->seedActiveSession('EMP-00003', $session2);

        $_GET['id'] = 'EMP-00003';
        $newPlainPassword = 'SandiBaruSuperAman#2026';

        $res = $this->runPassword(['new_password' => $newPlainPassword], $this->adminActor);

        $this->assertSame(200, $res->statusCode);
        $this->assertSame('success', $res->payload['status']);
        $this->assertSame('EMP-00003', $res->payload['employee_id']);

        // Verifikasi hash sandi di database sudah berubah ke sandi baru
        $storedHash = $this->db->query(
            "SELECT employee_password FROM employees WHERE employee_id = 'EMP-00003'"
        )->fetchColumn();
        $this->assertTrue(password_verify($newPlainPassword, (string) $storedHash));

        // Verifikasi semua sesi aktif dan refresh token dicabut dengan alasan admin_password_changed
        $revokedSessions = $this->db->query(
            "SELECT COUNT(*) FROM auth_sessions
             WHERE employee_id = 'EMP-00003'
               AND revoked_at IS NOT NULL
               AND revoked_reason = 'admin_password_changed'"
        )->fetchColumn();
        $this->assertSame(2, (int) $revokedSessions);

        $activeRefreshTokens = $this->db->query(
            "SELECT COUNT(*) FROM auth_refresh_tokens WHERE revoked_at IS NULL"
        )->fetchColumn();
        $this->assertSame(0, (int) $activeRefreshTokens);
    }
}