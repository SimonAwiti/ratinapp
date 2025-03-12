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
    // Fetch bulletin details based on ID
    if(isset($_GET['id'])) {
        $bulletin_id = $_GET['id'];
        $query = mysqli_query($con, "SELECT * FROM bulletins WHERE id='$bulletin_id'");
        $row = mysqli_fetch_assoc($query);
    }

    // Update bulletin on form submission
    if(isset($_POST['submit'])) {
        $bulletintitle = mysqli_real_escape_string($con, $_POST['bulletintitle']);
        $postdescription = mysqli_real_escape_string($con, $_POST['postdescription']);
        $published = isset($_POST['published']) ? 1 : 0;
        
        // Handle file upload
        $file = $_FILES["bulletinfile"]["name"];
        if ($file != "") {
            $extension = substr($file, strlen($file)-4, strlen($file));
            $allowed_extensions = array(".jpg", ".jpeg", ".png", ".gif", ".pdf");
            if(in_array($extension, $allowed_extensions)) {
                $newfile = md5($file) . $extension;
                if (move_uploaded_file($_FILES["bulletinfile"]["tmp_name"], "bulletin_files/" . $newfile)) {
                    $query = "UPDATE bulletins SET title='$bulletintitle', description='$postdescription', file='$newfile', published='$published' WHERE id='$bulletin_id'";
                } else {
                    $error = "Failed to upload file.";
                }
            } else {
                $error = "Invalid format. Only jpg / jpeg / png / gif / pdf format allowed.";
            }
        } else {
            $query = "UPDATE bulletins SET title='$bulletintitle', description='$postdescription', published='$published' WHERE id='$bulletin_id'";
        }

        if (empty($error)) {
            $result = mysqli_query($con, $query);
            if (!$result) {
                echo "Error executing query: " . mysqli_error($con);
            } else {
                $msg = "Bulletin successfully updated.";
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
    <title>Ratin.net | Edit Bulletin</title>

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
                                <h4 class="page-title">Edit Bulletin</h4>
                                <ol class="breadcrumb p-0 m-0">
                                    <li>
                                        <a href="#">Bulletin</a>
                                    </li>
                                    <li class="active">
                                        Edit Bulletin
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

                    <!-- Bulletin Edit Form -->
                    <div class="row">
                        <div class="col-md-10 col-md-offset-1">
                            <div class="p-6">
                                <form name="editbulletin" method="post" enctype="multipart/form-data">
                                    <!-- Bulletin Title -->
                                    <div class="form-group m-b-20">
                                        <label for="bulletintitle">Bulletin Title</label>
                                        <input type="text" class="form-control" id="bulletintitle" name="bulletintitle" placeholder="Enter title" value="<?php echo htmlentities($row['title']); ?>" required>
                                    </div>

                                    <!-- Description -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Bulletin Description</b></h4>
                                                <textarea class="summernote" name="postdescription" required><?php echo htmlentities($row['description']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Featured File -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="card-box">
                                                <h4 class="m-b-30 m-t-0 header-title"><b>Featured File</b></h4>
                                                <input type="file" class="form-control" id="bulletinfile" name="bulletinfile">
                                                <?php if($row['file']) { ?>
                                                    <p>Current file: <a href="bulletin_files/<?php echo htmlentities($row['file']); ?>" target="_blank"><?php echo htmlentities($row['file']); ?></a></p>
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
                                    <button type="submit" name="submit" class="btn btn-success waves-effect waves-light">Update Bulletin</button>
                                    <a href="manage-bulletins.php" class="btn btn-primary">Cancel</a>
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
</body>
</html>
