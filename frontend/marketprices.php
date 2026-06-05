<?php
// marketprices_dashboard.php — Extended Market Prices Dashboard
// Tabs: Table | Charts | Cards | Map

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

// ── JSON ENDPOINT: chart data ─────────────────────────────────
if (isset($_GET['chart_data'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $commodity_id = isset($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : 0;
    $market_id    = isset($_GET['market_id'])    ? (int)$_GET['market_id']    : 0;
    $country      = isset($_GET['country'])      ? trim($_GET['country'])      : '';
    $days         = isset($_GET['days'])         ? min((int)$_GET['days'], 365): 90;

    $where = ["mp.status IN ('published','approved')", "mp.date_posted >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
    $params = [$days]; $types = 'i';
    if ($commodity_id) { $where[] = "mp.commodity = ?"; $params[] = $commodity_id; $types .= 'i'; }
    if ($market_id)    { $where[] = "mp.market_id = ?"; $params[] = $market_id;    $types .= 'i'; }
    if ($country)      { $where[] = "mp.country_admin_0 = ?"; $params[] = $country; $types .= 's'; }

    $sql = "SELECT DATE(mp.date_posted) as date_label,
                   mp.price_type,
                   AVG(mp.Price) as avg_price,
                   MIN(mp.Price) as min_price,
                   MAX(mp.Price) as max_price,
                   COUNT(*) as record_count
            FROM market_prices mp
            WHERE " . implode(' AND ', $where) . "
            GROUP BY DATE(mp.date_posted), mp.price_type
            ORDER BY date_label ASC";
    $stmt = $con->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $rows]); exit;
}

// ── JSON ENDPOINT: filtered commodities by country/market ─────
if (isset($_GET['get_commodities'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $country   = trim($_GET['country']   ?? '');
    $market_id = (int)($_GET['market_id'] ?? 0);
    $where = []; $params = []; $types = '';
    if ($country)   { $where[] = "mp.country_admin_0 = ?"; $params[] = $country; $types .= 's'; }
    if ($market_id) { $where[] = "mp.market_id = ?"; $params[] = $market_id; $types .= 'i'; }
    $sql = "SELECT DISTINCT c.id, c.commodity_name FROM commodities c
            INNER JOIN market_prices mp ON mp.commodity = c.id"
         . ($where ? " WHERE " . implode(' AND ', $where) : '')
         . " ORDER BY c.commodity_name";
    $stmt = $con->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result(); $data = [];
    while ($row = $r->fetch_assoc()) $data[] = $row;
    $stmt->close();
    echo json_encode($data); exit;
}

// ── JSON ENDPOINT: filtered markets by country ────────────────
if (isset($_GET['get_markets'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $country = trim($_GET['country'] ?? '');
    $sql = "SELECT DISTINCT m.id, m.market_name FROM markets m
            INNER JOIN market_prices mp ON mp.market_id = m.id"
         . ($country ? " WHERE mp.country_admin_0 = ?" : '')
         . " ORDER BY m.market_name";
    $stmt = $con->prepare($sql);
    if ($country) $stmt->bind_param('s', $country);
    $stmt->execute();
    $r = $stmt->get_result(); $data = [];
    while ($row = $r->fetch_assoc()) $data[] = $row;
    $stmt->close();
    echo json_encode($data); exit;
}

// ── JSON ENDPOINT: map data ───────────────────────────────────
if (isset($_GET['map_data'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $commodity_id = isset($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : 0;
    $price_type   = in_array($_GET['price_type'] ?? '', ['Wholesale','Retail']) ? $_GET['price_type'] : 'Wholesale';
    $where = ["mp.status IN ('published','approved')", "mp.price_type = ?"];
    $params = [$price_type]; $types = 's';
    if ($commodity_id) { $where[] = "mp.commodity = ?"; $params[] = $commodity_id; $types .= 'i'; }
    $sql = "SELECT mp.market, mp.market_id, mp.country_admin_0,
                   AVG(mp.Price) as avg_price, MAX(mp.date_posted) as latest_date,
                   m.latitude, m.longitude
            FROM market_prices mp
            LEFT JOIN markets m ON mp.market_id = m.id
            WHERE " . implode(' AND ', $where) . "
            AND mp.date_posted >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND m.latitude IS NOT NULL AND m.longitude IS NOT NULL
            GROUP BY mp.market_id, mp.market, mp.country_admin_0, m.latitude, m.longitude
            ORDER BY avg_price DESC";
    $stmt = $con->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result(); $data = [];
    while ($row = $r->fetch_assoc()) $data[] = $row;
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $data]); exit;
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
    header('Location: marketprices_dashboard.php'); exit;
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

    $date_posted=date('Y-m-d H:i:s');
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
    header('Location: marketprices_dashboard.php'); exit;
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
            $date_string = trim($data[4]);
            $date_posted = null;
            $date_string = preg_replace('/\s+/', ' ', $date_string);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/', $date_string, $matches)) {
                $year = $matches[1]; $month = $matches[2]; $day = $matches[3];
                $hour = $matches[4]; $minute = $matches[5]; $second = $matches[6];
                if (checkdate($month, $day, $year)) $date_posted = "$year-$month-$day $hour:$minute:$second";
            }
            if ($date_posted === null) {
                try { $dt = new DateTime($date_string); $date_posted = $dt->format('Y-m-d H:i:s'); }
                catch (Exception $e) { $ts = strtotime($date_string); if ($ts !== false && $ts > 0) $date_posted = date('Y-m-d H:i:s', $ts); }
            }
            if ($date_posted === null || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_posted)) { $errors[] = "Row $rowNumber: Invalid date '$date_string'"; $errorCount++; continue; }
            $parsed = strtotime($date_posted);
            if ($parsed < strtotime('2020-01-01') || $parsed > strtotime('2030-12-31')) { $errors[] = "Row $rowNumber: Date out of range"; $errorCount++; continue; }

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
            $day2 = date('d', $parsed); $month2 = date('m', $parsed); $year2 = date('Y', $parsed);

            if (!in_array($price_type, ['Wholesale','Retail'])) { $errors[] = "Row $rowNumber: Invalid price type"; $errorCount++; continue; }
            if (!in_array($status, ['pending','approved','published','unpublished'])) { $errors[] = "Row $rowNumber: Invalid status"; $errorCount++; continue; }

            $market_id = 0;
            $market_stmt = $con->prepare("SELECT id FROM markets WHERE market_name = ? LIMIT 1");
            $market_stmt->bind_param('s', $market); $market_stmt->execute();
            $mr = $market_stmt->get_result();
            if ($mr->num_rows > 0) { $market_id = $mr->fetch_assoc()['id']; }
            else { $errors[] = "Row $rowNumber: Market '$market' not found"; $errorCount++; $market_stmt->close(); continue; }
            $market_stmt->close();

            $check_stmt = $con->prepare("SELECT id FROM market_prices WHERE market=? AND commodity=? AND price_type=? AND DATE(date_posted)=DATE(?)");
            $check_stmt->bind_param('siss', $market, $commodity_id, $price_type, $date_posted);
            $check_stmt->execute(); $cr = $check_stmt->get_result();
            if ($cr->num_rows > 0) {
                if ($overwrite) {
                    $us = $con->prepare("UPDATE market_prices SET Price=?,status=?,data_source=?,variety=?,weight=?,unit=?,country_admin_0=?,subject=?,supplied_volume=?,comments=?,supply_status=?,commodity_sources_data=? WHERE market=? AND commodity=? AND price_type=? AND DATE(date_posted)=DATE(?)");
                    $us->bind_param('dsssdsssissssiss', $price,$status,$data_source,$variety,$weight,$unit,$country_admin_0,$subject,$supplied_volume,$comments,$supply_status,$commodity_sources_data,$market,$commodity_id,$price_type,$date_posted);
                    if ($us->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Update failed"; $errorCount++; }
                    $us->close();
                } else { $errors[] = "Row $rowNumber: Record already exists"; $errorCount++; }
                $check_stmt->close(); continue;
            }
            $check_stmt->close();

            $is = $con->prepare("INSERT INTO market_prices (category,commodity,country_admin_0,market_id,market,weight,unit,price_type,Price,subject,day,month,year,date_posted,status,variety,data_source,supplied_volume,comments,supply_status,commodity_sources_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if (!$is) { $errors[] = "Row $rowNumber: Prepare failed"; $errorCount++; continue; }
            $bind_types = 'sisssdssdsiiissssisss';
            $is->bind_param($bind_types, $category,$commodity_id,$country_admin_0,$market_id,$market,$weight,$unit,$price_type,$price,$subject,$day2,$month2,$year2,$date_posted,$status,$variety,$data_source,$supplied_volume,$comments,$supply_status,$commodity_sources_data);
            if ($is->execute()) $successCount++; else { $errors[] = "Row $rowNumber: Insert failed - ".$is->error; $errorCount++; }
            $is->close();
        }

        if ($errorCount === 0) { $con->commit(); $_SESSION['import_message'] = "Successfully imported $successCount market prices."; $_SESSION['import_status'] = 'success'; }
        else { $con->rollback(); $_SESSION['import_message'] = "Import failed with $errorCount errors: " . implode('<br>', array_slice($errors, 0, 10)); $_SESSION['import_status'] = 'danger'; }
    } catch (Exception $e) { $con->rollback(); $_SESSION['import_message'] = "Import failed: " . $e->getMessage(); $_SESSION['import_status'] = 'danger'; }
    fclose($handle);
    header("Location: marketprices_dashboard.php"); exit;
} elseif (isset($_POST['import_csv'])) {
    $_SESSION['import_message'] = "Please select a valid CSV file."; $_SESSION['import_status'] = 'danger';
    header("Location: marketprices_dashboard.php"); exit;
}

// ── EXPORT ────────────────────────────────────────────────────
if (isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    $selected_ids = $_POST['selected_ids'] ?? [];
    $export_all = isset($_POST['export_all']) && $_POST['export_all'] == 'true';
    $data = [];
    if ($export_all) {
        $sql = "SELECT p.market,c.commodity_name as commodity,p.price_type,p.Price as price,p.date_posted,p.status,p.data_source as source,p.variety FROM market_prices p LEFT JOIN commodities c ON p.commodity=c.id ORDER BY p.date_posted DESC";
        $result = $con->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) $data[] = $row; }
    } elseif (!empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $sql = "SELECT p.market,c.commodity_name as commodity,p.price_type,p.Price as price,p.date_posted,p.status,p.data_source as source,p.variety FROM market_prices p LEFT JOIN commodities c ON p.commodity=c.id WHERE p.id IN ($ids) ORDER BY p.date_posted DESC";
        $result = $con->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) $data[] = $row; }
    }
    if ($format == 'excel' || $format == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="market_prices_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w'); fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Market','Commodity','Price Type','Price','Date Posted','Status','Source','Variety']);
        foreach ($data as $r) fputcsv($out, [$r['market'],$r['commodity'],$r['price_type'],$r['price'],$r['date_posted'],$r['status'],$r['source'],$r['variety']]);
        fclose($out); exit;
    } elseif ($format == 'pdf') { ?>
<!DOCTYPE html><html><head><title>Market Prices Export</title><style>body{font-family:Arial}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px}th{background:#f2f2f2}</style></head>
<body><h1>Market Prices Export</h1><p>Exported: <?= date('Y-m-d H:i:s') ?> | Records: <?= count($data) ?></p>
<table><thead><tr><th>Market</th><th>Commodity</th><th>Type</th><th>Price ($)</th><th>Date</th><th>Status</th><th>Source</th><th>Variety</th></tr></thead><tbody>
<?php foreach ($data as $row): ?><tr><td><?= htmlspecialchars($row['market']) ?></td><td><?= htmlspecialchars($row['commodity']) ?></td><td><?= htmlspecialchars($row['price_type']) ?></td><td><?= htmlspecialchars($row['price']) ?></td><td><?= htmlspecialchars($row['date_posted']) ?></td><td><?= htmlspecialchars($row['status']) ?></td><td><?= htmlspecialchars($row['source']) ?></td><td><?= htmlspecialchars($row['variety']) ?></td></tr><?php endforeach; ?>
</tbody></table><script>window.onload=function(){window.print();}</script></body></html>
<?php exit; } }

// ── PAGE SETUP ────────────────────────────────────────────────
include '../admin/includes/admin_header.php';

$import_message = null; $import_status = null;
if (isset($_SESSION['import_message'])) {
    $import_message = $_SESSION['import_message']; $import_status = $_SESSION['import_status'];
    unset($_SESSION['import_message']); unset($_SESSION['import_status']);
}

function getPricesData($con, $limit = 20, $offset = 0, $sort_col = 'date_posted', $sort_dir = 'DESC') {
    $allowed = ['market'=>'p.market','commodity'=>'c.commodity_name','date_posted'=>'p.date_posted','price_type'=>'p.price_type','Price'=>'p.Price','status'=>'p.status'];
    $order_by = $allowed[$sort_col] ?? 'p.date_posted';
    $dir = $sort_dir === 'ASC' ? 'ASC' : 'DESC';
    $sql = "SELECT p.id,p.market,p.commodity,c.commodity_name,c.variety,
                   CONCAT(c.commodity_name,IF(c.variety IS NOT NULL AND c.variety!='',CONCAT(' (',c.variety,')'),'')) AS commodity_display,
                   p.price_type,p.Price,p.date_posted,p.status,p.data_source,p.market_id,p.category,p.weight,p.unit
            FROM market_prices p LEFT JOIN commodities c ON p.commodity=c.id
            ORDER BY $order_by $dir, p.date_posted DESC LIMIT $limit OFFSET $offset";
    $result = $con->query($sql); $data = [];
    if ($result) { while ($row = $result->fetch_assoc()) $data[] = $row; $result->free(); }
    return $data;
}

function getTotalPriceRecords($con) {
    $r = $con->query("SELECT count(*) as total FROM market_prices");
    if ($r) { $row = $r->fetch_assoc(); return $row['total']; }
    return 0;
}

$sort_column    = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$search_market  = trim($_GET['search_market'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');
$total_records  = getTotalPriceRecords($con);
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if (!in_array($limit, [10,20,50,100])) $limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$prices_data = getPricesData($con, $limit, $offset, $sort_column, $sort_direction);
$total_pages = ceil($total_records / $limit);

function getStatusBadge($status) {
    $map = ['pending'=>'mp-badge-pending','published'=>'mp-badge-published','approved'=>'mp-badge-approved','unpublished'=>'mp-badge-unpublished'];
    $cls = $map[$status] ?? '';
    return '<span class="mp-badge '.$cls.'">'.ucfirst($status).'</span>';
}

function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $stmt = $con->prepare("SELECT Price FROM market_prices WHERE commodity=? AND market=? AND price_type=? AND DATE(date_posted)<DATE(?) ORDER BY date_posted DESC LIMIT 1");
    if (!$stmt) return 'N/A';
    $stmt->bind_param('isss', $commodityId, $market, $priceType, $currentDate);
    $stmt->execute(); $r = $stmt->get_result();
    if ($r && $r->num_rows > 0) { $prev = $r->fetch_assoc(); if ($prev['Price'] != 0) { $c = (($currentPrice - $prev['Price']) / $prev['Price']) * 100; $stmt->close(); return round($c, 2).'%'; } }
    $stmt->close(); return 'N/A';
}

function calculateMoMChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $ago = date('Y-m-d', strtotime($currentDate . ' -30 days'));
    $stmt = $con->prepare("SELECT Price,ABS(DATEDIFF(DATE(date_posted),?)) as dd FROM market_prices WHERE commodity=? AND market=? AND price_type=? AND DATE(date_posted) BETWEEN DATE_SUB(?,INTERVAL 35 DAY) AND DATE_SUB(?,INTERVAL 25 DAY) ORDER BY dd ASC LIMIT 1");
    if (!$stmt) return 'N/A';
    $stmt->bind_param('sissss', $ago, $commodityId, $market, $priceType, $ago, $ago);
    $stmt->execute(); $r = $stmt->get_result();
    if ($r && $r->num_rows > 0) { $d = $r->fetch_assoc(); if ($d['Price'] != 0) { $c = (($currentPrice - $d['Price']) / $d['Price']) * 100; $stmt->close(); return round($c, 2).'%'; } }
    $stmt->close(); return 'N/A';
}

// ── STATS ──────────────────────────────────────────────────────
$total_prices    = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices")->fetch_assoc())['t'] ?? 0);
$pending_count   = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='pending'")->fetch_assoc())['t'] ?? 0);
$published_count = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);
$wholesale_count = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE price_type='Wholesale'")->fetch_assoc())['t'] ?? 0);

