<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
include '../admin/includes/config.php';
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

/**
 * Fetch XBT volumes data with filtering and pagination
 */
function getXBTVolumesData($con, $limit = 10, $offset = 0, $filters = []) {
    $sql = "SELECT
                x.id,
                x.border_name,
                x.commodity_id,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                x.volume,
                x.source,
                x.destination,
                x.date_posted,
                x.status,
                ds.data_source_name AS data_source,
                x.country AS source_country,
                x.destination AS destination_country,
                'MT' AS unit
            FROM
                xbt_volumes x
            LEFT JOIN
                commodities c ON x.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON x.data_source_id = ds.id
            WHERE
                x.status IN ('published', 'approved')";
    
    // Apply filters
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($filters['border_name']) && $filters['border_name'] != 'all') {
        $whereConditions[] = "x.border_name = ?";
        $params[] = $filters['border_name'];
        $types .= 's';
    }
    
    if (!empty($filters['commodity']) && $filters['commodity'] != 'all') {
        $whereConditions[] = "CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) = ?";
        $params[] = $filters['commodity'];
        $types .= 's';
    }
    
    if (!empty($filters['source_country']) && $filters['source_country'] != 'all') {
        $whereConditions[] = "x.country = ?";
        $params[] = $filters['source_country'];
        $types .= 's';
    }
    
    if (!empty($filters['destination_country']) && $filters['destination_country'] != 'all') {
        $whereConditions[] = "x.destination = ?";
        $params[] = $filters['destination_country'];
        $types .= 's';
    }
    
    if (!empty($filters['data_source']) && $filters['data_source'] != 'all') {
        $whereConditions[] = "ds.data_source_name = ?";
        $params[] = $filters['data_source'];
        $types .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "DATE(x.date_posted) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "DATE(x.date_posted) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    if (!empty($filters['volume_min'])) {
        $whereConditions[] = "x.volume >= ?";
        $params[] = $filters['volume_min'];
        $types .= 'd';
    }
    
    if (!empty($filters['volume_max'])) {
        $whereConditions[] = "x.volume <= ?";
        $params[] = $filters['volume_max'];
        $types .= 'd';
    }
    
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY x.date_posted DESC LIMIT $limit OFFSET $offset";

    // Prepare and execute the query
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing XBT volumes query: " . $con->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Error executing XBT volumes query: " . $stmt->error);
        $stmt->close();
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Error getting XBT volumes result: " . $stmt->error);
        $stmt->close();
        return [];
    }

    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    $result->free();
    $stmt->close();
    return $data;
}

/**
 * Get total number of records for pagination with filters
 */
function getTotalXBTVolumeRecords($con, $filters = []) {
    $sql = "SELECT COUNT(*) as total 
            FROM xbt_volumes x
            LEFT JOIN commodities c ON x.commodity_id = c.id
            LEFT JOIN data_sources ds ON x.data_source_id = ds.id
            WHERE x.status IN ('published', 'approved')";
    
    // Apply filters
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($filters['border_name']) && $filters['border_name'] != 'all') {
        $whereConditions[] = "x.border_name = ?";
        $params[] = $filters['border_name'];
        $types .= 's';
    }
    
    if (!empty($filters['commodity']) && $filters['commodity'] != 'all') {
        $whereConditions[] = "CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) = ?";
        $params[] = $filters['commodity'];
        $types .= 's';
    }
    
    if (!empty($filters['source_country']) && $filters['source_country'] != 'all') {
        $whereConditions[] = "x.country = ?";
        $params[] = $filters['source_country'];
        $types .= 's';
    }
    
    if (!empty($filters['destination_country']) && $filters['destination_country'] != 'all') {
        $whereConditions[] = "x.destination = ?";
        $params[] = $filters['destination_country'];
        $types .= 's';
    }
    
    if (!empty($filters['data_source']) && $filters['data_source'] != 'all') {
        $whereConditions[] = "ds.data_source_name = ?";
        $params[] = $filters['data_source'];
        $types .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "DATE(x.date_posted) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "DATE(x.date_posted) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    if (!empty($filters['volume_min'])) {
        $whereConditions[] = "x.volume >= ?";
        $params[] = $filters['volume_min'];
        $types .= 'd';
    }
    
    if (!empty($filters['volume_max'])) {
        $whereConditions[] = "x.volume <= ?";
        $params[] = $filters['volume_max'];
        $types .= 'd';
    }
    
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing count query: " . $con->error);
        return 0;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Error executing count query: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Error getting count result: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $row = $result->fetch_assoc();
    $total = (int)$row['total'];
    
    $result->free();
    $stmt->close();
    return $total;
}

