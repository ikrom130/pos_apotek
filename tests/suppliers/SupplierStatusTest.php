<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplierStatusTest extends TestCase
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
            'REQUEST_METHOD' => 'PATCH',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => $this->config['app_origin'],
            'HTTP_X_CSRF_TOKEN' => 'valid-csrf-token-123',
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'PHPUnit Test Agent',
        ];
        $_COOKIE = [
            'csrf_token' => 'valid-csrf-token-123',
        ];
        $_GET = [];
    }

    private function seedSupplier(string $id = 'SUP-00001', int $isActive = 1): void
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
            $isActive,
        ]);
    }

    private function executeStatus(?string $id, array $payload, ?array $actor = null): AuthResponseException
    {
        $_GET = $id !== null ? ['id' => $id] : [];
        $activeActor = $actor ?? $this->adminActor;

        try {
            handleStatusSuppliers(
                $this->db,
                $activeActor,
                json_encode($payload, JSON_THROW_ON_ERROR)
            );
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_jika_csrf_tidak_valid(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-salah';

        $response = $this->executeStatus('SUP-00001', ['is_active' => false]);
        $this->assertSame(403, $response->statusCode);
    }

    public function test_menolak_metode_selain_patch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $response = $this->executeStatus('SUP-00001', ['is_active' => false]);
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_role_tidak_diizinkan(): void
    {
        $kasirActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'kasir',
        ];

        $response = $this->executeStatus('SUP-00001', ['is_active' => false], $kasirActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_validasi_id_dan_is_active_tidak_valid(): void
    {
        $this->seedSupplier('SUP-00001', 1);

        // ID tidak valid
        $resId = $this->executeStatus('SUP-1', ['is_active' => false]);
        $this->assertSame(422, $resId->statusCode);

        // is_active tidak dikirim
        $resMissing = $this->executeStatus('SUP-00001', []);
        $this->assertSame(422, $resMissing->statusCode);

        // is_active berupa string ("1" / "true") atau angka selain 0/1
        $resString = $this->executeStatus('SUP-00001', ['is_active' => '1']);
        $this->assertSame(422, $resString->statusCode);

        $resIntInvalid = $this->executeStatus('SUP-00001', ['is_active' => 2]);
        $this->assertSame(422, $resIntInvalid->statusCode);
    }

    public function test_mengembalikan_404_jika_supplier_tidak_ditemukan(): void
    {
        $response = $this->executeStatus('SUP-99999', ['is_active' => false]);
        $this->assertSame(404, $response->statusCode);
        $this->assertSame('Supplier tidak ditemukan.', $response->payload['message']);
    }

    public function test_berhasil_menonaktifkan_dan_mengaktifkan_kembali_supplier(): void
    {
        $this->seedSupplier('SUP-00001', 1);

        $apotekerActor = [
            'employee_id' => 'EMP-00003',
            'employee_role' => 'apoteker',
        ];

        // Nonaktifkan (boolean false)
        $deactivateRes = $this->executeStatus('SUP-00001', ['is_active' => false], $apotekerActor);
        $this->assertSame(200, $deactivateRes->statusCode);
        $this->assertSame('success', $deactivateRes->payload['status']);
        $this->assertSame('Supplier di-non-aktifkan.', $deactivateRes->payload['message']);

        $stmt = $this->db->prepare('SELECT is_active FROM suppliers WHERE supplier_id = ?');
        $stmt->execute(['SUP-00001']);
        $this->assertSame(0, (int) $stmt->fetchColumn());

        // Aktifkan kembali (boolean true)
        $activateRes = $this->executeStatus('SUP-00001', ['is_active' => true], $apotekerActor);
        $this->assertSame(200, $activateRes->statusCode);
        $this->assertSame('Supplier diaktifkan.', $activateRes->payload['message']);

        $stmt->execute(['SUP-00001']);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }
}