<?php
// manage_admin.php
require_once '../admin/includes/admin_header.php';

// Only allow 'super_admin' role to manage admin users
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("Location: ../base/landing_page.php");
    exit;
}

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$message = '';
$message_type = '';

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$valid_limits = [10, 20, 50, 100];
if (!in_array($limit, $valid_limits)) $limit = 20;

// Get sort parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_direction = isset($_GET['dir']) && $_GET['dir'] == 'asc' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['id', 'username', 'full_name', 'role', 'status', 'created_at'];
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'created_at';

// Handle Delete User
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    if ($user_id == $_SESSION['admin_id']) {
        $message = "You cannot delete your own account.";
        $message_type = "error";
    } else {
        $delete_stmt = $con->prepare("DELETE FROM admin_users WHERE id = ?");
        $delete_stmt->bind_param("i", $user_id);
        if ($delete_stmt->execute()) {
            $message = "Admin user deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting user: " . $delete_stmt->error;
            $message_type = "error";
        }
        $delete_stmt->close();
    }
}

// Handle Bulk Delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    $selected_ids = $_POST['selected_users'];
    $deleted_count = 0;
    
    foreach ($selected_ids as $user_id) {
        if ($user_id != $_SESSION['admin_id']) {
            $delete_stmt = $con->prepare("DELETE FROM admin_users WHERE id = ?");
            $delete_stmt->bind_param("i", $user_id);
            if ($delete_stmt->execute()) {
                $deleted_count++;
            }
            $delete_stmt->close();
        }
    }
    
    if ($deleted_count > 0) {
        $message = "$deleted_count user(s) deleted successfully!";
        $message_type = "success";
    } elseif (count($selected_ids) > 0) {
        $message = "Cannot delete your own account. Other users were not deleted.";
        $message_type = "error";
    }
}

// Handle Update User Status
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status'];
    
    if ($user_id == $_SESSION['admin_id'] && $new_status == 'inactive') {
        $message = "You cannot deactivate your own account.";
        $message_type = "error";
    } else {
        $update_stmt = $con->prepare("UPDATE admin_users SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $user_id);
        if ($update_stmt->execute()) {
            $message = "User status updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating status: " . $update_stmt->error;
            $message_type = "error";
        }
        $update_stmt->close();
    }
}

// Handle Update User Role
if (isset($_POST['update_role']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if ($user_id == $_SESSION['admin_id'] && $new_role !== 'super_admin') {
        $message = "You cannot downgrade your own role.";
        $message_type = "error";
    } else {
        $update_stmt = $con->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_role, $user_id);
        if ($update_stmt->execute()) {
            $message = "User role updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating role: " . $update_stmt->error;
            $message_type = "error";
        }
        $update_stmt->close();
    }
}

// Fetch all admin users with sorting
$users_query = "SELECT id, username, full_name, email, role, status, created_at, last_login 
                FROM admin_users 
                ORDER BY 
                    CASE WHEN role = 'super_admin' THEN 1 ELSE 2 END,
                    $sort_column $sort_direction";
$users_result = $con->query($users_query);
$all_admin_users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $all_admin_users[] = $row;
    }
}

// Statistics based on all users
$total_admins = count($all_admin_users);
$active_count = count(array_filter($all_admin_users, function($u) { return $u['status'] == 'active'; }));
$super_admin_count = count(array_filter($all_admin_users, function($u) { return $u['role'] == 'super_admin'; }));

