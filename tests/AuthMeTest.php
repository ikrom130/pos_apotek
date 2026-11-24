<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthMeTest extends TestCase
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
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'PHPUnit Test Agent',
        ];
        $_COOKIE = [];
    }

    private function seedEmployee(
        string $id = 'EMP-00001',
        string $username = 'apoteker1',
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
            password_hash('SandiKuat123!', PASSWORD_DEFAULT),
            $isActive,
        ]);
    }

    private function seedValidSession(
        string $employeeId = 'EMP-00001',
        string $sessionId = '33333333-3333-4333-8333-333333333333',
        ?string $lastSeenSql = 'UTC_TIMESTAMP(6)',
        ?string $absoluteExpiresSql = null,
        ?string $revokedAtSql = null
    ): array {
        $absSql = $absoluteExpiresSql ?? 'DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 DAY)';
        $seenSql = $lastSeenSql ?? 'NULL';
        $revSql = $revokedAtSql ?? 'NULL';

        $stmt = $this->db->prepare(
            "INSERT INTO auth_sessions
                (session_id, employee_id, ip_address, user_agent, created_at, last_seen_at, absolute_expires_at, revoked_at)
             VALUES
                (?, ?, '192.168.1.10', 'PHPUnit Test Agent', UTC_TIMESTAMP(6), {$seenSql}, {$absSql}, {$revSql})"
        );
        $stmt->execute([$sessionId, $employeeId]);

        $absExpiry = (int) $this->db->query(
            sprintf('SELECT UNIX_TIMESTAMP(absolute_expires_at) FROM auth_sessions WHERE session_id = %s', $this->db->quote($sessionId))
        )->fetchColumn();

        $GLOBALS['authConfig'] = $this->config;
        [$accessToken] = authIssueAccessToken($employeeId, $sessionId, $absExpiry);

        return [
            'session_id' => $sessionId,
            'access_token' => $accessToken,
        ];
    }

    private function executeMe(): AuthResponseException
    {
        try {
            handleMe($this->db, $this->config);
            $this->fail('Expected AuthResponseException was not thrown.');
        } catch (AuthResponseException $e) {
            return $e;
        }
    }

    public function test_menolak_metode_selain_get(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = $this->executeMe();
        $this->assertSame(405, $response->statusCode);
    }

    public function test_menolak_jika_access_token_tidak_ada(): void
    {
        $response = $this->executeMe();
        $this->assertSame(401, $response->statusCode);
    }

    public function test_menolak_jika_access_token_tidak_valid(): void
    {
        $_COOKIE['access_token'] = 'token-jwt-asal';

        $response = $this->executeMe();
        $this->assertSame(401, $response->statusCode);
    }

    public function test_menolak_jika_sesi_sudah_dicabut(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedValidSession(revokedAtSql: 'UTC_TIMESTAMP(6)');
        $_COOKIE['access_token'] = $seeded['access_token'];

        $response = $this->executeMe();
        $this->assertSame(401, $response->statusCode);
    }

    public function test_menolak_dan_mencabut_sesi_jika_idle_ttl_terlampaui(): void
    {
        $this->seedEmployee();
        $expiredIdle = (int) $this->config['idle_ttl'] + 60;
        $seeded = $this->seedValidSession(
            lastSeenSql: sprintf('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL %d SECOND)', $expiredIdle)
        );
        $_COOKIE['access_token'] = $seeded['access_token'];

        $response = $this->executeMe();
        $this->assertSame(401, $response->statusCode);

        $reason = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reason->execute([$seeded['session_id']]);
        $this->assertSame('idle_expired', $reason->fetchColumn());
    }

    public function test_menolak_jika_karyawan_nonaktif(): void
    {
        $this->seedEmployee(isActive: 0);
        $seeded = $this->seedValidSession();
        $_COOKIE['access_token'] = $seeded['access_token'];

        $response = $this->executeMe();
        $this->assertSame(401, $response->statusCode);

        $reason = $this->db->prepare('SELECT revoked_reason FROM auth_sessions WHERE session_id = ?');
        $reason->execute([$seeded['session_id']]);
        $this->assertSame('employee_inactive', $reason->fetchColumn());
    }

    public function test_mengembalikan_data_karyawan_dan_memperbarui_last_seen_at(): void
    {
        $this->seedEmployee();
        $seeded = $this->seedValidSession(
            lastSeenSql: 'DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 120 SECOND)'
        );
        $_COOKIE['access_token'] = $seeded['access_token'];

        $beforeSeen = $this->db->prepare('SELECT last_seen_at FROM auth_sessions WHERE session_id = ?');
        $beforeSeen->execute([$seeded['session_id']]);
        $oldLastSeen = (string) $beforeSeen->fetchColumn();

        $response = $this->executeMe();

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('success', $response->payload['status']);
        $this->assertSame('EMP-00001', $response->payload['employee']['employee_id']);
        $this->assertSame('apoteker1', $response->payload['employee']['employee_username']);

        $afterSeen = $this->db->prepare('SELECT last_seen_at FROM auth_sessions WHERE session_id = ?');
        $afterSeen->execute([$seeded['session_id']]);
        $newLastSeen = (string) $afterSeen->fetchColumn();

        $this->assertNotSame($oldLastSeen, $newLastSeen);
    }
}