<?php
include '../admin/includes/config.php';

header('Content-Type: application/json');

if (isset($_POST['commodity'])) {
    $commodity = $_POST['commodity'];
    
    try {
        $query = "SELECT id, commodity_name FROM commodities WHERE commodity = ?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $commodities = [];
        while ($row = $result->fetch_assoc()) {
            $commodities[] = [
                'id' => $row['id'],
                'commodity_name' => $row['commodity_name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'commodities' => $commodities
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Category not provided'
    ]);
}