/**
 * Get filter options for dropdowns
 */
function getFilterOptions($con, $table, $column) {
    $sql = "SELECT DISTINCT $column FROM $table WHERE $column IS NOT NULL AND $column != '' ORDER BY $column";
    $result = $con->query($sql);
    $options = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $options[] = $row[$column];
        }
        $result->free();
    }
    return $options;
}

// Get filter options
$border_options = getFilterOptions($con, 'xbt_volumes', 'border_name');
$source_country_options = getFilterOptions($con, 'xbt_volumes', 'country');
$destination_country_options = getFilterOptions($con, 'xbt_volumes', 'destination');
$data_source_options = getFilterOptions($con, 'data_sources', 'data_source_name');

// Get commodity options
$commodity_options = [];
$commodity_sql = "SELECT DISTINCT CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display 
                  FROM commodities c 
                  JOIN xbt_volumes x ON c.id = x.commodity_id 
                  WHERE x.status IN ('published', 'approved')
                  ORDER BY commodity_display";
$commodity_result = $con->query($commodity_sql);
if ($commodity_result && $commodity_result->num_rows > 0) {
    while ($row = $commodity_result->fetch_assoc()) {
        $commodity_options[] = $row['commodity_display'];
    }
    $commodity_result->free();
}

// Get filter values from request
$filters = [
    'border_name' => $_GET['border_name'] ?? 'all',
    'commodity' => $_GET['commodity'] ?? 'all',
    'source_country' => $_GET['source_country'] ?? 'all',
    'destination_country' => $_GET['destination_country'] ?? 'all',
    'data_source' => $_GET['data_source'] ?? 'all',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'volume_min' => $_GET['volume_min'] ?? '',
    'volume_max' => $_GET['volume_max'] ?? ''
];

// Get total records and setup pagination
$total_records = getTotalXBTVolumeRecords($con, $filters);
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch data with filters
$volumes_data = getXBTVolumesData($con, $limit, $offset, $filters);
$total_pages = ceil($total_records / $limit);

