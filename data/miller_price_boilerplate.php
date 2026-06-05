<?php
// miller_prices.php
session_start();

// ============================================================
// EXPORT CSV — must run BEFORE admin_header.php is included
// ============================================================
if (isset($_GET['export_csv'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="miller_prices_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $search_country = $_GET['search_country'] ?? '';
    $search_town = $_GET['search_town'] ?? '';
    $search_commodity = $_GET['search_commodity'] ?? '';
    $filter_status = $_GET['filter_status'] ?? '';

    $where_export = "WHERE 1=1";
    if (!empty($search_country)) {
        $where_export .= " AND mp.country LIKE '%" . $con->real_escape_string($search_country) . "%'";
    }
    if (!empty($search_town)) {
        $where_export .= " AND mp.town LIKE '%" . $con->real_escape_string($search_town) . "%'";
    }
    if (!empty($search_commodity)) {
        $where_export .= " AND (c.commodity_name LIKE '%" . $con->real_escape_string($search_commodity) . "%' OR c.variety LIKE '%" . $con->real_escape_string($search_commodity) . "%')";
    }
    if (!empty($filter_status)) {
        $where_export .= " AND mp.status = '" . $con->real_escape_string($filter_status) . "'";
    }

    $exp_query = "SELECT 
        mp.id, mp.country, mp.town, 
        CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
        mp.price, mp.price_usd, mp.day_change, mp.month_change,
        DATE(mp.date_posted) as price_date, mp.status, ds.data_source_name as data_source
        FROM miller_prices mp
        LEFT JOIN commodities c ON mp.commodity_id = c.id
        LEFT JOIN data_sources ds ON mp.data_source_id = ds.id
        $where_export
        ORDER BY mp.date_posted DESC";
    
    $exp_result = $con->query($exp_query);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Country', 'Town', 'Commodity', 'Price (Local)', 'Price (USD)', 'Day Change %', 'Month Change %', 'Date', 'Status', 'Data Source']);

    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['country'], $row['town'], $row['commodity_display'],
            number_format($row['price'], 2, '.', ''), number_format($row['price_usd'], 2, '.', ''),
            $row['day_change'] !== null ? $row['day_change'] . '%' : 'N/A',
            $row['month_change'] !== null ? $row['month_change'] . '%' : 'N/A',
            $row['price_date'], $row['status'], $row['data_source']
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// POST: Add Miller Price via modal
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_miller_price'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $country = trim($_POST['country']);
    $town = trim($_POST['town']);
    $commodity_id = (int)$_POST['commodity_id'];
    $price = (float)$_POST['price'];
    $data_source_id = (int)$_POST['data_source_id'];
    $date_posted = trim($_POST['date_posted']);
    
    // Get commodity name and variety
    $commodity_name = "";
    $variety = "";
    $stmt = $con->prepare("SELECT commodity_name, variety FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $commodity_name = $row['commodity_name'];
        $variety = $row['variety'];
    }
    $stmt->close();
    
    // Get data source name
    $data_source_name = "";
    $stmt = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
    $stmt->bind_param("i", $data_source_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $data_source_name = $row['data_source_name'];
    }
    $stmt->close();
    
    // Convert price to USD (using approximate rates)
    $rates = ['Kenya' => 150, 'Uganda' => 3700, 'Tanzania' => 2300, 'Rwanda' => 1200, 'Burundi' => 2000];
    $rate = $rates[$country] ?? 1;
    $price_usd = round($price / $rate, 2);
    
    // Extract date parts
    $date_obj = new DateTime($date_posted);
    $day = $date_obj->format('d');
    $month = $date_obj->format('m');
    $year = $date_obj->format('Y');
    $status = 'pending';
    
    $stmt = $con->prepare("INSERT INTO miller_prices (country, town, commodity_id, commodity_name, price, price_usd, data_source_id, data_source_name, date_posted, day, month, year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisddissiiis", $country, $town, $commodity_id, $commodity_name, $price, $price_usd, $data_source_id, $data_source_name, $date_posted, $day, $month, $year, $status);
    
    if ($stmt->execute()) {
        $_SESSION['import_message'] = "Miller price added successfully!";
        $_SESSION['import_status'] = "success";
    } else {
        $_SESSION['import_message'] = "Error adding miller price: " . $stmt->error;
        $_SESSION['import_status'] = "danger";
    }
    $stmt->close();
    header("Location: miller_prices.php");
    exit;
}

// ============================================================
// POST: Edit Miller Price via modal
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_miller_price'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $id = (int)$_POST['price_id'];
    $country = trim($_POST['country']);
    $town = trim($_POST['town']);
    $commodity_id = (int)$_POST['commodity_id'];
    $price = (float)$_POST['price'];
    $data_source_id = (int)$_POST['data_source_id'];
    $date_posted = trim($_POST['date_posted']);
    $status = trim($_POST['status']);
    
    // Get commodity name and variety
    $commodity_name = "";
    $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $commodity_name = $row['commodity_name'];
    }
    $stmt->close();
    
    // Get data source name
    $data_source_name = "";
    $stmt = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
    $stmt->bind_param("i", $data_source_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $data_source_name = $row['data_source_name'];
    }
    $stmt->close();
    
    // Convert price to USD
    $rates = ['Kenya' => 150, 'Uganda' => 3700, 'Tanzania' => 2300, 'Rwanda' => 1200, 'Burundi' => 2000];
    $rate = $rates[$country] ?? 1;
    $price_usd = round($price / $rate, 2);
    
    // Extract date parts
    $date_obj = new DateTime($date_posted);
    $day = $date_obj->format('d');
    $month = $date_obj->format('m');
    $year = $date_obj->format('Y');
    
    $stmt = $con->prepare("UPDATE miller_prices SET country=?, town=?, commodity_id=?, commodity_name=?, price=?, price_usd=?, data_source_id=?, data_source_name=?, date_posted=?, day=?, month=?, year=?, status=? WHERE id=?");
    $stmt->bind_param("ssisddissiiissi", $country, $town, $commodity_id, $commodity_name, $price, $price_usd, $data_source_id, $data_source_name, $date_posted, $day, $month, $year, $status, $id);
    
    if ($stmt->execute()) {
        $_SESSION['import_message'] = "Miller price updated successfully!";
        $_SESSION['import_status'] = "success";
    } else {
        $_SESSION['import_message'] = "Error updating miller price: " . $stmt->error;
        $_SESSION['import_status'] = "danger";
    }
    $stmt->close();
    header("Location: miller_prices.php");
    exit;
}

