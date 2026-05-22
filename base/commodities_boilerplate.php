<?php
session_start();

// ============================================================
// API HANDLER - Must be at very top before ANY output
// ============================================================
if (isset($_GET['get_commodity']) && is_numeric($_GET['get_commodity'])) {
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
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Commodity Name', 'Category', 'Variety', 'HS Code', 'Packaging/Units', 'Aliases', 'Countries', 'Date Added']);

    while ($row = $export_result->fetch_assoc()) {
        $units     = json_decode($row['units'], true) ?: [];
        $aliases   = json_decode($row['commodity_alias'], true) ?: [];
        $countries = json_decode($row['country'], true) ?: [];

        $units_str     = implode('; ', array_map(fn($u) => ($u['size'] ?? '') . ' ' . ($u['unit'] ?? ''), $units));
        $aliases_str   = implode(', ', $aliases);
        $countries_str = implode(', ', $countries);

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
// CSV TEMPLATE DOWNLOAD
// ============================================================
if (isset($_GET['download_template'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commodities_import_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['HS Code', 'Category', 'Commodity Name', 'Variety', 'Packaging & Units', 'Aliases & Countries']);
    fputcsv($out, ['1001.10', 'Cereals', 'Wheat', 'Hard Red Winter', '25 Kg, 50 Kg', 'Wheat:Kenya, Wheat:Uganda']);
    fputcsv($out, ['0713.10', 'Pulses', 'Chickpeas', 'Desi', '50 Kg', 'Garbanzo:USA']);
    fputcsv($out, ['1507.10', 'Oil Seeds', 'Soya Bean', '', '25 Kg', '']);
    fclose($out);
    exit;
}

// ============================================================
// NORMAL PAGE LOAD
// ============================================================
require_once '../admin/includes/admin_header.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$message      = '';
$message_type = '';

// ============================================================
// STATISTICS
// ============================================================
$total_commodities = (int)($con->query("SELECT COUNT(*) as t FROM commodities")->fetch_assoc()['t'] ?? 0);
$cereals_count     = (int)($con->query("SELECT COUNT(*) as t FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id=cat.id WHERE cat.name LIKE 'Cereal%'")->fetch_assoc()['t'] ?? 0);
$pulses_count      = (int)($con->query("SELECT COUNT(*) as t FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id=cat.id WHERE cat.name LIKE 'Pulse%'")->fetch_assoc()['t'] ?? 0);
$oil_seeds_count   = (int)($con->query("SELECT COUNT(*) as t FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id=cat.id WHERE cat.name LIKE 'Oil%'")->fetch_assoc()['t'] ?? 0);

// ============================================================
// FORM SUBMISSIONS
// ============================================================

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
        $message = "Please fill all required fields."; $message_type = "error";
    } else {
        $check = $con->prepare("SELECT id FROM commodities WHERE commodity_name = ? AND category_id = ?");
        $check->bind_param("si", $commodity_name, $category_id);
        $check->execute(); $check->store_result();

        if ($check->num_rows > 0) {
            $message = "This commodity already exists in this category!"; $message_type = "error";
        } else {
            $pu = [];
            for ($i = 0; $i < count($packaging_sizes); $i++) {
                if (!empty($packaging_sizes[$i]) && !empty($packaging_units[$i]))
                    $pu[] = ['size' => trim($packaging_sizes[$i]), 'unit' => trim($packaging_units[$i])];
            }
            $units_json    = json_encode($pu);
            $aliases_json  = json_encode(array_values(array_filter($aliases)));
            $countries_json= json_encode(array_values(array_filter($countries_list)));
            $image_url = '';
            if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../base/uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                $image_name = time() . '_' . basename($_FILES['commodity_image']['name']);
                if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $upload_dir . $image_name))
                    $image_url = 'uploads/' . $image_name;
            }
            $stmt = $con->prepare("INSERT INTO commodities (commodity_name, category_id, variety, hs_code, units, commodity_alias, country, image_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssssss", $commodity_name, $category_id, $variety, $hs_code, $units_json, $aliases_json, $countries_json, $image_url, $created_at);
            if ($stmt->execute()) { $message = "Commodity added successfully!"; $message_type = "success"; $total_commodities++; }
            else { $message = "Error adding commodity: " . $stmt->error; $message_type = "error"; }
            $stmt->close();
        }
        $check->close();
    }
}

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
        $pu = [];
        for ($i = 0; $i < count($packaging_sizes); $i++) {
            if (!empty($packaging_sizes[$i]) && !empty($packaging_units[$i]))
                $pu[] = ['size' => trim($packaging_sizes[$i]), 'unit' => trim($packaging_units[$i])];
        }
        $units_json    = json_encode($pu);
        $aliases_json  = json_encode(array_values(array_filter($aliases)));
        $countries_json= json_encode(array_values(array_filter($countries_list)));

        $s = $con->prepare("SELECT image_url FROM commodities WHERE id = ?");
        $s->bind_param("i", $id); $s->execute();
        $current   = $s->get_result()->fetch_assoc();
        $image_url = $current['image_url'] ?? '';
        $s->close();

        if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../base/uploads/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $image_name = time() . '_' . basename($_FILES['commodity_image']['name']);
            if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $upload_dir . $image_name))
                $image_url = 'uploads/' . $image_name;
        }

        $stmt = $con->prepare("UPDATE commodities SET commodity_name=?, category_id=?, variety=?, hs_code=?, units=?, commodity_alias=?, country=?, image_url=? WHERE id=?");
        $stmt->bind_param("sissssssi", $commodity_name, $category_id, $variety, $hs_code, $units_json, $aliases_json, $countries_json, $image_url, $id);
        if ($stmt->execute()) { $message = "Commodity updated successfully!"; $message_type = "success"; }
        else { $message = "Error updating commodity: " . $stmt->error; $message_type = "error"; }
        $stmt->close();
    }
}

