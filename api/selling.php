<?php

header("Content-Type: application/json; charset=UTF-8");

// set method POST
header("Access-Control-Allow-Methods: POST");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_REQUEST['REQUEST_METHOD'] !== 'POST' || $action === 'checkout') {

    // Membaca data JSON mentah yang dikirim oleh JavaScript Vanilla
    $json_input = file_get_contents("php://input");
    // masukkan ke var data
    $data = json_decode($json_input, true);

    // Validasi dasar
    if (empty($data) || !isset($data['employee_id']) || !isset($data['cart_items'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Format data transaksi tidak valid atau keranjang kosong."
        ]);
        exit();
    }

    $selling_no = "INV" . date("ymd") . "-" . rand(1000, 9999); // Maks 15 
    $employee_id = $data['employee_id'];
    $total_selling = $data['total_selling'];
    $cash_amount = $data['cash_amount'];
    $change_amount = $cash_amount - $total_selling;
    $cart_items = $data['cart_items'];
    $prescription_id = null; 

    try {
        
        // set transaction 
        $db->beginTransaction();

        // cek apakah ada data resep
        if (!empty($data['prescription'])) {
            $presc = $data['prescription'];
            
            // Validasi input resep
            if (!empty($presc['patient_name']) && !empty($presc['doctor_name'])) {
                $prescription_id = "RSP" . date("ymd") . "-" . rand(1000, 9999);
                $patient_name = $presc['patient_name'];
                $doctor_name = $presc['doctor_name'];
                $doctor_license_no = isset($presc['doctor_license_no']) ? $presc['doctor_license_no'] : null;
                $prescription_date = isset($presc['prescription_date']) ? $presc['prescription_date'] : date("Y-m-d");

                // perintah INSERT ke tabel prescriptions terlebih dahulu
                $query_presc = "INSERT INTO prescriptions (prescription_id, patient_name, doctor_name, doctor_license_no, prescription_date) 
                                VALUES (?, ?, ?, ?, ?)";
                $stmt_presc = $db->prepare($query_presc);
                $stmt_presc->execute([$prescription_id, $patient_name, $doctor_name, $doctor_license_no, $prescription_date]);
            }
        }

        // INSERT DATA KE TABEL selling
        $query_selling = "INSERT INTO selling (selling_no, employee_id, prescription_id, total_selling, cash_amount, change_amount, selling_status) 
                          VALUES (?, ?, ?, ?, ?, ?, 'Success')";
        $stmt_selling = $db->prepare($query_selling);
        $stmt_selling->execute([$selling_no, $employee_id, $prescription_id, $total_selling, $cash_amount, $change_amount]);

        // INSERT ITEM BELANJAAN KE TABEL DETAIL
        $query_detail = "INSERT INTO selling_details (selling_no, batch_id, selling_unit, selling_qty, selling_price_snapshot, subtotal) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detail = $db->prepare($query_detail);

        // Perintah potong stok manual di level backend
        $query_update_batch = "UPDATE stock_batches SET batch_stock = batch_stock - ? WHERE batch_id = ?";
        $stmt_update_batch = $db->prepare($query_update_batch);

        $query_update_product = "UPDATE products p
                                 JOIN stock_batches b ON p.product_id = b.product_id
                                 SET p.product_stock = p.product_stock - ?
                                 WHERE b.batch_id = ?";
        $stmt_update_product = $db->prepare($query_update_product);

        foreach ($cart_items as $item) {
            $batch_id = $item['batch_id'];
            $selling_unit = $item['selling_unit'];
            $selling_qty = $item['selling_qty'];
            $price_snapshot = $item['selling_price_snapshot'];
            $subtotal = $selling_qty * $price_snapshot;

            // Eksekusi rincian item belanjaan
            $stmt_detail->execute([$selling_no, $batch_id, $selling_unit, $selling_qty, $price_snapshot, $subtotal]);

            // Eksekusi pemotongan stok otomatis
            $stmt_update_batch->execute([$selling_qty, $batch_id]);
            $stmt_update_product->execute([$selling_qty, $batch_id]);
        }

        // simpan data ke database
        $db->commit();

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Transaksi kasir berhasil diproses.",
            "data" => [
                "selling_no" => $selling_no,
                "prescription_id" => $prescription_id, // Berisi kode resep jika pakai resep, atau null jika obat bebas
                "total_selling" => $total_selling,
                "change_amount" => $change_amount
            ]
        ]);

    } catch (Exception $e) {
        $db->rollBack();

        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Transaksi kasir gagal diproses karena kesalahan server: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Metode request tidak diizinkan."
    ]);
}
