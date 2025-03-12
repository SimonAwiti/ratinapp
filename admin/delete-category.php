<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login']) == 0) { 
    header('location:index.php');
} else {
    // Check if the category ID is provided
    if(isset($_GET['id'])) {
        $category_id = $_GET['id'];
        
        // Delete category from database
        $query = mysqli_query($con, "DELETE FROM categories WHERE id='$category_id'");

        if($query) {
            echo "<script>alert('Category deleted successfully');window.location='manage-category.php';</script>";
        } else {
            echo "<script>alert('Error deleting category');window.location='manage-category.php';</script>";
        }
    } else {
        header('location:manage-categories.php');
    }
}
?>
