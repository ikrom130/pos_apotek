<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
authRequireMethod('GET');

$token = authNewRefreshToken();
$expiresAt = time() + $authConfig['idle_ttl'];
try {
    authSetCsrfCookie($token, $expiresAt);
    authJson(200, ['csrf_token' => $token]);
} catch (Throwable $error) {
    error_log('Token CSRF gagal dibuat: ' . $error->getMessage());
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
