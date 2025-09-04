<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once __DIR__ . '/../config/database.php';


class ArticleAPI {
    private $conn;
    private $table_name = "news_articles";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all articles with optional filtering
    public function getArticles($category = null, $status = null, $search = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE 1=1";
        $params = [];

        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }

        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        if ($search) {
            $query .= " AND (title LIKE ? OR body LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        $articles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $articles[] = $row;
        }

        return $articles;
    }

    // Get articles by category
    public function getArticlesByCategory($category) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE category = ? AND status = 'published' ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $category);
        $stmt->execute();

        $articles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $articles[] = $row;
        }

        return $articles;
    }

    // Get single article
    public function getArticle($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create article
    public function createArticle($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                 (title, body, image, category, source, location, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['title'],
            $data['body'],
            $data['image'] ?? null,
            $data['category'],
            $data['source'] ?? null,
            $data['location'] ?? null,
            $data['status'] ?? 'draft'
        ]);
    }

    // Update article
    public function updateArticle($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                 SET title = ?, body = ?, image = ?, category = ?, source = ?, location = ?, status = ?
                 WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['title'],
            $data['body'],
            $data['image'] ?? null,
            $data['category'],
            $data['source'] ?? null,
            $data['location'] ?? null,
            $data['status'],
            $id
        ]);
    }

    // Update article status
    public function updateArticleStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$status, $id]);
    }

    // Delete article
    public function deleteArticle($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}

// Handle API requests
$database = new Database();
$db = $database->getConnection();
$articleAPI = new ArticleAPI($db);

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));

try {
    switch($method) {
        case 'GET':
            if (isset($request[0]) && is_numeric($request[0])) {
                // Get single article
                $article = $articleAPI->getArticle($request[0]);
                if ($article) {
                    echo json_encode($article);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Article not found']);
                }
            } else {
                // Get all articles with optional filters
                $category = $_GET['category'] ?? null;
                $status = $_GET['status'] ?? null;
                $search = $_GET['search'] ?? null;
                
                $articles = $articleAPI->getArticles($category, $status, $search);
                echo json_encode($articles);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($articleAPI->createArticle($data)) {
                http_response_code(201);
                echo json_encode(['message' => 'Article created successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Failed to create article']);
            }
            break;

        case 'PUT':
            if (isset($request[0]) && is_numeric($request[0])) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                if ($articleAPI->updateArticle($request[0], $data)) {
                    echo json_encode(['message' => 'Article updated successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Failed to update article']);
                }
            }
            break;

        case 'DELETE':
            if (isset($request[0]) && is_numeric($request[0])) {
                if ($articleAPI->deleteArticle($request[0])) {
                    echo json_encode(['message' => 'Article deleted successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Failed to delete article']);
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage()]);
}
?>