<?php

declare(strict_types=1);

if (!defined('EMPLOYEE_API_ENTRY')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

$employeeActor = authRequireEmployee();
$employeeDb = $authDb;

define('API_INTERNAL_INCLUDE', true);
require_once dirname(__DIR__) . '/_shared/id-sequence.php';

function employeeRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        authJson(405, ['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    }
}

function employeeRequireAdmin(): void
{
    global $employeeActor;
    if ($employeeActor['employee_role'] !== 'admin') {
        authJson(403, ['status' => 'error', 'message' => 'Akses admin diperlukan.']);
    }
}

function employeePasswordChangeIsLimited(PDO $db, string $usernameIpHash, string $ipHash): bool
{
    $user = $db->prepare(
        'SELECT COUNT(*) FROM auth_password_attempts
         WHERE username_ip_hash = ?
           AND attempted_at >= UTC_TIMESTAMP(6) - INTERVAL 15 MINUTE'
    );
    $user->execute([$usernameIpHash]);
    $userCount = (int) $user->fetchColumn();

    $ip = $db->prepare(
        'SELECT COUNT(*) FROM auth_password_attempts
         WHERE ip_hash = ?
           AND attempted_at >= UTC_TIMESTAMP(6) - INTERVAL 15 MINUTE'
    );
    $ip->execute([$ipHash]);
    $ipCount = (int) $ip->fetchColumn();

    return $userCount >= 5 || $ipCount >= 30;
}

function employeeRecordPasswordChangeFailure(PDO $db, string $usernameIpHash, string $ipHash): void
{
    $stmt = $db->prepare(
        'INSERT INTO auth_password_attempts (username_ip_hash, ip_hash, attempted_at)
         VALUES (?, ?, UTC_TIMESTAMP(6))'
    );
    $stmt->execute([$usernameIpHash, $ipHash]);
}

function employeeClearPasswordChangeFailures(PDO $db, string $usernameIpHash, string $ipHash): void
{
    $stmt = $db->prepare(
        'DELETE FROM auth_password_attempts WHERE username_ip_hash = ? AND ip_hash = ?'
    );
    $stmt->execute([$usernameIpHash, $ipHash]);
}

function employeeQueryValue(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) {
        authJson(422, ['status' => 'error', 'message' => "Parameter {$key} harus berupa teks."]);
    }
    return trim($value);
}

function employeeRequireId(): string
{
    $id = strtoupper(employeeQueryValue('id'));
    if (preg_match('/\AEMP-[0-9]{5,11}\z/', $id) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'employee_id harus berformat EMP-00001.']);
    }
    return $id;
}

function employeeReadProfile(array $data): array
{
    $name = trim(is_string($data['employee_name'] ?? null) ? $data['employee_name'] : '');
    $role = strtolower(trim(is_string($data['employee_role'] ?? null) ? $data['employee_role'] : ''));
    $phone = trim(is_string($data['employee_phone'] ?? null) ? $data['employee_phone'] : '');
    $username = strtolower(trim(is_string($data['employee_username'] ?? null) ? $data['employee_username'] : ''));

    if (preg_match('/\A[^\p{C}]{2,100}\z/u', $name) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Nama pegawai wajib diisi, maksimal 100 karakter.']);
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        authJson(422, ['status' => 'error', 'message' => 'Role harus admin atau staff.']);
    }
    if ($phone !== '' && preg_match('/\A[0-9+(). -]{7,25}\z/', $phone) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Nomor telepon tidak valid.']);
    }
    if (preg_match('/\A[a-zA-Z0-9._-]{3,50}\z/', $username) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Username harus 3–50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.']);
    }

    return [
        'employee_name' => $name,
        'employee_role' => $role,
        'employee_phone' => $phone === '' ? null : $phone,
        'employee_username' => $username,
    ];
}

function employeeHandleDatabaseError(Throwable $error, string $context): never
{
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        authJson(409, ['status' => 'error', 'message' => 'Username pegawai sudah digunakan.']);
    }
    error_log($context . ': ' . $error->getMessage());
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
