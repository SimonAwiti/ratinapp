<?php
session_start();

// Initialize selected enumerators in session if not exists
if (!isset($_SESSION['selected_enumerators'])) {
    $_SESSION['selected_enumerators'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_enumerators'])) {
            $_SESSION['selected_enumerators'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_enumerators']);
        if ($key !== false) {
            unset($_SESSION['selected_enumerators'][$key]);
            $_SESSION['selected_enumerators'] = array_values($_SESSION['selected_enumerators']); // Re-index
        }
    }
    
    // Clear all selections
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_enumerators'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_enumerators'])]);
    exit;
}

// Clear all selections if requested via GET
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_enumerators'] = [];
}

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
            $created_at = date('Y-m-d H:i:s'); // Add creation timestamp
            
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
                        tradepoints = ?,
                        created_at = ?
                        WHERE email = ?";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt->bind_param(
                        'ssssssddsss',
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
                        $created_at,
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
                token,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16)); // Generate random token
            
            $insert_stmt->bind_param(
                'ssssssssddsss',
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
                $token,
                $created_at
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

// --- Fetch all enumerators with their assigned tradepoints with filtering and sorting ---
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
    WHERE 1=1
";

// Apply filters from GET parameters (for server-side filtering)
$filterConditions = [];
$params = [];
$types = '';

if (isset($_GET['filter_name']) && !empty($_GET['filter_name'])) {
    $filterConditions[] = "name LIKE ?";
    $params[] = '%' . $_GET['filter_name'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_country']) && !empty($_GET['filter_country'])) {
    $filterConditions[] = "country LIKE ?";
    $params[] = '%' . $_GET['filter_country'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_region']) && !empty($_GET['filter_region'])) {
    $filterConditions[] = "county_district LIKE ?";
    $params[] = '%' . $_GET['filter_region'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_email']) && !empty($_GET['filter_email'])) {
    $filterConditions[] = "email LIKE ?";
    $params[] = '%' . $_GET['filter_email'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_gender']) && !empty($_GET['filter_gender'])) {
    $filterConditions[] = "gender LIKE ?";
    $params[] = '%' . $_GET['filter_gender'] . '%';
    $types .= 's';
}

if (!empty($filterConditions)) {
    $query .= " AND " . implode(" AND ", $filterConditions);
}

// Apply sorting
$sortable_columns = ['id', 'name', 'country', 'county_district', 'email', 'gender', 'created_at'];
$default_sort_column = 'name';
$default_sort_order = 'ASC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

$query .= " ORDER BY $sort_column $sort_order";

// Prepare and execute query with filters and sorting
$stmt = $con->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
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
    
    // Create a searchable string for tradepoints filtering
    $enum['tradepoints_search'] = implode(' ', $assigned_tradepoints_array);
}
unset($enum);

// Filter by tradepoints if specified
if (isset($_GET['filter_tradepoints']) && !empty($_GET['filter_tradepoints'])) {
    $filter_tradepoints = strtolower($_GET['filter_tradepoints']);
    $enumerators_raw = array_filter($enumerators_raw, function($enum) use ($filter_tradepoints) {
        return stripos($enum['tradepoints_search'], $filter_tradepoints) !== false;
    });
    $enumerators_raw = array_values($enumerators_raw); // Re-index array
}

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

// Pagination Logic (AFTER filtering and sorting)
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
        flex-wrap: wrap;
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
    .btn-delete, .btn-export, .btn-import, .btn-bulk-export, .btn-clear-selections {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover, .btn-import:hover, .btn-bulk-export:hover, .btn-clear-selections:hover {
        background-color: #f8f9fa;
    }
    .btn-clear-selections {
        background-color: #ffc107;
        color: black;
    }
    .btn-clear-selections:hover {
        background-color: #e0a800;
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
    /* Sorting styles */
    .sortable {
        cursor: pointer;
        position: relative;
        user-select: none;
    }
    .sortable:hover {
        background-color: #f0f0f0;
    }
    .sort-icon {
        display: inline-block;
        margin-left: 5px;
        font-size: 0.8em;
        opacity: 0.7;
    }
    .sort-asc .sort-icon::after {
        content: "↑";
    }
    .sort-desc .sort-icon::after {
        content: "↓";
    }
    .sortable.sort-asc,
    .sortable.sort-desc {
        background-color: #e9ecef;
        font-weight: bold;
    }
    .date-added {
        font-size: 0.8em;
        color: #6c757d;
    }
    .selected-count {
        display: inline-block;
        background-color: rgba(180, 80, 50, 0.1);
        color: rgba(180, 80, 50, 1);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        margin-left: 5px;
        font-weight: bold;
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
                <?php if (count($_SESSION['selected_enumerators']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_enumerators']); ?></span>
                <?php endif; ?>
            </button>

            <button class="btn btn-clear-selections" onclick="clearAllSelections()">
                <i class="fas fa-times-circle" style="margin-right: 3px;"></i>
                Clear Selections
            </button>

            <form method="POST" action="export_current_page_enumerators.php" style="display: inline;">
                <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
                <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
                <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
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
                    <th class="sortable <?php echo getSortClass('id'); ?>" onclick="sortTable('id')">
                        ID
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('name'); ?>" onclick="sortTable('name')">
                        Name
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('country'); ?>" onclick="sortTable('country')">
                        Country
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('county_district'); ?>" onclick="sortTable('county_district')">
                        Region
                        <span class="sort-icon"></span>
                    </th>
                    <th>Assigned Tradepoints</th>
                    <th class="sortable <?php echo getSortClass('created_at'); ?>" onclick="sortTable('created_at')">
                        Date Added
                        <span class="sort-icon"></span>
                    </th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th></th>
                    <th>
                        <input type="text" class="filter-input" id="filterName" placeholder="Filter Name"
                               value="<?php echo isset($_GET['filter_name']) ? htmlspecialchars($_GET['filter_name']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterCountry" placeholder="Filter Country"
                               value="<?php echo isset($_GET['filter_country']) ? htmlspecialchars($_GET['filter_country']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterRegion" placeholder="Filter Region"
                               value="<?php echo isset($_GET['filter_region']) ? htmlspecialchars($_GET['filter_region']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterTradepoints" placeholder="Filter Tradepoints"
                               value="<?php echo isset($_GET['filter_tradepoints']) ? htmlspecialchars($_GET['filter_tradepoints']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="enumeratorTable">
                <?php if (empty($enumerators_paged)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                            No enumerators found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enumerators_paged as $enumerator): ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       class="row-checkbox" 
                                       value="<?php echo htmlspecialchars($enumerator['id']); ?>"
                                       <?php echo in_array($enumerator['id'], $_SESSION['selected_enumerators']) ? 'checked' : ''; ?>
                                       onchange="updateSelection(this, <?php echo $enumerator['id']; ?>)">
                            </td>
                            <td><?php echo htmlspecialchars($enumerator['id']); ?></td>
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
                            <td class="date-added">
                                <?php 
                                if (!empty($enumerator['created_at'])) {
                                    echo date('Y-m-d', strtotime($enumerator['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
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
                <?php if (count($_SESSION['selected_enumerators']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_enumerators']); ?> selected across all pages</span>
                <?php endif; ?>
                <?php if (!empty($sort_column)): ?>
                    <span class="text-muted ms-2">Sorted by: <?php echo ucfirst(str_replace('_', ' ', $sort_column)); ?> (<?php echo $sort_order; ?>)</span>
                <?php endif; ?>
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
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : getPageUrl($page - 1, $itemsPerPage, $sort_column, $sort_order); ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo getPageUrl($i, $itemsPerPage, $sort_column, $sort_order); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $totalPages) ? '#' : getPageUrl($page + 1, $itemsPerPage, $sort_column, $sort_order); ?>">Next</a>
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

<?php
// Helper function to generate page URLs with filters and sorting
function getPageUrl($pageNum, $itemsPerPage, $sortColumn = null, $sortOrder = null) {
    $url = '?page=' . $pageNum . '&limit=' . $itemsPerPage;
    
    // Add sort parameters if provided
    if ($sortColumn) {
        $url .= '&sort=' . urlencode($sortColumn);
    }
    if ($sortOrder) {
        $url .= '&order=' . urlencode($sortOrder);
    }
    
    // Add filter parameters if they exist
    $filterParams = ['filter_name', 'filter_country', 'filter_region', 'filter_tradepoints', 'filter_email', 'filter_gender'];
    foreach ($filterParams as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url .= '&' . $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $url;
}

// Helper function to get sort CSS class
function getSortClass($column) {
    $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $current_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
    
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? 'sort-asc' : 'sort-desc';
    }
    return '';
}
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize select all checkbox based on current page selections
    updateSelectAllCheckbox();
    
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

// Update selection function
function updateSelection(checkbox, id) {
    const isSelected = checkbox.checked;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_selection&id=${id}&selected=${isSelected}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Selection updated:', data);
        updateSelectAllCheckbox();
        updateSelectionCount();
    })
    .catch(error => console.error('Error updating selection:', error));
}

function updateSelectionCount() {
    // This would refresh the selection count display
    console.log('Selection count updated');
}

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

function sortTable(column) {
    const url = new URL(window.location);
    const currentSort = url.searchParams.get('sort');
    const currentOrder = url.searchParams.get('order');
    
    // Toggle order if clicking the same column
    if (currentSort === column) {
        const newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
        url.searchParams.set('order', newOrder);
    } else {
        // New column, default to ASC for most, DESC for ID and created_at
        const defaultOrder = (column === 'id' || column === 'created_at') ? 'DESC' : 'ASC';
        url.searchParams.set('sort', column);
        url.searchParams.set('order', defaultOrder);
    }
    
    // Reset to page 1 when sorting
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function applyFilters() {
    const filters = {
        name: document.getElementById('filterName').value,
        country: document.getElementById('filterCountry').value,
        region: document.getElementById('filterRegion').value,
        tradepoints: document.getElementById('filterTradepoints').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.name) url.searchParams.set('filter_name', filters.name);
    else url.searchParams.delete('filter_name');
    
    if (filters.country) url.searchParams.set('filter_country', filters.country);
    else url.searchParams.delete('filter_country');
    
    if (filters.region) url.searchParams.set('filter_region', filters.region);
    else url.searchParams.delete('filter_region');
    
    if (filters.tradepoints) url.searchParams.set('filter_tradepoints', filters.tradepoints);
    else url.searchParams.delete('filter_tradepoints');
    
    // Reset to page 1 when filtering
    url.searchParams.set('page', '1');
    
    // Navigate to filtered URL
    window.location.href = url.toString();
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAll');
    
    if (checkboxes.length === 0) {
        selectAll.checked = false;
        return;
    }
    
    // Check if all checkboxes on current page are checked
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const someChecked = Array.from(checkboxes).some(cb => cb.checked);
    
    selectAll.checked = allChecked;
    selectAll.indeterminate = !allChecked && someChecked;
}

document.getElementById('selectAll').addEventListener('change', function() {
    const isChecked = this.checked;
    const checkboxes = document.querySelectorAll('.row-checkbox');
    
    // Update all checkboxes on current page
    checkboxes.forEach(checkbox => {
        if (checkbox.checked !== isChecked) {
            checkbox.checked = isChecked;
            // Trigger the update for each checkbox
            if (checkbox.onchange) {
                checkbox.onchange();
            }
        }
    });
    
    // Clear all selections if unchecking
    if (!isChecked) {
        clearAllSelectionsSilent();
    }
});

function clearAllSelections() {
    if (confirm('Clear all selections across all pages?')) {
        clearAllSelectionsSilent();
        alert('All selections cleared.');
        location.reload();
    }
}

function clearAllSelectionsSilent() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_selection&clear_all=true'
    })
    .catch(error => console.error('Error clearing selections:', error));
}

function deleteSelected() {
    // Get count from session (across all pages)
    const selectedCount = <?php echo count($_SESSION['selected_enumerators']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one enumerator to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selectedCount + ' selected enumerator(s) across all pages?')) {
        // Pass all selected IDs from session
        fetch('delete_enumerator.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: <?php echo json_encode($_SESSION['selected_enumerators']); ?> })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Clear selections after deletion
                clearAllSelectionsSilent();
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
    const selectedCount = <?php echo count($_SESSION['selected_enumerators']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one enumerator to export.');
        return;
    }
    
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
    
    // Add sort parameters
    const sortInput = document.createElement('input');
    sortInput.type = 'hidden';
    sortInput.name = 'sort';
    sortInput.value = '<?php echo $sort_column; ?>';
    form.appendChild(sortInput);
    
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = 'order';
    orderInput.value = '<?php echo $sort_order; ?>';
    form.appendChild(orderInput);
    
    // Add selected IDs from session
    <?php foreach ($_SESSION['selected_enumerators'] as $id): ?>
        const idInput<?php echo $id; ?> = document.createElement('input');
        idInput<?php echo $id; ?>.type = 'hidden';
        idInput<?php echo $id; ?>.name = 'selected_ids[]';
        idInput<?php echo $id; ?>.value = '<?php echo $id; ?>';
        form.appendChild(idInput<?php echo $id; ?>);
    <?php endforeach; ?>
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include '../admin/includes/footer.php'; ?>