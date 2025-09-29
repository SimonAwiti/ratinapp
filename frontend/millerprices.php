<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
include '../admin/includes/config.php';
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

/**
 * Build SQL query with filters
 */
function buildMillerPricesQuery($filters = []) {
    $sql = "SELECT
                mp.id,
                mp.town AS market,
                mp.commodity_id AS commodity,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                'wholesale' AS price_type,
                mp.price_usd AS Price,
                mp.day_change,
                mp.month_change,
                mp.date_posted,
                mp.status,
                ds.data_source_name AS data_source,
                mp.country AS country_admin_0,
                'kg' AS unit,
                er.kshusd,
                er.tshusd,
                er.ugxusd,
                er.rwfusd,
                er.birrusd
            FROM
                miller_prices mp
            LEFT JOIN
                commodities c ON mp.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON mp.data_source_id = ds.id
            LEFT JOIN
                (SELECT * FROM exchange_rates ORDER BY date DESC LIMIT 1) er ON 1=1
            WHERE
                mp.status IN ('published', 'approved')";
    
    // Apply filters
    if (!empty($filters['country'])) {
        $sql .= " AND mp.country = '" . $con->real_escape_string($filters['country']) . "'";
    }
    
    if (!empty($filters['town'])) {
        $sql .= " AND mp.town = '" . $con->real_escape_string($filters['town']) . "'";
    }
    
    if (!empty($filters['commodity'])) {
        $sql .= " AND mp.commodity_id = " . (int)$filters['commodity'];
    }
    
    if (!empty($filters['price_type'])) {
        $sql .= " AND 'wholesale' = '" . $con->real_escape_string($filters['price_type']) . "'";
    }
    
    if (!empty($filters['data_source'])) {
        $sql .= " AND ds.data_source_name = '" . $con->real_escape_string($filters['data_source']) . "'";
    }
    
    if (!empty($filters['commodity_category'])) {
        $sql .= " AND c.category = '" . $con->real_escape_string($filters['commodity_category']) . "'";
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(mp.date_posted) >= '" . $con->real_escape_string($filters['date_from']) . "'";
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(mp.date_posted) <= '" . $con->real_escape_string($filters['date_to']) . "'";
    }
    
    if (!empty($filters['price_min'])) {
        $sql .= " AND mp.price_usd >= " . (float)$filters['price_min'];
    }
    
    if (!empty($filters['price_max'])) {
        $sql .= " AND mp.price_usd <= " . (float)$filters['price_max'];
    }
    
    $sql .= " ORDER BY mp.date_posted DESC";
    
    return $sql;
}

/**
 * Fetch miller prices data with pagination
 */
function getMillerPricesData($con, $limit = 10, $offset = 0, $filters = []) {
    $sql = buildMillerPricesQuery($filters);
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $result = $con->query($sql);
    if (!$result) {
        error_log("Error fetching miller prices data: " . $con->error);
        return [];
    }

    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $result->free();
    return $data;
}

/**
 * Get total number of records for pagination
 */
function getTotalMillerPriceRecords($con, $filters = []) {
    $sql = buildMillerPricesQuery($filters);
    $sql = "SELECT COUNT(*) as total FROM ($sql) as count_query";
    $result = $con->query($sql);
    if (!$result) {
        error_log("Error counting miller prices records: " . $con->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)$row['total'];
}

/**
 * Calculate day-over-day price change
 */
function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $con) {
    if ($currentPrice === null || $currentPrice === '') return 0;

    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $sql = "SELECT price_usd FROM miller_prices
            WHERE commodity_id = " . (int)$commodityId . "
            AND town = '" . $con->real_escape_string($market) . "'
            AND DATE(date_posted) = '$yesterday'";

    $result = $con->query($sql);
    if (!$result) return 0;

    if ($result->num_rows > 0) {
        $yesterdayData = $result->fetch_assoc();
        $yesterdayPrice = $yesterdayData['price_usd'];
        if ($yesterdayPrice != 0) {
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2);
        }
    }
    return 0;
}

