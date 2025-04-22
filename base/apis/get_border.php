<?php
header('Content-Type: application/json');
include '../../admin/includes/config.php';

if (!isset($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "No ID provided"
    ]);
    exit;
}

$id = intval($_GET['id']);

$stmt = $con->prepare("SELECT * FROM border_points WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Border point not found"
    ]);
} else {
    $border = $result->fetch_assoc();
    $border['images'] = json_decode($border['images'], true); // Decode images JSON
    echo json_encode([
        "status" => "success",
        "data" => $border
    ]);
}
?>
