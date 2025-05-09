<?php
include '../admin/includes/config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    $idsToDelete = $_POST['ids'];

    echo '<pre>IDs received by delete_commodity.php:</pre>';
    echo '<pre>';
    var_dump($idsToDelete);
    echo '</pre>';

    if (!empty($idsToDelete) && is_array($idsToDelete)) {
        // Sanitize the IDs to prevent SQL injection
        $safeIds = array_map('intval', $idsToDelete);
        $idList = implode(',', $safeIds);

        $sql = "DELETE FROM commodities WHERE id IN ($idList)";

        echo '<pre>SQL Query:</pre>';
        echo '<pre>' . $sql . '</pre>';

        if ($con->query($sql) === TRUE) {
            echo '<pre>Deletion successful.</pre>';
            header("Location: commodities.php?delete_success=true");
            exit();
        } else {
            echo '<pre>Deletion failed. MySQL Error:</pre>';
            echo '<pre>' . $con->error . '</pre>';
            header("Location: commodities.php?delete_error=" . urlencode($con->error));
            exit();
        }
    } else {
        echo '<pre>No valid IDs to delete.</pre>';
        header("Location: commodities.php?delete_empty=true");
        exit();
    }

    $con->close();
} else {
    echo '<pre>Invalid request to delete_commodity.php</pre>';
    header("Location: sidebar.php");
    exit();
}
?>