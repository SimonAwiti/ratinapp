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

// Check if ID is provided
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(["status" => "error", "message" => "Commodity ID is required."]);
    exit;
}

$id = $data['id'];

// Fetch the existing commodity data
$sql = "SELECT * FROM commodities WHERE id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$commodity = $result->fetch_assoc();
$stmt->close();

// If commodity doesn't exist, return an error
if (!$commodity) {
    echo json_encode(["status" => "error", "message" => "Commodity not found."]);
    exit;
}

// Prepare update query dynamically
$updateFields = [];
$updateValues = [];

// Function to check and add updated fields
function addUpdateField($field, $data, &$updateFields, &$updateValues, $existingValue) {
    if (isset($data[$field])) {
        $updateFields[] = "$field = ?";
        $updateValues[] = $data[$field];
    } else {
        $updateValues[] = $existingValue;
    }
}

// Update only provided fields
addUpdateField("commodity_name", $data, $updateFields, $updateValues, $commodity["commodity_name"]);
addUpdateField("category", $data, $updateFields, $updateValues, $commodity["category"]);
addUpdateField("variety", $data, $updateFields, $updateValues, $commodity["variety"]);
addUpdateField("size", $data, $updateFields, $updateValues, $commodity["size"]);
addUpdateField("unit", $data, $updateFields, $updateValues, $commodity["unit"]);
addUpdateField("hs_code", $data, $updateFields, $updateValues, $commodity["hs_code"]);
addUpdateField("commodity_alias", $data, $updateFields, $updateValues, $commodity["commodity_alias"]);
addUpdateField("country", $data, $updateFields, $updateValues, $commodity["country"]);

// Handle image update (if provided)
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
} else {
    $image_url = $commodity["image_url"];
}
$updateFields[] = "image_url = ?";
$updateValues[] = $image_url;

// Prepare final update query
$updateQuery = "UPDATE commodities SET " . implode(", ", $updateFields) . " WHERE id = ?";
$updateValues[] = $id;

$stmt = $con->prepare($updateQuery);
$stmt->bind_param(str_repeat("s", count($updateValues) - 1) . "i", ...$updateValues);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Commodity updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update commodity"]);
}

$stmt->close();
$con->close();
?>
