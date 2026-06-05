<?php
// xbt_volumes.php
session_start();

// ============================================================
// EXPORT CSV — must run BEFORE admin_header.php is included
// ============================================================
if (isset($_GET['export_csv'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="xbt_volumes_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $search_border = $_GET['search_border'] ?? '';
    $search_commodity = $_GET['search_commodity'] ?? '';
    $search_source = $_GET['search_source'] ?? '';
    $search_destination = $_GET['search_destination'] ?? '';
    $filter_status = $_GET['filter_status'] ?? '';

    $where_export = "WHERE 1=1";
    if (!empty($search_border)) {
        $where_export .= " AND b.name LIKE '%" . $con->real_escape_string($search_border) . "%'";
    }
    if (!empty($search_commodity)) {
        $where_export .= " AND (c.commodity_name LIKE '%" . $con->real_escape_string($search_commodity) . "%' OR c.variety LIKE '%" . $con->real_escape_string($search_commodity) . "%')";
    }
    if (!empty($search_source)) {
        $where_export .= " AND x.source LIKE '%" . $con->real_escape_string($search_source) . "%'";
    }
    if (!empty($search_destination)) {
        $where_export .= " AND x.destination LIKE '%" . $con->real_escape_string($search_destination) . "%'";
    }
    if (!empty($filter_status)) {
        $where_export .= " AND x.status = '" . $con->real_escape_string($filter_status) . "'";
    }

    $exp_query = "SELECT 
        x.id, b.name as border_name, 
        CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
        x.volume, x.source, x.destination, DATE(x.date_posted) as volume_date, 
        x.status, ds.data_source_name as data_source
        FROM xbt_volumes x
        LEFT JOIN border_points b ON x.border_id = b.id
        LEFT JOIN commodities c ON x.commodity_id = c.id
        LEFT JOIN data_sources ds ON x.data_source_id = ds.id
        $where_export
        ORDER BY x.date_posted DESC";
    
    $exp_result = $con->query($exp_query);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Border Point', 'Commodity', 'Volume (MT)', 'Source', 'Destination', 'Date', 'Status', 'Data Source']);

    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['border_name'], $row['commodity_display'],
            number_format($row['volume'], 2, '.', ''), $row['source'], $row['destination'],
            $row['volume_date'], $row['status'], $row['data_source']
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// POST: Add XBT Volume via modal
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_xbt_volume'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $country = trim($_POST['country']);
    $border_id = (int)$_POST['border_id'];
    $commodity_id = (int)$_POST['commodity_id'];
    $category_id = (int)$_POST['category_id'];
    $variety = trim($_POST['variety']);
    $volume = (float)$_POST['volume'];
    $source = trim($_POST['source']);
    $destination = trim($_POST['destination']);
    $data_source_id = (int)$_POST['data_source_id'];
    
    // Get border name
    $border_name = "";
    $stmt = $con->prepare("SELECT name FROM border_points WHERE id = ?");
    $stmt->bind_param("i", $border_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $border_name = $row['name'];
    }
    $stmt->close();
    
    // Get commodity name
    $commodity_name = "";
    $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $commodity_name = $row['commodity_name'];
    }
    $stmt->close();
    
    // Get category name
    $category_name = "";
    $stmt = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $category_name = $row['name'];
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
    
    $date_posted = date('Y-m-d H:i:s');
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $status = 'pending';
    
    $stmt = $con->prepare("INSERT INTO xbt_volumes (country, border_id, border_name, commodity_id, commodity_name, category_id, category_name, variety, volume, source, destination, data_source_id, data_source_name, date_posted, day, month, year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssisssdssisiiis", $country, $border_id, $border_name, $commodity_id, $commodity_name, $category_id, $category_name, $variety, $volume, $source, $destination, $data_source_id, $data_source_name, $date_posted, $day, $month, $year, $status);
    
    if ($stmt->execute()) {
        $_SESSION['import_message'] = "XBT volume added successfully!";
        $_SESSION['import_status'] = "success";
    } else {
        $_SESSION['import_message'] = "Error adding XBT volume: " . $stmt->error;
        $_SESSION['import_status'] = "danger";
    }
    $stmt->close();
    header("Location: xbt_volumes.php");
    exit;
}

// ============================================================
// POST: Edit XBT Volume via modal
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_xbt_volume'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $id = (int)$_POST['volume_id'];
    $country = trim($_POST['country']);
    $border_id = (int)$_POST['border_id'];
    $commodity_id = (int)$_POST['commodity_id'];
    $category_id = (int)$_POST['category_id'];
    $variety = trim($_POST['variety']);
    $volume = (float)$_POST['volume'];
    $source = trim($_POST['source']);
    $destination = trim($_POST['destination']);
    $data_source_id = (int)$_POST['data_source_id'];
    $status = trim($_POST['status']);
    
    // Get border name
    $border_name = "";
    $stmt = $con->prepare("SELECT name FROM border_points WHERE id = ?");
    $stmt->bind_param("i", $border_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $border_name = $row['name'];
    }
    $stmt->close();
    
    // Get commodity name
    $commodity_name = "";
    $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $commodity_name = $row['commodity_name'];
    }
    $stmt->close();
    
    // Get category name
    $category_name = "";
    $stmt = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $category_name = $row['name'];
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
    
    $stmt = $con->prepare("UPDATE xbt_volumes SET country=?, border_id=?, border_name=?, commodity_id=?, commodity_name=?, category_id=?, category_name=?, variety=?, volume=?, source=?, destination=?, data_source_id=?, data_source_name=?, status=? WHERE id=?");
    $stmt->bind_param("sisssisssdssissi", $country, $border_id, $border_name, $commodity_id, $commodity_name, $category_id, $category_name, $variety, $volume, $source, $destination, $data_source_id, $data_source_name, $status, $id);
    
    if ($stmt->execute()) {
        $_SESSION['import_message'] = "XBT volume updated successfully!";
        $_SESSION['import_status'] = "success";
    } else {
        $_SESSION['import_message'] = "Error updating XBT volume: " . $stmt->error;
        $_SESSION['import_status'] = "danger";
    }
    $stmt->close();
    header("Location: xbt_volumes.php");
    exit;
}

