<?php
// datasource_boilerplate.php
session_start();

// ============================================================
// EXPORT CSV — must run BEFORE admin_header.php is included
// ============================================================
if (isset($_GET['export_csv'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data_sources_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $search_name = $_GET['search_name'] ?? '';
    $search_country = $_GET['search_country'] ?? '';

    $where_export = "WHERE 1=1";
    if (!empty($search_name)) {
        $where_export .= " AND data_source_name LIKE '%" . $con->real_escape_string($search_name) . "%'";
    }
    if (!empty($search_country)) {
        $where_export .= " AND countries_covered LIKE '%" . $con->real_escape_string($search_country) . "%'";
    }

    $exp_result = $con->query("SELECT id, data_source_name, countries_covered, DATE_FORMAT(created_at, '%Y-%m-%d') as created_date FROM data_sources $where_export ORDER BY data_source_name ASC");
    
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Data Source Name', 'Countries Covered', 'Date Added']);

    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['data_source_name'],
            $row['countries_covered'],
            $row['created_date'],
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// POST: Add Data Source
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_datasource'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $data_source_name = trim($_POST['data_source_name']);
    $countries_covered = isset($_POST['countries_covered']) ? implode(', ', $_POST['countries_covered']) : '';
    $created_at = date('Y-m-d H:i:s');
    
    if (empty($data_source_name) || empty($countries_covered)) {
        $_SESSION['import_message'] = "Please fill all required fields.";
        $_SESSION['import_status'] = "danger";
    } else {
        $stmt = $con->prepare("INSERT INTO data_sources (data_source_name, countries_covered, created_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data_source_name, $countries_covered, $created_at);
        if ($stmt->execute()) {
            $_SESSION['import_message'] = "Data source added successfully!";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error adding data source: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: datasource_boilerplate.php");
    exit;
}

// ============================================================
// POST: Edit Data Source
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_datasource'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $id = (int)$_POST['datasource_id'];
    $data_source_name = trim($_POST['data_source_name']);
    $countries_covered = isset($_POST['countries_covered']) ? implode(', ', $_POST['countries_covered']) : '';
    
    if (empty($data_source_name) || empty($countries_covered)) {
        $_SESSION['import_message'] = "Please fill all required fields.";
        $_SESSION['import_status'] = "danger";
    } else {
        $stmt = $con->prepare("UPDATE data_sources SET data_source_name = ?, countries_covered = ? WHERE id = ?");
        $stmt->bind_param("ssi", $data_source_name, $countries_covered, $id);
        if ($stmt->execute()) {
            $_SESSION['import_message'] = "Data source updated successfully!";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error updating data source: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: datasource_boilerplate.php");
    exit;
}

// ============================================================
// POST: Delete Data Sources
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM data_sources WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $_SESSION['import_message'] = "Successfully deleted $deleted data source(s).";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error deleting: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: datasource_boilerplate.php");
    exit;
}

// ============================================================
// API HANDLER — fetch single data source for edit modal (must be BEFORE admin_header)
// ============================================================
if (isset($_GET['get_datasource']) && is_numeric($_GET['get_datasource'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_datasource'];
    $result = $con->query("SELECT id, data_source_name, countries_covered FROM data_sources WHERE id = $get_id");
    if ($result && $row = $result->fetch_assoc()) {
        $row['countries_array'] = explode(', ', $row['countries_covered']);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}

// ============================================================
// CHECK ADMIN LOGIN
// ============================================================
require_once '../admin/includes/admin_header.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

// ============================================================
// INCLUDE CONFIG
// ============================================================
if (file_exists('includes/config.php')) include 'includes/config.php';
elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

// ============================================================
// STATISTICS
// ============================================================
$total_sources = (int)($con->query("SELECT COUNT(*) as t FROM data_sources")->fetch_assoc()['t'] ?? 0);
$unique_countries_count = 0;
$all_countries_list = [];

$all_sources = $con->query("SELECT countries_covered FROM data_sources");
if ($all_sources) {
    $all_countries_temp = [];
    while ($row = $all_sources->fetch_assoc()) {
        $countries = explode(', ', $row['countries_covered']);
        foreach ($countries as $country) {
            if (!in_array($country, $all_countries_temp)) {
                $all_countries_temp[] = $country;
            }
        }
    }
    $unique_countries_count = count($all_countries_temp);
    sort($all_countries_temp);
    $all_countries_list = $all_countries_temp;
}

// ============================================================
// PAGINATION + SORTING + FILTERING
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column = $_GET['sort'] ?? 'data_source_name';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts = ['id', 'data_source_name', 'countries_covered', 'created_date'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'data_source_name';

$search_name = trim($_GET['search_name'] ?? '');
$search_country = trim($_GET['search_country'] ?? '');

$where = "WHERE 1=1";
if ($search_name !== '') {
    $where .= " AND data_source_name LIKE '%" . $con->real_escape_string($search_name) . "%'";
}
if ($search_country !== '') {
    $where .= " AND countries_covered LIKE '%" . $con->real_escape_string($search_country) . "%'";
}

// Count total records
$count_result = $con->query("SELECT COUNT(*) as total FROM data_sources $where");
$filtered_records = (int)$count_result->fetch_assoc()['total'];

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($page - 1) * $limit;

// Fetch data
$data_sources = [];
$data_result = $con->query("SELECT id, data_source_name, countries_covered, DATE_FORMAT(created_at, '%Y-%m-%d') as created_date FROM data_sources $where ORDER BY $sort_column $sort_direction LIMIT $limit OFFSET $offset");
while ($row = $data_result->fetch_assoc()) {
    $data_sources[] = $row;
}

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;
?>

<style>
.auth-bg-gradient{background:radial-gradient(circle at top left,rgba(0,69,13,.03),transparent),radial-gradient(circle at bottom right,rgba(128,0,0,.03),transparent)}
.header-accent-gradient{background:linear-gradient(90deg,#00450d 0%,#800000 50%,#00450d 100%)}
.table-row-hover:hover{background-color:#fefaf5;transition:all .2s ease}
.stat-card{transition:all .2s ease;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.search-input:focus{border-color:#800000;outline:none}
.action-btn{padding:.2rem .4rem;border-radius:.375rem;font-size:.7rem;font-weight:500;transition:all .2s;cursor:pointer;border:none;display:inline-flex;align-items:center}
.pagination-btn{min-width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:.375rem;font-size:.75rem;transition:all .2s ease;cursor:pointer;border:1px solid #e5e7eb;background:white;color:#374151}
.pagination-btn:hover:not(:disabled):not(.active-page){background-color:#fef3e7;border-color:#800000;color:#800000}
.pagination-btn.active-page{background-color:#800000;border-color:#800000;color:white;font-weight:600}
.pagination-btn:disabled{opacity:.35;cursor:not-allowed}
.page-size-select{font-size:.75rem;padding:.25rem .5rem;border-radius:.375rem;border:1px solid #e5e7eb;background:white;cursor:pointer}
.sortable{cursor:pointer;user-select:none}
.sortable:hover{color:#800000}
.sort-icon{font-size:.7rem;margin-left:.2rem;vertical-align:middle}
.modal-gradient-header{background:linear-gradient(135deg,#800000 0%,#00450d 100%)}
.material-symbols-outlined{font-family:'Material Symbols Outlined'!important;font-style:normal;font-weight:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';font-feature-settings:'liga';-webkit-font-smoothing:antialiased}
.country-tag{display:inline-block;background:#f3f4f6;padding:.15rem .6rem;border-radius:999px;font-size:.7rem;color:#374151;margin:2px 3px}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Data Sources Management</h1>
                <p class="text-gray-600 text-sm mt-1">Manage data sources and their country coverage</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_csv=1&search_name=<?= urlencode($search_name) ?>&search_country=<?= urlencode($search_country) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export CSV
                </a>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">add_circle</span>Add Data Source
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['import_message'])): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $_SESSION['import_status'] == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $_SESSION['import_status'] == 'success' ? 'check_circle' : 'error' ?></span>
        <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['import_message']) ?></span>
    </div>
    <?php 
        unset($_SESSION['import_message']); 
        unset($_SESSION['import_status']);
    endif; 
    ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Data Sources</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_sources) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">database</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Countries Covered</p><p class="text-xl font-bold text-blue-600"><?= number_format($unique_countries_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-blue-400/50">public</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Active Sources</p><p class="text-xl font-bold text-green-600"><?= number_format($total_sources) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">check_circle</span>
            </div>
        </div>
    </div>

    <!-- Search & bulk actions -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div class="flex-1 min-w-[180px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                    <input type="text" id="searchName" placeholder="Search by name..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_name) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[180px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">public</span>
                    <select id="searchCountry" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Countries</option>
                        <?php foreach ($all_countries_list as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $search_country == $country ? 'selected' : '' ?>><?= htmlspecialchars($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">filter_list</span>Filter
                </button>
                <button id="clearSelectionsBtn" class="px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">clear</span>Clear Selected
                </button>
                <button id="bulkDeleteBtn" disabled class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">delete</span>Delete (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-8 px-3 py-2 text-left">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300">
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">
                            ID<?php if($sort_column=='id') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="data_source_name">
                            Data Source<?php if($sort_column=='data_source_name') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="countries_covered">
                            Countries Covered<?php if($sort_column=='countries_covered') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="created_date">
                            Date Added<?php if($sort_column=='created_date') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-20">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($data_sources)): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">database</span>
                            <p class="text-sm mt-1">No data sources found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data_sources as $source): ?>
                    <tr class="table-row-hover" data-id="<?= $source['id'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="<?= $source['id'] ?>" onchange="onCheckboxChange()">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= $source['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($source['data_source_name']) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1">
                                <?php 
                                $countries = explode(', ', $source['countries_covered']);
                                foreach ($countries as $country): 
                                ?>
                                    <span class="country-tag"><?= htmlspecialchars($country) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= date('M d, Y', strtotime($source['created_date'])) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editDataSource(<?= $source['id'] ?>)" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $source['id'] ?>, '<?= htmlspecialchars(addslashes($source['data_source_name'])) ?>')" class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
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

        <!-- Pagination -->
        <div class="border-t border-gray-200 px-4 py-3 bg-white">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="text-xs text-gray-500">
                    <?php if ($filtered_records === 0): ?>
                        No data sources found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> data sources
                        <?php if ($search_name || $search_country): ?>
                            <span class="ml-1 text-maroon">(filtered)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500" for="rowsPerPage">Rows:</label>
                        <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                            <?php foreach ([10,20,50,100] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $limit==$opt?'selected':'' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center gap-1">
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page<=1?'disabled':'' ?>><span class="material-symbols-outlined text-sm">first_page</span></button>
                        <button class="pagination-btn" onclick="goToPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>><span class="material-symbols-outlined text-sm">chevron_left</span></button>

                        <?php
                        $win = 2;
                        $sp = max(1, $page - $win);
                        $ep = min($total_pages, $page + $win);
                        if ($sp === 1) $ep = min($total_pages, 1 + $win * 2);
                        if ($ep === $total_pages) $sp = max(1, $total_pages - $win * 2);
                        if ($sp > 1): ?>
                            <button class="pagination-btn" onclick="goToPage(1)">1</button>
                            <?php if ($sp > 2): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $sp; $i <= $ep; $i++): ?>
                            <button class="pagination-btn <?= $i===$page ? 'active-page' : '' ?>" <?= $i===$page ? '' : "onclick=\"goToPage($i)\"" ?>><?= $i ?></button>
                        <?php endfor; ?>

                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                        <?php endif; ?>

                        <button class="pagination-btn" onclick="goToPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="material-symbols-outlined text-sm">chevron_right</span></button>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="material-symbols-outlined text-sm">last_page</span></button>
                    </nav>
                    <?php endif; ?>
                </div>

                <a href="../base/landing_page.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                    <span class="material-symbols-outlined text-base">arrow_back</span>Back
                </a>
            </div>
        </div>
    </div>

</div>
</div>

<!-- ADD / EDIT MODAL -->
<div id="dataSourceModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add Data Source</h3>
                <button onclick="closeModal('dataSourceModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="dataSourceForm">
                    <input type="hidden" name="datasource_id" id="dataSourceId">

                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Data Source Name <span class="text-red-500">*</span></label>
                        <input type="text" name="data_source_name" id="modalName" required
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                            placeholder="e.g., FAO, National Bureau of Statistics">
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Countries Covered <span class="text-red-500">*</span></label>
                        <select name="countries_covered[]" id="modalCountries" multiple required
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                            style="min-height: 150px;">
                            <option value="Ethiopia">Ethiopia</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Burundi">Burundi</option>
                            <option value="South Sudan">South Sudan</option>
                            <option value="Somalia">Somalia</option>
                            <option value="DR Congo">DR Congo</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple countries</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('dataSourceModal')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_datasource" id="submitBtn"
                            class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Data Source</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg w-full max-w-md shadow-xl">
        <div class="p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-red-500">warning</span>
                <h3 class="text-base font-semibold text-gray-800">Confirm Deletion</h3>
            </div>
            <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure?</p>
            <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-3 text-xs text-red-700">
                <span class="material-symbols-outlined text-xs align-middle">info</span> This action cannot be undone.
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="delete_selected" value="1">
                <div id="deleteIdsContainer"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// PHP → JS state
const PHP = {
    page: <?= $page ?>,
    limit: <?= $limit ?>,
    totalPages: <?= $total_pages ?>,
    sort: <?= json_encode($sort_column) ?>,
    dir: <?= json_encode(strtolower($sort_direction)) ?>,
    searchName: <?= json_encode($search_name) ?>,
    searchCountry: <?= json_encode($search_country) ?>,
};

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function buildUrl(overrides) {
    const p = {
        page: PHP.page,
        limit: PHP.limit,
        sort: PHP.sort,
        dir: PHP.dir,
        search_name: document.getElementById('searchName').value.trim(),
        search_country: document.getElementById('searchCountry').value,
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);

    const q = new URLSearchParams();
    q.set('page', p.page);
    q.set('limit', p.limit);
    if (p.sort) q.set('sort', p.sort);
    if (p.dir) q.set('dir', p.dir);
    if (p.search_name) q.set('search_name', p.search_name);
    if (p.search_country) q.set('search_country', p.search_country);
    return '?' + q.toString();
}

function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (isNaN(pg) || pg < 1 || pg > PHP.totalPages) return;
    window.location.href = buildUrl({ page: pg });
}

function changeRowsPerPage() { window.location.href = buildUrl({ page: 1 }); }
function applyFilters() { window.location.href = buildUrl({ page: 1 }); }

function sortTable(col) {
    const newDir = (PHP.sort === col && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: col, dir: newDir });
}

// Add modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Data Source';
    document.getElementById('dataSourceId').value = '';
    document.getElementById('modalName').value = '';
    
    const select = document.getElementById('modalCountries');
    for (let i = 0; i < select.options.length; i++) {
        select.options[i].selected = false;
    }
    
    document.getElementById('submitBtn').name = 'add_datasource';
    document.getElementById('submitBtn').textContent = 'Add Data Source';
    openModal('dataSourceModal');
}

// Edit modal - FIXED
function editDataSource(id) {
    fetch(`?get_datasource=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            
            document.getElementById('modalTitle').textContent = 'Edit Data Source';
            document.getElementById('dataSourceId').value = data.id;
            document.getElementById('modalName').value = data.data_source_name || '';
            
            const select = document.getElementById('modalCountries');
            for (let i = 0; i < select.options.length; i++) {
                select.options[i].selected = false;
            }
            
            if (data.countries_array && Array.isArray(data.countries_array)) {
                for (let i = 0; i < select.options.length; i++) {
                    if (data.countries_array.includes(select.options[i].value)) {
                        select.options[i].selected = true;
                    }
                }
            }
            
            document.getElementById('submitBtn').name = 'edit_datasource';
            document.getElementById('submitBtn').textContent = 'Update Data Source';
            openModal('dataSourceModal');
        })
        .catch(err => { 
            console.error(err); 
            alert('Failed to load data source. Please refresh and try again.');
        });
}

// Delete functions
function deleteSingle(id, label) {
    document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(label)}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML = `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}

// Checkbox handling
function onCheckboxChange() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const total = document.querySelectorAll('.row-checkbox').length;
    const selAll = document.getElementById('selectAllCheckbox');
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkDeleteBtn').disabled = checked === 0;
    selAll.checked = checked > 0 && checked === total;
    selAll.indeterminate = checked > 0 && checked < total;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('selectAllCheckbox').addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        onCheckboxChange();
    });

    document.getElementById('clearSelectionsBtn').addEventListener('click', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
        document.getElementById('selectAllCheckbox').indeterminate = false;
        onCheckboxChange();
    });

    document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
        const ids = [...document.querySelectorAll('.row-checkbox:checked')].map(cb => cb.value);
        if (!ids.length) return;
        document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${ids.length}</strong> selected data source(s)?`;
        document.getElementById('deleteIdsContainer').innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
        openModal('deleteModal');
    });

    document.querySelectorAll('.sortable').forEach(th =>
        th.addEventListener('click', () => sortTable(th.dataset.sort))
    );

    document.getElementById('searchName').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    document.getElementById('searchCountry').addEventListener('change', () => applyFilters());

    onCheckboxChange();
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>