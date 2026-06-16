<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include '../../admin/includes/config.php';

function sendResponse($status, $message, $data = null) {
    $response = ["status" => $status, "message" => $message];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Get token
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
if (empty($token)) $token = $_POST['token'] ?? $_GET['token'] ?? '';

if (empty($token)) {
    sendResponse("error", "Token required");
}

// Fetch enumerator
$stmt = $con->prepare("SELECT * FROM enumerators WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse("error", "Invalid token");
}

$user = $result->fetch_assoc();
$stmt->close();

$tradepointDetails = [];

if (!empty($user['tradepoints'])) {
    $tradepoints = json_decode($user['tradepoints'], true);
    
    if (is_array($tradepoints)) {
        foreach ($tradepoints as $tp) {
            if (!isset($tp['id'], $tp['type'])) continue;
            
            $id = (int)$tp['id'];
            $type = $tp['type'];
            $details = null;
            
            if ($type == 'Market' || $type == 'Markets') {
                $query = "SELECT id, market_name as name, longitude, latitude, radius, country, county_district FROM markets WHERE id = $id";
                $result_detail = $con->query($query);
                if ($result_detail && $result_detail->num_rows > 0) {
                    $details = $result_detail->fetch_assoc();
                }
            } 
            elseif ($type == 'Border Point' || $type == 'Border Points') {
                $query = "SELECT id, name, longitude, latitude, radius, country, county as county_district FROM border_points WHERE id = $id";
                $result_detail = $con->query($query);
                if ($result_detail && $result_detail->num_rows > 0) {
                    $details = $result_detail->fetch_assoc();
                }
            } 
            elseif ($type == 'Miller' || $type == 'Millers') {
                // First try: millers table (has location but no country)
                $query = "SELECT id, miller_name as name, longitude, latitude, radius FROM millers WHERE id = $id";
                $result_detail = $con->query($query);
                
                if ($result_detail && $result_detail->num_rows > 0) {
                    $details = $result_detail->fetch_assoc();
                    // Add null values for missing fields to maintain consistent response structure
                    $details['country'] = null;
                    $details['county_district'] = null;
                } else {
                    // Second try: miller_details table (has country but may not have location)
                    $query2 = "SELECT id, miller_name as name, country, county_district FROM miller_details WHERE id = $id";
                    $result_detail2 = $con->query($query2);
                    
                    if ($result_detail2 && $result_detail2->num_rows > 0) {
                        $details = $result_detail2->fetch_assoc();
                        // Get location from millers table if available
                        $millerName = $details['name'];
                        $locationQuery = "SELECT longitude, latitude, radius FROM millers WHERE miller_name = '$millerName' LIMIT 1";
                        $locationResult = $con->query($locationQuery);
                        if ($locationResult && $locationResult->num_rows > 0) {
                            $location = $locationResult->fetch_assoc();
                            $details['longitude'] = $location['longitude'];
                            $details['latitude'] = $location['latitude'];
                            $details['radius'] = $location['radius'];
                        } else {
                            $details['longitude'] = null;
                            $details['latitude'] = null;
                            $details['radius'] = null;
                        }
                    }
                }
            }
            
            $tradepointDetails[] = [
                'id' => $id,
                'type' => $type,
                'details' => $details
            ];
        }
    }
}

sendResponse("success", "Enumerator details fetched successfully", [
    "id" => (int)$user['id'],
    "name" => $user['name'],
    "email" => $user['email'],
    "phone" => $user['phone'],
    "gender" => $user['gender'],
    "country" => $user['country'],
    "county_district" => $user['county_district'],
    "username" => $user['username'],
    "created_at" => $user['created_at'],
    "tradepoints" => $tradepointDetails,
    "latitude" => $user['latitude'] ? (float)$user['latitude'] : null,
    "longitude" => $user['longitude'] ? (float)$user['longitude'] : null,
    "token" => $user['token']
]);
?>