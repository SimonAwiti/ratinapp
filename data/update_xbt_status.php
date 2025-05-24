<?php
header('Content-Type: application/json');
include '../admin/includes/config.php';

$response = ['success' => false, 'message' => ''];

try {
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action']) || !isset($input['ids'])) {
        throw new Exception('Invalid input data');
    }

    $action = $input['action'];
    $ids = $input['ids'];
    
    if (!is_array($ids) || empty($ids)) {
        throw new Exception('No items selected');
    }

    // Prepare the IDs for the query
    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $idTypes = str_repeat('i', count($ids));
    
    // Determine the new status based on action
    $newStatus = '';
    switch ($action) {
        case 'approve':
            $newStatus = 'approved';
            break;
        case 'publish':
            $newStatus = 'published';
            break;
        case 'unpublish':
            $newStatus = 'unpublished';
            break;
        case 'delete':
            // Handle deletion separately
            $stmt = $con->prepare("DELETE FROM xbt_volumes WHERE id IN ($idPlaceholders)");
            $stmt->bind_param($idTypes, ...$ids);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Items deleted successfully';
            } else {
                throw new Exception('Failed to delete items: ' . $stmt->error);
            }
            $stmt->close();
            echo json_encode($response);
            exit;
        default:
            throw new Exception('Invalid action specified');
    }

    // Update status for non-delete actions
    $stmt = $con->prepare("UPDATE xbt_volumes SET status = ? WHERE id IN ($idPlaceholders)");
    $params = array_merge([$newStatus], $ids);
    $stmt->bind_param(str_repeat('s', 1) . $idTypes, ...$params);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Items $action successfully";
    } else {
        throw new Exception("Failed to $action items: " . $stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in update_xbt_status: " . $e->getMessage());
}

echo json_encode($response);
?>