<?php
// article_list.php
//
// Powers the public /news/articles listing page (Articles.jsx). Same fix
// as latest_articles.php, applied to the paginated listing:
//   - Only the columns the article cards render (no `body`/excerpt at all —
//     the cards don't show a snippet, just title/tags/image).
//   - Real SQL pagination (LIMIT/OFFSET) + optional category filter,
//     instead of downloading every article and slicing in the browser.
//   - Distinct categories are returned alongside the page of articles so
//     the frontend doesn't need a second full-table scan just to build
//     the filter buttons.
//
// Same config include assumption as latest_articles.php — adjust the
// path if your api/ folder's relative location to admin/ differs.

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$config_path = '../../admin/includes/config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(["error" => "Configuration file not found."]);
    exit;
}
require_once $config_path;

if (!$con) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed."]);
    exit;
}

// ---- params ----
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 24;
if ($limit < 1) $limit = 24;
if ($limit > 100) $limit = 100;

$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$allowed_sort = ['created_at', 'title'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'created_at';
$dir  = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

$offset = ($page - 1) * $limit;

// ---- count (for total_pages) ----
$count_sql = "SELECT COUNT(*) AS total FROM news_articles WHERE status = 'published'";
if ($category !== '') {
    $count_sql .= " AND category = ?";
}
$count_stmt = $con->prepare($count_sql);
if ($category !== '') {
    $count_stmt->bind_param("s", $category);
}
$count_stmt->execute();
$total_items = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = $total_items > 0 ? (int) ceil($total_items / $limit) : 1;

// ---- page of articles ----
$sql = "SELECT id, title, category, location, source, image, created_at
        FROM news_articles
        WHERE status = 'published'";
if ($category !== '') {
    $sql .= " AND category = ?";
}
$sql .= " ORDER BY $sort $dir LIMIT ? OFFSET ?";

$stmt = $con->prepare($sql);
if ($category !== '') {
    $stmt->bind_param("sii", $category, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---- distinct categories (for filter buttons) ----
$categories = [];
$cat_result = $con->query(
    "SELECT DISTINCT category FROM news_articles
     WHERE status = 'published' AND category IS NOT NULL AND category != ''
     ORDER BY category"
);
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

echo json_encode([
    "data" => $articles,
    "total" => $total_items,
    "total_pages" => $total_pages,
    "page" => $page,
    "categories" => $categories,
]);
