<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmployeeUpdateTest extends TestCase
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
            'employee_id' => 'EMP-00002',
            'employee_name' => 'Darmaji',
            'employee_role' => 'admin',
            'employee_username' => 'darmaji',
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
            'REQUEST_METHOD' => 'PUT',
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
        string $name = 'Rina Kusuma',
        string $role = 'apoteker',
        string $phone = '081234567890',
        string $username = 'rina_apt',
        int $isActive = 1
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO employees (employee_id, employee_name, employee_role, employee_phone, employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $name,
            $role,
            $phone,
            $username,
            password_hash('PasswordAman123!', PASSWORD_DEFAULT),
            $isActive,
        ]);
    }

    private function runUpdate(array $payload, array $actor): AuthResponseException
    {
        try {
            handleUpdateEmployees($this->db, $actor, json_encode($payload, JSON_THROW_ON_ERROR));
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_jika_csrf_tidak_valid(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-salah';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runUpdate([
            'employee_name' => 'Nama Baru',
            'employee_role' => 'apoteker',
            'employee_phone' => '081111111111',
            'employee_username' => 'rina_apt',
        ], $this->adminActor);

        $this->assertSame(403, $res->statusCode);
    }

    public function test_menolak_metode_selain_put(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runUpdate([
            'employee_name' => 'Nama Baru',
            'employee_role' => 'apoteker',
            'employee_phone' => '081111111111',
            'employee_username' => 'rina_apt',
        ], $this->adminActor);

        $this->assertSame(405, $res->statusCode);
    }

    public function test_menolak_non_admin_dengan_403(): void
    {
        $_GET['id'] = 'EMP-00003';
        $nonAdmin = [
            'employee_id' => 'EMP-00003',
            'employee_name' => 'Kasir',
            'employee_role' => 'kasir',
            'employee_username' => 'kasir_1',
        ];

        $res = $this->runUpdate([
            'employee_name' => 'Nama Baru',
            'employee_role' => 'apoteker',
            'employee_phone' => '081111111111',
            'employee_username' => 'rina_apt',
        ], $nonAdmin);

        $this->assertSame(403, $res->statusCode);
    }

    public function test_mengembalikan_404_jika_target_pegawai_tidak_ditemukan(): void
    {
        $_GET['id'] = 'EMP-40404';

        $res = $this->runUpdate([
            'employee_name' => 'Tidak Ada',
            'employee_role' => 'apoteker',
            'employee_phone' => '081111111111',
            'employee_username' => 'tidak_ada',
        ], $this->adminActor);

        $this->assertSame(404, $res->statusCode);
        $this->assertSame('Pegawai tidak ditemukan.', $res->payload['message']);
    }

    public function test_mencegah_admin_menurunkan_role_sendiri(): void
    {
        $this->seedEmployee(
            id: 'EMP-00002',
            name: 'Darmaji',
            role: 'admin',
            phone: '081200000000',
            username: 'darmaji'
        );
        $_GET['id'] = 'EMP-00002';

        $res = $this->runUpdate([
            'employee_name' => 'Darmaji',
            'employee_role' => 'kasir',
            'employee_phone' => '081200000000',
            'employee_username' => 'darmaji',
        ], $this->adminActor);

        $this->assertSame(422, $res->statusCode);
        $this->assertSame('Anda tidak dapat menurunkan role admin sendiri.', $res->payload['message']);

        $role = $this->db->query("SELECT employee_role FROM employees WHERE employee_id = 'EMP-00002'")->fetchColumn();
        $this->assertSame('admin', $role);
    }

    public function test_berhasil_memperbarui_data_pegawai(): void
    {
        $this->seedEmployee(
            id: 'EMP-00003',
            name: 'Rina Kusuma',
            role: 'apoteker',
            phone: '081234567890',
            username: 'rina_apt'
        );
        $_GET['id'] = 'EMP-00003';

        $res = $this->runUpdate([
            'employee_name' => 'Rina Kusuma, S.Farm., Apt.',
            'employee_role' => 'admin',
            'employee_phone' => '089876543210',
            'employee_username' => 'rina_admin',
        ], $this->adminActor);

        $this->assertSame(200, $res->statusCode);
        $this->assertSame('success', $res->payload['status']);

        $updated = $this->db->query(
            "SELECT employee_name, employee_role, employee_phone, employee_username
             FROM employees WHERE employee_id = 'EMP-00003'"
        )->fetch();

        $this->assertSame('Rina Kusuma, S.Farm., Apt.', $updated['employee_name']);
        $this->assertSame('admin', $updated['employee_role']);
        $this->assertSame('089876543210', $updated['employee_phone']);
        $this->assertSame('rina_admin', $updated['employee_username']);
    }
}