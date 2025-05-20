<?php
// api/markets/get_market.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../admin/includes/config.php'; // Adjust the path if needed

// Function to handle errors and send JSON response
function sendJsonResponse($status, $data = null, $message = null, $http_code = 200) {
    http_response_code($http_code);
    $response = array("status" => $status);
    if ($data !== null) {
        $response["data"] = $data;
    }
    if ($message !== null) {
        $response["message"] = $message;
    }
    echo json_encode($response);
    exit; // Stop execution after sending the response
}

// Check for required parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    sendJsonResponse("error", null, "Market ID is required", 400);
}

$market_id = $_GET['id'];

// Use a prepared statement to prevent SQL injection
$query = "SELECT 
            m.id, 
            m.market_name, 
            m.category, 
            m.type, 
            m.country, 
            m.county_district, 
            m.longitude, 
            m.latitude, 
            m.radius, 
            m.currency, 
            m.additional_datasource, 
            m.image_url,
            CONCAT('[', GROUP_CONCAT(
                CONCAT('{\"id\":', c.id, 
                       ',\"name\":\"', c.commodity_name, 
                       '\",\"variety\":\"', IFNULL(c.variety, ''), '\"}')
                SEPARATOR ','
            ), ']') AS commodities_json
          FROM markets m
          LEFT JOIN commodities c ON FIND_IN_SET(c.id, m.primary_commodity)
          WHERE m.id = ?
          GROUP BY m.id";

$stmt = $con->prepare($query);

if (!$stmt) {
    // Log the error (optional, for server-side logging)
    error_log("Database error: " . $con->error);
    sendJsonResponse("error", null, "Failed to prepare statement", 500); // Internal Server Error
}

$stmt->bind_param("i", $market_id); // "i" for integer
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendJsonResponse("error", null, "Market not found", 404);
}

$row = $result->fetch_assoc();

// Decode the JSON-like string into an array
$commodities = $row['commodities_json'] ? json_decode($row['commodities_json'], true) : [];

$market = array(
    "id" => $row['id'],
    "market_name" => $row['market_name'],
    "category" => $row['category'],
    "type" => $row['type'],
    "country" => $row['country'],
    "county_district" => $row['county_district'],
    "location" => array(
        "longitude" => $row['longitude'],
        "latitude" => $row['latitude'],
        "radius" => $row['radius']
    ),
    "currency" => $row['currency'],
    "commodities" => $commodities,
    "additional_datasource" => $row['additional_datasource'],
    "image_url" => $row['image_url']
);

sendJsonResponse("success", $market); // 200 OK is implied
?>
