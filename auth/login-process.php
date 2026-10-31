<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

authRequireMethod('POST');
authRequireCsrf();
$input = authReadJson();
$username = trim(is_string($input['username'] ?? null) ? $input['username'] : '');
$password = is_string($input['password'] ?? null) ? $input['password'] : '';

if (
    preg_match('/\A[a-zA-Z0-9._-]{3,50}\z/D', $username) !== 1 ||
    $password === '' || strlen($password) > 72
) {
    authJson(422, ['status' => 'error', 'message' => 'Username atau sandi tidak valid.']);
}

$ip = authRequestIp();
[$usernameIpHash, $ipHash] = authRateHashes($username, $ip);

try {
    if (authLoginIsLimited($authDb, $usernameIpHash, $ipHash)) {
        header('Retry-After: 900');
        authJson(429, ['status' => 'error', 'message' => 'Terlalu banyak percobaan. Coba lagi beberapa menit.']);
    }

    $stmt = $authDb->prepare(
        'SELECT employee_id, employee_name, employee_role, employee_username,
                employee_password, is_active
         FROM employees
         WHERE employee_username = ?
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    $storedHash = $employee ? (string) $employee['employee_password'] : '';
    $hashInfo = password_get_info($storedHash);
    if (($hashInfo['algoName'] ?? 'unknown') === 'unknown') {
        $storedHash = $authConfig['dummy_password_hash'];
    }
    $passwordValid = password_verify($password, $storedHash);
    $loginValid = $employee !== false && (int) $employee['is_active'] === 1 && $passwordValid;

    if (!$loginValid) {
        authRecordLoginFailure($authDb, $usernameIpHash, $ipHash);
        authJson(401, ['status' => 'error', 'message' => 'Username atau sandi salah.']);
    }

    if (password_needs_rehash((string) $employee['employee_password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $authDb->prepare(
            'UPDATE employees SET employee_password = ? WHERE employee_id = ?'
        );
        $rehash->execute([$newHash, $employee['employee_id']]);
    }

    $now = time();
    $sessionId = authUuidV4();
    $absoluteExpiry = $now + $authConfig['session_ttl'];
    $refreshExpiry = min($now + $authConfig['refresh_ttl'], $absoluteExpiry);
    $rawRefreshToken = authNewRefreshToken();
    $csrfToken = authNewRefreshToken();
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $storedIp = filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;

    $authDb->beginTransaction();

    // Login ulang pada browser yang sama mencabut sesi lama bila refresh cookie-nya masih ada.
    $oldRefresh = $_COOKIE['refresh_token'] ?? '';
    if (is_string($oldRefresh) && $oldRefresh !== '') {
        $oldStmt = $authDb->prepare(
            'SELECT session_id FROM auth_refresh_tokens WHERE token_hash = ? LIMIT 1 FOR UPDATE'
        );
        $oldStmt->execute([hash('sha256', $oldRefresh)]);
        $oldSessionId = $oldStmt->fetchColumn();
        if (is_string($oldSessionId)) {
            authRevokeSession($authDb, $oldSessionId, 'relogin');
        }
    }

    $sessionInsert = $authDb->prepare(
        'INSERT INTO auth_sessions
            (session_id, employee_id, created_at, last_seen_at, absolute_expires_at, user_agent, ip_address)
         VALUES (?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), ?, ?, ?)'
    );
    $sessionInsert->execute([
        $sessionId,
        $employee['employee_id'],
        gmdate('Y-m-d H:i:s', $absoluteExpiry),
        $userAgent,
        $storedIp,
    ]);

    $refreshInsert = $authDb->prepare(
        'INSERT INTO auth_refresh_tokens (session_id, token_hash, expires_at)
         VALUES (?, ?, ?)'
    );
    $refreshInsert->execute([
        $sessionId,
        hash('sha256', $rawRefreshToken),
        gmdate('Y-m-d H:i:s', $refreshExpiry),
    ]);

    [$accessToken, $accessExpiry] = authIssueAccessToken(
        (string) $employee['employee_id'],
        $sessionId,
        $absoluteExpiry
    );

    $authDb->commit();
    authSetTokenCookies($accessToken, $rawRefreshToken, $csrfToken, $accessExpiry, $refreshExpiry);
    authJson(200, [
        'status' => 'success',
        'csrf_token' => $csrfToken,
        'employee' => [
            'employee_id' => (string) $employee['employee_id'],
            'employee_name' => (string) $employee['employee_name'],
            'employee_role' => (string) $employee['employee_role'],
            'employee_username' => (string) $employee['employee_username'],
        ],
        'access_expires_at' => gmdate(DATE_ATOM, $accessExpiry),
    ]);
} catch (Throwable $error) {
    if ($authDb->inTransaction()) {
        $authDb->rollBack();
    }
    error_log('Login gagal diproses: ' . $error->getMessage());
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
