<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmployeeCreateTest extends TestCase
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

        // Bersihkan tabel terkait sebelum setiap test case
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE auth_refresh_tokens');
        $this->db->exec('TRUNCATE TABLE auth_sessions');
        $this->db->exec('TRUNCATE TABLE employees');
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
            handleCreateEmployee(
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
            'employee_name' => 'Siti Aminah',
            'employee_role' => 'apoteker',
            'employee_phone' => '0812-3456-7890',
            'employee_username' => 'siti.aminah',
            'employee_password' => 'SandiAman12345!',
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

    public function test_menolak_jika_role_bukan_admin(): void
    {
        $kasirActor = [
            'employee_id' => 'EMP-00002',
            'employee_role' => 'kasir',
        ];

        $response = $this->executeCreate($this->validPayload(), $kasirActor);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Role tidak memiliki akses ke fitur ini.', $response->payload['message']);
    }

    public function test_validasi_profil_pegawai_tidak_valid(): void
    {
        // Nama terlalu pendek (< 2 karakter)
        $resName = $this->executeCreate($this->validPayload(['employee_name' => 'A']));
        $this->assertSame(422, $resName->statusCode);

        // Role tidak diizinkan
        $resRole = $this->executeCreate($this->validPayload(['employee_role' => 'manager']));
        $this->assertSame(422, $resRole->statusCode);

        // Nomor telepon tidak valid
        $resPhone = $this->executeCreate($this->validPayload(['employee_phone' => 'abc#123']));
        $this->assertSame(422, $resPhone->statusCode);

        // Username tidak valid
        $resUser = $this->executeCreate($this->validPayload(['employee_username' => 'us']));
        $this->assertSame(422, $resUser->statusCode);
    }

    public function test_validasi_panjang_sandi_awal(): void
    {
        // Kurang dari 12 byte
        $resShort = $this->executeCreate($this->validPayload(['employee_password' => 'Pendek123!']));
        $this->assertSame(422, $resShort->statusCode);

        // Lebih dari 72 byte
        $resLong = $this->executeCreate($this->validPayload(['employee_password' => str_repeat('x', 73)]));
        $this->assertSame(422, $resLong->statusCode);
    }

    public function test_berhasil_membuat_pegawai_baru_dengan_id_berurutan(): void
    {
        $response = $this->executeCreate($this->validPayload());

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame('EMP-00001', $response->payload['employee_id']);

        // Verifikasi data tersimpan di database dan password ter-hash
        $stmt = $this->db->prepare('SELECT * FROM employees WHERE employee_id = ?');
        $stmt->execute(['EMP-00001']);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('Siti Aminah', $row['employee_name']);
        $this->assertSame('apoteker', $row['employee_role']);
        $this->assertSame('0812-3456-7890', $row['employee_phone']);
        $this->assertSame('siti.aminah', $row['employee_username']);
        $this->assertSame(1, (int) $row['is_active']);
        $this->assertTrue(password_verify('SandiAman12345!', $row['employee_password']));

        // Pembuatan pegawai kedua harus mendapat EMP-00002 (phone kosong jadi null)
        $secondResponse = $this->executeCreate($this->validPayload([
            'employee_name' => 'Rudi Hartono',
            'employee_role' => 'kasir',
            'employee_phone' => '',
            'employee_username' => 'rudi_kasir',
        ]));

        $this->assertSame(201, $secondResponse->statusCode);
        $this->assertSame('EMP-00002', $secondResponse->payload['employee_id']);
    }

    public function test_username_duplikat_mengembalikan_409_dan_rollback_sequence(): void
    {
        // Buat pegawai pertama (EMP-00001)
        $first = $this->executeCreate($this->validPayload(['employee_username' => 'duplikat.user']));
        $this->assertSame(201, $first->statusCode);

        // Coba buat pegawai kedua dengan username sama
        $duplicate = $this->executeCreate($this->validPayload([
            'employee_name' => 'Pegawai Lain',
            'employee_username' => 'duplikat.user',
        ]));

        $this->assertSame(409, $duplicate->statusCode);
        $this->assertSame('Username pegawai sudah digunakan.', $duplicate->payload['message']);

        // Pastikan transaksi di-rollback sehingga sequence tidak meloncat
        $seq = (int) $this->db->query("SELECT last_id_recorded FROM id_sequences WHERE sequence_key = 'employee'")->fetchColumn();
        $this->assertSame(1, $seq);
    }
}