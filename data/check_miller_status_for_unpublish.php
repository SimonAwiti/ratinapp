<?php
include '../admin/includes/config.php';
header('Content-Type: application/json');

// Check if all selected items are published (for unpublishing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $ids = array_map('intval', $data['ids']);
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No IDs provided']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM miller_prices WHERE id IN ($placeholders) AND status = 'published'");
    
    // Bind parameters
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $allPublished = ($row['total'] == count($ids));
    
    echo json_encode([
        'success' => true,
        'allPublished' => $allPublished,
        'message' => $allPublished ? 'All items are published' : 'Not all items are published'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>