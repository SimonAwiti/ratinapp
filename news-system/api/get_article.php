<?php
// get_article.php
//
// Powers ArticleDetail.jsx. Fetches exactly one article by id (full body,
// this is the one place that's actually needed) plus a handful of the
// most recent OTHER published articles for the "Read Also" sidebar —
// instead of downloading the entire articles table to find one row and
// compute "other latest 4" client-side.

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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid id."]);
    exit;
}

// ---- the article itself (full body — this page needs it) ----
$stmt = $con->prepare(
    "SELECT id, title, body, category, location, source, image, created_at, updated_at
     FROM news_articles
     WHERE id = ? AND status = 'published'"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$article) {
    http_response_code(404);
    echo json_encode(["error" => "Article not found."]);
    exit;
}

// ---- a few other recent articles for the sidebar (no full body needed) ----
$related_stmt = $con->prepare(
    "SELECT id, title, created_at
     FROM news_articles
     WHERE status = 'published' AND id != ?
     ORDER BY created_at DESC
     LIMIT 4"
);
$related_stmt->bind_param("i", $id);
$related_stmt->execute();
$related = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$related_stmt->close();

echo json_encode([
    "data" => $article,
    "related" => $related,
]);
