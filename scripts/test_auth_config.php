<?php


require dirname(__DIR__) . '/config/database.php';

new Database();

echo 'JWT tersedia di $_ENV: ';
var_dump(isset($_ENV['JWT_PRIVATE_KEY_PATH']));

echo 'JWT tersedia di getenv(): ';
var_dump(getenv('JWT_PRIVATE_KEY_PATH') !== false);

echo "Memuat konfigurasi autentikasi...\n";

$config = require dirname(__DIR__) . '/config/auth.php';

echo "Algoritma: " . $config['algorithm'] . "\n";
echo "Issuer: " . $config['issuer'] . "\n";
echo "Audience: " . $config['audience'] . "\n";
echo "Access TTL: " . $config['access_ttl'] . "\n";
echo "Konfigurasi autentikasi berhasil dimuat.\n";
