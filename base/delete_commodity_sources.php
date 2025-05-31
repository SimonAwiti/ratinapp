<?php
session_start();
include '../admin/includes/config.php'; // Your DB connection

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $ids = $data['ids'] ?? [];

    if (empty($ids)) {
        $response['message'] = 'No IDs provided for deletion.';
        echo json_encode($response);
        exit;
    }

    // Ensure all IDs are integers to prevent SQL injection
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $con->begin_transaction();

    try {
        $stmt = $con->prepare("DELETE FROM commodity_sources WHERE id IN ($placeholders)");
        if ($stmt === false) {
            throw new Exception("Failed to prepare statement: " . $con->error);
        }

        // Dynamically bind parameters based on the number of IDs
        $types = str_repeat('i', count($ids)); // 'i' for integer
        $stmt->bind_param($types, ...$ids);

        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $con->commit();
            $response['success'] = true;
            $response['message'] = $stmt->affected_rows . ' commodity source(s) deleted successfully.';
        } else {
            $con->rollback();
            $response['message'] = 'No commodity sources found with the provided IDs, or no rows were affected.';
        }
        $stmt->close();

    } catch (Exception $e) {
        $con->rollback();
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$con->close();
echo json_encode($response);
?>