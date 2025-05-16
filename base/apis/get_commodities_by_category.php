<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include DB config
include '../../admin/includes/config.php';

// Initialize response array
$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

// Get category_id from request
$categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if ($categoryId <= 0) {
    $response['message'] = 'Invalid category ID.';
    echo json_encode($response);
    exit;
}

try {
    // Prepare and execute query
    $stmt = $con->prepare("
        SELECT id, commodity_name, variety, units, hs_code, commodity_alias, country, image_url, created_at, category_id
        FROM commodities
        WHERE category_id = ?
    ");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch data
    $commodities = $result->fetch_all(MYSQLI_ASSOC);

    $response['success'] = true;
    $response['data'] = $commodities;

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
