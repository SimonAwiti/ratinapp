<?php
// bulk_export_tradepoints.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Function to fetch ALL Markets data
function getAllMarketsData($con) {
    $query = "SELECT * FROM markets ORDER BY market_name ASC";
    $result = $con->query($query);
    $data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to fetch ALL Border Points data
function getAllBorderPointsData($con) {
    $query = "SELECT * FROM border_points ORDER BY name ASC";
    $result = $con->query($query);
    $data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to fetch ALL Millers data
function getAllMillersData($con) {
    $query = "SELECT * FROM miller_details ORDER BY miller_name ASC";
    $result = $con->query($query);
    $data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Get all data
$markets_data = getAllMarketsData($con);
$border_points_data = getAllBorderPointsData($con);
$millers_data = getAllMillersData($con);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tradepoints_all_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Start with Markets
fputcsv($output, ['--- MARKETS DATA (' . count($markets_data) . ' records) ---']);
fputcsv($output, ['ID', 'Market Name', 'Category', 'Type', 'Country', 'County/District', 'Longitude', 'Latitude', 'Radius (km)', 'Currency', 'Primary Commodities', 'Additional Datasource', 'Image URLs', 'Created At']);

foreach ($markets_data as $market) {
    fputcsv($output, [
        $market['id'],
        $market['market_name'],
        $market['category'],
        $market['type'],
        $market['country'],
        $market['county_district'],
        $market['longitude'],
        $market['latitude'],
        $market['radius'],
        $market['currency'],
        $market['primary_commodity'],
        $market['additional_datasource'],
        $market['image_urls'],
        $market['created_at']
    ]);
}

// Add separator
fputcsv($output, []);
fputcsv($output, []);

// Add Border Points
fputcsv($output, ['--- BORDER POINTS DATA (' . count($border_points_data) . ' records) ---']);
fputcsv($output, ['ID', 'Name', 'Country', 'County', 'Longitude', 'Latitude', 'Created At']);

foreach ($border_points_data as $border) {
    fputcsv($output, [
        $border['id'],
        $border['name'],
        $border['country'],
        $border['county'],
        $border['longitude'],
        $border['latitude'],
        $border['created_at']
    ]);
}

// Add separator
fputcsv($output, []);
fputcsv($output, []);

// Add Millers
fputcsv($output, ['--- MILLERS DATA (' . count($millers_data) . ' records) ---']);
fputcsv($output, ['ID', 'Miller Name', 'Country', 'County/District', 'Millers (JSON)', 'Currency', 'Created At']);

foreach ($millers_data as $miller) {
    fputcsv($output, [
        $miller['id'],
        $miller['miller_name'],
        $miller['country'],
        $miller['county_district'],
        $miller['miller'],
        $miller['currency'],
        $miller['created_at']
    ]);
}

// Add summary
fputcsv($output, []);
fputcsv($output, ['--- EXPORT SUMMARY ---']);
fputcsv($output, ['Total Markets:', count($markets_data)]);
fputcsv($output, ['Total Border Points:', count($border_points_data)]);
fputcsv($output, ['Total Millers:', count($millers_data)]);
fputcsv($output, ['Total All Tradepoints:', count($markets_data) + count($border_points_data) + count($millers_data)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Type:', 'BULK EXPORT (ALL RECORDS)']);

fclose($output);
exit();