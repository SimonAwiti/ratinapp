<?php
// market_prices_monitoring.php
session_start();


// ============================================================
// EXPORT CSV — must run BEFORE admin_header.php is included
// ============================================================
if (isset($_GET['export_csv'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="market_prices_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $start_date        = $_GET['start_date'] ?? '';
    $end_date          = $_GET['end_date'] ?? '';
    $search_enumerator = $_GET['search_enumerator'] ?? '';
    $search_market     = $_GET['search_market'] ?? '';
    $search_commodity  = $_GET['search_commodity'] ?? '';
    $filter_country    = $_GET['filter_country'] ?? '';

    $where_export  = "WHERE 1=1";
    $export_params = [];
    $export_types  = "";

    if (!empty($start_date) && !empty($end_date)) {
        $where_export   .= " AND DATE(date_posted) BETWEEN ? AND ?";
        $export_params[] = $start_date;
        $export_params[] = $end_date;
        $export_types   .= "ss";
    }
    if (!empty($search_enumerator)) {
        $where_export   .= " AND postedby LIKE ?";
        $export_params[] = '%' . $search_enumerator . '%';
        $export_types   .= "s";
    }
    if (!empty($search_market)) {
        $where_export   .= " AND market LIKE ?";
        $export_params[] = '%' . $search_market . '%';
        $export_types   .= "s";
    }
    if (!empty($search_commodity)) {
        $where_export   .= " AND (category LIKE ? OR commodity IN (SELECT id FROM commodities WHERE commodity_name LIKE ?))";
        $export_params[] = '%' . $search_commodity . '%';
        $export_params[] = '%' . $search_commodity . '%';
        $export_types   .= "ss";
    }
    if (!empty($filter_country)) {
        $where_export   .= " AND country_admin_0 = ?";
        $export_params[] = $filter_country;
        $export_types   .= "s";
    }

    $exp_stmt = $con->prepare("SELECT 
        mp.id, mp.category, c.commodity_name as commodity, mp.country_admin_0,
        mp.market, mp.weight, mp.unit, mp.price_type, mp.Price, mp.subject,
        DATE(CONCAT(mp.year, '-', mp.month, '-', mp.day)) as price_date,
        mp.date_posted, mp.variety, mp.data_source,
        mp.supplied_volume, mp.comments, mp.supply_status, mp.postedby
        FROM market_prices mp
        LEFT JOIN commodities c ON mp.commodity = c.id
        $where_export
        ORDER BY mp.date_posted DESC");
    if (!empty($export_params)) $exp_stmt->bind_param($export_types, ...$export_params);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', 'Category', 'Commodity', 'Country', 'Market', 'Weight', 'Unit',
        'Price Type', 'Price', 'Subject', 'Price Date', 'Date Posted',
        'Variety', 'Data Source', 'Supplied Volume', 'Comments', 'Supply Status', 'Submitted By'
    ]);
    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['category'], $row['commodity'], $row['country_admin_0'],
            $row['market'], $row['weight'], $row['unit'], $row['price_type'],
            number_format($row['Price'], 2, '.', ''), $row['subject'], $row['price_date'],
            $row['date_posted'], $row['variety'], $row['data_source'],
            $row['supplied_volume'], $row['comments'], $row['supply_status'], $row['postedby']
        ]);
    }
    fclose($out);
    $exp_stmt->close();
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
// HELPER FUNCTION TO GET MARKET LIST (expected markets)
// ============================================================
function getExpectedMarkets($con) {
    $result = $con->query("SELECT DISTINCT market, country_admin_0 FROM market_prices ORDER BY market");
    $markets = [];
    while ($row = $result->fetch_assoc()) {
        $markets[] = $row;
    }
    return $markets;
}

