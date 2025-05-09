<?php
include '../admin/includes/config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON data from the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['ids']) && is_array($data['ids'])) {
        $idsToDelete = $data['ids'];

        // Sanitize the IDs (very important!)
        $safeIds = array_map('intval', $idsToDelete);
        $idList = implode(',', $safeIds);

        // Construct the DELETE queries.  Execute each DELETE separately.
        $delete_queries = [
            "DELETE FROM markets WHERE id IN ($idList)",
            "DELETE FROM border_points WHERE id IN ($idList)",
            "DELETE FROM miller_details WHERE id IN ($idList)"
        ];

        $success = true; // Assume success initially
        $errors = [];

        // Use a transaction for atomicity (all deletes succeed or none)
        $con->begin_transaction();

        foreach ($delete_queries as $sql) {
            if ($con->query($sql) !== TRUE) {
                $success = false;
                $errors[] = "Error deleting: " . $con->error;
            }
        }

        if ($success) {
            $con->commit();
            echo json_encode(['success' => true, 'message' => 'Tradepoints deleted successfully.']);
        } else {
            $con->rollback();
            $errorMessage = implode('; ', $errors); // Combine errors into one message.
            echo json_encode(['success' => false, 'message' => 'Errors deleting tradepoints: ' . $errorMessage]);
        }


    } else {
        // If 'ids' is not provided or is not an array
        echo json_encode(['success' => false, 'message' => 'Invalid data: "ids" array not provided.']);
    }
} else {
    // If the request method is not POST
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
}

$con->close();
?>
