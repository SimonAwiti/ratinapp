<?php
// market_prices_view.php
session_start();

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Include your database configuration file
include '../admin/includes/config.php';

// Function to geocode market name to coordinates using OpenStreetMap Nominatim API
function geocodeMarket($market, $country) {
    // Prepare the query - combine market and country for better results
    $query = urlencode("$market, $country");
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=$query&limit=1";
    
    // Create context with proper headers
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: RATIN Market Prices App/1.0\r\n",
            'timeout' => 10 // 10 second timeout
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === FALSE) {
            error_log("Geocoding API request failed for: $market, $country");
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return [
                'latitude' => (float)$data[0]['lat'],
                'longitude' => (float)$data[0]['lon']
            ];
        } else {
            error_log("No coordinates found for: $market, $country");
            return null;
        }
    } catch (Exception $e) {
        error_log("Geocoding error for $market, $country: " . $e->getMessage());
        return null;
    }
}

// Function to convert USD to local currency using date-matched exchange rates
function convertToLocal($usdAmount, $country, $date, $con) {
    if (!is_numeric($usdAmount)) {
        error_log("Invalid amount provided to convertToLocal: " . var_export($usdAmount, true));
        return 0;
    }

    $exchangeRate = 1; // Default to 1 (assuming 1:1 if no rate is found)

    // Check if $con is a valid mysqli object before preparing statement
    if ($con instanceof mysqli && !$con->connect_error) {
        // First try to find exact date match
        $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? AND effective_date = ? ORDER BY date_created DESC LIMIT 1");
        if ($stmt) {
            $dateOnly = date('Y-m-d', strtotime($date));
            $stmt->bind_param("ss", $country, $dateOnly);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $exchangeRate = (float)$row['exchange_rate'];
            } else {
                // If no exact date match, find the most recent rate before the price date
                $stmt->close();
                $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? AND effective_date <= ? ORDER BY effective_date DESC, date_created DESC LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("ss", $country, $dateOnly);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $exchangeRate = (float)$row['exchange_rate'];
                    } else {
                        // Fallback to latest available rate
                        $stmt->close();
                        $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? ORDER BY effective_date DESC, date_created DESC LIMIT 1");
                        if ($stmt) {
                            $stmt->bind_param("s", $country);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result && $result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $exchangeRate = (float)$row['exchange_rate'];
                            } else {
                                error_log("No exchange rate found in DB for " . $country . ". Using default rate: " . $exchangeRate);
                            }
                        }
                    }
                }
            }
            if ($stmt) $stmt->close();
        } else {
            error_log("Error preparing currency query for " . $country . ": " . $con->error);
        }
    } else {
        error_log("Database connection not valid in convertToLocal function. Skipping currency rate fetch.");
    }

    // Ensure exchangeRate is not zero to prevent multiplication by zero errors.
    if ($exchangeRate == 0) {
        error_log("Exchange rate for " . $country . " is zero or invalid. Returning 0 for conversion to prevent multiplication by zero.");
        return 0;
    }

    return round($usdAmount * $exchangeRate, 2);
}

// Function to get exchange rate for display (date-matched) - SAME AS BEFORE
function getExchangeRate($country, $date, $con) {
    $exchangeRate = 1; // Default to 1

    if ($con instanceof mysqli && !$con->connect_error) {
        // First try to find exact date match
        $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? AND effective_date = ? ORDER BY date_created DESC LIMIT 1");
        if ($stmt) {
            $dateOnly = date('Y-m-d', strtotime($date));
            $stmt->bind_param("ss", $country, $dateOnly);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $exchangeRate = (float)$row['exchange_rate'];
            } else {
                // If no exact date match, find the most recent rate before the price date
                $stmt->close();
                $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? AND effective_date <= ? ORDER BY effective_date DESC, date_created DESC LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("ss", $country, $dateOnly);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $exchangeRate = (float)$row['exchange_rate'];
                    } else {
                        // Fallback to latest available rate
                        $stmt->close();
                        $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? ORDER BY effective_date DESC, date_created DESC LIMIT 1");
                        if ($stmt) {
                            $stmt->bind_param("s", $country);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result && $result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $exchangeRate = (float)$row['exchange_rate'];
                            }
                        }
                    }
                }
            }
            if ($stmt) $stmt->close();
        }
    }

    return $exchangeRate;
}

// Function to get currency code for a country
function getCurrencyCode($country) {
    $currencies = [
        'Kenya' => 'KES',
        'Tanzania' => 'TSH',
        'Uganda' => 'UGX',
        'Rwanda' => 'RWF',
        'Ethiopia' => 'ETB'
    ];
    
    return $currencies[$country] ?? 'USD';
}

// Function to build the SQL query with filters
function buildPricesQuery($filters = []) {
    $sql = "SELECT
                p.id,
                p.market,
                p.commodity,
                c.commodity_name,
                c.variety,
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source,
                p.country_admin_0,
                p.unit
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id
            WHERE
                p.status IN ('published', 'approved')";
    
    // Apply filters
    if (!empty($filters['country'])) {
        $sql .= " AND p.country_admin_0 = '" . $filters['country'] . "'";
    }
    
    if (!empty($filters['market'])) {
        $sql .= " AND p.market = '" . $filters['market'] . "'";
    }
    
    if (!empty($filters['commodity'])) {
        $sql .= " AND p.commodity = " . (int)$filters['commodity'];
    }
    
    if (!empty($filters['price_type'])) {
        $sql .= " AND p.price_type = '" . $filters['price_type'] . "'";
    }
    
    if (!empty($filters['data_source'])) {
        $sql .= " AND p.data_source = '" . $filters['data_source'] . "'";
    }
    
    if (!empty($filters['commodity_category'])) {
        $sql .= " AND c.category = '" . $filters['commodity_category'] . "'";
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(p.date_posted) >= '" . $filters['date_from'] . "'";
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(p.date_posted) <= '" . $filters['date_to'] . "'";
    }
    
    if (!empty($filters['price_range'])) {
        // Handle price range filter (assuming format like "100-200")
        $priceRange = explode('-', $filters['price_range']);
        if (count($priceRange) == 2) {
            $minPrice = (float)$priceRange[0];
            $maxPrice = (float)$priceRange[1];
            $sql .= " AND p.Price BETWEEN $minPrice AND $maxPrice";
        }
    }
    
    $sql .= " ORDER BY p.date_posted DESC";
    
    return $sql;
}

