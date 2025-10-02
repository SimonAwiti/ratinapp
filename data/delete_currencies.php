<?php
// data/delete_currencies.php
include '../admin/includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No currencies selected for deletion.']);
        exit;
    }
    
    // Create placeholder string for prepared statement
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    
    $stmt = $con->prepare("DELETE FROM currencies WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Currency rates deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting currency rates: ' . $con->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>