// Group data for display
$grouped_data = [];
foreach ($volumes_data as $volume) {
    if (!isset($volume['border_name'], $volume['commodity_display'], $volume['date_posted'], $volume['data_source'])) {
        continue;
    }
    $date = date('Y-m-d', strtotime($volume['date_posted']));
    $group_key = $date . '_' . $volume['border_name'] . '_' . $volume['commodity_display'] . '_' . $volume['data_source'];
    $grouped_data[$group_key][] = $volume;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATIN - XBT Volumes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #8B4513;
            --primary-light: #f5d6c6;
            --secondary-color: #6c757d;
            --success-color: #059669;
            --danger-color: #dc2626;
            --light-gray: #f8f9fa;
            --border-color: #eee;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #fff;
            border-right: 1px solid var(--border-color);
            padding: 20px 15px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sidebar .ratin-logo {
            max-width: 180px;
            height: auto;
        }
        
        .sidebar h6 {
            color: var(--secondary-color);
            font-weight: 600;
            margin: 25px 0 15px 10px;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 0.5px;
        }
        
        .sidebar .nav-link {
            color: #444;
            padding: 12px 15px;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.95em;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 0.9em;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }
        
        /* Header */
        .header-container {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            margin-left: 250px;
            height: 70px;
            z-index: 999;
        }
        
        .breadcrumb {
            margin: 0;
            font-size: 0.95em;
        }
        
        .breadcrumb a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .user-display {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: var(--primary-color);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 25px;
            flex-grow: 1;
            margin-top: 70px;
        }
        
        /* Card styles */
        .dashboard-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        /* Toolbar styles */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 25px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 18px;
            font-size: 0.85em;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        
        .btn-outline {
            background-color: #fff;
            border-color: #ddd;
            color: #444;
        }
        
        .btn-outline:hover {
            background-color: #f5f5f5;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #7a3a12;
            border-color: #7a3a12;
        }
        
        /* Table container */
        .table-container {
            width: 100%;
            overflow-x: auto;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        
        /* Table styles */
        .data-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.85em;
        }
        
        .data-table th, 
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
            vertical-align: middle;
        }
        
        .data-table th {
            position: sticky;
            top: 0;
            background-color: #f5f5f5;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8em;
            color: #555;
        }
        
        .data-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        /* Checkbox styling */
        .checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-info {
            font-size: 0.85em;
            color: #666;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border-radius: 4px;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s ease;
            font-size: 0.85em;
        }
        
        .page-btn:hover:not(.active) {
            background-color: #e0e0e0;
        }
        
        .page-btn.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        /* Filter section */
        .filter-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            margin-bottom: 0;
        }
        
        .filter-label {
            display: block;
            font-size: 0.85em;
            font-weight: 500;
            color: #555;
            margin-bottom: 6px;
        }
        
        .filter-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.85em;
        }
        
        /* View tabs */
        .view-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }
        
        .view-tab {
            padding: 15px 20px;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            color: #666;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        /* Chart container */
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        
        /* Map container */
        .map-container {
            height: 500px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Flow direction indicator */
        .flow-direction {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                margin-left: 0;
            }
            
            .header-container, 
            .main-content {
                margin-left: 0;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar-left, 
            .toolbar-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img class="ratin-logo" src="../base/img/Ratin-logo-1.png" alt="RATIN Logo">
        </div>
        
        <h6>Price Parity</h6>
        <div class="submenu">
            <a href="marketprices.php" class="nav-link">
                <i class="fas fa-store-alt"></i> Market Prices
            </a>
            <a href="millerprices.php" class="nav-link">
                <i class="fas fa-industry"></i> Miller Prices
            </a>
            <a href="#" class="nav-link active">
                <i class="fas fa-exchange-alt"></i> XBT Volumes
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1">
        <!-- Header -->
        <div class="header-container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="#"><i class="fa fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">XBT Volumes</li>
                </ol>
            </nav>
            <div class="user-display">
                <i class="fa fa-user-circle"></i> <span>Martin Kim</span>
            </div>
        </div>

        <!-- Content -->
        <div class="main-content">
            <!-- Filter Section -->
            <div class="filter-section dashboard-card">
                <form id="filter-form" method="GET">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Border Point</label>
                            <select class="filter-input" id="border-filter" name="border_name">
                                <option value="all">All Borders</option>
                                <?php foreach ($border_options as $border): ?>
                                    <option value="<?= htmlspecialchars($border) ?>" <?= $filters['border_name'] == $border ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($border) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Commodity</label>
                            <select class="filter-input" id="commodity-filter" name="commodity">
                                <option value="all">All Commodities</option>
                                <?php foreach ($commodity_options as $commodity): ?>
                                    <option value="<?= htmlspecialchars($commodity) ?>" <?= $filters['commodity'] == $commodity ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($commodity) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Source Country</label>
                            <select class="filter-input" id="source-country-filter" name="source_country">
                                <option value="all">All Countries</option>
                                <?php foreach ($source_country_options as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>" <?= $filters['source_country'] == $country ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Destination Country</label>
                            <select class="filter-input" id="destination-country-filter" name="destination_country">
                                <option value="all">All Countries</option>
                                <?php foreach ($destination_country_options as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>" <?= $filters['destination_country'] == $country ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Data Source</label>
                            <select class="filter-input" id="data-source-filter" name="data_source">
                                <option value="all">All Sources</option>
                                <?php foreach ($data_source_options as $source): ?>
                                    <option value="<?= htmlspecialchars($source) ?>" <?= $filters['data_source'] == $source ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($source) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Date Range</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="date" class="filter-input" id="date-from" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                                <input type="date" class="filter-input" id="date-to" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Volume Range (MT)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" class="filter-input" placeholder="Min" id="volume-min" name="volume_min" value="<?= htmlspecialchars($filters['volume_min']) ?>">
                                <input type="number" class="filter-input" placeholder="Max" id="volume-max" name="volume_max" value="<?= htmlspecialchars($filters['volume_max']) ?>">
                            </div>
                        </div>
                        <div class="filter-group" style="display: flex; align-items: flex-end;">
                            <button type="button" class="btn btn-outline" id="reset-filters">
                                <i class="fas fa-refresh"></i> Reset Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Data Display Card -->
            <div class="dashboard-card">
                <!-- View Tabs -->
                <div style="border-bottom: 1px solid var(--border-color);">
                    <nav class="view-tabs">
                        <button class="view-tab active" data-view="table">
                            <i class="fa fa-table"></i> Table View
                        </button>
                        <button class="view-tab" data-view="chart">
                            <i class="fa fa-chart-bar"></i> Chart View
                        </button>
                        <button class="view-tab" data-view="map">
                            <i class="fa fa-map"></i> Map View
                        </button>
                    </nav>
                </div>

                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-primary">
                            <i class="fas fa-ellipsis-h"></i> All Commodities
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-seedling"></i> Cereals
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-tint"></i> Oilseeds
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-leaf"></i> Pulses
                        </button>
                    </div>
                    <div class="toolbar-right">
                        <button class="btn btn-outline">
                            <i class="fas fa-download"></i> Download Data
                        </button>
                    </div>
                </div>

                <!-- Table View -->
                <div id="table-view" class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" class="checkbox"></th>
                                <th>Border Point</th>
                                <th>Commodity</th>
                                <th>Volume (MT)</th>
                                <th>Flow Direction</th>
                                <th>Date</th>
                                <th>Unit</th>
                                <th>Data Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($grouped_data)): ?>
                                <?php foreach ($grouped_data as $group_key => $volumes_in_group): ?>
                                    <?php 
                                    $first_row = true;
                                    $group_volume_ids = array_column($volumes_in_group, 'id');
                                    $group_volume_ids_json = htmlspecialchars(json_encode($group_volume_ids));
                                    ?>
                                    
                                    <?php foreach($volumes_in_group as $volume): ?>
                                        <tr>
                                            <?php if ($first_row): ?>
                                                <td rowspan="<?= count($volumes_in_group) ?>">
                                                    <input type="checkbox" 
                                                           data-group-key="<?= $group_key ?>"
                                                           data-volume-ids="<?= $group_volume_ids_json ?>"
                                                           class="checkbox">
                                                </td>
                                                <td rowspan="<?= count($volumes_in_group) ?>" style="font-weight: 500;">
                                                    <?= htmlspecialchars($volume['border_name']) ?>
                                                </td>
                                                <td rowspan="<?= count($volumes_in_group) ?>">
                                                    <?= htmlspecialchars($volume['commodity_display']) ?>
                                                </td>
                                                <td rowspan="<?= count($volumes_in_group) ?>" style="font-weight: 600;">
                                                    <?= number_format($volume['volume'], 2) ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="flow-direction">
                                                <?= htmlspecialchars($volume['source_country']) ?>
                                                <i class="fas fa-arrow-right"></i>
                                                <?= htmlspecialchars($volume['destination_country']) ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($volume['date_posted'])) ?></td>
                                            <td><?= htmlspecialchars($volume['unit']) ?></td>
                                            <?php if ($first_row): ?>
                                                <td rowspan="<?= count($volumes_in_group) ?>">
                                                    <?= htmlspecialchars($volume['data_source']) ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php $first_row = false; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px;">
                                        No XBT volumes data found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Chart View (Hidden by default) -->
                <div id="chart-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4 style="margin-bottom: 5px;">XBT Volume Trends</h4>
                            <p style="color: var(--secondary-color); margin: 0;">Visual representation of cross-border trade volumes</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <select id="chart-type-selector" class="filter-input" style="width: 180px;">
                                <option value="bar">Bar Chart</option>
                                <option value="line">Line Chart</option>
                                <option value="pie">Pie Chart</option>
                            </select>
                            <button class="btn btn-outline" id="export-chart">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="volume-trend-chart"></canvas>
                    </div>
                    
                    <div class="chart-container" style="margin-top: 40px;">
                        <canvas id="commodity-distribution-chart"></canvas>
                    </div>
                </div>

                <!-- Map View (Hidden by default) -->
                <div id="map-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4 style="margin-bottom: 5px;">XBT Volume Flows</h4>
                            <p style="color: var(--secondary-color); margin: 0;">Geographical representation of trade flows</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <select id="map-view-selector" class="filter-input" style="width: 180px;">
                                <option value="volume">By Volume</option>
                                <option value="commodity">By Commodity</option>
                                <option value="route">By Trade Route</option>
                            </select>
                            <button class="btn btn-outline" id="export-map">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="map-container" id="xbt-map">
                        <!-- Map will be rendered here -->
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <h5>Key Trade Routes</h5>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                            <?php
                            // Get unique trade routes
                            $routes = [];
                            foreach ($volumes_data as $volume) {
                                $routeKey = $volume['source_country'] . '-' . $volume['destination_country'];
                                if (!isset($routes[$routeKey])) {
                                    $routes[$routeKey] = [
                                        'source' => $volume['source_country'],
                                        'destination' => $volume['destination_country'],
                                        'count' => 0
                                    ];
                                }
                                $routes[$routeKey]['count']++;
                            }
                            ?>
                            <?php foreach ($routes as $route): ?>
                                <div style="background: #fff; padding: 10px 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                    <div class="flow-direction">
                                        <?= htmlspecialchars($route['source']) ?>
                                        <i class="fas fa-arrow-right"></i>
                                        <?= htmlspecialchars($route['destination']) ?>
                                    </div>
                                    <div style="font-size: 0.8em; color: var(--secondary-color); margin-top: 5px;">
                                        <?= $route['count'] ?> records
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_records > 0): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <span style="font-weight: 500;"><?= $offset + 1 ?></span> to 
                        <span style="font-weight: 500;"><?= min($offset + $limit, $total_records) ?></span> of 
                        <span style="font-weight: 500;"><?= $total_records ?></span> results
                    </div>
                    <div class="pagination-controls">
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="page-btn" <?= $page <= 1 ? 'style="visibility: hidden;"' : '' ?>>
                            Previous
                        </a>
                        
                        <?php
                        $visiblePages = 5;
                        $startPage = max(1, $page - floor($visiblePages / 2));
                        $endPage = min($total_pages, $startPage + $visiblePages - 1);
                        
                        if ($startPage > 1) {
                            echo '<a href="?' . http_build_query(array_merge($filters, ['page' => 1])) . '" class="page-btn">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="page-btn" style="background: transparent;">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = $i == $page ? 'active' : '';
                            echo '<a href="?' . http_build_query(array_merge($filters, ['page' => $i])) . '" class="page-btn '.$activeClass.'">'.$i.'</a>';
                        }
                        
                        if ($endPage < $total_pages) {
                            if ($endPage < $total_pages - 1) {
                                echo '<span class="page-btn" style="background: transparent;">...</span>';
                            }
                            echo '<a href="?' . http_build_query(array_merge($filters, ['page' => $total_pages])) . '" class="page-btn">'.$total_pages.'</a>';
                        }
                        ?>
                        
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="page-btn" <?= $page >= $total_pages ? 'style="visibility: hidden;"' : '' ?>>
                            Next
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.js"></script>

<script>
// Initialize charts and map
let volumeTrendChart;
let commodityDistributionChart;
let currentChartType = 'bar';
let xbtMap;

// Function to initialize or update charts
function initCharts(data) {
    // Process data for charts
    const processedData = processChartData(data);
    
    // Destroy existing charts if they exist
    if (volumeTrendChart) volumeTrendChart.destroy();
    if (commodityDistributionChart) commodityDistributionChart.destroy();
    
    // Create Volume Trend Chart
    const volumeTrendCtx = document.getElementById('volume-trend-chart');
    if (volumeTrendCtx) {
        volumeTrendChart = new Chart(volumeTrendCtx.getContext('2d'), {
            type: currentChartType,
            data: {
                labels: processedData.dates,
                datasets: processedData.trendDatasets
            },
            options: getTrendChartOptions()
        });
    }
    
    // Create Commodity Distribution Chart
    const commodityCtx = document.getElementById('commodity-distribution-chart');
    if (commodityCtx) {
        commodityDistributionChart = new Chart(commodityCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: processedData.commodityLabels,
                datasets: [{
                    data: processedData.commodityVolumes,
                    backgroundColor: getColorPalette(processedData.commodityLabels.length)
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Commodity Distribution',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} MT`;
                            }
                        }
                    }
                }
            }
        });
    }
}

