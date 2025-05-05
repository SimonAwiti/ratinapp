<?php
// api/markets/get_all_markets.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../admin/includes/config.php';

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
            m.tradepoint,
            GROUP_CONCAT(
                CONCAT('{\"id\":', c.id, ',\"name\":\"', c.commodity_name, '\"}')
                SEPARATOR ','
            ) AS commodities_json
          FROM markets m
          LEFT JOIN commodities c ON FIND_IN_SET(c.id, m.primary_commodity)
          GROUP BY m.id";

$result = $con->query($query);

$markets = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $commodities = [];
        if (!empty($row['commodities_json'])) {
            $commodities = json_decode('[' . $row['commodities_json'] . ']', true);
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
            "commodities" => $commodities,
            "additional_datasource" => $row['additional_datasource'],
            "image_url" => $row['image_url'],
            "tradepoint" => $row['tradepoint'],
        );
    }
}

http_response_code(200);
echo json_encode(array(
    "status" => "success",
    "data" => $markets
));

$con->close();
?>