// Function to fetch prices data from the database with filters
function getPricesData($con, $limit = 10, $offset = 0, $filters = []) {
    $sql = buildPricesQuery($filters);
    $sql .= " LIMIT $limit OFFSET $offset";
    
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

function getTotalPriceRecords($con, $filters = []) {
    $sql = buildPricesQuery($filters);
    $sql = "SELECT COUNT(*) as total FROM ($sql) as count_query";
    $result = $con->query($sql);
     if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
     }
     return 0;
}

// Get filter values from request
$filters = [
    'country' => isset($_GET['country']) ? $_GET['country'] : '',
    'market' => isset($_GET['market']) ? $_GET['market'] : '',
    'commodity' => isset($_GET['commodity']) ? $_GET['commodity'] : '',
    'price_type' => isset($_GET['price_type']) ? $_GET['price_type'] : '',
    'data_source' => isset($_GET['data_source']) ? $_GET['data_source'] : '',
    'commodity_category' => isset($_GET['commodity_category']) ? $_GET['commodity_category'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'price_range' => isset($_GET['price_range']) ? $_GET['price_range'] : ''
];

// Get total number of records with filters
$total_records = getTotalPriceRecords($con, $filters);

// Get pagination parameters from request
$limit_options = [10, 25, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $limit_options) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch prices data with filters
$prices_data = getPricesData($con, $limit, $offset, $filters);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Fetch prices data with filters
$prices_data = getPricesData($con, $limit, $offset, $filters);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Function to calculate price changes
function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $con) {
    if ($currentPrice === null || $currentPrice === '') return 0;

    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $sql = "SELECT Price FROM market_prices
            WHERE commodity = " . (int)$commodityId . "
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) = '$yesterday'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $yesterdayData = $result->fetch_assoc();
        $yesterdayPrice = $yesterdayData['Price'];
        if($yesterdayPrice != 0){
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2);
        }
        return 0;
    }
    return 0;
}

function calculateDoMChange($currentPrice, $commodityId, $market, $priceType, $con) {
    if ($currentPrice === null || $currentPrice === '') return 0;

    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));

    $sql = "SELECT AVG(Price) as avg_price FROM market_prices
            WHERE commodity = " . (int)$commodityId . "
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) BETWEEN '$firstDayOfLastMonth' AND '$lastDayOfLastMonth'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $monthData = $result->fetch_assoc();
        $averagePrice = $monthData['avg_price'];
        if($averagePrice != 0){
             $change = (($currentPrice - $averagePrice) / $averagePrice) * 100;
             return round($change, 2);
        }
        return 0;
    }
    return 0;
}

// Group data by market, commodity, date, and source
$grouped_data = [];
foreach ($prices_data as $price) {
    $date = date('Y-m-d', strtotime($price['date_posted']));
    $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'] . '_' . $price['data_source'];
    $grouped_data[$group_key][] = $price;
}

// Get filter options for dropdowns
$countries = [];
$markets = [];
$commodities = [];
$price_types = [];
$data_sources = [];

$options_query = "SELECT DISTINCT country_admin_0 FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row['country_admin_0'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT market FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $markets[] = $row['market'];
    }
    $result->free();
}

// Get commodities with varieties
$commodities = [];
$commodities_with_varieties = []; // For map and chart filters

$options_query = "SELECT id, commodity_name, variety FROM commodities WHERE variety IS NOT NULL AND variety != ''";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $commodity_display = $row['commodity_name'] . ($row['variety'] ? " ({$row['variety']})" : '');
        $commodities[$row['id']] = $commodity_display;
        $commodities_with_varieties[] = $commodity_display;
    }
    $result->free();
}

// Also get commodities without varieties
$options_query = "SELECT id, commodity_name FROM commodities WHERE variety IS NULL OR variety = ''";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $commodities[$row['id']] = $row['commodity_name'];
        if (!in_array($row['commodity_name'], $commodities_with_varieties)) {
            $commodities_with_varieties[] = $row['commodity_name'];
        }
    }
    $result->free();
}

$options_query = "SELECT DISTINCT price_type FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $price_types[] = $row['price_type'];
    }
    $result->free();
}

$options_query = "SELECT DISTINCT data_source FROM market_prices WHERE status IN ('published', 'approved')";
$result = $con->query($options_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data_sources[] = $row['data_source'];
    }
    $result->free();
}

// Get data for charts and maps (without pagination)
$chart_data = getPricesData($con, 1000, 0, $filters); // Increased limit for better chart data

