<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthLogoutTest extends TestCase
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

        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE auth_refresh_tokens');
        $this->db->exec('TRUNCATE TABLE auth_sessions');
        $this->db->exec('TRUNCATE TABLE auth_login_attempts');
        $this->db->exec('TRUNCATE TABLE employees');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

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

    private function seedEmployee(string $id = 'EMP-00001', int $isActive = 1): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO employees (employee_id, employee_name, employee_role, employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            'Budi Santoso',
            'pharmacist',
            'apoteker1',
            password_hash('SandiKuat123!', PASSWORD_DEFAULT),
            $isActive,
        ]);
    }

    private function seedSession(
        string $employeeId = 'EMP-00001',
        string $sessionId = '22222222-2222-4222-8222-222222222222',
        int $sessionTtlOffset = 86400
    ): array {
        $rawRefresh = authNewRefreshToken();
        $absExpiry = time() + $sessionTtlOffset;

        $insertSession = $this->db->prepare(
            "INSERT INTO auth_sessions
                (session_id, employee_id, ip_address, user_agent, created_at, last_seen_at, absolute_expires_at)
             VALUES
                (?, ?, '192.168.1.10', 'PHPUnit Test Agent', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), ?)"
        );
        $insertSession->execute([
            $sessionId,
            $employeeId,
            gmdate('Y-m-d H:i:s', $absExpiry),
        ]);

        $insertRefresh = $this->db->prepare(
            "INSERT INTO auth_refresh_tokens (session_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 DAY))"
        );
        $insertRefresh->execute([$sessionId, hash('sha256', $rawRefresh)]);

        $GLOBALS['authConfig'] = $this->config;
        [$accessToken] = authIssueAccessToken($employeeId, $sessionId, $absExpiry);

        return [
            'session_id' => $sessionId,
            'refresh_token' => $rawRefresh,
            'access_token' => $accessToken,
        ];
    }

    private function executeLogout(): AuthResponseException
    {
        try {
            handleLogout($this->db, $this->config);
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->executeLogout();
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_token_csrf_tidak_cocok(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-salah';

        $response = $this->executeLogout();
        $this->assertSame(403, $response->statusCode);
    }

    public function test_logout_dengan_refresh_token_mencabut_sesi_dan_refresh_token(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSession();
        $_COOKIE['refresh_token'] = $seeded['refresh_token'];

        $response = $this->executeLogout();
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);

        $session = $this->db->prepare('SELECT revoked_at, revoked_reason FROM auth_sessions WHERE session_id = ?');
        $session->execute([$seeded['session_id']]);
        $sessionRow = $session->fetch();

        $this->assertNotNull($sessionRow['revoked_at']);
        $this->assertSame('logout', $sessionRow['revoked_reason']);

        $activeTokens = $this->db->prepare(
            'SELECT COUNT(*) FROM auth_refresh_tokens WHERE session_id = ? AND revoked_at IS NULL'
        );
        $activeTokens->execute([$seeded['session_id']]);
        $this->assertSame(0, (int) $activeTokens->fetchColumn());
    }

    public function test_logout_tanpa_refresh_token_tetap_mencabut_sesi_dari_access_token(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSession();
        unset($_COOKIE['refresh_token']);
        $_COOKIE['access_token'] = $seeded['access_token'];

        $response = $this->executeLogout();
        $this->assertSame(200, $response->statusCode);

        $reason = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reason->execute([$seeded['session_id']]);
        $this->assertSame('logout', $reason->fetchColumn());
    }

    public function test_logout_tetap_berhasil_meski_tanpa_cookie_sesi(): void
    {
        unset($_COOKIE['refresh_token'], $_COOKIE['access_token']);

        $response = $this->executeLogout();
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
    }
}