<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login']) == 0) { 
    header('location:index.php');
} else {
    if(isset($_GET['id'])) {
        $post_id = $_GET['id'];
        $delete_query = "DELETE FROM articles WHERE id='$post_id'";
        $result = mysqli_query($con, $delete_query);
        
        if($result) {
            header('location: manage-posts.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Ratin.net | Delete Post</title>

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
                                <h4 class="page-title">Delete Post</h4>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation -->
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title"><b>Are you sure you want to delete this post?</b></h4>
                                <hr />
                                <a href="delete-post.php?id=<?php echo $_GET['id']; ?>" class="btn btn-danger">Yes, Delete</a>
                                <a href="manage-posts.php" class="btn btn-primary">No, Go Back</a>
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
