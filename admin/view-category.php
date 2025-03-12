<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login']) == 0) { 
    header('location:index.php');
} else {
    // Fetch category details based on ID
    if(isset($_GET['id'])) {
        $category_id = $_GET['id'];
        $query = mysqli_query($con, "SELECT * FROM categories WHERE id='$category_id'");
        $row = mysqli_fetch_assoc($query);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Ratin.net | View Category</title>

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
    <!-- Begin page -->
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
                                <h4 class="page-title">View Category</h4>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Category View -->
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title"><b>Category Details</b></h4>
                                <hr />
                                <p><strong>Category Name:</strong> <?php echo $row['category']; ?></p>
                                <p><strong>Description:</strong> <?php echo $row['description']; ?></p>

                                <a href="manage-categories.php" class="btn btn-primary">Back to Categories</a>
                            </div>
                        </div>
                    </div>

                </div> <!-- container -->
            </div> <!-- content -->

            <?php include('includes/footer.php');?>
        </div>
    </div>

    <!-- jQuery  -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>

<?php } ?>
