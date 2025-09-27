<?php
session_start();
include '../admin/includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No enumerators selected for deletion.']);
        exit;
    }
    
    // Convert IDs to comma-separated string for IN clause
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    try {
        $con->begin_transaction();
        
        // Delete from enumerators table
        $stmt = $con->prepare("DELETE FROM enumerators WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        
        $con->commit();
        
        echo json_encode(['success' => true, 'message' => 'Enumerators deleted successfully.']);
        
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting enumerators: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>