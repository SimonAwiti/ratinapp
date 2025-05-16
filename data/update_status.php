<?php
include '../admin/includes/config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$ids = $input['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit;
}

$validActions = ['approve', 'publish', 'delete'];
if (!in_array($action, $validActions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$idList = implode(',', array_map('intval', $ids));

if ($action === 'delete') {
    $sql = "DELETE FROM market_prices WHERE id IN ($idList)";
} else {
    $newStatus = $action === 'approve' ? 'approved' : 'published';
    $sql = "UPDATE market_prices SET status = '$newStatus' WHERE id IN ($idList)";
}

if ($con->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $con->error]);
}
?>