// ============================================================
// API HANDLER — fetch single market price for view modal
// ============================================================
if (isset($_GET['get_price']) && is_numeric($_GET['get_price'])) {
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_price'];
    $api_stmt = $con->prepare("SELECT 
        mp.*, c.commodity_name as commodity_name 
        FROM market_prices mp
        LEFT JOIN commodities c ON mp.commodity = c.id
        WHERE mp.id = ?");
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
// EXPORT CSV WITH DATE RANGE (DIRECT DOWNLOAD - FIXED)
// ============================================================
if (isset($_GET['export_csv'])) {
    // Clean output buffers
    while (ob_get_level()) ob_end_clean();
    
    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="market_prices_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $search_enumerator = $_GET['search_enumerator'] ?? '';
    $search_market = $_GET['search_market'] ?? '';
    $search_commodity = $_GET['search_commodity'] ?? '';
    $filter_country = $_GET['filter_country'] ?? '';
    
    $where_export = "WHERE 1=1";
    $export_params = [];
    $export_types = "";
    
    if (!empty($start_date) && !empty($end_date)) {
        $where_export .= " AND DATE(date_posted) BETWEEN ? AND ?";
        $export_params[] = $start_date;
        $export_params[] = $end_date;
        $export_types .= "ss";
    }
    if (!empty($search_enumerator)) {
        $where_export .= " AND postedby LIKE ?";
        $export_params[] = '%' . $search_enumerator . '%';
        $export_types .= "s";
    }
    if (!empty($search_market)) {
        $where_export .= " AND market LIKE ?";
        $export_params[] = '%' . $search_market . '%';
        $export_types .= "s";
    }
    if (!empty($search_commodity)) {
        $where_export .= " AND (category LIKE ? OR commodity IN (SELECT id FROM commodities WHERE commodity_name LIKE ?))";
        $export_params[] = '%' . $search_commodity . '%';
        $export_params[] = '%' . $search_commodity . '%';
        $export_types .= "ss";
    }
    if (!empty($filter_country)) {
        $where_export .= " AND country_admin_0 = ?";
        $export_params[] = $filter_country;
        $export_types .= "s";
    }
    
    $exp_query = "SELECT 
        mp.id, mp.category, c.commodity_name as commodity, mp.country_admin_0, 
        mp.market, mp.weight, mp.unit, mp.price_type, mp.Price, mp.subject,
        DATE(CONCAT(mp.year, '-', mp.month, '-', mp.day)) as price_date,
        mp.date_posted, mp.variety, mp.data_source, 
        mp.supplied_volume, mp.comments, mp.supply_status, mp.postedby
        FROM market_prices mp
        LEFT JOIN commodities c ON mp.commodity = c.id
        $where_export
        ORDER BY mp.date_posted DESC";
    
    $exp_stmt = $con->prepare($exp_query);
    if (!empty($export_params)) {
        $exp_stmt->bind_param($export_types, ...$export_params);
    }
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', 'Category', 'Commodity', 'Country', 'Market', 'Weight', 'Unit',
        'Price Type', 'Price', 'Subject', 'Price Date', 'Date Posted',
        'Variety', 'Data Source', 'Supplied Volume', 'Comments', 'Supply Status', 'Submitted By'
    ]);
    
    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['category'], $row['commodity'], $row['country_admin_0'],
            $row['market'], $row['weight'], $row['unit'], $row['price_type'],
            number_format($row['Price'], 2, '.', ''), $row['subject'], $row['price_date'],
            $row['date_posted'], $row['variety'], $row['data_source'],
            $row['supplied_volume'], $row['comments'], $row['supply_status'], $row['postedby']
        ]);
    }
    fclose($out);
    $exp_stmt->close();
    exit;
}

