<?php 
session_start();
include('includes/config.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);
$msg = "";  // Initialize $msg
$error = "";  // Initialize $error

if(strlen($_SESSION['login']) == 0 || !isset($_SESSION['userid'])) { 
    header('location:index.php');
    exit;
} else {
    // Fetch post details based on ID
    if(isset($_GET['id'])) {
        $post_id = $_GET['id'];
        $query = mysqli_query($con, "SELECT * FROM articles WHERE id='$post_id'");
        $row = mysqli_fetch_assoc($query);
    }

    // Update post on form submission
    if(isset($_POST['submit'])) {
        $posttitle = mysqli_real_escape_string($con, $_POST['posttitle']);
        $catid = $_POST['category'];
        $postdescription = mysqli_real_escape_string($con, $_POST['postdescription']);
        $postcontent = mysqli_real_escape_string($con, $_POST['postcontent']);
        $published = isset($_POST['published']) ? 1 : 0;
        
        // Handle image upload
        $imgfile = $_FILES["postimage"]["name"];
        if ($imgfile != "") {
            $extension = substr($imgfile, strlen($imgfile)-4, strlen($imgfile));
            $allowed_extensions = array(".jpg", ".jpeg", ".png", ".gif", ".pdf");
            if(in_array($extension, $allowed_extensions)) {
                $imgnewfile = md5($imgfile) . $extension;
                if (move_uploaded_file($_FILES["postimage"]["tmp_name"], "postimages/" . $imgnewfile)) {
                    $query = "UPDATE articles SET category='$catid', title='$posttitle', description='$postdescription', content='$postcontent', file='$imgnewfile', published='$published' WHERE id='$post_id'";
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid format. Only jpg / jpeg / png / gif / pdf format allowed.";
            }
        } else {
            $query = "UPDATE articles SET category='$catid', title='$posttitle', description='$postdescription', content='$postcontent', published='$published' WHERE id='$post_id'";
        }

        if (empty($error)) {
            $result = mysqli_query($con, $query);
            if (!$result) {
                echo "Error executing query: " . mysqli_error($con);
            } else {
                $msg = "Post successfully updated.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A fully featured admin theme which can be used to build CRM, CMS, etc.">
    <meta name="author" content="Coderthemes">

    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <!-- App title -->
    <title>Ratin.net | Edit Post</title>

    <!-- Summernote css -->
    <link href="../plugins/summernote/summernote.css" rel="stylesheet" />

    <!-- Select2 -->
    <link href="../plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />

    <!-- Jquery filer css -->
    <link href="../plugins/jquery.filer/css/jquery.filer.css" rel="stylesheet" />
    <link href="../plugins/jquery.filer/css/themes/jquery.filer-dragdropbox-theme.css" rel="stylesheet" />

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/core.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/components.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/pages.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/menu.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/responsive.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../plugins/switchery/switchery.min.css">
    <script src="assets/js/modernizr.min.js"></script>
</head>

<body class="fixed-left">
    <!-- Begin page -->
    <div id="wrapper">
        <!-- Top Bar Start -->
        <?php include('includes/topheader.php');?>
        <!-- Left Sidebar Start -->
        <?php include('includes/leftsidebar.php');?>

        <div class="content-page">
            <div class="content">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="page-title-box">
                                <h4 class="page-title">Edit Post</h4>
                                <ol class="breadcrumb p-0 m-0">
                                    <li>
                                        <a href="#">Post</a>
                                    </li>
                                    <li class="active">
                                        Edit Post
                                    </li>
                                </ol>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
                    <div class="row">
                        <div class="col-sm-6">  
                            <?php if($msg) { ?>
                            <div class="alert alert-success" role="alert">
                                <strong>Well done!</strong> <?php echo htmlentities($msg);?>
                            </div>
                            <?php } ?>

                            <?php if($error) { ?>
                            <div class="alert alert-danger" role="alert">
                                <strong>Oh snap!</strong> <?php echo htmlentities($error);?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Post Edit Form -->
                    <div class="row">
                        <div class="col-md-10 col-md-offset-1">
                            <div class="p-6">
                                <form name="editpost" method="post" enctype="multipart/form-data">
                                    <!-- Post Title -->
                                    <div class="form-group m-b-20">
                                        <label for="posttitle">Post Title</label>
                                        <input type="text" class="form-control" id="posttitle" name="posttitle" placeholder="Enter title" value="<?php echo htmlentities($row['title']); ?>" required>
                                    </div>

                                    <!-- Category -->
                                    <div class="form-group m-b-20">
                                        <label for="category">Category</label>
                                        <select class="form-control" name="category" id="category" required>
                                            <option value="">Select Category</option>
                                            <?php
                                            $ret = mysqli_query($con, "SELECT id, category FROM categories");
                                            while($result = mysqli_fetch_array($ret)) {    
                                                $selected = ($result['id'] == $row['category']) ? 'selected' : '';
                                                echo '<option value="' . htmlentities($result['id']) . '" ' . $selected . '>' . htmlentities($result['category']) . '</option>';
                                            }
                                            ?>
                                        </select> 
                                    </div>

                                    <!-- Description -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Post Description</b></h4>
                                                <textarea class="summernote" name="postdescription" required><?php echo htmlentities($row['description']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Post Content</b></h4>
                                                <textarea class="summernote" name="postcontent" required><?php echo htmlentities($row['content']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Featured Image -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Featured Image</b></h4>
                                                <input type="file" class="form-control" id="postimage" name="postimage">
                                                <?php if($row['file']) { ?>
                                                    <img src="postimages/<?php echo htmlentities($row['file']); ?>" width="100" class="img-thumbnail">
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Published Toggle -->
                                    <div class="form-group m-b-20">
                                        <label for="published">Published</label><br>
                                        <input type="checkbox" name="published" id="published" class="switchery" <?php echo $row['published'] ? 'checked' : ''; ?> />
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="submit" name="submit" class="btn btn-success waves-effect waves-light">Update Post</button>
                                    <a href="manage-posts.php" class="btn btn-primary">Cancel</a>
                                </form>
                            </div>
                        </div> <!-- end p-20 -->
                    </div> <!-- end col -->
                </div> <!-- end row -->
            </div> <!-- container -->
        </div> <!-- content -->

        <?php include('includes/footer.php'); ?>
    </div> <!-- content-page -->
</div> <!-- wrapper -->

<!-- jQuery  -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/detect.js"></script>
<script src="assets/js/fastclick.js"></script>
<script src="assets/js/jquery.blockUI.js"></script>
<script src="assets/js/waves.js"></script>
<script src="assets/js/jquery.slimscroll.js"></script>
<script src="assets/js/jquery.scrollTo.min.js"></script>

<!-- Summernote js -->
<script src="../plugins/summernote/summernote.min.js"></script>

<!-- Switchery js -->
<script src="../plugins/switchery/switchery.min.js"></script>

<script>
jQuery(document).ready(function(){
    $('.summernote').summernote({
        height: 240,
        minHeight: null,
        maxHeight: null,
        focus: false
    });
    var switchery = new Switchery(document.querySelector('#published'), { color: '#64bd63' });
});
</script>

<script>
    $(document).ready(function() {
        $('.filter-input').on('keyup', function() {
            let column = $(this).closest('th').index();
            let value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).find('td').eq(column).text().toLowerCase().indexOf(value) > -1);
            });
        });
    });
    </script>

</body>
</html>