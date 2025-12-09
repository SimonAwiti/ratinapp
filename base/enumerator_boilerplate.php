<?php
// base/enumerator_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Handle CSV import
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
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
            
            // Skip completely empty rows
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) {
                continue; // Skip empty rows without counting as errors
            }
            
            // Validate required fields
            if (empty(trim($data[0]))) { // Name
                $errors[] = "Row $rowNumber: Name is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[1]))) { // Email
                $errors[] = "Row $rowNumber: Email is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[2]))) { // Phone
                $errors[] = "Row $rowNumber: Phone is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[3]))) { // Gender
                $errors[] = "Row $rowNumber: Gender is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[4]))) { // Country
                $errors[] = "Row $rowNumber: Country is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[5]))) { // County/District
                $errors[] = "Row $rowNumber: County/District is required";
                $errorCount++;
                continue;
            }
            
            // Prepare enumerator data
            $name = trim($data[0]);
            $email = trim($data[1]);
            $phone = trim($data[2]);
            $gender = trim($data[3]);
            $country = trim($data[4]);
            $county_district = trim($data[5]);
            $username = isset($data[6]) ? trim($data[6]) : generateUsername($name);
            $password = isset($data[7]) ? trim($data[7]) : generateRandomPassword();
            $latitude = isset($data[8]) ? floatval(trim($data[8])) : 0.0;
            $longitude = isset($data[9]) ? floatval(trim($data[9])) : 0.0;
            
            // Process tradepoints (format: "Market:1,Border Point:2,Miller:3")
            $tradepoints_json = '[]';
            if (isset($data[10]) && !empty(trim($data[10]))) {
                $tradepoints_array = [];
                $tradepoints_csv = trim($data[10]);
                $tradepoint_pairs = array_map('trim', explode(',', $tradepoints_csv));
                
                foreach ($tradepoint_pairs as $pair) {
                    if (strpos($pair, ':') !== false) {
                        $parts = explode(':', $pair, 2);
                        if (count($parts) == 2) {
                            $type = trim($parts[0]);
                            $id = intval(trim($parts[1]));
                            
                            // Validate tradepoint type
                            $valid_types = ['Market', 'Border Point', 'Miller', 'Markets', 'Border Points', 'Millers'];
                            if (in_array($type, $valid_types) && $id > 0) {
                                // Normalize type names
                                if ($type === 'Markets') $type = 'Market';
                                if ($type === 'Border Points') $type = 'Border Point';
                                if ($type === 'Millers') $type = 'Miller';
                                
                                $tradepoints_array[] = [
                                    'id' => $id,
                                    'type' => $type
                                ];
                            } else {
                                $errors[] = "Row $rowNumber: Warning - Invalid tradepoint format: '$pair'";
                            }
                        }
                    }
                }
                $tradepoints_json = json_encode($tradepoints_array, JSON_UNESCAPED_UNICODE);
            }
            
            // Check if enumerator exists (by email)
            $check_query = "SELECT id FROM enumerators WHERE email = ?";
            $check_stmt = $con->prepare($check_query);
            $check_stmt->bind_param('s', $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    // Update existing enumerator
                    $update_query = "UPDATE enumerators SET 
                        name = ?, 
                        phone = ?, 
                        gender = ?, 
                        country = ?, 
                        county_district = ?, 
                        username = ?, 
                        password = ?, 
                        latitude = ?, 
                        longitude = ?, 
                        tradepoints = ? 
                        WHERE email = ?";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt->bind_param(
                        'ssssssddss',
                        $name,
                        $phone,
                        $gender,
                        $country,
                        $county_district,
                        $username,
                        $hashed_password,
                        $latitude,
                        $longitude,
                        $tradepoints_json,
                        $email
                    );
                    
                    if ($update_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                        $errorCount++;
                    }
                    $update_stmt->close();
                } else {
                    $errors[] = "Row $rowNumber: Enumerator with email '$email' already exists (use overwrite option to update)";
                    $errorCount++;
                }
                continue;
            }
            
            // Insert new enumerator
            $insert_query = "INSERT INTO enumerators (
                name, 
                email, 
                phone, 
                gender, 
                country, 
                county_district, 
                username, 
                password, 
                latitude, 
                longitude, 
                tradepoints,
                token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16)); // Generate random token
            
            $insert_stmt->bind_param(
                'ssssssssddss',
                $name,
                $email,
                $phone,
                $gender,
                $country,
                $county_district,
                $username,
                $hashed_password,
                $latitude,
                $longitude,
                $tradepoints_json,
                $token
            );
            
            if ($insert_stmt->execute()) {
                $successCount++;
            } else {
                $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                $errorCount++;
            }
            $insert_stmt->close();
        }
        
        // Commit transaction if no critical errors (allow warnings)
        $criticalErrors = 0;
        foreach ($errors as $error) {
            if (strpos($error, 'Warning') === false) {
                $criticalErrors++;
            }
        }
        
        if ($criticalErrors === 0) {
            $con->commit();
            $warningCount = count($errors) - $criticalErrors;
            if ($warningCount > 0) {
                $import_message = "Successfully imported " . $successCount . " enumerators with " . $warningCount . " warnings. Warnings: " . implode('<br>', $errors);
                $import_status = 'warning';
            } else {
                $import_message = "Successfully imported " . $successCount . " enumerators.";
                $import_status = 'success';
            }
        } else {
            $con->rollback();
            $import_message = "Import rolled back due to " . $criticalErrors . " critical errors. Processed " . $successCount . " rows successfully. Errors: " . implode('<br>', $errors);
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

// Function to generate username from name
function generateUsername($name) {
    $username = strtolower(str_replace(' ', '.', $name));
    $username = preg_replace('/[^a-z0-9.]/', '', $username);
    return $username . rand(100, 999);
}

// Function to generate random password
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Function to fetch the actual name of a tradepoint based on ID and type
function getTradepointName($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';

    switch ($type) {
        case 'Market':
        case 'Markets':
            $tableName = 'markets';
            $nameColumn = 'market_name';
            break;
        case 'Border Point':
        case 'Border Points':
            $tableName = 'border_points';
            $nameColumn = 'name';
            break;
        case 'Miller':
        case 'Millers':
            $tableName = 'miller_details';
            $nameColumn = 'miller_name';
            break;
        default:
            return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)";
}

// --- Fetch all enumerators with their assigned tradepoints ---
$query = "
    SELECT
        id,
        name,
        email,
        phone,
        gender,
        country,
        county_district,
        tradepoints,
        latitude,
        longitude,
        token,
        username,
        created_at
    FROM enumerators
    ORDER BY name ASC
";
$result = $con->query($query);
$enumerators_raw = $result->fetch_all(MYSQLI_ASSOC);

// Process tradepoints for each enumerator
foreach ($enumerators_raw as &$enum) {
    $assigned_tradepoints_array = [];
    if (!empty($enum['tradepoints'])) {
        $tradepoints_json = json_decode($enum['tradepoints'], true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tradepoints_json)) {
            foreach ($tradepoints_json as $tp_data) {
                if (isset($tp_data['id']) && isset($tp_data['type'])) {
                    $tradepoint_id = $tp_data['id'];
                    $tradepoint_type = $tp_data['type'];
                    $actual_name = getTradepointName($con, $tradepoint_id, $tradepoint_type);
                    
                    if (!empty($actual_name)) {
                        $assigned_tradepoints_array[] = htmlspecialchars($actual_name) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    } else {
                        $assigned_tradepoints_array[] = "ID: " . htmlspecialchars($tradepoint_id) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    }
                }
            }
        } else {
            $assigned_tradepoints_array[] = 'Invalid JSON or No Tradepoints Defined';
        }
    } else {
        $assigned_tradepoints_array[] = 'No Tradepoints';
    }
    $enum['tradepoints_list'] = $assigned_tradepoints_array;
}
unset($enum);

