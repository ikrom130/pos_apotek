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
unset($data['product_id']);

if (empty($id)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Gagal: product_id wajib disertakan!"
    ]);
    exit();
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "format tidak valid atau data kosong!"
    ]);
    exit();
}

try {
    $setFields = [];
    $bindings = [":id" => $id];

    foreach ($data as $key => $value) {
        $kolomBersih = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

        $setFields[] = "$kolomBersih = :$kolomBersih";
        $bindings[":$kolomBersih"] = $value; 
    }

    $query_set = "UPDATE products SET " . implode(", " , $setFields) . " WHERE product_id = :id"; 

    $stmt_set = $db->prepare($query_set);
    $stmt_set->execute($bindings);

    if ($stmt_set->rowCount() === 0) {
        http_response_code(404); // 404 Not Found / Not Modified
        echo json_encode([
            "status" => "error",
            "message" => "Data produk gagal diubah. ID tidak ditemukan atau tidak ada perubahan data."
        ]);
        exit();
    }
      
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Data produk " . $data['product_name'] . " berhasil diubah"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error"
    ]);
}
