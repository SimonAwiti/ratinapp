<?php
header('Content-Type: application/json');
include '../../admin/includes/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing ID.'
    ]);
    exit;
}

$id = intval($_GET['id']);
$stmt = $con->prepare("SELECT * FROM miller_details WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $detail = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'data' => $detail
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Miller detail not found.'
    ]);
}

$stmt->close();
?>
