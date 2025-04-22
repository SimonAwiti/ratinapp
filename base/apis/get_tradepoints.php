<?php
header('Content-Type: application/json');
include '../../admin/includes/config.php';

$response = [
    'markets' => [],
    'border_points' => [],
    'millers' => []
];

// Get markets
$market_query = "SELECT 
                    id, 
                    market_name AS name, 
                    category,
                    type,
                    country AS admin0, 
                    county_district AS admin1,
                    longitude,
                    latitude,
                    radius,
                    currency,
                    primary_commodity,
                    additional_datasource,
                    image_url,
                    tradepoint
                FROM markets
                ORDER BY market_name ASC";

$result = $con->query($market_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $response['markets'][] = $row;
    }
}

// Get border points
$border_query = "SELECT 
                    id, 
                    name, 
                    country AS admin0, 
                    county AS admin1,
                    longitude,
                    latitude,
                    radius,
                    tradepoint,
                    images
                FROM border_points
                ORDER BY name ASC";

$result = $con->query($border_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $response['border_points'][] = $row;
    }
}

// Get millers
$miller_query = "SELECT 
                    id, 
                    miller_name AS name, 
                    miller, 
                    country AS admin0, 
                    county_district AS admin1,
                    currency
                FROM miller_details
                ORDER BY miller_name ASC";

$result = $con->query($miller_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $response['millers'][] = $row;
    }
}

// Output as JSON
echo json_encode([
    'status' => 'success',
    'data' => $response
]);
?>
