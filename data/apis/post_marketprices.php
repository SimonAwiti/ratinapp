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

    // --- LOGIC FOR data_source (reporting sources) --- CORRECTED
    $data_reporting_source_names = [];
    
    // Debug logging
    error_log("DEBUG: Processing submission index {$index}");
    error_log("DEBUG: Raw submission data: " . json_encode($submission));
    
    if (isset($submission['data_reporting_sources'])) {
        error_log("DEBUG: data_reporting_sources exists");
        error_log("DEBUG: data_reporting_sources type: " . gettype($submission['data_reporting_sources']));
        error_log("DEBUG: data_reporting_sources content: " . json_encode($submission['data_reporting_sources']));
        
        if (is_array($submission['data_reporting_sources'])) {
            error_log("DEBUG: data_reporting_sources is an array with " . count($submission['data_reporting_sources']) . " items");
            
            foreach ($submission['data_reporting_sources'] as $idx => $source_item) {
                error_log("DEBUG: Processing source item {$idx}: " . json_encode($source_item));
                
                if (isset($source_item['name'])) {
                    $raw_name = $source_item['name'];
                    error_log("DEBUG: Found name field: '{$raw_name}'");
                    
                    if (!empty(trim($raw_name))) {
                        $cleaned_name = trim($raw_name);
                        error_log("DEBUG: Cleaned name: '{$cleaned_name}'");
                        
                        // Skip if name is literally "0"
                        if ($cleaned_name !== '0') {
                            $escaped_name = mysqli_real_escape_string($con, $cleaned_name);
                            $data_reporting_source_names[] = $escaped_name;
                            error_log("DEBUG: Added to array: '{$escaped_name}'");
                        } else {
                            error_log("DEBUG: Skipped '0' value");
                        }
                    } else {
                        error_log("DEBUG: Name field is empty after trim");
                    }
                } else {
                    error_log("DEBUG: Source item has no 'name' field");
                }
            }
        } else {
            error_log("DEBUG: data_reporting_sources is NOT an array");
        }
    } else {
        error_log("DEBUG: data_reporting_sources does NOT exist in submission");
    }

    error_log("DEBUG: Final data_reporting_source_names array: " . json_encode($data_reporting_source_names));

    // Store comma-separated names for 'data_source' column
    // DEFAULT to 'RATIN' if no valid sources provided
    if (!empty($data_reporting_source_names)) {
        $data_source_db_value = implode(', ', $data_reporting_source_names);
        error_log("DEBUG: Using names from array: '{$data_source_db_value}'");
    } else {
        // Fallback: check if there's a data_source directly in submission
        if (isset($submission['data_source']) && !empty(trim($submission['data_source'])) && trim($submission['data_source']) !== '0') {
            $data_source_db_value = mysqli_real_escape_string($con, trim($submission['data_source']));
            error_log("DEBUG: Using direct data_source: '{$data_source_db_value}'");
        } else {
            $data_source_db_value = 'RATIN';
            error_log("DEBUG: Using default RATIN");
        }
    }

    // Final validation: ensure it's never "0" or empty - default to RATIN
    if (empty($data_source_db_value) || $data_source_db_value === '0') {
        error_log("DEBUG: Data source was empty or '0', forcing to RATIN");
        $data_source_db_value = 'RATIN';
    }
    
    error_log("DEBUG: FINAL data_source_db_value to be inserted: '{$data_source_db_value}'");

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

    // Prepare the SQL statement for inserting market prices
    // This will insert one row for wholesale and potentially one for retail
    // Added 'commodity_sources_data' column at the end
    $sql_values_template = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 21 placeholders
    $sql_insert_base = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source, supplied_volume, comments, supply_status, commodity_sources_data) VALUES ";

    $params = [];
    $types = "";

    // Add wholesale price if available
    if ($wholesale_price_local > 0) {
        $sql = $sql_insert_base . $sql_values_template;
        
        error_log("DEBUG: Wholesale - About to insert with data_source='{$data_source_db_value}', subject='{$subject}', supply_status='{$supply_status_from_payload}'");
        
        $params = array_merge($params, [
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
            $supplied_volume, 
            $comments, 
            $supply_status_from_payload, 
            $commodity_sources_json_db_value
        ]);
        
        error_log("DEBUG: Wholesale params[16] (data_source): '" . $params[16] . "'");
        error_log("DEBUG: Wholesale params[9] (subject): '" . $params[9] . "'");
        error_log("DEBUG: Wholesale params[19] (supply_status): '" . $params[19] . "'");
        
        // s (category) i (commodity_id) s (country_admin_0) i (market_id) s (market) d (weight) s (unit) s (price_type) d (Price)
        // s (subject) i (day) i (month) i (year) s (date_posted) s (status) s (variety) s (data_source) i (supplied_volume)
        // s (comments) s (supply_status) s (commodity_sources_data)
        $types .= "sisssdssdiiissssissis";
    }

    // Add retail price if available
    if ($retail_price_local > 0) {
        // If wholesale price was added, append with a comma; otherwise, start a new INSERT statement
        if ($wholesale_price_local > 0) {
            $sql .= ", " . $sql_values_template;
        } else {
            $sql = $sql_insert_base . $sql_values_template;
        }
        
        error_log("DEBUG: Retail - About to insert with data_source='{$data_source_db_value}', subject='{$subject}', supply_status='{$supply_status_from_payload}'");
        
        $params = array_merge($params, [
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
            $supplied_volume, 
            $comments, 
            $supply_status_from_payload, 
            $commodity_sources_json_db_value
        ]);
        
        $param_offset = $wholesale_price_local > 0 ? 21 : 0;
        error_log("DEBUG: Retail params[" . ($param_offset + 16) . "] (data_source): '" . $params[$param_offset + 16] . "'");
        error_log("DEBUG: Retail params[" . ($param_offset + 9) . "] (subject): '" . $params[$param_offset + 9] . "'");
        error_log("DEBUG: Retail params[" . ($param_offset + 19) . "] (supply_status): '" . $params[$param_offset + 19] . "'");
        
        $types .= "sisssdssdiiissssissis";
    }

    // Only proceed if at least one price type (wholesale or retail) is available for insertion
    if (!empty($params)) {
        error_log("DEBUG: Final SQL: " . $sql);
        error_log("DEBUG: Final types: " . $types);
        error_log("DEBUG: Total params count: " . count($params));
        error_log("DEBUG: All params: " . json_encode($params));
        
        $stmt_insert = $con->prepare($sql);

        if ($stmt_insert) {

            $stmt_insert->bind_param($types, ...$params);

            if (!$stmt_insert->execute()) {
                error_log("Error inserting record for submission {$index}: " . $stmt_insert->error);
                $failed_submissions[] = ['index' => $index, 'error' => "Database insert failed: " . $stmt_insert->error];
                $all_success = false;
            } else {
                $inserted_ids[] = $con->insert_id;
                error_log("DEBUG: Successfully inserted with ID: " . $con->insert_id);
            }

            $stmt_insert->close();
        } else {
            error_log("Error preparing insert statement for submission {$index}: " . $con->error);
            $failed_submissions[] = ['index' => $index, 'error' => "Failed to prepare SQL statement."];
            $all_success = false;
        }
    } else {
        error_log("No valid wholesale or retail price found for commodity_id {$commodity_id} in submission {$index}. Skipping insertion.");
        $failed_submissions[] = ['index' => $index, 'error' => "No valid prices provided."];
        $all_success = false; // Mark overall failure if a submission was meant to insert but had no prices.
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