// Function to initialize the map
function initMap(data) {
    const mapContainer = document.getElementById('xbt-map');
    if (!mapContainer) return;
    
    // Clear existing map if it exists
    if (xbtMap) {
        xbtMap.remove();
    }
    
    // Create a new map centered on East Africa
    xbtMap = L.map('xbt-map').setView([-1.2921, 36.8219], 6);
    
    // Add base tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(xbtMap);
    
    // In a real implementation, you would add markers/polylines for trade flows
    // This is just a placeholder visualization
    const processedData = processMapData(data);
    
    // Add markers for border points
    processedData.borderPoints.forEach(point => {
        L.marker([point.lat, point.lng]).addTo(xbtMap)
            .bindPopup(`<b>${point.name}</b><br>Total Volume: ${point.totalVolume} MT`);
    });
    
    // Add flow lines between countries
    processedData.tradeFlows.forEach(flow => {
        L.polyline(
            [flow.sourceCoords, flow.destinationCoords],
            {color: '#8B4513', weight: Math.min(5, flow.volume / 1000)}
        ).addTo(xbtMap)
        .bindPopup(`<b>${flow.source} to ${flow.destination}</b><br>Volume: ${flow.volume} MT`);
    });
}

// Process data for charts
function processChartData(data) {
    // Convert PHP data to proper format if needed
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            console.error('Error parsing chart data:', e);
            data = [];
        }
    }
    
    // Filter data based on selected filters
    const selectedBorder = document.getElementById('border-filter').value;
    const selectedCommodity = document.getElementById('commodity-filter').value;
    const selectedSourceCountry = document.getElementById('source-country-filter').value;
    const selectedDestinationCountry = document.getElementById('destination-country-filter').value;
    
    let filteredData = data;
    
    if (selectedBorder && selectedBorder !== 'all') {
        filteredData = filteredData.filter(item => item.border_name === selectedBorder);
    }
    
    if (selectedCommodity && selectedCommodity !== 'all') {
        filteredData = filteredData.filter(item => item.commodity_display === selectedCommodity);
    }
    
    if (selectedSourceCountry && selectedSourceCountry !== 'all') {
        filteredData = filteredData.filter(item => item.source_country === selectedSourceCountry);
    }
    
    if (selectedDestinationCountry && selectedDestinationCountry !== 'all') {
        filteredData = filteredData.filter(item => item.destination_country === selectedDestinationCountry);
    }
    
    // Group data by date (without time) for trend chart
    const dates = [...new Set(filteredData.map(item => {
        const date = new Date(item.date_posted);
        return date.toISOString().split('T')[0]; // Get just the date part
    }))].sort();
    
    // Prepare trend datasets
    const trendDatasets = [];
    
    // If a specific commodity is selected, show only that
    if (selectedCommodity && selectedCommodity !== 'all') {
        const volumes = dates.map(date => {
            const item = filteredData.find(d => 
                d.commodity_display === selectedCommodity && 
                new Date(d.date_posted).toISOString().split('T')[0] === date
            );
            return item ? parseFloat(item.volume) : null;
        });
        
        trendDatasets.push({
            label: selectedCommodity,
            data: volumes,
            borderColor: '#8B4513',
            backgroundColor: 'rgba(139, 69, 19, 0.5)',
            borderWidth: 2,
            fill: false,
            tension: 0.1
        });
    } else {
        // Group by border if no specific commodity is selected
        const borders = [...new Set(filteredData.map(item => item.border_name))];
        
        // Use a consistent color palette
        const colorPalette = [
            '#8B4513', '#1E88E5', '#FFC107', '#004D40', 
            '#D81B60', '#039BE5', '#7CB342', '#5E35B1'
        ];
        
        borders.forEach((border, index) => {
            const volumes = dates.map(date => {
                const item = filteredData.find(d => 
                    d.border_name === border && 
                    new Date(d.date_posted).toISOString().split('T')[0] === date
                );
                return item ? parseFloat(item.volume) : null;
            });
            
            trendDatasets.push({
                label: border,
                data: volumes,
                borderColor: colorPalette[index % colorPalette.length],
                backgroundColor: 'rgba(0, 0, 0, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.1
            });
        });
    }
    
    // Prepare commodity distribution data
    const commodityDistribution = {};
    filteredData.forEach(item => {
        const commodity = item.commodity_display;
        if (!commodityDistribution[commodity]) {
            commodityDistribution[commodity] = 0;
        }
        commodityDistribution[commodity] += parseFloat(item.volume);
    });
    
    const commodityLabels = Object.keys(commodityDistribution);
    const commodityVolumes = Object.values(commodityDistribution);
    
    return {
        dates,
        trendDatasets,
        commodityLabels,
        commodityVolumes
    };
}

