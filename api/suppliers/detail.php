<?php

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('SUPPLIER_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleDetailSuppliers(PDO $db, array $apiActor): never
{
    apiRequireMethod('GET');
    apiRequireRole($apiActor, ['admin', 'apoteker']);
    $id = supplierRequireId();

    try {
        $stmt = $db->prepare(
            'SELECT supplier_id, supplier_name, supplier_phone, supplier_email,
                    supplier_address, supplier_tax_id, is_active, created_at, updated_at
             FROM suppliers WHERE supplier_id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$supplier) {
            authJson(404, ['status' => 'error', 'message' => 'Supplier tidak ditemukan.']);
        }
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        supplierHandleDatabaseError($error, 'Detail supplier gagal dimuat');
    }

    authJson(200, ['status' => 'success', 'data' => $supplier]);
}

if (!defined('APP_TESTING')) {
    handleDetailSuppliers($db, $apiActor);
}