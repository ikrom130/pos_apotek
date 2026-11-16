<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
employeeRequireMethod('GET');
authRequireCsrf();
$id = employeeRequireId();

try {
    $stmt = $employee
} catch (\Throwable $th) {
    //throw $th;
}