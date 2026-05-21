<?php
session_start();

// ============================================================
// API HANDLER - Must be at very top before ANY output
// ============================================================
if (isset($_GET['get_commodity']) && is_numeric($_GET['get_commodity'])) {
    // Include config before DB access
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_commodity'];
    $api_stmt = $con->prepare("SELECT id, commodity_name, category_id, variety, hs_code, units, commodity_alias, country, image_url FROM commodities WHERE id = ?");
    $api_stmt->bind_param("i", $get_id);
    $api_stmt->execute();
    $api_result = $api_stmt->get_result();
    if ($api_row = $api_result->fetch_assoc()) {
        $api_row['units']           = json_decode($api_row['units'], true) ?: [];
        $api_row['commodity_alias'] = json_decode($api_row['commodity_alias'], true) ?: [];
        $api_row['country']         = json_decode($api_row['country'], true) ?: [];
        echo json_encode($api_row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Commodity not found']);
    }
    $api_stmt->close();
    $con->close();
    exit;
}

// ============================================================
// EXPORT HANDLER - Must be before any HTML output too
// ============================================================
if (isset($_GET['export_all'])) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    // Clear any buffered output
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commodities_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $export_sql = "SELECT c.id, c.commodity_name, cat.name as category, c.variety, c.hs_code, c.units, c.commodity_alias, c.country, c.created_at
                   FROM commodities c
                   LEFT JOIN commodity_categories cat ON c.category_id = cat.id
                   ORDER BY c.id DESC";
    $export_result = $con->query($export_sql);

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Commodity Name', 'Category', 'Variety', 'HS Code', 'Packaging/Units', 'Aliases', 'Countries', 'Date Added']);

    while ($row = $export_result->fetch_assoc()) {
        $units    = json_decode($row['units'], true) ?: [];
        $aliases  = json_decode($row['commodity_alias'], true) ?: [];
        $countries= json_decode($row['country'], true) ?: [];

        $units_str    = implode('; ', array_map(fn($u) => ($u['size'] ?? '') . ' ' . ($u['unit'] ?? ''), $units));
        $aliases_str  = implode(', ', $aliases);
        $countries_str= implode(', ', $countries);

        fputcsv($output, [
            $row['id'],
            $row['commodity_name'],
            $row['category'] ?? '',
            $row['variety'] ?: '',
            $row['hs_code'] ?: '',
            $units_str,
            $aliases_str,
            $countries_str,
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    $con->close();
    exit;
}

// ============================================================
// NORMAL PAGE LOAD - now safe to include headers / HTML
// ============================================================
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

// Handle Add via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_commodity'])) {
    $commodity_name  = trim($_POST['commodity_name']);
    $category_id     = (int)$_POST['category'];
    $variety         = trim($_POST['variety']);
    $hs_code         = trim($_POST['hs_code']);
    $packaging_sizes = $_POST['packaging'] ?? [];
    $packaging_units = $_POST['unit'] ?? [];
    $aliases         = $_POST['commodity_alias'] ?? [];
    $countries_list  = $_POST['country'] ?? [];
    $created_at      = date('Y-m-d H:i:s');

    if (empty($commodity_name) || empty($category_id)) {
        $message = "Please fill all required fields.";
        $message_type = "error";
    } else {
        $check_stmt = $con->prepare("SELECT id FROM commodities WHERE commodity_name = ? AND category_id = ?");
        $check_stmt->bind_param("si", $commodity_name, $category_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $message = "This commodity already exists in this category!";
            $message_type = "error";
        } else {
            $packaging_units_arr = [];
            for ($i = 0; $i < count($packaging_sizes); $i++) {
                if (!empty($packaging_sizes[$i]) && !empty($packaging_units[$i])) {
                    $packaging_units_arr[] = ['size' => trim($packaging_sizes[$i]), 'unit' => trim($packaging_units[$i])];
                }
            }
            $units_json    = json_encode($packaging_units_arr);
            $aliases_json  = json_encode(array_values(array_filter($aliases)));
            $countries_json= json_encode(array_values(array_filter($countries_list)));

            $image_url = '';
            if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../base/uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                $image_name = time() . '_' . basename($_FILES['commodity_image']['name']);
                if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $upload_dir . $image_name)) {
                    $image_url = 'uploads/' . $image_name;
                }
            }

            $stmt = $con->prepare("INSERT INTO commodities (commodity_name, category_id, variety, hs_code, units, commodity_alias, country, image_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssssss", $commodity_name, $category_id, $variety, $hs_code, $units_json, $aliases_json, $countries_json, $image_url, $created_at);
            if ($stmt->execute()) { $message = "Commodity added successfully!"; $message_type = "success"; }
            else { $message = "Error adding commodity: " . $stmt->error; $message_type = "error"; }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Edit via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_commodity'])) {
    $id              = (int)$_POST['commodity_id'];
    $commodity_name  = trim($_POST['commodity_name']);
    $category_id     = (int)$_POST['category'];
    $variety         = trim($_POST['variety']);
    $hs_code         = trim($_POST['hs_code']);
    $packaging_sizes = $_POST['packaging'] ?? [];
    $packaging_units = $_POST['unit'] ?? [];
    $aliases         = $_POST['commodity_alias'] ?? [];
    $countries_list  = $_POST['country'] ?? [];

    if (empty($commodity_name) || empty($category_id)) {
        $message = "Please fill all required fields."; $message_type = "error";
    } else {
        $packaging_units_arr = [];
        for ($i = 0; $i < count($packaging_sizes); $i++) {
            if (!empty($packaging_sizes[$i]) && !empty($packaging_units[$i])) {
                $packaging_units_arr[] = ['size' => trim($packaging_sizes[$i]), 'unit' => trim($packaging_units[$i])];
            }
        }
        $units_json    = json_encode($packaging_units_arr);
        $aliases_json  = json_encode(array_values(array_filter($aliases)));
        $countries_json= json_encode(array_values(array_filter($countries_list)));

        // Get current image
        $s = $con->prepare("SELECT image_url FROM commodities WHERE id = ?");
        $s->bind_param("i", $id); $s->execute();
        $current   = $s->get_result()->fetch_assoc();
        $image_url = $current['image_url'] ?? '';
        $s->close();

        if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../base/uploads/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $image_name = time() . '_' . basename($_FILES['commodity_image']['name']);
            if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $upload_dir . $image_name)) {
                $image_url = 'uploads/' . $image_name;
            }
        }

        $stmt = $con->prepare("UPDATE commodities SET commodity_name=?, category_id=?, variety=?, hs_code=?, units=?, commodity_alias=?, country=?, image_url=? WHERE id=?");
        $stmt->bind_param("sissssssi", $commodity_name, $category_id, $variety, $hs_code, $units_json, $aliases_json, $countries_json, $image_url, $id);
        if ($stmt->execute()) { $message = "Commodity updated successfully!"; $message_type = "success"; }
        else { $message = "Error updating commodity: " . $stmt->error; $message_type = "error"; }
        $stmt->close();
    }
}

