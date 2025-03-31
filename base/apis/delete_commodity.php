<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include '../../admin/includes/config.php'; // Database connection

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Use DELETE."]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate required field
if (!isset($data["id"]) || empty($data["id"])) {
    echo json_encode(["status" => "error", "message" => "Missing required field: id"]);
    exit;
}

$commodity_id = intval($data["id"]);

// Check if commodity exists
$check_sql = "SELECT image_url FROM commodities WHERE id = ?";
$check_stmt = $con->prepare($check_sql);
$check_stmt->bind_param("i", $commodity_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Commodity not found"]);
    exit;
}

$row = $result->fetch_assoc();
$image_url = $row["image_url"];

// Delete commodity from database
$delete_sql = "DELETE FROM commodities WHERE id = ?";
$delete_stmt = $con->prepare($delete_sql);
$delete_stmt->bind_param("i", $commodity_id);

if ($delete_stmt->execute()) {
    // Delete the image file if it exists (and is not the default image)
    if ($image_url !== "uploads/default.jpg" && file_exists("../" . $image_url)) {
        unlink("../" . $image_url);
    }

    echo json_encode(["status" => "success", "message" => "Commodity deleted successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete commodity"]);
}

$check_stmt->close();
$delete_stmt->close();
$con->close();
?>
