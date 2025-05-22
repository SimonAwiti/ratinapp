<?php
// update_status.php

// Include your database configuration file
include '../admin/includes/config.php'; // Adjust path as needed

// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *'); // IMPORTANT: Restrict this to your actual domain in production!
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => ''];

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    echo json_encode($response);
    exit;
}

// Get the POST data from the request body
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$ids = $input['ids'] ?? [];

// Validate input
if (empty($ids) || !is_array($ids)) {
    $response['message'] = 'No items selected or invalid ID format.';
    echo json_encode($response);
    exit;
}
if (empty($action)) {
    $response['message'] = 'Action not specified.';
    echo json_encode($response);
    exit;
}

// Sanitize IDs for SQL IN clause to prevent SQL injection
$id_list = implode(',', array_map('intval', $ids));

$sql = ""; // Initialize SQL query variable
$success_message_verb = ""; // Verb for the success message

switch ($action) {
    case 'approve':
        $sql = "UPDATE market_prices SET status = 'approved' WHERE id IN ($id_list)";
        $success_message_verb = "approved";
        break;
    case 'publish':
        $sql = "UPDATE market_prices SET status = 'published' WHERE id IN ($id_list)";
        $success_message_verb = "published";
        break;
    case 'unpublish':
        $sql = "UPDATE market_prices SET status = 'unpublished' WHERE id IN ($id_list)";
        $success_message_verb = "unpublished";
        break;
    case 'delete':
        $sql = "DELETE FROM market_prices WHERE id IN ($id_list)";
        $success_message_verb = "deleted";
        break;
    default:
        $response['message'] = 'Invalid action specified.';
        echo json_encode($response);
        $con->close();
        exit;
}

// Execute the SQL query
if (!empty($sql)) {
    if ($con->query($sql)) {
        if ($con->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = count($ids) . ' items ' . $success_message_verb . ' successfully.';
        } else {
            $response['message'] = 'No records found or no changes made for the given IDs.';
        }
    } else {
        // Log the database error for debugging, but don't expose sensitive info to the client
        error_log("Database error in update_status.php: " . $con->error . " for query: " . $sql);
        $response['message'] = 'A database error occurred. Please try again later.';
    }
} else {
    $response['message'] = 'SQL query was not generated for the requested action.';
}

echo json_encode($response);

// Close the database connection
$con->close();
?>