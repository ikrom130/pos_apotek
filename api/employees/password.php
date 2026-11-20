<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
employeeRequireMethod('POST');
authRequireCsrf();

$id = $employeeActor['employee_id'];
$username = $employeeActor['employee_username'];
$ip = authRequestIp();
[$usernameIpHash, $ipHash] = authRateHashes($username, $ip);

if (employeePasswordChangeIsLimited($employeeDb, $usernameIpHash, $ipHash)) {
    authJson(429, ['status' => 'error', 'message' => 'Terlalu banyak percobaan gagal. Silakan coba lagi setelah 15 menit.']);
}

$input = authReadJson();
$currentPassword = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
$newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';

if (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
    authJson(422, ['status' => 'error', 'message' => 'Sandi baru harus 12–72 byte.']);
}

try {
    $employeeDb->beginTransaction();
    $accountStmt = $employeeDb->prepare(
        'SELECT employee_password
         FROM employees
         WHERE employee_id = ? AND is_active = 1
         FOR UPDATE'
    );
    $accountStmt->execute([$id]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$account ||
        !password_verify($currentPassword, (string) $account['employee_password'])
    ) {
        $employeeDb->rollBack();
        employeeRecordPasswordChangeFailure($employeeDb, $usernameIpHash, $ipHash);
        authJson(401, ['status' => 'error', 'message' => 'Konfirmasi sandi saat ini salah.']);
    }

    if (password_verify($newPassword, (string) $account['employee_password'])) {
        $employeeDb->rollBack();
        authJson(422, ['status' => 'error', 'message' => 'Sandi baru harus berbeda dari sandi saat ini.']);
    }

    // check reset status
    $resetStmt = $employeeDb->prepare(
        'SELECT employee_id FROM auth_password_resets WHERE employee_id = ? FOR UPDATE'
    );
    $resetStmt->execute([$id]);
    $isAdminReset = $resetStmt->fetchColumn() !== false;

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $employeeDb->prepare(
        'UPDATE employees
         SET employee_password = ?, must_change_password = 0
         WHERE employee_id = ?'
    );
    $update->execute([$newHash, $id]);

    if ($isAdminReset) {
        $resetAudit = $employeeDb->prepare(
            'INSERT INTO auth_password_reset_audit
                (actor_employee_id, target_employee_id, action, ip_hash, occurred_at)
             VALUES (NULL, ?, ?, ?, UTC_TIMESTAMP(6))'
        );
        $resetAudit->execute([$id, 'temporary_password_changed', $ipHash]);

        $deleteReset = $employeeDb->prepare(
            'DELETE FROM auth_password_resets WHERE employee_id = ?'
        );
        $deleteReset->execute([$id]);
    }

    $sessionStmt = $employeeDb->prepare(
        'SELECT session_id FROM auth_sessions
         WHERE employee_id = ? AND revoked_at IS NULL
         FOR UPDATE'
    );
    $sessionStmt->execute([$id]);
    while ($activeSessionId = $sessionStmt->fetchColumn()) {
        authRevokeSession($employeeDb, (string) $activeSessionId, 'password_changed');
    }

    $employeeDb->commit();
    employeeClearPasswordChangeFailures($employeeDb, $usernameIpHash, $ipHash);
    authClearCookies();

    authJson(200, [
        'status' => 'success',
        'message' => 'Sandi berhasil diperbarui. Silakan login kembali.',
        'requires_login' => true,
    ]);
} catch (Throwable $error) {
    if ($employeeDb->inTransaction()) {
        $employeeDb->rollBack();
    }
    employeeHandleDatabaseError($error, 'Perubahan sandi gagal');
}