// ── DATA FOR MODALS ────────────────────────────────────────────
$markets_for_modal = []; $mr = $con->query("SELECT id,market_name FROM markets ORDER BY market_name");
if ($mr) { while ($r = $mr->fetch_assoc()) $markets_for_modal[] = $r; }
$commodities_for_modal = []; $cr = $con->query("SELECT id,commodity_name FROM commodities ORDER BY commodity_name");
if ($cr) { while ($r = $cr->fetch_assoc()) $commodities_for_modal[] = $r; }

// Distinct countries in DB
$countries_in_db = [];
$ctr = $con->query("SELECT DISTINCT country_admin_0 FROM market_prices WHERE country_admin_0 != '' ORDER BY country_admin_0");
if ($ctr) { while ($r = $ctr->fetch_assoc()) $countries_in_db[] = $r['country_admin_0']; }

// All markets for chart/cards filter (with country info)
$all_markets = [];
$amr = $con->query("SELECT DISTINCT mp.market_id, mp.market, mp.country_admin_0 FROM market_prices mp ORDER BY mp.country_admin_0, mp.market");
if ($amr) { while ($r = $amr->fetch_assoc()) $all_markets[] = $r; }

// All commodities for chart/cards filter
$all_commodities_q = $con->query("SELECT DISTINCT c.id, c.commodity_name FROM commodities c INNER JOIN market_prices mp ON mp.commodity=c.id ORDER BY c.commodity_name");
$all_commodities = [];
if ($all_commodities_q) { while ($r = $all_commodities_q->fetch_assoc()) $all_commodities[] = $r; }

$modal_countries  = ['Kenya','Uganda','Tanzania','Rwanda','Burundi','Ethiopia','South Sudan'];
$modal_categories = ['Cereals','Pulses','Oil seeds','Vegetables','Fruits','Livestock'];
$modal_units      = ['kg','tons','g','lb','litres','pieces','bags'];

$active_tab = $_GET['tab'] ?? 'table';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<style>
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
.mp-wrap { min-height: 100vh; padding: 0 0 60px; font-family: 'Segoe UI', system-ui, sans-serif; color: var(--mp-text); }
.mp-page-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 4px; }
.mp-page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--mp-primary); margin: 0; }
.mp-page-header p  { font-size: .875rem; color: var(--mp-muted); margin: 4px 0 0; }
.mp-accent-bar { height: 3px; background: linear-gradient(90deg, var(--mp-green) 0%, var(--mp-primary) 50%, var(--mp-green) 100%); border-radius: 99px; margin: 10px 0 20px; }

/* ── Tab navigation ── */
.mp-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--mp-border); margin-bottom: 20px; overflow-x: auto; }
.mp-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; font-size: .875rem; font-weight: 500;
    color: var(--mp-muted); border-bottom: 2px solid transparent;
    cursor: pointer; transition: all .2s; white-space: nowrap;
    margin-bottom: -2px; text-decoration: none;
    background: none; border-top: none; border-left: none; border-right: none;
}
.mp-tab:hover { color: var(--mp-primary); background: rgba(128,0,0,.03); }
.mp-tab.active { color: var(--mp-primary); border-bottom-color: var(--mp-primary); font-weight: 600; }
.mp-tab .ms { font-size: 1.1rem; }

/* ── Tab panels ── */
.mp-panel { display: none; }
.mp-panel.active { display: block; }

