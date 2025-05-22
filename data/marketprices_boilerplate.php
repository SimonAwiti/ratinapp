<?php
// Include your database configuration file
include '../admin/includes/config.php';

// Function to fetch prices data from the database
function getPricesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                p.id,
                p.market,
                p.commodity, -- This is now the commodity ID from market_prices
                c.commodity_name, -- This will fetch the name from the commodities table
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id -- Correct join: p.commodity (ID) = c.id
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
        error_log("Error fetching prices data: " . $con->error); // Log error instead of echoing
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
//Get total number of records
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
        case 'unpublished': // Add this new status display
            return '<span class="status-dot status-unpublished"></span> Unpublished';
        default:
            return '<span class="status-dot"></span> Unknown';
    }
}

/**
 * Calculates the Day-over-Day (DoD) price change.
 *
 * @param float $currentPrice The current day's price.
 * @param int $commodityId The commodity ID (now correctly passed as an ID).
 * @param string $market The market.
 * @param string $priceType The price type (e.g., 'Wholesale', 'Retail').
 * @param mysqli $con The database connection.
 *
 * @return string The DoD change as a percentage (e.g., '2.04%') or 'N/A' if data is insufficient.
 */
function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $con) {
    // Get yesterday's date
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Query to fetch yesterday's price for the same commodity ID, market, and price type
    $sql = "SELECT Price FROM market_prices
            WHERE commodity = " . (int)$commodityId . " -- Use commodity ID from market_prices
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) = '$yesterday'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $yesterdayData = $result->fetch_assoc();
        $yesterdayPrice = $yesterdayData['Price'];
        if($yesterdayPrice != 0){
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2) . '%';
        }
        else{
            return 'N/A';
        }

    } else {
        return 'N/A'; // Not Available
    }
}



/**
 * Calculates the Day-over-Month (DoM) price change.
 *
 * @param float $currentPrice The current day's price.
 * @param int $commodityId The commodity ID (now correctly passed as an ID).
 * @param string $market The market.
 * @param string $priceType.
 * @param mysqli $con The database connection.
 *
 * @return string The DoM change as a percentage or 'N/A' if data is insufficient.
 */
function calculateDoMChange($currentPrice, $commodityId, $market, $priceType, $con) {
    // Get the date range for the previous month
    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));

    // Query to get the average price for the previous month using commodity ID
    $sql = "SELECT AVG(Price) as avg_price FROM market_prices
            WHERE commodity = " . (int)$commodityId . " -- Use commodity ID from market_prices
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) BETWEEN '$firstDayOfLastMonth' AND '$lastDayOfLastMonth'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $monthData = $result->fetch_assoc();
        $averagePrice = $monthData['avg_price'];
        if($averagePrice != 0){
             $change = (($currentPrice - $averagePrice) / $averagePrice) * 100;
             return round($change, 2) . '%';
        }
        else{
            return 'N/A';
        }


    } else {
        return 'N/A'; // Not Available
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Data Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h2 {
            margin: 0 0 5px;
        }
        p.subtitle {
            color: #777;
            font-size: 14px;
            margin: 0 0 20px;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .toolbar-left,
        .toolbar-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar button {
            padding: 12px 20px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background-color: #eee;
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
          background-color: rgba(180, 80, 50, 1); /* Keep this color, it's distinct from green approve */
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
        }
        table tr:nth-child(even) {
            background-color: #fafafa;
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
        .status-unpublished { /* New status color */
            background-color: grey;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
         .pagination {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pagination .pages {
            display: flex;
            gap: 5px;
        }
        .pagination .page {
            padding: 6px 10px;
            border-radius: 6px;
            background-color: #eee;
            cursor: pointer;
        }
        .pagination .current {
            background-color: #cddc39;
        }
        select {
            padding: 6px;
            margin-left: 5px;
        }

    </style>
</head>
<body>
    <div class="container">
        <h2>Data Management</h2>
        <p class="subtitle">Manage everything related to Market Prices</p>

        <div class="toolbar">
            <div class="toolbar-left">
                <a href="../data/add_marketprices.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New
                </a>
                <button class="delete-btn">
                    <i class="fa fa-trash" style="margin-right: 6px;"></i> Delete
                </button>
                <button>
                    <i class="fa fa-file-export" style="margin-right: 6px;"></i> Export
                </button>
                <button>
                    <i class="fa fa-filter" style="margin-right: 6px;"></i> Filters
                </button>
            </div>
            <div class="toolbar-right">
                <button class="approve">
                    <i class="fa fa-check-circle" style="margin-right: 6px;"></i> Approve
                </button>
                <button class="unpublish">
                    <i class="fa fa-ban" style="margin-right: 6px;"></i> Unpublish
                </button>
                <button class="primary">
                    <i class="fa fa-upload" style="margin-right: 6px;"></i> Publish
                </button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"/></th>
                    <th>Market</th>
                    <th>Commodity</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Price($)</th>
                    <th>Day Change(%)</th>
                    <th>Month Change(%)</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grouped_data = [];
                foreach ($prices_data as $price) {
                    $date = date('Y-m-d', strtotime($price['date_posted']));
                    // Group by commodity ID for consistency
                    $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'];
                    $grouped_data[$group_key][] = $price;
                }

                foreach ($grouped_data as $group_key => $prices_in_group):
                    $first_row = true;
                    // Collect all individual price IDs for this group
                    $group_price_ids = array_column($prices_in_group, 'id');
                    $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));

                    foreach($prices_in_group as $price):
                        // Pass 'commodity' (which is now the ID) to the functions
                        $day_change = calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                        $month_change = calculateDoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                    ?>
                    <tr>
                        <?php if ($first_row): ?>
                            <td rowspan="<?php echo count($prices_in_group); ?>">
                                <input type="checkbox"
                                       data-group-key="<?php echo $group_key; ?>"
                                       data-price-ids="<?php echo $group_price_ids_json; ?>"
                                />
                            </td>
                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['market']); ?></td>
                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['commodity_name']); ?></td> <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo date('Y-m-d', strtotime($price['date_posted'])); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($price['price_type']); ?></td>
                        <td><?php echo htmlspecialchars($price['Price']); ?></td>
                        <td><?php echo $day_change; ?></td>
                        <td><?php echo $month_change; ?></td>
                        <td><?php echo getStatusDisplay($price['status']); ?></td>
                        <td><?php echo htmlspecialchars($price['data_source']); ?></td>
                        <td>
                            <a href="../data/edit_marketprice.php?id=<?= $price['id'] ?>">
                                <button class="btn btn-sm btn-warning">
                                    <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
                                </button>
                            </a>
                        </td>
                    </tr>
                    <?php
                    $first_row = false;
                    endforeach;
                endforeach;
                ?>
            </tbody>
        </table>
       <div class="pagination">
            <div>
                Show
                <select>
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                </select>
                entries
            </div>
            <div>Displaying <?php echo ($offset + 1) . ' to ' . min($offset + $limit, $total_records) . ' of ' . $total_records; ?> items</div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="page">‹</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page <?php echo ($page == $i) ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="page">›</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
