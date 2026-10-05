<?php 

// set header
header("Content-Type: application/json; charset=UTF-8");

// set only GET Method
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

// connect ro database
$database = new Database();
$db = $database->getConnection();

// get action
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

if ($action == "search" && !empty($id)) {
    
    try {
        
        $query = "SELECT product_id, product_name, product_selling_price, product_stock, product_form 
                  FROM products 
                  WHERE product_id = ? 
                  LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);

        $product = $stmt->fetch();

        if ($product) {

            http_response_code(200); // respon
            echo json_encode([
                "status" => "success",
                "message" => "Product found",
                "data" => $product
            ]);
        } else {

            http_response_code(404); // product not found
            echo json_encode([
                "status" => "error",
                "message" => "Product not found in database"
            ]);
        }

    } catch (Exception $e) {

        http_response_code(500); // server error / not connected
        echo json_encode([
            "status" => "error",
            "message" => "Internal server error"
        ]);
    }

} else {

    http_response_code(400); // bad request
    echo json_encode([
        "status" => "error",
        "Message" => "Invalid request parameters. Ensure action=search and id=[barcode_number]."
    ]);
}