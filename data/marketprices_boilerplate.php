<?php
// base/marketprices_boilerplate.php

// Start session at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include the configuration file first
include '../admin/includes/config.php';

// Handle CSV import BEFORE any HTML output
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $overwrite = isset($_POST['overwrite_existing']);
    $data_source = $_POST['data_source'] ?? 'Manual Import';
    
    // Skip header row
    fgetcsv($handle);
    
    $successCount = 0;
    $errorCount = 0;
    $errors = array();
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        $rowNumber = 1;
        
        while (($data = fgetcsv($handle, 1000, ","))) {
            $rowNumber++;
            
            // Skip completely empty rows
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) {
                continue;
            }
            
            // Validate required fields - Updated based on actual table structure
            if (empty(trim($data[0]))) {
                $errors[] = "Row $rowNumber: Market is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[1]))) {
                $errors[] = "Row $rowNumber: Commodity ID is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[2]))) {
                $errors[] = "Row $rowNumber: Price Type is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[3]))) {
                $errors[] = "Row $rowNumber: Price is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[4]))) {
                $errors[] = "Row $rowNumber: Date is required";
                $errorCount++;
                continue;
            }
            
            // Prepare market price data - Updated mapping based on CSV structure
            $market = trim($data[0]);
            $commodity_id = intval(trim($data[1]));
            $price_type = trim($data[2]);
            $price = floatval(trim($data[3]));
            
            // DEBUG: Check what we're actually getting from the CSV
            $raw_date_string = trim($data[4]);
            error_log("Raw date string from CSV: '$raw_date_string'");
            
            // FIXED: More robust date parsing with validation
            $date_string = trim($data[4]);
            $date_posted = null;
            
            // Remove any extra spaces and ensure proper format
            $date_string = preg_replace('/\s+/', ' ', $date_string);
            
            // Try direct parsing first
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/', $date_string, $matches)) {
                // Format: YYYY-MM-DD HH:MM:SS
                $year = $matches[1];
                $month = $matches[2];
                $day = $matches[3];
                $hour = $matches[4];
                $minute = $matches[5];
                $second = $matches[6];
                
                // Validate date components
                if (checkdate($month, $day, $year) && 
                    $hour >= 0 && $hour <= 23 && 
                    $minute >= 0 && $minute <= 59 && 
                    $second >= 0 && $second <= 59) {
                    $date_posted = "$year-$month-$day $hour:$minute:$second";
                }
            }
            
            // If direct parsing failed, try DateTime
            if ($date_posted === null) {
                try {
                    $date_time = new DateTime($date_string);
                    $date_posted = $date_time->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // DateTime failed, try strtotime as last resort
                    $timestamp = strtotime($date_string);
                    if ($timestamp !== false && $timestamp > 0) {
                        $date_posted = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }
            
            // Final validation
            if ($date_posted === null || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_posted)) {
                $errors[] = "Row $rowNumber: Invalid date format '$date_string'. Could not parse to valid datetime.";
                $errorCount++;
                continue;
            }
            
            // Additional validation to ensure it's a reasonable date (not 1970 or future dates too far ahead)
            $parsed_timestamp = strtotime($date_posted);
            if ($parsed_timestamp < strtotime('2020-01-01') || $parsed_timestamp > strtotime('2030-12-31')) {
                $errors[] = "Row $rowNumber: Date '$date_posted' is out of reasonable range (2020-2030)";
                $errorCount++;
                continue;
            }
            
            error_log("Successfully parsed date: '$date_string' -> '$date_posted'");
            
            $status = isset($data[5]) ? trim($data[5]) : 'pending';
            $variety = isset($data[6]) ? trim($data[6]) : '';
            $weight = isset($data[7]) ? floatval(trim($data[7])) : 1.0;
            $unit = isset($data[8]) ? trim($data[8]) : 'kg';
            $country_admin_0 = isset($data[9]) ? trim($data[9]) : 'Kenya';
            $subject = isset($data[10]) ? trim($data[10]) : 'Market Prices';
            $supplied_volume = isset($data[11]) ? (trim($data[11]) !== '' ? intval(trim($data[11])) : null) : null;
            $comments = isset($data[12]) ? trim($data[12]) : '';
            $supply_status = isset($data[13]) ? trim($data[13]) : 'unknown';
            $category = isset($data[14]) ? trim($data[14]) : 'General';
            $commodity_sources_data = null;
            
            // Extract date components
            $day = date('d', strtotime($date_posted));
            $month = date('m', strtotime($date_posted));
            $year = date('Y', strtotime($date_posted));
            
            // Validate price type
            $valid_price_types = ['Wholesale', 'Retail'];
            if (!in_array($price_type, $valid_price_types)) {
                $errors[] = "Row $rowNumber: Invalid price type '$price_type'. Must be 'Wholesale' or 'Retail'";
                $errorCount++;
                continue;
            }
            
            // Validate status
            $valid_statuses = ['pending', 'approved', 'published', 'unpublished'];
            if (!in_array($status, $valid_statuses)) {
                $errors[] = "Row $rowNumber: Invalid status '$status'";
                $errorCount++;
                continue;
            }
            
            // Get market ID from market name - with flexible matching
            $market_id = 0;
            $market_name_to_search = $market;
            
            // Handle market name variations
            if (strtolower($market) === 'kangemi') {
                $market_name_to_search = 'Kangemi Market';
            } elseif (strtolower($market) === 'kibuye') {
                $market_name_to_search = 'Kibuye Market';
            }
            
            $market_query = "SELECT id FROM markets WHERE market_name = ? LIMIT 1";
            $market_stmt = $con->prepare($market_query);
            if (!$market_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare market query: " . $con->error;
                $errorCount++;
                continue;
            }
            $market_stmt->bind_param('s', $market_name_to_search);
            $market_stmt->execute();
            $market_result = $market_stmt->get_result();
            if ($market_result->num_rows > 0) {
                $market_row = $market_result->fetch_assoc();
                $market_id = $market_row['id'];
            } else {
                $errors[] = "Row $rowNumber: Market '$market' not found (searched as '$market_name_to_search')";
                $errorCount++;
                $market_stmt->close();
                continue;
            }
            $market_stmt->close();
            
            // DEBUG: Log what we're about to insert
            error_log("Preparing to insert: market=$market, commodity=$commodity_id, date=$date_posted");
            
            // Check if price record already exists
            $check_query = "SELECT id FROM market_prices WHERE market = ? AND commodity = ? AND price_type = ? AND DATE(date_posted) = DATE(?)";
            $check_stmt = $con->prepare($check_query);
            if (!$check_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare check query: " . $con->error;
                $errorCount++;
                continue;
            }
            $check_stmt->bind_param('siss', $market, $commodity_id, $price_type, $date_posted);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    // Update existing price
                    $update_query = "UPDATE market_prices SET 
                        Price = ?, 
                        status = ?, 
                        data_source = ?, 
                        variety = ?, 
                        weight = ?, 
                        unit = ?, 
                        country_admin_0 = ?, 
                        subject = ?, 
                        supplied_volume = ?, 
                        comments = ?, 
                        supply_status = ?,
                        commodity_sources_data = ?
                        WHERE market = ? AND commodity = ? AND price_type = ? AND DATE(date_posted) = DATE(?)";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        $check_stmt->close();
                        continue;
                    }
                    
                    // Correct parameter binding for update (16 parameters)
                    $update_stmt->bind_param(
                        'dsssdsssissssiss', // 16 type characters - CORRECTED
                        $price,
                        $status,
                        $data_source,
                        $variety,
                        $weight,
                        $unit,
                        $country_admin_0,
                        $subject,
                        $supplied_volume,
                        $comments,
                        $supply_status,
                        $commodity_sources_data,
                        $market,
                        $commodity_id,
                        $price_type,
                        $date_posted
                    );
                    
                    if ($update_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                        $errorCount++;
                    }
                    $update_stmt->close();
                } else {
                    $errors[] = "Row $rowNumber: Price record already exists (use overwrite option to update)";
                    $errorCount++;
                }
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            

            // Insert new price record - CORRECTED to match actual table column order
            $insert_query = "INSERT INTO market_prices (
                category,
                commodity,
                country_admin_0,
                market_id,
                market,
                weight,
                unit,
                price_type,
                Price,
                subject,
                day,
                month,
                year,
                date_posted,
                status,
                variety,
                data_source,
                supplied_volume,
                comments,
                supply_status,
                commodity_sources_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }

            // Create arrays for cleaner parameter handling
            $bind_types = [
                's', // category
                'i', // commodity
                's', // country_admin_0
                'i', // market_id
                's', // market
                'd', // weight
                's', // unit
                's', // price_type
                'd', // Price
                's', // subject
                'i', // day
                'i', // month
                'i', // year
                's', // date_posted
                's', // status
                's', // variety
                's', // data_source
                'i', // supplied_volume
                's', // comments
                's', // supply_status
                's'  // commodity_sources_data
            ];

            $bind_values = [
                $category,
                $commodity_id,
                $country_admin_0,
                $market_id,
                $market,
                $weight,
                $unit,
                $price_type,
                $price,
                $subject,
                $day,
                $month,
                $year,
                $date_posted,
                $status,
                $variety,
                $data_source,
                $supplied_volume,
                $comments,
                $supply_status,
                $commodity_sources_data
            ];

            // Debug: verify counts match
            error_log("Bind types count: " . count($bind_types));
            error_log("Bind values count: " . count($bind_values));

            if (count($bind_types) !== count($bind_values)) {
                $errors[] = "Row $rowNumber: Parameter count mismatch in binding";
                $errorCount++;
                $insert_stmt->close();
                continue;
            }

            // Convert type array to string
            $type_string = implode('', $bind_types);
            error_log("Type string: $type_string (length: " . strlen($type_string) . ")");

            // Bind parameters
            $insert_stmt->bind_param($type_string, ...$bind_values);

            if ($insert_stmt->execute()) {
                $successCount++;
                error_log("Insert successful for row $rowNumber");
            } else {
                $error_msg = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                $errors[] = $error_msg;
                error_log($error_msg);
                $errorCount++;
            }
            $insert_stmt->close();
                    }
        
        // Commit or rollback transaction
        if ($errorCount === 0) {
            $con->commit();
            $_SESSION['import_message'] = "Successfully imported $successCount market prices.";
            $_SESSION['import_status'] = 'success';
        } else {
            $con->rollback();
            $_SESSION['import_message'] = "Import failed with $errorCount errors. Errors: " . implode('<br>', array_slice($errors, 0, 10));
            $_SESSION['import_status'] = 'danger';
        }
        
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['import_message'] = "Import failed with exception: " . $e->getMessage();
        $_SESSION['import_status'] = 'danger';
    }
    
    fclose($handle);
    
    // Redirect to avoid form resubmission
    header("Location: marketprices_boilerplate.php");
    exit;
    
} elseif (isset($_POST['import_csv'])) {
    $_SESSION['import_message'] = "Please select a valid CSV file to import.";
    $_SESSION['import_status'] = 'danger';
    header("Location: marketprices_boilerplate.php");
    exit;
}
// Include the shared header AFTER handling POST requests
include '../admin/includes/header.php';

