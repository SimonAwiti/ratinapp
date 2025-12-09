<?php
// bulk_export_enumerators.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Function to fetch ALL enumerator data
function getAllEnumeratorsData($con) {
    $query = "
        SELECT
            id,
            name,
            email,
            phone,
            gender,
            country,
            county_district,
            username,
            tradepoints,
            latitude,
            longitude,
            token,
            created_at
        FROM enumerators
        ORDER BY name ASC
    ";

    $result = $con->query($query);
    $data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to parse tradepoints JSON and get readable format
function parseTradepointsForExport($tradepoints_json) {
    if (empty($tradepoints_json) || $tradepoints_json === '[]') {
        return 'No tradepoints assigned';
    }
    
    $tradepoints_array = json_decode($tradepoints_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($tradepoints_array)) {
        return 'Invalid JSON format';
    }
    
    $parsed = [];
    foreach ($tradepoints_array as $tp) {
        if (isset($tp['type']) && isset($tp['id'])) {
            $parsed[] = $tp['type'] . ':' . $tp['id'];
        }
    }
    
    return empty($parsed) ? 'No tradepoints assigned' : implode(', ', $parsed);
}

// Function to get basic statistics
function getEnumeratorStats($enumerators_data) {
    $total = count($enumerators_data);
    $assigned = 0;
    $unassigned = 0;
    $gender_stats = ['Male' => 0, 'Female' => 0, 'Other' => 0];
    
    foreach ($enumerators_data as $enumerator) {
        $tradepoints = parseTradepointsForExport($enumerator['tradepoints']);
        if ($tradepoints !== 'No tradepoints assigned') {
            $assigned++;
        } else {
            $unassigned++;
        }
        
        $gender = ucfirst(strtolower($enumerator['gender']));
        if (isset($gender_stats[$gender])) {
            $gender_stats[$gender]++;
        } else {
            $gender_stats['Other']++;
        }
    }
    
    return [
        'total' => $total,
        'assigned' => $assigned,
        'unassigned' => $unassigned,
        'gender_stats' => $gender_stats
    ];
}

// Get all data
$all_enumerators_data = getAllEnumeratorsData($con);
$stats = getEnumeratorStats($all_enumerators_data);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="enumerators_all_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Start with statistics section
fputcsv($output, ['--- ENUMERATORS STATISTICS ---']);
fputcsv($output, ['Total Enumerators:', $stats['total']]);
fputcsv($output, ['Assigned Enumerators:', $stats['assigned']]);
fputcsv($output, ['Unassigned Enumerators:', $stats['unassigned']]);
fputcsv($output, ['Male:', $stats['gender_stats']['Male']]);
fputcsv($output, ['Female:', $stats['gender_stats']['Female']]);
fputcsv($output, ['Other:', $stats['gender_stats']['Other']]);
fputcsv($output, ['Assigned Percentage:', ($stats['total'] > 0) ? round(($stats['assigned'] / $stats['total']) * 100, 2) . '%' : '0%']);

// Add separator
fputcsv($output, []);
fputcsv($output, []);

// Add data section
fputcsv($output, ['--- ENUMERATORS DATA (' . $stats['total'] . ' records) ---']);
fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Gender', 'Country', 'County/District', 'Username', 'Tradepoints (Readable)', 'Tradepoints (JSON)', 'Latitude', 'Longitude', 'Token', 'Created At']);

foreach ($all_enumerators_data as $enumerator) {
    $readable_tradepoints = parseTradepointsForExport($enumerator['tradepoints']);
    
    fputcsv($output, [
        $enumerator['id'],
        $enumerator['name'],
        $enumerator['email'],
        $enumerator['phone'],
        $enumerator['gender'],
        $enumerator['country'],
        $enumerator['county_district'],
        $enumerator['username'],
        $readable_tradepoints,
        $enumerator['tradepoints'],
        $enumerator['latitude'],
        $enumerator['longitude'],
        $enumerator['token'],
        $enumerator['created_at']
    ]);
}

// Add separator
fputcsv($output, []);
fputcsv($output, []);

// Add summary
fputcsv($output, ['--- EXPORT SUMMARY ---']);
fputcsv($output, ['Total Records Exported:', $stats['total']]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Type:', 'BULK EXPORT (ALL RECORDS)']);
fputcsv($output, ['Note:', 'Tradepoints format: "Type:ID" pairs (e.g., "Market:1,Border Point:2")']);

fclose($output);
exit();