if (isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM commodities WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $message = "Successfully deleted $deleted commodity(ies)."; $message_type = "success";
            $total_commodities = max(0, $total_commodities - $deleted);
        } else { $message = "Error deleting: " . $stmt->error; $message_type = "error"; }
        $stmt->close();
    }
}

// ============================================================
// CSV BULK IMPORT
// ============================================================
$import_message = '';
$import_type    = '';
$import_stats   = [];

if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    fgetcsv($handle); // skip header row

    $successCount = 0;
    $errorCount   = 0;
    $errors       = [];
    $rowNumber    = 1;

    $con->begin_transaction();
    try {
        while (($data = fgetcsv($handle, 2000, ',')) !== false) {
            $rowNumber++;
            if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) continue;

            $hs_code        = trim($data[0] ?? '');
            $category_name  = trim($data[1] ?? '');
            $commodity_name = trim($data[2] ?? '');
            $variety        = trim($data[3] ?? '');
            $packaging_raw  = trim($data[4] ?? '');
            $aliases_raw    = trim($data[5] ?? '');

            if ($hs_code === '')        { $errors[] = "Row $rowNumber: HS Code is required.";       $errorCount++; continue; }
            if ($category_name === '')  { $errors[] = "Row $rowNumber: Category is required.";      $errorCount++; continue; }
            if ($commodity_name === '') { $errors[] = "Row $rowNumber: Commodity Name is required."; $errorCount++; continue; }

            // Get or create category
            $cs = $con->prepare("SELECT id FROM commodity_categories WHERE name = ?");
            $cs->bind_param('s', $category_name); $cs->execute();
            $cr = $cs->get_result();
            if ($cr->num_rows > 0) {
                $category_id = $cr->fetch_assoc()['id'];
            } else {
                $ci = $con->prepare("INSERT INTO commodity_categories (name) VALUES (?)");
                $ci->bind_param('s', $category_name); $ci->execute();
                $category_id = $con->insert_id;
                $ci->close();
            }
            $cs->close();

            // Parse packaging  e.g. "25 Kg, 50 Kg"
            $units_arr = [];
            if ($packaging_raw !== '') {
                foreach (array_map('trim', explode(',', $packaging_raw)) as $pu) {
                    if (preg_match('/^(\d+(?:\.\d+)?)\s*(\w+)$/', $pu, $m)) {
                        $units_arr[] = ['size' => $m[1], 'unit' => $m[2]];
                    } else {
                        $errors[] = "Row $rowNumber: Warning \xe2\x80\x93 could not parse unit '$pu'.";
                    }
                }
            }
            $units_json = json_encode($units_arr);

            // Parse aliases & countries  e.g. "Maize:Kenya, Corn:USA"
            $alias_list   = [];
            $country_list = [];
            if ($aliases_raw !== '') {
                foreach (array_map('trim', explode(',', $aliases_raw)) as $ac) {
                    if (strpos($ac, ':') !== false) {
                        [$al, $co] = array_map('trim', explode(':', $ac, 2));
                        if ($al !== '') $alias_list[]   = $al;
                        if ($co !== '') $country_list[] = $co;
                    } else {
                        $errors[] = "Row $rowNumber: Warning \xe2\x80\x93 expected 'Alias:Country', got '$ac'.";
                    }
                }
            }
            $aliases_json   = json_encode(array_values(array_unique($alias_list)));
            $countries_json = json_encode(array_values(array_unique($country_list)));

            // Duplicate check
            $ck = $con->prepare("SELECT id FROM commodities WHERE hs_code = ? AND commodity_name = ?");
            $ck->bind_param('ss', $hs_code, $commodity_name); $ck->execute(); $ck->store_result();
            $exists = $ck->num_rows > 0;
            $ck->close();

            if ($exists) {
                if (isset($_POST['overwrite_existing'])) {
                    $up = $con->prepare("UPDATE commodities SET category_id=?, variety=?, units=?, commodity_alias=?, country=? WHERE hs_code=? AND commodity_name=?");
                    $up->bind_param('issssss', $category_id, $variety, $units_json, $aliases_json, $countries_json, $hs_code, $commodity_name);
                    if ($up->execute()) $successCount++;
                    else { $errors[] = "Row $rowNumber: Update failed \xe2\x80\x93 " . $up->error; $errorCount++; }
                    $up->close();
                } else {
                    $errors[] = "Row $rowNumber: '$commodity_name' (HS $hs_code) already exists \xe2\x80\x93 skipped (enable overwrite to update).";
                    $errorCount++;
                }
                continue;
            }

            // Insert
            $ins = $con->prepare("INSERT INTO commodities (hs_code, category_id, commodity_name, variety, units, commodity_alias, country, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $ins->bind_param('sisssss', $hs_code, $category_id, $commodity_name, $variety, $units_json, $aliases_json, $countries_json);
            if ($ins->execute()) $successCount++;
            else { $errors[] = "Row $rowNumber: Insert failed \xe2\x80\x93 " . $ins->error; $errorCount++; }
            $ins->close();
        }

        $criticalErrors = count(array_filter($errors, fn($e) => strpos($e, 'Warning') === false));
        if ($criticalErrors === 0) {
            $con->commit();
            $total_commodities += $successCount;
            $import_type    = count($errors) > 0 ? 'warning' : 'success';
            $import_message = "Successfully imported <strong>$successCount</strong> commodity(ies).";
            if (count($errors) > 0)
                $import_message .= ' <span class="opacity-70 font-normal">(' . count($errors) . ' warning(s))</span>';
            $import_stats = $errors;
        } else {
            $con->rollback();
            $import_type    = 'error';
            $import_message = "Import rolled back \xe2\x80\x94 <strong>$criticalErrors</strong> critical error(s). <strong>$successCount</strong> row(s) would have succeeded.";
            $import_stats   = $errors;
        }
    } catch (Exception $e) {
        $con->rollback();
        $import_type    = 'error';
        $import_message = "Import failed: " . htmlspecialchars($e->getMessage());
    }
    fclose($handle);
} elseif (isset($_POST['import_csv'])) {
    $import_type    = 'error';
    $import_message = "Please select a valid CSV file.";
}


