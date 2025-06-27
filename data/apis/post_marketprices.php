<?php
// post_marketprices.php

// Include shared functions and database connection
require_once __DIR__ . '/api_includes.php';

// --- AUTHENTICATION CHECK ---/
//authenticateApiKey(); // Call the authentication function at the start

// Ensure database connection exists
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed'], 500);
}

// Get the input data (JSON payload for POST requests)
$input = json_decode(file_get_contents('php://input'), true);

// Validate primary required top-level fields
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

$status = isset($input['status']) ? mysqli_real_escape_string($con, $input['status']) : 'pending'; // Default to 'pending' if not provided
$supplied_volume = isset($input['supplied_volume']) ? (int)$input['supplied_volume'] : NULL;
$comments = isset($input['comments']) ? mysqli_real_escape_string($con, $input['comments']) : NULL;
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

// --- Perform Lookups for Category, Variety, Country, Market Name ---

// 1. Fetch Market Name and Country based on tradepoint_id
$market_name = "";
$country_admin_0 = ""; // Use country_admin_0 to match DB column name
$stmt_market = $con->prepare("SELECT market_name, country FROM markets WHERE id = ?");
if ($stmt_market) {
    $stmt_market->bind_param("i", $tradepoint_id);
    $stmt_market->execute();
    $market_result = $stmt_market->get_result();
    if ($market_result && $market_result->num_rows > 0) {
        $market_row = $market_result->fetch_assoc();
        $market_name = $market_row['market_name'];
        $country_admin_0 = $market_row['country']; // Assign fetched country
    } else {
        sendJsonResponse(['error' => 'Invalid tradepoint_id. Market not found.'], 400);
    }
    $stmt_market->close();
} else {
    error_log("Error preparing market lookup statement: " . $con->error);
    sendJsonResponse(['error' => 'Failed to prepare market lookup query'], 500);
}

// 2. Fetch Variety and category_id based on commodity_id from 'commodities' table
$category_id = null; // Initialize
$variety = "";       // Initialize
$stmt_commodity = $con->prepare("SELECT variety, category_id FROM commodities WHERE id = ?");
if ($stmt_commodity) {
    $stmt_commodity->bind_param("i", $commodity_id);
    $stmt_commodity->execute();
    $commodity_result = $stmt_commodity->get_result();
    if ($commodity_result && $commodity_result->num_rows > 0) {
        $commodity_row = $commodity_result->fetch_assoc();
        $variety = $commodity_row['variety'];
        $category_id = $commodity_row['category_id']; // Fetch category_id
    } else {
        sendJsonResponse(['error' => 'Invalid commodity_id. Commodity not found.'], 400);
    }
    $stmt_commodity->close();
} else {
    error_log("Error preparing commodity lookup statement: " . $con->error);
    sendJsonResponse(['error' => 'Failed to prepare commodity lookup query'], 500);
}

// 3. Fetch Category Name based on category_id from 'categories' table
$category_name = ""; // Initialize category name
if ($category_id !== null) { // Only attempt lookup if category_id was found
    // --- FIX START ---
    $stmt_category = $con->prepare("SELECT category FROM categories WHERE id = ?"); // Changed 'category_name' to 'category'
    // --- FIX END ---
    if ($stmt_category) {
        $stmt_category->bind_param("i", $category_id);
        $stmt_category->execute();
        $category_result = $stmt_category->get_result();
        if ($category_result && $category_result->num_rows > 0) {
            $category_row = $category_result->fetch_assoc();
            $category_name = $category_row['category']; // Assign fetched category name from 'category' column
        } else {
            // Log an error but don't stop execution, category_name will remain empty
            error_log("Warning: Category name not found for category_id: " . $category_id);
        }
        $stmt_category->close();
    } else {
        error_log("Error preparing category lookup statement: " . $con->error);
    }
}


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

$all_success = true;
$inserted_ids = [];

foreach ($input['trader_submissions'] as $submission) {
    $wholesale_price_local = isset($submission['wholesale_per_kg']) ? (float)$submission['wholesale_per_kg'] : 0;
    $retail_price_local = isset($submission['retail_per_kg']) ? (float)$submission['retail_per_kg'] : 0;

    if ($wholesale_price_local <= 0 && $retail_price_local <= 0) {
        error_log("Skipping submission due to invalid prices (both zero or negative): " . json_encode($submission));
        continue;
    }

    // Convert prices from local currency to USD
    // Note: $country_admin_0 is used here as it's fetched from the markets table
    $wholesale_price_usd = convertToUSD($wholesale_price_local, $country_admin_0, $con);
    $retail_price_usd = convertToUSD($retail_price_local, $country_admin_0, $con);

    // Prepare the SQL INSERT statement
    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source, supplied_volume, comments)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Wholesale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($retail_price_local > 0) {
        $sql .= ", (?, ?, ?, ?, ?, ?, ?, 'Retail', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt_insert = $con->prepare($sql);

    if ($stmt_insert) {
        $params = [
            // Wholesale Record Parameters (category, variety, country are now looked up)
            $category_name, $commodity_id, $country_admin_0, $tradepoint_id, $market_name, $weight_value, $measuring_unit, $wholesale_price_usd,
            $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source, $supplied_volume, $comments
        ];

        if ($retail_price_local > 0) {
            // Retail Record Parameters
            $params = array_merge($params, [
                $category_name, $commodity_id,
                $country_admin_0, $tradepoint_id, $market_name, $weight_value, $measuring_unit, $retail_price_usd,
                $subject, $day,
                $month, $year, $date_posted, $status, $variety, $data_source, $supplied_volume, $comments
            ]);
        }
        
        // s (category_name), i (commodity), s (country_admin_0), i (market_id), s (market), d (weight), s (unit), d (price),
        // s (subject), i (day), i (month), i (year), s (date_posted), s (status), s (variety), s (data_source), i (supplied_volume), s (comments)
        $types_per_record = "sisssdssdiiissssis";
        $types = str_repeat($types_per_record, ($retail_price_local > 0) ? 2 : 1);
        
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