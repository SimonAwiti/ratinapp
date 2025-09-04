<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include_once __DIR__ . '/../config/database.php';


$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $category = $_GET['category'] ?? '';
    
    if (empty($category)) {
        http_response_code(400);
        echo json_encode(['message' => 'Category parameter is required']);
        exit;
    }

    try {
        $query = "SELECT * FROM news_articles WHERE category = ? AND status = 'published' ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $category);
        $stmt->execute();

        $articles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $articles[] = $row;
        }

        echo json_encode([
            'category' => $category,
            'count' => count($articles),
            'articles' => $articles
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
}
?>
