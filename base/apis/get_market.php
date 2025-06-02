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

// First, let's check what's in the primary_commodity field
$debug_query = "SELECT primary_commodity FROM markets WHERE id = ?";
$debug_stmt = $con->prepare($debug_query);
$debug_stmt->bind_param("i", $market_id);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();
$debug_row = $debug_result->fetch_assoc();

// Add debug info (remove this in production)
// error_log("Primary commodity field contains: " . $debug_row['primary_commodity']);

// Modified query with better error handling
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
            m.image_urls,
            m.primary_commodity,
            GROUP_CONCAT(
                CONCAT('{\"id\":', c.id,
                       ',\"name\":\"', REPLACE(c.commodity_name, '\"', '\\\"'),
                       '\",\"variety\":\"', REPLACE(IFNULL(c.variety, ''), '\"', '\\\"'),
                       '\",\"image_url\":\"', REPLACE(IFNULL(c.image_url, ''), '\"', '\\\"'),
                       '\",\"units\":', IFNULL(c.units, 'null'), '}')
                SEPARATOR ','
            ) AS commodities_list
          FROM markets m
          LEFT JOIN commodities c ON FIND_IN_SET(CAST(c.id AS CHAR), REPLACE(m.primary_commodity, ' ', ''))
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

// Debug: Log what we got from the query
// error_log("Commodities list from query: " . $row['commodities_list']);
// error_log("Primary commodity field: " . $row['primary_commodity']);

// Handle commodities JSON construction
$commodities_array = [];
if (!empty($row['commodities_list'])) {
    // Construct a valid JSON array string
    $commodities_json_string = '[' . $row['commodities_list'] . ']';
    $decoded_commodities = json_decode($commodities_json_string, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $commodities_array = $decoded_commodities;
        // error_log("Successfully parsed commodities array");
    } else {
        // error_log("JSON decode error for commodities: " . json_last_error_msg());
        // error_log("JSON string that failed: " . $commodities_json_string);
        // Fallback or error handling if JSON decoding fails
        $commodities_array = [];
    }
}

// Process image_urls
$image_urls_array = [];
if (!empty($row['image_urls'])) {
    $decoded_image_urls = json_decode($row['image_urls'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_image_urls)) {
        $image_urls_array = $decoded_image_urls;
    }
}


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
    "commodities" => $commodities_array, // Use the parsed array here
    "additional_datasource" => $row['additional_datasource'],
    "image_url" => $image_urls_array, // Use the parsed array here
);

sendJsonResponse("success", $market); // 200 OK is implied
?>