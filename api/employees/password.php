<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
employeeRequireMethod('POST');
authRequireCsrf();
$id = employeeRequireId();
$input = authReadJson();
$currentPassword = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
$newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';

if (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
    authJson(422, ['status' => 'error', 'message' => 'Sandi baru harus 12–72 byte.']);
}

try {
    $admin = $employeeDb->prepare('SELECT employee_password FROM employees WHERE employee_id = ? AND is_active = 1');
    $admin->execute([$employeeActor['employee_id']]);
    $adminHash = $admin->fetchColumn();
    if (!is_string($adminHash) || !password_verify($currentPassword, $adminHash)) {
        authJson(401, ['status' => 'error', 'message' => 'Konfirmasi sandi admin salah.']);
    }

    $employeeDb->beginTransaction();
    $target = $employeeDb->prepare('SELECT employee_id FROM employees WHERE employee_id = ? FOR UPDATE');
    $target->execute([$id]);
    if (!$target->fetchColumn()) {
        $employeeDb->rollBack();
        authJson(404, ['status' => 'error', 'message' => 'Pegawai tidak ditemukan.']);
    }

    $update = $employeeDb->prepare('UPDATE employees SET employee_password = ? WHERE employee_id = ?');
    $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    $revoke = $employeeDb->prepare(
        "UPDATE auth_sessions
         SET revoked_at = UTC_TIMESTAMP(6), revoked_reason = 'password_changed'
         WHERE employee_id = ? AND revoked_at IS NULL"
    );
    $revoke->execute([$id]);
    $employeeDb->commit();

    authJson(200, ['status' => 'success', 'message' => 'Sandi diperbarui dan seluruh sesi pegawai dicabut.']);
} catch (Throwable $error) {
    if ($employeeDb->inTransaction()) {
        $employeeDb->rollBack();
    }
    employeeHandleDatabaseError($error, 'Perubahan sandi pegawai gagal');
}