<?php
// base/marketprices_boilerplate.php

// ── JSON ENDPOINT: single price for edit modal ────────────────
if (isset($_GET['get_price']) && is_numeric($_GET['get_price'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $pid  = (int)$_GET['get_price'];
    $stmt = $con->prepare("SELECT mp.* FROM market_prices mp WHERE mp.id = ?");
    $stmt->bind_param('i', $pid); $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode($row ?: ['error' => 'Not found']); exit;
}

// ── POST: Edit price via modal ────────────────────────────────
if (isset($_POST['modal_edit_price'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    $price_id    = (int)$_POST['price_id'];
    $country     = trim($_POST['country']);
    $market_id   = (int)$_POST['market_id'];
    $category    = trim($_POST['category']);
    $commodity_id= (int)$_POST['commodity_id'];
    $weight      = trim($_POST['packaging_unit']);
    $unit        = trim($_POST['measuring_unit']);
    $variety     = trim($_POST['variety']);
    $price_type  = trim($_POST['price_type']);
    $price       = (float)$_POST['price'];
    $status      = trim($_POST['status']);
    $data_source = trim($_POST['data_source']);
    $mrow        = $con->query("SELECT market_name FROM markets WHERE id=$market_id")->fetch_assoc();
    $market_name = $mrow ? $mrow['market_name'] : '';
    $stmt = $con->prepare("UPDATE market_prices SET country_admin_0=?,market_id=?,market=?,category=?,commodity=?,weight=?,unit=?,variety=?,price_type=?,Price=?,status=?,data_source=? WHERE id=?");
    $stmt->bind_param('sississssdssi', $country,$market_id,$market_name,$category,$commodity_id,$weight,$unit,$variety,$price_type,$price,$status,$data_source,$price_id);
    if ($stmt->execute()) { $_SESSION['import_message']='Price record updated successfully.'; $_SESSION['import_status']='success'; }
    else                  { $_SESSION['import_message']='Error updating: '.$stmt->error;     $_SESSION['import_status']='danger'; }
    $stmt->close();
    header('Location: marketprices_boilerplate.php'); exit;
}

// ── POST: Add price via modal ─────────────────────────────────
if (isset($_POST['modal_add_price'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';

    function convertToUSDModal($amount, $country, $con) {
        if (!is_numeric($amount)) return 0;
        $rate = 1;
        $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country=? ORDER BY effective_date DESC,date_created DESC LIMIT 1");
        if ($stmt) { $stmt->bind_param('s',$country); $stmt->execute(); $r=$stmt->get_result(); if($r&&$r->num_rows>0){$row=$r->fetch_assoc();$rate=(float)$row['exchange_rate'];} $stmt->close(); }
        return $rate==0 ? 0 : round($amount/$rate,2);
    }

    $country       = trim($_POST['country']);
    $market_id     = (int)$_POST['market_id'];
    $category      = trim($_POST['category']);
    $commodity_id  = (int)$_POST['commodity_id'];
    $packaging_raw = trim($_POST['packaging_unit']);
    $measuring     = trim($_POST['measuring_unit']);
    $variety       = trim($_POST['variety']);
    $data_source   = trim($_POST['data_source']);
    $wholesale_in  = (float)$_POST['wholesale_price'];
    $retail_in     = (float)$_POST['retail_price'];

    $pkg_num = 1;
    if (preg_match('/^(\d+(\.\d+)?)/',$packaging_raw,$m)) $pkg_num=(float)$m[1]?:1;
    $w_usd = convertToUSDModal($pkg_num>0?($wholesale_in/$pkg_num):$wholesale_in, $country, $con);
    $r_usd = convertToUSDModal($retail_in, $country, $con);

    $mrow = $con->query("SELECT market_name FROM markets WHERE id=$market_id")->fetch_assoc();
    $market_name = $mrow ? $mrow['market_name'] : '';

    $date_posted='Y-m-d H:i:s'; $date_posted=date($date_posted);
    $day=date('d'); $month=date('m'); $year=date('Y');
    $subject='Market Prices'; $status='pending';

    $sql="INSERT INTO market_prices (category,commodity,country_admin_0,market_id,market,weight,unit,price_type,Price,subject,day,month,year,date_posted,status,variety,data_source) VALUES (?,?,?,?,?,?,?,'Wholesale',?,?,?,?,?,?,?,?,?),(?,?,?,?,?,?,?,'Retail',?,?,?,?,?,?,?,?,?)";
    $stmt=$con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sissssdsiiissssssissssdsiiisssss',
            $category,$commodity_id,$country,$market_id,$market_name,$packaging_raw,$measuring,$w_usd,$subject,$day,$month,$year,$date_posted,$status,$variety,$data_source,
            $category,$commodity_id,$country,$market_id,$market_name,$packaging_raw,$measuring,$r_usd,$subject,$day,$month,$year,$date_posted,$status,$variety,$data_source
        );
        if ($stmt->execute()) { $_SESSION['import_message']='New market price added successfully.'; $_SESSION['import_status']='success'; }
        else                  { $_SESSION['import_message']='Error adding price: '.$stmt->error;   $_SESSION['import_status']='danger'; }
        $stmt->close();
    }
    header('Location: marketprices_boilerplate.php'); exit;
}

// ─────────────────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include '../admin/includes/config.php';

// ── CSV IMPORT ────────────────────────────────────────────────
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $overwrite = isset($_POST['overwrite_existing']);
    $data_source = $_POST['data_source'] ?? 'Manual Import';
    fgetcsv($handle);
    $successCount = 0; $errorCount = 0; $errors = [];
    $con->begin_transaction();
    try {
        $rowNumber = 1;
        while (($data = fgetcsv($handle, 1000, ","))) {
            $rowNumber++;
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) continue;
            if (empty(trim($data[0]))) { $errors[] = "Row $rowNumber: Market is required"; $errorCount++; continue; }
            if (empty(trim($data[1]))) { $errors[] = "Row $rowNumber: Commodity ID is required"; $errorCount++; continue; }
            if (empty(trim($data[2]))) { $errors[] = "Row $rowNumber: Price Type is required"; $errorCount++; continue; }
            if (empty(trim($data[3]))) { $errors[] = "Row $rowNumber: Price is required"; $errorCount++; continue; }
            if (empty(trim($data[4]))) { $errors[] = "Row $rowNumber: Date is required"; $errorCount++; continue; }

            $market = trim($data[0]);
            $commodity_id = intval(trim($data[1]));
            $price_type = trim($data[2]);
            $price = floatval(trim($data[3]));
            $raw_date_string = trim($data[4]);
            error_log("Raw date string from CSV: '$raw_date_string'");
            $date_string = trim($data[4]);
            $date_posted = null;
            $date_string = preg_replace('/\s+/', ' ', $date_string);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/', $date_string, $matches)) {
                $year = $matches[1]; $month = $matches[2]; $day = $matches[3];
                $hour = $matches[4]; $minute = $matches[5]; $second = $matches[6];
                if (checkdate($month, $day, $year) && $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 && $second >= 0 && $second <= 59) {
                    $date_posted = "$year-$month-$day $hour:$minute:$second";
                }
            }
            if ($date_posted === null) {
                try {
                    $date_time = new DateTime($date_string);
                    $date_posted = $date_time->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $timestamp = strtotime($date_string);
                    if ($timestamp !== false && $timestamp > 0) { $date_posted = date('Y-m-d H:i:s', $timestamp); }
                }
            }
            if ($date_posted === null || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_posted)) {
                $errors[] = "Row $rowNumber: Invalid date format '$date_string'."; $errorCount++; continue;
            }
            $parsed_timestamp = strtotime($date_posted);
            if ($parsed_timestamp < strtotime('2020-01-01') || $parsed_timestamp > strtotime('2030-12-31')) {
                $errors[] = "Row $rowNumber: Date '$date_posted' is out of reasonable range (2020-2030)"; $errorCount++; continue;
            }
            error_log("Successfully parsed date: '$date_string' -> '$date_posted'");

            $status = isset($data[5]) ? trim($data[5]) : 'pending';
            $variety = isset($data[6]) ? trim($data[6]) : '';
            $weight = isset($data[7]) ? floatval(trim($data[7])) : 1.0;
            $unit = isset($data[8]) ? trim($data[8]) : 'kg';
            $country_admin_0 = isset($data[9]) ? trim($data[9]) : 'Kenya';
            $subject = isset($data[10]) ? trim($data[10]) : 'Market Prices';
            $supplied_volume = isset($data[11]) ? (trim($data[11]) !== '' ? intval(trim($data[11])) : null) : null;
            $comments = isset($data[12]) ? trim($data[12]) : '';
            $supply_status = isset($data[13]) ? trim($data[13]) : 'unknown';
            $category = isset($data[14]) ? trim($data[14]) : 'General';
            $commodity_sources_data = null;

            $day = date('d', strtotime($date_posted));
            $month = date('m', strtotime($date_posted));
            $year = date('Y', strtotime($date_posted));

            $valid_price_types = ['Wholesale', 'Retail'];
            if (!in_array($price_type, $valid_price_types)) { $errors[] = "Row $rowNumber: Invalid price type '$price_type'."; $errorCount++; continue; }
            $valid_statuses = ['pending', 'approved', 'published', 'unpublished'];
            if (!in_array($status, $valid_statuses)) { $errors[] = "Row $rowNumber: Invalid status '$status'"; $errorCount++; continue; }

            $market_id = 0;
            $market_name_to_search = $market;
            if (strtolower($market) === 'kangemi') { $market_name_to_search = 'Kangemi Market'; }
            elseif (strtolower($market) === 'kibuye') { $market_name_to_search = 'Kibuye Market'; }

            $market_query = "SELECT id FROM markets WHERE market_name = ? LIMIT 1";
            $market_stmt = $con->prepare($market_query);
            if (!$market_stmt) { $errors[] = "Row $rowNumber: Failed to prepare market query: " . $con->error; $errorCount++; continue; }
            $market_stmt->bind_param('s', $market_name_to_search);
            $market_stmt->execute();
            $market_result = $market_stmt->get_result();
            if ($market_result->num_rows > 0) { $market_row = $market_result->fetch_assoc(); $market_id = $market_row['id']; }
            else { $errors[] = "Row $rowNumber: Market '$market' not found"; $errorCount++; $market_stmt->close(); continue; }
            $market_stmt->close();

            error_log("Preparing to insert: market=$market, commodity=$commodity_id, date=$date_posted");

            $check_query = "SELECT id FROM market_prices WHERE market = ? AND commodity = ? AND price_type = ? AND DATE(date_posted) = DATE(?)";
            $check_stmt = $con->prepare($check_query);
            if (!$check_stmt) { $errors[] = "Row $rowNumber: Failed to prepare check query: " . $con->error; $errorCount++; continue; }
            $check_stmt->bind_param('siss', $market, $commodity_id, $price_type, $date_posted);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    $update_query = "UPDATE market_prices SET Price=?,status=?,data_source=?,variety=?,weight=?,unit=?,country_admin_0=?,subject=?,supplied_volume=?,comments=?,supply_status=?,commodity_sources_data=? WHERE market=? AND commodity=? AND price_type=? AND DATE(date_posted)=DATE(?)";
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) { $errors[] = "Row $rowNumber: Failed to prepare update: " . $con->error; $errorCount++; $check_stmt->close(); continue; }
                    $update_stmt->bind_param('dsssdsssissssiss', $price,$status,$data_source,$variety,$weight,$unit,$country_admin_0,$subject,$supplied_volume,$comments,$supply_status,$commodity_sources_data,$market,$commodity_id,$price_type,$date_posted);
                    if ($update_stmt->execute()) { $successCount++; } else { $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error; $errorCount++; }
                    $update_stmt->close();
                } else { $errors[] = "Row $rowNumber: Record already exists (use overwrite to update)"; $errorCount++; }
                $check_stmt->close(); continue;
            }
            $check_stmt->close();

            $insert_query = "INSERT INTO market_prices (category,commodity,country_admin_0,market_id,market,weight,unit,price_type,Price,subject,day,month,year,date_posted,status,variety,data_source,supplied_volume,comments,supply_status,commodity_sources_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) { $errors[] = "Row $rowNumber: Failed to prepare insert: " . $con->error; $errorCount++; continue; }
            $bind_types = ['s','i','s','i','s','d','s','s','d','s','i','i','i','s','s','s','s','i','s','s','s'];
            $bind_values = [$category,$commodity_id,$country_admin_0,$market_id,$market,$weight,$unit,$price_type,$price,$subject,$day,$month,$year,$date_posted,$status,$variety,$data_source,$supplied_volume,$comments,$supply_status,$commodity_sources_data];
            error_log("Bind types count: ".count($bind_types));
            error_log("Bind values count: ".count($bind_values));
            if (count($bind_types) !== count($bind_values)) { $errors[] = "Row $rowNumber: Parameter count mismatch"; $errorCount++; $insert_stmt->close(); continue; }
            $type_string = implode('', $bind_types);
            error_log("Type string: $type_string (length: ".strlen($type_string).")");
            $insert_stmt->bind_param($type_string, ...$bind_values);
            if ($insert_stmt->execute()) { $successCount++; error_log("Insert successful for row $rowNumber"); }
            else { $error_msg = "Row $rowNumber: Insert failed - " . $insert_stmt->error; $errors[] = $error_msg; error_log($error_msg); $errorCount++; }
            $insert_stmt->close();
        }

        if ($errorCount === 0) {
            $con->commit();
            $_SESSION['import_message'] = "Successfully imported $successCount market prices.";
            $_SESSION['import_status'] = 'success';
        } else {
            $con->rollback();
            $_SESSION['import_message'] = "Import failed with $errorCount errors. Errors: " . implode('<br>', array_slice($errors, 0, 10));
            $_SESSION['import_status'] = 'danger';
        }
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['import_message'] = "Import failed: " . $e->getMessage();
        $_SESSION['import_status'] = 'danger';
    }
    fclose($handle);
    header("Location: marketprices_boilerplate.php"); exit;

} elseif (isset($_POST['import_csv'])) {
    $_SESSION['import_message'] = "Please select a valid CSV file to import.";
    $_SESSION['import_status'] = 'danger';
    header("Location: marketprices_boilerplate.php"); exit;
}

