<?php

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('EMPLOYEE_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handlePasswordEmployee(PDO $employeeDb, array $apiActor, ?string $rawBody = null): never
{
    authRequireCsrf();
    apiRequireRole($apiActor, ['admin']);
    apiRequireMethod('POST');
    
    $targetId = employeeRequireId();
    $actorId = $apiActor['employee_id'];
    
    if (hash_equals($actorId, $targetId)) {
        authJson(422, [
            'status' => 'error',
            'message' => 'Admin tidak dapat mengganti sandi akunnya sendiri lewat endpoint ini. Minta admin lain.',
        ]);
    }
    
    $input = authReadJson($rawBody);
    $newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
    if (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
        authJson(422, ['status' => 'error', 'message' => 'Sandi baru harus 12–72 byte.']);
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($newHash)) {
        authJson(500, ['status' => 'error', 'message' => 'Sandi gagal diproses.']);
    }
    
    try {
        $employeeDb->beginTransaction();
    
        $targetQuery = $employeeDb->prepare(
            'SELECT is_active FROM employees WHERE employee_id = ? FOR UPDATE'
        );
        $targetQuery->execute([$targetId]);
        $target = $targetQuery->fetch(PDO::FETCH_ASSOC);
    
        if (!$target) {
            $employeeDb->rollBack();
            authJson(404, ['status' => 'error', 'message' => 'Pegawai tidak ditemukan.']);
        }
        if ((int) $target['is_active'] !== 1) {
            $employeeDb->rollBack();
            authJson(409, ['status' => 'error', 'message' => 'Akun nonaktif tidak dapat diubah sandinya.']);
        }
    
        $update = $employeeDb->prepare(
            'UPDATE employees SET employee_password = ? WHERE employee_id = ?'
        );
        $update->execute([$newHash, $targetId]);
    
        $sessions = $employeeDb->prepare(
            'SELECT session_id FROM auth_sessions
             WHERE employee_id = ? AND revoked_at IS NULL
             FOR UPDATE'
        );
        $sessions->execute([$targetId]);
        while ($sessionId = $sessions->fetchColumn()) {
            authRevokeSession($employeeDb, (string) $sessionId, 'admin_password_changed');
        }
    
        $employeeDb->commit();
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        if ($employeeDb->inTransaction()) {
            $employeeDb->rollBack();
        }
        employeeHandleDatabaseError($error, 'Perubahan sandi oleh admin gagal');
    }
    
    authJson(200, [
            'status' => 'success',
            'message' => 'Sandi pegawai berhasil diperbarui. Sesi pegawai telah dicabut.',
            'employee_id' => $targetId,
        ]);
}

if (!defined('APP_TESTING')) {
    handlePasswordEmployee($employeeDb, $apiActor);
}