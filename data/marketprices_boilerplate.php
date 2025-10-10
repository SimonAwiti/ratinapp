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
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
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

// Set pagination parameters - FIXED: Get limit from URL parameter
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
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

// --- Fetch counts for summary boxes ---
$total_prices_query = "SELECT COUNT(*) AS total FROM market_prices";
$total_prices_result = $con->query($total_prices_query);
$total_prices = 0;
if ($total_prices_result) {
    $row = $total_prices_result->fetch_assoc();
    $total_prices = $row['total'];
}

$pending_query = "SELECT COUNT(*) AS total FROM market_prices WHERE status = 'pending'";
$pending_result = $con->query($pending_query);
$pending_count = 0;
if ($pending_result) {
    $row = $pending_result->fetch_assoc();
    $pending_count = $row['total'];
}

$published_query = "SELECT COUNT(*) AS total FROM market_prices WHERE status = 'published'";
$published_result = $con->query($published_query);
$published_count = 0;
if ($published_result) {
    $row = $published_result->fetch_assoc();
    $published_count = $row['total'];
}

$wholesale_query = "SELECT COUNT(*) AS total FROM market_prices WHERE price_type = 'Wholesale'";
$wholesale_result = $con->query($wholesale_query);
$wholesale_count = 0;
if ($wholesale_result) {
    $row = $wholesale_result->fetch_assoc();
    $wholesale_count = $row['total'];
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
    }
    .btn-add-new {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        padding: 10px 20px;
        font-size: 16px;
        border: none;
        border-radius: 5px;
    }
    .btn-add-new:hover {
        background-color: darkred;
    }
    .btn-delete, .btn-export, .btn-import {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
        border-radius: 5px;
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

    /* Updated Table Styles for Borderless Design */
    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 1rem;
        background-color: transparent;
    }

    .table th,
    .table td {
        padding: 12px 8px;
        vertical-align: middle;
        border: none;
        text-align: left;
        font-size: 14px;
        border-bottom: 1px solid #f0f0f0;
    }

    .table thead th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
        padding: 12px 8px;
    }

    .table tbody tr {
        transition: background-color 0.15s ease;
    }

    .table tbody tr:hover {
        background-color: #f9fafb;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #fafafa;
    }

    .table-striped tbody tr:nth-of-type(odd):hover {
        background-color: #f3f4f6;
    }

    /* Remove all borders from the table */
    .table-bordered {
        border: none !important;
    }

    .table-bordered th,
    .table-bordered td {
        border: none !important;
    }

    /* Filter row styling */
    .filter-row th {
        background-color: white;
        padding: 8px;
        border-bottom: 1px solid #e5e7eb;
    }

    /* Checkbox styling */
    .table input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    /* Action buttons styling */
    .btn-group .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }

    /* Pagination styling */
    .pagination {
        margin-top: 20px;
    }

    .page-link {
        border: 1px solid #d1d5db;
        color: #6b7280;
        padding: 6px 12px;
    }

    .page-item.active .page-link {
        background-color: rgba(180, 80, 50, 1);
        border-color: rgba(180, 80, 50, 1);
        color: white;
    }

    .form-select {
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 4px 8px;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Market Prices Management</h3></div>
    <p class="p">Manage everything related to Market Prices data</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stats-title">Total Prices</div>
            <div class="stats-number"><?= $total_prices ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon pending-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-title">Pending</div>
            <div class="stats-number"><?= $pending_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon published-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-title">Published</div>
            <div class="stats-number"><?= $published_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon wholesale-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="stats-title">Wholesale</div>
            <div class="stats-number"><?= $wholesale_count ?></div>
        </div>
    </div>
</div>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?= $import_status ?>">
        <?= $import_message ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="../data/add_marketprices.php" class="btn btn-add-new">
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
            
            <button class="btn-approve" onclick="approveSelected()">
                <i class="fas fa-check-circle" style="margin-right: 5px;"></i>
                Approve
            </button>
            
            <button class="btn-publish" onclick="publishSelected()">
                <i class="fas fa-upload" style="margin-right: 5px;"></i>
                Publish
            </button>
            
            <button class="btn-unpublish" onclick="unpublishSelected()">
                <i class="fas fa-ban" style="margin-right: 5px;"></i>
                Unpublish
            </button>
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

        <table class="table table-striped table-hover table-bordered">
            <thead>
                <tr style="background-color: #d3d3d3 !important; color: black !important;">
                    <th><input type="checkbox" id="selectAll"></th>
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
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterMarket" placeholder="Filter Market"></th>
                    <th><input type="text" class="filter-input" id="filterCommodity" placeholder="Filter Commodity"></th>
                    <th><input type="text" class="filter-input" id="filterDate" placeholder="Filter Date"></th>
                    <th><input type="text" class="filter-input" id="filterType" placeholder="Filter Type"></th>
                    <th><input type="text" class="filter-input" id="filterPrice" placeholder="Filter Price"></th>
                    <th></th>
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterStatus" placeholder="Filter Status"></th>
                    <th><input type="text" class="filter-input" id="filterSource" placeholder="Filter Source"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="pricesTable">
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
                        ?>
                    <tr>
                        <?php if ($first_row): ?>
                            <td rowspan="<?php echo count($prices_in_group); ?>">
                                <input type="checkbox" class="row-checkbox" 
                                    data-group-key="<?php echo $group_key; ?>"
                                    data-price-ids="<?php echo $group_price_ids_json; ?>"
                                />
                            </td>
                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['market']); ?></td>
                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['commodity_display']); ?></td>
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

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> items
            </div>
            <div>
                <label for="itemsPerPage">Show:</label>
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page <= 1 ? '#' : '?page=' . ($page - 1) . '&limit=' . $limit ?>">Prev</a>
                    </li>
                    <?php 
                    // Calculate pagination range
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    // Show first page if not in range
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&limit=' . $limit . '">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>"><?= $i ?></a>
                        </li>
                    <?php 
                    endfor; 
                    
                    // Show last page if not in range
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&limit=' . $limit . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $total_pages ? '#' : '?page=' . ($page + 1) . '&limit=' . $limit ?>">Next</a>
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
                <h5 class="modal-title" id="importModalLabel">Import Market Prices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Format Instructions</h5>
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
        // Add clear filter functionality
        input.addEventListener('input', function() {
            if (this.value === '') {
                applyFilters();
            }
        });
    });

    // Initialize select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Market Prices');
    }
    
    // Show import modal if there was an error
    <?php if (isset($import_message) && $import_status === 'danger'): ?>
        var importModal = new bootstrap.Modal(document.getElementById('importModal'));
        importModal.show();
    <?php endif; ?>
});

