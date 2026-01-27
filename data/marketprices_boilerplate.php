<?php
// base/marketprices_boilerplate.php

// Start session at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize selected market prices in session if not exists
if (!isset($_SESSION['selected_market_prices'])) {
    $_SESSION['selected_market_prices'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_market_prices'])) {
            $_SESSION['selected_market_prices'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_market_prices']);
        if ($key !== false) {
            unset($_SESSION['selected_market_prices'][$key]);
            $_SESSION['selected_market_prices'] = array_values($_SESSION['selected_market_prices']); // Re-index
        }
    }
    
    // Clear all selections
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_market_prices'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_market_prices'])]);
    exit;
}

// Clear all selections if requested via GET
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_market_prices'] = [];
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
            
            // Additional validation to ensure it's a reasonable date
            $parsed_timestamp = strtotime($date_posted);
            if ($parsed_timestamp < strtotime('2020-01-01') || $parsed_timestamp > strtotime('2030-12-31')) {
                $errors[] = "Row $rowNumber: Date '$date_posted' is out of reasonable range (2020-2030)";
                $errorCount++;
                continue;
            }
            
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
                    
                    $update_stmt->bind_param(
                        'dsssdsssissssiss',
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
            
            // Insert new price record
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
                commodity_sources_data,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }

            $bind_types = [
                's', 'i', 's', 'i', 's', 'd', 's', 's', 'd', 's', 
                'i', 'i', 'i', 's', 's', 's', 's', 'i', 's', 's', 's'
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

            $type_string = implode('', $bind_types);
            $insert_stmt->bind_param($type_string, ...$bind_values);

            if ($insert_stmt->execute()) {
                $successCount++;
            } else {
                $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
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

// Function to fetch prices data with filtering and sorting
function getPricesData($con, $filters = [], $sort_column = 'date_posted', $sort_order = 'DESC', $limit = 10, $offset = 0) {
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
                p.data_source,
                p.date_posted
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Apply filters
    if (!empty($filters['market'])) {
        $sql .= " AND p.market LIKE ?";
        $params[] = '%' . $filters['market'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['commodity'])) {
        $sql .= " AND (c.commodity_name LIKE ? OR CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) LIKE ?)";
        $params[] = '%' . $filters['commodity'] . '%';
        $params[] = '%' . $filters['commodity'] . '%';
        $types .= 'ss';
    }
    
    if (!empty($filters['date'])) {
        $sql .= " AND DATE(p.date_posted) LIKE ?";
        $params[] = '%' . $filters['date'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['type'])) {
        $sql .= " AND p.price_type LIKE ?";
        $params[] = '%' . $filters['type'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['price'])) {
        $sql .= " AND p.Price LIKE ?";
        $params[] = '%' . $filters['price'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND p.status LIKE ?";
        $params[] = '%' . $filters['status'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['source'])) {
        $sql .= " AND p.data_source LIKE ?";
        $params[] = '%' . $filters['source'] . '%';
        $types .= 's';
    }
    
    // Apply sorting
    $sortable_columns = ['market', 'commodity_name', 'date_posted', 'price_type', 'Price', 'status', 'data_source', 'created_at'];
    $db_sort_column = in_array($sort_column, $sortable_columns) ? $sort_column : 'date_posted';
    $db_sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Map column names for database
    $db_column_map = [
        'market' => 'p.market',
        'commodity' => 'commodity_display',
        'date_posted' => 'p.date_posted',
        'price_type' => 'p.price_type',
        'Price' => 'p.Price',
        'status' => 'p.status',
        'data_source' => 'p.data_source',
        'created_at' => 'p.date_posted'
    ];
    
    $db_sort_column = isset($db_column_map[$sort_column]) ? $db_column_map[$sort_column] : $db_column_map['date_posted'];
    
    if ($sort_column === 'commodity') {
        $sql .= " ORDER BY c.commodity_name $db_sort_order, c.variety $db_sort_order";
    } else {
        $sql .= " ORDER BY $db_sort_column $db_sort_order";
    }
    
    // Add limit and offset
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $con->prepare($sql);
    $data = [];
    
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $result->free();
        }
        $stmt->close();
    } else {
        error_log("Error fetching prices data: " . $con->error);
    }
    return $data;
}

function getTotalPriceRecords($con, $filters = []){
    $sql = "SELECT COUNT(*) as total 
            FROM market_prices p
            LEFT JOIN commodities c ON p.commodity = c.id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Apply filters
    if (!empty($filters['market'])) {
        $sql .= " AND p.market LIKE ?";
        $params[] = '%' . $filters['market'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['commodity'])) {
        $sql .= " AND (c.commodity_name LIKE ? OR CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) LIKE ?)";
        $params[] = '%' . $filters['commodity'] . '%';
        $params[] = '%' . $filters['commodity'] . '%';
        $types .= 'ss';
    }
    
    if (!empty($filters['date'])) {
        $sql .= " AND DATE(p.date_posted) LIKE ?";
        $params[] = '%' . $filters['date'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['type'])) {
        $sql .= " AND p.price_type LIKE ?";
        $params[] = '%' . $filters['type'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['price'])) {
        $sql .= " AND p.Price LIKE ?";
        $params[] = '%' . $filters['price'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND p.status LIKE ?";
        $params[] = '%' . $filters['status'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['source'])) {
        $sql .= " AND p.data_source LIKE ?";
        $params[] = '%' . $filters['source'] . '%';
        $types .= 's';
    }
    
    $stmt = $con->prepare($sql);
    $total = 0;
    
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $total = $row['total'];
        }
        $stmt->close();
    }
    return $total;
}