// Check for session messages
$import_message = null;
$import_status = null;
if (isset($_SESSION['import_message'])) {
    $import_message = $_SESSION['import_message'];
    $import_status = $_SESSION['import_status'];
    unset($_SESSION['import_message']);
    unset($_SESSION['import_status']);
}

// Function to fetch prices data from the database
function getPricesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                p.id,
                p.market,
                p.commodity,
                c.commodity_name,
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id
            ORDER BY
                p.date_posted DESC
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
        error_log("Error fetching prices data: " . $con->error);
    }
    return $data;
}

function getTotalPriceRecords($con){
    $sql = "SELECT count(*) as total FROM market_prices";
    $result = $con->query($sql);
     if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
     }
     return 0;
}

// Get total number of records
$total_records = getTotalPriceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch prices data
$prices_data = getPricesData($con, $limit, $offset);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Function to get status display
function getStatusDisplay($status) {
    switch ($status) {
        case 'pending':
            return '<span class="status-dot status-pending"></span> Pending';
        case 'published':
            return '<span class="status-dot status-published"></span> Published';
        case 'approved':
            return '<span class="status-dot status-approved"></span> Approved';
        case 'unpublished':
            return '<span class="status-dot status-unpublished"></span> Unpublished';
        default:
            return '<span class="status-dot"></span> Unknown';
    }
}

