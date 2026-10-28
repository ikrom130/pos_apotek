<?php

declare(strict_types=1);

/**
 * Konfigurasi autentikasi JWT.
 *
 * File ini mengasumsikan config/database.php sudah memuat
 * variabel .env ke environment PHP.
 */

$privateKeyPath = $_ENV['JWT_PRIVATE_KEY_PATH']
    ?? getenv('JWT_PRIVATE_KEY_PATH')
    ?: '';

$publicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH']
    ?? getenv('JWT_PUBLIC_KEY_PATH')
    ?: '';

$issuer = $_ENV['JWT_ISSUER']
    ?? getenv('JWT_ISSUER')
    ?: '';

$audience = $_ENV['JWT_AUDIENCE']
    ?? getenv('JWT_AUDIENCE')
    ?: '';

$accessTtl = (int) (
    $_ENV['JWT_ACCESS_TTL']
    ?? getenv('JWT_ACCESS_TTL')
    ?: 300
);

if (!extension_loaded('openssl')) {
    throw new RuntimeException('Ekstensi OpenSSL belum aktif.');
}

if ($privateKeyPath === '' || !is_readable($privateKeyPath)) {
    throw new RuntimeException('Private key JWT tidak tersedia.');
}

if ($publicKeyPath === '' || !is_readable($publicKeyPath)) {
    throw new RuntimeException('Public key JWT tidak tersedia.');
}

if ($issuer === '' || $audience === '') {
    throw new RuntimeException('JWT issuer dan audience wajib diisi.');
}

if ($accessTtl < 1 || $accessTtl > 900) {
    throw new RuntimeException(
        'JWT access TTL harus antara 1 dan 900 detik.'
    );
}

return [
    'algorithm' => 'RS256',
    'private_key_path' => $privateKeyPath,
    'public_key_path' => $publicKeyPath,
    'issuer' => $issuer,
    'audience' => $audience,
    'access_ttl' => $accessTtl,
];
