<?php
session_start();

// ============================================================
// API HANDLER - Must be at very top before ANY output
// ============================================================
if (isset($_GET['get_tradepoint']) && is_numeric($_GET['get_tradepoint'])) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_tradepoint'];
    $type = $_GET['type'] ?? '';

    if ($type == 'Markets') {
        $stmt = $con->prepare("SELECT id, market_name as name, category, type as market_type, country, county_district as region, longitude, latitude, radius, currency, primary_commodity, additional_datasource, 'Markets' as tradepoint_type FROM markets WHERE id = ?");
    } elseif ($type == 'Border Points') {
        $stmt = $con->prepare("SELECT id, name, country, county as region, longitude, latitude, radius, 'Border Points' as tradepoint_type FROM border_points WHERE id = ?");
    } elseif ($type == 'Millers') {
        $stmt = $con->prepare("SELECT id, miller_name as name, country, county_district as region, currency, miller as miller_details, 'Millers' as tradepoint_type FROM miller_details WHERE id = ?");
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tradepoint type']);
        exit;
    }

    $stmt->bind_param("i", $get_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Tradepoint not found']);
    }
    $stmt->close();
    $con->close();
    exit;
}

// ============================================================
// EXPORT HANDLER
// ============================================================
if (isset($_GET['export_all'])) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tradepoints_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $export_sql = "SELECT * FROM (
        SELECT id, market_name as name, 'Markets' as type, country, county_district as region, created_at FROM markets
        UNION ALL
        SELECT id, name, 'Border Points' as type, country, county as region, created_at FROM border_points
        UNION ALL
        SELECT id, miller_name as name, 'Millers' as type, country, county_district as region, created_at FROM miller_details
    ) as combined ORDER BY name ASC";

    $export_result = $con->query($export_sql);
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Name', 'Type', 'Country', 'Region', 'Date Added']);

    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['type'],
            $row['country'],
            $row['region'],
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    $con->close();
    exit;
}

