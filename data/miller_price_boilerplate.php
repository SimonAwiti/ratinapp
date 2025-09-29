<?php
// base/millerprices_boilerplate.php

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
            
            // Validate required fields - Based on miller_prices table structure
            if (empty(trim($data[0]))) {
                $errors[] = "Row $rowNumber: Country is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[1]))) {
                $errors[] = "Row $rowNumber: Town is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[2]))) {
                $errors[] = "Row $rowNumber: Commodity ID is required";
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
            
            // Prepare miller price data
            $country = trim($data[0]);
            $town = trim($data[1]);
            $commodity_id = intval(trim($data[2]));
            $price = floatval(trim($data[3]));
            
            // Date parsing (same as market prices)
            $raw_date_string = trim($data[4]);
            error_log("Raw date string from CSV: '$raw_date_string'");
            
            $date_string = trim($data[4]);
            $date_posted = null;
            
            $date_string = preg_replace('/\s+/', ' ', $date_string);
            
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/', $date_string, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                $day = $matches[3];
                $hour = $matches[4];
                $minute = $matches[5];
                $second = $matches[6];
                
                if (checkdate($month, $day, $year) && 
                    $hour >= 0 && $hour <= 23 && 
                    $minute >= 0 && $minute <= 59 && 
                    $second >= 0 && $second <= 59) {
                    $date_posted = "$year-$month-$day $hour:$minute:$second";
                }
            }
            
            if ($date_posted === null) {
                try {
                    $date_time = new DateTime($date_string);
                    $date_posted = $date_time->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $timestamp = strtotime($date_string);
                    if ($timestamp !== false && $timestamp > 0) {
                        $date_posted = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }
            
            if ($date_posted === null || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_posted)) {
                $errors[] = "Row $rowNumber: Invalid date format '$date_string'. Could not parse to valid datetime.";
                $errorCount++;
                continue;
            }
            
            $parsed_timestamp = strtotime($date_posted);
            if ($parsed_timestamp < strtotime('2020-01-01') || $parsed_timestamp > strtotime('2030-12-31')) {
                $errors[] = "Row $rowNumber: Date '$date_posted' is out of reasonable range (2020-2030)";
                $errorCount++;
                continue;
            }
            
            error_log("Successfully parsed date: '$date_string' -> '$date_posted'");
            
            // Optional fields
            $data_source_id = isset($data[5]) ? intval(trim($data[5])) : 1;
            $status = isset($data[6]) ? trim($data[6]) : 'pending';
            
            // Extract date components
            $day = date('d', strtotime($date_posted));
            $month = date('m', strtotime($date_posted));
            $year = date('Y', strtotime($date_posted));
            
            // Validate status
            $valid_statuses = ['pending', 'approved', 'published', 'unpublished'];
            if (!in_array($status, $valid_statuses)) {
                $errors[] = "Row $rowNumber: Invalid status '$status'";
                $errorCount++;
                continue;
            }
            
            // Get commodity name
            $commodity_name = "";
            $commodity_query = "SELECT commodity_name FROM commodities WHERE id = ? LIMIT 1";
            $commodity_stmt = $con->prepare($commodity_query);
            if (!$commodity_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare commodity query: " . $con->error;
                $errorCount++;
                continue;
            }
            $commodity_stmt->bind_param('i', $commodity_id);
            $commodity_stmt->execute();
            $commodity_result = $commodity_stmt->get_result();
            if ($commodity_result->num_rows > 0) {
                $commodity_row = $commodity_result->fetch_assoc();
                $commodity_name = $commodity_row['commodity_name'];
            } else {
                $errors[] = "Row $rowNumber: Commodity ID '$commodity_id' not found";
                $errorCount++;
                $commodity_stmt->close();
                continue;
            }
            $commodity_stmt->close();
            
            // Get data source name
            $data_source_name = "";
            $source_query = "SELECT data_source_name FROM data_sources WHERE id = ? LIMIT 1";
            $source_stmt = $con->prepare($source_query);
            if (!$source_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare data source query: " . $con->error;
                $errorCount++;
                continue;
            }
            $source_stmt->bind_param('i', $data_source_id);
            $source_stmt->execute();
            $source_result = $source_stmt->get_result();
            if ($source_result->num_rows > 0) {
                $source_row = $source_result->fetch_assoc();
                $data_source_name = $source_row['data_source_name'];
            } else {
                $errors[] = "Row $rowNumber: Data Source ID '$data_source_id' not found";
                $errorCount++;
                $source_stmt->close();
                continue;
            }
            $source_stmt->close();
            
            // Convert price to USD
            $price_usd = convertToUSD($price, $country);
            
            // Calculate day and month changes
            $day_change = calculateDayChange($price, $commodity_id, $town, $con);
            $month_change = calculateMonthChange($price, $commodity_id, $town, $con);
            
            // DEBUG: Log what we're about to insert
            error_log("Preparing to insert: country=$country, town=$town, commodity=$commodity_id, date=$date_posted");
            
            // Check if miller price record already exists
            $check_query = "SELECT id FROM miller_prices WHERE town = ? AND commodity_id = ? AND DATE(date_posted) = DATE(?)";
            $check_stmt = $con->prepare($check_query);
            if (!$check_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare check query: " . $con->error;
                $errorCount++;
                continue;
            }
            $check_stmt->bind_param('sis', $town, $commodity_id, $date_posted);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    // Update existing price
                    $update_query = "UPDATE miller_prices SET 
                        country = ?,
                        price = ?,
                        price_usd = ?,
                        day_change = ?,
                        month_change = ?,
                        data_source_id = ?,
                        data_source_name = ?,
                        status = ?,
                        day = ?,
                        month = ?,
                        year = ?
                        WHERE town = ? AND commodity_id = ? AND DATE(date_posted) = DATE(?)";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        $check_stmt->close();
                        continue;
                    }
                    
                    $update_stmt->bind_param(
                        'sdddsisiiiss',
                        $country,
                        $price,
                        $price_usd,
                        $day_change,
                        $month_change,
                        $data_source_id,
                        $data_source_name,
                        $status,
                        $day,
                        $month,
                        $year,
                        $town,
                        $commodity_id,
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
                    $errors[] = "Row $rowNumber: Miller price record already exists (use overwrite option to update)";
                    $errorCount++;
                }
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert new miller price record
            $insert_query = "INSERT INTO miller_prices (
                country,
                town,
                commodity_id,
                commodity_name,
                price,
                price_usd,
                day_change,
                month_change,
                data_source_id,
                data_source_name,
                date_posted,
                day,
                month,
                year,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }

            $insert_stmt->bind_param(
                'ssisddddissiiis',
                $country,
                $town,
                $commodity_id,
                $commodity_name,
                $price,
                $price_usd,
                $day_change,
                $month_change,
                $data_source_id,
                $data_source_name,
                $date_posted,
                $day,
                $month,
                $year,
                $status
            );

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
            $_SESSION['import_message'] = "Successfully imported $successCount miller prices.";
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
    header("Location: miller_price_boilerplate.php");
    exit;
    
} elseif (isset($_POST['import_csv'])) {
    $_SESSION['import_message'] = "Please select a valid CSV file to import.";
    $_SESSION['import_status'] = 'danger';
    header("Location: millerprices_boilerplate.php");
    exit;
}

