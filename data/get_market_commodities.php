<?php
// get_market_commodities.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include your database configuration file
include '../admin/includes/config.php';

// Function to handle errors and send JSON response
function sendJsonResponse($status, $data = null, $message = null, $http_code = 200) {
    http_response_code($http_code);
    $response = array("status" => $status, "success" => ($status === "success"));
    if ($data !== null) {
        $response["data"] = $data;
    }
    if ($message !== null) {
        $response["message"] = $message;
    }
    echo json_encode($response);
    exit;
}

// Check if market_id is provided
if (!isset($_GET['market_id']) || empty($_GET['market_id'])) {
    sendJsonResponse("error", null, "Market ID is required", 400);
}

$market_id = (int)$_GET['market_id'];

if (!isset($con)) {
    sendJsonResponse("error", null, "Database connection error", 500);
}

try {
    // Use the same approach as your existing API
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
        error_log("Database error: " . $con->error);
        sendJsonResponse("error", null, "Failed to prepare statement", 500);
    }

    $stmt->bind_param("i", $market_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendJsonResponse("error", null, "Market not found", 404);
    }

    $row = $result->fetch_assoc();

    // Handle commodities JSON construction
    $commodities_array = [];
    if (!empty($row['commodities_list'])) {
        // Construct a valid JSON array string
        $commodities_json_string = '[' . $row['commodities_list'] . ']';
        $decoded_commodities = json_decode($commodities_json_string, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $commodities_array = $decoded_commodities;
        } else {
            error_log("JSON decode error for commodities: " . json_last_error_msg());
            error_log("JSON string that failed: " . $commodities_json_string);
            // Fallback: try to parse primary_commodity directly
            if (!empty($row['primary_commodity'])) {
                $primary_commodities = explode(',', str_replace(' ', '', $row['primary_commodity']));
                foreach ($primary_commodities as $commodity_id) {
                    if (is_numeric($commodity_id)) {
                        $commodity_query = "SELECT id, commodity_name, variety, units, image_url FROM commodities WHERE id = ?";
                        $commodity_stmt = $con->prepare($commodity_query);
                        $commodity_stmt->bind_param("i", $commodity_id);
                        $commodity_stmt->execute();
                        $commodity_result = $commodity_stmt->get_result();
                        
                        if ($commodity_result->num_rows > 0) {
                            $commodity = $commodity_result->fetch_assoc();
                            
                            // Parse units JSON
                            $units = json_decode($commodity['units'], true);
                            if (!$units || !is_array($units)) {
                                $units = [];
                            }
                            
                            $commodities_array[] = [
                                'id' => $commodity['id'],
                                'name' => $commodity['commodity_name'],
                                'variety' => $commodity['variety'] ?: '',
                                'image_url' => $commodity['image_url'] ?: '',
                                'units' => $units
                            ];
                        }
                        $commodity_stmt->close();
                    }
                }
            }
        }
    }

    // If still no commodities found, try alternative approach
    if (empty($commodities_array) && !empty($row['primary_commodity'])) {
        error_log("Trying alternative approach for primary_commodity: " . $row['primary_commodity']);
        
        // Try to extract numbers from the primary_commodity field
        preg_match_all('/\d+/', $row['primary_commodity'], $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $commodity_id) {
                $commodity_query = "SELECT id, commodity_name, variety, units, image_url FROM commodities WHERE id = ?";
                $commodity_stmt = $con->prepare($commodity_query);
                $commodity_stmt->bind_param("i", $commodity_id);
                $commodity_stmt->execute();
                $commodity_result = $commodity_stmt->get_result();
                
                if ($commodity_result->num_rows > 0) {
                    $commodity = $commodity_result->fetch_assoc();
                    
                    // Parse units JSON
                    $units = json_decode($commodity['units'], true);
                    if (!$units || !is_array($units)) {
                        $units = [];
                    }
                    
                    $commodities_array[] = [
                        'id' => $commodity['id'],
                        'name' => $commodity['commodity_name'],
                        'variety' => $commodity['variety'] ?: '',
                        'image_url' => $commodity['image_url'] ?: '',
                        'units' => $units
                    ];
                }
                $commodity_stmt->close();
            }
        }
    }

    // Return the response in the format expected by your JavaScript
    $response_data = [
        'market_name' => $row['market_name'],
        'data_source' => $row['additional_datasource'],
        'commodities' => $commodities_array
    ];

    if (empty($commodities_array)) {
        sendJsonResponse("error", $response_data, "No commodities found for this market", 404);
    } else {
        sendJsonResponse("success", $response_data, "Commodities fetched successfully");
    }
    
} catch (Exception $e) {
    error_log("Error in get_market_commodities.php: " . $e->getMessage());
    sendJsonResponse("error", null, "An error occurred while fetching commodities", 500);
}

// Close the database connection
if (isset($con)) {
    $con->close();
}
?>