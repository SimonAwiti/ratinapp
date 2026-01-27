<?php
session_start();

// Initialize selected tradepoints in session if not exists
if (!isset($_SESSION['selected_tradepoints'])) {
    $_SESSION['selected_tradepoints'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_tradepoints'])) {
            $_SESSION['selected_tradepoints'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_tradepoints']);
        if ($key !== false) {
            unset($_SESSION['selected_tradepoints'][$key]);
            $_SESSION['selected_tradepoints'] = array_values($_SESSION['selected_tradepoints']); // Re-index
        }
    }
    
    // Clear all selections
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_tradepoints'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_tradepoints'])]);
    exit;
}

// Clear all selections if requested via GET
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_tradepoints'] = [];
}

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
            
            // Skip completely empty rows
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) {
                continue; // Skip empty rows without counting as errors
            }
            
            // Process based on tradepoint type
            switch ($tradepoint_type) {
                case 'Markets':
                    // Validate required fields for Markets
                    if (empty(trim($data[0]))) { // Market Name
                        $errors[] = "Row $rowNumber: Market Name is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[1]))) { // Category
                        $errors[] = "Row $rowNumber: Category is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[2]))) { // Type
                        $errors[] = "Row $rowNumber: Type is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[3]))) { // Country
                        $errors[] = "Row $rowNumber: Country is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[4]))) { // County/District
                        $errors[] = "Row $rowNumber: County/District is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[5]))) { // Longitude
                        $errors[] = "Row $rowNumber: Longitude is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[6]))) { // Latitude
                        $errors[] = "Row $rowNumber: Latitude is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[7]))) { // Radius
                        $errors[] = "Row $rowNumber: Radius is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[8]))) { // Currency
                        $errors[] = "Row $rowNumber: Currency is required";
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
                    $image_urls = ''; // Set empty image_urls for import
                    $created_at = date('Y-m-d H:i:s'); // Add creation timestamp
                    
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
                                additional_datasource = ?,
                                image_urls = ?,
                                created_at = ?
                                WHERE market_name = ?";
                            
                            $update_stmt = $con->prepare($update_query);
                            if (!$update_stmt) {
                                $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                                $errorCount++;
                                continue;
                            }
                            
                            $update_stmt->bind_param(
                                'ssssdddssssss',
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
                                $image_urls,
                                $created_at,
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
                        image_urls,
                        tradepoint,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Markets', ?)";
                    
                    $insert_stmt = $con->prepare($insert_query);
                    if (!$insert_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $insert_stmt->bind_param(
                        'ssssdddssssss',
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
                        $additional_datasource,
                        $image_urls,
                        $created_at
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
                    if (empty(trim($data[0]))) { // Miller Name
                        $errors[] = "Row $rowNumber: Miller Name is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[1]))) { // Country
                        $errors[] = "Row $rowNumber: Country is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[2]))) { // County/District
                        $errors[] = "Row $rowNumber: County/District is required";
                        $errorCount++;
                        continue;
                    }
                    
                    // Prepare miller data
                    $miller_name = trim($data[0]);
                    $country = trim($data[1]);
                    $county_district = trim($data[2]);
                    $millers_csv = isset($data[3]) ? trim($data[3]) : '';
                    $created_at = date('Y-m-d H:i:s'); // Add creation timestamp
                    
                    // Process millers (comma-separated, max 2)
                    $millers_array = [];
                    if (!empty($millers_csv)) {
                        $millers = array_map('trim', explode(',', $millers_csv));
                        if (count($millers) > 2) {
                            $errors[] = "Row $rowNumber: Maximum of 2 millers allowed (found " . count($millers) . ")";
                            $errorCount++;
                            continue;
                        }
                        $millers_array = $millers;
                    }
                    
                    $millers_json = json_encode($millers_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($millers_json === false) {
                        $errors[] = "Row $rowNumber: Failed to encode millers as JSON - " . json_last_error_msg();
                        $errorCount++;
                        continue;
                    }
                    
                    // Auto-determine currency from country
                    $currency = getCurrencyFromCountry($country);
                    
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
                                miller = ?,
                                currency = ?,
                                created_at = ?
                                WHERE miller_name = ?";
                            
                            $update_stmt = $con->prepare($update_query);
                            if (!$update_stmt) {
                                $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                                $errorCount++;
                                continue;
                            }
                            
                            $update_stmt->bind_param(
                                'ssssss',
                                $country,
                                $county_district,
                                $millers_json,
                                $currency,
                                $created_at,
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
                        currency,
                        tradepoint,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, 'Millers', ?)";
                    
                    $insert_stmt = $con->prepare($insert_query);
                    if (!$insert_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $insert_stmt->bind_param(
                        'ssssss',
                        $miller_name,
                        $country,
                        $county_district,
                        $millers_json,
                        $currency,
                        $created_at
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
                    if (empty(trim($data[0]))) { // Name
                        $errors[] = "Row $rowNumber: Name is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[1]))) { // Country
                        $errors[] = "Row $rowNumber: Country is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[2]))) { // County
                        $errors[] = "Row $rowNumber: County is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[3]))) { // Longitude
                        $errors[] = "Row $rowNumber: Longitude is required";
                        $errorCount++;
                        continue;
                    }
                    if (empty(trim($data[4]))) { // Latitude
                        $errors[] = "Row $rowNumber: Latitude is required";
                        $errorCount++;
                        continue;
                    }
                    
                    // Prepare border point data
                    $name = trim($data[0]);
                    $country = trim($data[1]);
                    $county = trim($data[2]);
                    $longitude = floatval(trim($data[3]));
                    $latitude = floatval(trim($data[4]));
                    $created_at = date('Y-m-d H:i:s'); // Add creation timestamp
                    
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
                                latitude = ?,
                                created_at = ?
                                WHERE name = ?";
                            
                            $update_stmt = $con->prepare($update_query);
                            if (!$update_stmt) {
                                $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                                $errorCount++;
                                continue;
                            }
                            
                            $update_stmt->bind_param(
                                'ssddss',
                                $country,
                                $county,
                                $longitude,
                                $latitude,
                                $created_at,
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
                        tradepoint,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, 'Border Points', ?)";
                    
                    $insert_stmt = $con->prepare($insert_query);
                    if (!$insert_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $insert_stmt->bind_param(
                        'sssdds',
                        $name,
                        $country,
                        $county,
                        $longitude,
                        $latitude,
                        $created_at
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
                $import_message = "Successfully imported " . $successCount . " tradepoints with " . $warningCount . " warnings. Warnings: " . implode('<br>', $errors);
                $import_status = 'warning';
            } else {
                $import_message = "Successfully imported " . $successCount . " tradepoints.";
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

// Function to get currency from country
function getCurrencyFromCountry($country) {
    $currency_map = [
        'Kenya' => 'KES',
        'Uganda' => 'UGX',
        'Tanzania' => 'TZS',
        'Rwanda' => 'RWF',
        'Burundi' => 'BIF',
        'South Sudan' => 'SSP',
        'Ethiopia' => 'ETB',
        'Somalia' => 'SOS',
        'Democratic Republic of Congo' => 'CDF',
        'Congo' => 'CDF',
        'DRC' => 'CDF',
        'Sudan' => 'SDG',
        'Egypt' => 'EGP',
        'Zambia' => 'ZMW',
        'Zimbabwe' => 'ZWL',
        'Malawi' => 'MWK',
        'Mozambique' => 'MZN',
        'Angola' => 'AOA',
        'Nigeria' => 'NGN',
        'Ghana' => 'GHS',
        'South Africa' => 'ZAR',
        'Botswana' => 'BWP',
        'Namibia' => 'NAD',
        'Lesotho' => 'LSL',
        'Eswatini' => 'SZL',
        'Madagascar' => 'MGA',
        'Mauritius' => 'MUR',
        'Seychelles' => 'SCR'
    ];
    
    // Clean the country name and try to match
    $country = trim($country);
    
    // Exact match first
    if (isset($currency_map[$country])) {
        return $currency_map[$country];
    }
    
    // Case-insensitive search
    foreach ($currency_map as $map_country => $currency) {
        if (strtolower($map_country) === strtolower($country)) {
            return $currency;
        }
    }
    
    // Partial match (if country contains the key)
    foreach ($currency_map as $map_country => $currency) {
        if (stripos($country, $map_country) !== false) {
            return $currency;
        }
    }
    
    // Default to USD if no match found
    return 'USD';
}

// --- Fetch all data for the table with filtering and sorting ---
$base_query = "
    SELECT
        id,
        market_name AS name,
        'Markets' AS tradepoint_type,
        country AS admin0,
        county_district AS admin1,
        created_at
    FROM markets
    
    UNION ALL
    
    SELECT
        id,
        name AS name,
        'Border Points' AS tradepoint_type,
        country AS admin0,
        county AS admin1,
        created_at
    FROM border_points
    
    UNION ALL
    
    SELECT
        id,
        miller_name AS name,
        'Millers' AS tradepoint_type,
        country AS admin0,
        county_district AS admin1,
        created_at
    FROM miller_details
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

if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filterConditions[] = "tradepoint_type LIKE ?";
    $params[] = '%' . $_GET['filter_type'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_country']) && !empty($_GET['filter_country'])) {
    $filterConditions[] = "admin0 LIKE ?";
    $params[] = '%' . $_GET['filter_country'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_region']) && !empty($_GET['filter_region'])) {
    $filterConditions[] = "admin1 LIKE ?";
    $params[] = '%' . $_GET['filter_region'] . '%';
    $types .= 's';
}

// Build the query with filters
$query = "SELECT * FROM ($base_query) AS combined WHERE 1=1";
if (!empty($filterConditions)) {
    $query .= " AND " . implode(" AND ", $filterConditions);
}

// Apply sorting
$sortable_columns = ['id', 'name', 'tradepoint_type', 'admin0', 'admin1', 'created_at'];
$default_sort_column = 'name';
$default_sort_order = 'ASC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

$query .= " ORDER BY $sort_column $sort_order";

// Prepare and execute query with filters and sorting
if (!empty($params)) {
    $stmt = $con->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $tradepoints = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        // Fallback if prepare fails
        $result = $con->query($query);
        $tradepoints = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
} else {
    $result = $con->query($query);
    $tradepoints = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Pagination Logic (AFTER filtering and sorting)
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($tradepoints);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$tradepoints_paged = array_slice($tradepoints, $startIndex, $itemsPerPage);

// --- Fetch counts for summary boxes ---
$total_tradepoints_query = "SELECT COUNT(*) AS total FROM (
    SELECT id FROM markets 
    UNION ALL 
    SELECT id FROM border_points 
    UNION ALL 
    SELECT id FROM miller_details
) AS combined";
$total_tradepoints_result = $con->query($total_tradepoints_query);
$total_tradepoints_row = $total_tradepoints_result->fetch_assoc();
$total_tradepoints = $total_tradepoints_row['total'];

$markets_query = "SELECT COUNT(*) AS total FROM markets";
$markets_result = $con->query($markets_query);
$markets_row = $markets_result->fetch_assoc();
$markets_count = $markets_row['total'];

$border_points_query = "SELECT COUNT(*) AS total FROM border_points";
$border_points_result = $con->query($border_points_query);
$border_points_row = $border_points_result->fetch_assoc();
$border_points_count = $border_points_row['total'];

$millers_query = "SELECT COUNT(*) AS total FROM miller_details";
$millers_result = $con->query($millers_query);
$millers_row = $millers_result->fetch_assoc();
$millers_count = $millers_row['total'];
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
            <div class="stats-number"><?php echo $total_tradepoints; ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon markets-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="stats-title">Markets</div>
            <div class="stats-number"><?php echo $markets_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon border-icon">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stats-title">Border Points</div>
            <div class="stats-number"><?php echo $border_points_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon millers-icon">
                <i class="fas fa-industry"></i>
            </div>
            <div class="stats-title">Millers</div>
            <div class="stats-number"><?php echo $millers_count; ?></div>
        </div>
    </div>
</div>

<?php 
// Show import message if exists
if (isset($import_message)): ?>
    <div class="alert alert-<?php echo $import_status; ?>">
        <?php echo $import_message; ?>
    </div>
<?php endif; ?>

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
                <?php if (count($_SESSION['selected_tradepoints']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_tradepoints']); ?></span>
                <?php endif; ?>
            </button>

            <button class="btn btn-clear-selections" onclick="clearAllSelections()">
                <i class="fas fa-times-circle" style="margin-right: 3px;"></i>
                Clear Selections
            </button>

            <form method="POST" action="export_current_page_tradepoints.php" style="display: inline;">
                <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
                <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
                <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
                <button type="submit" class="btn-export">
                    <i class="fas fa-download" style="margin-right: 3px;"></i> Export (Current Page)
                </button>
            </form>

            <form method="POST" action="bulk_export_tradepoints.php" style="display: inline;">
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
                    <th class="sortable <?php echo getSortClass('tradepoint_type'); ?>" onclick="sortTable('tradepoint_type')">
                        Type
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('admin0'); ?>" onclick="sortTable('admin0')">
                        Country
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('admin1'); ?>" onclick="sortTable('admin1')">
                        Region
                        <span class="sort-icon"></span>
                    </th>
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
                        <input type="text" class="filter-input" id="filterType" placeholder="Filter Type"
                               value="<?php echo isset($_GET['filter_type']) ? htmlspecialchars($_GET['filter_type']) : ''; ?>"
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
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tradepointTable">
                <?php if (empty($tradepoints_paged)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                            No tradepoints found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tradepoints_paged as $tradepoint): ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       class="row-checkbox" 
                                       value="<?php echo htmlspecialchars($tradepoint['id']); ?>"
                                       <?php echo in_array($tradepoint['id'], $_SESSION['selected_tradepoints']) ? 'checked' : ''; ?>
                                       onchange="updateSelection(this, <?php echo $tradepoint['id']; ?>)">
                            </td>
                            <td><?php echo htmlspecialchars($tradepoint['id']); ?></td>
                            <td><?php echo htmlspecialchars($tradepoint['name']); ?></td>
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
                                <span class="type-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($tradepoint['tradepoint_type']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($tradepoint['admin0']); ?></td>
                            <td><?php echo htmlspecialchars($tradepoint['admin1']); ?></td>
                            <td class="date-added">
                                <?php 
                                if (!empty($tradepoint['created_at'])) {
                                    echo date('Y-m-d', strtotime($tradepoint['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
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
                                <a href="<?php echo $editPage; ?>?id=<?php echo htmlspecialchars($tradepoint['id']); ?>">
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
                <?php if (count($_SESSION['selected_tradepoints']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_tradepoints']); ?> selected across all pages</span>
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
                            <li><strong>market_name</strong> (required) - Name of the market</li>
                            <li><strong>category</strong> (required) - Urban, Rural, Border, etc.</li>
                            <li><strong>type</strong> (required) - Retail, Wholesale, etc.</li>
                            <li><strong>country</strong> (required) - Country name</li>
                            <li><strong>county_district</strong> (required) - County or district name</li>
                            <li><strong>longitude</strong> (required) - Geographic coordinate</li>
                            <li><strong>latitude</strong> (required) - Geographic coordinate</li>
                            <li><strong>radius</strong> (required) - Coverage radius in km</li>
                            <li><strong>currency</strong> (required) - Currency code (KES, UGX, etc.)</li>
                            <li><strong>primary_commodities</strong> (optional) - Comma-separated list</li>
                            <li><strong>additional_datasource</strong> (optional) - Data source information</li>
                        </ol>
                        <p><strong>Example:</strong> <code>"Nairobi Market","Urban","Retail","Kenya","Nairobi",36.82,-1.29,5,"KES","Maize,Beans","Government"</code></p>
                        <a href="downloads/markets_template.csv" class="download-template">
                            <i class="fas fa-download"></i> Download Markets Template
                        </a>
                    </div>
                    
                    <div id="millersInstructions" class="type-instructions" style="display: none;">
                        <h6>Millers CSV Format</h6>
                        <p>Your CSV file should have these columns in order:</p>
                        <ol>
                            <li><strong>miller_name</strong> (required) - Name of the milling company</li>
                            <li><strong>country</strong> (required) - Country name</li>
                            <li><strong>county_district</strong> (required) - County or district name</li>
                            <li><strong>millers</strong> (optional) - Comma-separated list of miller brands (max 2)</li>
                        </ol>
                        <p><strong>Example:</strong> <code>"Unga Group","Kenya","Nairobi","Unga Millers,Capwell Millers"</code></p>
                        <a href="downloads/millers_template.csv" class="download-template">
                            <i class="fas fa-download"></i> Download Millers Template
                        </a>
                    </div>
                    
                    <div id="borderInstructions" class="type-instructions" style="display: none;">
                        <h6>Border Points CSV Format</h6>
                        <p>Your CSV file should have these columns in order:</p>
                        <ol>
                            <li><strong>name</strong> (required) - Name of the border point</li>
                            <li><strong>country</strong> (required) - Country name</li>
                            <li><strong>county</strong> (required) - County name</li>
                            <li><strong>longitude</strong> (required) - Geographic coordinate</li>
                            <li><strong>latitude</strong> (required) - Geographic coordinate</li>
                        </ol>
                        <p><strong>Example:</strong> <code>"Namanga Border","Kenya","Kajiado",36.78,-2.55</code></p>
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
    $filterParams = ['filter_name', 'filter_type', 'filter_country', 'filter_region'];
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
        type: document.getElementById('filterType').value,
        country: document.getElementById('filterCountry').value,
        region: document.getElementById('filterRegion').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.name) url.searchParams.set('filter_name', filters.name);
    else url.searchParams.delete('filter_name');
    
    if (filters.type) url.searchParams.set('filter_type', filters.type);
    else url.searchParams.delete('filter_type');
    
    if (filters.country) url.searchParams.set('filter_country', filters.country);
    else url.searchParams.delete('filter_country');
    
    if (filters.region) url.searchParams.set('filter_region', filters.region);
    else url.searchParams.delete('filter_region');
    
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
    const selectedCount = <?php echo count($_SESSION['selected_tradepoints']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one tradepoint to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selectedCount + ' selected tradepoint(s) across all pages?')) {
        // Pass all selected IDs from session
        fetch('delete_tradepoint.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: <?php echo json_encode($_SESSION['selected_tradepoints']); ?> })
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
    const selectedCount = <?php echo count($_SESSION['selected_tradepoints']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one tradepoint to export.');
        return;
    }
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_tradepoints.php';
    
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
    <?php foreach ($_SESSION['selected_tradepoints'] as $id): ?>
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