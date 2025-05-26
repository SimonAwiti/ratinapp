<?php
// country_boilerplate.php
include '../admin/includes/config.php';

// Function to fetch countries data from the database
function getCountriesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                c.id,
                c.country_name,
                c.currency_code,
                c.date_created
            FROM
                countries c
            ORDER BY
                c.country_name ASC
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
        error_log("Error fetching countries data: " . $con->error);
    }
    return $data;
}

function getTotalCountryRecords($con) {
    $sql = "SELECT count(*) as total FROM countries";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalCountryRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch countries data
$countries_data = getCountriesData($con, $limit, $offset);

// Calculate total pages
$total_pages = ceil($total_records / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Countries Management</title>
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
        .currency-code {
            font-weight: bold;
            color: #333;
        }
        .country-flag {
            width: 24px;
            height: 16px;
            margin-right: 8px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Countries Management</h2>
        <p class="subtitle">Manage Countries and Their Currencies</p>

        <div class="toolbar">
            <div class="toolbar-left">
                <a href="../data/add_country.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New Country
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
                    <th>Country</th>
                    <th>Currency Code</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($countries_data as $country): ?>
                    <tr>
                        <td><input type="checkbox" data-id="<?php echo $country['id']; ?>"/></td>
                        <td>
                            <img src="../base/img/flags/<?php echo strtolower($country['currency_code']); ?>.png" 
                                 alt="<?php echo htmlspecialchars($country['country_name']); ?>" 
                                 class="country-flag" 
                                 onerror="this.style.display='none'">
                            <?php echo htmlspecialchars($country['country_name']); ?>
                        </td>
                        <td><span class="currency-code"><?php echo htmlspecialchars($country['currency_code']); ?></span></td>
                        <td><?php echo date('Y-m-d', strtotime($country['date_created'])); ?></td>
                        <td>
                            <a href="../data/edit_country.php?id=<?= $country['id'] ?>">
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
    <script>
        // Basic functionality for select all checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Delete functionality would go here
        document.querySelector('.delete-btn').addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('tbody input[type="checkbox"]:checked'))
                                    .map(checkbox => checkbox.dataset.id);
            
            if (selectedIds.length === 0) {
                alert('Please select at least one country to delete.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedIds.length} selected countries?`)) {
                // AJAX call to delete would go here
                console.log('Countries to delete:', selectedIds);
                // After successful deletion, you might want to reload the page
                // window.location.reload();
            }
        });
    </script>
</body>
</html>