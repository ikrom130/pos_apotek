<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
employeeRequireMethod('POST');
authRequireCsrf();
employeeRequireAdmin();

$targetId = employeeRequireId();
$actorId = $employeeActor['employee_id'];
if (hash_equals($actorId, $targetId)) {
    authJson(422, ['status' => 'error', 'message' => 'Admin tidak dapat mereset sandi akunnya sendiri. Minta admin lain melakukan reset.',
    ]);
}

$ip = authRequestIp();
$ipHash = hash_hmac('sha256', $ip, $authConfig['rate_limit_key']);

// 32 byte acak, dikirim satu kali saja; yang tersimpan hanya hash PHP.
$temporaryPassword = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
$temporaryHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

try {
    $employeeDb->beginTransaction();
    $lock = $employeeDb->prepare(
        'SELECT is_active FROM employees WHERE employee_id = ? FOR UPDATE'
    );
    $lock->execute([$targetId]);
    $target = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        $employeeDb->rollBack();
        authJson(404, ['status' => 'error', 'message' => 'Pegawai tidak ditemukan.']);
    }
    if ((int) $target['is_active'] !== 1) {
        $employeeDb->rollBack();
        authJson(409, ['status' => 'error', 'message' => 'Akun nonaktif tidak dapat direset.']);
    }

    $upsert = $employeeDb->prepare(
        'INSERT INTO auth_password_resets
            (employee_id, temporary_password_hash, expires_at, consumed_at, created_at)
         VALUES (?, ?, UTC_TIMESTAMP(6) + INTERVAL 2 HOUR, NULL, UTC_TIMESTAMP(6))
         ON DUPLICATE KEY UPDATE
            temporary_password_hash = VALUES(temporary_password_hash),
            expires_at = VALUES(expires_at), consumed_at = NULL,
            created_at = UTC_TIMESTAMP(6)'
    );
    $upsert->execute([$targetId, $temporaryHash]);

    $mark = $employeeDb->prepare(
        'UPDATE employees
         SET employee_password = ?, must_change_password = 1
         WHERE employee_id = ?'
    );
    $mark->execute([$temporaryHash, $targetId]);

    $sessions = $employeeDb->prepare(
        'SELECT session_id FROM auth_sessions WHERE employee_id = ? AND revoked_at IS NULL FOR UPDATE'
    );
    $sessions->execute([$targetId]);
    while ($sessionId = $sessions->fetchColumn()) {
        authRevokeSession($employeeDb, (string) $sessionId, 'admin_password_reset');
    }

    $audit = $employeeDb->prepare(
        'INSERT INTO auth_password_reset_audit
            (actor_employee_id, target_employee_id, action, ip_hash, occurred_at)
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP(6))'
    );
    $audit->execute([$actorId, $targetId, 'admin_reset_issued', $ipHash]);
    $employeeDb->commit();

    authJson(200, [
        'status' => 'success',
        'message' => 'Sandi sementara dibuat. Tampilkan kepada pegawai melalui kanal aman; nilai ini hanya tersedia pada respons ini.',
        'employee_id' => $targetId,
        'temporary_password' => $temporaryPassword,
        'expires_in_seconds' => 7200,
        'must_change_password' => true,
    ]);
} catch (Throwable $error) {
    if ($employeeDb->inTransaction()) {
        $employeeDb->rollBack();
    }
    employeeHandleDatabaseError($error, 'Reset sandi admin gagal');
}