// Process data for map visualization
function processMapData(data) {
    // In a real implementation, you would have coordinates for border points
    // This is just a simplified example
    
    // Get unique border points with total volumes
    const borderPoints = {};
    data.forEach(item => {
        if (!borderPoints[item.border_name]) {
            borderPoints[item.border_name] = {
                name: item.border_name,
                totalVolume: 0,
                // Mock coordinates - in real app you'd get these from your database
                lat: -1.2921 + (Math.random() * 4 - 2),
                lng: 36.8219 + (Math.random() * 4 - 2)
            };
        }
        borderPoints[item.border_name].totalVolume += parseFloat(item.volume);
    });
    
    // Get trade flows between countries
    const tradeFlows = {};
    data.forEach(item => {
        const flowKey = `${item.source_country}_${item.destination_country}`;
        if (!tradeFlows[flowKey]) {
            tradeFlows[flowKey] = {
                source: item.source_country,
                destination: item.destination_country,
                volume: 0,
                // Mock coordinates - in real app you'd get these from country centroids
                sourceCoords: [-1.2921 + (Math.random() * 4 - 2), 36.8219 + (Math.random() * 4 - 2)],
                destinationCoords: [-1.2921 + (Math.random() * 4 - 2), 36.8219 + (Math.random() * 4 - 2)]
            };
        }
        tradeFlows[flowKey].volume += parseFloat(item.volume);
    });
    
    return {
        borderPoints: Object.values(borderPoints),
        tradeFlows: Object.values(tradeFlows)
    };
}

