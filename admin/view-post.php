<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login']) == 0) { 
    header('location:index.php');
} else {
    // Fetch post details based on ID
    if(isset($_GET['id'])) {
        $post_id = $_GET['id'];
        $query = mysqli_query($con, "SELECT a.id, a.title, a.content, c.category, u.AdminUserName as created_by, a.created_on, a.published 
                                     FROM articles a 
                                     JOIN categories c ON a.category = c.id 
                                     JOIN tbladmin u ON a.created_by = u.id 
                                     WHERE a.id='$post_id'");
        $row = mysqli_fetch_assoc($query);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Ratin.net | View Post</title>

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
                                <h4 class="page-title">View Post</h4>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Post View -->
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title"><b>Post Details</b></h4>
                                <hr />
                                <p><strong>Title:</strong> <?php echo $row['title']; ?></p>
                                <p><strong>Category:</strong> <?php echo $row['category']; ?></p>
                                <p><strong>Created By:</strong> <?php echo $row['created_by']; ?></p>
                                <p><strong>Created On:</strong> <?php echo $row['created_on']; ?></p>
                                <p><strong>Published:</strong> <?php echo $row['published'] ? 'Yes' : 'No'; ?></p>
                                <p><strong>Content:</strong><br /><?php echo nl2br($row['content']); ?></p>

                                <a href="manage-posts.php" class="btn btn-primary">Back to Posts</a>
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
