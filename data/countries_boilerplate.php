<?php
// country_boilerplate.php
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Function to fetch countries data from the database with filters
function getCountriesData($con, $limit = 10, $offset = 0, $search_country = '', $search_currency = '') {
    $sql = "SELECT
                c.id,
                c.country_name,
                c.currency_code,
                c.date_created
            FROM
                countries c
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($search_country)) {
        $sql .= " AND c.country_name LIKE ?";
        $params[] = '%' . $search_country . '%';
    }
    
    if (!empty($search_currency)) {
        $sql .= " AND c.currency_code LIKE ?";
        $params[] = '%' . $search_currency . '%';
    }
    
    $sql .= " ORDER BY c.country_name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $con->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $types = str_repeat('s', count($params) - 2) . 'ii';
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $stmt->close();
        return $data;
    } else {
        error_log("Error fetching countries data: " . $con->error);
        return [];
    }
}

function getTotalCountryRecords($con, $search_country = '', $search_currency = '') {
    $sql = "SELECT count(*) as total FROM countries c WHERE 1=1";
    
    $params = [];
    
    if (!empty($search_country)) {
        $sql .= " AND c.country_name LIKE ?";
        $params[] = '%' . $search_country . '%';
    }
    
    if (!empty($search_currency)) {
        $sql .= " AND c.currency_code LIKE ?";
        $params[] = '%' . $search_currency . '%';
    }

    $stmt = $con->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['total'];
    }
    return 0;
}

