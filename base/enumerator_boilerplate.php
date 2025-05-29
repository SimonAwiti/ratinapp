<?php
session_start();
// Include database configuration
include '../admin/includes/config.php';

// Function to fetch the actual name of a tradepoint based on ID and type
function getTradepointName($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';

    // Determine the table and name column based on the type
    switch ($type) {
        case 'Market':
        case 'Markets': // Handle both singular and plural if necessary based on your data
            $tableName = 'markets';
            $nameColumn = 'market_name';
            break;
        case 'Border Point':
        case 'Border Points': // Handle both singular and plural
            $tableName = 'border_points';
            $nameColumn = 'name';
            break;
        case 'Miller':
        case 'Millers': // Handle both singular and plural
            $tableName = 'miller_details';
            $nameColumn = 'miller_name';
            break;
        default:
            return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        // Prepare a safe query to prevent SQL injection
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id); // Assuming ID is an integer
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for tradepoint name lookup: " . $con->error);
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)"; // Fallback if name not found or type is unknown
}

// Fetch enumerators with their assigned tradepoints (JSON string)
$query = "
    SELECT
        id,
        name,
        email,
        phone,
        gender,
        country,
        county_district,
        tradepoints, -- Directly select the JSON column
        latitude,
        longitude,
        token
    FROM enumerators
    ORDER BY name ASC
";

$result = $con->query($query);
$enumerators_raw = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $enumerators_raw[] = $row;
    }
}

// Process tradepoints for each enumerator
foreach ($enumerators_raw as &$enum) { // Use & for reference to modify original array
    $assigned_tradepoints_array = [];
    if (!empty($enum['tradepoints'])) {
        $tradepoints_json = json_decode($enum['tradepoints'], true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tradepoints_json)) {
            foreach ($tradepoints_json as $key => $tp_data) {
                // Ensure 'id' and 'type' keys exist within the nested array
                if (isset($tp_data['id']) && isset($tp_data['type'])) {
                    $tradepoint_id = $tp_data['id'];
                    $tradepoint_type = $tp_data['type'];

                    // Fetch the actual name using the new function
                    $actual_name = getTradepointName($con, $tradepoint_id, $tradepoint_type);

                    // Format for display: "Actual Name (Type)"
                    if (!empty($actual_name) && $actual_name !== "ID: " . htmlspecialchars($tradepoint_id) . " (Name Not Found)") {
                        $assigned_tradepoints_array[] = htmlspecialchars($actual_name) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    } else {
                        // Fallback if name is not found
                        $assigned_tradepoints_array[] = "ID: " . htmlspecialchars($tradepoint_id) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    }
                } else {
                    // Handle cases where 'id' or 'type' might be missing in a tradepoint entry
                    $assigned_tradepoints_array[] = "Malformed Tradepoint Data";
                }
            }
        } else {
            // Handle JSON decoding error or non-array JSON
            $assigned_tradepoints_array[] = 'Invalid JSON or No Tradepoints Defined';
        }
    } else {
        $assigned_tradepoints_array[] = 'No Tradepoints';
    }
    // Store as array for better handling
    $enum['tradepoints_list'] = $assigned_tradepoints_array;
}
unset($enum); // Unset the reference to avoid unintended modifications

// Calculate statistics
$totalEnumerators = count($enumerators_raw);
$activeEnumerators = 0;
$assignedEnumerators = 0;
$unassignedEnumerators = 0;

foreach ($enumerators_raw as $enum) {
    // Assume all are active for now (you can add a status field later)
    $activeEnumerators++;
    
    // Check if has assigned tradepoints
    if (!empty($enum['tradepoints_list']) && !in_array('No Tradepoints', $enum['tradepoints_list'])) {
        $assignedEnumerators++;
    } else {
        $unassignedEnumerators++;
    }
}

// Pagination setup
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($enumerators_raw);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

// Slice data for current page
$enumerators_display = array_slice($enumerators_raw, $startIndex, $itemsPerPage);

