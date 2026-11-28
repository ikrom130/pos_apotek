<?php

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('SUPPLIER_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleUpdateSuppliers(PDO $db, array $apiActor, ?string $rawBody = null): never
{
    authRequireCsrf();
    apiRequireMethod('PUT');
    apiRequireRole($apiActor, ['admin', 'apoteker']);
    $id = supplierRequireId();
    $profile = supplierReadData(authReadJson($rawBody));

    try {
        $db->beginTransaction();
        $check = $db->prepare('SELECT supplier_name FROM suppliers WHERE supplier_id = ? FOR UPDATE');
        $check->execute([$id]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            $db->rollBack();
            authJson(404, ['status' => 'error', 'message' => 'Supplier tidak ditemukan.']);
        }

        $stmt = $db->prepare(
            'UPDATE suppliers
             SET supplier_name = ?, supplier_phone = ?, supplier_email = ?, supplier_address = ?
             WHERE supplier_id = ?'
        );
        $stmt->execute([
            $profile['supplier_name'],
            $profile['supplier_phone'],
            $profile['supplier_email'],
            $profile['supplier_address'],
            $id,
        ]);
        $db->commit();
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        supplierHandleDatabaseError($error, 'Pembaruan supplier gagal');
    }

    authJson(200, ['status' => 'success', 'message' => 'Data supplier diperbarui.']);
}

if (!defined('APP_TESTING')) {
    handleUpdateSuppliers($db, $apiActor);
}