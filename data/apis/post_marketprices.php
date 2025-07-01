<?php
// post_marketprices.php

// Include shared functions and database connection
require_once __DIR__ . '/api_includes.php';

// --- AUTHENTICATION CHECK ---
//authenticateApiKey();

// Ensure database connection exists
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed'], 500);
}

// Get the input data (JSON payload for POST requests)
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['tradepoint_id', 'trader_submissions', 'commodity_id', 'packaging_unit', 'measuring_unit'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || (empty($input[$field]) && $field !== 'packaging_unit' && $field !== 'measuring_unit')) {
        sendJsonResponse(['error' => "Missing required field: {$field}"], 400);
    }
}

if (!is_array($input['trader_submissions']) || empty($input['trader_submissions'])) {
    sendJsonResponse(['error' => 'trader_submissions must be a non-empty array'], 400);
}

// Extract top-level data common to all submissions
$tradepoint_id = (int)$input['tradepoint_id'];
$commodity_id = (int)$input['commodity_id'];
$packaging_unit_raw = mysqli_real_escape_string($con, $input['packaging_unit']);
$measuring_unit = mysqli_real_escape_string($con, $input['measuring_unit']);

$status = isset($input['status']) ? mysqli_real_escape_string($con, $input['status']) : 'pending';
$supplied_volume = isset($input['supplied_volume']) ? (int)$input['supplied_volume'] : NULL;
$comments = isset($input['comments']) ? mysqli_real_escape_string($con, $input['comments']) : NULL;
$subject = isset($input['subject']) ? mysqli_real_escape_string($con, $input['subject']) : "Market Prices";
$supply_status = isset($input['supply_status']) ? mysqli_real_escape_string($con, $input['supply_status']) : 'unknown';

// Process sources array
$data_source_arr = [];
if (isset($input['sources']) && is_array($input['sources'])) {
    foreach ($input['sources'] as $source) {
        if (isset($source['name'])) {
            $data_source_arr[] = mysqli_real_escape_string($con, $source['name']);
        }
    }
}
$data_source = implode(', ', $data_source_arr);

// Lookups: market info
$market_name = "";
$country_admin_0 = "";
$stmt_market = $con->prepare("SELECT market_name, country FROM markets WHERE id = ?");
if ($stmt_market) {
    $stmt_market->bind_param("i", $tradepoint_id);
    $stmt_market->execute();
    $market_result = $stmt_market->get_result();
    if ($market_result && $market_result->num_rows > 0) {
        $market_row = $market_result->fetch_assoc();
        $market_name = $market_row['market_name'];
        $country_admin_0 = $market_row['country'];
    } else {
        sendJsonResponse(['error' => 'Invalid tradepoint_id. Market not found.'], 400);
    }
    $stmt_market->close();
} else {
    sendJsonResponse(['error' => 'Failed to prepare market lookup query'], 500);
}

// Lookups: commodity
$category_id = null;
$variety = "";
$stmt_commodity = $con->prepare("SELECT variety, category_id FROM commodities WHERE id = ?");
if ($stmt_commodity) {
    $stmt_commodity->bind_param("i", $commodity_id);
    $stmt_commodity->execute();
    $commodity_result = $stmt_commodity->get_result();
    if ($commodity_result && $commodity_result->num_rows > 0) {
        $commodity_row = $commodity_result->fetch_assoc();
        $variety = $commodity_row['variety'];
        $category_id = $commodity_row['category_id'];
    } else {
        sendJsonResponse(['error' => 'Invalid commodity_id. Commodity not found.'], 400);
    }
    $stmt_commodity->close();
} else {
    sendJsonResponse(['error' => 'Failed to prepare commodity lookup query'], 500);
}

// Lookups: category
$category_name = "";
if ($category_id !== null) {
    $stmt_category = $con->prepare("SELECT category FROM categories WHERE id = ?");
    if ($stmt_category) {
        $stmt_category->bind_param("i", $category_id);
        $stmt_category->execute();
        $category_result = $stmt_category->get_result();
        if ($category_result && $category_result->num_rows > 0) {
            $category_row = $category_result->fetch_assoc();
            $category_name = $category_row['category'];
        }
        $stmt_category->close();
    }
}

// Extract weight from packaging_unit_raw
$weight_value = 0;
if (preg_match('/^(\d+(\.\d+)?)/', $packaging_unit_raw, $matches)) {
    $weight_value = (float)$matches[1];
} else {
    $weight_value = (float)$packaging_unit_raw;
    if ($weight_value == 0 && $packaging_unit_raw !== "0" && $packaging_unit_raw !== "0.0") {
        $weight_value = 1;
    }
}

$date_posted = date('Y-m-d H:i:s');
$day = date('d');
$month = date('m');
$year = date('Y');

$all_success = true;
$inserted_ids = [];

foreach ($input['trader_submissions'] as $submission) {
    $wholesale_price_local = isset($submission['wholesale_per_kg']) ? (float)$submission['wholesale_per_kg'] : 0;
    $retail_price_local = isset($submission['retail_per_kg']) ? (float)$submission['retail_per_kg'] : 0;

    if ($wholesale_price_local <= 0 && $retail_price_local <= 0) {
        continue;
    }

    $wholesale_price_usd = convertToUSD($wholesale_price_local, $country_admin_0, $con);
    $retail_price_usd = convertToUSD($retail_price_local, $country_admin_0, $con);

    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source, supplied_volume, comments, supply_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Wholesale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($retail_price_local > 0) {
        $sql .= ", (?, ?, ?, ?, ?, ?, ?, 'Retail', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt_insert = $con->prepare($sql);

    if ($stmt_insert) {
        $params = [
            $category_name, $commodity_id, $country_admin_0, $tradepoint_id, $market_name, $weight_value, $measuring_unit,
            $wholesale_price_usd, $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source, $supplied_volume, $comments, $supply_status
        ];

        if ($retail_price_local > 0) {
            $params = array_merge($params, [
                $category_name, $commodity_id, $country_admin_0, $tradepoint_id, $market_name, $weight_value, $measuring_unit,
                $retail_price_usd, $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source, $supplied_volume, $comments, $supply_status
            ]);
        }

        $types_per_record = "sisssdssdiiissssiss";
        $types = str_repeat($types_per_record, ($retail_price_local > 0) ? 2 : 1);

        $stmt_insert->bind_param($types, ...$params);

        if (!$stmt_insert->execute()) {
            error_log("Error inserting record: " . $stmt_insert->error);
            $all_success = false;
        } else {
            $inserted_ids[] = $con->insert_id;
        }

        $stmt_insert->close();
    } else {
        error_log("Error preparing insert statement: " . $con->error);
        $all_success = false;
    }
}

if ($all_success) {
    sendJsonResponse(['message' => 'Market prices added successfully', 'inserted_ids' => $inserted_ids], 201);
} else {
    sendJsonResponse(['error' => 'One or more submissions failed', 'inserted_ids' => $inserted_ids], 500);
}

if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>
