<?php
// marketprices_predict.php — Market Prices Predictive Analysis Page
// Tabs: Forecast Chart | Forecast Table | Model Stats
// Uses: Linear Regression + Seasonal Indexing + Confidence Intervals (computed in PHP/JS)
// READ-ONLY: published records only

// ── JSON ENDPOINT: prediction data ──────────────────────────
if (isset($_GET['predict_data'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');

    $commodity_id = isset($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : 0;
    $market_id    = isset($_GET['market_id'])    ? (int)$_GET['market_id']    : 0;
    $country      = isset($_GET['country'])      ? trim($_GET['country'])      : '';
    $horizon_days = isset($_GET['horizon'])      ? (int)$_GET['horizon']      : 90;
    $price_type   = in_array($_GET['price_type'] ?? '', ['Wholesale','Retail','Both']) ? $_GET['price_type'] : 'Both';

    // Fetch last 2 years of published data, grouped by month
    $where = ["mp.status = 'published'", "mp.date_posted >= DATE_SUB(NOW(), INTERVAL 730 DAY)"];
    $params = []; $types = '';

    if ($commodity_id) { $where[] = "mp.commodity = ?"; $params[] = $commodity_id; $types .= 'i'; }
    if ($market_id)    { $where[] = "mp.market_id = ?"; $params[] = $market_id;    $types .= 'i'; }
    if ($country)      { $where[] = "mp.country_admin_0 = ?"; $params[] = $country; $types .= 's'; }
    if ($price_type !== 'Both') { $where[] = "mp.price_type = ?"; $params[] = $price_type; $types .= 's'; }

    // Monthly aggregates — used for trend + seasonality
    $sql_monthly = "SELECT
                        DATE_FORMAT(mp.date_posted, '%Y-%m-01') AS month_start,
                        mp.price_type,
                        AVG(mp.Price) AS avg_price,
                        MIN(mp.Price) AS min_price,
                        MAX(mp.Price) AS max_price,
                        COUNT(*) AS record_count,
                        STDDEV(mp.Price) AS std_price
                    FROM market_prices mp
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY month_start, mp.price_type
                    ORDER BY month_start ASC, mp.price_type ASC";

    $stmt = $con->prepare($sql_monthly);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    $historical = [];
    while ($r = $result->fetch_assoc()) $historical[] = $r;
    $stmt->close();

    // Group by price type
    $by_type = ['Wholesale' => [], 'Retail' => []];
    foreach ($historical as $row) {
        if (isset($by_type[$row['price_type']])) {
            $by_type[$row['price_type']][] = $row;
        }
    }

    function compute_forecast(array $series, int $horizon_days): array {
        if (count($series) < 3) return ['error' => 'insufficient_data', 'min_required' => 3];

        $n = count($series);
        $prices = array_column($series, 'avg_price');
        $stds   = array_column($series, 'std_price');

        // Convert month strings to numeric index (months since first)
        $first_ts = strtotime($series[0]['month_start']);
        $x_vals = array_map(function($s) use ($first_ts) {
            return (strtotime($s['month_start']) - $first_ts) / (30.44 * 86400);
        }, $series);

        // Least-squares linear regression
        $sum_x = array_sum($x_vals);
        $sum_y = array_sum($prices);
        $sum_xx = array_sum(array_map(fn($x) => $x*$x, $x_vals));
        $sum_xy = 0;
        for ($i = 0; $i < $n; $i++) $sum_xy += $x_vals[$i] * $prices[$i];
        $denom = $n * $sum_xx - $sum_x * $sum_x;
        if ($denom == 0) return ['error' => 'no_variance'];
        $slope     = ($n * $sum_xy - $sum_x * $sum_y) / $denom;
        $intercept = ($sum_y - $slope * $sum_x) / $n;

        // R² — goodness of fit
        $y_mean = $sum_y / $n;
        $ss_tot = array_sum(array_map(fn($y) => ($y - $y_mean) ** 2, $prices));
        $ss_res = 0;
        for ($i = 0; $i < $n; $i++) {
            $pred = $intercept + $slope * $x_vals[$i];
            $ss_res += ($prices[$i] - $pred) ** 2;
        }
        $r_squared = $ss_tot > 0 ? 1 - ($ss_res / $ss_tot) : 0;

        // Residual standard error
        $rse = $n > 2 ? sqrt($ss_res / ($n - 2)) : 0;

        // Seasonal indices — deviation per calendar month
        $seasonal = array_fill(1, 12, ['sum_dev' => 0, 'count' => 0]);
        for ($i = 0; $i < $n; $i++) {
            $month_num = (int)date('n', strtotime($series[$i]['month_start']));
            $trend_at  = $intercept + $slope * $x_vals[$i];
            if ($trend_at > 0) {
                $dev = $prices[$i] / $trend_at;
                $seasonal[$month_num]['sum_dev'] += $dev;
                $seasonal[$month_num]['count']++;
            }
        }
        $seasonal_idx = [];
        for ($m = 1; $m <= 12; $m++) {
            $seasonal_idx[$m] = $seasonal[$m]['count'] > 0
                ? $seasonal[$m]['sum_dev'] / $seasonal[$m]['count']
                : 1.0;
        }
        // Normalize so indices average to 1
        $idx_mean = array_sum($seasonal_idx) / 12;
        if ($idx_mean > 0) {
            foreach ($seasonal_idx as $m => $v) $seasonal_idx[$m] = $v / $idx_mean;
        }

        // Generate forecast months
        $last_ts  = strtotime(end($series)['month_start']);
        $last_x   = $x_vals[$n - 1];
        $last_price = $prices[$n - 1];
        $forecast_months = (int)ceil($horizon_days / 30.44);

        $forecast_points = [];
        for ($i = 1; $i <= $forecast_months; $i++) {
            $proj_ts    = strtotime("+$i months", $last_ts);
            $proj_x     = $last_x + $i;
            $trend_val  = $intercept + $slope * $proj_x;
            $month_num  = (int)date('n', $proj_ts);
            $seas_val   = $trend_val * $seasonal_idx[$month_num];

            // Confidence: widens with forecast distance (1.96 * RSE * sqrt(1 + 1/n + (x-x_mean)^2/S_xx))
            $x_mean = $sum_x / $n;
            $s_xx = $sum_xx - ($sum_x * $sum_x / $n);
            $ci_factor = $s_xx > 0
                ? 1.96 * $rse * sqrt(1 + 1/$n + pow($proj_x - $x_mean, 2) / $s_xx)
                : 1.96 * $rse;

            $forecast_points[] = [
                'month'         => date('Y-m-01', $proj_ts),
                'month_label'   => date('M Y', $proj_ts),
                'trend'         => round($trend_val,   6),
                'forecast'      => round($seas_val,    6),
                'ci_lower'      => round(max(0, $seas_val - $ci_factor), 6),
                'ci_upper'      => round($seas_val + $ci_factor, 6),
                'months_ahead'  => $i,
            ];
        }

        // Month-over-month trend direction
        $last_3   = array_slice($prices, -3);
        $prev_3   = array_slice($prices, max(0, $n-6), 3);
        $recent_avg = count($last_3) ? array_sum($last_3)/count($last_3) : 0;
        $prev_avg   = count($prev_3) ? array_sum($prev_3)/count($prev_3) : 0;
        $trend_dir  = $recent_avg > $prev_avg ? 'up' : ($recent_avg < $prev_avg ? 'down' : 'flat');
        $mom_change = $prev_avg > 0 ? round(($recent_avg - $prev_avg) / $prev_avg * 100, 2) : 0;

        return [
            'series'        => $series,
            'forecast'      => $forecast_points,
            'model' => [
                'slope'       => round($slope,    6),
                'intercept'   => round($intercept,6),
                'r_squared'   => round($r_squared,4),
                'rse'         => round($rse,      6),
                'n_obs'       => $n,
                'seasonal'    => $seasonal_idx,
                'trend_dir'   => $trend_dir,
                'mom_change'  => $mom_change,
            ],
        ];
    }

    $output = ['success' => true, 'types' => []];
    foreach ($by_type as $pt => $rows) {
        if (!empty($rows)) {
            $output['types'][$pt] = compute_forecast($rows, $horizon_days);
        }
    }
    $output['horizon_days'] = $horizon_days;
    echo json_encode($output);
    exit;
}

// ── JSON ENDPOINT: same cascading filters as main dashboard ──
if (isset($_GET['get_commodities'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    include '../admin/includes/config.php';
    header('Content-Type: application/json');
    $country   = trim($_GET['country']   ?? '');
    $market_id = (int)($_GET['market_id'] ?? 0);
    $where = ["mp.status = 'published'"]; $params = []; $types = '';
    if ($country)   { $where[] = "mp.country_admin_0 = ?"; $params[] = $country; $types .= 's'; }
    if ($market_id) { $where[] = "mp.market_id = ?"; $params[] = $market_id; $types .= 'i'; }
    $sql = "SELECT DISTINCT c.id,
                   CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                   c.commodity_name, c.variety
            FROM commodities c
            INNER JOIN market_prices mp ON mp.commodity = c.id
            WHERE " . implode(' AND ', $where) . " ORDER BY c.commodity_name, c.variety";
    $stmt = $con->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result(); $data = [];
    while ($row = $r->fetch_assoc()) $data[] = $row;
    $stmt->close();
    echo json_encode($data); exit;
}

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

// ── PAGE LOAD ────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include '../admin/includes/config.php';
include 'user_header.php';

// Stats
$total_prices    = (int)(($con->query("SELECT COUNT(*) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);
$markets_count   = (int)(($con->query("SELECT COUNT(DISTINCT market_id) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);
$countries_count = (int)(($con->query("SELECT COUNT(DISTINCT country_admin_0) AS t FROM market_prices WHERE status='published'")->fetch_assoc())['t'] ?? 0);

// Date range of available data
$range_q = $con->query("SELECT MIN(date_posted) AS oldest, MAX(date_posted) AS newest FROM market_prices WHERE status='published'");
$range   = $range_q ? $range_q->fetch_assoc() : ['oldest'=>null,'newest'=>null];
$data_months = 0;
if ($range['oldest'] && $range['newest']) {
    $data_months = (int)round((strtotime($range['newest']) - strtotime($range['oldest'])) / (30.44*86400));
}

// Filters
$countries_in_db = [];
$ctr = $con->query("SELECT DISTINCT country_admin_0 FROM market_prices WHERE country_admin_0 != '' AND status='published' ORDER BY country_admin_0");
if ($ctr) { while ($r = $ctr->fetch_assoc()) $countries_in_db[] = $r['country_admin_0']; }

$all_markets = [];
$amr = $con->query("SELECT DISTINCT mp.market_id, mp.market, mp.country_admin_0 FROM market_prices mp WHERE mp.status='published' ORDER BY mp.country_admin_0, mp.market");
if ($amr) { while ($r = $amr->fetch_assoc()) $all_markets[] = $r; }

$all_commodities_q = $con->query("SELECT DISTINCT c.id,
    CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
    c.commodity_name, c.variety
    FROM commodities c INNER JOIN market_prices mp ON mp.commodity=c.id
    WHERE mp.status='published'
    ORDER BY c.commodity_name, c.variety");
$all_commodities = [];
if ($all_commodities_q) { while ($r = $all_commodities_q->fetch_assoc()) $all_commodities[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<style>
:root {
    --mp-primary:   #800000;
    --mp-primary-dk:#660000;
    --mp-green:     #00450d;
    --mp-accent:    #b45032;
    --mp-bg:        #f9fafb;
    --mp-card:      #ffffff;
    --mp-border:    #e5e7eb;
    --mp-text:      #1f2937;
    --mp-muted:     #6b7280;
    --mp-radius:    .625rem;
    --mp-ws:        #7c3aed;
    --mp-rt:        #db2777;
    --mp-ci:        rgba(124,58,237,.12);
}
.mp-wrap { min-height:100vh; padding:0 0 60px; font-family:'Segoe UI',system-ui,sans-serif; color:var(--mp-text); }
.mp-page-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:4px; }
.mp-page-header h1 { font-size:1.5rem; font-weight:700; color:var(--mp-primary); margin:0; }
.mp-page-header p  { font-size:.875rem; color:var(--mp-muted); margin:4px 0 0; }
.mp-accent-bar { height:3px; background:linear-gradient(90deg,var(--mp-green) 0%,var(--mp-primary) 50%,var(--mp-green) 100%); border-radius:99px; margin:10px 0 20px; }

/* stat cards */
.mp-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.mp-stat-card { background:var(--mp-card); border-radius:var(--mp-radius); padding:14px 16px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 3px rgba(0,0,0,.06); border-left:4px solid var(--mp-primary); transition:transform .2s,box-shadow .2s; }
.mp-stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.1); }
.mp-stat-card.c-green  { border-left-color:#16a34a; }
.mp-stat-card.c-blue   { border-left-color:#2563eb; }
.mp-stat-card.c-orange { border-left-color:#d97706; }
.mp-stat-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--mp-muted); margin-bottom:4px; }
.mp-stat-value { font-size:1.4rem; font-weight:700; color:var(--mp-text); }
.mp-stat-icon  { font-size:2.2rem; opacity:.25; }

/* tabs */
.mp-tabs { display:flex; gap:0; border-bottom:2px solid var(--mp-border); margin-bottom:20px; overflow-x:auto; }
.mp-tab { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; font-size:.875rem; font-weight:500; color:var(--mp-muted); border-bottom:2px solid transparent; cursor:pointer; transition:all .2s; white-space:nowrap; margin-bottom:-2px; text-decoration:none; background:none; border-top:none; border-left:none; border-right:none; }
.mp-tab:hover { color:var(--mp-primary); background:rgba(128,0,0,.03); }
.mp-tab.active { color:var(--mp-primary); border-bottom-color:var(--mp-primary); font-weight:600; }
.mp-panel { display:none; }
.mp-panel.active { display:block; }

/* filter bar */
.mp-filters { background:var(--mp-card); border-radius:var(--mp-radius); padding:14px 16px; box-shadow:0 1px 3px rgba(0,0,0,.06); margin-bottom:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
.mp-filter-group { display:flex; flex-direction:column; gap:4px; min-width:150px; flex:1; }
.mp-filter-group label { font-size:.75rem; font-weight:600; color:var(--mp-muted); text-transform:uppercase; letter-spacing:.05em; }
.mp-filter-group select, .mp-filter-group input { padding:7px 10px; border:1px solid var(--mp-border); border-radius:6px; font-size:.8125rem; color:var(--mp-text); background:white; width:100%; box-sizing:border-box; }
.mp-filter-group select:focus, .mp-filter-group input:focus { outline:none; border-color:var(--mp-primary); box-shadow:0 0 0 3px rgba(128,0,0,.08); }

/* horizon buttons */
.mp-horizon-btns { display:flex; gap:6px; flex-wrap:wrap; }
.mp-horizon-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:6px; font-size:.8125rem; font-weight:600; border:1.5px solid var(--mp-border); background:white; color:var(--mp-muted); cursor:pointer; transition:all .15s; }
.mp-horizon-btn:hover  { border-color:var(--mp-primary); color:var(--mp-primary); background:rgba(128,0,0,.04); }
.mp-horizon-btn.active { background:var(--mp-primary); color:white; border-color:var(--mp-primary); }

/* chart panels */
.mp-chart-panel { background:var(--mp-card); border-radius:var(--mp-radius); box-shadow:0 1px 3px rgba(0,0,0,.06); padding:20px; margin-bottom:16px; }
.mp-chart-panel h3 { margin:0 0 4px; font-size:1rem; font-weight:600; }
.mp-chart-panel .subtitle { font-size:.8rem; color:var(--mp-muted); margin:0 0 16px; }

/* forecast summary cards */
.mp-forecast-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin-bottom:20px; }
.mp-fc-card { background:var(--mp-card); border-radius:var(--mp-radius); box-shadow:0 1px 3px rgba(0,0,0,.06); padding:14px 16px; border-top:3px solid var(--mp-primary); }
.mp-fc-card.retail { border-top-color:var(--mp-rt); }
.mp-fc-label  { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--mp-muted); margin-bottom:6px; }
.mp-fc-price  { font-size:1.4rem; font-weight:700; font-family:'Courier New',monospace; color:var(--mp-text); }
.mp-fc-range  { font-size:.75rem; color:var(--mp-muted); margin-top:4px; }
.mp-fc-change { display:inline-flex; align-items:center; gap:3px; font-size:.75rem; font-weight:600; padding:2px 8px; border-radius:4px; margin-top:6px; }
.mp-fc-change.up   { background:#dcfce7; color:#16a34a; }
.mp-fc-change.down { background:#fee2e2; color:#dc2626; }
.mp-fc-change.flat { background:#f3f4f6; color:var(--mp-muted); }

/* model stats */
.mp-model-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
.mp-model-stat { background:#f8f9fa; border-radius:8px; padding:12px 14px; }
.mp-model-stat-label { font-size:.7rem; color:var(--mp-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
.mp-model-stat-value { font-size:1.1rem; font-weight:700; color:var(--mp-text); }
.mp-model-stat-sub { font-size:.7rem; color:var(--mp-muted); margin-top:2px; }

/* forecast table */
.mp-fc-table { width:100%; border-collapse:collapse; font-size:.8125rem; }
.mp-fc-table thead tr { background:#f8f9fa; }
.mp-fc-table th { padding:9px 12px; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--mp-muted); border-bottom:2px solid var(--mp-border); white-space:nowrap; }
.mp-fc-table td { padding:9px 12px; border-bottom:1px solid #f3f4f6; }
.mp-fc-table tbody tr:hover { background:#fefaf5; }
.mp-fc-table .num { font-family:'Courier New',monospace; font-weight:700; }
.mp-fc-table .muted { color:var(--mp-muted); font-size:.75rem; }

/* legend */
.mp-legend { display:flex; gap:18px; flex-wrap:wrap; margin-bottom:12px; }
.mp-legend-item { display:flex; align-items:center; gap:7px; font-size:.8rem; color:var(--mp-text); }
.mp-legend-line { width:22px; height:3px; border-radius:2px; }
.mp-legend-band { width:22px; height:10px; border-radius:3px; opacity:.4; }

/* loading */
.mp-loading { text-align:center; padding:50px 20px; color:var(--mp-muted); }
@keyframes mpspin { to { transform:rotate(360deg); } }
.mp-spinner { animation:mpspin 1s linear infinite; display:inline-block; }

/* no data */
.mp-no-data { text-align:center; padding:50px 20px; color:var(--mp-muted); }
.mp-no-data .ms { font-size:3rem; opacity:.3; display:block; margin-bottom:12px; }

/* disclaimer */
.mp-disclaimer { background:#fef9c3; border:1px solid #fde047; border-radius:var(--mp-radius); padding:10px 14px; font-size:.8rem; color:#713f12; display:flex; gap:8px; align-items:flex-start; margin-bottom:16px; }

/* btn */
.mp-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:6px; font-size:.8125rem; font-weight:500; border:1px solid var(--mp-border); background:white; color:var(--mp-text); cursor:pointer; transition:all .2s; }
.mp-btn.primary { background:var(--mp-primary); color:white; border-color:var(--mp-primary); }
.mp-btn.primary:hover { background:var(--mp-primary-dk); }

/* confidence band note */
.mp-ci-note { font-size:.75rem; color:var(--mp-muted); margin-top:10px; }

/* pill */
.mp-pill { display:inline-flex; align-items:center; gap:5px; background:#dcfce7; color:#166534; font-size:.75rem; font-weight:600; padding:3px 10px; border-radius:99px; border:1px solid #bbf7d0; }

.ms { font-family:'Material Symbols Outlined' !important; font-style:normal; font-weight:normal; line-height:1; letter-spacing:normal; text-transform:none; display:inline-block; white-space:nowrap; direction:ltr; -webkit-font-smoothing:antialiased; vertical-align:middle; }

@media (max-width:768px) {
    .mp-stats { grid-template-columns:repeat(2,1fr); }
    .mp-filters { flex-direction:column; }
}
</style>
</head>

<div class="mp-wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    <!-- Page header -->
    <div class="mp-page-header">
        <div>
            <h1><span class="ms" style="font-size:1.4rem;margin-right:6px;">trending_up</span>Market Price Predictions</h1>
            <p>AI-assisted price forecasting using historical trends, seasonal patterns, and linear regression</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span class="mp-pill"><span class="ms" style="font-size:.85rem;">verified</span> Published Data Only</span>
            <a href="landing_page.php" class="mp-btn">
                <span class="ms">arrow_back</span> Main Dashboard
            </a>
        </div>
    </div>
    <div class="mp-accent-bar"></div>

    <!-- Stat cards -->
    <div class="mp-stats">
        <div class="mp-stat-card">
            <div><div class="mp-stat-label">Data Points Available</div><div class="mp-stat-value"><?= number_format($total_prices) ?></div></div>
            <span class="ms mp-stat-icon" style="color:var(--mp-primary);">database</span>
        </div>
        <div class="mp-stat-card c-blue">
            <div><div class="mp-stat-label">Markets Tracked</div><div class="mp-stat-value"><?= number_format($markets_count) ?></div></div>
            <span class="ms mp-stat-icon" style="color:#2563eb;">storefront</span>
        </div>
        <div class="mp-stat-card c-green">
            <div><div class="mp-stat-label">Countries</div><div class="mp-stat-value"><?= number_format($countries_count) ?></div></div>
            <span class="ms mp-stat-icon" style="color:#16a34a;">public</span>
        </div>
        <div class="mp-stat-card c-orange">
            <div><div class="mp-stat-label">Historical Depth</div><div class="mp-stat-value"><?= $data_months ?>mo</div></div>
            <span class="ms mp-stat-icon" style="color:#d97706;">history</span>
        </div>
    </div>

    <!-- Disclaimer -->
    <div class="mp-disclaimer">
        <span class="ms" style="font-size:1rem;flex-shrink:0;margin-top:1px;">warning</span>
        <span><strong>Forecasts are indicative only.</strong> Predictions are based on historical linear trends and seasonal patterns in published RATIN data. Agricultural prices are subject to weather events, policy changes, and market shocks that cannot be modelled from price history alone. Use for planning reference, not as financial advice.</span>
    </div>

    <!-- ── Filters ── -->
    <div class="mp-filters" id="globalFilters">
        <div class="mp-filter-group">
            <label>Country</label>
            <select id="f_country" onchange="onCountryChange()">
                <option value="">All Countries</option>
                <?php foreach ($countries_in_db as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mp-filter-group">
            <label>Market</label>
            <select id="f_market" onchange="onMarketChange()">
                <option value="">All Markets</option>
                <?php foreach ($all_markets as $m): ?>
                    <option value="<?= $m['market_id'] ?>" data-country="<?= htmlspecialchars($m['country_admin_0']) ?>"><?= htmlspecialchars($m['market']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mp-filter-group">
            <label>Commodity</label>
            <select id="f_commodity" onchange="runForecast()">
                <option value="">All Commodities</option>
                <?php foreach ($all_commodities as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['commodity_display']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mp-filter-group">
            <label>Price Type</label>
            <select id="f_price_type" onchange="runForecast()">
                <option value="Both">Both</option>
                <option value="Wholesale">Wholesale only</option>
                <option value="Retail">Retail only</option>
            </select>
        </div>
        <div class="mp-filter-group" style="max-width:300px;">
            <label>Forecast Horizon</label>
            <div class="mp-horizon-btns">
                <button class="mp-horizon-btn active" data-days="90"  onclick="setHorizon(90,this)">3 mo</button>
                <button class="mp-horizon-btn"         data-days="270" onclick="setHorizon(270,this)">9 mo</button>
                <button class="mp-horizon-btn"         data-days="365" onclick="setHorizon(365,this)">1 yr</button>
                <button class="mp-horizon-btn"         data-days="730" onclick="setHorizon(730,this)">2 yr</button>
            </div>
        </div>
        <div style="align-self:flex-end;">
            <button class="mp-btn primary" onclick="runForecast()">
                <span class="ms">auto_graph</span> Run Forecast
            </button>
        </div>
    </div>

    <!-- ── Tabs ── -->
    <div class="mp-tabs" role="tablist">
        <button class="mp-tab active" onclick="switchTab('chart')" role="tab">
            <span class="ms">show_chart</span> Forecast Chart
        </button>
        <button class="mp-tab" onclick="switchTab('table')" role="tab">
            <span class="ms">table_rows</span> Forecast Table
        </button>
        <button class="mp-tab" onclick="switchTab('model')" role="tab">
            <span class="ms">science</span> Model Stats
        </button>
    </div>

    <!-- Loading overlay (shared) -->
    <div id="loadingState" style="display:none;" class="mp-loading">
        <span class="ms mp-spinner" style="font-size:2.5rem;color:var(--mp-primary);">hourglass_empty</span>
        <p style="margin-top:10px;">Running forecast model…</p>
    </div>

    <!-- No data state -->
    <div id="noDataState" style="display:none;">
        <div class="mp-no-data">
            <span class="ms">query_stats</span>
            <p id="noDataMsg">Select filters and click <strong>Run Forecast</strong> to generate predictions.</p>
        </div>
    </div>

    <!-- ══════════════════
         TAB: CHART
    ══════════════════ -->
    <div id="panel-chart" class="mp-panel active">

        <!-- Forecast summary cards (filled by JS) -->
        <div id="forecastSummary" class="mp-forecast-summary" style="display:none;"></div>

        <!-- Main forecast chart -->
        <div class="mp-chart-panel" id="forecastChartWrap" style="display:none;">
            <h3>Price Forecast — Historical + Projection</h3>
            <p class="subtitle">Historical averages (solid) with forecast (dashed) and 95% confidence interval (shaded band)</p>

            <div class="mp-legend">
                <div class="mp-legend-item">
                    <div class="mp-legend-line" style="background:var(--mp-ws);"></div>
                    <span>Wholesale historical</span>
                </div>
                <div class="mp-legend-item">
                    <div class="mp-legend-line" style="background:var(--mp-ws);border-top:3px dashed var(--mp-ws);height:0;"></div>
                    <span>Wholesale forecast</span>
                </div>
                <div class="mp-legend-item">
                    <div class="mp-legend-band" style="background:var(--mp-ws);"></div>
                    <span>95% confidence</span>
                </div>
                <div class="mp-legend-item">
                    <div class="mp-legend-line" style="background:var(--mp-rt);"></div>
                    <span>Retail historical</span>
                </div>
                <div class="mp-legend-item">
                    <div class="mp-legend-line" style="background:var(--mp-rt);border-top:3px dashed var(--mp-rt);height:0;"></div>
                    <span>Retail forecast</span>
                </div>
            </div>

            <div style="position:relative;width:100%;height:400px;">
                <canvas id="forecastChart" role="img" aria-label="Line chart showing historical prices with forecast projections and confidence intervals">Forecast chart will render here.</canvas>
            </div>
            <p class="mp-ci-note">
                <span class="ms" style="font-size:.85rem;">info</span>
                Confidence intervals represent ±1.96 standard errors, adjusted for forecast distance. Wider bands indicate greater uncertainty further into the future.
            </p>
        </div>

        <!-- Seasonal pattern chart -->
        <div class="mp-chart-panel" id="seasonalChartWrap" style="display:none;">
            <h3>Seasonal Index by Month</h3>
            <p class="subtitle">How much prices deviate from trend in each calendar month (index of 1.0 = no seasonal effect)</p>
            <div style="position:relative;width:100%;height:200px;">
                <canvas id="seasonalChart" role="img" aria-label="Bar chart showing seasonal price index by month">Seasonal index chart.</canvas>
            </div>
        </div>

        <!-- Empty prompt -->
        <div id="chartEmptyState">
            <div class="mp-no-data">
                <span class="ms">auto_graph</span>
                <p>Configure filters above and click <strong>Run Forecast</strong> to generate predictions.</p>
            </div>
        </div>
    </div>

    <!-- ══════════════════
         TAB: TABLE
    ══════════════════ -->
    <div id="panel-table" class="mp-panel">
        <div class="mp-chart-panel" id="forecastTableWrap" style="display:none;">
            <h3>Monthly Forecast Values</h3>
            <p class="subtitle">Projected prices per month with confidence range</p>
            <div style="overflow-x:auto;">
                <table class="mp-fc-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Months Ahead</th>
                            <th>Wholesale Forecast</th>
                            <th>WS CI Lower</th>
                            <th>WS CI Upper</th>
                            <th>Retail Forecast</th>
                            <th>RT CI Lower</th>
                            <th>RT CI Upper</th>
                            <th>WS Trend Direction</th>
                        </tr>
                    </thead>
                    <tbody id="forecastTableBody"></tbody>
                </table>
            </div>
        </div>
        <div id="tableEmptyState">
            <div class="mp-no-data">
                <span class="ms">table_chart</span>
                <p>Run a forecast from the Forecast Chart tab to see monthly projection values here.</p>
            </div>
        </div>
    </div>

    <!-- ══════════════════
         TAB: MODEL STATS
    ══════════════════ -->
    <div id="panel-model" class="mp-panel">
        <div id="modelStatsWrap" style="display:none;">

            <div class="mp-chart-panel">
                <h3>Wholesale Model Statistics</h3>
                <p class="subtitle">Linear regression model fit metrics for wholesale prices</p>
                <div class="mp-model-grid" id="wsModelStats"></div>
            </div>

            <div class="mp-chart-panel">
                <h3>Retail Model Statistics</h3>
                <p class="subtitle">Linear regression model fit metrics for retail prices</p>
                <div class="mp-model-grid" id="rtModelStats"></div>
            </div>

            <div class="mp-chart-panel">
                <h3>Methodology</h3>
                <p class="subtitle">How the forecast is computed</p>
                <div style="font-size:.875rem;line-height:1.7;color:var(--mp-text);">
                    <p style="margin:0 0 12px;"><strong>1. Data preparation:</strong> Monthly average prices are computed from all published records in the last 24 months matching your filters. Months with no data are excluded.</p>
                    <p style="margin:0 0 12px;"><strong>2. Linear trend:</strong> An ordinary least-squares (OLS) regression is fitted to the monthly averages, giving a slope (price change per month) and intercept. The R² value indicates how well the straight-line trend fits the data — values above 0.7 indicate a strong trend.</p>
                    <p style="margin:0 0 12px;"><strong>3. Seasonal indexing:</strong> For each calendar month (Jan–Dec), the average ratio of actual price to trend price is computed. This seasonal index is then normalised so the 12 indices average to 1.0. Forecasts are multiplied by the relevant monthly index to capture recurring seasonal patterns.</p>
                    <p style="margin:0 0 12px;"><strong>4. Confidence intervals:</strong> 95% confidence intervals are computed using the residual standard error (RSE) and the distance from the historical mean. Bands widen with forecast horizon because prediction uncertainty compounds over time.</p>
                    <p style="margin:0;"><strong>Limitations:</strong> The model assumes price behaviour follows a linear trend with stable seasonality. Structural breaks, policy changes, droughts, and conflict are not captured. Short historical series (fewer than 12 months) produce wider confidence intervals and less reliable forecasts.</p>
                </div>
            </div>
        </div>
        <div id="modelEmptyState">
            <div class="mp-no-data">
                <span class="ms">science</span>
                <p>Run a forecast to see model fit statistics here.</p>
            </div>
        </div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── State
let _horizon = 90;
let _fcChart = null;
let _seasChart = null;
let _lastData = null;

// ── Tab switching
function switchTab(tab) {
    document.querySelectorAll('.mp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-panel').forEach(p => p.classList.remove('active'));
    const tabs = ['chart','table','model'];
    document.querySelectorAll('.mp-tab')[tabs.indexOf(tab)]?.classList.add('active');
    document.getElementById('panel-' + tab)?.classList.add('active');
}

// ── Horizon button
function setHorizon(days, btn) {
    _horizon = days;
    document.querySelectorAll('.mp-horizon-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// ── Cascading filters
function onCountryChange() {
    const country = document.getElementById('f_country').value;
    const mSel = document.getElementById('f_market');
    Array.from(mSel.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        opt.hidden = country ? opt.getAttribute('data-country') !== country : false;
    });
    if (mSel.selectedOptions[0]?.hidden) mSel.value = '';
    loadCommodities();
}

function onMarketChange() { loadCommodities(); }

function loadCommodities() {
    const country   = document.getElementById('f_country').value;
    const market_id = document.getElementById('f_market').value;
    const comSel    = document.getElementById('f_commodity');
    const curVal    = comSel.value;
    fetch(`?get_commodities=1&country=${encodeURIComponent(country)}&market_id=${encodeURIComponent(market_id)}`)
    .then(r => r.json()).then(data => {
        comSel.innerHTML = '<option value="">All Commodities</option>';
        data.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id; o.textContent = c.commodity_display;
            if (String(c.id) === String(curVal)) o.selected = true;
            comSel.appendChild(o);
        });
    });
}

// ── Run forecast
function runForecast() {
    const country      = document.getElementById('f_country').value;
    const market_id    = document.getElementById('f_market').value;
    const commodity_id = document.getElementById('f_commodity').value;
    const price_type   = document.getElementById('f_price_type').value;

    // Show loading
    setLoadingState(true);

    let url = `?predict_data=1&horizon=${_horizon}&price_type=${encodeURIComponent(price_type)}`;
    if (country)      url += `&country=${encodeURIComponent(country)}`;
    if (market_id)    url += `&market_id=${market_id}`;
    if (commodity_id) url += `&commodity_id=${commodity_id}`;

    fetch(url)
    .then(r => r.json())
    .then(data => {
        setLoadingState(false);
        if (!data.success) { showError('Server error fetching forecast data.'); return; }
        _lastData = data;
        renderAll(data);
    })
    .catch(() => { setLoadingState(false); showError('Network error. Please try again.'); });
}

function setLoadingState(on) {
    document.getElementById('loadingState').style.display = on ? 'block' : 'none';
    document.getElementById('noDataState').style.display  = 'none';
    if (on) {
        document.getElementById('forecastSummary').style.display    = 'none';
        document.getElementById('forecastChartWrap').style.display  = 'none';
        document.getElementById('seasonalChartWrap').style.display  = 'none';
        document.getElementById('chartEmptyState').style.display    = 'none';
        document.getElementById('forecastTableWrap').style.display  = 'none';
        document.getElementById('tableEmptyState').style.display    = 'none';
        document.getElementById('modelStatsWrap').style.display     = 'none';
        document.getElementById('modelEmptyState').style.display    = 'none';
    }
}

function showError(msg) {
    document.getElementById('noDataState').style.display = 'block';
    document.getElementById('noDataMsg').textContent = msg;
    document.getElementById('chartEmptyState').style.display  = 'none';
    document.getElementById('tableEmptyState').style.display  = 'none';
    document.getElementById('modelEmptyState').style.display  = 'none';
}

// ── Render all panels
function renderAll(data) {
    const ws = data.types['Wholesale'];
    const rt = data.types['Retail'];
    const hasWs = ws && !ws.error && ws.forecast?.length > 0;
    const hasRt = rt && !rt.error && rt.forecast?.length > 0;

    if (!hasWs && !hasRt) {
        showError('Not enough historical data to generate a forecast. At least 3 months of published records are required for the selected filters.');
        document.getElementById('chartEmptyState').style.display  = 'none';
        document.getElementById('tableEmptyState').style.display  = 'none';
        document.getElementById('modelEmptyState').style.display  = 'none';
        return;
    }

    renderSummaryCards(ws, rt);
    renderForecastChart(ws, rt);
    renderSeasonalChart(ws, rt);
    renderForecastTable(ws, rt);
    renderModelStats(ws, rt);
}

// ── Summary cards
function renderSummaryCards(ws, rt) {
    const box = document.getElementById('forecastSummary');
    const horizonLabel = { 90:'3-Month', 270:'9-Month', 365:'1-Year', 730:'2-Year' }[_horizon] || `${_horizon}d`;
    let html = '';

    function card(label, fc_arr, series_arr, cls) {
        if (!fc_arr || !fc_arr.length) return '';
        const last_fc = fc_arr[fc_arr.length - 1];
        const first_hist = series_arr[0]?.avg_price ?? 0;
        const last_hist  = series_arr[series_arr.length - 1]?.avg_price ?? 0;
        const forecast_price = last_fc.forecast;
        const pct_change = last_hist > 0 ? ((forecast_price - last_hist) / last_hist * 100) : 0;
        const dir = pct_change > 0.5 ? 'up' : pct_change < -0.5 ? 'down' : 'flat';
        const sign = dir === 'up' ? '▲ +' : dir === 'down' ? '▼ ' : '– ';
        return `
        <div class="mp-fc-card ${cls}">
            <div class="mp-fc-label">${horizonLabel} ${label} Forecast</div>
            <div class="mp-fc-price">$${Number(forecast_price).toFixed(4)}</div>
            <div class="mp-fc-range">95% CI: $${Number(last_fc.ci_lower).toFixed(4)} – $${Number(last_fc.ci_upper).toFixed(4)}</div>
            <div class="mp-fc-change ${dir}">${sign}${Math.abs(pct_change).toFixed(1)}% vs current</div>
        </div>`;
    }

    if (ws && !ws.error) html += card('Wholesale', ws.forecast, ws.series, '');
    if (rt && !rt.error) html += card('Retail',    rt.forecast, rt.series, 'retail');

    box.innerHTML = html;
    box.style.display = html ? 'grid' : 'none';
}

// ── Forecast + History chart
function renderForecastChart(ws, rt) {
    const wrap = document.getElementById('forecastChartWrap');
    document.getElementById('chartEmptyState').style.display = 'none';

    // Build unified date labels
    const allMonths = new Set();
    [ws?.series, rt?.series].forEach(s => s?.forEach(r => allMonths.add(r.month_start)));
    [ws?.forecast, rt?.forecast].forEach(f => f?.forEach(r => allMonths.add(r.month)));
    const sortedMonths = Array.from(allMonths).sort();

    function fmt(m) {
        const d = new Date(m + 'T00:00:00');
        return d.toLocaleDateString('en-GB', { month:'short', year:'2-digit' });
    }

    const labels = sortedMonths.map(fmt);

    // Boundary between historical and forecast
    const lastHistMonth = (() => {
        const mths = [];
        if (ws?.series?.length) mths.push(ws.series[ws.series.length-1].month_start);
        if (rt?.series?.length) mths.push(rt.series[rt.series.length-1].month_start);
        return mths.length ? mths.sort().pop() : null;
    })();
    const splitIdx = lastHistMonth ? sortedMonths.indexOf(lastHistMonth) : -1;

    function histValues(series) {
        const map = {};
        series?.forEach(r => { map[r.month_start] = parseFloat(r.avg_price); });
        return sortedMonths.map(m => map[m] ?? null);
    }

    function fcValues(forecast, field) {
        const map = {};
        forecast?.forEach(r => { map[r.month] = parseFloat(r[field]); });
        return sortedMonths.map((m, i) => i >= splitIdx ? (map[m] ?? null) : null);
    }

    const datasets = [];

    if (ws && !ws.error) {
        // CI fill area — upper
        datasets.push({
            label: 'WS CI Upper', data: fcValues(ws.forecast, 'ci_upper'),
            borderColor: 'transparent', backgroundColor: 'rgba(124,58,237,0.10)',
            fill: '+1', pointRadius: 0, spanGaps: true, borderWidth: 0,
        });
        // CI fill area — lower
        datasets.push({
            label: 'WS CI Lower', data: fcValues(ws.forecast, 'ci_lower'),
            borderColor: 'transparent', backgroundColor: 'rgba(124,58,237,0.10)',
            fill: false, pointRadius: 0, spanGaps: true, borderWidth: 0,
        });
        // Historical line
        datasets.push({
            label: 'Wholesale (hist)', data: histValues(ws.series),
            borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.05)',
            borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6,
            pointBackgroundColor: '#7c3aed', fill: false, tension: 0.35, spanGaps: true,
        });
        // Forecast line
        datasets.push({
            label: 'Wholesale (forecast)', data: fcValues(ws.forecast, 'forecast'),
            borderColor: '#7c3aed', borderDash: [7,4],
            borderWidth: 2, pointRadius: 2, pointHoverRadius: 6,
            pointBackgroundColor: '#7c3aed', fill: false, tension: 0.35, spanGaps: true,
        });
    }

    if (rt && !rt.error) {
        datasets.push({
            label: 'RT CI Upper', data: fcValues(rt.forecast, 'ci_upper'),
            borderColor: 'transparent', backgroundColor: 'rgba(219,39,119,0.08)',
            fill: '+1', pointRadius: 0, spanGaps: true, borderWidth: 0,
        });
        datasets.push({
            label: 'RT CI Lower', data: fcValues(rt.forecast, 'ci_lower'),
            borderColor: 'transparent', backgroundColor: 'rgba(219,39,119,0.08)',
            fill: false, pointRadius: 0, spanGaps: true, borderWidth: 0,
        });
        datasets.push({
            label: 'Retail (hist)', data: histValues(rt.series),
            borderColor: '#db2777', borderDash: [4,3],
            borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6,
            pointBackgroundColor: '#db2777', fill: false, tension: 0.35, spanGaps: true,
        });
        datasets.push({
            label: 'Retail (forecast)', data: fcValues(rt.forecast, 'forecast'),
            borderColor: '#db2777', borderDash: [10,4],
            borderWidth: 2, pointRadius: 2, pointHoverRadius: 6,
            pointBackgroundColor: '#db2777', fill: false, tension: 0.35, spanGaps: true,
        });
    }

    if (_fcChart) _fcChart.destroy();
    const ctx = document.getElementById('forecastChart').getContext('2d');
    _fcChart = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode:'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,0.97)',
                    titleColor: '#1f2937', bodyColor: '#374151',
                    borderColor: '#e5e7eb', borderWidth: 1, padding: 12,
                    filter: item => !['WS CI Upper','WS CI Lower','RT CI Upper','RT CI Lower'].includes(item.dataset.label),
                    callbacks: {
                        label: item => ` ${item.dataset.label}: $${Number(item.raw).toFixed(4)}`,
                    }
                },
                annotation: splitIdx >= 0 ? {
                    annotations: {
                        divider: { type:'line', xMin: splitIdx, xMax: splitIdx, borderColor:'rgba(0,0,0,0.2)', borderWidth:1, borderDash:[4,4], label:{content:'Forecast →',enabled:true,position:'end',font:{size:10},color:'#6b7280'} }
                    }
                } : {}
            },
            scales: {
                x: { grid:{color:'rgba(0,0,0,0.03)'}, ticks:{color:'#6b7280',font:{size:11},maxRotation:45,autoSkip:true,maxTicksLimit:18} },
                y: { grid:{color:'rgba(0,0,0,0.03)'}, ticks:{color:'#6b7280',font:{size:11},callback:v=>'$'+Number(v).toFixed(4)} }
            }
        }
    });
    wrap.style.display = 'block';
}

// ── Seasonal chart
function renderSeasonalChart(ws, rt) {
    const wrap = document.getElementById('seasonalChartWrap');
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const datasets = [];

    if (ws?.model?.seasonal) {
        datasets.push({
            label: 'Wholesale seasonal index',
            data: Object.values(ws.model.seasonal).map(v => parseFloat(v.toFixed(4))),
            backgroundColor: datasets.length === 0 ? 'rgba(124,58,237,0.65)' : 'rgba(219,39,119,0.65)',
            borderColor: '#7c3aed', borderWidth: 1, borderRadius: 4,
        });
    }
    if (rt?.model?.seasonal) {
        datasets.push({
            label: 'Retail seasonal index',
            data: Object.values(rt.model.seasonal).map(v => parseFloat(v.toFixed(4))),
            backgroundColor: 'rgba(219,39,119,0.55)',
            borderColor: '#db2777', borderWidth: 1, borderRadius: 4,
        });
    }

    if (!datasets.length) { wrap.style.display='none'; return; }

    if (_seasChart) _seasChart.destroy();
    const ctx = document.getElementById('seasonalChart').getContext('2d');
    _seasChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: monthNames, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position:'top', labels:{font:{size:12},boxWidth:12,padding:16} },
                tooltip: {
                    callbacks: {
                        label: item => ` ${item.dataset.label}: ${Number(item.raw).toFixed(4)} (${((item.raw-1)*100).toFixed(1)}% from trend)`,
                    }
                }
            },
            scales: {
                x: { grid:{display:false}, ticks:{color:'#6b7280',font:{size:11}} },
                y: {
                    grid:{color:'rgba(0,0,0,0.04)'},
                    ticks:{color:'#6b7280',font:{size:11},callback:v=>Number(v).toFixed(3)},
                    min: 0.7, max: 1.3,
                }
            }
        }
    });
    wrap.style.display = 'block';
}

// ── Forecast table
function renderForecastTable(ws, rt) {
    const wrap = document.getElementById('forecastTableWrap');
    const tbody = document.getElementById('forecastTableBody');
    document.getElementById('tableEmptyState').style.display = 'none';

    const fcWsMap = {}, fcRtMap = {};
    ws?.forecast?.forEach(r => { fcWsMap[r.month] = r; });
    rt?.forecast?.forEach(r => { fcRtMap[r.month] = r; });
    const allFcMonths = new Set([...Object.keys(fcWsMap), ...Object.keys(fcRtMap)]);
    const sorted = Array.from(allFcMonths).sort();

    if (!sorted.length) { wrap.style.display='none'; document.getElementById('tableEmptyState').style.display='block'; return; }

    const wsLastHist = ws?.series?.length ? parseFloat(ws.series[ws.series.length-1].avg_price) : null;

    tbody.innerHTML = sorted.map(month => {
        const w = fcWsMap[month];
        const r = fcRtMap[month];
        const label = new Date(month + 'T00:00:00').toLocaleDateString('en-GB', {month:'short',year:'numeric'});
        const wsF = w ? Number(w.forecast).toFixed(4) : '—';
        const wsL = w ? Number(w.ci_lower).toFixed(4)  : '—';
        const wsU = w ? Number(w.ci_upper).toFixed(4)  : '—';
        const rtF = r ? Number(r.forecast).toFixed(4) : '—';
        const rtL = r ? Number(r.ci_lower).toFixed(4)  : '—';
        const rtU = r ? Number(r.ci_upper).toFixed(4)  : '—';
        const ahead = w?.months_ahead ?? r?.months_ahead ?? '—';

        let trendHtml = '—';
        if (w && wsLastHist) {
            const pct = ((w.forecast - wsLastHist) / wsLastHist * 100);
            const cls = pct > 0.5 ? 'up' : pct < -0.5 ? 'down' : 'flat';
            const sign = cls==='up' ? '▲ +' : cls==='down' ? '▼ ' : '– ';
            trendHtml = `<span class="mp-fc-change ${cls}">${sign}${Math.abs(pct).toFixed(1)}%</span>`;
        }

        return `<tr>
            <td style="font-weight:600;">${label}</td>
            <td class="muted">${ahead}</td>
            <td class="num">$${wsF}</td>
            <td class="muted">$${wsL}</td>
            <td class="muted">$${wsU}</td>
            <td class="num">$${rtF}</td>
            <td class="muted">$${rtL}</td>
            <td class="muted">$${rtU}</td>
            <td>${trendHtml}</td>
        </tr>`;
    }).join('');

    wrap.style.display = 'block';
}

// ── Model stats
function renderModelStats(ws, rt) {
    const wrap = document.getElementById('modelStatsWrap');
    document.getElementById('modelEmptyState').style.display = 'none';

    function statGrid(model) {
        if (!model) return '<p style="color:var(--mp-muted);font-size:.85rem;">No data for this price type with current filters.</p>';
        const trendDir = model.trend_dir === 'up' ? '▲ Upward' : model.trend_dir === 'down' ? '▼ Downward' : '– Flat';
        const trendColor = model.trend_dir === 'up' ? '#16a34a' : model.trend_dir === 'down' ? '#dc2626' : '#6b7280';
        const r2Class = model.r_squared > 0.7 ? '#16a34a' : model.r_squared > 0.4 ? '#d97706' : '#dc2626';
        return `
        <div class="mp-model-stat">
            <div class="mp-model-stat-label">Observations (months)</div>
            <div class="mp-model-stat-value">${model.n_obs}</div>
            <div class="mp-model-stat-sub">Monthly data points</div>
        </div>
        <div class="mp-model-stat">
            <div class="mp-model-stat-label">R² (goodness of fit)</div>
            <div class="mp-model-stat-value" style="color:${r2Class};">${(model.r_squared*100).toFixed(1)}%</div>
            <div class="mp-model-stat-sub">${model.r_squared > 0.7 ? 'Strong fit' : model.r_squared > 0.4 ? 'Moderate fit' : 'Weak fit — treat with caution'}</div>
        </div>
        <div class="mp-model-stat">
            <div class="mp-model-stat-label">Monthly trend (slope)</div>
            <div class="mp-model-stat-value">${model.slope > 0 ? '+' : ''}$${Number(model.slope).toFixed(4)}</div>
            <div class="mp-model-stat-sub">Per month change</div>
        </div>
        <div class="mp-model-stat">
            <div class="mp-model-stat-label">Residual std error</div>
            <div class="mp-model-stat-value">$${Number(model.rse).toFixed(4)}</div>
            <div class="mp-model-stat-sub">Avg model error</div>
        </div>
        <div class="mp-model-stat">
            <div class="mp-model-stat-label">Recent trend (3-month)</div>
            <div class="mp-model-stat-value" style="color:${trendColor};">${trendDir}</div>
            <div class="mp-model-stat-sub">${model.mom_change > 0 ? '+' : ''}${Number(model.mom_change).toFixed(1)}% vs prior 3mo</div>
        </div>
        <div class="mp-model-stat">
            <div class="mp-model-stat-label">Intercept</div>
            <div class="mp-model-stat-value">$${Number(model.intercept).toFixed(4)}</div>
            <div class="mp-model-stat-sub">Base price (month 0)</div>
        </div>`;
    }

    document.getElementById('wsModelStats').innerHTML = statGrid(ws?.model);
    document.getElementById('rtModelStats').innerHTML = statGrid(rt?.model);
    wrap.style.display = 'block';
}

// ── Auto-run on load with defaults
(function init() {
    runForecast();
})();
</script>

<?php include 'user_footer.php'; ?>