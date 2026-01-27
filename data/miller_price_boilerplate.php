<?php
// base/millerprices_boilerplate.php

// Start session at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize session storage for selected items if not exists
if (!isset($_SESSION['selected_miller_prices'])) {
    $_SESSION['selected_miller_prices'] = [];
}

// Include the configuration file first
include '../admin/includes/config.php';

// Handle AJAX selection updates
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_miller_prices'])) {
            $_SESSION['selected_miller_prices'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_miller_prices']);
        if ($key !== false) {
            unset($_SESSION['selected_miller_prices'][$key]);
            $_SESSION['selected_miller_prices'] = array_values($_SESSION['selected_miller_prices']);
        }
    }
    
    // Clear all selections if requested
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_miller_prices'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_miller_prices'])]);
    exit;
}

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

// Function to fetch miller prices data from the database with filters and sorting
function getMillerPricesData($con, $limit = 10, $offset = 0, $filters = [], $sort_column = 'date_posted', $sort_order = 'DESC') {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters if provided
    if (!empty($filters['country'])) {
        $where_clauses[] = "mp.country LIKE ?";
        $params[] = '%' . $filters['country'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['town'])) {
        $where_clauses[] = "mp.town LIKE ?";
        $params[] = '%' . $filters['town'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['commodity'])) {
        $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
        $params[] = '%' . $filters['commodity'] . '%';
        $params[] = '%' . $filters['commodity'] . '%';
        $types .= 'ss';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(mp.date_posted) LIKE ?";
        $params[] = '%' . $filters['date'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $where_clauses[] = "mp.status LIKE ?";
        $params[] = '%' . $filters['status'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['data_source'])) {
        $where_clauses[] = "ds.data_source_name LIKE ?";
        $params[] = '%' . $filters['data_source'] . '%';
        $types .= 's';
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Map sortable columns to database columns
    $sortable_columns = [
        'id' => 'mp.id',
        'country' => 'mp.country',
        'town' => 'mp.town',
        'commodity' => 'c.commodity_name',
        'price' => 'mp.price',
        'price_usd' => 'mp.price_usd',
        'day_change' => 'mp.day_change',
        'month_change' => 'mp.month_change',
        'date' => 'mp.date_posted',
        'status' => 'mp.status',
        'data_source' => 'ds.data_source_name'
    ];
    
    $default_sort_column = 'date_posted';
    $default_sort_order = 'DESC';
    
    $db_sort_column = isset($sortable_columns[$sort_column]) ? $sortable_columns[$sort_column] : $sortable_columns['date'];
    $db_sort_order = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : $default_sort_order;
    
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
            $where_sql
            ORDER BY
                $db_sort_column $db_sort_order
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

function getTotalMillerPriceRecords($con, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters if provided
    if (!empty($filters['country'])) {
        $where_clauses[] = "mp.country LIKE ?";
        $params[] = '%' . $filters['country'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['town'])) {
        $where_clauses[] = "mp.town LIKE ?";
        $params[] = '%' . $filters['town'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['commodity'])) {
        $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
        $params[] = '%' . $filters['commodity'] . '%';
        $params[] = '%' . $filters['commodity'] . '%';
        $types .= 'ss';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(mp.date_posted) LIKE ?";
        $params[] = '%' . $filters['date'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $where_clauses[] = "mp.status LIKE ?";
        $params[] = '%' . $filters['status'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['data_source'])) {
        $where_clauses[] = "ds.data_source_name LIKE ?";
        $params[] = '%' . $filters['data_source'] . '%';
        $types .= 's';
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "SELECT count(*) as total 
            FROM miller_prices mp
            LEFT JOIN commodities c ON mp.commodity_id = c.id
            LEFT JOIN data_sources ds ON mp.data_source_id = ds.id
            $where_sql";
    
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
    'country' => isset($_GET['filter_country']) ? trim($_GET['filter_country']) : '',
    'town' => isset($_GET['filter_town']) ? trim($_GET['filter_town']) : '',
    'commodity' => isset($_GET['filter_commodity']) ? trim($_GET['filter_commodity']) : '',
    'date' => isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '',
    'status' => isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '',
    'data_source' => isset($_GET['filter_data_source']) ? trim($_GET['filter_data_source']) : ''
];

// Get sort parameters
$sortable_columns = ['id', 'country', 'town', 'commodity', 'price', 'price_usd', 'day_change', 'month_change', 'date', 'status', 'data_source'];
$default_sort_column = 'date';
$default_sort_order = 'DESC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

// Get total number of records with filters
$total_records = getTotalMillerPriceRecords($con, $filters);

// Set pagination parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch miller prices data with filters and sorting
$miller_prices_data = getMillerPricesData($con, $limit, $offset, $filters, $sort_column, $sort_order);

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
    
    /* Button group styling */
    .btn-group {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    
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
        min-width: 140px;
        text-align: center;
        transition: background-color 0.3s;
    }
    
    .btn-add-new:hover {
        background-color: #a52a2a;
        color: white;
        text-decoration: none;
    }
    
    .btn-delete, .btn-export, .btn-import, .btn-bulk-export, .btn-clear-selections,
    .btn-approve, .btn-publish, .btn-unpublish, .btn-clear-filters {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .btn-delete:hover, .btn-export:hover, .btn-import:hover, 
    .btn-bulk-export:hover, .btn-clear-selections:hover,
    .btn-clear-filters:hover {
        background-color: #f8f9fa;
        border-color: #ccc;
    }
    
    .btn-clear-selections {
        background-color: #ffc107;
        color: black;
        border-color: #ffc107;
    }
    
    .btn-clear-selections:hover {
        background-color: #e0a800;
        border-color: #e0a800;
    }
    
    .btn-approve {
        background-color: #28a745;
        color: white;
        border: none;
    }
    
    .btn-approve:hover {
        background-color: #218838;
    }
    
    .btn-unpublish {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        border: none;
    }
    
    .btn-unpublish:hover {
        background-color: #a52a2a;
    }
    
    .btn-publish {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        border: none;
    }
    
    .btn-publish:hover {
        background-color: #a52a2a;
    }
    
    .btn-clear-filters {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
    }
    
    .btn-clear-filters:hover {
        background-color: #f8f9fa;
        border-color: #ccc;
    }
    
    /* Dropdown styles */
    .dropdown {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-menu {
        display: none;
        position: absolute;
        background-color: white;
        min-width: 200px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        z-index: 1000;
        border-radius: 4px;
        padding: 5px 0;
        border: 1px solid #ddd;
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
        transition: background-color 0.2s;
    }
    
    .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .dropdown-divider {
        height: 1px;
        margin: 5px 0;
        background-color: #e5e7eb;
    }
    
    /* Table styles */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        margin-top: 20px;
    }
    
    table th, table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        text-align: left;
        vertical-align: top;
    }
    
    table th {
        background-color: #f1f1f1;
        font-weight: 600;
    }
    
    table tr:nth-child(even) {
        background-color: #fafafa;
    }
    
    table tr:hover {
        background-color: #f5f5f5;
    }
    
    /* Status dot styles */
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
    
    /* Change percentage styles */
    .positive-change {
        color: #28a745;
        font-weight: 600;
    }
    
    .negative-change {
        color: #dc3545;
        font-weight: 600;
    }
    
    /* Pagination styles */
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
        transition: background-color 0.3s;
    }
    
    .pagination .page:hover {
        background-color: #ddd;
    }
    
    .pagination .current {
        background-color: rgba(180, 80, 50, 1);
        color: white;
    }
    
    .pagination .current:hover {
        background-color: #a52a2a;
    }
    
    /* Selection styles */
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
        padding-right: 20px !important;
    }
    
    .sortable:hover {
        background-color: #e8e8e8;
    }
    
    .sort-icon {
        display: inline-block;
        margin-left: 5px;
        font-size: 0.8em;
        opacity: 0.7;
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
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
    
    /* Filter styles */
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
        transition: border-color 0.3s;
    }
    
    .filter-input:focus {
        outline: none;
        border-color: rgba(180, 80, 50, 1);
        box-shadow: 0 0 0 2px rgba(180, 80, 50, 0.1);
    }
    
    /* Stats styles */
    .stats-section {
        text-align: left;
        margin-left: 0;
        margin-bottom: 20px;
    }
    
    .stats-container {
        display: flex;
        gap: 15px;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        width: 100%;
        max-width: 100%;
        margin: 0 auto 20px auto;
    }
    
    .stats-container > div {
        flex: 1;
        min-width: 200px;
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
        transition: transform 0.3s;
    }
    
    .stats-container > div:hover {
        transform: translateY(-5px);
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        margin-bottom: 10px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .total-icon {
        background-color: #9b59b6;
        color: white;
    }
    
    .pending-icon {
        background-color: #f39c12;
        color: white;
    }
    
    .published-icon {
        background-color: #27ae60;
        color: white;
    }
    
    .approved-icon {
        background-color: #3498db;
        color: white;
    }
    
    .stats-title {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin: 8px 0 5px 0;
    }
    
    .stats-number {
        font-size: 28px;
        font-weight: 700;
        color: #34495e;
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
    
    /* Form styles */
    .form-control {
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 8px;
        width: 100%;
    }
    
    .form-control:focus {
        outline: none;
        border-color: rgba(180, 80, 50, 1);
        box-shadow: 0 0 5px rgba(180, 80, 50, 0.5);
    }
    
    .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .form-check-input {
        margin-right: 10px;
    }
    
    /* Modal styles */
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
        color: #333;
    }
    
    .close-modal {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
        transition: color 0.3s;
    }
    
    .close-modal:hover {
        color: #000;
    }
    
    /* Import instructions */
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
        font-weight: 600;
    }
    
    .download-template:hover {
        text-decoration: underline;
    }
    
    /* Scrollbar styling */
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
    
    /* Action buttons in table */
    .btn-group-sm {
        display: flex;
        gap: 5px;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 0.2rem;
    }
    
    .btn-primary {
        background-color: rgba(180, 80, 50, 1);
        border-color: rgba(180, 80, 50, 1);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #a52a2a;
        border-color: #a52a2a;
    }
    
    /* Utility classes */
    .d-flex {
        display: flex;
    }
    
    .justify-content-between {
        justify-content: space-between;
    }
    
    .align-items-center {
        align-items: center;
    }
    
    .text-muted {
        color: #6c757d !important;
    }
    
    .ms-2 {
        margin-left: 0.5rem !important;
    }
    
    .mb-0 {
        margin-bottom: 0 !important;
    }
    
    .form-select {
        display: inline-block;
        width: auto;
        padding: 0.375rem 2.25rem 0.375rem 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-select:focus {
        border-color: rgba(180, 80, 50, 0.5);
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(180, 80, 50, 0.25);
    }
    
    /* Page item styles */
    .page-item {
        display: inline-block;
    }
    
    .page-link {
        position: relative;
        display: block;
        padding: 0.375rem 0.75rem;
        margin-left: -1px;
        line-height: 1.25;
        color: rgba(180, 80, 50, 1);
        background-color: #fff;
        border: 1px solid #dee2e6;
        text-decoration: none;
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    }
    
    .page-link:hover {
        z-index: 2;
        color: rgba(180, 80, 50, 0.8);
        background-color: #e9ecef;
        border-color: #dee2e6;
    }
    
    .page-item.active .page-link {
        z-index: 3;
        color: #fff;
        background-color: rgba(180, 80, 50, 1);
        border-color: rgba(180, 80, 50, 1);
    }
    
    .page-item.disabled .page-link {
        color: #6c757d;
        pointer-events: none;
        background-color: #fff;
        border-color: #dee2e6;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .btn-group {
            justify-content: center;
        }
        
        .stats-container {
            flex-direction: column;
        }
        
        .stats-container > div {
            width: 100%;
            min-width: auto;
        }
        
        table {
            font-size: 12px;
        }
        
        table th, table td {
            padding: 8px;
        }
        
        .pagination {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Miller Prices Management</h3></div>
    <p class="p">Manage everything related to Miller Prices data</p>

    <?php
    // Fetch counts for summary boxes
    $total_miller_query = "SELECT COUNT(*) AS total FROM miller_prices";
    $total_miller_result = $con->query($total_miller_query);
    $total_miller = 0;
    if ($total_miller_result) {
        $row = $total_miller_result->fetch_assoc();
        $total_miller = $row['total'];
    }

    $pending_query = "SELECT COUNT(*) AS total FROM miller_prices WHERE status = 'pending'";
    $pending_result = $con->query($pending_query);
    $pending_count = 0;
    if ($pending_result) {
        $row = $pending_result->fetch_assoc();
        $pending_count = $row['total'];
    }

    $published_query = "SELECT COUNT(*) AS total FROM miller_prices WHERE status = 'published'";
    $published_result = $con->query($published_query);
    $published_count = 0;
    if ($published_result) {
        $row = $published_result->fetch_assoc();
        $published_count = $row['total'];
    }

    $approved_query = "SELECT COUNT(*) AS total FROM miller_prices WHERE status = 'approved'";
    $approved_result = $con->query($approved_query);
    $approved_count = 0;
    if ($approved_result) {
        $row = $approved_result->fetch_assoc();
        $approved_count = $row['total'];
    }
    ?>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stats-title">Total Prices</div>
            <div class="stats-number"><?php echo $total_miller; ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon pending-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-title">Pending</div>
            <div class="stats-number"><?php echo $pending_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon approved-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-title">Approved</div>
            <div class="stats-number"><?php echo $approved_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon published-icon">
                <i class="fas fa-upload"></i>
            </div>
            <div class="stats-title">Published</div>
            <div class="stats-number"><?php echo $published_count; ?></div>
        </div>
    </div>
</div>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?= $import_status ?>">
        <?= htmlspecialchars($import_message) ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="btn-group">
        <a href="../data/add_miller_prices.php" class="btn btn-add-new">
            <i class="fas fa-plus" style="margin-right: 5px;"></i>
            Add New
        </a>

        <button class="btn btn-import" onclick="openImportModal()">
            <i class="fas fa-upload" style="margin-right: 5px;"></i>
            Import
        </button>

        <button class="btn btn-delete" onclick="deleteSelected()">
            <i class="fas fa-trash" style="margin-right: 5px;"></i>
            Delete
            <?php if (count($_SESSION['selected_miller_prices']) > 0): ?>
                <span class="selected-count"><?php echo count($_SESSION['selected_miller_prices']); ?></span>
            <?php endif; ?>
        </button>

        <button class="btn btn-clear-selections" onclick="clearAllSelections()">
            <i class="fas fa-times-circle" style="margin-right: 5px;"></i>
            Clear Selections
        </button>

        <div class="dropdown">
            <button class="btn btn-export dropdown-toggle" type="button" onclick="toggleExportDropdown()">
                <i class="fas fa-file-export" style="margin-right: 5px;"></i>
                Export
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
        
        <button class="btn btn-clear-filters" onclick="clearAllFilters()">
            <i class="fas fa-filter" style="margin-right: 5px;"></i>
            Clear Filters
        </button>

        <button class="btn btn-approve" onclick="approveSelected()">
            <i class="fas fa-check-circle" style="margin-right: 5px;"></i>
            Approve
        </button>

        <button class="btn btn-unpublish" onclick="unpublishSelected()">
            <i class="fas fa-ban" style="margin-right: 5px;"></i>
            Unpublish
        </button>

        <button class="btn btn-publish" onclick="publishSelected()">
            <i class="fas fa-upload" style="margin-right: 5px;"></i>
            Publish
        </button>
    </div>

    <table>
        <thead>
            <tr style="background-color: #d3d3d3 !important; color: black !important;">
                <th><input type="checkbox" id="selectAll"></th>
                <th class="sortable <?php echo getSortClass('id'); ?>" onclick="sortTable('id')">
                    ID
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('country'); ?>" onclick="sortTable('country')">
                    Country
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('town'); ?>" onclick="sortTable('town')">
                    Town
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('commodity'); ?>" onclick="sortTable('commodity')">
                    Commodity
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('price_usd'); ?>" onclick="sortTable('price_usd')">
                    Price (USD)
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('day_change'); ?>" onclick="sortTable('day_change')">
                    Day Change %
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('month_change'); ?>" onclick="sortTable('month_change')">
                    Month Change %
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('date'); ?>" onclick="sortTable('date')">
                    Date
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('status'); ?>" onclick="sortTable('status')">
                    Status
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('data_source'); ?>" onclick="sortTable('data_source')">
                    Data Source
                    <span class="sort-icon"></span>
                </th>
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
                    <input type="text" class="filter-input" id="filterCountry" 
                           placeholder="Filter country" 
                           value="<?php echo htmlspecialchars($filters['country']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterTown" 
                           placeholder="Filter town" 
                           value="<?php echo htmlspecialchars($filters['town']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterCommodity" 
                           placeholder="Filter commodity" 
                           value="<?php echo htmlspecialchars($filters['commodity']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterPrice" placeholder="Filter price"
                           value="<?php echo isset($_GET['filter_price']) ? htmlspecialchars($_GET['filter_price']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th></th>
                <th></th>
                <th>
                    <input type="text" class="filter-input" id="filterDate" 
                           placeholder="YYYY-MM-DD" 
                           value="<?php echo htmlspecialchars($filters['date']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterStatus" 
                           placeholder="Filter status" 
                           value="<?php echo htmlspecialchars($filters['status']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterDataSource" 
                           placeholder="Filter data source" 
                           value="<?php echo htmlspecialchars($filters['data_source']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th></th>
            </tr>
        </thead>
        <tbody id="millerTable">
            <?php foreach ($miller_prices_data as $price): ?>
                <tr>
                    <td>
                        <input type="checkbox" 
                               class="row-checkbox" 
                               data-id="<?php echo $price['id']; ?>"
                               <?php echo in_array($price['id'], $_SESSION['selected_miller_prices']) ? 'checked' : ''; ?>
                               onchange="updateSelection(this, <?php echo $price['id']; ?>)">
                    </td>
                    <td><?php echo htmlspecialchars($price['id']); ?></td>
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
                        <div class="btn-group" role="group">
                            <a href="../data/edit_miller_price.php?id=<?= $price['id'] ?>" class="btn btn-sm btn-primary">
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
            Displaying <?php echo ($offset + 1) . ' to ' . min($offset + $limit, $total_records) . ' of ' . $total_records; ?> items
            <?php if (count($_SESSION['selected_miller_prices']) > 0): ?>
                <span class="selected-count"><?php echo count($_SESSION['selected_miller_prices']); ?> selected across all pages</span>
            <?php endif; ?>
            <?php if (!empty($sort_column)): ?>
                <span class="text-muted ms-2">Sorted by: <?php echo ucfirst(str_replace('_', ' ', $sort_column)); ?> (<?php echo $sort_order; ?>)</span>
            <?php endif; ?>
        </div>
        <div>
            <label for="itemsPerPage">Show:</label>
            <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </div>
        <nav>
            <ul class="pagination mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo ($page <= 1) ? '#' : getPageUrl($page - 1, $limit, $sort_column, $sort_order); ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo getPageUrl($i, $limit, $sort_column, $sort_order); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : getPageUrl($page + 1, $limit, $sort_column, $sort_order); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- Import Modal (same as before) -->
<div class="modal" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Miller Prices</h5>
                <button type="button" class="close-modal" onclick="closeImportModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Import instructions and form -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize select all checkbox
    updateSelectAllCheckbox();
    
    document.getElementById('selectAll').addEventListener('change', function() {
        const isChecked = this.checked;
        const checkboxes = document.querySelectorAll('.row-checkbox');
        
        // Update all checkboxes on current page
        checkboxes.forEach(checkbox => {
            if (checkbox.checked !== isChecked) {
                checkbox.checked = isChecked;
                // Trigger the update for each checkbox
                const id = checkbox.getAttribute('data-id');
                updateSelection(checkbox, id);
            }
        });
        
        // Clear all selections if unchecking
        if (!isChecked) {
            clearAllSelectionsSilent();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const exportDropdown = document.getElementById('exportDropdown');
        const exportButton = document.querySelector('.btn-export');
        
        if (exportButton && exportDropdown && !exportButton.contains(event.target) && !exportDropdown.contains(event.target)) {
            exportDropdown.classList.remove('show');
        }
    });
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Miller Prices');
    }
});

function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.classList.toggle('show');
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
        // New column, default to DESC for ID and date, ASC for others
        const defaultOrder = (column === 'id' || column === 'date') ? 'DESC' : 'ASC';
        url.searchParams.set('sort', column);
        url.searchParams.set('order', defaultOrder);
    }
    
    // Reset to page 1 when sorting
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function applyFilters() {
    const filters = {
        id: document.getElementById('filterId').value,
        country: document.getElementById('filterCountry').value,
        town: document.getElementById('filterTown').value,
        commodity: document.getElementById('filterCommodity').value,
        price: document.getElementById('filterPrice').value,
        date: document.getElementById('filterDate').value,
        status: document.getElementById('filterStatus').value,
        data_source: document.getElementById('filterDataSource').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.id) url.searchParams.set('filter_id', filters.id);
    else url.searchParams.delete('filter_id');
    
    if (filters.country) url.searchParams.set('filter_country', filters.country);
    else url.searchParams.delete('filter_country');
    
    if (filters.town) url.searchParams.set('filter_town', filters.town);
    else url.searchParams.delete('filter_town');
    
    if (filters.commodity) url.searchParams.set('filter_commodity', filters.commodity);
    else url.searchParams.delete('filter_commodity');
    
    if (filters.price) url.searchParams.set('filter_price', filters.price);
    else url.searchParams.delete('filter_price');
    
    if (filters.date) url.searchParams.set('filter_date', filters.date);
    else url.searchParams.delete('filter_date');
    
    if (filters.status) url.searchParams.set('filter_status', filters.status);
    else url.searchParams.delete('filter_status');
    
    if (filters.data_source) url.searchParams.set('filter_data_source', filters.data_source);
    else url.searchParams.delete('filter_data_source');
    
    // Reset to page 1 when filtering
    url.searchParams.set('page', '1');
    
    // Navigate to filtered URL
    window.location.href = url.toString();
}

function clearAllFilters() {
    // Clear all filter inputs
    document.getElementById('filterId').value = '';
    document.getElementById('filterCountry').value = '';
    document.getElementById('filterTown').value = '';
    document.getElementById('filterCommodity').value = '';
    document.getElementById('filterPrice').value = '';
    document.getElementById('filterDate').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterDataSource').value = '';
    
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
 * Update selection via AJAX
 */
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

function updateSelectionCount() {
    // Refresh the page to update selection count
    location.reload();
}

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

/**
 * Get all selected price IDs from session
 */
function getSelectedPriceIds() {
    // Return IDs from PHP session (not just current page)
    return <?php echo json_encode($_SESSION['selected_miller_prices']); ?>;
}

/**
 * Export selected items
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
    
    // Close dropdown
    document.getElementById('exportDropdown').classList.remove('show');
}

/**
 * Export all data (without filters)
 */
function exportAll(format) {
    if (confirm('Export ALL miller prices? This may take a moment for large datasets.')) {
        const params = new URLSearchParams();
        params.append('export', format);
        params.append('export_all', 'true');
        
        window.open('export_miller_prices.php?' + params.toString(), '_blank');
        
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
        country: document.getElementById('filterCountry').value,
        town: document.getElementById('filterTown').value,
        commodity: document.getElementById('filterCommodity').value,
        price: document.getElementById('filterPrice').value,
        date: document.getElementById('filterDate').value,
        status: document.getElementById('filterStatus').value,
        data_source: document.getElementById('filterDataSource').value
    };
    
    // Count how many filters are active
    const activeFilters = Object.values(filters).filter(val => val.trim() !== '').length;
    
    let message = 'Export ';
    if (activeFilters > 0) {
        message += 'all data with current filters applied?';
    } else {
        message += 'ALL miller prices (no filters active)?';
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
        
        window.open('export_miller_prices.php?' + params.toString(), '_blank');
        
        // Close dropdown
        document.getElementById('exportDropdown').classList.remove('show');
    }
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
    if (ids.length === 0) {
        alert('Please select items to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + ids.length + ' selected miller price(s) across all pages?')) {
        // Pass all selected IDs from session
        fetch('delete_miller_prices.php', {
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

// Keyboard support for closing modal
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImportModal();
    }
});
</script>

<?php 
// Helper function to get sort CSS class
function getSortClass($column) {
    $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
    $current_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
    
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? 'sort-asc' : 'sort-desc';
    }
    return '';
}

// Helper function to generate page URLs with filters and sorting
function getPageUrl($pageNum, $itemsPerPage, $sortColumn = null, $sortOrder = null) {
    global $filters;
    
    $url = '?page=' . $pageNum . '&limit=' . $itemsPerPage;
    
    // Add filter parameters
    if (!empty($filters['country'])) {
        $url .= '&filter_country=' . urlencode($filters['country']);
    }
    if (!empty($filters['town'])) {
        $url .= '&filter_town=' . urlencode($filters['town']);
    }
    if (!empty($filters['commodity'])) {
        $url .= '&filter_commodity=' . urlencode($filters['commodity']);
    }
    if (!empty($filters['date'])) {
        $url .= '&filter_date=' . urlencode($filters['date']);
    }
    if (!empty($filters['status'])) {
        $url .= '&filter_status=' . urlencode($filters['status']);
    }
    if (!empty($filters['data_source'])) {
        $url .= '&filter_data_source=' . urlencode($filters['data_source']);
    }
    
    // Add sort parameters if provided
    if ($sortColumn) {
        $url .= '&sort=' . urlencode($sortColumn);
    }
    if ($sortOrder) {
        $url .= '&order=' . urlencode($sortOrder);
    }
    
    return $url;
}

include '../admin/includes/footer.php'; 
?>