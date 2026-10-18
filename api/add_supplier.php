<?php 

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = ($_GET['action']) ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action !== 'add_supplier') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "request method tidak diizinkan"
    ]);
    exit();
}

$json_input = file_get_contents("php://input");
$data = json_decode($json_input, true);

if (empty($data)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Format tidak valid atau data kosong"
    ]);
    exit();
}

try {
    $query_add = "INSERT INTO suppliers (supplier_id, supplier_name, supplier_address, supplier_phone, officer_name) VALUES (:id, :name, :address, :officer_phone, :officer_name)";
    $bindings = [
        "id" => $data['supplier_id'] ?? '',
        "name" => $data['supplier_name'] ?? '',
        "address" => $data['supplier_address'] ?? '',
        "officer_phone" => $data['supplier_phone'] ?? '',
        "officer_name" => $data['officer_name'] ?? '',
    ];

    $stmt_add = $db->prepare($query_add);
    $stmt_add->execute($bindings);

    if ($stmt_add->rowCount() === 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "gagal menambahkan data. Pastikan data yang dikirim sudah benar atau belum terdaftar."
        ]);
        exit();
    }

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "data berhasil ditambahkan",
        "data" => $data['supplier_id']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal Server Error"
    ]);
}