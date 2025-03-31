<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../../admin/includes/config.php'; // Database connection

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Use POST."]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$required_fields = ["commodity_name", "category", "variety", "size", "unit", "hs_code", "commodity_alias", "country"];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
        exit;
    }
}

// Extract data
$commodity_name = $data["commodity_name"];
$category = $data["category"];
$variety = $data["variety"];
$size = $data["size"];
$unit = $data["unit"];
$hs_code = $data["hs_code"];
$commodity_alias = $data["commodity_alias"];
$country = $data["country"];

// Handle image upload (if provided)
$image_url = "uploads/default.jpg"; // Default image
if (!empty($data['commodity_image'])) {
    $upload_dir = '../uploads/';
    $image_name = "commodity_" . time() . ".jpg";
    $image_path = $upload_dir . $image_name;

    if (file_put_contents($image_path, base64_decode($data['commodity_image']))) {
        $image_url = "uploads/" . $image_name;
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save image."]);
        exit;
    }
}

// Insert into database
$sql = "INSERT INTO commodities (commodity_name, category, variety, size, unit, hs_code, commodity_alias, country, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $con->prepare($sql);
$stmt->bind_param("sssssssss", $commodity_name, $category, $variety, $size, $unit, $hs_code, $commodity_alias, $country, $image_url);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Commodity added successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to add commodity"]);
}

$stmt->close();
$con->close();
?>
