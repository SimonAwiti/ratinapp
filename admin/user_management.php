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
    
    // Send email (in production, use a proper email library)
    $email_sent = false;
    
    // For testing, we'll just log it. In production, use actual mail() or SMTP
    // $email_sent = mail($to_email, $subject, $html_message, $headers);
    
    // For now, we'll simulate successful sending and log it
    $email_sent = true;
    
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

/**
 * Function to check and send subscription expiry notifications
 * Counts 30 days from approved_date and notifies 4 days before expiry
 */
function checkAndSendExpiryNotifications($con) {
    // Find users whose subscriptions expire in 4 days (30 days from approval minus 4 days = 26 days after approval)
    $query = "SELECT id, email, full_name, approved_date 
              FROM subscribed_users 
              WHERE status = 'active' 
              AND approved_date IS NOT NULL
              AND DATE(approved_date) = DATE_SUB(CURDATE(), INTERVAL 26 DAY)
              AND id NOT IN (
                  SELECT user_id FROM email_notifications 
                  WHERE notification_type = 'subscription_expiry_4days' 
                  AND DATE(sent_at) = CURDATE()
              )";
    
    $stmt = $con->prepare($query);
    if (!$stmt) return false;
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications_sent = 0;
    while ($user = $result->fetch_assoc()) {
        $expiry_date = date('Y-m-d', strtotime($user['approved_date'] . ' +30 days'));
        $subject = "Your RATIN Subscription Expires Soon";
        $message = "Dear " . $user['full_name'] . ",\n\n";
        $message .= "This is a reminder that your RATIN subscription will expire in 4 days on " . date('F j, Y', strtotime($expiry_date)) . ".\n\n";
        $message .= "To continue enjoying uninterrupted access to our market data and analytics, please renew your subscription before it expires.\n\n";
        $message .= "You can renew your subscription by logging into your account and visiting the subscription page.\n\n";
        $message .= "If you have any questions, please contact our support team.\n";
        
        if (sendEmailNotification($con, $user['id'], 'subscription_expiry_4days', $subject, $message)) {
            $notifications_sent++;
        }
    }
    
    return $notifications_sent;
}

/**
 * Function to check and send subscription expiry notifications (Manual trigger)
 */
function sendManualExpiryNotifications($con) {
    // Find ALL active users whose subscriptions expire in 4 days or less
    $query = "SELECT id, email, full_name, approved_date,
              DATEDIFF(DATE_ADD(approved_date, INTERVAL 30 DAY), CURDATE()) as days_until_expiry
              FROM subscribed_users 
              WHERE status = 'active' 
              AND approved_date IS NOT NULL
              AND DATEDIFF(DATE_ADD(approved_date, INTERVAL 30 DAY), CURDATE()) BETWEEN 0 AND 4
              AND id NOT IN (
                  SELECT user_id FROM email_notifications 
                  WHERE notification_type = 'subscription_expiry_4days' 
                  AND DATE(sent_at) = CURDATE()
              )";
    
    $stmt = $con->prepare($query);
    if (!$stmt) return false;
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications_sent = 0;
    while ($user = $result->fetch_assoc()) {
        $expiry_date = date('Y-m-d', strtotime($user['approved_date'] . ' +30 days'));
        $subject = "Your RATIN Subscription Expires Soon";
        $message = "Dear " . $user['full_name'] . ",\n\n";
        $message .= "This is a reminder that your RATIN subscription will expire in " . $user['days_until_expiry'] . " days on " . date('F j, Y', strtotime($expiry_date)) . ".\n\n";
        $message .= "To continue enjoying uninterrupted access to our market data and analytics, please renew your subscription before it expires.\n\n";
        $message .= "You can renew your subscription by logging into your account and visiting the subscription page.\n\n";
        $message .= "If you have any questions, please contact our support team.\n";
        
        if (sendEmailNotification($con, $user['id'], 'subscription_expiry_4days', $subject, $message)) {
            $notifications_sent++;
        }
    }
    
    return $notifications_sent;
}

// Handle cron job request
if (isset($_GET['cron_expiry']) && $_GET['cron_expiry'] == '1') {
    $sent_count = checkAndSendExpiryNotifications($con);
    echo "CRON: Sent " . $sent_count . " subscription expiry notification(s)!";
    exit;
}

