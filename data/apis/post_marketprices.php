<?php
// post_marketprices.php

// Include shared functions (like sendJsonResponse and convertToUSD) and database connection
require_once __DIR__ . '/api_includes.php';

// --- AUTHENTICATION CHECK ---
// Uncomment the line below if you have an API key authentication mechanism
// authenticateApiKey();

// Ensure database connection exists
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed'], 500);
}

// Get the input data (JSON payload for POST requests)
$input = json_decode(file_get_contents('php://input'), true);

// Validate top-level required fields
$required_top_level_fields = ['tradepoint_id', 'submissions'];
foreach ($required_top_level_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        sendJsonResponse(['error' => "Missing required top-level field: {$field}"], 400);
    }
}

// Ensure 'submissions' is a non-empty array
if (!is_array($input['submissions']) || empty($input['submissions'])) {
    sendJsonResponse(['error' => 'submissions must be a non-empty array'], 400);
}

// Extract top-level data common to all submissions
$tradepoint_id = (int)$input['tradepoint_id'];

// Lookups: market info (common for all submissions)
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
        // Log the error for internal debugging
        error_log("Invalid tradepoint_id: {$tradepoint_id}. Market not found.");
        sendJsonResponse(['error' => 'Invalid tradepoint_id. Market not found.'], 400);
    }
    $stmt_market->close();
} else {
    error_log("Failed to prepare market lookup query: " . $con->error);
    sendJsonResponse(['error' => 'Failed to prepare market lookup query'], 500);
}

// Date and time variables for insertion
$date_posted = date('Y-m-d H:i:s');
$day = (int)date('d');
$month = (int)date('m');
$year = (int)date('Y');

$all_success = true;
$inserted_ids = [];
$failed_submissions = []; // To track specific failures

