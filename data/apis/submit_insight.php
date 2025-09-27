<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to your database
include '../../admin/includes/config.php';

// Check for database connection
if (!$con) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

// Get the raw POST data
$json_data = file_get_contents('php://input');

// Check if data was received
if (empty($json_data)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No data received."]);
    exit;
}

// Decode JSON data
$data = json_decode($json_data, true);

// Check if JSON decoding failed
if ($data === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON format."]);
    exit;
}

// Check if all required fields are present
$required_fields = ['tradepoint_id', 'title', 'description'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize and validate input
$tradepoint_id = (int)$data['tradepoint_id'];
$title = trim($data['title']);
$description = trim($data['description']);

// Validate inputs
if ($tradepoint_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid tradepoint ID. Must be a positive number."]);
    exit;
}

if (empty($title)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Title cannot be empty."]);
    exit;
}

if (empty($description)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Description cannot be empty."]);
    exit;
}

// Prepare and execute the INSERT statement
$stmt = $con->prepare("INSERT INTO tradepoint_insights (tradepoint_id, title, description) VALUES (?, ?, ?)");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database preparation failed: " . $con->error]);
    exit;
}

$stmt->bind_param("iss", $tradepoint_id, $title, $description);

if ($stmt->execute()) {
    // Success response
    http_response_code(201);
    echo json_encode([
        "success" => true, 
        "message" => "Insight submitted successfully.",
        "insight_id" => $con->insert_id,
        "data" => [
            "tradepoint_id" => $tradepoint_id,
            "title" => $title,
            "description" => $description
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database insertion failed: " . $stmt->error]);
}

// Close connections
$stmt->close();
$con->close();
?>