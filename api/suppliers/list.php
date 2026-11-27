<?php

declare(strict_types=1);

if (!defined('APP_TESTING')) {
    define('SUPPLIER_API_ENTRY', true);
    require_once __DIR__ . '/_bootstrap.php';
} else {
    require_once __DIR__ . '/_bootstrap.php';
}

function handleListSuppliers(PDO $db, array $apiActor): never
{
    apiRequireMethod('GET');
    apiRequireRole($apiActor, ['admin', 'apoteker']);
    
    $page = filter_var(apiQueryValue('page', '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($page === false) {
        authJson(422, ['status' => 'error', 'message' => 'Filter pagination page harus 1-1000000']);
    }

    $limit = filter_var(apiQueryValue('limit', '20'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if ($limit === false) {
        authJson(422, ['status' => 'error', 'message' => 'Filter pagination limit harus 1–100.']);
    }

    $where = [];
    $params = [];
    $search = apiQueryValue('search');
    if ($search !== '') {
        if (strlen($search) > 100) {
            authJson(422, ['status' => 'error', 'message' => 'search maksimal 100 karakter.']);
        }
        $where[] = '(supplier_id LIKE ? OR supplier_name LIKE ? OR supplier_phone LIKE ? OR supplier_email LIKE ?)';
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle, $needle);
    }

    $active = apiQueryValue('active');
    if ($active !== '') {
        if (!in_array($active, ['0', '1'], true)) {
            authJson(422, ['status' => 'error', 'message' => 'Filter active harus 0 atau 1.']);
        }
        $where[] = 'is_active = ?';
        $params[] = (int) $active;
    }

    try {
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $db->prepare('SELECT COUNT(*) FROM suppliers' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    
        $stmt = $db->prepare(
            'SELECT supplier_id, supplier_name, supplier_phone, supplier_email, is_active
             FROM suppliers' . $whereSql .
            ' ORDER BY supplier_name ASC, supplier_id ASC LIMIT ? OFFSET ?'
        );
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
        $stmt->execute();
    
    } catch (AuthResponseException $e) {
        throw $e;
    } catch (Throwable $error) {
        supplierHandleDatabaseError($error, 'Daftar supplier gagal dimuat');
    }

    authJson(200, [
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
    ]);
}

if (!defined('APP_TESTING')) {
    handleListSuppliers($db, $apiActor);
}