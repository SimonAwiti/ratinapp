<?php
// api/xbt_volumes.php

// Include your database configuration
include '../admin/includes/config.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to get XBT volumes data
function getXBTVolumes($con, $countryFilter = '', $borderFilter = '', $commodityFilter = '') {
    // Build the main query to get all approved data
    $query = "
        SELECT 
            border_name,
            country,
            source,
            destination,
            SUM(volume) as total_volume
        FROM xbt_volumes 
        WHERE status = 'approved'
    ";
    
    $conditions = [];
    $params = [];
    $types = '';
    
    // Add filters to the query
    if (!empty($countryFilter)) {
        $conditions[] = "country = ?";
        $params[] = $countryFilter;
        $types .= 's';
    }
    
    if (!empty($borderFilter)) {
        $conditions[] = "border_name = ?";
        $params[] = $borderFilter;
        $types .= 's';
    }
    
    if (!empty($commodityFilter)) {
        $conditions[] = "commodity_name = ?";
        $params[] = $commodityFilter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " GROUP BY border_name, country, source, destination
               ORDER BY total_volume DESC";
    
    // Prepare and execute the query
    if (!empty($params)) {
        $stmt = $con->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $con->query($query);
    }
    
    if (!$result) {
        return ['error' => 'Failed to fetch XBT volumes: ' . $con->error];
    }
    
    $rawData = [];
    while ($row = $result->fetch_assoc()) {
        $rawData[] = $row;
    }
    
    if (!empty($params)) {
        $stmt->close();
    }
    
    // Process the data into the desired format
    $xbtData = [];
    $id = 1;
    
    foreach ($rawData as $item) {
        $border = $item['border_name'];
        $country = $item['country'];
        $source = ($item['source'] === '0' || empty($item['source'])) ? $country : $item['source'];
        $destination = ($item['destination'] === '0' || empty($item['destination'])) ? 'Unknown' : $item['destination'];
        $flowDirection = "{$source} - {$destination}";
        $totalVolume = floatval($item['total_volume']);
        
        // Format volume with commas
        $formattedVolume = number_format($totalVolume) . " MT";
        
        $xbtData[] = [
            'id' => $id++,
            'border_point' => $border,
            'country' => $country,
            'volume_quarter' => $formattedVolume,
            'flow_direction' => $flowDirection
        ];
    }
    
    return $xbtData;
}

// Main API logic
try {
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Get filter parameters from request
    $countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
    $borderFilter = isset($_GET['border_point']) ? $_GET['border_point'] : '';
    $commodityFilter = isset($_GET['commodity']) ? $_GET['commodity'] : '';
    
    // Get XBT volumes data
    $xbtVolumes = getXBTVolumes($con, $countryFilter, $borderFilter, $commodityFilter);
    
    if (isset($xbtVolumes['error'])) {
        http_response_code(500);
        echo json_encode([
            'error' => $xbtVolumes['error']
        ]);
    } else {
        // Return ONLY the data array in the exact format requested
        echo json_encode([
            'data' => $xbtVolumes
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>