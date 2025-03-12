<?php
session_start();
include('includes/config.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in
if (strlen($_SESSION['login']) == 0 || !isset($_SESSION['userid'])) {
    header('location:index.php');
    exit;
}

// Fetch bulletins from the database
$bulletinsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $bulletinsPerPage;

// Query to fetch bulletins with pagination
$query = "SELECT b.id, b.title, u.AdminUserName as created_by, b.created_on, b.published 
          FROM bulletins b 
          JOIN tbladmin u ON b.created_by = u.id 
          ORDER BY b.created_on DESC 
          LIMIT $offset, $bulletinsPerPage";
$result = mysqli_query($con, $query);

// Total number of bulletins for pagination
$totalBulletinsQuery = "SELECT COUNT(*) as total FROM bulletins";
$totalBulletinsResult = mysqli_query($con, $totalBulletinsQuery);
$totalBulletins = mysqli_fetch_assoc($totalBulletinsResult)['total'];
$totalPages = ceil($totalBulletins / $bulletinsPerPage);
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
    <title>Ratin.net | Manage Bulletins</title>

    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/core.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/components.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/pages.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/menu.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/responsive.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/modernizr.min.js"></script>

    <!-- Custom column widths -->
    <style>
        .table th:nth-child(1), .table td:nth-child(1) { width: 35%; } /* Title column */
        .table th:nth-child(2), .table td:nth-child(2) { width: 15%; } /* Created By column */
        .table th:nth-child(3), .table td:nth-child(3) { width: 15%; } /* Created On column */
        .table th:nth-child(4), .table td:nth-child(4) { width: 10%; } /* Published column */
        .table th:nth-child(5), .table td:nth-child(5) { width: 10%; } /* Actions column */
    </style>
</head>

<body class="fixed-left">
    <!-- Begin page -->
    <div id="wrapper">
        <!-- Top Bar Start -->
        <?php include('includes/topheader.php'); ?>
        <!-- Left Sidebar Start -->
        <?php include('includes/leftsidebar.php'); ?>

        <div class="content-page">
            <div class="content">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="page-title-box">
                                <h4 class="page-title">Manage Bulletins</h4>
                                <ol class="breadcrumb p-0 m-0">
                                    <li>
                                        <a href="#">Bulletins</a>
                                    </li>
                                    <li class="active">
                                        Manage Bulletins
                                    </li>
                                </ol>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Table to display bulletins -->
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card-box">
                                <h4 class="m-t-0 header-title"><b>Bulletins List</b></h4>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Title <input type="text" class="form-control filter-input" placeholder="Filter by Title"></th>
                                                <th>Created By <input type="text" class="form-control filter-input" placeholder="Filter by Created By"></th>
                                                <th>Created On <input type="text" class="form-control filter-input" placeholder="Filter by Created On"></th>
                                                <th>Published <input type="text" class="form-control filter-input" placeholder="Filter by Published"></th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                                <tr>
                                                    <td><?php echo htmlentities($row['title']); ?></td>
                                                    <td><?php echo htmlentities($row['created_by']); ?></td>
                                                    <td><?php echo htmlentities($row['created_on']); ?></td>
                                                    <td><?php echo $row['published'] ? 'Yes' : 'No'; ?></td>
                                                    <td>
                                                        <!-- Edit Icon -->
                                                        <a href="edit-bulletin.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-xs" title="Edit">
                                                            <i class="fa fa-pencil"></i>
                                                        </a>
                                                        

                                                        <!-- View Icon -->
                                                        <a href="view-bulletin.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-xs" title="View">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                        

                                                        <!-- Delete Icon -->
                                                        <a href="delete-bulletin.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-xs" title="Delete" onclick="return confirm('Are you sure you want to delete this bulletin?')">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div class="text-center">
                                    <ul class="pagination">
                                        <?php if ($page > 1) { ?>
                                            <li><a href="manage-bulletins.php?page=<?php echo $page - 1; ?>">Previous</a></li>
                                        <?php } ?>

                                        <?php if ($page > 2) { ?>
                                            <li><a href="manage-bulletins.php?page=1">1</a></li>
                                            <?php if ($page > 3) { ?>
                                                <li class="disabled"><span>...</span></li>
                                            <?php } ?>
                                        <?php } ?>

                                        <?php
                                        $start = max(1, $page - 1);
                                        $end = min($totalPages, $page + 1);
                                        for ($i = $start; $i <= $end; $i++) { ?>
                                            <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                                <a href="manage-bulletins.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php } ?>

                                        <?php if ($page < $totalPages - 1) { ?>
                                            <?php if ($page < $totalPages - 2) { ?>
                                                <li class="disabled"><span>...</span></li>
                                            <?php } ?>
                                            <li><a href="manage-bulletins.php?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
                                        <?php } ?>

                                        <?php if ($page < $totalPages) { ?>
                                            <li><a href="manage-bulletins.php?page=<?php echo $page + 1; ?>">Next</a></li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- container -->
            </div> <!-- content -->
        </div> <!-- content-page -->

        <?php include('includes/footer.php'); ?>
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

    <!-- Filter functionality -->
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
