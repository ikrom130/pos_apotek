<?php

header("Content-Type: application/json; charset=UTF-8");
// header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' || $action !== 'set_product') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "request Method tidak diizinkan"
    ]);
    exit();
}

$json_input = file_get_contents("php://input");
$data = json_decode($json_input, true);
$id = $data['product_id'] ?? '';

if (empty($data) || empty($id)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Gagal: product_id wajib disertakan!"
    ]);
    exit();
}

try {

    $query_set = "UPDATE products SET 
                  product_name = :name, 
                  product_form = :form, 
                  product_unit_dose = :unit, 
                  product_purchase_price = :purchase_price, 
                  product_selling_price = :selling_price, 
                  product_stock = :stock, 
                  min_stock_product = :min_stock, 
                  product_type = :type  WHERE product_id = :id";
    $params = [
    'name' => $data['product_name'] ?? null,
    'form' => $data['product_form'] ?? null,
    'unit' => $data['product_unit_dose'] ?? null,
    'purchase_price' => $data['product_purchase_price'] ?? 0,
    'selling_price' => $data['product_selling_price'] ?? 0,
    'stock' => $data['product_stock'] ?? 0,
    'min_stock' => $data['min_stock_product'] ?? 0,
    'type' => $data['product_type'] ?? null,
    'id' => $id
    ];  

    $stmt_set = $db->prepare($query_set);
    $stmt_set->execute($params);

    if ($stmt_set->rowCount() === 0) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Tidak ada perubahan data produk atau ID tidak ditemukan."
        ]);
        exit();
    }
      
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Data produk " . htmlspecialchars($data['product_name'] ?? '') . " berhasil diubah"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}
