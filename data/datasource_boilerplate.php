<?php
// datasources_boilerplate.php
include '../admin/includes/config.php';

// Function to fetch data sources from the database
function getDataSourcesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT 
                id, 
                data_source_name, 
                countries_covered,
                DATE_FORMAT(created_at, '%Y-%m-%d') as created_date
            FROM 
                data_sources
            ORDER BY 
                data_source_name ASC
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
        error_log("Error fetching data sources: " . $con->error);
    }
    return $data;
}

function getTotalDataSourceRecords($con) {
    $sql = "SELECT count(*) as total FROM data_sources";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalDataSourceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch data sources data
$data_sources = getDataSourcesData($con, $limit, $offset);

// Calculate total pages
$total_pages = ceil($total_records / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Data Sources Management</title>
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
        .toolbar-left {
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
        .countries-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .country-tag {
            background-color: #e0e0e0;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Data Sources Management</h2>
        <p class="subtitle">Manage Data Sources and Their Coverage</p>

        <div class="toolbar">
            <div class="toolbar-left">
                <a href="../data/add_datasource.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
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
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"/></th>
                    <th>Data Source Name</th>
                    <th>Countries Covered</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data_sources as $source): ?>
                    <tr>
                        <td><input type="checkbox" data-id="<?php echo $source['id']; ?>"/></td>
                        <td><?php echo htmlspecialchars($source['data_source_name']); ?></td>
                        <td>
                            <div class="countries-list">
                                <?php 
                                $countries = explode(', ', $source['countries_covered']);
                                foreach ($countries as $country): 
                                ?>
                                    <span class="country-tag"><?php echo htmlspecialchars($country); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($source['created_date']); ?></td>
                        <td class="actions">
                            <a href="../data/edit_datasource.php?id=<?= $source['id'] ?>">
                                <button class="btn btn-sm btn-warning">
                                    <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px;">
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
    <script src="../base/assets/datasources.js"></script>
</body>
</html>