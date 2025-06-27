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
                p.data_source
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
function getStatusDisplay($status) {
    switch ($status) {
        case 'pending':
            return '<span class="status-dot status-pending"></span> Pending';
        case 'published':
            return '<span class="status-dot status-published"></span> Published';
        case 'approved':
            return '<span class="status-dot status-approved"></span> Approved';
        case 'unpublished':
            return '<span class="status-dot status-unpublished"></span> Unpublished';
        default:
            return '<span class="status-dot"></span> Unknown';
    }
}

// Function to calculate price changes
function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $con) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATIN - Market Prices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .sidebar-item.active {
            background-color: #2d7d32;
            color: white;
        }
        
        .sidebar-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #1b5e20;
        }
        
        .view-tab.active {
            color: #2d7d32;
            border-bottom-color: #2d7d32;
        }
        
        .filter-tab.active {
            background: #2d7d32;
            color: white;
            border-color: #2d7d32;
        }
        
        .change-positive {
            color: #059669;
        }
        
        .change-negative {
            color: #dc2626;
        }
        
        .table-row-even {
            background: #f9fafb;
        }
        
        .checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-pending {
            background-color: orange;
        }
        .status-published {
            background-color: blue;
        }
        .status-approved {
            background-color: green;
        }
        .status-unpublished {
            background-color: grey;
        }

        .container {
            background: #fff;
            margin: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
        }
        .toolbar-left,
        .toolbar-right {
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
        }
        .toolbar .primary {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .toolbar .approve {
          background-color: #218838;
          color: white;
        }
        .toolbar .unpublish {
          background-color: rgba(180, 80, 50, 1);
          color: white;
        }

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

        .pagination {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            font-size: 14px;
            align-items: center;
            flex-wrap: wrap;
            border-top: 1px solid #eee;
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
        }
        .pagination .current {
            background-color: #2d7d32;
            color: white;
        }
        .pagination .page:hover:not(.current) {
            background-color: #ddd;
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header style="position: fixed; top: 0; left: 0; right: 0; background: white; border-bottom: 1px solid #eee; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between; z-index: 50;">
        <div style="display: flex; align-items: center; gap: 24px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 40px; height: 40px; background: #2d7d32; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">RATIN</div>
            </div>
            <div>
                <h1 style="font-size: 18px; font-weight: 600; color: #111; margin: 0;">Market Prices</h1>
                <p style="font-size: 14px; color: #666; margin: 0;">Price parity for market prices</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <button style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #666; background: none; border: none; cursor: pointer;">
                <i class="fa fa-bell" style="font-size: 16px;"></i>
            </button>
            <div style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <div style="width: 32px; height: 32px; background: #2d7d32; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">MK</div>
                <div style="font-size: 14px;">
                    <div style="font-weight: 500; color: #111;">Martin Kim</div>
                    <div style="color: #666;">User</div>
                </div>
                <i class="fa fa-chevron-down" style="color: #666; font-size: 12px;"></i>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav style="position: fixed; left: 0; top: 64px; bottom: 0; width: 192px; background: white; border-right: 1px solid #eee; overflow-y: auto; padding-top: 16px;">
        <div style="margin-bottom: 16px;">
            <div style="padding: 8px 16px; font-size: 12px; font-weight: 500; color: #666; text-transform: uppercase; letter-spacing: 0.05em;">Price Parity</div>
            <a href="#" style="position: relative; display: flex; align-items: center; gap: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; color: white; background: #2d7d32; text-decoration: none;">
                <i class="fa fa-dollar-sign" style="font-size: 16px;"></i>
                Market Prices
            </a>
            <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; color: #666; text-decoration: none;">
                <i class="fa fa-industry" style="font-size: 16px;"></i>
                Miller Prices
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main style="margin-left: 192px; margin-top: 64px; padding: 24px;">
        <!-- Filters -->
        <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 24px;">
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

        <!-- Content Area -->
        <div class="container">
            <!-- View Tabs -->
            <div style="border-bottom: 1px solid #eee;">
                <nav style="display: flex;">
                    <button style="display: flex; align-items: center; gap: 8px; padding: 16px 24px; border-bottom: 2px solid #2d7d32; font-weight: 500; font-size: 14px; color: #2d7d32; background: none; border-left: none; border-right: none; border-top: none;">
                        <i class="fa fa-table"></i>
                        Table view
                    </button>
                    <button style="display: flex; align-items: center; gap: 8px; padding: 16px 24px; border-bottom: 2px solid transparent; font-weight: 500; font-size: 14px; color: #666; background: none; border: none; cursor: pointer;">
                        <i class="fa fa-chart-bar"></i>
                        Chart view
                    </button>
                    <button style="display: flex; align-items: center; gap: 8px; padding: 16px 24px; border-bottom: 2px solid transparent; font-weight: 500; font-size: 14px; color: #666; background: none; border: none; cursor: pointer;">
                        <i class="fa fa-map"></i>
                        Map view
                    </button>
                </nav>
            </div>

            <!-- Filter Tabs -->
            <div style="padding: 16px 24px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button style="padding: 8px 16px; background: #2d7d32; color: white; font-size: 14px; font-weight: 500; border-radius: 6px; border: none; display: flex; align-items: center; gap: 8px;">
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

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <a href="../data/add_marketprices.php" class="primary">
                        <i class="fa fa-plus"></i> Add New
                    </a>
                    <button class="delete-btn">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                    <button>
                        <i class="fa fa-file-export"></i> Export
                    </button>
                    <button>
                        <i class="fa fa-filter"></i> Filters
                    </button>
                </div>
                <div class="toolbar-right">
                    <button class="approve">
                        <i class="fa fa-check-circle"></i> Approve
                    </button>
                    <button class="unpublish">
                        <i class="fa fa-ban"></i> Unpublish
                    </button>
                    <button class="primary">
                        <i class="fa fa-upload"></i> Publish
                    </button>
                </div>
            </div>

            <!-- Table -->
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
                            <th>Status</th>
                            <th>Data Source</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
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
                                <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo getStatusDisplay($price['status']); ?></td>
                                <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['data_source']); ?></td>
                                <td rowspan="<?php echo count($prices_in_group); ?>">
                                    <a href="../data/edit_marketprice.php?id=<?= $price['id'] ?>" style="text-decoration: none;">
                                        <button style="background: #fbbf24; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-edit" style="font-size: 14px;"></i>
                                        </button>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php
                        $first_row = false;
                        endforeach;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        onclick="window.location.href='?page=<?php echo $page - 1; ?>'" 
                        class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                        <?php echo $page <= 1 ? 'disabled' : ''; ?>
                    >
                        Previous
                    </button>
                    
                    <?php
                    $visiblePages = 5;
                    $startPage = max(1, $page - floor($visiblePages / 2));
                    $endPage = min($total_pages, $startPage + $visiblePages - 1);
                    
                    if ($startPage > 1) {
                        echo '<button onclick="window.location.href=\'?page=1\'" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">1</button>';
                        if ($startPage > 2) {
                            echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = $i == $page ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-700 hover:bg-gray-50';
                        echo '<button onclick="window.location.href=\'?page='.$i.'\'" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium '.$activeClass.'">'.$i.'</button>';
                    }
                    
                    if ($endPage < $total_pages) {
                        if ($endPage < $total_pages - 1) {
                            echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                        }
                        echo '<button onclick="window.location.href=\'?page='.$total_pages.'\'" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">'.$total_pages.'</button>';
                    }
                    ?>
                    
                    <button 
                        onclick="window.location.href='?page=<?php echo $page + 1; ?>'" 
                        class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 <?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                        <?php echo $page >= $total_pages ? 'disabled' : ''; ?>
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>