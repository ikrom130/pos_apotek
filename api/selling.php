<?php

header("Content-Type: application/json; charset=UTF-8");

header("Access-Control-Allow-Methods: POST");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action = "checkout") {

    $json_input = file_get_contents("php://input");
    $data = json_decode($json_input, true);

    if (empty($data) || !isset($data['employee_id']) || !isset($data['cart_items'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Data format not valid or empty cart"
        ]);
        exit();
    }

    $selling_no = "INV-" . date("ymd") . "-" . rand(1000, 9999);
    $employee_id = $data['employee_id'];
    $total_selling = $data['total_selling'];
    $cash_amount = $data['cash_amount'];
    $change_amount = $cash_amount - $total_selling;
    $cart_items = $data['cart_items'];
    
    try {
        
        $db->beginTransaction();

        $query_selling = "INSERT INTO selling (selling_no, employee_id, total_selling, cash_amount, change_amount, selling_status) 
                          VALUES (?,?,?,?,?, 'success')";

        $stmt_selling = $db->prepare($query_selling);
        $stmt_selling->execute([$selling_no, $employee_id, $total_selling, $cash_amount, $change_amount]);

        $query_detail = "INSERT INTO selling_details (selling_no, batch_id, selling_unit, selling_qty, selling_price_snapshot, subtotal) VALUES (?,?,?,?,?,?)";
        $stmt_detail = $db->prepare($query_detail);

        $query_update_batch = "UPDATE stock_batches SET batch_stock = batch_stock - ? 
                               WHERE batch_id = ?";
        $stmt_update_batch = $db->prepare($query_update_batch);

        $query_update_product = "UPDATE products p 
                                 JOIN stock_batches b on p.product_id = b.product_id 
                                 SET p.product_stock = p.product_stock - ? 
                                 WHERE b.batch_id = ?";
        $stmt_update_product = $db->prepare($query_update_product);

        foreach ($cart_items as $cart) {
            $batch_id = $cart['batch_id'];
            $selling_unit = $cart['selling_unit'];
            $selling_qty = $cart['selling_qty'];
            $price_snapshot = $cart['selling_price_snapshot'];
            $subtotal = $selling_qty * $price_snapshot;

            // input each setail product to selling detail
            $stmt_detail->execute([$selling_no, $batch_id, $selling_unit, $selling_qty, $price_snapshot, $subtotal]);

            // update batch_stock
            $stmt_update_batch->execute([$selling_qty, $batch_id]);

            // update stock in master product
            $stmt_update_product->execute([$selling_qty, $batch_id]);

        }

        $db->commit();

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Data transaksi berhasil ditambahkan",
            "data" => [
                "selling_no" => $selling_no,
                "total_selling" => $total_selling,
                "change_amount" => $change_amount
            ]
        ]);

    } catch (Exception $e) {
        $db->rollBack();

        http_response_code(500); // 
        echo json_encode([
            "status" => "error",
            "message" => "Transaksi gagal ditambahkan. Terjadi error di server: " . $e->getMessage()
        ]);
     
    }
    
} else {
    http_response_code(405); // method not allowed
    echo json_encode([
        "status" => "error",
        "message" => "Method tidak diizinkan atau parameter salah!"
    ]);

}
