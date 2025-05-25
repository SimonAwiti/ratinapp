<?php
// delete_data_sources.php
include '../admin/includes/config.php'; // Include your database configuration file

header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No data source IDs provided']);
    exit;
}

// Sanitize the IDs
$ids = array_map('intval', $input['ids']);
$ids = array_filter($ids, function($id) { return $id > 0; });

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data source IDs']);
    exit;
}

try {
    // Prepare the SQL statement
    // IMPORTANT: Change 'currencies' to 'data_sources'
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM data_sources WHERE id IN ($placeholders)"; // Assuming your table is named 'data_sources'

    $stmt = $con->prepare($sql);

    // Bind parameters
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);

    // Execute the query
    $stmt->execute();

    // Check if any rows were affected
    $affected_rows = $stmt->affected_rows;

    if ($affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted $affected_rows data source(s)"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No data sources were deleted'
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error deleting data sources: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting data sources: ' . $e->getMessage()
    ]);
}
?>