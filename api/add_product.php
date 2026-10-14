<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action !== 'add_product') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method request tidak diizinkan"
    ]);
    exit();
}

$json_input = file_get_contents("php://input");
$data = json_decode($json_input, true);

if (empty($data)) {
    http_response_code(400);
    json_encode([
        "status" => "error",
        "message" => "format tidak valid atau data kosong!"
    ]);
    exit();
}

$id_product = $data['id_product'];
$name = $data['product_name'];
$form = $data['product_form'];
$unit_dose = $data['product_unit_does'];
$purchase_price = $data['product_purchase_price'];
$selling_price = $data['product_selling_price'];
$stock = $data['product_stock'];
$min_stock = $data["min_stock_product"];
$type = $data["product_type"];

try {
    $kolomTabel = [];
    $placeholders = [];
    $bindings = [];

    foreach ($data as $kolom => $nilai) {
        
        $kolomBersih = preg_replace('/[^a-zA-Z0-9_]/', '', $kolom);
        
        $kolomTabel[] = $kolomBersih;
        $placeholders[] = ":" . $kolomBersih;
        $bindings[":" . $kolomBersih] = $nilai;
    }

    $query_add = "INSERT INTO products (" . implode(", ", $kolomTabel) . ") VALUES (" . implode(", ", $placeholders) . ")";

    $stmt_add = $db->prepare($query_add);
    $stmt_add->execute($bindings);

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "data berhasil disimpan"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Gagal menyimpan data"
    ]);
}