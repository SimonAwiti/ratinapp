<?php
// api/commodities_data.php

session_start();
include '../admin/includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Main query
    $query = "SELECT c.id, c.hs_code, cc.name AS category, 
              c.commodity_name, c.variety, c.image_url
              FROM commodities c
              JOIN commodity_categories cc ON c.category_id = cc.id";
    
    $result = $con->query($query);
    if (!$result) {
        throw new Exception("Database query failed: " . $con->error);
    }
    
    $commodities = $result->fetch_all(MYSQLI_ASSOC);

    // Pagination
    $itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $totalItems = count($commodities);
    $totalPages = ceil($totalItems / $itemsPerPage);
    $startIndex = ($page - 1) * $itemsPerPage;
    $commodities_paged = array_slice($commodities, $startIndex, $itemsPerPage);

    // Counts for summary boxes
    $counts = [
        'total' => $totalItems,
        'cereals' => getCountByCategory($con, 'Cereals'),
        'pulses' => getCountByCategory($con, 'Pulses'),
        'oilSeeds' => getCountByCategory($con, 'Oil seeds')
    ];

    echo json_encode([
        'success' => true,
        'commodities' => $commodities_paged,
        'pagination' => [
            'currentPage' => $page,
            'itemsPerPage' => $itemsPerPage,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages
        ],
        'counts' => $counts
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

function getCountByCategory($con, $categoryName) {
    $query = "SELECT COUNT(*) AS total FROM commodities 
              WHERE category_id = (SELECT id FROM commodity_categories WHERE name = ?)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $categoryName);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];
}