// ── EXPORT ────────────────────────────────────────────────────
if (isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
    $export_all = isset($_POST['export_all']) && $_POST['export_all'] == 'true';
    $data = [];
    if ($export_all) {
        $sql = "SELECT p.market,c.commodity_name as commodity,p.price_type,p.Price as price,p.date_posted,p.status,p.data_source as source,p.variety FROM market_prices p LEFT JOIN commodities c ON p.commodity=c.id ORDER BY p.date_posted DESC";
        $result = $con->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) { $data[] = $row; } }
    } elseif (!empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $sql = "SELECT p.market,c.commodity_name as commodity,p.price_type,p.Price as price,p.date_posted,p.status,p.data_source as source,p.variety FROM market_prices p LEFT JOIN commodities c ON p.commodity=c.id WHERE p.id IN ($ids) ORDER BY p.date_posted DESC";
        $result = $con->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) { $data[] = $row; } }
    }
    if ($format == 'excel' || $format == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="market_prices_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Market','Commodity','Price Type','Price','Date Posted','Status','Source','Variety']);
        foreach ($data as $row) { fputcsv($output, [$row['market'],$row['commodity'],$row['price_type'],$row['price'],$row['date_posted'],$row['status'],$row['source'],$row['variety']]); }
        fclose($output); exit;
    } elseif ($format == 'pdf') { ?>
        <!DOCTYPE html><html><head><title>Market Prices Export</title><style>body{font-family:Arial}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px}th{background:#f2f2f2}</style></head>
        <body><h1>Market Prices Export</h1><p>Exported: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp; Records: <?= count($data) ?></p>
        <table><thead><tr><th>Market</th><th>Commodity</th><th>Price Type</th><th>Price ($)</th><th>Date Posted</th><th>Status</th><th>Source</th><th>Variety</th></tr></thead><tbody>
        <?php foreach ($data as $row): ?><tr><td><?= htmlspecialchars($row['market']) ?></td><td><?= htmlspecialchars($row['commodity']) ?></td><td><?= htmlspecialchars($row['price_type']) ?></td><td><?= htmlspecialchars($row['price']) ?></td><td><?= htmlspecialchars($row['date_posted']) ?></td><td><?= htmlspecialchars($row['status']) ?></td><td><?= htmlspecialchars($row['source']) ?></td><td><?= htmlspecialchars($row['variety']) ?></td></tr><?php endforeach; ?>
        </tbody></table><script>window.onload=function(){window.print();}</script></body></html>
    <?php exit; }
}

// ── PAGE SETUP ────────────────────────────────────────────────
include '../admin/includes/admin_header.php';

$import_message = null; $import_status = null;
if (isset($_SESSION['import_message'])) {
    $import_message = $_SESSION['import_message']; $import_status = $_SESSION['import_status'];
    unset($_SESSION['import_message']); unset($_SESSION['import_status']);
}

function getPricesData($con, $limit = 10, $offset = 0, $sort_col = 'date_posted', $sort_dir = 'DESC') {
    $allowed = ['market' => 'p.market', 'commodity' => 'c.commodity_name', 'date_posted' => 'p.date_posted',
                'price_type' => 'p.price_type', 'Price' => 'p.Price', 'status' => 'p.status'];
    $order_by = $allowed[$sort_col] ?? 'p.date_posted';
    $dir      = $sort_dir === 'ASC' ? 'ASC' : 'DESC';
    $sql = "SELECT p.id,p.market,p.commodity,c.commodity_name,c.variety,
                   CONCAT(c.commodity_name,IF(c.variety IS NOT NULL AND c.variety!='',CONCAT(' (',c.variety,')'),'')) AS commodity_display,
                   p.price_type,p.Price,p.date_posted,p.status,p.data_source,p.market_id,p.category,p.weight,p.unit
            FROM market_prices p
            LEFT JOIN commodities c ON p.commodity=c.id
            ORDER BY $order_by $dir, p.date_posted DESC
            LIMIT $limit OFFSET $offset";
    $result = $con->query($sql);
    $data = [];
    if ($result) { if ($result->num_rows > 0) { while ($row = $result->fetch_assoc()) { $data[] = $row; } } $result->free(); }
    else { error_log("Error fetching prices data: " . $con->error); }
    return $data;
}

