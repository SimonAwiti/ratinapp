<?php
header("Content-Type: application/json"); // Set content type to JSON
include '../../admin/includes/config.php'; // Include your database configuration

// Check database connection
if ($con->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $con->connect_error]));
}

$sql = "SELECT id, data_source_name, countries_covered, created_at FROM data_sources";
$result = $con->query($sql);

$dataSources = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dataSources[] = $row;
    }
    echo json_encode($dataSources); // Return all data sources as JSON
} else {
    echo json_encode(["message" => "No data sources found"]);
}

$con->close();
?>