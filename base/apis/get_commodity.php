<?php
header("Content-Type: application/json");
include '../../admin/includes/config.php';


if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(["status" => "error", "message" => "Commodity ID is required"]);
    exit;
}

$id = intval($_GET['id']); // Ensure ID is an integer

// Prepare and execute query
$stmt = $con->prepare("SELECT * FROM commodities WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $commodity = $result->fetch_assoc();
    echo json_encode(["status" => "success", "commodity" => $commodity]);
} else {
    echo json_encode(["status" => "error", "message" => "Commodity not found"]);
}

$stmt->close();
$con->close();
?>
