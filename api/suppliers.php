<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $action !== 'get_supplier') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "request method tidak diizinkan"
    ]);
    exit ();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';

$query_supp = "SELECT supplier_id, supplier_name, supplier_address, officer_phone, officer_name FROM supplier";
$params = [];

if ($search !== '') {
    $query_supp .= " WHERE supplier_id LIKE :search 
                     OR supplier_name LIKE :search 
                     OR officer_phone LIKE :search";
    $params['search'] = "%" . $search . "%"; 
}

$query_supp .= " ORDER BY supplier_name ASC";

try {

    $stmt_supp = $db->prepare($query_supp);
    $stmt_supp->execute($params);

    $supplier = $stmt_supp->fetchAll();

    if (count($supplier) > 0) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "data supplier ditemukan",
            "data" => $supplier
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "data tidak ditemukan"
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "internal server error: " . $e->getMessage()
    ]);
}