// Handle Delete (bulk or single)
if (isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM commodities WHERE id IN ($placeholders)");
    if ($stmt) {
        $types = str_repeat('i', count($selected_ids));
        $stmt->bind_param($types, ...$selected_ids);
        if ($stmt->execute()) { $message = "Successfully deleted " . $stmt->affected_rows . " commodity(ies)."; $message_type = "success"; }
        else { $message = "Error deleting: " . $stmt->error; $message_type = "error"; }
        $stmt->close();
    }
}

// Pagination
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

// Sort
$sort_column    = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_direction = (isset($_GET['dir']) && $_GET['dir'] == 'asc') ? 'ASC' : 'DESC';
if (!in_array($sort_column, ['id','commodity_name','category_name','variety','created_at'])) $sort_column = 'created_at';

// Search
$search_commodity = $_GET['search_commodity'] ?? '';
$search_category  = $_GET['search_category'] ?? '';

// Build query
$sql    = "SELECT c.id, c.commodity_name, c.variety, c.hs_code, c.units, c.commodity_alias, c.country, c.image_url, c.created_at, cat.name as category_name
           FROM commodities c
           LEFT JOIN commodity_categories cat ON c.category_id = cat.id
           WHERE 1=1";
$params = []; $types = "";

if (!empty($search_commodity)) { $sql .= " AND c.commodity_name LIKE ?"; $params[] = '%'.$search_commodity.'%'; $types .= "s"; }
if (!empty($search_category))  { $sql .= " AND cat.name LIKE ?";          $params[] = '%'.$search_category.'%';  $types .= "s"; }

