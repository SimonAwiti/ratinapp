<?php
// check_status_for_unpublish.php
include '../admin/includes/config.php';

header('Content-Type: application/json');

$response = ['allPublished' => false, 'message' => ''];

error_log("Received request to check_status_for_unpublish.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    error_log("Received IDs: " . print_r($ids, true));

    if (empty($ids)) {
        $response['message'] = 'No items selected.';
        echo json_encode($response);
        exit;
    }

    $id_list = implode(',', array_map('intval', $ids));
    error_log("Processed ID list: $id_list");

    $sql = "SELECT COUNT(*) as not_published_count FROM market_prices WHERE id IN ($id_list) AND status != 'published'";
    error_log("Executing SQL: $sql");

    $result = $con->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();
        error_log("Query result: " . print_r($row, true));
        
        if ($row['not_published_count'] == 0) {
            $response['allPublished'] = true;
            $response['message'] = 'All items are published and can be unpublished.';
        } else {
            $response['message'] = 'Some items are not in "Published" status.';
        }
    } else {
        $response['message'] = 'Database error: ' . $con->error;
        error_log("Database error: " . $con->error);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

error_log("Sending response: " . print_r($response, true));
echo json_encode($response);
$con->close();
?>