function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    // Find the most recent price before the current date for the same commodity/market/price_type
    $sql = "SELECT Price FROM market_prices
            WHERE commodity = ? 
            AND market = ?
            AND price_type = ?
            AND DATE(date_posted) < DATE(?)
            ORDER BY date_posted DESC
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) return 'N/A';
    
    $stmt->bind_param('isss', $commodityId, $market, $priceType, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $previousData = $result->fetch_assoc();
        $previousPrice = $previousData['Price'];
        if ($previousPrice != 0) {
            $change = (($currentPrice - $previousPrice) / $previousPrice) * 100;
            $stmt->close();
            return round($change, 2) . '%';
        }
    }
    $stmt->close();
    return 'N/A';
}

function calculateMoMChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    // Calculate date 30 days before current date
    $thirtyDaysAgo = date('Y-m-d', strtotime($currentDate . ' -30 days'));
    
    // Find the closest price to 30 days ago (within a reasonable range)
    $sql = "SELECT Price, ABS(DATEDIFF(DATE(date_posted), ?)) as date_diff 
            FROM market_prices
            WHERE commodity = ?
            AND market = ?
            AND price_type = ?
            AND DATE(date_posted) BETWEEN DATE_SUB(?, INTERVAL 35 DAY) AND DATE_SUB(?, INTERVAL 25 DAY)
            ORDER BY date_diff ASC
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) return 'N/A';
    
    $stmt->bind_param('sissss', $thirtyDaysAgo, $commodityId, $market, $priceType, $thirtyDaysAgo, $thirtyDaysAgo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $monthAgoData = $result->fetch_assoc();
        $monthAgoPrice = $monthAgoData['Price'];
        if ($monthAgoPrice != 0) {
            $change = (($currentPrice - $monthAgoPrice) / $monthAgoPrice) * 100;
            $stmt->close();
            return round($change, 2) . '%';
        }
    }
    $stmt->close();
    return 'N/A';
}
?>

