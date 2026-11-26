<?php

declare(strict_types=1);

if (!defined('API_BOOTSTRAP_ENTRY')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/auth/bootstrap.php';

if (!defined('APP_TESTING')) {
    $apiActor = authRequireEmployee();
    $apiDb = $authDb;
}

if (!defined('API_INTERNAL_INCLUDE')) {
    define('API_INTERNAL_INCLUDE', true);
}
require_once __DIR__ . '/id-sequence.php';

function apiRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        authJson(405, ['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    }
}

function apiRequireRole(array $actor, array $allowedRoles): void
{
    $actorRole = $actor['employee_role'] ?? null;
    if (!is_string($actorRole) || !in_array($actorRole, $allowedRoles, true)) {
        authJson(403, ['status' => 'error', 'message' => 'Role tidak memiliki akses ke fitur ini.']);
    }
}

function apiQueryValue(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) {
        authJson(422, ['status' => 'error', 'message' => "Parameter {$key} harus berupa teks."]);
    }
    return trim($value);
}

function apiHandleDatabaseError(Throwable $error, string $context): never
{
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        authJson(409, ['status' => 'error', 'message' => 'Data bertentangan dengan data yang sudah ada.']);
    }

    error_log($context . ': ' . $error->getMessage());
    authJson(500, ['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
