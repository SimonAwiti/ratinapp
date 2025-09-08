<?php
// base/tradepoints_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Handle CSV import
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $tradepoint_type = $_POST['tradepoint_type'];
    $overwrite = isset($_POST['overwrite_existing']);
    
    // Skip header row
    fgetcsv($handle);
    
    $successCount = 0;
    $errorCount = 0;
    $errors = array();
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        $rowNumber = 1; // Track row numbers for better error reporting
        
        while (($data = fgetcsv($handle, 1000, ","))) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) {
                continue;
            }
            
            // Process based on tradepoint type
            switch ($tradepoint_type) {
                case 'Markets':
                    // Validate required fields for Markets
                    if (empty(trim($data[0])) || empty(trim($data[1])) || empty(trim($data[2])) || 
                        empty(trim($data[3])) || empty(trim($data[4])) || empty(trim($data[5])) || 
                        empty(trim($data[6])) || empty(trim($data[7])) || empty(trim($data[8]))) {
                        $errors[] = "Row $rowNumber: Missing required fields for Market";
                        $errorCount++;
                        continue;
                    }
                    
                    // Prepare market data
                    $market_name = trim($data[0]);
                    $category = trim($data[1]);
                    $type = trim($data[2]);
                    $country = trim($data[3]);
                    $county_district = trim($data[4]);
                    $longitude = floatval(trim($data[5]));
                    $latitude = floatval(trim($data[6]));
                    $radius = floatval(trim($data[7]));
                    $currency = trim($data[8]);
                    $primary_commodities = isset($data[9]) ? trim($data[9]) : '';
                    $additional_datasource = isset($data[10]) ? trim($data[10]) : '';
                    
                    // Check if market exists
                    $check_query = "SELECT id FROM markets WHERE market_name = ?";
                    $check_stmt = $con->prepare($check_query);
                    $check_stmt->bind_param('s', $market_name);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        if ($overwrite) {
                            // Update existing market
                            $update_query = "UPDATE markets SET 
                                category = ?, 
                                type = ?, 
                                country = ?, 
                                county_district = ?, 
                                longitude = ?, 
                                latitude = ?, 
                                radius = ?, 
                                currency = ?, 
                                primary_commodity = ?, 
                                additional_datasource = ? 
                                WHERE market_name = ?";
                            
                            $update_stmt = $con->prepare($update_query);
                            $update_stmt->bind_param(
                                'ssssdddssss',
                                $category,
                                $type,
                                $country,
                                $county_district,
                                $longitude,
                                $latitude,
                                $radius,
                                $currency,
                                $primary_commodities,
                                $additional_datasource,
                                $market_name
                            );
                            
                            if ($update_stmt->execute()) {
                                $successCount++;
                            } else {
                                $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                                $errorCount++;
                            }
                            $update_stmt->close();
                        } else {
                            $errors[] = "Row $rowNumber: Market '$market_name' already exists (use overwrite option to update)";
                            $errorCount++;
                        }
                        continue;
                    }
                    
                    // Insert new market
                    $insert_query = "INSERT INTO markets (
                        market_name, 
                        category, 
                        type, 
                        country, 
                        county_district, 
                        longitude, 
                        latitude, 
                        radius, 
                        currency, 
                        primary_commodity, 
                        additional_datasource,
                        tradepoint
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Markets')";
                    
                    $insert_stmt = $con->prepare($insert_query);
                    $insert_stmt->bind_param(
                        'ssssdddssss',
                        $market_name,
                        $category,
                        $type,
                        $country,
                        $county_district,
                        $longitude,
                        $latitude,
                        $radius,
                        $currency,
                        $primary_commodities,
                        $additional_datasource
                    );
                    
                    if ($insert_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                        $errorCount++;
                    }
                    $insert_stmt->close();
                    break;
                    
                case 'Millers':
                    // Validate required fields for Millers
                    if (empty(trim($data[0])) || empty(trim($data[1])) || empty(trim($data[2]))) {
                        $errors[] = "Row $rowNumber: Missing required fields for Miller";
                        $errorCount++;
                        continue;
                    }
                    
                    // Prepare miller data
                    $miller_name = trim($data[0]);
                    $country = trim($data[1]);
                    $county_district = trim($data[2]);
                    $millers = isset($data[3]) ? array_map('trim', explode(',', trim($data[3]))) : [];
                    
                    // Validate millers (max 2)
                    if (count($millers) > 2) {
                        $errors[] = "Row $rowNumber: Maximum of 2 millers allowed (found " . count($millers) . ")";
                        $errorCount++;
                        continue;
                    }
                    
                    $millers_json = json_encode($millers);
                    
                    // Check if miller exists
                    $check_query = "SELECT id FROM miller_details WHERE miller_name = ?";
                    $check_stmt = $con->prepare($check_query);
                    $check_stmt->bind_param('s', $miller_name);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        if ($overwrite) {
                            // Update existing miller
                            $update_query = "UPDATE miller_details SET 
                                country = ?, 
                                county_district = ?, 
                                miller = ? 
                                WHERE miller_name = ?";
                            
                            $update_stmt = $con->prepare($update_query);
                            $update_stmt->bind_param(
                                'ssss',
                                $country,
                                $county_district,
                                $millers_json,
                                $miller_name
                            );
                            
                            if ($update_stmt->execute()) {
                                $successCount++;
                            } else {
                                $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                                $errorCount++;
                            }
                            $update_stmt->close();
                        } else {
                            $errors[] = "Row $rowNumber: Miller '$miller_name' already exists (use overwrite option to update)";
                            $errorCount++;
                        }
                        continue;
                    }
                    
                    // Insert new miller
                    $insert_query = "INSERT INTO miller_details (
                        miller_name, 
                        country, 
                        county_district, 
                        miller,
                        tradepoint
                    ) VALUES (?, ?, ?, ?, 'Millers')";
                    
                    $insert_stmt = $con->prepare($insert_query);
                    $insert_stmt->bind_param(
                        'ssss',
                        $miller_name,
                        $country,
                        $county_district,
                        $millers_json
                    );
                    
                    if ($insert_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                        $errorCount++;
                    }
                    $insert_stmt->close();
                    break;
                    
                case 'Border Points':
                    // Validate required fields for Border Points
                    if (empty(trim($data[0])) || empty(trim($data[1])) || empty(trim($data[2])) || 
                        empty(trim($data[3])) || empty(trim($data[4]))) {
                        $errors[] = "Row $rowNumber: Missing required fields for Border Point";
                        $errorCount++;
                        continue;
                    }
                    
                    // Prepare border point data
                    $name = trim($data[0]);
                    $country = trim($data[1]);
                    $county = trim($data[2]);
                    $longitude = floatval(trim($data[3]));
                    $latitude = floatval(trim($data[4]));
                    
                    // Check if border point exists
                    $check_query = "SELECT id FROM border_points WHERE name = ?";
                    $check_stmt = $con->prepare($check_query);
                    $check_stmt->bind_param('s', $name);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        if ($overwrite) {
                            // Update existing border point
                            $update_query = "UPDATE border_points SET 
                                country = ?, 
                                county = ?, 
                                longitude = ?, 
                                latitude = ? 
                                WHERE name = ?";
                            
                            $update_stmt = $con->prepare($update_query);
                            $update_stmt->bind_param(
                                'ssdds',
                                $country,
                                $county,
                                $longitude,
                                $latitude,
                                $name
                            );
                            
                            if ($update_stmt->execute()) {
                                $successCount++;
                            } else {
                                $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                                $errorCount++;
                            }
                            $update_stmt->close();
                        } else {
                            $errors[] = "Row $rowNumber: Border Point '$name' already exists (use overwrite option to update)";
                            $errorCount++;
                        }
                        continue;
                    }
                    
                    // Insert new border point
                    $insert_query = "INSERT INTO border_points (
                        name, 
                        country, 
                        county, 
                        longitude, 
                        latitude,
                        tradepoint
                    ) VALUES (?, ?, ?, ?, ?, 'Border Points')";
                    
                    $insert_stmt = $con->prepare($insert_query);
                    $insert_stmt->bind_param(
                        'sssdd',
                        $name,
                        $country,
                        $county,
                        $longitude,
                        $latitude
                    );
                    
                    if ($insert_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                        $errorCount++;
                    }
                    $insert_stmt->close();
                    break;
                    
                default:
                    $errors[] = "Row $rowNumber: Invalid tradepoint type";
                    $errorCount++;
                    continue;
            }
        }
        
        // Commit transaction if no critical errors
        if (count($errors) === 0) {
            $con->commit();
            $import_message = "Successfully imported $successCount tradepoints.";
            $import_status = 'success';
        } else {
            $con->rollback();
            $import_message = "Import rolled back due to errors. Processed $successCount rows successfully. Errors: " . implode('<br>', $errors);
            $import_status = 'danger';
        }
        
    } catch (Exception $e) {
        $con->rollback();
        $import_message = "Import failed with exception: " . $e->getMessage();
        $import_status = 'danger';
    }
    
    fclose($handle);
} elseif (isset($_POST['import_csv'])) {
    $import_message = "Please select a valid CSV file to import.";
    $import_status = 'danger';
}

