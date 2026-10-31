<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
authRequireMethod('POST');
authRequireCsrf();

try {
    $sessionId = null;
    $refreshToken = $_COOKIE['refresh_token'] ?? '';

    $authDb->beginTransaction();
    if (is_string($refreshToken) && $refreshToken !== '') {
        $stmt = $authDb->prepare(
            'SELECT session_id FROM auth_refresh_tokens WHERE token_hash = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([hash('sha256', $refreshToken)]);
        $found = $stmt->fetchColumn();
        if (is_string($found)) {
            $sessionId = $found;
        }
    }

    // Bila refresh cookie hilang, sesi masih dapat dicabut dari access JWT yang belum kedaluwarsa.
    if ($sessionId === null) {
        $accessToken = $_COOKIE['access_token'] ?? '';
        if (is_string($accessToken) && $accessToken !== '') {
            try {
                $claims = authDecodeAccessToken($accessToken);
                if (is_string($claims->sid ?? null)) {
                    $sessionId = $claims->sid;
                }
            } catch (Throwable) {
                // Logout tetap idempotent: bersihkan cookie walau token sudah kedaluwarsa.
            }
        }
    }

    if ($sessionId !== null) {
        authRevokeSession($authDb, $sessionId, 'logout');
    }
    $authDb->commit();
    authClearCookies();
    authJson(200, ['status' => 'success', 'message' => 'Logout berhasil.']);
} catch (Throwable $error) {
    if ($authDb->inTransaction()) {
        $authDb->rollBack();
    }
    error_log('Logout gagal diproses: ' . $error->getMessage());
    authClearCookies();
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
