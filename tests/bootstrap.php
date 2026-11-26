<?php

declare(strict_types=1);

define('APP_TESTING', true);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$privPath = __DIR__ . '/fixtures/jwt_test_private.pem';
$pubPath  = __DIR__ . '/fixtures/jwt_test_public.pem';

if (!file_exists($privPath) || !file_exists($pubPath)) {
    throw new RuntimeException(
        "File test RSA key tidak ditemukan. Pastikan {$privPath} dan {$pubPath} sudah ada."
    );
}

$GLOBALS['authConfig'] = [
    'app_origin' => 'https://pos_apotek.test',
    'rate_limit_key' => 'test-secret-hmac-key-32-chars-min',
    'private_key_path' => $privPath,
    'public_key_path' => $pubPath,
    'issuer' => 'https://pos_apotek.test',
    'audience' => 'https://pos_apotek.test',
    'access_ttl' => 900,
    'refresh_ttl' => 86400,
    'session_ttl' => 604800,
    'idle_ttl' => 1800,
    'dummy_password_hash' => password_hash('dummy_password_for_timing', PASSWORD_DEFAULT),
];

$GLOBALS['testDbConfig'] = [
    'host' => '127.0.0.1',
    'dbname' => 'test_pos_apotek_target',
    'charset' => 'utf8mb4',
    'user' => 'root',
    'pass' => 'plplpl123',
];

require_once dirname(__DIR__) . '/auth/bootstrap.php';
require_once dirname(__DIR__) . '/auth/login-process.php';
require_once dirname(__DIR__) . '/auth/refresh.php';
require_once dirname(__DIR__) . '/auth/logout.php';
require_once dirname(__DIR__) . '/auth/me.php';

if (!defined('API_INTERNAL_INCLUDE')) {
    define('API_INTERNAL_INCLUDE', true);
}
require_once dirname(__DIR__) . '/api/_shared/id-sequence.php';

if (!defined('EMPLOYEE_API_ENTRY')) {
    define('EMPLOYEE_API_ENTRY', true);
}
if (!defined('API_BOOTSTRAP_ENTRY')) {
    define('API_BOOTSTRAP_ENTRY', true);
}if (!defined('SUPPLIER_API_ENTRY')) {
    define('SUPPLIER_API_ENTRY', true);
}

require_once dirname(__DIR__) . '/api/employees/create.php';
require_once dirname(__DIR__) . '/api/employees/list.php';
require_once dirname(__DIR__) . '/api/employees/detail.php';
require_once dirname(__DIR__) . '/api/employees/update.php';
require_once dirname(__DIR__) . '/api/employees/status.php';
require_once dirname(__DIR__) . '/api/employees/password.php';
require_once dirname(__DIR__) . '/api/suppliers/create.php';