// Handle delete action
if (isset($_POST['delete_selected']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    
    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    
    $delete_sql = "DELETE FROM countries WHERE id IN ($placeholders)";
    $stmt = $con->prepare($delete_sql);
    
    if ($stmt) {
        // Bind parameters
        $types = str_repeat('i', count($selected_ids));
        $stmt->bind_param($types, ...$selected_ids);
        
        if ($stmt->execute()) {
            $delete_message = "Successfully deleted " . $stmt->affected_rows . " country(ies).";
            $delete_status = 'success';
        } else {
            $delete_message = "Error deleting countries: " . $stmt->error;
            $delete_status = 'danger';
        }
        $stmt->close();
    }
}

// Get search filters
$search_country = isset($_GET['search_country']) ? $_GET['search_country'] : '';
$search_currency = isset($_GET['search_currency']) ? $_GET['search_currency'] : '';

// Get items per page
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of records with filters
$total_records = getTotalCountryRecords($con, $search_country, $search_currency);

// Fetch countries data with filters
$countries_data = getCountriesData($con, $limit, $offset, $search_country, $search_currency);

// Calculate total pages
$total_pages = ceil($total_records / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
            gap: 12px;
        }
        .toolbar-left {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a.button {
            padding: 12px 20px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background-color: #eee;
            text-decoration: none;
            color: #333;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .toolbar .primary {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .toolbar .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        .toolbar .delete-btn:hover {
            background-color: #c82333;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
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
        }
        table th {
            background-color: #f1f1f1;
        }
        table tr:nth-child(even) {
            background-color: #fafafa;
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
            text-decoration: none;
            color: #333;
        }
        .pagination .current {
            background-color: #cddc39;
        }
        select {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        .filter-input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .filter-input:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Countries Management</h2>
        <p class="subtitle">Manage Countries and Their Currencies</p>

        <?php if (isset($delete_message)): ?>
            <div class="alert alert-<?php echo $delete_status; ?>">
                <?php echo $delete_message; ?>
            </div>
        <?php endif; ?>

        <div class="toolbar">
            <div class="toolbar-left">
                <a href="../data/add_country.php" class="primary" style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; text-decoration: none; color: white;">
                    <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New Country
                </a>
                
                <button type="button" class="delete-btn" onclick="confirmDelete()">
                    <i class="fa fa-trash" style="margin-right: 6px;"></i> Delete
                </button>

                <form method="POST" action="export_current_page_countries.php" style="display: inline;">
                    <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                    <input type="hidden" name="offset" value="<?php echo $offset; ?>">
                    <input type="hidden" name="search_country" value="<?php echo htmlspecialchars($search_country); ?>">
                    <input type="hidden" name="search_currency" value="<?php echo htmlspecialchars($search_currency); ?>">
                    <button type="submit">
                        <i class="fa fa-file-export" style="margin-right: 6px;"></i> Export (Current Page)
                    </button>
                </form>

                <form method="POST" action="bulk_export_countries.php" style="display: inline;">
                    <input type="hidden" name="search_country" value="<?php echo htmlspecialchars($search_country); ?>">
                    <input type="hidden" name="search_currency" value="<?php echo htmlspecialchars($search_currency); ?>">
                    <button type="submit" style="background-color: #17a2b8; color: white;">
                        <i class="fa fa-download" style="margin-right: 6px;"></i> Bulk Export (All)
                    </button>
                </form>
            </div>
        </div>

        <form method="GET" action="" id="filterForm">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all"/>
                        </th>
                        <th>Country</th>
                        <th>Currency Code</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                    <!-- Filter Row -->
                    <tr>
                        <th></th>
                        <th>
                            <input type="text" 
                                   class="filter-input" 
                                   name="search_country" 
                                   placeholder="Search country..."
                                   value="<?php echo htmlspecialchars($search_country); ?>"
                                   onkeyup="this.form.submit()">
                        </th>
                        <th>
                            <input type="text" 
                                   class="filter-input" 
                                   name="search_currency" 
                                   placeholder="Search currency..."
                                   value="<?php echo htmlspecialchars($search_currency); ?>"
                                   onkeyup="this.form.submit()">
                        </th>
                        <th></th>
                        <th>
                            <?php if (!empty($search_country) || !empty($search_currency)): ?>
                                <a href="?" class="button" style="padding: 6px 12px; font-size: 12px; background-color: #6c757d; color: white;">
                                    <i class="fa fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($countries_data)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #666;">
                                No countries found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($countries_data as $country): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" 
                                           class="row-checkbox" 
                                           name="selected_ids[]" 
                                           value="<?php echo $country['id']; ?>">
                                </td>
                                <td>
                                    <img src="../base/img/flags/<?php echo strtolower($country['currency_code']); ?>.png" 
                                         alt="<?php echo htmlspecialchars($country['country_name']); ?>" 
                                         class="country-flag" 
                                         onerror="this.style.display='none'">
                                    <?php echo htmlspecialchars($country['country_name']); ?>
                                </td>
                                <td>
                                    <span class="currency-code">
                                        <?php echo htmlspecialchars($country['currency_code']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($country['date_created'])); ?></td>
                                <td>
                                    <a href="../data/edit_country.php?id=<?= $country['id'] ?>">
                                        <button type="button" style="background: none; border: none; cursor: pointer; padding: 4px;">
                                            <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px;">
                                        </button>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <div>
                    Show
                    <select name="limit" onchange="this.form.submit()">
                        <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                    entries
                </div>
                <div>
                    Displaying 
                    <?php 
                        $start = ($page - 1) * $limit + 1;
                        $end = min($page * $limit, $total_records);
                        echo "$start to $end of $total_records items";
                    ?>
                </div>
                <div class="pages">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search_country=<?php echo urlencode($search_country); ?>&search_currency=<?php echo urlencode($search_currency); ?>" class="page">‹</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search_country=<?php echo urlencode($search_country); ?>&search_currency=<?php echo urlencode($search_currency); ?>" 
                           class="page <?php echo ($page == $i) ? 'current' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search_country=<?php echo urlencode($search_country); ?>&search_currency=<?php echo urlencode($search_currency); ?>" class="page">›</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Hidden input to maintain page number -->
            <input type="hidden" name="page" value="<?php echo $page; ?>">
        </form>

        <!-- Delete form (separate from filter form) -->
        <form method="POST" action="" id="deleteForm" style="display: none;">
            <input type="hidden" name="delete_selected" value="1">
        </form>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select-all when individual checkboxes change
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = document.querySelectorAll('.row-checkbox:checked').length;
                const total = document.querySelectorAll('.row-checkbox').length;
                document.getElementById('select-all').checked = allChecked === total && total > 0;
            });
        });
    });

    function confirmDelete() {
        const selected = document.querySelectorAll('.row-checkbox:checked');
        if (selected.length === 0) {
            alert('Please select at least one country to delete.');
            return;
        }

        if (confirm(`Are you sure you want to delete ${selected.length} selected country(ies)?`)) {
            // Get the delete form
            const deleteForm = document.getElementById('deleteForm');
            
            // Remove any existing selected_ids inputs
            const existingInputs = deleteForm.querySelectorAll('input[name="selected_ids[]"]');
            existingInputs.forEach(input => input.remove());
            
            // Add selected IDs to the delete form
            selected.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = checkbox.value;
                deleteForm.appendChild(input);
            });
            
            // Submit the delete form
            deleteForm.submit();
        }
    }
    </script>
</body>
<?php include '../admin/includes/footer.php'; ?>
</html>