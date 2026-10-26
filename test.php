
<?php

require_once __DIR__ . '/config/database.php';

new Database();

$variables = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'];

foreach ($variables as $name) {
    echo $name . ': '
        . (getenv($name) !== false ? 'TERBACA' : 'TIDAK TERBACA')
        . PHP_EOL;
}

$password = getenv('DB_PASS');

echo 'DB_PASS: '
    . ($password !== false && $password !== ''
        ? 'TERSEDIA'
        : 'KOSONG ATAU TIDAK TERBACA')
    . PHP_EOL;
