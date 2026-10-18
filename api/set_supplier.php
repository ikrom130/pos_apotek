<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-control-Allow-Headers: Content-Type, Access-Control-allow-Headers,
Authorization, X-Requested-With");

require_once '../config/database.php';

$db = new Database()->getConnection();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' || $action !== "set_supplier") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "request method tidak diizikan"
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$id = $data['supplier_id'];

if (empty($data) || empty($id)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "data tidak valid atau id tidak disertakan"
    ]);
    exit();
}

try {
    $query_set = "UPDATE suppliers SET 
                  supplier_name = :supp_name, 
                  supplier_address = :sup_adress, 
                  supplier_phone = :phone, 
                  officer_name = :name 
                  WHERE supplier_id = :id";
    $bindings = [
        "supp_name" => $data['supplier_name'],
        "supp_address" => $data['supplier_adress'],
        "phone" => $data['supplier_phone'],
        "name" => $data['officer_name'],
        "id" => $id
    ];

    $stmt_set = $db->prepare($query_set);
    $stmt_set->execute($bindings);

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
        "message" => "Data produk " . htmlspecialchars($data['supplier_name'] ?? '') . " berhasil diubah"
    ]);
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