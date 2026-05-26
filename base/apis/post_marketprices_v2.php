<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include '../../admin/includes/config.php';

function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode(["status" => "error", "message" => $message]);
    exit;
}

// Get token from header
$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (empty($token)) {
    sendErrorResponse("Token required", 401);
}

// Fetch enumerator
$stmt = $con->prepare("SELECT * FROM enumerators WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendErrorResponse("Invalid token", 401);
}

$user = $result->fetch_assoc();
$stmt->close();

// Process tradepoints
$tradepoints = json_decode($user['tradepoints'], true);
$tradepointDetails = [];

if (is_array($tradepoints)) {
    foreach ($tradepoints as $tp) {
        if (!isset($tp['id']) || !isset($tp['type'])) {
            continue;
        }
        
        $details = null;
        $responseType = $tp['type']; // Default to original type
        
        // Handle Market type (singular in DB, plural in response)
        if ($tp['type'] == 'Market') {
            $responseType = 'Markets'; // Change to plural for response
            
            $query = "SELECT 
                        id, 
                        market_name as name, 
                        category, 
                        type as market_type, 
                        country, 
                        county_district, 
                        longitude, 
                        latitude, 
                        radius, 
                        currency, 
                        primary_commodity, 
                        additional_datasource,
                        tradepoint
                      FROM markets 
                      WHERE id = ?";
                      
            $stmt_item = $con->prepare($query);
            if ($stmt_item) {
                $stmt_item->bind_param("i", $tp['id']);
                $stmt_item->execute();
                $result_item = $stmt_item->get_result();
                if ($result_item->num_rows > 0) {
                    $details = $result_item->fetch_assoc();
                }
                $stmt_item->close();
            }
        }
        // Handle Border Point type (singular in DB, plural in response)
        elseif ($tp['type'] == 'Border Point') {
            $responseType = 'Border Points'; // Change to plural for response
            
            $query = "SELECT 
                        id, 
                        name, 
                        country, 
                        county,
                        longitude, 
                        latitude, 
                        radius, 
                        created_at,
                        tradepoint,
                        images
                      FROM border_points 
                      WHERE id = ?";
                      
            $stmt_item = $con->prepare($query);
            if ($stmt_item) {
                $stmt_item->bind_param("i", $tp['id']);
                $stmt_item->execute();
                $result_item = $stmt_item->get_result();
                if ($result_item->num_rows > 0) {
                    $row = $result_item->fetch_assoc();
                    $details = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'country' => $row['country'],
                        'county_district' => $row['county'],
                        'longitude' => $row['longitude'],
                        'latitude' => $row['latitude'],
                        'radius' => $row['radius'],
                        'tradepoint' => $row['tradepoint'],
                        'images' => $row['images']
                    ];
                }
                $stmt_item->close();
            }
        }
        // Handle Miller type (stays as Miller - already fine)
        elseif ($tp['type'] == 'Miller') {
            $responseType = 'Miller'; // Keep as is
            
            // First check if exists in millers table
            $query = "SELECT 
                        id, 
                        miller_name as name, 
                        miller, 
                        longitude, 
                        latitude, 
                        radius, 
                        created_at
                      FROM millers 
                      WHERE id = ?";
                      
            $stmt_item = $con->prepare($query);
            if ($stmt_item) {
                $stmt_item->bind_param("i", $tp['id']);
                $stmt_item->execute();
                $result_item = $stmt_item->get_result();
                if ($result_item->num_rows > 0) {
                    $row = $result_item->fetch_assoc();
                    $details = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'miller' => $row['miller'],
                        'longitude' => $row['longitude'],
                        'latitude' => $row['latitude'],
                        'radius' => $row['radius'],
                        'type' => 'Miller'
                    ];
                    
                    // Try to get additional details from miller_details if available
                    $stmt_details = $con->prepare("SELECT country, county_district, currency FROM miller_details WHERE miller_name = ?");
                    if ($stmt_details) {
                        $stmt_details->bind_param("s", $row['name']);
                        $stmt_details->execute();
                        $result_details = $stmt_details->get_result();
                        if ($result_details->num_rows > 0) {
                            $details_row = $result_details->fetch_assoc();
                            $details['country'] = $details_row['country'];
                            $details['county_district'] = $details_row['county_district'];
                            $details['currency'] = $details_row['currency'];
                        }
                        $stmt_details->close();
                    }
                } else {
                    // If not found in millers, try miller_details
                    $query2 = "SELECT 
                                id, 
                                miller_name as name, 
                                miller, 
                                country, 
                                county_district, 
                                currency,
                                tradepoint,
                                created_at
                              FROM miller_details 
                              WHERE id = ?";
                    
                    $stmt_item2 = $con->prepare($query2);
                    if ($stmt_item2) {
                        $stmt_item2->bind_param("i", $tp['id']);
                        $stmt_item2->execute();
                        $result_item2 = $stmt_item2->get_result();
                        if ($result_item2->num_rows > 0) {
                            $row = $result_item2->fetch_assoc();
                            $details = [
                                'id' => $row['id'],
                                'name' => $row['name'],
                                'miller' => $row['miller'],
                                'country' => $row['country'],
                                'county_district' => $row['county_district'],
                                'currency' => $row['currency'],
                                'tradepoint' => $row['tradepoint'],
                                'type' => 'Miller'
                            ];
                            
                            // Try to get coordinates from millers table
                            $stmt_coords = $con->prepare("SELECT longitude, latitude, radius FROM millers WHERE miller_name = ?");
                            if ($stmt_coords) {
                                $stmt_coords->bind_param("s", $row['name']);
                                $stmt_coords->execute();
                                $result_coords = $stmt_coords->get_result();
                                if ($result_coords->num_rows > 0) {
                                    $coord_row = $result_coords->fetch_assoc();
                                    $details['longitude'] = $coord_row['longitude'];
                                    $details['latitude'] = $coord_row['latitude'];
                                    $details['radius'] = $coord_row['radius'];
                                }
                                $stmt_coords->close();
                            }
                        }
                        $stmt_item2->close();
                    }
                }
                $stmt_item->close();
            }
        }
        
        $tradepointDetails[] = [
            'id' => $tp['id'],
            'type' => $responseType, // Use the plural version for Market and Border Point
            'details' => $details
        ];
    }
}

// Return response
echo json_encode([
    "status" => "success",
    "message" => "Enumerator details fetched successfully",
    "data" => [
        "id" => $user['id'],
        "name" => $user['name'],
        "email" => $user['email'],
        "phone" => $user['phone'],
        "gender" => $user['gender'],
        "country" => $user['country'],
        "county_district" => $user['county_district'],
        "username" => $user['username'],
        "created_at" => $user['created_at'],
        "tradepoints" => $tradepointDetails,
        "latitude" => $user['latitude'],
        "longitude" => $user['longitude'],
        "token" => $user['token']
    ]
], JSON_PRETTY_PRINT);
?>