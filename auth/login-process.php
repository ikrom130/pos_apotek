<?php

declare(strict_types=1);

$privateKey = file_get_contents(__DIR__ . '/../certs/private_key.pem');
$payload = [
    'iss' => 'https://pos_apotel.test',
    //'sub' => $userId,
    'iat' => time(),
    'exp' => time() + (15 * 60) // 15 Menit
];
//$accessToken = JWT::encode($payload, $privateKey, 'RS256');

// 2. Generate Refresh Token & Simpan ke DB (Untuk Kill-Switch)
$refreshToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 Hari
// QUERY: INSERT INTO user_refresh_tokens (user_id, token, expires_at) VALUES ($userId, '$refreshToken', '$expiresAt')

// 3. Kirim ke Browser sebagai HttpOnly + Secure Cookie
$cookieOptions = [
    'expires' => time() + (7 * 24 * 60 * 60),
    'path' => '/',
    'secure' => true,     // Wajib HTTPS di production
    'httponly' => true,   // Proteksi dari serangan XSS (JavaScript tidak bisa baca)
    'samesite' => 'Strict' // Proteksi dari serangan CSRF
];

// Set Cookie Access Token (Short-lived)
//setcookie('access_token', $accessToken, array_merge($cookieOptions, ['expires' => time() + (15 * 60)]));

// Set Cookie Refresh Token (Long-lived)
setcookie('refresh_token', $refreshToken, $cookieOptions);

// Redirect ke dashboard
header("Location: ../dashboard.php");
exit;