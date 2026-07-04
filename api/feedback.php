<?php
/**
 * feedback.php — PROPOSED public endpoint (NEW)
 * ─────────────────────────────────────────────────────────────
 * Accepts POST from the FeedbackWidget on the public site and stores it
 * for review in the admin panel. No auth required (anonymous feedback is
 * allowed — name/email are optional).
 *
 * Run this once against your RATIN database before deploying:
 *
 *   CREATE TABLE IF NOT EXISTS site_feedback (
 *     id           INT AUTO_INCREMENT PRIMARY KEY,
 *     category     ENUM('general','data','bug','idea') NOT NULL DEFAULT 'general',
 *     name         VARCHAR(150) NULL,
 *     email        VARCHAR(190) NULL,
 *     message      TEXT NOT NULL,
 *     rating       TINYINT NULL,              -- 1-5, nullable
 *     page_url     VARCHAR(500) NULL,
 *     status       ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
 *     submitted_at DATETIME NOT NULL,
 *     created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 * POST body (application/json):
 *   { category, name, email, message, rating, page_url, submitted_at }
 *
 * Response: { success: true, id } | { success: false, msg }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'msg' => 'Method not allowed']);
    exit;
}

require_once '../admin/includes/config.php'; // reuse existing mysqli $con, same relative include style as processor_prices_detailed.php

if (!isset($con) || !($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => 'Database connection not available']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    echo json_encode(['success' => false, 'msg' => 'Invalid payload']);
    exit;
}

$allowedCategories = ['general', 'data', 'bug', 'idea'];
$category = in_array($body['category'] ?? '', $allowedCategories) ? $body['category'] : 'general';
$name     = isset($body['name']) && trim($body['name']) !== '' ? trim($body['name']) : null;
$email    = isset($body['email']) && trim($body['email']) !== '' ? trim($body['email']) : null;
$message  = trim($body['message'] ?? '');
$rating   = isset($body['rating']) && is_numeric($body['rating']) ? max(1, min(5, (int)$body['rating'])) : null;
$pageUrl  = isset($body['page_url']) ? substr(trim($body['page_url']), 0, 500) : null;
$submittedAt = isset($body['submitted_at']) ? date('Y-m-d H:i:s', strtotime($body['submitted_at'])) : date('Y-m-d H:i:s');

if ($message === '') {
    echo json_encode(['success' => false, 'msg' => 'Message is required']);
    exit;
}
if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'msg' => 'Invalid email']);
    exit;
}

// Basic anti-spam: cap message length, strip control characters.
$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $message);
$message = substr($message, 0, 4000);

$stmt = $con->prepare(
    "INSERT INTO site_feedback (category, name, email, message, rating, page_url, submitted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => 'Query prepare failed: ' . $con->error]);
    exit;
}
$stmt->bind_param('sssssss', $category, $name, $email, $message, $rating, $pageUrl, $submittedAt);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => 'Could not save feedback']);
}
$stmt->close();
