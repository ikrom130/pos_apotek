<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
employeeRequireMethod('GET');
employeeRequireAdmin();
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
    authJson(200, ['status' => 'success', 'data' => $employee]);
} catch (Throwable $error) {
    employeeHandleDatabaseError($error, 'Detail pegawai gagal dimuat');
}
