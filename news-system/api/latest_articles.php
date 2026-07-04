<?php
// latest_articles.php
//
// Purpose-built for the homepage widgets (Latest News carousel, homepage
// article grid, Insight of the Week). Those only ever need a handful of
// rows, so this endpoint:
//   1. Selects ONLY the columns the widgets actually render (no full
//      `body` column — that's what was making articles.php slow: every
//      article's full rich-text HTML, including any embedded images,
//      was being sent for EVERY row, EVERY time).
//   2. Truncates the body server-side with SUBSTRING() into a small
//      `excerpt` field — same trick your user_articles.php already uses.
//   3. Filters to status='published' and sorts by created_at using a
//      LIMIT, so the database only ever touches/returns a few rows
//      instead of the whole table.
//
// Adjust the config include path below to match wherever your existing
// api/articles.php pulls its $con (mysqli) connection from — I mirrored
// the path used in your content_management.php / user_articles.php
// (`../admin/includes/config.php`). If this file lives somewhere else
// relative to admin/, just fix the require path.

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");   // public, read-only, published content
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
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 3;
if ($limit < 1) $limit = 1;
if ($limit > 20) $limit = 20; // sane cap, this endpoint is for small widgets only

$allowed_sort = ['created_at', 'updated_at'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'created_at';
$dir  = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

// ---- query ----
// Only the columns the widgets use, plus a short SUBSTRING excerpt
// instead of the full `body`. This is the single biggest win: it avoids
// pulling potentially megabytes of rich-text/base64-image HTML per row.
$sql = "SELECT id, title, category, location, source, image,
               created_at, updated_at,
               SUBSTRING(body, 1, 600) AS excerpt
        FROM news_articles
        WHERE status = 'published'
        ORDER BY $sort $dir
        LIMIT ?";

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Query preparation failed."]);
    exit;
}

$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();
$articles = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(["data" => $articles]);
