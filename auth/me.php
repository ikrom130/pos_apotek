<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
authRequireMethod('GET');
$employee = authRequireEmployee();
authJson(200, ['status' => 'success', 'employee' => $employee]);
