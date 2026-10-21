<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../config/database.php';

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Allow: POST");
    respond(405, ["status" => "error", "message" => "Metode request tidak diizinkan."]);
}

$action = $_GET['action'] ?? '';
if ($action !== 'checkout') {
    respond(400, ["status" => "error", "message" => "Action tidak valid. Gunakan action=checkout."]);
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    respond(400, ["status" => "error", "message" => "Body request harus berupa JSON yang valid."]);
}

$employeeId = trim((string)($data['employee_id'] ?? ''));
$cartItems = $data['cart_items'] ?? [];
$cashAmount = $data['cash_amount'] ?? null;

if ($employeeId === '' || !is_array($cartItems) || count($cartItems) === 0 ||
    !is_numeric($cashAmount) || (float)$cashAmount < 0) {
    respond(400, ["status" => "error", "message" => "employee_id, cash_amount, dan cart_items wajib diisi dengan format yang benar."]);
}

if (count($cartItems) > 100) {
    respond(400, ["status" => "error", "message" => "Jumlah item dalam satu transaksi melebihi batas."]);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $db->beginTransaction();

    // Tahap ini belum memakai login/autentikasi. employee_id hanya divalidasi
    // terhadap data pegawai yang ada, bukan bukti identitas pengguna.
    $employeeStmt = $db->prepare(
        "SELECT employee_id FROM employees WHERE employee_id = ? LIMIT 1"
    );
    $employeeStmt->execute([$employeeId]);
    if (!$employeeStmt->fetch(PDO::FETCH_ASSOC)) {
        $db->rollBack();
        respond(400, ["status" => "error", "message" => "employee_id tidak ditemukan."]);
    }

    $prescriptionId = null;
    if (isset($data['prescription']) && $data['prescription'] !== null && $data['prescription'] !== []) {
        $prescription = $data['prescription'];
        $patientName = trim((string)($prescription['patient_name'] ?? ''));
        $doctorName = trim((string)($prescription['doctor_name'] ?? ''));

        if ($patientName === '' || $doctorName === '') {
            $db->rollBack();
            respond(400, ["status" => "error", "message" => "Nama pasien dan dokter wajib diisi untuk data resep."]);
        }

        $prescriptionId = 'RSP' . date('ymd') . '-' . random_int(1000, 9999);
        $prescriptionDate = $prescription['prescription_date'] ?? date('Y-m-d');
        $doctorLicenseNo = trim((string)($prescription['doctor_license_no'] ?? ''));
        $doctorLicenseNo = $doctorLicenseNo === '' ? null : $doctorLicenseNo;

        $prescriptionStmt = $db->prepare(
            "INSERT INTO prescriptions
             (prescription_id, patient_name, doctor_name, doctor_license_no, prescription_date)
             VALUES (?, ?, ?, ?, ?)"
        );
        $prescriptionStmt->execute([
            $prescriptionId, $patientName, $doctorName, $doctorLicenseNo, $prescriptionDate
        ]);
    }

    $sellingNo = 'INV' . date('ymd') . '-' . random_int(1000, 9999);

    $totalSelling = 0.0;
    $preparedItems = [];

    // Kunci baris batch selama transaksi untuk mengurangi risiko stok terjual ganda.
    $itemStmt = $db->prepare(
        "SELECT
            b.batch_id, b.product_id, b.batch_stock, b.expired_date,
            p.product_name,
            pu.unit_name, pu.selling_price,
            puc.factor_to_stock_unit, puc.is_stock_unit
         FROM stock_batches b
         JOIN products p ON p.product_id = b.product_id
         JOIN product_units pu
           ON pu.product_id = b.product_id AND pu.unit_name = ?
         JOIN product_unit_conversions puc
           ON puc.product_id = b.product_id
          AND puc.unit_name = pu.unit_name
          AND puc.is_active = 1
         WHERE b.batch_id = ?
         FOR UPDATE"
    );

    foreach ($cartItems as $index => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException("Item keranjang ke-" . ($index + 1) . " tidak valid.");
        }

        $batchId = filter_var($item['batch_id'] ?? null, FILTER_VALIDATE_INT);
        $sellingUnit = trim((string)($item['selling_unit'] ?? ''));
        $sellingQty = filter_var($item['selling_qty'] ?? null, FILTER_VALIDATE_INT);

        if (!$batchId || $sellingUnit === '' || !$sellingQty || $sellingQty < 1) {
            throw new InvalidArgumentException("Batch, satuan, atau jumlah item ke-" . ($index + 1) . " tidak valid.");
        }

        $itemStmt->execute([$sellingUnit, $batchId]);
        $row = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new InvalidArgumentException("Batch atau konfigurasi satuan item ke-" . ($index + 1) . " tidak ditemukan.");
        }

        // Untuk tahap ini, penjualan hanya boleh memakai satuan stok dasar.
        // Konversi satuan non-dasar belum diproses oleh endpoint ini.
        if ((int)$row['is_stock_unit'] !== 1 || (float)$row['factor_to_stock_unit'] !== 1.0) {
            throw new InvalidArgumentException("Satuan {$sellingUnit} bukan satuan stok dasar yang aktif untuk produk {$row['product_name']}.");
        }

        if ($row['expired_date'] !== null && $row['expired_date'] < date('Y-m-d')) {
            throw new InvalidArgumentException("Batch {$batchId} sudah kedaluwarsa dan tidak dapat dijual.");
        }

        if ((int)$row['batch_stock'] < $sellingQty) {
            throw new InvalidArgumentException("Stok batch {$batchId} tidak mencukupi.");
        }

        $price = (float)$row['selling_price'];
        if ($price < 0) {
            throw new RuntimeException("Harga jual pada konfigurasi satuan tidak valid.");
        }

        $subtotal = round($price * $sellingQty, 2);
        $totalSelling = round($totalSelling + $subtotal, 2);

        $preparedItems[] = [
            'batch_id' => (int)$row['batch_id'],
            'product_id' => $row['product_id'],
            'unit' => $sellingUnit,
            'qty' => $sellingQty,
            'price' => $price,
            'subtotal' => $subtotal,
            'balance_before' => (int)$row['batch_stock'],
        ];
    }

    $cashAmount = round((float)$cashAmount, 2);
    if ($cashAmount < $totalSelling) {
        throw new InvalidArgumentException("Uang tunai kurang dari total transaksi.");
    }
    $changeAmount = round($cashAmount - $totalSelling, 2);

    $sellingStmt = $db->prepare(
        "INSERT INTO selling
         (selling_no, employee_id, prescription_id, total_selling, cash_amount, change_amount, selling_status)
         VALUES (?, ?, ?, ?, ?, ?, 'Success')"
    );
    $sellingStmt->execute([
        $sellingNo, $employeeId, $prescriptionId, $totalSelling, $cashAmount, $changeAmount
    ]);

    $detailStmt = $db->prepare(
        "INSERT INTO selling_details
         (selling_no, batch_id, selling_unit, selling_qty, selling_price_snapshot, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $updateBatchStmt = $db->prepare(
        "UPDATE stock_batches
         SET batch_stock = batch_stock - ?
         WHERE batch_id = ? AND batch_stock >= ?"
    );

    $updateProductStmt = $db->prepare(
        "UPDATE products
         SET product_stock = (
             SELECT COALESCE(SUM(batch_stock), 0)
             FROM stock_batches
             WHERE product_id = ?
         )
         WHERE product_id = ?"
    );

    $balanceStmt = $db->prepare("SELECT batch_stock FROM stock_batches WHERE batch_id = ?");

    $movementStmt = $db->prepare(
        "INSERT INTO inventory_movements
         (batch_id, movement_type, source_type, source_ref, source_line_id,
          qty_base_change, balance_after_base, employee_id, event_key, note)
         VALUES (?, 'SALE', 'SELLING', ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($preparedItems as $lineNo => $item) {
        $detailStmt->execute([
            $sellingNo, $item['batch_id'], $item['unit'], $item['qty'],
            $item['price'], $item['subtotal']
        ]);
        $detailId = (string)$db->lastInsertId();

        $updateBatchStmt->execute([$item['qty'], $item['batch_id'], $item['qty']]);
        if ($updateBatchStmt->rowCount() !== 1) {
            throw new RuntimeException("Stok batch berubah saat transaksi diproses. Silakan coba kembali.");
        }

        $balanceStmt->execute([$item['batch_id']]);
        $balanceAfter = (int)$balanceStmt->fetchColumn();

        $updateProductStmt->execute([$item['product_id'], $item['product_id']]);

        $movementStmt->execute([
            $item['batch_id'],
            $sellingNo,
            $detailId !== '0' ? $detailId : (string)($lineNo + 1),
            -1 * $item['qty'],
            $balanceAfter,
            $employeeId,
            'SALE-' . $sellingNo . '-LINE-' . ($lineNo + 1),
            'Penjualan kasir'
        ]);
    }

    $db->commit();

    respond(201, [
        "status" => "success",
        "message" => "Transaksi kasir berhasil diproses.",
        "data" => [
            "selling_no" => $sellingNo,
            "prescription_id" => $prescriptionId,
            "total_selling" => $totalSelling,
            "cash_amount" => $cashAmount,
            "change_amount" => $changeAmount
        ]
    ]);
} catch (InvalidArgumentException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    respond(400, ["status" => "error", "message" => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("selling.php error: " . $e->getMessage());
    respond(500, [
        "status" => "error",
        "message" => "Transaksi gagal diproses karena kesalahan server."
    ]);
}
