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
            // Get a single country by ID
            // Assuming your countries table has 'id', 'country_name', 'currency_code', 'date_created'
            $stmt = $con->prepare("SELECT id, country_name, currency_code, date_created FROM countries WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $country = $result->fetch_assoc();
            
            if ($country) {
                echo json_encode($country);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(["message" => "Country not found."]);
            }
            $stmt->close();
        } else {
            // Get all countries
            $sql = "SELECT id, country_name, currency_code, date_created FROM countries";
            $result = $con->query($sql);
            
            $countries = [];
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $countries[] = $row;
                }
            }
            echo json_encode($countries);
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