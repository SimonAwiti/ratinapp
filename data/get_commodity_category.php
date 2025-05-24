<?php
header('Content-Type: application/json');
include '../admin/includes/config.php';

$response = ['category_id' => null];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $commodityId = (int)$_GET['id'];
    $stmt = $con->prepare("SELECT category_id FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodityId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['category_id'] = $result->fetch_assoc()['category_id'];
    }
}

echo json_encode($response);
?>