<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // Adjust if needed

include '../../admin/includes/config.php'; // DB connection

// Get the token from the header
$headers = apache_request_headers();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Token required"]);
    exit;
}

// Fetch the enumerator associated with the token
$stmt = $con->prepare("SELECT * FROM enumerators WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}

$user = $result->fetch_assoc();

// Decode tradepoints from JSON
$tradepoints = json_decode($user['tradepoints'], true);
$tradepointDetails = [];

foreach ($tradepoints as $tp) {
    $id = $tp['id'];
    $type = $tp['type'];
    $details = null;

    if ($type === 'Markets') {
        $stmt = $con->prepare("SELECT id, market_name as name, longitude, latitude, radius, country, county_district FROM markets WHERE id = ?");
    } elseif ($type === 'Border Points') {
        $stmt = $con->prepare("SELECT id, name, longitude, latitude, radius, country, county as county_district FROM border_points WHERE id = ?");
    } elseif ($type === 'Miller') {
        $stmt = $con->prepare("SELECT md.id, md.miller_name as name, m.longitude, m.latitude, m.radius, md.country, md.county_district 
                                FROM miller_details md 
                                JOIN millers m ON md.miller_name = m.miller_name
                                WHERE md.id = ?");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $details = $result->fetch_assoc();
    }
    $stmt->close();

    // Store the tradepoint details
    $tradepointDetails[] = [
        'id' => $id,
        'type' => $type,
        'details' => $details
    ];
}

// Prepare the response
$response = [
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
];

echo json_encode($response);
?>
