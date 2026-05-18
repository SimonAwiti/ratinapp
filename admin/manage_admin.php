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

// Fetch all admin users
$users_query = "SELECT id, username, full_name, email, role, status, created_at, last_login 
                FROM admin_users 
                ORDER BY 
                    CASE WHEN role = 'super_admin' THEN 1 ELSE 2 END,
                    created_at DESC";
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
?>

<style>
.auth-bg-gradient {
    background: radial-gradient(circle at top left, rgba(0, 69, 13, 0.05), transparent),
                radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.05), transparent);
}
.header-accent-gradient {
    background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
}
.table-row-hover:hover {
    background-color: #fef3e7;
    transition: all 0.2s ease;
}
.search-input:focus {
    border-color: #800000;
    ring-color: rgba(128,0,0,0.2);
}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-maroon">Admin Users Management</h1>
                    <p class="text-gray-600 mt-1">Manage all administrator accounts and permissions</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="exportToCSV()" class="inline-flex items-center gap-2 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-xl">download</span>
                        Export CSV
                    </button>
                    <a href="create_admin.php" class="inline-flex items-center gap-2 px-6 py-3 bg-maroon text-white rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-xl">person_add</span>
                        Add New Admin
                    </a>
                </div>
            </div>
            <div class="h-1 w-full header-accent-gradient mt-4 rounded-full"></div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-maroon">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm uppercase tracking-wide">Total Admins</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $total_admins ?></p>
                    </div>
                    <span class="material-symbols-outlined text-4xl text-maroon/50">admin_panel_settings</span>
                </div>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-green-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm uppercase tracking-wide">Active Users</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $active_count ?></p>
                    </div>
                    <span class="material-symbols-outlined text-4xl text-green-600/50">check_circle</span>
                </div>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-yellow-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm uppercase tracking-wide">Super Admins</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $super_admin_count ?></p>
                    </div>
                    <span class="material-symbols-outlined text-4xl text-yellow-600/50">star</span>
                </div>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm uppercase tracking-wide">Standard Admins</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $total_admins - $super_admin_count ?></p>
                    </div>
                    <span class="material-symbols-outlined text-4xl text-blue-600/50">person</span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center gap-3 <?= $message_type == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
                <span class="material-symbols-outlined"><?= $message_type == 'success' ? 'check_circle' : 'error' ?></span>
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-xl shadow-sm mb-6 p-4">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                        <input type="text" id="searchInput" placeholder="Search by username, name, or email..." 
                               class="search-input w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                    </div>
                </div>
                <div class="flex gap-3">
                    <select id="roleFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Roles</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="content_manager">Content Manager</option>
                    </select>
                    <select id="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button id="bulkDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <span class="material-symbols-outlined text-base align-middle">delete</span>
                        Delete Selected
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full" id="usersTable">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-maroon focus:ring-maroon/20">
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-maroon sortable" data-sort="id">
                                ID <span class="material-symbols-outlined text-sm align-middle">unfold_more</span>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-maroon sortable" data-sort="username">
                                Username <span class="material-symbols-outlined text-sm align-middle">unfold_more</span>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-maroon sortable" data-sort="full_name">
                                Full Name <span class="material-symbols-outlined text-sm align-middle">unfold_more</span>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Login</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php foreach ($all_admin_users as $user): ?>
                            <tr class="table-row-hover" data-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" data-fullname="<?= htmlspecialchars($user['full_name']) ?>" data-email="<?= htmlspecialchars($user['email']) ?>" data-role="<?= $user['role'] ?>" data-status="<?= $user['status'] ?>">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="row-checkbox rounded border-gray-300 text-maroon focus:ring-maroon/20" value="<?= $user['id'] ?>">
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= $user['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-gray-400 text-sm">person</span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($user['full_name']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-gray-400">—</span>' ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($user['role'] == 'super_admin'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <span class="material-symbols-outlined text-sm">star</span>
                                            Super Admin
                                        </span>
                                    <?php elseif ($user['role'] == 'admin'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <span class="material-symbols-outlined text-sm">shield</span>
                                            Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <span class="material-symbols-outlined text-sm">edit_note</span>
                                            Content Manager
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <span class="material-symbols-outlined text-sm">check_circle</span>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <span class="material-symbols-outlined text-sm">cancel</span>
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <form method="POST" action="" class="inline" onsubmit="return confirm('Change role for <?= htmlspecialchars($user['username']) ?>?')">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="new_role" onchange="this.form.submit()" class="text-xs border rounded px-2 py-1 focus:ring-1 focus:ring-maroon">
                                                <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                <option value="content_manager" <?= $user['role'] == 'content_manager' ? 'selected' : '' ?>>Content Manager</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                        </form>

                                        <form method="POST" action="" class="inline" onsubmit="return confirm('Toggle status for <?= htmlspecialchars($user['username']) ?>?')">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $user['status'] == 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" name="toggle_status" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" title="<?= $user['status'] == 'active' ? 'Deactivate' : 'Activate' ?>">
                                                <span class="material-symbols-outlined text-sm <?= $user['status'] == 'active' ? 'text-red-500' : 'text-green-500' ?>">
                                                    <?= $user['status'] == 'active' ? 'block' : 'check_circle' ?>
                                                </span>
                                            </button>
                                        </form>

                                        <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                            <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('Delete <?= htmlspecialchars($user['username']) ?>? This action cannot be undone.')" class="p-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Delete User">
                                                <span class="material-symbols-outlined text-sm text-red-500">delete</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="p-1.5 opacity-40 cursor-not-allowed" title="Cannot delete your own account">
                                                <span class="material-symbols-outlined text-sm text-gray-400">delete</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions Footer -->
        <div class="mt-8 flex justify-between items-center flex-wrap gap-4">
            <div class="text-sm text-gray-500" id="resultCount">
                Showing <span id="visibleCount"><?= count($all_admin_users) ?></span> of <?= $total_admins ?> admin users
            </div>
            <div class="flex gap-3">
                <a href="create_admin.php" class="inline-flex items-center gap-2 px-4 py-2 bg-maroon text-white rounded-lg hover:bg-[#660000] transition-all text-sm">
                    <span class="material-symbols-outlined text-base">person_add</span>
                    Add New Admin
                </a>
                <a href="../base/landing_page.php" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all text-sm">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Search, Filter, Sort, Select, and Export functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('tableBody');
    const rows = tableBody.querySelectorAll('tr');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const visibleCountSpan = document.getElementById('visibleCount');
    
    let currentSortColumn = null;
    let currentSortDirection = 'asc';
    
    // Search and Filter function
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
        
        visibleCountSpan.textContent = visibleCount;
        updateSelectAllCheckbox();
        updateBulkDeleteButton();
    }
    
    // Sorting function
    function sortTable(column, sortDirection) {
        const rowsArray = Array.from(rows);
        const columnIndex = {
            'id': 0,
            'username': 1,
            'full_name': 2
        }[column];
        
        if (columnIndex === undefined) return;
        
        rowsArray.sort((a, b) => {
            const aValue = a.children[columnIndex + 1].textContent.trim();
            const bValue = b.children[columnIndex + 1].textContent.trim();
            
            if (column === 'id') {
                return sortDirection === 'asc' ? parseInt(aValue) - parseInt(bValue) : parseInt(bValue) - parseInt(aValue);
            } else {
                return sortDirection === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            }
        });
        
        // Reorder rows
        rowsArray.forEach(row => tableBody.appendChild(row));
    }
    
    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const checkboxes = visibleRows.map(row => row.querySelector('.row-checkbox'));
        const checkedCheckboxes = checkboxes.filter(cb => cb && cb.checked);
        
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
    
    // Update bulk delete button state
    function updateBulkDeleteButton() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const checkboxes = visibleRows.map(row => row.querySelector('.row-checkbox'));
        const checkedCheckboxes = checkboxes.filter(cb => cb && cb.checked);
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = checkedCheckboxes.length === 0;
        }
    }
    
    // Get selected user IDs
    function getSelectedUserIds() {
        const selectedIds = [];
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox && checkbox.checked) {
                    const userId = checkbox.value;
                    selectedIds.push(userId);
                }
            }
        });
        return selectedIds;
    }
    
    // Bulk delete form submission
    function submitBulkDelete() {
        const selectedIds = getSelectedUserIds();
        if (selectedIds.length === 0) return;
        
        if (confirm(`Are you sure you want to delete ${selectedIds.length} selected user(s)? This action cannot be undone.`)) {
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
    
    // Export to CSV function - stays on same page
    window.exportToCSV = function() {
        // Get all visible rows after filtering
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        
        if (visibleRows.length === 0) {
            alert('No data to export.');
            return;
        }
        
        // Prepare CSV headers
        const headers = ['ID', 'Username', 'Full Name', 'Email', 'Role', 'Status', 'Created At', 'Last Login'];
        
        // Collect data from visible rows
        const data = [];
        visibleRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 9) {
                const rowData = [
                    cells[1]?.innerText.trim() || '',      // ID
                    cells[2]?.innerText.trim() || '',      // Username
                    cells[3]?.innerText.trim() || '',      // Full Name
                    cells[4]?.innerText.trim() || '',      // Email
                    cells[5]?.innerText.trim() || '',      // Role
                    cells[6]?.innerText.trim() || '',      // Status
                    cells[7]?.innerText.trim() || '',      // Created At
                    cells[8]?.innerText.trim() || ''       // Last Login
                ];
                data.push(rowData);
            }
        });
        
        // Add statistics summary at the top of CSV
        const stats = [
            ['RATIN Analytics - Admin Users Export'],
            ['Generated on:', new Date().toLocaleString()],
            ['Total Admins:', '<?= $total_admins ?>'],
            ['Active Users:', '<?= $active_count ?>'],
            ['Super Admins:', '<?= $super_admin_count ?>'],
            ['Standard Admins:', '<?= $total_admins - $super_admin_count ?>'],
            [],
            []  // Empty row before data
        ];
        
        // Combine stats and data
        const csvData = [...stats, headers, ...data];
        
        // Convert to CSV
        const csvContent = csvData.map(row => {
            return row.map(cell => {
                // Escape quotes and wrap in quotes if contains comma or newline
                if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                    return '"' + cell.replace(/"/g, '""') + '"';
                }
                return cell;
            }).join(',');
        }).join('\n');
        
        // Add BOM for UTF-8 to handle special characters
        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        
        // Create download link
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
    
    // Select All functionality
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
    
    // Individual row checkboxes
    rows.forEach(row => {
        const checkbox = row.querySelector('.row-checkbox');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                updateSelectAllCheckbox();
                updateBulkDeleteButton();
            });
        }
    });
    
    // Bulk delete button
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', submitBulkDelete);
    }
    
    // Sorting functionality
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortColumn = this.getAttribute('data-sort');
            
            if (currentSortColumn === sortColumn) {
                currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = sortColumn;
                currentSortDirection = 'asc';
            }
            
            // Update sort icons
            sortableHeaders.forEach(h => {
                const icon = h.querySelector('.material-symbols-outlined');
                if (icon) icon.textContent = 'unfold_more';
            });
            
            const currentIcon = this.querySelector('.material-symbols-outlined');
            if (currentIcon) {
                currentIcon.textContent = currentSortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward';
            }
            
            sortTable(currentSortColumn, currentSortDirection);
        });
    });
    
    // Initial filter to show proper count
    filterRows();
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>