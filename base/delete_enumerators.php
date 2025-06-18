<?php
// delete_enumerators.php
session_start();
include '../admin/includes/config.php'; // Adjust path if necessary

// You might not need to set JSON header if you're redirecting afterwards
// header('Content-Type: application/json'); // Removed this line if you want to redirect

$response = ['status' => 'error', 'message' => 'An unknown error occurred.']; // Prepare for status message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? []; // **CHANGED: Get IDs from $_POST**

    if (empty($ids) || !is_array($ids)) {
        $_SESSION['error_message'] = 'No enumerator IDs provided for deletion.';
        header("Location: sidebar.php"); // Redirect back
        exit;
    }

    // Sanitize IDs to ensure they are integers
    $clean_ids = array_map('intval', $ids);
    $clean_ids = array_filter($clean_ids, function($id) { return $id > 0; });

    if (empty($clean_ids)) {
        $_SESSION['error_message'] = 'Invalid enumerator IDs provided.';
        header("Location: sidebar.php");
        exit;
    }

    $ids_placeholder = implode(',', array_fill(0, count($clean_ids), '?'));

    $stmt = $con->prepare("DELETE FROM enumerators WHERE id IN ($ids_placeholder)");

    if ($stmt) {
        $types = str_repeat('i', count($clean_ids));
        $stmt->bind_param($types, ...$clean_ids);

        if ($stmt->execute()) {
            $deleted_count = $stmt->affected_rows;
            $_SESSION['success_message'] = "$deleted_count enumerator(s) deleted successfully."; // Use session for messages
        } else {
            $_SESSION['error_message'] = 'Database error during deletion: ' . $stmt->error;
            error_log("Enumerator deletion error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = 'Failed to prepare database statement: ' . $con->error;
        error_log("Statement preparation error for deletion: " . $con->error);
    }
} else {
    $_SESSION['error_message'] = 'Invalid request method. Only POST is allowed.';
}

$con->close();
header("Location: sidebar.php"); // Always redirect back after processing
exit;
?>