<style>
    .container {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin: 20px;
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
    .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
    }
    .status-pending {
        background-color: orange;
    }
    .status-published {
        background-color: blue;
    }
    .status-approved {
        background-color: green;
    }
    .status-unpublished {
        background-color: grey;
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
    
    /* Import instructions styles */
    .import-instructions {
        background-color: #f8f9fa;
        border-left: 4px solid rgba(180, 80, 50, 1);
        padding: 15px;
        margin-bottom: 20px;
        max-height: 300px;
        overflow-y: auto;
        border-radius: 5px;
    }
    .import-instructions h5 {
        color: rgba(180, 80, 50, 1);
        margin-top: 0;
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        padding-bottom: 10px;
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 15px;
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
    
    /* Fixed Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }
    
    .modal.show {
        display: block;
    }
    
    .modal-dialog {
        margin: 5% auto;
        max-width: 800px;
    }
    
    .modal-content {
        background-color: #fefefe;
        padding: 20px;
        border: 1px solid #888;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 15px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .modal-title {
        margin: 0;
        font-size: 1.25rem;
    }
    
    .close-modal {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
    }
    
    .close-modal:hover {
        color: black;
    }
    
    /* Miller Prices Section */
    .miller-section {
        margin-top: 40px;
        border-top: 2px solid #eee;
        padding-top: 20px;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .section-title {
        font-size: 1.5em;
        color: rgba(180, 80, 50, 1);
        margin: 0;
    }
    
    /* Instructions scrollbar styling */
    .import-instructions::-webkit-scrollbar {
        width: 6px;
    }
    .import-instructions::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    .import-instructions::-webkit-scrollbar-thumb {
        background: rgba(180, 80, 50, 0.5);
        border-radius: 3px;
    }
    .import-instructions::-webkit-scrollbar-thumb:hover {
        background: rgba(180, 80, 50, 0.7);
    }
    
    /* Alert styles */
    .alert {
        padding: 12px 20px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    
    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }
</style>

<div class="text-wrapper-8"><h3>Market Prices Management</h3></div>
<p class="p">Manage everything related to Market Prices data</p>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?= $import_status ?>">
        <?= htmlspecialchars($import_message) ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_marketprices.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
                <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New
            </a>
            <button class="btn-import" onclick="openImportModal()">
                <i class="fa fa-upload" style="margin-right: 6px;"></i> Import
            </button>
            <button class="delete-btn" onclick="deleteSelected()">
                <i class="fa fa-trash" style="margin-right: 6px;"></i> Delete
            </button>
            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-file-export" style="margin-right: 6px;"></i> Export
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
        </div>
        <div class="toolbar-right">
            <button class="approve" onclick="approveSelected()">
                <i class="fa fa-check-circle" style="margin-right: 6px;"></i> Approve
            </button>
            <button class="unpublish" onclick="unpublishSelected()">
                <i class="fa fa-ban" style="margin-right: 6px;"></i> Unpublish
            </button>
            <button class="primary" onclick="publishSelected()">
                <i class="fa fa-upload" style="margin-right: 6px;"></i> Publish
            </button>
        </div>
    </div>

    <?php
    // IMPORTANT: Data grouping logic - must come BEFORE the table display
    $grouped_data = [];
    foreach ($prices_data as $price) {
        $date = date('Y-m-d', strtotime($price['date_posted']));
        $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'];
        $grouped_data[$group_key][] = $price;
    }
    ?>

    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"/></th>
                <th>Market</th>
                <th>Commodity</th>
                <th>Date</th>
                <th>Type</th>
                <th>Price($)</th>
                <th>Day Change(%)</th>
                <th>Month Change(%)</th>
                <th>Status</th>
                <th>Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Updated table row generation - replace your existing tbody loop
            foreach ($grouped_data as $group_key => $prices_in_group):
                $first_row = true;
                $group_price_ids = array_column($prices_in_group, 'id');
                $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));

                foreach($prices_in_group as $price):
                    // CORRECTED: Pass the actual date_posted from the record
                    $price_date = $price['date_posted'];
                    $day_change = calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price_date, $con);
                    
                    // Choose between Month-over-Month or Week-over-Week
                    $month_change = calculateMoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price_date, $con);
                    // OR use Week-over-Week instead:
                    // $week_change = calculateWoWChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price_date, $con);
                    ?>
                <tr>
                    <?php if ($first_row): ?>
                        <td rowspan="<?php echo count($prices_in_group); ?>">
                            <input type="checkbox"
                                data-group-key="<?php echo $group_key; ?>"
                                data-price-ids="<?php echo $group_price_ids_json; ?>"
                            />
                        </td>
                        <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['market']); ?></td>
                        <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['commodity_name']); ?></td>
                        <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo date('Y-m-d', strtotime($price['date_posted'])); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($price['price_type']); ?></td>
                    <td><?php echo htmlspecialchars($price['Price']); ?></td>
                    <td><?php echo $day_change; ?></td>
                    <td><?php echo $month_change; ?></td>
                    <td><?php echo getStatusDisplay($price['status']); ?></td>
                    <td><?php echo htmlspecialchars($price['data_source']); ?></td>
                    <td>
                        <a href="../data/edit_marketprice.php?id=<?= $price['id'] ?>">
                            <button class="btn btn-sm btn-warning">
                                <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
                            </button>
                        </a>
                    </td>
                </tr>
                <?php
                $first_row = false;
                endforeach;
            endforeach;
            ?>
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