// Show import message if exists
if (isset($import_message)) {
    echo '<div class="alert alert-' . $import_status . '">' . $import_message . '</div>';
}

// --- Fetch all data for the table ---
$query = "
    SELECT
        id,
        market_name AS name,
        'Markets' AS tradepoint_type,
        country AS admin0,
        county_district AS admin1
    FROM markets
    
    UNION ALL
    
    SELECT
        id,
        name AS name,
        'Border Points' AS tradepoint_type,
        country AS admin0,
        county AS admin1
    FROM border_points
    
    UNION ALL
    
    SELECT
        id,
        miller_name AS name,
        'Millers' AS tradepoint_type,
        country AS admin0,
        county_district AS admin1
    FROM miller_details
    
    ORDER BY name ASC
";
$result = $con->query($query);
$tradepoints = $result->fetch_all(MYSQLI_ASSOC);

// Pagination and Filtering Logic
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($tradepoints);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$tradepoints_paged = array_slice($tradepoints, $startIndex, $itemsPerPage);

// --- Fetch counts for summary boxes ---
$total_tradepoints = count($tradepoints);

$markets_query = "SELECT COUNT(*) AS total FROM markets";
$markets_result = $con->query($markets_query);
$markets_count = $markets_result->fetch_assoc()['total'];