/* ── Stat cards ── */
.mp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.mp-stat-card { background: var(--mp-card); border-radius: var(--mp-radius); padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,.06); border-left: 4px solid var(--mp-primary); transition: transform .2s, box-shadow .2s; }
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
.mp-alert { padding: 10px 14px; border-radius: var(--mp-radius); font-size: .875rem; display: flex; align-items: center; gap: 8px; margin-bottom: 14px; border-left: 4px solid transparent; }
.mp-alert.success { background: #f0fdf4; color: #15803d; border-left-color: #16a34a; }
.mp-alert.danger  { background: #fef2f2; color: #dc2626; border-left-color: #dc2626; }

/* ── Toolbar ── */
.mp-toolbar { background: var(--mp-card); border-radius: var(--mp-radius); padding: 12px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 14px; }
.mp-toolbar-left  { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.mp-toolbar-right { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

/* ── Buttons ── */
.mp-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 6px; font-size: .8125rem; font-weight: 500; border: 1px solid var(--mp-border); background: white; color: var(--mp-text); cursor: pointer; transition: all .2s; white-space: nowrap; }
.mp-btn:hover { background: #f3f4f6; }
.mp-btn.primary  { background: var(--mp-primary); color: white; border-color: var(--mp-primary); }
.mp-btn.primary:hover { background: var(--mp-primary-dk); }
.mp-btn.success  { background: #16a34a; color: white; border-color: #16a34a; }
.mp-btn.info     { background: #0891b2; color: white; border-color: #0891b2; }
.mp-btn.warning  { background: #d97706; color: white; border-color: #d97706; }
.mp-btn.danger   { background: #dc2626; color: white; border-color: #dc2626; }
.mp-btn.ghost    { background: transparent; border-color: var(--mp-border); color: var(--mp-muted); }
.mp-btn.ghost:hover { background: #f9fafb; color: var(--mp-text); }
.mp-btn:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }
.mp-badge-count  { background: rgba(255,255,255,.25); color: white; font-size: .7rem; font-weight: 700; padding: 1px 7px; border-radius: 99px; margin-left: 2px; }
.mp-badge-count.dark { background: rgba(0,0,0,.1); color: inherit; }

/* ── Dropdown ── */
.mp-dropdown { position: relative; }
.mp-dropdown-menu { position: absolute; top: calc(100% + 4px); left: 0; min-width: 190px; z-index: 200; background: white; border: 1px solid var(--mp-border); border-radius: var(--mp-radius); box-shadow: 0 8px 24px rgba(0,0,0,.1); display: none; }
.mp-dropdown-menu.open { display: block; }
.mp-dropdown-item { display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: .8125rem; color: var(--mp-text); cursor: pointer; transition: background .15s; }
.mp-dropdown-item:hover { background: #f9fafb; }
.mp-dropdown-divider { border: none; border-top: 1px solid var(--mp-border); margin: 4px 0; }

/* ── Search bar ── */
.mp-search-bar { background: var(--mp-card); border-radius: var(--mp-radius); padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 14px; }
.mp-search-field { position: relative; flex: 1; min-width: 150px; }
.mp-search-field input, .mp-search-field select { width: 100%; padding: 6px 10px 6px 32px; border: 1px solid var(--mp-border); border-radius: 6px; font-size: .8125rem; color: var(--mp-text); transition: border-color .2s; box-sizing: border-box; background: white; }
.mp-search-field input:focus, .mp-search-field select:focus { outline: none; border-color: var(--mp-primary); box-shadow: 0 0 0 3px rgba(128,0,0,.1); }
.mp-search-field select { padding-left: 32px; }
.mp-search-icon { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: var(--mp-muted); font-size: 1rem; pointer-events: none; z-index: 1; }

/* ── Table ── */
.mp-table-card { background: var(--mp-card); border-radius: var(--mp-radius); box-shadow: 0 1px 3px rgba(0,0,0,.06); overflow: hidden; }
.mp-table-wrap { overflow-x: auto; }
.mp-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.mp-table thead tr { background: #f8f9fa; }
.mp-table th { padding: 10px 12px; text-align: left; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--mp-muted); border-bottom: 2px solid var(--mp-border); white-space: nowrap; }
.mp-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.mp-table tbody tr:hover { background: #fefaf5; }
.mp-table tbody tr.mp-selected { background: rgba(128,0,0,.06) !important; }
.mp-table td.muted { color: var(--mp-muted); font-size: .75rem; }

/* ── Badges ── */
.mp-badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 99px; font-size: .7rem; font-weight: 600; }
.mp-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.mp-badge-pending    { background: #fef3c7; color: #92400e; } .mp-badge-pending::before    { background: #d97706; }
.mp-badge-published  { background: #dcfce7; color: #166534; } .mp-badge-published::before  { background: #16a34a; }
.mp-badge-approved   { background: #e0f2fe; color: #075985; } .mp-badge-approved::before   { background: #0891b2; }
.mp-badge-unpublished{ background: #fee2e2; color: #991b1b; } .mp-badge-unpublished::before{ background: #dc2626; }

/* ── Price / change ── */
.mp-price { font-family: 'Courier New', monospace; font-weight: 700; font-size: .875rem; }
.mp-change { display: inline-flex; align-items: center; gap: 2px; font-size: .7rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; }
.mp-change.up   { background: #dcfce7; color: #16a34a; }
.mp-change.down { background: #fee2e2; color: #dc2626; }
.mp-change.flat { background: #f3f4f6; color: var(--mp-muted); }

/* ── Action btns ── */
.mp-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer; transition: all .2s; background: #f3f4f6; color: var(--mp-muted); }
.mp-action-btn:hover { background: #e0f2fe; color: #0891b2; }

/* ── Pagination ── */
.mp-pagination-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--mp-border); background: var(--mp-card); }
.mp-pagination-info { font-size: .8125rem; color: var(--mp-muted); }
.mp-pagination-nav  { display: flex; align-items: center; gap: 4px; }
.mp-pg-btn { min-width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; font-size: .75rem; border: 1px solid var(--mp-border); background: white; color: var(--mp-text); cursor: pointer; transition: all .2s; padding: 0 4px; }
.mp-pg-btn:hover:not(:disabled):not(.active) { background: #fef3e7; border-color: var(--mp-primary); color: var(--mp-primary); }
.mp-pg-btn.active { background: var(--mp-primary); border-color: var(--mp-primary); color: white; font-weight: 700; }
.mp-pg-btn:disabled { opacity: .35; cursor: not-allowed; }
.mp-page-size select { font-size: .75rem; padding: 3px 8px; border: 1px solid var(--mp-border); border-radius: 6px; background: white; cursor: pointer; }

/* ── Modal ── */
.mp-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 500; display: none; overflow-y: auto; }
.mp-modal-backdrop.open { display: block; }
.mp-modal-center { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.mp-modal-box { background: white; border-radius: var(--mp-radius); width: 100%; max-width: 560px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
.mp-modal-box.wide { max-width: 700px; }
.mp-modal-header { background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-green) 100%); padding: 14px 18px; border-radius: var(--mp-radius) var(--mp-radius) 0 0; display: flex; align-items: center; justify-content: space-between; color: white; }
.mp-modal-header h3 { margin: 0; font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.mp-modal-header button { background: none; border: none; color: rgba(255,255,255,.8); cursor: pointer; font-size: 1.25rem; line-height: 1; }
.mp-modal-body  { padding: 18px; }
.mp-modal-footer { padding: 14px 18px; border-top: 1px solid var(--mp-border); display: flex; justify-content: flex-end; gap: 8px; }
.mp-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.mp-form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.mp-form-group label { font-size: .8125rem; font-weight: 600; color: var(--mp-text); }
.mp-form-group input, .mp-form-group select { padding: 7px 10px; border: 1px solid var(--mp-border); border-radius: 6px; font-size: .8125rem; color: var(--mp-text); }
.mp-form-group input:focus, .mp-form-group select:focus { outline: none; border-color: var(--mp-primary); box-shadow: 0 0 0 3px rgba(128,0,0,.1); }
input[type="checkbox"].mp-check { width: 15px; height: 15px; cursor: pointer; accent-color: var(--mp-primary); }
.mp-checkbox-row { display: flex; align-items: center; gap: 8px; font-size: .8125rem; color: var(--mp-text); margin-bottom: 14px; cursor: pointer; }

/* ── Sortable ── */
.mp-th-sort { cursor: pointer; user-select: none; white-space: nowrap; }
.mp-th-sort:hover { color: var(--mp-primary); }
.mp-sort-icon { font-size: .65rem; margin-left: 3px; opacity: .5; vertical-align: middle; }
.mp-th-sort.active-sort { color: var(--mp-primary); }
.mp-th-sort.active-sort .mp-sort-icon { opacity: 1; }

/* ── Row groups ── */
.mp-row-first { border-top: 2px solid #e5e7eb !important; }
.mp-row-first:first-child { border-top: none !important; }
.mp-group-even { background: #fafafa; }
.mp-group-even:hover { background: #f5f5f5 !important; }
.mp-row-cont td.mp-shared-cell { color: transparent !important; user-select: none; pointer-events: none; }
.mp-row-cont td.mp-shared-cell * { visibility: hidden; }

/* ── Material icons ── */
.ms { font-family: 'Material Symbols Outlined' !important; font-style: normal; font-weight: normal; line-height: 1; letter-spacing: normal; text-transform: none; display: inline-block; white-space: nowrap; direction: ltr; -webkit-font-smoothing: antialiased; vertical-align: middle; }

/* ── Chart panel ── */
.mp-chart-panel { background: var(--mp-card); border-radius: var(--mp-radius); box-shadow: 0 1px 3px rgba(0,0,0,.06); padding: 20px; margin-bottom: 16px; }
.mp-chart-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--mp-border); }
.mp-chart-filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 160px; flex: 1; }
.mp-chart-filter-group label { font-size: .75rem; font-weight: 600; color: var(--mp-muted); text-transform: uppercase; letter-spacing: .05em; }
.mp-chart-filter-group select { padding: 7px 10px; border: 1px solid var(--mp-border); border-radius: 6px; font-size: .8125rem; color: var(--mp-text); background: white; width: 100%; }
.mp-chart-filter-group select:focus { outline: none; border-color: var(--mp-primary); }

/* ── Chart legend ── */
.mp-chart-legend { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 12px; }
.mp-legend-item { display: flex; align-items: center; gap: 8px; font-size: .8125rem; color: var(--mp-text); }
.mp-legend-dot { width: 12px; height: 3px; border-radius: 2px; }
.mp-legend-value { font-weight: 700; color: var(--mp-text); margin-left: 4px; }

/* ── Chart stats row ── */
.mp-chart-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 16px; }
.mp-chart-stat { background: #f8f9fa; border-radius: 8px; padding: 10px 14px; }
.mp-chart-stat-label { font-size: .7rem; color: var(--mp-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
.mp-chart-stat-value { font-size: 1.1rem; font-weight: 700; color: var(--mp-text); }
.mp-chart-stat-sub { font-size: .7rem; color: var(--mp-muted); margin-top: 2px; }

/* ── Cards view ── */
.mp-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.mp-commodity-card { background: var(--mp-card); border-radius: var(--mp-radius); box-shadow: 0 1px 3px rgba(0,0,0,.06); border: 1px solid var(--mp-border); overflow: hidden; transition: box-shadow .2s, transform .2s; }
.mp-commodity-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
.mp-card-header { padding: 12px 14px; border-bottom: 1px solid var(--mp-border); display: flex; align-items: center; justify-content: space-between; }
.mp-card-commodity { font-weight: 700; font-size: .9375rem; color: var(--mp-text); }
.mp-card-market { font-size: .75rem; color: var(--mp-muted); margin-top: 2px; display: flex; align-items: center; gap: 4px; }
.mp-card-body { padding: 12px 14px; }
.mp-card-prices { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.mp-card-price-box { background: #f8f9fa; border-radius: 8px; padding: 10px 12px; }
.mp-card-price-type { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
.mp-card-price-type.ws { color: #6d28d9; }
.mp-card-price-type.rt { color: #be185d; }
.mp-card-price-val { font-size: 1.25rem; font-weight: 700; font-family: 'Courier New', monospace; color: var(--mp-text); }
.mp-card-price-change { font-size: .7rem; margin-top: 2px; }
.mp-card-footer { padding: 8px 14px; border-top: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; font-size: .75rem; color: var(--mp-muted); }
.mp-card-flag { font-size: 1rem; }

/* ── Cards filter bar ── */
.mp-cards-filters { background: var(--mp-card); border-radius: var(--mp-radius); padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.mp-filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 160px; flex: 1; }
.mp-filter-group label { font-size: .75rem; font-weight: 600; color: var(--mp-muted); text-transform: uppercase; letter-spacing: .05em; }
.mp-filter-group select, .mp-filter-group input { padding: 7px 10px; border: 1px solid var(--mp-border); border-radius: 6px; font-size: .8125rem; color: var(--mp-text); background: white; width: 100%; box-sizing: border-box; }
.mp-filter-group select:focus, .mp-filter-group input:focus { outline: none; border-color: var(--mp-primary); }

/* ── Map panel ── */
.mp-map-container { background: var(--mp-card); border-radius: var(--mp-radius); box-shadow: 0 1px 3px rgba(0,0,0,.06); overflow: hidden; }
.mp-map-filters { padding: 14px 16px; border-bottom: 1px solid var(--mp-border); display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
#mp-map { width: 100%; height: 520px; }
.mp-map-legend { padding: 12px 16px; border-top: 1px solid var(--mp-border); display: flex; align-items: center; gap: 16px; flex-wrap: wrap; font-size: .8125rem; color: var(--mp-muted); }
.mp-map-legend-title { font-weight: 600; color: var(--mp-text); }
.mp-map-legend-gradient { width: 140px; height: 12px; border-radius: 6px; background: linear-gradient(90deg, #bee3f8 0%, #2b6cb0 100%); }
.mp-map-legend-labels { display: flex; justify-content: space-between; width: 140px; font-size: .7rem; }
.mp-map-tooltip { position: absolute; background: white; border: 1px solid var(--mp-border); border-radius: 8px; padding: 10px 14px; box-shadow: 0 4px 16px rgba(0,0,0,.12); pointer-events: none; font-size: .8125rem; z-index: 100; display: none; min-width: 180px; }
.mp-map-tooltip-market { font-weight: 700; color: var(--mp-text); margin-bottom: 4px; }
.mp-map-tooltip-price  { font-size: 1.1rem; font-weight: 700; font-family: 'Courier New', monospace; color: var(--mp-primary); }

/* ── No data state ── */
.mp-no-data { text-align: center; padding: 50px 20px; color: var(--mp-muted); }
.mp-no-data .ms { font-size: 3rem; opacity: .3; display: block; margin-bottom: 12px; }
.mp-no-data p { font-size: .9rem; margin: 0; }

/* ── Loading spinner ── */
.mp-loading { text-align: center; padding: 40px; color: var(--mp-muted); }
@keyframes mpspin { to { transform: rotate(360deg); } }
.mp-spinner { animation: mpspin 1s linear infinite; display: inline-block; }

/* ── Import info ── */
.mp-import-info { background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 0 6px 6px 0; padding: 14px; margin-bottom: 16px; font-size: .8125rem; }
.mp-import-info h5 { color: #1d4ed8; font-size: .875rem; margin: 0 0 8px; }
.mp-import-info ol { margin: 0; padding-left: 18px; color: #1e40af; }
.mp-import-info li { margin-bottom: 3px; }
.mp-template-link { display: inline-flex; align-items: center; gap: 4px; margin-top: 10px; color: #2563eb; font-size: .8rem; font-weight: 500; text-decoration: none; }

@media (max-width: 768px) {
    .mp-stats { grid-template-columns: repeat(2, 1fr); }
    .mp-form-row { grid-template-columns: 1fr; }
    .mp-chart-filters { flex-direction: column; }
    .mp-cards-grid { grid-template-columns: 1fr; }
}
</style>

<div class="mp-wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    <!-- ── Page Header ── -->
    <div class="mp-page-header">
        <div>
            <h1><span class="ms" style="font-size:1.4rem;margin-right:6px;">monitoring</span>Market Prices Dashboard</h1>
            <p>Explore and analyse commodity price trends across markets and countries</p>
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
            <div><div class="mp-stat-label">Total Prices</div><div class="mp-stat-value"><?= number_format($total_prices) ?></div></div>
            <span class="ms mp-stat-icon ic-total" style="font-size:2.2rem;">monitoring</span>
        </div>
        <div class="mp-stat-card stat-pending">
            <div><div class="mp-stat-label">Pending</div><div class="mp-stat-value"><?= number_format($pending_count) ?></div></div>
            <span class="ms mp-stat-icon ic-pending" style="font-size:2.2rem;">schedule</span>
        </div>
        <div class="mp-stat-card stat-published">
            <div><div class="mp-stat-label">Published</div><div class="mp-stat-value"><?= number_format($published_count) ?></div></div>
            <span class="ms mp-stat-icon ic-published" style="font-size:2.2rem;">check_circle</span>
        </div>
        <div class="mp-stat-card stat-wholesale">
            <div><div class="mp-stat-label">Wholesale</div><div class="mp-stat-value"><?= number_format($wholesale_count) ?></div></div>
            <span class="ms mp-stat-icon ic-wholesale" style="font-size:2.2rem;">balance</span>
        </div>
    </div>

    <!-- ── Tab Navigation ── -->
    <div class="mp-tabs" role="tablist">
        <button class="mp-tab <?= $active_tab==='table'  ? 'active' : '' ?>" onclick="switchTab('table')"  role="tab">
            <span class="ms">table_rows</span> Table View
        </button>
        <button class="mp-tab <?= $active_tab==='charts' ? 'active' : '' ?>" onclick="switchTab('charts')" role="tab">
            <span class="ms">show_chart</span> Price Trends
        </button>
        <button class="mp-tab <?= $active_tab==='cards'  ? 'active' : '' ?>" onclick="switchTab('cards')"  role="tab">
            <span class="ms">grid_view</span> Cards View
        </button>
        <button class="mp-tab <?= $active_tab==='map'    ? 'active' : '' ?>" onclick="switchTab('map')"    role="tab">
            <span class="ms">map</span> Map View
        </button>
    </div>

    <!-- ══════════════════════════════════════
         TAB 1: TABLE VIEW
    ══════════════════════════════════════ -->
    <div id="panel-table" class="mp-panel <?= $active_tab==='table' ? 'active' : '' ?>">

        <!-- Toolbar -->
        <div class="mp-toolbar">
            <div class="mp-toolbar-left">
                <button class="mp-btn danger" id="bulkDeleteBtn" disabled onclick="deleteSelected()">
                    <span class="ms">delete</span> Delete
                    <span class="mp-badge-count dark" id="selectedCount">0</span>
                </button>
                <button class="mp-btn ghost" onclick="clearAllSelections()">
                    <span class="ms">clear</span> Clear
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
                    </div>
                </div>
                <button class="mp-btn" onclick="openModal('importModal')">
                    <span class="ms">upload_file</span> Import CSV
                </button>
            </div>
        </div>

        <!-- Search -->
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
            <button class="mp-btn primary" onclick="applyClientFilter()"><span class="ms">search</span> Filter</button>
            <button class="mp-btn ghost" onclick="clearFilter()"><span class="ms">close</span></button>
        </div>

        <!-- Table -->
        <div class="mp-table-card">
            <div class="mp-table-wrap">
                <table class="mp-table" id="pricesTable">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" class="mp-check" id="selectAll" onchange="mpSelectAll(this)"></th>
                            <?php
                            $sort_cols = ['market'=>'Market','commodity'=>'Commodity','date_posted'=>'Date','price_type'=>'Type','Price'=>'Price (USD)'];
                            foreach ($sort_cols as $ck => $cl):
                                $ia = ($sort_column===$ck); $nd = ($ia&&$sort_direction==='DESC')?'asc':'desc'; $ic=$ia?($sort_direction==='ASC'?'↑':'↓'):'↕';
                            ?>
                            <th class="mp-th-sort <?= $ia?'active-sort':'' ?>" onclick="mpSortTable('<?= $ck ?>','<?= $nd ?>')"><?= $cl ?><span class="mp-sort-icon"><?= $ic ?></span></th>
                            <?php endforeach; ?>
                            <th>Day Δ</th>
                            <th>Month Δ</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody id="pricesTableBody">
                    <?php
                    $grouped_data = [];
                    foreach ($prices_data as $price) {
                        $gk = date('Y-m-d', strtotime($price['date_posted'])) . '_' . $price['market'] . '_' . $price['commodity'];
                        $grouped_data[$gk][] = $price;
                    }
                    $gi = 0;
                    foreach ($grouped_data as $gk => $grp):
                        usort($grp, function($a,$b){ $o=['Wholesale'=>0,'Retail'=>1]; return ($o[$a['price_type']]??9)-($o[$b['price_type']]??9); });
                        $gids = array_column($grp,'id');
                        $gids_json = htmlspecialchars(json_encode($gids));
                        $gb = $gi++ % 2 === 1 ? 'mp-group-even' : '';
                        foreach ($grp as $ri => $price):
                            $isf = ($ri===0);
                            $dc = calculateDoDChange($price['Price'],$price['commodity'],$price['market'],$price['price_type'],$price['date_posted'],$con);
                            $mc = calculateMoMChange($price['Price'],$price['commodity'],$price['market'],$price['price_type'],$price['date_posted'],$con);
                            $dcc='flat'; $mcc='flat';
                            if ($dc!=='N/A') $dcc=floatval($dc)>=0?'up':'down';
                            if ($mc!=='N/A') $mcc=floatval($mc)>=0?'up':'down';
                            $di=$dcc==='up'?'▲':($dcc==='down'?'▼':'–');
                            $mi=$mcc==='up'?'▲':($mcc==='down'?'▼':'–');
                    ?>
                    <tr class="price-row <?= $gb ?> <?= $isf?'mp-row-first':'mp-row-cont' ?>"
                        data-price-id="<?= $price['id'] ?>"
                        data-group-key="<?= htmlspecialchars($gk) ?>"
                        data-group-ids="<?= $gids_json ?>"
                        data-market="<?= htmlspecialchars(strtolower($price['market'])) ?>"
                        data-commodity="<?= htmlspecialchars(strtolower($price['commodity_display'])) ?>"
                        data-type="<?= htmlspecialchars(strtolower($price['price_type'])) ?>"
                        data-status="<?= htmlspecialchars(strtolower($price['status'])) ?>">
                        <td><?php if($isf): ?><input type="checkbox" class="mp-check row-checkbox" data-group-key="<?= htmlspecialchars($gk) ?>" data-group-ids="<?= $gids_json ?>" onchange="mpCheckboxChange(this)"><?php endif; ?></td>
                        <td class="mp-shared-cell" style="font-weight:600;"><?= htmlspecialchars($price['market']) ?></td>
                        <td class="mp-shared-cell"><?= htmlspecialchars($price['commodity_display']) ?></td>
                        <td class="mp-shared-cell muted"><?= date('M d, Y', strtotime($price['date_posted'])) ?></td>
                        <td><span style="font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:4px;background:<?= $price['price_type']==='Wholesale'?'#ede9fe':'#fce7f3' ?>;color:<?= $price['price_type']==='Wholesale'?'#6d28d9':'#be185d' ?>;"><?= htmlspecialchars($price['price_type']) ?></span></td>
                        <td><span class="mp-price">$<?= number_format($price['Price'],4) ?></span></td>
                        <td><span class="mp-change <?= $dcc ?>"><?= $di ?> <?= $dc ?></span></td>
                        <td><span class="mp-change <?= $mcc ?>"><?= $mi ?> <?= $mc ?></span></td>
                        <td class="muted"><?= htmlspecialchars($price['data_source']??'') ?></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mp-pagination-bar">
                <div class="mp-pagination-info">
                    Showing <?= $offset+1 ?> – <?= min($offset+$limit,$total_records) ?> of <?= number_format($total_records) ?> records
                    <span id="selectionSummary" style="color:var(--mp-primary);font-weight:600;margin-left:6px;"></span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="mp-page-size">
                        <label style="font-size:.8rem;color:var(--mp-muted);margin-right:5px;">Rows:</label>
                        <select onchange="changeRowsPerPage(this.value)">
                            <?php foreach ([10,20,50,100] as $opt): ?><option value="<?= $opt ?>" <?= $limit==$opt?'selected':'' ?>><?= $opt ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <nav class="mp-pagination-nav">
                        <button class="mp-pg-btn" onclick="goToPage(1)" <?= $page<=1?'disabled':'' ?>><span class="ms" style="font-size:.9rem;">first_page</span></button>
                        <button class="mp-pg-btn" onclick="goToPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>><span class="ms" style="font-size:.9rem;">chevron_left</span></button>
                        <?php
                        $win=2;$sp=max(1,$page-$win);$ep=min($total_pages,$page+$win);
                        if($sp===1)$ep=min($total_pages,1+$win*2); if($ep===$total_pages)$sp=max(1,$total_pages-$win*2);
                        if($sp>1):?><button class="mp-pg-btn" onclick="goToPage(1)">1</button><?php if($sp>2):?><span style="color:var(--mp-muted);font-size:.75rem;padding:0 2px">…</span><?php endif;endif;
                        for($i=$sp;$i<=$ep;$i++):?><button class="mp-pg-btn <?=$i===$page?'active':''?>" <?=$i===$page?'':sprintf('onclick="goToPage(%d)"',$i)?>><?=$i?></button><?php endfor;
                        if($ep<$total_pages):if($ep<$total_pages-1):?><span style="color:var(--mp-muted);font-size:.75rem;padding:0 2px">…</span><?php endif;?><button class="mp-pg-btn" onclick="goToPage(<?=$total_pages?>)"><?=$total_pages?></button><?php endif;?>
                        <button class="mp-pg-btn" onclick="goToPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="ms" style="font-size:.9rem;">chevron_right</span></button>
                        <button class="mp-pg-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="ms" style="font-size:.9rem;">last_page</span></button>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         TAB 2: CHARTS VIEW
    ══════════════════════════════════════ -->
    <div id="panel-charts" class="mp-panel <?= $active_tab==='charts' ? 'active' : '' ?>">

        <div class="mp-chart-panel">
            <!-- Filters -->
            <div class="mp-chart-filters">
                <div class="mp-chart-filter-group">
                    <label>Country</label>
                    <select id="chart_country" onchange="onChartCountryChange()">
                        <option value="">All Countries</option>
                        <?php foreach ($countries_in_db as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-chart-filter-group">
                    <label>Market</label>
                    <select id="chart_market" onchange="onChartMarketChange()">
                        <option value="">All Markets</option>
                        <?php foreach ($all_markets as $m): ?>
                            <option value="<?= $m['market_id'] ?>" data-country="<?= htmlspecialchars($m['country_admin_0']) ?>"><?= htmlspecialchars($m['market']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-chart-filter-group">
                    <label>Commodity</label>
                    <select id="chart_commodity" onchange="loadChartData()">
                        <option value="">All Commodities</option>
                        <?php foreach ($all_commodities as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-chart-filter-group">
                    <label>Time Range</label>
                    <select id="chart_days" onchange="loadChartData()">
                        <option value="30">Last 30 days</option>
                        <option value="60">Last 60 days</option>
                        <option value="90" selected>Last 90 days</option>
                        <option value="180">Last 6 months</option>
                        <option value="365">Last year</option>
                    </select>
                </div>
                <button class="mp-btn primary" style="align-self:flex-end;" onclick="loadChartData()">
                    <span class="ms">refresh</span> Update
                </button>
            </div>

            <!-- Chart stat cards -->
            <div class="mp-chart-stats" id="chartStats">
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Avg Wholesale</div><div class="mp-chart-stat-value" id="stat-avg-ws">—</div><div class="mp-chart-stat-sub">USD per unit</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Avg Retail</div><div class="mp-chart-stat-value" id="stat-avg-rt">—</div><div class="mp-chart-stat-sub">USD per unit</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Price Range</div><div class="mp-chart-stat-value" id="stat-range">—</div><div class="mp-chart-stat-sub">Min – Max</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Data Points</div><div class="mp-chart-stat-value" id="stat-points">—</div><div class="mp-chart-stat-sub">Records in range</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Trend (30d)</div><div class="mp-chart-stat-value" id="stat-trend">—</div><div class="mp-chart-stat-sub">Price direction</div></div>
            </div>

            <!-- Custom legend -->
            <div class="mp-chart-legend">
                <div class="mp-legend-item">
                    <div class="mp-legend-dot" style="background:#7c3aed;height:3px;"></div>
                    <span>Wholesale</span>
                    <span class="mp-legend-value" id="legend-ws-latest">—</span>
                </div>
                <div class="mp-legend-item">
                    <div class="mp-legend-dot" style="background:#db2777;height:3px;border-top:1px dashed #db2777;height:0;border-top-width:3px;width:18px;"></div>
                    <span>Retail</span>
                    <span class="mp-legend-value" id="legend-rt-latest">—</span>
                </div>
                <div id="chartLoadingIndicator" style="font-size:.8rem;color:var(--mp-muted);display:none;margin-left:auto;">
                    <span class="ms mp-spinner">hourglass_empty</span> Loading…
                </div>
            </div>

            <!-- Chart canvas -->
            <div style="position:relative;width:100%;height:360px;">
                <canvas id="priceChart" role="img" aria-label="Line chart showing wholesale and retail price trends over time">Price trend data</canvas>
            </div>

            <!-- Tooltip hint -->
            <p style="font-size:.75rem;color:var(--mp-muted);margin-top:10px;text-align:right;">
                <span class="ms" style="font-size:.85rem;">info</span> Hover over data points for exact values. Click legend items to show/hide series.
            </p>
        </div>

        <!-- Secondary: spread chart -->
        <div class="mp-chart-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div>
                    <h3 style="margin:0;font-size:1rem;font-weight:600;">Retail–Wholesale Spread</h3>
                    <p style="margin:4px 0 0;font-size:.8rem;color:var(--mp-muted);">Gap between retail and wholesale prices over time</p>
                </div>
            </div>
            <div style="position:relative;width:100%;height:200px;">
                <canvas id="spreadChart" role="img" aria-label="Area chart showing the spread between retail and wholesale prices">Spread data</canvas>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         TAB 3: CARDS VIEW
    ══════════════════════════════════════ -->
    <div id="panel-cards" class="mp-panel <?= $active_tab==='cards' ? 'active' : '' ?>">

        <!-- Filter bar -->
        <div class="mp-cards-filters">
            <div class="mp-filter-group">
                <label>Country</label>
                <select id="cards_country" onchange="onCardsCountryChange()">
                    <option value="">All Countries</option>
                    <?php foreach ($countries_in_db as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mp-filter-group">
                <label>Market</label>
                <select id="cards_market" onchange="onCardsMarketChange()">
                    <option value="">All Markets</option>
                    <?php foreach ($all_markets as $m): ?>
                        <option value="<?= $m['market_id'] ?>" data-country="<?= htmlspecialchars($m['country_admin_0']) ?>"><?= htmlspecialchars($m['market']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mp-filter-group">
                <label>Commodity</label>
                <select id="cards_commodity" onchange="loadCardsData()">
                    <option value="">All Commodities</option>
                    <?php foreach ($all_commodities as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mp-filter-group">
                <label>Search</label>
                <input type="text" id="cards_search" placeholder="Search commodity or market…" oninput="filterCardsLocal()">
            </div>
            <div class="mp-filter-group" style="max-width:120px;">
                <label>Sort by</label>
                <select id="cards_sort" onchange="sortCards()">
                    <option value="commodity">Commodity</option>
                    <option value="market">Market</option>
                    <option value="price_desc">Price ↓</option>
                    <option value="price_asc">Price ↑</option>
                </select>
            </div>
        </div>

        <!-- Cards grid -->
        <div id="cardsGrid" class="mp-cards-grid">
            <div class="mp-loading"><span class="ms mp-spinner" style="font-size:2rem;color:var(--mp-primary);">hourglass_empty</span><p style="margin-top:10px;">Loading cards…</p></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         TAB 4: MAP VIEW
    ══════════════════════════════════════ -->
    <div id="panel-map" class="mp-panel <?= $active_tab==='map' ? 'active' : '' ?>">

        <div class="mp-map-container">
            <!-- Map filters -->
            <div class="mp-map-filters">
                <div class="mp-filter-group">
                    <label>Commodity</label>
                    <select id="map_commodity" onchange="loadMapData()">
                        <option value="">All Commodities</option>
                        <?php foreach ($all_commodities as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-filter-group" style="max-width:180px;">
                    <label>Price Type</label>
                    <select id="map_price_type" onchange="loadMapData()">
                        <option value="Wholesale">Wholesale</option>
                        <option value="Retail">Retail</option>
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end;gap:8px;">
                    <div style="font-size:.8rem;color:var(--mp-muted);padding-bottom:8px;">
                        <span class="ms" style="font-size:.9rem;vertical-align:middle;">info</span>
                        Showing last 90 days. Circle size = relative price.
                    </div>
                </div>
            </div>

            <!-- Map -->
            <div id="mp-map" style="position:relative;">
                <div id="mapLoadingOverlay" style="position:absolute;inset:0;background:rgba(255,255,255,.85);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:0;">
                    <div style="text-align:center;"><span class="ms mp-spinner" style="font-size:2.5rem;color:var(--mp-primary);">hourglass_empty</span><p style="color:var(--mp-muted);margin-top:8px;">Loading map…</p></div>
                </div>
            </div>

            <!-- Map legend -->
            <div class="mp-map-legend">
                <span class="mp-map-legend-title">Price Level:</span>
                <div>
                    <div class="mp-map-legend-gradient"></div>
                    <div class="mp-map-legend-labels"><span>Low</span><span>High</span></div>
                </div>
                <span id="mapMarkerCount" style="margin-left:auto;font-size:.8rem;color:var(--mp-muted);"></span>
            </div>
        </div>

        <!-- Tooltip -->
        <div id="mapTooltip" class="mp-map-tooltip"></div>
    </div>

</div><!-- /mp-wrap -->

<!-- ══════════════ MODALS ══════════════ -->

<!-- Add Price Modal -->
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
                                <?php foreach ($modal_countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mp-form-group">
                            <label>Market <span style="color:red">*</span></label>
                            <select name="market_id" id="add_market" required>
                                <option value="" disabled selected>Select market</option>
                                <?php foreach ($markets_for_modal as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['market_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>Category <span style="color:red">*</span></label>
                            <select name="category" id="add_category" required>
                                <option value="" disabled selected>Select category</option>
                                <?php foreach ($modal_categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
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
                            <input type="text" name="packaging_unit" id="add_packaging" readonly placeholder="Auto-filled" required style="background:#f9fafb;">
                        </div>
                        <div class="mp-form-group">
                            <label>Measuring Unit <span style="color:red">*</span></label>
                            <input type="text" name="measuring_unit" id="add_measuring" readonly placeholder="Auto-filled" required style="background:#f9fafb;">
                        </div>
                    </div>
                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>Variety</label>
                            <input type="text" name="variety" id="add_variety" placeholder="e.g. Yellow, White">
                        </div>
                        <div class="mp-form-group">
                            <label>Data Source</label>
                            <input type="text" name="data_source" id="add_data_source" placeholder="e.g. Field Survey">
                        </div>
                    </div>
                    <div style="background:#fafafa;border:1px solid var(--mp-border);border-radius:8px;padding:14px;margin-top:4px;">
                        <p style="font-size:.75rem;font-weight:600;color:var(--mp-muted);margin:0 0 10px;text-transform:uppercase;letter-spacing:.05em;">Pricing</p>
                        <div class="mp-form-row" style="margin-bottom:0;">
                            <div class="mp-form-group" style="margin-bottom:0;">
                                <label>Wholesale Price <span style="color:red">*</span></label>
                                <input type="number" step="0.01" name="wholesale_price" id="add_wholesale" placeholder="Wholesale price" required>
                            </div>
                            <div class="mp-form-group" style="margin-bottom:0;">
                                <label>Retail Price <span style="color:red">*</span></label>
                                <input type="number" step="0.01" name="retail_price" id="add_retail" placeholder="Retail price" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mp-modal-footer">
                    <button type="button" class="mp-btn ghost" onclick="closeModal('addPriceModal')">Cancel</button>
                    <button type="submit" class="mp-btn primary"><span class="ms">save</span> Add Price</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
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
                        <span class="ms mp-spinner" style="font-size:2.5rem;color:var(--mp-primary);">hourglass_empty</span>
                        <p style="color:var(--mp-muted);margin-top:8px;font-size:.875rem;">Loading…</p>
                    </div>
                    <div id="editModalContent">
                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Country</label>
                                <select name="country" id="edit_country">
                                    <?php foreach ($modal_countries as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mp-form-group">
                                <label>Market</label>
                                <select name="market_id" id="edit_market">
                                    <?php foreach ($markets_for_modal as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['market_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Category</label>
                                <select name="category" id="edit_category">
                                    <?php foreach ($modal_categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mp-form-group">
                                <label>Commodity</label>
                                <select name="commodity_id" id="edit_commodity">
                                    <?php foreach ($commodities_for_modal as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mp-form-row">
                            <div class="mp-form-group">
                                <label>Packaging Unit</label>
                                <input type="text" name="packaging_unit" id="edit_packaging">
                            </div>
                            <div class="mp-form-group">
                                <label>Measuring Unit</label>
                                <select name="measuring_unit" id="edit_measuring">
                                    <?php foreach ($modal_units as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
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
                                <label>Price Type</label>
                                <select name="price_type" id="edit_price_type">
                                    <option value="Wholesale">Wholesale</option>
                                    <option value="Retail">Retail</option>
                                </select>
                            </div>
                            <div class="mp-form-group">
                                <label>Price (USD)</label>
                                <input type="number" step="0.0001" name="price" id="edit_price">
                            </div>
                        </div>
                        <div class="mp-form-group">
                            <label>Status</label>
                            <select name="status" id="edit_status">
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
                    <button type="submit" class="mp-btn primary" id="editSaveBtn"><span class="ms">save</span> Update Price</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
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
                        <li><strong>Market</strong> — required</li>
                        <li><strong>Commodity ID</strong> — required (integer)</li>
                        <li><strong>Price Type</strong> — required: Wholesale or Retail</li>
                        <li><strong>Price</strong> — required (numeric)</li>
                        <li><strong>Date Posted</strong> — required: YYYY-MM-DD</li>
                        <li><strong>Status, Variety, Weight, Unit, Country, Subject…</strong> — optional</li>
                    </ol>
                    <a href="downloads/market_prices_template.csv" class="mp-template-link"><span class="ms">download</span> Download CSV Template</a>
                </div>
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mp-form-row">
                        <div class="mp-form-group">
                            <label>CSV File <span style="color:red">*</span></label>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </div>
                        <div class="mp-form-group">
                            <label>Data Source <span style="color:red">*</span></label>
                            <input type="text" name="data_source" placeholder="e.g. Field Survey" required>
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

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="mp-modal-backdrop">
    <div class="mp-modal-center">
        <div class="mp-modal-box">
            <div class="mp-modal-header" style="background:linear-gradient(135deg,#dc2626,#991b1b)">
                <h3><span class="ms">warning</span> Confirm Deletion</h3>
                <button onclick="closeModal('deleteModal')">✕</button>
            </div>
            <div class="mp-modal-body">
                <p id="deleteModalText" style="font-size:.9rem;margin-bottom:12px;"></p>
                <div style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:0 6px 6px 0;padding:10px 12px;font-size:.8rem;color:#991b1b;">
                    <span class="ms" style="font-size:.9rem;vertical-align:middle;">info</span> This action cannot be undone.
                </div>
            </div>
            <div class="mp-modal-footer">
                <button class="mp-btn ghost" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="mp-btn danger" id="confirmDeleteBtn"><span class="ms">delete</span> Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS + JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

<script>
// ─────────────────────────────────────────────────────────────
// GLOBAL STATE
// ─────────────────────────────────────────────────────────────
let allSelectedIds = new Set();
let _priceChart = null;
let _spreadChart = null;
let _leafletMap = null;
let _mapMarkers = [];
let _cardsData = [];
const PAGE_URL = window.location.href.split('?')[0];

// Country flag emoji helper
function countryFlag(country) {
    const flags = {'Kenya':'🇰🇪','Uganda':'🇺🇬','Tanzania':'🇹🇿','Rwanda':'🇷🇼','Burundi':'🇧🇮','Ethiopia':'🇪🇹','South Sudan':'🇸🇸'};
    return flags[country] || '🌍';
}

// ─────────────────────────────────────────────────────────────
// TAB SWITCHING
// ─────────────────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.mp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-panel').forEach(p => p.classList.remove('active'));
    const tabs = ['table','charts','cards','map'];
    document.querySelectorAll('.mp-tab')[tabs.indexOf(tab)]?.classList.add('active');
    document.getElementById('panel-' + tab)?.classList.add('active');

    if (tab === 'charts' && !_priceChart) { setTimeout(loadChartData, 100); }
    if (tab === 'cards')  { loadCardsData(); }
    if (tab === 'map')    { initMap(); }
}

// ─────────────────────────────────────────────────────────────
// MODAL HELPERS
// ─────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.mp-modal-backdrop').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ─────────────────────────────────────────────────────────────
// EXPORT DROPDOWN
// ─────────────────────────────────────────────────────────────
function mpExportToggle() { document.getElementById('exportDropdownMenu').classList.toggle('open'); }
document.addEventListener('click', e => {
    const menu = document.getElementById('exportDropdownMenu');
    if (menu && !menu.closest('.mp-dropdown')?.contains(e.target)) menu.classList.remove('open');
});

// ─────────────────────────────────────────────────────────────
// SELECTION (Table)
// ─────────────────────────────────────────────────────────────
function mpCheckboxChange(cb) {
    const gk = cb.getAttribute('data-group-key');
    let ids = []; try { ids = JSON.parse(cb.getAttribute('data-group-ids') || '[]'); } catch(e) {}
    ids.forEach(id => cb.checked ? allSelectedIds.add(String(id)) : allSelectedIds.delete(String(id)));
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => {
        if (r.getAttribute('data-group-key') === gk) r.classList.toggle('mp-selected', cb.checked);
    });
    mpSyncUI();
}
function mpSelectAll(masterCb) {
    document.querySelectorAll('#pricesTableBody .row-checkbox').forEach(cb => {
        if (cb.closest('tr').classList.contains('mp-filtered-out')) return;
        if (cb.checked === masterCb.checked) return;
        cb.checked = masterCb.checked;
        let ids = []; try { ids = JSON.parse(cb.getAttribute('data-group-ids') || '[]'); } catch(e) {}
        ids.forEach(id => masterCb.checked ? allSelectedIds.add(String(id)) : allSelectedIds.delete(String(id)));
        const gk = cb.getAttribute('data-group-key');
        document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => {
            if (r.getAttribute('data-group-key') === gk) r.classList.toggle('mp-selected', masterCb.checked);
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
function clearAllSelections() { mpClearAllSelections(); }
function mpSyncUI() {
    const count = allSelectedIds.size;
    document.getElementById('selectedCount').textContent = count;
    ['bulkDeleteBtn','approveBtn','publishBtn','unpublishBtn'].forEach(id => {
        const el = document.getElementById(id); if (el) el.disabled = count === 0;
    });
    const sum = document.getElementById('selectionSummary');
    if (sum) sum.textContent = count > 0 ? `(${count} selected)` : '';
    const vCbs = [...document.querySelectorAll('#pricesTableBody .row-checkbox')].filter(c => !c.closest('tr').classList.contains('mp-filtered-out'));
    const chk  = vCbs.filter(c => c.checked);
    const sa = document.getElementById('selectAll');
    if (sa) { sa.checked = vCbs.length > 0 && chk.length === vCbs.length; sa.indeterminate = chk.length > 0 && chk.length < vCbs.length; }
}
function mpGetSelectedIds() { return Array.from(allSelectedIds); }
function approveSelected()   { mpBulkAction('approve','Approve'); }
function publishSelected()   { mpBulkAction('publish','Publish'); }
function unpublishSelected() { mpBulkAction('unpublish','Unpublish'); }
function mpBulkAction(action, label) {
    const ids = mpGetSelectedIds();
    if (!ids.length) { alert('Select at least one item.'); return; }
    if (!confirm(`${label} ${ids.length} item(s)?`)) return;
    mpPerformAction(action, ids);
}
function deleteSelected() {
    const ids = mpGetSelectedIds();
    if (!ids.length) { alert('Select at least one price to delete.'); return; }
    document.getElementById('deleteModalText').innerHTML = `Delete <strong>${ids.length}</strong> selected price record(s)?`;
    document.getElementById('confirmDeleteBtn').onclick = () => { closeModal('deleteModal'); mpPerformAction('delete', ids); };
    openModal('deleteModal');
}
function mpPerformAction(action, ids) {
    fetch('../data/update_status.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action,ids}) })
    .then(r => r.json()).then(d => { if (d.success) { alert(d.message||'Done.'); location.reload(); } else alert('Error: '+(d.message||'Unknown')); })
    .catch(err => alert('Request failed: '+err.message));
}

// ─────────────────────────────────────────────────────────────
// TABLE FILTER / SORT / PAGINATION
// ─────────────────────────────────────────────────────────────
function applyClientFilter() {
    const fm = (document.getElementById('searchMarket')?.value||'').trim().toLowerCase();
    const fc = (document.getElementById('searchCommodity')?.value||'').trim().toLowerCase();
    const ft = (document.getElementById('searchType')?.value||'').trim().toLowerCase();
    const groups = {};
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => {
        const gk = r.getAttribute('data-group-key');
        if (!groups[gk]) groups[gk] = {mkt:!fm,com:!fc,typ:!ft};
        const g = groups[gk];
        if (fm&&!g.mkt) g.mkt=(r.getAttribute('data-market')||'').includes(fm);
        if (fc&&!g.com) g.com=(r.getAttribute('data-commodity')||'').includes(fc);
        if (ft&&!g.typ) g.typ=(r.getAttribute('data-type')||'').includes(ft);
    });
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => {
        const g = groups[r.getAttribute('data-group-key')];
        const show = g.mkt && g.com && g.typ;
        r.classList.toggle('mp-filtered-out', !show);
        r.style.display = show ? '' : 'none';
    });
    mpSyncUI();
}
function clearFilter() {
    ['searchMarket','searchCommodity','searchType'].forEach(id => { const e=document.getElementById(id); if(e) e.value=''; });
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => { r.classList.remove('mp-filtered-out'); r.style.display=''; });
    mpSyncUI();
}
function mpSortTable(col, dir) {
    const url = new URL(window.location); url.searchParams.set('sort',col); url.searchParams.set('dir',dir); url.searchParams.set('page',1); url.searchParams.set('tab','table'); window.location.href=url.toString();
}
function goToPage(pg) {
    const url = new URL(window.location); url.searchParams.set('page',pg); url.searchParams.set('tab','table'); window.location.href=url.toString();
}
function changeRowsPerPage(val) {
    const url = new URL(window.location); url.searchParams.set('limit',val); url.searchParams.set('page',1); url.searchParams.set('tab','table'); window.location.href=url.toString();
}

// ─────────────────────────────────────────────────────────────
// EXPORT
// ─────────────────────────────────────────────────────────────
function exportSelected(fmt) { const ids=mpGetSelectedIds(); if(!ids.length){alert('Select items first.');return;} mpSubmitExport(fmt,ids,false); }
function exportAll(fmt) { if(!confirm('Export ALL prices?'))return; mpSubmitExport(fmt,[],true); }
function mpSubmitExport(fmt,ids,doAll) {
    const form=document.createElement('form'); form.method='POST'; form.action=PAGE_URL; form.target='_blank';
    const add=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);};
    add('export_format',fmt); if(doAll){add('export_all','true');}else ids.forEach(id=>add('selected_ids[]',id));
    document.body.appendChild(form); form.submit(); document.body.removeChild(form);
}

// ─────────────────────────────────────────────────────────────
// EDIT MODAL
// ─────────────────────────────────────────────────────────────
function openEditModal(priceId) {
    document.getElementById('editModalSpinner').style.display='block';
    document.getElementById('editModalContent').style.display='none';
    document.getElementById('editSaveBtn').disabled=true;
    openModal('editPriceModal');
    fetch(`?get_price=${priceId}`).then(r=>r.json()).then(d=>{
        if(d.error) throw new Error(d.error);
        document.getElementById('edit_price_id').value=d.id;
        const sv=(id,v)=>{const e=document.getElementById(id);if(e)e.value=v??'';};
        sv('edit_country',d.country_admin_0); sv('edit_market',d.market_id); sv('edit_category',d.category);
        sv('edit_commodity',d.commodity); sv('edit_packaging',d.weight); sv('edit_measuring',d.unit);
        sv('edit_variety',d.variety||''); sv('edit_data_source',d.data_source||'');
        sv('edit_price_type',d.price_type); sv('edit_price',d.Price); sv('edit_status',d.status);
        document.getElementById('editModalSpinner').style.display='none';
        document.getElementById('editModalContent').style.display='block';
        document.getElementById('editSaveBtn').disabled=false;
    }).catch(err=>{closeModal('editPriceModal');alert('Failed to load: '+err.message);});
}

// ─────────────────────────────────────────────────────────────
// ADD MODAL COMMODITY AJAX
// ─────────────────────────────────────────────────────────────
function loadAddCommodities() {
    const mid=document.getElementById('add_market').value;
    const sel=document.getElementById('add_commodity');
    sel.innerHTML='<option value="" disabled selected>Loading…</option>';
    if(!mid){sel.innerHTML='<option value="" disabled selected>Select market first</option>';return;}
    fetch(`get_market_commodities.php?market_id=${mid}`).then(r=>r.json()).then(data=>{
        sel.innerHTML='<option value="" disabled selected>Select commodity</option>';
        if(data.success&&data.data?.commodities?.length){
            window._mpAddCommodities=data.data.commodities;
            data.data.commodities.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o);});
            const ds=document.getElementById('add_data_source');
            if(ds&&data.data.data_source)ds.value=data.data.data_source;
        }else sel.innerHTML='<option value="" disabled selected>No commodities found</option>';
    }).catch(()=>{sel.innerHTML='<option value="" disabled selected>Error loading</option>';});
}
function fillAddCommodityDetails() {
    const comList=window._mpAddCommodities||[];
    const val=document.getElementById('add_commodity').value;
    const sel=comList.find(c=>String(c.id)===String(val));
    if(!sel)return;
    const sv=(id,v)=>{const e=document.getElementById(id);if(e&&v)e.value=v;};
    if(sel.units?.length){sv('add_packaging',sel.units[0].size);sv('add_measuring',sel.units[0].unit);}
    if(sel.variety)sv('add_variety',sel.variety);
    if(sel.category_name)sv('add_category',sel.category_name);
}

// ─────────────────────────────────────────────────────────────
// CHARTS
// ─────────────────────────────────────────────────────────────
function onChartCountryChange() {
    const country = document.getElementById('chart_country').value;
    const marketSel = document.getElementById('chart_market');
    // Filter market options by country
    Array.from(marketSel.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        opt.hidden = country ? opt.getAttribute('data-country') !== country : false;
    });
    // Reset market if hidden
    if (marketSel.selectedOptions[0]?.hidden) marketSel.value = '';
    // Reload commodity options
    loadFilteredCommodities('chart_country','chart_market','chart_commodity');
    loadChartData();
}
function onChartMarketChange() {
    loadFilteredCommodities('chart_country','chart_market','chart_commodity');
    loadChartData();
}
function loadFilteredCommodities(countryId, marketId, commodityId) {
    const country   = document.getElementById(countryId)?.value || '';
    const market_id = document.getElementById(marketId)?.value  || '';
    const comSel    = document.getElementById(commodityId);
    const curVal    = comSel.value;
    fetch(`?get_commodities=1&country=${encodeURIComponent(country)}&market_id=${encodeURIComponent(market_id)}`)
    .then(r=>r.json()).then(data=>{
        comSel.innerHTML='<option value="">All Commodities</option>';
        data.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.commodity_name;if(String(c.id)===String(curVal))o.selected=true;comSel.appendChild(o);});
    });
}
function loadChartData() {
    const country      = document.getElementById('chart_country')?.value    || '';
    const market_id    = document.getElementById('chart_market')?.value     || '';
    const commodity_id = document.getElementById('chart_commodity')?.value  || '';
    const days         = document.getElementById('chart_days')?.value       || '90';
    const loader       = document.getElementById('chartLoadingIndicator');
    if (loader) loader.style.display = 'flex';

    let url = `?chart_data=1&days=${days}`;
    if (country)      url += `&country=${encodeURIComponent(country)}`;
    if (market_id)    url += `&market_id=${market_id}`;
    if (commodity_id) url += `&commodity_id=${commodity_id}`;

    fetch(url).then(r=>r.json()).then(resp=>{
        if (loader) loader.style.display = 'none';
        if (!resp.success) return;
        renderPriceChart(resp.data);
    }).catch(()=>{ if(loader)loader.style.display='none'; });
}

function renderPriceChart(rawData) {
    // Separate wholesale and retail
    const wsMap = {}, rtMap = {};
    rawData.forEach(r => {
        if (r.price_type === 'Wholesale') wsMap[r.date_label] = parseFloat(r.avg_price);
        if (r.price_type === 'Retail')    rtMap[r.date_label] = parseFloat(r.avg_price);
    });
    const allDates = [...new Set(rawData.map(r=>r.date_label))].sort();
    const wsValues = allDates.map(d => wsMap[d] ?? null);
    const rtValues = allDates.map(d => rtMap[d] ?? null);
    const spread   = allDates.map(d => (rtMap[d]!=null && wsMap[d]!=null) ? parseFloat((rtMap[d]-wsMap[d]).toFixed(4)) : null);

    // Format date labels
    const labels = allDates.map(d => {
        const dt = new Date(d); return dt.toLocaleDateString('en-GB', {day:'2-digit',month:'short'});
    });

    // Update stat cards
    const wsFiltered = wsValues.filter(v=>v!==null);
    const rtFiltered = rtValues.filter(v=>v!==null);
    const allPrices  = [...wsFiltered, ...rtFiltered];
    document.getElementById('stat-avg-ws').textContent   = wsFiltered.length ? '$'+( wsFiltered.reduce((a,b)=>a+b,0)/wsFiltered.length ).toFixed(4) : '—';
    document.getElementById('stat-avg-rt').textContent   = rtFiltered.length ? '$'+( rtFiltered.reduce((a,b)=>a+b,0)/rtFiltered.length ).toFixed(4) : '—';
    document.getElementById('stat-range').textContent    = allPrices.length  ? '$'+Math.min(...allPrices).toFixed(4)+' – $'+Math.max(...allPrices).toFixed(4) : '—';
    document.getElementById('stat-points').textContent   = rawData.reduce((a,r)=>a+parseInt(r.record_count),0).toLocaleString();

    // Trend: compare last 10 vs first 10 wholesale values
    const wsV30 = wsFiltered.slice(-10); const wsV0 = wsFiltered.slice(0,10);
    const trendVal = wsV30.length && wsV0.length ? ((wsV30[wsV30.length-1]-wsV0[0])/wsV0[0]*100) : null;
    const trendEl = document.getElementById('stat-trend');
    if (trendVal !== null) {
        trendEl.textContent = (trendVal>=0?'▲ +':'▼ ')+trendVal.toFixed(1)+'%';
        trendEl.style.color = trendVal >= 0 ? '#16a34a' : '#dc2626';
    } else { trendEl.textContent = '—'; trendEl.style.color=''; }

    // Latest legend values
    const wsLast = wsFiltered[wsFiltered.length-1]; const rtLast = rtFiltered[rtFiltered.length-1];
    document.getElementById('legend-ws-latest').textContent = wsLast ? '$'+wsLast.toFixed(4) : '—';
    document.getElementById('legend-rt-latest').textContent = rtLast ? '$'+rtLast.toFixed(4) : '—';

    // Render main chart
    const ctx = document.getElementById('priceChart').getContext('2d');
    if (_priceChart) _priceChart.destroy();
    _priceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Wholesale',
                    data: wsValues,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,0.06)',
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#7c3aed',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: false,
                    tension: 0.35,
                    spanGaps: true,
                },
                {
                    label: 'Retail',
                    data: rtValues,
                    borderColor: '#db2777',
                    backgroundColor: 'rgba(219,39,119,0.06)',
                    borderWidth: 2.5,
                    borderDash: [6, 3],
                    pointRadius: 3,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#db2777',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: false,
                    tension: 0.35,
                    spanGaps: true,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,0.97)',
                    titleColor: '#1f2937',
                    bodyColor: '#374151',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        title: items => items[0].label,
                        label: item => ` ${item.dataset.label}: $${Number(item.raw).toFixed(4)}`,
                        afterBody: items => {
                            const ws = items.find(i=>i.dataset.label==='Wholesale'); const rt = items.find(i=>i.dataset.label==='Retail');
                            if(ws&&rt&&ws.raw&&rt.raw) return ['', ` Spread: $${(rt.raw-ws.raw).toFixed(4)}`];
                            return [];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks: { color: '#6b7280', font: { size: 11 }, maxRotation: 45, autoSkip: true, maxTicksLimit: 12 },
                    border: { color: 'rgba(0,0,0,0.08)' }
                },
                y: {
                    grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                    ticks: { color: '#6b7280', font: { size: 11 }, callback: v => '$' + Number(v).toFixed(4) },
                    border: { color: 'rgba(0,0,0,0.08)', dash: [4,4] }
                }
            }
        }
    });

    // Spread chart
    const ctx2 = document.getElementById('spreadChart').getContext('2d');
    if (_spreadChart) _spreadChart.destroy();
    _spreadChart = new Chart(ctx2, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Spread (Retail − Wholesale)',
                data: spread,
                borderColor: '#0891b2',
                backgroundColor: 'rgba(8,145,178,0.08)',
                borderWidth: 2,
                pointRadius: 2,
                pointHoverRadius: 5,
                fill: true,
                tension: 0.4,
                spanGaps: true,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,0.97)', titleColor: '#1f2937', bodyColor: '#374151', borderColor: '#e5e7eb', borderWidth: 1, padding: 10,
                    callbacks: { label: item => ` Spread: $${Number(item.raw).toFixed(4)}` }
                }
            },
            scales: {
                x: { grid:{color:'rgba(0,0,0,0.03)'}, ticks:{color:'#6b7280',font:{size:10},maxTicksLimit:10,autoSkip:true}, border:{color:'rgba(0,0,0,0.08)'} },
                y: { grid:{color:'rgba(0,0,0,0.03)'}, ticks:{color:'#6b7280',font:{size:10},callback:v=>'$'+Number(v).toFixed(4)}, border:{color:'rgba(0,0,0,0.08)',dash:[4,4]} }
            }
        }
    });
}

// ─────────────────────────────────────────────────────────────
// CARDS VIEW
// ─────────────────────────────────────────────────────────────
function onCardsCountryChange() {
    const country = document.getElementById('cards_country').value;
    const marketSel = document.getElementById('cards_market');
    Array.from(marketSel.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        opt.hidden = country ? opt.getAttribute('data-country') !== country : false;
    });
    if (marketSel.selectedOptions[0]?.hidden) marketSel.value = '';
    loadFilteredCommodities('cards_country','cards_market','cards_commodity');
    loadCardsData();
}
function onCardsMarketChange() {
    loadFilteredCommodities('cards_country','cards_market','cards_commodity');
    loadCardsData();
}

function loadCardsData() {
    const country      = document.getElementById('cards_country')?.value    || '';
    const market_id    = document.getElementById('cards_market')?.value     || '';
    const commodity_id = document.getElementById('cards_commodity')?.value  || '';
    const grid         = document.getElementById('cardsGrid');
    grid.innerHTML     = '<div class="mp-loading"><span class="ms mp-spinner" style="font-size:2rem;color:var(--mp-primary);">hourglass_empty</span><p style="margin-top:10px;color:var(--mp-muted);">Loading…</p></div>';

    let url = `?chart_data=1&days=90`;
    if (country)      url += `&country=${encodeURIComponent(country)}`;
    if (market_id)    url += `&market_id=${market_id}`;
    if (commodity_id) url += `&commodity_id=${commodity_id}`;

    fetch(url).then(r=>r.json()).then(resp=>{
        if (!resp.success) { grid.innerHTML='<div class="mp-no-data"><span class="ms">sentiment_dissatisfied</span><p>No data available for selected filters.</p></div>'; return; }
        // Build per-commodity/market combos from latest data
        const combos = {};
        resp.data.forEach(r => {
            // use a stub key; in real usage you'd have market-level data
            const key = r.price_type + '___' + r.date_label;
            if (!combos[r.date_label]) combos[r.date_label] = {};
            combos[r.date_label][r.price_type] = r;
        });
        // Build flat cards from latest date
        const dates = Object.keys(combos).sort().reverse();
        if (!dates.length) { grid.innerHTML='<div class="mp-no-data"><span class="ms">inbox</span><p>No price records found.</p></div>'; return; }

        // Aggregate by grouping unique commodity+market pairs from rawData
        const pairMap = {};
        resp.data.forEach(r => {
            // We don't have per-market data in chart_data — show aggregated commodity cards
            const key = 'agg';
            if (!pairMap[key]) pairMap[key] = { ws_prices: [], rt_prices: [], dates: [], wsLatest: null, rtLatest: null };
            if (r.price_type==='Wholesale') { pairMap[key].ws_prices.push(parseFloat(r.avg_price)); if (!pairMap[key].wsLatest) pairMap[key].wsLatest = { price: parseFloat(r.avg_price), date: r.date_label, count: r.record_count }; }
            if (r.price_type==='Retail')    { pairMap[key].rt_prices.push(parseFloat(r.avg_price)); if (!pairMap[key].rtLatest) pairMap[key].rtLatest = { price: parseFloat(r.avg_price), date: r.date_label, count: r.record_count }; }
        });

        // For a richer cards view, fetch latest prices via dedicated endpoint
        let mapUrl = `?map_data=1&price_type=Wholesale`;
        if (commodity_id) mapUrl += `&commodity_id=${commodity_id}`;
        fetch(mapUrl).then(r=>r.json()).then(mapResp=>{
            if (!mapResp.success || !mapResp.data.length) {
                grid.innerHTML='<div class="mp-no-data"><span class="ms">inbox</span><p>No cards data found for current filters.</p></div>';
                return;
            }
            renderCards(mapResp.data, commodity_id, country, market_id);
        });
    });
}

function renderCards(wsData, commodity_id, country, market_id) {
    // Build market-keyed wholesale map
    const wsMap = {}; wsData.forEach(r => wsMap[r.market_id] = r);
    // Fetch retail for same params
    let rtUrl = `?map_data=1&price_type=Retail`;
    if (commodity_id) rtUrl += `&commodity_id=${commodity_id}`;
    fetch(rtUrl).then(r=>r.json()).then(rtResp=>{
        const rtMap = {};
        if (rtResp.success) rtResp.data.forEach(r => rtMap[r.market_id] = r);

        const allMarketIds = [...new Set([...Object.keys(wsMap), ...Object.keys(rtMap)])];
        _cardsData = allMarketIds.map(mid => ({
            market_id:  mid,
            market:     wsMap[mid]?.market    || rtMap[mid]?.market    || '',
            country:    wsMap[mid]?.country_admin_0 || rtMap[mid]?.country_admin_0 || '',
            ws_price:   wsMap[mid]?.avg_price  || null,
            rt_price:   rtMap[mid]?.avg_price  || null,
            latest:     wsMap[mid]?.latest_date || rtMap[mid]?.latest_date || '',
            lat:        wsMap[mid]?.latitude   || null,
            lng:        wsMap[mid]?.longitude  || null,
        })).filter(c => {
            if (country   && c.country   !== country)              return false;
            if (market_id && String(c.market_id) !== market_id)    return false;
            return true;
        });

        sortCards();
    });
}

function sortCards() {
    const sortBy = document.getElementById('cards_sort')?.value || 'commodity';
    _cardsData.sort((a,b) => {
        if (sortBy==='market')     return a.market.localeCompare(b.market);
        if (sortBy==='price_desc') return (parseFloat(b.ws_price)||0) - (parseFloat(a.ws_price)||0);
        if (sortBy==='price_asc')  return (parseFloat(a.ws_price)||0) - (parseFloat(b.ws_price)||0);
        return a.market.localeCompare(b.market);
    });
    filterCardsLocal();
}

function filterCardsLocal() {
    const q    = (document.getElementById('cards_search')?.value||'').trim().toLowerCase();
    const grid = document.getElementById('cardsGrid');
    const data = q ? _cardsData.filter(c => c.market.toLowerCase().includes(q)) : _cardsData;
    if (!data.length) { grid.innerHTML='<div class="mp-no-data"><span class="ms">search_off</span><p>No results for "'+q+'".</p></div>'; return; }

    grid.innerHTML = data.map(c => {
        const ws    = c.ws_price ? '$'+parseFloat(c.ws_price).toFixed(4) : '—';
        const rt    = c.rt_price ? '$'+parseFloat(c.rt_price).toFixed(4) : '—';
        const flag  = countryFlag(c.country);
        const spread= (c.ws_price && c.rt_price) ? '$'+(parseFloat(c.rt_price)-parseFloat(c.ws_price)).toFixed(4) : '—';
        const date  = c.latest ? new Date(c.latest).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—';
        return `
        <div class="mp-commodity-card">
            <div class="mp-card-header">
                <div>
                    <div class="mp-card-commodity"><span class="ms" style="font-size:.9rem;vertical-align:middle;margin-right:4px;">storefront</span>${escHtml(c.market)}</div>
                    <div class="mp-card-market"><span>${flag}</span>${escHtml(c.country)}</div>
                </div>
                <span style="font-size:.7rem;background:#f3f4f6;color:#6b7280;padding:3px 8px;border-radius:99px;">Last 90d</span>
            </div>
            <div class="mp-card-body">
                <div class="mp-card-prices">
                    <div class="mp-card-price-box">
                        <div class="mp-card-price-type ws">Wholesale</div>
                        <div class="mp-card-price-val">${ws}</div>
                        <div class="mp-card-price-change" style="color:#6b7280;">avg USD/unit</div>
                    </div>
                    <div class="mp-card-price-box">
                        <div class="mp-card-price-type rt">Retail</div>
                        <div class="mp-card-price-val">${rt}</div>
                        <div class="mp-card-price-change" style="color:#6b7280;">avg USD/unit</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0 0;border-top:1px solid #f3f4f6;font-size:.75rem;color:#6b7280;">
                    <span><span class="ms" style="font-size:.85rem;vertical-align:middle;">trending_up</span> Spread: <strong style="color:#1f2937;">${spread}</strong></span>
                    <span>${spread !== '—' ? '<span style="color:#16a34a;font-weight:600;">▲</span>' : ''}</span>
                </div>
            </div>
            <div class="mp-card-footer">
                <span><span class="ms" style="font-size:.85rem;vertical-align:middle;">calendar_today</span> ${date}</span>
                <button class="mp-btn" style="padding:3px 10px;font-size:.75rem;" onclick="openChartForMarket(${c.market_id})">
                    <span class="ms" style="font-size:.85rem;">show_chart</span> Trend
                </button>
            </div>
        </div>`;
    }).join('');
}

function openChartForMarket(marketId) {
    const mSel = document.getElementById('chart_market');
    if (mSel) mSel.value = marketId;
    switchTab('charts');
    loadChartData();
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─────────────────────────────────────────────────────────────
// MAP VIEW (Leaflet)
// ─────────────────────────────────────────────────────────────
function initMap() {
    const mapEl = document.getElementById('mp-map');
    if (!mapEl || _leafletMap) {
        // Map already initialised — just reload data
        if (_leafletMap) loadMapData();
        return;
    }

    // Wait for Leaflet to load
    if (typeof L === 'undefined') {
        setTimeout(initMap, 300); return;
    }

    _leafletMap = L.map('mp-map', { zoomControl: true, scrollWheelZoom: true }).setView([1.5, 32], 5);

    // Clean OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(_leafletMap);

    loadMapData();
}

function loadMapData() {
    if (!_leafletMap) { initMap(); return; }
    const commodity_id = document.getElementById('map_commodity')?.value || '';
    const price_type   = document.getElementById('map_price_type')?.value || 'Wholesale';
    const overlay      = document.getElementById('mapLoadingOverlay');
    if (overlay) overlay.style.display = 'flex';

    let url = `?map_data=1&price_type=${price_type}`;
    if (commodity_id) url += `&commodity_id=${commodity_id}`;

    fetch(url).then(r=>r.json()).then(resp=>{
        if (overlay) overlay.style.display = 'none';
        _mapMarkers.forEach(m => m.remove());
        _mapMarkers = [];
        if (!resp.success || !resp.data.length) {
            document.getElementById('mapMarkerCount').textContent = 'No data found';
            return;
        }

        const prices = resp.data.map(r => parseFloat(r.avg_price));
        const minP   = Math.min(...prices);
        const maxP   = Math.max(...prices);
        document.getElementById('mapMarkerCount').textContent = `${resp.data.length} market${resp.data.length!==1?'s':''} found`;

        // Color scale: low=light blue, high=dark maroon
        function priceColor(price) {
            const t = maxP > minP ? (price - minP) / (maxP - minP) : 0.5;
            if (t < 0.2) return '#bee3f8';
            if (t < 0.4) return '#63b3ed';
            if (t < 0.6) return '#3182ce';
            if (t < 0.8) return '#9c2424';
            return '#800000';
        }

        // Radius scale 8–26 based on price
        function priceRadius(price) {
            const t = maxP > minP ? (price - minP) / (maxP - minP) : 0.5;
            return 8 + t * 18;
        }

        const tooltip = document.getElementById('mapTooltip');

        resp.data.forEach(mkt => {
            const lat = parseFloat(mkt.latitude);
            const lng = parseFloat(mkt.longitude);
            if (isNaN(lat) || isNaN(lng)) return;
            const price = parseFloat(mkt.avg_price);
            const r     = priceRadius(price);
            const color = priceColor(price);
            const date  = mkt.latest_date ? new Date(mkt.latest_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—';

            const circle = L.circleMarker([lat, lng], {
                radius: r,
                fillColor: color,
                color: '#fff',
                weight: 2,
                opacity: 0.9,
                fillOpacity: 0.78
            }).addTo(_leafletMap);

            circle.bindPopup(`
                <div style="min-width:170px;font-family:'Segoe UI',sans-serif;">
                    <div style="font-weight:700;font-size:.95rem;color:#1f2937;margin-bottom:4px;">${mkt.market}</div>
                    <div style="font-size:.8rem;color:#6b7280;margin-bottom:8px;">${countryFlag(mkt.country_admin_0)} ${mkt.country_admin_0}</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;background:#fef9f9;border-radius:6px;padding:8px 10px;">
                        <span style="font-size:.75rem;color:#6b7280;">${price_type} avg</span>
                        <span style="font-size:1.1rem;font-weight:700;font-family:monospace;color:#800000;">$${price.toFixed(4)}</span>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af;margin-top:6px;">Latest: ${date}</div>
                </div>
            `);
            _mapMarkers.push(circle);
        });

        // Fit map to markers
        if (_mapMarkers.length) {
            const group = L.featureGroup(_mapMarkers);
            _leafletMap.fitBounds(group.getBounds().pad(0.15));
        }
    }).catch(()=>{ if(overlay)overlay.style.display='none'; });
}

// ─────────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────────
(function mpInit() {
    // Search keyboard shortcuts
    ['searchMarket','searchCommodity','searchType'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('keydown', e => { if(e.key==='Enter')applyClientFilter(); if(e.key==='Escape')clearFilter(); }); }
    });

    // Add modal wiring
    const am = document.getElementById('add_market');
    if (am) am.addEventListener('change', loadAddCommodities);
    const ac = document.getElementById('add_commodity');
    if (ac) ac.addEventListener('change', fillAddCommodityDetails);

    // Re-open import modal on error
    <?php if ($import_message && $import_status === 'danger'): ?>openModal('importModal');<?php endif; ?>

    if (typeof updateBreadcrumb==='function') updateBreadcrumb('Base','Market Prices');

    mpSyncUI();

    // Auto-load active tab
    const activeTab = '<?= $active_tab ?>';
    if (activeTab==='charts') setTimeout(loadChartData, 200);
    if (activeTab==='cards')  setTimeout(loadCardsData, 200);
    if (activeTab==='map')    setTimeout(initMap, 300);
})();

// Spin animation
const _s=document.createElement('style'); _s.textContent='@keyframes mpspin{to{transform:rotate(360deg)}}'; document.head.appendChild(_s);
</script>

<?php include '../admin/includes/footer.php'; ?>