// Prepare market coordinates for map
$marketCoordinates = [];
foreach ($markets as $market) {
    // Find the country for this market from the actual data
    $marketCountry = '';
    foreach ($prices_data as $price) {
        if ($price['market'] === $market) {
            $marketCountry = $price['country_admin_0'];
            break;
        }
    }
    if (empty($marketCountry)) {
        $marketCountry = 'Kenya'; // Default fallback
    }
    $coords = geocodeMarket($market, $marketCountry);
    $marketCoordinates[$market] = $coords;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATIN - Market Prices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 0px solid #ddd;
            padding: 15px;
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
            margin-bottom: 20px;
        }

        .sidebar .ratin-logo {
            max-width: 150px;
            height: auto;
        }

        .sidebar h6 {
            color: #6c757d;
            font-weight: bold;
            margin-top: 20px;
            padding-left: 10px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .sidebar .nav-link {
            color: #333;
            padding: 12px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 5px;
            transition: all 0.2s ease-in-out;
            font-size: 1.0em;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #f5d6c6;
            color: #8B4513;
        }

        /* Header */
        .header-container {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 20px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            z-index: 999;
            position: sticky;
            top: 0;
            margin-left: 250px;
            height: 64px;
        }

        .breadcrumb {
            margin: 0;
            font-size: 17px;
            color: #6c757d;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #8B4513;
            font-weight: bold;
        }

        .breadcrumb-item.active {
            color: #8B4513;
            font-weight: bold;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: " > ";
            color: #6c757d;
        }

        .user-display {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            color: #8B4513;
            position: relative;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.2s;
        }

        .user-display:hover {
            background-color: #f5f5f5;
        }

        .user-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-width: 150px;
            z-index: 1000;
            display: none;
        }

        .user-menu.show {
            display: block;
        }

        .user-menu-item {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #333;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .user-menu-item:hover {
            background-color: #f5f5f5;
        }

        .user-menu-item:last-child {
            border-bottom: none;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            flex-grow: 1;
            margin-top: 64px;
        }

        /* Container styles */
        .container {
            background: #fff;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        /* Toolbar styles */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
            gap: 10px;
        }
        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a {
            padding: 12px 20px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background-color: #eee;
            text-decoration: none;
            color: #333;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s ease;
        }
        .toolbar button:hover, .toolbar a:hover:not(.primary) {
            background-color: #e0e0e0;
        }
        .toolbar .primary {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .toolbar .primary:hover {
            background-color: rgba(160, 70, 40, 1);
        }

        /* Improved Table Container */
        .table-responsive-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0 15px;
            margin-bottom: 20px;
        }

        /* Wider Table with Better Column Sizes */
        table {
            width: 100%;
            min-width: 1500px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
        }

        /* Column Width Adjustments */
        table th:nth-child(1), table td:nth-child(1) { width: 50px; }
        table th:nth-child(2), table td:nth-child(2) { width: 180px; }
        table th:nth-child(3), table td:nth-child(3) { width: 150px; }
        table th:nth-child(4), table td:nth-child(4) { width: 200px; }
        table th:nth-child(5), table td:nth-child(5) { width: 120px; }
        table th:nth-child(6), table td:nth-child(6) { width: 120px; }
        table th:nth-child(7), table td:nth-child(7) { width: 100px; }
        table th:nth-child(8), table td:nth-child(8) { width: 180px; }
        table th:nth-child(9), table td:nth-child(9) { width: 180px; }
        table th:nth-child(10), table td:nth-child(10) { width: 180px; }
        table th:nth-child(11), table td:nth-child(11) { width: 150px; }
        table th:nth-child(12), table td:nth-child(12) { width: 150px; }
        table th:nth-child(13), table td:nth-child(13) { width: 150px; }
        table th:nth-child(14), table td:nth-child(14) { width: 180px; }

        /* Table Cell Styling */
        table th, table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Alternating row colors */
        table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        table tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        table tbody tr:hover {
            background-color: #f5f5f5;
        }

        /* Sticky Header with Shadow */
        table th {
            position: sticky;
            top: 0;
            background-color: #f1f1f1;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
            font-size: 13px;
        }

        /* Horizontal Scrollbar Styling */
        .table-responsive-container::-webkit-scrollbar {
            height: 10px;
        }
        .table-responsive-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-responsive-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .table-responsive-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Price Change Display */
        .change-positive {
            color: #059669;
            font-weight: bold;
        }
        .change-negative {
            color: #dc2626;
            font-weight: bold;
        }

        /* Checkbox styling */
        .checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            font-size: 14px;
            align-items: center;
            flex-wrap: wrap;
            border-top: 1px solid #eee;
            gap: 10px;
        }
        .pagination .pages {
            display: flex;
            gap: 5px;
        }
        .pagination .page {
            padding: 8px 12px;
            border-radius: 6px;
            background-color: #eee;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            transition: background-color 0.2s ease;
        }
        .pagination .current {
            background-color: #8B4513;
            color: white;
            font-weight: bold;
        }
        .pagination .page:hover:not(.current) {
            background-color: #ddd;
        }
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            background-color: white;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .pagination button:hover:not(:disabled) {
            background-color: #f5f5f5;
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
        }

        /* View tabs */
        .view-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
        }
        .view-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 24px;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            font-size: 14px;
            color: #666;
            background: none;
            border: none;
            cursor: pointer;
        }
        .view-tab.active {
            color: #8B4513;
            border-bottom-color: #8B4513;
        }

        /* Chart filters */
        .chart-filters {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .map-filters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .commodity-category-btn.active {
            background-color: #8B4513;
            color: white;
        }

        /* Map container */
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Map popup styling */
        .map-popup {
            font-family: Arial, sans-serif;
        }
        .map-popup h4 {
            margin: 0 0 8px 0;
            color: #8B4513;
        }
        .map-popup p {
            margin: 4px 0;
        }

        /* Map legend styling */
        .info {
            padding: 6px 8px;
            font: 14px/16px Arial, Helvetica, sans-serif;
            background: white;
            background: rgba(255,255,255,0.8);
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            border-radius: 5px;
        }

        .info h6 {
            margin: 0 0 5px;
            color: #777;
        }

        .legend {
            line-height: 18px;
            color: #555;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                margin-left: 0;
            }
            .header-container, .main-content {
                margin-left: 0;
            }
            .filter-section > div {
                grid-template-columns: 1fr !important;
            }
            .chart-filters, .map-filters {
                grid-template-columns: 1fr;
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
        </div><br>
        <br>
        <h6>Price Parity</h6>

        <div class="submenu" id="dataSubmenu" style="display: block;">
            <a href="#" class="nav-link active">
                <i class="fas fa-store-alt"></i> Market Prices
            </a>
            <a href="millerprices.php" class="nav-link">
                <i class="fas fa-industry"></i> Miller Prices
            </a>
            <a href="xbtvols.php" class="nav-link">
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
                        <a href="#"><i class="fa fa-home"></i></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Market Prices</li>
                </ol>
            </nav>
            <div class="user-display" id="user-display">
                <i class="fa fa-user-circle"></i> 
                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <div class="user-menu" id="user-menu">
                    <a href="#" class="user-menu-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="#" class="user-menu-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="logout.php" class="user-menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="main-content">
            <form id="filter-form" method="GET">
                <div class="filter-section">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Country/District</label>
                            <select name="country" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo isset($filters['country']) && $filters['country'] == $country ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Market</label>
                            <select name="market" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">Select Market</option>
                                <?php foreach ($markets as $market): ?>
                                    <option value="<?php echo htmlspecialchars($market); ?>" <?php echo isset($filters['market']) && $filters['market'] == $market ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($market); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Commodity</label>
                            <select name="commodity" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">Select Commodity</option>
                                <?php foreach ($commodities as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo isset($filters['commodity']) && $filters['commodity'] == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Price type</label>
                            <select name="price_type" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">All Types</option>
                                <?php foreach ($price_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo isset($filters['price_type']) && $filters['price_type'] == $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Data Source</label>
                            <select name="data_source" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <option value="">All Sources</option>
                                <?php foreach ($data_sources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source); ?>" <?php echo isset($filters['data_source']) && $filters['data_source'] == $source ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($source); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Date Range</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="date" name="date_from" value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <span style="color: #666;">to</span>
                                <input type="date" name="date_to" value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Market Prices</label>
                            <input type="text" name="price_range" value="<?php echo isset($filters['price_range']) ? $filters['price_range'] : ''; ?>" placeholder="Enter price range (e.g., 100-200)" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div style="display: flex; align-items: end; gap: 8px;">
                            <button type="submit" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-weight: 500; color: white; background: #8B4513; cursor: pointer;">
                                <i class="fa fa-filter"></i>
                                Apply filters
                            </button>
                            <button type="button" id="reset-filters" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-weight: 500; color: #374151; background: white; cursor: pointer;">
                                <i class="fa fa-refresh"></i>
                                Reset filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="container">
                <div style="border-bottom: 1px solid #eee;">
                    <nav class="view-tabs">
                        <button class="view-tab active" data-view="table">
                            <i class="fa fa-table"></i>
                            Table view
                        </button>
                        <button class="view-tab" data-view="chart">
                            <i class="fa fa-chart-bar"></i>
                            Chart view
                        </button>
                        <button class="view-tab" data-view="map">
                            <i class="fa fa-map"></i>
                            Map view
                        </button>
                    </nav>
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">

                    </div>
                    <button id="download-btn" style="padding: 8px 16px; border: 1px solid #d1d5db; color: #374151; font-size: 14px; font-weight: 500; border-radius: 6px; background: white; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        Download
                        <i class="fa fa-download"></i>
                    </button>
                </div>

                <div id="table-view" class="table-responsive-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" class="checkbox"></th>
                                <th>Markets</th>
                                <th>Country</th>
                                <th>Commodity</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Unit</th>
                                <th>Price (Local)</th>
                                <th>Price (USD)</th>
                                <th>Exchange Rate</th>
                                <th>Day Change(%)</th>
                                <th>Month Change(%)</th>
                                <th>Year Change(%)</th>
                                <th>Data Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($grouped_data)) {
                                foreach ($grouped_data as $group_key => $prices_in_group):
                                    $first_row = true;
                                    $group_price_ids = array_column($prices_in_group, 'id');
                                    $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));

                                    foreach($prices_in_group as $price):
                                        $dayChange = calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                                        $monthChange = calculateDoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                                        $yearChange = 20; // Hardcoded as per original design
                                        
                                        $dayChangeClass = $dayChange >= 0 ? 'change-positive' : 'change-negative';
                                        $monthChangeClass = $monthChange >= 0 ? 'change-positive' : 'change-negative';
                                        $yearChangeClass = $yearChange >= 0 ? 'change-positive' : 'change-negative';
                                        
                                        // CORRECTED: Get currency code and date-matched exchange rate
                                        $currency = getCurrencyCode($price['country_admin_0']);
                                        $exchangeRate = getExchangeRate($price['country_admin_0'], $price['date_posted'], $con);
                                        
                                        // The price in database is in USD, convert to local currency
                                        $usdPrice = $price['Price'];
                                        $localPrice = convertToLocal($usdPrice, $price['country_admin_0'], $price['date_posted'], $con);
                                        
                                        // Get commodity display name with variety
                                        $commodity_display = $price['commodity_name'];
                                        if (!empty($price['variety'])) {
                                            $commodity_display .= " ({$price['variety']})";
                                        }
                                    ?>
                                    <tr>
                                        <?php if ($first_row): ?>
                                            <td rowspan="<?php echo count($prices_in_group); ?>">
                                                <input type="checkbox" 
                                                    data-group-key="<?php echo $group_key; ?>"
                                                    data-price-ids="<?php echo $group_price_ids_json; ?>"
                                                    class="checkbox" />
                                            </td>
                                            <td rowspan="<?php echo count($prices_in_group); ?>" style="font-weight: 500;"><?php echo htmlspecialchars($price['market']); ?></td>
                                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['country_admin_0']); ?></td>
                                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($commodity_display); ?></td>
                                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo date('d/m/Y', strtotime($price['date_posted'])); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($price['price_type']); ?></td>
                                        <td><?php echo htmlspecialchars($price['unit']); ?></td>
                                        <td style="font-weight: 600;"><?php echo number_format($localPrice, 2); ?> <?php echo $currency; ?></td>
                                        <td style="font-weight: 600;">$<?php echo number_format($usdPrice, 2); ?></td>
                                        <td><?php echo number_format($exchangeRate, 2); ?> <?php echo $currency; ?>/USD</td>
                                        <td class="<?php echo $dayChangeClass; ?>"><?php echo $dayChange >= 0 ? '+' : ''; ?><?php echo $dayChange; ?>%</td>
                                        <td class="<?php echo $monthChangeClass; ?>"><?php echo $monthChange >= 0 ? '+' : ''; ?><?php echo $monthChange; ?>%</td>
                                        <td class="<?php echo $yearChangeClass; ?>"><?php echo $yearChange >= 0 ? '+' : ''; ?><?php echo $yearChange; ?>%</td>
                                        <?php if ($first_row): ?>
                                            <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['data_source']); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php
                                            $first_row = false;
                                        endforeach;
                                    endforeach;
                                } else {
                                    echo '<tr><td colspan="14" style="text-align: center; padding: 20px;">No market prices data found</td></tr>';
                                }
                                ?>
                            </tbody>
                    </table>
                </div>

                <div id="chart-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4>Market Price Trends</h4>
                            <p class="text-muted">Visual representation of price movements</p>
                        </div>
                        <div>
                            <select id="chart-type-selector" class="form-select" style="width: 200px; display: inline-block;">
                                <option value="line">Line Chart</option>
                                <option value="bar">Bar Chart</option>
                                <option value="combo">Combined View</option>
                            </select>
                            <button id="export-chart-btn" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="chart-filters">
                        <div>
                            <label for="chart-country-filter" class="form-label">Country</label>
                            <select id="chart-country-filter" class="form-select">
                                <option value="all">All Countries</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>"><?php echo htmlspecialchars($country); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="chart-market-filter" class="form-label">Market</label>
                            <select id="chart-market-filter" class="form-select">
                                <option value="all">All Markets</option>
                                <?php foreach ($markets as $market): ?>
                                    <option value="<?php echo htmlspecialchars($market); ?>"><?php echo htmlspecialchars($market); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="chart-commodity-filter" class="form-label">Commodity</label>
                            <select id="chart-commodity-filter" class="form-select">
                                <option value="all">All Commodities</option>
                                <?php foreach ($commodities_with_varieties as $commodity_display): ?>
                                    <option value="<?php echo htmlspecialchars($commodity_display); ?>"><?php echo htmlspecialchars($commodity_display); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="chart-container" style="position: relative; height:400px;">
                                <canvas id="price-trend-chart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Price Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div id="price-summary">
                                        <p>Select a data point to view details</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="map-view" style="display: none; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <h4>Market Locations</h4>
                            <p class="text-muted">Geographic distribution of market prices</p>
                        </div>
                        <div>
                            <button id="export-map-btn" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="map-filters">
                        <div>
                            <label for="map-commodity-filter" class="form-label">Commodity</label>
                            <select id="map-commodity-filter" class="form-select">
                                <option value="all">All Commodities</option>
                                <?php foreach ($commodities_with_varieties as $commodity_display): ?>
                                    <option value="<?php echo htmlspecialchars($commodity_display); ?>"><?php echo htmlspecialchars($commodity_display); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="map-country-filter" class="form-label">Country</label>
                            <select id="map-country-filter" class="form-select">
                                <option value="all">All Countries</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>"><?php echo htmlspecialchars($country); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="map-date-from" class="form-label">Date From</label>
                            <input type="date" id="map-date-from" class="form-control" value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>">
                        </div>
                        <div>
                            <label for="map-date-to" class="form-label">Date To</label>
                            <input type="date" id="map-date-to" class="form-control" value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div id="map"></div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Market Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div id="market-stats">
                                        <p>Click on a market marker to view statistics</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Price Legend</h5>
                                </div>
                                <div class="card-body">
                                    <div id="price-legend">
                                        <div class="d-flex align-items-center mb-2">
                                            <div style="width: 20px; height: 20px; background-color: #ff4444; border-radius: 50%; margin-right: 10px;"></div>
                                            <span>High Prices</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <div style="width: 20px; height: 20px; background-color: #ffaa00; border-radius: 50%; margin-right: 10px;"></div>
                                            <span>Medium Prices</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div style="width: 20px; height: 20px; background-color: #44ff44; border-radius: 50%; margin-right: 10px;"></div>
                                            <span>Low Prices</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($total_records > 0): ?>
                <div class="pagination">
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-700">Rows per page:</span>
                            <select id="rows-per-page" style="padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px;">
                                <?php foreach ($limit_options as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo $limit == $option ? 'selected' : ''; ?>>
                                        <?php echo $option; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            onclick="window.location.href='?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1, 'limit' => $limit])); ?>'"
                            <?php echo $page <= 1 ? 'disabled' : ''; ?>
                            style="<?php echo $page <= 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                        >
                            Previous
                        </button>
                        
                        <?php
                        $visiblePages = 5;
                        $startPage = max(1, $page - floor($visiblePages / 2));
                        $endPage = min($total_pages, $startPage + $visiblePages - 1);
                        
                        if ($startPage > 1) {
                            echo '<button onclick="window.location.href=\'?' . http_build_query(array_merge($filters, ['page' => 1, 'limit' => $limit])) . '\'" class="page">1</button>';
                            if ($startPage > 2) {
                                echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = $i == $page ? 'current' : '';
                            echo '<button onclick="window.location.href=\'?' . http_build_query(array_merge($filters, ['page' => $i, 'limit' => $limit])) . '\'" class="page '.$activeClass.'">'.$i.'</button>';
                        }
                        
                        if ($endPage < $total_pages) {
                            if ($endPage < $total_pages - 1) {
                                echo '<span class="px-3 py-1 text-sm text-gray-700">...</span>';
                            }
                            echo '<button onclick="window.location.href=\'?' . http_build_query(array_merge($filters, ['page' => $total_pages, 'limit' => $limit])) . '\'" class="page">'.$total_pages.'</button>';
                        }
                        ?>
                        
                        <button
                            onclick="window.location.href='?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1, 'limit' => $limit])); ?>'"
                            <?php echo $page >= $total_pages ? 'disabled' : ''; ?>
                            style="<?php echo $page >= $total_pages ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                        >
                            Next
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
// Add user menu toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const userDisplay = document.getElementById('user-display');
    const userMenu = document.getElementById('user-menu');
    
    if (userDisplay && userMenu) {
        userDisplay.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('show');
        });
        
        // Close menu when clicking elsewhere
        document.addEventListener('click', function() {
            userMenu.classList.remove('show');
        });
    }
    
    // Your existing JavaScript code remains the same
    // ... (all the existing JavaScript code) ...
});

