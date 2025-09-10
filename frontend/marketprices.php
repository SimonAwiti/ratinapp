<?php
// market_prices_view.php

// Include your database configuration file
include '../admin/includes/config.php';

// Function to build the SQL query with filters
function buildPricesQuery($filters = []) {
    $sql = "SELECT
                p.id,
                p.market,
                p.commodity,
                c.commodity_name,
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source,
                p.country_admin_0,
                p.unit,
                er.kshusd,
                er.tshusd,
                er.ugxusd,
                er.rwfusd,
                er.birrusd
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id
            LEFT JOIN
                (SELECT * FROM exchange_rates ORDER BY date DESC LIMIT 1) er ON 1=1
            WHERE
                p.status IN ('published', 'approved')";
    
    // Apply filters
    if (!empty($filters['country'])) {
        $sql .= " AND p.country_admin_0 = '" . $filters['country'] . "'";
    }
    
    if (!empty($filters['market'])) {
        $sql .= " AND p.market = '" . $filters['market'] . "'";
    }
    
    if (!empty($filters['commodity'])) {
        $sql .= " AND p.commodity = " . (int)$filters['commodity'];
    }
    
    if (!empty($filters['price_type'])) {
        $sql .= " AND p.price_type = '" . $filters['price_type'] . "'";
    }
    
    if (!empty($filters['data_source'])) {
        $sql .= " AND p.data_source = '" . $filters['data_source'] . "'";
    }
    
    if (!empty($filters['commodity_category'])) {
        $sql .= " AND c.category = '" . $filters['commodity_category'] . "'";
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(p.date_posted) >= '" . $filters['date_from'] . "'";
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(p.date_posted) <= '" . $filters['date_to'] . "'";
    }
    
    if (!empty($filters['price_range'])) {
        // Handle price range filter (assuming format like "100-200")
        $priceRange = explode('-', $filters['price_range']);
        if (count($priceRange) == 2) {
            $minPrice = (float)$priceRange[0];
            $maxPrice = (float)$priceRange[1];
            $sql .= " AND p.Price BETWEEN $minPrice AND $maxPrice";
        }
    }
    
    $sql .= " ORDER BY p.date_posted DESC";
    
    return $sql;
}

// Function to fetch prices data from the database with filters
function getPricesData($con, $limit = 10, $offset = 0, $filters = []) {
    $sql = buildPricesQuery($filters);
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $result = $con->query($sql);
    $data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $result->free();
    } else {
        error_log("Error fetching prices data: " . $con->error);
    }
    return $data;
}

function getTotalPriceRecords($con, $filters = []) {
    $sql = buildPricesQuery($filters);
    $sql = "SELECT COUNT(*) as total FROM ($sql) as count_query";
    $result = $con->query($sql);
     if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
     }
     return 0;
}

// Get filter values from request
$filters = [
    'country' => isset($_GET['country']) ? $_GET['country'] : '',
    'market' => isset($_GET['market']) ? $_GET['market'] : '',
    'commodity' => isset($_GET['commodity']) ? $_GET['commodity'] : '',
    'price_type' => isset($_GET['price_type']) ? $_GET['price_type'] : '',
    'data_source' => isset($_GET['data_source']) ? $_GET['data_source'] : '',
    'commodity_category' => isset($_GET['commodity_category']) ? $_GET['commodity_category'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'price_range' => isset($_GET['price_range']) ? $_GET['price_range'] : ''
];

// Get total number of records with filters
$total_records = getTotalPriceRecords($con, $filters);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch prices data with filters
$prices_data = getPricesData($con, $limit, $offset, $filters);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Function to calculate price changes
function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $con) {
    if ($currentPrice === null || $currentPrice === '') return 0;

    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $sql = "SELECT Price FROM market_prices
            WHERE commodity = " . (int)$commodityId . "
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) = '$yesterday'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $yesterdayData = $result->fetch_assoc();
        $yesterdayPrice = $yesterdayData['Price'];
        if($yesterdayPrice != 0){
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2);
        }
        return 0;
    }
    return 0;
}