// Count
$count_sql  = preg_replace('/SELECT .+ FROM/', 'SELECT COUNT(*) as total FROM', $sql);
$count_stmt = $con->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$sql .= " ORDER BY " . ($sort_column == 'category_name' ? 'cat.name' : 'c.'.$sort_column) . " $sort_direction LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = ($page - 1) * $limit; $types .= "ii";

$stmt = $con->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$commodities_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$total_pages = ceil($total_records / $limit);

// Categories & Countries
$categories = [];
$cat_result = $con->query("SELECT id, name FROM commodity_categories ORDER BY name ASC");
if ($cat_result) while ($r = $cat_result->fetch_assoc()) $categories[] = $r;

$countries = [];
$ctry_result = $con->query("SELECT country_name FROM countries ORDER BY country_name ASC");
if ($ctry_result) while ($r = $ctry_result->fetch_assoc()) $countries[] = $r['country_name'];

// Stats
$total_commodities = $total_records;
$cereals_count = $con->query("SELECT COUNT(*) as t FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id=cat.id WHERE cat.name LIKE 'Cereal%'")->fetch_assoc()['t'] ?? 0;
$pulses_count  = $con->query("SELECT COUNT(*) as t FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id=cat.id WHERE cat.name LIKE 'Pulse%'")->fetch_assoc()['t'] ?? 0;
$oil_seeds_count=$con->query("SELECT COUNT(*) as t FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id=cat.id WHERE cat.name LIKE 'Oil%'")->fetch_assoc()['t'] ?? 0;
?>