// Initialize charts
let priceTrendChart;
let currentChartType = 'line';
let map;
window.mapMarkers = [];

// Function to initialize or update charts
function initCharts(data) {
    // Process data for charts
    const processedData = processChartData(data);
    
    // Destroy existing chart if it exists
    if (priceTrendChart) priceTrendChart.destroy();
    
    // Create Price Trend Chart
    const priceTrendCtx = document.getElementById('price-trend-chart').getContext('2d');
    priceTrendChart = new Chart(priceTrendCtx, {
        type: currentChartType,
        data: {
            labels: processedData.dates,
            datasets: processedData.trendDatasets
        },
        options: getTrendChartOptions()
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
    const selectedCountry = document.getElementById('chart-country-filter').value;
    const selectedMarket = document.getElementById('chart-market-filter').value;
    const selectedCommodity = document.getElementById('chart-commodity-filter').value;
    
    let filteredData = data;
    
    if (selectedCountry && selectedCountry !== 'all') {
        filteredData = filteredData.filter(item => item.country_admin_0 === selectedCountry);
    }
    
    if (selectedMarket && selectedMarket !== 'all') {
        filteredData = filteredData.filter(item => item.market === selectedMarket);
    }
    
    if (selectedCommodity && selectedCommodity !== 'all') {
        filteredData = filteredData.filter(item => {
            // Handle both commodity name and variety
            const fullCommodityName = item.commodity_name + (item.variety ? ` (${item.variety})` : '');
            return fullCommodityName === selectedCommodity || item.commodity_name === selectedCommodity;
        });
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
        const prices = dates.map(date => {
            const item = filteredData.find(d => {
                const fullCommodityName = d.commodity_name + (d.variety ? ` (${d.variety})` : '');
                return (fullCommodityName === selectedCommodity || d.commodity_name === selectedCommodity) && 
                       new Date(d.date_posted).toISOString().split('T')[0] === date;
            });
            return item ? parseFloat(item.Price) : null;
        });
        
        trendDatasets.push({
            label: selectedCommodity,
            data: prices,
            borderColor: '#8B4513', // Use theme color
            backgroundColor: 'rgba(139, 69, 19, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.1
        });
    } else {
        // Group by commodity if no specific one is selected
        const commodities = [...new Set(filteredData.map(item => {
            return item.commodity_name + (item.variety ? ` (${item.variety})` : '');
        }))];
        
        commodities.forEach(commodity => {
            const prices = dates.map(date => {
                const item = filteredData.find(d => {
                    const fullCommodityName = d.commodity_name + (d.variety ? ` (${d.variety})` : '');
                    return fullCommodityName === commodity && 
                           new Date(d.date_posted).toISOString().split('T')[0] === date;
                });
                return item ? parseFloat(item.Price) : null;
            });
            
            trendDatasets.push({
                label: commodity,
                data: prices,
                borderColor: getRandomColor(),
                backgroundColor: 'rgba(0, 0, 0, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.1
            });
        });
    }
    
    return {
        dates,
        trendDatasets
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
                text: 'Price Trends Over Time'
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.parsed.y.toFixed(2)}`;
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
                onClick: (e, legendItem, legend) => {
                    const index = legendItem.datasetIndex;
                    const ci = legend.chart;
                    const meta = ci.getDatasetMeta(index);
                    
                    meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                    ci.update();
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Date'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Price (USD)'
                }
            }
        },
        onClick: (e) => {
            const points = priceTrendChart.getElementsAtEventForMode(
                e, 'nearest', { intersect: true }, true
            );
            
            if (points.length) {
                const firstPoint = points[0];
                const dataset = priceTrendChart.data.datasets[firstPoint.datasetIndex];
                const value = dataset.data[firstPoint.index];
                const date = priceTrendChart.data.labels[firstPoint.index];
                
                updatePriceSummary(dataset.label, date, value);
            }
        }
    };
}

// Initialize map with geocoded coordinates
function initMap(data) {
    // Destroy existing map if it exists
    if (map) {
        map.remove();
    }
    
    // Create map centered on East Africa
    map = L.map('map').setView([1.0, 35.0], 6);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Filter data based on selected filters
    const selectedCommodity = document.getElementById('map-commodity-filter').value;
    const selectedCountry = document.getElementById('map-country-filter').value;
    const dateFrom = document.getElementById('map-date-from').value;
    const dateTo = document.getElementById('map-date-to').value;
    
    let filteredData = data;
    
    // Apply commodity filter if not "all"
    if (selectedCommodity && selectedCommodity !== 'all') {
        filteredData = filteredData.filter(item => {
            // Handle both commodity name and variety
            const fullCommodityName = item.commodity_name + (item.variety ? ` (${item.variety})` : '');
            return fullCommodityName === selectedCommodity || item.commodity_name === selectedCommodity;
        });
    }
    
    // Apply country filter if not "all"
    if (selectedCountry && selectedCountry !== 'all') {
        filteredData = filteredData.filter(item => item.country_admin_0 === selectedCountry);
    }
    
    // Apply date filters
    if (dateFrom) {
        filteredData = filteredData.filter(item => new Date(item.date_posted) >= new Date(dateFrom));
    }
    
    if (dateTo) {
        const toDate = new Date(dateTo);
        toDate.setHours(23, 59, 59, 999); // End of the day
        filteredData = filteredData.filter(item => new Date(item.date_posted) <= toDate);
    }
    
    // Group data by market and calculate average price
    const marketData = {};
    filteredData.forEach(item => {
        if (!marketData[item.market]) {
            marketData[item.market] = {
                prices: [],
                commodities: new Set(),
                country: item.country_admin_0,
                data_source: item.data_source
            };
        }
        marketData[item.market].prices.push(parseFloat(item.Price));
        const commodityDisplay = item.commodity_name + (item.variety ? ` (${item.variety})` : '');
        marketData[item.market].commodities.add(commodityDisplay);
    });
    
    // Calculate price ranges for color coding
    const allPrices = filteredData.map(item => parseFloat(item.Price));
    const minPrice = allPrices.length > 0 ? Math.min(...allPrices) : 0;
    const maxPrice = allPrices.length > 0 ? Math.max(...allPrices) : 0;
    const priceRange = maxPrice - minPrice;
    
    // Get coordinates from PHP (already geocoded)
    const marketCoordinates = <?php echo json_encode($marketCoordinates); ?>;
    
    // Clear existing markers if any
    if (window.mapMarkers) {
        window.mapMarkers.forEach(marker => map.removeLayer(marker));
    }
    window.mapMarkers = [];
    
    // Add markers for each market
    Object.keys(marketData).forEach(market => {
        const data = marketData[market];
        const avgPrice = data.prices.length > 0 ? data.prices.reduce((a, b) => a + b, 0) / data.prices.length : 0;
        
        // Determine marker color based on price
        let markerColor;
        const priceRatio = priceRange > 0 ? (avgPrice - minPrice) / priceRange : 0.5;
        if (priceRatio > 0.7) {
            markerColor = '#ff4444'; // High price
        } else if (priceRatio > 0.3) {
            markerColor = '#ffaa00'; // Medium price
        } else {
            markerColor = '#44ff44'; // Low price
        }
        
        // Get coordinates for this market
        let lat, lng;
        if (marketCoordinates[market] && marketCoordinates[market].latitude && marketCoordinates[market].longitude) {
            lat = marketCoordinates[market].latitude;
            lng = marketCoordinates[market].longitude;
        } else {
            // Fallback to enhanced country coordinates
            const fallbackCoords = getEnhancedDefaultLatLng(data.country, market);
            lat = fallbackCoords[0];
            lng = fallbackCoords[1];
        }
        
        // Create custom marker icon with better styling
        const markerIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
        
        // Create marker
        const marker = L.marker([lat, lng], {icon: markerIcon}).addTo(map);
        window.mapMarkers.push(marker);
        
        // Create popup content with coordinate information
        const popupContent = `
            <div class="map-popup">
                <h4>${market}</h4>
                <p><strong>Country:</strong> ${data.country}</p>
                <p><strong>Average Price:</strong> $${avgPrice.toFixed(2)}</p>
                <p><strong>Commodities:</strong> ${Array.from(data.commodities).join(', ')}</p>
                <p><strong>Data Source:</strong> ${data.data_source}</p>
                <p><strong>Coordinates:</strong> ${lat.toFixed(4)}, ${lng.toFixed(4)}</p>
                <p style="font-size: 11px; color: #666; margin-top: 8px;">
                    <i class="fas fa-info-circle"></i> Coordinates from OpenStreetMap
                </p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        
        // Add click event to update market stats
        marker.on('click', function() {
            updateMarketStats(market, data, lat, lng);
        });
    });
    
    // Add a legend for the price colors
    addPriceLegend(minPrice, maxPrice);
    
    // Update market stats with initial message if no data
    if (Object.keys(marketData).length === 0) {
        document.getElementById('market-stats').innerHTML = '<p>No data available for selected filters</p>';
    }
}

