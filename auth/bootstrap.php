<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';

$authDatabase = new Database();
$authDb = $authDatabase->getConnection();
$authConfig = require dirname(__DIR__) . '/config/auth.php';

function authJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function authRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        authJson(405, ['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    }
}

function authReadJson(): array
{
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        authJson(415, ['status' => 'error', 'message' => 'Content-Type harus application/json.']);
    }

    try {
        $data = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        authJson(400, ['status' => 'error', 'message' => 'Body JSON tidak valid.']);
    }

    if (!is_array($data)) {
        authJson(400, ['status' => 'error', 'message' => 'Body JSON harus berupa object.']);
    }
    return $data;
}

function authrequireCsrf(): void
{
    global $authConfig;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $cookieToken = $_COOKIE['csrf_token'];
    $headerToken = $_SERVER['HTTP-X-CSRF-TOKEN'] ?? '';

    if (
        !is_string($origin) || !hash_equals($authConfig['app_origin'], $origin) ||
        !is_string($cookieToken) || 
    ) {
        # code...
    }
}

function authRequestIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
}

function authRateHashes(string $username, string $ip): array
{
    global $authConfig;
    $normalizedUsername = strtolower(trim($username));
    return [
        hash_hmac('sha256', $normalizedUsername . "\0" . $ip, $authConfig['rate_limit_key']),
        hash_hmac('sha256', $ip, $authConfig['rate_limit_key']),
    ];
}

function authLoginIsLimited(PDO $db, string $usernameIpHash, string $ipHash): bool
{
    $userQuery = $db->prepare(
        'SELECT COUNT(*) FROM auth_login_attempts
         WHERE username_ip_hash = ?
           AND attempted_at >= UTC_TIMESTAMP(6) - INTERVAL 15 MINUTE'
    );
    $userQuery->execute([$usernameIpHash]);
    $userCount = (int) $userQuery->fetchColumn();

    $ipQuery = $db->prepare(
        'SELECT COUNT(*) FROM auth_login_attempts
         WHERE ip_hash = ?
           AND attempted_at >= UTC_TIMESTAMP(6) - INTERVAL 15 MINUTE'
    );
    $ipQuery->execute([$ipHash]);
    $ipCount = (int) $ipQuery->fetchColumn();

    return $userCount >= 5 || $ipCount >= 30;
}

function authRecordLoginFailure(PDO $db, string $usernameIpHash, string $ipHash): void
{
    $stmt = $db->prepare(
        'INSERT INTO auth_login_attempts (username_ip_hash, ip_hash, attempted_at)
         VALUES (?, ?, UTC_TIMESTAMP(6))'
    );
    $stmt->execute([$usernameIpHash, $ipHash]);
}

function authUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s',
        substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4),
        substr($hex, 16, 4), substr($hex, 20, 12)
    );
}

function authNewRefreshToken(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function authIssueAccessToken(string $employeeId, string $sessionId, int $absoluteExpiry): array
{
    global $authConfig;
    $now = time();
    $expiresAt = min($now + $authConfig['access_ttl'], $absoluteExpiry);
    if ($expiresAt <= $now) {
        throw new RuntimeException('Sesi telah kedaluwarsa.');
    }

    $privateKey = file_get_contents($authConfig['private_key_path']);
    if ($privateKey === false) {
        throw new RuntimeException('Private key JWT tidak dapat dibaca.');
    }

    $token = JWT::encode([
        'iss' => $authConfig['issuer'],
        'aud' => $authConfig['audience'],
        'sub' => $employeeId,
        'sid' => $sessionId,
        'iat' => $now,
        'nbf' => $now,
        'exp' => $expiresAt,
        'jti' => bin2hex(random_bytes(16)),
    ], $privateKey, 'RS256');

    return [$token, $expiresAt];
}

function authSetCsrfCookie(string $token, int $expiresAt): void
{
    if (!setcookie('csrf_token', $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => true,
        'httponly' => false,
        'samesite' => 'Strict',
    ])) {
        throw new RuntimeException('Cookie CSRF gagal dipasang.');
    }
}

