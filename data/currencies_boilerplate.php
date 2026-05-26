<?php
// currencies_boilerplate.php
session_start();

// ============================================================
// API HANDLER — fetch single currency rate for edit modal
// ============================================================
if (isset($_GET['get_currency']) && is_numeric($_GET['get_currency'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $get_id   = (int)$_GET['get_currency'];
    $api_stmt = $con->prepare("SELECT id, country, currency_code, exchange_rate, effective_date FROM currencies WHERE id = ?");
    $api_stmt->bind_param("i", $get_id);
    $api_stmt->execute();
    if ($api_row = $api_stmt->get_result()->fetch_assoc()) {
        echo json_encode($api_row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
    $api_stmt->close();
    exit;
}

// ============================================================
// EXPORT ALL — full CSV download
// ============================================================
if (isset($_GET['export_all'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="currency_rates_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    $exp = $con->query("SELECT id, country, currency_code, exchange_rate, effective_date, date_created FROM currencies ORDER BY effective_date DESC");
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Country', 'Currency Code', 'Exchange Rate (to USD)', 'Effective Date', 'Date Created']);
    while ($row = $exp->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['country'],
            $row['currency_code'],
            number_format($row['exchange_rate'], 4, '.', ''),
            $row['effective_date'],
            $row['date_created'],
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// CSV TEMPLATE DOWNLOAD
// ============================================================
if (isset($_GET['download_template'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="currency_rates_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Country', 'Currency Code', 'Exchange Rate', 'Effective Date']);
    fputcsv($out, ['Kenya',    'KES', '128.50', '2025-01-15']);
    fputcsv($out, ['Uganda',   'UGX', '3720.00', '2025-01-15']);
    fputcsv($out, ['Tanzania', 'TZS', '2560.00', '2025-01-15']);
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

if (file_exists('includes/config.php')) include 'includes/config.php';
elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

// ── Country → currency mapping (shared PHP + JS) ─────────────
$eastAfricanCountries = [
    'Kenya'      => 'KES', 'Uganda'     => 'UGX', 'Tanzania'   => 'TZS',
    'Rwanda'     => 'RWF', 'Burundi'    => 'BIF', 'Ethiopia'   => 'ETB',
    'South Sudan'=> 'SSP', 'Sudan'      => 'SDG', 'Somalia'    => 'SOS',
    'Djibouti'   => 'DJF', 'Eritrea'    => 'ERN', 'DR Congo'   => 'CDF',
    'Comoros'    => 'KMF', 'Seychelles' => 'SCR', 'Mauritius'  => 'MUR',
    'Madagascar' => 'MGA', 'Malawi'     => 'MWK', 'Zambia'     => 'ZMW',
    'Mozambique' => 'MZN',
];

$message      = '';
$message_type = '';

// ============================================================
// ADD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_currency'])) {
    $country       = trim($_POST['country']);
    $currency_code = trim($_POST['currency_code']);
    $exchange_rate = (float)$_POST['exchange_rate'];
    $effective_date= trim($_POST['effective_date']);
    $date_created  = date('Y-m-d H:i:s');

    if (empty($country) || empty($currency_code) || $exchange_rate <= 0 || empty($effective_date)) {
        $message = "Please fill all required fields with valid values.";
        $message_type = "error";
    } else {
        $day   = date('d', strtotime($effective_date));
        $month = date('m', strtotime($effective_date));
        $year  = date('Y', strtotime($effective_date));
        $stmt  = $con->prepare("INSERT INTO currencies (country, currency_code, exchange_rate, effective_date, date_created, day, month, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdssiii", $country, $currency_code, $exchange_rate, $effective_date, $date_created, $day, $month, $year);
        if ($stmt->execute()) { $message = "Currency rate added successfully!"; $message_type = "success"; }
        else { $message = "Error adding rate: " . $stmt->error; $message_type = "error"; }
        $stmt->close();
    }
}

// ============================================================
// EDIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_currency'])) {
    $id            = (int)$_POST['currency_id'];
    $country       = trim($_POST['country']);
    $currency_code = trim($_POST['currency_code']);
    $exchange_rate = (float)$_POST['exchange_rate'];
    $effective_date= trim($_POST['effective_date']);
    $date_updated  = date('Y-m-d H:i:s');

    if (empty($country) || empty($currency_code) || $exchange_rate <= 0 || empty($effective_date)) {
        $message = "Please fill all required fields with valid values.";
        $message_type = "error";
    } else {
        $day   = date('d', strtotime($effective_date));
        $month = date('m', strtotime($effective_date));
        $year  = date('Y', strtotime($effective_date));
        $stmt  = $con->prepare("UPDATE currencies SET country=?, currency_code=?, exchange_rate=?, effective_date=?, date_updated=?, day=?, month=?, year=? WHERE id=?");
        $stmt->bind_param("ssdssiiit", $country, $currency_code, $exchange_rate, $effective_date, $date_updated, $day, $month, $year, $id);
        if ($stmt->execute()) { $message = "Currency rate updated successfully!"; $message_type = "success"; }
        else { $message = "Error updating rate: " . $stmt->error; $message_type = "error"; }
        $stmt->close();
    }
}

// ============================================================
// BULK DELETE
// ============================================================
if (isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM currencies WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $message = "Successfully deleted $deleted currency rate(s)."; $message_type = "success";
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
    fgetcsv($handle); // skip header
    $successCount = 0; $errorCount = 0; $errors = [];
    $con->begin_transaction();
    try {
        $rowNumber = 1;
        while (($data = fgetcsv($handle, 2000, ',')) !== false) {
            $rowNumber++;
            if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) continue;

            $country        = trim($data[0] ?? '');
            $currency_code  = trim($data[1] ?? '');
            $exchange_rate  = trim($data[2] ?? '');
            $effective_date = trim($data[3] ?? '');

            if ($country === '')       { $errors[] = "Row $rowNumber: Country required.";        $errorCount++; continue; }
            if ($currency_code === '') { $errors[] = "Row $rowNumber: Currency Code required.";   $errorCount++; continue; }
            if (!is_numeric($exchange_rate) || (float)$exchange_rate <= 0) {
                $errors[] = "Row $rowNumber: Valid Exchange Rate required."; $errorCount++; continue;
            }
            if ($effective_date === '' || !strtotime($effective_date)) {
                $errors[] = "Row $rowNumber: Valid Effective Date required."; $errorCount++; continue;
            }

            $exchange_rate  = (float)$exchange_rate;
            $effective_date = date('Y-m-d', strtotime($effective_date));
            $date_created   = date('Y-m-d H:i:s');
            $day = date('d', strtotime($effective_date));
            $month = date('m', strtotime($effective_date));
            $year  = date('Y', strtotime($effective_date));

            // Duplicate check
            $ck = $con->prepare("SELECT id FROM currencies WHERE country=? AND currency_code=? AND effective_date=?");
            $ck->bind_param('sss', $country, $currency_code, $effective_date); $ck->execute(); $ck->store_result();
            $exists = $ck->num_rows > 0; $ck->close();

            if ($exists) {
                if (isset($_POST['overwrite_existing'])) {
                    $up = $con->prepare("UPDATE currencies SET exchange_rate=?, date_created=?, day=?, month=?, year=? WHERE country=? AND currency_code=? AND effective_date=?");
                    $up->bind_param('dsiiisss', $exchange_rate, $date_created, $day, $month, $year, $country, $currency_code, $effective_date);
                    if ($up->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Update failed."; $errorCount++; }
                    $up->close();
                } else { $errors[] = "Row $rowNumber: '$country / $currency_code / $effective_date' already exists — skipped."; $errorCount++; }
                continue;
            }

            $ins = $con->prepare("INSERT INTO currencies (country, currency_code, exchange_rate, effective_date, date_created, day, month, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param('ssdssiii', $country, $currency_code, $exchange_rate, $effective_date, $date_created, $day, $month, $year);
            if ($ins->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Insert failed — " . $ins->error; $errorCount++; }
            $ins->close();
        }

        $criticalErrors = count(array_filter($errors, fn($e) => strpos($e, 'Warning') === false && strpos($e, 'skipped') === false));
        if ($criticalErrors === 0) {
            $con->commit();
            $import_type    = count($errors) > 0 ? 'warning' : 'success';
            $import_message = "Successfully imported <strong>$successCount</strong> rate(s).";
            if ($errors) $import_message .= ' <span class="opacity-70 font-normal">(' . count($errors) . ' skipped)</span>';
            $import_stats = $errors;
        } else {
            $con->rollback();
            $import_type    = 'error';
            $import_message = "Import rolled back — <strong>$criticalErrors</strong> critical error(s). <strong>$successCount</strong> row(s) would have succeeded.";
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
// STATISTICS
// ============================================================
$total_rates      = (int)($con->query("SELECT COUNT(*) as t FROM currencies")->fetch_assoc()['t'] ?? 0);
$unique_countries = (int)($con->query("SELECT COUNT(DISTINCT country) as t FROM currencies")->fetch_assoc()['t'] ?? 0);
$unique_currencies= (int)($con->query("SELECT COUNT(DISTINCT currency_code) as t FROM currencies")->fetch_assoc()['t'] ?? 0);
$latest_upd_row   = $con->query("SELECT MAX(effective_date) as latest FROM currencies")->fetch_assoc();
$latest_update    = $latest_upd_row['latest'] ? date('M j, Y', strtotime($latest_upd_row['latest'])) : 'N/A';

// ============================================================
// PAGINATION + SORTING + FILTERING
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column    = $_GET['sort'] ?? 'effective_date';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts  = ['id', 'country', 'currency_code', 'exchange_rate', 'effective_date', 'date_created'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'effective_date';

$search_country  = trim($_GET['search_country']  ?? '');
$search_currency = trim($_GET['search_currency'] ?? '');

// WHERE
$where  = "WHERE 1=1"; $params = []; $types = "";
if ($search_country  !== '') { $where .= " AND country LIKE ?";       $params[] = '%'.$search_country.'%';  $types .= "s"; }
if ($search_currency !== '') { $where .= " AND currency_code LIKE ?"; $params[] = '%'.$search_currency.'%'; $types .= "s"; }

// Count
$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM currencies $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages  = max(1, (int)ceil($filtered_records / $limit));
$page         = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset       = ($page - 1) * $limit;

// Data
$data_params = array_merge($params, [$limit, $offset]);
$data_types  = $types . "ii";
$data_stmt   = $con->prepare("SELECT id, country, currency_code, exchange_rate, effective_date, date_created FROM currencies $where ORDER BY $sort_column $sort_direction LIMIT ? OFFSET ?");
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$currencies_data = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to   = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;
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
.rate-badge{font-family:monospace;font-size:.72rem;background:#f3f4f6;padding:.15rem .45rem;border-radius:.25rem;color:#374151;font-weight:600}
.material-symbols-outlined{font-family:'Material Symbols Outlined'!important;font-style:normal;font-weight:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';font-feature-settings:'liga';-webkit-font-smoothing:antialiased}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- ── Page Header ── -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Currency Rates Management</h1>
                <p class="text-gray-600 text-sm mt-1">Manage cross-border currency exchange rates</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_all=1" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export All CSV
                </a>
                <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">upload_file</span>Import CSV
                </button>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">add_circle</span>Add Rate
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- ── CRUD message ── -->
    <?php if (!empty($message)): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type=='success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $message_type=='success' ? 'check_circle' : 'error' ?></span>
        <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Import result message ── -->
    <?php if (!empty($import_message)):
        $imp_bg   = $import_type==='success' ? 'bg-green-100 text-green-800 border-green-500' : ($import_type==='warning' ? 'bg-amber-50 text-amber-800 border-amber-500' : 'bg-red-100 text-red-800 border-red-500');
        $imp_icon = $import_type==='success' ? 'check_circle' : ($import_type==='warning' ? 'warning' : 'error');
    ?>
    <div class="mb-4 rounded-lg border-l-4 <?= $imp_bg ?> text-sm overflow-hidden">
        <div class="flex items-center gap-2 p-3">
            <span class="material-symbols-outlined text-base"><?= $imp_icon ?></span>
            <span class="font-medium"><?= $import_message ?></span>
        </div>
        <?php if (!empty($import_stats)): ?>
        <details class="px-4 pb-3">
            <summary class="cursor-pointer text-xs font-medium opacity-70 hover:opacity-100">Show <?= count($import_stats) ?> detail(s)</summary>
            <ul class="mt-2 space-y-0.5 text-xs opacity-80 list-disc list-inside max-h-40 overflow-y-auto">
                <?php foreach ($import_stats as $d): ?><li><?= htmlspecialchars($d) ?></li><?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Stat cards ── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Rates</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_rates) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">attach_money</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-amber-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Countries</p><p class="text-xl font-bold text-gray-800"><?= number_format($unique_countries) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-amber-400/60">public</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Currencies</p><p class="text-xl font-bold text-gray-800"><?= number_format($unique_currencies) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">currency_exchange</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Latest Update</p><p class="text-sm font-bold text-gray-800 leading-tight mt-0.5"><?= $latest_update ?></p></div>
                <span class="material-symbols-outlined text-3xl text-blue-400/50">calendar_today</span>
            </div>
        </div>
    </div>

    <!-- ── Search & bulk actions ── -->
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
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">currency_exchange</span>
                    <input type="text" id="searchCurrency" placeholder="Search currency code..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_currency) ?>">
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

    <!-- ── Table ── -->
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
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="country">
                            Country<?php if($sort_column=='country') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="currency_code">
                            Currency<?php if($sort_column=='currency_code') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="exchange_rate">
                            Rate (to USD)<?php if($sort_column=='exchange_rate') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="effective_date">
                            Effective Date<?php if($sort_column=='effective_date') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_created">
                            Date Added<?php if($sort_column=='date_created') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-20">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="tableBody">
                <?php if (empty($currencies_data)): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">currency_exchange</span>
                            <p class="text-sm mt-1">No currency rates found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($currencies_data as $r): ?>
                    <tr class="table-row-hover" data-id="<?= $r['id'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="<?= $r['id'] ?>" onchange="onCheckboxChange()">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= $r['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($r['country']) ?></td>
                        <td class="px-3 py-2">
                            <span class="rate-badge"><?= htmlspecialchars($r['currency_code']) ?></span>
                        </td>
                        <td class="px-3 py-2 text-xs font-mono font-semibold text-gray-700"><?= number_format($r['exchange_rate'], 4) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= date('M d, Y', strtotime($r['effective_date'])) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($r['date_created'])) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editCurrencyRate(<?= $r['id'] ?>)" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['country'])) ?> / <?= htmlspecialchars($r['currency_code']) ?>')" class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
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

        <!-- ── Pagination ── -->
        <div class="border-t border-gray-200 px-4 py-3 bg-white">
            <div class="flex flex-wrap justify-between items-center gap-3">

                <div class="text-xs text-gray-500">
                    <?php if ($filtered_records === 0): ?>
                        No rates found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> rates
                        <?php if ($search_country || $search_currency): ?>
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
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page<=1?'disabled':'' ?> title="First">
                            <span class="material-symbols-outlined text-sm">first_page</span>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?> title="Prev">
                            <span class="material-symbols-outlined text-sm">chevron_left</span>
                        </button>

                        <?php
                        $win = 2;
                        $sp  = max(1, $page - $win);
                        $ep  = min($total_pages, $page + $win);
                        if ($sp === 1)            $ep = min($total_pages, 1 + $win * 2);
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

                        <button class="pagination-btn" onclick="goToPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?> title="Next">
                            <span class="material-symbols-outlined text-sm">chevron_right</span>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?> title="Last">
                            <span class="material-symbols-outlined text-sm">last_page</span>
                        </button>
                    </nav>
                    <?php endif; ?>

                </div>

                <a href="../base/landing_page.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                    <span class="material-symbols-outlined text-base">arrow_back</span>Back
                </a>
            </div>
        </div>
    </div><!-- /table card -->

</div><!-- /max-w -->
</div><!-- /bg-gradient -->


<!-- ══════════════════════════════════════════════
     ADD / EDIT MODAL
══════════════════════════════════════════════ -->
<div id="currencyModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0 z-10 rounded-t-xl">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add Currency Rate</h3>
                <button onclick="closeModal('currencyModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="currencyForm">
                    <input type="hidden" name="currency_id" id="currencyId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label>
                            <select name="country" id="modalCountry" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="" disabled selected>Select Country</option>
                                <?php foreach ($eastAfricanCountries as $country => $code): ?>
                                    <option value="<?= htmlspecialchars($country) ?>" data-currency="<?= $code ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Currency Code <span class="text-red-500">*</span></label>
                            <input type="text" name="currency_code" id="modalCurrencyCode" readonly
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 font-mono font-semibold"
                                placeholder="Auto-filled">
                            <p id="currencyAutoNote" class="text-xs text-gray-400 mt-1">Select a country to auto-fill</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Exchange Rate (to USD) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" name="exchange_rate" id="modalRate" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                placeholder="e.g. 128.5000">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Effective Date <span class="text-red-500">*</span></label>
                            <input type="date" name="effective_date" id="modalDate" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('currencyModal')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_currency" id="submitBtn"
                            class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     DELETE CONFIRM MODAL
══════════════════════════════════════════════ -->
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

<!-- ══════════════════════════════════════════════
     IMPORT CSV MODAL
══════════════════════════════════════════════ -->
<div id="importModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">upload_file</span>
                    Bulk Import Currency Rates (CSV)
                </h3>
                <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-r-lg p-4 mb-5 text-sm">
                    <p class="font-semibold text-blue-800 mb-2">CSV Column Order</p>
                    <ol class="list-decimal list-inside text-blue-700 space-y-0.5 text-xs">
                        <li><strong>Country</strong> — required (e.g. <code>Kenya</code>)</li>
                        <li><strong>Currency Code</strong> — required (e.g. <code>KES</code>)</li>
                        <li><strong>Exchange Rate</strong> — required, numeric (e.g. <code>128.50</code>)</li>
                        <li><strong>Effective Date</strong> — required (e.g. <code>2025-01-15</code>)</li>
                    </ol>
                    <a href="?download_template=1" class="inline-flex items-center gap-1 mt-3 text-xs text-blue-700 font-medium hover:underline">
                        <span class="material-symbols-outlined text-sm">download</span>Download example template CSV
                    </a>
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">Select CSV File <span class="text-red-500">*</span></label>
                        <input type="file" name="csv_file" id="importCsvFile" accept=".csv" required
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                        <p id="importFileInfo" class="mt-1 text-xs text-gray-400 hidden"></p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none mb-5">
                        <input type="checkbox" name="overwrite_existing" class="rounded border-gray-300">
                        <span>Overwrite existing rates with matching Country + Currency Code + Date</span>
                    </label>

                    <div id="importPreviewInfo" class="hidden mb-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-600">
                        <span class="material-symbols-outlined text-sm align-middle text-blue-500">info</span>
                        File selected — click <strong>Import</strong> to proceed.
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('importModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="import_csv" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">upload</span>Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
// ── PHP → JS state ────────────────────────────────────────────
const countryCurrencyMap = <?= json_encode($eastAfricanCountries) ?>;
const PHP = {
    page:       <?= $page ?>,
    limit:      <?= $limit ?>,
    totalPages: <?= $total_pages ?>,
    sort:       <?= json_encode($sort_column) ?>,
    dir:        <?= json_encode(strtolower($sort_direction)) ?>,
    searchC:    <?= json_encode($search_country) ?>,
    searchCur:  <?= json_encode($search_currency) ?>,
};

// ── Utilities ─────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function buildUrl(overrides) {
    const p = {
        page:            PHP.page,
        limit:           PHP.limit,
        sort:            PHP.sort,
        dir:             PHP.dir,
        search_country:  document.getElementById('searchCountry').value.trim(),
        search_currency: document.getElementById('searchCurrency').value.trim(),
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);

    const q = new URLSearchParams();
    q.set('page',  p.page);
    q.set('limit', p.limit);
    if (p.sort)            q.set('sort', p.sort);
    if (p.dir)             q.set('dir',  p.dir);
    if (p.search_country)  q.set('search_country',  p.search_country);
    if (p.search_currency) q.set('search_currency', p.search_currency);
    return '?' + q.toString();
}

// ── Navigation ────────────────────────────────────────────────
function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (isNaN(pg) || pg < 1 || pg > PHP.totalPages) return;
    window.location.href = buildUrl({ page: pg });
}
function changeRowsPerPage() { window.location.href = buildUrl({ page: 1 }); }
function applyFilters()      { window.location.href = buildUrl({ page: 1 }); }
function sortTable(col) {
    const newDir = (PHP.sort === col && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: col, dir: newDir });
}
function jumpToPage() {
    const val = parseInt(document.getElementById('pageJumpInput').value, 10);
    if (!isNaN(val)) goToPage(val);
}

// ── Add modal ─────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent    = 'Add Currency Rate';
    document.getElementById('currencyId').value          = '';
    document.getElementById('modalCountry').value        = '';
    document.getElementById('modalCurrencyCode').value   = '';
    document.getElementById('modalRate').value           = '';
    document.getElementById('modalDate').value           = new Date().toISOString().split('T')[0];
    document.getElementById('currencyAutoNote').textContent = 'Select a country to auto-fill';
    document.getElementById('currencyAutoNote').className = 'text-xs text-gray-400 mt-1';
    document.getElementById('submitBtn').name        = 'add_currency';
    document.getElementById('submitBtn').textContent = 'Add Rate';
    openModal('currencyModal');
}

// ── Edit modal ────────────────────────────────────────────────
function editCurrencyRate(id) {
    fetch(`${window.location.pathname}?get_currency=${id}`)
        .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
        .then(data => {
            document.getElementById('modalTitle').textContent    = 'Edit Currency Rate';
            document.getElementById('currencyId').value          = data.id;
            document.getElementById('modalCountry').value        = data.country || '';
            document.getElementById('modalCurrencyCode').value   = data.currency_code || '';
            document.getElementById('modalRate').value           = data.exchange_rate || '';
            document.getElementById('modalDate').value           = data.effective_date || '';
            const note = document.getElementById('currencyAutoNote');
            note.textContent  = 'Currency code for ' + (data.country || '');
            note.className    = 'text-xs text-green-600 mt-1';
            document.getElementById('submitBtn').name        = 'edit_currency';
            document.getElementById('submitBtn').textContent = 'Update Rate';
            openModal('currencyModal');
        })
        .catch(err => { console.error(err); alert('Failed to load rate data.'); });
}

// ── Delete ────────────────────────────────────────────────────
function deleteSingle(id, label) {
    document.getElementById('deleteModalText').innerHTML =
        `Are you sure you want to delete <strong>${label}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML =
        `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}

// ── Checkbox state ────────────────────────────────────────────
function onCheckboxChange() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const total   = document.querySelectorAll('.row-checkbox').length;
    const selAll  = document.getElementById('selectAllCheckbox');
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkDeleteBtn').disabled = checked === 0;
    selAll.checked       = checked > 0 && checked === total;
    selAll.indeterminate = checked > 0 && checked < total;
}

// ── Import modal ──────────────────────────────────────────────
function openImportModal() {
    document.getElementById('importForm').reset();
    document.getElementById('importFileInfo').classList.add('hidden');
    document.getElementById('importPreviewInfo').classList.add('hidden');
    openModal('importModal');
}

// ── DOMContentLoaded wiring ───────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {

    // Country → currency auto-fill in modal
    document.getElementById('modalCountry').addEventListener('change', function() {
        const code = countryCurrencyMap[this.value] || '';
        const inp  = document.getElementById('modalCurrencyCode');
        const note = document.getElementById('currencyAutoNote');
        inp.value = code;
        if (code) {
            note.textContent = '✓ Auto-selected: ' + code + ' for ' + this.value;
            note.className   = 'text-xs text-green-600 mt-1';
        } else {
            note.textContent = 'No default currency found — enter manually';
            note.className   = 'text-xs text-amber-500 mt-1';
            inp.readOnly     = false;
        }
    });

    // Select-all checkbox
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
            `Are you sure you want to delete <strong>${ids.length}</strong> selected rate(s)?`;
        document.getElementById('deleteIdsContainer').innerHTML =
            ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
        openModal('deleteModal');
    });

    // Sortable headers
    document.querySelectorAll('.sortable').forEach(th =>
        th.addEventListener('click', () => sortTable(th.dataset.sort))
    );

    // Import file info preview
    const importFile = document.getElementById('importCsvFile');
    if (importFile) {
        importFile.addEventListener('change', function() {
            const infoEl    = document.getElementById('importFileInfo');
            const previewEl = document.getElementById('importPreviewInfo');
            if (this.files[0]) {
                infoEl.textContent = `Selected: ${this.files[0].name} (${(this.files[0].size/1024).toFixed(1)} KB)`;
                infoEl.classList.remove('hidden');
                previewEl.classList.remove('hidden');
            } else {
                infoEl.classList.add('hidden');
                previewEl.classList.add('hidden');
            }
        });
    }

    // Enter key on search inputs
    ['searchCountry','searchCurrency'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') applyFilters();
        });
    });

    // Enter key on page-jump
    const jumpInput = document.getElementById('pageJumpInput');
    if (jumpInput) jumpInput.addEventListener('keydown', e => { if (e.key === 'Enter') jumpToPage(); });

    onCheckboxChange();
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>