function getTotalPriceRecords($con) {
    $sql = "SELECT count(*) as total FROM market_prices";
    $result = $con->query($sql);
    if ($result) { $row = $result->fetch_assoc(); return $row['total']; }
    return 0;
}

$total_records = getTotalPriceRecords($con);
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if (!in_array($limit, [10,20,50,100])) $limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$prices_data = getPricesData($con, $limit, $offset, $sort_column, $sort_direction);
$total_pages = ceil($total_records / $limit);

function getStatusBadge($status) {
    switch ($status) {
        case 'pending':    return '<span class="mp-badge mp-badge-pending">Pending</span>';
        case 'published':  return '<span class="mp-badge mp-badge-published">Published</span>';
        case 'approved':   return '<span class="mp-badge mp-badge-approved">Approved</span>';
        case 'unpublished':return '<span class="mp-badge mp-badge-unpublished">Unpublished</span>';
        default:           return '<span class="mp-badge">Unknown</span>';
    }
}

function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $sql = "SELECT Price FROM market_prices WHERE commodity=? AND market=? AND price_type=? AND DATE(date_posted)<DATE(?) ORDER BY date_posted DESC LIMIT 1";
    $stmt = $con->prepare($sql); if (!$stmt) return 'N/A';
    $stmt->bind_param('isss', $commodityId, $market, $priceType, $currentDate);
    $stmt->execute(); $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $prev = $result->fetch_assoc(); $prevPrice = $prev['Price'];
        if ($prevPrice != 0) { $change = (($currentPrice - $prevPrice) / $prevPrice) * 100; $stmt->close(); return round($change, 2) . '%'; }
    }
    $stmt->close(); return 'N/A';
}

function calculateMoMChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $thirtyDaysAgo = date('Y-m-d', strtotime($currentDate . ' -30 days'));
    $sql = "SELECT Price,ABS(DATEDIFF(DATE(date_posted),?)) as date_diff FROM market_prices WHERE commodity=? AND market=? AND price_type=? AND DATE(date_posted) BETWEEN DATE_SUB(?,INTERVAL 35 DAY) AND DATE_SUB(?,INTERVAL 25 DAY) ORDER BY date_diff ASC LIMIT 1";
    $stmt = $con->prepare($sql); if (!$stmt) return 'N/A';
    $stmt->bind_param('sissss', $thirtyDaysAgo, $commodityId, $market, $priceType, $thirtyDaysAgo, $thirtyDaysAgo);
    $stmt->execute(); $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $moData = $result->fetch_assoc(); $moPrice = $moData['Price'];
        if ($moPrice != 0) { $change = (($currentPrice - $moPrice) / $moPrice) * 100; $stmt->close(); return round($change, 2) . '%'; }
    }
    $stmt->close(); return 'N/A';
}

