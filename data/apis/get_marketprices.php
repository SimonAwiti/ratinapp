<?php
// get_marketprices.php

// Include shared functions and database connection
require_once __DIR__ . '/api_includes.php'; // Corrected path for robustness

// Ensure database connection exists
if (!isset($con) || $con->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed'], 500);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Fetch single market price by ID
    $stmt = $con->prepare("SELECT * FROM market_prices WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            sendJsonResponse($result->fetch_assoc());
        } else {
            sendJsonResponse(['message' => 'Market price not found'], 404);
        }
        $stmt->close();
    } else {
        sendJsonResponse(['error' => 'Failed to prepare statement for fetching single market price: ' . $con->error], 500);
    }
} else {
    // Fetch all market prices
    $stmt = $con->prepare("SELECT * FROM market_prices");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $marketPrices = [];
        while ($row = $result->fetch_assoc()) {
            $marketPrices[] = $row;
        }
        sendJsonResponse($marketPrices);
        $stmt->close();
    } else {
        sendJsonResponse(['error' => 'Failed to prepare statement for fetching all market prices: ' . $con->error], 500);
    }
}

// Close database connection
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}

?>