/**
 * Calculate month-over-month price change
 */
function calculateDoMChange($currentPrice, $commodityId, $market, $priceType, $con) {
    if ($currentPrice === null || $currentPrice === '') return 0;

    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));

    $sql = "SELECT AVG(price_usd) as avg_price FROM miller_prices
            WHERE commodity_id = " . (int)$commodityId . "
            AND town = '" . $con->real_escape_string($market) . "'
            AND DATE(date_posted) BETWEEN '$firstDayOfLastMonth' AND '$lastDayOfLastMonth'";

    $result = $con->query($sql);
    if (!$result) return 0;

    if ($result->num_rows > 0) {
        $monthData = $result->fetch_assoc();
        $averagePrice = $monthData['avg_price'];
        if ($averagePrice != 0) {
            $change = (($currentPrice - $averagePrice) / $averagePrice) * 100;
            return round($change, 2);
        }
    }
    return 0;
}

// Get filter values from request
$filters = [
    'country' => isset($_GET['country']) ? $_GET['country'] : '',
    'town' => isset($_GET['town']) ? $_GET['town'] : '',
    'commodity' => isset($_GET['commodity']) ? $_GET['commodity'] : '',
    'price_type' => isset($_GET['price_type']) ? $_GET['price_type'] : '',
    'data_source' => isset($_GET['data_source']) ? $_GET['data_source'] : '',
    'commodity_category' => isset($_GET['commodity_category']) ? $_GET['commodity_category'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'price_min' => isset($_GET['price_min']) ? $_GET['price_min'] : '',
    'price_max' => isset($_GET['price_max']) ? $_GET['price_max'] : ''
];

// Get total records and setup pagination
$total_records = getTotalMillerPriceRecords($con, $filters);
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch data
$prices_data = getMillerPricesData($con, $limit, $offset, $filters);
$total_pages = ceil($total_records / $limit);

// Get chart data (without pagination)
$chart_data = getMillerPricesData($con, 1000, 0, $filters);

// Group data for display
$grouped_data = [];
foreach ($prices_data as $price) {
    if (!isset($price['market'], $price['commodity'], $price['date_posted'], $price['data_source'])) {
        continue;
    }
    $date = date('Y-m-d', strtotime($price['date_posted']));
    $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'] . '_' . $price['data_source'];
    $grouped_data[$group_key][] = $price;
}

// Get filter options for dropdowns
$countries = [];
$towns = [];
$commodities = [];
$data_sources = [];

$options_query = "SELECT DISTINCT country FROM miller_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row['country'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT town FROM miller_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $towns[] = $row['town'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT c.id, c.commodity_name, c.variety 
                  FROM miller_prices mp
                  JOIN commodities c ON mp.commodity_id = c.id
                  WHERE mp.status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $display = $row['commodity_name'];
        if (!empty($row['variety'])) {
            $display .= ' (' . $row['variety'] . ')';
        }
        $commodities[$row['id']] = $display;
    }
    $result->free();
}

