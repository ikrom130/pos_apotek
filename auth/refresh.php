<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
authRequireMethod('POST');
authRequireCsrf();

$rawToken = $_COOKIE['refresh_token'] ?? '';
if (!is_string($rawToken) || $rawToken === '') {
    authClearCookies();
    authJson(401, ['status' => 'error', 'message' => 'Sesi tidak berlaku. Silakan login kembali.']);
}

try {
    $authDb->beginTransaction();
    $stmt = $authDb->prepare(
        'SELECT rt.refresh_token_id, rt.session_id, rt.token_hash, rt.expires_at,
                rt.used_at AS token_used_at, rt.revoked_at AS token_revoked_at,
                s.employee_id, s.last_seen_at, s.absolute_expires_at,
                s.revoked_at AS session_revoked_at,
                e.employee_name, e.employee_role, e.employee_username, e.is_active
         FROM auth_refresh_tokens rt
         JOIN auth_sessions s ON s.session_id = rt.session_id
         JOIN employees e ON e.employee_id = s.employee_id
         WHERE rt.token_hash = ?
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([hash('sha256', $rawToken)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $authDb->rollBack();
        authClearCookies();
        authJson(401, ['status' => 'error', 'message' => 'Sesi tidak berlaku. Silakan login kembali.']);
    }

    // Token lama yang sudah dipakai dan belum dicabut berarti terulang; cabut seluruh sesi.
    if (
        $row['token_used_at'] !== null &&
        $row['token_revoked_at'] === null &&
        $row['session_revoked_at'] === null
    ) {
        authRevokeSession($authDb, (string) $row['session_id'], 'refresh_reuse');
        $authDb->commit();
        authClearCookies();
        authJson(401, ['status' => 'error', 'message' => 'Sesi tidak berlaku. Silakan login kembali.']);
    }

    if ($row['token_revoked_at'] !== null || $row['session_revoked_at'] !== null) {
        $authDb->rollBack();
        authClearCookies();
        authJson(401, ['status' => 'error', 'message' => 'Sesi tidak berlaku. Silakan login kembali.']);
    }

    $now = time();
    $absoluteExpiry = authUtcEpoch((string) $row['absolute_expires_at']);
    $refreshExpiry = authUtcEpoch((string) $row['expires_at']);
    $lastSeen = authUtcEpoch($row['last_seen_at'] === null ? null : (string) $row['last_seen_at']);

    $reason = null;
    if ((int) $row['is_active'] !== 1) {
        $reason = 'employee_inactive';
    } elseif ($absoluteExpiry === null || $absoluteExpiry <= $now) {
        $reason = 'absolute_expired';
    } elseif ($lastSeen === null || $lastSeen < $now - $authConfig['idle_ttl']) {
        $reason = 'idle_expired';
    } elseif ($refreshExpiry === null || $refreshExpiry <= $now) {
        $reason = 'refresh_expired';
    }

    if ($reason !== null) {
        authRevokeSession($authDb, (string) $row['session_id'], $reason);
        $authDb->commit();
        authClearCookies();
        authJson(401, ['status' => 'error', 'message' => 'Sesi tidak berlaku. Silakan login kembali.']);
    }

    $newRefreshToken = authNewRefreshToken();
    $newRefreshExpiry = min($now + $authConfig['refresh_ttl'], $absoluteExpiry);
    $csrfToken = authNewRefreshToken();

    $insert = $authDb->prepare(
        'INSERT INTO auth_refresh_tokens (session_id, token_hash, issued_at, expires_at)
         VALUES (?, ?, UTC_TIMESTAMP(6), ?)'
    );
    $insert->execute([
        $row['session_id'],
        hash('sha256', $newRefreshToken),
        gmdate('Y-m-d H:i:s', $newRefreshExpiry),
    ]);
    $newTokenId = (int) $authDb->lastInsertId();

    $replace = $authDb->prepare(
        'UPDATE auth_refresh_tokens
         SET used_at = UTC_TIMESTAMP(6), replaced_by_token_id = ?
         WHERE refresh_token_id = ? AND used_at IS NULL AND revoked_at IS NULL'
    );
    $replace->execute([$newTokenId, $row['refresh_token_id']]);
    if ($replace->rowCount() !== 1) {
        throw new RuntimeException('Refresh token gagal dirotasi.');
    }

    $touch = $authDb->prepare(
        'UPDATE auth_sessions SET last_seen_at = UTC_TIMESTAMP(6) WHERE session_id = ?'
    );
    $touch->execute([$row['session_id']]);

    [$accessToken, $accessExpiry] = authIssueAccessToken(
        (string) $row['employee_id'],
        (string) $row['session_id'],
        $absoluteExpiry
    );

    $authDb->commit();
    authSetTokenCookies($accessToken, $newRefreshToken, $csrfToken, $accessExpiry, $newRefreshExpiry);
    authJson(200, [
        'status' => 'success',
        'csrf_token' => $csrfToken,
        'access_expires_at' => gmdate(DATE_ATOM, $accessExpiry),
    ]);
} catch (Throwable $error) {
    if ($authDb->inTransaction()) {
        $authDb->rollBack();
    }
    error_log('Refresh autentikasi gagal diproses: ' . $error->getMessage());
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