<!-- Import Modal -->
<div class="modal" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Market Prices</h5>
                <button type="button" class="close-modal" onclick="closeImportModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Import Instructions</h5>
                    <p>Your CSV file should have the following columns in order:</p>
                    <ol>
                        <li><strong>Market</strong> (required) - Market name (must exist in markets table)</li>
                        <li><strong>Commodity ID</strong> (required) - Commodity ID from commodities table</li>
                        <li><strong>Price Type</strong> (required) - "Wholesale" or "Retail"</li>
                        <li><strong>Price</strong> (required) - Price value (numeric)</li>
                        <li><strong>Date Posted</strong> (required) - YYYY-MM-DD format</li>
                        <li><strong>Status</strong> (optional) - pending/approved/published/unpublished (default: pending)</li>
                        <li><strong>Variety</strong> (optional) - Commodity variety</li>
                        <li><strong>Weight</strong> (optional) - Weight value (default: 1.0)</li>
                        <li><strong>Unit</strong> (optional) - Measurement unit (default: kg)</li>
                        <li><strong>Country</strong> (optional) - Country name (default: Kenya)</li>
                        <li><strong>Subject</strong> (optional) - Price subject (default: Market Prices)</li>
                        <li><strong>Supplied Volume</strong> (optional) - Volume as integer</li>
                        <li><strong>Comments</strong> (optional) - Additional comments</li>
                        <li><strong>Supply Status</strong> (optional) - Supply status (default: unknown)</li>
                        <li><strong>Category</strong> (optional) - Commodity category (default: General)</li>
                    </ol>
                    
                    <h6>Example CSV Format:</h6>
                    <pre>Market,Commodity ID,Price Type,Price,Date Posted,Status,Variety
Kangemi Market,40,Wholesale,1.14,2025-06-03,published,Yellow
Kangemi Market,40,Retail,1.52,2025-06-03,published,Yellow</pre>
                    
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>Market names must exist in your markets table</li>
                        <li>Commodity IDs must exist in your commodities table</li>
                        <li>The commodity name will be automatically fetched from the commodities table</li>
                        <li>All required fields must have values</li>
                    </ul>
                    
                    <a href="downloads/market_prices_template.csv" class="download-template">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="data_source" class="form-label">Data Source</label>
                        <input type="text" class="form-control" id="data_source" name="data_source" placeholder="Source of this data" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="overwriteExisting" name="overwrite_existing">
                        <label class="form-check-label" for="overwriteExisting">
                            Overwrite existing prices with matching market, commodity, price type and date
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                <button type="submit" form="importForm" name="import_csv" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Export selected items to Excel or PDF
 */
