<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

$db = (new Database())->getConnection();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $action !== 'get_products') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "request method tidak diizinkan"
    ]);
    exit ();
}

$search = $_GET['search'] ?? '';

$query_products = "SELECT product_id, 
                   product_name, 
                   product_form, 
                   product_unit_dose, 
                   product_purchase_price, 
                   product_selling_price, 
                   product_stock, 
                   min_stock_product, 
                   product_type FROM products";
$params = [];

if ($search !== '') {
    $query_products .= " WHERE product_id LIKE :search 
                     OR product_name LIKE :search";
    $params['search'] = "%" . $search . "%"; 
}

$query_products .= " ORDER BY product_name ASC";

try {

    $stmt_product = $db->prepare($query_products);
    $stmt_product->execute($params);

    $products = $stmt_product->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) > 0) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "data product ditemukan",
            "data" => $products
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            "status" => "error",
            "message" => "data tidak ditemukan"
        ]);
    }

} catch (PDOException $e) { 
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}