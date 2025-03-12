<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login']) == 0) { 
    header('location:index.php');
} else {
    // Handle form submission for updating category
    if(isset($_POST['update'])) {
        $category_id = $_GET['id'];
        $category_name = $_POST['category_name'];
        $description = $_POST['description'];

        // Update category in database
        $query = mysqli_query($con, "UPDATE categories SET category='$category_name', description='$description' WHERE id='$category_id'");

        if($query) {
            echo "<script>alert('Category updated successfully');</script>";
        } else {
            echo "<script>alert('Error updating category');</script>";
        }
    }

    // Fetch category data for editing
    if(isset($_GET['id'])) {
        $category_id = $_GET['id'];
        $query = mysqli_query($con, "SELECT * FROM categories WHERE id='$category_id'");
        $row = mysqli_fetch_assoc($query);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Ratin.net | Edit Category</title>

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/core.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/components.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/pages.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/menu.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/responsive.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/modernizr.min.js"></script>
</head>
<body class="fixed-left">
    <div id="wrapper">
        <!-- Top Bar Start -->
        <?php include('includes/topheader.php');?>
        <!-- Top Bar End -->

        <!-- Left Sidebar Start -->
        <?php include('includes/leftsidebar.php');?>
        <!-- Left Sidebar End -->

        <div class="content-page">
            <div class="content">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="page-title-box">
                                <h4 class="page-title">Edit Category</h4>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title"><b>Edit Category</b></h4>
                                <hr />

                                <form method="post" action="edit-category.php?id=<?php echo $_GET['id']; ?>">
                                    <div class="form-group">
                                        <label for="category_name">Category Name</label>
                                        <input type="text" name="category_name" class="form-control" value="<?php echo $row['category']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea name="description" class="form-control" rows="5" required><?php echo $row['description']; ?></textarea>
                                    </div>

                                    <button type="submit" name="update" class="btn btn-success">Update Category</button>
                                    <a href="manage-categories.php" class="btn btn-primary">Cancel</a>
                                </form>

                            </div>
                        </div>
                    </div>

                </div> <!-- container -->
            </div> <!-- content -->

            <?php include('includes/footer.php');?>
        </div>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>

<?php } ?>