function authSetTokenCookies(
    string $accessToken,
    string $refreshToken,
    string $csrfToken,
    int $accessExpiresAt,
    int $refreshExpiresAt
): void {
    $accessSet = setcookie('access_token', $accessToken, [
        'expires' => $accessExpiresAt,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $refreshSet = setcookie('refresh_token', $refreshToken, [
        'expires' => $refreshExpiresAt,
        'path' => '/auth',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    authSetCsrfCookie($csrfToken, $refreshExpiresAt);

    if (!$accessSet || !$refreshSet) {
        throw new RuntimeException('Cookie autentikasi gagal dipasang.');
    }
}

function authClearCookies(): void
{
    $expires = time() - 3600;
    foreach ([
        ['access_token', '/'],
        ['refresh_token', '/auth'],
        ['csrf_token', '/'],
    ] as [$name, $path]) {
        setcookie($name, '', [
            'expires' => $expires,
            'path' => $path,
            'secure' => true,
            'httponly' => $name !== 'csrf_token',
            'samesite' => 'Strict',
        ]);
    }
}

function authUtcEpoch(?string $dateTime): ?int
{
    if ($dateTime === null || $dateTime === '') {
        return null;
    }
    $timestamp = strtotime($dateTime . ' UTC');
    return $timestamp === false ? null : $timestamp;
}

function authRevokeSession(PDO $db, string $sessionId, string $reason): void
{
    $session = $db->prepare(
        'UPDATE auth_sessions
         SET revoked_at = COALESCE(revoked_at, UTC_TIMESTAMP(6)),
             revoked_reason = COALESCE(revoked_reason, ?)
         WHERE session_id = ?'
    );
    $session->execute([$reason, $sessionId]);

    $tokens = $db->prepare(
        'UPDATE auth_refresh_tokens
         SET revoked_at = UTC_TIMESTAMP(6)
         WHERE session_id = ? AND revoked_at IS NULL'
    );
    $tokens->execute([$sessionId]);
}

function authDecodeAccessToken(string $token): object
{
    global $authConfig;
    $publicKey = file_get_contents($authConfig['public_key_path']);
    if ($publicKey === false) {
        throw new RuntimeException('Public key JWT tidak dapat dibaca.');
    }
    return JWT::decode($token, new Key($publicKey, 'RS256'));
}

function authRequireEmployee(): array
{
    global $authDb, $authConfig;
    $token = $_COOKIE['access_token'] ?? '';
    if (!is_string($token) || $token === '') {
        authJson(401, ['status' => 'error', 'message' => 'Autentikasi diperlukan.']);
    }

    try {
        $claims = authDecodeAccessToken($token);
    } catch (Throwable) {
        authJson(401, ['status' => 'error', 'message' => 'Autentikasi diperlukan.']);
    }

    if (
        !is_string($claims->iss ?? null) || !hash_equals($authConfig['issuer'], $claims->iss) ||
        !is_string($claims->aud ?? null) || !hash_equals($authConfig['audience'], $claims->aud) ||
        !is_string($claims->sub ?? null) || $claims->sub === '' ||
        !is_string($claims->sid ?? null) || $claims->sid === ''
    ) {
        authJson(401, ['status' => 'error', 'message' => 'Autentikasi diperlukan.']);
    }

    try {
        $stmt = $authDb->prepare(
            'SELECT s.session_id, s.employee_id, s.last_seen_at,
                    s.absolute_expires_at, s.revoked_at,
                    e.employee_name, e.employee_role, e.employee_username, e.is_active
             FROM auth_sessions s
             JOIN employees e ON e.employee_id = s.employee_id
             WHERE s.session_id = ? AND s.employee_id = ?
             LIMIT 1'
        );
        $stmt->execute([$claims->sid, $claims->sub]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            authJson(401, ['status' => 'error', 'message' => 'Autentikasi diperlukan.']);
        }

        $absoluteExpiry = authUtcEpoch((string) $employee['absolute_expires_at']);
        $lastSeen = authUtcEpoch($employee['last_seen_at'] === null ? null : (string) $employee['last_seen_at']);
        $reason = null;
        if ($employee['revoked_at'] !== null) {
            authJson(401, ['status' => 'error', 'message' => 'Autentikasi diperlukan.']);
        } elseif ($absoluteExpiry === null || $absoluteExpiry <= time()) {
            $reason = 'absolute_expired';
        } elseif ($lastSeen === null || $lastSeen < time() - $authConfig['idle_ttl']) {
            $reason = 'idle_expired';
        } elseif ((int) $employee['is_active'] !== 1) {
            $reason = 'employee_inactive';
        }

        if ($reason !== null) {
            authRevokeSession($authDb, (string) $employee['session_id'], $reason);
            authJson(401, ['status' => 'error', 'message' => 'Autentikasi diperlukan.']);
        }

        $touch = $authDb->prepare(
            'UPDATE auth_sessions SET last_seen_at = UTC_TIMESTAMP(6) WHERE session_id = ?'
        );
        $touch->execute([$employee['session_id']]);

        return [
            'employee_id' => (string) $employee['employee_id'],
            'employee_name' => (string) $employee['employee_name'],
            'employee_role' => (string) $employee['employee_role'],
            'employee_username' => (string) $employee['employee_username'],
        ];
    } catch (Throwable $error) {
        error_log('Autentikasi gagal diproses: ' . $error->getMessage());
        authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
    }
}