// ── STATS ──────────────────────────────────────────────────────
$total_prices   = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices")->fetch_assoc())['t'] ?? 0);
$pending_count  = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='pending'")->fetch_assoc())['t'] ?? 0);
$published_count= (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);
$wholesale_count= (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE price_type='Wholesale'")->fetch_assoc())['t'] ?? 0);

// ── SORT / SEARCH PARAMS ───────────────────────────────────────
$sort_column    = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$search_market  = trim($_GET['search_market'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');

// ── DATA FOR MODALS ────────────────────────────────────────────
$markets_for_modal = [];
$mr = $con->query("SELECT id, market_name FROM markets ORDER BY market_name");
if ($mr) { while ($r = $mr->fetch_assoc()) $markets_for_modal[] = $r; }

$commodities_for_modal = [];
$cr = $con->query("SELECT id, commodity_name FROM commodities ORDER BY commodity_name");
if ($cr) { while ($r = $cr->fetch_assoc()) $commodities_for_modal[] = $r; }

$modal_countries  = ['Kenya','Uganda','Tanzania','Rwanda','Burundi','Ethiopia','South Sudan'];
$modal_categories = ['Cereals','Pulses','Oil seeds','Vegetables','Fruits','Livestock'];
$modal_units      = ['kg','tons','g','lb','litres','pieces','bags'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<style>
/* ── Root variables ── */
:root {
    --mp-primary: #800000;
    --mp-primary-dk: #660000;
    --mp-green: #00450d;
    --mp-accent: #b45032;
    --mp-bg: #f9fafb;
    --mp-card: #ffffff;
    --mp-border: #e5e7eb;
    --mp-text: #1f2937;
    --mp-muted: #6b7280;
    --mp-radius: .625rem;
}

/* ── Page background ── */
.mp-wrap {
    background: radial-gradient(circle at top left, rgba(0,69,13,.04), transparent 50%),
                radial-gradient(circle at bottom right, rgba(128,0,0,.04), transparent 50%);
    min-height: 100vh;
    padding: 0 0 40px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: var(--mp-text);
}

/* ── Header ── */
.mp-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 4px;
}
.mp-page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--mp-primary); margin: 0; }
.mp-page-header p  { font-size: .875rem; color: var(--mp-muted); margin: 4px 0 0; }
.mp-accent-bar { height: 3px; background: linear-gradient(90deg, var(--mp-green) 0%, var(--mp-primary) 50%, var(--mp-green) 100%); border-radius: 99px; margin: 10px 0 20px; }

/* ── Stat cards ── */
.mp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.mp-stat-card {
    background: var(--mp-card);
    border-radius: var(--mp-radius);
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    border-left: 4px solid var(--mp-primary);
    transition: transform .2s, box-shadow .2s;
}
.mp-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.mp-stat-card.stat-pending   { border-left-color: #d97706; }
.mp-stat-card.stat-published { border-left-color: #16a34a; }
.mp-stat-card.stat-wholesale { border-left-color: #2563eb; }
.mp-stat-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--mp-muted); margin-bottom: 4px; }
.mp-stat-value { font-size: 1.4rem; font-weight: 700; color: var(--mp-text); }
.mp-stat-icon  { font-size: 2rem; opacity: .25; }
.mp-stat-icon.ic-total     { color: var(--mp-primary); opacity: .3; }
.mp-stat-icon.ic-pending   { color: #d97706; opacity: .3; }
.mp-stat-icon.ic-published { color: #16a34a; opacity: .3; }
.mp-stat-icon.ic-wholesale { color: #2563eb; opacity: .3; }

/* ── Alert ── */
.mp-alert {
    padding: 10px 14px;
    border-radius: var(--mp-radius);
    font-size: .875rem;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    border-left: 4px solid transparent;
}
.mp-alert.success { background: #f0fdf4; color: #15803d; border-left-color: #16a34a; }
.mp-alert.danger  { background: #fef2f2; color: #dc2626; border-left-color: #dc2626; }

/* ── Toolbar card ── */
.mp-toolbar {
    background: var(--mp-card);
    border-radius: var(--mp-radius);
    padding: 12px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 14px;
}
.mp-toolbar-left  { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.mp-toolbar-right { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

/* ── Buttons ── */
.mp-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 6px; font-size: .8125rem; font-weight: 500;
    border: 1px solid var(--mp-border); background: white; color: var(--mp-text);
    cursor: pointer; transition: all .2s; white-space: nowrap;
}
.mp-btn:hover { background: #f3f4f6; }
.mp-btn.primary  { background: var(--mp-primary); color: white; border-color: var(--mp-primary); }
.mp-btn.primary:hover { background: var(--mp-primary-dk); }
.mp-btn.success  { background: #16a34a; color: white; border-color: #16a34a; }
.mp-btn.success:hover { background: #15803d; }
.mp-btn.info     { background: #0891b2; color: white; border-color: #0891b2; }
.mp-btn.info:hover { background: #0e7490; }
.mp-btn.warning  { background: #d97706; color: white; border-color: #d97706; }
.mp-btn.warning:hover { background: #b45309; }
.mp-btn.danger   { background: #dc2626; color: white; border-color: #dc2626; }
.mp-btn.danger:hover { background: #b91c1c; }
.mp-btn.ghost    { background: transparent; border-color: var(--mp-border); color: var(--mp-muted); }
.mp-btn.ghost:hover { background: #f9fafb; color: var(--mp-text); }
.mp-btn:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }
.mp-badge-count  { background: rgba(255,255,255,.25); color: white; font-size: .7rem; font-weight: 700; padding: 1px 7px; border-radius: 99px; margin-left: 2px; }
.mp-badge-count.dark { background: rgba(0,0,0,.1); color: inherit; }

/* ── Dropdown ── */
.mp-dropdown { position: relative; }
.mp-dropdown-menu {
    position: absolute; top: calc(100% + 4px); left: 0; min-width: 190px; z-index: 200;
    background: white; border: 1px solid var(--mp-border); border-radius: var(--mp-radius);
    box-shadow: 0 8px 24px rgba(0,0,0,.1); display: none;
}
.mp-dropdown-menu.open { display: block; }
.mp-dropdown-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 14px;
    font-size: .8125rem; color: var(--mp-text); cursor: pointer;
    transition: background .15s;
}
.mp-dropdown-item:hover { background: #f9fafb; }
.mp-dropdown-divider { border: none; border-top: 1px solid var(--mp-border); margin: 4px 0; }

/* ── Search bar ── */
.mp-search-bar {
    background: var(--mp-card);
    border-radius: var(--mp-radius);
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    margin-bottom: 14px;
}
.mp-search-field { position: relative; flex: 1; min-width: 160px; }
.mp-search-field input {
    width: 100%; padding: 6px 10px 6px 32px;
    border: 1px solid var(--mp-border); border-radius: 6px;
    font-size: .8125rem; color: var(--mp-text);
    transition: border-color .2s, box-shadow .2s;
    box-sizing: border-box;
}
.mp-search-field input:focus { outline: none; border-color: var(--mp-primary); box-shadow: 0 0 0 3px rgba(128,0,0,.1); }
.mp-search-icon { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--mp-muted); font-size: 1rem; pointer-events: none; }

/* ── Table card ── */
.mp-table-card {
    background: var(--mp-card);
    border-radius: var(--mp-radius);
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    overflow: hidden;
}
.mp-table-wrap { overflow-x: auto; }
.mp-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.mp-table thead tr { background: #f8f9fa; }
.mp-table th {
    padding: 10px 12px; text-align: left;
    font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
    color: var(--mp-muted); border-bottom: 2px solid var(--mp-border);
    white-space: nowrap;
}
.mp-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.mp-table tbody tr:hover { background: #fefaf5; }
.mp-table tbody tr.mp-selected { background: rgba(128,0,0,.06) !important; }
.mp-table tbody tr.mp-selected:hover { background: rgba(128,0,0,.09) !important; }
.mp-table td.muted { color: var(--mp-muted); font-size: .75rem; }

/* ── Status badges ── */
.mp-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 9px; border-radius: 99px; font-size: .7rem; font-weight: 600;
}
.mp-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.mp-badge-pending    { background: #fef3c7; color: #92400e; }
.mp-badge-pending::before { background: #d97706; }
.mp-badge-published  { background: #dcfce7; color: #166534; }
.mp-badge-published::before { background: #16a34a; }
.mp-badge-approved   { background: #e0f2fe; color: #075985; }
.mp-badge-approved::before { background: #0891b2; }
.mp-badge-unpublished{ background: #fee2e2; color: #991b1b; }
.mp-badge-unpublished::before { background: #dc2626; }

/* ── Change pills ── */
.mp-change {
    display: inline-flex; align-items: center; gap: 2px;
    font-size: .7rem; font-weight: 600; padding: 1px 6px; border-radius: 4px;
}
.mp-change.up   { background: #dcfce7; color: #16a34a; }
.mp-change.down { background: #fee2e2; color: #dc2626; }
.mp-change.flat { background: #f3f4f6; color: var(--mp-muted); }

/* ── Price display ── */
.mp-price { font-family: 'Courier New', monospace; font-weight: 700; font-size: .875rem; color: var(--mp-text); }

/* ── Action buttons in table ── */
.mp-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer;
    transition: all .2s; background: #f3f4f6; color: var(--mp-muted);
}
.mp-action-btn:hover { background: #e0f2fe; color: #0891b2; }

/* ── Pagination ── */
.mp-pagination-bar {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 12px; padding: 12px 16px; border-top: 1px solid var(--mp-border);
    background: var(--mp-card);
}
.mp-pagination-info { font-size: .8125rem; color: var(--mp-muted); }
.mp-pagination-nav  { display: flex; align-items: center; gap: 4px; }
.mp-pg-btn {
    min-width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: .75rem; border: 1px solid var(--mp-border);
    background: white; color: var(--mp-text); cursor: pointer; transition: all .2s; padding: 0 4px;
}
.mp-pg-btn:hover:not(:disabled):not(.active) { background: #fef3e7; border-color: var(--mp-primary); color: var(--mp-primary); }
.mp-pg-btn.active { background: var(--mp-primary); border-color: var(--mp-primary); color: white; font-weight: 700; }
.mp-pg-btn:disabled { opacity: .35; cursor: not-allowed; }
.mp-page-size select {
    font-size: .75rem; padding: 3px 8px; border: 1px solid var(--mp-border);
    border-radius: 6px; background: white; cursor: pointer; color: var(--mp-text);
}

/* ── Modal ── */
.mp-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 500; display: none; overflow-y: auto;
}
.mp-modal-backdrop.open { display: block; }
.mp-modal-center { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.mp-modal-box {
    background: white; border-radius: var(--mp-radius);
    width: 100%; max-width: 560px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.mp-modal-box.wide { max-width: 700px; }
.mp-modal-header {
    background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-green) 100%);
    padding: 14px 18px; border-radius: var(--mp-radius) var(--mp-radius) 0 0;
    display: flex; align-items: center; justify-content: space-between;
    color: white;
}
.mp-modal-header h3 { margin: 0; font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.mp-modal-header button { background: none; border: none; color: rgba(255,255,255,.8); cursor: pointer; font-size: 1.25rem; line-height: 1; }
.mp-modal-header button:hover { color: white; }
.mp-modal-body  { padding: 18px; }
.mp-modal-footer { padding: 14px 18px; border-top: 1px solid var(--mp-border); display: flex; justify-content: flex-end; gap: 8px; }

/* ── Import instructions box ── */
.mp-import-info {
    background: #eff6ff; border-left: 4px solid #2563eb;
    border-radius: 0 6px 6px 0; padding: 14px; margin-bottom: 16px; font-size: .8125rem;
}
.mp-import-info h5 { color: #1d4ed8; font-size: .875rem; margin: 0 0 8px; }
.mp-import-info ol { margin: 0; padding-left: 18px; color: #1e40af; }
.mp-import-info li { margin-bottom: 3px; }
.mp-template-link { display: inline-flex; align-items: center; gap: 4px; margin-top: 10px; color: #2563eb; font-size: .8rem; font-weight: 500; text-decoration: none; }
.mp-template-link:hover { text-decoration: underline; }

/* ── Form helpers ── */
.mp-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.mp-form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.mp-form-group label { font-size: .8125rem; font-weight: 600; color: var(--mp-text); }
.mp-form-group input, .mp-form-group select {
    padding: 7px 10px; border: 1px solid var(--mp-border); border-radius: 6px;
    font-size: .8125rem; color: var(--mp-text);
}
.mp-form-group input:focus, .mp-form-group select:focus {
    outline: none; border-color: var(--mp-primary); box-shadow: 0 0 0 3px rgba(128,0,0,.1);
}
.mp-checkbox-row { display: flex; align-items: center; gap: 8px; font-size: .8125rem; color: var(--mp-text); margin-bottom: 14px; cursor: pointer; }

/* ── Checkbox style ── */
input[type="checkbox"].mp-check { width: 15px; height: 15px; cursor: pointer; accent-color: var(--mp-primary); }

/* ── Material icons util ── */
.ms { font-family: 'Material Symbols Outlined' !important; font-style: normal; font-weight: normal; line-height: 1; letter-spacing: normal; text-transform: none; display: inline-block; white-space: nowrap; direction: ltr; font-feature-settings: 'liga'; -webkit-font-smoothing: antialiased; vertical-align: middle; }

/* ── Sortable headers ── */
.mp-th-sort { cursor: pointer; user-select: none; white-space: nowrap; }
.mp-th-sort:hover { color: var(--mp-primary); }
.mp-sort-icon { font-size: .65rem; margin-left: 3px; opacity: .5; vertical-align: middle; }
.mp-th-sort.active-sort { color: var(--mp-primary); }
.mp-th-sort.active-sort .mp-sort-icon { opacity: 1; }

/* ── Flat-row group display (no rowspan) ── */
.mp-row-cont td.mp-shared-cell {
    color: transparent !important;
    user-select: none;
    pointer-events: none;
}
.mp-row-cont td.mp-shared-cell span,
.mp-row-cont td.mp-shared-cell * { visibility: hidden; }
/* Group separator between different groups */
.mp-row-first { border-top: 2px solid #e5e7eb !important; }
.mp-row-first:first-child { border-top: none !important; }
/* Subtle group background bands */
.mp-group-even { background: #fafafa; }
.mp-group-even:hover { background: #f5f5f5 !important; }

@media (max-width: 768px) {
    .mp-stats { grid-template-columns: repeat(2, 1fr); }
    .mp-form-row { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .mp-stats { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="mp-wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    <!-- ── Page Header ── -->
    <div class="mp-page-header">
        <div>
            <h1>Market Prices Management</h1>
            <p>Manage market price data across commodities and markets</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="mp-btn primary" onclick="openModal('addPriceModal')">
                <span class="ms">add_circle</span> Add New Price
            </button>
        </div>
    </div>
    <div class="mp-accent-bar"></div>

    <!-- ── Alert ── -->
    <?php if ($import_message): ?>
    <div class="mp-alert <?= $import_status === 'success' ? 'success' : 'danger' ?>">
        <span class="ms"><?= $import_status === 'success' ? 'check_circle' : 'error' ?></span>
        <span><?= $import_message ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="mp-stats">
        <div class="mp-stat-card">
            <div>
                <div class="mp-stat-label">Total Prices</div>
                <div class="mp-stat-value"><?= number_format($total_prices) ?></div>
            </div>
            <span class="ms mp-stat-icon ic-total" style="font-size:2.2rem;">monitoring</span>
        </div>
        <div class="mp-stat-card stat-pending">
            <div>
                <div class="mp-stat-label">Pending</div>
                <div class="mp-stat-value"><?= number_format($pending_count) ?></div>
            </div>
            <span class="ms mp-stat-icon ic-pending" style="font-size:2.2rem;">schedule</span>
        </div>
        <div class="mp-stat-card stat-published">
            <div>
                <div class="mp-stat-label">Published</div>
                <div class="mp-stat-value"><?= number_format($published_count) ?></div>
            </div>
            <span class="ms mp-stat-icon ic-published" style="font-size:2.2rem;">check_circle</span>
        </div>
        <div class="mp-stat-card stat-wholesale">
            <div>
                <div class="mp-stat-label">Wholesale</div>
                <div class="mp-stat-value"><?= number_format($wholesale_count) ?></div>
            </div>
            <span class="ms mp-stat-icon ic-wholesale" style="font-size:2.2rem;">balance</span>
        </div>
    </div>

    <!-- ── Toolbar ── -->
    <div class="mp-toolbar">
        <div class="mp-toolbar-left">
            <button class="mp-btn danger" id="bulkDeleteBtn" disabled onclick="deleteSelected()">
                <span class="ms">delete</span> Delete
                <span class="mp-badge-count dark" id="selectedCount">0</span>
            </button>
            <button class="mp-btn ghost" onclick="clearAllSelections()">
                <span class="ms">clear</span> Clear Selected
            </button>
            <button class="mp-btn success" id="approveBtn" disabled onclick="approveSelected()">
                <span class="ms">check_circle</span> Approve
            </button>
            <button class="mp-btn info" id="publishBtn" disabled onclick="publishSelected()">
                <span class="ms">publish</span> Publish
            </button>
            <button class="mp-btn warning" id="unpublishBtn" disabled onclick="unpublishSelected()">
                <span class="ms">unpublished</span> Unpublish
            </button>
        </div>
        <div class="mp-toolbar-right">
            <div class="mp-dropdown" id="exportDropdown">
                <button class="mp-btn" onclick="mpExportToggle()">
                    <span class="ms">download</span> Export <span class="ms" style="font-size:.9rem;">expand_more</span>
                </button>
                <div class="mp-dropdown-menu" id="exportDropdownMenu">
                    <div class="mp-dropdown-item" onclick="exportSelected('excel')"><span class="ms">table_view</span> Selected → Excel/CSV</div>
                    <div class="mp-dropdown-item" onclick="exportSelected('pdf')"><span class="ms">picture_as_pdf</span> Selected → PDF</div>
                    <hr class="mp-dropdown-divider">
                    <div class="mp-dropdown-item" onclick="exportAll('excel')"><span class="ms">table_view</span> All → Excel/CSV</div>
                    <div class="mp-dropdown-item" onclick="exportAll('pdf')"><span class="ms">picture_as_pdf</span> All → PDF</div>
                    <div class="mp-dropdown-item" onclick="exportAll('csv')"><span class="ms">csv</span> All → CSV</div>
                </div>
            </div>
            <button class="mp-btn" onclick="openModal('importModal')">
                <span class="ms">upload_file</span> Import CSV
            </button>
        </div>
    </div>

    <!-- ── Search ── -->
    <div class="mp-search-bar">
        <div class="mp-search-field">
            <span class="ms mp-search-icon">search</span>
            <input type="text" id="searchMarket" placeholder="Search market…" value="<?= htmlspecialchars($search_market) ?>">
        </div>
        <div class="mp-search-field">
            <span class="ms mp-search-icon">grain</span>
            <input type="text" id="searchCommodity" placeholder="Search commodity…" value="<?= htmlspecialchars($search_commodity) ?>">
        </div>
        <div class="mp-search-field">
            <span class="ms mp-search-icon">filter_alt</span>
            <input type="text" id="searchType" placeholder="Price type…">
        </div>
        <div class="mp-search-field">
            <span class="ms mp-search-icon">flag</span>
            <input type="text" id="searchStatus" placeholder="Status…">
        </div>
        <button class="mp-btn primary" onclick="applyClientFilter()">
            <span class="ms">search</span> Filter
        </button>
        <button class="mp-btn ghost" onclick="clearFilter()">
            <span class="ms">close</span>
        </button>
    </div>

    <!-- ── Table ── -->
    <div class="mp-table-card">
        <div class="mp-table-wrap">
            <table class="mp-table" id="pricesTable">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" class="mp-check" id="selectAll" onchange="mpSelectAll(this)">
                        </th>
                        <?php
                        $all_sort_cols = [
                            'market'     => 'Market',
                            'commodity'  => 'Commodity',
                            'date_posted'=> 'Date',
                            'price_type' => 'Type',
                            'Price'      => 'Price (USD)',
                        ];
                        foreach ($all_sort_cols as $col_key => $col_label):
                            $is_active = ($sort_column === $col_key);
                            $next_dir  = ($is_active && $sort_direction === 'DESC') ? 'asc' : 'desc';
                            $icon      = $is_active ? ($sort_direction === 'ASC' ? '↑' : '↓') : '↕';
                        ?>
                        <th class="mp-th-sort <?= $is_active ? 'active-sort' : '' ?>"
                            onclick="mpSortTable('<?= $col_key ?>', '<?= $next_dir ?>')">
                            <?= $col_label ?><span class="mp-sort-icon"><?= $icon ?></span>
                        </th>
                        <?php endforeach; ?>
                        <th>Day Δ</th>
                        <th>Month Δ</th>
                        <?php
                        $st_active = ($sort_column === 'status');
                        $st_dir    = ($st_active && $sort_direction === 'DESC') ? 'asc' : 'desc';
                        $st_icon   = $st_active ? ($sort_direction === 'ASC' ? '↑' : '↓') : '↕';
                        ?>
                        <th class="mp-th-sort <?= $st_active ? 'active-sort' : '' ?>"
                            onclick="mpSortTable('status', '<?= $st_dir ?>')">
                            Status<span class="mp-sort-icon"><?= $st_icon ?></span>
                        </th>
                        <th>Source</th>
                        <th style="width:60px; text-align:center;">Edit</th>
                    </tr>
                </thead>
                <tbody id="pricesTableBody">
                <?php
                $grouped_data = [];
                foreach ($prices_data as $price) {
                    $date = date('Y-m-d', strtotime($price['date_posted']));
                    $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'];
                    $grouped_data[$group_key][] = $price;
                }

                $group_index = 0;
                foreach ($grouped_data as $group_key => $prices_in_group):
                    // Always Wholesale before Retail
                    usort($prices_in_group, function($a, $b) {
                        $o = ['Wholesale'=>0,'Retail'=>1];
                        return ($o[$a['price_type']]??9) - ($o[$b['price_type']]??9);
                    });
                    $group_ids      = array_column($prices_in_group, 'id');
                    $group_ids_json = htmlspecialchars(json_encode($group_ids));
                    $group_band     = $group_index % 2 === 1 ? 'mp-group-even' : '';
                    $group_index++;

                    foreach ($prices_in_group as $row_idx => $price):
                        $is_first   = ($row_idx === 0);
                        $price_date = $price['date_posted'];
                        $day_change   = calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price_date, $con);
                        $month_change = calculateMoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price_date, $con);
                        $dod_class = 'flat'; $mom_class = 'flat';
                        if ($day_change   !== 'N/A') { $dod_class = floatval($day_change)   >= 0 ? 'up' : 'down'; }
                        if ($month_change !== 'N/A') { $mom_class = floatval($month_change) >= 0 ? 'up' : 'down'; }
                        $dod_icon = $dod_class==='up' ? '▲' : ($dod_class==='down' ? '▼' : '–');
                        $mom_icon = $mom_class==='up' ? '▲' : ($mom_class==='down' ? '▼' : '–');
                        $row_classes = $group_band;
                        $row_classes .= $is_first ? ' mp-row-first' : ' mp-row-cont';
                ?>
                <tr class="price-row <?= $row_classes ?>"
                    data-price-id="<?= $price['id'] ?>"
                    data-group-key="<?= htmlspecialchars($group_key) ?>"
                    data-group-ids="<?= $group_ids_json ?>"
                    data-market="<?= htmlspecialchars(strtolower($price['market'])) ?>"
                    data-commodity="<?= htmlspecialchars(strtolower($price['commodity_display'])) ?>"
                    data-type="<?= htmlspecialchars(strtolower($price['price_type'])) ?>"
                    data-status="<?= htmlspecialchars(strtolower($price['status'])) ?>"
                    data-source="<?= htmlspecialchars(strtolower($price['data_source'] ?? '')) ?>">

                    <td>
                        <?php if ($is_first): ?>
                        <input type="checkbox" class="mp-check row-checkbox"
                            data-group-key="<?= htmlspecialchars($group_key) ?>"
                            data-group-ids="<?= $group_ids_json ?>"
                            onchange="mpCheckboxChange(this)">
                        <?php endif; ?>
                    </td>

                    <!-- Shared cells: visible on first row, invisible on continuation rows -->
                    <td class="mp-shared-cell" style="font-weight:600;">
                        <?= htmlspecialchars($price['market']) ?>
                    </td>
                    <td class="mp-shared-cell">
                        <?= htmlspecialchars($price['commodity_display']) ?>
                    </td>
                    <td class="mp-shared-cell muted">
                        <?= date('M d, Y', strtotime($price['date_posted'])) ?>
                    </td>

                    <!-- Per-row data cells -->
                    <td>
                        <span style="font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:4px;
                            background:<?= $price['price_type']==='Wholesale' ? '#ede9fe' : '#fce7f3' ?>;
                            color:<?= $price['price_type']==='Wholesale' ? '#6d28d9' : '#be185d' ?>;">
                            <?= htmlspecialchars($price['price_type']) ?>
                        </span>
                    </td>
                    <td><span class="mp-price">$<?= number_format($price['Price'], 4) ?></span></td>
                    <td><span class="mp-change <?= $dod_class ?>"><?= $dod_icon ?> <?= $day_change ?></span></td>
                    <td><span class="mp-change <?= $mom_class ?>"><?= $mom_icon ?> <?= $month_change ?></span></td>
                    <td><?= getStatusBadge($price['status']) ?></td>
                    <td class="muted"><?= htmlspecialchars($price['data_source'] ?? '') ?></td>
                    <td style="text-align:center;">
                        <button class="mp-action-btn" title="Edit" onclick="openEditModal(<?= $price['id'] ?>)">
                            <span class="ms" style="font-size:1rem;">edit</span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ── -->
        <div class="mp-pagination-bar">
            <div class="mp-pagination-info" id="paginationInfo">
                Showing <?= $offset + 1 ?> – <?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> records
                <span id="selectionSummary" style="color:var(--mp-primary);font-weight:600;margin-left:6px;"></span>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <div class="mp-page-size">
                    <label style="font-size:.8rem;color:var(--mp-muted);margin-right:5px;">Rows:</label>
                    <select onchange="changeRowsPerPage(this.value)">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav class="mp-pagination-nav">
                    <button class="mp-pg-btn" onclick="goToPage(1)" <?= $page <= 1 ? 'disabled' : '' ?> title="First">
                        <span class="ms" style="font-size:.9rem;">first_page</span>
                    </button>
                    <button class="mp-pg-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?> title="Prev">
                        <span class="ms" style="font-size:.9rem;">chevron_left</span>
                    </button>
                    <?php
                    $win = 2; $sp = max(1, $page - $win); $ep = min($total_pages, $page + $win);
                    if ($sp === 1) $ep = min($total_pages, 1 + $win * 2);
                    if ($ep === $total_pages) $sp = max(1, $total_pages - $win * 2);
                    if ($sp > 1): ?><button class="mp-pg-btn" onclick="goToPage(1)">1</button><?php if ($sp > 2): ?><span style="color:var(--mp-muted);font-size:.75rem;padding:0 2px">…</span><?php endif; endif; ?>
                    <?php for ($i = $sp; $i <= $ep; $i++): ?>
                        <button class="mp-pg-btn <?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? '' : "onclick=\"goToPage($i)\"" ?>><?= $i ?></button>
                    <?php endfor; ?>
                    <?php if ($ep < $total_pages): ?><?php if ($ep < $total_pages - 1): ?><span style="color:var(--mp-muted);font-size:.75rem;padding:0 2px">…</span><?php endif; ?><button class="mp-pg-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button><?php endif; ?>
                    <button class="mp-pg-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?> title="Next">
                        <span class="ms" style="font-size:.9rem;">chevron_right</span>
                    </button>
                    <button class="mp-pg-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?> title="Last">
                        <span class="ms" style="font-size:.9rem;">last_page</span>
                    </button>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /table card -->

</div><!-- /mp-wrap -->


<!-- ══════════════════════════════════════
     ADD PRICE MODAL
══════════════════════════════════════ -->
<div id="addPriceModal" class="mp-modal-backdrop">
    <div class="mp-modal-center">
        <div class="mp-modal-box wide">
            <div class="mp-modal-header">
                <h3><span class="ms">add_circle</span> Add New Market Price</h3>
                <button onclick="closeModal('addPriceModal')">✕</button>
            </div>
            <form method="POST" action="" id="addPriceForm">
                <input type="hidden" name="modal_add_price" value="1">
                <div class="mp-modal-body">

                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>Country <span style="color:red">*</span></label>
                            <select name="country" id="add_country" required>
                                <option value="" disabled selected>Select country</option>
                                <?php foreach ($modal_countries as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mp-form-group">
                            <label>Market <span style="color:red">*</span></label>
                            <select name="market_id" id="add_market" required>
                                <option value="" disabled selected>Select market</option>
                                <?php foreach ($markets_for_modal as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['market_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>Category <span style="color:red">*</span></label>
                            <select name="category" id="add_category" required>
                                <option value="" disabled selected>Select category</option>
                                <?php foreach ($modal_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mp-form-group">
                            <label>Commodity <span style="color:red">*</span></label>
                            <select name="commodity_id" id="add_commodity" required>
                                <option value="" disabled selected>Select market first</option>
                            </select>
                        </div>
                    </div>

                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>Packaging Unit <span style="color:red">*</span></label>
                            <input type="text" name="packaging_unit" id="add_packaging" readonly
                                placeholder="Auto-filled from commodity" required
                                style="background:#f9fafb;color:var(--mp-muted);">
                        </div>
                        <div class="mp-form-group">
                            <label>Measuring Unit <span style="color:red">*</span></label>
                            <input type="text" name="measuring_unit" id="add_measuring" readonly
                                placeholder="Auto-filled from commodity" required
                                style="background:#f9fafb;color:var(--mp-muted);">
                        </div>
                    </div>

                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>Variety</label>
                            <input type="text" name="variety" id="add_variety"
                                placeholder="e.g. Yellow, White, Mixed">
                        </div>
                        <div class="mp-form-group">
                            <label>Data Source</label>
                            <input type="text" name="data_source" id="add_data_source"
                                placeholder="e.g. Field Survey">
                        </div>
                    </div>

                    <div style="background:#fafafa;border:1px solid var(--mp-border);border-radius:8px;padding:14px;margin-top:4px;">
                        <p style="font-size:.75rem;font-weight:600;color:var(--mp-muted);margin:0 0 10px;text-transform:uppercase;letter-spacing:.05em;">Pricing</p>
                        <div class="mp-form-row" style="margin-bottom:0;">
                            <div class="mp-form-group" style="margin-bottom:0;">
                                <label>Wholesale Price <span style="color:red">*</span>
                                    <span id="add_ws_hint" style="font-weight:400;color:var(--mp-muted);font-size:.72rem;"></span>
                                </label>
                                <input type="number" step="0.01" name="wholesale_price" id="add_wholesale"
                                    placeholder="Price for entire packaging unit" required>
                            </div>
                            <div class="mp-form-group" style="margin-bottom:0;">
                                <label>Retail Price <span style="color:red">*</span>
                                    <span id="add_rt_hint" style="font-weight:400;color:var(--mp-muted);font-size:.72rem;"></span>
                                </label>
                                <input type="number" step="0.01" name="retail_price" id="add_retail"
                                    placeholder="Price per measuring unit" required>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="mp-modal-footer">
                    <button type="button" class="mp-btn ghost" onclick="closeModal('addPriceModal')">Cancel</button>
                    <button type="submit" class="mp-btn primary">
                        <span class="ms">save</span> Add Price
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     EDIT PRICE MODAL
══════════════════════════════════════ -->
<div id="editPriceModal" class="mp-modal-backdrop">
    <div class="mp-modal-center">
        <div class="mp-modal-box wide">
            <div class="mp-modal-header">
                <h3><span class="ms">edit</span> Edit Market Price</h3>
                <button onclick="closeModal('editPriceModal')">✕</button>
            </div>
            <form method="POST" action="" id="editPriceForm">
                <input type="hidden" name="modal_edit_price" value="1">
                <input type="hidden" name="price_id" id="edit_price_id">
                <div class="mp-modal-body">

                    <div id="editModalSpinner" style="text-align:center;padding:30px;display:none;">
                        <span class="ms" style="font-size:2.5rem;color:var(--mp-primary);animation:spin 1s linear infinite;">hourglass_empty</span>
                        <p style="color:var(--mp-muted);margin-top:8px;font-size:.875rem;">Loading record…</p>
                    </div>

                    <div id="editModalContent">
                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Country <span style="color:red">*</span></label>
                                <select name="country" id="edit_country" required>
                                    <?php foreach ($modal_countries as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mp-form-group">
                                <label>Market <span style="color:red">*</span></label>
                                <select name="market_id" id="edit_market" required>
                                    <?php foreach ($markets_for_modal as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['market_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Category <span style="color:red">*</span></label>
                                <select name="category" id="edit_category" required>
                                    <?php foreach ($modal_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mp-form-group">
                                <label>Commodity <span style="color:red">*</span></label>
                                <select name="commodity_id" id="edit_commodity" required>
                                    <?php foreach ($commodities_for_modal as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Packaging Unit <span style="color:red">*</span></label>
                                <input type="text" name="packaging_unit" id="edit_packaging" required>
                            </div>
                            <div class="mp-form-group">
                                <label>Measuring Unit <span style="color:red">*</span></label>
                                <select name="measuring_unit" id="edit_measuring" required>
                                    <?php foreach ($modal_units as $u): ?>
                                        <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Variety</label>
                                <input type="text" name="variety" id="edit_variety">
                            </div>
                            <div class="mp-form-group">
                                <label>Data Source</label>
                                <input type="text" name="data_source" id="edit_data_source">
                            </div>
                        </div>

                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Price Type <span style="color:red">*</span></label>
                                <select name="price_type" id="edit_price_type" required>
                                    <option value="Wholesale">Wholesale</option>
                                    <option value="Retail">Retail</option>
                                </select>
                            </div>
                            <div class="mp-form-group">
                                <label>Price (USD) <span style="color:red">*</span></label>
                                <input type="number" step="0.0001" name="price" id="edit_price" required>
                            </div>
                        </div>

                        <div class="mp-form-group">
                            <label>Status <span style="color:red">*</span></label>
                            <select name="status" id="edit_status" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="published">Published</option>
                                <option value="unpublished">Unpublished</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="mp-modal-footer">
                    <button type="button" class="mp-btn ghost" onclick="closeModal('editPriceModal')">Cancel</button>
                    <button type="submit" class="mp-btn primary" id="editSaveBtn">
                        <span class="ms">save</span> Update Price
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div id="importModal" class="mp-modal-backdrop">
    <div class="mp-modal-center">
        <div class="mp-modal-box wide">
            <div class="mp-modal-header">
                <h3><span class="ms">upload_file</span> Import Market Prices (CSV)</h3>
                <button onclick="closeModal('importModal')">✕</button>
            </div>
            <div class="mp-modal-body">
                <div class="mp-import-info">
                    <h5>CSV Column Order</h5>
                    <ol>
                        <li><strong>Market</strong> — required (must exist in markets table)</li>
                        <li><strong>Commodity ID</strong> — required (integer ID)</li>
                        <li><strong>Price Type</strong> — required: <code>Wholesale</code> or <code>Retail</code></li>
                        <li><strong>Price</strong> — required (numeric)</li>
                        <li><strong>Date Posted</strong> — required: <code>YYYY-MM-DD</code></li>
                        <li><strong>Status</strong> — optional: pending / approved / published / unpublished</li>
                        <li><strong>Variety</strong> — optional</li>
                        <li><strong>Weight, Unit, Country, Subject, Supplied Volume, Comments, Supply Status, Category</strong> — all optional</li>
                    </ol>
                    <a href="downloads/market_prices_template.csv" class="mp-template-link">
                        <span class="ms">download</span> Download CSV Template
                    </a>
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label for="csv_file">CSV File <span style="color:red">*</span></label>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </div>
                        <div class="mp-form-group">
                            <label for="data_source">Data Source <span style="color:red">*</span></label>
                            <input type="text" name="data_source" id="data_source" placeholder="e.g. Field Survey, API Feed" required>
                        </div>
                    </div>
                    <label class="mp-checkbox-row">
                        <input type="checkbox" class="mp-check" name="overwrite_existing">
                        Overwrite existing records with matching market, commodity, price type and date
                    </label>
                </form>
            </div>
            <div class="mp-modal-footer">
                <button class="mp-btn ghost" onclick="closeModal('importModal')">Cancel</button>
                <button class="mp-btn primary" onclick="document.getElementById('importForm').submit()">
                    <span class="ms">upload</span> Import
                    <input type="hidden" name="import_csv" value="1" form="importForm">
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     CONFIRM DELETE MODAL
══════════════════════════════════════ -->
<div id="deleteModal" class="mp-modal-backdrop">
    <div class="mp-modal-center">
        <div class="mp-modal-box">
            <div class="mp-modal-header" style="background:linear-gradient(135deg,#dc2626,#991b1b)">
                <h3><span class="ms">warning</span> Confirm Deletion</h3>
                <button onclick="closeModal('deleteModal')">✕</button>
            </div>
            <div class="mp-modal-body">
                <p id="deleteModalText" style="font-size:.9rem;color:var(--mp-text);margin-bottom:12px;"></p>
                <div style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:0 6px 6px 0;padding:10px 12px;font-size:.8rem;color:#991b1b;">
                    <span class="ms" style="font-size:.9rem;vertical-align:middle;">info</span>
                    This action is irreversible and cannot be undone.
                </div>
            </div>
            <div class="mp-modal-footer">
                <button class="mp-btn ghost" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="mp-btn danger" id="confirmDeleteBtn">
                    <span class="ms">delete</span> Delete
                </button>
            </div>
        </div>
    </div>
</div>


<script>
// ─────────────────────────────────────────────────────────────
// All page functions are namespaced with "mp" prefix so they
// never conflict with admin_header.php's toggleDropdown / etc.
// ─────────────────────────────────────────────────────────────

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.mp-modal-backdrop').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Export dropdown (namespaced — does NOT conflict with sidebar) ─
function mpExportToggle() {
    document.getElementById('exportDropdownMenu').classList.toggle('open');
}
document.addEventListener('click', e => {
    const menu = document.getElementById('exportDropdownMenu');
    if (menu && !menu.closest('.mp-dropdown').contains(e.target)) {
        menu.classList.remove('open');
    }
});

// ─────────────────────────────────────────────────────────────
// SELECTION — uses explicit inline onchange, no delegation
// Uses getAttribute comparison (not CSS selectors) for reliability
// ─────────────────────────────────────────────────────────────
let allSelectedIds = new Set();

// Called by each group checkbox via onchange="mpCheckboxChange(this)"
function mpCheckboxChange(cb) {
    const groupKey = cb.getAttribute('data-group-key');
    let   groupIds = [];
    try   { groupIds = JSON.parse(cb.getAttribute('data-group-ids') || '[]'); } catch(e) {}

    if (cb.checked) {
        groupIds.forEach(id => allSelectedIds.add(String(id)));
    } else {
        groupIds.forEach(id => allSelectedIds.delete(String(id)));
    }

    // Highlight rows: iterate & compare attribute — safe for any market name
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(row => {
        if (row.getAttribute('data-group-key') === groupKey) {
            row.classList.toggle('mp-selected', cb.checked);
        }
    });

    mpSyncUI();
}

// Called by the header select-all checkbox via onchange="mpSelectAll(this)"
function mpSelectAll(masterCb) {
    const checked = masterCb.checked;
    document.querySelectorAll('#pricesTableBody .row-checkbox').forEach(cb => {
        // skip rows hidden by filter
        if (cb.closest('tr').classList.contains('mp-filtered-out')) return;
        if (cb.checked === checked) return; // nothing to change

        cb.checked = checked;
        const groupKey = cb.getAttribute('data-group-key');
        let   groupIds = [];
        try   { groupIds = JSON.parse(cb.getAttribute('data-group-ids') || '[]'); } catch(e) {}

        if (checked) {
            groupIds.forEach(id => allSelectedIds.add(String(id)));
        } else {
            groupIds.forEach(id => allSelectedIds.delete(String(id)));
        }
        document.querySelectorAll('#pricesTableBody tr.price-row').forEach(row => {
            if (row.getAttribute('data-group-key') === groupKey) {
                row.classList.toggle('mp-selected', checked);
            }
        });
    });
    mpSyncUI();
}

function mpClearAllSelections() {
    allSelectedIds.clear();
    document.querySelectorAll('#pricesTableBody .row-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => r.classList.remove('mp-selected'));
    const sa = document.getElementById('selectAll');
    if (sa) { sa.checked = false; sa.indeterminate = false; }
    mpSyncUI();
}

// Sync button states + select-all indicator
function mpSyncUI() {
    const count = allSelectedIds.size;
    document.getElementById('selectedCount').textContent = count;

    const isAny = count > 0;
    ['bulkDeleteBtn','approveBtn','publishBtn','unpublishBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = !isAny;
    });

    const sumEl = document.getElementById('selectionSummary');
    if (sumEl) sumEl.textContent = isAny ? `(${count} selected)` : '';

    // Update select-all checkbox state
    const visibleCbs    = [...document.querySelectorAll('#pricesTableBody .row-checkbox')]
        .filter(cb => !cb.closest('tr').classList.contains('mp-filtered-out'));
    const checkedCbs    = visibleCbs.filter(cb => cb.checked);
    const sa = document.getElementById('selectAll');
    if (sa) {
        sa.checked       = visibleCbs.length > 0 && checkedCbs.length === visibleCbs.length;
        sa.indeterminate = checkedCbs.length > 0 && checkedCbs.length < visibleCbs.length;
    }
}

function mpGetSelectedIds() { return Array.from(allSelectedIds); }

// ── Expose clearAllSelections under the old name used in HTML ─
function clearAllSelections() { mpClearAllSelections(); }

// ── Bulk actions ──────────────────────────────────────────────
function approveSelected()   { mpBulkAction('approve',   'Approve'); }
function publishSelected()   { mpBulkAction('publish',   'Publish'); }
function unpublishSelected() { mpBulkAction('unpublish', 'Unpublish'); }

function mpBulkAction(action, label) {
    const ids = mpGetSelectedIds();
    if (!ids.length) { alert(`Select at least one item to ${label.toLowerCase()}.`); return; }
    if (!confirm(`${label} ${ids.length} item(s)?`)) return;
    mpPerformAction(action, ids);
}

function deleteSelected() {
    const ids = mpGetSelectedIds();
    if (!ids.length) { alert('Select at least one price to delete.'); return; }
    document.getElementById('deleteModalText').innerHTML =
        `Are you sure you want to delete <strong>${ids.length}</strong> selected price record(s)?`;
    document.getElementById('confirmDeleteBtn').onclick = () => { closeModal('deleteModal'); mpPerformAction('delete', ids); };
    openModal('deleteModal');
}

function mpPerformAction(action, ids) {
    const toast = document.createElement('div');
    toast.className = 'mp-alert success';
    toast.innerHTML = '<span class="ms">hourglass_empty</span> Processing…';
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:220px;box-shadow:0 4px 16px rgba(0,0,0,.15)';
    document.body.appendChild(toast);

    fetch('../data/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ids })
    })
    .then(r => r.json())
    .then(data => {
        toast.remove();
        if (data.success) { alert(data.message || 'Done.'); location.reload(); }
        else { alert('Error: ' + (data.message || 'Unknown error')); }
    })
    .catch(err => { toast.remove(); alert('Request failed: ' + err.message); });
}

// ── Export ────────────────────────────────────────────────────
function exportSelected(format) {
    const ids = mpGetSelectedIds();
    if (!ids.length) { alert('Select at least one price to export.'); return; }
    mpSubmitExport(format, ids, false);
}
function exportAll(format) {
    if (!confirm('Export ALL prices? This may take a moment for large datasets.')) return;
    mpSubmitExport(format, [], true);
}
function mpSubmitExport(format, ids, doAll) {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = window.location.href; form.target = '_blank';
    const add = (n, v) => { const i = document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    add('export_format', format);
    if (doAll) { add('export_all','true'); } else { ids.forEach(id => add('selected_ids[]', id)); }
    document.body.appendChild(form); form.submit(); document.body.removeChild(form);
}

// ─────────────────────────────────────────────────────────────
// FILTER — reads data-* attributes set on every <tr>
// For Type filter: each row has its own data-type (wholesale/retail)
// A group is shown if any one of its rows passes the type filter
// ─────────────────────────────────────────────────────────────
function applyClientFilter() {
    const fm = (document.getElementById('searchMarket')?.value    || '').trim().toLowerCase();
    const fc = (document.getElementById('searchCommodity')?.value || '').trim().toLowerCase();
    const ft = (document.getElementById('searchType')?.value      || '').trim().toLowerCase();
    const fs = (document.getElementById('searchStatus')?.value    || '').trim().toLowerCase();

    // Phase 1: scan every row and accumulate per-group match flags
    const groups = {}; // groupKey → { mkt, com, typ, sts }

    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(row => {
        const gk = row.getAttribute('data-group-key');
        if (!groups[gk]) groups[gk] = { mkt: !fm, com: !fc, typ: !ft, sts: !fs };
        const g = groups[gk];

        if (fm && !g.mkt) g.mkt = (row.getAttribute('data-market')    || '').includes(fm);
        if (fc && !g.com) g.com = (row.getAttribute('data-commodity')  || '').includes(fc);
        if (ft && !g.typ) g.typ = (row.getAttribute('data-type')       || '').includes(ft);
        if (fs && !g.sts) g.sts = (row.getAttribute('data-status')     || '').includes(fs);
    });

    // Phase 2: show/hide based on group result
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(row => {
        const g = groups[row.getAttribute('data-group-key')];
        const show = g.mkt && g.com && g.typ && g.sts;
        row.classList.toggle('mp-filtered-out', !show);
        row.style.display = show ? '' : 'none';
    });

    mpSyncUI();
}

function clearFilter() {
    ['searchMarket','searchCommodity','searchType','searchStatus'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
    });
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => {
        r.classList.remove('mp-filtered-out'); r.style.display = '';
    });
    mpSyncUI();
}

// ── Server-side sort ──────────────────────────────────────────
function mpSortTable(col, dir) {
    const url = new URL(window.location);
    url.searchParams.set('sort',  col);
    url.searchParams.set('dir',   dir);
    url.searchParams.set('page',  1);
    url.searchParams.set('limit', document.querySelector('.mp-page-size select')?.value || 20);
    window.location.href = url.toString();
}

// ── Pagination ────────────────────────────────────────────────
function goToPage(pg) {
    const url = new URL(window.location);
    url.searchParams.set('page',  pg);
    url.searchParams.set('limit', document.querySelector('.mp-page-size select')?.value || 20);
    window.location.href = url.toString();
}
function changeRowsPerPage(val) {
    const url = new URL(window.location);
    url.searchParams.set('limit', val); url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

// ── Edit modal ────────────────────────────────────────────────
function openEditModal(priceId) {
    document.getElementById('editModalSpinner').style.display = 'block';
    document.getElementById('editModalContent').style.display = 'none';
    document.getElementById('editSaveBtn').disabled = true;
    openModal('editPriceModal');

    fetch(`?get_price=${priceId}`)
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(d => {
            if (d.error) throw new Error(d.error);
            document.getElementById('edit_price_id').value = d.id;
            mpSetVal('edit_country',    d.country_admin_0);
            mpSetVal('edit_market',     d.market_id);
            mpSetVal('edit_category',   d.category);
            mpSetVal('edit_commodity',  d.commodity);
            mpSetVal('edit_packaging',  d.weight);
            mpSetVal('edit_measuring',  d.unit);
            mpSetVal('edit_variety',    d.variety     || '');
            mpSetVal('edit_data_source',d.data_source || '');
            mpSetVal('edit_price_type', d.price_type);
            mpSetVal('edit_price',      d.Price);
            mpSetVal('edit_status',     d.status);
            document.getElementById('editModalSpinner').style.display = 'none';
            document.getElementById('editModalContent').style.display = 'block';
            document.getElementById('editSaveBtn').disabled = false;
        })
        .catch(err => { closeModal('editPriceModal'); alert('Failed to load price record: ' + err.message); });
}

function mpSetVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val ?? '';
}

// ── Add modal commodity AJAX ──────────────────────────────────
function loadAddCommodities() {
    const marketId     = document.getElementById('add_market').value;
    const commoditySel = document.getElementById('add_commodity');
    commoditySel.innerHTML = '<option value="" disabled selected>Loading…</option>';
    ['add_packaging','add_measuring','add_variety','add_data_source'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
    });
    ['add_ws_hint','add_rt_hint'].forEach(id => {
        const el = document.getElementById(id); if (el) el.textContent = '';
    });
    if (!marketId) { commoditySel.innerHTML = '<option value="" disabled selected>Select market first</option>'; return; }

    fetch(`get_market_commodities.php?market_id=${marketId}`)
        .then(r => r.json())
        .then(data => {
            commoditySel.innerHTML = '<option value="" disabled selected>Select commodity</option>';
            if (data.success && data.data?.commodities?.length) {
                window._mpAddCommodities = data.data.commodities;
                data.data.commodities.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id; o.textContent = c.name;
                    commoditySel.appendChild(o);
                });
                const ds = document.getElementById('add_data_source');
                if (ds && data.data.data_source) ds.value = data.data.data_source;
            } else {
                commoditySel.innerHTML = '<option value="" disabled selected>No commodities found</option>';
            }
        })
        .catch(() => { commoditySel.innerHTML = '<option value="" disabled selected>Error loading</option>'; });
}

function fillAddCommodityDetails() {
    const comList  = window._mpAddCommodities || [];
    const val      = document.getElementById('add_commodity').value;
    const selected = comList.find(c => String(c.id) === String(val));
    if (!selected) return;
    if (selected.units?.length) {
        mpSetVal('add_packaging', selected.units[0].size);
        mpSetVal('add_measuring', selected.units[0].unit);
        const wh = document.getElementById('add_ws_hint');
        const rt = document.getElementById('add_rt_hint');
        if (wh) wh.textContent = `(for ${selected.units[0].size} ${selected.units[0].unit} bag)`;
        if (rt) rt.textContent = `(per ${selected.units[0].unit})`;
    }
    if (selected.variety)       mpSetVal('add_variety',  selected.variety);
    if (selected.category_name) mpSetVal('add_category', selected.category_name);
}

// ── Init ──────────────────────────────────────────────────────
(function mpInit() {
    // Wire up search keyboard shortcuts
    ['searchMarket','searchCommodity','searchType','searchStatus'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('keydown', e => {
                if (e.key === 'Enter')  applyClientFilter();
                if (e.key === 'Escape') clearFilter();
            });
        }
    });

    // Wire add modal commodity selects
    const addMarket = document.getElementById('add_market');
    if (addMarket) addMarket.addEventListener('change', loadAddCommodities);
    const addCom = document.getElementById('add_commodity');
    if (addCom) addCom.addEventListener('change', fillAddCommodityDetails);

    // Re-open import modal on import error
    <?php if ($import_message && $import_status === 'danger'): ?>
    openModal('importModal');
    <?php endif; ?>

    if (typeof updateBreadcrumb === 'function') updateBreadcrumb('Base', 'Market Prices');

    mpSyncUI();
})();

// Spin animation for edit modal loader
const _mpSpinStyle = document.createElement('style');
_mpSpinStyle.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(_mpSpinStyle);
</script>


<?php include '../admin/includes/footer.php'; ?>