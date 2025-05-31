<?php
session_start();
include '../admin/includes/config.php';

// Check if the request is POST and has an ID
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Verify this miller belongs to the current session's miller_name
    if (isset($_SESSION['miller_name'])) {
        $miller_name = $_SESSION['miller_name'];
        $stmt = $con->prepare("DELETE FROM millers WHERE id = ? AND miller_name = ?");
        $stmt->bind_param("is", $id, $miller_name);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Session expired']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>