// ============================================================
// BULK IMPORT HANDLER
// ============================================================
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    if (file_exists('includes/config.php')) {
        include 'includes/config.php';
    } elseif (file_exists('../admin/includes/config.php')) {
        include '../admin/includes/config.php';
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $tradepoint_type = $_POST['tradepoint_type'];
    $overwrite = isset($_POST['overwrite_existing']);

    fgetcsv($handle); // Skip header row

    $successCount = 0;
    $errorCount   = 0;
    $errors       = [];

    $con->begin_transaction();

    try {
        $rowNumber = 1;

        while (($data = fgetcsv($handle, 1000, ","))) {
            $rowNumber++;
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) continue;

            switch ($tradepoint_type) {

                // ---- MARKETS ----
                case 'Markets':
                    $required = [0=>'Market Name',1=>'Category',2=>'Type',3=>'Country',4=>'County/District',5=>'Longitude',6=>'Latitude',7=>'Radius',8=>'Currency'];
                    $skip = false;
                    foreach ($required as $idx => $label) {
                        if (empty(trim($data[$idx] ?? ''))) {
                            $errors[] = "Row $rowNumber: $label is required";
                            $errorCount++;
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;

                    $market_name          = trim($data[0]);
                    $category             = trim($data[1]);
                    $type                 = trim($data[2]);
                    $country              = trim($data[3]);
                    $county_district      = trim($data[4]);
                    $longitude            = floatval(trim($data[5]));
                    $latitude             = floatval(trim($data[6]));
                    $radius               = floatval(trim($data[7]));
                    $currency             = trim($data[8]);
                    $primary_commodities  = isset($data[9])  ? trim($data[9])  : '';
                    $additional_datasource= isset($data[10]) ? trim($data[10]) : '';
                    $created_at           = date('Y-m-d H:i:s');

                    $check_stmt = $con->prepare("SELECT id FROM markets WHERE market_name = ?");
                    $check_stmt->bind_param('s', $market_name);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();

                    if ($exists) {
                        if ($overwrite) {
                            $s = $con->prepare("UPDATE markets SET category=?,type=?,country=?,county_district=?,longitude=?,latitude=?,radius=?,currency=?,primary_commodity=?,additional_datasource=?,created_at=? WHERE market_name=?");
                            $s->bind_param('ssssdddsssss', $category,$type,$country,$county_district,$longitude,$latitude,$radius,$currency,$primary_commodities,$additional_datasource,$created_at,$market_name);
                            if ($s->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Update failed - ".$s->error; $errorCount++; }
                            $s->close();
                        } else {
                            $errors[] = "Row $rowNumber: Market '$market_name' already exists (use overwrite to update)";
                            $errorCount++;
                        }
                    } else {
                        $s = $con->prepare("INSERT INTO markets (market_name,category,type,country,county_district,longitude,latitude,radius,currency,primary_commodity,additional_datasource,tradepoint,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'Markets',?)");
                        $s->bind_param('sssssdddssss', $market_name,$category,$type,$country,$county_district,$longitude,$latitude,$radius,$currency,$primary_commodities,$additional_datasource,$created_at);
                        if ($s->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Insert failed - ".$s->error; $errorCount++; }
                        $s->close();
                    }
                    break;

                // ---- MILLERS ----
                case 'Millers':
                    $required = [0=>'Miller Name',1=>'Country',2=>'County/District'];
                    $skip = false;
                    foreach ($required as $idx => $label) {
                        if (empty(trim($data[$idx] ?? ''))) {
                            $errors[] = "Row $rowNumber: $label is required";
                            $errorCount++;
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;

                    $miller_name     = trim($data[0]);
                    $country         = trim($data[1]);
                    $county_district = trim($data[2]);
                    $millers_csv     = isset($data[3]) ? trim($data[3]) : '';
                    $millers_array   = !empty($millers_csv) ? array_map('trim', explode(',', $millers_csv)) : [];
                    if (count($millers_array) > 2) {
                        $errors[] = "Row $rowNumber: Maximum 2 millers allowed (".count($millers_array)." given)";
                        $errorCount++;
                        continue;
                    }
                    $millers_json = json_encode($millers_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $currency_map = ['Kenya'=>'KES','Uganda'=>'UGX','Tanzania'=>'TZS','Rwanda'=>'RWF','Burundi'=>'BIF','South Sudan'=>'SSP','Ethiopia'=>'ETB','Somalia'=>'SOS','Democratic Republic of Congo'=>'CDF','DRC'=>'CDF'];
                    $currency  = $currency_map[$country] ?? 'USD';
                    $created_at = date('Y-m-d H:i:s');

                    $check_stmt = $con->prepare("SELECT id FROM miller_details WHERE miller_name = ?");
                    $check_stmt->bind_param('s', $miller_name);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();

                    if ($exists) {
                        if ($overwrite) {
                            $s = $con->prepare("UPDATE miller_details SET country=?,county_district=?,miller=?,currency=?,created_at=? WHERE miller_name=?");
                            $s->bind_param('ssssss', $country,$county_district,$millers_json,$currency,$created_at,$miller_name);
                            if ($s->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Update failed - ".$s->error; $errorCount++; }
                            $s->close();
                        } else {
                            $errors[] = "Row $rowNumber: Miller '$miller_name' already exists (use overwrite to update)";
                            $errorCount++;
                        }
                    } else {
                        $s = $con->prepare("INSERT INTO miller_details (miller_name,country,county_district,miller,currency,tradepoint,created_at) VALUES (?,?,?,?,?,'Millers',?)");
                        $s->bind_param('ssssss', $miller_name,$country,$county_district,$millers_json,$currency,$created_at);
                        if ($s->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Insert failed - ".$s->error; $errorCount++; }
                        $s->close();
                    }
                    break;

                // ---- BORDER POINTS ----
                case 'Border Points':
                    $required = [0=>'Name',1=>'Country',2=>'County',3=>'Longitude',4=>'Latitude'];
                    $skip = false;
                    foreach ($required as $idx => $label) {
                        if (empty(trim($data[$idx] ?? ''))) {
                            $errors[] = "Row $rowNumber: $label is required";
                            $errorCount++;
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;

                    $name       = trim($data[0]);
                    $country    = trim($data[1]);
                    $county     = trim($data[2]);
                    $longitude  = floatval(trim($data[3]));
                    $latitude   = floatval(trim($data[4]));
                    $created_at = date('Y-m-d H:i:s');

                    $check_stmt = $con->prepare("SELECT id FROM border_points WHERE name = ?");
                    $check_stmt->bind_param('s', $name);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();

                    if ($exists) {
                        if ($overwrite) {
                            $s = $con->prepare("UPDATE border_points SET country=?,county=?,longitude=?,latitude=?,created_at=? WHERE name=?");
                            $s->bind_param('ssddss', $country,$county,$longitude,$latitude,$created_at,$name);
                            if ($s->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Update failed - ".$s->error; $errorCount++; }
                            $s->close();
                        } else {
                            $errors[] = "Row $rowNumber: Border Point '$name' already exists (use overwrite to update)";
                            $errorCount++;
                        }
                    } else {
                        $s = $con->prepare("INSERT INTO border_points (name,country,county,longitude,latitude,tradepoint,created_at) VALUES (?,?,?,?,?,'Border Points',?)");
                        $s->bind_param('sssdds', $name,$country,$county,$longitude,$latitude,$created_at);
                        if ($s->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Insert failed - ".$s->error; $errorCount++; }
                        $s->close();
                    }
                    break;
            }
        }

        $criticalErrors = count(array_filter($errors, fn($e) => strpos($e, 'Warning') === false));

        if ($criticalErrors === 0) {
            $con->commit();
            $import_message = "Successfully imported $successCount tradepoint(s)." . (!empty($errors) ? " Warnings: " . implode('<br>', $errors) : "");
            $import_status  = 'success';
        } else {
            $con->rollback();
            $import_message = "Import rolled back due to $criticalErrors error(s). Errors:<br>" . implode('<br>', $errors);
            $import_status  = 'error';
        }
    } catch (Exception $e) {
        $con->rollback();
        $import_message = "Import failed: " . $e->getMessage();
        $import_status  = 'error';
    }

    fclose($handle);
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

// Pick up the flash message left behind by addtradepoint_process.php after its redirect
if (isset($_SESSION['flash_message'])) {
    $message      = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if (!isset($_SESSION['selected_tradepoints'])) {
    $_SESSION['selected_tradepoints'] = [];
}

// AJAX selection handler
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id         = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';

    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_tradepoints'])) {
            $_SESSION['selected_tradepoints'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_tradepoints']);
        if ($key !== false) {
            unset($_SESSION['selected_tradepoints'][$key]);
            $_SESSION['selected_tradepoints'] = array_values($_SESSION['selected_tradepoints']);
        }
    }
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_tradepoints'] = [];
    }
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_tradepoints'])]);
    exit;
}

// Handle Edit via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_tradepoint'])) {
    $id      = $_POST['tradepoint_id'];
    $type    = $_POST['tradepoint_type_edit'];
    $name    = trim($_POST['name']);
    $country = trim($_POST['country']);
    $region  = trim($_POST['region']);

    if ($type == 'Markets') {
        $category             = trim($_POST['category']);
        $market_type          = trim($_POST['market_type']);
        $longitude            = $_POST['longitude'];
        $latitude             = $_POST['latitude'];
        $radius               = $_POST['radius'];
        $currency             = trim($_POST['currency']);
        $primary_commodity    = trim($_POST['primary_commodity']);
        $additional_datasource= trim($_POST['additional_datasource']);

        $stmt = $con->prepare("UPDATE markets SET market_name=?,category=?,type=?,country=?,county_district=?,longitude=?,latitude=?,radius=?,currency=?,primary_commodity=?,additional_datasource=? WHERE id=?");
        $stmt->bind_param("sssssdddsssi", $name,$category,$market_type,$country,$region,$longitude,$latitude,$radius,$currency,$primary_commodity,$additional_datasource,$id);
    } elseif ($type == 'Border Points') {
        $longitude = $_POST['longitude'];
        $latitude  = $_POST['latitude'];
        $radius    = $_POST['radius'];

        $stmt = $con->prepare("UPDATE border_points SET name=?,country=?,county=?,longitude=?,latitude=?,radius=? WHERE id=?");
        $stmt->bind_param("sssdddi", $name,$country,$region,$longitude,$latitude,$radius,$id);
    } elseif ($type == 'Millers') {
        $currency       = trim($_POST['currency']);
        $miller_details = trim($_POST['miller_details']);

        $stmt = $con->prepare("UPDATE miller_details SET miller_name=?,country=?,county_district=?,currency=?,miller=? WHERE id=?");
        $stmt->bind_param("sssssi", $name,$country,$region,$currency,$miller_details,$id);
    }

    if (isset($stmt) && $stmt->execute()) {
        $message      = "Tradepoint updated successfully!";
        $message_type = "success";
    } elseif (isset($stmt)) {
        $message      = "Error updating: " . $stmt->error;
        $message_type = "error";
    }
    if (isset($stmt)) $stmt->close();
}

// Handle Delete
if (isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    $selected_ids  = array_map('intval', (array)$_POST['selected_ids']);
    $deleted_count = 0;

    foreach ($selected_ids as $delete_id) {
        foreach (['markets','border_points','miller_details'] as $tbl) {
            $col = ($tbl === 'miller_details') ? 'id' : 'id';
            $s   = $con->prepare("DELETE FROM $tbl WHERE id = ?");
            $s->bind_param("i", $delete_id);
            if ($s->execute() && $s->affected_rows > 0) $deleted_count++;
            $s->close();
        }
    }

    if ($deleted_count > 0) {
        $message      = "Successfully deleted $deleted_count tradepoint(s).";
        $message_type = "success";
        $_SESSION['selected_tradepoints'] = [];
    } else {
        $message      = "No tradepoints were deleted.";
        $message_type = "error";
    }
}

// ============================================================
// PAGINATION & FILTERING
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column    = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
if (!in_array($sort_column, ['id','name','type','country','region','created_at'])) $sort_column = 'name';

$search_name    = trim($_GET['search_name']    ?? '');
$search_type    = trim($_GET['search_type']    ?? '');
$search_country = trim($_GET['search_country'] ?? '');

$base_query = "
    SELECT id, market_name as name, 'Markets' as type, country, county_district as region, created_at FROM markets
    UNION ALL
    SELECT id, name, 'Border Points' as type, country, county as region, created_at FROM border_points
    UNION ALL
    SELECT id, miller_name as name, 'Millers' as type, country, county_district as region, created_at FROM miller_details
";

$where  = [];
$params = [];
$types  = "";

if (!empty($search_name)) {
    $where[]  = "name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $types   .= "s";
}
if (!empty($search_type)) {
    $where[]  = "type = ?";
    $params[] = $search_type;
    $types   .= "s";
}
if (!empty($search_country)) {
    $where[]  = "country LIKE ?";
    $params[] = '%' . $search_country . '%';
    $types   .= "s";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Total count for pagination
$count_sql  = "SELECT COUNT(*) as total FROM ($base_query) as combined $where_clause";
$count_stmt = $con->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ($filtered_records > 0) ? (int)ceil($filtered_records / $limit) : 1;
$page        = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset      = ($page - 1) * $limit;

$data_sql    = "SELECT * FROM ($base_query) as combined $where_clause ORDER BY $sort_column $sort_direction LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$limit, $offset]);
$data_types  = $types . "ii";

$data_stmt = $con->prepare($data_sql);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$tradepoints_data = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to   = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;

// Statistics
$markets_count    = $con->query("SELECT COUNT(*) as t FROM markets")->fetch_assoc()['t']        ?? 0;
$border_count     = $con->query("SELECT COUNT(*) as t FROM border_points")->fetch_assoc()['t']  ?? 0;
$millers_count    = $con->query("SELECT COUNT(*) as t FROM miller_details")->fetch_assoc()['t'] ?? 0;
$total_tradepoints = $markets_count + $border_count + $millers_count;

// Dropdowns
$countries   = [];
$ctry_result = $con->query("SELECT DISTINCT admin0_country FROM commodity_sources ORDER BY admin0_country ASC");
if ($ctry_result) while ($r = $ctry_result->fetch_assoc()) $countries[] = $r['admin0_country'];

$commodities = [];
$comm_result = $con->query("SELECT id, commodity_name, variety FROM commodities ORDER BY commodity_name ASC");
if ($comm_result) while ($r = $comm_result->fetch_assoc()) $commodities[] = $r;

$data_sources = [];
$ds_result    = $con->query("SELECT data_source_name FROM data_sources ORDER BY data_source_name ASC");
if ($ds_result) while ($r = $ds_result->fetch_assoc()) $data_sources[] = $r['data_source_name'];

$currency_map = [
    'Kenya' => 'KES', 'Uganda' => 'UGX', 'Tanzania' => 'TZS',
    'Rwanda' => 'RWF', 'Burundi' => 'BIF', 'South Sudan' => 'SSP',
    'Ethiopia' => 'ETB', 'Somalia' => 'SOS',
    'Democratic Republic of Congo' => 'CDF', 'DRC' => 'CDF',
];
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
.type-badge{display:inline-flex;align-items:center;gap:.2rem;padding:.2rem .5rem;border-radius:9999px;font-size:.65rem;font-weight:500}
.type-market{background-color:#fee2e2;color:#991b1b}
.type-border{background-color:#fef3c7;color:#92400e}
.type-miller{background-color:#d1fae5;color:#065f46}

/* Import instruction blocks */
.import-instructions-block{background-color:#f8fafc;border-left:4px solid #800000;border-radius:0 .5rem .5rem 0;padding:.75rem 1rem}
.import-instructions-block h6{color:#800000;font-size:.75rem;font-weight:600;margin:0 0 .5rem}
.import-instructions-block ol{padding-left:1.1rem;margin:0 0 .5rem;font-size:.72rem;color:#374151}
.import-instructions-block ol li{margin-bottom:.2rem}
.import-instructions-block .example-row{font-family:monospace;font-size:.68rem;color:#6b7280;word-break:break-all;margin:.5rem 0 0}
.import-instructions-block .tpl-link{display:inline-flex;align-items:center;gap:.25rem;color:#800000;font-size:.72rem;font-weight:500;text-decoration:none;margin-top:.5rem}
.import-instructions-block .tpl-link:hover{text-decoration:underline}

/* Commodity picker */
.commodity-tag{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .6rem;border-radius:9999px;font-size:.7rem;font-weight:500;background-color:#800000;color:#fff}
.commodity-tag button{background:none;border:none;color:#fff;font-weight:bold;cursor:pointer;line-height:1;padding:0;margin-left:.15rem}
.commodity-tag button:hover{color:#fecaca}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Flash messages -->
    <?php if (isset($import_message)): ?>
    <div class="mb-4 p-3 rounded-lg flex items-start gap-2 text-sm <?= $import_status === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base mt-0.5"><?= $import_status === 'success' ? 'check_circle' : 'error' ?></span>
        <span><?= $import_message ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $message_type === 'success' ? 'check_circle' : 'error' ?></span>
        <span class="font-medium"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Tradepoints Management</h1>
                <p class="text-gray-600 text-sm mt-1">Manage markets, border points, and millers</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_all=1" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span> Export All CSV
                </a>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">add_circle</span> Add Tradepoint
                </button>
                <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">upload_file</span> Import CSV
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Tradepoints</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_tradepoints) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">location_on</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Markets</p><p class="text-xl font-bold text-gray-800"><?= number_format($markets_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-red-500/50">storefront</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Border Points</p><p class="text-xl font-bold text-gray-800"><?= number_format($border_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-yellow-500/50">border_all</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Millers</p><p class="text-xl font-bold text-gray-800"><?= number_format($millers_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-600/50">factory</span>
            </div>
        </div>
    </div>

    <!-- Search & bulk actions -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                    <input type="text" id="searchName" placeholder="Search by name…"
                           class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                           value="<?= htmlspecialchars($search_name) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">category</span>
                    <select id="searchType" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none bg-white">
                        <option value="">All Types</option>
                        <option value="Markets"      <?= $search_type === 'Markets'       ? 'selected' : '' ?>>Markets</option>
                        <option value="Border Points"<?= $search_type === 'Border Points' ? 'selected' : '' ?>>Border Points</option>
                        <option value="Millers"      <?= $search_type === 'Millers'       ? 'selected' : '' ?>>Millers</option>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">flag</span>
                    <input type="text" id="searchCountry" placeholder="Search by country…"
                           class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                           value="<?= htmlspecialchars($search_country) ?>">
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
                        <th class="w-8 px-3 py-2 text-left"><input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300"></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">
                            ID<?php if ($sort_column === 'id') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="name">
                            Name<?php if ($sort_column === 'name') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="type">
                            Type<?php if ($sort_column === 'type') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="country">
                            Country<?php if ($sort_column === 'country') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="region">
                            Region<?php if ($sort_column === 'region') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="created_at">
                            Date Added<?php if ($sort_column === 'created_at') echo '<span class="sort-icon">'.($sort_direction === 'ASC' ? '↑' : '↓').'</span>'; ?>
                        </th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="tableBody">
                <?php if (empty($tradepoints_data)): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">location_off</span>
                            <p class="text-sm mt-1">No tradepoints found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tradepoints_data as $tp):
                        $badgeClass = $tp['type'] === 'Markets' ? 'type-market' : ($tp['type'] === 'Border Points' ? 'type-border' : 'type-miller');
                    ?>
                    <tr class="table-row-hover" data-id="<?= $tp['id'] ?>" data-type="<?= $tp['type'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="<?= $tp['id'] ?>" onchange="onCheckboxChange()">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= $tp['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($tp['name']) ?></td>
                        <td class="px-3 py-2"><span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($tp['type']) ?></span></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($tp['country']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($tp['region']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= date('M d, Y', strtotime($tp['created_at'])) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editTradepoint(<?= $tp['id'] ?>, '<?= $tp['type'] ?>')"
                                        class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $tp['id'] ?>,'<?= htmlspecialchars(addslashes($tp['name'])) ?>')"
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

                <!-- Record count -->
                <div class="text-xs text-gray-500">
                    <?php if ($filtered_records === 0): ?>
                        No tradepoints found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> tradepoints
                        <?php if ($search_name || $search_type || $search_country): ?>
                            <span class="ml-1 text-maroon">(filtered)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3 flex-wrap">

                    <!-- Rows per page -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">Rows:</label>
                        <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                            <?php foreach ([10, 20, 50, 100] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Page buttons -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center gap-1">
                        <!-- First / Prev -->
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page <= 1 ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">first_page</span>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">chevron_left</span>
                        </button>

                        <?php
                        // Window of ±2 around current page, anchored so we always show 5 pages when possible
                        $win = 2;
                        $sp  = max(1, $page - $win);
                        $ep  = min($total_pages, $page + $win);

                        // Expand the window if we're near the start or end
                        if ($sp === 1) $ep = min($total_pages, 1 + $win * 2);
                        if ($ep === $total_pages) $sp = max(1, $total_pages - $win * 2);

                        // Leading ellipsis block
                        if ($sp > 1): ?>
                            <button class="pagination-btn" onclick="goToPage(1)">1</button>
                            <?php if ($sp > 2): ?>
                                <span class="text-gray-400 text-xs px-1">…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $sp; $i <= $ep; $i++): ?>
                            <button class="pagination-btn <?= $i === $page ? 'active-page' : '' ?>"
                                    <?= $i === $page ? '' : "onclick=\"goToPage($i)\"" ?>>
                                <?= $i ?>
                            </button>
                        <?php endfor; ?>

                        <!-- Trailing ellipsis block -->
                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?>
                                <span class="text-gray-400 text-xs px-1">…</span>
                            <?php endif; ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                        <?php endif; ?>

                        <!-- Next / Last -->
                        <button class="pagination-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">chevron_right</span>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined text-sm">last_page</span>
                        </button>
                    </nav>
                    <?php endif; ?>

                    <!-- Jump-to-page (only when there are many pages) -->
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

                <a href="../base/landing_page.php"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                    <span class="material-symbols-outlined text-base">arrow_back</span>Back
                </a>
            </div>
        </div>
    </div><!-- /table card -->

</div><!-- /max-w-7xl -->
</div><!-- /auth-bg-gradient -->


<!-- ===================== ADD MODAL ===================== -->
<div id="addModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0 z-10">
                <h3 class="text-base font-semibold text-white">Add New Tradepoint</h3>
                <button onclick="closeModal('addModal')" class="text-white/80 hover:text-white"><span class="material-symbols-outlined text-base">close</span></button>
            </div>
            <div class="p-5">
                <!-- Step indicators -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex-1 text-center"><div id="step1Indicator" class="w-8 h-8 mx-auto rounded-full bg-maroon text-white flex items-center justify-center text-sm font-bold">1</div><span class="text-xs text-gray-500 mt-1 block">Select Type</span></div>
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <div class="flex-1 text-center"><div id="step2Indicator" class="w-8 h-8 mx-auto rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-sm font-bold">2</div><span class="text-xs text-gray-500 mt-1 block">Basic Info</span></div>
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <div class="flex-1 text-center"><div id="step3Indicator" class="w-8 h-8 mx-auto rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-sm font-bold">3</div><span class="text-xs text-gray-500 mt-1 block">Complete</span></div>
                </div>

                <form id="addTradepointForm" method="POST" action="addtradepoint_process.php" enctype="multipart/form-data">

                    <!-- Step 1 -->
                    <div id="step1" class="step-content">
                        <div class="text-center py-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Tradepoint Type</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-2xl mx-auto">
                                <div class="border rounded-lg p-4 cursor-pointer hover:border-maroon hover:shadow-md transition-all" onclick="selectType('Markets')">
                                    <div class="text-center"><span class="material-symbols-outlined text-4xl text-maroon">storefront</span><h4 class="font-semibold mt-2">Market</h4><p class="text-xs text-gray-500">Agricultural market location</p></div>
                                </div>
                                <div class="border rounded-lg p-4 cursor-pointer hover:border-maroon hover:shadow-md transition-all" onclick="selectType('Border Points')">
                                    <div class="text-center"><span class="material-symbols-outlined text-4xl text-maroon">border_all</span><h4 class="font-semibold mt-2">Border Point</h4><p class="text-xs text-gray-500">Border crossing location</p></div>
                                </div>
                                <div class="border rounded-lg p-4 cursor-pointer hover:border-maroon hover:shadow-md transition-all" onclick="selectType('Millers')">
                                    <div class="text-center"><span class="material-symbols-outlined text-4xl text-maroon">factory</span><h4 class="font-semibold mt-2">Miller</h4><p class="text-xs text-gray-500">Milling facility location</p></div>
                                </div>
                            </div>
                            <input type="hidden" id="selectedType" name="tradepoint_type" value="">
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div id="step2" class="step-content" style="display:none;">
                        <div id="marketForm" class="tradepoint-form" style="display:none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div><label class="block text-xs text-gray-600 mb-1">Market Name <span class="text-red-500">*</span></label><input type="text" name="market_name" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Category <span class="text-red-500">*</span></label><select name="category" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><option>Regional</option><option>Wholesale</option><option>Retail</option><option>Terminal</option></select></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Market Type <span class="text-red-500">*</span></label><select name="type" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><option>Primary</option><option>Secondary</option><option>Assembly</option><option>Terminal</option></select></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label><select name="country" class="country-select w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select Country</option><?php foreach ($countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                                <div><label class="block text-xs text-gray-600 mb-1">County/District <span class="text-red-500">*</span></label><input type="text" name="county_district" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Longitude <span class="text-red-500">*</span></label><input type="number" step="any" name="longitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Latitude <span class="text-red-500">*</span></label><input type="number" step="any" name="latitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Radius (km) <span class="text-red-500">*</span></label><input type="number" name="radius" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            </div>
                        </div>
                        <div id="borderForm" class="tradepoint-form" style="display:none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div><label class="block text-xs text-gray-600 mb-1">Border Name <span class="text-red-500">*</span></label><input type="text" name="border_name" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label><select name="border_country" class="country-select w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select Country</option><?php foreach ($countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                                <div><label class="block text-xs text-gray-600 mb-1">County <span class="text-red-500">*</span></label><input type="text" name="border_county" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Longitude <span class="text-red-500">*</span></label><input type="number" step="any" name="border_longitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Latitude <span class="text-red-500">*</span></label><input type="number" step="any" name="border_latitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Radius (m) <span class="text-red-500">*</span></label><input type="number" name="border_radius" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            </div>
                        </div>
                        <div id="millerForm" class="tradepoint-form" style="display:none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div><label class="block text-xs text-gray-600 mb-1">Town Name <span class="text-red-500">*</span></label><input type="text" name="miller_name" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label><select name="miller_country" id="millerCountrySelect" class="country-select w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select Country</option><?php foreach ($countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                                <div><label class="block text-xs text-gray-600 mb-1">County/District <span class="text-red-500">*</span></label><input type="text" name="miller_county_district" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                                <div><label class="block text-xs text-gray-600 mb-1">Currency</label><div id="currencyDisplay" class="w-full px-3 py-2 text-sm bg-gray-100 border border-gray-200 rounded-lg text-gray-500">Select country first</div><input type="hidden" name="miller_currency" id="millerCurrencyHidden"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div id="step3" class="step-content" style="display:none;">
                        <div id="marketStep3" class="step3-form" style="display:none;">
                            <div class="mb-4">
                                <label class="block text-xs text-gray-600 mb-1">Primary Commodities <span class="text-red-500">*</span></label>
                                <input type="text" id="commoditySearch" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg mb-2" placeholder="Search commodities…">
                                <select id="commoditySelect" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white">
                                    <option value="">Select a commodity to add…</option>
                                    <?php foreach ($commodities as $c):
                                        $display = $c['commodity_name'];
                                        if (!empty($c['variety'])) $display .= ' (' . $c['variety'] . ')';
                                    ?>
                                    <option value="<?= htmlspecialchars($display) ?>"><?= htmlspecialchars($display) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="commodityTags" class="flex flex-wrap gap-2 mt-2 p-2 border border-gray-100 rounded-lg min-h-[40px]">
                                    <small class="text-gray-400">Selected commodities will appear here</small>
                                </div>
                                <input type="hidden" name="primary_commodity" id="primaryCommodityHidden">
                            </div>
                            <div class="mb-4"><label class="block text-xs text-gray-600 mb-1">Data Source</label><select name="additional_datasource" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><?php foreach ($data_sources as $ds): ?><option><?= htmlspecialchars($ds) ?></option><?php endforeach; ?></select></div>
                            <div class="mb-4"><label class="block text-xs text-gray-600 mb-1">Market Images</label><input type="file" name="marketImages[]" multiple accept="image/*" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                        </div>
                        <div id="borderStep3" class="step3-form" style="display:none;">
                            <div class="mb-4"><label class="block text-xs text-gray-600 mb-1">Border Images</label><input type="file" name="borderImages[]" multiple accept="image/*" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                        </div>
                        <div id="millerStep3" class="step3-form" style="display:none;">
                            <div class="mb-4"><label class="block text-xs text-gray-600 mb-1">Select Millers (max 2)</label><div id="millersList" class="border rounded-lg p-3 max-h-40 overflow-y-auto text-sm text-gray-400">No millers available</div><input type="hidden" name="selected_millers" id="selectedMillers"></div>
                        </div>
                    </div>

                    <div class="flex justify-between gap-2 pt-3 border-t border-gray-100 mt-4">
                        <button type="button" id="prevBtn" onclick="prevStep()" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50" style="display:none;">Previous</button>
                        <div class="flex gap-2 ml-auto">
                            <button type="button" id="nextBtn" onclick="nextStep()" class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Next</button>
                            <button type="submit" id="submitBtn" name="add_tradepoint" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700" style="display:none;">Add Tradepoint</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- ===================== EDIT MODAL ===================== -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0 z-10">
                <h3 id="editModalTitle" class="text-base font-semibold text-white">Edit Tradepoint</h3>
                <button onclick="closeModal('editModal')" class="text-white/80 hover:text-white"><span class="material-symbols-outlined text-base">close</span></button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="tradepoint_id" id="editId">
                    <input type="hidden" name="tradepoint_type_edit" id="editType">
                    <input type="hidden" name="edit_tradepoint" value="1">

                    <!-- Markets fields -->
                    <div id="editMarketFields" style="display:none;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-xs text-gray-600 mb-1">Market Name</label><input type="text" name="name" id="editMarketName" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Category</label><select name="category" id="editCategory" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><option>Regional</option><option>Wholesale</option><option>Retail</option><option>Terminal</option></select></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Market Type</label><select name="market_type" id="editMarketType" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><option>Primary</option><option>Secondary</option><option>Assembly</option><option>Terminal</option></select></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Country</label><select name="country" id="editMarketCountry" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><?php foreach ($countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Region</label><input type="text" name="region" id="editMarketRegion" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Longitude</label><input type="number" step="any" name="longitude" id="editMarketLongitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Latitude</label><input type="number" step="any" name="latitude" id="editMarketLatitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Radius</label><input type="number" step="any" name="radius" id="editMarketRadius" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Currency</label><select name="currency" id="editMarketCurrency" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><option>KES</option><option>UGX</option><option>TZS</option><option>RWF</option><option>BIF</option><option>SSP</option><option>ETB</option><option>SOS</option><option>CDF</option></select></div>
                            <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Primary Commodities</label><input type="text" name="primary_commodity" id="editMarketCommodities" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Data Source</label><input type="text" name="additional_datasource" id="editMarketDataSource" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                        </div>
                    </div>

                    <!-- Border Points fields -->
                    <div id="editBorderFields" style="display:none;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-xs text-gray-600 mb-1">Border Name</label><input type="text" name="name" id="editBorderName" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Country</label><select name="country" id="editBorderCountry" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><?php foreach ($countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                            <div><label class="block text-xs text-gray-600 mb-1">County</label><input type="text" name="region" id="editBorderCounty" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Longitude</label><input type="number" step="any" name="longitude" id="editBorderLongitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Latitude</label><input type="number" step="any" name="latitude" id="editBorderLatitude" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Radius</label><input type="number" name="radius" id="editBorderRadius" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                        </div>
                    </div>

                    <!-- Millers fields -->
                    <div id="editMillerFields" style="display:none;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-xs text-gray-600 mb-1">Miller Name</label><input type="text" name="name" id="editMillerName" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Country</label><select name="country" id="editMillerCountry" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><?php foreach ($countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
                            <div><label class="block text-xs text-gray-600 mb-1">County/District</label><input type="text" name="region" id="editMillerRegion" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></div>
                            <div><label class="block text-xs text-gray-600 mb-1">Currency</label><select name="currency" id="editMillerCurrency" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"><option value="">Select</option><option>KES</option><option>UGX</option><option>TZS</option><option>RWF</option><option>BIF</option><option>SSP</option><option>ETB</option><option>SOS</option><option>CDF</option></select></div>
                            <div class="md:col-span-2"><label class="block text-xs text-gray-600 mb-1">Miller Details (JSON or text)</label><textarea name="miller_details" id="editMillerDetails" rows="3" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg"></textarea></div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('editModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Update Tradepoint</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- ===================== IMPORT MODAL ===================== -->
<div id="importModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center sticky top-0 z-10">
                <h3 class="text-base font-semibold text-white">Import Tradepoints</h3>
                <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">

                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-r-lg p-3 mb-4 text-sm text-blue-700">
                    <span class="material-symbols-outlined text-sm align-middle">info</span>
                    Select the tradepoint type first to see the required CSV format, then upload your file.
                </div>

                <form method="POST" action="" enctype="multipart/form-data">

                    <!-- Type selector -->
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tradepoint Type <span class="text-red-500">*</span></label>
                        <select name="tradepoint_type" id="importType"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 bg-white" required>
                            <option value="">-- Select Type --</option>
                            <option value="Markets">Markets</option>
                            <option value="Border Points">Border Points</option>
                            <option value="Millers">Millers</option>
                        </select>
                    </div>

                    <!-- Per-type instruction blocks -->
                    <div id="importInstructionsWrap" style="display:none;" class="mb-4">

                        <div id="importMarketsInstructions" class="import-type-instructions import-instructions-block" style="display:none;">
                            <h6>Markets — required CSV columns (in order)</h6>
                            <ol>
                                <li><strong>market_name</strong> (required) — name of the market</li>
                                <li><strong>category</strong> (required) — e.g. Regional, Wholesale, Retail, Terminal</li>
                                <li><strong>type</strong> (required) — e.g. Primary, Secondary, Assembly</li>
                                <li><strong>country</strong> (required) — country name</li>
                                <li><strong>county_district</strong> (required) — county or district</li>
                                <li><strong>longitude</strong> (required) — geographic coordinate</li>
                                <li><strong>latitude</strong> (required) — geographic coordinate</li>
                                <li><strong>radius</strong> (required) — coverage radius in km</li>
                                <li><strong>currency</strong> (required) — e.g. KES, UGX, TZS</li>
                                <li><strong>primary_commodities</strong> (optional) — comma-separated list</li>
                                <li><strong>additional_datasource</strong> (optional) — data source info</li>
                            </ol>
                            <p class="example-row">Example: "Nairobi Market","Urban","Retail","Kenya","Nairobi",36.82,-1.29,5,"KES","Maize,Beans","Government"</p>
                            <a href="downloads/markets_template.csv" class="tpl-link">
                                <span class="material-symbols-outlined text-sm">download</span> Download Markets Template
                            </a>
                        </div>

                        <div id="importBorderInstructions" class="import-type-instructions import-instructions-block" style="display:none;">
                            <h6>Border Points — required CSV columns (in order)</h6>
                            <ol>
                                <li><strong>name</strong> (required) — name of the border point</li>
                                <li><strong>country</strong> (required) — country name</li>
                                <li><strong>county</strong> (required) — county name</li>
                                <li><strong>longitude</strong> (required) — geographic coordinate</li>
                                <li><strong>latitude</strong> (required) — geographic coordinate</li>
                            </ol>
                            <p class="example-row">Example: "Namanga Border","Kenya","Kajiado",36.78,-2.55</p>
                            <a href="downloads/border_points_template.csv" class="tpl-link">
                                <span class="material-symbols-outlined text-sm">download</span> Download Border Points Template
                            </a>
                        </div>

                        <div id="importMillersInstructions" class="import-type-instructions import-instructions-block" style="display:none;">
                            <h6>Millers — required CSV columns (in order)</h6>
                            <ol>
                                <li><strong>miller_name</strong> (required) — name of the milling company</li>
                                <li><strong>country</strong> (required) — country name (currency is auto-detected)</li>
                                <li><strong>county_district</strong> (required) — county or district</li>
                                <li><strong>millers</strong> (optional) — comma-separated miller brands, max 2</li>
                            </ol>
                            <p class="example-row">Example: "Unga Group","Kenya","Nairobi","Unga Millers,Capwell Millers"</p>
                            <a href="downloads/millers_template.csv" class="tpl-link">
                                <span class="material-symbols-outlined text-sm">download</span> Download Millers Template
                            </a>
                        </div>

                    </div><!-- /importInstructionsWrap -->

                    <!-- File upload -->
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-600 mb-1">CSV File <span class="text-red-500">*</span></label>
                        <input type="file" name="csv_file" accept=".csv"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none" required>
                    </div>

                    <!-- Overwrite toggle -->
                    <div class="mb-5">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="overwrite_existing" class="rounded border-gray-300 text-maroon">
                            Overwrite existing records with matching names
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('importModal')"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" name="import_csv"
                                class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000] inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-base">upload_file</span> Import CSV
                        </button>
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
            <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure you want to delete this tradepoint?</p>
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
// ---------------------------------------------------------------
// PHP → JS bridge
// ---------------------------------------------------------------
const PHP = {
    page:        <?= $page ?>,
    limit:       <?= $limit ?>,
    totalPages:  <?= $total_pages ?>,
    sort:        <?= json_encode($sort_column) ?>,
    dir:         <?= json_encode(strtolower($sort_direction)) ?>,
    searchName:  <?= json_encode($search_name) ?>,
    searchType:  <?= json_encode($search_type) ?>,
    searchCountry: <?= json_encode($search_country) ?>
};

const CURRENCY_MAP = <?= json_encode($currency_map) ?>;

// ---------------------------------------------------------------
// URL helpers
// ---------------------------------------------------------------
function buildUrl(overrides) {
    const p = {
        page:           PHP.page,
        limit:          PHP.limit,
        sort:           PHP.sort,
        dir:            PHP.dir,
        search_name:    document.getElementById('searchName').value.trim(),
        search_type:    document.getElementById('searchType').value,
        search_country: document.getElementById('searchCountry').value.trim()
    };
    Object.assign(p, overrides);

    const q = new URLSearchParams();
    q.set('page',  p.page);
    q.set('limit', p.limit);
    if (p.sort)          q.set('sort',           p.sort);
    if (p.dir)           q.set('dir',            p.dir);
    if (p.search_name)   q.set('search_name',    p.search_name);
    if (p.search_type)   q.set('search_type',    p.search_type);
    if (p.search_country)q.set('search_country', p.search_country);
    return '?' + q.toString();
}

// ---------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------
function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (!isNaN(pg) && pg >= 1 && pg <= PHP.totalPages) {
        window.location.href = buildUrl({ page: pg });
    }
}

// FIX: read the new value from the select element itself
function changeRowsPerPage() {
    const newLimit = document.getElementById('rowsPerPage').value;
    window.location.href = buildUrl({ page: 1, limit: newLimit });
}

function jumpToPage() {
    const val = parseInt(document.getElementById('pageJumpInput')?.value, 10);
    if (!isNaN(val)) goToPage(val);
}

// ---------------------------------------------------------------
// Filtering & sorting
// ---------------------------------------------------------------
function applyFilters() {
    window.location.href = buildUrl({ page: 1 });
}

function sortTable(column) {
    const newDir = (PHP.sort === column && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: column, dir: newDir });
}

// ---------------------------------------------------------------
// Modal helpers
// ---------------------------------------------------------------
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// ---------------------------------------------------------------
// Add modal (3-step)
// ---------------------------------------------------------------
let currentStep            = 1;
let selectedTradepointType = '';

function openAddModal() {
    currentStep            = 1;
    selectedTradepointType = '';
    document.getElementById('selectedType').value = '';
    showStep(1);
    openModal('addModal');
}

function openImportModal() {
    openModal('importModal');
}

function selectType(type) {
    selectedTradepointType = type;
    document.getElementById('selectedType').value = type;

    document.querySelectorAll('.tradepoint-form').forEach(f => f.style.display = 'none');
    document.querySelectorAll('.step3-form').forEach(f => f.style.display = 'none');

    if (type === 'Markets') {
        document.getElementById('marketForm').style.display  = 'block';
        document.getElementById('marketStep3').style.display = 'block';
    } else if (type === 'Border Points') {
        document.getElementById('borderForm').style.display  = 'block';
        document.getElementById('borderStep3').style.display = 'block';
    } else if (type === 'Millers') {
        document.getElementById('millerForm').style.display  = 'block';
        document.getElementById('millerStep3').style.display = 'block';
    }
    nextStep();
}

function showStep(step) {
    document.getElementById('step1').style.display = step === 1 ? 'block' : 'none';
    document.getElementById('step2').style.display = step === 2 ? 'block' : 'none';
    document.getElementById('step3').style.display = step === 3 ? 'block' : 'none';
    document.getElementById('prevBtn').style.display   = step > 1 ? 'inline-flex' : 'none';
    document.getElementById('nextBtn').style.display   = step < 3 ? 'inline-flex' : 'none';
    document.getElementById('submitBtn').style.display = step === 3 ? 'inline-flex' : 'none';

    [1, 2, 3].forEach(n => {
        const el = document.getElementById('step' + n + 'Indicator');
        if (n <= step) {
            el.classList.remove('bg-gray-200', 'text-gray-500');
            el.classList.add('bg-maroon', 'text-white');
        } else {
            el.classList.remove('bg-maroon', 'text-white');
            el.classList.add('bg-gray-200', 'text-gray-500');
        }
    });
}

function nextStep() {
    if (currentStep === 1 && !selectedTradepointType) {
        alert('Please select a tradepoint type first.');
        return;
    }
    if (currentStep < 3) { currentStep++; showStep(currentStep); }
}

function prevStep() {
    if (currentStep > 1) { currentStep--; showStep(currentStep); }
}

// ---------------------------------------------------------------
// Edit modal
// ---------------------------------------------------------------
function editTradepoint(id, type) {
    fetch(`${window.location.pathname}?get_tradepoint=${id}&type=${encodeURIComponent(type)}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('editId').value   = data.id;
            document.getElementById('editType').value = type;
            document.getElementById('editModalTitle').textContent = 'Edit ' + type;

            document.getElementById('editMarketFields').style.display = 'none';
            document.getElementById('editBorderFields').style.display = 'none';
            document.getElementById('editMillerFields').style.display = 'none';

            if (type === 'Markets') {
                document.getElementById('editMarketFields').style.display   = 'block';
                document.getElementById('editMarketName').value             = data.name                  || '';
                document.getElementById('editCategory').value               = data.category              || '';
                document.getElementById('editMarketType').value             = data.market_type           || '';
                document.getElementById('editMarketCountry').value          = data.country               || '';
                document.getElementById('editMarketRegion').value           = data.region                || '';
                document.getElementById('editMarketLongitude').value        = data.longitude             || '';
                document.getElementById('editMarketLatitude').value         = data.latitude              || '';
                document.getElementById('editMarketRadius').value           = data.radius                || '';
                document.getElementById('editMarketCurrency').value         = data.currency              || '';
                document.getElementById('editMarketCommodities').value      = data.primary_commodity     || '';
                document.getElementById('editMarketDataSource').value       = data.additional_datasource || '';
            } else if (type === 'Border Points') {
                document.getElementById('editBorderFields').style.display   = 'block';
                document.getElementById('editBorderName').value             = data.name      || '';
                document.getElementById('editBorderCountry').value          = data.country   || '';
                document.getElementById('editBorderCounty').value           = data.region    || '';
                document.getElementById('editBorderLongitude').value        = data.longitude || '';
                document.getElementById('editBorderLatitude').value         = data.latitude  || '';
                document.getElementById('editBorderRadius').value           = data.radius    || '';
            } else if (type === 'Millers') {
                document.getElementById('editMillerFields').style.display   = 'block';
                document.getElementById('editMillerName').value             = data.name           || '';
                document.getElementById('editMillerCountry').value          = data.country        || '';
                document.getElementById('editMillerRegion').value           = data.region         || '';
                document.getElementById('editMillerCurrency').value         = data.currency       || '';
                document.getElementById('editMillerDetails').value          = data.miller_details || '';
            }
            openModal('editModal');
        })
        .catch(err => {
            console.error(err);
            alert('Failed to load tradepoint data. Please try again.');
        });
}

// ---------------------------------------------------------------
// Delete helpers
// ---------------------------------------------------------------
function deleteSingle(id, name) {
    document.getElementById('deleteModalText').innerHTML =
        `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML =
        `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}

// ---------------------------------------------------------------
// Checkbox / bulk actions
// ---------------------------------------------------------------
function onCheckboxChange() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const total   = document.querySelectorAll('.row-checkbox').length;
    const selAll  = document.getElementById('selectAllCheckbox');
    const delBtn  = document.getElementById('bulkDeleteBtn');

    document.getElementById('selectedCount').textContent = checked;
    delBtn.disabled          = checked === 0;
    selAll.checked           = checked > 0 && checked === total;
    selAll.indeterminate     = checked > 0 && checked < total;
}

// ---------------------------------------------------------------
// Utility
// ---------------------------------------------------------------
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => (
        {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
}

// ---------------------------------------------------------------
// Commodity multi-picker (Add Market → Step 3)
// ---------------------------------------------------------------
(function() {
    const select   = document.getElementById('commoditySelect');
    const search   = document.getElementById('commoditySearch');
    const tagsWrap = document.getElementById('commodityTags');
    const hidden   = document.getElementById('primaryCommodityHidden');
    if (!select || !search || !tagsWrap || !hidden) return;

    let selected = [];
    const allOptions = Array.from(select.options).filter(o => o.value);

    function renderTags() {
        hidden.value = selected.join(',');
        if (selected.length === 0) {
            tagsWrap.innerHTML = '<small class="text-gray-400">Selected commodities will appear here</small>';
            return;
        }
        tagsWrap.innerHTML = '';
        selected.forEach(name => {
            const tag = document.createElement('span');
            tag.className = 'commodity-tag';
            tag.innerHTML = `${escapeHtml(name)} <button type="button" title="Remove">&times;</button>`;
            tag.querySelector('button').addEventListener('click', () => {
                selected = selected.filter(s => s !== name);
                renderTags();
            });
            tagsWrap.appendChild(tag);
        });
    }

    search.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        select.innerHTML = '<option value="">Select a commodity to add…</option>';
        allOptions
            .filter(o => o.value.toLowerCase().includes(term))
            .forEach(o => select.appendChild(o.cloneNode(true)));
    });

    select.addEventListener('change', function() {
        const val = this.value;
        if (val && !selected.includes(val)) {
            selected.push(val);
            renderTags();
        }
        this.selectedIndex = 0;
    });

    // Reset picker whenever the Add modal is reopened
    document.querySelector('button[onclick="openAddModal()"]')?.addEventListener('click', function() {
        selected = [];
        renderTags();
        search.value = '';
    });
})();

// ---------------------------------------------------------------
// DOMContentLoaded — wire up all static event listeners
// ---------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

    // --- Import type selector → show/hide instruction blocks ---
    document.getElementById('importType').addEventListener('change', function () {
        const type = this.value;
        const wrap = document.getElementById('importInstructionsWrap');

        // Hide all blocks first
        document.querySelectorAll('.import-type-instructions').forEach(el => el.style.display = 'none');

        if (type === 'Markets') {
            wrap.style.display = 'block';
            document.getElementById('importMarketsInstructions').style.display = 'block';
        } else if (type === 'Border Points') {
            wrap.style.display = 'block';
            document.getElementById('importBorderInstructions').style.display = 'block';
        } else if (type === 'Millers') {
            wrap.style.display = 'block';
            document.getElementById('importMillersInstructions').style.display = 'block';
        } else {
            wrap.style.display = 'none';
        }
    });

    // Re-open import modal on import error (mirrors old code behaviour)
    <?php if (isset($import_status) && $import_status === 'error'): ?>
        openModal('importModal');
    <?php endif; ?>

    // --- Miller country → auto-fill currency ---
    const millerCountrySelect = document.getElementById('millerCountrySelect');
    if (millerCountrySelect) {
        millerCountrySelect.addEventListener('change', function () {
            const country      = this.value;
            const display      = document.getElementById('currencyDisplay');
            const hiddenInput  = document.getElementById('millerCurrencyHidden');
            if (country && CURRENCY_MAP[country]) {
                display.textContent    = CURRENCY_MAP[country];
                display.classList.remove('text-gray-500');
                display.classList.add('text-gray-800', 'font-medium');
                hiddenInput.value      = CURRENCY_MAP[country];
            } else {
                display.textContent    = 'Select country first';
                display.classList.add('text-gray-500');
                display.classList.remove('text-gray-800', 'font-medium');
                hiddenInput.value      = '';
            }
        });
    }

    // --- Select-all checkbox ---
    document.getElementById('selectAllCheckbox').addEventListener('change', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        onCheckboxChange();
    });

    // --- Clear selections ---
    document.getElementById('clearSelectionsBtn').addEventListener('click', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked      = false;
        document.getElementById('selectAllCheckbox').indeterminate = false;
        onCheckboxChange();
    });

    // --- Bulk delete ---
    document.getElementById('bulkDeleteBtn').addEventListener('click', function () {
        const ids = [...document.querySelectorAll('.row-checkbox:checked')].map(cb => cb.value);
        if (!ids.length) return;
        document.getElementById('deleteModalText').innerHTML =
            `Are you sure you want to delete <strong>${ids.length}</strong> selected tradepoint(s)?`;
        document.getElementById('deleteIdsContainer').innerHTML =
            ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
        openModal('deleteModal');
    });

    // --- Column sort headers ---
    document.querySelectorAll('.sortable').forEach(th =>
        th.addEventListener('click', () => sortTable(th.dataset.sort))
    );

    // --- Enter key on search inputs ---
    ['searchName', 'searchCountry'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') applyFilters();
        });
    });
    document.getElementById('searchType').addEventListener('change', () => applyFilters());

    // --- Enter key on page-jump input ---
    const jumpInput = document.getElementById('pageJumpInput');
    if (jumpInput) jumpInput.addEventListener('keydown', e => { if (e.key === 'Enter') jumpToPage(); });

    // Initialise checkbox state counters
    onCheckboxChange();
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>