// ============================================================
// POST: Delete Miller Prices
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM miller_prices WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $_SESSION['import_message'] = "Successfully deleted $deleted miller price(s).";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error deleting: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: miller_prices.php");
    exit;
}

// ============================================================
// POST: Bulk Status Update
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_status_update']) && !empty($_POST['selected_ids']) && isset($_POST['new_status'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $new_status = $_POST['new_status'];
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("UPDATE miller_prices SET status = ? WHERE id IN ($placeholders)");
    if ($stmt) {
        $types = 's' . str_repeat('i', count($selected_ids));
        $stmt->bind_param($types, $new_status, ...$selected_ids);
        if ($stmt->execute()) {
            $updated = $stmt->affected_rows;
            $_SESSION['import_message'] = "Successfully updated $updated miller price(s) to '$new_status'.";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error updating status: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: miller_prices.php");
    exit;
}

// ============================================================
// CSV IMPORT
// ============================================================
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $overwrite = isset($_POST['overwrite_existing']);
    fgetcsv($handle); // skip header
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $con->begin_transaction();
    
    try {
        $rowNumber = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $rowNumber++;
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) continue;
            
            if (empty(trim($data[0]))) { $errors[] = "Row $rowNumber: Country is required"; $errorCount++; continue; }
            if (empty(trim($data[1]))) { $errors[] = "Row $rowNumber: Town is required"; $errorCount++; continue; }
            if (empty(trim($data[2]))) { $errors[] = "Row $rowNumber: Commodity ID is required"; $errorCount++; continue; }
            if (empty(trim($data[3]))) { $errors[] = "Row $rowNumber: Price is required"; $errorCount++; continue; }
            if (empty(trim($data[4]))) { $errors[] = "Row $rowNumber: Date is required"; $errorCount++; continue; }
            
            $country = trim($data[0]);
            $town = trim($data[1]);
            $commodity_id = (int)trim($data[2]);
            $price = (float)trim($data[3]);
            $date_string = trim($data[4]);
            $data_source_id = isset($data[5]) ? (int)trim($data[5]) : 1;
            $status = isset($data[6]) ? trim($data[6]) : 'pending';
            
            // Parse date
            $date_posted = null;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_string, $matches)) {
                $date_posted = "$matches[1]-$matches[2]-$matches[3] 00:00:00";
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/', $date_string, $matches)) {
                $date_posted = "$matches[1]-$matches[2]-$matches[3] $matches[4]:$matches[5]:$matches[6]";
            } else {
                $timestamp = strtotime($date_string);
                if ($timestamp !== false && $timestamp > 0) {
                    $date_posted = date('Y-m-d H:i:s', $timestamp);
                }
            }
            
            if ($date_posted === null) {
                $errors[] = "Row $rowNumber: Invalid date format '$date_string'";
                $errorCount++;
                continue;
            }
            
            // Get commodity name
            $commodity_name = "";
            $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
            $stmt->bind_param("i", $commodity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $commodity_name = $row['commodity_name'];
            } else {
                $errors[] = "Row $rowNumber: Commodity ID $commodity_id not found";
                $errorCount++;
                continue;
            }
            $stmt->close();
            
            // Get data source name
            $data_source_name = "";
            $stmt = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
            $stmt->bind_param("i", $data_source_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $data_source_name = $row['data_source_name'];
            } else {
                $errors[] = "Row $rowNumber: Data Source ID $data_source_id not found";
                $errorCount++;
                continue;
            }
            $stmt->close();
            
            // Convert to USD
            $rates = ['Kenya' => 150, 'Uganda' => 3700, 'Tanzania' => 2300, 'Rwanda' => 1200, 'Burundi' => 2000];
            $rate = $rates[$country] ?? 1;
            $price_usd = round($price / $rate, 2);
            
            // Extract date parts
            $date_obj = new DateTime($date_posted);
            $day = $date_obj->format('d');
            $month = $date_obj->format('m');
            $year = $date_obj->format('Y');
            
            // Check if record exists
            $check_stmt = $con->prepare("SELECT id FROM miller_prices WHERE town = ? AND commodity_id = ? AND DATE(date_posted) = DATE(?)");
            $check_stmt->bind_param("sis", $town, $commodity_id, $date_posted);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    $update_stmt = $con->prepare("UPDATE miller_prices SET country=?, price=?, price_usd=?, data_source_id=?, data_source_name=?, status=?, day=?, month=?, year=? WHERE town=? AND commodity_id=? AND DATE(date_posted)=DATE(?)");
                    $update_stmt->bind_param("sddissiiissi", $country, $price, $price_usd, $data_source_id, $data_source_name, $status, $day, $month, $year, $town, $commodity_id, $date_posted);
                    if ($update_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                        $errorCount++;
                    }
                    $update_stmt->close();
                } else {
                    $errors[] = "Row $rowNumber: Record already exists (use overwrite to update)";
                    $errorCount++;
                }
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert new record
            $insert_stmt = $con->prepare("INSERT INTO miller_prices (country, town, commodity_id, commodity_name, price, price_usd, data_source_id, data_source_name, date_posted, day, month, year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssisddissiiis", $country, $town, $commodity_id, $commodity_name, $price, $price_usd, $data_source_id, $data_source_name, $date_posted, $day, $month, $year, $status);
            
            if ($insert_stmt->execute()) {
                $successCount++;
            } else {
                $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                $errorCount++;
            }
            $insert_stmt->close();
        }
        
        if ($errorCount === 0) {
            $con->commit();
            $_SESSION['import_message'] = "Successfully imported $successCount miller prices.";
            $_SESSION['import_status'] = "success";
        } else {
            $con->rollback();
            $_SESSION['import_message'] = "Import failed with $errorCount errors. " . implode('<br>', array_slice($errors, 0, 10));
            $_SESSION['import_status'] = "danger";
        }
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['import_message'] = "Import failed: " . $e->getMessage();
        $_SESSION['import_status'] = "danger";
    }
    fclose($handle);
    header("Location: miller_prices.php");
    exit;
}

