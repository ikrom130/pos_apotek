<?php

declare(strict_types=1);

if (!defined('APP_TESTING') && !defined('SUPPLIER_API_ENTRY')) {
    http_response_code(404);
    exit;
}

if (!defined('API_BOOTSTRAP_ENTRY')) {
    define('API_BOOTSTRAP_ENTRY', true);
}
require_once dirname(__DIR__) . '/_shared/bootstrap.php';

if (!defined('APP_TESTING')) {
    $db = $apiDb;
}

function supplierReadData(array $data): array
{
    $name = trim(is_string($data['supplier_name'] ?? null) ? $data['supplier_name'] : '');
    $phone = trim(is_string($data['supplier_phone'] ?? null) ? $data['supplier_phone'] : '');
    $email = strtolower(trim(is_string($data['supplier_email'] ?? null) ? $data['supplier_email'] : ''));
    $address = trim(is_string($data['supplier_address'] ?? null) ? $data['supplier_address'] : '');

    if (preg_match('/\A[^\p{C}]{2,100}\z/u', $name) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Nama supplier wajib diisi, maksimal 100 karakter.']);
    }
    if ($phone !== '' && preg_match('/\A[0-9+(). -]{8,15}\z/', $phone) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Nomor telepon tidak valid.']);
    }
    if ($email !== '' && preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Email tidak valid.']);
    }
    if (mb_strlen($address, 'UTF-8') > 255) {
        authJson(422, ['status' => 'error', 'message' => 'Alamat maksimal 255 karakter.']);
    }
    if ($address !== '' && preg_match('/\A[\p{L}\p{N}\s.,\-\/#&\']+\z/u', $address) !== 1) {
        authJson(422, ['status' => 'error', 'message' => 'Format alamat tidak valid.']);
    }

    return [
        'supplier_name' => $name,
        'supplier_phone' => $phone === '' ? null : $phone,
        'supplier_email' => $email === '' ? null : $email,
        'supplier_address' => $address === '' ? null : $address,
    ];
}

function supplierHandleDatabaseError(Throwable $error, string $context): never
{
    if ($error instanceof PDOException && ((string) $error->getCode() === '23000' || (($error->errorInfo[0] ?? null) === '23000'))) {
        authJson(409, ['status' => 'error', 'message' => 'Data supplier sudah terdaftar.']);
    }

    apiHandleDatabaseError($error, $context);
}