// Chart options
function getTrendChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'XBT Volume Trends Over Time',
                font: {
                    size: 16
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.parsed.y.toFixed(2)} MT`;
                    }
                }
            },
            zoom: {
                zoom: {
                    wheel: {
                        enabled: true
                    },
                    pinch: {
                        enabled: true
                    },
                    mode: 'xy'
                },
                pan: {
                    enabled: true,
                    mode: 'xy'
                }
            },
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    padding: 20,
                    usePointStyle: true
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Date',
                    font: {
                        weight: 'bold'
                    }
                },
                grid: {
                    display: false
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Volume (MT)',
                    font: {
                        weight: 'bold'
                    }
                },
                beginAtZero: true
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    };
}

// Generate a color palette
function getColorPalette(count) {
    const palette = [
        '#8B4513', '#1E88E5', '#FFC107', '#004D40', 
        '#D81B60', '#039BE5', '#7CB342', '#5E35B1',
        '#E53935', '#8E24AA', '#3949AB', '#43A047',
        '#FB8C00', '#6D4C41', '#546E7A'
    ];
    return palette.slice(0, count);
}

// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    // Set up tab switching
    const tabs = document.querySelectorAll('.view-tab');
    const views = {
        'table': document.getElementById('table-view'),
        'chart': document.getElementById('chart-view'),
        'map': document.getElementById('map-view')
    };
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all views
            Object.values(views).forEach(v => {
                if (v) v.style.display = 'none';
            });
            
            // Show selected view
            if (views[view]) {
                views[view].style.display = 'block';
                
                // Initialize visualizations if view is selected
                if (view === 'chart') {
                    const chartData = <?= json_encode($volumes_data) ?>;
                    initCharts(chartData);
                } else if (view === 'map') {
                    const mapData = <?= json_encode($volumes_data) ?>;
                    initMap(mapData);
                }
            }
        });
    });

    // Chart type selector
    document.getElementById('chart-type-selector')?.addEventListener('change', function() {
        currentChartType = this.value;
        if (volumeTrendChart) {
            volumeTrendChart.config.type = currentChartType;
            volumeTrendChart.update();
        }
    });

    // Map view selector
    document.getElementById('map-view-selector')?.addEventListener('change', function() {
        const mapData = <?= json_encode($volumes_data) ?>;
        initMap(mapData);
    });

    // Filter change event listeners
    const filterElements = [
        'border-filter', 'commodity-filter', 'source-country-filter',
        'destination-country-filter', 'data-source-filter', 'date-from',
        'date-to', 'volume-min', 'volume-max'
    ];
    
    filterElements.forEach(id => {
        document.getElementById(id)?.addEventListener('change', function() {
            // Submit the filter form
            document.getElementById('filter-form').submit();
        });
    });
    
    // Reset filters button
    document.getElementById('reset-filters')?.addEventListener('click', function() {
        // Reset all filter values
        filterElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                if (element.tagName === 'SELECT') {
                    element.value = 'all';
                } else if (element.tagName === 'INPUT') {
                    element.value = '';
                }
            }
        });
        
        // Submit the form to apply reset
        document.getElementById('filter-form').submit();
    });

    // Initialize charts if on chart view by default (unlikely but possible)
    if (document.getElementById('chart-view')?.style.display === 'block') {
        const chartData = <?= json_encode($volumes_data) ?>;
        initCharts(chartData);
    }
    
    // Initialize map if on map view by default (unlikely but possible)
    if (document.getElementById('map-view')?.style.display === 'block') {
        const mapData = <?= json_encode($volumes_data) ?>;
        initMap(mapData);
    }
});
</script>
</body>
</html>