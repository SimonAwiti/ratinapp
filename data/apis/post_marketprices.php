<?php
// post_marketprices.php

// Include shared functions and database connection
require_once __DIR__ . '/api_includes.php'; // Corrected path for robustness

// Ensure database connection exists
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed'], 500);
}

// Get the input data (JSON payload for POST requests)
$input = json_decode(file_get_contents('php://input'), true);

// Validate required top-level fields from the JSON input
$required_fields = ['tradepoint_id', 'trader_submissions']; // Simplified validation for main structure
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        sendJsonResponse(['error' => "Missing required field: {$field}"], 400);
    }
}

if (!is_array($input['trader_submissions']) || empty($input['trader_submissions'])) {
    sendJsonResponse(['error' => 'trader_submissions must be a non-empty array'], 400);
}

// Extract top-level data common to all submissions
$tradepoint_id = (int)$input['tradepoint_id'];
// Assuming these are still required or can be derived/defaulted if not present in the input for now.
$required_common_fields = ['country', 'category', 'commodity_id', 'packaging_unit', 'measuring_unit'];
foreach ($required_common_fields as $field) {
    if (!isset($input[$field])) {
        sendJsonResponse(['error' => "Missing common required field: {$field}"], 400);
    }
}

$country = mysqli_real_escape_string($con, $input['country']);
$category = mysqli_real_escape_string($con, $input['category']);
$commodity_id = (int)$input['commodity_id'];
$packaging_unit_raw = mysqli_real_escape_string($con, $input['packaging_unit']);
$measuring_unit = mysqli_real_escape_string($con, $input['measuring_unit']);
$variety = isset($input['variety']) ? mysqli_real_escape_string($con, $input['variety']) : '';
$status = isset($input['status']) ? mysqli_real_escape_string($con, $input['status']) : 'pending'; // Default to 'pending' if not provided

// New fields: supplied_volume and comments
$supplied_volume = isset($input['supplied_volume']) ? (int)$input['supplied_volume'] : NULL;
$comments = isset($input['comments']) ? mysqli_real_escape_string($con, $input['comments']) : NULL;

// *** UPDATED: Allow 'subject' to be passed from frontend, default to "Market Prices" ***
$subject = isset($input['subject']) ? mysqli_real_escape_string($con, $input['subject']) : "Market Prices";


// Process sources array into a comma-separated string for 'data_source' column
$data_source_arr = [];
if (isset($input['sources']) && is_array($input['sources'])) {
    foreach ($input['sources'] as $source) {
        if (isset($source['name'])) {
            $data_source_arr[] = mysqli_real_escape_string($con, $source['name']);
        }
    }
}
$data_source = implode(', ', $data_source_arr);

// --- Extract numerical part from packaging_unit_raw for the 'weight' column (DECIMAL type in DB) ---
$weight_value = 0;
if (preg_match('/^(\d+(\.\d+)?)/', $packaging_unit_raw, $matches)) {
    $weight_value = (float)$matches[1];
} else {
    $weight_value = (float)$packaging_unit_raw;
    if ($weight_value == 0 && $packaging_unit_raw !== "0" && $packaging_unit_raw !== "0.0") {
        error_log("Warning: Could not extract valid number from packaging unit '{$packaging_unit_raw}'. Defaulting to 1 for calculation.");
        $weight_value = 1;
    }
}

// Get current date and derived values
$date_posted = date('Y-m-d H:i:s');
$day = date('d');
$month = date('m');
$year = date('Y');
// Subject is now dynamic based on input, or defaults to "Market Prices"


// Fetch market name based on tradepoint_id (which maps to market_id)
$market_name = "";
$stmt_market = $con->prepare("SELECT market_name FROM markets WHERE id = ?");
if ($stmt_market) {
    $stmt_market->bind_param("i", $tradepoint_id);
    $stmt_market->execute();
    $market_name_result = $stmt_market->get_result();
    if ($market_name_result && $market_name_result->num_rows > 0) {
        $market_name_row = $market_name_result->fetch_assoc();
        $market_name = $market_name_row['market_name'];
    }
    $stmt_market->close();
} else {
    error_log("Error preparing market name query: " . $con->error);
    sendJsonResponse(['error' => 'Failed to fetch market name'], 500);
}

$all_success = true;
$inserted_ids = [];

foreach ($input['trader_submissions'] as $submission) {
    $wholesale_price_local = isset($submission['wholesale_per_kg']) ? (float)$submission['wholesale_per_kg'] : 0;
    $retail_price_local = isset($submission['retail_per_kg']) ? (float)$submission['retail_per_kg'] : 0;

    if ($wholesale_price_local <= 0 && $retail_price_local <= 0) {
        error_log("Skipping submission due to invalid prices (both zero or negative): " . json_encode($submission));
        continue; // Skip this submission if both prices are invalid
    }

    // Convert prices from local currency to USD
    $wholesale_price_usd = convertToUSD($wholesale_price_local, $country, $con);
    $retail_price_usd = convertToUSD($retail_price_local, $country, $con);

    // Prepare the SQL INSERT statement to include new columns: supplied_volume, comments
    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source, supplied_volume, comments)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Wholesale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // If retail price is valid, also prepare to insert retail record
    if ($retail_price_local > 0) {
        $sql .= ", (?, ?, ?, ?, ?, ?, ?, 'Retail', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt_insert = $con->prepare($sql);

    if ($stmt_insert) {
        $params = [
            // Wholesale Record Parameters
            $category, $commodity_id, $country, $tradepoint_id, $market_name, $weight_value, $measuring_unit, $wholesale_price_usd,
            $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source, $supplied_volume, $comments
        ];

        if ($retail_price_local > 0) {
            // Retail Record Parameters
            $params = array_merge($params, [
                $category, $commodity_id,                   // Common fields for both price types
                $country, $tradepoint_id, $market_name, $weight_value, $measuring_unit, $retail_price_usd,
                $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source, $supplied_volume, $comments
            ]);
        }
        
        // Determine parameter types dynamically based on the number of records to insert
        // The type string for one set of values is now:
        // s (category), i (commodity), s (country_admin_0), i (market_id), s (market), d (weight - float/decimal), s (unit),
        // d (Price - float/decimal), s (subject), i (day), i (month), i (year), s (date_posted), s (status),
        // s (variety), s (data_source), i (supplied_volume), s (comments)
        // Note: 'price_type' ('Wholesale'/'Retail') is hardcoded into the SQL and not bound as a parameter.
        $types_per_record = "sisssdssdiiissssis"; // s(cat), i(comm_id), s(country), i(market_id), s(market), d(weight), s(unit), d(price), s(subject), i(day), i(month), i(year), s(date_posted), s(status), s(variety), s(data_source), i(supplied_volume), s(comments)
        $types = str_repeat($types_per_record, ($retail_price_local > 0) ? 2 : 1);
        
        // Dynamically bind parameters
        $stmt_insert->bind_param($types, ...$params);

        if (!$stmt_insert->execute()) {
            error_log("Error inserting market price record: " . $stmt_insert->error);
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
    sendJsonResponse(['error' => 'One or more market price submissions failed', 'inserted_ids' => $inserted_ids], 500);
}

// Close database connection
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}

?>