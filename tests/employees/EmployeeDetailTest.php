<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmployeeDetailTest extends TestCase
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
            'REQUEST_METHOD' => 'GET',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => $this->config['app_origin'],
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $_COOKIE = [];
    }

    private function seedEmployee(
        string $id = 'EMP-00003',
        string $name = 'Rina Kusuma',
        string $role = 'pharmacist',
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

    private function runDetail(array $actor): AuthResponseException
    {
        try {
            handleDetailEmployees($this->db, $actor);
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_get(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['id'] = 'EMP-00003';

        $res = $this->runDetail($this->adminActor);
        $this->assertSame(405, $res->statusCode);
    }

    public function test_menolak_non_admin_dengan_403(): void
    {
        $this->seedEmployee(id: 'EMP-00003', role: 'cashier', username: 'kasir_test');
        $_GET['id'] = 'EMP-00003';

        $nonAdmin = [
            'employee_id' => 'EMP-00003',
            'employee_name' => 'Kasir Test',
            'employee_role' => 'cashier',
            'employee_username' => 'kasir_test',
        ];

        $res = $this->runDetail($nonAdmin);
        $this->assertSame(403, $res->statusCode);
    }

    public function test_mengembalikan_404_jika_pegawai_tidak_ditemukan(): void
    {
        $_GET['id'] = 'EMP-99999';

        $res = $this->runDetail($this->adminActor);
        $this->assertSame(404, $res->statusCode);
        $this->assertSame('Pegawai tidak ditemukan.', $res->payload['message']);
    }

    public function test_berhasil_mengambil_detail_pegawai_tanpa_password_hash(): void
    {
        $this->seedEmployee(
            id: 'EMP-00003',
            name: 'Rina Kusuma',
            role: 'apoteker',
            phone: '081234567890',
            username: 'rina_apt'
        );
        $_GET['id'] = 'EMP-00003';

        $res = $this->runDetail($this->adminActor);

        $this->assertSame(200, $res->statusCode);
        $this->assertSame('success', $res->payload['status']);
        $this->assertSame('EMP-00003', $res->payload['data']['employee_id']);
        $this->assertSame('Rina Kusuma', $res->payload['data']['employee_name']);
        $this->assertSame('apoteker', $res->payload['data']['employee_role']);
        $this->assertArrayNotHasKey('employee_password', $res->payload['data']);
    }
}