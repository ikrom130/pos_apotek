<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' || $_GET['action'] !== 'delete_product') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "request method tidak diizinkan"
    ]);
    exit();
}

$json_input = file_get_contents("php://input");
$data = json_decode($json_input, true);
$id = $data['product_id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "format tidak valid atau data kosong"
    ]);
    exit();
}

try {
    $query_delete = "DELETE FROM products where product_id = ?";
    $stmt_delete = $db->prepare($query_delete);
    $stmt_delete->execute([$id]);

    if ($stmt_delete->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Data gagal dihapus. tidak ditemukan id product atau data tidak lengkap"
        ]);
        exit();
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Data product berhasil dihapus!"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "internal server error" . $e->getMessage()
    ]);
}