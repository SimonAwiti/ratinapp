<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to your database
include '../../admin/includes/config.php';

// Check for database connection
if (!$con) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

// Get the raw POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Function definitions
function convertToUSD($amount, $country) {
    if (!is_numeric($amount)) return 0;
    switch ($country) {
        case 'Kenya': return round($amount / 150, 2);
        case 'Uganda': return round($amount / 3700, 2);
        case 'Tanzania': return round($amount / 2300, 2);
        case 'Rwanda': return round($amount / 1200, 2);
        case 'Burundi': return round($amount / 2000, 2);
        default: return round($amount, 2);
    }
}

function calculateDayChange($currentPrice, $commodityId, $town, $con) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt = $con->prepare("SELECT price FROM miller_prices WHERE commodity_id = ? AND town = ? AND DATE(date_posted) = ?");
    $stmt->bind_param("iss", $commodityId, $town, $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $yesterdayPrice = $result->fetch_assoc()['price'];
        if ($yesterdayPrice > 0) {
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2);
        }
    }
    return null;
}

function calculateMonthChange($currentPrice, $commodityId, $town, $con) {
    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));
    $stmt = $con->prepare("SELECT AVG(price) as avg_price FROM miller_prices WHERE commodity_id = ? AND town = ? AND DATE(date_posted) BETWEEN ? AND ?");
    $stmt->bind_param("isss", $commodityId, $town, $firstDayOfLastMonth, $lastDayOfLastMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $avgPrice = $result->fetch_assoc()['avg_price'];
        if ($avgPrice > 0) {
            $change = (($currentPrice - $avgPrice) / $avgPrice) * 100;
            return round($change, 2);
        }
    }
    return null;
}

// Check if data is valid
if ($data === null || !isset($data['tradepoint_id']) || !isset($data['submissions']) || !is_array($data['submissions'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON data or missing required fields."]);
    exit;
}

$tradepoint_id = (int)$data['tradepoint_id'];
$submissions = $data['submissions'];

// --- THIS IS THE CRITICAL CHANGE ---
// Fetch miller details using the 'miller_details' table and the 'miller_name' column.
$stmt_tradepoint = $con->prepare("SELECT miller_name, country FROM miller_details WHERE id = ?");
if (!$stmt_tradepoint) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to prepare tradepoint query: " . $con->error]);
    exit;
}
$stmt_tradepoint->bind_param("i", $tradepoint_id);
$stmt_tradepoint->execute();
$result_tradepoint = $stmt_tradepoint->get_result();

if ($result_tradepoint->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Tradepoint ID not found in miller_details."]);
    exit;
}

$tradepoint_data = $result_tradepoint->fetch_assoc();
$town = $tradepoint_data['miller_name']; // Use 'miller_name' as the key
$country = $tradepoint_data['country'];

$processed_submissions = [];
$errors = [];

foreach ($submissions as $submission) {
    $commodity_id = (int)$submission['commodity_id'];
    $price = (float)$submission['price_per_kg'];
    $comments = isset($submission['comments']) ? $submission['comments'] : '';

    // Validate required fields for each submission
    if ($commodity_id <= 0 || $price <= 0) {
        $errors[] = "Invalid commodity ID or price for a submission.";
        continue;
    }

    // Get commodity name
    $commodity_name = "";
    $stmt_commodity = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt_commodity->bind_param("i", $commodity_id);
    $stmt_commodity->execute();
    $result_commodity = $stmt_commodity->get_result();
    if ($result_commodity->num_rows > 0) {
        $commodity_name = $result_commodity->fetch_assoc()['commodity_name'];
    } else {
        $errors[] = "Commodity ID " . $commodity_id . " not found.";
        continue;
    }
    
    $data_source_id = 1;
    $data_source_name = "API Submission";

    // Convert price to USD
    $price_usd = convertToUSD($price, $country);
    
    // Calculate day and month change percentages
    $day_change = calculateDayChange($price, $commodity_id, $town, $con);
    $month_change = calculateMonthChange($price, $commodity_id, $town, $con);
    
    // Prepare date values
    $date_posted = date('Y-m-d H:i:s');
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $status = 'pending';

    // Insert into database
    $stmt_insert = $con->prepare("INSERT INTO miller_prices
                                  (country, town, commodity_id, commodity_name, price, price_usd,
                                   day_change, month_change, data_source_id, data_source_name,
                                   date_posted, day, month, year, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt_insert) {
        $errors[] = "Failed to prepare insert statement: " . $con->error;
        continue;
    }

    $stmt_insert->bind_param("ssisddddissiiis",
        $country, $town, $commodity_id, $commodity_name, $price, $price_usd,
        $day_change, $month_change, $data_source_id, $data_source_name,
        $date_posted, $day, $month, $year, $status);
    
    if ($stmt_insert->execute()) {
        $processed_submissions[] = ["commodity_id" => $commodity_id, "status" => "success", "message" => "Price added."];
    } else {
        $errors[] = "Error adding price for commodity ID " . $commodity_id . ": " . $stmt_insert->error;
    }
}

$con->close();

if (empty($errors)) {
    http_response_code(201);
    echo json_encode(["success" => true, "message" => "All prices added successfully.", "results" => $processed_submissions]);
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Some prices could not be added.", "errors" => $errors, "results" => $processed_submissions]);
}
?>