// Loop through each submission in the payload
foreach ($input['submissions'] as $index => $submission) {
    // Validate required fields for each submission
    $required_submission_fields = ['commodity_id', 'wholesale_per_kg', 'retail_per_kg'];
    foreach ($required_submission_fields as $field) {
        if (!isset($submission[$field])) {
            $failed_submissions[] = ['index' => $index, 'error' => "Missing required field: {$field}"];
            $all_success = false;
            continue 2; // Skip to the next submission
        }
    }

    $commodity_id = (int)$submission['commodity_id'];
    $wholesale_price_local = (float)$submission['wholesale_per_kg'];
    $retail_price_local = (float)$submission['retail_per_kg'];

    // If both prices are 0 or less, skip this submission as there's no valid price data to record
    if ($wholesale_price_local <= 0 && $retail_price_local <= 0) {
        error_log("Skipping submission {$index}: Both wholesale and retail prices are zero or negative for commodity_id {$commodity_id}.");
        continue;
    }

    $supplied_volume = isset($submission['supplied_volume']) && $submission['supplied_volume'] !== '' ? (int)$submission['supplied_volume'] : NULL;
    
    // 'status' in payload maps to 'supply_status' in DB
    $supply_status_from_payload = 'unknown'; // Default value
    if (isset($submission['status']) && !empty(trim($submission['status']))) {
        $supply_status_from_payload = mysqli_real_escape_string($con, trim($submission['status']));
    }
    
    // 'status' in DB is always 'pending' for new submissions
    $admin_status = 'pending';

    // Handle comments - can be NULL or string, never "0"
    $comments = NULL;
    if (isset($submission['comments']) && trim($submission['comments']) !== '') {
        $comments = mysqli_real_escape_string($con, trim($submission['comments']));
    }
    
    // Handle subject - default to "Market Prices"
    $subject = "Market Prices";
    if (isset($submission['subject']) && !empty(trim($submission['subject']))) {
        $subject = mysqli_real_escape_string($con, trim($submission['subject']));
    }
    
    error_log("DEBUG: Before data_source processing - subject='{$subject}', supply_status='{$supply_status_from_payload}'");

    // --- SIMPLIFIED LOGIC FOR data_source (reporting sources) ---
    $data_source_db_value = 'RATIN'; // Default value
    
    // Check for data_reporting_sources array first
    if (isset($submission['data_reporting_sources']) && is_array($submission['data_reporting_sources'])) {
        $valid_sources = [];
        
        foreach ($submission['data_reporting_sources'] as $source_item) {
            if (isset($source_item['name']) && !empty(trim($source_item['name']))) {
                $source_name = trim($source_item['name']);
                // Only add if it's not "0" and not empty
                if ($source_name !== '0' && $source_name !== '') {
                    $valid_sources[] = mysqli_real_escape_string($con, $source_name);
                }
            }
        }
        
        if (!empty($valid_sources)) {
            $data_source_db_value = implode(', ', $valid_sources);
            error_log("DEBUG: Using data_reporting_sources: '{$data_source_db_value}'");
        }
    }
    // If no valid data_reporting_sources, check for direct data_source field
    else if (isset($submission['data_source']) && !empty(trim($submission['data_source']))) {
        $direct_source = trim($submission['data_source']);
        if ($direct_source !== '0' && $direct_source !== '') {
            $data_source_db_value = mysqli_real_escape_string($con, $direct_source);
            error_log("DEBUG: Using direct data_source: '{$data_source_db_value}'");
        }
    }
    
    error_log("DEBUG: FINAL data_source_db_value: '{$data_source_db_value}'");

    // --- LOGIC FOR commodity_sources_data (NEW JSON COLUMN) ---
    $commodity_sources_payload = isset($submission['commodity_sources']) && is_array($submission['commodity_sources'])
                                ? $submission['commodity_sources']
                                : [];
    // Encode the array directly to JSON string for the 'commodity_sources_data' column
    $commodity_sources_json_db_value = json_encode($commodity_sources_payload);
    if ($commodity_sources_json_db_value === false) {
        error_log("JSON encoding failed for commodity_sources for commodity_id {$commodity_id}: " . json_last_error_msg());
        $commodity_sources_json_db_value = '[]'; // Default to empty JSON array on error
    }

    // Lookups: commodity details from 'commodities' table
    $category_id = null;
    $variety = "";
    $packaging_unit_raw = ""; // This will store the 'size' from JSON
    $measuring_unit = "";     // This will store the 'unit' from JSON

    // CORRECTED SQL: Select 'units' JSON column from 'commodities' table
    $stmt_commodity = $con->prepare("SELECT variety, category_id, units FROM commodities WHERE id = ?");
    if ($stmt_commodity) {
        $stmt_commodity->bind_param("i", $commodity_id);
        $stmt_commodity->execute();
        $commodity_result = $stmt_commodity->get_result();
        if ($commodity_result && $commodity_result->num_rows > 0) {
            $commodity_row = $commodity_result->fetch_assoc();
            $variety = $commodity_row['variety'];
            $category_id = $commodity_row['category_id'];

            // CORRECTED: Parse the 'units' JSON column from 'commodities' table
            $units_json = $commodity_row['units'];
            $units_array = json_decode($units_json, true); // Decode JSON into a PHP array

            // Assuming 'units' is an array of objects and we take the first one for simplicity
            if (!empty($units_array) && is_array($units_array) && isset($units_array[0]['size']) && isset($units_array[0]['unit'])) {
                $packaging_unit_raw = (string)$units_array[0]['size']; // Cast to string for preg_match
                $measuring_unit = (string)$units_array[0]['unit'];
            } else {
                // Log and handle cases where 'units' JSON might be empty or malformed in DB
                error_log("Units data for commodity_id {$commodity_id} is missing or malformed in DB. Defaulting to '1' and 'unit'. JSON: " . ($units_json ?? 'NULL'));
                $packaging_unit_raw = "1"; // Default value to prevent errors
                $measuring_unit = "unit";  // Default value
            }

        } else {
            error_log("Invalid commodity_id {$commodity_id}. Commodity not found in 'commodities' table. Skipping submission {$index}.");
            $stmt_commodity->close();
            $failed_submissions[] = ['index' => $index, 'error' => "Commodity ID {$commodity_id} not found."];
            $all_success = false;
            continue; // Skip this submission if commodity not found
        }
        $stmt_commodity->close();
    } else {
        error_log("Failed to prepare commodity lookup query: " . $con->error . ". Skipping submission {$index}.");
        $failed_submissions[] = ['index' => $index, 'error' => "Failed to prepare commodity lookup query."];
        $all_success = false;
        continue; // Skip this submission if query preparation fails
    }

    // Lookups: category name from 'categories' table
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
            } else {
                error_log("Category ID {$category_id} not found for commodity {$commodity_id}.");
                // $category_name will remain empty, which is acceptable if not strictly required
            }
            $stmt_category->close();
        } else {
            error_log("Failed to prepare category lookup query: " . $con->error);
        }
    }

    // Extract numerical weight from $packaging_unit_raw (e.g., "50" from "50 Kg" or just "50")
    $weight_value = 0;
    if (preg_match('/^(\d+(\.\d+)?)/', $packaging_unit_raw, $matches)) {
        $weight_value = (float)$matches[1];
    } else {
        // If no number found, default to 1 as a base unit weight if it's not explicitly zero.
        $weight_value = (float)$packaging_unit_raw;
        if ($weight_value == 0 && $packaging_unit_raw !== "0" && $packaging_unit_raw !== "0.0") {
            $weight_value = 1;
        }
    }

    // Convert local prices to USD
    $wholesale_price_usd = convertToUSD($wholesale_price_local, $country_admin_0, $con);
    $retail_price_usd = convertToUSD($retail_price_local, $country_admin_0, $con);

    // Final validation to prevent "0" values in critical fields
    if ($data_source_db_value === '0') {
        $data_source_db_value = 'RATIN';
    }
    if ($subject === '0') {
        $subject = "Market Prices";
    }
    if ($supply_status_from_payload === '0') {
        $supply_status_from_payload = 'unknown';
    }
    if ($measuring_unit === '0') {
        $measuring_unit = 'kg'; // Default to kg if unit is 0
    }

    // Handle NULL values for supplied_volume
    $supplied_volume_for_db = $supplied_volume === null ? 0 : $supplied_volume;

    // SINGLE INSERT APPROACH - Much more reliable than batch inserts
    $inserted_count = 0;
    
    // Insert wholesale price if available
    if ($wholesale_price_local > 0) {
        $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source, supplied_volume, comments, supply_status, commodity_sources_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $types = "sisssdssdiiisssssssis"; // 21 characters - supplied_volume treated as string
        $params = [
            $category_name, 
            $commodity_id, 
            $country_admin_0, 
            $tradepoint_id, 
            $market_name, 
            $weight_value, 
            $measuring_unit,
            'Wholesale', 
            $wholesale_price_usd, 
            $subject,
            $day, 
            $month, 
            $year, 
            $date_posted, 
            $admin_status, 
            $variety,
            $data_source_db_value,
            $supplied_volume_for_db,
            $comments, 
            $supply_status_from_payload, 
            $commodity_sources_json_db_value
        ];
        
        error_log("INSERTING WHOLESALE - data_source: '{$data_source_db_value}'");
        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $inserted_id = $con->insert_id;
                $inserted_ids[] = $inserted_id;
                $inserted_count++;
                error_log("Wholesale inserted successfully, ID: " . $inserted_id);
                
                // Immediate verification
                $verify_sql = "SELECT id, data_source, subject, supply_status FROM market_prices WHERE id = ?";
                $stmt_verify = $con->prepare($verify_sql);
                $stmt_verify->bind_param("i", $inserted_id);
                $stmt_verify->execute();
                $verify_result = $stmt_verify->get_result();
                if ($verify_row = $verify_result->fetch_assoc()) {
                    error_log("VERIFICATION WHOLESALE: ID {$verify_row['id']} - data_source='{$verify_row['data_source']}', subject='{$verify_row['subject']}', supply_status='{$verify_row['supply_status']}'");
                } else {
                    error_log("VERIFICATION FAILED: Could not retrieve inserted wholesale record");
                }
                $stmt_verify->close();
            } else {
                error_log("Wholesale insert failed: " . $stmt->error);
                $failed_submissions[] = ['index' => $index, 'error' => "Wholesale insert failed: " . $stmt->error];
                $all_success = false;
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare wholesale statement: " . $con->error);
            $failed_submissions[] = ['index' => $index, 'error' => "Failed to prepare wholesale statement"];
            $all_success = false;
        }
    }
    
    // Insert retail price if available
    if ($retail_price_local > 0) {
        $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source, supplied_volume, comments, supply_status, commodity_sources_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $types = "sisssdssdiiisssssssis"; // 21 characters - supplied_volume treated as string
        $params = [
            $category_name, 
            $commodity_id, 
            $country_admin_0, 
            $tradepoint_id, 
            $market_name, 
            $weight_value, 
            $measuring_unit,
            'Retail', 
            $retail_price_usd, 
            $subject,
            $day, 
            $month, 
            $year, 
            $date_posted, 
            $admin_status, 
            $variety,
            $data_source_db_value,
            $supplied_volume_for_db,
            $comments, 
            $supply_status_from_payload, 
            $commodity_sources_json_db_value
        ];
        
        error_log("INSERTING RETAIL - data_source: '{$data_source_db_value}'");
        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $inserted_id = $con->insert_id;
                $inserted_ids[] = $inserted_id;
                $inserted_count++;
                error_log("Retail inserted successfully, ID: " . $inserted_id);
                
                // Immediate verification
                $verify_sql = "SELECT id, data_source, subject, supply_status FROM market_prices WHERE id = ?";
                $stmt_verify = $con->prepare($verify_sql);
                $stmt_verify->bind_param("i", $inserted_id);
                $stmt_verify->execute();
                $verify_result = $stmt_verify->get_result();
                if ($verify_row = $verify_result->fetch_assoc()) {
                    error_log("VERIFICATION RETAIL: ID {$verify_row['id']} - data_source='{$verify_row['data_source']}', subject='{$verify_row['subject']}', supply_status='{$verify_row['supply_status']}'");
                } else {
                    error_log("VERIFICATION FAILED: Could not retrieve inserted retail record");
                }
                $stmt_verify->close();
            } else {
                error_log("Retail insert failed: " . $stmt->error);
                $failed_submissions[] = ['index' => $index, 'error' => "Retail insert failed: " . $stmt->error];
                $all_success = false;
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare retail statement: " . $con->error);
            $failed_submissions[] = ['index' => $index, 'error' => "Failed to prepare retail statement"];
            $all_success = false;
        }
    }
    
    if ($inserted_count === 0) {
        error_log("No records inserted for submission {$index}");
        $failed_submissions[] = ['index' => $index, 'error' => "No valid prices provided."];
        $all_success = false;
    }
}

// Final response based on overall success/failure
if ($all_success) {
    sendJsonResponse(['message' => 'All market prices added successfully', 'inserted_ids' => $inserted_ids], 201);
} else {
    // Provide details about failures if any occurred
    sendJsonResponse([
        'error' => 'One or more submissions failed',
        'inserted_ids' => $inserted_ids, // IDs successfully inserted before failures
        'failed_submissions' => $failed_submissions
    ], 500);
}

// Close the database connection
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>