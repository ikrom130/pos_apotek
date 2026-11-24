<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function handleMe(PDO $authDb, array $authConfig): never
{
    $GLOBALS['authDb'] = $authDb;
    $GLOBALS['authConfig'] = $authConfig;

    authRequireMethod('GET');
    $employee = authRequireEmployee();
    authJson(200, ['status' => 'success', 'employee' => $employee]);
}

if (!defined('APP_TESTING')) {
    handleMe($authDb, $authConfig);
}