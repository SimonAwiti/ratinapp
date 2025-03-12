<?php
session_start();
include('includes/config.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (strlen($_SESSION['login']) == 0 || !isset($_SESSION['userid'])) {
    header('location:index.php');
    exit;
}

if(isset($_GET['id'])) {
    $bulletin_id = $_GET['id'];

    // Query to delete the bulletin
    $query = "DELETE FROM bulletins WHERE id='$bulletin_id'";
    $result = mysqli_query($con, $query);

    if($result) {
        header('location:manage-bulletins.php');
    } else {
        echo "Error deleting bulletin: " . mysqli_error($con);
    }
}
?>
