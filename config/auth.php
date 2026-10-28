<?php

declare(strict_types=1);

$env = static function (string $name, ?string $default = null): ?string {
    $value = $_ENV[$name] ?? getenv($name);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
};

$integer = static function (string $name, int $default) use ($env): int {
    $value = $env($name, (string) $default);
    if ($value === null || !ctype_digit($value)) {
        throw new RuntimeException("Konfigurasi {$name} harus berupa bilangan bulat.");
    }
    return (int) $value;
};

$privateKeyPath = $env('JWT_PRIVATE_KEY_PATH');
$publicKeyPath = $env('JWT_PUBLIC_KEY_PATH');
$issuer = $env('JWT_ISSUER');
$audience = $env('JWT_AUDIENCE');
$appOrigin = rtrim((string) $env('APP_ORIGIN'), '/');
$rateLimitKey = $env('AUTH_RATE_LIMIT_KEY');
$dummyPasswordHash = $env('AUTH_DUMMY_PASSWORD_HASH');
$accessTtl = $integer('JWT_ACCESS_TTL', 300);
$refreshTtl = $integer('JWT_REFRESH_TTL', 604800);
$sessionTtl = $integer('AUTH_SESSION_TTL', 2592000);
$idleTtl = $integer('AUTH_IDLE_TTL', 43200);

if (!extension_loaded('openssl')) {
    throw new RuntimeException('Ekstensi OpenSSL belum aktif.');
}
if ($privateKeyPath === null || !is_readable($privateKeyPath)) {
    throw new RuntimeException('Private key JWT tidak tersedia.');
}
if ($publicKeyPath === null || !is_readable($publicKeyPath)) {
    throw new RuntimeException('Public key JWT tidak tersedia.');
}
if ($issuer === null || $issuer === '' || $audience === null || $audience === '') {
    throw new RuntimeException('JWT issuer dan audience wajib diisi.');
}
$originParts = parse_url($appOrigin);
if (
    $originParts === false ||
    ($originParts['scheme'] ?? '') !== 'https' ||
    !isset($originParts['host']) ||
    isset($originParts['path']) ||
    isset($originParts['query']) ||
    isset($originParts['fragment'])
) {
    throw new RuntimeException('APP_ORIGIN harus origin HTTPS tanpa path.');
}
if ($accessTtl < 60 || $accessTtl > 900) {
    throw new RuntimeException('JWT_ACCESS_TTL harus 60–900 detik.');
}
if ($refreshTtl < 300 || $refreshTtl > 604800) {
    throw new RuntimeException('JWT_REFRESH_TTL harus 300–604800 detik.');
}
if ($sessionTtl < $refreshTtl || $sessionTtl > 2592000) {
    throw new RuntimeException('AUTH_SESSION_TTL harus setidaknya selama refresh token dan maksimal 30 hari.');
}
if ($idleTtl < 300 || $idleTtl > $sessionTtl) {
    throw new RuntimeException('AUTH_IDLE_TTL harus antara 300 detik dan durasi sesi maksimum.');
}
if ($rateLimitKey === null || preg_match('/\A[a-f0-9]{64}\z/i', $rateLimitKey) !== 1) {
    throw new RuntimeException('AUTH_RATE_LIMIT_KEY harus berisi 64 karakter heksadesimal acak.');
}
$dummyHashInfo = $dummyPasswordHash === null ? ['algoName' => 'unknown'] : password_get_info($dummyPasswordHash);
if ($dummyPasswordHash === null || ($dummyHashInfo['algoName'] ?? 'unknown') === 'unknown') {
    throw new RuntimeException('AUTH_DUMMY_PASSWORD_HASH harus berisi hash password PHP yang valid.');
}

return [
    'algorithm' => 'RS256',
    'private_key_path' => $privateKeyPath,
    'public_key_path' => $publicKeyPath,
    'issuer' => $issuer,
    'audience' => $audience,
    'app_origin' => $appOrigin,
    'access_ttl' => $accessTtl,
    'refresh_ttl' => $refreshTtl,
    'session_ttl' => $sessionTtl,
    'idle_ttl' => $idleTtl,
    'rate_limit_key' => $rateLimitKey,
    'dummy_password_hash' => $dummyPasswordHash,
];