// ============================================================
// PAGINATION PARAMETERS  ← all parsed cleanly in PHP
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column    = $_GET['sort'] ?? 'created_at';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
if (!in_array($sort_column, ['id', 'commodity_name', 'category_name', 'variety', 'created_at'])) $sort_column = 'created_at';

$search_commodity = trim($_GET['search_commodity'] ?? '');
$search_category  = trim($_GET['search_category']  ?? '');

// ── Build WHERE ──────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = []; $types = "";
if ($search_commodity !== '') { $where .= " AND c.commodity_name LIKE ?"; $params[] = '%'.$search_commodity.'%'; $types .= "s"; }
if ($search_category  !== '') { $where .= " AND cat.name LIKE ?";          $params[] = '%'.$search_category.'%';  $types .= "s"; }

// ── Total filtered count ─────────────────────────────────────
$count_sql  = "SELECT COUNT(*) as total FROM commodities c LEFT JOIN commodity_categories cat ON c.category_id = cat.id $where";
$count_stmt = $con->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// ── Clamp page AFTER knowing total ──────────────────────────
$total_pages = ($filtered_records > 0) ? (int)ceil($filtered_records / $limit) : 1;
$page        = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset      = ($page - 1) * $limit;

// ── Fetch page rows ──────────────────────────────────────────
$order_col  = ($sort_column === 'category_name') ? 'cat.name' : 'c.'.$sort_column;
$data_sql   = "SELECT c.id, c.commodity_name, c.variety, c.hs_code, c.units, c.commodity_alias, c.country, c.image_url, c.created_at, cat.name as category_name
               FROM commodities c
               LEFT JOIN commodity_categories cat ON c.category_id = cat.id
               $where
               ORDER BY $order_col $sort_direction
               LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$limit, $offset]);
