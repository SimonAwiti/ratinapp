<?php
// get_commodities_by_market.php

// Include your database configuration file
include '../admin/includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($con)) {
    $response['message'] = 'Database connection not established.';
    echo json_encode($response);
    exit;
}

if (isset($_GET['market_id']) && !empty($_GET['market_id'])) {
    $market_id = (int)$_GET['market_id'];

    if ($market_id <= 0) {
        $response['message'] = 'Invalid market ID provided.';
        echo json_encode($response);
        exit;
    }

    // --- FINAL CORRECTED SQL Query ---
    // - Uses FIND_IN_SET() for comma-separated primary_commodity IDs in 'markets' table.
    // - Joins with 'commodity_categories' table (your actual category table) to get the category name.
    // - Fetches 'additional_datasource' from the 'markets' table.
    $sql = "SELECT c.id AS commodity_id, c.commodity_name,
                   cat.name AS category_name,             -- Corrected: using 'cat.name' for category name
                   m.additional_datasource AS data_source  -- Fetches data source from markets table
            FROM markets m
            JOIN commodities c ON FIND_IN_SET(c.id, m.primary_commodity)
            JOIN commodity_categories cat ON c.category_id = cat.id -- Corrected: using 'commodity_categories'
            WHERE m.id = ?
            ORDER BY c.commodity_name ASC";

    $stmt = $con->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $market_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $commodities = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $commodities[] = $row;
            }
            $response['success'] = true;
            $response['data'] = $commodities;
        } else {
            $response['message'] = 'No commodities found for this market, or market does not exist.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Failed to prepare the SQL statement: ' . $con->error;
        error_log("Error preparing statement in get_commodities_by_market.php: " . $con->error);
    }
} else {
    $response['message'] = 'Market ID parameter is missing.';
}

echo json_encode($response);