// Get current URL without query parameters
$currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enumerators Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css" />
    <link rel="stylesheet" href="assets/globals.css" />
    <link rel="stylesheet" href="assets/styleguide.css" />
    
    <style>
    body {
        padding: 20px;
        background-color: #f8f9fa;
    }
    .table-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .filter-row {
        background-color: white;
    }
    .btn-group {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
    }
    .btn-add-new {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        padding: 10px 20px;
        font-size: 16px;
        border: none;
    }
    .btn-add-new:hover {
        background-color: darkred;
    }
    .btn-delete, .btn-export {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover {
        background-color: #f8f9fa;
    }
    .dropdown-menu {
        min-width: 120px;
    }
    .dropdown-item {
        cursor: pointer;
    }
    .filter-input {
        width: 100%;
        border: none;
        background: white;
        padding: 5px;
        border-radius: 5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .filter-input:focus {
        outline: none;
        background: white;
    }
    .stats-container {
        display: flex;
        gap: 15px;
        justify-content: space-between;
        align-items: center;
        flex-wrap: nowrap;
        width: 87%;
        max-width: 100%;
        margin: 0 auto 20px auto;
        margin-left: 0.7%;
    }
    .stats-container > div {
        flex: 1;
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        text-align: center;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .stats-icon {
        width: 40px;
        height: 40px;
        margin-bottom: 10px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    .total-icon {
        background-color: #3498db;
        color: white;
    }
    .active-icon {
        background-color: #27ae60;
        color: white;
    }
    .assigned-icon {
        background-color: #e74c3c;
        color: white;
    }
    .unassigned-icon {
        background-color: #f39c12;
        color: white;
    }
    .stats-section {
        text-align: left;
        margin-left: 11%;
    }
    .stats-title {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin: 8px 0 5px 0;
    }
    .stats-number {
        font-size: 24px;
        font-weight: 700;
        color: #34495e;
    }
    .modal-content {
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
        background-color: #2c3e50;
        color: white;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }
    .modal-header .btn-close {
        color: white;
    }
    .form-control {
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 8px;
    }
    .form-control:focus {
        outline: none;
        border-color: rgba(180, 80, 50, 1);
        box-shadow: 0 0 5px rgba(180, 80, 50, 0.5);
    }
    .btn-primary {
        background-color: rgba(180, 80, 50, 1);
        border: none;
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 5px;
        color: white;
        cursor: pointer;
    }
    .btn-primary:hover {
        background-color: darkred;
    }
    
    /* Enhanced tradepoints display */
    .tradepoints-container {
        max-width: 300px;
        position: relative;
    }
    
    .tradepoints-visible {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 5px;
    }
    
    .tradepoint-tag {
        background-color: #e9ecef;
        color: #495057;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        white-space: nowrap;
        border: 1px solid #dee2e6;
    }
    
    .tradepoint-tag.market {
        background-color: #ffeaea;
        color: #721c24;
        border-color: #f5c6cb;
    }
    
    .tradepoint-tag.border {
        background-color: #fff3cd;
        color: #856404;
        border-color: #ffeaa7;
    }
    
    .tradepoint-tag.miller {
        background-color: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
    }
    
    .show-more-btn {
        background: none;
        border: none;
        color: #007bff;
        font-size: 11px;
        padding: 2px 6px;
        cursor: pointer;
        text-decoration: underline;
    }
    
    .show-more-btn:hover {
        color: #0056b3;
    }
    
    .tradepoints-hidden {
        display: none;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px;
    }
    
    .tradepoints-hidden.show {
        display: flex;
    }
    
    .no-tradepoints {
        color: #6c757d;
        font-style: italic;
        font-size: 12px;
    }
    </style>
</head>

<body>
    <div class="stats-section">
        <div class="text-wrapper-8"><h3>Enumerators Management</h3></div>
        <p class="p">Manage everything related to Enumerators and their assignments</p>

        <div class="stats-container">
            <!-- Total Enumerators -->
            <div class="overlap-6">
                <div class="stats-icon total-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-title">Total Enumerators</div>
                <div class="stats-number"><?= $totalEnumerators ?></div>
            </div>
            
            <!-- Active Enumerators -->
            <div class="overlap-6">
                <div class="stats-icon active-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stats-title">Active</div>
                <div class="stats-number"><?= $activeEnumerators ?></div>
            </div>
            
            <!-- Assigned Enumerators -->
            <div class="overlap-7">
                <div class="stats-icon assigned-icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div class="stats-title">Assigned</div>
                <div class="stats-number"><?= $assignedEnumerators ?></div>
            </div>
            
            <!-- Unassigned Enumerators -->
            <div class="overlap-7">
                <div class="stats-icon unassigned-icon">
                    <i class="fas fa-user-minus"></i>
                </div>
                <div class="stats-title">Unassigned</div>
                <div class="stats-number"><?= $unassignedEnumerators ?></div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="table-container">
            <div class="btn-group">
                <a href="add_enumerator.php" class="btn btn-add-new">
                    <i class="fas fa-plus" style="margin-right: 5px;"></i>
                    Add New
                </a>

                <button class="btn btn-delete" onclick="deleteSelected()"> 
                    <i class="fas fa-trash" style="margin-right: 3px;"></i>
                    Delete
                </button>

                <div class="dropdown">
                    <button class="btn btn-export dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download" style="margin-right: 3px;"></i>
                        Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">
                            <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export to Excel
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">
                            <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export to PDF
                        </a></li>
                    </ul>
                </div>
            </div>

            <table class="table table-striped table-hover">
                <thead>
                    <tr style="background-color: #d3d3d3 !important; color: black !important;">
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Name</th>
                        <th>Admin 0</th>
                        <th>Admin 1</th>
                        <th>Assigned Tradepoints</th>
                        <th>Actions</th>
                    </tr>
                    <tr class="filter-row">
                        <th></th>
                        <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                        <th><input type="text" class="filter-input" id="filterAdmin0" placeholder="Filter Admin 0"></th>
                        <th><input type="text" class="filter-input" id="filterAdmin1" placeholder="Filter Admin 1"></th>
                        <th><input type="text" class="filter-input" id="filterTradepoint" placeholder="Filter Tradepoint"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="enumeratorTable">
                    <?php foreach ($enumerators_display as $enum): ?>
                        <tr>
                            <td><input type="checkbox" class="row-checkbox" value="<?= $enum['id'] ?>"></td>
                            <td><?= htmlspecialchars($enum['name']) ?></td>
                            <td><?= htmlspecialchars($enum['country']) ?></td>
                            <td><?= htmlspecialchars($enum['county_district']) ?></td>
                            <td>
                                <div class="tradepoints-container">
                                    <?php if (!empty($enum['tradepoints_list']) && !in_array('No Tradepoints', $enum['tradepoints_list'])): ?>
                                        <?php 
                                        $tradepoints = $enum['tradepoints_list'];
                                        $visibleCount = 3; // Show first 3 tradepoints
                                        $hasMore = count($tradepoints) > $visibleCount;
                                        ?>
                                        
                                        <div class="tradepoints-visible">
                                            <?php for ($i = 0; $i < min($visibleCount, count($tradepoints)); $i++): ?>
                                                <?php 
                                                $tp = $tradepoints[$i];
                                                $class = '';
                                                if (strpos($tp, '(Markets)') !== false || strpos($tp, '(Market)') !== false) {
                                                    $class = 'market';
                                                } elseif (strpos($tp, '(Border Points)') !== false || strpos($tp, '(Border Point)') !== false) {
                                                    $class = 'border';
                                                } elseif (strpos($tp, '(Millers)') !== false || strpos($tp, '(Miller)') !== false) {
                                                    $class = 'miller';
                                                }
                                                ?>
                                                <span class="tradepoint-tag <?= $class ?>"><?= htmlspecialchars($tp) ?></span>
                                            <?php endfor; ?>
                                        </div>
                                        
                                        <?php if ($hasMore): ?>
                                            <button class="show-more-btn" onclick="toggleTradepoints(this)">
                                                +<?= count($tradepoints) - $visibleCount ?> more
                                            </button>
                                            <div class="tradepoints-hidden">
                                                <?php for ($i = $visibleCount; $i < count($tradepoints); $i++): ?>
                                                    <?php 
                                                    $tp = $tradepoints[$i];
                                                    $class = '';
                                                    if (strpos($tp, '(Markets)') !== false || strpos($tp, '(Market)') !== false) {
                                                        $class = 'market';
                                                    } elseif (strpos($tp, '(Border Points)') !== false || strpos($tp, '(Border Point)') !== false) {
                                                        $class = 'border';
                                                    } elseif (strpos($tp, '(Millers)') !== false || strpos($tp, '(Miller)') !== false) {
                                                        $class = 'miller';
                                                    }
                                                    ?>
                                                    <span class="tradepoint-tag <?= $class ?>"><?= htmlspecialchars($tp) ?></span>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-tradepoints">No tradepoints assigned</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="edit_enumerator.php?id=<?= htmlspecialchars($enum['id']) ?>">
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div>Displaying <?= $startIndex + 1 ?> to <?= min($startIndex + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> items</div>
                <div>
                    <label for="itemsPerPage">Show:</label>
                    <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                        <option value="7" <?= $itemsPerPage == 7 ? 'selected' : '' ?>>7</option>
                        <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                        <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                    </select>
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentUrl ?>?page=<?= $page - 1 ?>&limit=<?= $itemsPerPage ?>">Prev</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $currentUrl ?>?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentUrl ?>?page=<?= $page + 1 ?>&limit=<?= $itemsPerPage ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle tradepoints visibility
        function toggleTradepoints(button) {
            const hiddenDiv = button.nextElementSibling;
            const isVisible = hiddenDiv.classList.contains('show');
            
            if (isVisible) {
                hiddenDiv.classList.remove('show');
                const totalHidden = hiddenDiv.children.length;
                button.textContent = `+${totalHidden} more`;
            } else {
                hiddenDiv.classList.add('show');
                button.textContent = 'Show less';
            }
        }
        
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Update items per page
        function updateItemsPerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('limit', value);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }
        
        // Delete selected functionality
        function deleteSelected() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one enumerator to delete.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${checkedBoxes.length} enumerator(s)?`)) {
                const ids = Array.from(checkedBoxes).map(cb => cb.value);
                // Add your delete logic here
                console.log('Delete enumerators with IDs:', ids);
            }
        }
        
        // Export functionality
        function exportSelected(format) {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one enumerator to export.');
                return;
            }
            
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            // Add your export logic here
            console.log(`Export ${format} for enumerators with IDs:`, ids);
        }
        
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterInputs = document.querySelectorAll('.filter-input');
            
            filterInputs.forEach(input => {
                input.addEventListener('keyup', function() {
                    filterTable();
                });
            });
            
            function filterTable() {
                const nameFilter = document.getElementById('filterName').value.toLowerCase();
                const admin0Filter = document.getElementById('filterAdmin0').value.toLowerCase();
                const admin1Filter = document.getElementById('filterAdmin1').value.toLowerCase();
                const tradepointFilter = document.getElementById('filterTradepoint').value.toLowerCase();
                
                const rows = document.querySelectorAll('#enumeratorTable tr');
                
                rows.forEach(row => {
                    const name = row.cells[1]?.textContent.toLowerCase() || '';
                    const admin0 = row.cells[2]?.textContent.toLowerCase() || '';
                    const admin1 = row.cells[3]?.textContent.toLowerCase() || '';
                    const tradepoints = row.cells[4]?.textContent.toLowerCase() || '';
                    
                    const nameMatch = name.includes(nameFilter);
                    const admin0Match = admin0.includes(admin0Filter);
                    const admin1Match = admin1.includes(admin1Filter);
                    const tradepointMatch = tradepoints.includes(tradepointFilter);
                    
                    if (nameMatch && admin0Match && admin1Match && tradepointMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });
    </script>
    
    <script src="assets/filter3.js"></script>
</body>
</html>