// ============================================================
// POST: Delete XBT Volumes
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    $selected_ids = array_map('intval', (array)$_POST['selected_ids']);
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $stmt = $con->prepare("DELETE FROM xbt_volumes WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $_SESSION['import_message'] = "Successfully deleted $deleted XBT volume(s).";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error deleting: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: xbt_volumes.php");
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
    $stmt = $con->prepare("UPDATE xbt_volumes SET status = ? WHERE id IN ($placeholders)");
    if ($stmt) {
        $types = 's' . str_repeat('i', count($selected_ids));
        $stmt->bind_param($types, $new_status, ...$selected_ids);
        if ($stmt->execute()) {
            $updated = $stmt->affected_rows;
            $_SESSION['import_message'] = "Successfully updated $updated XBT volume(s) to '$new_status'.";
            $_SESSION['import_status'] = "success";
        } else {
            $_SESSION['import_message'] = "Error updating status: " . $stmt->error;
            $_SESSION['import_status'] = "danger";
        }
        $stmt->close();
    }
    header("Location: xbt_volumes.php");
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
            
            if (empty(trim($data[0]))) { $errors[] = "Row $rowNumber: Border ID is required"; $errorCount++; continue; }
            if (empty(trim($data[1]))) { $errors[] = "Row $rowNumber: Commodity ID is required"; $errorCount++; continue; }
            if (empty(trim($data[2]))) { $errors[] = "Row $rowNumber: Volume is required"; $errorCount++; continue; }
            if (empty(trim($data[3]))) { $errors[] = "Row $rowNumber: Date is required"; $errorCount++; continue; }
            
            $border_id = (int)trim($data[0]);
            $commodity_id = (int)trim($data[1]);
            $volume = (float)trim($data[2]);
            $date_string = trim($data[3]);
            $source = isset($data[4]) ? trim($data[4]) : '';
            $destination = isset($data[5]) ? trim($data[5]) : '';
            $country = isset($data[6]) ? trim($data[6]) : 'Kenya';
            $data_source_id = isset($data[7]) && !empty(trim($data[7])) ? (int)trim($data[7]) : 1;
            $status = isset($data[8]) ? trim($data[8]) : 'pending';
            
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
            
            // Get border name
            $border_name = "";
            $stmt = $con->prepare("SELECT name FROM border_points WHERE id = ?");
            $stmt->bind_param("i", $border_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $border_name = $row['name'];
            } else {
                $errors[] = "Row $rowNumber: Border ID $border_id not found";
                $errorCount++;
                continue;
            }
            $stmt->close();
            
            // Get commodity name and category
            $commodity_name = "";
            $variety = "";
            $category_id = 1;
            $stmt = $con->prepare("SELECT commodity_name, variety, category_id FROM commodities WHERE id = ?");
            $stmt->bind_param("i", $commodity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $commodity_name = $row['commodity_name'];
                $variety = $row['variety'];
                $category_id = $row['category_id'] ?? 1;
            } else {
                $errors[] = "Row $rowNumber: Commodity ID $commodity_id not found";
                $errorCount++;
                continue;
            }
            $stmt->close();
            
            // Get category name
            $category_name = "";
            $stmt = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $category_name = $row['name'];
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
            
            $day = date('d', strtotime($date_posted));
            $month = date('m', strtotime($date_posted));
            $year = date('Y', strtotime($date_posted));
            
            $valid_statuses = ['pending', 'approved', 'published', 'unpublished'];
            if (!in_array($status, $valid_statuses)) {
                $errors[] = "Row $rowNumber: Invalid status '$status'";
                $errorCount++;
                continue;
            }
            
            // Check if record exists
            $check_stmt = $con->prepare("SELECT id FROM xbt_volumes WHERE border_id = ? AND commodity_id = ? AND DATE(date_posted) = DATE(?)");
            $check_stmt->bind_param("iis", $border_id, $commodity_id, $date_posted);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if ($overwrite) {
                    $update_stmt = $con->prepare("UPDATE xbt_volumes SET volume=?, source=?, destination=?, country=?, data_source_id=?, data_source_name=?, status=?, day=?, month=?, year=? WHERE border_id=? AND commodity_id=? AND DATE(date_posted)=DATE(?)");
                    $update_stmt->bind_param("dsssisiiiiiis", $volume, $source, $destination, $country, $data_source_id, $data_source_name, $status, $day, $month, $year, $border_id, $commodity_id, $date_posted);
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
            $insert_stmt = $con->prepare("INSERT INTO xbt_volumes (country, border_id, border_name, commodity_id, commodity_name, category_id, category_name, variety, volume, source, destination, data_source_id, data_source_name, date_posted, day, month, year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sisssisssdssisiiis", $country, $border_id, $border_name, $commodity_id, $commodity_name, $category_id, $category_name, $variety, $volume, $source, $destination, $data_source_id, $data_source_name, $date_posted, $day, $month, $year, $status);
            
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
            $_SESSION['import_message'] = "Successfully imported $successCount XBT volumes.";
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
    header("Location: xbt_volumes.php");
    exit;
}

