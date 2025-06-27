<?php
// api_includes.php

// It's good practice to display errors during development
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL); // Uncomment for detailed error reporting during development

// Include your database configuration file
// Assumes project structure:
// /your_project_root/
// ├── admin/
// │   └── includes/
// │       └── config.php
// └── data/
//     └── apis/
//         └── api_includes.php (this file)
//
// If your config.php is located elsewhere, adjust this path accordingly.
include '../../admin/includes/config.php'; // Corrected path: go up two directories from data/apis/

// Set content type to JSON for all API responses
header('Content-Type: application/json');

// Function to convert currency to USD using database exchange rates
function convertToUSD($amount, $country, $con) {
    if (!is_numeric($amount)) {
        error_log("Invalid amount provided to convertToUSD: " . var_export($amount, true));
        return 0;
    }

    $exchangeRate = 1; // Default to 1 (assuming 1:1 for USD or if no rate is found)

    // Check if $con is a valid mysqli object before preparing statement
    if ($con instanceof mysqli && !$con->connect_error) {
        $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? ORDER BY effective_date DESC, date_created DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $country);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $exchangeRate = (float)$row['exchange_rate'];
            } else {
                error_log("No recent exchange rate found in DB for " . $country . ". Using default rate: " . $exchangeRate);
            }
            $stmt->close();
        } else {
            error_log("Error preparing currency query for " . $country . ": " . $con->error);
        }
    } else {
        error_log("Database connection not valid in convertToUSD function. Skipping currency rate fetch.");
    }

    // Ensure exchangeRate is not zero to prevent division by zero errors.
    if ($exchangeRate == 0) {
        error_log("Exchange rate for " . $country . " is zero or invalid. Returning 0 for conversion to prevent division by zero.");
        return 0;
    }

    return round($amount / $exchangeRate, 2);
}

// Function to send a JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Ensure database connection exists *after* all functions are defined
// If $con is not set or there's a connection error, terminate gracefully.
// We are confident sendJsonResponse is defined by this point if no fatal errors occurred above.
if (!isset($con) || !($con instanceof mysqli) || $con->connect_error) {
    error_log("API: Database connection check failed - connection object invalid or not set in api_includes.php.");
    sendJsonResponse(['error' => 'Database connection failed or not properly initialized. Check your config.php path and database credentials.'], 500);
}

// All functions are defined by this point if no fatal errors occurred above.
?>