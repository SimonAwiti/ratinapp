<?php
include '../admin/includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = isset($data['action']) ? $data['action'] : '';
    $ids = isset($data['ids']) ? array_map('intval', $data['ids']) : [];
    
    if (empty($ids) || !in_array($action, ['approve', 'publish', 'unpublish', 'delete'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        $con->begin_transaction();
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        switch ($action) {
            case 'approve':
                $stmt = $con->prepare("UPDATE miller_prices SET status = 'approved' WHERE id IN ($placeholders)");
                break;
            case 'publish':
                $stmt = $con->prepare("UPDATE miller_prices SET status = 'published' WHERE id IN ($placeholders) AND status = 'approved'");
                break;
            case 'unpublish':
                $stmt = $con->prepare("UPDATE miller_prices SET status = 'unpublished' WHERE id IN ($placeholders) AND status = 'published'");
                break;
            case 'delete':
                $stmt = $con->prepare("DELETE FROM miller_prices WHERE id IN ($placeholders)");
                break;
        }
        
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $con->commit();
            echo json_encode(['success' => true, 'message' => "Items {$action}d successfully"]);
        } else {
            $con->rollback();
            echo json_encode(['success' => false, 'message' => "No items were {$action}d"]);
        }
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => "Error during {$action}: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>