function applyFilters() {
    const filters = {
        market: document.getElementById('filterMarket').value.toLowerCase(),
        commodity: document.getElementById('filterCommodity').value.toLowerCase(),
        date: document.getElementById('filterDate').value.toLowerCase(),
        type: document.getElementById('filterType').value.toLowerCase(),
        price: document.getElementById('filterPrice').value.toLowerCase(),
        status: document.getElementById('filterStatus').value.toLowerCase(),
        source: document.getElementById('filterSource').value.toLowerCase()
    };

    const rows = document.querySelectorAll('#pricesTable tr');
    let visibleCount = 0;
    let currentGroup = null;
    let groupMatches = false;
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        
        // Check if this is a row with rowspan (first row of a group)
        const hasRowspan = cells[0] && cells[0].hasAttribute('rowspan');
        
        if (hasRowspan) {
            // This is the first row of a new group
            // Check group-level filters (market, commodity, date)
            const market = cells[1] ? cells[1].textContent.toLowerCase() : '';
            const commodity = cells[2] ? cells[2].textContent.toLowerCase() : '';
            const date = cells[3] ? cells[3].textContent.toLowerCase() : '';
            
            // Check row-level filters (type, price, status, source)
            const type = cells[4] ? cells[4].textContent.toLowerCase() : '';
            const price = cells[5] ? cells[5].textContent.toLowerCase() : '';
            const status = cells[8] ? cells[8].textContent.toLowerCase() : '';
            const source = cells[9] ? cells[9].textContent.toLowerCase() : '';
            
            groupMatches = 
                market.includes(filters.market) &&
                commodity.includes(filters.commodity) &&
                date.includes(filters.date) &&
                type.includes(filters.type) &&
                price.includes(filters.price) &&
                status.includes(filters.status) &&
                source.includes(filters.source);
            
            row.style.display = groupMatches ? '' : 'none';
            if (groupMatches) visibleCount++;
            
        } else {
            // This is a continuation row of the current group
            // Check row-level filters only (type, price, status, source)
            const type = cells[0] ? cells[0].textContent.toLowerCase() : '';
            const price = cells[1] ? cells[1].textContent.toLowerCase() : '';
            const status = cells[4] ? cells[4].textContent.toLowerCase() : '';
            const source = cells[5] ? cells[5].textContent.toLowerCase() : '';
            
            const rowMatches = 
                groupMatches && // Must be part of a matching group
                type.includes(filters.type) &&
                price.includes(filters.price) &&
                status.includes(filters.status) &&
                source.includes(filters.source);
            
            row.style.display = rowMatches ? '' : 'none';
            if (rowMatches) visibleCount++;
        }
    });
    
    // Update display count
    const displayElement = document.querySelector('.d-flex.justify-content-between.align-items-center div:first-child');
    if (displayElement) {
        const totalText = displayElement.textContent.match(/of (\d+) items/);
        if (totalText) {
            displayElement.textContent = `Displaying ${visibleCount > 0 ? '1' : '0'} to ${visibleCount} of ${totalText[1]} items`;
        }
    }
}

function clearAllFilters() {
    document.querySelectorAll('.filter-input').forEach(input => {
        input.value = '';
    });
    applyFilters();
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

/**
 * Get all selected price IDs
 */
function getSelectedPriceIds() {
    const selectedIds = [];
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    
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
</script>

<?php include '../admin/includes/footer.php'; ?>