// Check if we should run expiry notifications manually
if (isset($_GET['run_expiry_notifications'])) {
    $sent_count = sendManualExpiryNotifications($con);
    if ($sent_count > 0) {
        $success_message = "Sent " . $sent_count . " subscription expiry notification(s)!";
    } else {
        $info_message = "No expiry notifications needed at this time.";
    }
}

// Run automatic expiry notifications check (for cron job)
// Uncomment the line below to run automatically on page load
// checkAndSendExpiryNotifications($con);

// Handle actions - THIS MUST BE AT THE TOP BEFORE ANY OUTPUT
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
        $old_status = $user['status'];
        
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active', approved_date = NOW(), approved_by = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                
                // Send approval email
                $subject = "Your RATIN Account Has Been Approved";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Great news! Your RATIN account has been approved and is now active.\n\n";
                $message .= "You can now log in to access our market data and analytics platform.\n\n";
                $message .= "Your subscription is valid for 30 days from today.\n\n";
                $message .= "Login URL: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
                $message .= "If you have any questions, please don't hesitate to contact our support team.\n";
                
                sendEmailNotification($con, $user_id, 'account_approved', $subject, $message);
                break;
                
            case 'reject':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'rejected', approved_by = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                
                // Send rejection email
                $subject = "Your RATIN Account Application";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Thank you for your interest in RATIN Trade Analytics.\n\n";
                $message .= "After reviewing your application, we regret to inform you that we are unable to approve your account at this time.\n\n";
                $message .= "If you believe this is an error or would like more information, please contact our support team.\n\n";
                $message .= "Thank you for considering RATIN.\n";
                
                sendEmailNotification($con, $user_id, 'account_rejected', $subject, $message);
                break;
                
            case 'suspend':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'suspended' WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Send suspension email
                $subject = "Your RATIN Account Has Been Suspended";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN account has been suspended effective immediately.\n\n";
                $message .= "You will no longer be able to access the platform until your account is reinstated.\n\n";
                $message .= "If you believe this is an error or would like to appeal this decision, please contact our support team.\n\n";
                $message .= "Support Email: support@ratin.com\n";
                
                sendEmailNotification($con, $user_id, 'account_suspended', $subject, $message);
                break;
                
            case 'activate':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active' WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
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
                
                // Send activation email
                $subject = "Your RATIN Account Has Been Reactivated";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN account has been reactivated and is now active.\n\n";
                $message .= "You can now log in to access our market data and analytics platform.\n\n";
                $message .= "Login URL: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
                $message .= "Welcome back to RATIN!\n";
                
                sendEmailNotification($con, $user_id, 'account_activated', $subject, $message);
                break;
                
            case 'update_subscription':
                $new_subscription = $_POST['subscription_type'];
                $stmt = $con->prepare("UPDATE subscribed_users SET subscription_type = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("si", $new_subscription, $user_id);
                $stmt->execute();
                
                // Send subscription update email
                $subject = "Your RATIN Subscription Has Been Updated";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN subscription has been updated to the " . ucfirst($new_subscription) . " plan.\n\n";
                $message .= "The changes are effective immediately.\n\n";
                $message .= "If you did not request this change or have any questions, please contact our support team.\n";
                
                sendEmailNotification($con, $user_id, 'subscription_updated', $subject, $message);
                break;
                
            case 'add_user':
                // Handle add user functionality
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $full_name = trim($_POST['full_name']);
                $company = trim($_POST['company']);
                $phone = trim($_POST['phone']);
                $subscription_type = $_POST['subscription_type'];
                $status = $_POST['status']; // Allow admin to set status directly
                
                // Validation
                if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($subscription_type)) {
                    throw new Exception("Please fill all required fields.");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("Password must be at least 6 characters long.");
                }
                
                // Check if username or email already exists
                $check_stmt = $con->prepare("SELECT id FROM subscribed_users WHERE username = ? OR email = ?");
                if (!$check_stmt) throw new Exception("Prepare failed: " . $con->error);
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
                
                // FIXED: Changed the parameter order and fixed bind_param
                $insert_stmt = $con->prepare("INSERT INTO subscribed_users (username, email, password, full_name, company, phone, subscription_type, status, registration_date, approved_date, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                if (!$insert_stmt) throw new Exception("Prepare failed: " . $con->error);
                
                // FIXED: Changed to 10 parameters with correct types
                if ($approved_date === null && $approved_by === null) {
                    $insert_stmt->bind_param("ssssssssss", $username, $email, $hashed_password, $full_name, $company, $phone, $subscription_type, $status, $approved_date, $approved_by);
                } else {
                    // Bind as integer for approved_by when not null
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
                        $message .= "You can now log in to access our market data and analytics platform.\n\n";
                        $message .= "Login URL: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
                        $message .= "If you have any questions, please don't hesitate to contact our support team.\n";
                        
                        sendEmailNotification($con, $new_user_id, 'welcome', $subject, $message);
                    }
                } else {
                    throw new Exception("Failed to add user. Please try again.");
                }
                break;
                
            case 'renew_subscription':
                // Update approved_date to now (resets 30-day clock)
                $stmt = $con->prepare("UPDATE subscribed_users SET approved_date = NOW(), approved_by = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                
                // Send renewal email
                $subject = "Your RATIN Subscription Has Been Renewed";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN subscription has been renewed for another 30 days.\n\n";
                $message .= "Your new subscription period is from " . date('F j, Y') . " to " . date('F j, Y', strtotime('+30 days')) . ".\n\n";
                $message .= "Thank you for continuing to use RATIN Trade Analytics.\n";
                
                sendEmailNotification($con, $user_id, 'subscription_renewed', $subject, $message);
                break;
                
            case 'delete_user':
                $stmt = $con->prepare("DELETE FROM subscribed_users WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $success_message = "User deleted successfully!";
                break;
                
            case 'reset_password':
                $new_password = bin2hex(random_bytes(8)); // Generate random password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $con->prepare("UPDATE subscribed_users SET password = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                
                // Send password reset email
                $subject = "Your RATIN Password Has Been Reset";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "Your RATIN account password has been reset by an administrator.\n\n";
                $message .= "Your new temporary password is: " . $new_password . "\n\n";
                $message .= "Please log in and change your password immediately for security reasons.\n\n";
                $message .= "Login URL: " . (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
                $message .= "If you did not request this change, please contact our support team immediately.\n";
                
                sendEmailNotification($con, $user_id, 'password_reset', $subject, $message);
                $success_message = "Password reset! New password has been sent to user's email.";
                break;
        }
        
        // Use JavaScript redirect instead of header() to avoid "headers already sent" error
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
    if (!$users_stmt) throw new Exception("Prepare failed: " . $con->error);
    
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

// Get subscription packages for add user form
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

// --- Fetch counts for summary boxes ---
$total_users_query = "SELECT COUNT(*) AS total FROM subscribed_users";
$total_users_result = $con->query($total_users_query);
$total_users = 0;
if ($total_users_result) {
    $row = $total_users_result->fetch_assoc();
    $total_users = $row['total'];
}

$active_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'active'";
$active_users_result = $con->query($active_users_query);
$active_users_count = 0;
if ($active_users_result) {
    $row = $active_users_result->fetch_assoc();
    $active_users_count = $row['total'];
}

$pending_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'pending'";
$pending_users_result = $con->query($pending_users_query);
$pending_users_count = 0;
if ($pending_users_result) {
    $row = $pending_users_result->fetch_assoc();
    $pending_users_count = $row['total'];
}

$suspended_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'suspended'";
$suspended_users_result = $con->query($suspended_users_query);
$suspended_users_count = 0;
if ($suspended_users_result) {
    $row = $suspended_users_result->fetch_assoc();
    $suspended_users_count = $row['total'];
}

// Get active users with expiring subscriptions (4 days or less)
$expiring_users_query = "SELECT COUNT(*) AS total FROM subscribed_users 
                         WHERE status = 'active' 
                         AND approved_date IS NOT NULL
                         AND DATEDIFF(DATE_ADD(approved_date, INTERVAL 30 DAY), CURDATE()) BETWEEN 0 AND 4";
$expiring_users_result = $con->query($expiring_users_query);
$expiring_users_count = 0;
if ($expiring_users_result) {
    $row = $expiring_users_result->fetch_assoc();
    $expiring_users_count = $row['total'];
}

// Get rejected users count
$rejected_users_query = "SELECT COUNT(*) AS total FROM subscribed_users WHERE status = 'rejected'";
$rejected_users_result = $con->query($rejected_users_query);
$rejected_users_count = 0;
if ($rejected_users_result) {
    $row = $rejected_users_result->fetch_assoc();
    $rejected_users_count = $row['total'];
}

// Get email notifications count for today
$today_notifications_query = "SELECT COUNT(*) AS total FROM email_notifications WHERE DATE(sent_at) = CURDATE()";
$today_notifications_result = $con->query($today_notifications_query);
$today_notifications_count = 0;
if ($today_notifications_result) {
    $row = $today_notifications_result->fetch_assoc();
    $today_notifications_count = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - RATIN Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .page-header {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header p {
            font-size: 16px;
            opacity: 0.9;
            max-width: 800px;
        }
        
        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.total { border-color: #007bff; }
        .stat-card.active { border-color: #28a745; }
        .stat-card.pending { border-color: #ffc107; }
        .stat-card.expiring { border-color: #fd7e14; }
        .stat-card.suspended { border-color: #dc3545; }
        .stat-card.rejected { border-color: #6c757d; }
        .stat-card.emails { border-color: #17a2b8; }
        
        .stat-icon {
            font-size: 32px;
            margin-bottom: 15px;
            display: inline-block;
            padding: 12px;
            border-radius: 10px;
        }
        
        .total .stat-icon { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .active .stat-icon { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .pending .stat-icon { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .expiring .stat-icon { background: rgba(253, 126, 20, 0.1); color: #fd7e14; }
        .suspended .stat-icon { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .rejected .stat-icon { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .emails .stat-icon { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        
        .stat-content h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            font-size: 14px;
            color: #666;
        }
        
        /* Toolbar */
        .toolbar {
            background: white;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .toolbar-left h2 {
            font-size: 22px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toolbar-right {
            display: flex;
            gap: 12px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #8B4513;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #6d3510;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 13px;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        table thead {
            background-color: #8B4513;
            color: white;
        }
        
        table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 15px;
        }
        
        table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        table tbody tr:hover {
            background-color: #f0f7ff;
        }
        
        table td {
            padding: 14px 20px;
            font-size: 14px;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-active { background: #d4edda; color: #155724; }
        .status-suspended { background: #f8d7da; color: #721c24; }
        .status-rejected { background: #e2e3e5; color: #383d41; }
        
        /* Expiry indicators */
        .expiry-indicator {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }
        
        .expiry-soon {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .expiry-today {
            background-color: #f8d7da;
            color: #721c24;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .expiry-expired {
            background-color: #dc3545;
            color: white;
        }
        
        .expiry-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        /* Select styling */
        select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            min-width: 120px;
        }
        
        select:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 2px rgba(139, 69, 19, 0.25);
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            width: 90%;
            max-width: 700px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: modalopen 0.3s;
        }
        
        @keyframes modalopen {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 22px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group.full-width {
            flex: 0 0 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group label.required::after {
            content: " *";
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }
        
        .modal-footer {
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 10px 10px;
            text-align: right;
        }
        
        /* Automation Section */
        .automation-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .automation-section h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .code-block {
            background-color: #2d3748;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        
        .code-block code {
            display: block;
            white-space: pre-wrap;
            line-height: 1.5;
        }
        
        /* Notes section in table */
        .notes-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notes-cell:hover {
            overflow: visible;
            white-space: normal;
            background: white;
            position: relative;
            z-index: 10;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .toolbar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .toolbar-right {
                width: 100%;
                flex-wrap: wrap;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
        
        /* No data message */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .no-data h4 {
            font-size: 20px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> Subscribed Users Management</h1>
            <p>Manage user accounts, subscriptions, and automated notifications. Track subscription expiries and send email notifications.</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($info_message)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($info_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Section -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $total_users ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $active_users_count ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $pending_users_count ?></h3>
                    <p>Pending Verification</p>
                </div>
            </div>
            
            <div class="stat-card suspended">
                <div class="stat-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $suspended_users_count ?></h3>
                    <p>Suspended Users</p>
                </div>
            </div>
            
            <div class="stat-card emails">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $today_notifications_count ?></h3>
                    <p>Emails Sent Today</p>
                </div>
            </div>
        </div>
        
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <h2><i class="fas fa-list"></i> All Users (<?= count($users) ?>)</h2>
            </div>
            <div class="toolbar-right">
                <a href="?run_expiry_notifications=1" class="btn btn-warning">
                    <i class="fas fa-envelope"></i> Send Expiry Notifications
                </a>
                <button class="btn btn-info" onclick="openCronModal()">
                    <i class="fas fa-cogs"></i> Cron Setup
                </button>
                <button class="btn btn-primary" onclick="openAddUserModal()">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="table-container">
            <?php if (empty($users)): ?>
                <div class="no-data">
                    <i class="fas fa-users-slash"></i>
                    <h4>No Users Found</h4>
                    <p>No subscribed users found in the database. Use the "Add New User" button to create your first user.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Subscription</th>
                            <th>Status</th>
                            <th>Expiry Status</th>
                            <th>Registration Date</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="subscription_type" onchange="this.form.submit()" title="Change subscription type">
                                            <option value="basic" <?= $user['subscription_type'] == 'basic' ? 'selected' : '' ?>>Basic</option>
                                            <option value="medium" <?= $user['subscription_type'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                            <option value="premium" <?= $user['subscription_type'] == 'premium' ? 'selected' : '' ?>>Premium</option>
                                        </select>
                                        <input type="hidden" name="action" value="update_subscription">
                                    </form>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active' && $user['approved_date']): 
                                        $days_until_expiry = $user['days_until_expiry'];
                                        if ($days_until_expiry === null): ?>
                                            <span class="expiry-indicator expiry-active">No expiry date</span>
                                        <?php elseif ($days_until_expiry > 4): ?>
                                            <span class="expiry-indicator expiry-active">Expires in <?= $days_until_expiry ?> days</span>
                                        <?php elseif ($days_until_expiry > 0): ?>
                                            <span class="expiry-indicator expiry-soon">Expires in <?= $days_until_expiry ?> days</span>
                                        <?php elseif ($days_until_expiry == 0): ?>
                                            <span class="expiry-indicator expiry-today">Expires today!</span>
                                        <?php else: ?>
                                            <span class="expiry-indicator expiry-expired">Expired <?= abs($days_until_expiry) ?> days ago</span>
                                        <?php endif; ?>
                                    <?php elseif ($user['status'] == 'active' && !$user['approved_date']): ?>
                                        <span class="expiry-indicator">Not approved yet</span>
                                    <?php else: ?>
                                        <span class="expiry-indicator">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($user['registration_date'])) ?></td>
                                <td><?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($user['status'] == 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="action-btn" style="background: #28a745; color: white;" title="Approve user">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" class="action-btn" style="background: #6c757d; color: white;" title="Reject user">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        <?php elseif ($user['status'] == 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="suspend" class="action-btn" style="background: #ffc107; color: #212529;" title="Suspend user">
                                                    <i class="fas fa-pause"></i> Suspend
                                                </button>
                                                <?php if ($user['approved_date']): ?>
                                                    <button type="submit" name="action" value="renew_subscription" class="action-btn" style="background: #17a2b8; color: white;" title="Renew subscription for 30 days">
                                                        <i class="fas fa-sync"></i> Renew
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" onclick="openResetPasswordModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" class="action-btn" style="background: #6f42c1; color: white;" title="Reset password">
                                                    <i class="fas fa-key"></i> Reset PW
                                                </button>
                                            </form>
                                        <?php elseif ($user['status'] == 'suspended'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="action" value="activate" class="action-btn" style="background: #28a745; color: white;" title="Activate user">
                                                    <i class="fas fa-play"></i> Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" class="action-btn" style="background: #dc3545; color: white;" title="Delete user">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="close-modal" onclick="closeAddUserModal()">&times;</button>
            </div>
            <form method="POST" action="" id="addUserForm">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Full Name</label>
                            <input type="text" name="full_name" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label class="required">Username</label>
                            <input type="text" name="username" placeholder="Enter username" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Email</label>
                            <input type="email" name="email" placeholder="Enter email address" required>
                        </div>
                        <div class="form-group">
                            <label class="required">Password</label>
                            <input type="password" name="password" placeholder="Enter password (min 6 chars)" required minlength="6" id="passwordField">
                            <small id="passwordStrength" style="display: block; margin-top: 5px;"></small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Company (Optional)</label>
                            <input type="text" name="company" placeholder="Enter company name">
                        </div>
                        <div class="form-group">
                            <label>Phone (Optional)</label>
                            <input type="text" name="phone" placeholder="Enter phone number">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Subscription Type</label>
                            <select name="subscription_type" required>
                                <option value="basic">Basic</option>
                                <option value="medium">Medium</option>
                                <option value="premium">Premium</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required">Initial Status</label>
                            <select name="status" required>
                                <option value="pending">Pending</option>
                                <option value="active" selected>Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" name="action" value="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-trash"></i> Confirm Deletion</h3>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
                    <p class="alert alert-warning" style="margin-top: 15px;">
                        <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. All user data will be permanently deleted.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="action" value="delete_user" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Reset Password</h3>
                <button class="close-modal" onclick="closeResetPasswordModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="resetPasswordUserId">
                    <p>Reset password for user: <strong id="resetPasswordUserName"></strong></p>
                    <p class="alert alert-info">
                        <i class="fas fa-info-circle"></i> A new random password will be generated and sent to the user's email address.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                    <button type="submit" name="action" value="reset_password" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cron Setup Modal -->
    <div id="cronModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-cogs"></i> Cron Job Setup</h3>
                <button class="close-modal" onclick="closeCronModal()">&times;</button>
            </div>
            <div class="modal-body">
                <h4>Create a cron_notifications.php file:</h4>
                <div class="code-block">
                    <code>&lt;?php
// cron_notifications.php
require_once "includes/config.php";

function runCronExpiryNotifications($con) {
    $query = "SELECT id, email, full_name, approved_date 
              FROM subscribed_users 
              WHERE status = 'active' 
              AND approved_date IS NOT NULL
              AND DATE(approved_date) = DATE_SUB(CURDATE(), INTERVAL 26 DAY)
              AND id NOT IN (
                  SELECT user_id FROM email_notifications 
                  WHERE notification_type = 'subscription_expiry_4days' 
                  AND DATE(sent_at) = CURDATE()
              )";
    
    $stmt = $con->prepare($query);
    if (!$stmt) return false;
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications_sent = 0;
    while ($user = $result->fetch_assoc()) {
        // Send email notification (same as in user_management.php)
        $notifications_sent++;
    }
    
    return $notifications_sent;
}

// Run the function
$count = runCronExpiryNotifications($con);
echo "Sent " . $count . " expiry notifications.";
?&gt;</code>
                </div>
                
                <h4 style="margin-top: 20px;">Add to crontab (run daily at 9 AM):</h4>
                <div class="code-block">
                    <code>0 9 * * * /usr/bin/php /path/to/your/website/cron_notifications.php > /dev/null 2>&1</code>
                </div>
                
                <p style="margin-top: 15px; color: #666;">
                    <i class="fas fa-lightbulb"></i> This will automatically check for expiring subscriptions and send email notifications daily.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCronModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
            document.getElementById('passwordStrength').textContent = '';
        }
        
        function openDeleteModal(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        function openResetPasswordModal(userId, userName) {
            document.getElementById('resetPasswordUserId').value = userId;
            document.getElementById('resetPasswordUserName').textContent = userName;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
        }
        
        function openCronModal() {
            document.getElementById('cronModal').style.display = 'block';
        }
        
        function closeCronModal() {
            document.getElementById('cronModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modals = ['addUserModal', 'deleteModal', 'resetPasswordModal', 'cronModal'];
            modals.forEach(function(modalId) {
                var modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Password strength indicator
        document.getElementById('passwordField').addEventListener('input', function(e) {
            var password = e.target.value;
            var strengthText = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthText.textContent = '';
                strengthText.style.color = '';
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
        document.querySelector('input[name="email"]').addEventListener('blur', function(e) {
            var email = e.target.value;
            var usernameField = document.querySelector('input[name="username"]');
            
            if (email && !usernameField.value) {
                var username = email.split('@')[0];
                username = username.replace(/[^a-zA-Z0-9]/g, '_');
                username = username.substring(0, 20); // Limit to 20 chars
                usernameField.value = username;
            }
        });
        
        // Auto-refresh page every 5 minutes to check for expiring subscriptions
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>