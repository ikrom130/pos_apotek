<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthRequireEmployeeTest extends TestCase
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

        // authRequireEmployee() membaca $authDb dan $authConfig dari scope global[cite: 1]
        $GLOBALS['authDb'] = $this->db;
        $GLOBALS['authConfig'] = $this->config;

        // Bersihkan tabel sebelum setiap test case
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE auth_refresh_tokens');
        $this->db->exec('TRUNCATE TABLE auth_sessions');
        $this->db->exec('TRUNCATE TABLE auth_login_attempts');
        $this->db->exec('TRUNCATE TABLE employees');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $_COOKIE = [];
    }

    /**
     * Helper untuk membuat data employee + session + JWT valid secara langsung
     * dengan opsi kustomisasi last_seen_at, absolute_expires_at, dan revoked_at.
     */
    private function seedSessionWithToken(array $overrides = []): string
    {
        $employeeId = $overrides['employee_id'] ?? 'EMP-00001';
        $sessionId = $overrides['session_id'] ?? authUuidV4(); //[cite: 1]
        $isActive = $overrides['is_active'] ?? 1;

        $stmtEmp = $this->db->prepare(
            'INSERT INTO employees (employee_id, employee_name, employee_role, employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmtEmp->execute([
            $employeeId,
            'Siti Rahma',
            'cashier',
            'kasir1_' . substr($sessionId, 0, 6),
            password_hash('Rahasia123!', PASSWORD_DEFAULT),
            $isActive,
        ]);

        $lastSeenExpr = $overrides['last_seen_sql'] ?? 'UTC_TIMESTAMP(6)';
        $absExpiryExpr = $overrides['abs_expiry_sql'] ?? 'DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 7 DAY)';
        $revokedAt = $overrides['revoked_at'] ?? null;
        $revokedReason = $overrides['revoked_reason'] ?? null;

        $sqlSession = "INSERT INTO auth_sessions
            (session_id, employee_id, created_at, last_seen_at, absolute_expires_at, revoked_at, revoked_reason, user_agent, ip_address)
         VALUES (?, ?, UTC_TIMESTAMP(6), {$lastSeenExpr}, {$absExpiryExpr}, ?, ?, 'PHPUnit', '127.0.0.1')";

        $stmtSess = $this->db->prepare($sqlSession);
        $stmtSess->execute([
            $sessionId,
            $employeeId,
            $revokedAt,
            $revokedReason,
        ]);

        // Buat JWT yang valid secara kriptografis[cite: 1]
        $now = time();
        $absEpoch = $overrides['jwt_abs_exp'] ?? ($now + $this->config['session_ttl']);
        [$accessToken] = authIssueAccessToken($employeeId, $sessionId, $absEpoch); //[cite: 1]

        return $accessToken;
    }

    private function invokeMiddleware(): array|AuthResponseException
    {
        try {
            return authRequireEmployee(); //[cite: 1]
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_jika_cookie_access_token_kosong(): void
    {
        $result = $this->invokeMiddleware();

        $this->assertInstanceOf(AuthResponseException::class, $result);
        $this->assertSame(401, $result->statusCode);
    }

    public function test_sesi_aktif_berhasil_diautentikasi_dan_memperbarui_last_seen_at(): void
    {
        $sessionId = authUuidV4(); //[cite: 1]
        $token = $this->seedSessionWithToken([
            'session_id' => $sessionId,
            // Terakhir aktif 5 menit lalu (masih di bawah idle_ttl 30 menit / 1800 detik)
            'last_seen_sql' => 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 5 MINUTE)',
        ]);
        $_COOKIE['access_token'] = $token;

        $beforeTouch = $this->db->query(
            "SELECT last_seen_at FROM auth_sessions WHERE session_id = '{$sessionId}'"
        )->fetchColumn();

        $employee = $this->invokeMiddleware();

        $this->assertIsArray($employee);
        $this->assertSame('EMP-00001', $employee['employee_id']);
        $this->assertSame('cashier', $employee['employee_role']);

        $afterTouch = $this->db->query(
            "SELECT last_seen_at FROM auth_sessions WHERE session_id = '{$sessionId}'"
        )->fetchColumn();

        // last_seen_at harus ter-update menjadi lebih baru[cite: 1]
        $this->assertNotSame($beforeTouch, $afterTouch);
    }

    public function test_idle_timeout_menolak_akses_dan_mencabut_sesi(): void
    {
        $sessionId = authUuidV4(); //[cite: 1]
        $token = $this->seedSessionWithToken([
            'session_id' => $sessionId,
            // Terakhir aktif 31 menit lalu (> idle_ttl 1800 detik / 30 menit)
            'last_seen_sql' => 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 31 MINUTE)',
        ]);
        $_COOKIE['access_token'] = $token;

        $result = $this->invokeMiddleware();

        $this->assertInstanceOf(AuthResponseException::class, $result);
        $this->assertSame(401, $result->statusCode);

        $session = $this->db->query(
            "SELECT revoked_at, revoked_reason FROM auth_sessions WHERE session_id = '{$sessionId}'"
        )->fetch();

        $this->assertNotNull($session['revoked_at']);
        $this->assertSame('idle_expired', $session['revoked_reason']);
    }

    public function test_absolute_expiry_menolak_akses_dan_mencabut_sesi(): void
    {
        $sessionId = authUuidV4(); //[cite: 1]
        $token = $this->seedSessionWithToken([
            'session_id' => $sessionId,
            // absolute_expires_at sudah lewat 1 jam yang lalu
            'abs_expiry_sql' => 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 HOUR)',
        ]);
        $_COOKIE['access_token'] = $token;

        $result = $this->invokeMiddleware();

        $this->assertInstanceOf(AuthResponseException::class, $result);
        $this->assertSame(401, $result->statusCode);

        $session = $this->db->query(
            "SELECT revoked_at, revoked_reason FROM auth_sessions WHERE session_id = '{$sessionId}'"
        )->fetch();

        $this->assertNotNull($session['revoked_at']);
        $this->assertSame('absolute_expired', $session['revoked_reason']);
    }

    public function test_revoked_session_ditolak_dengan_401(): void
    {
        $sessionId = authUuidV4(); //[cite: 1]
        $token = $this->seedSessionWithToken([
            'session_id' => $sessionId,
            'revoked_at' => gmdate('Y-m-d H:i:s'),
            'revoked_reason' => 'logout',
        ]);
        $_COOKIE['access_token'] = $token;

        $result = $this->invokeMiddleware();

        $this->assertInstanceOf(AuthResponseException::class, $result);
        $this->assertSame(401, $result->statusCode);
    }

    public function test_akun_karyawan_nonaktif_mencabut_sesi_dan_mengembalikan_401(): void
    {
        $sessionId = authUuidV4(); //[cite: 1]
        $token = $this->seedSessionWithToken([
            'session_id' => $sessionId,
            'is_active' => 0,
        ]);
        $_COOKIE['access_token'] = $token;

        $result = $this->invokeMiddleware();

        $this->assertInstanceOf(AuthResponseException::class, $result);
        $this->assertSame(401, $result->statusCode);

        $session = $this->db->query(
            "SELECT revoked_at, revoked_reason FROM auth_sessions WHERE session_id = '{$sessionId}'"
        )->fetch();

        $this->assertNotNull($session['revoked_at']);
        $this->assertSame('employee_inactive', $session['revoked_reason']);
    }
}