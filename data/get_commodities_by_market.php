<?php
// get_commodities_by_market.php
include '../admin/includes/config.php'; // Adjust path as needed

header('Content-Type: application/json');

$response = ['success' => false, 'data' => [], 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['market_id'])) {
    $market_id = intval($_GET['market_id']); // Ensure it's an integer for safety

    if ($market_id > 0) {
        // Query to fetch:
        // - commodity ID and name (from commodities table)
        // - category NAME (JOIN from commodity_categories table using commodity.category_id)
        // - data source (from markets table)
        $stmt = $con->prepare("SELECT
                                    c.id AS commodity_id,
                                    c.commodity_name,
                                    cc.name AS category_name,       -- Corrected: 'cc.name' from 'commodity_categories'
                                    m.additional_datasource         -- Fetched from the markets table
                                FROM
                                    markets m
                                JOIN
                                    commodities c ON m.primary_commodity = c.id
                                JOIN
                                    commodity_categories cc ON c.category_id = cc.id -- Corrected: JOIN with 'commodity_categories'
                                WHERE
                                    m.id = ?");
        $stmt->bind_param("i", $market_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['success'] = true;
            $response['data'] = [
                'commodity_id' => $row['commodity_id'],
                'commodity_name' => $row['commodity_name'],
                'category' => $row['category_name'],         // This key matches what the JS expects
                'data_source' => $row['additional_datasource']
            ];
        } else {
            $response['success'] = true; // Still success, but no data found for market or related commodity/category
            $response['message'] = 'No details found for this market, or related commodity/category.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid market ID.';
    }
} else {
    $response['message'] = 'Invalid request. Market ID is required.';
}

echo json_encode($response);
$con->close();
?>