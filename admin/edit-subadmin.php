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
        $query = mysqli_query($con, "SELECT * FROM articles WHERE id='$post_id'");
        $row = mysqli_fetch_assoc($query);
    }

    // Update post on form submission
    if(isset($_POST['submit'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $category = $_POST['category'];
        $published = isset($_POST['published']) ? 1 : 0;
        $update_query = "UPDATE articles SET title='$title', content='$content', category='$category', published='$published' WHERE id='$post_id'";
        $result = mysqli_query($con, $update_query);
        
        if ($result) {
            header('location: manage-posts.php');
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Ratin.net | Edit Post</title>

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
                                <h4 class="page-title">Edit Post</h4>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Post Edit Form -->
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title"><b>Edit Post</b></h4>
                                <hr />
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="title">Title</label>
                                        <input type="text" name="title" class="form-control" value="<?php echo $row['title']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <input type="text" name="category" class="form-control" value="<?php echo $row['category']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="content">Content</label>
                                        <textarea name="content" class="form-control" rows="5" required><?php echo $row['content']; ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="published">Published</label>
                                        <input type="checkbox" name="published" <?php echo $row['published'] ? 'checked' : ''; ?>>
                                    </div>
                                    <button type="submit" name="submit" class="btn btn-primary">Save Changes</button>
                                </form>

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
