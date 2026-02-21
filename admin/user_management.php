<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'includes/config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Debug: Check database connection
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

/**
 * Function to send email notification
 */
function sendEmailNotification($con, $user_id, $notification_type, $subject, $message) {
    // Get user details
    $stmt = $con->prepare("SELECT email, full_name FROM subscribed_users WHERE id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return false;
    
    $user = $result->fetch_assoc();
    $to_email = $user['email'];
    $to_name = $user['full_name'];
    
    // Email headers
    $headers = "From: RATIN Trade Analytics <noreply@ratin.com>\r\n";
    $headers .= "Reply-To: support@ratin.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Create HTML email template
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #8B4513; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
            .btn { display: inline-block; background-color: #8B4513; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>RATIN Trade Analytics</h2>
            </div>
            <div class="content">
                <h3>' . htmlspecialchars($subject) . '</h3>
                <p>Hello ' . htmlspecialchars($to_name) . ',</p>
                ' . nl2br(htmlspecialchars($message)) . '
                <br><br>
                <p>Best regards,<br>The RATIN Team</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>© ' . date('Y') . ' RATIN Trade Analytics. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // For testing, we'll log it. In production, use actual mail() or SMTP
    $email_sent = mail($to_email, $subject, $html_message, $headers);
    
    // Log the email notification
    $log_stmt = $con->prepare("INSERT INTO email_notifications (user_id, notification_type, subject, message, sent_at, status) VALUES (?, ?, ?, ?, NOW(), ?)");
    if ($log_stmt) {
        $status = $email_sent ? 'sent' : 'failed';
        $log_stmt->bind_param("issss", $user_id, $notification_type, $subject, $message, $status);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    return $email_sent;
}

// Handle actions
if (isset($_POST['action'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $admin_id = $_SESSION['admin_id'];
    
    try {
        // Get user details before update for email notifications
        $user_stmt = $con->prepare("SELECT email, full_name, status FROM subscribed_users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active', approved_date = NOW(), approved_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                
                $subject = "Your RATIN Account Has Been Approved";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Great news! Your RATIN account has been approved and is now active.\n\n";
                $message .= "Your subscription is valid for 30 days from today.\n\n";
                $message .= "Login URL: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
                
                sendEmailNotification($con, $user_id, 'account_approved', $subject, $message);
                break;
                
            case 'reject':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'rejected', approved_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                
                $subject = "Your RATIN Account Application";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Thank you for your interest in RATIN Trade Analytics.\n\n";
                $message .= "After reviewing your application, we regret to inform you that we are unable to approve your account at this time.\n\n";
                
                sendEmailNotification($con, $user_id, 'account_rejected', $subject, $message);
                break;
                
            case 'suspend':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'suspended' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $subject = "Your RATIN Account Has Been Suspended";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN account has been suspended effective immediately.\n\n";
                $message .= "If you believe this is an error, please contact our support team.\n";
                
                sendEmailNotification($con, $user_id, 'account_suspended', $subject, $message);
                break;
                
            case 'activate':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // If there's no approved_date yet, set it
                $check_stmt = $con->prepare("SELECT approved_date FROM subscribed_users WHERE id = ?");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_user = $check_result->fetch_assoc();
                
                if (!$check_user['approved_date']) {
                    $update_stmt = $con->prepare("UPDATE subscribed_users SET approved_date = NOW(), approved_by = ? WHERE id = ?");
                    $update_stmt->bind_param("ii", $admin_id, $user_id);
                    $update_stmt->execute();
                }
                
                $subject = "Your RATIN Account Has Been Reactivated";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN account has been reactivated and is now active.\n\n";
                
                sendEmailNotification($con, $user_id, 'account_activated', $subject, $message);
                break;
                
            case 'update_subscription':
                $new_subscription = $_POST['subscription_type'];
                $stmt = $con->prepare("UPDATE subscribed_users SET subscription_type = ? WHERE id = ?");
                $stmt->bind_param("si", $new_subscription, $user_id);
                $stmt->execute();
                
                $subject = "Your RATIN Subscription Has Been Updated";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN subscription has been updated to the " . ucfirst($new_subscription) . " plan.\n\n";
                
                sendEmailNotification($con, $user_id, 'subscription_updated', $subject, $message);
                break;
                
            case 'add_user':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $full_name = trim($_POST['full_name']);
                $company = trim($_POST['company']);
                $phone = trim($_POST['phone']);
                $subscription_type = $_POST['subscription_type'];
                $status = $_POST['status'];
                
                // Validation
                if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($subscription_type)) {
                    throw new Exception("Please fill all required fields.");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("Password must be at least 6 characters long.");
                }
                
                // Check if username or email already exists
                $check_stmt = $con->prepare("SELECT id FROM subscribed_users WHERE username = ? OR email = ?");
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    throw new Exception("Username or email already exists.");
                }
                
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Set approved_date if status is active
                $approved_date = $status == 'active' ? date('Y-m-d H:i:s') : null;
                $approved_by = $status == 'active' ? $admin_id : null;
                
                $insert_stmt = $con->prepare("INSERT INTO subscribed_users (username, email, password, full_name, company, phone, subscription_type, status, registration_date, approved_date, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                
                if ($approved_date === null && $approved_by === null) {
                    $insert_stmt->bind_param("ssssssssss", $username, $email, $hashed_password, $full_name, $company, $phone, $subscription_type, $status, $approved_date, $approved_by);
                } else {
                    $insert_stmt->bind_param("sssssssssi", $username, $email, $hashed_password, $full_name, $company, $phone, $subscription_type, $status, $approved_date, $approved_by);
                }
                
                if ($insert_stmt->execute()) {
                    $new_user_id = $insert_stmt->insert_id;
                    $success_message = "User added successfully!";
                    
                    // Send welcome email if user is active
                    if ($status == 'active') {
                        $subject = "Welcome to RATIN Trade Analytics!";
                        $message = "Dear " . $full_name . ",\n\n";
                        $message .= "Welcome to RATIN Trade Analytics! Your account has been created and is now active.\n\n";
                        $message .= "Account Details:\n";
                        $message .= "- Username: " . $username . "\n";
                        $message .= "- Subscription: " . ucfirst($subscription_type) . " (30 days)\n";
                        $message .= "- Start Date: " . date('F j, Y') . "\n";
                        $message .= "- End Date: " . date('F j, Y', strtotime('+30 days')) . "\n\n";
                        $message .= "Login URL: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
                        
                        sendEmailNotification($con, $new_user_id, 'welcome', $subject, $message);
                    }
                } else {
                    throw new Exception("Failed to add user. Please try again.");
                }
                break;
                
            case 'renew_subscription':
                $stmt = $con->prepare("UPDATE subscribed_users SET approved_date = NOW(), approved_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                
                $subject = "Your RATIN Subscription Has Been Renewed";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN subscription has been renewed for another 30 days.\n\n";
                $message .= "Your new subscription period is from " . date('F j, Y') . " to " . date('F j, Y', strtotime('+30 days')) . ".\n\n";
                
                sendEmailNotification($con, $user_id, 'subscription_renewed', $subject, $message);
                break;
                
            case 'delete_user':
                $stmt = $con->prepare("DELETE FROM subscribed_users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $success_message = "User deleted successfully!";
                break;
                
            case 'reset_password':
                $new_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $con->prepare("UPDATE subscribed_users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                
                $subject = "Your RATIN Password Has Been Reset";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN account password has been reset by an administrator.\n\n";
                $message .= "Your new temporary password is: " . $new_password . "\n\n";
                $message .= "Please log in and change your password immediately for security reasons.\n\n";
                
                sendEmailNotification($con, $user_id, 'password_reset', $subject, $message);
                $success_message = "Password reset! New password has been sent to user's email.";
                break;
        }
        
        echo '<script>window.location.href = "user_management.php";</script>';
        exit;
        
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

// Get subscribed users with expiry information
try {
    $users_stmt = $con->prepare("SELECT su.*, au.username as approved_by_name,
                                DATEDIFF(DATE_ADD(su.approved_date, INTERVAL 30 DAY), CURDATE()) as days_until_expiry,
                                DATE_ADD(su.approved_date, INTERVAL 30 DAY) as expiry_date
                                FROM subscribed_users su 
                                LEFT JOIN admin_users au ON su.approved_by = au.id 
                                ORDER BY su.registration_date DESC");
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    $users = [];
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    $error_message = "Failed to fetch users: " . $e->getMessage();
    $users = [];
}

// Get subscription packages
$packages_stmt = $con->prepare("SELECT * FROM subscription_packages WHERE is_active = TRUE");
if ($packages_stmt) {
    $packages_stmt->execute();
    $packages_result = $packages_stmt->get_result();
    $packages = [];
    while ($row = $packages_result->fetch_assoc()) {
        $packages[] = $row;
    }
} else {
    $packages = [];
}

// Fetch counts for summary boxes
$total_users_query = "SELECT COUNT(*) AS total FROM subscribed_users";
$total_users_result = $con->query($total_users_query);
$total_users = $total_users_result ? $total_users_result->fetch_assoc()['total'] : 0;

$active_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'active'";
$active_users_result = $con->query($active_users_query);
$active_users_count = $active_users_result ? $active_users_result->fetch_assoc()['total'] : 0;

$pending_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'pending'";
$pending_users_result = $con->query($pending_users_query);
$pending_users_count = $pending_users_result ? $pending_users_result->fetch_assoc()['total'] : 0;

$suspended_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'suspended'";
$suspended_users_result = $con->query($suspended_users_query);
$suspended_users_count = $suspended_users_result ? $suspended_users_result->fetch_assoc()['total'] : 0;

$rejected_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'rejected'";
$rejected_users_result = $con->query($rejected_users_query);
$rejected_users_count = $rejected_users_result ? $rejected_users_result->fetch_assoc()['total'] : 0;

$today_notifications_query = "SELECT COUNT(*) AS total FROM email_notifications WHERE DATE(sent_at) = CURDATE()";
$today_notifications_result = $con->query($today_notifications_query);
$today_notifications_count = $today_notifications_result ? $today_notifications_result->fetch_assoc()['total'] : 0;

// Pagination Logic
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$totalItems = count($users);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$users_paged = array_slice($users, $startIndex, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - RATIN Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Main Container Styles */
        .container-fluid {
            padding: 20px;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 30px;
        }

        .text-wrapper-8 h3 {
            color: #2c3e50;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stats-section .p {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .stats-container {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            width: 100%;
            margin: 0 auto 20px auto;
        }

        .stats-container > div {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stats-icon {
            width: 40px;
            height: 40px;
            margin-bottom: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .total-users-icon { background-color: #3498db; color: white; }
        .active-users-icon { background-color: #28a745; color: white; }
        .pending-users-icon { background-color: #ffc107; color: #212529; }
        .suspended-users-icon { background-color: #dc3545; color: white; }
        .rejected-users-icon { background-color: #6c757d; color: white; }
        .emails-icon { background-color: #17a2b8; color: white; }

        .stats-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin: 8px 0 5px 0;
        }

        .stats-number {
            font-size: 24px;
            font-weight: 700;
            color: #34495e;
        }

        /* Table Container */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        /* Button Group */
        .btn-group {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-add-new {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-add-new:hover {
            background-color: darkred;
        }

        .btn-export {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-export:hover {
            background-color: #218838;
        }

        .btn-remind {
            background-color: #ffc107;
            color: #212529;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-remind:hover {
            background-color: #e0a800;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead tr:first-child {
            background-color: #d3d3d3 !important;
            color: black !important;
        }

        table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }

        table tbody tr {
            border-bottom: 1px solid #eee;
        }

        table tbody tr:hover {
            background-color: #f5f5f5;
        }

        table td {
            padding: 10px 15px;
            vertical-align: middle;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-suspended { background-color: #f8d7da; color: #721c24; }
        .status-rejected { background-color: #e2e3e5; color: #383d41; }

        /* Expiry Indicators */
        .expiry-indicator {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .expiry-good { background-color: #d4edda; color: #155724; }
        .expiry-soon { background-color: #fff3cd; color: #856404; }
        .expiry-today { background-color: #f8d7da; color: #721c24; animation: pulse 1.5s infinite; }
        .expiry-expired { background-color: #dc3545; color: white; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Action Buttons */
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin: 2px;
        }

        .action-btn i {
            margin-right: 3px;
        }

        .btn-approve { background-color: #28a745; color: white; }
        .btn-reject { background-color: #6c757d; color: white; }
        .btn-suspend { background-color: #ffc107; color: #212529; }
        .btn-activate { background-color: #28a745; color: white; }
        .btn-renew { background-color: #17a2b8; color: white; }
        .btn-reset { background-color: #6f42c1; color: white; }
        .btn-delete { background-color: #dc3545; color: white; }

        /* Select Dropdown */
        .subscription-select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .subscription-select:focus {
            outline: none;
            border-color: #8B4513;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background-color: #2c3e50;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .modal-header .btn-close {
            color: white;
            filter: invert(1);
        }

        .form-control, .form-select {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 1);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.5);
        }

        /* Alert Styles */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        /* Pagination */
        .pagination .page-link {
            color: #8B4513;
        }

        .pagination .active .page-link {
            background-color: #8B4513;
            border-color: #8B4513;
            color: white;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                flex-wrap: wrap;
            }
            .stats-container > div {
                flex: 0 0 calc(50% - 10px);
            }
        }

        @media (max-width: 768px) {
            .stats-container > div {
                flex: 0 0 100%;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <!-- Statistics Section (Styled like commodity sources) -->
        <div class="stats-section">
            <div class="text-wrapper-8"><h3>User Management Dashboard</h3></div>
            <p class="p">Manage user accounts, subscriptions, and automated notifications</p>

            <div class="stats-container">
                <div class="overlap-6">
                    <div class="stats-icon total-users-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-title">Total Users</div>
                    <div class="stats-number"><?php echo $total_users; ?></div>
                </div>
                
                <div class="overlap-6">
                    <div class="stats-icon active-users-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stats-title">Active Users</div>
                    <div class="stats-number"><?php echo $active_users_count; ?></div>
                </div>
                
                <div class="overlap-7">
                    <div class="stats-icon pending-users-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stats-title">Pending</div>
                    <div class="stats-number"><?php echo $pending_users_count; ?></div>
                </div>
                
                <div class="overlap-7">
                    <div class="stats-icon suspended-users-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stats-title">Suspended</div>
                    <div class="stats-number"><?php echo $suspended_users_count; ?></div>
                </div>

                <div class="overlap-7">
                    <div class="stats-icon rejected-users-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stats-title">Rejected</div>
                    <div class="stats-number"><?php echo $rejected_users_count; ?></div>
                </div>

                <div class="overlap-7">
                    <div class="stats-icon emails-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stats-title">Emails Today</div>
                    <div class="stats-number"><?php echo $today_notifications_count; ?></div>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Content Container -->
        <div class="container">
            <div class="table-container">
                <!-- Button Group -->
                <div class="btn-group">
                    <button class="btn-add-new" onclick="openAddUserModal()">
                        <i class="fas fa-plus" style="margin-right: 5px;"></i>
                        Add New User
                    </button>

                    <a href="?run_expiry_notifications=1" class="btn-remind">
                        <i class="fas fa-envelope" style="margin-right: 3px;"></i>
                        Send Expiry Notifications
                    </a>
                </div>

                <!-- Users Table -->
                <table class="table table-striped table-hover">
                    <thead>
                        <tr style="background-color: #d3d3d3 !important; color: black !important;">
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Subscription</th>
                            <th>Status</th>
                            <th>Expiry Status</th>
                            <th>Registration</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users_paged)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-users-slash" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                                    No users found in the database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users_paged as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="subscription_type" class="subscription-select" onchange="this.form.submit()">
                                                <option value="basic" <?php echo $user['subscription_type'] == 'basic' ? 'selected' : ''; ?>>Basic</option>
                                                <option value="medium" <?php echo $user['subscription_type'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                <option value="premium" <?php echo $user['subscription_type'] == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                            </select>
                                            <input type="hidden" name="action" value="update_subscription">
                                        </form>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['status'] == 'active' && $user['approved_date']): 
                                            $days = $user['days_until_expiry'];
                                            if ($days === null): ?>
                                                <span class="expiry-indicator expiry-good">No expiry</span>
                                            <?php elseif ($days > 4): ?>
                                                <span class="expiry-indicator expiry-good"><?php echo $days; ?> days left</span>
                                            <?php elseif ($days > 0): ?>
                                                <span class="expiry-indicator expiry-soon"><?php echo $days; ?> days left</span>
                                            <?php elseif ($days == 0): ?>
                                                <span class="expiry-indicator expiry-today">Expires today!</span>
                                            <?php else: ?>
                                                <span class="expiry-indicator expiry-expired">Expired <?php echo abs($days); ?> days ago</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="expiry-indicator">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['registration_date'])); ?></td>
                                    <td><?php echo $user['last_login'] ? date('Y-m-d', strtotime($user['last_login'])) : 'Never'; ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <?php if ($user['status'] == 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="action-btn btn-approve" title="Approve">
                                                        <i class="fas fa-check"></i> App
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="action-btn btn-reject" title="Reject">
                                                        <i class="fas fa-times"></i> Rej
                                                    </button>
                                                </form>
                                            <?php elseif ($user['status'] == 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="action" value="suspend" class="action-btn btn-suspend" title="Suspend">
                                                        <i class="fas fa-pause"></i> Sus
                                                    </button>
                                                    <?php if ($user['approved_date']): ?>
                                                        <button type="submit" name="action" value="renew_subscription" class="action-btn btn-renew" title="Renew 30 days">
                                                            <i class="fas fa-sync"></i> Ren
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            <?php elseif ($user['status'] == 'suspended'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="action" value="activate" class="action-btn btn-activate" title="Activate">
                                                        <i class="fas fa-play"></i> Act
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button onclick="openResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" class="action-btn btn-reset" title="Reset Password">
                                                <i class="fas fa-key"></i> PW
                                            </button>
                                            
                                            <button onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" class="action-btn btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i> Del
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Displaying <?php echo $startIndex + 1; ?> to <?php echo min($startIndex + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> users
                    </div>
                    <div>
                        <label for="itemsPerPage">Show:</label>
                        <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                            <option value="5" <?php echo $itemsPerPage == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $itemsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $itemsPerPage == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page > 1 ? '?page=' . ($page-1) . '&limit=' . $itemsPerPage : '#'; ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $itemsPerPage; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page < $totalPages ? '?page=' . ($page+1) . '&limit=' . $itemsPerPage : '#'; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label required">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label required">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Password (min 6 chars)</label>
                                <input type="password" name="password" class="form-control" required minlength="6" id="passwordField">
                                <small id="passwordStrength" style="display: block; margin-top: 5px;"></small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Company</label>
                                <input type="text" name="company" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label required">Subscription Type</label>
                                <select name="subscription_type" class="form-select" required>
                                    <option value="basic">Basic</option>
                                    <option value="medium">Medium</option>
                                    <option value="premium">Premium</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Initial Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending">Pending</option>
                                    <option value="active" selected>Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="delete_user" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="resetPasswordUserId">
                        <p>Reset password for: <strong id="resetPasswordUserName"></strong></p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> A new random password will be generated and sent to the user's email.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="reset_password" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap modals
        var addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        var resetPasswordModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
        var cronModal = new bootstrap.Modal(document.getElementById('cronModal'));

        function openAddUserModal() {
            addUserModal.show();
        }

        function openDeleteModal(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            deleteModal.show();
        }

        function openResetPasswordModal(userId, userName) {
            document.getElementById('resetPasswordUserId').value = userId;
            document.getElementById('resetPasswordUserName').textContent = userName;
            resetPasswordModal.show();
        }

        function openCronModal() {
            cronModal.show();
        }

        function updateItemsPerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('limit', value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        // Password strength indicator
        document.getElementById('passwordField')?.addEventListener('input', function(e) {
            var password = e.target.value;
            var strengthText = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthText.textContent = '';
            } else if (password.length < 6) {
                strengthText.textContent = 'Too short (min 6 characters)';
                strengthText.style.color = '#dc3545';
            } else if (password.length < 8) {
                strengthText.textContent = 'Fair';
                strengthText.style.color = '#ffc107';
            } else if (password.length < 12) {
                strengthText.textContent = 'Good';
                strengthText.style.color = '#28a745';
            } else {
                strengthText.textContent = 'Excellent';
                strengthText.style.color = '#28a745';
            }
        });

        // Auto-generate username from email
        document.querySelector('input[name="email"]')?.addEventListener('blur', function(e) {
            var email = e.target.value;
            var usernameField = document.querySelector('input[name="username"]');
            
            if (email && !usernameField.value) {
                var username = email.split('@')[0];
                username = username.replace(/[^a-zA-Z0-9]/g, '_');
                username = username.substring(0, 20);
                usernameField.value = username;
            }
        });
    </script>
</body>
</html>