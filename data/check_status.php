<?php
include '../admin/includes/config.php'; // Include your database connection

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['ids']) && is_array($data['ids'])) {
        $ids = $data['ids'];
        $idList = implode(',', array_map('intval', $ids)); // Sanitize IDs

        $sql = "SELECT status FROM market_prices WHERE id IN ($idList)";
        $result = $con->query($sql);

        if ($result) {
            $allApproved = true;
            while ($row = $result->fetch_assoc()) {
                if ($row['status'] !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }
            echo json_encode(['success' => true, 'allApproved' => $allApproved]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error checking statuses: ' . $con->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$con->close();
?>