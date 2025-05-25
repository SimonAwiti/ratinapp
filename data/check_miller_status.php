<?php
include '../admin/includes/config.php';
header('Content-Type: application/json');

// Check if all selected items are approved (for publishing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $ids = array_map('intval', $data['ids']);
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No IDs provided']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM miller_prices WHERE id IN ($placeholders) AND status = 'approved'");
    
    // Bind parameters
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $allApproved = ($row['total'] == count($ids));
    
    echo json_encode([
        'success' => true,
        'allApproved' => $allApproved,
        'message' => $allApproved ? 'All items are approved' : 'Not all items are approved'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>