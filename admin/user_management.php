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

// Handle actions - THIS MUST BE AT THE TOP BEFORE ANY OUTPUT
if (isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    $admin_id = $_SESSION['admin_id'];
    
    try {
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active', approved_date = NOW(), approved_by = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                break;
                
            case 'reject':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'rejected', approved_by = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("ii", $admin_id, $user_id);
                $stmt->execute();
                break;
                
            case 'suspend':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'suspended' WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                break;
                
            case 'activate':
                $stmt = $con->prepare("UPDATE subscribed_users SET status = 'active' WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                break;
                
            case 'update_subscription':
                $new_subscription = $_POST['subscription_type'];
                $stmt = $con->prepare("UPDATE subscribed_users SET subscription_type = ? WHERE id = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $con->error);
                $stmt->bind_param("si", $new_subscription, $user_id);
                $stmt->execute();
                break;
        }
        
        // Use JavaScript redirect instead of header() to avoid "headers already sent" error
        echo '<script>window.location.href = "user_management.php";</script>';
        exit;
        
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

// NOW include header.php after handling the POST actions
include 'includes/header.php';

// Get subscribed users
try {
    $users_stmt = $con->prepare("SELECT su.*, au.username as approved_by_name 
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
?>

<style>
    .container {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin: 20px;
    }
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    table th, table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        text-align: left;
    }
    table th {
        background-color: #f1f1f1;
    }
    .status-pending { color: #ff9800; font-weight: bold; }
    .status-active { color: #4caf50; font-weight: bold; }
    .status-suspended { color: #f44336; font-weight: bold; }
    .status-rejected { color: #9e9e9e; font-weight: bold; }
    .actions {
        display: flex;
        gap: 5px;
    }
    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }
    .alert {
        margin: 20px;
    }
    
    /* Stats Container Styles */
    .stats-container {
        display: flex;
        gap: 15px;
        justify-content: space-between;
        align-items: center;
        flex-wrap: nowrap;
        width: 87%;
        max-width: 100%;
        margin: 0 auto 20px auto;
        margin-left: 0.7%;
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
    .total-icon {
        background-color: #9b59b6;
        color: white;
    }
    .active-icon {
        background-color: #27ae60;
        color: white;
    }
    .pending-icon {
        background-color: #f39c12;
        color: white;
    }
    .suspended-icon {
        background-color: #e74c3c;
        color: white;
    }
    .stats-section {
        text-align: left;
        margin-left: 11%;
    }
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
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Subscribed Users Management</h3></div>
    <p class="p">Manage subscribed user accounts and subscriptions</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-title">Total Users</div>
            <div class="stats-number"><?= $total_users ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon active-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-title">Active Users</div>
            <div class="stats-number"><?= $active_users_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon pending-icon">
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="stats-title">Pending Verification</div>
            <div class="stats-number"><?= $pending_users_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon suspended-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stats-title">Suspended Users</div>
            <div class="stats-number"><?= $suspended_users_count ?></div>
        </div>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <h4>Subscribed Users (<?= count($users) ?> total)</h4>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="alert alert-info">
            No subscribed users found in the database. 
            <br><br>
            <strong>To fix this:</strong>
            <ol>
                <li>Make sure you've run the SQL queries to create the subscribed_users table</li>
                <li>Register some test users through the frontend registration form</li>
            </ol>
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
                    <th>Registration Date</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="subscription_type" onchange="this.form.submit()">
                                    <option value="basic" <?= $user['subscription_type'] == 'basic' ? 'selected' : '' ?>>Basic</option>
                                    <option value="medium" <?= $user['subscription_type'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="premium" <?= $user['subscription_type'] == 'premium' ? 'selected' : '' ?>>Premium</option>
                                </select>
                                <input type="hidden" name="action" value="update_subscription">
                            </form>
                        </td>
                        <td class="status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($user['registration_date'])) ?></td>
                        <td><?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                        <td class="actions">
                            <?php if ($user['status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php elseif ($user['status'] == 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="suspend" class="btn btn-warning btn-sm">Suspend</button>
                                </form>
                            <?php elseif ($user['status'] == 'suspended'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">Activate</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>