<?php
header('Content-Type: application/json');
include '../../admin/includes/config.php';

$sql = "SELECT * FROM border_points";
$result = $con->query($sql);

$borders = [];
while ($row = $result->fetch_assoc()) {
    $row['images'] = json_decode($row['images'], true); // Decode images JSON
    $borders[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $borders
]);
?>
