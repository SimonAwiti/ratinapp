<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include database configuration
include '../../admin/includes/config.php'; // Database connection
// Initialize response
$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    // Query to fetch all categories
    $query = "SELECT id, name FROM commodity_categories ORDER BY name ASC";
    $result = $con->query($query);

    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        $response['success'] = true;
        $response['data'] = $categories;
    } else {
        $response['message'] = "Failed to fetch categories.";
    }

} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

// Output JSON response
echo json_encode($response);
?>