$data_types  = $types . "ii";

$data_stmt = $con->prepare($data_sql);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$commodities_data = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Showing X – Y ────────────────────────────────────────────
$showing_from = $filtered_records > 0 ? $offset + 1                          : 0;
$showing_to   = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;

// Categories & Countries for dropdowns
$categories = [];
$cat_result = $con->query("SELECT id, name FROM commodity_categories ORDER BY name ASC");
if ($cat_result) while ($r = $cat_result->fetch_assoc()) $categories[] = $r;

$countries = [];
$ctry_result = $con->query("SELECT country_name FROM countries ORDER BY country_name ASC");
if ($ctry_result) while ($r = $ctry_result->fetch_assoc()) $countries[] = $r['country_name'];
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
.image-preview{width:32px;height:32px;object-fit:cover;border-radius:4px;cursor:pointer}
.dynamic-group{display:flex;gap:10px;margin-bottom:10px;align-items:flex-end}
.dynamic-group>div{flex:1}
.dynamic-group label{font-size:.65rem;margin-bottom:2px;display:block;color:#666}
.dynamic-group input,.dynamic-group select{width:100%;padding:.4rem .5rem;font-size:.75rem;border:1px solid #e5e7eb;border-radius:.375rem}
.remove-row-btn{padding:.3rem .5rem;background:#fee2e2;color:#dc2626;border:none;border-radius:.375rem;cursor:pointer;margin-bottom:.2rem;flex-shrink:0}
.remove-row-btn:hover{background:#fecaca}
.add-more-btn{padding:.4rem .8rem;background:#e0e7ff;color:#3730a3;border:none;border-radius:.375rem;font-size:.7rem;cursor:pointer;margin-top:.5rem;display:inline-flex;align-items:center;gap:4px}
.add-more-btn:hover{background:#c7d2fe}
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
                <a href="?export_all=1" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export All CSV
                </a>
                <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">upload_file</span>Import CSV
                </button>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">add_circle</span>Add Commodity
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Messages: CRUD operations -->
    <?php if (!empty($message)): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type=='success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $message_type=='success' ? 'check_circle' : 'error' ?></span>
        <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- Messages: Import results -->
    <?php if (!empty($import_message)): ?>
    <?php
        $imp_bg  = $import_type === 'success' ? 'bg-green-100 text-green-800 border-green-500'
                 : ($import_type === 'warning'  ? 'bg-amber-50  text-amber-800 border-amber-500'
                                                 : 'bg-red-100  text-red-800   border-red-500');
        $imp_icon= $import_type === 'success' ? 'check_circle'
                 : ($import_type === 'warning'  ? 'warning' : 'error');
    ?>
    <div class="mb-4 rounded-lg border-l-4 <?= $imp_bg ?> text-sm overflow-hidden">
        <div class="flex items-center gap-2 p-3">
            <span class="material-symbols-outlined text-base"><?= $imp_icon ?></span>
            <span class="font-medium"><?= $import_message ?></span>
        </div>
        <?php if (!empty($import_stats)): ?>
        <details class="px-4 pb-3">
            <summary class="cursor-pointer text-xs font-medium opacity-70 hover:opacity-100">
                Show <?= count($import_stats) ?> detail(s)
            </summary>
            <ul class="mt-2 space-y-0.5 text-xs opacity-80 list-disc list-inside max-h-40 overflow-y-auto">
                <?php foreach ($import_stats as $detail): ?>
                    <li><?= htmlspecialchars($detail) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Commodities</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_commodities) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">eco</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-amber-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Cereals</p><p class="text-xl font-bold text-gray-800"><?= number_format($cereals_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-amber-400/60">grain</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Pulses</p><p class="text-xl font-bold text-gray-800"><?= number_format($pulses_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">grass</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Oil Seeds</p><p class="text-xl font-bold text-gray-800"><?= number_format($oil_seeds_count) ?></p></div>
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

    <!-- Table -->
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
                    <?php foreach ($commodities_data as $c): ?>
                    <tr class="table-row-hover" data-id="<?= $c['id'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="<?= $c['id'] ?>" onchange="onCheckboxChange()">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= $c['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($c['commodity_name']) ?></td>
                        <td class="px-3 py-2"><span class="bg-gray-100 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($c['category_name'] ?? '—') ?></span></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($c['variety'] ?: '—') ?></td>
                        <td class="px-3 py-2 text-xs font-mono text-gray-600"><?= htmlspecialchars($c['hs_code'] ?: '—') ?></td>
                        <td class="px-3 py-2">
                            <?php if (!empty($c['image_url'])): ?>
                                <img src="../base/<?= htmlspecialchars($c['image_url']) ?>" class="image-preview"
                                    onclick="showImageModal('<?= htmlspecialchars($c['image_url']) ?>','<?= htmlspecialchars($c['commodity_name']) ?>')">
                            <?php else: ?>
                                <span class="text-gray-400 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editCommodity(<?= $c['id'] ?>)" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $c['id'] ?>,'<?= htmlspecialchars(addslashes($c['commodity_name'])) ?>')" class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
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

        <!-- ===================== PAGINATION ===================== -->
        <div class="border-t border-gray-200 px-4 py-3 bg-white">
            <div class="flex flex-wrap justify-between items-center gap-3">

                <!-- Record count -->
                <div class="text-xs text-gray-500">
                    <?php if ($filtered_records === 0): ?>
                        No commodities found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> commodities
                        <?php if ($search_commodity || $search_category): ?>
                            <span class="ml-1 text-maroon">(filtered)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Rows per page + page buttons -->
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
                    <nav class="flex items-center gap-1" aria-label="Pagination">

                        <!-- First -->
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page<=1?'disabled':'' ?> title="First page">
                            <span class="material-symbols-outlined text-sm">first_page</span>
                        </button>

                        <!-- Prev -->
                        <button class="pagination-btn" onclick="goToPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?> title="Previous page">
                            <span class="material-symbols-outlined text-sm">chevron_left</span>
                        </button>

                        <!-- Numbered pages (window of 5 centred on current) -->
                        <?php
                        $win   = 2;                                        // pages each side of current
                        $sp    = max(1,          $page - $win);
                        $ep    = min($total_pages, $page + $win);
                        // Extend window if near edges so we always show 5 numbers
                        if ($sp === 1)           $ep = min($total_pages, 1 + $win * 2);
                        if ($ep === $total_pages) $sp = max(1, $total_pages - $win * 2);

                        if ($sp > 1): ?>
                            <button class="pagination-btn" onclick="goToPage(1)">1</button>
                            <?php if ($sp > 2): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $sp; $i <= $ep; $i++): ?>
                            <button class="pagination-btn <?= $i===$page ? 'active-page' : '' ?>"
                                    <?= $i===$page ? '' : "onclick=\"goToPage($i)\"" ?>>
                                <?= $i ?>
                            </button>
                        <?php endfor; ?>

                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                        <?php endif; ?>

                        <!-- Next -->
                        <button class="pagination-btn" onclick="goToPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?> title="Next page">
                            <span class="material-symbols-outlined text-sm">chevron_right</span>
                        </button>

                        <!-- Last -->
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?> title="Last page">
                            <span class="material-symbols-outlined text-sm">last_page</span>
                        </button>

                    </nav>
                    <?php endif; ?>

                    <!-- Quick jump -->
                    <?php if ($total_pages > 5): ?>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-gray-500">Go:</span>
                        <input type="number" id="pageJumpInput" min="1" max="<?= $total_pages ?>"
                               class="w-14 px-2 py-1 text-xs border border-gray-200 rounded text-center focus:outline-none focus:border-maroon"
                               placeholder="<?= $page ?>">
                        <button onclick="jumpToPage()" class="px-2 py-1 text-xs bg-gray-100 border border-gray-200 rounded hover:bg-gray-200">Go</button>
                    </div>
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

<!-- ===================== ADD/EDIT MODAL ===================== -->
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
                            <input type="text" name="commodity_name" id="commodityName" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Category <span class="text-red-500">*</span></label>
                            <select name="category" id="categoryId" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Variety</label>
                            <input type="text" name="variety" id="variety" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">HS Code</label>
                            <input type="text" name="hs_code" id="hsCode" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">Packaging &amp; Units</label>
                        <div id="packagingContainer"></div>
                        <button type="button" class="add-more-btn" onclick="addPackagingRow()">
                            <span class="material-symbols-outlined text-sm">add</span>Add Packaging
                        </button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">Aliases &amp; Countries</label>
                        <div id="aliasContainer"></div>
                        <button type="button" class="add-more-btn" onclick="addAliasRow()">
                            <span class="material-symbols-outlined text-sm">add</span>Add Alias
                        </button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Commodity Image</label>
                        <input type="file" name="commodity_image" id="commodityImage" accept="image/*" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg">
                        <div id="imagePreview" class="mt-2 hidden">
                            <img id="previewImg" class="h-16 w-16 object-cover rounded-lg">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('commodityModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_commodity" id="submitBtn" class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Commodity</button>
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
                    <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
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

<!-- ===================== IMPORT MODAL ===================== -->
<div id="importModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-xl">

            <!-- Header -->
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">upload_file</span>
                    Bulk Import Commodities (CSV)
                </h3>
                <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <div class="p-5">
                <!-- Instructions -->
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-r-lg p-4 mb-5 text-sm">
                    <p class="font-semibold text-blue-800 mb-2">CSV Column Order</p>
                    <ol class="list-decimal list-inside text-blue-700 space-y-0.5 text-xs">
                        <li><strong>HS Code</strong> — required (e.g. <code>1001.10</code>)</li>
                        <li><strong>Category</strong> — required; created automatically if new</li>
                        <li><strong>Commodity Name</strong> — required</li>
                        <li><strong>Variety</strong> — optional</li>
                        <li><strong>Packaging &amp; Units</strong> — optional, comma-separated (e.g. <code>25 Kg, 50 Kg</code>)</li>
                        <li><strong>Aliases &amp; Countries</strong> — optional, comma-separated pairs (e.g. <code>Maize:Kenya, Corn:USA</code>)</li>
                    </ol>
                    <a href="?download_template=1"
                       class="inline-flex items-center gap-1 mt-3 text-xs text-blue-700 font-medium hover:underline">
                        <span class="material-symbols-outlined text-sm">download</span>
                        Download example template CSV
                    </a>
                </div>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">Select CSV File <span class="text-red-500">*</span></label>
                        <input type="file" name="csv_file" id="importCsvFile" accept=".csv" required
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                        <p id="importFileInfo" class="mt-1 text-xs text-gray-400 hidden"></p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none mb-5">
                        <input type="checkbox" name="overwrite_existing" id="overwriteExisting"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Overwrite existing commodities with matching HS Code &amp; Name</span>
                    </label>

                    <!-- Preview info -->
                    <div id="importPreviewInfo" class="hidden mb-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-600">
                        <span class="material-symbols-outlined text-sm align-middle text-blue-500">info</span>
                        File selected — click <strong>Import</strong> to proceed.
                        Rows with critical errors will be skipped; the rest will be committed.
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('importModal')"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="import_csv"
                                class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">upload</span>Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
// ── PHP → JS ─────────────────────────────────────────────────
const categories    = <?= json_encode($categories) ?>;
const countriesList = <?= json_encode($countries) ?>;
// Pagination state from PHP (single source of truth)
const PHP = {
    page:       <?= $page ?>,
    limit:      <?= $limit ?>,
    totalPages: <?= $total_pages ?>,
    sort:       <?= json_encode($sort_column) ?>,
    dir:        <?= json_encode(strtolower($sort_direction)) ?>,
    searchCom:  <?= json_encode($search_commodity) ?>,
    searchCat:  <?= json_encode($search_category) ?>,
};

// ── Utilities ─────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// ── Import modal ──────────────────────────────────────────────
function openImportModal() {
    // Reset form state
    document.getElementById('importForm').reset();
    document.getElementById('importFileInfo').classList.add('hidden');
    document.getElementById('importPreviewInfo').classList.add('hidden');
    openModal('importModal');
}

// ── URL builder — uses PHP as base, overrides only what's passed ──
function buildUrl(overrides) {
    // Start from PHP values so nothing gets lost
    const p = {
        page:             PHP.page,
        limit:            PHP.limit,
        sort:             PHP.sort,
        dir:              PHP.dir,
        search_commodity: PHP.searchCom,
        search_category:  PHP.searchCat,
    };
    // Apply runtime overrides (e.g. current input values)
    p.search_commodity = document.getElementById('searchCommodity').value.trim();
    p.search_category  = document.getElementById('searchCategory').value.trim();
    p.limit            = document.getElementById('rowsPerPage').value;

    Object.assign(p, overrides);  // caller overrides take final priority

    const q = new URLSearchParams();
    // Always include page and limit; only include others if non-empty
    q.set('page',  p.page);
    q.set('limit', p.limit);
    if (p.sort)             q.set('sort', p.sort);
    if (p.dir)              q.set('dir',  p.dir);
    if (p.search_commodity) q.set('search_commodity', p.search_commodity);
    if (p.search_category)  q.set('search_category',  p.search_category);
    return '?' + q.toString();
}

// ── Navigation functions ──────────────────────────────────────
function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (isNaN(pg) || pg < 1 || pg > PHP.totalPages) return;
    window.location.href = buildUrl({ page: pg });
}
function changeRowsPerPage() {
    window.location.href = buildUrl({ page: 1 });
}
function applyFilters() {
    window.location.href = buildUrl({ page: 1 });
}
function sortTable(column) {
    const newDir = (PHP.sort === column && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: column, dir: newDir });
}
function jumpToPage() {
    const val = parseInt(document.getElementById('pageJumpInput').value, 10);
    if (!isNaN(val)) goToPage(val);
}

// ── Dynamic row builders ──────────────────────────────────────
function packagingRowHtml(size='', unit='') {
    const units = ['Kg','Tons','g','lb'];
    return `<div class="dynamic-group">
        <div><label>Size</label><input type="text" name="packaging[]" placeholder="e.g. 10" value="${escapeHtml(size)}"></div>
        <div><label>Unit</label><select name="unit[]">
            <option value="">Select</option>
            ${units.map(u=>`<option value="${u}"${u===unit?' selected':''}>${u}</option>`).join('')}
        </select></div>
        <button type="button" class="remove-row-btn" onclick="removeRow(this)">✕</button>
    </div>`;
}
function aliasRowHtml(alias='', country='') {
    const opts = countriesList.map(c=>`<option value="${escapeHtml(c)}"${c===country?' selected':''}>${escapeHtml(c)}</option>`).join('');
    return `<div class="dynamic-group">
        <div><label>Alias</label><input type="text" name="commodity_alias[]" placeholder="e.g. Maize" value="${escapeHtml(alias)}"></div>
        <div><label>Country</label><select name="country[]"><option value="">Select Country</option>${opts}</select></div>
        <button type="button" class="remove-row-btn" onclick="removeRow(this)">✕</button>
    </div>`;
}
function addPackagingRow() { document.getElementById('packagingContainer').insertAdjacentHTML('beforeend', packagingRowHtml()); }
function addAliasRow()     { document.getElementById('aliasContainer').insertAdjacentHTML('beforeend', aliasRowHtml()); }
function removeRow(btn) {
    const wrap = btn.closest('.dynamic-group');
    if (wrap.parentElement.children.length > 1) wrap.remove();
    else alert('You must keep at least one entry.');
}

// ── Add modal ─────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Commodity';
    document.getElementById('commodityId').value   = '';
    document.getElementById('commodityName').value = '';
    document.getElementById('categoryId').value    = '';
    document.getElementById('variety').value       = '';
    document.getElementById('hsCode').value        = '';
    document.getElementById('packagingContainer').innerHTML = packagingRowHtml();
    document.getElementById('aliasContainer').innerHTML     = aliasRowHtml();
    document.getElementById('commodityImage').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
    document.getElementById('submitBtn').name        = 'add_commodity';
    document.getElementById('submitBtn').textContent = 'Add Commodity';
    openModal('commodityModal');
}

// ── Edit modal ────────────────────────────────────────────────
function editCommodity(id) {
    fetch(`${window.location.pathname}?get_commodity=${id}`)
        .then(res => { if (!res.ok) throw new Error(`HTTP ${res.status}`); return res.json(); })
        .then(data => {
            document.getElementById('modalTitle').textContent  = 'Edit Commodity';
            document.getElementById('commodityId').value      = data.id;
            document.getElementById('commodityName').value    = data.commodity_name || '';
            document.getElementById('categoryId').value       = data.category_id || '';
            document.getElementById('variety').value          = data.variety || '';
            document.getElementById('hsCode').value           = data.hs_code || '';

            const pc = document.getElementById('packagingContainer');
            pc.innerHTML = '';
            (data.units && data.units.length ? data.units : [{}])
                .forEach(u => pc.insertAdjacentHTML('beforeend', packagingRowHtml(u.size||'', u.unit||'')));

            const ac = document.getElementById('aliasContainer');
            ac.innerHTML = '';
            const len = Math.max((data.commodity_alias||[]).length, (data.country||[]).length, 1);
            for (let i = 0; i < len; i++) {
                ac.insertAdjacentHTML('beforeend',
                    aliasRowHtml(data.commodity_alias?.[i]||'', data.country?.[i]||''));
            }

            document.getElementById('commodityImage').value  = '';
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('submitBtn').name        = 'edit_commodity';
            document.getElementById('submitBtn').textContent = 'Update Commodity';
            openModal('commodityModal');
        })
        .catch(err => { console.error(err); alert('Failed to load commodity data.'); });
}

// ── Delete ────────────────────────────────────────────────────
function deleteSingle(id, name) {
    document.getElementById('deleteModalText').innerHTML =
        `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML =
        `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}
function showImageModal(url, name) {
    document.getElementById('modalImage').src = '../base/' + url;
    document.getElementById('modalImage').alt = name;
    openModal('imageModal');
}

// ── Checkbox state ────────────────────────────────────────────
function onCheckboxChange() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const total   = document.querySelectorAll('.row-checkbox').length;
    const selAll  = document.getElementById('selectAllCheckbox');
    const delBtn  = document.getElementById('bulkDeleteBtn');
    document.getElementById('selectedCount').textContent = checked;
    delBtn.disabled = checked === 0;
    selAll.checked       = checked > 0 && checked === total;
    selAll.indeterminate = checked > 0 && checked < total;
}

// ── Image preview ─────────────────────────────────────────────
document.getElementById('commodityImage').addEventListener('change', function() {
    if (this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').classList.remove('hidden');
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        document.getElementById('imagePreview').classList.add('hidden');
    }
});

// ── DOMContentLoaded wiring ───────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Select-all
    document.getElementById('selectAllCheckbox').addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        onCheckboxChange();
    });

    // Clear selections
    document.getElementById('clearSelectionsBtn').addEventListener('click', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
        document.getElementById('selectAllCheckbox').indeterminate = false;
        onCheckboxChange();
    });

    // Bulk delete
    document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
        const ids = [...document.querySelectorAll('.row-checkbox:checked')].map(cb => cb.value);
        if (!ids.length) return;
        document.getElementById('deleteModalText').innerHTML =
            `Are you sure you want to delete <strong>${ids.length}</strong> selected commodity(ies)?`;
        document.getElementById('deleteIdsContainer').innerHTML =
            ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
        openModal('deleteModal');
    });

    // Sortable headers
    document.querySelectorAll('.sortable').forEach(th =>
        th.addEventListener('click', () => sortTable(th.dataset.sort))
    );

    // Import file selection preview
    const importFile = document.getElementById('importCsvFile');
    if (importFile) {
        importFile.addEventListener('change', function() {
            const infoEl    = document.getElementById('importFileInfo');
            const previewEl = document.getElementById('importPreviewInfo');
            if (this.files[0]) {
                const size = (this.files[0].size / 1024).toFixed(1);
                infoEl.textContent = `Selected: ${this.files[0].name} (${size} KB)`;
                infoEl.classList.remove('hidden');
                previewEl.classList.remove('hidden');
            } else {
                infoEl.classList.add('hidden');
                previewEl.classList.add('hidden');
            }
        });
    }

    // Enter key on search inputs
    ['searchCommodity','searchCategory'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') applyFilters();
        });
    });

    // Enter key on page-jump input
    const jumpInput = document.getElementById('pageJumpInput');
    if (jumpInput) jumpInput.addEventListener('keydown', e => { if (e.key === 'Enter') jumpToPage(); });

    onCheckboxChange();

    // Auto-open import modal if import just ran with errors (so user sees feedback in context)
    <?php if (!empty($import_message) && $import_type === 'error'): ?>
    // Import had critical errors — modal stays closed; banner at top shows result.
    <?php endif; ?>
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>