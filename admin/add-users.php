<?php
session_start();
include('includes/config.php');
error_reporting(0);
if(strlen($_SESSION['login'])==0)
{ 
    header('location:index.php');
}
else{

// Code for Add New User
if(isset($_POST['submit'])){
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['emailid'];
    $phone = $_POST['phone'];
    $category = $_POST['category'];
    $gender = $_POST['gender'];
    $location = $_POST['location'];
    $country = $_POST['country'];
    $expiry = $_POST['expiry'];
    $group = $_POST['group'];
    $status = $_POST['status'];
    
    $password = md5($_POST['pwd']);
    
    // Insert new user into database
    $query = mysqli_query($con, "INSERT INTO tblusers(FirstName, LastName, Email, Phone, Category, Gender, Location, Country, MembershipExpiry, CreatedOn, GroupRoles, Status) 
                                VALUES('$firstname', '$lastname', '$email', '$phone', '$category', '$gender', '$location', '$country', '$expiry', NOW(), '$group', '$status')");
    
    if($query){
        echo "<script>alert('User added successfully.');</script>";
        echo "<script type='text/javascript'> document.location = 'add-users.php'; </script>";
    } else {
        echo "<script>alert('Something went wrong. Please try again.');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Ratin.net | Add Users</title>
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
                                    <h4 class="page-title">Add User</h4>
                                    <ol class="breadcrumb p-0 m-0">
                                        <li>
                                            <a href="#">Admin</a>
                                        </li>
                                        <li>
                                            <a href="#">Users</a>
                                        </li>
                                        <li class="active">
                                            Add User
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
                                    <h4 class="m-t-0 header-title"><b>Add User</b></h4>
                                    <hr />

                                    <!-- Form to add user -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <form class="form-horizontal" name="adduser" method="post">
                                                <div class="form-group">
                                                    <label for="firstname">First Name</label>
                                                    <input type="text" name="firstname" class="form-control" placeholder="Enter First Name" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="lastname">Last Name</label>
                                                    <input type="text" name="lastname" class="form-control" placeholder="Enter Last Name" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="emailid">Email Id</label>
                                                    <input type="email" name="emailid" class="form-control" placeholder="Enter Email" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="phone">Phone</label>
                                                    <input type="text" name="phone" class="form-control" placeholder="Enter Phone" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="category">Category</label>
                                                    <select name="category" class="form-control" required>
                                                        <option value="farmer/producer">Farmer/Producer</option>
                                                        <option value="processor/trader">Processor/Trader</option>
                                                        <option value="researcher/student">Researcher/Student</option>
                                                        <option value="NGO">NGO</option>
                                                        <option value="policy maker/government">Policy Maker/Government</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="gender">Gender</label>
                                                    <select name="gender" class="form-control" required>
                                                        <option value="male">Male</option>
                                                        <option value="female">Female</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="location">Location</label>
                                                    <input type="text" name="location" class="form-control" placeholder="Enter Location" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="country">Country</label>
                                                    <input type="text" name="country" class="form-control" placeholder="Enter Country" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="expiry">Membership Expiry</label>
                                                    <input type="date" name="expiry" class="form-control" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="group">Group/Roles</label>
                                                    <input type="text" name="group" class="form-control" placeholder="Enter Groups/Roles" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="status">Status</label>
                                                    <select name="status" class="form-control" required>
                                                        <option value="active">Active</option>
                                                        <option value="inactive">Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="pwd">Password</label>
                                                    <input type="password" name="pwd" class="form-control" placeholder="Enter Password" required>
                                                </div>
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-custom waves-effect waves-light btn-md" name="submit">
                                                        Submit
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

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
