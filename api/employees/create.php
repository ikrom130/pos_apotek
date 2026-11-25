<?php

declare(strict_types=1);

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('EMPLOYEE_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleCreateEmployee(PDO $employeeDb, array $apiActor, ?string $rawBody = null): never
{
    authRequireCsrf();
    apiRequireMethod('POST');
    apiRequireRole($apiActor, ['admin']);
    $input = $rawBody !== null ? json_decode($rawBody, true) : authReadJson();
    if (!is_array($input)) {
        authJson(400, ['status' => 'error', 'message' => 'Format JSON tidak valid.']);
    }

    $profile = employeeReadProfile($input);
    $password = is_string($input['employee_password'] ?? null) ? $input['employee_password'] : '';
    if (strlen($password) < 12 || strlen($password) > 72) {
        authJson(422, ['status' => 'error', 'message' => 'Sandi awal harus 12–72 byte.']);
    }

    $createId = null;
    try {
        $employeeDb->beginTransaction();
        $id = nextSequentialId($employeeDb, 'employee', 'EMP-', 5, 15);

        $stmt = $employeeDb->prepare(
            'INSERT INTO employees
                (employee_id, employee_name, employee_role, employee_phone,
                 employee_username, employee_password, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $id,
            $profile['employee_name'],
            $profile['employee_role'],
            $profile['employee_phone'],
            $profile['employee_username'],
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $employeeDb->commit();
        $createId = $id;
    } catch (Throwable $error) {
        if ($employeeDb->inTransaction()) {
            $employeeDb->rollBack();
        }
        employeeHandleDatabaseError($error, 'Pembuatan pegawai gagal');
    }
    authJson(201, ['status' => 'success', 'message' => 'Pegawai dibuat.', 'employee_id' => $id]);
}

if (!defined('APP_TESTING')) {
    handleCreateEmployee($employeeDb, $apiActor);
}