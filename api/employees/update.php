<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
employeeRequireMethod('PUT');
authRequireCsrf();
$id = employeeRequireId();
$profile = employeeReadProfile(authReadJson());

try {
    $employeeDb->beginTransaction();
    $check = $employeeDb->prepare('SELECT employee_role FROM employees WHERE employee_id = ? FOR UPDATE');
    $check->execute([$id]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        $employeeDb->rollBack();
        authJson(404, ['status' => 'error', 'message' => 'Pegawai tidak ditemukan.']);
    }
    if ($id === $employeeActor['employee_id'] && $profile['employee_role'] !== 'admin') {
        $employeeDb->rollBack();
        authJson(422, ['status' => 'error', 'message' => 'Anda tidak dapat menurunkan role admin sendiri.']);
    }

    $stmt = $employeeDb->prepare(
        'UPDATE employees
         SET employee_name = ?, employee_role = ?, employee_phone = ?, employee_username = ?
         WHERE employee_id = ?'
    );
    $stmt->execute([
        $profile['employee_name'], $profile['employee_role'],
        $profile['employee_phone'], $profile['employee_username'], $id,
    ]);
    $employeeDb->commit();
    authJson(200, ['status' => 'success', 'message' => 'Data pegawai diperbarui.']);
} catch (Throwable $error) {
    if ($employeeDb->inTransaction()) {
        $employeeDb->rollBack();
    }
    employeeHandleDatabaseError($error, 'Pembaruan pegawai gagal');
}
