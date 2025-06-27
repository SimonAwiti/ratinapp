<?php
// put_marketprices.php

// Include shared functions and database connection
require_once __DIR__ . '/api_includes.php';

// --- AUTHENTICATION CHECK ---
authenticateApiKey(); // Call the authentication function at the start

// Ensure database connection exists
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed'], 500);
}

// Get the ID from the URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    sendJsonResponse(['error' => 'Missing or invalid ID for update'], 400);
}

// Get the input data (JSON payload for PUT requests)
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields for update
$required_fields = ['country', 'market_id', 'category', 'commodity_id', 'packaging_unit', 'measuring_unit', 'price_type', 'Price'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        sendJsonResponse(['error' => "Missing required field for update: {$field}"], 400);
    }
}

$country = mysqli_real_escape_string($con, $input['country']);
$market_id = (int)$input['market_id'];
$category = mysqli_real_escape_string($con, $input['category']);
$commodity_id = (int)$input['commodity_id'];
$packaging_unit = mysqli_real_escape_string($con, $input['packaging_unit']);
$measuring_unit = mysqli_real_escape_string($con, $input['measuring_unit']);
$variety = isset($input['variety']) ? mysqli_real_escape_string($con, $input['variety']) : '';
$data_source = isset($input['data_source']) ? mysqli_real_escape_string($con, $input['data_source']) : '';
$price_type = mysqli_real_escape_string($con, $input['price_type']); // 'Wholesale' or 'Retail'
$price_local = (float)$input['Price']; // Price in local currency
$status = isset($input['status']) ? mysqli_real_escape_string($con, $input['status']) : '';

// New fields for PUT: supplied_volume and comments
$supplied_volume = isset($input['supplied_volume']) ? (int)$input['supplied_volume'] : NULL;
$comments = isset($input['comments']) ? mysqli_real_escape_string($con, $input['comments']) : NULL;

// --- Extract numerical part from packaging_unit for the 'weight' column (DECIMAL type in DB) ---
$weight_value = 0;
if (preg_match('/^(\d+(\.\d+)?)/', $packaging_unit, $matches)) {
    $weight_value = (float)$matches[1];
} else {
    $weight_value = (float)$packaging_unit;
    if ($weight_value == 0 && $packaging_unit !== "0" && $packaging_unit !== "0.0") {
        error_log("Warning: Could not extract valid number from packaging unit '{$packaging_unit}'. Defaulting to 1 for calculation.");
        $weight_value = 1;
    }
}

if (!in_array($price_type, ['Wholesale', 'Retail'])) {
    sendJsonResponse(['error' => 'price_type must be "Wholesale" or "Retail"'], 400);
}
if ($price_local <= 0) {
    sendJsonResponse(['error' => 'Price must be a positive number'], 400);
}

// Convert local price to USD
$price_usd = convertToUSD($price_local, $country, $con);

// Fetch market name based on market_id
$market_name = "";
$stmt_market = $con->prepare("SELECT market_name FROM markets WHERE id = ?");
if ($stmt_market) {
    $stmt_market->bind_param("i", $market_id);
    $stmt_market->execute();
    $market_name_result = $stmt_market->get_result();
    if ($market_name_result && $market_name_result->num_rows > 0) {
        $market_name_row = $market_name_result->fetch_assoc();
        $market_name = $market_name_row['market_name'];
    }
    $stmt_market->close();
} else {
    error_log("Error preparing market name query for update: " . $con->error);
    sendJsonResponse(['error' => 'Failed to fetch market name for update'], 500);
}

$sql = "UPDATE market_prices SET
            category = ?,
            commodity = ?,
            country_admin_0 = ?,
            market_id = ?,
            market = ?,
            weight = ?,
            unit = ?,
            price_type = ?,
            Price = ?,
            variety = ?,
            data_source = ?,
            supplied_volume = ?,
            comments = ?,
            date_posted = NOW()";
if (!empty($status)) {
    $sql .= ", status = ?";
}
$sql .= " WHERE id = ?";


$stmt = $con->prepare($sql);
if ($stmt) {
    $params = [
        $category, $commodity_id, $country, $market_id, $market_name, $weight_value, $measuring_unit, $price_type, $price_usd, $variety, $data_source, $supplied_volume, $comments
    ];
    $types = "sisssdssdssis";

    if (!empty($status)) {
        $params[] = $status;
        $types .= "s";
    }
    $params[] = $id;
    $types .= "i";

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(['message' => 'Market price updated successfully'], 200);
        } else {
            sendJsonResponse(['message' => 'No changes made or market price not found'], 404);
        }
    } else {
        error_log("Error updating market price: " . $stmt->error);
        sendJsonResponse(['error' => 'Failed to update market price: ' . $stmt->error], 500);
    }
    $stmt->close();
} else {
    sendJsonResponse(['error' => 'Failed to prepare update statement: ' . $con->error], 500);
}

// Close database connection
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}

?>