// Currency conversion function
function convertToUSD($amount, $country) {
    if (!is_numeric($amount)) return 0;

    switch ($country) {
        case 'Kenya': return round($amount / 150, 2);   // 1 USD = 150 KES
        case 'Uganda': return round($amount / 3700, 2); // 1 USD = 3700 UGX
        case 'Tanzania': return round($amount / 2300, 2); // 1 USD = 2300 TZS
        case 'Rwanda': return round($amount / 1200, 2);  // 1 USD = 1200 RWF
        case 'Burundi': return round($amount / 2000, 2); // 1 USD = 2000 BIF
        default: return round($amount, 2);
    }
}

// Function to calculate day change percentage
function calculateDayChange($currentPrice, $commodityId, $town, $con) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $stmt = $con->prepare("SELECT price FROM miller_prices 
                          WHERE commodity_id = ? AND town = ? AND DATE(date_posted) = ?");
    $stmt->bind_param("iss", $commodityId, $town, $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $yesterdayPrice = $result->fetch_assoc()['price'];
        if ($yesterdayPrice > 0) {
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2);
        }
    }
    return null;
}

// Function to calculate month change percentage
function calculateMonthChange($currentPrice, $commodityId, $town, $con) {
    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));
    
    $stmt = $con->prepare("SELECT AVG(price) as avg_price FROM miller_prices 
                          WHERE commodity_id = ? AND town = ? 
                          AND DATE(date_posted) BETWEEN ? AND ?");
    $stmt->bind_param("isss", $commodityId, $town, $firstDayOfLastMonth, $lastDayOfLastMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $avgPrice = $result->fetch_assoc()['avg_price'];
        if ($avgPrice > 0) {
            $change = (($currentPrice - $avgPrice) / $avgPrice) * 100;
            return round($change, 2);
        }
    }
    return null;
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

// Function to fetch miller prices data from the database
function getMillerPricesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                mp.id,
                mp.country,
                mp.town,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                mp.price,
                mp.price_usd,
                mp.day_change,
                mp.month_change,
                mp.date_posted,
                mp.status,
                ds.data_source_name AS data_source
            FROM
                miller_prices mp
            LEFT JOIN
                commodities c ON mp.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON mp.data_source_id = ds.id
            ORDER BY
                mp.date_posted DESC
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
        error_log("Error fetching miller prices data: " . $con->error);
    }
    return $data;
}

