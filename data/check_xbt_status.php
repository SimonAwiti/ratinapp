<?php
header('Content-Type: application/json');
include '../admin/includes/config.php';

$response = ['success' => false, 'allApproved' => false, 'message' => ''];

try {
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['ids'])) {
        throw new Exception('Invalid input data');
    }

    $ids = $input['ids'];
    
    if (!is_array($ids) || empty($ids)) {
        throw new Exception('No items selected');
    }

    // Prepare the IDs for the query
    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $idTypes = str_repeat('i', count($ids));
    
    // Check if all selected items are approved
    $stmt = $con->prepare("SELECT COUNT(*) AS unapproved_count FROM xbt_volumes 
                          WHERE id IN ($idPlaceholders) AND status != 'approved'");
    $stmt->bind_param($idTypes, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $response['success'] = true;
    $response['allApproved'] = ($row['unapproved_count'] == 0);
    
    if (!$response['allApproved']) {
        $response['message'] = $row['unapproved_count'] . ' item(s) are not approved';
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in check_xbt_status: " . $e->getMessage());
}

echo json_encode($response);
?>