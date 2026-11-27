<?php

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('SUPPLIER_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleStatusSuppliers(PDO $db, array $apiActor, ?string $rawBody = null): never
{
    authRequireCsrf();
    apiRequireMethod('PATCH');
    apiRequireRole($apiActor, ['admin', 'apoteker']);
    $id = supplierRequireId();
    $input = authReadJson($rawBody);
    $active = $input['is_active'] ?? null;
    
    if (!in_array($active, [true, false, 0, 1], true)) {
        authJson(422, ['status' => 'error', 'message' => 'is_active harus boolean.']);
    }
    $active = (int) $active;
    
    try {
        $db->beginTransaction();
        $check = $db->prepare('SELECT supplier_id FROM suppliers WHERE supplier_id = ? FOR UPDATE');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            $db->rollBack();
            authJson(404, ['status' => 'error', 'message' => 'Supplier tidak ditemukan.']);
        }
    
        $stmt = $db->prepare('UPDATE suppliers SET is_active = ? WHERE supplier_id = ?');
        $stmt->execute([$active, $id]);
        $db->commit();
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        supplierHandleDatabaseError($error, 'Perubahan status supplier gagal');
    }
    
    authJson(200, ['status' => 'success', 'message' => $active ? 'Supplier diaktifkan.' : 'Supplier di-non-aktifkan.']);
}

if (!defined('APP_TESTING')) {
    handleStatusSuppliers($db, $apiActor);
}