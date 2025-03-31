<?php
header("Content-Type: application/json");
include '../../admin/includes/config.php';

// Fetch commodities from database
$result = $con->query("SELECT * FROM commodities ORDER BY id DESC");
$commodities = [];

while ($row = $result->fetch_assoc()) {
    $commodities[] = $row;
}

echo json_encode(["status" => "success", "commodities" => $commodities]);
?>
