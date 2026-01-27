<?php
session_start();

// Initialize selected commodities in session if not exists
if (!isset($_SESSION['selected_commodities'])) {
    $_SESSION['selected_commodities'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_commodities'])) {
            $_SESSION['selected_commodities'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_commodities']);
        if ($key !== false) {
            unset($_SESSION['selected_commodities'][$key]);
            $_SESSION['selected_commodities'] = array_values($_SESSION['selected_commodities']); // Re-index
        }
    }
    
    // Clear all selections
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_commodities'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_commodities'])]);
    exit;
}

// Clear all selections if requested via GET
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_commodities'] = [];
}

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Handle CSV import
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
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
            
            // Debug: Log the raw row data
            // Uncomment this line to see what's in each row
            //error_log("Row $rowNumber data: " . print_r($data, true));
            
            // Skip completely empty rows
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) {
                continue; // Skip empty rows without counting as errors
            }
            
            // Validate required fields
            if (empty(trim($data[0]))) { // HS Code
                $errors[] = "Row $rowNumber: HS Code is required (found: '" . (isset($data[0]) ? $data[0] : 'null') . "')";
                $errorCount++;
                continue;
            }
            
            if (empty(trim($data[1]))) { // Category
                $errors[] = "Row $rowNumber: Category is required (found: '" . (isset($data[1]) ? $data[1] : 'null') . "')";
                $errorCount++;
                continue;
            }
            
            if (empty(trim($data[2]))) { // Commodity Name
                $errors[] = "Row $rowNumber: Commodity Name is required (found: '" . (isset($data[2]) ? $data[2] : 'null') . "')";
                $errorCount++;
                continue;
            }
            
            // Get or create category
            $category_name = trim($data[1]);
            $category_query = "SELECT id FROM commodity_categories WHERE name = ?";
            $category_stmt = $con->prepare($category_query);
            $category_stmt->bind_param('s', $category_name);
            $category_stmt->execute();
            $category_result = $category_stmt->get_result();
            
            if ($category_result->num_rows > 0) {
                $category_row = $category_result->fetch_assoc();
                $category_id = $category_row['id'];
            } else {
                // Create new category
                $insert_category = "INSERT INTO commodity_categories (name) VALUES (?)";
                $insert_stmt = $con->prepare($insert_category);
                $insert_stmt->bind_param('s', $category_name);
                $insert_stmt->execute();
                $category_id = $con->insert_id;
            }
            
            // Prepare commodity data
            $hs_code = trim($data[0]);
            $commodity_name = trim($data[2]);
            $variety = isset($data[3]) ? trim($data[3]) : '';
            
            // Handle units JSON - FIXED VERSION
            $units_array = [];
            $packaging_units_csv = isset($data[4]) ? trim($data[4]) : '';
            if (!empty($packaging_units_csv)) {
                $packaging_units = array_map('trim', explode(',', $packaging_units_csv)); // Trim all elements
                foreach ($packaging_units as $pu) {
                    // Updated regex to handle optional spaces and be more flexible
                    if (preg_match('/(\d+)\s*(\w+)/', $pu, $matches)) {
                        $units_array[] = array(
                            'size' => trim($matches[1]),
                            'unit' => trim($matches[2])
                        );
                    } else {
                        // Log warning for unparseable units
                        $errors[] = "Row $rowNumber: Warning - Could not parse unit format: '$pu'";
                    }
                }
            }
            $units_json = json_encode($units_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($units_json === false) {
                $errors[] = "Row $rowNumber: Failed to encode units as JSON - " . json_last_error_msg();
                $errorCount++;
                continue;
            }
            // Clean the JSON string of any potential invisible characters
            $units_json = trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $units_json));
            
            // Handle aliases and countries JSON - IMPROVED VERSION
            $alias_country_pairs = [];
            $aliases_countries_csv = isset($data[5]) ? trim($data[5]) : '';
            if (!empty($aliases_countries_csv)) {
                $aliases_countries = array_map('trim', explode(',', $aliases_countries_csv)); // Trim all elements
                foreach ($aliases_countries as $ac) {
                    if (strpos($ac, ':') !== false) {
                        $parts = explode(':', $ac, 2);
                        if (count($parts) == 2) {
                            $alias_country_pairs[] = array(
                                'alias' => trim($parts[0]),
                                'country' => trim($parts[1])
                            );
                        }
                    } else {
                        // Log warning for unparseable alias:country format
                        $errors[] = "Row $rowNumber: Warning - Could not parse alias:country format: '$ac'";
                    }
                }
            }
            $aliases_json = json_encode($alias_country_pairs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($aliases_json === false) {
                $errors[] = "Row $rowNumber: Failed to encode aliases as JSON - " . json_last_error_msg();
                $errorCount++;
                continue;
            }
            // Clean the JSON string of any potential invisible characters
            $aliases_json = trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $aliases_json));
            
            // Get unique countries for country column
            $unique_countries = [];
            if (!empty($alias_country_pairs)) {
                $countries = array_column($alias_country_pairs, 'country');
                $unique_countries = array_values(array_unique(array_filter($countries))); // Remove empty values
            }
            $countries_json = json_encode($unique_countries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($countries_json === false) {
                $errors[] = "Row $rowNumber: Failed to encode countries as JSON - " . json_last_error_msg();
                $errorCount++;
                continue;
            }
            // Clean the JSON string of any potential invisible characters
            $countries_json = trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $countries_json));
            
            // Enhanced JSON validation with proper error checking
            $units_decoded = json_decode($units_json);
            $aliases_decoded = json_decode($aliases_json);
            $countries_decoded = json_decode($countries_json);
            
            if ($units_json !== '[]' && $units_decoded === null) {
                $errors[] = "Row $rowNumber: Invalid units JSON format - " . json_last_error_msg();
                $errorCount++;
                continue;
            }
            if ($aliases_json !== '[]' && $aliases_decoded === null) {
                $errors[] = "Row $rowNumber: Invalid aliases JSON format - " . json_last_error_msg();
                $errorCount++;
                continue;
            }
            if ($countries_json !== '[]' && $countries_decoded === null) {
                $errors[] = "Row $rowNumber: Invalid countries JSON format - " . json_last_error_msg();
                $errorCount++;
                continue;
            }
            
            // Check if commodity already exists
            $check_query = "SELECT id FROM commodities WHERE hs_code = ? AND commodity_name = ?";
            $check_stmt = $con->prepare($check_query);
            $check_stmt->bind_param('ss', $hs_code, $commodity_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if (isset($_POST['overwrite_existing'])) {
                    // Update existing commodity - Direct JSON insertion without CAST
                    $update_query = "UPDATE commodities SET 
                        category_id = ?, 
                        variety = ?, 
                        units = ?, 
                        commodity_alias = ?, 
                        country = ? 
                        WHERE hs_code = ? AND commodity_name = ?";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $update_stmt->bind_param(
                        'issssss',
                        $category_id,
                        $variety,
                        $units_json,
                        $aliases_json,
                        $countries_json,
                        $hs_code,
                        $commodity_name
                    );
                    
                    if ($update_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                        $errorCount++;
                    }
                    $update_stmt->close();
                } else {
                    $errors[] = "Row $rowNumber: Commodity with HS Code '$hs_code' and name '$commodity_name' already exists (use overwrite option to update)";
                    $errorCount++;
                }
                continue;
            }
            
            // Insert commodity - Direct JSON insertion without CAST
            $insert_query = "INSERT INTO commodities (
                hs_code, 
                category_id, 
                commodity_name, 
                variety, 
                units, 
                commodity_alias, 
                country
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }
            
            // Send JSON as strings - MySQL will handle the conversion automatically
            $insert_stmt->bind_param(
                'sisssss',
                $hs_code,
                $category_id,
                $commodity_name,
                $variety,
                $units_json,
                $aliases_json,
                $countries_json
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
                $import_message = "Successfully imported $successCount commodities with $warningCount warnings. Warnings: " . implode('<br>', $errors);
                $import_status = 'warning';
            } else {
                $import_message = "Successfully imported $successCount commodities.";
                $import_status = 'success';
            }
        } else {
            $con->rollback();
            $import_message = "Import rolled back due to $criticalErrors critical errors. Processed $successCount rows successfully. Errors: " . implode('<br>', $errors);
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

// --- Fetch all data for the table with filtering ---
$query = "
    SELECT
        c.id,
        c.hs_code,
        cc.name AS category,
        c.commodity_name,
        c.variety,
        c.image_url
    FROM
        commodities c
    JOIN
        commodity_categories cc ON c.category_id = cc.id
    WHERE 1=1
";

// Apply filters from GET parameters (for server-side filtering)
$filterConditions = [];
$params = [];
$types = '';

if (isset($_GET['filter_id']) && !empty($_GET['filter_id'])) {
    $filterConditions[] = "c.id = ?";
    $params[] = $_GET['filter_id'];
    $types .= 's';
}

if (isset($_GET['filter_hs_code']) && !empty($_GET['filter_hs_code'])) {
    $filterConditions[] = "c.hs_code LIKE ?";
    $params[] = '%' . $_GET['filter_hs_code'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_category']) && !empty($_GET['filter_category'])) {
    $filterConditions[] = "cc.name LIKE ?";
    $params[] = '%' . $_GET['filter_category'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_commodity']) && !empty($_GET['filter_commodity'])) {
    $filterConditions[] = "c.commodity_name LIKE ?";
    $params[] = '%' . $_GET['filter_commodity'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_variety']) && !empty($_GET['filter_variety'])) {
    $filterConditions[] = "c.variety LIKE ?";
    $params[] = '%' . $_GET['filter_variety'] . '%';
    $types .= 's';
}

if (!empty($filterConditions)) {
    $query .= " AND " . implode(" AND ", $filterConditions);
}

$query .= " ORDER BY c.id";

// Prepare and execute query with filters
$stmt = $con->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$commodities = $result->fetch_all(MYSQLI_ASSOC);

// Pagination Logic (AFTER filtering)
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($commodities);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$commodities_paged = array_slice($commodities, $startIndex, $itemsPerPage);

// --- Fetch counts for summary boxes ---
$total_commodities_query = "SELECT COUNT(*) AS total FROM commodities";
$total_commodities_result = $con->query($total_commodities_query);
$total_commodities = 0;
if ($total_commodities_result) {
    $row = $total_commodities_result->fetch_assoc();
    $total_commodities = $row['total'];
}

// FIXED: Use LIKE queries to count categories that start with specific patterns
$cereals_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id IN (SELECT id FROM commodity_categories WHERE name LIKE 'Cereal%')";
$cereals_result = $con->query($cereals_query);
$cereals_count = 0;
if ($cereals_result) {
    $row = $cereals_result->fetch_assoc();
    $cereals_count = $row['total'];
}

$pulses_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id IN (SELECT id FROM commodity_categories WHERE name LIKE 'Pulse%')";
$pulses_result = $con->query($pulses_query);
$pulses_count = 0;
if ($pulses_result) {
    $row = $pulses_result->fetch_assoc();
    $pulses_count = $row['total'];
}

$oil_seeds_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id IN (SELECT id FROM commodity_categories WHERE name LIKE 'Oil%')";
$oil_seeds_result = $con->query($oil_seeds_query);
$oil_seeds_count = 0;
if ($oil_seeds_result) {
    $row = $oil_seeds_result->fetch_assoc();
    $oil_seeds_count = $row['total'];
}
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
        background-color: #9b59b6;
        color: white;
    }
    .cereals-icon {
        background-color: #f39c12;
        color: white;
    }
    .pulses-icon {
        background-color: #27ae60;
        color: white;
    }
    .oil-seeds-icon {
        background-color: #e74c3c;
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
    .image-preview {
        width: 40px;
        height: 40px;
        border-radius: 5px;
        object-fit: cover;
        cursor: pointer;
    }
    .no-image {
        color: #6c757d;
        font-style: italic;
        font-size: 0.9em;
    }
    .alert {
        margin-bottom: 20px;
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
    .download-template {
        display: inline-block;
        margin-top: 10px;
        color: rgba(180, 80, 50, 1);
        text-decoration: none;
    }
    .download-template:hover {
        text-decoration: underline;
    }
    /* Compact table rows */
    .table {
        font-size: 0.875rem;
    }
    .table thead th {
        padding: 0.5rem;
        vertical-align: middle;
        line-height: 1.2;
    }
    .table tbody td {
        padding: 0.4rem 0.5rem;
        vertical-align: middle;
        line-height: 1.3;
    }
    .filter-row input.filter-input {
        padding: 0.25rem 0.5rem;
        font-size: 0.85rem;
        height: 28px;
    }
    .image-preview {
        width: 30px;
        height: 30px;
    }
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
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
    <div class="text-wrapper-8"><h3>Commodities Management</h3></div>
    <p class="p">Manage everything related to Agricultural Commodities</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="stats-title">Total Commodities</div>
            <div class="stats-number"><?php echo $total_commodities; ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon cereals-icon">
                <i class="fas fa-wheat-awn"></i>
            </div>
            <div class="stats-title">Cereals</div>
            <div class="stats-number"><?php echo $cereals_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon pulses-icon">
                <i class="fas fa-dot-circle"></i>
            </div>
            <div class="stats-title">Pulses</div>
            <div class="stats-number"><?php echo $pulses_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon oil-seeds-icon">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stats-title">Oil Seeds</div>
            <div class="stats-number"><?php echo $oil_seeds_count; ?></div>
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
            <a href="add_commodity.php" class="btn btn-add-new">
                <i class="fas fa-plus" style="margin-right: 5px;"></i>
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()">
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
                <?php if (count($_SESSION['selected_commodities']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_commodities']); ?></span>
                <?php endif; ?>
            </button>

            <button class="btn btn-clear-selections" onclick="clearAllSelections()">
                <i class="fas fa-times-circle" style="margin-right: 3px;"></i>
                Clear Selections
            </button>

            <form method="POST" action="export_current_page_commodities.php" style="display: inline;">
                <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
                <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
                <button type="submit" class="btn-export">
                    <i class="fas fa-download" style="margin-right: 3px;"></i> Export (Current Page)
                </button>
            </form>

            <form method="POST" action="bulk_export_commodities.php" style="display: inline;">
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
                    <th>ID</th>
                    <th>HS Code</th>
                    <th>Category</th>
                    <th>Commodity</th>
                    <th>Variety</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th>
                        <input type="text" class="filter-input" id="filterId" placeholder="Filter ID"
                               value="<?php echo isset($_GET['filter_id']) ? htmlspecialchars($_GET['filter_id']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterHsCode" placeholder="Filter HS Code"
                               value="<?php echo isset($_GET['filter_hs_code']) ? htmlspecialchars($_GET['filter_hs_code']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterCategory" placeholder="Filter Category"
                               value="<?php echo isset($_GET['filter_category']) ? htmlspecialchars($_GET['filter_category']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterCommodity" placeholder="Filter Commodity"
                               value="<?php echo isset($_GET['filter_commodity']) ? htmlspecialchars($_GET['filter_commodity']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterVariety" placeholder="Filter Variety"
                               value="<?php echo isset($_GET['filter_variety']) ? htmlspecialchars($_GET['filter_variety']) : ''; ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="commodityTable">
                <?php foreach ($commodities_paged as $commodity): ?>
                    <tr>
                        <td>
                            <input type="checkbox" 
                                   class="row-checkbox" 
                                   value="<?php echo htmlspecialchars($commodity['id']); ?>"
                                   <?php echo in_array($commodity['id'], $_SESSION['selected_commodities']) ? 'checked' : ''; ?>
                                   onchange="updateSelection(this, <?php echo $commodity['id']; ?>)">
                        </td>
                        <td><?php echo htmlspecialchars($commodity['id']); ?></td>
                        <td><?php echo htmlspecialchars($commodity['hs_code']); ?></td>
                        <td><?php echo htmlspecialchars($commodity['category']); ?></td>
                        <td><?php echo htmlspecialchars($commodity['commodity_name']); ?></td>
                        <td><?php echo htmlspecialchars($commodity['variety']); ?></td>
                        <td>
                            <?php if (!empty($commodity['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($commodity['image_url']); ?>" 
                                    alt="<?php echo htmlspecialchars($commodity['commodity_name']); ?>" 
                                    class="image-preview" 
                                    onclick="showImageModal('<?php echo htmlspecialchars($commodity['image_url']); ?>', '<?php echo htmlspecialchars($commodity['commodity_name']); ?>')">
                            <?php else: ?>
                                <span class="no-image">No Image</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="edit_commodity.php?id=<?php echo $commodity['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?php echo $startIndex + 1; ?> to <?php echo min($startIndex + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> items
                <?php if (count($_SESSION['selected_commodities']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_commodities']); ?> selected across all pages</span>
                <?php endif; ?>
            </div>
            <div>
                <label for="itemsPerPage">Show:</label>
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                    <option value="7" <?php echo ($itemsPerPage == 7) ? 'selected' : ''; ?>>7</option>
                    <option value="10" <?php echo ($itemsPerPage == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo ($itemsPerPage == 20) ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo ($itemsPerPage == 50) ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo ($itemsPerPage == 100) ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : getPageUrl($page - 1, $itemsPerPage); ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo getPageUrl($i, $itemsPerPage); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $totalPages) ? '#' : getPageUrl($page + 1, $itemsPerPage); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Commodity Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 400px;">
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Commodities</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Format Instructions</h5>
                    <p>Your CSV file should have the following columns in order:</p>
                    <ol>
                        <li><strong>HS Code</strong> (required)</li>
                        <li><strong>Category</strong> (required, will be created if doesn't exist)</li>
                        <li><strong>Commodity Name</strong> (required)</li>
                        <li><strong>Variety</strong> (optional)</li>
                        <li><strong>Packaging & Units</strong> (optional, comma-separated, e.g. "10kg, 25kg, 50kg")</li>
                        <li><strong>Aliases & Countries</strong> (optional, comma-separated pairs, e.g. "Maize:Kenya, Corn:USA")</li>
                    </ol>
                    <a href="downloads/commodities_template.csv" class="download-template">
                        <i class="fas fa-download"></i> Download CSV Template
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
                            Overwrite existing commodities with matching HS Code and Name
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
// Helper function to generate page URLs with filters
function getPageUrl($pageNum, $itemsPerPage) {
    $url = '?page=' . $pageNum . '&limit=' . $itemsPerPage;
    
    // Add filter parameters if they exist
    $filterParams = ['filter_id', 'filter_hs_code', 'filter_category', 'filter_commodity', 'filter_variety'];
    foreach ($filterParams as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url .= '&' . $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $url;
}
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize filter inputs with current values
    const filterInputs = document.querySelectorAll('.filter-input');
    
    // Initialize select all checkbox based on current page selections
    updateSelectAllCheckbox();
    
    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Commodities');
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
    // In a real implementation, you might update a counter on the page
    console.log('Selection count updated');
}

function applyFilters() {
    const filters = {
        id: document.getElementById('filterId').value,
        hsCode: document.getElementById('filterHsCode').value,
        category: document.getElementById('filterCategory').value,
        commodity: document.getElementById('filterCommodity').value,
        variety: document.getElementById('filterVariety').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.id) url.searchParams.set('filter_id', filters.id);
    else url.searchParams.delete('filter_id');
    
    if (filters.hsCode) url.searchParams.set('filter_hs_code', filters.hsCode);
    else url.searchParams.delete('filter_hs_code');
    
    if (filters.category) url.searchParams.set('filter_category', filters.category);
    else url.searchParams.delete('filter_category');
    
    if (filters.commodity) url.searchParams.set('filter_commodity', filters.commodity);
    else url.searchParams.delete('filter_commodity');
    
    if (filters.variety) url.searchParams.set('filter_variety', filters.variety);
    else url.searchParams.delete('filter_variety');
    
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

function showImageModal(imageUrl, commodityName) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    document.getElementById('modalImage').src = imageUrl;
    document.getElementById('modalImage').alt = commodityName;
    document.getElementById('imageModalLabel').textContent = commodityName;
    modal.show();
}

function deleteSelected() {
    // Get count from session (across all pages)
    const selectedCount = <?php echo count($_SESSION['selected_commodities']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one commodity to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selectedCount + ' selected commodity(ies) across all pages?')) {
        // Pass all selected IDs from session
        fetch('delete_commodity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: <?php echo json_encode($_SESSION['selected_commodities']); ?> })
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
    const selectedCount = <?php echo count($_SESSION['selected_commodities']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one commodity to export.');
        return;
    }
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_commodities.php';
    
    // Add format parameter
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'export_format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    // Add selected IDs from session
    <?php foreach ($_SESSION['selected_commodities'] as $id): ?>
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