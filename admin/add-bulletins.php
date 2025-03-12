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
        $bulletintitle = mysqli_real_escape_string($con, $_POST['bulletintitle']);
        $bulletindescription = mysqli_real_escape_string($con, $_POST['bulletindescription']);
        $postedby = $_SESSION['userid']; // Ensure this is set in the session
        $arr = explode(" ", $bulletintitle);
        $url = implode("-", $arr);
        $file = $_FILES["bulletinfile"]["name"];

        // Debugging: Check if the form data is being received
        echo '<pre>';
        var_dump($_POST);
        echo '</pre>';

        // Handle file upload
        if ($file != "") {
            // Get the file extension
            $extension = substr($file, strlen($file)-4, strlen($file));
            $allowed_extensions = array(".jpg", ".jpeg", ".png", ".gif", ".pdf");

            // Validate file extension
            if(!in_array($extension, $allowed_extensions)) {
                echo "<script>alert('Invalid format. Only jpg / jpeg / png / gif / pdf format allowed');</script>";
            } else {
                // Rename the file and move it
                $newfile = md5($file) . $extension;
                if (move_uploaded_file($_FILES["bulletinfile"]["tmp_name"], "postimages/" . $newfile)) {
                    $published = isset($_POST['published']) ? 1 : 0;
                    
                    // Build the query
                    $query = "INSERT INTO bulletins 
                                (title, description, file, created_by, created_on, published) 
                              VALUES 
                                ('$bulletintitle', '$bulletindescription', '$newfile', '$postedby', NOW(), '$published')";

                    // Debugging: Check the query before execution
                    echo "Query: " . $query . "<br>";

                    $result = mysqli_query($con, $query);

                    // Check for query execution
                    if (!$result) {
                        echo "Error executing query: " . mysqli_error($con);  // Show the MySQL error
                    } else {
                        $msg = "Bulletin successfully added.";
                    }
                } else {
                    $error = "Failed to upload file.";
                }
            }
        } else {
            // Handle the case where no file is uploaded
            $published = isset($_POST['published']) ? 1 : 0;
            $query = "INSERT INTO bulletins 
                        (title, description, file, created_by, created_on, published) 
                      VALUES 
                        ('$bulletintitle', '$bulletindescription', NULL, '$postedby', NOW(), '$published')";
            
            // Debugging: Check the query before execution
            //echo "Query: " . $query . "<br>";
            
            $result = mysqli_query($con, $query);

            // Check for query execution
            if (!$result) {
                echo "Error executing query: " . mysqli_error($con);  // Show the MySQL error
            } else {
                $msg = "Bulletin successfully added.";
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
    <title>Ratin.net | Add Bulletin</title>

    <!-- Summernote css -->
    <link href="../plugins/summernote/summernote.css" rel="stylesheet" />

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
        <!-- Left Sidebar Start -->
        <?php include('includes/leftsidebar.php');?>
        
        <div class="content-page">
            <div class="content">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="page-title-box">
                                <h4 class="page-title">Add Bulletin</h4>
                                <ol class="breadcrumb p-0 m-0">
                                    <li>
                                        <a href="#">Bulletins</a>
                                    </li>
                                    <li>
                                        <a href="#">Add Bulletin</a>
                                    </li>
                                    <li class="active">
                                        Add Bulletin
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
                                <form name="addbulletin" method="post" enctype="multipart/form-data">
                                    <!-- Bulletin Title -->
                                    <div class="form-group m-b-20">
                                        <label for="bulletintitle">Bulletin Title</label>
                                        <input type="text" class="form-control" id="bulletintitle" name="bulletintitle" placeholder="Enter title" required>
                                    </div>

                                    <!-- Description -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Bulletin Description</b></h4>
                                                <textarea class="summernote" name="bulletindescription" required></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- File Upload -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Upload File</b></h4>
                                                <input type="file" class="form-control" id="bulletinfile" name="bulletinfile">
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

<!-- Summernote js -->
<script src="../plugins/summernote/summernote.min.js"></script>

<script>
jQuery(document).ready(function(){
    $('.summernote').summernote({
        height: 240,
        minHeight: null,
        maxHeight: null,
        focus: false
    });
});
</script>
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

</body>
</html>
