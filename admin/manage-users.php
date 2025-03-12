<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(strlen($_SESSION['login'])==0)
{ 
    header('location:index.php');
}
else{
    // Define how many users to show per page
    $users_per_page = 10;

    // Get the total number of users
    $result_total = mysqli_query($con, "SELECT COUNT(*) AS total_users FROM tblusers");
    $row_total = mysqli_fetch_assoc($result_total);
    $total_users = $row_total['total_users'];

    // Calculate total pages
    $total_pages = ceil($total_users / $users_per_page);

    // Get the current page number from the URL, default is 1
    $current_page = isset($_GET['page']) ? $_GET['page'] : 1;

    // Calculate the starting record
    $start = ($current_page - 1) * $users_per_page;

    // Fetch users for the current page
    $result = mysqli_query($con, "SELECT * FROM tblusers LIMIT $start, $users_per_page");
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Ratin.net | Manage Users</title>
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

            <!-- ========== Left Sidebar Start ========== -->
            <?php include('includes/leftsidebar.php');?>
            <!-- Left Sidebar End -->

            <div class="content-page">
                <!-- Start content -->
                <div class="content">
                    <div class="container">

                        <div class="row">
                            <div class="col-xs-12">
                                <div class="page-title-box">
                                    <h4 class="page-title">Manage Users</h4>
                                    <ol class="breadcrumb p-0 m-0">
                                        <li>
                                            <a href="#">Admin</a>
                                        </li>
                                        <li>
                                            <a href="#">Users</a>
                                        </li>
                                        <li class="active">
                                            Manage Users
                                        </li>
                                    </ol>
                                    <div class="clearfix"></div>
                                </div>
                            </div>
                        </div>
                        <!-- end row -->

                        <div class="row">
                            <div class="col-sm-12">
                                <div class="card-box">
                                    <h4 class="m-t-0 header-title"><b>Manage Users</b></h4>
                                    <hr />

                                    <!-- User Table -->
                                    <h4 class="header-title"><b>All Users</b></h4>
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>First Name</th>
                                                <th>Last Name</th>
                                                <th>Email</th>
                                                <th>Created On</th>
                                                <th>Last Login</th>
                                                <th>Membership Expiry</th>
                                                <th>Groups/Roles</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo "<tr>
                                                        <td>" . $row['FirstName'] . "</td>
                                                        <td>" . $row['LastName'] . "</td>
                                                        <td>" . $row['Email'] . "</td>
                                                        <td>" . $row['CreatedOn'] . "</td>
                                                        <td>" . $row['LastLogin'] . "</td>
                                                        <td>" . $row['MembershipExpiry'] . "</td>
                                                        <td>" . $row['GroupRoles'] . "</td>
                                                        <td>" . $row['Status'] . "</td>
                                                        <td>
                                                            <a href='edit-user.php?id=" . $row['ID'] . "' class='btn btn-warning btn-sm'>Edit</a>
                                                            <a href='delete-user.php?id=" . $row['ID'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete?\")'>Delete</a>
                                                        </td>
                                                    </tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>

                                    <!-- Pagination -->
                                    <div class="pagination-container">
                                        <ul class="pagination">
                                            <!-- Previous Page Link -->
                                            <?php if($current_page > 1): ?>
                                                <li><a href="?page=<?php echo $current_page - 1; ?>">Previous</a></li>
                                            <?php endif; ?>

                                            <!-- Page Number Links -->
                                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="<?php if($i == $current_page) echo 'active'; ?>">
                                                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Next Page Link -->
                                            <?php if($current_page < $total_pages): ?>
                                                <li><a href="?page=<?php echo $current_page + 1; ?>">Next</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <!-- end row -->

                    </div> <!-- container -->

                </div> <!-- content -->

            <?php include('includes/footer.php');?>

            </div>
        </div>

        <script>
            var resizefunc = [];
        </script>

        <!-- jQuery  -->
        <script src="assets/js/jquery.min.js"></script>
        <script src="assets/js/bootstrap.min.js"></script>
        <script src="assets/js/detect.js"></script>
        <script src="assets/js/fastclick.js"></script>
        <script src="assets/js/jquery.blockUI.js"></script>
        <script src="assets/js/waves.js"></script>
        <script src="assets/js/jquery.slimscroll.js"></script>
        <script src="assets/js/jquery.scrollTo.min.js"></script>

        <!-- App js -->
        <script src="assets/js/jquery.core.js"></script>
        <script src="assets/js/jquery.app.js"></script>

    </body>
</html>

<?php } ?>
