<?php
// countries_boilerplate.php
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

// Country-currency mapping
$country_currency = [
    'Kenya' => 'KES',
    'Tanzania' => 'TZS',
    'Uganda' => 'UGX',
    'Rwanda' => 'RWF',
    'Burundi' => 'BIF',
    'Ethiopia' => 'ETB',
    'South Sudan' => 'SSP',
    'Somalia' => 'SOS',
    'Djibouti' => 'DJF',
    'Eritrea' => 'ERN',
    'Sudan' => 'SDG',
    'Madagascar' => 'MGA',
    'Mauritius' => 'MUR',
    'Comoros' => 'KMF',
    'Seychelles' => 'SCR',
    'Malawi' => 'MWK',
    'Zambia' => 'ZMW',
    'Mozambique' => 'MZN',
    'DR Congo' => 'CDF'
];

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$valid_limits = [10, 20, 50, 100];
if (!in_array($limit, $valid_limits)) $limit = 20;

// Get sort parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'country_name';
$sort_direction = isset($_GET['dir']) && $_GET['dir'] == 'asc' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['id', 'country_name', 'currency_code', 'date_created', 'status'];
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'country_name';

// Get search parameters
$search_country = isset($_GET['search_country']) ? $_GET['search_country'] : '';
$search_currency = isset($_GET['search_currency']) ? $_GET['search_currency'] : '';

