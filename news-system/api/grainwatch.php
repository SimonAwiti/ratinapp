<?php
session_start();
header('Content-Type: application/json');

// Enable maximum error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check if user is logged in and is admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('Unauthorized - User not logged in or not admin');
    }

    // Include your existing database connector
    $configPath = '../config/database.php'; // Adjust path based on your structure
    
    if (!file_exists($configPath)) {
        throw new Exception('Database config file not found at: ' . $configPath);
    }
    
    require_once $configPath;
    
    // Create Database instance and get connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if connection was successful
    if ($pdo === null) {
        throw new Exception('Failed to establish database connection');
    }
    
    // Test the connection
    $pdo->query("SELECT 1");
    
    // Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log("GrainWatch API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

function handleGet($pdo) {
    // Get single grainwatch entry
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM grainwatch WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'GrainWatch entry not found']);
        }
        return;
    }
    
    // Get all grainwatch entries with optional search and category filter
    $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : null;
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    
    $sql = "SELECT * FROM grainwatch WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (heading LIKE ? OR description LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['heading']) || !isset($input['description']) || !isset($input['category'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Heading, description and category are required']);
        return;
    }
    
    $heading = trim($input['heading']);
    $description = trim($input['description']);
    $category = trim($input['category']);
    $document_path = isset($input['document_path']) ? $input['document_path'] : null;
    
    // Validate category
    $validCategories = ['grain watch', 'grain standards', 'policy briefs', 'reports'];
    if (!in_array($category, $validCategories)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid category. Must be one of: ' . implode(', ', $validCategories)]);
        return;
    }
    
    if (empty($heading) || empty($description) || empty($category)) {
        http_response_code(400);
        echo json_encode(['error' => 'Heading, description and category cannot be empty']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO grainwatch (heading, description, category, document_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$heading, $description, $category, $document_path]);
        
        $id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'id' => $id,
            'message' => 'GrainWatch entry created successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create entry: ' . $e->getMessage()]);
    }
}

function handlePut($pdo) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['heading']) || !isset($input['description']) || !isset($input['category'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Heading, description and category are required']);
        return;
    }
    
    $heading = trim($input['heading']);
    $description = trim($input['description']);
    $category = trim($input['category']);
    $document_path = isset($input['document_path']) ? $input['document_path'] : null;
    
    // Validate category
    $validCategories = ['grain watch', 'grain standards', 'policy briefs', 'reports'];
    if (!in_array($category, $validCategories)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid category. Must be one of: ' . implode(', ', $validCategories)]);
        return;
    }
    
    if (empty($heading) || empty($description) || empty($category)) {
        http_response_code(400);
        echo json_encode(['error' => 'Heading, description and category cannot be empty']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE grainwatch SET heading = ?, description = ?, category = ?, document_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$heading, $description, $category, $document_path, $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'GrainWatch entry updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'GrainWatch entry not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update entry: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        return;
    }
    
    try {
        // First get the document path to delete the file
        $stmt = $pdo->prepare("SELECT document_path FROM grainwatch WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the database entry
        $stmt = $pdo->prepare("DELETE FROM grainwatch WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            // Delete the associated PDF file if it exists
            if ($entry && $entry['document_path'] && file_exists('../' . $entry['document_path'])) {
                unlink('../' . $entry['document_path']);
            }
            
            echo json_encode(['success' => true, 'message' => 'GrainWatch entry deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'GrainWatch entry not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete entry: ' . $e->getMessage()]);
    }
}
?>