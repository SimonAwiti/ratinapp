<?php
// user_management.php
session_start();
require_once '../admin/includes/admin_header.php';

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

$message = '';
$message_type = '';

/**
 * Function to send email notification
 */
function sendEmailNotification($con, $user_id, $notification_type, $subject, $message) {
    $stmt = $con->prepare("SELECT email, full_name FROM subscribed_users WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;
    $user = $result->fetch_assoc();
    
    $to_email = $user['email'];
    $to_name = $user['full_name'];
    $headers = "From: RATIN Trade Analytics <noreply@ratin.com>\r\n";
    $headers .= "Reply-To: support@ratin.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>' . htmlspecialchars($subject) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(90deg, #00450d 0%, #800000 100%); color: white; padding: 20px; text-align: center; border-radius: 12px 12px 0 0; }
        .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 12px 12px; border: 1px solid #ddd; border-top: none; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
    </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h2>RATIN Trade Analytics</h2></div>
            <div class="content">
                <h3>' . htmlspecialchars($subject) . '</h3>
                <p>Hello ' . htmlspecialchars($to_name) . ',</p>
                ' . nl2br(htmlspecialchars($message)) . '
                <p>Best regards,<br>The RATIN Team</p>
            </div>
            <div class="footer"><p>© ' . date('Y') . ' RATIN Trade Analytics. All rights reserved.</p></div>
        </div>
    </body>
    </html>';
    
    $email_sent = mail($to_email, $subject, $html_message, $headers);
    
    $log_stmt = $con->prepare("INSERT INTO email_notifications (user_id, notification_type, subject, message, sent_at, status) VALUES (?, ?, ?, ?, NOW(), ?)");
    if ($log_stmt) {
        $status = $email_sent ? 'sent' : 'failed';
        $log_stmt->bind_param("issss", $user_id, $notification_type, $subject, $message, $status);
        $log_stmt->execute();
        $log_stmt->close();
    }
    return $email_sent;
}

// Handle Bulk Delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    $selected_ids = $_POST['selected_users'];
    $deleted_count = 0;
    
    foreach ($selected_ids as $user_id) {
        $delete_stmt = $con->prepare("DELETE FROM subscribed_users WHERE id = ?");
        $delete_stmt->bind_param("i", $user_id);
        if ($delete_stmt->execute()) {
            $deleted_count++;
        }
        $delete_stmt->close();
    }
    
    if ($deleted_count > 0) {
        $message = "$deleted_count user(s) deleted successfully!";
        $message_type = "success";
    }
}

