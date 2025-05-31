<?php
include '../admin/includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if country parameter is provided
if (!isset($_POST['country'])) {
    echo json_encode([]);
    exit();
}

$country = $_POST['country'];

// Fetch counties/districts for the selected country
$query = "SELECT DISTINCT admin1_county_district FROM commodity_sources 
          WHERE admin0_country = ? ORDER BY admin1_county_district ASC";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $country);
$stmt->execute();
$result = $stmt->get_result();

$counties = [];
while ($row = $result->fetch_assoc()) {
    $counties[] = $row['admin1_county_district'];
}

echo json_encode($counties);