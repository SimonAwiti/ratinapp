<?php
// delete_enumerators.php
// Include database configuration
include '../admin/includes/config.php';

// Ensure that the request is a POST request.  This is important for security.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.  Use POST.']);
    exit;
}

// Get the posted data.  We expect a JSON array of ids.
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format.  Expected an array of ids.']);
    exit;
}

$ids = $data['ids'];

// Sanitize the ids.  This is CRITICAL to prevent SQL injection.
$safe_ids = array_map('intval', $ids); // Ensure all ids are integers
$placeholders = implode(',', array_fill(0, count($safe_ids), '?')); // Create placeholders for prepared statement

// Prepare the SQL statement.  Use a prepared statement to prevent SQL injection.
$sql = "DELETE FROM enumerators WHERE id IN ($placeholders)";
$stmt = $con->prepare($sql);

if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement: ' . $con->error]);
    exit;
}

// Bind the parameters.  This is where the sanitized ids are bound to the prepared statement.
$stmt->bind_param(str_repeat('i', count($safe_ids)), ...$safe_ids);

// Execute the statement.
if ($stmt->execute()) {
    $deleted_rows = $stmt->affected_rows;
    echo json_encode(['status' => 'success', 'message' => "Successfully deleted $deleted_rows enumerators."]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete enumerators: ' . $stmt->error]);
}

$stmt->close();
$con->close();
?>
