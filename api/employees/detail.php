<?php

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('EMPLOYEE_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleDetailEmployees(PDO $employeeDb, array $apiActor): never
{
    apiRequireMethod('GET');
    apiRequireRole($apiActor, ['admin']);
    $id = employeeRequireId();

    try {
        $stmt = $employeeDb->prepare(
            'SELECT employee_id, employee_name, employee_role, employee_phone,
                    employee_username, is_active, created_at, updated_at
             FROM employees WHERE employee_id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            authJson(404, ['status' => 'error', 'message' => 'Pegawai tidak ditemukan.']);
        }
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        employeeHandleDatabaseError($error, 'Detail pegawai gagal dimuat');
    }

    authJson(200, ['status' => 'success', 'data' => $employee]);
}

if (!defined('APP_TESTING')) {
    handleDetailEmployees($employeeDb, $apiActor);
}