// Enhanced helper function with better city coordinates
function getEnhancedDefaultLatLng(country, market = '') {
    const enhancedCountryCoords = {
        'Kenya': {
            'Nairobi': [-1.286389, 36.817223],
            'Mombasa': [-4.0435, 39.6682],
            'Kisumu': [-0.1022, 34.7617],
            'Nakuru': [-0.3031, 36.0800],
            'Eldoret': [0.5143, 35.2698],
            'Thika': [-1.0333, 37.0833],
            'default': [-1.286389, 36.817223]
        },
        'Tanzania': {
            'Dar es Salaam': [-6.8235, 39.2695],
            'Mwanza': [-2.5164, 32.9176],
            'Arusha': [-3.3869, 36.6820],
            'Dodoma': [-6.1630, 35.7516],
            'Mbeya': [-8.9000, 33.4500],
            'default': [-6.3690, 34.8888]
        },
        'Uganda': {
            'Kampala': [0.3476, 32.5825],
            'Jinja': [0.4244, 33.2041],
            'Mbale': [1.0644, 34.1794],
            'Gulu': [2.7746, 32.2980],
            'Lira': [2.2350, 32.9097],
            'default': [1.3733, 32.2903]
        },
        'Rwanda': {
            'Kigali': [-1.9441, 30.0619],
            'Butare': [-2.5967, 29.7439],
            'Gisenyi': [-1.7028, 29.2569],
            'default': [-1.9403, 29.8739]
        },
        'Ethiopia': {
            'Addis Ababa': [9.0227, 38.7468],
            'Dire Dawa': [9.5892, 41.8662],
            'Mekele': [13.4963, 39.4752],
            'Bahir Dar': [11.5742, 37.3614],
            'Awasa': [7.0500, 38.4667],
            'default': [9.1450, 40.4897]
        }
    };
    
    // If we have specific city coordinates, use them
    const countryData = enhancedCountryCoords[country];
    if (countryData) {
        // Try to match market name with known cities
        if (market && countryData[market]) {
            return countryData[market];
        }
        
        // Try partial matching for market names containing city names
        if (market) {
            for (const [city, coords] of Object.entries(countryData)) {
                if (city !== 'default' && market.toLowerCase().includes(city.toLowerCase())) {
                    return coords;
                }
            }
        }
        
        return countryData['default'];
    }
    
    return [0, 35]; // Default fallback
}

