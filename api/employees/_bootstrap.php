<?php

declare(strict_types=1);

if (!defined('EMPLOYEE_API_ENTRY')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

$employeeActor = authRequireEmployee();
if ($employeeActor['employee_role'] !== 'admin') {
    authJson(403, ['status' => 'error', 'message' => 'Akses admin diperlukan.']);
}
$employeeDb = $authDb;

function employeeRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        authJson(405, ['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    }
}

function employeeQueryValue(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) {
        authJson(422, ['status' => 'error', 'message' => "Parameter {$key} harus berupa teks."]);
    }
    return trim($value);
}

function employeeHandleDatabaseError(Throwable $error, string $context): never
{
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        authJson(409, ['status' => 'error', 'message' => 'ID atau username pegawai sudah digunakan.']);
    }
    error_log($context . ': ' . $error->getMessage());
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
