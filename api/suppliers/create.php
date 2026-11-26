<?php

declare(strict_types=1);


if (!defined('APP_TESTING')) {
    define('SUPPLIER_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleCreateSupplier(PDO $db, array $apiActor, ?string $rawBody = null): never
{
    authRequireCsrf();
    apiRequireMethod('POST');
    apiRequireRole($apiActor, ['admin', 'apoteker']);
    $input = $rawBody !== null ? json_decode($rawBody, true) : authReadJson();
    if (!is_array($input)) {
        authJson(400, ['status' => 'error', 'message' => 'Format JSON tidak valid.']);
    }

    $data = supplierReadData($input);

    $createId = null;
    try {
        $db->beginTransaction();
        $id = nextSequentialId($db, 'supplier', 'SUP-', 5, 15);

        $stmt = $db->prepare(
            'INSERT INTO suppliers(supplier_id, supplier_name, supplier_phone, supplier_email, supplier_address, is_active) VALUES (?,?,?,?,?, 1)'
        );
        $stmt->execute([
            $id,
            $data['supplier_name'],
            $data['supplier_phone'],
            $data['supplier_email'],
            $data['supplier_address'],
        ]);
        $db->commit();
        $createId = $id;
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        supplierHandleDatabaseError($error, 'Pembuatan supplier gagal');
    }
    authJson(201, ['status' => 'success', 'message' => 'Supplier dibuat.', 'supplier_id' => $createId]);
}

if (!defined('APP_TESTING')) {
    handleCreateSupplier($db, $apiActor);
}