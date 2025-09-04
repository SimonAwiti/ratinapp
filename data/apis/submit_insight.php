<?php
header("Content-Type: application/json");
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
$data = json_decode($json_data, true);

// Check if data is valid and all required fields are present
if ($data === null || !isset($data['tradepoint_id']) || !isset($data['title']) || !isset($data['description']) || !isset($data['tradepoint_type'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON data or missing required fields."]);
    exit;
}

$tradepoint_id = (int)$data['tradepoint_id'];
$title = trim($data['title']);
$description = trim($data['description']);
$tradepoint_type = trim($data['tradepoint_type']);

// Validate that tradepoint_id is a positive integer
if ($tradepoint_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid tradepoint ID."]);
    exit;
}

// Determine which table to query based on tradepoint type
$query = "";
$name_column = "";

switch ($tradepoint_type) {
    case 'Millers':
        $query = "SELECT miller_name AS tradepoint_name, country FROM miller_details WHERE id = ?";
        $name_column = 'tradepoint_name';
        break;
    case 'Markets':
        $query = "SELECT market_name AS tradepoint_name, country FROM markets WHERE id = ?";
        $name_column = 'tradepoint_name';
        break;
    case 'Border Points':
        $query = "SELECT name AS tradepoint_name, country FROM border_points WHERE id = ?";
        $name_column = 'tradepoint_name';
        break;
    default:
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid tradepoint type specified."]);
        exit;
}

// Fetch tradepoint details from the correct table
$stmt_tradepoint = $con->prepare($query);
if (!$stmt_tradepoint) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to prepare tradepoint query: " . $con->error]);
    exit;
}

$stmt_tradepoint->bind_param("i", $tradepoint_id);
$stmt_tradepoint->execute();
$result_tradepoint = $stmt_tradepoint->get_result();

if ($result_tradepoint->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Tradepoint ID not found for the specified type."]);
    exit;
}

$tradepoint_data = $result_tradepoint->fetch_assoc();
$tradepoint_name = $tradepoint_data['tradepoint_name'];
$country = $tradepoint_data['country'];

// Prepare date value
$date_posted = date('Y-m-d H:i:s');
$status = 'pending'; // Default status for new insights

// Insert into the new 'tradepoint_insights' table
$stmt_insert = $con->prepare("INSERT INTO tradepoint_insights
                                  (tradepoint_id, tradepoint_name, country, title, description, date_posted)
                                  VALUES (?, ?, ?, ?, ?, ?)");
    
if (!$stmt_insert) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to prepare insert statement: " . $con->error]);
    exit;
}

$stmt_insert->bind_param("isssss",
    $tradepoint_id, $tradepoint_name, $country, $title, $description, $date_posted);

if ($stmt_insert->execute()) {
    http_response_code(201); // 201 Created
    echo json_encode(["success" => true, "message" => "Insight submitted successfully.", "insight_id" => $con->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error submitting insight: " . $stmt_insert->error]);
}

$con->close();
?>
