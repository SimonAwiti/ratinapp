<?php
// api/markets/get_all_markets.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../admin/includes/config.php';

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
            m.tradepoint,
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
          GROUP BY m.id";

$result = $con->query($query);

// Check for query execution errors
if (!$result) {
    error_log("Database error: " . $con->error);
    sendJsonResponse("error", null, "Failed to fetch markets", 500);
}

$markets = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Handle commodities JSON construction
        $commodities_array = [];
        if (!empty($row['commodities_list'])) {
            $commodities_json_string = '[' . $row['commodities_list'] . ']';
            $decoded_commodities = json_decode($commodities_json_string, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $commodities_array = $decoded_commodities;
            } else {
                // Log error if decoding fails for debugging, but don't stop execution
                error_log("JSON decode error for commodities in market ID " . $row['id'] . ": " . json_last_error_msg());
                $commodities_array = [];
            }
        }

        // Process image_urls
        $image_urls_array = [];
        if (!empty($row['image_urls'])) {
            $decoded_image_urls = json_decode($row['image_urls'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_image_urls)) {
                $image_urls_array = $decoded_image_urls;
            } else {
                // Log error if decoding fails for debugging
                error_log("JSON decode error for image_urls in market ID " . $row['id'] . ": " . json_last_error_msg());
                $image_urls_array = [];
            }
        }

        $markets[] = array(
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
            "commodities" => $commodities_array, // Use the parsed array
            "additional_datasource" => $row['additional_datasource'],
            "image_url" => $image_urls_array, // Use the parsed array
            "tradepoint" => $row['tradepoint'],
        );
    }
}

sendJsonResponse("success", $markets);

$con->close();
?>