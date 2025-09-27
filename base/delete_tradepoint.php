<?php
session_start();
include '../admin/includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No tradepoints selected for deletion.']);
        exit;
    }
    
    // Convert IDs to comma-separated string for IN clause
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    try {
        $con->begin_transaction();
        
        // Delete from markets table
        $stmt1 = $con->prepare("DELETE FROM markets WHERE id IN ($placeholders)");
        $types1 = str_repeat('i', count($ids));
        $stmt1->bind_param($types1, ...$ids);
        $stmt1->execute();
        
        // Delete from border_points table
        $stmt2 = $con->prepare("DELETE FROM border_points WHERE id IN ($placeholders)");
        $types2 = str_repeat('i', count($ids));
        $stmt2->bind_param($types2, ...$ids);
        $stmt2->execute();
        
        // Delete from miller_details table
        $stmt3 = $con->prepare("DELETE FROM miller_details WHERE id IN ($placeholders)");
        $types3 = str_repeat('i', count($ids));
        $stmt3->bind_param($types3, ...$ids);
        $stmt3->execute();
        
        $con->commit();
        
        echo json_encode(['success' => true, 'message' => 'Tradepoints deleted successfully.']);
        
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting tradepoints: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>