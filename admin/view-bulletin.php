<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login']) == 0) { 
    header('location:index.php');
} else {
    // Fetch bulletin details based on ID
    if(isset($_GET['id'])) {
        $bulletin_id = $_GET['id'];
        $query = mysqli_query($con, "SELECT a.id, a.title, a.description, a.file, u.AdminUserName as created_by, a.created_on, a.published 
                                     FROM bulletins a 
                                     JOIN tbladmin u ON a.created_by = u.id 
                                     WHERE a.id='$bulletin_id'");
        $row = mysqli_fetch_assoc($query);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Ratin.net | View Bulletin</title>

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
                                <h4 class="page-title">View Bulletin</h4>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulletin View -->
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="header-title"><b>Bulletin Details</b></h4>
                                <hr />
                                <p><strong>Title:</strong> <?php echo $row['title']; ?></p>
                                <p><strong>Description:</strong> <?php echo nl2br($row['description']); ?></p>
                                <p><strong>Created By:</strong> <?php echo $row['created_by']; ?></p>
                                <p><strong>Created On:</strong> <?php echo $row['created_on']; ?></p>
                                <p><strong>Published:</strong> <?php echo $row['published'] ? 'Yes' : 'No'; ?></p>
                                
                                <!-- Display File (if available) -->
                                <?php if ($row['file']) { ?>
                                    <p><strong>Attached File:</strong> <a href="bulletin_files/<?php echo htmlentities($row['file']); ?>" target="_blank">Download File</a></p>
                                <?php } ?>

                                <a href="manage-bulletins.php" class="btn btn-primary">Back to Bulletins</a>
                            </div>
                        </div>
                    </div>

                </div> <!-- container -->
            </div> <!-- content -->

            <?php include('includes/footer.php');?>
        </div>
    </div>
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

    <!-- jQuery  -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>

<?php } ?>
