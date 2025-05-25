<?php
// Include your database configuration file
include '../admin/includes/config.php';

// Function to fetch miller prices data from the database
function getMillerPricesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                mp.id,
                mp.town,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                mp.price_usd,
                mp.day_change,
                mp.month_change,
                mp.date_posted,
                mp.status,
                ds.data_source_name AS data_source
            FROM
                miller_prices mp
            LEFT JOIN
                commodities c ON mp.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON mp.data_source_id = ds.id
            ORDER BY
                mp.date_posted DESC
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
        error_log("Error fetching miller prices data: " . $con->error);
    }
    return $data;
}

function getTotalMillerPriceRecords($con) {
    $sql = "SELECT count(*) as total FROM miller_prices";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalMillerPriceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch miller prices data
$miller_prices_data = getMillerPricesData($con, $limit, $offset);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Miller Prices Management</title>
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
        .status-unpublished {
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
        .positive-change {
            color: green;
        }
        .negative-change {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Miller Prices Management</h2>
        <p class="subtitle">Manage Miller Price Data</p>

        <div class="toolbar">
            <div class="toolbar-left">
                <a href="../data/add_miller_prices.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
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
                    <th>Town</th>
                    <th>Commodity</th>
                    <th>Price</th>
                    <th>Day Change %</th>
                    <th>Month Change %</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Data Source</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($miller_prices_data as $price): ?>
                    <tr>
                        <td><input type="checkbox" data-id="<?php echo $price['id']; ?>"/></td>
                        <td><?php echo htmlspecialchars($price['town']); ?></td>
                        <td><?php echo htmlspecialchars($price['commodity_display']); ?></td>
                        <td><?php echo htmlspecialchars($price['price_usd']); ?></td>
                        <td class="<?php echo ($price['day_change'] > 0) ? 'positive-change' : 'negative-change'; ?>">
                            <?php echo ($price['day_change'] !== null) ? htmlspecialchars($price['day_change']) . '%' : 'N/A'; ?>
                        </td>
                        <td class="<?php echo ($price['month_change'] > 0) ? 'positive-change' : 'negative-change'; ?>">
                            <?php echo ($price['month_change'] !== null) ? htmlspecialchars($price['month_change']) . '%' : 'N/A'; ?>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($price['date_posted'])); ?></td>
                        <td><?php echo getStatusDisplay($price['status']); ?></td>
                        <td><?php echo htmlspecialchars($price['data_source']); ?></td>
                        <td>
                            <a href="../data/edit_miller_price.php?id=<?= $price['id'] ?>">
                                <button class="btn btn-sm btn-warning">
                                    <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
                                </button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
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
    <script src="../base/assets/miller_prices.js"></script>
</body>
</html>