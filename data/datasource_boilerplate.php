<?php
// datasources_boilerplate.php
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Function to fetch data sources from the database with filters
function getDataSourcesData($con, $limit = 10, $offset = 0, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters if provided
    if (!empty($filters['name'])) {
        $where_clauses[] = "data_source_name LIKE ?";
        $params[] = '%' . $filters['name'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['country'])) {
        $where_clauses[] = "countries_covered LIKE ?";
        $params[] = '%' . $filters['country'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(created_at) = ?";
        $params[] = $filters['date'];
        $types .= 's';
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "SELECT 
                id, 
                data_source_name, 
                countries_covered,
                DATE_FORMAT(created_at, '%Y-%m-%d') as created_date
            FROM 
                data_sources
            $where_sql
            ORDER BY 
                data_source_name ASC
            LIMIT $limit OFFSET $offset";

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing statement: " . $con->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $result->free();
    }
    $stmt->close();
    
    return $data;
}

function getTotalDataSourceRecords($con, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters if provided
    if (!empty($filters['name'])) {
        $where_clauses[] = "data_source_name LIKE ?";
        $params[] = '%' . $filters['name'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['country'])) {
        $where_clauses[] = "countries_covered LIKE ?";
        $params[] = '%' . $filters['country'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(created_at) = ?";
        $params[] = $filters['date'];
        $types .= 's';
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "SELECT count(*) as total FROM data_sources $where_sql";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing count statement: " . $con->error);
        return 0;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $total = 0;
    
    if ($result) {
        $row = $result->fetch_assoc();
        $total = $row['total'];
    }
    $stmt->close();
    
    return $total;
}

// Get filter values from GET parameters
$filters = [
    'name' => isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '',
    'country' => isset($_GET['filter_country']) ? trim($_GET['filter_country']) : '',
    'date' => isset($_GET['filter_date']) ? trim($_GET['filter_date']) : ''
];

// Get total number of records with filters
$total_records = getTotalDataSourceRecords($con, $filters);

// Set pagination parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch data sources data with filters
$data_sources = getDataSourcesData($con, $limit, $offset, $filters);

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
            text-decoration: none;
            color: #333;
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
        
        /* Filter Row Styles */
        .filter-row th {
            background-color: white;
            padding: 8px;
        }
        .filter-input {
            width: 100%;
            border: 1px solid #e5e7eb;
            background: white;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 13px;
        }
        .filter-input:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 1);
            box-shadow: 0 0 0 2px rgba(180, 80, 50, 0.1);
        }
        
        /* Button Styles */
        .btn-add-new {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 52px;
        }
        .btn-add-new:hover {
            background-color: darkred;
        }
        .btn-delete, .btn-export, .btn-filter {
            background-color: white;
            color: black;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }
        .btn-delete:hover, .btn-export:hover, .btn-filter:hover {
            background-color: #f8f9fa;
        }
        
        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1000;
            border-radius: 4px;
            padding: 5px 0;
        }
        .dropdown-menu.show {
            display: block;
        }
        .dropdown-item {
            padding: 8px 16px;
            text-decoration: none;
            display: block;
            color: #333;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .dropdown-divider {
            height: 1px;
            margin: 5px 0;
            background-color: #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Data Sources Management</h2>
        <p class="subtitle">Manage Data Sources and Their Coverage</p>

        <div class="toolbar">
            <div class="toolbar-left">
                <a href="../data/add_datasource.php" class="btn-add-new">
                    <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New
                </a>
                <button class="btn-delete" onclick="deleteSelected()">
                    <i class="fa fa-trash" style="margin-right: 6px;"></i> Delete
                </button>
                
                <div class="dropdown">
                    <button class="btn-export dropdown-toggle" type="button" onclick="toggleExportDropdown()">
                        <i class="fa fa-file-export" style="margin-right: 6px;"></i> Export
                    </button>
                    <div class="dropdown-menu" id="exportDropdown">
                        <a class="dropdown-item" href="#" onclick="exportSelected('excel')">
                            <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export Selected (Excel)
                        </a>
                        <a class="dropdown-item" href="#" onclick="exportSelected('csv')">
                            <i class="fas fa-file-csv" style="margin-right: 8px;"></i>Export Selected (CSV)
                        </a>
                        <a class="dropdown-item" href="#" onclick="exportSelected('pdf')">
                            <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export Selected (PDF)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="exportAll('excel')">
                            <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export All (Excel)
                        </a>
                        <a class="dropdown-item" href="#" onclick="exportAll('csv')">
                            <i class="fas fa-file-csv" style="margin-right: 8px;"></i>Export All (CSV)
                        </a>
                        <a class="dropdown-item" href="#" onclick="exportAll('pdf')">
                            <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export All (PDF)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="exportAllWithFilters('excel')">
                            <i class="fas fa-filter" style="margin-right: 8px;"></i>Export Filtered (Excel)
                        </a>
                        <a class="dropdown-item" href="#" onclick="exportAllWithFilters('csv')">
                            <i class="fas fa-filter" style="margin-right: 8px;"></i>Export Filtered (CSV)
                        </a>
                        <a class="dropdown-item" href="#" onclick="exportAllWithFilters('pdf')">
                            <i class="fas fa-filter" style="margin-right: 8px;"></i>Export Filtered (PDF)
                        </a>
                    </div>
                </div>
                
                <button class="btn-filter" onclick="clearAllFilters()">
                    <i class="fa fa-filter" style="margin-right: 6px;"></i> Clear Filters
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
                <tr class="filter-row">
                    <th></th>
                    <th>
                        <input type="text" class="filter-input" id="filterName" 
                               placeholder="Filter by name" 
                               value="<?php echo htmlspecialchars($filters['name']); ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterCountry" 
                               placeholder="Filter by country" 
                               value="<?php echo htmlspecialchars($filters['country']); ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="date" class="filter-input" id="filterDate" 
                               value="<?php echo htmlspecialchars($filters['date']); ?>"
                               onchange="applyFilters()">
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="dataSourcesTable">
                <?php foreach ($data_sources as $source): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" data-id="<?php echo $source['id']; ?>"/></td>
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
                <select id="itemsPerPage" onchange="updateItemsPerPage(this.value)">
                    <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                </select>
                entries
            </div>
            <div>Displaying <?php echo ($offset + 1) . ' to ' . min($offset + $limit, $total_records) . ' of ' . $total_records; ?> items</div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?><?php echo getFilterParams($filters); ?>" class="page">‹</a>
                <?php endif; ?>

                <?php 
                // Calculate pagination range
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                // Show first page if not in range
                if ($start_page > 1) {
                    echo '<a href="?page=1&limit=' . $limit . getFilterParams($filters) . '" class="page">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="page" style="background: none; cursor: default;">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?><?php echo getFilterParams($filters); ?>" 
                       class="page <?php echo ($page == $i) ? 'current' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; 
                
                // Show last page if not in range
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="page" style="background: none; cursor: default;">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '&limit=' . $limit . getFilterParams($filters) . '" class="page">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?><?php echo getFilterParams($filters); ?>" class="page">›</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize select all checkbox
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const exportDropdown = document.getElementById('exportDropdown');
            const exportButton = document.querySelector('.btn-export');
            
            if (!exportButton.contains(event.target) && !exportDropdown.contains(event.target)) {
                exportDropdown.classList.remove('show');
            }
        });
    });

    function toggleExportDropdown() {
        const dropdown = document.getElementById('exportDropdown');
        dropdown.classList.toggle('show');
    }

    function applyFilters() {
        const filters = {
            name: document.getElementById('filterName').value,
            country: document.getElementById('filterCountry').value,
            date: document.getElementById('filterDate').value
        };
        
        // Build URL with filters
        const url = new URL(window.location.href.split('?')[0], window.location.origin);
        url.searchParams.set('page', '1');
        
        if (filters.name) url.searchParams.set('filter_name', filters.name);
        if (filters.country) url.searchParams.set('filter_country', filters.country);
        if (filters.date) url.searchParams.set('filter_date', filters.date);
        
        // Keep current limit
        const currentLimit = new URLSearchParams(window.location.search).get('limit');
        if (currentLimit) url.searchParams.set('limit', currentLimit);
        
        window.location.href = url.toString();
    }

    function clearAllFilters() {
        // Clear all filter inputs
        document.getElementById('filterName').value = '';
        document.getElementById('filterCountry').value = '';
        document.getElementById('filterDate').value = '';
        
        // Reload page without filters
        const url = new URL(window.location.href.split('?')[0], window.location.origin);
        
        // Keep current limit
        const currentLimit = new URLSearchParams(window.location.search).get('limit');
        if (currentLimit) url.searchParams.set('limit', currentLimit);
        
        window.location.href = url.toString();
    }

    function updateItemsPerPage(value) {
        const url = new URL(window.location.href);
        url.searchParams.set('limit', value);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    /**
     * Get all selected data source IDs
     */
    function getSelectedDataSourceIds() {
        const selectedIds = [];
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        
        checkboxes.forEach(checkbox => {
            selectedIds.push(checkbox.getAttribute('data-id'));
        });
        
        return selectedIds;
    }

    /**
     * Export selected items
     */
    function exportSelected(format) {
        const selectedIds = getSelectedDataSourceIds();
        
        if (selectedIds.length === 0) {
            alert('Please select items to export.');
            return;
        }
        
        // Create URL parameters for export
        const params = new URLSearchParams();
        params.append('export', format);
        params.append('ids', JSON.stringify(selectedIds));
        
        // Open export in new window
        window.open('export_datasources.php?' + params.toString(), '_blank');
        
        // Close dropdown
        document.getElementById('exportDropdown').classList.remove('show');
    }

    /**
     * Export all data (without filters)
     */
    function exportAll(format) {
        if (confirm('Export ALL data sources? This may take a moment for large datasets.')) {
            const params = new URLSearchParams();
            params.append('export', format);
            params.append('export_all', 'true');
            
            window.open('export_datasources.php?' + params.toString(), '_blank');
            
            // Close dropdown
            document.getElementById('exportDropdown').classList.remove('show');
        }
    }

    /**
     * Export all data with current filters applied
     */
    function exportAllWithFilters(format) {
        // Get current filter values
        const filters = {
            name: document.getElementById('filterName').value,
            country: document.getElementById('filterCountry').value,
            date: document.getElementById('filterDate').value
        };
        
        // Count how many filters are active
        const activeFilters = Object.values(filters).filter(val => val.trim() !== '').length;
        
        let message = 'Export ';
        if (activeFilters > 0) {
            message += 'all data with current filters applied?';
        } else {
            message += 'ALL data sources (no filters active)?';
        }
        message += ' This may take a moment for large datasets.';
        
        if (confirm(message)) {
            const params = new URLSearchParams();
            params.append('export', format);
            params.append('export_all', 'true');
            params.append('apply_filters', 'true');
            
            // Add filters to params
            Object.keys(filters).forEach(key => {
                if (filters[key]) {
                    params.append('filter_' + key, filters[key]);
                }
            });
            
            window.open('export_datasources.php?' + params.toString(), '_blank');
            
            // Close dropdown
            document.getElementById('exportDropdown').classList.remove('show');
        }
    }

    /**
     * Delete selected items
     */
    function deleteSelected() {
        const selectedIds = getSelectedDataSourceIds();
        
        if (selectedIds.length === 0) {
            alert('Please select items to delete.');
            return;
        }
        
        if (confirm('Are you sure you want to delete the selected data sources?')) {
            // Send delete request via AJAX
            fetch('../data/delete_datasources.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: selectedIds }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Data sources deleted successfully.');
                    window.location.reload();
                } else {
                    alert('Failed to delete data sources: ' + (data.message || 'Unknown error.'));
                }
            })
            .catch(error => {
                console.error('Fetch error during delete:', error);
                alert('An error occurred while deleting data sources: ' + error.message);
            });
        }
    }
    </script>
</body>
</html>

<?php 
// Helper function to build filter parameters for URLs
function getFilterParams($filters) {
    $params = '';
    if (!empty($filters['name'])) {
        $params .= '&filter_name=' . urlencode($filters['name']);
    }
    if (!empty($filters['country'])) {
        $params .= '&filter_country=' . urlencode($filters['country']);
    }
    if (!empty($filters['date'])) {
        $params .= '&filter_date=' . urlencode($filters['date']);
    }
    return $params;
}
?>