// ============================================================
// CSV TEMPLATE DOWNLOAD
// ============================================================
if (isset($_GET['download_template'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="miller_prices_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Country', 'Town', 'Commodity ID', 'Price', 'Date (YYYY-MM-DD)', 'Data Source ID', 'Status']);
    fputcsv($out, ['Kenya', 'Nairobi', '1', '4500.00', '2025-01-15', '1', 'pending']);
    fputcsv($out, ['Uganda', 'Kampala', '2', '120000.00', '2025-01-15', '1', 'pending']);
    fclose($out);
    exit;
}

// ============================================================
// API HANDLER — fetch single miller price for edit modal
// ============================================================
if (isset($_GET['get_miller_price']) && is_numeric($_GET['get_miller_price'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_miller_price'];
    $result = $con->query("SELECT mp.*, c.commodity_name as commodity_name FROM miller_prices mp LEFT JOIN commodities c ON mp.commodity_id = c.id WHERE mp.id = $get_id");
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}

// ============================================================
// CHECK ADMIN LOGIN
// ============================================================
require_once '../admin/includes/admin_header.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

// ============================================================
// INCLUDE CONFIG
// ============================================================
if (file_exists('includes/config.php')) include 'includes/config.php';
elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

// ============================================================
// STATISTICS
// ============================================================
$total_prices = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices")->fetch_assoc()['t'] ?? 0);
$pending_count = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices WHERE status = 'pending'")->fetch_assoc()['t'] ?? 0);
$approved_count = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices WHERE status = 'approved'")->fetch_assoc()['t'] ?? 0);
$published_count = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices WHERE status = 'published'")->fetch_assoc()['t'] ?? 0);
$unpublished_count = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices WHERE status = 'unpublished'")->fetch_assoc()['t'] ?? 0);

// Get distinct towns for filter
$towns_result = $con->query("SELECT DISTINCT town FROM miller_prices ORDER BY town");
$distinct_towns = [];
while ($row = $towns_result->fetch_assoc()) {
    $distinct_towns[] = $row['town'];
}

// Get distinct countries for filter
$countries_result = $con->query("SELECT DISTINCT country FROM miller_prices ORDER BY country");
$distinct_countries = [];
while ($row = $countries_result->fetch_assoc()) {
    $distinct_countries[] = $row['country'];
}

// ============================================================
// PAGINATION + SORTING + FILTERING
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts = ['id', 'country', 'town', 'commodity', 'price_usd', 'day_change', 'month_change', 'date_posted', 'status', 'data_source'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'date_posted';

$search_country = trim($_GET['search_country'] ?? '');
$search_town = trim($_GET['search_town'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search_country !== '') {
    $where .= " AND mp.country LIKE ?";
    $params[] = '%' . $search_country . '%';
    $types .= "s";
}
if ($search_town !== '') {
    $where .= " AND mp.town LIKE ?";
    $params[] = '%' . $search_town . '%';
    $types .= "s";
}
if ($search_commodity !== '') {
    $where .= " AND (c.commodity_name LIKE ? OR c.variety LIKE ?)";
    $params[] = '%' . $search_commodity . '%';
    $params[] = '%' . $search_commodity . '%';
    $types .= "ss";
}
if ($filter_status !== '') {
    $where .= " AND mp.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Count total records
$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM miller_prices mp LEFT JOIN commodities c ON mp.commodity_id = c.id LEFT JOIN data_sources ds ON mp.data_source_id = ds.id $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($page - 1) * $limit;

// Map sort column to database column
$sort_map = [
    'id' => 'mp.id',
    'country' => 'mp.country',
    'town' => 'mp.town',
    'commodity' => 'c.commodity_name',
    'price_usd' => 'mp.price_usd',
    'day_change' => 'mp.day_change',
    'month_change' => 'mp.month_change',
    'date_posted' => 'mp.date_posted',
    'status' => 'mp.status',
    'data_source' => 'ds.data_source_name'
];
$order_by = $sort_map[$sort_column] ?? 'mp.date_posted';
$dir = $sort_direction === 'ASC' ? 'ASC' : 'DESC';

// Fetch data
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$query = "SELECT 
    mp.id, mp.country, mp.town, mp.commodity_id, mp.commodity_name,
    mp.price, mp.price_usd, mp.day_change, mp.month_change,
    mp.date_posted, mp.status, ds.data_source_name as data_source,
    CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display
    FROM miller_prices mp
    LEFT JOIN commodities c ON mp.commodity_id = c.id
    LEFT JOIN data_sources ds ON mp.data_source_id = ds.id
    $where 
    ORDER BY $order_by $dir
    LIMIT ? OFFSET ?";

$data_stmt = $con->prepare($query);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$miller_prices = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;

// Get commodities and data sources for modals
$commodities = [];
$comm_result = $con->query("SELECT id, commodity_name, variety, CONCAT(commodity_name, IF(variety IS NOT NULL AND variety != '', CONCAT(' (', variety, ')'), '')) as display_name FROM commodities ORDER BY commodity_name");
while ($row = $comm_result->fetch_assoc()) {
    $commodities[] = $row;
}

$data_sources = [];
$ds_result = $con->query("SELECT id, data_source_name FROM data_sources ORDER BY data_source_name");
while ($row = $ds_result->fetch_assoc()) {
    $data_sources[] = $row;
}

function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="status-badge status-pending">Pending</span>';
        case 'approved': return '<span class="status-badge status-approved">Approved</span>';
        case 'published': return '<span class="status-badge status-published">Published</span>';
        case 'unpublished': return '<span class="status-badge status-unpublished">Unpublished</span>';
        default: return '<span class="status-badge">Unknown</span>';
    }
}

function getChangeClass($change) {
    if ($change === null) return 'flat';
    return $change >= 0 ? 'up' : 'down';
}

function getChangeIcon($change) {
    if ($change === null) return '–';
    return $change >= 0 ? '▲' : '▼';
}
?>

<style>
.auth-bg-gradient{background:radial-gradient(circle at top left,rgba(0,69,13,.03),transparent),radial-gradient(circle at bottom right,rgba(128,0,0,.03),transparent)}
.header-accent-gradient{background:linear-gradient(90deg,#00450d 0%,#800000 50%,#00450d 100%)}
.table-row-hover:hover{background-color:#fefaf5;transition:all .2s ease}
.stat-card{transition:all .2s ease;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.search-input:focus{border-color:#800000;outline:none}
.action-btn{padding:.2rem .4rem;border-radius:.375rem;font-size:.7rem;font-weight:500;transition:all .2s;cursor:pointer;border:none;display:inline-flex;align-items:center}
.pagination-btn{min-width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:.375rem;font-size:.75rem;transition:all .2s ease;cursor:pointer;border:1px solid #e5e7eb;background:white;color:#374151}
.pagination-btn:hover:not(:disabled):not(.active-page){background-color:#fef3e7;border-color:#800000;color:#800000}
.pagination-btn.active-page{background-color:#800000;border-color:#800000;color:white;font-weight:600}
.pagination-btn:disabled{opacity:.35;cursor:not-allowed}
.page-size-select{font-size:.75rem;padding:.25rem .5rem;border-radius:.375rem;border:1px solid #e5e7eb;background:white;cursor:pointer}
.sortable{cursor:pointer;user-select:none}
.sortable:hover{color:#800000}
.sort-icon{font-size:.7rem;margin-left:.2rem;vertical-align:middle}
.modal-gradient-header{background:linear-gradient(135deg,#800000 0%,#00450d 100%)}
.material-symbols-outlined{font-family:'Material Symbols Outlined'!important;font-style:normal;font-weight:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';font-feature-settings:'liga';-webkit-font-smoothing:antialiased}

.status-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:600}
.status-badge::before{content:'';width:7px;height:7px;border-radius:50%;display:inline-block}
.status-pending{background:#fef3c7;color:#92400e}
.status-pending::before{background:#d97706}
.status-approved{background:#e0f2fe;color:#075985}
.status-approved::before{background:#0891b2}
.status-published{background:#dcfce7;color:#166534}
.status-published::before{background:#16a34a}
.status-unpublished{background:#fee2e2;color:#991b1b}
.status-unpublished::before{background:#dc2626}

.change-indicator{display:inline-flex;align-items:center;gap:2px;font-size:.7rem;font-weight:600;padding:2px 6px;border-radius:4px}
.change-up{background:#dcfce7;color:#16a34a}
.change-down{background:#fee2e2;color:#dc2626}
.change-flat{background:#f3f4f6;color:#6b7280}

.price-value{font-family:monospace;font-weight:700;font-size:.85rem}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Miller Prices Management</h1>
                <p class="text-gray-600 text-sm mt-1">Manage miller price data across towns and commodities</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_csv=1&search_country=<?= urlencode($search_country) ?>&search_town=<?= urlencode($search_town) ?>&search_commodity=<?= urlencode($search_commodity) ?>&filter_status=<?= urlencode($filter_status) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export CSV
                </a>
                <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">upload_file</span>Import CSV
                </button>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">add_circle</span>Add Price
                </button>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['import_message'])): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $_SESSION['import_status'] == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $_SESSION['import_status'] == 'success' ? 'check_circle' : 'error' ?></span>
        <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['import_message']) ?></span>
    </div>
    <?php 
        unset($_SESSION['import_message']); 
        unset($_SESSION['import_status']);
    endif; 
    ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Prices</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_prices) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">attach_money</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Pending</p><p class="text-xl font-bold text-yellow-600"><?= number_format($pending_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-yellow-400/60">pending</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Approved</p><p class="text-xl font-bold text-blue-600"><?= number_format($approved_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-blue-400/50">check_circle</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Published</p><p class="text-xl font-bold text-green-600"><?= number_format($published_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">public</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Unpublished</p><p class="text-xl font-bold text-red-600"><?= number_format($unpublished_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-red-400/50">visibility_off</span>
            </div>
        </div>
    </div>

    <!-- Search & bulk actions -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">public</span>
                    <select id="searchCountry" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Countries</option>
                        <?php foreach ($distinct_countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $search_country == $country ? 'selected' : '' ?>><?= htmlspecialchars($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">location_city</span>
                    <select id="searchTown" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Towns</option>
                        <?php foreach ($distinct_towns as $town): ?>
                            <option value="<?= htmlspecialchars($town) ?>" <?= $search_town == $town ? 'selected' : '' ?>><?= htmlspecialchars($town) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">eco</span>
                    <input type="text" id="searchCommodity" placeholder="Search commodity..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_commodity) ?>">
                </div>
            </div>
            <div class="w-32">
                <select id="filterStatus" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="published" <?= $filter_status == 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="unpublished" <?= $filter_status == 'unpublished' ? 'selected' : '' ?>>Unpublished</option>
                </select>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">filter_list</span>Filter
                </button>
                <button id="clearSelectionsBtn" class="px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition-all inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">clear</span>Clear Selected
                </button>
                <button id="bulkDeleteBtn" disabled class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">delete</span>Delete (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
        
        <!-- Bulk Status Update Row -->
        <div class="flex flex-wrap gap-3 items-center mt-3 pt-3 border-t border-gray-100">
            <span class="text-xs text-gray-500">Bulk Actions:</span>
            <select id="bulkStatusSelect" class="px-2 py-1 text-sm border border-gray-200 rounded-lg">
                <option value="">Change Status...</option>
                <option value="approved">Approve Selected</option>
                <option value="published">Publish Selected</option>
                <option value="unpublished">Unpublish Selected</option>
                <option value="pending">Mark as Pending</option>
            </select>
            <button id="bulkStatusBtn" disabled class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-base">update</span>Apply
            </button>
        </div>
    </div>

    <!-- Main Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-8 px-3 py-2 text-left">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300">
                        </th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">ID<?php if($sort_column=='id') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="country">Country<?php if($sort_column=='country') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="town">Town<?php if($sort_column=='town') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="commodity">Commodity<?php if($sort_column=='commodity') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="price_usd">Price (USD)<?php if($sort_column=='price_usd') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Day Δ</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Month Δ</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_posted">Date<?php if($sort_column=='date_posted') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="status">Status<?php if($sort_column=='status') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="data_source">Data Source<?php if($sort_column=='data_source') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-20">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($miller_prices)): ?>
                    <tr>
                        <td colspan="12" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">agriculture</span>
                            <p class="text-sm mt-1">No miller prices found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($miller_prices as $price): 
                        $day_change = $price['day_change'] !== null ? round($price['day_change'], 2) . '%' : 'N/A';
                        $month_change = $price['month_change'] !== null ? round($price['month_change'], 2) . '%' : 'N/A';
                        $day_class = getChangeClass($price['day_change']);
                        $month_class = getChangeClass($price['month_change']);
                        $day_icon = getChangeIcon($price['day_change']);
                        $month_icon = getChangeIcon($price['month_change']);
                    ?>
                    <tr class="table-row-hover" data-id="<?= $price['id'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="<?= $price['id'] ?>" onchange="onCheckboxChange()">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= $price['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($price['country']) ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($price['town']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($price['commodity_display']) ?></td>
                        <td class="px-3 py-2 text-xs font-mono font-semibold text-gray-700">$<?= number_format($price['price_usd'], 2) ?></td>
                        <td class="px-3 py-2"><span class="change-indicator change-<?= $day_class ?>"><?= $day_icon ?> <?= $day_change ?></span></td>
                        <td class="px-3 py-2"><span class="change-indicator change-<?= $month_class ?>"><?= $month_icon ?> <?= $month_change ?></span></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= date('M d, Y', strtotime($price['date_posted'])) ?></td>
                        <td class="px-3 py-2"><?= getStatusBadge($price['status']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($price['data_source']) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editMillerPrice(<?= $price['id'] ?>)" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $price['id'] ?>, '<?= htmlspecialchars(addslashes($price['town'])) ?> - <?= htmlspecialchars(addslashes($price['commodity_display'])) ?>')" class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="border-t border-gray-200 px-4 py-3 bg-white">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="text-xs text-gray-500">
                    <?php if ($filtered_records === 0): ?>
                        No prices found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> prices
                        <?php if ($search_country || $search_town || $search_commodity || $filter_status): ?>
                            <span class="ml-1 text-maroon">(filtered)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500" for="rowsPerPage">Rows:</label>
                        <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                            <?php foreach ([10,20,50,100] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $limit==$opt?'selected':'' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center gap-1">
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page<=1?'disabled':'' ?>><span class="material-symbols-outlined text-sm">first_page</span></button>
                        <button class="pagination-btn" onclick="goToPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>><span class="material-symbols-outlined text-sm">chevron_left</span></button>

                        <?php
                        $win = 2;
                        $sp = max(1, $page - $win);
                        $ep = min($total_pages, $page + $win);
                        if ($sp === 1) $ep = min($total_pages, 1 + $win * 2);
                        if ($ep === $total_pages) $sp = max(1, $total_pages - $win * 2);
                        if ($sp > 1): ?>
                            <button class="pagination-btn" onclick="goToPage(1)">1</button>
                            <?php if ($sp > 2): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $sp; $i <= $ep; $i++): ?>
                            <button class="pagination-btn <?= $i===$page ? 'active-page' : '' ?>" <?= $i===$page ? '' : "onclick=\"goToPage($i)\"" ?>><?= $i ?></button>
                        <?php endfor; ?>

                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                        <?php endif; ?>

                        <button class="pagination-btn" onclick="goToPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="material-symbols-outlined text-sm">chevron_right</span></button>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="material-symbols-outlined text-sm">last_page</span></button>
                    </nav>
                    <?php endif; ?>
                </div>

                <a href="../base/landing_page.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                    <span class="material-symbols-outlined text-base">arrow_back</span>Back
                </a>
            </div>
        </div>
    </div>

</div>
</div>

<!-- ============================================================
     ADD / EDIT MODAL
============================================================ -->
<div id="millerPriceModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add Miller Price</h3>
                <button onclick="closeModal('millerPriceModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="millerPriceForm">
                    <input type="hidden" name="price_id" id="priceId">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Country <span class="text-red-500">*</span></label>
                            <select name="country" id="modalCountry" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Country</option>
                                <option value="Kenya">Kenya</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Burundi">Burundi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Town <span class="text-red-500">*</span></label>
                            <input type="text" name="town" id="modalTown" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                placeholder="e.g., Nairobi, Kampala">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Commodity <span class="text-red-500">*</span></label>
                            <select name="commodity_id" id="modalCommodity" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Commodity</option>
                                <?php foreach ($commodities as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Price (Local Currency) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="price" id="modalPrice" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                placeholder="e.g., 4500.00">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Data Source <span class="text-red-500">*</span></label>
                            <select name="data_source_id" id="modalDataSource" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Data Source</option>
                                <?php foreach ($data_sources as $ds): ?>
                                    <option value="<?= $ds['id'] ?>"><?= htmlspecialchars($ds['data_source_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="date_posted" id="modalDate" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div id="editStatusDiv" class="mb-4 hidden">
                        <label class="block text-xs text-gray-600 mb-1">Status</label>
                        <select name="status" id="modalStatus"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="published">Published</option>
                            <option value="unpublished">Unpublished</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('millerPriceModal')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_miller_price" id="submitBtn"
                            class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Price</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     IMPORT CSV MODAL
============================================================ -->
<div id="importModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">upload_file</span>
                    Bulk Import Miller Prices (CSV)
                </h3>
                <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-r-lg p-4 mb-5 text-sm">
                    <p class="font-semibold text-blue-800 mb-2">CSV Column Order</p>
                    <ol class="list-decimal list-inside text-blue-700 space-y-0.5 text-xs">
                        <li><strong>Country</strong> — required (e.g., Kenya, Uganda, Tanzania, Rwanda, Burundi)</li>
                        <li><strong>Town</strong> — required (e.g., Nairobi, Kampala)</li>
                        <li><strong>Commodity ID</strong> — required (integer ID from commodities table)</li>
                        <li><strong>Price</strong> — required, numeric (in local currency)</li>
                        <li><strong>Date</strong> — required (e.g., 2025-01-15 or 2025-01-15 14:30:00)</li>
                        <li><strong>Data Source ID</strong> — optional (integer ID from data_sources table, default 1)</li>
                        <li><strong>Status</strong> — optional (pending/approved/published/unpublished, default pending)</li>
                    </ol>
                    <a href="?download_template=1" class="inline-flex items-center gap-1 mt-3 text-xs text-blue-700 font-medium hover:underline">
                        <span class="material-symbols-outlined text-sm">download</span>Download example template CSV
                    </a>
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1 font-medium">Select CSV File <span class="text-red-500">*</span></label>
                        <input type="file" name="csv_file" id="importCsvFile" accept=".csv" required
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                        <p id="importFileInfo" class="mt-1 text-xs text-gray-400 hidden"></p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none mb-5">
                        <input type="checkbox" name="overwrite_existing" class="rounded border-gray-300">
                        <span>Overwrite existing prices with matching Town + Commodity + Date</span>
                    </label>

                    <div id="importPreviewInfo" class="hidden mb-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-600">
                        <span class="material-symbols-outlined text-sm align-middle text-blue-500">info</span>
                        File selected — click <strong>Import</strong> to proceed.
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal('importModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="import_csv" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">upload</span>Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     DELETE CONFIRM MODAL
============================================================ -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg w-full max-w-md shadow-xl">
        <div class="p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-red-500">warning</span>
                <h3 class="text-base font-semibold text-gray-800">Confirm Deletion</h3>
            </div>
            <p id="deleteModalText" class="text-sm text-gray-500 mb-3">Are you sure?</p>
            <div class="bg-red-50 border-l-4 border-red-500 p-2 mb-3 text-xs text-red-700">
                <span class="material-symbols-outlined text-xs align-middle">info</span> This action cannot be undone.
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="delete_selected" value="1">
                <div id="deleteIdsContainer"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     BULK STATUS UPDATE FORM
============================================================ -->
<form id="bulkStatusForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="bulk_status_update" value="1">
    <input type="hidden" name="new_status" id="bulkNewStatus">
    <div id="bulkStatusIdsContainer"></div>
</form>

<script>
// PHP → JS state
const PHP = {
    page: <?= $page ?>,
    limit: <?= $limit ?>,
    totalPages: <?= $total_pages ?>,
    sort: <?= json_encode($sort_column) ?>,
    dir: <?= json_encode(strtolower($sort_direction)) ?>,
    searchCountry: <?= json_encode($search_country) ?>,
    searchTown: <?= json_encode($search_town) ?>,
    searchCommodity: <?= json_encode($search_commodity) ?>,
    filterStatus: <?= json_encode($filter_status) ?>,
};

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function buildUrl(overrides) {
    const p = {
        page: PHP.page,
        limit: PHP.limit,
        sort: PHP.sort,
        dir: PHP.dir,
        search_country: document.getElementById('searchCountry').value,
        search_town: document.getElementById('searchTown').value,
        search_commodity: document.getElementById('searchCommodity').value.trim(),
        filter_status: document.getElementById('filterStatus').value,
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);
    
    const q = new URLSearchParams();
    q.set('page', p.page);
    q.set('limit', p.limit);
    if (p.sort) q.set('sort', p.sort);
    if (p.dir) q.set('dir', p.dir);
    if (p.search_country) q.set('search_country', p.search_country);
    if (p.search_town) q.set('search_town', p.search_town);
    if (p.search_commodity) q.set('search_commodity', p.search_commodity);
    if (p.filter_status) q.set('filter_status', p.filter_status);
    return '?' + q.toString();
}

function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (isNaN(pg) || pg < 1 || pg > PHP.totalPages) return;
    window.location.href = buildUrl({ page: pg });
}

function changeRowsPerPage() { window.location.href = buildUrl({ page: 1 }); }
function applyFilters() { window.location.href = buildUrl({ page: 1 }); }

function sortTable(col) {
    const newDir = (PHP.sort === col && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: col, dir: newDir });
}

// Add modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Miller Price';
    document.getElementById('priceId').value = '';
    document.getElementById('modalCountry').value = '';
    document.getElementById('modalTown').value = '';
    document.getElementById('modalCommodity').value = '';
    document.getElementById('modalPrice').value = '';
    document.getElementById('modalDataSource').value = '';
    document.getElementById('modalDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('editStatusDiv').classList.add('hidden');
    document.getElementById('submitBtn').name = 'add_miller_price';
    document.getElementById('submitBtn').textContent = 'Add Price';
    openModal('millerPriceModal');
}

// Edit modal
function editMillerPrice(id) {
    fetch(`${window.location.pathname}?get_miller_price=${id}`)
        .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Edit Miller Price';
            document.getElementById('priceId').value = data.id;
            document.getElementById('modalCountry').value = data.country || '';
            document.getElementById('modalTown').value = data.town || '';
            document.getElementById('modalCommodity').value = data.commodity_id || '';
            document.getElementById('modalPrice').value = data.price || '';
            document.getElementById('modalDataSource').value = data.data_source_id || '';
            document.getElementById('modalDate').value = data.date_posted ? data.date_posted.split(' ')[0] : '';
            document.getElementById('modalStatus').value = data.status || 'pending';
            document.getElementById('editStatusDiv').classList.remove('hidden');
            document.getElementById('submitBtn').name = 'edit_miller_price';
            document.getElementById('submitBtn').textContent = 'Update Price';
            openModal('millerPriceModal');
        })
        .catch(err => { console.error(err); alert('Failed to load price data.'); });
}

// Delete functions
function deleteSingle(id, label) {
    document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(label)}</strong>?`;
    document.getElementById('deleteIdsContainer').innerHTML = `<input type="hidden" name="selected_ids[]" value="${id}">`;
    openModal('deleteModal');
}

// Checkbox handling
function onCheckboxChange() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const total = document.querySelectorAll('.row-checkbox').length;
    const selAll = document.getElementById('selectAllCheckbox');
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkDeleteBtn').disabled = checked === 0;
    document.getElementById('bulkStatusBtn').disabled = checked === 0;
    selAll.checked = checked > 0 && checked === total;
    selAll.indeterminate = checked > 0 && checked < total;
}

// Import modal functions
function openImportModal() {
    document.getElementById('importForm').reset();
    document.getElementById('importFileInfo').classList.add('hidden');
    document.getElementById('importPreviewInfo').classList.add('hidden');
    openModal('importModal');
}

// Bulk status update
document.getElementById('bulkStatusBtn')?.addEventListener('click', function() {
    const ids = [...document.querySelectorAll('.row-checkbox:checked')].map(cb => cb.value);
    const newStatus = document.getElementById('bulkStatusSelect').value;
    if (!ids.length || !newStatus) return;
    
    document.getElementById('bulkNewStatus').value = newStatus;
    document.getElementById('bulkStatusIdsContainer').innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
    document.getElementById('bulkStatusForm').submit();
});

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Select-all checkbox
    document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        onCheckboxChange();
    });
    
    // Clear selections
    document.getElementById('clearSelectionsBtn')?.addEventListener('click', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
        document.getElementById('selectAllCheckbox').indeterminate = false;
        onCheckboxChange();
    });
    
    // Bulk delete
    document.getElementById('bulkDeleteBtn')?.addEventListener('click', function() {
        const ids = [...document.querySelectorAll('.row-checkbox:checked')].map(cb => cb.value);
        if (!ids.length) return;
        document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${ids.length}</strong> selected price(s)?`;
        document.getElementById('deleteIdsContainer').innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
        openModal('deleteModal');
    });
    
    // Sortable headers
    document.querySelectorAll('.sortable').forEach(th =>
        th.addEventListener('click', () => sortTable(th.dataset.sort))
    );
    
    // Import file preview
    const importFile = document.getElementById('importCsvFile');
    if (importFile) {
        importFile.addEventListener('change', function() {
            const infoEl = document.getElementById('importFileInfo');
            const previewEl = document.getElementById('importPreviewInfo');
            if (this.files[0]) {
                infoEl.textContent = `Selected: ${this.files[0].name} (${(this.files[0].size/1024).toFixed(1)} KB)`;
                infoEl.classList.remove('hidden');
                previewEl.classList.remove('hidden');
            } else {
                infoEl.classList.add('hidden');
                previewEl.classList.add('hidden');
            }
        });
    }
    
    // Enter key on search inputs
    ['searchCommodity'].forEach(id => {
        document.getElementById(id)?.addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    });
    
    onCheckboxChange();
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>