<?php
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include your database connector
    require_once '../config/database.php';
    
    // Create Database instance and get connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if connection was successful
    if ($pdo === null) {
        throw new Exception('Failed to establish database connection');
    }
    
    // Get all grainwatch entries ordered by most recent
    $stmt = $pdo->prepare("SELECT * FROM grainwatch ORDER BY created_at DESC");
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert document_path to full URLs
    $baseUrl = 'https://ratin.net/ratinapp/news-system/api/';
    foreach ($results as &$result) {
        if (!empty($result['document_path'])) {
            $result['document_url'] = $baseUrl . $result['document_path'];
        } else {
            $result['document_url'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>