$options_query = "SELECT DISTINCT ds.data_source_name 
                  FROM miller_prices mp
                  JOIN data_sources ds ON mp.data_source_id = ds.id
                  WHERE mp.status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data_sources[] = $row['data_source_name'];
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATIN - Miller Prices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #8B4513;
            --primary-light: #f5d6c6;
            --secondary-color: #6c757d;
            --success-color: #059669;
            --danger-color: #dc2626;
            --light-gray: #f8f9fa;
            --border-color: #eee;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #fff;
            border-right: 1px solid var(--border-color);
            padding: 20px 15px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sidebar .ratin-logo {
            max-width: 180px;
            height: auto;
        }
        
        .sidebar h6 {
            color: var(--secondary-color);
            font-weight: 600;
            margin: 25px 0 15px 10px;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 0.5px;
        }
        
        .sidebar .nav-link {
            color: #444;
            padding: 12px 15px;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.95em;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 0.9em;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }
        
        /* Header */
        .header-container {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            margin-left: 250px;
            height: 70px;
            z-index: 999;
        }
        
        .breadcrumb {
            margin: 0;
            font-size: 0.95em;
        }
        
        .breadcrumb a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .user-display {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: var(--primary-color);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 25px;
            flex-grow: 1;
            margin-top: 70px;
        }
        
        /* Card styles */
        .dashboard-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        /* Toolbar styles */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 25px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 18px;
            font-size: 0.85em;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        
        .btn-outline {
            background-color: #fff;
            border-color: #ddd;
            color: #444;
        }
        
        .btn-outline:hover {
            background-color: #f5f5f5;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #7a3a12;
            border-color: #7a3a12;
        }
        
        /* Table container */
        .table-container {
            width: 100%;
            overflow-x: auto;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        
        /* Table styles */
        .data-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.85em;
        }
        
        .data-table th, 
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
            vertical-align: middle;
        }
        
        .data-table th {
            position: sticky;
            top: 0;
            background-color: #f5f5f5;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8em;
            color: #555;
        }
        
        .data-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        /* Price change indicators */
        .change-positive {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .change-negative {
            color: var(--danger-color);
            font-weight: 500;
        }
        
        /* Checkbox styling */
        .checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-info {
            font-size: 0.85em;
            color: #666;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border-radius: 4px;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s ease;
            font-size: 0.85em;
        }
        
        .page-btn:hover:not(.active) {
            background-color: #e0e0e0;
        }
        
        .page-btn.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        /* Filter section */
        .filter-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            margin-bottom: 0;
        }
        
        .filter-label {
            display: block;
            font-size: 0.85em;
            font-weight: 500;
            color: #555;
            margin-bottom: 6px;
        }
        
        .filter-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.85em;
        }
        
        /* View tabs */
        .view-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }
        
        .view-tab {
            padding: 15px 20px;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            color: #666;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        /* Chart container */
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        
        /* Map container */
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Map popup styling */
        .map-popup {
            font-family: Arial, sans-serif;
        }
        .map-popup h4 {
            margin: 0 0 8px 0;
            color: #8B4513;
        }
        .map-popup p {
            margin: 4px 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                margin-left: 0;
            }
            
            .header-container, 
            .main-content {
                margin-left: 0;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar-left, 
            .toolbar-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img class="ratin-logo" src="../base/img/Ratin-logo-1.png" alt="RATIN Logo">
        </div>
        
        <h6>Price Parity</h6>
        <div class="submenu">
            <a href="marketprices.php" class="nav-link">
                <i class="fas fa-store-alt"></i> Market Prices
            </a>
            <a href="#" class="nav-link active">
                <i class="fas fa-industry"></i> Miller Prices
            </a>
            <a href="xbtvols.php" class="nav-link">
                <i class="fas fa-exchange-alt"></i> XBT Volumes
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1">
        <!-- Header -->
        <div class="header-container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="#"><i class="fa fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Miller Prices</li>
                </ol>
            </nav>
            <div class="user-display">
                <i class="fa fa-user-circle"></i> <span>Martin Kim</span>
            </div>
        </div>

        <!-- Content -->
        <div class="main-content">
            <!-- Filter Section -->
            <form id="filter-form" method="GET">
                <div class="filter-section dashboard-card">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Country/District</label>
                            <select name="country" class="filter-input">
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>" <?= $filters['country'] == $country ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Town</label>
                            <select name="town" class="filter-input">
                                <option value="">Select Town</option>
                                <?php foreach ($towns as $town): ?>
                                    <option value="<?= htmlspecialchars($town) ?>" <?= $filters['town'] == $town ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($town) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Commodity</label>
                            <select name="commodity" class="filter-input">
                                <option value="">Select Commodity</option>
                                <?php foreach ($commodities as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= $filters['commodity'] == $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Price Type</label>
                            <select name="price_type" class="filter-input">
                                <option value="">All Types</option>
                                <option value="wholesale" <?= $filters['price_type'] == 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
                                <option value="retail" <?= $filters['price_type'] == 'retail' ? 'selected' : '' ?>>Retail</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Data Source</label>
                            <select name="data_source" class="filter-input">
                                <option value="">All Sources</option>
                                <?php foreach ($data_sources as $source): ?>
                                    <option value="<?= htmlspecialchars($source) ?>" <?= $filters['data_source'] == $source ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($source) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Date Range</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filters['date_from']) ?>">
                                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filters['date_to']) ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Price Range (USD)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="price_min" class="filter-input" placeholder="Min" value="<?= htmlspecialchars($filters['price_min']) ?>">
                                <input type="number" name="price_max" class="filter-input" placeholder="Max" value="<?= htmlspecialchars($filters['price_max']) ?>">
                            </div>
                        </div>
                        <div class="filter-group" style="display: flex; align-items: flex-end; gap: 8px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <button type="button" id="reset-filters" class="btn btn-outline" style="flex: 1;">
                                <i class="fas fa-refresh"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Data Display Card -->
            <div class="dashboard-card">
                <!-- View Tabs -->
                <div style="border-bottom: 1px solid var(--border-color);">
                    <nav class="view-tabs">
                        <button class="view-tab active" data-view="table">
                            <i class="fa fa-table"></i> Table View
                        </button>
                        <button class="view-tab" data-view="chart">
                            <i class="fa fa-chart-bar"></i> Chart View
                        </button>
                        <button class="view-tab" data-view="map">
                            <i class="fa fa-map"></i> Map View
                        </button>
                    </nav>
                </div>

                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-primary">
                            <i class="fas fa-ellipsis-h"></i> All Commodities
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-seedling"></i> Cereals
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-tint"></i> Oilseeds
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-leaf"></i> Pulses
                        </button>
                    </div>
                    <div class="toolbar-right">
                        <button class="btn btn-outline">
                            <i class="fas fa-download"></i> Download Data
                        </button>
                    </div>
                </div>

                <!-- Table View -->
                <div id="table-view" class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" class="checkbox"></th>
                                <th>Town</th>
                                <th>Country</th>
                                <th>Commodity</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Unit</th>
                                <th>Price (Local)</th>
                                <th>Price (USD)</th>
                                <th>Exchange Rate</th>
                                <th>Day Change(%)</th>
                                <th>Month Change(%)</th>
                                <th>Year Change(%)</th>
                                <th>Data Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($grouped_data)): ?>
                                <?php foreach ($grouped_data as $group_key => $prices_in_group): ?>
                                    <?php 
                                    $first_row = true;
                                    $group_price_ids = array_column($prices_in_group, 'id');
                                    $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));
                                    ?>
                                    
                                    <?php foreach($prices_in_group as $price): ?>
                                        <?php
                                        $dayChange = $price['day_change'] ?? calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                                        $monthChange = $price['month_change'] ?? calculateDoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                                        $yearChange = 0;
                                        
                                        $dayChangeClass = $dayChange >= 0 ? 'change-positive' : 'change-negative';
                                        $monthChangeClass = $monthChange >= 0 ? 'change-positive' : 'change-negative';
                                        $yearChangeClass = $yearChange >= 0 ? 'change-positive' : 'change-negative';
                                        
                                        $currency = '';
                                        $exchangeRate = 1;
                                        $localPrice = $price['Price'];
                                        
                                        switch(strtolower($price['country_admin_0'])) {
                                            case 'kenya':
                                                $currency = 'KES';
                                                $exchangeRate = $price['kshusd'] ?? 1;
                                                $localPrice = $price['Price'] * $exchangeRate;
                                                break;
                                            case 'tanzania':
                                                $currency = 'TSH';
                                                $exchangeRate = $price['tshusd'] ?? 1;
                                                $localPrice = $price['Price'] * $exchangeRate;
                                                break;
                                            case 'uganda':
                                                $currency = 'UGX';
                                                $exchangeRate = $price['ugxusd'] ?? 1;
                                                $localPrice = $price['Price'] * $exchangeRate;
                                                break;
                                            case 'rwanda':
                                                $currency = 'RWF';
                                                $exchangeRate = $price['rwfusd'] ?? 1;
                                                $localPrice = $price['Price'] * $exchangeRate;
                                                break;
                                            case 'ethiopia':
                                                $currency = 'ETB';
                                                $exchangeRate = $price['birrusd'] ?? 1;
                                                $localPrice = $price['Price'] * $exchangeRate;
                                                break;
                                            default:
                                                $currency = 'USD';
                                                $exchangeRate = 1;
                                        }
                                        ?>
                                        <tr>
                                            <?php if ($first_row): ?>
                                                <td rowspan="<?= count($prices_in_group) ?>">
                                                    <input type="checkbox" 
                                                           data-group-key="<?= $group_key ?>"
                                                           data-price-ids="<?= $group_price_ids_json ?>"
                                                           class="checkbox">
                                                </td>
                                                <td rowspan="<?= count($prices_in_group) ?>" style="font-weight: 500;">
                                                    <?= htmlspecialchars($price['market']) ?>
                                                </td>
                                                <td rowspan="<?= count($prices_in_group) ?>">
                                                    <?= htmlspecialchars($price['country_admin_0']) ?>
                                                </td>
                                                <td rowspan="<?= count($prices_in_group) ?>">
                                                    <?= htmlspecialchars($price['commodity_display']) ?>
                                                </td>
                                                <td rowspan="<?= count($prices_in_group) ?>">
                                                    <?= date('d/m/Y', strtotime($price['date_posted'])) ?>
                                                </td>
                                            <?php endif; ?>
                                            <td><?= htmlspecialchars($price['price_type']) ?></td>
                                            <td><?= htmlspecialchars($price['unit']) ?></td>
                                            <td style="font-weight: 600;">
                                                <?= number_format($localPrice, 2) ?> <?= $currency ?>
                                            </td>
                                            <td style="font-weight: 600;">
                                                $<?= number_format($price['Price'], 2) ?>
                                            </td>
                                            <td>
                                                <?= number_format($exchangeRate, 2) ?> <?= $currency ?>/USD
                                            </td>
                                            <td class="<?= $dayChangeClass ?>">
                                                <?= $dayChange >= 0 ? '+' : '' ?><?= $dayChange ?>%
                                            </td>
                                            <td class="<?= $monthChangeClass ?>">
                                                <?= $monthChange >= 0 ? '+' : '' ?><?= $monthChange ?>%
                                            </td>
                                            <td class="<?= $yearChangeClass ?>">
                                                <?= $yearChange >= 0 ? '+' : '' ?><?= $yearChange ?>%
                                            </td>
                                            <?php if ($first_row): ?>
                                                <td rowspan="<?= count($prices_in_group) ?>">
                                                    <?= htmlspecialchars($price['data_source']) ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php $first_row = false; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="14" style="text-align: center; padding: 30px;">
                                        No miller prices data found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Chart View -->
                <div id="chart-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4 style="margin-bottom: 5px;">Miller Price Trends</h4>
                            <p style="color: var(--secondary-color); margin: 0;">Visual representation of price movements</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <select id="chart-type-selector" class="filter-input" style="width: 180px;">
                                <option value="line">Line Chart</option>
                                <option value="bar">Bar Chart</option>
                                <option value="combo">Combined View</option>
                            </select>
                            <button class="btn btn-outline">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-grid" style="margin-bottom: 25px;">
                        <div class="filter-group">
                            <label class="filter-label">Country</label>
                            <select id="chart-country-filter" class="filter-input">
                                <option value="all">All Countries</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Town</label>
                            <select id="chart-town-filter" class="filter-input">
                                <option value="all">All Towns</option>
                                <?php foreach ($towns as $town): ?>
                                    <option value="<?= htmlspecialchars($town) ?>"><?= htmlspecialchars($town) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Commodity</label>
                            <select id="chart-commodity-filter" class="filter-input">
                                <option value="all">All Commodities</option>
                                <?php foreach ($commodities as $id => $name): ?>
                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Date Range</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="date" id="chart-date-from" class="filter-input">
                                <input type="date" id="chart-date-to" class="filter-input">
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="price-trend-chart"></canvas>
                    </div>
                </div>

                <!-- Map View -->
                <div id="map-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4>Miller Prices Map View</h4>
                            <p style="color: var(--secondary-color);">Geographic distribution of miller prices</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <select id="map-commodity-filter" class="filter-input" style="width: 200px;">
                                <option value="all">All Commodities</option>
                                <?php foreach ($commodities as $id => $name): ?>
                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div id="map"></div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Market Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div id="market-stats">
                                        <p>Click on a market marker to view statistics</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Price Legend</h5>
                                </div>
                                <div class="card-body">
                                    <div id="price-legend">
                                        <div class="d-flex align-items-center mb-2">
                                            <div style="width: 20px; height: 20px; background-color: #ff4444; border-radius: 50%; margin-right: 10px;"></div>
                                            <span>High Prices</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <div style="width: 20px; height: 20px; background-color: #ffaa00; border-radius: 50%; margin-right: 10px;"></div>
                                            <span>Medium Prices</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div style="width: 20px; height: 20px; background-color: #44ff44; border-radius: 50%; margin-right: 10px;"></div>
                                            <span>Low Prices</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_records > 0): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <span style="font-weight: 500;"><?= $offset + 1 ?></span> to 
                        <span style="font-weight: 500;"><?= min($offset + $limit, $total_records) ?></span> of 
                        <span style="font-weight: 500;"><?= $total_records ?></span> results
                    </div>
                    <div class="pagination-controls">
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="page-btn" <?= $page <= 1 ? 'style="visibility: hidden;"' : '' ?>>
                            Previous
                        </a>
                        
                        <?php
                        $visiblePages = 5;
                        $startPage = max(1, $page - floor($visiblePages / 2));
                        $endPage = min($total_pages, $startPage + $visiblePages - 1);
                        
                        if ($startPage > 1) {
                            echo '<a href="?' . http_build_query(array_merge($filters, ['page' => 1])) . '" class="page-btn">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="page-btn" style="background: transparent;">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = $i == $page ? 'active' : '';
                            echo '<a href="?' . http_build_query(array_merge($filters, ['page' => $i])) . '" class="page-btn '.$activeClass.'">'.$i.'</a>';
                        }
                        
                        if ($endPage < $total_pages) {
                            if ($endPage < $total_pages - 1) {
                                echo '<span class="page-btn" style="background: transparent;">...</span>';
                            }
                            echo '<a href="?' . http_build_query(array_merge($filters, ['page' => $total_pages])) . '" class="page-btn">'.$total_pages.'</a>';
                        }
                        ?>
                        
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="page-btn" <?= $page >= $total_pages ? 'style="visibility: hidden;"' : '' ?>>
                            Next
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let priceTrendChart;
let currentChartType = 'line';
let map;

