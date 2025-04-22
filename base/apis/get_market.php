<?php
// api/markets/get_market.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../admin/includes/config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Market ID is required"));
    exit;
}

$market_id = $con->real_escape_string($_GET['id']);

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
            GROUP_CONCAT(c.commodity_name SEPARATOR ', ') AS commodities
          FROM markets m
          LEFT JOIN commodities c ON FIND_IN_SET(c.id, m.primary_commodity)
          WHERE m.id = '$market_id'
          GROUP BY m.id";
$result = $con->query($query);

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(array("status" => "error", "message" => "Market not found"));
    exit;
}

$row = $result->fetch_assoc();

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
    "commodities" => $row['commodities'] ? explode(', ', $row['commodities']) : [],
    "additional_datasource" => $row['additional_datasource'],
    "image_url" => $row['image_url']
);

http_response_code(200);
echo json_encode(array(
    "status" => "success",
    "data" => $market
));

$con->close();
?>