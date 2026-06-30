<?php
// marketprices_dashboard.php — Extended Market Prices Dashboard (Public Reporting View)
// Tabs: Table | Charts | Cards | Map | Compare
// READ-ONLY: published records only, no data manipulation

// ── JSON ENDPOINT: chart data ─────────────────────────────────
if (isset($_GET['chart_data'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $commodity_id = isset($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : 0;
    $market_id    = isset($_GET['market_id'])    ? (int)$_GET['market_id']    : 0;
    $country      = isset($_GET['country'])      ? trim($_GET['country'])      : '';
    $days         = isset($_GET['days'])         ? min((int)$_GET['days'], 730): 90;
    // Custom date range
    $date_from    = isset($_GET['date_from'])    ? trim($_GET['date_from'])    : '';
    $date_to      = isset($_GET['date_to'])      ? trim($_GET['date_to'])      : '';

    // Only published records
    $where = ["mp.status = 'published'"];
    $params = []; $types = '';

    if ($date_from && $date_to) {
        $where[] = "mp.date_posted BETWEEN ? AND ?";
        $params[] = $date_from . ' 00:00:00'; $types .= 's';
        $params[] = $date_to   . ' 23:59:59'; $types .= 's';
    } else {
        $where[] = "mp.date_posted >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $days; $types .= 'i';
    }

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
    $where = ["mp.status = 'published'"]; $params = []; $types = '';
    if ($country)   { $where[] = "mp.country_admin_0 = ?"; $params[] = $country; $types .= 's'; }
    if ($market_id) { $where[] = "mp.market_id = ?"; $params[] = $market_id; $types .= 'i'; }
    // Include variety in commodity display
    $sql = "SELECT DISTINCT c.id,
                   CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                   c.commodity_name, c.variety
            FROM commodities c
            INNER JOIN market_prices mp ON mp.commodity = c.id
            WHERE " . implode(' AND ', $where)
         . " ORDER BY c.commodity_name, c.variety";
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
            INNER JOIN market_prices mp ON mp.market_id = m.id
            WHERE mp.status = 'published'"
         . ($country ? " AND mp.country_admin_0 = ?" : '')
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
    $where = ["mp.status = 'published'", "mp.price_type = ?"];
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

// ── JSON ENDPOINT: comparison data ────────────────────────────
if (isset($_GET['compare_data'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');

    $mode         = $_GET['mode'] ?? 'commodity'; // commodity | market | country
    $price_type   = in_array($_GET['price_type'] ?? '', ['Wholesale','Retail','Both']) ? $_GET['price_type'] : 'Wholesale';
    $days         = isset($_GET['days']) ? min((int)$_GET['days'], 730) : 90;
    $date_from    = trim($_GET['date_from'] ?? '');
    $date_to      = trim($_GET['date_to']   ?? '');

    $date_cond = '';
    $date_params = []; $date_types = '';
    if ($date_from && $date_to) {
        $date_cond = "AND mp.date_posted BETWEEN ? AND ?";
        $date_params[] = $date_from . ' 00:00:00'; $date_types .= 's';
        $date_params[] = $date_to   . ' 23:59:59'; $date_types .= 's';
    } else {
        $date_cond = "AND mp.date_posted >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $date_params[] = $days; $date_types .= 'i';
    }

    // Fixed: Properly handle price_type condition
    $pt_cond = '';
    $pt_params = [];
    if ($price_type !== 'Both') {
        $pt_cond = "AND mp.price_type = ?";
        $pt_params[] = $price_type;
    }

    if ($mode === 'commodity') {
        // Compare up to 4 commodities, optionally filtered by country/market
        $ids_raw  = array_filter(array_map('intval', explode(',', $_GET['commodity_ids'] ?? '')));
        $country  = trim($_GET['country']   ?? '');
        $market_id = (int)($_GET['market_id'] ?? 0);
        if (empty($ids_raw)) { echo json_encode(['success'=>false,'msg'=>'No commodities selected']); exit; }
        $placeholders = implode(',', array_fill(0, count($ids_raw), '?'));
        $country_cond = $country   ? "AND mp.country_admin_0 = ?" : '';
        $market_cond  = $market_id ? "AND mp.market_id = ?"       : '';
        
        // Fixed: Use CONCAT with proper label, and include all non-aggregated columns in GROUP BY
        $sql = "SELECT DATE(mp.date_posted) as d,
                       mp.price_type,
                       mp.commodity as commodity_id,
                       CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS label,
                       AVG(mp.Price) as avg_price,
                       MIN(mp.Price) as min_price,
                       MAX(mp.Price) as max_price,
                       COUNT(*) as cnt
                FROM market_prices mp
                LEFT JOIN commodities c ON mp.commodity = c.id
                WHERE mp.status = 'published'
                  AND mp.commodity IN ($placeholders)
                  $date_cond $pt_cond $country_cond $market_cond
                GROUP BY DATE(mp.date_posted), mp.price_type, mp.commodity, label
                ORDER BY d ASC, label ASC";
        
        $stmt = $con->prepare($sql);
        // Build params: ids first, then date params, then pt params, then country/market
        $all_params = array_merge($ids_raw, $date_params, $pt_params);
        $all_types  = str_repeat('i', count($ids_raw)) . $date_types . str_repeat('s', count($pt_params));
        if ($country)   { $all_params[] = $country;   $all_types .= 's'; }
        if ($market_id) { $all_params[] = $market_id; $all_types .= 'i'; }
        $stmt->bind_param($all_types, ...$all_params);
        $stmt->execute(); $r = $stmt->get_result();
        $rows = []; while ($row = $r->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        echo json_encode(['success'=>true,'mode'=>'commodity','data'=>$rows]); exit;
    }

    if ($mode === 'market') {
        // Compare up to 4 markets for a given commodity
        $market_ids_raw = array_filter(array_map('intval', explode(',', $_GET['market_ids'] ?? '')));
        $commodity_id   = (int)($_GET['commodity_id'] ?? 0);
        if (empty($market_ids_raw)) { echo json_encode(['success'=>false,'msg'=>'No markets selected']); exit; }
        $placeholders = implode(',', array_fill(0, count($market_ids_raw), '?'));
        $com_cond = $commodity_id ? "AND mp.commodity = ?" : '';
        $com_params = $commodity_id ? [$commodity_id] : [];
        
        // Fixed: Include market_id and label in GROUP BY
        $sql = "SELECT DATE(mp.date_posted) as d,
                       mp.price_type,
                       mp.market_id,
                       mp.market as label,
                       AVG(mp.Price) as avg_price,
                       MIN(mp.Price) as min_price,
                       MAX(mp.Price) as max_price,
                       COUNT(*) as cnt
                FROM market_prices mp
                WHERE mp.status = 'published'
                  AND mp.market_id IN ($placeholders)
                  $date_cond $pt_cond $com_cond
                GROUP BY DATE(mp.date_posted), mp.price_type, mp.market_id, mp.market
                ORDER BY d ASC, label ASC";
        
        $stmt = $con->prepare($sql);
        $all_params = array_merge($market_ids_raw, $date_params, $pt_params, $com_params);
        $all_types  = str_repeat('i', count($market_ids_raw)) . $date_types . str_repeat('s', count($pt_params));
        if ($commodity_id) { $all_types .= 'i'; }
        $stmt->bind_param($all_types, ...$all_params);
        $stmt->execute(); $r = $stmt->get_result();
        $rows = []; while ($row = $r->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        echo json_encode(['success'=>true,'mode'=>'market','data'=>$rows]); exit;
    }

    if ($mode === 'country') {
        // Compare countries for a given commodity
        $countries_raw = array_filter(array_map('trim', explode(',', $_GET['countries'] ?? '')));
        $commodity_id  = (int)($_GET['commodity_id'] ?? 0);
        if (empty($countries_raw)) { echo json_encode(['success'=>false,'msg'=>'No countries selected']); exit; }
        $placeholders = implode(',', array_fill(0, count($countries_raw), '?'));
        $com_cond = $commodity_id ? "AND mp.commodity = ?" : '';
        $com_params = $commodity_id ? [$commodity_id] : [];
        
        // Fixed: Include country_admin_0 in GROUP BY
        $sql = "SELECT DATE(mp.date_posted) as d,
                       mp.price_type,
                       mp.country_admin_0 as label,
                       AVG(mp.Price) as avg_price,
                       MIN(mp.Price) as min_price,
                       MAX(mp.Price) as max_price,
                       COUNT(*) as cnt
                FROM market_prices mp
                WHERE mp.status = 'published'
                  AND mp.country_admin_0 IN ($placeholders)
                  $date_cond $pt_cond $com_cond
                GROUP BY DATE(mp.date_posted), mp.price_type, mp.country_admin_0
                ORDER BY d ASC, label ASC";
        
        $stmt = $con->prepare($sql);
        $all_params = array_merge($countries_raw, $date_params, $pt_params, $com_params);
        $all_types  = str_repeat('s', count($countries_raw)) . $date_types . str_repeat('s', count($pt_params));
        if ($commodity_id) { $all_types .= 'i'; }
        $stmt->bind_param($all_types, ...$all_params);
        $stmt->execute(); $r = $stmt->get_result();
        $rows = []; while ($row = $r->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        echo json_encode(['success'=>true,'mode'=>'country','data'=>$rows]); exit;
    }

    echo json_encode(['success'=>false,'msg'=>'Invalid mode']); exit;
}

// ── POST: Export functionality ─────────────────────────────────
if (isset($_POST['export_format'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    $format = $_POST['export_format'];
    $selected_ids = isset($_POST['selected_ids']) && !empty($_POST['selected_ids']) ? explode(',', $_POST['selected_ids']) : [];
    $export_all = isset($_POST['export_all']) && $_POST['export_all'] == 'true';
    $data = [];
    
    if ($export_all) {
        $sql = "SELECT p.market, c.commodity_name as commodity, p.price_type, p.Price as price, p.date_posted, p.status, p.data_source as source, p.variety 
                FROM market_prices p 
                LEFT JOIN commodities c ON p.commodity = c.id 
                WHERE p.status = 'published' 
                ORDER BY p.date_posted DESC";
        $result = $con->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) $data[] = $row; }
    } elseif (!empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $sql = "SELECT p.market, c.commodity_name as commodity, p.price_type, p.Price as price, p.date_posted, p.status, p.data_source as source, p.variety 
                FROM market_prices p 
                LEFT JOIN commodities c ON p.commodity = c.id 
                WHERE p.id IN ($ids) AND p.status = 'published' 
                ORDER BY p.date_posted DESC";
        $result = $con->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) $data[] = $row; }
    }
    
    if ($format == 'excel' || $format == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="market_prices_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w'); fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Market','Commodity','Price Type','Price (USD)','Date Posted','Status','Source','Variety']);
        foreach ($data as $r) fputcsv($out, [$r['market'],$r['commodity'],$r['price_type'],$r['price'],$r['date_posted'],$r['status'],$r['source'],$r['variety']]);
        fclose($out); exit;
    } elseif ($format == 'pdf') { ?>
<!DOCTYPE html><html><head><title>Market Prices Export</title><style>body{font-family:Arial}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px}th{background:#f2f2f2}</style></head>
<body><h1>Market Prices Export</h1><p>Exported: <?= date('Y-m-d H:i:s') ?> | Records: <?= count($data) ?></p>
<table><thead><tr><th>Market</th><th>Commodity</th><th>Type</th><th>Price ($)</th><th>Date</th><th>Status</th><th>Source</th><th>Variety</th></tr></thead><tbody>
<?php foreach ($data as $row): ?>
<tr><td><?= htmlspecialchars($row['market']) ?></td><td><?= htmlspecialchars($row['commodity']) ?></td><td><?= htmlspecialchars($row['price_type']) ?></td><td><?= htmlspecialchars($row['price']) ?></td><td><?= htmlspecialchars($row['date_posted']) ?></td><td><?= htmlspecialchars($row['status']) ?></td><td><?= htmlspecialchars($row['source']) ?></td><td><?= htmlspecialchars($row['variety']) ?></td></tr>
<?php endforeach; ?>
</tbody></table><script>window.onload=function(){window.print();}</script></body></html>
<?php exit; } }

// ─────────────────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include '../admin/includes/config.php';
include '../admin/includes/admin_header.php';

function getPricesData($con, $limit = 20, $offset = 0, $sort_col = 'date_posted', $sort_dir = 'DESC') {
    $allowed = ['market'=>'p.market','commodity'=>'c.commodity_name','date_posted'=>'p.date_posted','price_type'=>'p.price_type','Price'=>'p.Price','status'=>'p.status'];
    $order_by = $allowed[$sort_col] ?? 'p.date_posted';
    $dir = $sort_dir === 'ASC' ? 'ASC' : 'DESC';
    // Only published records
    $sql = "SELECT p.id,p.market,p.commodity,c.commodity_name,c.variety,
                   CONCAT(c.commodity_name,IF(c.variety IS NOT NULL AND c.variety!='',CONCAT(' (',c.variety,')'),'')) AS commodity_display,
                   p.price_type,p.Price,p.date_posted,p.status,p.data_source,p.market_id,p.category,p.weight,p.unit
            FROM market_prices p LEFT JOIN commodities c ON p.commodity=c.id
            WHERE p.status = 'published'
            ORDER BY $order_by $dir, p.date_posted DESC LIMIT $limit OFFSET $offset";
    $result = $con->query($sql); $data = [];
    if ($result) { while ($row = $result->fetch_assoc()) $data[] = $row; $result->free(); }
    return $data;
}

function getTotalPriceRecords($con) {
    $r = $con->query("SELECT count(*) as total FROM market_prices WHERE status = 'published'");
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
    $stmt = $con->prepare("SELECT Price FROM market_prices WHERE commodity=? AND market=? AND price_type=? AND DATE(date_posted)<DATE(?) AND status='published' ORDER BY date_posted DESC LIMIT 1");
    if (!$stmt) return 'N/A';
    $stmt->bind_param('isss', $commodityId, $market, $priceType, $currentDate);
    $stmt->execute(); $r = $stmt->get_result();
    if ($r && $r->num_rows > 0) { $prev = $r->fetch_assoc(); if ($prev['Price'] != 0) { $c = (($currentPrice - $prev['Price']) / $prev['Price']) * 100; $stmt->close(); return round($c, 2).'%'; } }
    $stmt->close(); return 'N/A';
}

function calculateMoMChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $ago = date('Y-m-d', strtotime($currentDate . ' -30 days'));
    $stmt = $con->prepare("SELECT Price,ABS(DATEDIFF(DATE(date_posted),?)) as dd FROM market_prices WHERE commodity=? AND market=? AND price_type=? AND status='published' AND DATE(date_posted) BETWEEN DATE_SUB(?,INTERVAL 35 DAY) AND DATE_SUB(?,INTERVAL 25 DAY) ORDER BY dd ASC LIMIT 1");
    if (!$stmt) return 'N/A';
    $stmt->bind_param('sissss', $ago, $commodityId, $market, $priceType, $ago, $ago);
    $stmt->execute(); $r = $stmt->get_result();
    if ($r && $r->num_rows > 0) { $d = $r->fetch_assoc(); if ($d['Price'] != 0) { $c = (($currentPrice - $d['Price']) / $d['Price']) * 100; $stmt->close(); return round($c, 2).'%'; } }
    $stmt->close(); return 'N/A';
}

// ── STATS (published only) ─────────────────────────────────────
$total_prices    = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);
$markets_count   = (int)(($con->query("SELECT COUNT(DISTINCT market_id) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);
$wholesale_count = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='published' AND price_type='Wholesale'")->fetch_assoc())['t'] ?? 0);
$countries_count = (int)(($con->query("SELECT COUNT(DISTINCT country_admin_0) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);

// Distinct countries in DB (published only)
$countries_in_db = [];
$ctr = $con->query("SELECT DISTINCT country_admin_0 FROM market_prices WHERE country_admin_0 != '' AND status='published' ORDER BY country_admin_0");
if ($ctr) { while ($r = $ctr->fetch_assoc()) $countries_in_db[] = $r['country_admin_0']; }

// All markets (published only)
$all_markets = [];
$amr = $con->query("SELECT DISTINCT mp.market_id, mp.market, mp.country_admin_0 FROM market_prices mp WHERE mp.status='published' ORDER BY mp.country_admin_0, mp.market");
if ($amr) { while ($r = $amr->fetch_assoc()) $all_markets[] = $r; }

// All commodities with variety (published only)
$all_commodities_q = $con->query("SELECT DISTINCT c.id,
    CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
    c.commodity_name, c.variety
    FROM commodities c INNER JOIN market_prices mp ON mp.commodity=c.id
    WHERE mp.status='published'
    ORDER BY c.commodity_name, c.variety");
$all_commodities = [];
if ($all_commodities_q) { while ($r = $all_commodities_q->fetch_assoc()) $all_commodities[] = $r; }

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
.mp-stat-card.stat-markets   { border-left-color: #d97706; }
.mp-stat-card.stat-countries { border-left-color: #16a34a; }
.mp-stat-card.stat-wholesale { border-left-color: #2563eb; }
.mp-stat-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--mp-muted); margin-bottom: 4px; }
.mp-stat-value { font-size: 1.4rem; font-weight: 700; color: var(--mp-text); }
.mp-stat-icon  { font-size: 2rem; opacity: .25; }
.mp-stat-icon.ic-total     { color: var(--mp-primary); opacity: .3; }
.mp-stat-icon.ic-markets   { color: #d97706; opacity: .3; }
.mp-stat-icon.ic-countries { color: #16a34a; opacity: .3; }
.mp-stat-icon.ic-wholesale { color: #2563eb; opacity: .3; }

/* ── Toolbar ── */
.mp-toolbar { background: var(--mp-card); border-radius: var(--mp-radius); padding: 12px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 14px; }
.mp-toolbar-left  { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.mp-toolbar-right { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

/* ── Buttons ── */
.mp-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 6px; font-size: .8125rem; font-weight: 500; border: 1px solid var(--mp-border); background: white; color: var(--mp-text); cursor: pointer; transition: all .2s; white-space: nowrap; }
.mp-btn:hover { background: #f3f4f6; }
.mp-btn.primary  { background: var(--mp-primary); color: white; border-color: var(--mp-primary); }
.mp-btn.primary:hover { background: var(--mp-primary-dk); }
.mp-btn.ghost    { background: transparent; border-color: var(--mp-border); color: var(--mp-muted); }
.mp-btn.ghost:hover { background: #f9fafb; color: var(--mp-text); }
.mp-badge-count  { background: rgba(0,0,0,.1); color: inherit; font-size: .7rem; font-weight: 700; padding: 1px 7px; border-radius: 99px; margin-left: 2px; }

/* ── Dropdown ── */
.mp-dropdown { position: relative; }
.mp-dropdown-menu { position: absolute; top: calc(100% + 4px); right: 0; min-width: 190px; z-index: 200; background: white; border: 1px solid var(--mp-border); border-radius: var(--mp-radius); box-shadow: 0 8px 24px rgba(0,0,0,.1); display: none; }
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
.mp-badge-published  { background: #dcfce7; color: #166534; } .mp-badge-published::before  { background: #16a34a; }

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
.mp-chart-filter-group select, .mp-chart-filter-group input[type="date"] { padding: 7px 10px; border: 1px solid var(--mp-border); border-radius: 6px; font-size: .8125rem; color: var(--mp-text); background: white; width: 100%; }
.mp-chart-filter-group select:focus, .mp-chart-filter-group input[type="date"]:focus { outline: none; border-color: var(--mp-primary); }

/* Custom date range row */
.mp-custom-range { display: none; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--mp-border); }
.mp-custom-range.visible { display: flex; }
.mp-custom-range-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 140px; }
.mp-custom-range-group label { font-size: .75rem; font-weight: 600; color: var(--mp-muted); text-transform: uppercase; letter-spacing: .05em; }
.mp-custom-range-group input[type="date"] { padding: 7px 10px; border: 1px solid var(--mp-border); border-radius: 6px; font-size: .8125rem; color: var(--mp-text); background: white; width: 100%; box-sizing: border-box; }
.mp-custom-range-group input[type="date"]:focus { outline: none; border-color: var(--mp-primary); }

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
.mp-card-footer { padding: 8px 14px; border-top: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; font-size: .75rem; color: var(--mp-muted); }

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

/* ── No data state ── */
.mp-no-data { text-align: center; padding: 50px 20px; color: var(--mp-muted); }
.mp-no-data .ms { font-size: 3rem; opacity: .3; display: block; margin-bottom: 12px; }
.mp-no-data p { font-size: .9rem; margin: 0; }

/* ── Loading spinner ── */
.mp-loading { text-align: center; padding: 40px; color: var(--mp-muted); }
@keyframes mpspin { to { transform: rotate(360deg); } }
.mp-spinner { animation: mpspin 1s linear infinite; display: inline-block; }

/* Published badge pill in header */
.mp-published-pill { display: inline-flex; align-items: center; gap: 5px; background: #dcfce7; color: #166534; font-size: .75rem; font-weight: 600; padding: 3px 10px; border-radius: 99px; border: 1px solid #bbf7d0; }

/* ── Compare panel styles ── */
.cmp-panel-card  { background:var(--mp-card);border-radius:var(--mp-radius);box-shadow:0 1px 3px rgba(0,0,0,.06);padding:20px;margin-bottom:16px; }
.cmp-mode-pills  { display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap; }
.cmp-mode-pill   { display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:99px;font-size:.8125rem;font-weight:500;border:1.5px solid var(--mp-border);background:white;color:var(--mp-muted);cursor:pointer;transition:all .2s; }
.cmp-mode-pill:hover { border-color:var(--mp-primary);color:var(--mp-primary); }
.cmp-mode-pill.active { background:var(--mp-primary);color:#fff;border-color:var(--mp-primary);font-weight:600; }
.cmp-mode-pill .ms { font-size:1rem; }
.cmp-config      { display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;padding:16px 0 20px;border-bottom:1px solid var(--mp-border);margin-bottom:20px; }
.cmp-fg          { display:flex;flex-direction:column;gap:4px;flex:1;min-width:160px; }
.cmp-fg label    { font-size:.75rem;font-weight:600;color:var(--mp-muted);text-transform:uppercase;letter-spacing:.05em; }
.cmp-fg select, .cmp-fg input { padding:7px 10px;border:1px solid var(--mp-border);border-radius:6px;font-size:.8125rem;color:var(--mp-text);background:white;width:100%;box-sizing:border-box; }
.cmp-fg select:focus,.cmp-fg input:focus { outline:none;border-color:var(--mp-primary);box-shadow:0 0 0 3px rgba(128,0,0,.1); }
.cmp-chips       { display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:34px;padding:5px 8px;border:1px solid var(--mp-border);border-radius:6px;background:white;cursor:text;transition:border-color .2s; }
.cmp-chips:focus-within { border-color:var(--mp-primary);box-shadow:0 0 0 3px rgba(128,0,0,.1); }
.cmp-chip        { display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:99px;font-size:.75rem;font-weight:600;white-space:nowrap; }
.cmp-chip-rm     { background:none;border:none;cursor:pointer;font-size:.9rem;line-height:1;color:inherit;opacity:.7;padding:0;display:flex;align-items:center; }
.cmp-chip-rm:hover { opacity:1; }
.cmp-chip-hint   { font-size:.75rem;color:var(--mp-muted);padding:2px 4px; }
.cmp-chip-limit  { font-size:.7rem;color:var(--mp-muted);margin-top:4px; }
.cmp-summary     { overflow-x:auto;margin-top:20px; }
.cmp-summary table { width:100%;border-collapse:collapse;font-size:.8rem; }
.cmp-summary th  { padding:8px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--mp-muted);border-bottom:2px solid var(--mp-border);white-space:nowrap; }
.cmp-summary td  { padding:8px 12px;border-bottom:1px solid #f3f4f6;vertical-align:middle; }
.cmp-summary tr:hover td { background:#fefaf5; }
.cmp-swatch      { display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:6px;vertical-align:middle; }
.cmp-delta       { display:inline-flex;align-items:center;gap:2px;font-size:.72rem;font-weight:600;padding:1px 7px;border-radius:4px; }
.cmp-delta.up    { background:#dcfce7;color:#16a34a; }
.cmp-delta.dn    { background:#fee2e2;color:#dc2626; }
.cmp-delta.flat  { background:#f3f4f6;color:#6b7280; }
.cmp-chart-wrap  { position:relative;width:100%;height:360px; }
.cmp-chart-wrap2 { position:relative;width:100%;height:200px;margin-top:20px; }
.cmp-empty       { text-align:center;padding:60px 20px;color:var(--mp-muted); }
.cmp-empty .ms   { font-size:3rem;opacity:.25;display:block;margin-bottom:12px; }

@media (max-width: 768px) {
    .mp-stats { grid-template-columns: repeat(2, 1fr); }
    .mp-chart-filters { flex-direction: column; }
    .mp-cards-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<div class="mp-wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    <!-- ── Page Header ── -->
    <div class="mp-page-header">
        <div>
            <h1><span class="ms" style="font-size:1.4rem;margin-right:6px;">monitoring</span>Market Prices Dashboard</h1>
            <p>Explore and analyse commodity price trends across markets and countries</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span class="mp-published-pill"><span class="ms" style="font-size:.85rem;">verified</span> Published Data Only</span>
            <div class="mp-dropdown" id="exportDropdown">
                <button class="mp-btn primary" onclick="mpExportToggle()">
                    <span class="ms">download</span> Export <span class="ms" style="font-size:.9rem;">expand_more</span>
                </button>
                <div class="mp-dropdown-menu" id="exportDropdownMenu" style="position:absolute;right:0;left:auto;margin-top:4px;">
                    <div class="mp-dropdown-item" onclick="exportAll('excel')"><span class="ms">table_view</span> All → CSV/Excel</div>
                    <div class="mp-dropdown-item" onclick="exportAll('pdf')"><span class="ms">picture_as_pdf</span> All → PDF</div>
                    <div class="mp-dropdown-item" onclick="exportSelected('excel')" id="exportSelectedCsv" style="opacity:0.5;pointer-events:none;"><span class="ms">checklist</span> Selected → CSV</div>
                    <div class="mp-dropdown-item" onclick="exportSelected('pdf')" id="exportSelectedPdf" style="opacity:0.5;pointer-events:none;"><span class="ms">checklist</span> Selected → PDF</div>
                </div>
            </div>
        </div>
    </div>
    <div class="mp-accent-bar"></div>

    <!-- ── Stat Cards ── -->
    <div class="mp-stats">
        <div class="mp-stat-card">
            <div><div class="mp-stat-label">Published Prices</div><div class="mp-stat-value"><?= number_format($total_prices) ?></div></div>
            <span class="ms mp-stat-icon ic-total" style="font-size:2.2rem;">monitoring</span>
        </div>
        <div class="mp-stat-card stat-markets">
            <div><div class="mp-stat-label">Active Markets</div><div class="mp-stat-value"><?= number_format($markets_count) ?></div></div>
            <span class="ms mp-stat-icon ic-markets" style="font-size:2.2rem;">storefront</span>
        </div>
        <div class="mp-stat-card stat-countries">
            <div><div class="mp-stat-label">Countries</div><div class="mp-stat-value"><?= number_format($countries_count) ?></div></div>
            <span class="ms mp-stat-icon ic-countries" style="font-size:2.2rem;">public</span>
        </div>
        <div class="mp-stat-card stat-wholesale">
            <div><div class="mp-stat-label">Wholesale Records</div><div class="mp-stat-value"><?= number_format($wholesale_count) ?></div></div>
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
        <button class="mp-tab <?= $active_tab==='compare' ? 'active' : '' ?>" onclick="switchTab('compare')" role="tab">
            <span class="ms">compare_arrows</span> Compare
        </button>
    </div>

    <!-- ══════════════════════════════════════
         TAB 1: TABLE VIEW
    ══════════════════════════════════════ -->
    <div id="panel-table" class="mp-panel <?= $active_tab==='table' ? 'active' : '' ?>">

        <!-- Toolbar with selection controls (Delete removed) -->
        <div class="mp-toolbar">
            <div class="mp-toolbar-left">
                <button class="mp-btn ghost" onclick="clearAllSelections()">
                    <span class="ms">clear</span> Clear Selection
                    <span class="mp-badge-count" id="selectedCount">0</span>
                </button>
            </div>
            <div class="mp-toolbar-right">
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
                    Showing <?= $offset+1 ?> – <?= min($offset+$limit,$total_records) ?> of <?= number_format($total_records) ?> published records
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
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-chart-filter-group">
                    <label>Time Range</label>
                    <select id="chart_days" onchange="onChartDaysChange()">
                        <option value="30">Last 30 days</option>
                        <option value="60">Last 60 days</option>
                        <option value="90" selected>Last 90 days</option>
                        <option value="180">Last 6 months</option>
                        <option value="365">Last year</option>
                        <option value="custom">Custom range…</option>
                    </select>
                </div>
                <button class="mp-btn primary" style="align-self:flex-end;" onclick="loadChartData()">
                    <span class="ms">refresh</span> Update
                </button>
            </div>

            <!-- Custom date range (shown when "custom" is selected) -->
            <div class="mp-custom-range" id="chartCustomRange">
                <div class="mp-custom-range-group">
                    <label>From Date</label>
                    <input type="date" id="chart_date_from" onchange="loadChartData()">
                </div>
                <div class="mp-custom-range-group">
                    <label>To Date</label>
                    <input type="date" id="chart_date_to" onchange="loadChartData()">
                </div>
                <div style="align-self:flex-end;padding-bottom:1px;">
                    <button class="mp-btn ghost" onclick="clearChartCustomRange()">
                        <span class="ms">close</span> Clear
                    </button>
                </div>
            </div>

            <!-- Chart stat cards -->
            <div class="mp-chart-stats" id="chartStats" style="margin-top:16px;">
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Avg Wholesale</div><div class="mp-chart-stat-value" id="stat-avg-ws">—</div><div class="mp-chart-stat-sub">USD per unit</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Avg Retail</div><div class="mp-chart-stat-value" id="stat-avg-rt">—</div><div class="mp-chart-stat-sub">USD per unit</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Price Range</div><div class="mp-chart-stat-value" id="stat-range">—</div><div class="mp-chart-stat-sub">Min – Max</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Data Points</div><div class="mp-chart-stat-value" id="stat-points">—</div><div class="mp-chart-stat-sub">Records in range</div></div>
                <div class="mp-chart-stat"><div class="mp-chart-stat-label">Trend (30d)</div><div class="mp-chart-stat-value" id="stat-trend">—</div><div class="mp-chart-stat-sub">Price direction</div></div>
            </div>

            <!-- Custom legend -->
            <div class="mp-chart-legend" style="margin-top:12px;">
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
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
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
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
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
    </div>

    <!-- ══════════════════════════════════════
         TAB 5: COMPARE VIEW
    ══════════════════════════════════════ -->
    <div id="panel-compare" class="mp-panel <?= $active_tab==='compare' ? 'active' : '' ?>">

        <!-- Mode switcher -->
        <div class="cmp-panel-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;font-size:1rem;font-weight:600;">Price Comparison</h2>
                    <p style="margin:4px 0 0;font-size:.8rem;color:var(--mp-muted);">Compare commodities, markets, or countries side-by-side</p>
                </div>
                <div id="cmpRunBtn" style="display:none;">
                    <button class="mp-btn primary" onclick="runComparison()">
                        <span class="ms">compare_arrows</span> Compare
                    </button>
                </div>
            </div>

            <!-- Mode pills -->
            <div class="cmp-mode-pills">
                <button class="cmp-mode-pill active" id="pill-commodity" onclick="setCmpMode('commodity')">
                    <span class="ms">grain</span> Commodity vs Commodity
                </button>
                <button class="cmp-mode-pill" id="pill-market" onclick="setCmpMode('market')">
                    <span class="ms">storefront</span> Market vs Market
                </button>
                <button class="cmp-mode-pill" id="pill-country" onclick="setCmpMode('country')">
                    <span class="ms">public</span> Country vs Country
                </button>
            </div>

            <!-- ── MODE: COMMODITY ── -->
            <div id="cmp-cfg-commodity" class="cmp-config">
                <div class="cmp-fg" style="max-width:200px;">
                    <label>Country (optional)</label>
                    <select id="cmp_com_country" onchange="onCmpComCountryChange()">
                        <option value="">All Countries</option>
                        <?php foreach ($countries_in_db as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg" style="max-width:200px;">
                    <label>Market (optional)</label>
                    <select id="cmp_com_market">
                        <option value="">All Markets</option>
                        <?php foreach ($all_markets as $m): ?>
                            <option value="<?= $m['market_id'] ?>" data-country="<?= htmlspecialchars($m['country_admin_0']) ?>"><?= htmlspecialchars($m['market']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg">
                    <label>Commodities to compare <span class="cmp-chip-limit">(up to 4)</span></label>
                    <div class="cmp-chips" id="cmp_com_chips" onclick="document.getElementById('cmp_com_picker').focus()">
                        <span class="cmp-chip-hint" id="cmp_com_hint">Pick from list below…</span>
                    </div>
                    <select id="cmp_com_picker" onchange="addCmpChip('commodity',this)" style="margin-top:6px;">
                        <option value="">+ Add commodity…</option>
                        <?php foreach ($all_commodities as $c): ?>
                            <option value="<?= $c['id'] ?>" data-label="<?= htmlspecialchars($c['commodity_display']) ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg" style="max-width:160px;">
                    <label>Price Type</label>
                    <select id="cmp_com_ptype">
                        <option value="Wholesale">Wholesale</option>
                        <option value="Retail">Retail</option>
                        <option value="Both">Both</option>
                    </select>
                </div>
                <?php $this_days_opts = [30=>'Last 30 days',60=>'Last 60 days',90=>'Last 90 days',180=>'Last 6 months',365=>'Last year']; ?>
                <div class="cmp-fg" style="max-width:160px;">
                    <label>Time Range</label>
                    <select id="cmp_com_days">
                        <?php foreach ($this_days_opts as $v=>$l): ?><option value="<?=$v?>" <?=$v==90?'selected':''?>><?=$l?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ── MODE: MARKET ── -->
            <div id="cmp-cfg-market" class="cmp-config" style="display:none;">
                <div class="cmp-fg">
                    <label>Commodity</label>
                    <select id="cmp_mkt_commodity">
                        <option value="">Any commodity</option>
                        <?php foreach ($all_commodities as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg">
                    <label>Markets to compare <span class="cmp-chip-limit">(up to 4)</span></label>
                    <div class="cmp-chips" id="cmp_mkt_chips">
                        <span class="cmp-chip-hint" id="cmp_mkt_hint">Pick from list below…</span>
                    </div>
                    <select id="cmp_mkt_picker" onchange="addCmpChip('market',this)" style="margin-top:6px;">
                        <option value="">+ Add market…</option>
                        <?php foreach ($all_markets as $m): ?>
                            <option value="<?= $m['market_id'] ?>" data-label="<?= htmlspecialchars($m['market']) ?> (<?= htmlspecialchars($m['country_admin_0']) ?>)"><?= htmlspecialchars($m['market']) ?> — <?= htmlspecialchars($m['country_admin_0']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg" style="max-width:160px;">
                    <label>Price Type</label>
                    <select id="cmp_mkt_ptype">
                        <option value="Wholesale">Wholesale</option>
                        <option value="Retail">Retail</option>
                        <option value="Both">Both</option>
                    </select>
                </div>
                <div class="cmp-fg" style="max-width:160px;">
                    <label>Time Range</label>
                    <select id="cmp_mkt_days">
                        <?php foreach ($this_days_opts as $v=>$l): ?><option value="<?=$v?>" <?=$v==90?'selected':''?>><?=$l?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ── MODE: COUNTRY ── -->
            <div id="cmp-cfg-country" class="cmp-config" style="display:none;">
                <div class="cmp-fg">
                    <label>Commodity (optional)</label>
                    <select id="cmp_cty_commodity">
                        <option value="">All commodities</option>
                        <?php foreach ($all_commodities as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg">
                    <label>Countries to compare <span class="cmp-chip-limit">(up to 4)</span></label>
                    <div class="cmp-chips" id="cmp_cty_chips">
                        <span class="cmp-chip-hint" id="cmp_cty_hint">Pick from list below…</span>
                    </div>
                    <select id="cmp_cty_picker" onchange="addCmpChip('country',this)" style="margin-top:6px;">
                        <option value="">+ Add country…</option>
                        <?php foreach ($countries_in_db as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" data-label="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cmp-fg" style="max-width:160px;">
                    <label>Price Type</label>
                    <select id="cmp_cty_ptype">
                        <option value="Wholesale">Wholesale</option>
                        <option value="Retail">Retail</option>
                        <option value="Both">Both</option>
                    </select>
                </div>
                <div class="cmp-fg" style="max-width:160px;">
                    <label>Time Range</label>
                    <select id="cmp_cty_days">
                        <?php foreach ($this_days_opts as $v=>$l): ?><option value="<?=$v?>" <?=$v==90?'selected':''?>><?=$l?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Action row -->
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button class="mp-btn primary" onclick="runComparison()">
                    <span class="ms">compare_arrows</span> Run Comparison
                </button>
                <button class="mp-btn ghost" onclick="clearComparison()">
                    <span class="ms">restart_alt</span> Reset
                </button>
                <span id="cmpStatus" style="font-size:.8rem;color:var(--mp-muted);"></span>
            </div>
        </div>

        <!-- Results area -->
        <div id="cmpResults" style="display:none;">

            <!-- Line trend chart -->
            <div class="cmp-panel-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                    <div>
                        <h3 style="margin:0;font-size:1rem;font-weight:600;" id="cmpChartTitle">Price Trends</h3>
                        <p style="margin:4px 0 0;font-size:.8rem;color:var(--mp-muted);" id="cmpChartSub">Average price over time</p>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="mp-btn ghost" id="cmpChartTypeBtn" onclick="toggleCmpChartType()" style="font-size:.75rem;padding:4px 12px;">
                            <span class="ms" style="font-size:.9rem;">bar_chart</span> Switch to Bar
                        </button>
                    </div>
                </div>
                <div id="cmpLegendRow" style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:14px;"></div>
                <div class="cmp-chart-wrap">
                    <canvas id="cmpLineChart"></canvas>
                </div>
            </div>

            <!-- Summary stats table -->
            <div class="cmp-panel-card">
                <h3 style="margin:0 0 14px;font-size:1rem;font-weight:600;">Summary Statistics</h3>
                <div class="cmp-summary">
                    <table id="cmpSummaryTable">
                        <thead>
                            <tr>
                                <th>Series</th>
                                <th>Price Type</th>
                                <th>Avg Price</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Data Points</th>
                                <th>Trend (period)</th>
                            </tr>
                        </thead>
                        <tbody id="cmpSummaryBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Spread / difference chart (shown when exactly 2 series selected) -->
            <div class="cmp-panel-card" id="cmpSpreadCard" style="display:none;">
                <h3 style="margin:0 0 6px;font-size:1rem;font-weight:600;">Price Difference Over Time</h3>
                <p style="margin:0 0 14px;font-size:.8rem;color:var(--mp-muted);" id="cmpSpreadLabel">Series A − Series B</p>
                <div class="cmp-chart-wrap2">
                    <canvas id="cmpSpreadChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Empty state -->
        <div id="cmpEmpty" class="cmp-panel-card">
            <div class="cmp-empty">
                <span class="ms">compare_arrows</span>
                <p style="font-weight:600;color:var(--mp-text);margin-bottom:6px;">Select items to compare</p>
                <p>Choose a comparison mode above, pick 2–4 items, then click <strong>Run Comparison</strong>.</p>
                <p style="margin-top:8px;font-size:.8rem;">Examples: Maize vs Beans in Kenya · Nairobi vs Mombasa market prices · Kenya vs Uganda for rice</p>
            </div>
        </div>
    </div>

</div><!-- /mp-wrap -->

<!-- Export form (hidden) -->
<form id="exportForm" method="POST" action="" target="_blank" style="display:none;">
    <input type="hidden" name="export_format" id="exportFormat">
    <input type="hidden" name="export_all" id="exportAll" value="">
    <input type="hidden" name="selected_ids" id="selectedIds" value="">
</form>

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
let _cmpChart = null;
let _cmpSpread = null;
let _cmpChartType = 'line';
let _cmpSelections = { commodity: [], market: [], country: [] };
let _cmpLastData = null;
const PAGE_URL = window.location.href.split('?')[0];

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
    const tabs = ['table','charts','cards','map','compare'];
    document.querySelectorAll('.mp-tab')[tabs.indexOf(tab)]?.classList.add('active');
    document.getElementById('panel-' + tab)?.classList.add('active');
    if (tab === 'charts' && !_priceChart) { setTimeout(loadChartData, 100); }
    if (tab === 'cards')  { loadCardsData(); }
    if (tab === 'map')    { initMap(); }
    if (tab === 'compare' && !_cmpLastData) { /* show empty state, nothing to load */ }
}

// ─────────────────────────────────────────────────────────────
// MODAL HELPERS
// ─────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// ─────────────────────────────────────────────────────────────
// EXPORT DROPDOWN
// ─────────────────────────────────────────────────────────────
function mpExportToggle() { 
    const menu = document.getElementById('exportDropdownMenu');
    menu.classList.toggle('open');
}
document.addEventListener('click', e => {
    const menu = document.getElementById('exportDropdownMenu');
    if (menu && !e.target.closest('#exportDropdown') && !menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});

// ─────────────────────────────────────────────────────────────
// SELECTION (Table)
// ─────────────────────────────────────────────────────────────
function mpCheckboxChange(cb) {
    const gk = cb.getAttribute('data-group-key');
    let ids = []; 
    try { 
        ids = JSON.parse(cb.getAttribute('data-group-ids') || '[]'); 
    } catch(e) {}
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
        let ids = []; 
        try { 
            ids = JSON.parse(cb.getAttribute('data-group-ids') || '[]'); 
        } catch(e) {}
        ids.forEach(id => masterCb.checked ? allSelectedIds.add(String(id)) : allSelectedIds.delete(String(id)));
        const gk = cb.getAttribute('data-group-key');
        document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => {
            if (r.getAttribute('data-group-key') === gk) r.classList.toggle('mp-selected', masterCb.checked);
        });
    });
    mpSyncUI();
}

function clearAllSelections() {
    allSelectedIds.clear();
    document.querySelectorAll('#pricesTableBody .row-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('#pricesTableBody tr.price-row').forEach(r => r.classList.remove('mp-selected'));
    const sa = document.getElementById('selectAll');
    if (sa) { sa.checked = false; sa.indeterminate = false; }
    mpSyncUI();
}

function mpSyncUI() {
    const count = allSelectedIds.size;
    document.getElementById('selectedCount').textContent = count;
    
    // Update export selected buttons
    const exportCsv = document.getElementById('exportSelectedCsv');
    const exportPdf = document.getElementById('exportSelectedPdf');
    if (exportCsv) {
        exportCsv.style.opacity = count === 0 ? '0.5' : '1';
        exportCsv.style.pointerEvents = count === 0 ? 'none' : 'auto';
    }
    if (exportPdf) {
        exportPdf.style.opacity = count === 0 ? '0.5' : '1';
        exportPdf.style.pointerEvents = count === 0 ? 'none' : 'auto';
    }
    
    const sum = document.getElementById('selectionSummary');
    if (sum) sum.textContent = count > 0 ? `(${count} selected)` : '';
    
    const vCbs = [...document.querySelectorAll('#pricesTableBody .row-checkbox')].filter(c => !c.closest('tr').classList.contains('mp-filtered-out'));
    const chk  = vCbs.filter(c => c.checked);
    const sa = document.getElementById('selectAll');
    if (sa) { sa.checked = vCbs.length > 0 && chk.length === vCbs.length; sa.indeterminate = chk.length > 0 && chk.length < vCbs.length; }
}

function mpGetSelectedIds() { return Array.from(allSelectedIds); }

// ─────────────────────────────────────────────────────────────
// EXPORT FUNCTIONS
// ─────────────────────────────────────────────────────────────
function exportSelected(fmt) { 
    const ids = mpGetSelectedIds(); 
    if(!ids.length){ alert('Select items first.'); return; } 
    mpSubmitExport(fmt, ids, false); 
}

function exportAll(fmt) { 
    if(!confirm('Export ALL published prices?')) return; 
    mpSubmitExport(fmt, [], true); 
}

function mpSubmitExport(fmt, ids, doAll) {
    const form = document.getElementById('exportForm');
    document.getElementById('exportFormat').value = fmt;
    if (doAll) {
        document.getElementById('exportAll').value = 'true';
        document.getElementById('selectedIds').value = '';
    } else {
        document.getElementById('exportAll').value = '';
        document.getElementById('selectedIds').value = ids.join(',');
    }
    form.submit();
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
// CHARTS — custom date range
// ─────────────────────────────────────────────────────────────
function onChartDaysChange() {
    const val = document.getElementById('chart_days').value;
    const customRow = document.getElementById('chartCustomRange');
    if (val === 'custom') {
        customRow.classList.add('visible');
        const today = new Date();
        const from  = new Date(); from.setDate(today.getDate() - 30);
        document.getElementById('chart_date_to').value   = today.toISOString().split('T')[0];
        document.getElementById('chart_date_from').value = from.toISOString().split('T')[0];
        loadChartData();
    } else {
        customRow.classList.remove('visible');
        loadChartData();
    }
}

function clearChartCustomRange() {
    document.getElementById('chart_days').value = '90';
    document.getElementById('chartCustomRange').classList.remove('visible');
    loadChartData();
}

function onChartCountryChange() {
    const country = document.getElementById('chart_country').value;
    const marketSel = document.getElementById('chart_market');
    Array.from(marketSel.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        opt.hidden = country ? opt.getAttribute('data-country') !== country : false;
    });
    if (marketSel.selectedOptions[0]?.hidden) marketSel.value = '';
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
        data.forEach(c=>{
            const o=document.createElement('option');
            o.value=c.id;
            o.textContent=c.commodity_display;
            if(String(c.id)===String(curVal))o.selected=true;
            comSel.appendChild(o);
        });
    });
}

function loadChartData() {
    const country      = document.getElementById('chart_country')?.value    || '';
    const market_id    = document.getElementById('chart_market')?.value     || '';
    const commodity_id = document.getElementById('chart_commodity')?.value  || '';
    const daysVal      = document.getElementById('chart_days')?.value       || '90';
    const date_from    = document.getElementById('chart_date_from')?.value  || '';
    const date_to      = document.getElementById('chart_date_to')?.value    || '';
    const loader       = document.getElementById('chartLoadingIndicator');
    if (loader) loader.style.display = 'flex';

    let url = '?chart_data=1';
    if (daysVal === 'custom' && date_from && date_to) {
        url += `&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}`;
    } else {
        url += `&days=${daysVal !== 'custom' ? daysVal : '90'}`;
    }
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
    const wsMap = {}, rtMap = {};
    rawData.forEach(r => {
        if (r.price_type === 'Wholesale') wsMap[r.date_label] = parseFloat(r.avg_price);
        if (r.price_type === 'Retail')    rtMap[r.date_label] = parseFloat(r.avg_price);
    });
    const allDates = [...new Set(rawData.map(r=>r.date_label))].sort();
    const wsValues = allDates.map(d => wsMap[d] ?? null);
    const rtValues = allDates.map(d => rtMap[d] ?? null);
    const spread   = allDates.map(d => (rtMap[d]!=null && wsMap[d]!=null) ? parseFloat((rtMap[d]-wsMap[d]).toFixed(4)) : null);
    const labels = allDates.map(d => { const dt = new Date(d); return dt.toLocaleDateString('en-GB', {day:'2-digit',month:'short'}); });

    const wsFiltered = wsValues.filter(v=>v!==null);
    const rtFiltered = rtValues.filter(v=>v!==null);
    const allPrices  = [...wsFiltered, ...rtFiltered];
    document.getElementById('stat-avg-ws').textContent   = wsFiltered.length ? '$'+( wsFiltered.reduce((a,b)=>a+b,0)/wsFiltered.length ).toFixed(4) : '—';
    document.getElementById('stat-avg-rt').textContent   = rtFiltered.length ? '$'+( rtFiltered.reduce((a,b)=>a+b,0)/rtFiltered.length ).toFixed(4) : '—';
    document.getElementById('stat-range').textContent    = allPrices.length  ? '$'+Math.min(...allPrices).toFixed(4)+' – $'+Math.max(...allPrices).toFixed(4) : '—';
    document.getElementById('stat-points').textContent   = rawData.reduce((a,r)=>a+parseInt(r.record_count),0).toLocaleString();
    const wsV30 = wsFiltered.slice(-10); const wsV0 = wsFiltered.slice(0,10);
    const trendVal = wsV30.length && wsV0.length ? ((wsV30[wsV30.length-1]-wsV0[0])/wsV0[0]*100) : null;
    const trendEl = document.getElementById('stat-trend');
    if (trendVal !== null) { trendEl.textContent = (trendVal>=0?'▲ +':'▼ ')+trendVal.toFixed(1)+'%'; trendEl.style.color = trendVal >= 0 ? '#16a34a' : '#dc2626'; }
    else { trendEl.textContent = '—'; trendEl.style.color=''; }
    const wsLast = wsFiltered[wsFiltered.length-1]; const rtLast = rtFiltered[rtFiltered.length-1];
    document.getElementById('legend-ws-latest').textContent = wsLast ? '$'+wsLast.toFixed(4) : '—';
    document.getElementById('legend-rt-latest').textContent = rtLast ? '$'+rtLast.toFixed(4) : '—';

    const ctx = document.getElementById('priceChart').getContext('2d');
    if (_priceChart) _priceChart.destroy();
    _priceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label:'Wholesale', data:wsValues, borderColor:'#7c3aed', backgroundColor:'rgba(124,58,237,0.06)', borderWidth:2.5, pointRadius:3, pointHoverRadius:7, pointBackgroundColor:'#7c3aed', pointBorderColor:'#fff', pointBorderWidth:2, fill:false, tension:0.35, spanGaps:true },
                { label:'Retail',    data:rtValues, borderColor:'#db2777', backgroundColor:'rgba(219,39,119,0.06)', borderWidth:2.5, borderDash:[6,3], pointRadius:3, pointHoverRadius:7, pointBackgroundColor:'#db2777', pointBorderColor:'#fff', pointBorderWidth:2, fill:false, tension:0.35, spanGaps:true }
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{
                legend:{display:false},
                tooltip:{backgroundColor:'rgba(255,255,255,0.97)',titleColor:'#1f2937',bodyColor:'#374151',borderColor:'#e5e7eb',borderWidth:1,padding:12,
                    callbacks:{title:items=>items[0].label,label:item=>` ${item.dataset.label}: $${Number(item.raw).toFixed(4)}`,afterBody:items=>{const ws=items.find(i=>i.dataset.label==='Wholesale');const rt=items.find(i=>i.dataset.label==='Retail');if(ws&&rt&&ws.raw&&rt.raw)return['',` Spread: $${(rt.raw-ws.raw).toFixed(4)}`];return[];}}}
            },
            scales:{
                x:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{color:'#6b7280',font:{size:11},maxRotation:45,autoSkip:true,maxTicksLimit:12},border:{color:'rgba(0,0,0,0.08)'}},
                y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{color:'#6b7280',font:{size:11},callback:v=>'$'+Number(v).toFixed(4)},border:{color:'rgba(0,0,0,0.08)',dash:[4,4]}}
            }
        }
    });

    const ctx2 = document.getElementById('spreadChart').getContext('2d');
    if (_spreadChart) _spreadChart.destroy();
    _spreadChart = new Chart(ctx2, {
        type:'line', data:{labels,datasets:[{label:'Spread (Retail − Wholesale)',data:spread,borderColor:'#0891b2',backgroundColor:'rgba(8,145,178,0.08)',borderWidth:2,pointRadius:2,pointHoverRadius:5,fill:true,tension:0.4,spanGaps:true}]},
        options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(255,255,255,0.97)',titleColor:'#1f2937',bodyColor:'#374151',borderColor:'#e5e7eb',borderWidth:1,padding:10,callbacks:{label:item=>` Spread: $${Number(item.raw).toFixed(4)}`}}},
            scales:{x:{grid:{color:'rgba(0,0,0,0.03)'},ticks:{color:'#6b7280',font:{size:10},maxTicksLimit:10,autoSkip:true},border:{color:'rgba(0,0,0,0.08)'}},y:{grid:{color:'rgba(0,0,0,0.03)'},ticks:{color:'#6b7280',font:{size:10},callback:v=>'$'+Number(v).toFixed(4)},border:{color:'rgba(0,0,0,0.08)',dash:[4,4]}}}}
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

    let wsUrl = `?map_data=1&price_type=Wholesale`;
    if (commodity_id) wsUrl += `&commodity_id=${commodity_id}`;
    let rtUrl = `?map_data=1&price_type=Retail`;
    if (commodity_id) rtUrl += `&commodity_id=${commodity_id}`;

    Promise.all([fetch(wsUrl).then(r=>r.json()), fetch(rtUrl).then(r=>r.json())])
    .then(([wsResp, rtResp]) => {
        const wsMap = {}; if (wsResp.success) wsResp.data.forEach(r => wsMap[r.market_id] = r);
        const rtMap = {}; if (rtResp.success) rtResp.data.forEach(r => rtMap[r.market_id] = r);
        const allMarketIds = [...new Set([...Object.keys(wsMap), ...Object.keys(rtMap)])];
        _cardsData = allMarketIds.map(mid => ({
            market_id: mid,
            market:    wsMap[mid]?.market    || rtMap[mid]?.market    || '',
            country:   wsMap[mid]?.country_admin_0 || rtMap[mid]?.country_admin_0 || '',
            ws_price:  wsMap[mid]?.avg_price  || null,
            rt_price:  rtMap[mid]?.avg_price  || null,
            latest:    wsMap[mid]?.latest_date || rtMap[mid]?.latest_date || '',
        })).filter(c => {
            if (country   && c.country   !== country)              return false;
            if (market_id && String(c.market_id) !== market_id)    return false;
            return true;
        });
        if (!_cardsData.length) { grid.innerHTML='<div class="mp-no-data"><span class="ms">inbox</span><p>No published price records found for these filters.</p></div>'; return; }
        sortCards();
    });
}

function sortCards() {
    const sortBy = document.getElementById('cards_sort')?.value || 'market';
    _cardsData.sort((a,b) => {
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
                        <div style="font-size:.7rem;color:#6b7280;margin-top:2px;">avg USD/unit</div>
                    </div>
                    <div class="mp-card-price-box">
                        <div class="mp-card-price-type rt">Retail</div>
                        <div class="mp-card-price-val">${rt}</div>
                        <div style="font-size:.7rem;color:#6b7280;margin-top:2px;">avg USD/unit</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0 0;border-top:1px solid #f3f4f6;font-size:.75rem;color:#6b7280;">
                    <span><span class="ms" style="font-size:.85rem;vertical-align:middle;">trending_up</span> Spread: <strong style="color:#1f2937;">${spread}</strong></span>
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
    if (!mapEl || _leafletMap) { if (_leafletMap) loadMapData(); return; }
    if (typeof L === 'undefined') { setTimeout(initMap, 300); return; }
    _leafletMap = L.map('mp-map', { zoomControl: true, scrollWheelZoom: true }).setView([1.5, 32], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors', maxZoom: 18 }).addTo(_leafletMap);
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
        _mapMarkers.forEach(m => m.remove()); _mapMarkers = [];
        if (!resp.success || !resp.data.length) { document.getElementById('mapMarkerCount').textContent = 'No data found'; return; }
        const prices = resp.data.map(r => parseFloat(r.avg_price));
        const minP = Math.min(...prices), maxP = Math.max(...prices);
        document.getElementById('mapMarkerCount').textContent = `${resp.data.length} market${resp.data.length!==1?'s':''} found`;
        function priceColor(p) { const t=maxP>minP?(p-minP)/(maxP-minP):0.5; if(t<0.2)return'#bee3f8';if(t<0.4)return'#63b3ed';if(t<0.6)return'#3182ce';if(t<0.8)return'#9c2424';return'#800000'; }
        function priceRadius(p) { const t=maxP>minP?(p-minP)/(maxP-minP):0.5; return 8+t*18; }
        resp.data.forEach(mkt => {
            const lat=parseFloat(mkt.latitude), lng=parseFloat(mkt.longitude);
            if(isNaN(lat)||isNaN(lng))return;
            const price=parseFloat(mkt.avg_price);
            const date=mkt.latest_date?new Date(mkt.latest_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}):'—';
            const circle = L.circleMarker([lat,lng],{radius:priceRadius(price),fillColor:priceColor(price),color:'#fff',weight:2,opacity:0.9,fillOpacity:0.78}).addTo(_leafletMap);
            circle.bindPopup(`<div style="min-width:170px;font-family:'Segoe UI',sans-serif;"><div style="font-weight:700;font-size:.95rem;color:#1f2937;margin-bottom:4px;">${mkt.market}</div><div style="font-size:.8rem;color:#6b7280;margin-bottom:8px;">${countryFlag(mkt.country_admin_0)} ${mkt.country_admin_0}</div><div style="display:flex;justify-content:space-between;align-items:center;background:#fef9f9;border-radius:6px;padding:8px 10px;"><span style="font-size:.75rem;color:#6b7280;">${price_type} avg</span><span style="font-size:1.1rem;font-weight:700;font-family:monospace;color:#800000;">$${price.toFixed(4)}</span></div><div style="font-size:.75rem;color:#9ca3af;margin-top:6px;">Latest: ${date}</div></div>`);
            _mapMarkers.push(circle);
        });
        if (_mapMarkers.length) { const group=L.featureGroup(_mapMarkers); _leafletMap.fitBounds(group.getBounds().pad(0.15)); }
    }).catch(()=>{ if(overlay)overlay.style.display='none'; });
}

// ─────────────────────────────────────────────────────────────
// COMPARE TAB
// ─────────────────────────────────────────────────────────────
const CMP_COLORS = ['#7c3aed','#db2777','#0891b2','#d97706'];
const CMP_COLORS_LIGHT = ['rgba(124,58,237,0.08)','rgba(219,39,119,0.08)','rgba(8,145,178,0.08)','rgba(217,119,6,0.08)'];
let _cmpMode = 'commodity';

function setCmpMode(mode) {
    _cmpMode = mode;
    ['commodity','market','country'].forEach(m => {
        document.getElementById('pill-'+m).classList.toggle('active', m === mode);
        document.getElementById('cmp-cfg-'+m).style.display = m === mode ? 'flex' : 'none';
    });
}

function addCmpChip(type, sel) {
    if (!sel.value) return;
    const arr = _cmpSelections[type];
    if (arr.length >= 4) { alert('Maximum 4 items can be compared at once.'); sel.value=''; return; }
    if (arr.find(x => String(x.value) === String(sel.value))) { sel.value=''; return; }
    const label = sel.options[sel.selectedIndex]?.getAttribute('data-label') || sel.options[sel.selectedIndex]?.text || sel.value;
    arr.push({ value: sel.value, label });
    sel.value = '';
    renderCmpChips(type);
}

function removeCmpChip(type, val) {
    _cmpSelections[type] = _cmpSelections[type].filter(x => String(x.value) !== String(val));
    renderCmpChips(type);
}

function renderCmpChips(type) {
    const prefixMap = { commodity:'cmp_com', market:'cmp_mkt', country:'cmp_cty' };
    const pfx  = prefixMap[type];
    const arr  = _cmpSelections[type];
    const cont = document.getElementById(pfx + '_chips');
    const hint = document.getElementById(pfx + '_hint');
    if (!cont) return;
    cont.querySelectorAll('.cmp-chip').forEach(c => c.remove());
    arr.forEach((item, i) => {
        const chip = document.createElement('span');
        chip.className = 'cmp-chip';
        chip.style.cssText = `background:${CMP_COLORS_LIGHT[i]};color:${CMP_COLORS[i]};border:1px solid ${CMP_COLORS[i]}40`;
        chip.innerHTML = `<span style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(item.label)}</span><button class="cmp-chip-rm" onclick="removeCmpChip('${type}','${item.value}')" title="Remove">✕</button>`;
        cont.insertBefore(chip, hint);
    });
    if (hint) hint.style.display = arr.length > 0 ? 'none' : 'inline';
}

function onCmpComCountryChange() {
    const country = document.getElementById('cmp_com_country').value;
    const mSel    = document.getElementById('cmp_com_market');
    Array.from(mSel.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        opt.hidden = country ? opt.getAttribute('data-country') !== country : false;
    });
    if (mSel.selectedOptions[0]?.hidden) mSel.value = '';
}

function runComparison() {
    const status = document.getElementById('cmpStatus');
    const results = document.getElementById('cmpResults');
    const empty   = document.getElementById('cmpEmpty');
    status.textContent = '';

    let url = `?compare_data=1&mode=${_cmpMode}`;

    if (_cmpMode === 'commodity') {
        const ids = _cmpSelections.commodity.map(x => x.value);
        if (ids.length < 2) { status.textContent = '⚠ Select at least 2 commodities.'; return; }
        url += `&commodity_ids=${ids.join(',')}&price_type=${document.getElementById('cmp_com_ptype').value}&days=${document.getElementById('cmp_com_days').value}`;
        const country = document.getElementById('cmp_com_country').value;
        const market  = document.getElementById('cmp_com_market').value;
        if (country) url += `&country=${encodeURIComponent(country)}`;
        if (market)  url += `&market_id=${market}`;
    } else if (_cmpMode === 'market') {
        const ids = _cmpSelections.market.map(x => x.value);
        if (ids.length < 2) { status.textContent = '⚠ Select at least 2 markets.'; return; }
        url += `&market_ids=${ids.join(',')}&price_type=${document.getElementById('cmp_mkt_ptype').value}&days=${document.getElementById('cmp_mkt_days').value}`;
        const com = document.getElementById('cmp_mkt_commodity').value;
        if (com) url += `&commodity_id=${com}`;
    } else {
        const countries = _cmpSelections.country.map(x => x.value);
        if (countries.length < 2) { status.textContent = '⚠ Select at least 2 countries.'; return; }
        url += `&countries=${encodeURIComponent(countries.join(','))}&price_type=${document.getElementById('cmp_cty_ptype').value}&days=${document.getElementById('cmp_cty_days').value}`;
        const com = document.getElementById('cmp_cty_commodity').value;
        if (com) url += `&commodity_id=${com}`;
    }

    status.innerHTML = '<span class="ms mp-spinner" style="font-size:.9rem;vertical-align:middle;">hourglass_empty</span> Loading…';

    fetch(url).then(r => r.json()).then(resp => {
        status.textContent = '';
        if (!resp.success || !resp.data?.length) {
            status.textContent = '⚠ No data found for the selected combination.';
            results.style.display = 'none';
            empty.style.display   = 'block';
            return;
        }
        _cmpLastData = resp;
        empty.style.display   = 'none';
        results.style.display = 'block';
        _cmpChartType = 'line';
        document.getElementById('cmpChartTypeBtn').innerHTML = '<span class="ms" style="font-size:.9rem;">bar_chart</span> Switch to Bar';
        renderCmpCharts(resp);
    }).catch(() => { status.textContent = '⚠ Request failed. Please try again.'; });
}

function renderCmpCharts(resp) {
    const data = resp.data;
    const mode = resp.mode;

    const seriesMap = {};
    const ptypes    = [...new Set(data.map(r => r.price_type))];
    const useType   = ptypes.length > 1;

    data.forEach(row => {
        const key = useType ? `${row.label} [${row.price_type}]` : row.label;
        if (!seriesMap[key]) seriesMap[key] = { label: key, rawLabel: row.label, priceType: row.price_type, dates: {}, prices: [], minP: Infinity, maxP: -Infinity, cnt: 0 };
        const s = seriesMap[key];
        s.dates[row.d] = parseFloat(row.avg_price);
        s.prices.push(parseFloat(row.avg_price));
        s.minP = Math.min(s.minP, parseFloat(row.min_price));
        s.maxP = Math.max(s.maxP, parseFloat(row.max_price));
        s.cnt += parseInt(row.cnt);
    });

    const allDates  = [...new Set(data.map(r => r.d))].sort();
    const seriesList = Object.values(seriesMap);

    const datasets = seriesList.map((s, i) => ({
        label: s.label,
        data:  allDates.map(d => s.dates[d] ?? null),
        borderColor: CMP_COLORS[i % CMP_COLORS.length],
        backgroundColor: _cmpChartType === 'bar' ? CMP_COLORS[i % CMP_COLORS.length] + 'cc' : CMP_COLORS_LIGHT[i % CMP_COLORS.length],
        borderWidth: _cmpChartType === 'bar' ? 0 : 2.5,
        pointRadius: _cmpChartType === 'bar' ? 0 : 3,
        pointHoverRadius: 7,
        pointBackgroundColor: CMP_COLORS[i % CMP_COLORS.length],
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        fill: false,
        tension: 0.35,
        spanGaps: true,
        borderDash: i % 2 === 1 && ptypes.length > 1 ? [6,3] : [],
    }));

    const labels = allDates.map(d => {
        const dt = new Date(d); return dt.toLocaleDateString('en-GB', {day:'2-digit', month:'short'});
    });

    const modeLabel = { commodity:'Commodity Comparison', market:'Market Comparison', country:'Country Comparison' };
    document.getElementById('cmpChartTitle').textContent  = modeLabel[mode] || 'Price Comparison';
    document.getElementById('cmpChartSub').textContent    = `Average price (USD) · ${allDates[0]} → ${allDates[allDates.length-1]}`;

    const legendRow = document.getElementById('cmpLegendRow');
    legendRow.innerHTML = '';
    seriesList.forEach((s, i) => {
        const last = s.prices[s.prices.length - 1];
        legendRow.innerHTML += `
        <div style="display:flex;align-items:center;gap:8px;font-size:.8125rem;">
            <span class="cmp-swatch" style="background:${CMP_COLORS[i % CMP_COLORS.length]};width:14px;height:14px;border-radius:3px;"></span>
            <span>${escHtml(s.label)}</span>
            <strong style="color:${CMP_COLORS[i % CMP_COLORS.length]};">${last ? '$'+last.toFixed(4) : '—'}</strong>
        </div>`;
    });

    const ctx = document.getElementById('cmpLineChart').getContext('2d');
    if (_cmpChart) _cmpChart.destroy();
    _cmpChart = new Chart(ctx, {
        type: _cmpChartType,
        data: { labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,0.97)', titleColor:'#1f2937', bodyColor:'#374151',
                    borderColor:'#e5e7eb', borderWidth:1, padding:12,
                    callbacks: {
                        label: item => ` ${item.dataset.label}: $${Number(item.raw).toFixed(4)}`
                    }
                }
            },
            scales: {
                x: { grid:{color:'rgba(0,0,0,0.04)'}, ticks:{color:'#6b7280',font:{size:11},maxRotation:45,autoSkip:true,maxTicksLimit:14} },
                y: { grid:{color:'rgba(0,0,0,0.04)'}, ticks:{color:'#6b7280',font:{size:11},callback:v=>'$'+Number(v).toFixed(4)} }
            }
        }
    });

    const tbody = document.getElementById('cmpSummaryBody');
    tbody.innerHTML = '';
    seriesList.forEach((s, i) => {
        const avg  = s.prices.length ? s.prices.reduce((a,b)=>a+b,0)/s.prices.length : 0;
        const first = s.prices[0], last = s.prices[s.prices.length-1];
        let trend = '—', trendCls = 'flat';
        if (first && last && first !== last) {
            const pct = ((last - first) / first * 100).toFixed(1);
            trendCls  = pct >= 0 ? 'up' : 'dn';
            trend = `${pct >= 0 ? '▲' : '▼'} ${pct}%`;
        }
        tbody.innerHTML += `<tr>
            <td><span class="cmp-swatch" style="background:${CMP_COLORS[i % CMP_COLORS.length]};"></span>${escHtml(s.label)}</td>
            <td style="font-size:.75rem;color:var(--mp-muted);">${escHtml(s.priceType || '—')}</td>
            <td style="font-family:monospace;font-weight:700;">$${avg.toFixed(4)}</td>
            <td style="font-family:monospace;">$${s.minP === Infinity ? '—' : s.minP.toFixed(4)}</td>
            <td style="font-family:monospace;">$${s.maxP === -Infinity ? '—' : s.maxP.toFixed(4)}</td>
            <td>${s.cnt.toLocaleString()}</td>
            <td><span class="cmp-delta ${trendCls}">${trend}</span></td>
        </tr>`;
    });

    const spreadCard = document.getElementById('cmpSpreadCard');
    if (seriesList.length === 2) {
        spreadCard.style.display = 'block';
        const s0 = seriesList[0], s1 = seriesList[1];
        const spreadData = allDates.map(d => {
            const v0 = s0.dates[d], v1 = s1.dates[d];
            return (v0 != null && v1 != null) ? parseFloat((v0 - v1).toFixed(4)) : null;
        });
        document.getElementById('cmpSpreadLabel').textContent = `${s0.label} − ${s1.label}`;
        const ctx2 = document.getElementById('cmpSpreadChart').getContext('2d');
        if (_cmpSpread) _cmpSpread.destroy();
        _cmpSpread = new Chart(ctx2, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Difference',
                    data:  spreadData,
                    borderColor: '#0891b2',
                    backgroundColor: 'rgba(8,145,178,0.07)',
                    borderWidth: 2,
                    pointRadius: 2,
                    fill: true,
                    tension: 0.4,
                    spanGaps: true,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend:{display:false}, tooltip:{ backgroundColor:'rgba(255,255,255,.97)', titleColor:'#1f2937', bodyColor:'#374151', borderColor:'#e5e7eb', borderWidth:1, padding:10, callbacks:{label:item=>` Diff: $${Number(item.raw).toFixed(4)}`}} },
                scales: {
                    x: { grid:{color:'rgba(0,0,0,.03)'}, ticks:{color:'#6b7280',font:{size:10},maxTicksLimit:12,autoSkip:true} },
                    y: { grid:{color:'rgba(0,0,0,.03)'}, ticks:{color:'#6b7280',font:{size:10},callback:v=>'$'+Number(v).toFixed(4)} }
                }
            }
        });
    } else {
        spreadCard.style.display = 'none';
    }
}

function toggleCmpChartType() {
    _cmpChartType = _cmpChartType === 'line' ? 'bar' : 'line';
    const btn = document.getElementById('cmpChartTypeBtn');
    btn.innerHTML = _cmpChartType === 'line'
        ? '<span class="ms" style="font-size:.9rem;">bar_chart</span> Switch to Bar'
        : '<span class="ms" style="font-size:.9rem;">show_chart</span> Switch to Line';
    if (_cmpLastData) renderCmpCharts(_cmpLastData);
}

function clearComparison() {
    _cmpSelections = { commodity:[], market:[], country:[] };
    ['cmp_com_chips','cmp_mkt_chips','cmp_cty_chips'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.querySelectorAll('.cmp-chip').forEach(c=>c.remove()); }
    });
    ['cmp_com_hint','cmp_mkt_hint','cmp_cty_hint'].forEach(id => {
        const el = document.getElementById(id); if (el) el.style.display = 'inline';
    });
    document.getElementById('cmpResults').style.display = 'none';
    document.getElementById('cmpEmpty').style.display   = 'block';
    document.getElementById('cmpStatus').textContent    = '';
    _cmpLastData = null;
}

// ─────────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────────
(function mpInit() {
    ['searchMarket','searchCommodity','searchType'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('keydown', e => { if(e.key==='Enter')applyClientFilter(); if(e.key==='Escape')clearFilter(); }); }
    });
    if (typeof updateBreadcrumb==='function') updateBreadcrumb('Base','Market Prices');
    const activeTab = '<?= $active_tab ?>';
    if (activeTab==='charts') setTimeout(loadChartData, 200);
    if (activeTab==='cards')  setTimeout(loadCardsData, 200);
    if (activeTab==='map')    setTimeout(initMap, 300);
})();

const _s=document.createElement('style'); _s.textContent='@keyframes mpspin{to{transform:rotate(360deg)}}'; document.head.appendChild(_s);
</script>

<?php include 'user_footer.php'; ?>