function calculateDoMChange($currentPrice, $commodityId, $market, $priceType, $con) {
    if ($currentPrice === null || $currentPrice === '') return 0;

    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));

    $sql = "SELECT AVG(Price) as avg_price FROM market_prices
            WHERE commodity = " . (int)$commodityId . "
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) BETWEEN '$firstDayOfLastMonth' AND '$lastDayOfLastMonth'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $monthData = $result->fetch_assoc();
        $averagePrice = $monthData['avg_price'];
        if($averagePrice != 0){
             $change = (($currentPrice - $averagePrice) / $averagePrice) * 100;
             return round($change, 2);
        }
        return 0;
    }
    return 0;
}

// Group data by market, commodity, date, and source
$grouped_data = [];
foreach ($prices_data as $price) {
    $date = date('Y-m-d', strtotime($price['date_posted']));
    $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'] . '_' . $price['data_source'];
    $grouped_data[$group_key][] = $price;
}

// Get filter options for dropdowns
$countries = [];
$markets = [];
$commodities = [];
$price_types = [];
$data_sources = [];

$options_query = "SELECT DISTINCT country_admin_0 FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row['country_admin_0'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT market FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $markets[] = $row['market'];
    }
    $result->free();
}

$options_query = "SELECT id, commodity_name FROM commodities";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $commodities[$row['id']] = $row['commodity_name'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT price_type FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $price_types[] = $row['price_type'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT data_source FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data_sources[] = $row['data_source'];
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATIN - Market Prices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Your existing CSS styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 0px solid #ddd;
            padding: 15px;
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
            margin-bottom: 20px;
        }

        .sidebar .ratin-logo {
            max-width: 150px;
            height: auto;
        }

        .sidebar h6 {
            color: #6c757d;
            font-weight: bold;
            margin-top: 20px;
            padding-left: 10px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .sidebar .nav-link {
            color: #333;
            padding: 12px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 5px;
            transition: all 0.2s ease-in-out;
            font-size: 1.0em;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #f5d6c6;
            color: #8B4513;
        }

        /* Header */
        .header-container {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 20px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            z-index: 999;
            position: sticky;
            top: 0;
            margin-left: 250px;
            height: 64px;
        }

        .breadcrumb {
            margin: 0;
            font-size: 17px;
            color: #6c757d;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #8B4513;
            font-weight: bold;
        }

        .breadcrumb-item.active {
            color: #8B4513;
            font-weight: bold;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: " > ";
            color: #6c757d;
        }

        .user-display {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            color: #8B4513;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            flex-grow: 1;
            margin-top: 64px;
        }

        /* Container styles */
        .container {
            background: #fff;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        /* Toolbar styles */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
            gap: 10px;
        }
        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a {
            padding: 12px 20px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background-color: #eee;
            text-decoration: none;
            color: #333;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s ease;
        }
        .toolbar button:hover, .toolbar a:hover:not(.primary) {
            background-color: #e0e0e0;
        }
        .toolbar .primary {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .toolbar .primary:hover {
            background-color: rgba(160, 70, 40, 1);
        }

        /* Improved Table Container */
        .table-responsive-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0 15px;
            margin-bottom: 20px;
        }

        /* Wider Table with Better Column Sizes */
        table {
            width: 100%;
            min-width: 1500px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
        }

        /* Column Width Adjustments */
        table th:nth-child(1), table td:nth-child(1) { width: 50px; }
        table th:nth-child(2), table td:nth-child(2) { width: 180px; }
        table th:nth-child(3), table td:nth-child(3) { width: 150px; }
        table th:nth-child(4), table td:nth-child(4) { width: 200px; }
        table th:nth-child(5), table td:nth-child(5) { width: 120px; }
        table th:nth-child(6), table td:nth-child(6) { width: 120px; }
        table th:nth-child(7), table td:nth-child(7) { width: 100px; }
        table th:nth-child(8), table td:nth-child(8) { width: 180px; }
        table th:nth-child(9), table td:nth-child(9) { width: 180px; }
        table th:nth-child(10), table td:nth-child(10) { width: 180px; }
        table th:nth-child(11), table td:nth-child(11) { width: 150px; }
        table th:nth-child(12), table td:nth-child(12) { width: 150px; }
        table th:nth-child(13), table td:nth-child(13) { width: 150px; }
        table th:nth-child(14), table td:nth-child(14) { width: 180px; }

        /* Table Cell Styling */
        table th, table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Alternating row colors */
        table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        table tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        table tbody tr:hover {
            background-color: #f5f5f5;
        }

        /* Sticky Header with Shadow */
        table th {
            position: sticky;
            top: 0;
            background-color: #f1f1f1;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
            font-size: 13px;
        }

        /* Horizontal Scrollbar Styling */
        .table-responsive-container::-webkit-scrollbar {
            height: 10px;
        }
        .table-responsive-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-responsive-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .table-responsive-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Price Change Display */
        .change-positive {
            color: #059669;
            font-weight: bold;
        }
        .change-negative {
            color: #dc2626;
            font-weight: bold;
        }

        /* Checkbox styling */
        .checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            font-size: 14px;
            align-items: center;
            flex-wrap: wrap;
            border-top: 1px solid #eee;
            gap: 10px;
        }
        .pagination .pages {
            display: flex;
            gap: 5px;
        }
        .pagination .page {
            padding: 8px 12px;
            border-radius: 6px;
            background-color: #eee;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            transition: background-color 0.2s ease;
        }
        .pagination .current {
            background-color: #8B4513;
            color: white;
            font-weight: bold;
        }
        .pagination .page:hover:not(.current) {
            background-color: #ddd;
        }
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            background-color: white;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .pagination button:hover:not(:disabled) {
            background-color: #f5f5f5;
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
        }

        /* View tabs */
        .view-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
        }
        .view-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 24px;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            font-size: 14px;
            color: #666;
            background: none;
            border: none;
            cursor: pointer;
        }
        .view-tab.active {
            color: #8B4513;
            border-bottom-color: #8B4513;
        }

        /* Chart filters */
        .chart-filters {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .commodity-category-btn.active {
            background-color: #8B4513;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                margin-left: 0;
            }
            .header-container, .main-content {
                margin-left: 0;
            }
            .filter-section > div {
                grid-template-columns: 1fr !important;
            }
            .chart-filters {
                grid-template-columns: 1fr;
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
        </div><br>
        <br>
        <h6>Price Parity</h6>

        <div class="submenu" id="dataSubmenu" style="display: block;">
            <a href="#" class="nav-link active">
                <i class="fas fa-store-alt"></i> Market Prices
            </a>
            <a href="millerprices.php" class="nav-link">
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
                        <a href="#"><i class="fa fa-home"></i></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Market Prices</li>
                </ol>
            </nav>
            <div class="user-display">
                <i class="fa fa-user-circle"></i> <span>Martin Kim</span>
            </div>
        </div>

        <!-- Content -->
        <div class="main-content">
            <form id="filter-form" method="GET">
                <div class="filter-section">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Country/District</label>
                            <select name="country" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo isset($filters['country']) && $filters['country'] == $country ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Market</label>
                            <select name="market" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">Select Market</option>
                                <?php foreach ($markets as $market): ?>
                                    <option value="<?php echo htmlspecialchars($market); ?>" <?php echo isset($filters['market']) && $filters['market'] == $market ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($market); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Commodity</label>
                            <select name="commodity" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">Select Commodity</option>
                                <?php foreach ($commodities as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo isset($filters['commodity']) && $filters['commodity'] == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Price type</label>
                            <select name="price_type" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">All Types</option>
                                <?php foreach ($price_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo isset($filters['price_type']) && $filters['price_type'] == $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Data Source</label>
                            <select name="data_source" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">All Sources</option>
                                <?php foreach ($data_sources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source); ?>" <?php echo isset($filters['data_source']) && $filters['data_source'] == $source ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($source); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Date Range</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="date" name="date_from" value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <span style="color: #666;">to</span>
                                <input type="date" name="date_to" value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Market Prices</label>
                            <input type="text" name="price_range" value="<?php echo isset($filters['price_range']) ? $filters['price_range'] : ''; ?>" placeholder="Enter price range (e.g., 100-200)" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div style="display: flex; align-items: end; gap: 8px;">
                            <button type="submit" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-weight: 500; color: white; background: #8B4513; cursor: pointer;">
                                <i class="fa fa-filter"></i>
                                Apply filters
                            </button>
                            <button type="button" id="reset-filters" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-weight: 500; color: #374151; background: white; cursor: pointer;">
                                <i class="fa fa-refresh"></i>
                                Reset filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="container">
                <div style="border-bottom: 1px solid #eee;">
                    <nav class="view-tabs">
                        <button class="view-tab active" data-view="table">
                            <i class="fa fa-table"></i>
                            Table view
                        </button>
                        <button class="view-tab" data-view="chart">
                            <i class="fa fa-chart-bar"></i>
                            Chart view
                        </button>
                        <button class="view-tab" data-view="map">
                            <i class="fa fa-map"></i>
                            Map view
                        </button>
                    </nav>
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" class="commodity-category-btn <?php echo empty($filters['commodity_category']) ? 'active' : ''; ?>" data-category="">
                            <i class="fa fa-ellipsis-h"></i>
                            All
                        </button>
                        <button type="button" class="commodity-category-btn <?php echo isset($filters['commodity_category']) && $filters['commodity_category'] == 'Cereals' ? 'active' : ''; ?>" data-category="Cereals">
                            <i class="fa fa-seedling"></i>
                            Cereals
                        </button>
                        <button type="button" class="commodity-category-btn <?php echo isset($filters['commodity_category']) && $filters['commodity_category'] == 'Oilseeds' ? 'active' : ''; ?>" data-category="Oilseeds">
                            <i class="fa fa-tint"></i>
                            Oilseeds
                        </button>
                        <button type="button" class="commodity-category-btn <?php echo isset($filters['commodity_category']) && $filters['commodity_category'] == 'Pulses' ? 'active' : ''; ?>" data-category="Pulses">
                            <i class="fa fa-leaf"></i>
                            Pulses
                        </button>
                    </div>
                    <button id="download-btn" style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        Download
                        <i class="fa fa-download"></i>
                    </button>
                </div>

                <div id="table-view" class="table-responsive-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" class="checkbox"></th>
                                <th>Markets</th>
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
                            <?php
                            if (!empty($grouped_data)) {
                                foreach ($grouped_data as $group_key => $prices_in_group):
                                    $first_row = true;
                                    $group_price_ids = array_column($prices_in_group, 'id');
                                    $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));

                                    foreach($prices_in_group as $price):
                                        $dayChange = calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                                        $monthChange = calculateDoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                                        $yearChange = 20; // Hardcoded as per original design
                                        
                                        $dayChangeClass = $dayChange >= 0 ? 'change-positive' : 'change-negative';
                                        $monthChangeClass = $monthChange >= 0 ? 'change-positive' : 'change-negative';
                                        $yearChangeClass = $yearChange >= 0 ? 'change-positive' : 'change-negative';
                                        
                                        // Determine currency and exchange rate based on country
                                        $currency = '';
                                        $exchangeRate = 1;
                                        $localPrice = $price['Price'];
                                        $usdPrice = $price['Price'];
                                        
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
                                                $localPrice = $price['Price'];
                                        }
                            ?>
                            <tr>
                                <?php if ($first_row): ?>
                                    <td rowspan="<?php echo count($prices_in_group); ?>">
                                        <input type="checkbox" 
                                               data-group-key="<?php echo $group_key; ?>"
                                               data-price-ids="<?php echo $group_price_ids_json; ?>"
                                               class="checkbox" />
                                    </td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>" style="font-weight: 500;"><?php echo htmlspecialchars($price['market']); ?></td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['country_admin_0']); ?></td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['commodity_name']); ?></td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo date('d/m/Y', strtotime($price['date_posted'])); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($price['price_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['unit']); ?></td>
                                <td style="font-weight: 600;"><?php echo number_format($localPrice, 2); ?> <?php echo $currency; ?></td>
                                <td style="font-weight: 600;">$<?php echo number_format($usdPrice, 2); ?></td>
                                <td><?php echo number_format($exchangeRate, 2); ?> <?php echo $currency; ?>/USD</td>
                                <td class="<?php echo $dayChangeClass; ?>"><?php echo $dayChange >= 0 ? '+' : ''; ?><?php echo $dayChange; ?>%</td>
                                <td class="<?php echo $monthChangeClass; ?>"><?php echo $monthChange >= 0 ? '+' : ''; ?><?php echo $monthChange; ?>%</td>
                                <td class="<?php echo $yearChangeClass; ?>"><?php echo $yearChange >= 0 ? '+' : ''; ?><?php echo $yearChange; ?>%</td>
                                <?php if ($first_row): ?>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['data_source']); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php
                                    $first_row = false;
                                endforeach;
                                endforeach;
                            } else {
                                echo '<tr><td colspan="14" style="text-align: center; padding: 20px;">No market prices data found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div id="chart-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4>Market Price Trends</h4>
                            <p class="text-muted">Visual representation of price movements</p>
                        </div>
                        <div>
                            <select id="chart-type-selector" class="form-select" style="width: 200px; display: inline-block;">
                                <option value="line">Line Chart</option>
                                <option value="bar">Bar Chart</option>
                                <option value="combo">Combined View</option>
                            </select>
                            <button id="export-chart-btn" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="chart-filters">
                        <div>
                            <label for="country-filter" class="form-label">Country</label>
                            <select id="country-filter" class="form-select">
                                <option value="all">All Countries</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>"><?php echo htmlspecialchars($country); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="market-filter" class="form-label">Market</label>
                            <select id="market-filter" class="form-select">
                                <option value="all">All Markets</option>
                                <?php foreach ($markets as $market): ?>
                                    <option value="<?php echo htmlspecialchars($market); ?>"><?php echo htmlspecialchars($market); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="commodity-filter" class="form-label">Commodity</label>
                            <select id="commodity-filter" class="form-select">
                                <option value="all">All Commodities</option>
                                <?php foreach ($commodities as $id => $name): ?>
                                    <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="chart-container" style="position: relative; height:400px;">
                                <canvas id="price-trend-chart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Price Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div id="price-summary">
                                        <p>Select a data point to view details</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="map-view" style="display: none; padding: 20px;">
                    <h4>Map View</h4>
                    <p>This is where the map would be displayed</p>
                </div>

                <?php if ($total_records > 0): ?>
                <div class="pagination">
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            onclick="window.location.href='?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>'"
                            <?php echo $page <= 1 ? 'disabled' : ''; ?>
                            style="<?php echo $page <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                        >
                            Previous
                        </button>
                        
                        <?php
                        $visiblePages = 5;
                        $startPage = max(1, $page - floor($visiblePages / 2));
                        $endPage = min($total_pages, $startPage + $visiblePages - 1);
                        
                        if ($startPage > 1) {
                            echo '<button onclick="window.location.href=\'?' . http_build_query(array_merge($filters, ['page' => 1])) . '\'" class="page">1</button>';
                            if ($startPage > 2) {
                                echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = $i == $page ? 'current' : '';
                            echo '<button onclick="window.location.href=\'?' . http_build_query(array_merge($filters, ['page' => $i])) . '\'" class="page '.$activeClass.'">'.$i.'</button>';
                        }
                        
                        if ($endPage < $total_pages) {
                            if ($endPage < $total_pages - 1) {
                                echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                            }
                            echo '<button onclick="window.location.href=\'?' . http_build_query(array_merge($filters, ['page' => $total_pages])) . '\'" class="page">'.$total_pages.'</button>';
                        }
                        ?>
                        
                        <button
                            onclick="window.location.href='?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1])); ?>'"
                            <?php echo $page >= $total_pages ? 'disabled' : ''; ?>
                            style="<?php echo $page >= $total_pages ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                        >
                            Next
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
// Initialize charts
let priceTrendChart;
let currentChartType = 'line';

// Function to initialize or update charts
function initCharts(data) {
    // Process data for charts
    const processedData = processChartData(data);
    
    // Destroy existing chart if it exists
    if (priceTrendChart) priceTrendChart.destroy();
    
    // Create Price Trend Chart
    const priceTrendCtx = document.getElementById('price-trend-chart').getContext('2d');
    priceTrendChart = new Chart(priceTrendCtx, {
        type: currentChartType,
        data: {
            labels: processedData.dates,
            datasets: processedData.trendDatasets
        },
        options: getTrendChartOptions()
    });
}

// Process data for charts
function processChartData(data) {
    // Convert PHP data to proper format if needed
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            console.error('Error parsing chart data:', e);
            data = [];
        }
    }
    
    // Filter data based on selected filters
    const selectedCountry = document.getElementById('country-filter').value;
    const selectedMarket = document.getElementById('market-filter').value;
    const selectedCommodity = document.getElementById('commodity-filter').value;
    
    let filteredData = data;
    
    if (selectedCountry && selectedCountry !== 'all') {
        filteredData = filteredData.filter(item => item.country_admin_0 === selectedCountry);
    }
    
    if (selectedMarket && selectedMarket !== 'all') {
        filteredData = filteredData.filter(item => item.market === selectedMarket);
    }
    
    if (selectedCommodity && selectedCommodity !== 'all') {
        filteredData = filteredData.filter(item => item.commodity_name === selectedCommodity);
    }
    
    // Group data by date (without time) for trend chart
    const dates = [...new Set(filteredData.map(item => {
        const date = new Date(item.date_posted);
        return date.toISOString().split('T')[0]; // Get just the date part
    }))].sort();
    
    // Prepare trend datasets
    const trendDatasets = [];
    
    // If a specific commodity is selected, show only that
    if (selectedCommodity && selectedCommodity !== 'all') {
        const prices = dates.map(date => {
            const item = filteredData.find(d => 
                d.commodity_name === selectedCommodity && 
                new Date(d.date_posted).toISOString().split('T')[0] === date
            );
            return item ? parseFloat(item.Price) : null;
        });
        
        trendDatasets.push({
            label: selectedCommodity,
            data: prices,
            borderColor: '#8B4513', // Use theme color
            backgroundColor: 'rgba(139, 69, 19, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.1
        });
    } else {
        // Group by commodity if no specific one is selected
        const commodities = [...new Set(filteredData.map(item => item.commodity_name))];
        
        commodities.forEach(commodity => {
            const prices = dates.map(date => {
                const item = filteredData.find(d => 
                    d.commodity_name === commodity && 
                    new Date(d.date_posted).toISOString().split('T')[0] === date
                );
                return item ? parseFloat(item.Price) : null;
            });
            
            trendDatasets.push({
                label: commodity,
                data: prices,
                borderColor: getRandomColor(),
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

// Chart options
function getTrendChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Price Trends Over Time'
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: $${context.parsed.y.toFixed(2)}`;
                    }
                }
            },
            zoom: {
                zoom: {
                    wheel: {
                        enabled: true
                    },
                    pinch: {
                        enabled: true
                    },
                    mode: 'xy'
                },
                pan: {
                    enabled: true,
                    mode: 'xy'
                }
            },
            legend: {
                position: 'top',
                onClick: (e, legendItem, legend) => {
                    const index = legendItem.datasetIndex;
                    const ci = legend.chart;
                    const meta = ci.getDatasetMeta(index);
                    
                    meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                    ci.update();
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Date'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Price (USD)'
                }
            }
        },
        onClick: (e) => {
            const points = priceTrendChart.getElementsAtEventForMode(
                e, 'nearest', { intersect: true }, true
            );
            
            if (points.length) {
                const firstPoint = points[0];
                const dataset = priceTrendChart.data.datasets[firstPoint.datasetIndex];
                const value = dataset.data[firstPoint.index];
                const date = priceTrendChart.data.labels[firstPoint.index];
                
                updatePriceSummary(dataset.label, date, value);
            }
        }
    };
}

// Helper function to generate random colors
function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}

// Update price summary
function updatePriceSummary(commodity, date, price) {
    const summaryDiv = document.getElementById('price-summary');
    const selectedMarket = document.getElementById('market-filter').value;
    const selectedCountry = document.getElementById('country-filter').value;
    
    summaryDiv.innerHTML = `
        <h6>${commodity}</h6>
        <p><strong>Date:</strong> ${new Date(date).toLocaleDateString()}</p>
        <p><strong>Price:</strong> $${price.toFixed(2)}</p>
        <p><strong>Market:</strong> ${selectedMarket !== 'all' ? selectedMarket : 'All Markets'}</p>
        <p><strong>Country:</strong> ${selectedCountry !== 'all' ? selectedCountry : 'All Countries'}</p>
    `;
}

// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    // Set up tab switching
    const tabs = document.querySelectorAll('.view-tab');
    const views = {
        'table': document.getElementById('table-view'),
        'chart': document.getElementById('chart-view'),
        'map': document.getElementById('map-view')
    };
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all views
            Object.values(views).forEach(v => v.style.display = 'none');
            
            // Show selected view
            if (views[view]) {
                views[view].style.display = 'block';
                
                // Initialize charts if chart view is selected
                if (view === 'chart') {
                    const chartData = <?php echo json_encode($prices_data); ?>;
                    initCharts(chartData);
                }
            }
        });
    });

    // Chart type selector
    document.getElementById('chart-type-selector')?.addEventListener('change', function() {
        currentChartType = this.value;
        if (priceTrendChart) {
            priceTrendChart.config.type = currentChartType;
            priceTrendChart.update();
        }
    });

    // Export chart button
    document.getElementById('export-chart-btn')?.addEventListener('click', function() {
        if (priceTrendChart) {
            const link = document.createElement('a');
            link.download = 'market-prices-chart.png';
            link.href = priceTrendChart.toBase64Image();
            link.click();
        }
    });

    // Filter change event listeners
    document.getElementById('country-filter')?.addEventListener('change', updateCharts);
    document.getElementById('market-filter')?.addEventListener('change', updateCharts);
    document.getElementById('commodity-filter')?.addEventListener('change', updateCharts);

    function updateCharts() {
        const chartData = <?php echo json_encode($prices_data); ?>;
        initCharts(chartData);
    }

    // Commodity category buttons
    const categoryButtons = document.querySelectorAll('.commodity-category-btn');
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Update active state
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Add hidden input for category filter
            let categoryInput = document.querySelector('input[name="commodity_category"]');
            if (!categoryInput) {
                categoryInput = document.createElement('input');
                categoryInput.type = 'hidden';
                categoryInput.name = 'commodity_category';
                document.getElementById('filter-form').appendChild(categoryInput);
            }
            categoryInput.value = category;
            
            // Submit the form
            document.getElementById('filter-form').submit();
        });
    });

    // Reset filters button
    document.getElementById('reset-filters')?.addEventListener('click', function() {
        // Clear all form inputs
        const form = document.getElementById('filter-form');
        const inputs = form.querySelectorAll('select, input');
        inputs.forEach(input => {
            if (input.type !== 'submit' && input.type !== 'button') {
                input.value = '';
            }
        });
        
        // Submit the form
        form.submit();
    });

    // Download button functionality
    document.getElementById('download-btn')?.addEventListener('click', function() {
        // Create a form to submit download request
        const downloadForm = document.createElement('form');
        downloadForm.method = 'POST';
        downloadForm.action = 'download_market_prices.php';
        downloadForm.target = '_blank';
        
        // Add all current filters as hidden inputs
        const filterForm = document.getElementById('filter-form');
        const inputs = filterForm.querySelectorAll('select, input');
        inputs.forEach(input => {
            if (input.name && input.value) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = input.name;
                hiddenInput.value = input.value;
                downloadForm.appendChild(hiddenInput);
            }
        });
        
        // Add page information
        const pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        pageInput.value = '<?php echo $page; ?>';
        downloadForm.appendChild(pageInput);
        
        // Add to document and submit
        document.body.appendChild(downloadForm);
        downloadForm.submit();
        document.body.removeChild(downloadForm);
    });
});
</script>
</body>
</html>