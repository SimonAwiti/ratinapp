<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$ids = array_map('intval', $data['ids']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

require 'db.php'; // your db connection

$stmt = $pdo->prepare("DELETE FROM markets WHERE id IN ($placeholders)");
if ($stmt->execute($ids)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
?>
