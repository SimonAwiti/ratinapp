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
    if(isset($_POST['submit'])) {
        // Capture the form inputs
        $posttitle = mysqli_real_escape_string($con, $_POST['posttitle']);
        $catid = $_POST['category'];
        $postdescription = mysqli_real_escape_string($con, $_POST['postdescription']);
        $postcontent = mysqli_real_escape_string($con, $_POST['postcontent']);
        $postedby = $_SESSION['userid']; // Ensure this is set in the session
        $arr = explode(" ", $posttitle);
        $url = implode("-", $arr);
        $imgfile = $_FILES["postimage"]["name"];

        // Debugging: Check if the form data is being received
        echo '<pre>';
        var_dump($_POST);
        echo '</pre>';

        // Handle file upload
        if ($imgfile != "") {
            // Get the image extension
            $extension = substr($imgfile, strlen($imgfile)-4, strlen($imgfile));
            $allowed_extensions = array(".jpg", ".jpeg", ".png", ".gif", ".pdf");

            // Validate file extension
            if(!in_array($extension, $allowed_extensions)) {
                echo "<script>alert('Invalid format. Only jpg / jpeg / png / gif / pdf format allowed');</script>";
            } else {
                // Rename the image file and move it
                $imgnewfile = md5($imgfile) . $extension;
                if (move_uploaded_file($_FILES["postimage"]["tmp_name"], "postimages/" . $imgnewfile)) {
                    $published = isset($_POST['published']) ? 1 : 0;
                    
                    // Build the query
                    $query = "INSERT INTO articles 
                                (category, title, description, content, file, created_by, created_on, published) 
                              VALUES 
                                ('$catid', '$posttitle', '$postdescription', '$postcontent', '$imgnewfile', '$postedby', NOW(), '$published')";

                    // Debugging: Check the query before execution
                    echo "Query: " . $query . "<br>";

                    $result = mysqli_query($con, $query);

                    // Check for query execution
                    if (!$result) {
                        echo "Error executing query: " . mysqli_error($con);  // Show the MySQL error
                    } else {
                        $msg = "Post successfully added.";
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        } else {
            // Handle the case where no file is uploaded
            $published = isset($_POST['published']) ? 1 : 0;
            $query = "INSERT INTO articles 
                        (category, title, description, content, file, created_by, created_on, published) 
                      VALUES 
                        ('$catid', '$posttitle', '$postdescription', '$postcontent', NULL, '$postedby', NOW(), '$published')";
            
            // Debugging: Check the query before execution
            echo "Query: " . $query . "<br>";
            
            $result = mysqli_query($con, $query);

            // Check for query execution
            if (!$result) {
                echo "Error executing query: " . mysqli_error($con);  // Show the MySQL error
            } else {
                $msg = "Post successfully added.";
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
    <title>Ratin.net | Add Post</title>

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
    <script>
    function getSubCat(val) {
        $.ajax({
            type: "POST",
            url: "get_subcategory.php",
            data: 'catid=' + val,
            success: function(data) {
                $("#subcategory").html(data);
            }
        });
    }
    </script>
</head>

<body class="fixed-left">
    <!-- Begin page -->
    <div id="wrapper">
        <!-- Top Bar Start -->
        <?php include('includes/topheader.php');?>
        <!-- Left Sidebar Start -->
              <!-- Top Bar Start -->
              <?php include('includes/topheader.php');?>
            <!-- Top Bar End -->

            <!-- ========== Left Sidebar Start ========== -->
            <?php include('includes/leftsidebar.php');?>
            <!-- Left Sidebar End -->
                    <!-- Sidebar -->
                    <div class="clearfix"></div>


                </div>
                <!-- Sidebar -left -->

            </div>

        <div class="content-page">
            <div class="content">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="page-title-box">
                                <h4 class="page-title">Add Post </h4>
                                <ol class="breadcrumb p-0 m-0">
                                    <li>
                                        <a href="#">Post</a>
                                    </li>
                                    <li>
                                        <a href="#">Add Post </a>
                                    </li>
                                    <li class="active">
                                        Add Post
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

                    <div class="row">
                        <div class="col-md-10 col-md-offset-1">
                            <div class="p-6">
                                <form name="addpost" method="post" enctype="multipart/form-data">
                                    <!-- Post Title -->
                                    <div class="form-group m-b-20">
                                        <label for="exampleInputEmail1">Post Title</label>
                                        <input type="text" class="form-control" id="posttitle" name="posttitle" placeholder="Enter title" required>
                                    </div>

                                    <!-- Category -->
                                    <div class="form-group m-b-20">
                                        <label for="exampleInputEmail1">Category</label>
                                        <select class="form-control" name="category" id="category" required>
                                            <option value="">Select Category </option>
                                            <?php
                                            $ret = mysqli_query($con, "SELECT id, category FROM categories");
                                            while($result = mysqli_fetch_array($ret)) {    
                                            ?>
                                            <option value="<?php echo htmlentities($result['id']);?>"><?php echo htmlentities($result['category']);?></option>
                                            <?php } ?>
                                        </select> 
                                    </div>

                                    <!-- Description -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Post Description</b></h4>
                                                <textarea class="summernote" name="postdescription" required></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Post Content</b></h4>
                                                <textarea class="summernote" name="postcontent" required></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Featured Image -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Featured Image</b></h4>
                                                <input type="file" class="form-control" id="postimage" name="postimage" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Published Toggle -->
                                    <div class="form-group m-b-20">
                                        <label for="published">Published</label><br>
                                        <input type="checkbox" name="published" id="published" class="switchery" checked />
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="submit" name="submit" class="btn btn-success waves-effect waves-light">Save and Post</button>
                                    <button type="button" class="btn btn-danger waves-effect waves-light">Discard</button>
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
$('.has_sub > a').on('click', function (e) {
    var $parent = $(this).parent();
    if ($parent.hasClass('active')) {
        $parent.removeClass('active');
        $parent.find('ul').slideUp();
    } else {
        $parent.addClass('active');
        $parent.find('ul').slideDown();
    }
    e.preventDefault();
});

</script>

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

</body>
</html>
