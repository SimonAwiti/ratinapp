<?php
// api/processor_prices_detailed.php

// Include your database configuration
include '../admin/includes/config.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to get processor prices with detailed info
function getProcessorPricesDetailed($con) {
    $processorPrices = [];
    
    // Define the cities/countries
    $cities = [
        'Nairobi (Kenya)' => 'Kenya',
        'Dar el Salam (TZ)' => 'Tanzania', 
        'Kigali (Rwanda)' => 'Rwanda',
        'Addis Ababa' => 'Ethiopia',
        'Kampala (Uganda)' => 'Uganda'
    ];
    
    foreach ($cities as $cityDisplay => $country) {
        $priceData = getPriceDataByCountry($con, $country);
        
        $processorPrices[] = [
            'city' => $cityDisplay,
            'current' => $priceData['current_avg'],
            'previous' => $priceData['previous_avg'],
            'details' => [
                'current_date' => $priceData['current_date'],
                'previous_date' => $priceData['previous_date'],
                'total_records_current' => $priceData['total_current'],
                'total_records_previous' => $priceData['total_previous']
            ]
        ];
    }
    
    return $processorPrices;
}

// Function to get detailed price data for a country
function getPriceDataByCountry($con, $country) {
    $data = [
        'current_avg' => 0,
        'previous_avg' => 0,
        'current_date' => null,
        'previous_date' => null,
        'total_current' => 0,
        'total_previous' => 0
    ];
    
    // Get the two most recent dates with data for this country
    $datesQuery = "
        SELECT DISTINCT DATE(date_posted) as price_date
        FROM market_prices 
        WHERE country_admin_0 = ? 
        AND status IN ('published', 'approved')
        AND Price > 0
        ORDER BY price_date DESC 
        LIMIT 2
    ";
    
    $stmt = $con->prepare($datesQuery);
    $stmt->bind_param('s', $country);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $dates = [];
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['price_date'];
    }
    $stmt->close();
    
    if (count($dates) === 0) {
        return $data;
    }
    
    // Current period (latest date)
    $currentDate = $dates[0];
    $data['current_date'] = $currentDate;
    
    $currentQuery = "
        SELECT AVG(Price) as avg_price, COUNT(*) as total_records
        FROM market_prices 
        WHERE country_admin_0 = ? 
        AND DATE(date_posted) = ?
        AND status IN ('published', 'approved')
        AND Price > 0
    ";
    
    $stmt = $con->prepare($currentQuery);
    $stmt->bind_param('ss', $country, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $data['current_avg'] = round(floatval($row['avg_price']), 2);
        $data['total_current'] = intval($row['total_records']);
    }
    $stmt->close();
    
    // Previous period (second latest date, if available)
    if (count($dates) > 1) {
        $previousDate = $dates[1];
        $data['previous_date'] = $previousDate;
        
        $previousQuery = "
            SELECT AVG(Price) as avg_price, COUNT(*) as total_records
            FROM market_prices 
            WHERE country_admin_0 = ? 
            AND DATE(date_posted) = ?
            AND status IN ('published', 'approved')
            AND Price > 0
        ";
        
        $stmt = $con->prepare($previousQuery);
        $stmt->bind_param('ss', $country, $previousDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $data['previous_avg'] = round(floatval($row['avg_price']), 2);
            $data['total_previous'] = intval($row['total_records']);
        }
        $stmt->close();
    }
    
    return $data;
}

// Main API logic
try {
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Get processor prices
    $processorPrices = getProcessorPricesDetailed($con);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'processorPrices' => $processorPrices,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'totalCities' => count($processorPrices),
            'calculation_note' => 'Averages calculated across all commodities and price types for each country'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>