// Pagination calculations
$total_pages = ceil($total_admins / $limit);
$offset = ($page - 1) * $limit;
$users_paged = array_slice($all_admin_users, $offset, $limit);
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
    font-size: 0.7rem;
    margin-left: 0.2rem;
    vertical-align: middle;
}
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 500;
}
.role-super-admin { background-color: #fef3c7; color: #92400e; }
.role-admin { background-color: #dbeafe; color: #1e40af; }
.role-content-manager { background-color: #d1fae5; color: #065f46; }
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
.status-inactive { background-color: #fee2e2; color: #991b1b; }
.modal-gradient-header {
    background: linear-gradient(135deg, #800000 0%, #00450d 100%);
}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-maroon">Admin Users Management</h1>
                    <p class="text-gray-600 text-sm mt-1">Manage administrator accounts and permissions</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportToCSV()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">download</span>
                        Export CSV
                    </button>
                    <a href="create_admin.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">person_add</span>
                        Add Admin
                    </a>
                </div>
            </div>
            <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
        </div>

        <!-- Statistics Cards - Compact -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Total Admins</p>
                        <p class="text-xl font-bold text-gray-800"><?= $total_admins ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-maroon/40">admin_panel_settings</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Active Users</p>
                        <p class="text-xl font-bold text-gray-800"><?= $active_count ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-green-600/40">check_circle</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Super Admins</p>
                        <p class="text-xl font-bold text-gray-800"><?= $super_admin_count ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-yellow-500/40">star</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Standard Admins</p>
                        <p class="text-xl font-bold text-gray-800"><?= $total_admins - $super_admin_count ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-blue-500/40">person</span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
                <span class="material-symbols-outlined text-base"><?= $message_type == 'success' ? 'check_circle' : 'error' ?></span>
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

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
                    <select id="roleFilter" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 bg-white">
                        <option value="">All Roles</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="content_manager">Content Manager</option>
                    </select>
                    <select id="statusFilter" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 bg-white">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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
                            <th class="w-8 px-3 py-2 text-left">
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
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="role">
                                Role
                                <?php if ($sort_column == 'role'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="status">
                                Status
                                <?php if ($sort_column == 'status'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="created_at">
                                Created
                                <?php if ($sort_column == 'created_at'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Last Login</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-36">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php if (empty($users_paged)): ?>
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-3xl">people</span>
                                    <p class="text-sm mt-1">No admin users found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users_paged as $user): ?>
                                <tr class="table-row-hover" data-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" 
                                    data-fullname="<?= htmlspecialchars($user['full_name']) ?>" data-email="<?= htmlspecialchars($user['email']) ?>" 
                                    data-role="<?= $user['role'] ?>" data-status="<?= $user['status'] ?>">
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
                                    <td class="px-3 py-2 text-xs text-gray-600"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-gray-400">—</span>' ?></td>
                                    <td class="px-3 py-2">
                                        <?php if ($user['role'] == 'super_admin'): ?>
                                            <span class="role-badge role-super-admin">
                                                <span class="material-symbols-outlined text-xs">star</span>
                                                Super Admin
                                            </span>
                                        <?php elseif ($user['role'] == 'admin'): ?>
                                            <span class="role-badge role-admin">
                                                <span class="material-symbols-outlined text-xs">shield</span>
                                                Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-content-manager">
                                                <span class="material-symbols-outlined text-xs">edit_note</span>
                                                Content Mgr
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <?php if ($user['status'] == 'active'): ?>
                                            <span class="status-badge status-active">
                                                <span class="material-symbols-outlined text-xs">check_circle</span>
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                <span class="material-symbols-outlined text-xs">cancel</span>
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td class="px-3 py-2 text-xs text-gray-500"><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-center gap-1">
                                            <form method="POST" action="" class="inline" onsubmit="return confirm('Change role for <?= htmlspecialchars($user['username']) ?>?')">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <select name="new_role" onchange="this.form.submit()" class="text-xs border rounded px-1 py-0.5 focus:ring-1 focus:ring-maroon bg-white">
                                                    <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super</option>
                                                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="content_manager" <?= $user['role'] == 'content_manager' ? 'selected' : '' ?>>Content</option>
                                                </select>
                                                <input type="hidden" name="update_role" value="1">
                                            </form>

                                            <form method="POST" action="" class="inline" onsubmit="return confirm('Toggle status for <?= htmlspecialchars($user['username']) ?>?')">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $user['status'] == 'active' ? 'inactive' : 'active' ?>">
                                                <button type="submit" name="toggle_status" class="action-btn <?= $user['status'] == 'active' ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>" title="<?= $user['status'] == 'active' ? 'Deactivate' : 'Activate' ?>">
                                                    <span class="material-symbols-outlined text-sm">
                                                        <?= $user['status'] == 'active' ? 'pause_circle' : 'play_circle' ?>
                                                    </span>
                                                </button>
                                            </form>

                                            <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                                <a href="?delete=<?= $user['id'] ?>&page=<?= $page ?>&limit=<?= $limit ?>&sort=<?= $sort_column ?>&dir=<?= strtolower($sort_direction) ?>" onclick="return confirm('Delete <?= htmlspecialchars($user['username']) ?>? This action cannot be undone.')" class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete User">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                </a>
                                            <?php else: ?>
                                                <span class="action-btn opacity-40 cursor-not-allowed bg-gray-100 text-gray-400" title="Cannot delete your own account">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                </span>
                                            <?php endif; ?>
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
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_admins) ?> of <?= $total_admins ?> admin users
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Rows:</span>
                            <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
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
                    
                    <a href="create_admin.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all">
                        <span class="material-symbols-outlined text-base">person_add</span>
                        Add Admin
                    </a>
                </div>
            </div>
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
    const limit = document.getElementById('rowsPerPage')?.value || 20;
    let newDir = 'asc';
    
    if (currentSort === column && currentDir === 'asc') {
        newDir = 'desc';
    }
    
    window.location.href = '?page=1&limit=' + limit + '&sort=' + column + '&dir=' + newDir;
}

// Search, Filter, Select, and Export functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('tableBody');
    const rows = tableBody.querySelectorAll('tr');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const visibleCountSpan = document.getElementById('visibleCount');
    
    // Attach sort listeners
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortColumn = this.getAttribute('data-sort');
            if (sortColumn) {
                sortTable(sortColumn);
            }
        });
    });
    
    function filterRows() {
        const searchTerm = searchInput.value.toLowerCase();
        const roleValue = roleFilter.value;
        const statusValue = statusFilter.value;
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            const username = row.getAttribute('data-username')?.toLowerCase() || '';
            const fullname = row.getAttribute('data-fullname')?.toLowerCase() || '';
            const email = row.getAttribute('data-email')?.toLowerCase() || '';
            const role = row.getAttribute('data-role') || '';
            const status = row.getAttribute('data-status') || '';
            
            const matchesSearch = searchTerm === '' || username.includes(searchTerm) || fullname.includes(searchTerm) || email.includes(searchTerm);
            const matchesRole = roleValue === '' || role === roleValue;
            const matchesStatus = statusValue === '' || status === statusValue;
            
            if (matchesSearch && matchesRole && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (visibleCountSpan) visibleCountSpan.textContent = visibleCount;
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
    
    function submitBulkDelete() {
        const selectedIds = getSelectedUserIds();
        if (selectedIds.length === 0) return;
        if (confirm('Delete ' + selectedIds.length + ' selected user(s)? This action cannot be undone.')) {
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
    }
    
    window.exportToCSV = function() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        if (visibleRows.length === 0) { alert('No data to export.'); return; }
        
        const headers = ['ID', 'Username', 'Full Name', 'Email', 'Role', 'Status', 'Created At', 'Last Login'];
        const data = [];
        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 9) {
                data.push([
                    cells[1]?.innerText.trim() || '',
                    cells[2]?.innerText.trim() || '',
                    cells[3]?.innerText.trim() || '',
                    cells[4]?.innerText.trim() || '',
                    cells[5]?.innerText.trim() || '',
                    cells[6]?.innerText.trim() || '',
                    cells[7]?.innerText.trim() || '',
                    cells[8]?.innerText.trim() || ''
                ]);
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
        link.setAttribute('download', 'admin_users_' + new Date().toISOString().split('T')[0] + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };
    
    // Event Listeners
    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (roleFilter) roleFilter.addEventListener('change', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);
    
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
    
    filterRows();
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>