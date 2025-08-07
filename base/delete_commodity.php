<?php
include '../admin/includes/config.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// Ensure it's a POST request and using JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['ids']) || !is_array($data['ids'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing IDs.'
        ]);
        exit;
    }

    $idsToDelete = array_map('intval', $data['ids']);
    $idList = implode(',', $idsToDelete);

    $sql = "DELETE FROM commodities WHERE id IN ($idList)";
    if ($con->query($sql) === TRUE) {
        echo json_encode([
            'success' => true,
            'message' => count($idsToDelete) . " commodity(ies) deleted successfully."
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Deletion failed: ' . $con->error
        ]);
    }

    $con->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
