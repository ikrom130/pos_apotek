<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplierUpdateTest extends TestCase
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
            'REQUEST_METHOD' => 'PUT',
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

    private function seedSupplier(
        string $id = 'SUP-00001',
        string $name = 'PT Kimia Farma Trading',
        ?string $phone = '0812-3456-7890',
        ?string $email = 'sales@kftd.co.id',
        ?string $address = 'Jl. Veteran No. 9, Jakarta Pusat'
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO suppliers (supplier_id, supplier_name, supplier_phone, supplier_email, supplier_address, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$id, $name, $phone, $email, $address]);
    }

    private function executeUpdate(?string $id, array $payload, ?array $actor = null): AuthResponseException
    {
        $_GET = $id !== null ? ['id' => $id] : [];
        $activeActor = $actor ?? $this->adminActor;

        try {
            handleUpdateSuppliers(
                $this->db,
                $activeActor,
                json_encode($payload, JSON_THROW_ON_ERROR)
            );
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'supplier_name' => 'PT Enseval Putera Megatrading',
            'supplier_phone' => '021-46822422',
            'supplier_email' => 'cs@enseval.com',
            'supplier_address' => 'Jl. Pulo Lentut No. 10, Jakarta Timur',
        ], $overrides);
    }

    public function test_menolak_jika_csrf_tidak_valid(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-salah';

        $response = $this->executeUpdate('SUP-00001', $this->validPayload());
        $this->assertSame(403, $response->statusCode);
    }

    public function test_menolak_metode_selain_put(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = $this->executeUpdate('SUP-00001', $this->validPayload());
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_role_tidak_diizinkan(): void
    {
        $kasirActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'kasir',
        ];

        $response = $this->executeUpdate('SUP-00001', $this->validPayload(), $kasirActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_validasi_id_dan_payload_tidak_valid(): void
    {
        $this->seedSupplier('SUP-00001');

        // ID tidak valid
        $resId = $this->executeUpdate('INVALID-ID', $this->validPayload());
        $this->assertSame(422, $resId->statusCode);

        // Nama kosong / terlalu pendek
        $resName = $this->executeUpdate('SUP-00001', $this->validPayload(['supplier_name' => 'X']));
        $this->assertSame(422, $resName->statusCode);

        // Telepon tidak valid
        $resPhone = $this->executeUpdate('SUP-00001', $this->validPayload(['supplier_phone' => 'telp@123']));
        $this->assertSame(422, $resPhone->statusCode);

        // Email tidak valid
        $resEmail = $this->executeUpdate('SUP-00001', $this->validPayload(['supplier_email' => 'email-salah']));
        $this->assertSame(422, $resEmail->statusCode);

        // Alamat mengandung karakter terlarang
        $resAddress = $this->executeUpdate('SUP-00001', $this->validPayload([
            'supplier_address' => 'Jl. Sudirman <script>',
        ]));
        $this->assertSame(422, $resAddress->statusCode);
    }

    public function test_mengembalikan_404_jika_supplier_tidak_ditemukan(): void
    {
        $response = $this->executeUpdate('SUP-99999', $this->validPayload());
        $this->assertSame(404, $response->statusCode);
        $this->assertSame('Supplier tidak ditemukan.', $response->payload['message']);
    }

    public function test_berhasil_memperbarui_data_supplier_dan_mengubah_field_kosong_menjadi_null(): void
    {
        $this->seedSupplier('SUP-00001');

        $apotekerActor = [
            'employee_id' => 'EMP-00003',
            'employee_role' => 'apoteker',
        ];

        $response = $this->executeUpdate('SUP-00001', $this->validPayload([
            'supplier_name' => '  PT Enseval Putera Megatrading  ',
            'supplier_email' => 'CS@ENSEVAL.COM',
        ]), $apotekerActor);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame('Data supplier diperbarui.', $response->payload['message']);

        $stmt = $this->db->prepare('SELECT * FROM suppliers WHERE supplier_id = ?');
        $stmt->execute(['SUP-00001']);
        $row = $stmt->fetch();

        $this->assertSame('PT Enseval Putera Megatrading', $row['supplier_name']);
        $this->assertSame('021-46822422', $row['supplier_phone']);
        $this->assertSame('cs@enseval.com', $row['supplier_email']);
        $this->assertSame('Jl. Pulo Lentut No. 10, Jakarta Timur', $row['supplier_address']);

        // Kosongkan field opsional -> harus tersimpan NULL
        $clearResponse = $this->executeUpdate('SUP-00001', [
            'supplier_name' => 'PT Enseval Putera Megatrading',
            'supplier_phone' => '',
            'supplier_email' => '',
            'supplier_address' => '',
        ]);
        $this->assertSame(200, $clearResponse->statusCode);

        $stmt->execute(['SUP-00001']);
        $clearedRow = $stmt->fetch();
        $this->assertNull($clearedRow['supplier_phone']);
        $this->assertNull($clearedRow['supplier_email']);
        $this->assertNull($clearedRow['supplier_address']);
    }

    public function test_duplikat_kolom_unik_saat_update_mengembalikan_409(): void
    {
        $this->seedSupplier('SUP-00001', 'Supplier Satu', '0811-1111-1111', 'satu@supplier.test', 'Jakarta');
        $this->seedSupplier('SUP-00002', 'Supplier Dua', '0822-2222-2222', 'dua@supplier.test', 'Bandung');

        // Mencoba mengubah SUP-00002 menggunakan email milik SUP-00001 (asumsi supplier_email UNIQUE)
        $response = $this->executeUpdate('SUP-00002', $this->validPayload([
            'supplier_email' => 'satu@supplier.test',
        ]));

        $this->assertSame(409, $response->statusCode);
        $this->assertSame('Data supplier sudah terdaftar.', $response->payload['message']);
    }
}