function exportSelected(format) {
    const selectedIds = getSelectedPriceIds();
    
    if (selectedIds.length === 0) {
        alert('Please select items to export.');
        return;
    }
    
    // Create URL parameters for export
    const params = new URLSearchParams();
    params.append('export', format);
    params.append('ids', JSON.stringify(selectedIds));
    
    // Open export in new window
    window.open('export_market_prices.php?' + params.toString(), '_blank');
}

/**
 * Displays a confirmation dialog and sends a request to update item status or delete items.
 */
function confirmAction(action, ids) {
    if (ids.length === 0) {
        alert('Please select items to ' + action + '.');
        return;
    }

    let message = 'Are you sure you want to ' + action + ' these items?';
    if (confirm(message)) {
        fetch('../data/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                ids: ids,
            }),
        })
        .then(response => {
            if (!response.ok) {
                return response.json().catch(() => {
                    throw new Error(`HTTP error! status: ${response.status} - No JSON response from server.`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Items ' + action + ' successfully.');
                window.location.reload();
            } else {
                alert('Failed to ' + action + ' items: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(error => {
            console.error('Fetch error during ' + action + ':', error);
            alert('An error occurred while ' + action + ' items: ' + error.message);
        });
    }
}

/**
 * Get all selected price IDs
 */
function getSelectedPriceIds() {
    const selectedIds = [];
    const checkboxes = document.querySelectorAll('table tbody input[type="checkbox"]:checked');
    
    checkboxes.forEach(checkbox => {
        try {
            const priceIds = JSON.parse(checkbox.getAttribute('data-price-ids'));
            selectedIds.push(...priceIds);
        } catch (e) {
            console.error('Error parsing price IDs:', e);
        }
    });
    
    return selectedIds;
}

// Modal management functions
function openImportModal() {
    const modal = document.getElementById('importModal');
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.style.zIndex = '1040';
        document.body.appendChild(backdrop);
        document.body.style.overflow = 'hidden';
    }
}

function closeImportModal() {
    const modal = document.getElementById('importModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        // Remove backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            document.body.removeChild(backdrop);
        }
        document.body.style.overflow = '';
    }
}

// Action functions
function approveSelected() {
    const ids = getSelectedPriceIds();
    confirmAction('approve', ids);
}

function publishSelected() {
    const ids = getSelectedPriceIds();
    if (ids.length === 0) {
        alert('Please select items to publish.');
        return;
    }
    
    // Check if all selected items are approved before publishing
    fetch('../data/check_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: ids }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.allApproved) {
            confirmAction('publish', ids);
        } else {
            alert('Cannot publish. All selected items must be approved first. ' + (data.message || ''));
        }
    })
    .catch(error => {
        console.error('Fetch error checking approval status:', error);
        alert('An error occurred while checking approval status: ' + error.message);
    });
}

function unpublishSelected() {
    const ids = getSelectedPriceIds();
    if (ids.length === 0) {
        alert('Please select items to unpublish.');
        return;
    }
    
    // Check if all selected items are currently published before unpublishing
    fetch('../data/check_status_for_unpublish.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: ids }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.allPublished) {
            confirmAction('unpublish', ids);
        } else {
            alert('Cannot unpublish. All selected items must currently be in "Published" status.');
        }
    })
    .catch(error => {
        console.error('Fetch error checking status for unpublish:', error);
        alert('An error occurred while checking status for unpublish: ' + error.message);
    });
}

function deleteSelected() {
    const ids = getSelectedPriceIds();
    confirmAction('delete', ids);
}

/**
 * Initializes all event listeners for the market prices table.
 */
function initializeMarketPrices() {
    console.log("Initializing Market Prices functionality...");

    // Initialize select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    const groupCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-group-key]');

    if (selectAllCheckbox && groupCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            groupCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        groupCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(groupCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            });
        });
    }

    // Close modal when clicking outside
    const modal = document.getElementById('importModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeImportModal();
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeMarketPrices();
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Market Prices');
    }
});

// Keyboard support for closing modal
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImportModal();
    }
});
</script>

<?php include '../admin/includes/footer.php'; ?>