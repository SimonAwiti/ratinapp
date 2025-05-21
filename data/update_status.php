<?php
// update_status.php
include '../admin/includes/config.php'; // Adjust path as needed

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $action = $input['action'] ?? '';
    $ids = $input['ids'] ?? [];

    if (empty($ids)) {
        $response['message'] = 'No items selected.';
        echo json_encode($response);
        exit;
    }

    // Sanitize IDs for SQL IN clause
    $id_list = implode(',', array_map('intval', $ids));

    $new_status = '';
    $requires_pre_check = false; // Flag to indicate if a status check is needed before update
    $required_current_status = ''; // The status items must be in for the action to proceed

    switch ($action) {
        case 'approve':
            $new_status = 'approved';
            // No pre-check for 'approve', can go from 'pending'
            break;
        case 'publish':
            $new_status = 'published';
            $requires_pre_check = true;
            $required_current_status = 'approved'; // Must be approved to publish
            break;
        case 'unpublish':
            $new_status = 'unpublished'; // Changed from 'pending' to 'unpublished'
            $requires_pre_check = true;
            $required_current_status = 'published'; // Must be published to unpublish
            break;
        case 'delete':
            $sql = "DELETE FROM market_prices WHERE id IN ($id_list)";
            if ($con->query($sql) === TRUE) {
                $response['success'] = true;
                $response['message'] = 'Items deleted successfully.';
            } else {
                $response['message'] = 'Error deleting items: ' . $con->error;
            }
            echo json_encode($response);
            $con->close();
            exit;
        default:
            $response['message'] = 'Invalid action.';
            echo json_encode($response);
            exit;
    }

    // Perform pre-check if required (for publish and unpublish actions)
    if ($requires_pre_check) {
        $check_sql = "SELECT COUNT(*) as incorrect_status_count FROM market_prices WHERE id IN ($id_list) AND status != '$required_current_status'";
        $check_result = $con->query($check_sql);
        if ($check_result && $check_result->fetch_assoc()['incorrect_status_count'] > 0) {
            $response['message'] = "Cannot " . $action . ". One or more selected items are not in '$required_current_status' status.";
            echo json_encode($response);
            $con->close();
            exit;
        }
    }

    // Proceed with status update
    $sql = "UPDATE market_prices SET status = '$new_status' WHERE id IN ($id_list)";

    if ($con->query($sql) === TRUE) {
        $response['success'] = true;
        $response['message'] = 'Status updated successfully.';
    } else {
        $response['message'] = 'Error updating status: ' . $con->error;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
$con->close();
?>