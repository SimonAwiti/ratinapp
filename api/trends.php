<?php
// api/commodity_prices.php

// Include your database configuration
include '../admin/includes/config.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to get commodity prices in the required format
function getCommodityPrices($con, $countryFilter = '', $commodityFilter = '') {
    // Build the query using the correct column names from your database
    $query = "
        SELECT 
            mp.country_admin_0 as country,
            mp.market as location,
            c.commodity_name as commodity,
            mp.Price as price,
            mp.date_posted,
            mp.price_type
        FROM market_prices mp
        JOIN commodities c ON mp.commodity = c.id
        WHERE mp.status IN ('published', 'approved')
        AND mp.date_posted IN (
            SELECT MAX(date_posted)
            FROM market_prices 
            WHERE commodity = mp.commodity 
            AND country_admin_0 = mp.country_admin_0
            AND market = mp.market
            AND price_type = mp.price_type
            AND status IN ('published', 'approved')
        )
    ";
    
    // Add filters to the query
    $conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($countryFilter)) {
        $conditions[] = "mp.country_admin_0 = ?";
        $params[] = $countryFilter;
        $types .= 's';
    }
    
    if (!empty($commodityFilter)) {
        $conditions[] = "c.commodity_name = ?";
        $params[] = $commodityFilter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY mp.country_admin_0, mp.market, c.commodity_name, mp.price_type";
    
    // Prepare and execute the query
    $stmt = $con->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return ['error' => 'Failed to fetch commodity prices: ' . $con->error];
    }
    
    $currentPrices = [];
    while ($row = $result->fetch_assoc()) {
        $currentPrices[] = $row;
    }
    $stmt->close();

    // Get historical data for trend calculation
    $trendQuery = "
        SELECT 
            mp.country_admin_0 as country,
            mp.market as location,
            c.commodity_name as commodity,
            mp.Price as price,
            mp.date_posted,
            mp.price_type
        FROM market_prices mp
        JOIN commodities c ON mp.commodity = c.id
        WHERE mp.status IN ('published', 'approved')
        ORDER BY mp.date_posted DESC
        LIMIT 2000
    ";
    
    $trendResult = $con->query($trendQuery);
    $historicalData = [];
    while ($row = $trendResult->fetch_assoc()) {
        $historicalData[] = $row;
    }

    // Calculate East Africa average prices for each commodity
    $eaAverages = calculateEAAverages($historicalData);

    // Process the data into the required format
    $commodityPrices = [];
    
    foreach ($currentPrices as $price) {
        $country = $price['country'];
        $location = $price['location'] ?? 'Market';
        $commodity = $price['commodity'];
        $currentPrice = floatval($price['price']);
        $priceType = $price['price_type'];
        
        // Only include wholesale prices for consistency, or adjust as needed
        if ($priceType !== 'Wholesale') {
            continue;
        }
        
        // Calculate East Africa difference
        $eaAvg = $eaAverages[$commodity] ?? 0;
        $eastafr = $eaAvg > 0 ? round((($currentPrice - $eaAvg) / $eaAvg) * 100) : 0;
        
        // Get trend data (last 5 most recent entries)
        $trend = getTrendData($historicalData, $country, $location, $commodity, $priceType, 5);
        
        $commodityPrices[] = [
            'country' => $country,
            'location' => $location,
            'commodity' => $commodity,
            'price' => $currentPrice,
            'eastafr' => $eastafr,
            'trend' => $trend
        ];
    }
    
    // Add EAC average entry for Maize if we have data
    if (in_array('Maize', array_column($currentPrices, 'commodity'))) {
        $maizeEAAvg = $eaAverages['Maize'] ?? 0;
        if ($maizeEAAvg > 0) {
            $maizeTrend = getEACTrendData($historicalData, 'Maize', 5);
            $commodityPrices[] = [
                'country' => 'EAC',
                'location' => 'EAGC avg',
                'commodity' => 'Maize',
                'price' => $maizeEAAvg,
                'eastafr' => -15, // You can adjust this calculation as needed
                'trend' => $maizeTrend
            ];
        }
    }

    return $commodityPrices;
}

// Function to calculate East Africa averages for each commodity
function calculateEAAverages($historicalData) {
    $eaCountries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Ethiopia', 'Burundi'];
    $commodityData = [];
    
    // Group latest prices by commodity and country (only wholesale prices)
    foreach ($historicalData as $data) {
        $country = $data['country'];
        $commodity = $data['commodity'];
        $price = floatval($data['price']);
        $date = $data['date_posted'];
        $priceType = $data['price_type'];
        
        // Only include wholesale prices and EA countries
        if (!in_array($country, $eaCountries) || $priceType !== 'Wholesale') {
            continue;
        }
        
        if (!isset($commodityData[$commodity][$country])) {
            $commodityData[$commodity][$country] = [
                'price' => $price,
                'date' => $date
            ];
        } else {
            // Keep only the latest price for each country-commodity combination
            if ($date > $commodityData[$commodity][$country]['date']) {
                $commodityData[$commodity][$country] = [
                    'price' => $price,
                    'date' => $date
                ];
            }
        }
    }
    
    // Calculate averages
    $averages = [];
    foreach ($commodityData as $commodity => $countries) {
        $total = 0;
        $count = 0;
        
        foreach ($countries as $countryData) {
            if ($countryData['price'] > 0) {
                $total += $countryData['price'];
                $count++;
            }
        }
        
        $averages[$commodity] = $count > 0 ? round($total / $count, 2) : 0;
    }
    
    return $averages;
}