function initCharts(data) {
    const processedData = processChartData(data);
    
    if (priceTrendChart) priceTrendChart.destroy();
    
    const priceTrendCtx = document.getElementById('price-trend-chart');
    if (!priceTrendCtx) return;
    
    priceTrendChart = new Chart(priceTrendCtx.getContext('2d'), {
        type: currentChartType,
        data: {
            labels: processedData.dates,
            datasets: processedData.trendDatasets
        },
        options: getTrendChartOptions()
    });
}

function processChartData(data) {
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            console.error('Error parsing chart data:', e);
            data = [];
        }
    }
    
    const selectedCountry = document.getElementById('chart-country-filter')?.value;
    const selectedTown = document.getElementById('chart-town-filter')?.value;
    const selectedCommodity = document.getElementById('chart-commodity-filter')?.value;
    const dateFrom = document.getElementById('chart-date-from')?.value;
    const dateTo = document.getElementById('chart-date-to')?.value;
    
    let filteredData = data;
    
    if (selectedCountry && selectedCountry !== 'all') {
        filteredData = filteredData.filter(item => item.country_admin_0 === selectedCountry);
    }
    
    if (selectedTown && selectedTown !== 'all') {
        filteredData = filteredData.filter(item => item.market === selectedTown);
    }
    
    if (selectedCommodity && selectedCommodity !== 'all') {
        filteredData = filteredData.filter(item => item.commodity_display === selectedCommodity);
    }
    
    if (dateFrom) {
        filteredData = filteredData.filter(item => {
            const itemDate = new Date(item.date_posted).toISOString().split('T')[0];
            return itemDate >= dateFrom;
        });
    }
    
    if (dateTo) {
        filteredData = filteredData.filter(item => {
            const itemDate = new Date(item.date_posted).toISOString().split('T')[0];
            return itemDate <= dateTo;
        });
    }
    
    const dates = [...new Set(filteredData.map(item => {
        const date = new Date(item.date_posted);
        return date.toISOString().split('T')[0];
    }))].sort();
    
    const trendDatasets = [];
    
    if (selectedCommodity && selectedCommodity !== 'all') {
        const prices = dates.map(date => {
            const item = filteredData.find(d => 
                d.commodity_display === selectedCommodity && 
                new Date(d.date_posted).toISOString().split('T')[0] === date
            );
            return item ? parseFloat(item.Price) : null;
        });
        
        trendDatasets.push({
            label: selectedCommodity,
            data: prices,
            borderColor: '#8B4513',
            backgroundColor: 'rgba(139, 69, 19, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.1
        });
    } else {
        const commodities = [...new Set(filteredData.map(item => item.commodity_display))];
        const colorPalette = [
            '#8B4513', '#1E88E5', '#FFC107', '#004D40', 
            '#D81B60', '#039BE5', '#7CB342', '#5E35B1'
        ];
        
        commodities.forEach((commodity, index) => {
            const prices = dates.map(date => {
                const item = filteredData.find(d => 
                    d.commodity_display === commodity && 
                    new Date(d.date_posted).toISOString().split('T')[0] === date
                );
                return item ? parseFloat(item.Price) : null;
            });
            
            trendDatasets.push({
                label: commodity,
                data: prices,
                borderColor: colorPalette[index % colorPalette.length],
                backgroundColor: 'rgba(0, 0, 0, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.1
            });
        });
    }
    
    return {
        dates,
        trendDatasets
    };
}

function getTrendChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Miller Price Trends Over Time',
                font: { size: 16 }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.parsed.y.toFixed(2)}`;
                    }
                }
            },
            zoom: {
                zoom: {
                    wheel: { enabled: true },
                    pinch: { enabled: true },
                    mode: 'xy'
                },
                pan: {
                    enabled: true,
                    mode: 'xy'
                }
            },
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    padding: 20,
                    usePointStyle: true
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Date',
                    font: { weight: 'bold' }
                },
                grid: { display: false }
            },
            y: {
                title: {
                    display: true,
                    text: 'Price (USD)',
                    font: { weight: 'bold' }
                },
                beginAtZero: false
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    };
}

function initMap(data) {
    if (map) {
        map.remove();
    }
    
    map = L.map('map').setView([1.0, 35.0], 6);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    
    const selectedCommodity = document.getElementById('map-commodity-filter')?.value;
    let filteredData = data;
    
    if (selectedCommodity && selectedCommodity !== 'all') {
        filteredData = filteredData.filter(item => item.commodity_display === selectedCommodity);
    }
    
    const marketData = {};
    filteredData.forEach(item => {
        if (!marketData[item.market]) {
            marketData[item.market] = {
                prices: [],
                commodities: new Set(),
                country: item.country_admin_0,
                data_source: item.data_source
            };
        }
        marketData[item.market].prices.push(parseFloat(item.Price));
        marketData[item.market].commodities.add(item.commodity_display);
    });
    
    const allPrices = filteredData.map(item => parseFloat(item.Price));
    const minPrice = Math.min(...allPrices);
    const maxPrice = Math.max(...allPrices);
    const priceRange = maxPrice - minPrice;
    
    Object.keys(marketData).forEach(market => {
        const data = marketData[market];
        const avgPrice = data.prices.reduce((a, b) => a + b, 0) / data.prices.length;
        
        let markerColor;
        const priceRatio = (avgPrice - minPrice) / priceRange;
        if (priceRatio > 0.7) {
            markerColor = '#ff4444';
        } else if (priceRatio > 0.3) {
            markerColor = '#ffaa00';
        } else {
            markerColor = '#44ff44';
        }
        
        const markerIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white;"></div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        
        const coords = getDefaultLatLng(data.country);
        const marker = L.marker(coords, {icon: markerIcon}).addTo(map);
        
        const popupContent = `
            <div class="map-popup">
                <h4>${market}</h4>
                <p><strong>Country:</strong> ${data.country}</p>
                <p><strong>Average Price:</strong> ${avgPrice.toFixed(2)}</p>
                <p><strong>Commodities:</strong> ${Array.from(data.commodities).join(', ')}</p>
                <p><strong>Data Source:</strong> ${data.data_source}</p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        
        marker.on('click', function() {
            updateMarketStats(market, data, avgPrice);
        });
    });
}