// Handle filters from GET
$filters = [
    'market' => isset($_GET['filter_market']) ? trim($_GET['filter_market']) : '',
    'commodity' => isset($_GET['filter_commodity']) ? trim($_GET['filter_commodity']) : '',
    'date' => isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '',
    'type' => isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '',
    'price' => isset($_GET['filter_price']) ? trim($_GET['filter_price']) : '',
    'status' => isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '',
    'source' => isset($_GET['filter_source']) ? trim($_GET['filter_source']) : ''
];

// Apply sorting
$sortable_columns = ['market', 'commodity', 'date_posted', 'price_type', 'Price', 'status', 'data_source', 'created_at'];
$default_sort_column = 'date_posted';
$default_sort_order = 'DESC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

// Get total number of records with filters
$total_records = getTotalPriceRecords($con, $filters);

// Set pagination parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Fetch prices data with filters, sorting, and pagination
$prices_data = getPricesData($con, $filters, $sort_column, $sort_order, $limit, $offset);

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

// Helper function to get sort CSS class
function getSortClass($column) {
    $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_posted';
    $current_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
    
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? 'sort-asc' : 'sort-desc';
    }
    return '';
}

// Helper function to generate page URLs with filters and sorting
function getPageUrl($pageNum, $limit, $sortColumn = null, $sortOrder = null, $filters = []) {
    $url = '?page=' . $pageNum . '&limit=' . $limit;
    
    // Add sort parameters if provided
    if ($sortColumn) {
        $url .= '&sort=' . urlencode($sortColumn);
    }
    if ($sortOrder) {
        $url .= '&order=' . urlencode($sortOrder);
    }
    
    // Add filter parameters if they exist
    $filterParams = ['filter_market', 'filter_commodity', 'filter_date', 'filter_type', 'filter_price', 'filter_status', 'filter_source'];
    foreach ($filterParams as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url .= '&' . $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $url;
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
    
    // Find the closest price to 30 days ago
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

// Helper function to determine change color
function getChangeClass($change) {
    if ($change === 'N/A') {
        return 'change-neutral';
    }
    
    // Extract numeric value from percentage string
    $numeric_value = floatval(str_replace('%', '', $change));
    
    if ($numeric_value > 0) {
        return 'change-positive';
    } elseif ($numeric_value < 0) {
        return 'change-negative';
    } else {
        return 'change-neutral';
    }
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
    .pending-icon {
        background-color: #f39c12;
        color: white;
    }
    .published-icon {
        background-color: #27ae60;
        color: white;
    }
    .wholesale-icon {
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

    /* Status dot colors */
    .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
        vertical-align: middle;
    }

    .status-pending {
        background-color: #ffc107;
    }

    .status-published {
        background-color: #28a745;
    }

    .status-approved {
        background-color: #17a2b8;
    }

    .status-unpublished {
        background-color: #dc3545;
    }

    /* Sortable header styling */
    .sortable {
        cursor: pointer;
        position: relative;
        user-select: none;
        white-space: nowrap;
        transition: background-color 0.2s ease;
    }

    .sortable:hover {
        background-color: #f8f9fa !important;
    }

    .sort-icon {
        display: inline-block;
        margin-left: 5px;
        font-size: 0.8em;
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .sort-asc .sort-icon::after {
        content: "↑";
        color: rgba(180, 80, 50, 1);
    }

    .sort-desc .sort-icon::after {
        content: "↓";
        color: rgba(180, 80, 50, 1);
    }

    .sortable.sort-asc,
    .sortable.sort-desc {
        background-color: #f0f0f0 !important;
        font-weight: 600;
    }

    .sortable.sort-asc .sort-icon,
    .sortable.sort-desc .sort-icon {
        opacity: 1;
    }

    /* Button styling improvements */
    .btn-approve, .btn-publish, .btn-unpublish {
        padding: 8px 16px;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid transparent;
    }

    .btn-approve {
        background-color: #28a745;
        color: white;
    }

    .btn-approve:hover {
        background-color: #218838;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .btn-publish {
        background-color: #17a2b8;
        color: white;
    }

    .btn-publish:hover {
        background-color: #138496;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .btn-unpublish {
        background-color: #dc3545;
        color: white;
    }

    .btn-unpublish:hover {
        background-color: #c82333;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Selected count badge */
    .selected-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(180, 80, 50, 0.15);
        color: rgba(180, 80, 50, 1);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        margin-left: 8px;
        font-weight: 600;
        min-width: 24px;
        height: 24px;
    }

    /* Clear filters button */
    .btn-clear-filters {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .btn-clear-filters:hover {
        background-color: #5a6268;
        transform: translateY(-1px);
    }

    /* Action button improvements */
    .btn-sm {
        padding: 4px 10px !important;
        font-size: 0.8rem !important;
        border-radius: 4px !important;
    }

    .btn-warning {
        background-color: #ffc107 !important;
        border-color: #ffc107 !important;
        color: #212529 !important;
    }

    .btn-warning:hover {
        background-color: #e0a800 !important;
        border-color: #d39e00 !important;
    }

    /* Form select styling */
    .form-select {
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 6px 12px;
        font-size: 0.875rem;
        height: 36px;
        transition: border-color 0.15s ease-in-out;
    }

    .form-select:focus {
        border-color: rgba(180, 80, 50, 1);
        box-shadow: 0 0 0 0.2rem rgba(180, 80, 50, 0.25);
        outline: none;
    }

    /* Pagination active state */
    .page-item.active .page-link {
        background-color: rgba(180, 80, 50, 1) !important;
        border-color: rgba(180, 80, 50, 1) !important;
        color: white !important;
    }

    .page-link {
        color: rgba(180, 80, 50, 0.8);
        border: 1px solid #dee2e6;
        padding: 6px 12px;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .page-link:hover {
        color: darkred;
        background-color: #f8f9fa;
        border-color: #dee2e6;
    }

    /* Empty state styling */
    .table tbody tr td[colspan] {
        text-align: center;
        padding: 40px !important;
        color: #6c757d;
        font-size: 1rem;
        background-color: #f9f9f9;
    }

    .table tbody tr td[colspan] i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
        color: #dee2e6;
        opacity: 0.7;
    }

    /* Alert message styling */
    .alert {
        margin: 20px 11% 20px 11%;
        padding: 15px 20px;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: 0.95rem;
    }

    .alert-success {
        color: #0f5132;
        background-color: #d1e7dd;
        border-color: #badbcc;
    }

    .alert-danger {
        color: #842029;
        background-color: #f8d7da;
        border-color: #f5c2c7;
    }

    .alert-warning {
        color: #664d03;
        background-color: #fff3cd;
        border-color: #ffecb5;
    }

    /* Rowspan styling */
    td[rowspan] {
        background-color: #f8f9fa;
        font-weight: 500;
        border-right: 2px solid #e9ecef !important;
    }

    /* Dropdown improvements */
    .dropdown-menu {
        min-width: 200px;
        border: 1px solid rgba(0,0,0,0.15);
        border-radius: 8px;
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        padding: 8px 0;
    }

    .dropdown-item {
        padding: 8px 16px;
        font-size: 0.875rem;
        color: #212529;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: rgba(180, 80, 50, 1);
    }

    .dropdown-item i {
        width: 20px;
        margin-right: 10px;
        text-align: center;
    }

    /* Modal improvements */
    .modal-content {
        border-radius: 12px;
        overflow: hidden;
    }

    .modal-body pre {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 12px;
        font-size: 0.85rem;
        overflow-x: auto;
        margin: 10px 0;
    }

    /* Import instructions */
    .import-instructions ol,
    .import-instructions ul {
        padding-left: 20px;
        margin-bottom: 15px;
    }

    .import-instructions li {
        margin-bottom: 5px;
        font-size: 0.9rem;
    }

    .import-instructions pre {
        font-size: 0.8rem;
        line-height: 1.4;
    }

    /* Filter row improvements */
    .filter-row th {
        padding: 8px !important;
        background-color: white !important;
    }

    .filter-input {
        font-size: 0.85rem;
        padding: 6px 10px;
        height: 34px;
    }

    /* Hover effects for table rows */
    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* Checkbox alignment */
    .table th:first-child,
    .table td:first-child {
        text-align: center;
        width: 50px;
    }

    /* Stats section improvements */
    .stats-section h3 {
        color: #2c3e50;
        font-size: 1.75rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .stats-section .p {
        color: #6c757d;
        font-size: 0.95rem;
        margin-bottom: 25px;
    }

    /* Button group spacing */
    .btn-group {
        gap: 12px;
        margin-bottom: 25px;
    }

    /* Export dropdown specific */
    #exportDropdown {
        position: relative;
    }

    #exportDropdown::after {
        margin-left: 8px;
    }

    /* Loading state for actions */
    .btn-loading {
        position: relative;
        color: transparent !important;
    }

    .btn-loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Form validation styling */
    .form-control:invalid {
        border-color: #dc3545;
    }

    .form-control:valid {
        border-color: #28a745;
    }

    /* Required field indicators */
    .required::after {
        content: " *";
        color: #dc3545;
    }

    /* Zebra striping for grouped rows */
    .table tbody tr:nth-child(4n+1),
    .table tbody tr:nth-child(4n+2) {
        background-color: #fafafa;
    }

    .table tbody tr:nth-child(4n+1):hover,
    .table tbody tr:nth-child(4n+2):hover {
        background-color: #f3f4f6 !important;
    }

    /* ===== ALIGNMENT FIXES ===== */

    /* Ensure consistent column alignment */
    .table th, .table td {
        vertical-align: middle !important;
    }

    /* Price type badges */
    .price-type-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        min-width: 85px;
        text-align: center;
    }

    .wholesale-type .price-type-badge {
        background-color: rgba(52, 152, 219, 0.1);
        color: #2980b9;
        border: 1px solid rgba(52, 152, 219, 0.3);
    }

    .retail-type .price-type-badge {
        background-color: rgba(46, 204, 113, 0.1);
        color: #27ae60;
        border: 1px solid rgba(46, 204, 113, 0.3);
    }

    /* Price value alignment */
    .price-value {
        text-align: right;
        font-weight: 600;
        font-family: 'Courier New', monospace;
        min-width: 70px;
    }

    /* Change value alignment */
    .change-value {
        text-align: center;
        font-weight: 500;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        min-width: 90px;
    }

    .change-positive {
        color: #27ae60 !important;
    }

    .change-negative {
        color: #e74c3c !important;
    }

    .change-neutral {
        color: #7f8c8d !important;
    }

    /* Status cell alignment */
    .status-cell {
        text-align: left;
        min-width: 110px;
    }

    /* Source cell alignment */
    .source-cell {
        text-align: left;
        min-width: 120px;
    }

    /* Action cell alignment */
    .action-cell {
        text-align: center;
        min-width: 80px;
    }

    .no-action {
        color: #bdc3c7;
        font-style: italic;
        font-size: 0.85rem;
    }

    /* Rowspan cell styling */
    td[rowspan] {
        background-color: #f8f9fa;
        border-right: 2px solid #e9ecef !important;
        font-weight: 500;
    }

    /* Even/odd row styling for better visual grouping */
    .even-row {
        background-color: #fafafa;
    }

    .odd-row {
        background-color: #ffffff;
    }

    .even-row:hover, .odd-row:hover {
        background-color: #f8f9fa !important;
    }

    /* Fix for empty groups - ensure N/A displays properly */
    .price-type-cell, .price-value, .change-value, .status-cell, .source-cell, .action-cell {
        white-space: nowrap;
    }

    /* Ensure checkbox column is properly aligned */
    .table th:first-child,
    .table td:first-child {
        text-align: center;
        width: 40px;
    }

    /* Fixed column widths for better alignment */
    .table th:nth-child(2) { width: 120px; } /* Market */
    .table th:nth-child(3) { width: 150px; } /* Commodity */
    .table th:nth-child(4) { width: 100px; } /* Date */
    .table th:nth-child(5) { width: 100px; } /* Type */
    .table th:nth-child(6) { width: 80px; } /* Price */
    .table th:nth-child(7) { width: 100px; } /* Day Change */
    .table th:nth-child(8) { width: 110px; } /* Month Change */
    .table th:nth-child(9) { width: 110px; } /* Status */
    .table th:nth-child(10) { width: 120px; } /* Source */
    .table th:nth-child(11) { width: 80px; } /* Actions */

    /* Responsive improvements */
    @media (max-width: 1200px) {
        .stats-container {
            flex-wrap: wrap;
        }
        
        .stats-container > div {
            min-width: calc(50% - 10px);
            max-width: calc(50% - 10px);
            margin-bottom: 10px;
        }
        
        .container, .stats-section {
            margin-left: 13%;
        }
        
        .btn-group {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 768px) {
        .container, .stats-section {
            margin-left: 15%;
            padding: 15px;
        }
        
        .stats-container > div {
            min-width: 100%;
            max-width: 100%;
        }
        
        .btn-group {
            flex-direction: column;
            gap: 8px;
        }
        
        .btn-group > * {
            width: 100%;
            justify-content: center;
        }
        
        .table-responsive {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow-x: auto;
        }
        
        .d-flex {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start !important;
        }
        
        .pagination {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .alert {
            margin: 15px;
        }
        
        .price-type-badge {
            padding: 2px 6px;
            font-size: 0.7rem;
            min-width: 70px;
        }
        
        .change-value {
            font-size: 0.8rem;
            min-width: 70px;
        }
        
        .table th:nth-child(2) { width: 80px; }
        .table th:nth-child(3) { width: 100px; }
        .table th:nth-child(4) { width: 70px; }
        .table th:nth-child(5) { width: 70px; }
        .table th:nth-child(6) { width: 60px; }
        .table th:nth-child(7) { width: 80px; }
        .table th:nth-child(8) { width: 90px; }
        .table th:nth-child(9) { width: 80px; }
        .table th:nth-child(10) { width: 80px; }
        .table th:nth-child(11) { width: 60px; }
    }

    /* Compact view for small screens */
    @media (max-width: 576px) {
        .table th,
        .table td {
            padding: 8px 6px;
            font-size: 0.8rem;
        }
        
        .btn-sm {
            padding: 3px 6px !important;
            font-size: 0.75rem !important;
        }
        
        .stats-number {
            font-size: 1.25rem;
        }
        
        .stats-title {
            font-size: 0.875rem;
        }
    }

    /* Print styles */
    @media print {
        .btn-group,
        .filter-row,
        .pagination,
        .stats-section .p {
            display: none !important;
        }
        
        .table {
            border: 1px solid #000 !important;
        }
        
        .table th {
            background-color: #f0f0f0 !important;
            color: #000 !important;
        }
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
                <?php if (count($_SESSION['selected_market_prices']) > 0): ?>
                    <span class="selected-count"><?= count($_SESSION['selected_market_prices']) ?></span>
                <?php endif; ?>
            </button>

            <button class="btn btn-clear-selections" onclick="clearAllSelections()">
                <i class="fas fa-times-circle" style="margin-right: 3px;"></i>
                Clear Selections
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download" style="margin-right: 3px;"></i>
                    Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">
                        <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export Selected to Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">
                        <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export Selected to PDF
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="exportAll('excel')">
                        <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export All to Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportAll('pdf')">
                        <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export All to PDF
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportAll('csv')">
                        <i class="fas fa-file-csv" style="margin-right: 8px;"></i>Export All to CSV
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
        // Group data by date, market, and commodity
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
                    <th class="sortable <?= getSortClass('market') ?>" onclick="sortTable('market')">
                        Market
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?= getSortClass('commodity') ?>" onclick="sortTable('commodity')">
                        Commodity
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?= getSortClass('date_posted') ?>" onclick="sortTable('date_posted')">
                        Date
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?= getSortClass('price_type') ?>" onclick="sortTable('price_type')">
                        Type
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?= getSortClass('Price') ?>" onclick="sortTable('Price')">
                        Price($)
                        <span class="sort-icon"></span>
                    </th>
                    <th>Day Change(%)</th>
                    <th>Month Change(%)</th>
                    <th class="sortable <?= getSortClass('status') ?>" onclick="sortTable('status')">
                        Status
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?= getSortClass('data_source') ?>" onclick="sortTable('data_source')">
                        Source
                        <span class="sort-icon"></span>
                    </th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th>
                        <input type="text" class="filter-input" id="filterMarket" placeholder="Filter Market"
                               value="<?= htmlspecialchars($filters['market']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterCommodity" placeholder="Filter Commodity"
                               value="<?= htmlspecialchars($filters['commodity']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterDate" placeholder="Filter Date"
                               value="<?= htmlspecialchars($filters['date']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterType" placeholder="Filter Type"
                               value="<?= htmlspecialchars($filters['type']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterPrice" placeholder="Filter Price"
                               value="<?= htmlspecialchars($filters['price']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th></th>
                    <th></th>
                    <th>
                        <input type="text" class="filter-input" id="filterStatus" placeholder="Filter Status"
                               value="<?= htmlspecialchars($filters['status']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" class="filter-input" id="filterSource" placeholder="Filter Source"
                               value="<?= htmlspecialchars($filters['source']) ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <?php if (!empty(array_filter($filters))): ?>
                            <a href="?" class="btn btn-sm btn-clear-filters">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </th>
                </tr>
            </thead>
            <tbody id="pricesTable">
                <?php if (empty($grouped_data)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                            No market prices found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $row_index = 0;
                    foreach ($grouped_data as $group_key => $prices_in_group): 
                        $row_index++;
                        $group_count = count($prices_in_group);
                        $group_price_ids = array_column($prices_in_group, 'id');
                        $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));
                        
                        // Separate wholesale and retail
                        $wholesale_price = null;
                        $retail_price = null;
                        $wholesale_id = null;
                        $retail_id = null;
                        $wholesale_data = null;
                        $retail_data = null;
                        
                        foreach($prices_in_group as $price):
                            if ($price['price_type'] === 'Wholesale') {
                                $wholesale_price = $price;
                                $wholesale_id = $price['id'];
                            } elseif ($price['price_type'] === 'Retail') {
                                $retail_price = $price;
                                $retail_id = $price['id'];
                            }
                        endforeach;
                        
                        // Always display both rows even if one is missing
                        $display_rows = [];
                        if ($wholesale_price) {
                            $display_rows[] = ['type' => 'Wholesale', 'data' => $wholesale_price];
                        }
                        if ($retail_price) {
                            $display_rows[] = ['type' => 'Retail', 'data' => $retail_price];
                        }
                        
                        // Calculate changes for both
                        $wholesale_day_change = $wholesale_price ? calculateDoDChange($wholesale_price['Price'], $wholesale_price['commodity'], $wholesale_price['market'], 'Wholesale', $wholesale_price['date_posted'], $con) : 'N/A';
                        $wholesale_month_change = $wholesale_price ? calculateMoMChange($wholesale_price['Price'], $wholesale_price['commodity'], $wholesale_price['market'], 'Wholesale', $wholesale_price['date_posted'], $con) : 'N/A';
                        $retail_day_change = $retail_price ? calculateDoDChange($retail_price['Price'], $retail_price['commodity'], $retail_price['market'], 'Retail', $retail_price['date_posted'], $con) : 'N/A';
                        $retail_month_change = $retail_price ? calculateMoMChange($retail_price['Price'], $retail_price['commodity'], $retail_price['market'], 'Retail', $retail_price['date_posted'], $con) : 'N/A';
                        
                        $first_price = reset($prices_in_group);
                        $rowspan_count = count($display_rows);
                    ?>
                    
                    <?php foreach($display_rows as $display_index => $row_data): 
                        $price_data = $row_data['data'];
                        $is_wholesale = $row_data['type'] === 'Wholesale';
                        $day_change = $is_wholesale ? $wholesale_day_change : $retail_day_change;
                        $month_change = $is_wholesale ? $wholesale_month_change : $retail_month_change;
                        $price_id = $is_wholesale ? $wholesale_id : $retail_id;
                    ?>
                    <tr class="<?= $row_index % 2 == 0 ? 'even-row' : 'odd-row' ?>">
                        <?php if ($display_index === 0): ?>
                            <td rowspan="<?= $rowspan_count ?>">
                                <input type="checkbox" 
                                       class="row-checkbox" 
                                       data-group-key="<?= $group_key ?>"
                                       data-price-ids="<?= $group_price_ids_json ?>"
                                       value="<?= $first_price['id'] ?>"
                                       <?= in_array($first_price['id'], $_SESSION['selected_market_prices']) ? 'checked' : '' ?>
                                       onchange="updateSelection(this, <?= $first_price['id'] ?>)">
                            </td>
                            <td rowspan="<?= $rowspan_count ?>"><?= htmlspecialchars($first_price['market']) ?></td>
                            <td rowspan="<?= $rowspan_count ?>"><?= htmlspecialchars($first_price['commodity_display']) ?></td>
                            <td rowspan="<?= $rowspan_count ?>"><?= date('Y-m-d', strtotime($first_price['date_posted'])) ?></td>
                        <?php endif; ?>
                        <td class="price-type-cell <?= $is_wholesale ? 'wholesale-type' : 'retail-type' ?>">
                            <span class="price-type-badge"><?= $row_data['type'] ?></span>
                        </td>
                        <td class="price-value"><?= $price_data ? htmlspecialchars($price_data['Price']) : 'N/A' ?></td>
                        <td class="change-value <?= getChangeClass($day_change) ?>"><?= $day_change ?></td>
                        <td class="change-value <?= getChangeClass($month_change) ?>"><?= $month_change ?></td>
                        <td class="status-cell"><?= $price_data ? getStatusDisplay($price_data['status']) : 'N/A' ?></td>
                        <td class="source-cell"><?= $price_data ? htmlspecialchars($price_data['data_source']) : 'N/A' ?></td>
                        <td class="action-cell">
                            <?php if ($price_id): ?>
                                <a href="../data/edit_marketprice.php?id=<?= $price_id ?>">
                                    <button class="btn btn-sm btn-warning">
                                        <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
                                    </button>
                                </a>
                            <?php else: ?>
                                <span class="no-action">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> items
                <?php if (count($_SESSION['selected_market_prices']) > 0): ?>
                    <span class="selected-count"><?= count($_SESSION['selected_market_prices']) ?> selected across all pages</span>
                <?php endif; ?>
                <?php if (!empty($sort_column)): ?>
                    <span class="text-muted ms-2">Sorted by: <?= ucfirst(str_replace('_', ' ', $sort_column)) ?> (<?= $sort_order ?>)</span>
                <?php endif; ?>
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
                        <a class="page-link" href="<?= $page <= 1 ? '#' : getPageUrl($page - 1, $limit, $sort_column, $sort_order) ?>">Prev</a>
                    </li>
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . getPageUrl(1, $limit, $sort_column, $sort_order) . '">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= getPageUrl($i, $limit, $sort_column, $sort_order) ?>"><?= $i ?></a>
                        </li>
                    <?php 
                    endfor; 
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . getPageUrl($total_pages, $limit, $sort_column, $sort_order) . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $total_pages ? '#' : getPageUrl($page + 1, $limit, $sort_column, $sort_order) ?>">Next</a>
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
    // Initialize select all checkbox based on current page selections
    updateSelectAllCheckbox();
    
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
        // New column, default to DESC for date_posted, ASC for others
        const defaultOrder = column === 'date_posted' || column === 'created_at' ? 'DESC' : 'ASC';
        url.searchParams.set('sort', column);
        url.searchParams.set('order', defaultOrder);
    }
    
    // Reset to page 1 when sorting
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function applyFilters() {
    const filters = {
        market: document.getElementById('filterMarket').value,
        commodity: document.getElementById('filterCommodity').value,
        date: document.getElementById('filterDate').value,
        type: document.getElementById('filterType').value,
        price: document.getElementById('filterPrice').value,
        status: document.getElementById('filterStatus').value,
        source: document.getElementById('filterSource').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.market) url.searchParams.set('filter_market', filters.market);
    else url.searchParams.delete('filter_market');
    
    if (filters.commodity) url.searchParams.set('filter_commodity', filters.commodity);
    else url.searchParams.delete('filter_commodity');
    
    if (filters.date) url.searchParams.set('filter_date', filters.date);
    else url.searchParams.delete('filter_date');
    
    if (filters.type) url.searchParams.set('filter_type', filters.type);
    else url.searchParams.delete('filter_type');
    
    if (filters.price) url.searchParams.set('filter_price', filters.price);
    else url.searchParams.delete('filter_price');
    
    if (filters.status) url.searchParams.set('filter_status', filters.status);
    else url.searchParams.delete('filter_status');
    
    if (filters.source) url.searchParams.set('filter_source', filters.source);
    else url.searchParams.delete('filter_source');
    
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
            const id = checkbox.value;
            if (checkbox.onchange) {
                checkbox.onchange();
            } else if (id) {
                updateSelection(checkbox, id);
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

/**
 * Get all selected price IDs from session
 */
function getSelectedPriceIds() {
    // This will be handled by server-side session
    return <?= json_encode($_SESSION['selected_market_prices']) ?>;
}

function deleteSelected() {
    const selectedCount = <?= count($_SESSION['selected_market_prices']) ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one market price to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selectedCount + ' selected market price(s) across all pages?')) {
        // Pass all selected IDs from session
        fetch('../data/delete_market_prices.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: <?= json_encode($_SESSION['selected_market_prices']) ?> })
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
    const selectedCount = <?= count($_SESSION['selected_market_prices']) ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one market price to export.');
        return;
    }
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../data/export_market_prices.php';
    
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
    sortInput.value = '<?= $sort_column ?>';
    form.appendChild(sortInput);
    
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = 'order';
    orderInput.value = '<?= $sort_order ?>';
    form.appendChild(orderInput);
    
    // Add selected IDs from session
    <?php foreach ($_SESSION['selected_market_prices'] as $id): ?>
        const idInput<?= $id ?> = document.createElement('input');
        idInput<?= $id ?>.type = 'hidden';
        idInput<?= $id ?>.name = 'selected_ids[]';
        idInput<?= $id ?>.value = '<?= $id ?>';
        form.appendChild(idInput<?= $id ?>);
    <?php endforeach; ?>
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function exportAll(format) {
    if (confirm('Export ALL market prices? This may take a moment for large datasets.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../data/export_market_prices.php';
        
        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'export_format';
        formatInput.value = format;
        form.appendChild(formatInput);
        
        const exportAllInput = document.createElement('input');
        exportAllInput.type = 'hidden';
        exportAllInput.name = 'export_all';
        exportAllInput.value = 'true';
        form.appendChild(exportAllInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

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
</script>
<script>
// Make sure the table is responsive on smaller screens
document.addEventListener('DOMContentLoaded', function() {
    // Add responsive wrapper to table
    const table = document.querySelector('.table');
    if (table && !table.parentElement.classList.contains('table-responsive')) {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.parentElement.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    }
    
    // Adjust stats container on window resize
    function adjustStatsContainer() {
        const statsContainer = document.querySelector('.stats-container');
        if (statsContainer && window.innerWidth < 1200) {
            statsContainer.style.flexWrap = 'wrap';
        }
    }
    
    window.addEventListener('resize', adjustStatsContainer);
    adjustStatsContainer();
});
</script>

<?php include '../admin/includes/footer.php'; ?>