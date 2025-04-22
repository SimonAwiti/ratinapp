<?php
header('Content-Type: application/json');
include '../../admin/includes/config.php';

$sql = "SELECT * FROM miller_details";
$result = $con->query($sql);

$millerDetails = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $millerDetails[] = $row;
    }
}

echo json_encode([
    'status' => 'success',
    'data' => $millerDetails
]);
?>