// Function to get trend data for a specific commodity-location combination
function getTrendData($historicalData, $country, $location, $commodity, $priceType, $limit = 5) {
    $filteredData = array_filter($historicalData, function($item) use ($country, $location, $commodity, $priceType) {
        return $item['country'] === $country && 
               $item['location'] === $location && 
               $item['commodity'] === $commodity &&
               $item['price_type'] === $priceType;
    });
    
    // Sort by date descending and get the latest entries
    usort($filteredData, function($a, $b) {
        return strtotime($b['date_posted']) - strtotime($a['date_posted']);
    });
    
    // Get the most recent entries and reverse to show chronological order
    $trend = array_slice($filteredData, 0, $limit);
    $trend = array_reverse($trend);
    
    $trendPrices = array_map(function($item) {
        return floatval($item['price']);
    }, $trend);
    
    // If we don't have enough trend data, pad with the current price
    while (count($trendPrices) < $limit) {
        $trendPrices[] = count($trendPrices) > 0 ? $trendPrices[count($trendPrices) - 1] : 0;
    }
    
    return $trendPrices;
}

// Function to get EAC average trend data
function getEACTrendData($historicalData, $commodity, $limit = 5) {
    $eaCountries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Ethiopia', 'Burundi'];
    
    // Group by date and calculate daily averages (only wholesale prices)
    $dailyAverages = [];
    foreach ($historicalData as $data) {
        if ($data['commodity'] === $commodity && 
            in_array($data['country'], $eaCountries) &&
            $data['price_type'] === 'Wholesale') {
            
            $date = date('Y-m-d', strtotime($data['date_posted']));
            if (!isset($dailyAverages[$date])) {
                $dailyAverages[$date] = [];
            }
            $dailyAverages[$date][] = floatval($data['price']);
        }
    }
    
    // Calculate average for each day
    $averages = [];
    foreach ($dailyAverages as $date => $prices) {
        if (!empty($prices)) {
            $averages[$date] = round(array_sum($prices) / count($prices), 2);
        }
    }
    
    // Sort by date and get the most recent ones
    krsort($averages);
    $recentAverages = array_slice($averages, 0, $limit);
    ksort($recentAverages); // Sort chronologically for trend
    
    $trend = array_values($recentAverages);
    
    // If we don't have enough trend data, pad with zeros
    while (count($trend) < $limit) {
        $trend[] = count($trend) > 0 ? $trend[count($trend) - 1] : 0;
    }
    
    return $trend;
}

// Function to get available filters
function getAvailableFilters($con) {
    $filters = [
        'countries' => [],
        'commodities' => []
    ];
    
    // Get available countries
    $countriesQuery = "SELECT DISTINCT country_admin_0 as country FROM market_prices WHERE status IN ('published', 'approved') ORDER BY country_admin_0";
    $result = $con->query($countriesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['countries'][] = $row['country'];
    }
    
    // Get available commodities
    $commoditiesQuery = "SELECT DISTINCT c.commodity_name 
                         FROM commodities c 
                         JOIN market_prices mp ON c.id = mp.commodity 
                         WHERE mp.status IN ('published', 'approved') 
                         ORDER BY c.commodity_name";
    $result = $con->query($commoditiesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['commodities'][] = $row['commodity_name'];
    }
    
    return $filters;
}

// Main API logic
try {
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Get filter parameters from request
    $countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
    $commodityFilter = isset($_GET['commodity']) ? $_GET['commodity'] : '';
    
    // Get commodity prices
    $commodityPrices = getCommodityPrices($con, $countryFilter, $commodityFilter);
    
    // Get available filters
    $availableFilters = getAvailableFilters($con);
    
    if (isset($commodityPrices['error'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $commodityPrices['error']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'filters' => [
                'applied' => [
                    'country' => $countryFilter,
                    'commodity' => $commodityFilter
                ],
                'available' => $availableFilters
            ],
            'commodityPrices' => $commodityPrices,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'totalRecords' => count($commodityPrices),
            'dataInfo' => [
                'price' => 'Current wholesale price of the commodity in the specified market (KES/kg)',
                'eastafr' => 'Percentage difference between local price and East Africa average',
                'trend' => 'Last 5 most recent wholesale price entries for the commodity'
            ]
        ]);
    }
    
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