// Add price legend to the map
function addPriceLegend(minPrice, maxPrice) {
    const legend = L.control({ position: 'bottomright' });
    
    legend.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'info legend');
        div.style.backgroundColor = 'white';
        div.style.padding = '10px';
        div.style.borderRadius = '5px';
        div.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
        
        const priceRange = maxPrice - minPrice;
        const grades = [
            { color: '#44ff44', range: 'Low' },
            { color: '#ffaa00', range: 'Medium' },
            { color: '#ff4444', range: 'High' }
        ];
        
        let legendHTML = '<h6 style="margin: 0 0 8px 0;">Price Levels</h6>';
        grades.forEach(grade => {
            legendHTML += `
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <div style="width: 15px; height: 15px; background-color: ${grade.color}; border-radius: 50%; border: 2px solid white; margin-right: 8px;"></div>
                    <span>${grade.range} Prices</span>
                </div>
            `;
        });
        
        legendHTML += `<div style="margin-top: 8px; font-size: 11px; color: #666;">
            Range: $${minPrice.toFixed(2)} - $${maxPrice.toFixed(2)}
        </div>`;
        
        div.innerHTML = legendHTML;
        return div;
    };
    
    legend.addTo(map);
}

// Update market statistics to include coordinates
function updateMarketStats(market, data, lat, lng) {
    const statsDiv = document.getElementById('market-stats');
    const avgPrice = data.prices.reduce((a, b) => a + b, 0) / data.prices.length;
    const minPrice = Math.min(...data.prices);
    const maxPrice = Math.max(...data.prices);
    
    statsDiv.innerHTML = `
        <h6>${market}</h6>
        <p><strong>Country:</strong> ${data.country}</p>
        <p><strong>Coordinates:</strong> ${lat.toFixed(4)}, ${lng.toFixed(4)}</p>
        <p><strong>Average Price:</strong> $${avgPrice.toFixed(2)}</p>
        <p><strong>Price Range:</strong> $${minPrice.toFixed(2)} - $${maxPrice.toFixed(2)}</p>
        <p><strong>Commodities:</strong> ${Array.from(data.commodities).join(', ')}</p>
        <p><strong>Data Points:</strong> ${data.prices.length}</p>
        <p><strong>Data Source:</strong> ${data.data_source}</p>
    `;
}