// Handle individual actions
if (isset($_POST['action'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $admin_id = $_SESSION['admin_id'];
    
    try {
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
                sendEmailNotification($con, $user_id, 'account_approved', 'Your RATIN Account Has Been Approved', 
                    "Dear " . $user['full_name'] . ",\n\nGreat news! Your RATIN account has been approved and is now active.\n\nYour subscription is valid for 30 days from today.");
                $message = "User approved successfully!";
                $message_type = "success";
                break;
                
            case 'reject':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'rejected', approved_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                $message = "User rejected.";
                $message_type = "warning";
                break;
                
            case 'suspend':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'suspended' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User suspended.";
                $message_type = "warning";
                break;
                
            case 'activate':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User activated successfully!";
                $message_type = "success";
                break;
                
            case 'update_subscription':
                $new_subscription = $_POST['subscription_type'];
                $stmt = $con->prepare("UPDATE subscribed_users SET subscription_type = ? WHERE id = ?");
                $stmt->bind_param("si", $new_subscription, $user_id);
                $stmt->execute();
                $message = "Subscription updated!";
                $message_type = "success";
                break;
                
            case 'renew_subscription':
                $stmt = $con->prepare("UPDATE subscribed_users SET approved_date = NOW(), approved_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                $message = "Subscription renewed for 30 days!";
                $message_type = "success";
                break;
                
            case 'delete_user':
                $stmt = $con->prepare("DELETE FROM subscribed_users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $message = "User deleted successfully!";
                $message_type = "success";
                break;
                
            case 'reset_password':
                $new_password = bin2hex(random_bytes(6));
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $con->prepare("UPDATE subscribed_users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                sendEmailNotification($con, $user_id, 'password_reset', 'Your RATIN Password Has Been Reset',
                    "Dear " . $user['full_name'] . ",\n\nYour password has been reset.\n\nNew temporary password: " . $new_password . "\n\nPlease log in and change it immediately.");
                $message = "Password reset! New password sent to user's email.";
                $message_type = "success";
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
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $approved_date = $status == 'active' ? date('Y-m-d H:i:s') : null;
                $approved_by = $status == 'active' ? $admin_id : null;
                
                $insert_stmt = $con->prepare("INSERT INTO subscribed_users (username, email, password, full_name, company, phone, subscription_type, status, registration_date, approved_date, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $insert_stmt->bind_param("ssssssssss", $username, $email, $hashed_password, $full_name, $company, $phone, $subscription_type, $status, $approved_date, $approved_by);
                
                if ($insert_stmt->execute()) {
                    $new_user_id = $insert_stmt->insert_id;
                    if ($status == 'active') {
                        sendEmailNotification($con, $new_user_id, 'welcome', 'Welcome to RATIN Trade Analytics!',
                            "Dear " . $full_name . ",\n\nWelcome to RATIN! Your account has been created and is active.\n\nUsername: " . $username . "\nSubscription: " . ucfirst($subscription_type));
                    }
                    $message = "User added successfully!";
                    $message_type = "success";
                } else {
                    throw new Exception("Failed to add user.");
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Action failed: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$valid_limits = [10, 20, 50, 100];
if (!in_array($limit, $valid_limits)) $limit = 20;

// Get sort parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'registration_date';
$sort_direction = isset($_GET['dir']) && $_GET['dir'] == 'asc' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['id', 'username', 'full_name', 'registration_date'];
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'registration_date';

// Fetch all subscribed users with sorting
$users_query = "SELECT su.*, au.username as approved_by_name,
                DATEDIFF(DATE_ADD(su.approved_date, INTERVAL 30 DAY), CURDATE()) as days_until_expiry,
                DATE_ADD(su.approved_date, INTERVAL 30 DAY) as expiry_date
                FROM subscribed_users su 
                LEFT JOIN admin_users au ON su.approved_by = au.id 
                ORDER BY " . $sort_column . " " . $sort_direction;
$users_result = $con->query($users_query);
$all_users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $all_users[] = $row;
    }
}

// Calculate statistics
$total_users = count($all_users);
$active_count = count(array_filter($all_users, function($u) { return $u['status'] == 'active'; }));
$pending_count = count(array_filter($all_users, function($u) { return $u['status'] == 'pending'; }));
$suspended_count = count(array_filter($all_users, function($u) { return $u['status'] == 'suspended'; }));
$rejected_count = count(array_filter($all_users, function($u) { return $u['status'] == 'rejected'; }));

// Pagination calculations
$total_pages = ceil($total_users / $limit);
$offset = ($page - 1) * $limit;
$users_paged = array_slice($all_users, $offset, $limit);
?>

<style>
.auth-bg-gradient {
    background: radial-gradient(circle at top left, rgba(0, 69, 13, 0.03), transparent),
                radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.03), transparent);
}
.header-accent-gradient {
    background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
}
.table-row-hover:hover {
    background-color: #fefaf5;
    transition: all 0.2s ease;
}
.stat-card {
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 500;
}
.status-active { background-color: #d1fae5; color: #065f46; }
.status-pending { background-color: #fef3c7; color: #92400e; }
.status-suspended { background-color: #fee2e2; color: #991b1b; }
.status-rejected { background-color: #f3f4f6; color: #374151; }
.subscription-select {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.375rem;
    border: 1px solid #e5e7eb;
    background-color: white;
    cursor: pointer;
}
.subscription-select:focus {
    outline: none;
    border-color: #800000;
}
.expiry-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 500;
}
.expiry-good { background-color: #d1fae5; color: #065f46; }
.expiry-soon { background-color: #fef3c7; color: #92400e; }
.expiry-today { background-color: #fee2e2; color: #991b1b; animation: pulse 1.5s infinite; }
.expiry-expired { background-color: #dc2626; color: white; }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
.search-input:focus {
    border-color: #800000;
    outline: none;
    ring: 2px solid rgba(128,0,0,0.2);
}
.action-btn {
    padding: 0.2rem 0.4rem;
    border-radius: 0.375rem;
    font-size: 0.7rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}
.modal-gradient-header {
    background: linear-gradient(135deg, #800000 0%, #00450d 100%);
}
.pagination-btn {
    min-width: 32px;
    height: 32px;
    transition: all 0.2s ease;
}
.pagination-btn:hover:not(:disabled):not(.active-page) {
    background-color: #fef3e7;
    border-color: #800000;
    color: #800000;
}
.pagination-btn.active-page {
    background-color: #800000;
    border-color: #800000;
    color: white;
}
.page-size-select {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid #e5e7eb;
    background-color: white;
    cursor: pointer;
}
.sortable {
    cursor: pointer;
    user-select: none;
}
.sortable:hover {
    color: #800000;
}
.sort-icon {
    font-size: 0.75rem;
    margin-left: 0.25rem;
    vertical-align: middle;
}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-maroon">User Management</h1>
                    <p class="text-gray-600 text-sm mt-1">Manage subscribed users, subscriptions, and account statuses</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportToCSV()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">download</span>
                        Export CSV
                    </button>
                    <button onclick="openAddUserModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">person_add</span>
                        Add User
                    </button>
                </div>
            </div>
            <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : ($message_type == 'error' ? 'bg-red-100 text-red-700 border-l-4 border-red-600' : 'bg-yellow-100 text-yellow-700 border-l-4 border-yellow-600') ?>">
                <span class="material-symbols-outlined text-base"><?= $message_type == 'success' ? 'check_circle' : ($message_type == 'error' ? 'error' : 'warning') ?></span>
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Total</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($total_users) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-maroon/40">group</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Active</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($active_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-green-600/40">check_circle</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Pending</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($pending_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-yellow-500/40">hourglass_empty</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Suspended</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($suspended_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-red-500/40">block</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-gray-400">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Rejected</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($rejected_count) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-gray-400/40">cancel</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Premium</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format(count(array_filter($all_users, function($u) { return $u['subscription_type'] == 'premium'; }))) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-purple-500/40">diamond</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Expiring Soon</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format(count(array_filter($all_users, function($u) { return $u['status'] == 'active' && $u['days_until_expiry'] !== null && $u['days_until_expiry'] >= 0 && $u['days_until_expiry'] <= 7; }))) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-orange-500/40">timer</span>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                        <input type="text" id="searchInput" placeholder="Search by username, name, or email..." 
                               class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                    </div>
                </div>
                <div class="flex gap-2">
                    <select id="statusFilter" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 bg-white">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select id="subscriptionFilter" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 bg-white">
                        <option value="">All Plans</option>
                        <option value="basic">Basic</option>
                        <option value="medium">Medium</option>
                        <option value="premium">Premium</option>
                    </select>
                    <select id="expiryFilter" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 bg-white">
                        <option value="">Expiry Status</option>
                        <option value="good">&gt;7 days left</option>
                        <option value="soon">≤7 days left</option>
                        <option value="today">Expires today</option>
                        <option value="expired">Expired</option>
                    </select>
                    <button id="bulkDeleteBtn" class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <span class="material-symbols-outlined text-base align-middle">delete</span>
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table with Pagination at Bottom -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="usersTable">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="w-8 px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                                <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-maroon focus:ring-maroon/20">
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">
                                ID 
                                <?php if ($sort_column == 'id'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="username">
                                Username
                                <?php if ($sort_column == 'username'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="full_name">
                                Full Name
                                <?php if ($sort_column == 'full_name'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Plan</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Expiry</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="registration_date">
                                Registered
                                <?php if ($sort_column == 'registration_date'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-36">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php if (empty($users_paged)): ?>
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-3xl">people</span>
                                    <p class="text-sm mt-1">No users found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users_paged as $user): 
                                $expiry_class = 'expiry-good';
                                $expiry_text = 'N/A';
                                if ($user['status'] == 'active' && $user['approved_date']) {
                                    $days = $user['days_until_expiry'];
                                    if ($days === null) {
                                        $expiry_class = 'expiry-good';
                                        $expiry_text = 'No expiry';
                                    } elseif ($days > 7) {
                                        $expiry_class = 'expiry-good';
                                        $expiry_text = $days . 'd';
                                    } elseif ($days > 0 && $days <= 7) {
                                        $expiry_class = 'expiry-soon';
                                        $expiry_text = $days . 'd';
                                    } elseif ($days == 0) {
                                        $expiry_class = 'expiry-today';
                                        $expiry_text = 'Today!';
                                    } else {
                                        $expiry_class = 'expiry-expired';
                                        $expiry_text = 'Expired';
                                    }
                                }
                            ?>
                                <tr class="table-row-hover" data-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" 
                                    data-fullname="<?= htmlspecialchars($user['full_name']) ?>" data-email="<?= htmlspecialchars($user['email']) ?>" 
                                    data-status="<?= $user['status'] ?>" data-subscription="<?= $user['subscription_type'] ?>"
                                    data-expiry-days="<?= $user['days_until_expiry'] ?? '' ?>">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="row-checkbox rounded border-gray-300 text-maroon focus:ring-maroon/20" value="<?= $user['id'] ?>">
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600"><?= $user['id'] ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-gray-400 text-sm">person</span>
                                            <span class="font-medium text-gray-800 text-xs"><?= htmlspecialchars($user['username']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                                    <td class="px-3 py-2">
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="subscription_type" onchange="this.form.submit()" class="subscription-select text-xs">
                                                <option value="basic" <?= $user['subscription_type'] == 'basic' ? 'selected' : '' ?>>Basic</option>
                                                <option value="medium" <?= $user['subscription_type'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                                <option value="premium" <?= $user['subscription_type'] == 'premium' ? 'selected' : '' ?>>Premium</option>
                                            </select>
                                            <input type="hidden" name="action" value="update_subscription">
                                        </form>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="status-badge status-<?= $user['status'] ?>">
                                            <span class="material-symbols-outlined text-xs">
                                                <?= $user['status'] == 'active' ? 'check_circle' : ($user['status'] == 'pending' ? 'hourglass_empty' : ($user['status'] == 'suspended' ? 'block' : 'cancel')) ?>
                                            </span>
                                            <?= ucfirst(substr($user['status'], 0, 3)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <?php if ($user['status'] == 'active' && $user['approved_date']): ?>
                                            <span class="expiry-indicator <?= $expiry_class ?>">
                                                <span class="material-symbols-outlined text-xs">schedule</span>
                                                <?= $expiry_text ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($user['registration_date'])) ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-center gap-1">
                                            <?php if ($user['status'] == 'pending'): ?>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="action-btn bg-green-100 text-green-700 hover:bg-green-200" title="Approve">
                                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="action-btn bg-gray-100 text-gray-700 hover:bg-gray-200" title="Reject">
                                                        <span class="material-symbols-outlined text-sm">cancel</span>
                                                    </button>
                                                </form>
                                            <?php elseif ($user['status'] == 'active'): ?>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="action" value="suspend" class="action-btn bg-yellow-100 text-yellow-700 hover:bg-yellow-200" title="Suspend">
                                                        <span class="material-symbols-outlined text-sm">pause_circle</span>
                                                    </button>
                                                    <?php if ($user['approved_date']): ?>
                                                        <button type="submit" name="action" value="renew_subscription" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Renew">
                                                            <span class="material-symbols-outlined text-sm">autorenew</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            <?php elseif ($user['status'] == 'suspended'): ?>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="action" value="activate" class="action-btn bg-green-100 text-green-700 hover:bg-green-200" title="Activate">
                                                        <span class="material-symbols-outlined text-sm">play_circle</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" 
                                                    class="action-btn bg-purple-100 text-purple-700 hover:bg-purple-200" title="Reset Password">
                                                <span class="material-symbols-outlined text-sm">key</span>
                                            </button>
                                            
                                            <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" 
                                                    class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION SECTION - AT THE BOTTOM OF THE TABLE -->
            <div class="border-t border-gray-200 px-4 py-3 bg-white">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div class="text-xs text-gray-500">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_users) ?> of <?= $total_users ?> users
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Rows:</span>
                            <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        
                        <nav class="flex items-center gap-1">
                            <button onclick="goToPage(1)" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 flex items-center justify-center <?= $page <= 1 ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $page <= 1 ? 'disabled' : '' ?>>
                                <span class="material-symbols-outlined text-sm">first_page</span>
                            </button>
                            <button onclick="goToPage(<?= $page - 1 ?>)" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 flex items-center justify-center <?= $page <= 1 ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $page <= 1 ? 'disabled' : '' ?>>
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </button>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            if ($start_page > 1) {
                                echo '<button onclick="goToPage(1)" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 text-xs">1</button>';
                                if ($start_page > 2) echo '<span class="text-gray-400 px-1">...</span>';
                            }
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active_class = ($i == $page) ? 'active-page bg-maroon text-white' : 'border border-gray-200 hover:bg-gray-50';
                                echo '<button onclick="goToPage(' . $i . ')" class="pagination-btn w-7 h-7 rounded text-xs ' . $active_class . '">' . $i . '</button>';
                            }
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<span class="text-gray-400 px-1">...</span>';
                                echo '<button onclick="goToPage(' . $total_pages . ')" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 text-xs">' . $total_pages . '</button>';
                            }
                            ?>
                            
                            <button onclick="goToPage(<?= $page + 1 ?>)" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 flex items-center justify-center <?= $page >= $total_pages ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </button>
                            <button onclick="goToPage(<?= $total_pages ?>)" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 flex items-center justify-center <?= $page >= $total_pages ? 'opacity-40 cursor-not-allowed' : '' ?>" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                <span class="material-symbols-outlined text-sm">last_page</span>
                            </button>
                        </nav>
                    </div>
                    
                    <a href="../base/landing_page.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0">
                <h3 class="text-base font-semibold text-white">Add New User</h3>
                <button onclick="closeModal('addUserModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="addUserForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" required class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required minlength="6" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Company</label>
                            <input type="text" name="company" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Phone</label>
                            <input type="text" name="phone" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Subscription Plan</label>
                            <select name="subscription_type" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                                <option value="basic">Basic</option>
                                <option value="medium">Medium</option>
                                <option value="premium">Premium</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Initial Status</label>
                            <select name="status" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                                <option value="pending">Pending</option>
                                <option value="active" selected>Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('addUserModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="action" value="add_user" class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg w-full max-w-md shadow-xl">
        <div class="p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-red-500">warning</span>
                <h3 class="text-base font-semibold text-gray-800">Confirm Deletion</h3>
            </div>
            <p class="text-sm text-gray-500 mb-3">Delete user: <strong id="deleteUserName"></strong>?</p>
            <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-3 text-xs text-red-700">
                <span class="material-symbols-outlined text-xs align-middle">info</span>
                This action cannot be undone.
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" name="action" value="delete_user" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg w-full max-w-md shadow-xl">
        <div class="p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-blue-500">key</span>
                <h3 class="text-base font-semibold text-gray-800">Reset Password</h3>
            </div>
            <p class="text-sm text-gray-500 mb-3">Reset password for: <strong id="resetPasswordUserName"></strong></p>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-2 mb-3 text-xs text-blue-700">
                <span class="material-symbols-outlined text-xs align-middle">info</span>
                A new random password will be sent to the user's email.
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="resetPasswordUserId">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('resetPasswordModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" name="action" value="reset_password" class="px-3 py-1.5 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Pagination functions
function goToPage(page) {
    const limit = document.getElementById('rowsPerPage').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=' + page + '&limit=' + limit;
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function changeRowsPerPage() {
    const limit = document.getElementById('rowsPerPage').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=1&limit=' + limit;
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

// Sorting function
function sortTable(column) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort');
    const currentDir = urlParams.get('dir');
    let newDir = 'asc';
    
    if (currentSort === column && currentDir === 'asc') {
        newDir = 'desc';
    }
    
    const limit = document.getElementById('rowsPerPage').value;
    window.location.href = '?page=1&limit=' + limit + '&sort=' + column + '&dir=' + newDir;
}

// Modal functions
function openAddUserModal() { document.getElementById('addUserModal').classList.remove('hidden'); }
function closeModal(modalId) { document.getElementById(modalId).classList.add('hidden'); }

function deleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function resetPassword(userId, userName) {
    document.getElementById('resetPasswordUserId').value = userId;
    document.getElementById('resetPasswordUserName').textContent = userName;
    document.getElementById('resetPasswordModal').classList.remove('hidden');
}

// Attach sort event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Attach click handlers to sortable headers
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortColumn = this.getAttribute('data-sort');
            if (sortColumn) {
                sortTable(sortColumn);
            }
        });
    });
    
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const subscriptionFilter = document.getElementById('subscriptionFilter');
    const expiryFilter = document.getElementById('expiryFilter');
    const tableBody = document.getElementById('tableBody');
    const rows = tableBody.querySelectorAll('tr');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    function filterRows() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        const subscriptionValue = subscriptionFilter.value;
        const expiryValue = expiryFilter.value;
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            const username = row.getAttribute('data-username')?.toLowerCase() || '';
            const fullname = row.getAttribute('data-fullname')?.toLowerCase() || '';
            const email = row.getAttribute('data-email')?.toLowerCase() || '';
            const status = row.getAttribute('data-status') || '';
            const subscription = row.getAttribute('data-subscription') || '';
            const expiryDays = parseInt(row.getAttribute('data-expiry-days')) || null;
            
            let matchesExpiry = true;
            if (expiryValue && expiryDays !== null) {
                if (expiryValue === 'good') matchesExpiry = expiryDays > 7;
                else if (expiryValue === 'soon') matchesExpiry = expiryDays > 0 && expiryDays <= 7;
                else if (expiryValue === 'today') matchesExpiry = expiryDays === 0;
                else if (expiryValue === 'expired') matchesExpiry = expiryDays < 0;
            } else if (expiryValue && expiryDays === null) {
                matchesExpiry = false;
            }
            
            const matchesSearch = searchTerm === '' || username.includes(searchTerm) || fullname.includes(searchTerm) || email.includes(searchTerm);
            const matchesStatus = statusValue === '' || status === statusValue;
            const matchesSubscription = subscriptionValue === '' || subscription === subscriptionValue;
            
            if (matchesSearch && matchesStatus && matchesSubscription && matchesExpiry) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        updateSelectAllCheckbox();
        updateBulkDeleteButton();
    }
    
    function updateSelectAllCheckbox() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const checkboxes = visibleRows.map(row => row.querySelector('.row-checkbox')).filter(cb => cb);
        const checkedCheckboxes = checkboxes.filter(cb => cb.checked);
        
        if (selectAllCheckbox) {
            if (checkboxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length === checkboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length > 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
        }
    }
    
    function updateBulkDeleteButton() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const checkboxes = visibleRows.map(row => row.querySelector('.row-checkbox')).filter(cb => cb);
        const checkedCheckboxes = checkboxes.filter(cb => cb.checked);
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = checkedCheckboxes.length === 0;
    }
    
    function getSelectedUserIds() {
        const selectedIds = [];
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox && checkbox.checked) selectedIds.push(checkbox.value);
            }
        });
        return selectedIds;
    }
    
    window.submitBulkDelete = function() {
        const selectedIds = getSelectedUserIds();
        if (selectedIds.length === 0) return;
        if (confirm('Delete ' + selectedIds.length + ' selected user(s)? This cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_users[]';
                input.value = id;
                form.appendChild(input);
            });
            const bulkInput = document.createElement('input');
            bulkInput.type = 'hidden';
            bulkInput.name = 'bulk_delete';
            bulkInput.value = '1';
            form.appendChild(bulkInput);
            document.body.appendChild(form);
            form.submit();
        }
    };
    
    window.exportToCSV = function() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        if (visibleRows.length === 0) { alert('No data to export.'); return; }
        
        const headers = ['ID', 'Username', 'Full Name', 'Email', 'Subscription', 'Status', 'Expiry Status', 'Registration Date'];
        const data = [];
        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 9) {
                const expiryCell = cells[7]?.innerText.trim() || '';
                const expiryMatch = expiryCell.match(/\d+d|Today!|Expired|No expiry/);
                const rowData = [
                    cells[1]?.innerText.trim() || '',
                    cells[2]?.innerText.trim() || '',
                    cells[3]?.innerText.trim() || '',
                    cells[4]?.innerText.trim() || '',
                    cells[5]?.innerText.trim() || '',
                    cells[6]?.innerText.trim() || '',
                    expiryMatch ? expiryMatch[0] : expiryCell,
                    cells[8]?.innerText.trim() || ''
                ];
                data.push(rowData);
            }
        });
        
        const csvContent = [headers, ...data].map(row => row.map(cell => {
            if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"'))) {
                return '"' + cell.replace(/"/g, '""') + '"';
            }
            return cell;
        }).join(',')).join('\n');
        
        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.setAttribute('download', 'users_export_' + new Date().toISOString().split('T')[0] + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };
    
    // Event listeners
    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);
    if (subscriptionFilter) subscriptionFilter.addEventListener('change', filterRows);
    if (expiryFilter) expiryFilter.addEventListener('change', filterRows);
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            visibleRows.forEach(row => {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox) checkbox.checked = selectAllCheckbox.checked;
            });
            updateBulkDeleteButton();
        });
    }
    
    rows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                updateSelectAllCheckbox();
                updateBulkDeleteButton();
            });
        }
    });
    
    if (bulkDeleteBtn) bulkDeleteBtn.addEventListener('click', submitBulkDelete);
});

// Auto-generate username from email
var emailField = document.querySelector('input[name="email"]');
if (emailField) {
    emailField.addEventListener('blur', function(e) {
        var email = e.target.value;
        var usernameField = document.querySelector('input[name="username"]');
        if (email && !usernameField.value) {
            var username = email.split('@')[0].replace(/[^a-zA-Z0-9]/g, '_').substring(0, 20);
            usernameField.value = username;
        }
    });
}
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>