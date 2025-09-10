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

class InsightAPI {
    private $conn;
    private $table_name = "insights";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all insights with optional filtering
    public function getInsights($search = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE 1=1";
        $params = [];

        if ($search) {
            $query .= " AND (title LIKE ? OR body LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        $insights = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insights[] = $row;
        }

        return $insights;
    }

    // Get single insight
    public function getInsight($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create insight
    public function createInsight($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                 (title, body, created_at) 
                 VALUES (?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['title'],
            $data['body']
        ]);
    }

    // Update insight
    public function updateInsight($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                 SET title = ?, body = ?, updated_at = NOW()
                 WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            $data['title'],
            $data['body'],
            $id
        ]);
    }

    // Delete insight
    public function deleteInsight($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
}

// Handle API requests
$database = new Database();
$db = $database->getConnection();
$insightAPI = new InsightAPI($db);

$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_SERVER['PATH_INFO']) ? explode('/', trim($_SERVER['PATH_INFO'], '/')) : [];

try {
    switch($method) {
        case 'GET':
            if (isset($request[0]) && is_numeric($request[0])) {
                // Get single insight
                $insight = $insightAPI->getInsight($request[0]);
                if ($insight) {
                    echo json_encode($insight);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Insight not found']);
                }
            } else {
                // Get all insights with optional search filter
                $search = $_GET['search'] ?? null;
                
                $insights = $insightAPI->getInsights($search);
                echo json_encode($insights);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($insightAPI->createInsight($data)) {
                http_response_code(201);
                
                // Return the created insight with ID
                $insightId = $db->lastInsertId();
                $insight = $insightAPI->getInsight($insightId);
                echo json_encode($insight);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Failed to create insight']);
            }
            break;

        case 'PUT':
            if (isset($request[0]) && is_numeric($request[0])) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                if ($insightAPI->updateInsight($request[0], $data)) {
                    // Return the updated insight
                    $insight = $insightAPI->getInsight($request[0]);
                    echo json_encode($insight);
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Failed to update insight']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Insight ID is required']);
            }
            break;

        case 'DELETE':
            if (isset($request[0]) && is_numeric($request[0])) {
                if ($insightAPI->deleteInsight($request[0])) {
                    echo json_encode(['message' => 'Insight deleted successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Failed to delete insight']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Insight ID is required']);
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