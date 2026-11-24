<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthRefreshTest extends TestCase
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

    private function seedSessionAndRefreshToken(
        string $employeeId = 'EMP-00001',
        string $sessionId = '11111111-1111-4111-8111-111111111111',
        ?string $rawRefreshToken = null,
        ?string $lastSeenSql = 'UTC_TIMESTAMP(6)',
        ?string $absoluteExpiresSql = null,
        ?string $refreshExpiresSql = null,
        ?string $sessionRevokedSql = null,
        ?string $tokenRevokedSql = null,
        ?string $tokenUsedSql = null
    ): array {
        $rawToken = $rawRefreshToken ?? authNewRefreshToken();
        $tokenHash = hash('sha256', $rawToken);

        $absExpr = $absoluteExpiresSql ?? sprintf(
            'DATE_ADD(UTC_TIMESTAMP(6), INTERVAL %d SECOND)',
            max((int) $this->config['refresh_ttl'] + 3600, 7200)
        );
        $refExpr = $refreshExpiresSql ?? sprintf(
            'DATE_ADD(UTC_TIMESTAMP(6), INTERVAL %d SECOND)',
            (int) $this->config['refresh_ttl']
        );
        $lastSeenExpr = $lastSeenSql ?? 'NULL';
        $sessionRevokedExpr = $sessionRevokedSql ?? 'NULL';
        $tokenRevokedExpr = $tokenRevokedSql ?? 'NULL';
        $tokenUsedExpr = $tokenUsedSql ?? 'NULL';

        $insertSession = $this->db->prepare(
            "INSERT INTO auth_sessions
                (session_id, employee_id, ip_address, user_agent, created_at, last_seen_at, absolute_expires_at, revoked_at, revoked_reason)
             VALUES
                (?, ?, '192.168.1.10', 'PHPUnit Test Agent', UTC_TIMESTAMP(6), {$lastSeenExpr}, {$absExpr}, {$sessionRevokedExpr}, NULL)"
        );
        $insertSession->execute([$sessionId, $employeeId]);

        $insertToken = $this->db->prepare(
            "INSERT INTO auth_refresh_tokens
                (session_id, token_hash, issued_at, expires_at, used_at, revoked_at)
             VALUES
                (?, ?, UTC_TIMESTAMP(6), {$refExpr}, {$tokenUsedExpr}, {$tokenRevokedExpr})"
        );
        $insertToken->execute([$sessionId, $tokenHash]);

        return [
            'session_id' => $sessionId,
            'refresh_token_id' => (int) $this->db->lastInsertId(),
            'raw_token' => $rawToken,
            'token_hash' => $tokenHash,
        ];
    }

    private function executeRefresh(): AuthResponseException
    {
        try {
            handleRefresh($this->db, $this->config);
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->executeRefresh();
        $this->assertSame(405, $response->statusCode);
        $this->assertSame('error', $response->payload['status']);
    }

    public function test_menolak_jika_token_csrf_tidak_cocok(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-salah';

        $response = $this->executeRefresh();
        $this->assertSame(403, $response->statusCode);
    }

    public function test_menolak_jika_cookie_refresh_token_kosong(): void
    {
        unset($_COOKIE['refresh_token']);

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);
    }

    public function test_menolak_refresh_token_yang_tidak_dikenal(): void
    {
        $this->seedEmployee();
        $_COOKIE['refresh_token'] = authNewRefreshToken();

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);
    }

    public function test_refresh_berhasil_merotasi_token_dan_menerbitkan_access_token_baru(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSessionAndRefreshToken();
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertArrayHasKey('csrf_token', $response->payload);
        $this->assertArrayHasKey('access_expires_at', $response->payload);

        // Token lama harus ditandai sudah terpakai dan terhubung ke token baru
        $oldStmt = $this->db->prepare(
            'SELECT used_at, replaced_by_token_id, revoked_at
             FROM auth_refresh_tokens
             WHERE refresh_token_id = ?'
        );
        $oldStmt->execute([$seeded['refresh_token_id']]);
        $oldToken = $oldStmt->fetch();

        $this->assertNotNull($oldToken['used_at']);
        $this->assertNotNull($oldToken['replaced_by_token_id']);
        $this->assertNull($oldToken['revoked_at']);

        // Harus ada 1 refresh token baru yang masih aktif
        $activeTokens = (int) $this->db->query(
            'SELECT COUNT(*) FROM auth_refresh_tokens WHERE used_at IS NULL AND revoked_at IS NULL'
        )->fetchColumn();
        $this->assertSame(1, $activeTokens);

        // Cookie terotasi dan access_token baru valid
        $this->assertNotSame($seeded['raw_token'], $_COOKIE['refresh_token']);
        $this->assertNotEmpty($_COOKIE['access_token']);

        $claims = authDecodeAccessToken($_COOKIE['access_token']);
        $this->assertSame('EMP-00001', $claims->sub);
        $this->assertSame($seeded['session_id'], $claims->sid);
    }

    public function test_reuse_token_yang_sudah_dipakai_mencabut_seluruh_sesi(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSessionAndRefreshToken(
            tokenUsedSql: 'UTC_TIMESTAMP(6)'
        );
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);

        $sessionStmt = $this->db->prepare(
            'SELECT revoked_at, revoked_reason FROM auth_sessions WHERE session_id = ?'
        );
        $sessionStmt->execute([$seeded['session_id']]);
        $sessionRow = $sessionStmt->fetch();

        $this->assertNotNull($sessionRow['revoked_at']);
        $this->assertSame('refresh_reuse', $sessionRow['revoked_reason']);
    }

    public function test_menolak_jika_sesi_sudah_dicabut(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSessionAndRefreshToken(
            sessionRevokedSql: 'UTC_TIMESTAMP(6)'
        );
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);
    }

    public function test_menolak_dan_mencabut_sesi_jika_idle_ttl_terlampaui(): void
    {
        $this->seedEmployee();
        $expiredIdleSeconds = (int) $this->config['idle_ttl'] + 60;
        $seeded = $this->seedSessionAndRefreshToken(
            lastSeenSql: sprintf('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL %d SECOND)', $expiredIdleSeconds)
        );
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);

        $reasonStmt = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reasonStmt->execute([$seeded['session_id']]);
        $this->assertSame('idle_expired', $reasonStmt->fetchColumn());
    }

    public function test_menolak_dan_mencabut_sesi_jika_refresh_token_kedaluwarsa(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSessionAndRefreshToken(
            refreshExpiresSql: 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 10 SECOND)'
        );
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);

        $reasonStmt = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reasonStmt->execute([$seeded['session_id']]);
        $this->assertSame('refresh_expired', $reasonStmt->fetchColumn());
    }

    public function test_menolak_dan_mencabut_sesi_jika_absolute_expiry_terlampaui(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedSessionAndRefreshToken(
            absoluteExpiresSql: 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 10 SECOND)'
        );
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);

        $reasonStmt = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reasonStmt->execute([$seeded['session_id']]);
        $this->assertSame('absolute_expired', $reasonStmt->fetchColumn());
    }

    public function test_menolak_dan_mencabut_sesi_jika_karyawan_nonaktif(): void
    {
        $this->seedEmployee(isActive: 0);
        $seeded = $this->seedSessionAndRefreshToken();
        $_COOKIE['refresh_token'] = $seeded['raw_token'];

        $response = $this->executeRefresh();
        $this->assertSame(401, $response->statusCode);

        $reasonStmt = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reasonStmt->execute([$seeded['session_id']]);
        $this->assertSame('employee_inactive', $reasonStmt->fetchColumn());
    }
}