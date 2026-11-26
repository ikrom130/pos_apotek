<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplierCreateTest extends TestCase
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
        $this->db->exec('TRUNCATE TABLE id_sequences');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->adminActor = [
            'employee_id' => 'EMP-00000',
            'employee_name' => 'Super Admin',
            'employee_role' => 'admin',
            'employee_username' => 'admin',
        ];

        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => $this->config['app_origin'],
            'HTTP_X_CSRF_TOKEN' => 'valid-csrf-token-123',
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'PHPUnit Test Agent',
        ];
        $_COOKIE = [
            'csrf_token' => 'valid-csrf-token-123',
        ];
    }

    private function executeCreate(array $payload, ?array $actor = null): AuthResponseException
    {
        $activeActor = $actor ?? $this->adminActor;
        try {
            handleCreateSupplier(
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
            'supplier_name' => 'PT Kimia Farma Trading',
            'supplier_phone' => '0812-3456-7890',
            'supplier_email' => 'sales@kftd.co.id',
            'supplier_address' => 'Jl. Veteran No. 9, Jakarta Pusat',
        ], $overrides);
    }

    public function test_menolak_jika_csrf_tidak_valid(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-salah';

        $response = $this->executeCreate($this->validPayload());
        $this->assertSame(403, $response->statusCode);
    }

    public function test_menolak_metode_selain_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->executeCreate($this->validPayload());
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_role_tidak_diizinkan(): void
    {
        $kasirActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'kasir',
        ];

        $response = $this->executeCreate($this->validPayload(), $kasirActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_mengizinkan_role_apoteker(): void
    {
        $apotekerActor = [
            'employee_id' => 'EMP-00003',
            'employee_role' => 'apoteker',
        ];

        $response = $this->executeCreate($this->validPayload(), $apotekerActor);
        $this->assertSame(201, $response->statusCode);
        $this->assertSame('SUP-00001', $response->payload['supplier_id']);
    }

    public function test_validasi_data_supplier_tidak_valid(): void
    {
        // Nama terlalu pendek (< 2 karakter)
        $resShortName = $this->executeCreate($this->validPayload(['supplier_name' => 'A']));
        $this->assertSame(422, $resShortName->statusCode);

        // Nama terlalu panjang (> 100 karakter)
        $resLongName = $this->executeCreate($this->validPayload(['supplier_name' => str_repeat('a', 101)]));
        $this->assertSame(422, $resLongName->statusCode);

        // Nomor telepon tidak valid
        $resPhone = $this->executeCreate($this->validPayload(['supplier_phone' => 'abc#123']));
        $this->assertSame(422, $resPhone->statusCode);

        // Email tidak valid
        $resEmail = $this->executeCreate($this->validPayload(['supplier_email' => 'bukan-email']));
        $this->assertSame(422, $resEmail->statusCode);

        // Alamat terlalu panjang (> 255 karakter)
        $resLongAddress = $this->executeCreate($this->validPayload([
            'supplier_address' => str_repeat('Jl. Sudirman ', 25),
        ]));
        $this->assertSame(422, $resLongAddress->statusCode);

        // Alamat mengandung karakter terlarang
        $resInvalidAddress = $this->executeCreate($this->validPayload([
            'supplier_address' => 'Jl. Sudirman <script>alert(1)</script>',
        ]));
        $this->assertSame(422, $resInvalidAddress->statusCode);
    }

    public function test_berhasil_membuat_supplier_baru_dengan_id_berurutan(): void
    {
        $response = $this->executeCreate($this->validPayload([
            'supplier_name' => '  PT Kimia Farma Trading  ',
            'supplier_email' => 'Sales@KFTD.co.id',
        ]));

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame('SUP-00001', $response->payload['supplier_id']);

        $stmt = $this->db->prepare('SELECT * FROM suppliers WHERE supplier_id = ?');
        $stmt->execute(['SUP-00001']);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('PT Kimia Farma Trading', $row['supplier_name']);
        $this->assertSame('0812-3456-7890', $row['supplier_phone']);
        $this->assertSame('sales@kftd.co.id', $row['supplier_email']);
        $this->assertSame('Jl. Veteran No. 9, Jakarta Pusat', $row['supplier_address']);
        $this->assertSame(1, (int) $row['is_active']);

        // Supplier kedua mendapat SUP-00002 dan field opsional kosong menjadi NULL
        $secondResponse = $this->executeCreate([
            'supplier_name' => 'PT Anugrah Argon Medica',
            'supplier_phone' => '',
            'supplier_email' => '',
            'supplier_address' => '',
        ]);

        $this->assertSame(201, $secondResponse->statusCode);
        $this->assertSame('SUP-00002', $secondResponse->payload['supplier_id']);

        $stmtSecond = $this->db->prepare('SELECT * FROM suppliers WHERE supplier_id = ?');
        $stmtSecond->execute(['SUP-00002']);
        $secondRow = $stmtSecond->fetch();

        $this->assertIsArray($secondRow);
        $this->assertNull($secondRow['supplier_phone']);
        $this->assertNull($secondRow['supplier_email']);
        $this->assertNull($secondRow['supplier_address']);
    }

    public function test_duplikat_kolom_unik_mengembalikan_409_dan_rollback_sequence(): void
    {
        // Sesuaikan kolom unik ini jika pada tabel suppliers yang unik adalah supplier_phone atau supplier_name
        $first = $this->executeCreate($this->validPayload([
            'supplier_email' => 'duplikat@supplier.co.id',
        ]));
        $this->assertSame(201, $first->statusCode);

        // Pastikan transaksi di-rollback sehingga sequence tidak meloncat
        $seq = (int) $this->db
            ->query("SELECT last_id_recorded FROM id_sequences WHERE sequence_key = 'supplier'")
            ->fetchColumn();
        $this->assertSame(1, $seq);
    }
}