function getTotalMillerPriceRecords($con) {
    $sql = "SELECT count(*) as total FROM miller_prices";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalMillerPriceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch miller prices data
$miller_prices_data = getMillerPricesData($con, $limit, $offset);

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
    .positive-change {
        color: green;
    }
    .negative-change {
        color: red;
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

<div class="text-wrapper-8"><h3>Miller Prices Management</h3></div>
<p class="p">Manage everything related to Miller Prices data</p>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?= $import_status ?>">
        <?= htmlspecialchars($import_message) ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_miller_prices.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
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

    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"/></th>
                <th>Country</th>
                <th>Town</th>
                <th>Commodity</th>
                <th>Price (USD)</th>
                <th>Day Change %</th>
                <th>Month Change %</th>
                <th>Date</th>
                <th>Status</th>
                <th>Data Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($miller_prices_data as $price): ?>
                <tr>
                    <td><input type="checkbox" data-id="<?php echo $price['id']; ?>"/></td>
                    <td><?php echo htmlspecialchars($price['country']); ?></td>
                    <td><?php echo htmlspecialchars($price['town']); ?></td>
                    <td><?php echo htmlspecialchars($price['commodity_display']); ?></td>
                    <td><?php echo htmlspecialchars($price['price_usd']); ?></td>
                    <td class="<?php echo ($price['day_change'] > 0) ? 'positive-change' : 'negative-change'; ?>">
                        <?php echo ($price['day_change'] !== null) ? htmlspecialchars($price['day_change']) . '%' : 'N/A'; ?>
                    </td>
                    <td class="<?php echo ($price['month_change'] > 0) ? 'positive-change' : 'negative-change'; ?>">
                        <?php echo ($price['month_change'] !== null) ? htmlspecialchars($price['month_change']) . '%' : 'N/A'; ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($price['date_posted'])); ?></td>
                    <td><?php echo getStatusDisplay($price['status']); ?></td>
                    <td><?php echo htmlspecialchars($price['data_source']); ?></td>
                    <td>
                        <a href="../data/edit_miller_price.php?id=<?= $price['id'] ?>">
                            <button class="btn btn-sm btn-warning">
                                <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
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
                <h5 class="modal-title" id="importModalLabel">Import Miller Prices</h5>
                <button type="button" class="close-modal" onclick="closeImportModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Import Instructions</h5>
                    <p>Your CSV file should have the following columns in order:</p>
                    <ol>
                        <li><strong>Country</strong> (required) - Country name (Kenya, Uganda, Tanzania, Rwanda, Burundi)</li>
                        <li><strong>Town</strong> (required) - Town/Miller location</li>
                        <li><strong>Commodity ID</strong> (required) - Commodity ID from commodities table</li>
                        <li><strong>Price</strong> (required) - Price value in local currency (numeric)</li>
                        <li><strong>Date Posted</strong> (required) - YYYY-MM-DD format</li>
                        <li><strong>Data Source ID</strong> (optional) - Data Source ID from data_sources table (default: 1)</li>
                        <li><strong>Status</strong> (optional) - pending/approved/published/unpublished (default: pending)</li>
                    </ol>
                    
                    <h6>Example CSV Format:</h6>
                    <pre>Country,Town,Commodity ID,Price,Date Posted,Data Source ID,Status
Kenya,Nairobi,40,150.00,2025-06-03,1,published
Kenya,Mwea,41,200.00,2025-06-03,1,approved</pre>
                    
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>Commodity IDs must exist in your commodities table</li>
                        <li>Data Source IDs must exist in your data_sources table</li>
                        <li>Prices will be automatically converted to USD based on country</li>
                        <li>Day and Month change percentages will be calculated automatically</li>
                        <li>All required fields must have values</li>
                    </ul>
                    
                    <a href="downloads/miller_prices_template.csv" class="download-template">
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
                            Overwrite existing prices with matching town, commodity and date
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
    window.open('export_miller_prices.php?' + params.toString(), '_blank');
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
        fetch('../data/update_miller_status.php', {
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
        const priceId = parseInt(checkbox.getAttribute('data-id'));
        if (!isNaN(priceId)) {
            selectedIds.push(priceId);
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
    fetch('../data/check_miller_status.php', {
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
    fetch('../data/check_miller_status_for_unpublish.php', {
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
 * Initializes all event listeners for the miller prices table.
 */
function initializeMillerPrices() {
    console.log("Initializing Miller Prices functionality...");

    // Initialize select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]');

    if (selectAllCheckbox && itemCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
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
    initializeMillerPrices();
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Miller Prices');
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