// Calculate statistics
$totalEnumerators = count($enumerators_raw);
$activeEnumerators = 0;
$assignedEnumerators = 0;
$unassignedEnumerators = 0;

foreach ($enumerators_raw as $enum) {
    $activeEnumerators++;
    if (!empty($enum['tradepoints_list']) && !in_array('No Tradepoints', $enum['tradepoints_list'])) {
        $assignedEnumerators++;
    } else {
        $unassignedEnumerators++;
    }
}

// Pagination and Filtering Logic
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($enumerators_raw);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$enumerators_paged = array_slice($enumerators_raw, $startIndex, $itemsPerPage);
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
    .btn-delete, .btn-export, .btn-import, .btn-bulk-export {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover, .btn-import:hover, .btn-bulk-export:hover {
        background-color: #f8f9fa;
    }
    .btn-bulk-export {
        background-color: #17a2b8;
        color: white;
    }
    .btn-bulk-export:hover {
        background-color: #138496;
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
    .btn-import {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-import:hover {
        background-color: #f8f9fa;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Enumerators Management</h3></div>
    <p class="p">Manage everything related to Enumerators and their assignments</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-title">Total Enumerators</div>
            <div class="stats-number"><?php echo $totalEnumerators; ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon active-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-title">Active</div>
            <div class="stats-number"><?php echo $activeEnumerators; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon assigned-icon">
                <i class="fas fa-user-tag"></i>
            </div>
            <div class="stats-title">Assigned</div>
            <div class="stats-number"><?php echo $assignedEnumerators; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon unassigned-icon">
                <i class="fas fa-user-minus"></i>
            </div>
            <div class="stats-title">Unassigned</div>
            <div class="stats-number"><?php echo $unassignedEnumerators; ?></div>
        </div>
    </div>
</div>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?php echo $import_status; ?>">
        <?php echo $import_message; ?>
    </div>
<?php endif; ?>

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

            <form method="POST" action="export_current_page_enumerators.php" style="display: inline;">
                <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
                <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
                <button type="submit" class="btn-export">
                    <i class="fas fa-download" style="margin-right: 3px;"></i> Export (Current Page)
                </button>
            </form>

            <form method="POST" action="bulk_export_enumerators.php" style="display: inline;">
                <button type="submit" class="btn-bulk-export">
                    <i class="fas fa-database" style="margin-right: 3px;"></i> Bulk Export (All)
                </button>
            </form>
            
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
                    <th>Country</th>
                    <th>Region</th>
                    <th>Assigned Tradepoints</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                    <th><input type="text" class="filter-input" id="filterCountry" placeholder="Filter Country"></th>
                    <th><input type="text" class="filter-input" id="filterRegion" placeholder="Filter Region"></th>
                    <th><input type="text" class="filter-input" id="filterTradepoints" placeholder="Filter Tradepoints"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="enumeratorTable">
                <?php if (empty($enumerators_paged)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                            No enumerators found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enumerators_paged as $enumerator): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-checkbox" value="<?php echo htmlspecialchars($enumerator['id']); ?>">
                            </td>
                            <td><?php echo htmlspecialchars($enumerator['name']); ?></td>
                            <td><?php echo htmlspecialchars($enumerator['country']); ?></td>
                            <td><?php echo htmlspecialchars($enumerator['county_district']); ?></td>
                            <td>
                                <div class="tradepoints-container">
                                    <?php if (!empty($enumerator['tradepoints_list']) && !in_array('No Tradepoints', $enumerator['tradepoints_list'])): ?>
                                        <?php 
                                        $tradepoints = $enumerator['tradepoints_list'];
                                        $visibleCount = 3;
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
                                                <span class="tradepoint-tag <?php echo $class; ?>"><?php echo htmlspecialchars($tp); ?></span>
                                            <?php endfor; ?>
                                        </div>
                                        
                                        <?php if ($hasMore): ?>
                                            <button class="show-more-btn" onclick="toggleTradepoints(this)">
                                                +<?php echo count($tradepoints) - $visibleCount; ?> more
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
                                                    <span class="tradepoint-tag <?php echo $class; ?>"><?php echo htmlspecialchars($tp); ?></span>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-tradepoints">No tradepoints assigned</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="edit_enumerator.php?id=<?php echo htmlspecialchars($enumerator['id']); ?>">
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?php echo $startIndex + 1; ?> to <?php echo min($startIndex + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> items
            </div>
            <div>
                <label for="itemsPerPage">Show:</label>
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                    <option value="7" <?php echo ($itemsPerPage == 7) ? 'selected' : ''; ?>>7</option>
                    <option value="10" <?php echo ($itemsPerPage == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo ($itemsPerPage == 20) ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo ($itemsPerPage == 50) ? 'selected' : ''; ?>>50</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page=' . ($page - 1) . '&limit=' . $itemsPerPage; ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $itemsPerPage; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $totalPages) ? '#' : '?page=' . ($page + 1) . '&limit=' . $itemsPerPage; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Enumerators</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Import Instructions</h5>
                    <p>Your CSV file should have the following columns in order:</p>
                    <ol>
                        <li><strong>Name</strong> (required) - Full name of the enumerator</li>
                        <li><strong>Email</strong> (required) - Email address</li>
                        <li><strong>Phone</strong> (required) - Phone number</li>
                        <li><strong>Gender</strong> (required) - Male/Female/Other</li>
                        <li><strong>Country</strong> (required) - Country name</li>
                        <li><strong>County/District</strong> (required) - Region or district</li>
                        <li><strong>Username</strong> (optional) - Will be auto-generated if empty</li>
                        <li><strong>Password</strong> (optional) - Will be auto-generated if empty</li>
                        <li><strong>Latitude</strong> (optional) - Geographic coordinate</li>
                        <li><strong>Longitude</strong> (optional) - Geographic coordinate</li>
                        <li><strong>Tradepoints</strong> (optional) - Comma-separated list of "Type:ID" pairs</li>
                    </ol>
                    
                    <h6>Tradepoints Format Examples:</h6>
                    <ul>
                        <li><code>Market:1,Border Point:2,Miller:3</code></li>
                        <li><code>Markets:5</code> (for single tradepoint)</li>
                        <li><code>Border Points:10,Millers:15</code></li>
                    </ul>
                    
                    <p><strong>Note:</strong> To find tradepoint IDs, check the IDs in your Markets, Border Points, and Millers management pages.</p>
                    
                    <a href="downloads/enumerators_template.csv" class="download-template">
                        <i class="fas fa-download"></i> Download Enumerators Template
                    </a>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="overwriteExisting" name="overwrite_existing">
                        <label class="form-check-label" for="overwriteExisting">
                            Overwrite existing enumerators with matching emails
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
        updateBreadcrumb('Base', 'Enumerators');
    }
    
    // Show import modal if there was an error
    <?php if (isset($import_message) && $import_status === 'danger'): ?>
        var importModal = new bootstrap.Modal(document.getElementById('importModal'));
        importModal.show();
    <?php endif; ?>
});

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

function applyFilters() {
    const filters = {
        name: document.getElementById('filterName').value.toLowerCase(),
        country: document.getElementById('filterCountry').value.toLowerCase(),
        region: document.getElementById('filterRegion').value.toLowerCase(),
        tradepoints: document.getElementById('filterTradepoints').value.toLowerCase()
    };

    const rows = document.querySelectorAll('#enumeratorTable tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const matches = 
            cells[1].textContent.toLowerCase().includes(filters.name) &&
            cells[2].textContent.toLowerCase().includes(filters.country) &&
            cells[3].textContent.toLowerCase().includes(filters.region) &&
            cells[4].textContent.toLowerCase().includes(filters.tradepoints);
        
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
        alert('Please select at least one enumerator to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + checkedBoxes.length + ' selected enumerator(s)?')) {
        const ids = Array.from(checkedBoxes).map(cb => cb.value);

        fetch('delete_enumerator.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Request failed: ' + error.message);
        });
    }
}

function exportSelected(format) {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one enumerator to export.');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_enumerators.php';
    
    // Add format parameter
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'export_format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    // Add selected IDs
    ids.forEach(id => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'selected_ids[]';
        idInput.value = id;
        form.appendChild(idInput);
    });
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include '../admin/includes/footer.php'; ?>