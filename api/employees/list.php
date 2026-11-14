<?php

declare(strict_types=1);

define('EMPLOYEE_API_ENTRY', true);
require_once __DIR__ . '/_bootstrap.php';
employeeRequireMethod('GET');

try {
    $page = filter_var(employeeQueryValue('page', '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    $limit = filter_var(employeeQueryValue('limit', '20'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if ($page === false || $limit === false) {
        authJson(422, ['status' => 'error', 'message' => 'page minimal 1 dan limit harus 1–100.']);
    }

    $where = [];
    $params = [];
    $search = employeeQueryValue('search');
    if ($search !== '') {
        if (strlen($search) > 100) {
            authJson(422, ['status' => 'error', 'message' => 'search maksimal 100 karakter.']);
        }
        $where[] = '(employee_id LIKE ? OR employee_name LIKE ? OR employee_username LIKE ?)';
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle);
    }

    $role = strtolower(employeeQueryValue('role'));
    if ($role !== '') {
        if (!in_array($role, ['admin', 'staff'], true)) {
            authJson(422, ['status' => 'error', 'message' => 'Filter role tidak valid.']);
        }
        $where[] = 'employee_role = ?';
        $params[] = $role;
    }

    $active = employeeQueryValue('active');
    if ($active !== '') {
        if (!in_array($active, ['0', '1'], true)) {
            authJson(422, ['status' => 'error', 'message' => 'Filter active harus 0 atau 1.']);
        }
        $where[] = 'is_active = ?';
        $params[] = (int) $active;
    }

    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $employeeDb->prepare('SELECT COUNT(*) FROM employees' . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $employeeDb->prepare(
        'SELECT employee_id, employee_name, employee_role, employee_phone,
                employee_username, is_active, created_at, updated_at
         FROM employees' . $whereSql .
        ' ORDER BY employee_name ASC, employee_id ASC LIMIT ? OFFSET ?'
    );
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();

    authJson(200, [
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
    ]);
} catch (Throwable $error) {
    employeeHandleDatabaseError($error, 'Daftar pegawai gagal dimuat');
}
