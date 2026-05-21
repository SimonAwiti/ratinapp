<?php
session_start();
require_once '../admin/includes/admin_header.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
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

// Initialize selected commodity sources in session if not exists
if (!isset($_SESSION['selected_commodity_sources'])) {
    $_SESSION['selected_commodity_sources'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_commodity_sources'])) {
            $_SESSION['selected_commodity_sources'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_commodity_sources']);
        if ($key !== false) {
            unset($_SESSION['selected_commodity_sources'][$key]);
            $_SESSION['selected_commodity_sources'] = array_values($_SESSION['selected_commodity_sources']);
        }
    }
    
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_commodity_sources'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_commodity_sources'])]);
    exit;
}

// Clear all selections if requested
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_commodity_sources'] = [];
}

// Handle Add/Edit via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_source'])) {
        $admin0_country = trim($_POST['admin0_country']);
        $admin1_county_district = trim($_POST['admin1_county_district']);
        $created_at = date('Y-m-d H:i:s');
        
        // Check for duplicate
        $check_stmt = $con->prepare("SELECT id FROM commodity_sources WHERE admin0_country = ? AND admin1_county_district = ?");
        $check_stmt->bind_param("ss", $admin0_country, $admin1_county_district);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "This commodity source already exists!";
            $message_type = "error";
        } else {
            $stmt = $con->prepare("INSERT INTO commodity_sources (admin0_country, admin1_county_district, created_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $admin0_country, $admin1_county_district, $created_at);
            if ($stmt->execute()) {
                $message = "Commodity source added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding source: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    if (isset($_POST['edit_source'])) {
        $id = $_POST['source_id'];
        $admin0_country = trim($_POST['admin0_country']);
        $admin1_county_district = trim($_POST['admin1_county_district']);
        
        // Check for duplicate excluding current
        $check_stmt = $con->prepare("SELECT id FROM commodity_sources WHERE admin0_country = ? AND admin1_county_district = ? AND id != ?");
        $check_stmt->bind_param("ssi", $admin0_country, $admin1_county_district, $id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "This commodity source already exists!";
            $message_type = "error";
        } else {
            $stmt = $con->prepare("UPDATE commodity_sources SET admin0_country = ?, admin1_county_district = ? WHERE id = ?");
            $stmt->bind_param("ssi", $admin0_country, $admin1_county_district, $id);
            if ($stmt->execute()) {
                $message = "Commodity source updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating source: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    // Handle Delete
    if (isset($_POST['delete_selected']) && isset($_POST['selected_ids'])) {
        $selected_ids = $_POST['selected_ids'];
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $delete_sql = "DELETE FROM commodity_sources WHERE id IN ($placeholders)";
        $stmt = $con->prepare($delete_sql);
        if ($stmt) {
            $types = str_repeat('i', count($selected_ids));
            $stmt->bind_param($types, ...$selected_ids);
            if ($stmt->execute()) {
                $message = "Successfully deleted " . $stmt->affected_rows . " source(s).";
                $message_type = "success";
                $_SESSION['selected_commodity_sources'] = [];
            } else {
                $message = "Error deleting sources: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$valid_limits = [10, 20, 50, 100];
if (!in_array($limit, $valid_limits)) $limit = 20;

// Get sort parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'admin0_country';
$sort_direction = isset($_GET['dir']) && $_GET['dir'] == 'asc' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['id', 'admin0_country', 'admin1_county_district', 'created_at'];
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'admin0_country';

// Get search parameters
$search_country = isset($_GET['search_country']) ? $_GET['search_country'] : '';
$search_county = isset($_GET['search_county']) ? $_GET['search_county'] : '';

// Build query (removed date_updated)
$sql = "SELECT id, admin0_country, admin1_county_district, created_at FROM commodity_sources WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_country)) {
    $sql .= " AND admin0_country LIKE ?";
    $params[] = '%' . $search_country . '%';
    $types .= "s";
}
if (!empty($search_county)) {
    $sql .= " AND admin1_county_district LIKE ?";
    $params[] = '%' . $search_county . '%';
    $types .= "s";
}

// Count total records
$count_sql = str_replace("SELECT id, admin0_country, admin1_county_district, created_at", "SELECT COUNT(*) as total", $sql);
$count_stmt = $con->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Add sorting and pagination
$sql .= " ORDER BY $sort_column $sort_direction LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = ($page - 1) * $limit;
$types .= "ii";

$stmt = $con->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$sources_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch statistics
$total_sources = $total_records;
$distinct_countries = 0;
$distinct_counties = 0;
$kenya_sources = 0;

$country_stmt = $con->query("SELECT COUNT(DISTINCT admin0_country) as total FROM commodity_sources");
if ($country_stmt) $distinct_countries = $country_stmt->fetch_assoc()['total'];

$county_stmt = $con->query("SELECT COUNT(DISTINCT admin1_county_district) as total FROM commodity_sources");
if ($county_stmt) $distinct_counties = $county_stmt->fetch_assoc()['total'];

$kenya_stmt = $con->query("SELECT COUNT(*) as total FROM commodity_sources WHERE admin0_country = 'Kenya'");
if ($kenya_stmt) $kenya_sources = $kenya_stmt->fetch_assoc()['total'];
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
.modal-gradient-header {
    background: linear-gradient(135deg, #800000 0%, #00450d 100%);
}
.selected-count {
    display: inline-block;
    background-color: rgba(180, 80, 50, 0.15);
    color: #800000;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 600;
    margin-left: 0.5rem;
}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-maroon">Commodity Sources Management</h1>
                    <p class="text-gray-600 text-sm mt-1">Manage geographical origins of commodities</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportToCSV()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">download</span>
                        Export CSV
                    </button>
                    <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">add_location</span>
                        Add Source
                    </button>
                </div>
            </div>
            <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
                <span class="material-symbols-outlined text-base"><?= $message_type == 'success' ? 'check_circle' : 'error' ?></span>
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Total Sources</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($total_sources) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-maroon/40">inventory</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Distinct Countries</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($distinct_countries) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-purple-500/40">flag</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-teal-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Distinct Counties</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($distinct_counties) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-teal-500/40">location_city</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Sources from Kenya</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($kenya_sources) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-orange-500/40">map</span>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <div class="flex-1 min-w-[150px]">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                        <input type="text" id="searchCountry" placeholder="Search country..." 
                               class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                               value="<?= htmlspecialchars($search_country) ?>">
                    </div>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">location_on</span>
                        <input type="text" id="searchCounty" placeholder="Search county/district..." 
                               class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                               value="<?= htmlspecialchars($search_county) ?>">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all">
                        <span class="material-symbols-outlined text-base align-middle">filter_list</span>
                        Filter
                    </button>
                    <button id="clearSelectionsBtn" class="px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-all">
                        <span class="material-symbols-outlined text-base align-middle">clear</span>
                        Clear Selected
                    </button>
                    <button id="bulkDeleteBtn" class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <span class="material-symbols-outlined text-base align-middle">delete</span>
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Sources Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="sourcesTable">
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
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="admin0_country">
                                Country
                                <?php if ($sort_column == 'admin0_country'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="admin1_county_district">
                                County/District
                                <?php if ($sort_column == 'admin1_county_district'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="created_at">
                                Date Added
                                <?php if ($sort_column == 'created_at'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php if (empty($sources_data)): ?>
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-3xl">inventory_2</span>
                                    <p class="text-sm mt-1">No commodity sources found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sources_data as $source): ?>
                                <tr class="table-row-hover" data-id="<?= $source['id'] ?>" 
                                    data-country="<?= htmlspecialchars($source['admin0_country']) ?>" 
                                    data-county="<?= htmlspecialchars($source['admin1_county_district']) ?>">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="row-checkbox rounded border-gray-300 text-maroon focus:ring-maroon/20" value="<?= $source['id'] ?>"
                                            <?= in_array($source['id'], $_SESSION['selected_commodity_sources']) ? 'checked' : '' ?>>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600"><?= $source['id'] ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-gray-400 text-sm">flag</span>
                                            <span class="text-gray-800 text-xs font-medium"><?= htmlspecialchars($source['admin0_country']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-gray-400 text-sm">location_on</span>
                                            <span class="text-gray-700 text-xs"><?= htmlspecialchars($source['admin1_county_district']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($source['created_at'])) ?></span></div></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick="editSource(<?= $source['id'] ?>, '<?= htmlspecialchars($source['admin0_country']) ?>', '<?= htmlspecialchars($source['admin1_county_district']) ?>')" 
                                                    class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                            </button>
                                            <button onclick="deleteSource(<?= $source['id'] ?>, '<?= htmlspecialchars($source['admin0_country']) ?>', '<?= htmlspecialchars($source['admin1_county_district']) ?>')" 
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
            
            <!-- Pagination Section -->
            <div class="border-t border-gray-200 px-4 py-3 bg-white">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div class="text-xs text-gray-500">
                        Showing <?= ($page - 1) * $limit + 1 ?> to <?= min($page * $limit, $total_records) ?> of <?= $total_records ?> sources
                        <?php if (count($_SESSION['selected_commodity_sources']) > 0): ?>
                            <span class="selected-count"><?= count($_SESSION['selected_commodity_sources']) ?> selected</span>
                        <?php endif; ?>
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
                    
                    <a href="../base/landing_page.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Source Modal -->
<div id="sourceModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add Commodity Source</h3>
                <button onclick="closeModal('sourceModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="sourceForm">
                    <input type="hidden" name="source_id" id="sourceId">
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">flag</span>
                            <input type="text" name="admin0_country" id="countryInput" required 
                                   class="w-full pl-10 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                                   placeholder="e.g., Kenya">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">County/District <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">location_on</span>
                            <input type="text" name="admin1_county_district" id="countyInput" required 
                                   class="w-full pl-10 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                                   placeholder="e.g., Nairobi">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('sourceModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_source" id="submitBtn" class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Source</button>
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
            <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure you want to delete this source?</p>
            <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-3 text-xs text-red-700">
                <span class="material-symbols-outlined text-xs align-middle">info</span>
                This action cannot be undone.
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="delete_selected" value="1">
                <input type="hidden" name="selected_ids[]" id="deleteId">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Pagination functions
function goToPage(page) {
    const limit = document.getElementById('rowsPerPage').value;
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCounty = document.getElementById('searchCounty').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=' + page + '&limit=' + limit;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCounty) url += '&search_county=' + encodeURIComponent(searchCounty);
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function changeRowsPerPage() {
    const limit = document.getElementById('rowsPerPage').value;
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCounty = document.getElementById('searchCounty').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=1&limit=' + limit;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCounty) url += '&search_county=' + encodeURIComponent(searchCounty);
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function applyFilters() {
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCounty = document.getElementById('searchCounty').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=1&limit=' + document.getElementById('rowsPerPage').value;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCounty) url += '&search_county=' + encodeURIComponent(searchCounty);
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function sortTable(column) {
    const limit = document.getElementById('rowsPerPage').value;
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCounty = document.getElementById('searchCounty').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort');
    const currentDir = urlParams.get('dir');
    let newDir = 'asc';
    if (currentSort === column && currentDir === 'asc') newDir = 'desc';
    let url = '?page=1&limit=' + limit + '&sort=' + column + '&dir=' + newDir;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCounty) url += '&search_county=' + encodeURIComponent(searchCounty);
    window.location.href = url;
}

// Modal functions
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = 'Add Commodity Source';
    document.getElementById('sourceId').value = '';
    document.getElementById('countryInput').value = '';
    document.getElementById('countyInput').value = '';
    document.getElementById('submitBtn').name = 'add_source';
    openModal('sourceModal');
}

function editSource(id, country, county) {
    document.getElementById('modalTitle').innerHTML = 'Edit Commodity Source';
    document.getElementById('sourceId').value = id;
    document.getElementById('countryInput').value = country;
    document.getElementById('countyInput').value = county;
    document.getElementById('submitBtn').name = 'edit_source';
    openModal('sourceModal');
}

function deleteSource(id, country, county) {
    document.getElementById('deleteModalText').innerHTML = 'Are you sure you want to delete <strong>' + country + ' - ' + county + '</strong>?';
    document.getElementById('deleteId').value = id;
    openModal('deleteModal');
}

function openModal(modalId) { document.getElementById(modalId).classList.remove('hidden'); }
function closeModal(modalId) { document.getElementById(modalId).classList.add('hidden'); }

// Selection management
function updateSelection(checkbox, id) {
    const isSelected = checkbox.checked;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_selection&id=' + id + '&selected=' + isSelected
    })
    .then(response => response.json())
    .then(data => {
        updateSelectAllCheckbox();
        updateBulkDeleteButton();
        updateSelectionCount(data.count);
    })
    .catch(error => console.error('Error updating selection:', error));
}

function updateSelectionCount(count) {
    const countSpan = document.querySelector('.selected-count');
    if (countSpan) {
        if (count > 0) {
            countSpan.textContent = count + ' selected';
            countSpan.style.display = 'inline-block';
        } else {
            countSpan.style.display = 'none';
        }
    }
}

function clearAllSelections() {
    if (confirm('Clear all selections across all pages?')) {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update_selection&clear_all=true'
        })
        .then(() => {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            updateSelectAllCheckbox();
            updateBulkDeleteButton();
            updateSelectionCount(0);
        })
        .catch(error => console.error('Error clearing selections:', error));
    }
}

// Table interaction functions
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const clearSelectionsBtn = document.getElementById('clearSelectionsBtn');
    const tableBody = document.getElementById('tableBody');
    const rows = tableBody.querySelectorAll('tr');
    
    // Attach sort listeners
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortColumn = this.getAttribute('data-sort');
            if (sortColumn) sortTable(sortColumn);
        });
    });
    
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
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            visibleRows.forEach(row => {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                    if (checkbox.onchange) checkbox.onchange();
                }
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
                if (checkbox.onchange) checkbox.onchange();
            });
        }
    });
    
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedIds = [];
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            visibleRows.forEach(row => {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox && checkbox.checked) selectedIds.push(checkbox.value);
            });
            
            if (selectedIds.length === 0) {
                alert('Please select at least one source to delete.');
                return;
            }
            
            if (confirm('Delete ' + selectedIds.length + ' selected source(s)? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = '<input type="hidden" name="delete_selected" value="1">' +
                    selectedIds.map(id => '<input type="hidden" name="selected_ids[]" value="' + id + '">').join('');
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    if (clearSelectionsBtn) {
        clearSelectionsBtn.addEventListener('click', clearAllSelections);
    }
    
    updateSelectAllCheckbox();
    updateBulkDeleteButton();
});

// Export to CSV
function exportToCSV() {
    const rows = document.querySelectorAll('#tableBody tr');
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    
    if (visibleRows.length === 0) {
        alert('No data to export.');
        return;
    }
    
    const headers = ['ID', 'Country', 'County/District', 'Date Added'];
    const data = [];
    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            data.push([
                cells[1]?.innerText.trim() || '',
                cells[2]?.innerText.trim() || '',
                cells[3]?.innerText.trim() || '',
                cells[4]?.innerText.trim() || ''
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
    link.setAttribute('download', 'commodity_sources_export_' + new Date().toISOString().split('T')[0] + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>