// ============================================================
// CSV TEMPLATE DOWNLOAD
// ============================================================
if (isset($_GET['download_template'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="xbt_volumes_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Border ID', 'Commodity ID', 'Volume (MT)', 'Date (YYYY-MM-DD)', 'Source', 'Destination', 'Country', 'Data Source ID', 'Status']);
    fputcsv($out, ['1', '40', '1500.50', '2025-06-03', 'Kenya', 'Uganda', 'Kenya', '1', 'pending']);
    fputcsv($out, ['2', '41', '2000.75', '2025-06-03', 'Tanzania', 'Rwanda', 'Tanzania', '1', 'approved']);
    fclose($out);
    exit;
}

// ============================================================
// API HANDLER — fetch single XBT volume for edit modal
// ============================================================
if (isset($_GET['get_xbt_volume']) && is_numeric($_GET['get_xbt_volume'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';
    
    header('Content-Type: application/json');
    $get_id = (int)$_GET['get_xbt_volume'];
    $result = $con->query("SELECT x.*, c.category_id FROM xbt_volumes x LEFT JOIN commodities c ON x.commodity_id = c.id WHERE x.id = $get_id");
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
$total_volumes = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes")->fetch_assoc()['t'] ?? 0);
$pending_count = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes WHERE status = 'pending'")->fetch_assoc()['t'] ?? 0);
$approved_count = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes WHERE status = 'approved'")->fetch_assoc()['t'] ?? 0);
$published_count = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes WHERE status = 'published'")->fetch_assoc()['t'] ?? 0);
$unpublished_count = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes WHERE status = 'unpublished'")->fetch_assoc()['t'] ?? 0);

// Get distinct borders for filter
$borders_result = $con->query("SELECT DISTINCT b.id, b.name FROM xbt_volumes x LEFT JOIN border_points b ON x.border_id = b.id ORDER BY b.name");
$distinct_borders = [];
while ($row = $borders_result->fetch_assoc()) {
    if ($row['name']) $distinct_borders[] = $row['name'];
}

// Get distinct sources/destinations
$sources_result = $con->query("SELECT DISTINCT source FROM xbt_volumes ORDER BY source");
$distinct_sources = [];
while ($row = $sources_result->fetch_assoc()) {
    if ($row['source']) $distinct_sources[] = $row['source'];
}

$destinations_result = $con->query("SELECT DISTINCT destination FROM xbt_volumes ORDER BY destination");
$distinct_destinations = [];
while ($row = $destinations_result->fetch_assoc()) {
    if ($row['destination']) $distinct_destinations[] = $row['destination'];
}

// ============================================================
// PAGINATION + SORTING + FILTERING
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts = ['id', 'border', 'commodity', 'volume', 'source', 'destination', 'date_posted', 'status', 'data_source'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'date_posted';

$search_border = trim($_GET['search_border'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');
$search_source = trim($_GET['search_source'] ?? '');
$search_destination = trim($_GET['search_destination'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search_border !== '') {
    $where .= " AND b.name LIKE ?";
    $params[] = '%' . $search_border . '%';
    $types .= "s";
}
if ($search_commodity !== '') {
    $where .= " AND (c.commodity_name LIKE ? OR c.variety LIKE ?)";
    $params[] = '%' . $search_commodity . '%';
    $params[] = '%' . $search_commodity . '%';
    $types .= "ss";
}
if ($search_source !== '') {
    $where .= " AND x.source LIKE ?";
    $params[] = '%' . $search_source . '%';
    $types .= "s";
}
if ($search_destination !== '') {
    $where .= " AND x.destination LIKE ?";
    $params[] = '%' . $search_destination . '%';
    $types .= "s";
}
if ($filter_status !== '') {
    $where .= " AND x.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Count total records
$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM xbt_volumes x LEFT JOIN border_points b ON x.border_id = b.id LEFT JOIN commodities c ON x.commodity_id = c.id LEFT JOIN data_sources ds ON x.data_source_id = ds.id $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($page - 1) * $limit;

// Map sort column to database column
$sort_map = [
    'id' => 'x.id',
    'border' => 'b.name',
    'commodity' => 'c.commodity_name',
    'volume' => 'x.volume',
    'source' => 'x.source',
    'destination' => 'x.destination',
    'date_posted' => 'x.date_posted',
    'status' => 'x.status',
    'data_source' => 'ds.data_source_name'
];
$order_by = $sort_map[$sort_column] ?? 'x.date_posted';
$dir = $sort_direction === 'ASC' ? 'ASC' : 'DESC';

// Fetch data
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$query = "SELECT 
    x.id, x.volume, x.source, x.destination, x.date_posted, x.status,
    b.name as border_name,
    CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
    ds.data_source_name as data_source
    FROM xbt_volumes x
    LEFT JOIN border_points b ON x.border_id = b.id
    LEFT JOIN commodities c ON x.commodity_id = c.id
    LEFT JOIN data_sources ds ON x.data_source_id = ds.id
    $where 
    ORDER BY $order_by $dir
    LIMIT ? OFFSET ?";

$data_stmt = $con->prepare($query);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$xbt_volumes = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;

// Get data for modals
$border_points = [];
$bp_result = $con->query("SELECT id, name FROM border_points ORDER BY name");
while ($row = $bp_result->fetch_assoc()) {
    $border_points[] = $row;
}

$commodities = [];
$comm_result = $con->query("SELECT id, commodity_name, variety, category_id FROM commodities ORDER BY commodity_name");
while ($row = $comm_result->fetch_assoc()) {
    $row['display_name'] = $row['commodity_name'] . (!empty($row['variety']) ? ' (' . $row['variety'] . ')' : '');
    $commodities[] = $row;
}

$categories = [];
$cat_result = $con->query("SELECT id, name FROM commodity_categories ORDER BY name");
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row;
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

.volume-value{font-family:monospace;font-weight:700;font-size:.85rem}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">XBT Volumes Management</h1>
                <p class="text-gray-600 text-sm mt-1">Manage cross-border trade volume data</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_csv=1&search_border=<?= urlencode($search_border) ?>&search_commodity=<?= urlencode($search_commodity) ?>&search_source=<?= urlencode($search_source) ?>&search_destination=<?= urlencode($search_destination) ?>&filter_status=<?= urlencode($filter_status) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export CSV
                </a>
                <button onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">upload_file</span>Import CSV
                </button>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">add_circle</span>Add Volume
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
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Volumes</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_volumes) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">bar_chart</span>
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
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">warehouse</span>
                    <select id="searchBorder" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Borders</option>
                        <?php foreach ($distinct_borders as $border): ?>
                            <option value="<?= htmlspecialchars($border) ?>" <?= $search_border == $border ? 'selected' : '' ?>><?= htmlspecialchars($border) ?></option>
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
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">flight_takeoff</span>
                    <select id="searchSource" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Sources</option>
                        <?php foreach ($distinct_sources as $source): ?>
                            <option value="<?= htmlspecialchars($source) ?>" <?= $search_source == $source ? 'selected' : '' ?>><?= htmlspecialchars($source) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">flight_land</span>
                    <select id="searchDestination" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Destinations</option>
                        <?php foreach ($distinct_destinations as $dest): ?>
                            <option value="<?= htmlspecialchars($dest) ?>" <?= $search_destination == $dest ? 'selected' : '' ?>><?= htmlspecialchars($dest) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="border">Border Point<?php if($sort_column=='border') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="commodity">Commodity<?php if($sort_column=='commodity') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="volume">Volume (MT)<?php if($sort_column=='volume') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="source">Source<?php if($sort_column=='source') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="destination">Destination<?php if($sort_column=='destination') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_posted">Date<?php if($sort_column=='date_posted') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="status">Status<?php if($sort_column=='status') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="data_source">Data Source<?php if($sort_column=='data_source') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase w-20">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($xbt_volumes)): ?>
                    <tr>
                        <td colspan="11" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">swap_horiz</span>
                            <p class="text-sm mt-1">No XBT volumes found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($xbt_volumes as $volume): ?>
                    <tr class="table-row-hover" data-id="<?= $volume['id'] ?>">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="<?= $volume['id'] ?>" onchange="onCheckboxChange()">
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= $volume['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($volume['border_name']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($volume['commodity_display']) ?></td>
                        <td class="px-3 py-2 text-xs font-mono font-semibold text-gray-700"><?= number_format($volume['volume'], 2) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($volume['source']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($volume['destination']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= date('M d, Y', strtotime($volume['date_posted'])) ?></td>
                        <td class="px-3 py-2"><?= getStatusBadge($volume['status']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($volume['data_source']) ?></td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editXBTVolume(<?= $volume['id'] ?>)" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-200" title="Edit">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button onclick="deleteSingle(<?= $volume['id'] ?>, '<?= htmlspecialchars(addslashes($volume['border_name'])) ?> - <?= htmlspecialchars(addslashes($volume['commodity_display'])) ?>')" class="action-btn bg-red-100 text-red-700 hover:bg-red-200" title="Delete">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </div>
                        </tr>
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
                        No volumes found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> volumes
                        <?php if ($search_border || $search_commodity || $search_source || $search_destination || $filter_status): ?>
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
<div id="xbtVolumeModal" class="fixed inset-0 bg-black/50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
            <div class="modal-gradient-header px-5 py-3 flex justify-between items-center rounded-t-xl">
                <h3 id="modalTitle" class="text-base font-semibold text-white">Add XBT Volume</h3>
                <button onclick="closeModal('xbtVolumeModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <form method="POST" action="" id="xbtVolumeForm">
                    <input type="hidden" name="volume_id" id="volumeId">

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
                            <label class="block text-xs text-gray-600 mb-1">Border Point <span class="text-red-500">*</span></label>
                            <select name="border_id" id="modalBorder" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Border</option>
                                <?php foreach ($border_points as $bp): ?>
                                    <option value="<?= $bp['id'] ?>"><?= htmlspecialchars($bp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                            <label class="block text-xs text-gray-600 mb-1">Category <span class="text-red-500">*</span></label>
                            <select name="category_id" id="modalCategory" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Variety</label>
                            <input type="text" name="variety" id="modalVariety"
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                placeholder="e.g., Yellow, White">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Volume (MT) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="volume" id="modalVolume" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none"
                                placeholder="e.g., 1500.50">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Source Country <span class="text-red-500">*</span></label>
                            <select name="source" id="modalSource" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Source</option>
                                <option value="Kenya">Kenya</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Burundi">Burundi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Destination Country <span class="text-red-500">*</span></label>
                            <select name="destination" id="modalDestination" required
                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-maroon focus:outline-none">
                                <option value="">Select Destination</option>
                                <option value="Kenya">Kenya</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Burundi">Burundi</option>
                            </select>
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
                        <button type="button" onclick="closeModal('xbtVolumeModal')"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="add_xbt_volume" id="submitBtn"
                            class="px-3 py-1.5 text-sm bg-maroon text-white rounded-lg hover:bg-[#660000]">Add Volume</button>
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
                    Bulk Import XBT Volumes (CSV)
                </h3>
                <button onclick="closeModal('importModal')" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="p-5">
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-r-lg p-4 mb-5 text-sm">
                    <p class="font-semibold text-blue-800 mb-2">CSV Column Order</p>
                    <ol class="list-decimal list-inside text-blue-700 space-y-0.5 text-xs">
                        <li><strong>Border ID</strong> — required (integer ID from border_points table)</li>
                        <li><strong>Commodity ID</strong> — required (integer ID from commodities table)</li>
                        <li><strong>Volume (MT)</strong> — required (numeric)</li>
                        <li><strong>Date</strong> — required (e.g., 2025-01-15 or 2025-01-15 14:30:00)</li>
                        <li><strong>Source</strong> — optional (e.g., Kenya, Uganda)</li>
                        <li><strong>Destination</strong> — optional (e.g., Kenya, Uganda)</li>
                        <li><strong>Country</strong> — optional (default Kenya)</li>
                        <li><strong>Data Source ID</strong> — optional (default 1)</li>
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
                        <span>Overwrite existing volumes with matching Border + Commodity + Date</span>
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
    searchBorder: <?= json_encode($search_border) ?>,
    searchCommodity: <?= json_encode($search_commodity) ?>,
    searchSource: <?= json_encode($search_source) ?>,
    searchDestination: <?= json_encode($search_destination) ?>,
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
        search_border: document.getElementById('searchBorder').value,
        search_commodity: document.getElementById('searchCommodity').value.trim(),
        search_source: document.getElementById('searchSource').value,
        search_destination: document.getElementById('searchDestination').value,
        filter_status: document.getElementById('filterStatus').value,
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);
    
    const q = new URLSearchParams();
    q.set('page', p.page);
    q.set('limit', p.limit);
    if (p.sort) q.set('sort', p.sort);
    if (p.dir) q.set('dir', p.dir);
    if (p.search_border) q.set('search_border', p.search_border);
    if (p.search_commodity) q.set('search_commodity', p.search_commodity);
    if (p.search_source) q.set('search_source', p.search_source);
    if (p.search_destination) q.set('search_destination', p.search_destination);
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
    document.getElementById('modalTitle').textContent = 'Add XBT Volume';
    document.getElementById('volumeId').value = '';
    document.getElementById('modalCountry').value = '';
    document.getElementById('modalBorder').value = '';
    document.getElementById('modalCommodity').value = '';
    document.getElementById('modalCategory').value = '';
    document.getElementById('modalVariety').value = '';
    document.getElementById('modalVolume').value = '';
    document.getElementById('modalSource').value = '';
    document.getElementById('modalDestination').value = '';
    document.getElementById('modalDataSource').value = '';
    document.getElementById('editStatusDiv').classList.add('hidden');
    document.getElementById('submitBtn').name = 'add_xbt_volume';
    document.getElementById('submitBtn').textContent = 'Add Volume';
    openModal('xbtVolumeModal');
}

// Edit modal
function editXBTVolume(id) {
    fetch(`${window.location.pathname}?get_xbt_volume=${id}`)
        .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Edit XBT Volume';
            document.getElementById('volumeId').value = data.id;
            document.getElementById('modalCountry').value = data.country || '';
            document.getElementById('modalBorder').value = data.border_id || '';
            document.getElementById('modalCommodity').value = data.commodity_id || '';
            document.getElementById('modalCategory').value = data.category_id || '';
            document.getElementById('modalVariety').value = data.variety || '';
            document.getElementById('modalVolume').value = data.volume || '';
            document.getElementById('modalSource').value = data.source || '';
            document.getElementById('modalDestination').value = data.destination || '';
            document.getElementById('modalDataSource').value = data.data_source_id || '';
            document.getElementById('modalStatus').value = data.status || 'pending';
            document.getElementById('editStatusDiv').classList.remove('hidden');
            document.getElementById('submitBtn').name = 'edit_xbt_volume';
            document.getElementById('submitBtn').textContent = 'Update Volume';
            openModal('xbtVolumeModal');
        })
        .catch(err => { console.error(err); alert('Failed to load XBT volume data.'); });
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
        document.getElementById('deleteModalText').innerHTML = `Are you sure you want to delete <strong>${ids.length}</strong> selected volume(s)?`;
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