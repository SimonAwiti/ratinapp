<?php
header("Content-Type: application/json"); // Set content type to JSON
include '../../admin/includes/config.php'; // Include your database connection file

// Check connection
if ($con->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $con->connect_error]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0; // Get ID from query parameter, sanitize it

switch ($method) {
    case 'GET':
        if ($id > 0) {
            // Get a single commodity source by ID
            $stmt = $con->prepare("SELECT id, admin0_country, admin1_county_district FROM commodity_sources WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $source = $result->fetch_assoc();
            
            if ($source) {
                echo json_encode($source);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(["message" => "Commodity source not found."]);
            }
            $stmt->close();
        } else {
            // Get all commodity sources
            $sql = "SELECT id, admin0_country, admin1_county_district FROM commodity_sources";
            $result = $con->query($sql);
            
            $sources = [];
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $sources[] = $row;
                }
            }
            echo json_encode($sources);
        }
        break;

    // You can add other HTTP methods (POST, PUT, DELETE) here if needed

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(["message" => "Method not allowed."]);
        break;
}

$con->close();
?>