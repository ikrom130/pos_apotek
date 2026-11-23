<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
authRequireCsrf();
apiRequireMethod('PATCH');
apiRequireRole($apiActor, ['admin']);
$id = employeeRequireId();
$input = authReadJson();
$active = $input['is_active'] ?? null;

if (!in_array($active, [true, false, 0, 1], true)) {
    authJson(422, ['status' => 'error', 'message' => 'is_active harus boolean.']);
}
$active = (int) $active;
f ($id === $apiActor['employee_id'] && $active === 0) {
    authJson(422, ['status' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
}

try {
    $employeeDb->beginTransaction();
    $check = $employeeDb->prepare('SELECT employee_id FROM employees WHERE employee_id = ? FOR UPDATE');
    $check->execute([$id]);
    if (!$check->fetchColumn()) {
        $employeeDb->rollBack();
        authJson(404, ['status' => 'error', 'message' => 'Pegawai tidak ditemukan.']);
    }

    $stmt = $employeeDb->prepare('UPDATE employees SET is_active = ? WHERE employee_id = ?');
    $stmt->execute([$active, $id]);
    if ($active === 0) {
        $revoke = $employeeDb->prepare(
            "UPDATE auth_sessions
             SET revoked_at = UTC_TIMESTAMP(6), revoked_reason = 'employee_deactivated'
             WHERE employee_id = ? AND revoked_at IS NULL"
        );
        $revoke->execute([$id]);
    }
    $employeeDb->commit();
    authJson(200, ['status' => 'success', 'message' => $active ? 'Pegawai diaktifkan.' : 'Pegawai dinonaktifkan.']);
} catch (Throwable $error) {
    if ($employeeDb->inTransaction()) {
        $employeeDb->rollBack();
    }
    employeeHandleDatabaseError($error, 'Perubahan status pegawai gagal');
}
