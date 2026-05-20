<?php
// api/articles_optimized.php - Optimized with server-side pagination
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include config
$paths = ['../admin/includes/config.php', '../../admin/includes/config.php', '../../../admin/includes/config.php'];
$config_loaded = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    http_response_code(500);
    echo json_encode(['error' => 'Config not found']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(5, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';
    
    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if (!empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    if (!empty($search)) {
        $where[] = "(title LIKE ? OR category LIKE ? OR location LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count first (for pagination)
    $count_query = "SELECT COUNT(*) as total FROM news_articles $where_clause";
    $stmt = $con->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_count = $total_result->fetch_assoc()['total'];
    $stmt->close();
    
    // Get paginated data - only select necessary columns, not the full body content
    $query = "SELECT id, title, category, location, image, status, created_at, 
                     LEFT(body, 200) as body_preview 
              FROM news_articles 
              $where_clause 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $con->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $articles = [];
    while ($row = $result->fetch_assoc()) {
        $articles[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'data' => $articles,
        'total' => $total_count,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total_count / $limit)
    ]);
    exit;
}
?>