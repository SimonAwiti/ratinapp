<?php
session_start();
header('Content-Type: application/json'); // Set header for JSON response

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in as an administrator.']);
    exit;
}

// Include database configuration
include '../admin/includes/config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'No CSV file uploaded or an upload error occurred.';
        echo json_encode($response);
        exit;
    }

    $file_tmp_path = $_FILES['csv_file']['tmp_name'];
    $file_type = mime_content_type($file_tmp_path);

    // Basic file type validation
    if ($file_type !== 'text/csv' && strpos($file_type, 'application/vnd.ms-excel') === false) { // Some systems might report CSV as application/vnd.ms-excel
        $response['message'] = 'Invalid file type. Please upload a CSV file.';
        echo json_encode($response);
        exit;
    }

    $handle = fopen($file_tmp_path, 'r');
    if ($handle === FALSE) {
        $response['message'] = 'Could not open the uploaded CSV file.';
        echo json_encode($response);
        exit;
    }

    // Get the header row
    $header = fgetcsv($handle);
    if ($header === FALSE) {
        $response['message'] = 'Could not read header from CSV file.';
        fclose($handle);
        echo json_encode($response);
        exit;
    }

    // Trim whitespace from header column names
    $header = array_map('trim', $header);

    // Define required columns and their expected mapping
    $required_columns = [
        'hs_code',
        'category',
        'commodity_name',
        'variety'
    ];

    // Check if all required columns are present
    $missing_columns = array_diff($required_columns, $header);
    if (!empty($missing_columns)) {
        $response['message'] = 'Missing required CSV columns: ' . implode(', ', $missing_columns) . '. Please ensure your CSV has these columns.';
        fclose($handle);
        echo json_encode($response);
        exit;
    }

    $imported_count = 0;
    $skipped_count = 0;
    $errors = [];

    // Prepare statements outside the loop for efficiency
    $get_category_id_stmt = $con->prepare("SELECT id FROM commodity_categories WHERE name = ?");
    $check_duplicate_stmt = $con->prepare("SELECT id FROM commodities WHERE category_id = ? AND commodity_name = ? AND variety = ?");
    $insert_commodity_stmt = $con->prepare("INSERT INTO commodities (hs_code, category_id, commodity_name, variety, units, commodity_alias, country, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    // Ensure statements were prepared successfully
    if (!$get_category_id_stmt || !$check_duplicate_stmt || !$insert_commodity_stmt) {
        $response['message'] = "Database statement preparation failed: " . $con->error;
        fclose($handle);
        echo json_encode($response);
        exit;
    }

    // Loop through each row of the CSV
    while (($row = fgetcsv($handle)) !== FALSE) {
        // Combine header with row data into an associative array
        $data = array_combine($header, $row);

        $hs_code = trim($data['hs_code'] ?? '');
        $category_name = trim($data['category'] ?? '');
        $commodity_name = trim($data['commodity_name'] ?? '');
        $variety = trim($data['variety'] ?? '');
        $units_json_input = trim($data['units'] ?? '[]'); // Optional, default to empty JSON array
        $commodity_alias_json_input = trim($data['commodity_alias'] ?? '[]'); // Optional, default to empty JSON array
        $country_json_input = trim($data['country'] ?? '[]'); // Optional, default to empty JSON array
        $image_url = trim($data['image_url'] ?? ''); // Optional

        // Basic validation for current row data
        if (empty($hs_code) || empty($category_name) || empty($commodity_name)) {
            $errors[] = "Skipping row due to missing required data (HS Code, Category, or Commodity Name).";
            $skipped_count++;
            continue;
        }

        // Get category_id
        $category_id = null;
        $get_category_id_stmt->bind_param('s', $category_name);
        $get_category_id_stmt->execute();
        $cat_result = $get_category_id_stmt->get_result();
        if ($cat_row = $cat_result->fetch_assoc()) {
            $category_id = $cat_row['id'];
        } else {
            $errors[] = "Skipping row (HS: {$hs_code}) because category '{$category_name}' does not exist. Please add this category first.";
            $skipped_count++;
            continue;
        }

        // Check for duplicate commodity (same category, commodity name, and variety)
        $check_duplicate_stmt->bind_param('iss', $category_id, $commodity_name, $variety);
        $check_duplicate_stmt->execute();
        $duplicate_result = $check_duplicate_stmt->get_result();
        if ($duplicate_result->num_rows > 0) {
            $errors[] = "Skipping row (HS: {$hs_code}) due to duplicate commodity (same category, name, variety).";
            $skipped_count++;
            continue;
        }

        // Validate and decode JSON fields
        $units_json = $units_json_input;
        if (!empty($units_json_input) && !is_array(json_decode($units_json_input, true))) {
            $errors[] = "Skipping row (HS: {$hs_code}) due to invalid 'units' JSON format.";
            $skipped_count++;
            continue;
        }

        $commodity_alias_json = $commodity_alias_json_input;
        if (!empty($commodity_alias_json_input) && !is_array(json_decode($commodity_alias_json_input, true))) {
            $errors[] = "Skipping row (HS: {$hs_code}) due to invalid 'commodity_alias' JSON format.";
            $skipped_count++;
            continue;
        }
        
        $country_json = $country_json_input;
        if (!empty($country_json_input) && !is_array(json_decode($country_json_input, true))) {
            $errors[] = "Skipping row (HS: {$hs_code}) due to invalid 'country' JSON format.";
            $skipped_count++;
            continue;
        }

        // Insert into database
        $insert_commodity_stmt->bind_param(
            'sississs',
            $hs_code,
            $category_id,
            $commodity_name,
            $variety,
            $units_json,
            $commodity_alias_json,
            $country_json,
            $image_url
        );

        if ($insert_commodity_stmt->execute()) {
            $imported_count++;
        } else {
            $errors[] = "Failed to insert row (HS: {$hs_code}): " . $insert_commodity_stmt->error;
            error_log("CSV Import Error: " . $insert_commodity_stmt->error);
            $skipped_count++;
        }
    }

    fclose($handle);

    $get_category_id_stmt->close();
    $check_duplicate_stmt->close();
    $insert_commodity_stmt->close();
    mysqli_close($con);

    if ($imported_count > 0) {
        $response['success'] = true;
        $response['message'] = "Successfully imported {$imported_count} commodities. ";
        if ($skipped_count > 0) {
            $response['message'] .= "{$skipped_count} rows were skipped due to errors or duplicates.";
        }
    } else {
        $response['message'] = "No commodities were imported. " . ($skipped_count > 0 ? "{$skipped_count} rows were skipped. " : "") . "Please check the CSV file format and content.";
    }

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

} else {
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

echo json_encode($response);
exit;
?>