<style>
.auth-bg-gradient{background:radial-gradient(circle at top left,rgba(0,69,13,.03),transparent),radial-gradient(circle at bottom right,rgba(128,0,0,.03),transparent)}
.header-accent-gradient{background:linear-gradient(90deg,#00450d 0%,#800000 50%,#00450d 100%)}
.table-row-hover:hover{background-color:#fefaf5;transition:all .2s ease}
.stat-card{transition:all .2s ease;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.search-input:focus{border-color:#800000;outline:none}
.action-btn{padding:.2rem .4rem;border-radius:.375rem;font-size:.7rem;font-weight:500;transition:all .2s;cursor:pointer;border:none;display:inline-flex;align-items:center}
.pagination-btn{min-width:32px;height:32px;transition:all .2s ease}
.pagination-btn:hover:not(:disabled):not(.active-page){background-color:#fef3e7;border-color:#800000;color:#800000}
.pagination-btn.active-page{background-color:#800000;border-color:#800000;color:white}
.page-size-select{font-size:.75rem;padding:.25rem .5rem;border-radius:.375rem;border:1px solid #e5e7eb;background-color:white;cursor:pointer}
.sortable{cursor:pointer;user-select:none}
.sortable:hover{color:#800000}
.sort-icon{font-size:.7rem;margin-left:.2rem;vertical-align:middle}
.modal-gradient-header{background:linear-gradient(135deg,#800000 0%,#00450d 100%)}
.selected-badge{display:inline-block;background-color:rgba(180,80,50,.15);color:#800000;padding:.125rem .5rem;border-radius:9999px;font-size:.65rem;font-weight:600;margin-left:.5rem}
.image-preview{width:32px;height:32px;object-fit:cover;border-radius:4px;cursor:pointer}
.dynamic-group{display:flex;gap:10px;margin-bottom:10px;align-items:flex-end}
.dynamic-group>div{flex:1}
.dynamic-group label{font-size:.65rem;margin-bottom:2px;display:block;color:#666}
.dynamic-group input,.dynamic-group select{width:100%;padding:.4rem .5rem;font-size:.75rem;border:1px solid #e5e7eb;border-radius:.375rem}
.remove-row-btn{padding:.3rem .5rem;background:#fee2e2;color:#dc2626;border:none;border-radius:.375rem;cursor:pointer;margin-bottom:.2rem;flex-shrink:0}
.remove-row-btn:hover{background:#fecaca}
.add-more-btn{padding:.4rem .8rem;background:#e0e7ff;color:#3730a3;border:none;border-radius:.375rem;font-size:.7rem;cursor:pointer;margin-top:.5rem;display:inline-flex;align-items:center;gap:4px}
.add-more-btn:hover{background:#c7d2fe}
/* Fix: ensure Material Symbols render as icons not text */
.material-symbols-outlined{font-family:'Material Symbols Outlined'!important;font-style:normal;font-weight:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';font-feature-settings:'liga';-webkit-font-smoothing:antialiased}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
  <div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
      <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
          <h1 class="text-2xl font-bold text-maroon">Commodities Management</h1>
          <p class="text-gray-600 text-sm mt-1">Manage agricultural commodities and their details</p>
        </div>
        <div class="flex gap-2">
          <!-- FIX 1: export opens as file download, not new window -->
          <a href="?export_all=1"
             class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
            <span class="material-symbols-outlined text-base">download</span>
            Export All CSV
          </a>
          <button onclick="openAddModal()"
                  class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
            <span class="material-symbols-outlined text-base">add_circle</span>
            Add Commodity
          </button>
        </div>
      </div>
      <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Messages -->
    <?php if (!empty($message)): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type=='success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
      <span class="material-symbols-outlined text-base"><?= $message_type=='success' ? 'check_circle' : 'error' ?></span>
      <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- Stats Cards — FIX 2: replaced broken FA icons with Material Symbols -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
      <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Total Commodities</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($total_commodities) ?></p>
          </div>
          <span class="material-symbols-outlined text-3xl text-maroon/40">eco</span>
        </div>
      </div>
      <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-amber-500">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Cereals</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($cereals_count) ?></p>
          </div>
          <span class="material-symbols-outlined text-3xl text-amber-400/60">grain</span>
        </div>
      </div>
      <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Pulses</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($pulses_count) ?></p>
          </div>
          <span class="material-symbols-outlined text-3xl text-green-500/50">grass</span>
        </div>
      </div>
      <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-600">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Oil Seeds</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($oil_seeds_count) ?></p>
          </div>
          <span class="material-symbols-outlined text-3xl text-yellow-500/50">water_drop</span>
        </div>
      </div>
    </div>

    <!-- Search & Bulk Actions -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
      <div class="flex flex-wrap gap-3 items-center justify-between">
        <div class="flex-1 min-w-[150px]">
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
            <input type="text" id="searchCommodity" placeholder="Search commodity..."
                   class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                   value="<?= htmlspecialchars($search_commodity) ?>">
          </div>
        </div>
        <div class="flex-1 min-w-[150px]">
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">category</span>
            <input type="text" id="searchCategory" placeholder="Search category..."
                   class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                   value="<?= htmlspecialchars($search_category) ?>">
          </div>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button onclick="applyFilters()"
                  class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-base">filter_list</span> Filter
          </button>
          <!-- FIX 3: clear just unchecks visible checkboxes client-side -->
          <button id="clearSelectionsBtn"
                  class="px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-all inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-base">clear</span> Clear Selected
          </button>
          <button id="bulkDeleteBtn" disabled
                  class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-base">delete</span>
            Delete (<span id="selectedCount">0</span>)
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="commoditiesTable">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="w-8 px-3 py-2 text-left">
                <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-maroon focus:ring-maroon/20">
              </th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">
                ID<?php if($sort_column=='id') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
              </th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="commodity_name">
                Commodity<?php if($sort_column=='commodity_name') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
              </th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="category_name">
                Category<?php if($sort_column=='category_name') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
              </th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="variety">
                Variety<?php if($sort_column=='variety') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
              </th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">HS Code</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Image</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="created_at">
                Date Added<?php if($sort_column=='created_at') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
              </th>
              <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-24">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100" id="tableBody">
            <?php if (empty($commodities_data)): ?>
            <tr>
              <td colspan="9" class="px-3 py-8 text-center text-gray-400">
                <span class="material-symbols-outlined text-5xl text-gray-300 block">eco</span>
                <p class="text-sm mt-1">No commodities found</p>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($commodities_data as $commodity): ?>
            <tr class="table-row-hover" data-id="<?= $commodity['id'] ?>">
              <td class="px-3 py-2">
                <!-- FIX 3: simple client-side checkbox, no AJAX session tracking -->
                <input type="checkbox" class="row-checkbox rounded border-gray-300 text-maroon focus:ring-maroon/20"
                       value="<?= $commodity['id'] ?>" onchange="onCheckboxChange()">
              </td>
              <td class="px-3 py-2 text-xs text-gray-600"><?= $commodity['id'] ?></td>
              <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($commodity['commodity_name']) ?></td>
              <td class="px-3 py-2"><span class="bg-gray-100 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($commodity['category_name'] ?? '—') ?></span></td>
              <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($commodity['variety'] ?: '—') ?></td>
              <td class="px-3 py-2 text-xs font-mono text-gray-600"><?= htmlspecialchars($commodity['hs_code'] ?: '—') ?></td>
              <td class="px-3 py-2">
                <?php if (!empty($commodity['image_url'])): ?>
                <img src="../base/<?= htmlspecialchars($commodity['image_url']) ?>" class="image-preview"
                     onclick="showImageModal('<?= htmlspecialchars($commodity['image_url']) ?>','<?= htmlspecialchars($commodity['commodity_name']) ?>')">
                <?php else: ?>
                <span class="text-gray-400 text-xs">No image</span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($commodity['created_at'])) ?></td>
              <td class="px-3 py-2">
                <div class="flex items-center justify-center gap-1">
                  <button onclick="editCommodity(<?= $commodity['id'] ?>)"
                          class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                    <span class="material-symbols-outlined text-sm">edit</span>
                  </button>
                  <button onclick="deleteSingle(<?= $commodity['id'] ?>,'<?= htmlspecialchars(addslashes($commodity['commodity_name'])) ?>')"
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

      <!-- Pagination -->
      <div class="border-t border-gray-200 px-4 py-3 bg-white">
        <div class="flex flex-wrap justify-between items-center gap-3">
          <div class="text-xs text-gray-500">
            Showing <?= ($page-1)*$limit+1 ?> to <?= min($page*$limit,$total_records) ?> of <?= $total_records ?> commodities
          </div>
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500">Rows:</span>
              <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                <option value="10" <?= $limit==10?'selected':'' ?>>10</option>
                <option value="20" <?= $limit==20?'selected':'' ?>>20</option>
                <option value="50" <?= $limit==50?'selected':'' ?>>50</option>
                <option value="100" <?= $limit==100?'selected':'' ?>>100</option>
              </select>
            </div>
            <nav class="flex items-center gap-1">
              <button onclick="goToPage(1)" class="pagination-btn w-7 h-7 rounded border border-gray-200 flex items-center justify-center <?= $page<=1?'opacity-40 cursor-not-allowed':'' ?>" <?= $page<=1?'disabled':'' ?>>
                <span class="material-symbols-outlined text-sm">first_page</span>
              </button>
              <button onclick="goToPage(<?= $page-1 ?>)" class="pagination-btn w-7 h-7 rounded border border-gray-200 flex items-center justify-center <?= $page<=1?'opacity-40 cursor-not-allowed':'' ?>" <?= $page<=1?'disabled':'' ?>>
                <span class="material-symbols-outlined text-sm">chevron_left</span>
              </button>
              <?php
              $sp = max(1,$page-2); $ep = min($total_pages,$page+2);
              if ($sp>1){ echo '<button onclick="goToPage(1)" class="pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 text-xs">1</button>'; if($sp>2) echo '<span class="text-gray-400 px-1">…</span>'; }
              for($i=$sp;$i<=$ep;$i++){
                  $cls = ($i==$page) ? 'active-page bg-maroon text-white' : 'border border-gray-200 hover:bg-gray-50';
                  echo "<button onclick=\"goToPage($i)\" class=\"pagination-btn w-7 h-7 rounded text-xs $cls\">$i</button>";
              }
              if($ep<$total_pages){ if($ep<$total_pages-1) echo '<span class="text-gray-400 px-1">…</span>'; echo "<button onclick=\"goToPage($total_pages)\" class=\"pagination-btn w-7 h-7 rounded border border-gray-200 hover:bg-gray-50 text-xs\">$total_pages</button>"; }
              ?>
              <button onclick="goToPage(<?= $page+1 ?>)" class="pagination-btn w-7 h-7 rounded border border-gray-200 flex items-center justify-center <?= $page>=$total_pages?'opacity-40 cursor-not-allowed':'' ?>" <?= $page>=$total_pages?'disabled':'' ?>>
                <span class="material-symbols-outlined text-sm">chevron_right</span>
              </button>
              <button onclick="goToPage(<?= $total_pages ?>)" class="pagination-btn w-7 h-7 rounded border border-gray-200 flex items-center justify-center <?= $page>=$total_pages?'opacity-40 cursor-not-allowed':'' ?>" <?= $page>=$total_pages?'disabled':'' ?>>
                <span class="material-symbols-outlined text-sm">last_page</span>
              </button>
            </nav>
          </div>
          <a href="../base/landing_page.php"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
            <span class="material-symbols-outlined text-base">arrow_back</span> Back
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===================== ADD / EDIT MODAL ===================== -->
<div id="commodityModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-xl">
      <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0 z-10">
        <h3 id="modalTitle" class="text-base font-semibold text-white">Add New Commodity</h3>
        <button onclick="closeModal('commodityModal')" class="text-white/80 hover:text-white">
          <span class="material-symbols-outlined text-base">close</span>
        </button>
      </div>
      <div class="p-5">
        <form method="POST" action="" id="commodityForm" enctype="multipart/form-data">
          <input type="hidden" name="commodity_id" id="commodityId">

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Commodity Name <span class="text-red-500">*</span></label>
              <input type="text" name="commodity_name" id="commodityName" required
                     class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Category <span class="text-red-500">*</span></label>
              <select name="category" id="categoryId" required
                      class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Variety</label>
              <input type="text" name="variety" id="variety"
                     class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">HS Code</label>
              <input type="text" name="hs_code" id="hsCode"
                     class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
            </div>
          </div>

          <!-- Packaging -->
          <div class="mb-4">
            <label class="block text-xs text-gray-600 mb-1 font-medium">Packaging &amp; Units</label>
            <div id="packagingContainer"></div>
            <button type="button" class="add-more-btn" onclick="addPackagingRow()">
              <span class="material-symbols-outlined text-sm">add</span> Add Packaging
            </button>
          </div>

          <!-- Aliases & Countries -->
          <div class="mb-4">
            <label class="block text-xs text-gray-600 mb-1 font-medium">Aliases &amp; Countries</label>
            <div id="aliasContainer"></div>
            <button type="button" class="add-more-btn" onclick="addAliasRow()">
              <span class="material-symbols-outlined text-sm">add</span> Add Alias
            </button>
          </div>

          <div class="mb-4">
            <label class="block text-xs text-gray-600 mb-1">Commodity Image</label>
            <input type="file" name="commodity_image" id="commodityImage" accept="image/*"
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
            <div id="imagePreview" class="mt-2 hidden">
              <img id="previewImg" class="h-16 w-16 object-cover rounded-lg">
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
            <button type="button" onclick="closeModal('commodityModal')"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button type="submit" name="add_commodity" id="submitBtn"
                    class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Commodity</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ===================== DELETE MODAL ===================== -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg w-full max-w-md shadow-xl">
    <div class="p-4">
      <div class="flex items-center gap-2 mb-3">
        <span class="material-symbols-outlined text-red-500">warning</span>
        <h3 class="text-base font-semibold text-gray-800">Confirm Deletion</h3>
      </div>
      <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure you want to delete this commodity?</p>
      <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-3 text-xs text-red-700">
        <span class="material-symbols-outlined text-xs align-middle">info</span> This action cannot be undone.
      </div>
      <form method="POST" action="" id="deleteForm">
        <input type="hidden" name="delete_selected" value="1">
        <div id="deleteIdsContainer"></div>
        <div class="flex justify-end gap-2">
          <button type="button" onclick="closeModal('deleteModal')"
                  class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
          <button type="submit"
                  class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== IMAGE MODAL ===================== -->
<div id="imageModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg max-w-2xl max-h-[90vh] overflow-auto">
    <div class="p-4">
      <div class="flex justify-end mb-2">
        <button onclick="closeModal('imageModal')" class="text-gray-500 hover:text-gray-700">✕</button>
      </div>
      <img id="modalImage" src="" alt="" class="w-full h-auto">
    </div>
  </div>
</div>

<script>
// ─── Data from PHP ───────────────────────────────────────────
const categories    = <?= json_encode($categories) ?>;
const countriesList = <?= json_encode($countries) ?>;

// ─── Helpers ─────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// ─── Packaging helpers ────────────────────────────────────────
function packagingRowHtml(size='', unit='') {
    return `<div class="dynamic-group">
        <div><label>Size</label><input type="text" name="packaging[]" placeholder="e.g. 10" value="${escapeHtml(size)}"></div>
        <div><label>Unit</label>
            <select name="unit[]">
                <option value="">Select</option>
                ${['Kg','Tons','g','lb'].map(u=>`<option value="${u}" ${u===unit?'selected':''}>${u}</option>`).join('')}
            </select>
        </div>
        <button type="button" class="remove-row-btn" onclick="removeRow(this)">✕</button>
    </div>`;
}

function aliasRowHtml(alias='', country='') {
    const opts = countriesList.map(c=>`<option value="${escapeHtml(c)}" ${c===country?'selected':''}>${escapeHtml(c)}</option>`).join('');
    return `<div class="dynamic-group">
        <div><label>Alias</label><input type="text" name="commodity_alias[]" placeholder="e.g. Maize" value="${escapeHtml(alias)}"></div>
        <div><label>Country</label><select name="country[]"><option value="">Select Country</option>${opts}</select></div>
        <button type="button" class="remove-row-btn" onclick="removeRow(this)">✕</button>
    </div>`;
}

function addPackagingRow() { document.getElementById('packagingContainer').insertAdjacentHTML('beforeend', packagingRowHtml()); }
function addAliasRow()     { document.getElementById('aliasContainer').insertAdjacentHTML('beforeend', aliasRowHtml()); }

function removeRow(btn) {
    const container = btn.closest('.dynamic-group').parentElement;
    if (container.children.length > 1) btn.closest('.dynamic-group').remove();
    else alert('You must have at least one entry.');
}

// ─── Modal: Add ──────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Commodity';
    document.getElementById('commodityId').value = '';
    document.getElementById('commodityName').value = '';
    document.getElementById('categoryId').value = '';
    document.getElementById('variety').value = '';
    document.getElementById('hsCode').value = '';
    document.getElementById('packagingContainer').innerHTML = packagingRowHtml();
    document.getElementById('aliasContainer').innerHTML = aliasRowHtml();
    document.getElementById('commodityImage').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
    document.getElementById('submitBtn').name = 'add_commodity';
    document.getElementById('submitBtn').textContent = 'Add Commodity';
    openModal('commodityModal');
}

// ─── Modal: Edit — FIX 4 ─────────────────────────────────────
// The API handler is now at the very top of the PHP file (before any HTML),
// so fetch() gets clean JSON instead of HTML-polluted output.
function editCommodity(id) {
    fetch(`${window.location.pathname}?get_commodity=${id}`)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Edit Commodity';
            document.getElementById('commodityId').value = data.id;
            document.getElementById('commodityName').value = data.commodity_name || '';
            document.getElementById('categoryId').value = data.category_id || '';
            document.getElementById('variety').value = data.variety || '';
            document.getElementById('hsCode').value = data.hs_code || '';

            // Packaging rows
            const pc = document.getElementById('packagingContainer');
            pc.innerHTML = '';
            if (data.units && data.units.length > 0) {
                data.units.forEach(u => pc.insertAdjacentHTML('beforeend', packagingRowHtml(u.size||'', u.unit||'')));
            } else {
                pc.innerHTML = packagingRowHtml();
            }

            // Alias rows
            const ac = document.getElementById('aliasContainer');
            ac.innerHTML = '';
            const maxLen = Math.max((data.commodity_alias||[]).length, (data.country||[]).length, 1);
            for (let i = 0; i < maxLen; i++) {
                const a = (data.commodity_alias && data.commodity_alias[i]) || '';
                const c = (data.country && data.country[i]) || '';
                ac.insertAdjacentHTML('beforeend', aliasRowHtml(a, c));
            }

            document.getElementById('commodityImage').value = '';
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('submitBtn').name = 'edit_commodity';
            document.getElementById('submitBtn').textContent = 'Update Commodity';
            openModal('commodityModal');
        })
        .catch(err => {
            console.error('Edit fetch error:', err);
            alert('Failed to load commodity data. Check console for details.');
        });
}

// ─── Delete: single ──────────────────────────────────────────
function deleteSingle(id, name) {
    document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML = `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}

// ─── Delete: bulk — FIX 3 ────────────────────────────────────
function showImageModal(imageUrl, name) {
    document.getElementById('modalImage').src  = '../base/' + imageUrl;
    document.getElementById('modalImage').alt  = name;
    openModal('imageModal');
}

// ─── Checkbox logic (client-side only, no AJAX session) ──────
function onCheckboxChange() {
    const total   = document.querySelectorAll('.row-checkbox:checked').length;
    const allCbs  = document.querySelectorAll('.row-checkbox');
    const selAll  = document.getElementById('selectAllCheckbox');
    const delBtn  = document.getElementById('bulkDeleteBtn');
    const countEl = document.getElementById('selectedCount');

    countEl.textContent = total;
    delBtn.disabled = (total === 0);

    if (total === 0)              { selAll.checked = false; selAll.indeterminate = false; }
    else if (total === allCbs.length) { selAll.checked = true;  selAll.indeterminate = false; }
    else                          { selAll.checked = false; selAll.indeterminate = true; }
}

document.addEventListener('DOMContentLoaded', function () {
    // Select-all toggle
    document.getElementById('selectAllCheckbox').addEventListener('change', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = this.checked; });
        onCheckboxChange();
    });

    // Clear selected
    document.getElementById('clearSelectionsBtn').addEventListener('click', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
        document.getElementById('selectAllCheckbox').indeterminate = false;
        onCheckboxChange();
    });

    // Bulk delete — collect IDs and populate delete modal
    document.getElementById('bulkDeleteBtn').addEventListener('click', function () {
        const ids = [...document.querySelectorAll('.row-checkbox:checked')].map(cb => cb.value);
        if (ids.length === 0) return;
        document.getElementById('deleteModalText').innerHTML =
            `Are you sure you want to delete <strong>${ids.length}</strong> selected commodity(ies)?`;
        document.getElementById('deleteIdsContainer').innerHTML =
            ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
        openModal('deleteModal');
    });

    // Sortable column headers
    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', function () { sortTable(this.dataset.sort); });
    });

    onCheckboxChange();
});

// ─── Image preview ────────────────────────────────────────────
document.getElementById('commodityImage').addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('imagePreview').classList.add('hidden');
    }
});

// ─── Pagination / Sort / Filter helpers ──────────────────────
function buildUrl(overrides={}) {
    const params = new URLSearchParams(window.location.search);
    const defaults = {
        page:             params.get('page')             || 1,
        limit:            document.getElementById('rowsPerPage').value,
        search_commodity: document.getElementById('searchCommodity').value,
        search_category:  document.getElementById('searchCategory').value,
        sort:             params.get('sort')             || '',
        dir:              params.get('dir')              || '',
    };
    Object.assign(defaults, overrides);
    const q = new URLSearchParams();
    Object.entries(defaults).forEach(([k,v]) => { if (v) q.set(k,v); });
    return '?' + q.toString();
}

function goToPage(page)        { window.location.href = buildUrl({page}); }
function changeRowsPerPage()   { window.location.href = buildUrl({page:1}); }
function applyFilters()        { window.location.href = buildUrl({page:1}); }
function sortTable(column) {
    const params = new URLSearchParams(window.location.search);
    const cur = params.get('sort'); const curDir = params.get('dir');
    const dir = (cur===column && curDir==='asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({page:1, sort:column, dir});
}
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>