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

// 1. CEK RATE LIMIT SEBELUM PROSES
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
    $stmt = $employeeDb->prepare('SELECT employee_password FROM employees WHERE employee_id = ? AND is_active = 1');
    $stmt->execute([$id]);
    $currentHash = $stmt->fetchColumn();
    
    if (!is_string($currentHash) || !password_verify($currentPassword, $currentHash)) {
        employeeRecordPasswordChangeFailure($employeeDb, $usernameIpHash, $ipHash);
        
        authJson(401, ['status' => 'error', 'message' => 'Konfirmasi sandi saat ini salah.']);
    }

    $employeeDb->beginTransaction();
    
    $update = $employeeDb->prepare('UPDATE employees SET employee_password = ? WHERE employee_id = ?');
    $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    
    $stmtSessions = $employeeDb->prepare('SELECT session_id FROM auth_sessions WHERE employee_id = ? AND revoked_at IS NULL');
    $stmtSessions->execute([$id]);
    while ($activeSessionId = $stmtSessions->fetchColumn()) {
        authRevokeSession($employeeDb, (string)$activeSessionId, 'password_changed');
    }

    $employeeDb->commit();

    employeeClearPasswordChangeFailures($employeeDb, $usernameIpHash, $ipHash);

    authJson(200, ['status' => 'success', 'message' => 'Sandi berhasil diperbarui.']);
} catch (Throwable $error) {
    if ($employeeDb->inTransaction()) {
        $employeeDb->rollBack();
    }
    employeeHandleDatabaseError($error, 'Perubahan sandi gagal');
}