// Helper function to generate random colors
function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}

// Update price summary
function updatePriceSummary(commodity, date, price) {
    const summaryDiv = document.getElementById('price-summary');
    const selectedMarket = document.getElementById('chart-market-filter').value;
    const selectedCountry = document.getElementById('chart-country-filter').value;
    
    summaryDiv.innerHTML = `
        <h6>${commodity}</h6>
        <p><strong>Date:</strong> ${new Date(date).toLocaleDateString()}</p>
        <p><strong>Price:</strong> $${price.toFixed(2)}</p>
        <p><strong>Market:</strong> ${selectedMarket !== 'all' ? selectedMarket : 'All Markets'}</p>
        <p><strong>Country:</strong> ${selectedCountry !== 'all' ? selectedCountry : 'All Countries'}</p>
    `;
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
            Object.values(views).forEach(v => v.style.display = 'none');
            
            // Show selected view
            if (views[view]) {
                views[view].style.display = 'block';
                
                // Initialize charts if chart view is selected
                if (view === 'chart') {
                    const chartData = <?php echo json_encode($chart_data); ?>;
                    initCharts(chartData);
                }
                
                // Initialize map if map view is selected
                if (view === 'map') {
                    const mapData = <?php echo json_encode($chart_data); ?>;
                    initMap(mapData);
                }
            }
        });
    });

    // Chart type selector
    document.getElementById('chart-type-selector')?.addEventListener('change', function() {
        currentChartType = this.value;
        if (priceTrendChart) {
            priceTrendChart.config.type = currentChartType;
            priceTrendChart.update();
        }
    });

    // Export chart button
    document.getElementById('export-chart-btn')?.addEventListener('click', function() {
        if (priceTrendChart) {
            const link = document.createElement('a');
            link.download = 'market-prices-chart.png';
            link.href = priceTrendChart.toBase64Image();
            link.click();
        }
    });

    // Export map button
    document.getElementById('export-map-btn')?.addEventListener('click', function() {
        if (map) {
            html2canvas(document.querySelector('#map')).then(canvas => {
                const link = document.createElement('a');
                link.download = 'market-map.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        }
    });

    // Filter change event listeners for charts
    document.getElementById('chart-country-filter')?.addEventListener('change', updateCharts);
    document.getElementById('chart-market-filter')?.addEventListener('change', updateCharts);
    document.getElementById('chart-commodity-filter')?.addEventListener('change', updateCharts);

    // Filter change event listeners for map
    document.getElementById('map-commodity-filter')?.addEventListener('change', updateMap);
    document.getElementById('map-country-filter')?.addEventListener('change', updateMap);
    document.getElementById('map-date-from')?.addEventListener('change', updateMap);
    document.getElementById('map-date-to')?.addEventListener('change', updateMap);

    function updateCharts() {
        const chartData = <?php echo json_encode($chart_data); ?>;
        initCharts(chartData);
    }

    function updateMap() {
        const mapData = <?php echo json_encode($chart_data); ?>;
        initMap(mapData);
    }

    // Reset filters button
    document.getElementById('reset-filters')?.addEventListener('click', function() {
        // Clear all form inputs
        const form = document.getElementById('filter-form');
        const inputs = form.querySelectorAll('select, input');
        inputs.forEach(input => {
            if (input.type !== 'submit' && input.type !== 'button') {
                input.value = '';
            }
        });
        
        // Submit the form
        form.submit();
    });


  // Rows per page selector
    document.getElementById('rows-per-page')?.addEventListener('change', function() {
        const limit = this.value;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('limit', limit);
        currentUrl.searchParams.set('page', 1); // Reset to first page when changing limit
        window.location.href = currentUrl.toString();
    });  
    
    // Download button functionality
    document.getElementById('download-btn')?.addEventListener('click', function() {
        // Create a form to submit download request
        const downloadForm = document.createElement('form');
        downloadForm.method = 'POST';
        downloadForm.action = 'download_market_prices.php';
        downloadForm.target = '_blank';
        
        // Add all current filters as hidden inputs
        const filterForm = document.getElementById('filter-form');
        const inputs = filterForm.querySelectorAll('select, input');
        inputs.forEach(input => {
            if (input.name && input.value) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = input.name;
                hiddenInput.value = input.value;
                downloadForm.appendChild(hiddenInput);
            }
        });
        
        // Add page information
        const pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        pageInput.value = '<?php echo $page; ?>';
        downloadForm.appendChild(pageInput);
        
        // Add to document and submit
        document.body.appendChild(downloadForm);
        downloadForm.submit();
        document.body.removeChild(downloadForm);
    });
});
</script>
</body>
</html>