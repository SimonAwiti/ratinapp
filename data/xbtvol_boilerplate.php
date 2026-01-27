<?php
// base/xbtvolumes_boilerplate.php

// Start session at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize session storage for selected items if not exists
if (!isset($_SESSION['selected_xbt_volumes'])) {
    $_SESSION['selected_xbt_volumes'] = [];
}

// Include the configuration file first
include '../admin/includes/config.php';

// Handle AJAX selection updates
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_xbt_volumes'])) {
            $_SESSION['selected_xbt_volumes'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_xbt_volumes']);
        if ($key !== false) {
            unset($_SESSION['selected_xbt_volumes'][$key]);
            $_SESSION['selected_xbt_volumes'] = array_values($_SESSION['selected_xbt_volumes']);
        }
    }
    
    // Clear all selections if requested
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_xbt_volumes'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_xbt_volumes'])]);
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
            
            // Validate required fields - Based on xbt_volumes table structure
            if (empty(trim($data[0]))) {
                $errors[] = "Row $rowNumber: Border ID is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[1]))) {
                $errors[] = "Row $rowNumber: Commodity ID is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[2]))) {
                $errors[] = "Row $rowNumber: Volume is required";
                $errorCount++;
                continue;
            }
            if (empty(trim($data[3]))) {
                $errors[] = "Row $rowNumber: Date is required";
                $errorCount++;
                continue;
            }
            
            // Prepare XBT volume data
            $border_id = intval(trim($data[0]));
            $commodity_id = intval(trim($data[1]));
            $volume = floatval(trim($data[2]));
            
            // Date parsing (same as market prices)
            $raw_date_string = trim($data[3]);
            error_log("Raw date string from CSV: '$raw_date_string'");
            
            $date_string = trim($data[3]);
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
            
            // Optional fields with defaults
            $source = isset($data[4]) ? trim($data[4]) : '';
            $destination = isset($data[5]) ? trim($data[5]) : '';
            $country = isset($data[6]) ? trim($data[6]) : 'Kenya'; // Default country
            
            // Handle data source ID - ensure it's valid
            $data_source_id_input = isset($data[7]) ? trim($data[7]) : '';
            if (empty($data_source_id_input) || $data_source_id_input == '0') {
                $data_source_id = 1; // Default to AGRA if empty or 0
            } else {
                $data_source_id = intval($data_source_id_input);
            }

            $status = isset($data[8]) ? trim($data[8]) : 'pending'; // STATUS ASSIGNMENT BEFORE VALIDATION
            
            // Extract date components
            $day = date('d', strtotime($date_posted));
            $month = date('m', strtotime($date_posted));
            $year = date('Y', strtotime($date_posted));
            
            // Validate status - NOW THIS COMES AFTER STATUS IS ASSIGNED
            $valid_statuses = ['pending', 'approved', 'published', 'unpublished'];
            if (!in_array($status, $valid_statuses)) {
                $errors[] = "Row $rowNumber: Invalid status '$status'";
                $errorCount++;
                continue;
            }
            
            // Get border name and country if not provided
            $border_name = "";
            $border_country = $country;
            $border_query = "SELECT name, country FROM border_points WHERE id = ? LIMIT 1";
            $border_stmt = $con->prepare($border_query);
            if (!$border_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare border query: " . $con->error;
                $errorCount++;
                continue;
            }
            $border_stmt->bind_param('i', $border_id);
            $border_stmt->execute();
            $border_result = $border_stmt->get_result();
            if ($border_result->num_rows > 0) {
                $border_row = $border_result->fetch_assoc();
                $border_name = $border_row['name'];
                // Use border country if not provided in CSV
                if (empty($country) && !empty($border_row['country'])) {
                    $border_country = $border_row['country'];
                }
            } else {
                $errors[] = "Row $rowNumber: Border ID '$border_id' not found";
                $errorCount++;
                $border_stmt->close();
                continue;
            }
            $border_stmt->close();
            
            // Get commodity name
            $commodity_name = "";
            $commodity_query = "SELECT commodity_name, variety FROM commodities WHERE id = ? LIMIT 1";
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
                $variety = $commodity_row['variety'];
            } else {
                $errors[] = "Row $rowNumber: Commodity ID '$commodity_id' not found";
                $errorCount++;
                $commodity_stmt->close();
                continue;
            }
            $commodity_stmt->close();

            // Get data source name
            $data_source_name = "AGRA"; // Default
            $source_query = "SELECT data_source_name FROM data_sources WHERE id = ? LIMIT 1";
            $source_stmt = $con->prepare($source_query);
            if ($source_stmt) {
                $source_stmt->bind_param('i', $data_source_id);
                $source_stmt->execute();
                $source_result = $source_stmt->get_result();
                if ($source_result->num_rows > 0) {
                    $source_row = $source_result->fetch_assoc();
                    $data_source_name = $source_row['data_source_name'];
                } else {
                    // If data source ID not found, use default and log warning
                    error_log("Warning: Data Source ID '$data_source_id' not found, using default AGRA");
                    $data_source_id = 1; // Reset to default AGRA
                    $data_source_name = "AGRA";
                }
                $source_stmt->close();
            } else {
                error_log("Failed to prepare data source query: " . $con->error);
                // Continue with defaults
            }
            
            // DEBUG: Log what we're about to insert
            error_log("Preparing to insert: border=$border_id, commodity=$commodity_id, volume=$volume, country=$border_country, date=$date_posted");
            
            // Check if XBT volume record already exists
            $check_query = "SELECT id FROM xbt_volumes WHERE border_id = ? AND commodity_id = ? AND DATE(date_posted) = DATE(?)";
            $check_stmt = $con->prepare($check_query);
            if (!$check_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare check query: " . $con->error;
                $errorCount++;
                continue;
            }
            $check_stmt->bind_param('iis', $border_id, $commodity_id, $date_posted);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    // Update existing volume
                    $update_query = "UPDATE xbt_volumes SET 
                        volume = ?,
                        source = ?,
                        destination = ?,
                        country = ?,
                        data_source_id = ?,
                        data_source_name = ?,
                        status = ?,
                        day = ?,
                        month = ?,
                        year = ?
                        WHERE border_id = ? AND commodity_id = ? AND DATE(date_posted) = DATE(?)";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        $check_stmt->close();
                        continue;
                    }
                    
                    $update_stmt->bind_param(
                        'dsssisiiiiss',
                        $volume,
                        $source,
                        $destination,
                        $border_country,
                        $data_source_id,
                        $data_source_name,
                        $status,
                        $day,
                        $month,
                        $year,
                        $border_id,
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
                    $errors[] = "Row $rowNumber: XBT volume record already exists (use overwrite option to update)";
                    $errorCount++;
                }
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert new XBT volume record
            $insert_query = "INSERT INTO xbt_volumes (
                border_id,
                border_name,
                commodity_id,
                commodity_name,
                variety,
                volume,
                source,
                destination,
                country,
                data_source_id,
                data_source_name,
                date_posted,
                day,
                month,
                year,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }

            $insert_stmt->bind_param(
                'isisdsssissiiis',
                $border_id,
                $border_name,
                $commodity_id,
                $commodity_name,
                $variety,
                $volume,
                $source,
                $destination,
                $border_country,
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
            $_SESSION['import_message'] = "Successfully imported $successCount XBT volumes.";
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
    header("Location: xbtvol_boilerplate.php");
    exit;
    
} elseif (isset($_POST['import_csv'])) {
    $_SESSION['import_message'] = "Please select a valid CSV file to import.";
    $_SESSION['import_status'] = 'danger';
    header("Location: xbtvol_boilerplate.php");
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

// Function to fetch XBT volumes data from the database with filters and sorting
function getXBTVolumesData($con, $limit = 10, $offset = 0, $filters = [], $sort_column = 'date_posted', $sort_order = 'DESC') {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters if provided
    if (!empty($filters['border'])) {
        $where_clauses[] = "b.name LIKE ?";
        $params[] = '%' . $filters['border'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['commodity'])) {
        $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
        $params[] = '%' . $filters['commodity'] . '%';
        $params[] = '%' . $filters['commodity'] . '%';
        $types .= 'ss';
    }
    
    if (!empty($filters['source'])) {
        $where_clauses[] = "x.source LIKE ?";
        $params[] = '%' . $filters['source'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['destination'])) {
        $where_clauses[] = "x.destination LIKE ?";
        $params[] = '%' . $filters['destination'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(x.date_posted) LIKE ?";
        $params[] = '%' . $filters['date'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $where_clauses[] = "x.status LIKE ?";
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
        'id' => 'x.id',
        'border' => 'b.name',
        'commodity' => 'c.commodity_name',
        'volume' => 'x.volume',
        'source' => 'x.source',
        'destination' => 'x.destination',
        'date' => 'x.date_posted',
        'status' => 'x.status',
        'data_source' => 'ds.data_source_name'
    ];
    
    $default_sort_column = 'date_posted';
    $default_sort_order = 'DESC';
    
    $db_sort_column = isset($sortable_columns[$sort_column]) ? $sortable_columns[$sort_column] : $sortable_columns['date'];
    $db_sort_order = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : $default_sort_order;
    
    $sql = "SELECT
                x.id,
                b.name AS border_name,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                x.volume,
                x.source,
                x.destination,
                x.date_posted,
                x.status,
                ds.data_source_name AS data_source
            FROM
                xbt_volumes x
            LEFT JOIN
                border_points b ON x.border_id = b.id
            LEFT JOIN
                commodities c ON x.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON x.data_source_id = ds.id
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

function getTotalXBTVolumeRecords($con, $filters = []) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Apply filters if provided
    if (!empty($filters['border'])) {
        $where_clauses[] = "b.name LIKE ?";
        $params[] = '%' . $filters['border'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['commodity'])) {
        $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
        $params[] = '%' . $filters['commodity'] . '%';
        $params[] = '%' . $filters['commodity'] . '%';
        $types .= 'ss';
    }
    
    if (!empty($filters['source'])) {
        $where_clauses[] = "x.source LIKE ?";
        $params[] = '%' . $filters['source'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['destination'])) {
        $where_clauses[] = "x.destination LIKE ?";
        $params[] = '%' . $filters['destination'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['date'])) {
        $where_clauses[] = "DATE(x.date_posted) LIKE ?";
        $params[] = '%' . $filters['date'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $where_clauses[] = "x.status LIKE ?";
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
            FROM xbt_volumes x
            LEFT JOIN border_points b ON x.border_id = b.id
            LEFT JOIN commodities c ON x.commodity_id = c.id
            LEFT JOIN data_sources ds ON x.data_source_id = ds.id
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
    'border' => isset($_GET['filter_border']) ? trim($_GET['filter_border']) : '',
    'commodity' => isset($_GET['filter_commodity']) ? trim($_GET['filter_commodity']) : '',
    'source' => isset($_GET['filter_source']) ? trim($_GET['filter_source']) : '',
    'destination' => isset($_GET['filter_destination']) ? trim($_GET['filter_destination']) : '',
    'date' => isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '',
    'status' => isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '',
    'data_source' => isset($_GET['filter_data_source']) ? trim($_GET['filter_data_source']) : ''
];

// Get sort parameters
$sortable_columns = ['id', 'border', 'commodity', 'volume', 'source', 'destination', 'date', 'status', 'data_source'];
$default_sort_column = 'date';
$default_sort_order = 'DESC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

// Get total number of records with filters
$total_records = getTotalXBTVolumeRecords($con, $filters);

// Set pagination parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch XBT volumes data with filters and sorting
$xbt_volumes_data = getXBTVolumesData($con, $limit, $offset, $filters, $sort_column, $sort_order);

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
    
    /* Button group styling - FIXED */
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
    }
    
    .btn-add-new:hover {
        background-color: darkred;
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
    }
    
    .btn-delete:hover, .btn-export:hover, .btn-import:hover, 
    .btn-bulk-export:hover, .btn-clear-selections:hover,
    .btn-clear-filters:hover {
        background-color: #f8f9fa;
    }
    
    .btn-clear-selections {
        background-color: #ffc107;
        color: black;
    }
    
    .btn-clear-selections:hover {
        background-color: #e0a800;
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
        background-color: darkred;
    }
    
    .btn-publish {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        border: none;
    }
    
    .btn-publish:hover {
        background-color: darkred;
    }
    
    .btn-clear-filters {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
    }
    
    .btn-clear-filters:hover {
        background-color: #f8f9fa;
    }
    
    .dropdown-menu {
        min-width: 120px;
    }
    
    .dropdown-item {
        cursor: pointer;
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
    }
    .filter-input:focus {
        outline: none;
        border-color: rgba(180, 80, 50, 1);
        box-shadow: 0 0 0 2px rgba(180, 80, 50, 0.1);
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
        min-width: 160px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        z-index: 1000;
        border-radius: 4px;
        padding: 5px 0;
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
    }
    .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    .dropdown-divider {
        height: 1px;
        margin: 5px 0;
        background-color: #e5e7eb;
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
    
    /* Toolbar layout */
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .toolbar-left,
    .toolbar-right {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    
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
        flex-wrap: nowrap;
        width: 100%;
        max-width: 100%;
        margin: 0 auto 20px auto;
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
        font-size: 24px;
        font-weight: 700;
        color: #34495e;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>XBT Volumes Management</h3></div>
    <p class="p">Manage everything related to Cross Border Trade Volume Data</p>

    <?php
    // Fetch counts for summary boxes
    $total_xbt_query = "SELECT COUNT(*) AS total FROM xbt_volumes";
    $total_xbt_result = $con->query($total_xbt_query);
    $total_xbt = 0;
    if ($total_xbt_result) {
        $row = $total_xbt_result->fetch_assoc();
        $total_xbt = $row['total'];
    }

    $pending_query = "SELECT COUNT(*) AS total FROM xbt_volumes WHERE status = 'pending'";
    $pending_result = $con->query($pending_query);
    $pending_count = 0;
    if ($pending_result) {
        $row = $pending_result->fetch_assoc();
        $pending_count = $row['total'];
    }

    $published_query = "SELECT COUNT(*) AS total FROM xbt_volumes WHERE status = 'published'";
    $published_result = $con->query($published_query);
    $published_count = 0;
    if ($published_result) {
        $row = $published_result->fetch_assoc();
        $published_count = $row['total'];
    }

    $approved_query = "SELECT COUNT(*) AS total FROM xbt_volumes WHERE status = 'approved'";
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
                <i class="fas fa-database"></i>
            </div>
            <div class="stats-title">Total Volumes</div>
            <div class="stats-number"><?php echo $total_xbt; ?></div>
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
        <a href="../data/add_xbtvol.php" class="btn btn-add-new">
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
            <?php if (count($_SESSION['selected_xbt_volumes']) > 0): ?>
                <span class="selected-count"><?php echo count($_SESSION['selected_xbt_volumes']); ?></span>
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
                <th class="sortable <?php echo getSortClass('border'); ?>" onclick="sortTable('border')">
                    Border Point
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('commodity'); ?>" onclick="sortTable('commodity')">
                    Commodity
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('volume'); ?>" onclick="sortTable('volume')">
                    Volume (MT)
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('source'); ?>" onclick="sortTable('source')">
                    Source
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('destination'); ?>" onclick="sortTable('destination')">
                    Destination
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
                    <input type="text" class="filter-input" id="filterBorder" 
                           placeholder="Filter border" 
                           value="<?php echo htmlspecialchars($filters['border']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterCommodity" 
                           placeholder="Filter commodity" 
                           value="<?php echo htmlspecialchars($filters['commodity']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterVolume" placeholder="Filter volume"
                           value="<?php echo isset($_GET['filter_volume']) ? htmlspecialchars($_GET['filter_volume']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterSource" 
                           placeholder="Filter source" 
                           value="<?php echo htmlspecialchars($filters['source']); ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterDestination" 
                           placeholder="Filter destination" 
                           value="<?php echo htmlspecialchars($filters['destination']); ?>"
                           onkeyup="applyFilters()">
                </th>
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
        <tbody id="xbtTable">
            <?php foreach ($xbt_volumes_data as $volume): ?>
                <tr>
                    <td>
                        <input type="checkbox" 
                               class="row-checkbox" 
                               data-id="<?php echo $volume['id']; ?>"
                               <?php echo in_array($volume['id'], $_SESSION['selected_xbt_volumes']) ? 'checked' : ''; ?>
                               onchange="updateSelection(this, <?php echo $volume['id']; ?>)">
                    </td>
                    <td><?php echo htmlspecialchars($volume['id']); ?></td>
                    <td><?php echo htmlspecialchars($volume['border_name']); ?></td>
                    <td><?php echo htmlspecialchars($volume['commodity_display']); ?></td>
                    <td><?php echo htmlspecialchars($volume['volume']); ?></td>
                    <td><?php echo htmlspecialchars($volume['source']); ?></td>
                    <td><?php echo htmlspecialchars($volume['destination']); ?></td>
                    <td class="date-added"><?php echo date('Y-m-d', strtotime($volume['date_posted'])); ?></td>
                    <td><?php echo getStatusDisplay($volume['status']); ?></td>
                    <td><?php echo htmlspecialchars($volume['data_source']); ?></td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="../data/edit_xbt_volume.php?id=<?= $volume['id'] ?>" class="btn btn-sm btn-primary">
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
            <?php if (count($_SESSION['selected_xbt_volumes']) > 0): ?>
                <span class="selected-count"><?php echo count($_SESSION['selected_xbt_volumes']); ?> selected across all pages</span>
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

<!-- Import Modal -->
<div class="modal" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import XBT Volumes</h5>
                <button type="button" class="close-modal" onclick="closeImportModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Import Instructions</h5>
                    <p>Your CSV file should have the following columns in order:</p>
                    <ol>
                        <li><strong>Border ID</strong> (required) - Border Point ID from border_points table</li>
                        <li><strong>Commodity ID</strong> (required) - Commodity ID from commodities table</li>
                        <li><strong>Volume</strong> (required) - Volume in metric tons (numeric)</li>
                        <li><strong>Date Posted</strong> (required) - YYYY-MM-DD format</li>
                        <li><strong>Source</strong> (optional) - Source country/region</li>
                        <li><strong>Destination</strong> (optional) - Destination country/region</li>
                        <li><strong>Data Source ID</strong> (optional) - Data Source ID from data_sources table (default: 1)</li>
                        <li><strong>Status</strong> (optional) - pending/approved/published/unpublished (default: pending)</li>
                    </ol>
                    
                    <h6>Example CSV Format:</h6>
                    <pre>Border ID,Commodity ID,Volume,Date Posted,Source,Destination,Data Source ID,Status
1,40,1500.50,2025-06-03,Kenya,Uganda,1,published
2,41,2000.75,2025-06-03,Tanzania,Rwanda,1,approved</pre>
                    
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>Border IDs must exist in your border_points table</li>
                        <li>Commodity IDs must exist in your commodities table</li>
                        <li>Data Source IDs must exist in your data_sources table</li>
                        <li>Border names and commodity names will be automatically fetched</li>
                        <li>All required fields must have values</li>
                    </ul>
                    
                    <a href="downloads/xbt_volumes_template.csv" class="download-template">
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
                            Overwrite existing volumes with matching border, commodity and date
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
        updateBreadcrumb('Base', 'XBT Volumes');
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
        border: document.getElementById('filterBorder').value,
        commodity: document.getElementById('filterCommodity').value,
        volume: document.getElementById('filterVolume').value,
        source: document.getElementById('filterSource').value,
        destination: document.getElementById('filterDestination').value,
        date: document.getElementById('filterDate').value,
        status: document.getElementById('filterStatus').value,
        data_source: document.getElementById('filterDataSource').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.id) url.searchParams.set('filter_id', filters.id);
    else url.searchParams.delete('filter_id');
    
    if (filters.border) url.searchParams.set('filter_border', filters.border);
    else url.searchParams.delete('filter_border');
    
    if (filters.commodity) url.searchParams.set('filter_commodity', filters.commodity);
    else url.searchParams.delete('filter_commodity');
    
    if (filters.volume) url.searchParams.set('filter_volume', filters.volume);
    else url.searchParams.delete('filter_volume');
    
    if (filters.source) url.searchParams.set('filter_source', filters.source);
    else url.searchParams.delete('filter_source');
    
    if (filters.destination) url.searchParams.set('filter_destination', filters.destination);
    else url.searchParams.delete('filter_destination');
    
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
    document.getElementById('filterBorder').value = '';
    document.getElementById('filterCommodity').value = '';
    document.getElementById('filterVolume').value = '';
    document.getElementById('filterSource').value = '';
    document.getElementById('filterDestination').value = '';
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
 * Get all selected volume IDs from session
 */
function getSelectedVolumeIds() {
    // Return IDs from PHP session (not just current page)
    return <?php echo json_encode($_SESSION['selected_xbt_volumes']); ?>;
}

/**
 * Export selected items
 */
function exportSelected(format) {
    const selectedIds = getSelectedVolumeIds();
    
    if (selectedIds.length === 0) {
        alert('Please select items to export.');
        return;
    }
    
    // Create URL parameters for export
    const params = new URLSearchParams();
    params.append('export', format);
    params.append('ids', JSON.stringify(selectedIds));
    
    // Open export in new window
    window.open('export_xbt_volumes.php?' + params.toString(), '_blank');
    
    // Close dropdown
    document.getElementById('exportDropdown').classList.remove('show');
}

/**
 * Export all data (without filters)
 */
function exportAll(format) {
    if (confirm('Export ALL XBT volumes? This may take a moment for large datasets.')) {
        const params = new URLSearchParams();
        params.append('export', format);
        params.append('export_all', 'true');
        
        window.open('export_xbt_volumes.php?' + params.toString(), '_blank');
        
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
        border: document.getElementById('filterBorder').value,
        commodity: document.getElementById('filterCommodity').value,
        source: document.getElementById('filterSource').value,
        destination: document.getElementById('filterDestination').value,
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
        message += 'ALL XBT volumes (no filters active)?';
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
        
        window.open('export_xbt_volumes.php?' + params.toString(), '_blank');
        
        // Close dropdown
        document.getElementById('exportDropdown').classList.remove('show');
    }
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
        fetch('../data/update_xbt_status.php', {
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
    const ids = getSelectedVolumeIds();
    confirmAction('approve', ids);
}

function publishSelected() {
    const ids = getSelectedVolumeIds();
    if (ids.length === 0) {
        alert('Please select items to publish.');
        return;
    }
    
    // Check if all selected items are approved before publishing
    fetch('../data/check_xbt_status.php', {
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
    const ids = getSelectedVolumeIds();
    if (ids.length === 0) {
        alert('Please select items to unpublish.');
        return;
    }
    
    // Check if all selected items are currently published before unpublishing
    fetch('../data/check_xbt_status_for_unpublish.php', {
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
    const ids = getSelectedVolumeIds();
    if (ids.length === 0) {
        alert('Please select items to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + ids.length + ' selected XBT volume(s) across all pages?')) {
        // Pass all selected IDs from session
        fetch('delete_xbt_volumes.php', {
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

// Keyboard support for closing modal
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImportModal();
    }
});
</script>

<?php 
// Helper function to build filter parameters for URLs
function getFilterParams($filters, $sort_column = null, $sort_order = null) {
    $params = '';
    
    // Add filter parameters
    if (!empty($filters['border'])) {
        $params .= '&filter_border=' . urlencode($filters['border']);
    }
    if (!empty($filters['commodity'])) {
        $params .= '&filter_commodity=' . urlencode($filters['commodity']);
    }
    if (!empty($filters['source'])) {
        $params .= '&filter_source=' . urlencode($filters['source']);
    }
    if (!empty($filters['destination'])) {
        $params .= '&filter_destination=' . urlencode($filters['destination']);
    }
    if (!empty($filters['date'])) {
        $params .= '&filter_date=' . urlencode($filters['date']);
    }
    if (!empty($filters['status'])) {
        $params .= '&filter_status=' . urlencode($filters['status']);
    }
    if (!empty($filters['data_source'])) {
        $params .= '&filter_data_source=' . urlencode($filters['data_source']);
    }
    
    // Add sort parameters
    if ($sort_column) {
        $params .= '&sort=' . urlencode($sort_column);
    }
    if ($sort_order) {
        $params .= '&order=' . urlencode($sort_order);
    }
    
    return $params;
}

// Helper function to generate page URLs with filters and sorting
function getPageUrl($pageNum, $itemsPerPage, $sortColumn = null, $sortOrder = null) {
    global $filters;
    
    $url = '?page=' . $pageNum . '&limit=' . $itemsPerPage;
    
    // Add filter parameters
    if (!empty($filters['border'])) {
        $url .= '&filter_border=' . urlencode($filters['border']);
    }
    if (!empty($filters['commodity'])) {
        $url .= '&filter_commodity=' . urlencode($filters['commodity']);
    }
    if (!empty($filters['source'])) {
        $url .= '&filter_source=' . urlencode($filters['source']);
    }
    if (!empty($filters['destination'])) {
        $url .= '&filter_destination=' . urlencode($filters['destination']);
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

// Helper function to get sort CSS class
function getSortClass($column) {
    $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
    $current_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
    
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? 'sort-asc' : 'sort-desc';
    }
    return '';
}

include '../admin/includes/footer.php'; 
?>