// Handle Add/Edit Country via AJAX-style form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_country'])) {
        $country = trim($_POST['country']);
        $currency = trim($_POST['currency']);
        $status = 'active';
        $date_created = date('Y-m-d H:i:s');
        
        // Check if country already exists
        $check_stmt = $con->prepare("SELECT id FROM countries WHERE country_name = ?");
        $check_stmt->bind_param("s", $country);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "Country already exists!";
            $message_type = "error";
        } else {
            $stmt = $con->prepare("INSERT INTO countries (country_name, currency_code, status, date_created) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $country, $currency, $status, $date_created);
            if ($stmt->execute()) {
                $message = "Country added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding country: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    if (isset($_POST['edit_country'])) {
        $country_id = $_POST['country_id'];
        $country = trim($_POST['country']);
        $currency = trim($_POST['currency']);
        $status = $_POST['status'];
        $date_updated = date('Y-m-d H:i:s');
        
        $stmt = $con->prepare("UPDATE countries SET country_name = ?, currency_code = ?, status = ?, date_updated = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $country, $currency, $status, $date_updated, $country_id);
        if ($stmt->execute()) {
            $message = "Country updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating country: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    
    // Handle Delete
    if (isset($_POST['delete_selected']) && isset($_POST['selected_ids'])) {
        $selected_ids = $_POST['selected_ids'];
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $delete_sql = "DELETE FROM countries WHERE id IN ($placeholders)";
        $stmt = $con->prepare($delete_sql);
        if ($stmt) {
            $types = str_repeat('i', count($selected_ids));
            $stmt->bind_param($types, ...$selected_ids);
            if ($stmt->execute()) {
                $message = "Successfully deleted " . $stmt->affected_rows . " country(ies).";
                $message_type = "success";
            } else {
                $message = "Error deleting countries: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Build the query with filters and sorting
$sql = "SELECT id, country_name, currency_code, status, date_created, date_updated FROM countries WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_country)) {
    $sql .= " AND country_name LIKE ?";
    $params[] = '%' . $search_country . '%';
    $types .= "s";
}
if (!empty($search_currency)) {
    $sql .= " AND currency_code LIKE ?";
    $params[] = '%' . $search_currency . '%';
    $types .= "s";
}

// Count total records
$count_sql = str_replace("SELECT id, country_name, currency_code, status, date_created, date_updated", "SELECT COUNT(*) as total", $sql);
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
$countries_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Statistics
$total_countries = $total_records;
$active_countries = 0;
$inactive_countries = 0;

$stats_stmt = $con->query("SELECT COUNT(*) as active FROM countries WHERE status = 'active'");
if ($stats_stmt) $active_countries = $stats_stmt->fetch_assoc()['active'];
$inactive_countries = $total_countries - $active_countries;
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
.status-inactive { background-color: #fee2e2; color: #991b1b; }
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
.country-flag {
    width: 20px;
    height: 14px;
    margin-right: 6px;
    vertical-align: middle;
    object-fit: cover;
}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-maroon">Countries Management</h1>
                    <p class="text-gray-600 text-sm mt-1">Manage countries and their currencies</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportToCSV()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">download</span>
                        Export CSV
                    </button>
                    <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">add_location</span>
                        Add Country
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
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Total Countries</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($total_countries) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-maroon/40">public</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Active</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($active_countries) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-green-600/40">check_circle</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Inactive</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($inactive_countries) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-red-500/40">cancel</span>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Currencies</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format(count($country_currency)) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-blue-500/40">payments</span>
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
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">attach_money</span>
                        <input type="text" id="searchCurrency" placeholder="Search currency..." 
                               class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                               value="<?= htmlspecialchars($search_currency) ?>">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all">
                        <span class="material-symbols-outlined text-base align-middle">filter_list</span>
                        Filter
                    </button>
                    <button id="bulkDeleteBtn" class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <span class="material-symbols-outlined text-base align-middle">delete</span>
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Countries Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="countriesTable">
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
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="country_name">
                                Country
                                <?php if ($sort_column == 'country_name'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="currency_code">
                                Currency
                                <?php if ($sort_column == 'currency_code'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="status">
                                Status
                                <?php if ($sort_column == 'status'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_created">
                                Date Added
                                <?php if ($sort_column == 'date_created'): ?>
                                    <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                                <?php endif; ?>
                            </th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php if (empty($countries_data)): ?>
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-3xl">public_off</span>
                                    <p class="text-sm mt-1">No countries found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($countries_data as $country): ?>
                                <tr class="table-row-hover" data-id="<?= $country['id'] ?>" 
                                    data-country="<?= htmlspecialchars($country['country_name']) ?>" 
                                    data-currency="<?= htmlspecialchars($country['currency_code']) ?>"
                                    data-status="<?= $country['status'] ?>">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="row-checkbox rounded border-gray-300 text-maroon focus:ring-maroon/20" value="<?= $country['id'] ?>">
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600"><?= $country['id'] ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <img src="../base/img/flags/<?= strtolower($country['currency_code']); ?>.png" 
                                                 class="country-flag" 
                                                 onerror="this.style.display='none'">
                                            <span class="text-gray-800 text-xs font-medium"><?= htmlspecialchars($country['country_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded"><?= htmlspecialchars($country['currency_code']) ?></span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="status-badge status-<?= $country['status'] ?>">
                                            <span class="material-symbols-outlined text-xs"><?= $country['status'] == 'active' ? 'check_circle' : 'cancel' ?></span>
                                            <?= ucfirst($country['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($country['date_created'])) ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick="editCountry(<?= $country['id'] ?>, '<?= htmlspecialchars($country['country_name']) ?>', '<?= htmlspecialchars($country['currency_code']) ?>', '<?= $country['status'] ?>')" 
                                                    class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                            </button>
                                            <button onclick="deleteCountry(<?= $country['id'] ?>, '<?= htmlspecialchars($country['country_name']) ?>')" 
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
                        Showing <?= ($page - 1) * $limit + 1 ?> to <?= min($page * $limit, $total_records) ?> of <?= $total_records ?> countries
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

<!-- Add/Edit Country Modal -->
<div id="countryModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add New Country</h3>
                <button onclick="closeModal('countryModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="countryForm">
                    <input type="hidden" name="country_id" id="countryId">
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label>
                        <select name="country" id="countrySelect" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                            <option value="" disabled selected>Select Country</option>
                            <?php foreach ($country_currency as $country => $currency): ?>
                                <option value="<?= htmlspecialchars($country) ?>" data-currency="<?= $currency ?>">
                                    <?= htmlspecialchars($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Currency Code <span class="text-red-500">*</span></label>
                        <input type="text" name="currency" id="currencyInput" readonly 
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50">
                    </div>
                    <div class="mb-4" id="statusField" style="display: none;">
                        <label class="block text-xs text-gray-600 mb-1">Status</label>
                        <select name="status" id="statusSelect" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('countryModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_country" id="submitBtn" class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Country</button>
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
            <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure you want to delete this country?</p>
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
// Country-currency mapping for JavaScript
const countryCurrencyMap = <?php echo json_encode($country_currency); ?>;

// Pagination and sorting functions
function goToPage(page) {
    const limit = document.getElementById('rowsPerPage').value;
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCurrency = document.getElementById('searchCurrency').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=' + page + '&limit=' + limit;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCurrency) url += '&search_currency=' + encodeURIComponent(searchCurrency);
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function changeRowsPerPage() {
    const limit = document.getElementById('rowsPerPage').value;
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCurrency = document.getElementById('searchCurrency').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=1&limit=' + limit;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCurrency) url += '&search_currency=' + encodeURIComponent(searchCurrency);
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function applyFilters() {
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCurrency = document.getElementById('searchCurrency').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || '';
    const currentDir = urlParams.get('dir') || '';
    let url = '?page=1&limit=' + document.getElementById('rowsPerPage').value;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCurrency) url += '&search_currency=' + encodeURIComponent(searchCurrency);
    if (currentSort) url += '&sort=' + currentSort;
    if (currentDir) url += '&dir=' + currentDir;
    window.location.href = url;
}

function sortTable(column) {
    const limit = document.getElementById('rowsPerPage').value;
    const searchCountry = document.getElementById('searchCountry').value;
    const searchCurrency = document.getElementById('searchCurrency').value;
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort');
    const currentDir = urlParams.get('dir');
    let newDir = 'asc';
    if (currentSort === column && currentDir === 'asc') newDir = 'desc';
    let url = '?page=1&limit=' + limit + '&sort=' + column + '&dir=' + newDir;
    if (searchCountry) url += '&search_country=' + encodeURIComponent(searchCountry);
    if (searchCurrency) url += '&search_currency=' + encodeURIComponent(searchCurrency);
    window.location.href = url;
}

// Modal functions
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = 'Add New Country';
    document.getElementById('countryId').value = '';
    document.getElementById('countrySelect').value = '';
    document.getElementById('currencyInput').value = '';
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('submitBtn').name = 'add_country';
    openModal('countryModal');
}

function editCountry(id, countryName, currencyCode, status) {
    document.getElementById('modalTitle').innerHTML = 'Edit Country';
    document.getElementById('countryId').value = id;
    document.getElementById('countrySelect').value = countryName;
    document.getElementById('currencyInput').value = currencyCode;
    document.getElementById('statusField').style.display = 'block';
    document.getElementById('statusSelect').value = status;
    document.getElementById('submitBtn').name = 'edit_country';
    openModal('countryModal');
}

function deleteCountry(id, countryName) {
    document.getElementById('deleteModalText').innerHTML = 'Are you sure you want to delete <strong>' + countryName + '</strong>?';
    document.getElementById('deleteId').value = id;
    openModal('deleteModal');
}

function openModal(modalId) { document.getElementById(modalId).classList.remove('hidden'); }
function closeModal(modalId) { document.getElementById(modalId).classList.add('hidden'); }

// Auto-update currency when country is selected
document.addEventListener('DOMContentLoaded', function() {
    const countrySelect = document.getElementById('countrySelect');
    const currencyInput = document.getElementById('currencyInput');
    
    if (countrySelect && currencyInput) {
        countrySelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const currency = selectedOption.getAttribute('data-currency');
            if (currency) {
                currencyInput.value = currency;
            }
        });
    }
});

// Search and Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
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
    
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedIds = [];
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            visibleRows.forEach(row => {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox && checkbox.checked) selectedIds.push(checkbox.value);
            });
            
            if (selectedIds.length === 0) {
                alert('Please select at least one country to delete.');
                return;
            }
            
            if (confirm('Delete ' + selectedIds.length + ' selected country(ies)? This cannot be undone.')) {
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
});

// Export to CSV
function exportToCSV() {
    const rows = document.querySelectorAll('#tableBody tr');
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    
    if (visibleRows.length === 0) {
        alert('No data to export.');
        return;
    }
    
    const headers = ['ID', 'Country', 'Currency Code', 'Status', 'Date Added'];
    const data = [];
    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            data.push([
                cells[1]?.innerText.trim() || '',
                cells[2]?.innerText.trim() || '',
                cells[3]?.innerText.trim() || '',
                cells[4]?.innerText.trim() || '',
                cells[5]?.innerText.trim() || ''
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
    link.setAttribute('download', 'countries_export_' + new Date().toISOString().split('T')[0] + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>