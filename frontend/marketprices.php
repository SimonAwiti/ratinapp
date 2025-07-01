<?php
// Include your database configuration file
include '../admin/includes/config.php';

// Function to fetch prices data from the database
function getPricesData($con, $limit = 10, $offset = 0) {
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
                p.supply_status
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id
            ORDER BY
                p.date_posted DESC
            LIMIT $limit OFFSET $offset";

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

function getTotalPriceRecords($con){
    $sql = "SELECT count(*) as total FROM market_prices";
    $result = $con->query($sql);
     if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
     }
     return 0;
}

// Get total number of records
$total_records = getTotalPriceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch prices data
$prices_data = getPricesData($con, $limit, $offset);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Function to get status display
function getSupplyStatusDisplay($supplyStatus) {
    switch ($supplyStatus) {
        case 'good':
            return '<span class="status-dot status-approved"></span> Good';
        case 'moderate':
            return '<span class="status-dot status-pending"></span> Moderate';
        case 'low':
            return '<span class="status-dot status-unpublished"></span> Low';
        case 'critical':
            return '<span class="status-dot status-critical"></span> Critical';
        default:
            return '<span class="status-dot"></span> Unknown';
    }
}

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
    $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'] . '_' . $price['data_source'] . '_' . $price['supply_status'];
    $grouped_data[$group_key][] = $price;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATIN - Market Prices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* General Body and Container */
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
            max-width: 150px; /* Adjust as needed */
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

        .sidebar .nav-link i.fa-chevron-down {
            margin-right: 0;
            width: auto;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #f5d6c6;
            color: #8B4513;
        }

        .sidebar .submenu {
            padding-left: 10px;
            display: none;
        }

        .rotate {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
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

        .user-display i {
            font-size: 1.2em;
            color: #6c757d;
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
        .toolbar .approve {
            background-color: #218838;
            color: white;
        }
        .toolbar .approve:hover {
            background-color: #1a732f;
        }
        .toolbar .unpublish {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .toolbar .unpublish:hover {
            background-color: rgba(160, 70, 40, 1);
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table th, table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: top;
        }
        table th {
            background-color: #f1f1f1;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: #666;
        }
        table tr:nth-child(even) {
            background-color: #fafafa;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }

        /* Checkbox styling */
        .checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
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

        /* Status Dots (Supply Status) */
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-approved {
            background-color: #28a745;
        }
        .status-pending {
            background-color: #ffc107;
        }
        .status-unpublished {
            background-color: #6c757d;
        }
        .status-critical {
            background-color: #dc3545;
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
            <a href="#" class="nav-link">
                <i class="fas fa-industry"></i> Miller Prices
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-exchange-alt"></i> XBT Volumes
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-money-bill-wave"></i> Currency Rates
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
            <div class="filter-section">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Country/District</label>
                        <select style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            <option>Select Country</option>
                            <option>Kenya</option>
                            <option>Uganda</option>
                            <option>Rwanda</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Market</label>
                        <select style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            <option>Select Market</option>
                            <option>Nyamakima</option>
                            <option>Eldoret</option>
                            <option>Kampala</option>
                            <option>Kimironko</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Commodity</label>
                        <select style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            <option>Select Commodity</option>
                            <option>Maize (White)</option>
                            <option>Beans (Yellow)</option>
                            <option>Millet (Pearl)</option>
                            <option>Rice (Kigori)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Price type</label>
                        <select style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            <option>All Types</option>
                            <option>Wholesale</option>
                            <option>Retail</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Data Source</label>
                        <select style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            <option>All Sources</option>
                            <option>EAGC RATIN</option>
                            <option>MoALD Kenya</option>
                            <option>MoA/Esoko RW</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Date Range</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="date" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            <span style="color: #666;">to</span>
                            <input type="date" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Market Prices</label>
                        <input type="text" placeholder="Enter price range" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div style="display: flex; align-items: end;">
                        <button style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-weight: 500; color: #374151; background: white; cursor: pointer;">
                            <i class="fa fa-refresh"></i>
                            Reset filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="container">
                <div style="border-bottom: 1px solid #eee;">
                    <nav class="view-tabs">
                        <button class="view-tab active">
                            <i class="fa fa-table"></i>
                            Table view
                        </button>
                        <button class="view-tab">
                            <i class="fa fa-chart-bar"></i>
                            Chart view
                        </button>
                        <button class="view-tab">
                            <i class="fa fa-map"></i>
                            Map view
                        </button>
                    </nav>
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button style="padding: 8px 16px; background: #8B4513; color: white; font-size: 14px; font-weight: 500; border-radius: 6px; border: none; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-ellipsis-h"></i>
                            All
                        </button>
                        <button style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <i class="fa fa-seedling"></i>
                            Cereals
                        </button>
                        <button style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <i class="fa fa-tint"></i>
                            Oilseeds
                        </button>
                        <button style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <i class="fa fa-leaf"></i>
                            Pulses
                        </button>
                        <button style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            Currency
                            <i class="fa fa-chevron-down"></i>
                        </button>
                    </div>
                    <button style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        Download
                        <i class="fa fa-download"></i>
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" class="checkbox"></th>
                                <th>Markets</th>
                                <th>Country</th>
                                <th>Commodity</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Day Change(%)</th>
                                <th>Month Change(%)</th>
                                <th>Year Change(%)</th>
                                <th>Supply Status</th>
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
                                    <td rowspan="<?php echo count($prices_in_group); ?>">Kenya</td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['commodity_name']); ?></td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo date('d/m/Y', strtotime($price['date_posted'])); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($price['price_type']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($price['Price']); ?></td>
                                <td class="<?php echo $dayChangeClass; ?>"><?php echo $dayChange >= 0 ? '+' : ''; ?><?php echo $dayChange; ?>%</td>
                                <td class="<?php echo $monthChangeClass; ?>"><?php echo $monthChange >= 0 ? '+' : ''; ?><?php echo $monthChange; ?>%</td>
                                <td class="<?php echo $yearChangeClass; ?>"><?php echo $yearChange >= 0 ? '+' : ''; ?><?php echo $yearChange; ?>%</td>
                                <?php if ($first_row): ?>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo getSupplyStatusDisplay($price['supply_status']); ?></td>
                                    <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['data_source']); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php
                                    $first_row = false;
                                endforeach;
                                endforeach;
                            } else {
                                echo '<tr><td colspan="12" style="text-align: center; padding: 20px;">No market prices data found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
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
                            onclick="window.location.href='?page=<?php echo $page - 1; ?>'"
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
                            echo '<button onclick="window.location.href=\'?page=1\'" class="page">1</button>';
                            if ($startPage > 2) {
                                echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = $i == $page ? 'current' : '';
                            echo '<button onclick="window.location.href=\'?page='.$i.'\'" class="page '.$activeClass.'">'.$i.'</button>';
                        }
                        
                        if ($endPage < $total_pages) {
                            if ($endPage < $total_pages - 1) {
                                echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                            }
                            echo '<button onclick="window.location.href=\'?page='.$total_pages.'\'" class="page">'.$total_pages.'</button>';
                        }
                        ?>
                        
                        <button
                            onclick="window.location.href='?page=<?php echo $page + 1; ?>'"
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSubmenu(submenuId, element) {
        let submenu = document.getElementById(submenuId);
        let icon = element.querySelector("i.fa-chevron-down");
        
        if (submenu.style.display === "block") {
            submenu.style.display = "none";
            icon.classList.remove("rotate");
        } else {
            submenu.style.display = "block";
            icon.classList.add("rotate");
        }
        
        let parentContainer = element.closest('.submenu') || document.querySelector('.sidebar');
        parentContainer.querySelectorAll('.submenu').forEach(otherSubmenu => {
            if (otherSubmenu.id !== submenuId && otherSubmenu !== submenu.parentElement) {
                otherSubmenu.style.display = "none";
                let otherIcon = otherSubmenu.previousElementSibling?.querySelector("i.fa-chevron-down");
                if (otherIcon) otherIcon.classList.remove("rotate");
            }
        });
        
        return false;
    }
</script>
</body>
</html>