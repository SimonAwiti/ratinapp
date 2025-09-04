<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include_once __DIR__ . '/../config/database.php';


if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    $database = new Database();
    $db = $database->getConnection();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;

    if (!$id || !$status) {
        http_response_code(400);
        echo json_encode(['message' => 'ID and status are required']);
        exit;
    }

    if (!in_array($status, ['published', 'unpublished', 'draft'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid status']);
        exit;
    }

    try {
        $query = "UPDATE news_articles SET status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$status, $id])) {
            echo json_encode(['message' => 'Article status updated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Failed to update article status']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
}
?>