<?php

declare(strict_types=1);

if (!defined('EMPLOYEE_API_ENTRY')) {
    http_response_code(404);
    exit;
}

define('API_BOOTSTRAP_ENTRY', true);
require_once dirname(__DIR__) . '/_shared/bootstrap.php';

$employeeActor = $apiActor;
$employeeDb = $apiDb;


function employeeRequireId(): string
{
    $id = strtoupper(apiQueryValue('id'));
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
    if (!in_array($role, ['admin', 'apoteker', 'kasir'], true)) {
        authJson(422, ['status' => 'error', 'message' => 'Role harus admin, apoteker, atau kasir.']);
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

    apiHandleDatabaseError($error, $context);
}
