<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthLoginTest extends TestCase
{
    private PDO $db;
    private array $config;

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
        
        // Bersihkan tabel sebelum setiap test case
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE auth_refresh_tokens');
        $this->db->exec('TRUNCATE TABLE auth_sessions');
        $this->db->exec('TRUNCATE TABLE auth_login_attempts');
        $this->db->exec('TRUNCATE TABLE employees');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Reset superglobals default untuk request POST valid
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

    private function seedEmployee(
        string $id = 'EMP-00001',
        string $username = 'apoteker1',
        string $password = 'SandiKuat123!',
        int $isActive = 1
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO employees (employee_id, employee_name, employee_role, employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            'Budi Santoso',
            'pharmacist',
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $isActive,
        ]);
    }

    private function executeLogin(array $payload): AuthResponseException
    {
        try {
            handleLogin($this->db, $this->config, json_encode($payload, JSON_THROW_ON_ERROR));
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->executeLogin([]);
        $this->assertSame(405, $response->statusCode);
        $this->assertSame('error', $response->payload['status']);
    }

    public function test_menolak_jika_token_csrf_tidak_cocok(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-salah';

        $response = $this->executeLogin(['username' => 'apoteker1', 'password' => 'SandiKuat123!']);
        $this->assertSame(403, $response->statusCode);
    }

    public function test_validasi_input_username_dan_password_tidak_valid(): void
    {
        // Username terlalu pendek (< 3 karakter)
        $response = $this->executeLogin(['username' => 'ab', 'password' => '123456']);
        $this->assertSame(422, $response->statusCode);

        // Password lebih dari 72 byte (batas bcrypt)
        $responseLongPass = $this->executeLogin([
            'username' => 'apoteker1',
            'password' => str_repeat('a', 73),
        ]);
        $this->assertSame(422, $responseLongPass->statusCode);
    }

    public function test_login_gagal_mencatat_attempt_dan_mengembalikan_401(): void
    {
        $this->seedEmployee();

        $response = $this->executeLogin([
            'username' => 'apoteker1',
            'password' => 'SandiSalah!',
        ]);

        $this->assertSame(401, $response->statusCode);

        $attempts = (int) $this->db->query('SELECT COUNT(*) FROM auth_login_attempts')->fetchColumn();
        $this->assertSame(1, $attempts);
    }

    public function test_rate_limit_memblokir_pada_percobaan_ke_6(): void
    {
        $this->seedEmployee();

        // Lakukan 5 kali percobaan gagal
        for ($i = 0; $i < 5; $i++) {
            $res = $this->executeLogin(['username' => 'apoteker1', 'password' => 'Salah']);
            $this->assertSame(401, $res->statusCode);
        }

        // Percobaan ke-6 harus terkena HTTP 429 meskipun sandi benar
        $blockedResponse = $this->executeLogin([
            'username' => 'apoteker1',
            'password' => 'SandiKuat123!',
        ]);

        $this->assertSame(429, $blockedResponse->statusCode);
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $this->seedEmployee(isActive: 0);

        $response = $this->executeLogin([
            'username' => 'apoteker1',
            'password' => 'SandiKuat123!',
        ]);

        $this->assertSame(401, $response->statusCode);
    }

    public function test_login_berhasil_membuat_sesi_jwt_dan_cookies(): void
    {
        $this->seedEmployee();

        $response = $this->executeLogin([
            'username' => 'apoteker1',
            'password' => 'SandiKuat123!',
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame('EMP-00001', $response->payload['employee']['employee_id']);
        $this->assertArrayHasKey('csrf_token', $response->payload);

        // Pastikan cookie terpasang dan JWT valid saat didecode
        $this->assertNotEmpty($_COOKIE['access_token']);
        $claims = authDecodeAccessToken($_COOKIE['access_token']);
        $this->assertSame('EMP-00001', $claims->sub);

        // Pastikan record sesi dan refresh token tersimpan di DB
        $sessionCount = (int) $this->db->query('SELECT COUNT(*) FROM auth_sessions WHERE revoked_at IS NULL')->fetchColumn();
        $refreshCount = (int) $this->db->query('SELECT COUNT(*) FROM auth_refresh_tokens WHERE revoked_at IS NULL')->fetchColumn();
        $this->assertSame(1, $sessionCount);
        $this->assertSame(1, $refreshCount);
    }

    public function test_relogin_mencabut_sesi_sebelumnya(): void
    {
        $this->seedEmployee();

        // Login pertama
        $this->executeLogin(['username' => 'apoteker1', 'password' => 'SandiKuat123!']);
        $firstRefreshToken = $_COOKIE['refresh_token'];

        // Login kedua di browser yang sama (cookie refresh_token lama masih terbawa)
        $_COOKIE['refresh_token'] = $firstRefreshToken;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $_COOKIE['csrf_token'] ?? '';
        $second = $this->executeLogin(['username' => 'apoteker1', 'password' => 'SandiKuat123!']);

        // Sesi lama harus berstatus revoked dengan alasan 'relogin'
        $stmt = $this->db->query("SELECT COUNT(*) FROM auth_sessions WHERE revoked_reason = 'relogin'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }
}