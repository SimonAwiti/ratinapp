<?php
// check_status.php
include '../admin/includes/config.php';

header('Content-Type: application/json');

$response = ['allApproved' => false, 'message' => ''];

error_log("Received request to check_status.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    error_log("Received IDs: " . print_r($ids, true));

    if (empty($ids)) {
        $response['message'] = 'No items selected.';
        echo json_encode($response);
        exit;
    }

    // Sanitize IDs
    $id_list = implode(',', array_map('intval', $ids));
    error_log("Processed ID list: $id_list");

    // Check if any items are not approved
    $sql = "SELECT COUNT(*) as not_approved_count FROM market_prices WHERE id IN ($id_list) AND status != 'approved'";
    error_log("Executing SQL: $sql");

    $result = $con->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();
        error_log("Query result: " . print_r($row, true));
        
        if ($row['not_approved_count'] == 0) {
            $response['allApproved'] = true;
            $response['message'] = 'All selected items are approved.';
        } else {
            $response['message'] = 'One or more selected items are not in "approved" status.';
        }
    } else {
        $response['message'] = 'Database error checking approval status: ' . $con->error;
        error_log("Database error: " . $con->error);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

error_log("Sending response: " . print_r($response, true));
echo json_encode($response);
$con->close();
?>