// ============================================================
// GET DATE RANGE FROM REQUEST
// ============================================================
$date_preset = $_GET['date_preset'] ?? 'today';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($date_preset == 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($date_preset == 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d');
} elseif ($date_preset == 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
} elseif ($date_preset == 'custom' && !empty($start_date) && !empty($end_date)) {
    // keep as is
} elseif (!empty($start_date) && !empty($end_date)) {
    $date_preset = 'custom';
} else {
    $date_preset = 'today';
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
}

// ============================================================
// STATISTICS WITH DATE RANGE
// ============================================================
$date_condition = "";
$stats_params = [];
$stats_types = "";

if (!empty($start_date) && !empty($end_date)) {
    $date_condition = "WHERE DATE(date_posted) BETWEEN ? AND ?";
    $stats_params = [$start_date, $end_date];
    $stats_types = "ss";
}

$submissions_query = "SELECT COUNT(DISTINCT market) as count FROM market_prices $date_condition";
$submissions_stmt = $con->prepare($submissions_query);
if (!empty($stats_params)) $submissions_stmt->bind_param($stats_types, ...$stats_params);
$submissions_stmt->execute();
$markets_with_submissions = (int)$submissions_stmt->get_result()->fetch_assoc()['count'];
$submissions_stmt->close();

$total_submissions_query = "SELECT COUNT(*) as count FROM market_prices $date_condition";
$total_stmt = $con->prepare($total_submissions_query);
if (!empty($stats_params)) $total_stmt->bind_param($stats_types, ...$stats_params);
$total_stmt->execute();
$total_submissions = (int)$total_stmt->get_result()->fetch_assoc()['count'];
$total_stmt->close();

$commodities_query = "SELECT COUNT(DISTINCT commodity) as count FROM market_prices $date_condition";
$commodities_stmt = $con->prepare($commodities_query);
if (!empty($stats_params)) $commodities_stmt->bind_param($stats_types, ...$stats_params);
$commodities_stmt->execute();
$total_commodities = (int)$commodities_stmt->get_result()->fetch_assoc()['count'];
$commodities_stmt->close();

$all_expected_markets = getExpectedMarkets($con);
$total_expected_markets = count($all_expected_markets);
$markets_no_submission = max(0, $total_expected_markets - $markets_with_submissions);

$enumerators_query = "SELECT COUNT(DISTINCT postedby) as count FROM market_prices $date_condition";
$enumerators_stmt = $con->prepare($enumerators_query);
if (!empty($stats_params)) $enumerators_stmt->bind_param($stats_types, ...$stats_params);
$enumerators_stmt->execute();
$unique_enumerators = (int)$enumerators_stmt->get_result()->fetch_assoc()['count'];
$enumerators_stmt->close();

// ============================================================
// PAGINATION + SORTING + FILTERING
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts = ['market', 'commodity', 'date_posted'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'date_posted';

$search_enumerator = trim($_GET['search_enumerator'] ?? '');
$search_market = trim($_GET['search_market'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');
$filter_country = trim($_GET['filter_country'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND DATE(date_posted) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

if ($search_enumerator !== '') {
    $where .= " AND postedby LIKE ?";
    $params[] = '%' . $search_enumerator . '%';
    $types .= "s";
}
if ($search_market !== '') {
    $where .= " AND market LIKE ?";
    $params[] = '%' . $search_market . '%';
    $types .= "s";
}
if ($search_commodity !== '') {
    $where .= " AND (category LIKE ? OR commodity IN (SELECT id FROM commodities WHERE commodity_name LIKE ?))";
    $params[] = '%' . $search_commodity . '%';
    $params[] = '%' . $search_commodity . '%';
    $types .= "ss";
}
if ($filter_country !== '') {
    $where .= " AND country_admin_0 = ?";
    $params[] = $filter_country;
    $types .= "s";
}

$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM market_prices $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($page - 1) * $limit;

$countries_result = $con->query("SELECT DISTINCT country_admin_0 FROM market_prices ORDER BY country_admin_0");
$distinct_countries = [];
while ($row = $countries_result->fetch_assoc()) {
    $distinct_countries[] = $row['country_admin_0'];
}

// ============================================================
// ENUMERATOR SUMMARY (Excel-style: Market Days / Submissions /
// Commodities, broken out by calendar week Mon–Sun)
//
// Definitions (matched against the enumerator's own Excel workbook):
//   Market Days  = distinct days the enumerator had ANY submission
//                  in that week
//   Submissions  = total number of price rows (Wholesale + Retail
//                  entries) submitted in that week
//   Commodities  = sum, across each day the enumerator submitted,
//                  of the distinct commodities recorded that day
//                  (mirrors how the workbook's Raw Data tab is
//                  rolled up day-by-day, not a week-wide distinct
//                  count)
// ============================================================
$summary_where = "WHERE 1=1";
$summary_params = [];
$summary_types = "";

if (!empty($start_date) && !empty($end_date)) {
    $summary_where .= " AND DATE(date_posted) BETWEEN ? AND ?";
    $summary_params[] = $start_date;
    $summary_params[] = $end_date;
    $summary_types  .= "ss";
}
if ($filter_country !== '') {
    $summary_where .= " AND country_admin_0 = ?";
    $summary_params[] = $filter_country;
    $summary_types  .= "s";
}

$summary_sql = "SELECT postedby, DATE(date_posted) as sub_date, commodity
    FROM market_prices
    $summary_where
    ORDER BY postedby, sub_date";
$summary_stmt = $con->prepare($summary_sql);
if (!empty($summary_params)) $summary_stmt->bind_param($summary_types, ...$summary_params);
$summary_stmt->execute();
$summary_rows = $summary_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$summary_stmt->close();

// Step 1: roll every row up to enumerator + day
$day_agg = []; // [enumerator][date] => ['rows' => n, 'commodities' => [id => true]]
foreach ($summary_rows as $r) {
    $enum = trim((string)$r['postedby']) !== '' ? $r['postedby'] : 'Unknown';
    $date = $r['sub_date'];
    if (!isset($day_agg[$enum][$date])) {
        $day_agg[$enum][$date] = ['rows' => 0, 'commodities' => []];
    }
    $day_agg[$enum][$date]['rows']++;
    if ($r['commodity'] !== null && $r['commodity'] !== '') {
        $day_agg[$enum][$date]['commodities'][$r['commodity']] = true;
    }
}

// Step 2: figure out which Monday-start weeks fall within the selected range
function mp_monday_of($dateStr) {
    $d = new DateTime($dateStr);
    $d->modify('monday this week');
    return $d->format('Y-m-d');
}

$week_buckets = []; // 'Y-m-d' (Monday) => display label
if (!empty($start_date) && !empty($end_date)) {
    $cursor = new DateTime(mp_monday_of($start_date));
    $range_end = new DateTime($end_date);
    while ($cursor <= $range_end) {
        $wk_start_str = $cursor->format('Y-m-d');
        $wk_end = (clone $cursor)->modify('+6 days');
        $week_buckets[$wk_start_str] = date('M j', strtotime($wk_start_str)) . '–' . $wk_end->format('M j');
        $cursor->modify('+7 days');
    }
}
$week_keys = array_keys($week_buckets);

// Step 3: pivot enumerator x week
$pivot_final = [];
$grand_by_week = [];
$grand_total = ['days' => 0, 'subs' => 0, 'commodities' => 0];
foreach ($week_keys as $wk) $grand_by_week[$wk] = ['days' => 0, 'subs' => 0, 'commodities' => 0];

foreach ($day_agg as $enum => $dates) {
    $row = ['name' => $enum, 'weeks' => [], 'total' => ['days' => 0, 'subs' => 0, 'commodities' => 0]];
    foreach ($week_keys as $wk) $row['weeks'][$wk] = ['days' => 0, 'subs' => 0, 'commodities' => 0];

    foreach ($dates as $date => $info) {
        $wk = mp_monday_of($date);
        if (!isset($row['weeks'][$wk])) continue; // outside the selected range
        $row['weeks'][$wk]['days'] += 1;
        $row['weeks'][$wk]['subs'] += $info['rows'];
        $row['weeks'][$wk]['commodities'] += count($info['commodities']);
    }

    foreach ($week_keys as $wk) {
        $row['total']['days'] += $row['weeks'][$wk]['days'];
        $row['total']['subs'] += $row['weeks'][$wk]['subs'];
        $row['total']['commodities'] += $row['weeks'][$wk]['commodities'];
        $grand_by_week[$wk]['days'] += $row['weeks'][$wk]['days'];
        $grand_by_week[$wk]['subs'] += $row['weeks'][$wk]['subs'];
        $grand_by_week[$wk]['commodities'] += $row['weeks'][$wk]['commodities'];
    }

    // Skip enumerators with zero activity in range entirely (keeps table tight)
    if ($row['total']['subs'] > 0 || $row['total']['days'] > 0) {
        $grand_total['days'] += $row['total']['days'];
        $grand_total['subs'] += $row['total']['subs'];
        $grand_total['commodities'] += $row['total']['commodities'];
        $pivot_final[] = $row;
    }
}

// Most active enumerator first, same feel as the workbook
usort($pivot_final, function ($a, $b) { return $b['total']['subs'] <=> $a['total']['subs']; });

// ============================================================
// FETCH AND GROUP DATA BY MARKET + COMMODITY + DATE
// ============================================================
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$query = "SELECT 
    mp.id, mp.category, mp.commodity, mp.country_admin_0, 
    mp.market, mp.weight, mp.unit, mp.price_type, mp.Price, mp.subject,
    mp.day, mp.month, mp.year, mp.date_posted, mp.variety,
    mp.data_source, mp.supplied_volume, mp.comments, mp.supply_status, mp.postedby,
    c.commodity_name as commodity_name
    FROM market_prices mp
    LEFT JOIN commodities c ON mp.commodity = c.id
    $where 
    ORDER BY $sort_column $sort_direction, mp.market, c.commodity_name, mp.price_type
    LIMIT ? OFFSET ?";

$data_stmt = $con->prepare($query);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$prices_data = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// Group data by Market + Commodity + Date
$grouped_prices = [];
foreach ($prices_data as $price) {
    $date_key = date('Y-m-d', strtotime($price['date_posted']));
    $commodity_name = $price['commodity_name'] ?? $price['category'];
    $group_key = $price['market'] . '|' . $commodity_name . '|' . $date_key;
    
    if (!isset($grouped_prices[$group_key])) {
        $grouped_prices[$group_key] = [
            'market' => $price['market'],
            'commodity' => $commodity_name,
            'variety' => $price['variety'],
            'date_posted' => $date_key,
            'date_display' => date('M d, Y', strtotime($price['date_posted'])),
            'postedby' => $price['postedby'] ?? 'Unknown',
            'wholesale' => null,
            'retail' => null,
            'wholesale_id' => null,
            'retail_id' => null,
        ];
    }
    
    if ($price['price_type'] == 'Wholesale') {
        $grouped_prices[$group_key]['wholesale'] = $price['Price'];
        $grouped_prices[$group_key]['wholesale_id'] = $price['id'];
    } elseif ($price['price_type'] == 'Retail') {
        $grouped_prices[$group_key]['retail'] = $price['Price'];
        $grouped_prices[$group_key]['retail_id'] = $price['id'];
    }
}

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;

$date_range_display = "";
if ($date_preset == 'today') {
    $date_range_display = date('F j, Y');
} elseif ($date_preset == 'week') {
    $date_range_display = "Week of " . date('M j', strtotime($start_date)) . " - " . date('M j, Y');
} elseif ($date_preset == 'month') {
    $date_range_display = date('F Y');
} else {
    $date_range_display = date('M j, Y', strtotime($start_date)) . " - " . date('M j, Y', strtotime($end_date));
}
?>

<style>
.auth-bg-gradient{background:radial-gradient(circle at top left,rgba(0,69,13,.03),transparent),radial-gradient(circle at bottom right,rgba(128,0,0,.03),transparent)}
.header-accent-gradient{background:linear-gradient(90deg,#00450d 0%,#800000 50%,#00450d 100%)}
.table-row-hover:hover{background-color:#fefaf5;transition:all .2s ease}
.stat-card{transition:all .2s ease;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.search-input:focus{border-color:#800000;outline:none}
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
.material-symbols-outlined{font-family:'Material Symbols Outlined'!important;font-style:normal;font-weight:normal;line-height:1;letter-spacing:normal;text-transform:none;display-inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';font-feature-settings:'liga';-webkit-font-smoothing:antialiased}

.price-value{font-family:monospace;font-weight:700;font-size:.85rem}
.commodity-name{font-weight:500;color:#1f2937}

/* Tabs */
.mp-tab-btn{color:#6b7280;border-color:transparent}
.mp-tab-btn:hover{color:#800000}
.mp-tab-btn.active{color:#800000;border-color:#800000}
.tab-panel.hidden{display:none}

/* Enumerator summary table */
#summaryTable th, #summaryTable td{white-space:nowrap}
#summaryTable td.sticky, #summaryTable th.sticky{z-index:5}
</style>


<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Submission Monitoring</h1>
                <p class="text-gray-600 text-sm mt-1">Track field enumerator submissions (Wholesale &amp; Retail grouped)</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button onclick="openExportModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export CSV
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- View Tabs -->
    <div class="flex gap-2 mb-5 border-b border-gray-200">
        <button id="tabBtnRecords" onclick="switchTab('records')"
            class="mp-tab-btn active px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all">
            <span class="material-symbols-outlined text-base align-middle mr-1">receipt_long</span>Submissions
        </button>
        <button id="tabBtnSummary" onclick="switchTab('summary')"
            class="mp-tab-btn px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all">
            <span class="material-symbols-outlined text-base align-middle mr-1">groups</span>Enumerator Summary
        </button>
    </div>

    <!-- Date Range Filter Bar -->

    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center">
            <span class="text-sm font-medium text-gray-700">Range:</span>
            <div class="flex gap-2">
                <a href="?date_preset=today&<?= http_build_query(array_filter(['search_enumerator' => $search_enumerator, 'search_market' => $search_market, 'search_commodity' => $search_commodity, 'filter_country' => $filter_country, 'limit' => $limit, 'sort' => $sort_column, 'dir' => strtolower($sort_direction)])) ?>" 
                   class="px-3 py-1.5 text-sm rounded-lg transition-all <?= $date_preset == 'today' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Today</a>
                <a href="?date_preset=week&<?= http_build_query(array_filter(['search_enumerator' => $search_enumerator, 'search_market' => $search_market, 'search_commodity' => $search_commodity, 'filter_country' => $filter_country, 'limit' => $limit, 'sort' => $sort_column, 'dir' => strtolower($sort_direction)])) ?>" 
                   class="px-3 py-1.5 text-sm rounded-lg transition-all <?= $date_preset == 'week' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Week</a>
                <a href="?date_preset=month&<?= http_build_query(array_filter(['search_enumerator' => $search_enumerator, 'search_market' => $search_market, 'search_commodity' => $search_commodity, 'filter_country' => $filter_country, 'limit' => $limit, 'sort' => $sort_column, 'dir' => strtolower($sort_direction)])) ?>" 
                   class="px-3 py-1.5 text-sm rounded-lg transition-all <?= $date_preset == 'month' ? 'bg-maroon text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Month</a>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" id="customStartDate" value="<?= $start_date ?>" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                <span class="text-gray-500">—</span>
                <input type="date" id="customEndDate" value="<?= $end_date ?>" class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                <button onclick="applyCustomDateRange()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000]">Go</button>
            </div>
            <div class="ml-auto text-sm text-gray-500">
                <span class="material-symbols-outlined text-sm align-middle">calendar_today</span> <?= $date_range_display ?>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Markets Reported</p>
                    <p class="text-xl font-bold text-gray-800"><?= $markets_with_submissions ?></p>
                    <p class="text-xs text-gray-400">of <?= $total_expected_markets ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">storefront</span>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Missing Reports</p>
                    <p class="text-xl font-bold text-red-600"><?= $markets_no_submission ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-red-400/60">warning</span>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Total Submissions</p>
                    <p class="text-xl font-bold text-blue-600"><?= number_format($total_submissions) ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-blue-400/50">receipt</span>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Commodities</p>
                    <p class="text-xl font-bold text-green-600"><?= number_format($total_commodities) ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">eco</span>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Enumerators</p>
                    <p class="text-xl font-bold text-purple-600"><?= number_format($unique_enumerators) ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-purple-400/50">people</span>
            </div>
        </div>
    </div>

    <!-- ═══════════ RECORDS TAB PANEL ═══════════ -->
    <div id="panelRecords" class="tab-panel">

    <!-- Search & Filters -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">person</span>
                    <input type="text" id="searchEnumerator" placeholder="Enumerator..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_enumerator) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">storefront</span>
                    <input type="text" id="searchMarket" placeholder="Market..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_market) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">eco</span>
                    <input type="text" id="searchCommodity" placeholder="Commodity..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_commodity) ?>">
                </div>
            </div>
            <div class="w-32">
                <select id="filterCountry" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                    <option value="">All Countries</option>
                    <?php foreach ($distinct_countries as $country): ?>
                        <option value="<?= htmlspecialchars($country) ?>" <?= $filter_country == $country ? 'selected' : '' ?>><?= htmlspecialchars($country) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">search</span>Filter
                </button>
                <a href="?date_preset=<?= $date_preset ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&limit=<?= $limit ?>&sort=<?= $sort_column ?>&dir=<?= strtolower($sort_direction) ?>" class="px-3 py-1.5 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600 transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">refresh</span>Reset
                </a>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="market">Market</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="commodity">Commodity</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Variety</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_posted">Date</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Wholesale ($)</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Retail ($)</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Enumerator</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($grouped_prices)): ?>
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">receipt</span>
                            <p class="text-sm mt-1">No submissions found for the selected date range</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($grouped_prices as $group): ?>
                    <tr class="table-row-hover cursor-pointer" onclick="viewMarketPrice(<?= $group['wholesale_id'] ?? $group['retail_id'] ?>)">
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($group['market']) ?></td>
                        <td class="px-3 py-2 text-xs commodity-name"><?= htmlspecialchars($group['commodity']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($group['variety'] ?? '—') ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= $group['date_display'] ?></td>
                        <td class="px-3 py-2">
                            <?php if ($group['wholesale'] !== null): ?>
                                <span class="price-value">$<?= number_format((float)$group['wholesale'], 2) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php if ($group['retail'] !== null): ?>
                                <span class="price-value">$<?= number_format((float)$group['retail'], 2) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-maroon/10 flex items-center justify-center text-maroon text-[10px] font-bold">
                                    <?= strtoupper(substr($group['postedby'], 0, 2)) ?>
                                </div>
                                <span class="text-xs text-gray-700"><?= htmlspecialchars($group['postedby']) ?></span>
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
                        No submissions
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> submissions
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">Rows:</label>
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

    </div><!-- /panelRecords -->

    <!-- ═══════════ ENUMERATOR SUMMARY TAB PANEL ═══════════ -->
    <div id="panelSummary" class="tab-panel hidden">
        <div class="bg-white rounded-lg shadow-sm p-4 mb-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Enumerator Submission Summary</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Market Days, Submissions and Commodities per enumerator, broken down by week (Mon–Sun) ·
                        <?= htmlspecialchars($date_range_display) ?>
                    </p>
                </div>
                <button onclick="exportSummaryCsv()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 transition-all">
                    <span class="material-symbols-outlined text-sm">download</span>Export Summary CSV
                </button>
            </div>
        </div>

        <?php if (empty($week_keys)): ?>
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-400">
                <span class="material-symbols-outlined text-5xl text-gray-300 block">date_range</span>
                <p class="text-sm mt-1">Select a valid date range above to see the weekly breakdown.</p>
            </div>
        <?php elseif (empty($pivot_final)): ?>
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-400">
                <span class="material-symbols-outlined text-5xl text-gray-300 block">groups</span>
                <p class="text-sm mt-1">No enumerator activity found for the selected date range.</p>
            </div>
        <?php else: ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse" id="summaryTable">
                    <thead>
                        <tr class="bg-gray-50">
                            <th rowspan="2" class="sticky left-0 bg-gray-50 px-3 py-2 text-left font-semibold text-gray-600 uppercase border-b border-r border-gray-200 align-bottom" style="min-width:170px;">Enumerator</th>
                            <th colspan="<?= count($week_keys) + 1 ?>" class="px-2 py-1.5 text-center font-semibold text-purple-700 uppercase border-b border-l border-gray-200 bg-purple-50">Market Days</th>
                            <th colspan="<?= count($week_keys) + 1 ?>" class="px-2 py-1.5 text-center font-semibold text-blue-700 uppercase border-b border-l border-gray-200 bg-blue-50">Submissions</th>
                            <th colspan="<?= count($week_keys) + 1 ?>" class="px-2 py-1.5 text-center font-semibold text-green-700 uppercase border-b border-l border-gray-200 bg-green-50">Commodities</th>
                        </tr>
                        <tr class="bg-gray-50">
                            <?php
                            // Full, literal Tailwind class strings (dynamically concatenated
                            // class names don't get picked up by Tailwind's JIT scanner).
                            $mp_grp_week_bg  = ['purple' => 'bg-purple-50/40', 'blue' => 'bg-blue-50/40',  'green' => 'bg-green-50/40'];
                            $mp_grp_total_bg = ['purple' => 'bg-purple-100',   'blue' => 'bg-blue-100',    'green' => 'bg-green-100'];
                            foreach (['purple', 'blue', 'green'] as $grp): ?>
                                <?php foreach ($week_buckets as $wk => $label): ?>
                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500 border-b border-l border-gray-200 whitespace-nowrap <?= $mp_grp_week_bg[$grp] ?>" title="<?= $wk ?>"><?= $label ?></th>
                                <?php endforeach; ?>
                                <th class="px-2 py-1.5 text-center font-bold text-gray-700 border-b border-l-2 border-gray-300 <?= $mp_grp_total_bg[$grp] ?>">Total</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pivot_final as $row): ?>
                        <tr class="table-row-hover">
                            <td class="sticky left-0 bg-white px-3 py-2 font-medium text-gray-800 border-r border-gray-200 whitespace-nowrap"><?= htmlspecialchars($row['name']) ?></td>
                            <?php foreach ($week_keys as $wk): ?>
                                <td class="px-2 py-2 text-center border-l border-gray-100 <?= $row['weeks'][$wk]['days'] === 0 ? 'text-gray-300' : 'text-gray-700' ?>"><?= $row['weeks'][$wk]['days'] ?></td>
                            <?php endforeach; ?>
                            <td class="px-2 py-2 text-center font-bold text-purple-700 border-l-2 border-gray-300 bg-purple-50/50"><?= $row['total']['days'] ?></td>
                            <?php foreach ($week_keys as $wk): ?>
                                <td class="px-2 py-2 text-center border-l border-gray-100 <?= $row['weeks'][$wk]['subs'] === 0 ? 'text-gray-300' : 'text-gray-700' ?>"><?= $row['weeks'][$wk]['subs'] ?></td>
                            <?php endforeach; ?>
                            <td class="px-2 py-2 text-center font-bold text-blue-700 border-l-2 border-gray-300 bg-blue-50/50"><?= $row['total']['subs'] ?></td>
                            <?php foreach ($week_keys as $wk): ?>
                                <td class="px-2 py-2 text-center border-l border-gray-100 <?= $row['weeks'][$wk]['commodities'] === 0 ? 'text-gray-300' : 'text-gray-700' ?>"><?= $row['weeks'][$wk]['commodities'] ?></td>
                            <?php endforeach; ?>
                            <td class="px-2 py-2 text-center font-bold text-green-700 border-l-2 border-gray-300 bg-green-50/50"><?= $row['total']['commodities'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td class="sticky left-0 bg-gray-100 px-3 py-2 text-gray-800 border-r border-t-2 border-gray-300">TOTAL</td>
                            <?php foreach ($week_keys as $wk): ?>
                                <td class="px-2 py-2 text-center text-gray-800 border-l border-t-2 border-gray-300"><?= $grand_by_week[$wk]['days'] ?></td>
                            <?php endforeach; ?>
                            <td class="px-2 py-2 text-center text-purple-800 border-l-2 border-t-2 border-gray-300 bg-purple-100"><?= $grand_total['days'] ?></td>
                            <?php foreach ($week_keys as $wk): ?>
                                <td class="px-2 py-2 text-center text-gray-800 border-l border-t-2 border-gray-300"><?= $grand_by_week[$wk]['subs'] ?></td>
                            <?php endforeach; ?>
                            <td class="px-2 py-2 text-center text-blue-800 border-l-2 border-t-2 border-gray-300 bg-blue-100"><?= $grand_total['subs'] ?></td>
                            <?php foreach ($week_keys as $wk): ?>
                                <td class="px-2 py-2 text-center text-gray-800 border-l border-t-2 border-gray-300"><?= $grand_by_week[$wk]['commodities'] ?></td>
                            <?php endforeach; ?>
                            <td class="px-2 py-2 text-center text-green-800 border-l-2 border-t-2 border-gray-300 bg-green-100"><?= $grand_total['commodities'] ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <p class="text-[11px] text-gray-400 mt-2">
            Market Days = distinct days with a submission that week · Submissions = total price rows submitted ·
            Commodities = sum of distinct commodities recorded on each submission day.
        </p>
        <?php endif; ?>
    </div><!-- /panelSummary -->

</div>
</div>

<!-- Export CSV Modal -->
<div id="exportModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-md shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 class="text-base font-semibold text-white">Export Submissions</h3>
                <button onclick="closeModal('exportModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm text-gray-600 mb-4">Select date range to export:</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Range</label>
                        <select id="exportDatePreset" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="customRangeDiv" class="hidden">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">From</label>
                                <input type="date" id="exportStartDate" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">To</label>
                                <input type="date" id="exportEndDate" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-2 pt-3">
                        <button onclick="closeModal('exportModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button onclick="exportCSV()" class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">download</span>Download CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 class="text-base font-semibold text-white">Submission Details</h3>
                <button onclick="closeModal('viewModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <div id="viewDetailsContent" class="space-y-3"></div>
                <div class="flex justify-end gap-2 pt-4 border-t border-gray-100 mt-4">
                    <button onclick="closeModal('viewModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PHP = {
    page: <?= $page ?>,
    limit: <?= $limit ?>,
    totalPages: <?= $total_pages ?>,
    sort: <?= json_encode($sort_column) ?>,
    dir: <?= json_encode(strtolower($sort_direction)) ?>,
    searchEnumerator: <?= json_encode($search_enumerator) ?>,
    searchMarket: <?= json_encode($search_market) ?>,
    searchCommodity: <?= json_encode($search_commodity) ?>,
    filterCountry: <?= json_encode($filter_country) ?>,
    datePreset: <?= json_encode($date_preset) ?>,
    startDate: <?= json_encode($start_date) ?>,
    endDate: <?= json_encode($end_date) ?>
};

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// ── Tab switching ────────────────────────────────────────────
function switchTab(tab) {
    const isRecords = tab === 'records';
    document.getElementById('panelRecords').classList.toggle('hidden', !isRecords);
    document.getElementById('panelSummary').classList.toggle('hidden', isRecords);
    document.getElementById('tabBtnRecords').classList.toggle('active', isRecords);
    document.getElementById('tabBtnSummary').classList.toggle('active', !isRecords);
    try { sessionStorage.setItem('mpActiveTab', tab); } catch (e) {}
}

// ── Export the on-screen Enumerator Summary table as CSV ───────
function exportSummaryCsv() {
    const table = document.getElementById('summaryTable');
    if (!table) { alert('No summary data to export.'); return; }
    const rows = [];
    table.querySelectorAll('tr').forEach(tr => {
        const cells = Array.from(tr.children).map(td => {
            let text = td.textContent.trim().replace(/"/g, '""');
            return `"${text}"`;
        });
        rows.push(cells.join(','));
    });
    const csv = "\uFEFF" + rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'enumerator_summary_<?= $start_date ?>_to_<?= $end_date ?>.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}


function buildUrl(overrides) {
    const p = {
        page: PHP.page,
        limit: PHP.limit,
        sort: PHP.sort,
        dir: PHP.dir,
        date_preset: PHP.datePreset,
        start_date: PHP.startDate,
        end_date: PHP.endDate,
        search_enumerator: document.getElementById('searchEnumerator').value.trim(),
        search_market: document.getElementById('searchMarket').value.trim(),
        search_commodity: document.getElementById('searchCommodity').value.trim(),
        filter_country: document.getElementById('filterCountry').value,
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);
    
    const q = new URLSearchParams();
    q.set('page', p.page);
    q.set('limit', p.limit);
    if (p.sort) q.set('sort', p.sort);
    if (p.dir) q.set('dir', p.dir);
    if (p.date_preset) q.set('date_preset', p.date_preset);
    if (p.start_date && p.date_preset === 'custom') q.set('start_date', p.start_date);
    if (p.end_date && p.date_preset === 'custom') q.set('end_date', p.end_date);
    if (p.search_enumerator) q.set('search_enumerator', p.search_enumerator);
    if (p.search_market) q.set('search_market', p.search_market);
    if (p.search_commodity) q.set('search_commodity', p.search_commodity);
    if (p.filter_country) q.set('filter_country', p.filter_country);
    return '?' + q.toString();
}

function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (isNaN(pg) || pg < 1 || pg > PHP.totalPages) return;
    window.location.href = buildUrl({ page: pg });
}

function changeRowsPerPage() { window.location.href = buildUrl({ page: 1 }); }
function applyFilters() { window.location.href = buildUrl({ page: 1 }); }

function applyCustomDateRange() {
    const startDate = document.getElementById('customStartDate').value;
    const endDate = document.getElementById('customEndDate').value;
    if (startDate && endDate) {
        window.location.href = buildUrl({ 
            page: 1, 
            date_preset: 'custom', 
            start_date: startDate, 
            end_date: endDate 
        });
    }
}

function sortTable(col) {
    const newDir = (PHP.sort === col && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: col, dir: newDir });
}

function viewMarketPrice(id) {
    if (!id) return;
    fetch(`${window.location.pathname}?get_price=${id}`)
        .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
        .then(data => {
            const content = document.getElementById('viewDetailsContent');
            content.innerHTML = `
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><label class="text-gray-500 text-xs">ID:</label><p class="font-medium">${data.id}</p></div>
                    <div><label class="text-gray-500 text-xs">Enumerator:</label><p class="font-medium">${escapeHtml(data.postedby || 'Unknown')}</p></div>
                    <div><label class="text-gray-500 text-xs">Category:</label><p class="font-medium">${escapeHtml(data.category)}</p></div>
                    <div><label class="text-gray-500 text-xs">Commodity:</label><p class="font-medium">${escapeHtml(data.commodity_name || data.commodity)}</p></div>
                    <div><label class="text-gray-500 text-xs">Country:</label><p class="font-medium">${escapeHtml(data.country_admin_0)}</p></div>
                    <div><label class="text-gray-500 text-xs">Market:</label><p class="font-medium">${escapeHtml(data.market)}</p></div>
                    <div><label class="text-gray-500 text-xs">Weight/Unit:</label><p class="font-medium">${data.weight} ${data.unit}</p></div>
                    <div><label class="text-gray-500 text-xs">Price Type:</label><p class="font-medium">${escapeHtml(data.price_type)}</p></div>
                    <div><label class="text-gray-500 text-xs">Price:</label><p class="font-medium text-maroon font-bold">$${parseFloat(data.Price).toLocaleString()}</p></div>
                    <div><label class="text-gray-500 text-xs">Variety:</label><p class="font-medium">${escapeHtml(data.variety) || 'N/A'}</p></div>
                    <div><label class="text-gray-500 text-xs">Price Date:</label><p class="font-medium">${data.year}-${data.month}-${data.day}</p></div>
                    <div><label class="text-gray-500 text-xs">Date Posted:</label><p class="font-medium">${new Date(data.date_posted).toLocaleString()}</p></div>
                    <div><label class="text-gray-500 text-xs">Data Source:</label><p class="font-medium">${escapeHtml(data.data_source) || 'N/A'}</p></div>
                    <div><label class="text-gray-500 text-xs">Supply Status:</label><p class="font-medium">${escapeHtml(data.supply_status) || 'unknown'}</p></div>
                    <div class="col-span-2"><label class="text-gray-500 text-xs">Comments:</label><p class="font-medium">${escapeHtml(data.comments) || 'No comments'}</p></div>
                </div>
            `;
            openModal('viewModal');
        })
        .catch(err => { console.error(err); alert('Failed to load details.'); });
}

function openExportModal() {
    document.getElementById('exportDatePreset').value = 'all';
    document.getElementById('customRangeDiv').classList.add('hidden');
    openModal('exportModal');
}

function exportCSV() {
    const preset = document.getElementById('exportDatePreset').value;
    let startDate = '', endDate = '';
    
    if (preset === 'today') {
        startDate = '<?= date('Y-m-d') ?>';
        endDate = '<?= date('Y-m-d') ?>';
    } else if (preset === 'week') {
        startDate = '<?= date('Y-m-d', strtotime('monday this week')) ?>';
        endDate = '<?= date('Y-m-d') ?>';
    } else if (preset === 'month') {
        startDate = '<?= date('Y-m-01') ?>';
        endDate = '<?= date('Y-m-d') ?>';
    } else if (preset === 'custom') {
        startDate = document.getElementById('exportStartDate').value;
        endDate = document.getElementById('exportEndDate').value;
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
    }
    
    // Get current filter values
    const searchEnumerator = document.getElementById('searchEnumerator').value.trim();
    const searchMarket = document.getElementById('searchMarket').value.trim();
    const searchCommodity = document.getElementById('searchCommodity').value.trim();
    const filterCountry = document.getElementById('filterCountry').value;
    
    // Build URL with all parameters
    const params = new URLSearchParams();
    params.set('export_csv', '1');
    if (startDate && endDate && preset !== 'all') {
        params.set('start_date', startDate);
        params.set('end_date', endDate);
    }
    if (searchEnumerator) params.set('search_enumerator', searchEnumerator);
    if (searchMarket) params.set('search_market', searchMarket);
    if (searchCommodity) params.set('search_commodity', searchCommodity);
    if (filterCountry) params.set('filter_country', filterCountry);
    
    // Direct download - this will trigger the file download
    window.location.href = '?' + params.toString();
    closeModal('exportModal');
}

document.getElementById('exportDatePreset')?.addEventListener('change', function() {
    const customDiv = document.getElementById('customRangeDiv');
    customDiv.classList.toggle('hidden', this.value !== 'custom');
});

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sortable').forEach(th => th.addEventListener('click', () => sortTable(th.dataset.sort)));
    
    ['searchEnumerator', 'searchMarket', 'searchCommodity'].forEach(id => {
        document.getElementById(id)?.addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    });
    
    if (PHP.datePreset === 'custom') {
        document.getElementById('customStartDate').value = PHP.startDate;
        document.getElementById('customEndDate').value = PHP.endDate;
    }

    try {
        const savedTab = sessionStorage.getItem('mpActiveTab');
        if (savedTab === 'summary') switchTab('summary');
    } catch (e) {}
});

</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>