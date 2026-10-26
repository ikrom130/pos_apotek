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


$query_products = "
    SELECT
        p.product_id,
        p.product_name,
        pf.form_name AS product_form,
        p.product_type,
        p.product_group,
        COALESCE(stock.total_stock, 0) AS total_stock,
        p.min_stock_product
    FROM products AS p

    LEFT JOIN product_forms AS pf
        ON pf.form_id = p.form_id

    LEFT JOIN (
        SELECT
            product_id,
            SUM(batch_stock) AS total_stock
        FROM stock_batches
        GROUP BY product_id
    ) AS stock
        ON stock.product_id = p.product_id
";

$params = [];

if ($search !== '') {
    $query_products .= "
        WHERE p.product_id LIKE :search_id
           OR p.product_name LIKE :search_name
    ";

    $search_value = '%' . $search . '%';

    $params['search_id'] = $search_value;
    $params['search_name'] = $search_value;
}

$query_products .= " ORDER BY p.product_name ASC";

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