$border_points_query = "SELECT COUNT(*) AS total FROM border_points";
$border_points_result = $con->query($border_points_query);
$border_points_count = $border_points_result->fetch_assoc()['total'];

$millers_query = "SELECT COUNT(*) AS total FROM miller_details";
$millers_result = $con->query($millers_query);
$millers_count = $millers_result->fetch_assoc()['total'];
?>

<style>
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
    .btn-delete, .btn-export, .btn-import {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover, .btn-import:hover {
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
    .markets-icon {
        background-color: #e74c3c;
        color: white;
    }
    .border-icon {
        background-color: #f39c12;
        color: white;
    }
    .millers-icon {
        background-color: #27ae60;
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
        filter: invert(1);
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
    .type-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-market {
        background-color: #ffeaea;
        color: #721c24;
    }
    .badge-border {
        background-color: #fff3cd;
        color: #856404;
    }
    .badge-miller {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    .import-instructions {
        background-color: #f8f9fa;
        border-left: 4px solid rgba(180, 80, 50, 1);
        padding: 15px;
        margin-bottom: 20px;
    }
    .import-instructions h5 {
        color: rgba(180, 80, 50, 1);
        margin-top: 0;
    }
    .import-instructions h6 {
        color: rgba(180, 80, 50, 0.8);
        margin-top: 15px;
    }
    .download-template {
        display: inline-block;
        margin-top: 10px;
        color: rgba(180, 80, 50, 1);
        text-decoration: none;
    }
    .download-template:hover {
        text-decoration: underline;
    }
    .type-instructions ol {
        padding-left: 20px;
    }
    .type-instructions ol li {
        margin-bottom: 5px;
    }
    .alert {
        margin-bottom: 20px;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Tradepoints Management</h3></div>
    <p class="p">Manage everything related to Markets, Border Points and Millers</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="stats-title">Total Tradepoints</div>
            <div class="stats-number"><?= $total_tradepoints ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon markets-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="stats-title">Markets</div>
            <div class="stats-number"><?= $markets_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon border-icon">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stats-title">Border Points</div>
            <div class="stats-number"><?= $border_points_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon millers-icon">
                <i class="fas fa-industry"></i>
            </div>
            <div class="stats-title">Millers</div>
            <div class="stats-number"><?= $millers_count ?></div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="addtradepoint.php" class="btn btn-add-new">
                <i class="fas fa-plus" style="margin-right: 5px;"></i>
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()">
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download" style="margin-right: 3px;"></i>
                    Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">
                        <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export to Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">
                        <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export to PDF
                    </a></li>
                </ul>
            </div>
            
            <button class="btn btn-import" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-upload" style="margin-right: 3px;"></i>
                Import
            </button>
        </div>

        <table class="table table-striped table-hover">
            <thead>
                <tr style="background-color: #d3d3d3 !important; color: black !important;">
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Country</th>
                    <th>Region</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                    <th><input type="text" class="filter-input" id="filterType" placeholder="Filter Type"></th>
                    <th><input type="text" class="filter-input" id="filterCountry" placeholder="Filter Country"></th>
                    <th><input type="text" class="filter-input" id="filterRegion" placeholder="Filter Region"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tradepointTable">
                <?php foreach ($tradepoints_paged as $tradepoint): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($tradepoint['id']) ?>">
                        </td>
                        <td><?= htmlspecialchars($tradepoint['name']) ?></td>
                        <td>
                            <?php 
                            $badgeClass = '';
                            if ($tradepoint['tradepoint_type'] === 'Markets') {
                                $badgeClass = 'badge-market';
                            } elseif ($tradepoint['tradepoint_type'] === 'Border Points') {
                                $badgeClass = 'badge-border';
                            } elseif ($tradepoint['tradepoint_type'] === 'Millers') {
                                $badgeClass = 'badge-miller';
                            }
                            ?>
                            <span class="type-badge <?= $badgeClass ?>"><?= htmlspecialchars($tradepoint['tradepoint_type']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($tradepoint['admin0']) ?></td>
                        <td><?= htmlspecialchars($tradepoint['admin1']) ?></td>
                        <td>
                            <?php
                            $editPage = '';
                            switch ($tradepoint['tradepoint_type']) {
                                case 'Markets':
                                    $editPage = 'edit_market.php';
                                    break;
                                case 'Border Points':
                                    $editPage = 'edit_borderpoint.php';
                                    break;
                                case 'Millers':
                                    $editPage = 'edit_miller.php';
                                    break;
                            }
                            ?>
                            <a href="<?= $editPage ?>?id=<?= htmlspecialchars($tradepoint['id']) ?>">
                                <button class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?= $startIndex + 1 ?> to <?= min($startIndex + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> items
            </div>
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
                        <a class="page-link" href="<?= $page <= 1 ? '#' : '?page=' . ($page - 1) . '&limit=' . $itemsPerPage ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $totalPages ? '#' : '?page=' . ($page + 1) . '&limit=' . $itemsPerPage ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Tradepoints</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Import Instructions</h5>
                    <p>First select the type of tradepoint you want to import, then upload your CSV file.</p>
                    
                    <div class="mb-3">
                        <label for="tradepoint_type" class="form-label">Tradepoint Type</label>
                        <select class="form-select" id="tradepoint_type" name="tradepoint_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Markets">Markets</option>
                            <option value="Millers">Millers</option>
                            <option value="Border Points">Border Points</option>
                        </select>
                    </div>
                    
                    <div id="marketsInstructions" class="type-instructions" style="display: none;">
                        <h6>Markets CSV Format</h6>
                        <p>Your CSV file should have these columns in order:</p>
                        <ol>
                            <li><strong>Market Name</strong> (required)</li>
                            <li><strong>Category</strong> (required)</li>
                            <li><strong>Type</strong> (required)</li>
                            <li><strong>Country</strong> (required)</li>
                            <li><strong>County/District</strong> (required)</li>
                            <li><strong>Longitude</strong> (required)</li>
                            <li><strong>Latitude</strong> (required)</li>
                            <li><strong>Radius</strong> (required)</li>
                            <li><strong>Currency</strong> (required)</li>
                            <li><strong>Primary Commodities</strong> (comma-separated)</li>
                            <li><strong>Additional Data Source</strong></li>
                        </ol>
                        <a href="downloads/markets_template.csv" class="download-template">
                            <i class="fas fa-download"></i> Download Markets Template
                        </a>
                    </div>
                    
                    <div id="millersInstructions" class="type-instructions" style="display: none;">
                        <h6>Millers CSV Format</h6>
                        <p>Your CSV file should have these columns in order:</p>
                        <ol>
                            <li><strong>Miller Name</strong> (required)</li>
                            <li><strong>Country</strong> (required)</li>
                            <li><strong>County/District</strong> (required)</li>
                            <li><strong>Millers</strong> (comma-separated list, max 2)</li>
                        </ol>
                        <a href="downloads/millers_template.csv" class="download-template">
                            <i class="fas fa-download"></i> Download Millers Template
                        </a>
                    </div>
                    
                    <div id="borderInstructions" class="type-instructions" style="display: none;">
                        <h6>Border Points CSV Format</h6>
                        <p>Your CSV file should have these columns in order:</p>
                        <ol>
                            <li><strong>Name</strong> (required)</li>
                            <li><strong>Country</strong> (required)</li>
                            <li><strong>County</strong> (required)</li>
                            <li><strong>Longitude</strong> (required)</li>
                            <li><strong>Latitude</strong> (required)</li>
                        </ol>
                        <a href="downloads/border_points_template.csv" class="download-template">
                            <i class="fas fa-download"></i> Download Border Points Template
                        </a>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <input type="hidden" name="tradepoint_type" id="selected_tradepoint_type" value="">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="overwriteExisting" name="overwrite_existing">
                        <label class="form-check-label" for="overwriteExisting">
                            Overwrite existing records with matching names
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="importForm" name="import_csv" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize filter functionality
    const filterInputs = document.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('keyup', applyFilters);
    });

    // Initialize select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Tradepoints');
    }
    
    // Handle tradepoint type selection in import modal
    document.getElementById('tradepoint_type').addEventListener('change', function() {
        const type = this.value;
        document.getElementById('selected_tradepoint_type').value = type;
        
        // Hide all instruction blocks first
        document.querySelectorAll('.type-instructions').forEach(el => {
            el.style.display = 'none';
        });
        
        // Show the relevant instruction block
        if (type === 'Markets') {
            document.getElementById('marketsInstructions').style.display = 'block';
        } else if (type === 'Millers') {
            document.getElementById('millersInstructions').style.display = 'block';
        } else if (type === 'Border Points') {
            document.getElementById('borderInstructions').style.display = 'block';
        }
    });
    
    // Show import modal if there was an error
    <?php if (isset($import_message) && $import_status === 'danger'): ?>
        var importModal = new bootstrap.Modal(document.getElementById('importModal'));
        importModal.show();
    <?php endif; ?>
});

function applyFilters() {
    const filters = {
        name: document.getElementById('filterName').value.toLowerCase(),
        type: document.getElementById('filterType').value.toLowerCase(),
        country: document.getElementById('filterCountry').value.toLowerCase(),
        region: document.getElementById('filterRegion').value.toLowerCase()
    };

    const rows = document.querySelectorAll('#tradepointTable tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const matches = 
            cells[1].textContent.toLowerCase().includes(filters.name) &&
            cells[2].textContent.toLowerCase().includes(filters.type) &&
            cells[3].textContent.toLowerCase().includes(filters.country) &&
            cells[4].textContent.toLowerCase().includes(filters.region);
        
        row.style.display = matches ? '' : 'none';
    });
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function deleteSelected() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one tradepoint to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${checkedBoxes.length} selected tradepoint(s)?`)) {
        const ids = Array.from(checkedBoxes).map(cb => cb.value);
        // Implement your delete logic here
        console.log('Deleting tradepoints with IDs:', ids);
        // Example: fetch('delete_tradepoints.php', { method: 'POST', body: JSON.stringify({ ids }) })
        // .then(response => response.json())
        // .then(data => { if(data.success) location.reload(); });
    }
}

function exportSelected(format) {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one tradepoint to export.');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    // Implement your export logic here
    console.log(`Exporting ${format} for tradepoints with IDs:`, ids);
    // Example: window.location.href = `export_tradepoints.php?format=${format}&ids=${ids.join(',')}`;
}
</script>

<?php include '../admin/includes/footer.php'; ?>