function getDefaultLatLng(country) {
    const countryCoords = {
        'Kenya': [1.0, 38.0],
        'Tanzania': [-6.0, 35.0],
        'Uganda': [1.0, 32.0],
        'Rwanda': [-2.0, 30.0],
        'Ethiopia': [9.0, 40.0]
    };
    return countryCoords[country] || [0, 35];
}

function updateMarketStats(market, data, avgPrice) {
    const statsDiv = document.getElementById('market-stats');
    const minPrice = Math.min(...data.prices);
    const maxPrice = Math.max(...data.prices);
    
    statsDiv.innerHTML = `
        <h6>${market}</h6>
        <p><strong>Country:</strong> ${data.country}</p>
        <p><strong>Average Price:</strong> ${avgPrice.toFixed(2)}</p>
        <p><strong>Price Range:</strong> ${minPrice.toFixed(2)} - ${maxPrice.toFixed(2)}</p>
        <p><strong>Commodities:</strong> ${Array.from(data.commodities).join(', ')}</p>
        <p><strong>Data Points:</strong> ${data.prices.length}</p>
        <p><strong>Data Source:</strong> ${data.data_source}</p>
    `;
}

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.view-tab');
    const views = {
        'table': document.getElementById('table-view'),
        'chart': document.getElementById('chart-view'),
        'map': document.getElementById('map-view')
    };
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            Object.values(views).forEach(v => {
                if (v) v.style.display = 'none';
            });
            
            if (views[view]) {
                views[view].style.display = 'block';
                
                if (view === 'chart') {
                    const chartData = <?= json_encode($chart_data) ?>;
                    initCharts(chartData);
                }
                
                if (view === 'map') {
                    const mapData = <?= json_encode($chart_data) ?>;
                    initMap(mapData);
                }
            }
        });
    });

    document.getElementById('chart-type-selector')?.addEventListener('change', function() {
        currentChartType = this.value;
        if (priceTrendChart) {
            priceTrendChart.config.type = currentChartType;
            priceTrendChart.update();
        }
    });

    document.getElementById('chart-country-filter')?.addEventListener('change', updateCharts);
    document.getElementById('chart-town-filter')?.addEventListener('change', updateCharts);
    document.getElementById('chart-commodity-filter')?.addEventListener('change', updateCharts);
    document.getElementById('chart-date-from')?.addEventListener('change', updateCharts);
    document.getElementById('chart-date-to')?.addEventListener('change', updateCharts);
    document.getElementById('map-commodity-filter')?.addEventListener('change', updateMap);

    function updateCharts() {
        const chartData = <?= json_encode($chart_data) ?>;
        initCharts(chartData);
    }

    function updateMap() {
        const mapData = <?= json_encode($chart_data) ?>;
        initMap(mapData);
    }

    document.getElementById('reset-filters')?.addEventListener('click', function() {
        window.location.href = window.location.pathname;
    });
});
</script>
</body>
</html>