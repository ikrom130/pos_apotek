<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplierDetailTest extends TestCase
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

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE suppliers');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->adminActor = [
            'employee_id' => 'EMP-00000',
            'employee_name' => 'Super Admin',
            'employee_role' => 'admin',
            'employee_username' => 'admin',
        ];

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => $this->config['app_origin'],
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'PHPUnit Test Agent',
        ];
        $_GET = [];
        $_COOKIE = [];
    }

    private function seedSupplier(string $id = 'SUP-00001'): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO suppliers (supplier_id, supplier_name, supplier_phone, supplier_email, supplier_address, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            'PT Kimia Farma Trading',
            '0812-3456-7890',
            'sales@kftd.co.id',
            'Jl. Veteran No. 9, Jakarta Pusat',
            1,
        ]);
    }

    private function executeDetail(?string $id = 'SUP-00001', ?array $actor = null): AuthResponseException
    {
        $_GET = $id !== null ? ['id' => $id] : [];
        $activeActor = $actor ?? $this->adminActor;

        try {
            handleDetailSuppliers($this->db, $activeActor);
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_get(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = $this->executeDetail('SUP-00001');
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_role_tidak_diizinkan(): void
    {
        $kasirActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'kasir',
        ];

        $response = $this->executeDetail('SUP-00001', $kasirActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_validasi_format_supplier_id_tidak_valid(): void
    {
        $resMissing = $this->executeDetail(null);
        $this->assertSame(422, $resMissing->statusCode);

        $resWrongPrefix = $this->executeDetail('EMP-00001');
        $this->assertSame(422, $resWrongPrefix->statusCode);

        $resShortDigits = $this->executeDetail('SUP-001');
        $this->assertSame(422, $resShortDigits->statusCode);
    }

    public function test_mengembalikan_404_jika_supplier_tidak_ditemukan(): void
    {
        $response = $this->executeDetail('SUP-99999');
        $this->assertSame(404, $response->statusCode);
        $this->assertSame('Supplier tidak ditemukan.', $response->payload['message']);
    }

    public function test_berhasil_mengambil_detail_supplier_termasuk_lowercase_id_dan_role_apoteker(): void
    {
        $this->seedSupplier('SUP-00001');

        $apotekerActor = [
            'employee_id' => 'EMP-00003',
            'employee_role' => 'apoteker',
        ];

        // supplierRequireId() menormalisasi ke huruf kapital
        $response = $this->executeDetail('sup-00001', $apotekerActor);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame('SUP-00001', $response->payload['data']['supplier_id']);
        $this->assertSame('PT Kimia Farma Trading', $response->payload['data']['supplier_name']);
        $this->assertSame('0812-3456-7890', $response->payload['data']['supplier_phone']);
        $this->assertSame('sales@kftd.co.id', $response->payload['data']['supplier_email']);
        $this->assertSame('Jl. Veteran No. 9, Jakarta Pusat', $response->payload['data']['supplier_address']);
        $this->assertArrayHasKey('supplier_tax_id', $response->payload['data']);
        $this->assertArrayHasKey('created_at', $response->payload['data']);
        $this->assertArrayHasKey('updated_at', $response->payload['data']);
    }
}