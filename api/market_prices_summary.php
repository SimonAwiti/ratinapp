<?php
// api/market_prices_enhanced.php

// Include your database configuration
include '../admin/includes/config.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get filter parameters from request
$countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
$commodityFilter = isset($_GET['commodity']) ? $_GET['commodity'] : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Function to get commodity groups with proper categories and calculations
function getCommodityGroups($con, $countryFilter = '', $commodityFilter = '', $categoryFilter = '') {
    // Build the base query with proper joins
    $query = "
        SELECT 
            c.id as commodity_id,
            c.commodity_name,
            c.variety,
            cat.name as category_name,
            mp.country_admin_0 as country,
            mp.price_type,
            mp.Price,
            mp.date_posted
        FROM market_prices mp
        JOIN commodities c ON mp.commodity = c.id
        JOIN commodity_categories cat ON c.category_id = cat.id
        WHERE mp.status IN ('published', 'approved')
        AND mp.date_posted IN (
            SELECT MAX(date_posted)
            FROM market_prices 
            WHERE commodity = mp.commodity 
            AND country_admin_0 = mp.country_admin_0
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
    
    if (!empty($categoryFilter)) {
        $conditions[] = "cat.name = ?";
        $params[] = $categoryFilter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY cat.name, c.commodity_name, mp.country_admin_0, mp.price_type";
    
    // Prepare and execute the query
    $stmt = $con->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return ['error' => 'Failed to fetch commodities: ' . $con->error];
    }
    
    $pricesData = [];
    while ($row = $result->fetch_assoc()) {
        $pricesData[] = $row;
    }
    $stmt->close();

    // Get all data for EA average calculations
    $allDataQuery = "
        SELECT 
            c.id as commodity_id,
            c.commodity_name,
            c.variety,
            cat.name as category_name,
            mp.country_admin_0 as country,
            mp.price_type,
            mp.Price,
            mp.date_posted
        FROM market_prices mp
        JOIN commodities c ON mp.commodity = c.id
        JOIN commodity_categories cat ON c.category_id = cat.id
        WHERE mp.status IN ('published', 'approved')
        ORDER BY mp.date_posted DESC
    ";
    
    $allDataResult = $con->query($allDataQuery);
    $allPricesData = [];
    while ($row = $allDataResult->fetch_assoc()) {
        $allPricesData[] = $row;
    }

    // Group data by commodity category and name
    $commodityGroups = [];
    
    foreach ($pricesData as $price) {
        $commodityId = $price['commodity_id'];
        $commodityName = $price['commodity_name'];
        $variety = $price['variety'];
        $country = $price['country'];
        $priceType = strtolower($price['price_type']);
        $priceValue = floatval($price['Price']);
        $category = $price['category_name'];
        
        // Create display name
        $displayName = $commodityName;
        if (!empty($variety)) {
            $displayName .= " (" . $variety . ")";
        }
        
        // Find or create category group
        if (!isset($commodityGroups[$category])) {
            $commodityGroups[$category] = [
                'title' => $category . ' Prices',
                'icon' => getCategoryIcon($category),
                'items' => []
            ];
        }
        
        // Find or create commodity-country combination
        $found = false;
        foreach ($commodityGroups[$category]['items'] as &$item) {
            if ($item['country'] === $country && $item['commodity'] === $displayName) {
                $item['prices'][$priceType]['countryPrice'] = $priceValue;
                // Calculate EA price and change
                $eaPrice = calculateEAPrice($commodityId, $priceType, $allPricesData, $country);
                $change = calculatePriceChange($con, $commodityId, $country, $priceType);
                
                $item['prices'][$priceType]['eaPrice'] = $eaPrice;
                $item['prices'][$priceType]['change'] = $change;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            // Calculate EA price and change for new item
            $eaPrice = calculateEAPrice($commodityId, $priceType, $allPricesData, $country);
            $change = calculatePriceChange($con, $commodityId, $country, $priceType);
            
            $commodityGroups[$category]['items'][] = [
                'country' => $country,
                'commodity' => $displayName,
                'prices' => [
                    'wholesale' => ['countryPrice' => 0, 'eaPrice' => 0, 'change' => 0],
                    'retail' => ['countryPrice' => 0, 'eaPrice' => 0, 'change' => 0]
                ]
            ];
            
            // Set the price for the newly created item
            $lastIndex = count($commodityGroups[$category]['items']) - 1;
            $commodityGroups[$category]['items'][$lastIndex]['prices'][$priceType]['countryPrice'] = $priceValue;
            $commodityGroups[$category]['items'][$lastIndex]['prices'][$priceType]['eaPrice'] = $eaPrice;
            $commodityGroups[$category]['items'][$lastIndex]['prices'][$priceType]['change'] = $change;
        }
    }

    // Convert to indexed array
    return array_values($commodityGroups);
}

// Function to calculate East Africa average price for a commodity
function calculateEAPrice($commodityId, $priceType, $allPricesData, $excludeCountry = '') {
    $prices = [];
    $eaCountries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Ethiopia'];
    
    // Get latest prices for each country
    $countryLatestPrices = [];
    foreach ($allPricesData as $price) {
        if ($price['commodity_id'] == $commodityId && 
            strtolower($price['price_type']) == $priceType &&
            in_array($price['country'], $eaCountries) &&
            $price['country'] !== $excludeCountry) {
            
            $country = $price['country'];
            $priceDate = $price['date_posted'];
            $priceValue = floatval($price['Price']);
            
            // Only keep the latest price for each country
            if (!isset($countryLatestPrices[$country]) || $priceDate > $countryLatestPrices[$country]['date']) {
                $countryLatestPrices[$country] = [
                    'price' => $priceValue,
                    'date' => $priceDate
                ];
            }
        }
    }
    
    // Calculate average from latest prices
    $total = 0;
    $count = 0;
    foreach ($countryLatestPrices as $countryData) {
        if ($countryData['price'] > 0) {
            $total += $countryData['price'];
            $count++;
        }
    }
    
    return $count > 0 ? round($total / $count, 2) : 0;
}

// Function to calculate price change percentage
function calculatePriceChange($con, $commodityId, $country, $priceType) {
    // Get current and previous prices
    $changeQuery = "
        SELECT 
            current.Price as current_price,
            previous.Price as previous_price
        FROM (
            SELECT Price, date_posted
            FROM market_prices 
            WHERE commodity = ?
            AND country_admin_0 = ?
            AND price_type = ?
            AND status IN ('published', 'approved')
            ORDER BY date_posted DESC 
            LIMIT 1
        ) as current
        LEFT JOIN (
            SELECT Price, date_posted
            FROM market_prices 
            WHERE commodity = ?
            AND country_admin_0 = ?
            AND price_type = ?
            AND status IN ('published', 'approved')
            AND date_posted < (
                SELECT MAX(date_posted)
                FROM market_prices 
                WHERE commodity = ?
                AND country_admin_0 = ?
                AND price_type = ?
                AND status IN ('published', 'approved')
            )
            ORDER BY date_posted DESC 
            LIMIT 1
        ) as previous ON 1=1
    ";
    
    $stmt = $con->prepare($changeQuery);
    if (!$stmt) return 0;
    
    $priceTypeUpper = ucfirst($priceType);
    $stmt->bind_param('isssisisi', 
        $commodityId, $country, $priceTypeUpper,
        $commodityId, $country, $priceTypeUpper,
        $commodityId, $country, $priceTypeUpper
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $change = 0;
    if ($row = $result->fetch_assoc()) {
        $currentPrice = floatval($row['current_price']);
        $previousPrice = floatval($row['previous_price']);
        
        if ($previousPrice > 0 && $currentPrice > 0) {
            $change = (($currentPrice - $previousPrice) / $previousPrice) * 100;
        }
    }
    $stmt->close();
    
    return round($change, 2);
}

// Function to get available filters from database
function getAvailableFilters($con) {
    $filters = [
        'countries' => [],
        'commodities' => [],
        'categories' => []
    ];
    
    // Get available countries from countries table
    $countriesQuery = "SELECT country_name FROM countries WHERE status = 'active' ORDER BY country_name";
    $result = $con->query($countriesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['countries'][] = $row['country_name'];
    }
    
    // Get available commodities
    $commoditiesQuery = "SELECT DISTINCT commodity_name FROM commodities ORDER BY commodity_name";
    $result = $con->query($commoditiesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['commodities'][] = $row['commodity_name'];
    }
    
    // Get available categories from commodity_categories table
    $categoriesQuery = "SELECT name FROM commodity_categories ORDER BY name";
    $result = $con->query($categoriesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['categories'][] = $row['name'];
    }
    
    return $filters;
}

// Function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'Cereals' => '/assets/icons/cereals.svg',
        'Pulses' => '/assets/icons/pulses.svg',
        'Oil seeds' => '/assets/icons/oil-seeds.svg',
        'Maize' => '/assets/icons/maize.svg'
    ];
    
    return $icons[$category] ?? '/assets/icons/default.svg';
}

// Main API logic
try {
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Get commodity groups with prices
    $commodityGroups = getCommodityGroups($con, $countryFilter, $commodityFilter, $categoryFilter);
    
    // Get available filters
    $availableFilters = getAvailableFilters($con);
    
    if (isset($commodityGroups['error'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $commodityGroups['error']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'filters' => [
                'applied' => [
                    'country' => $countryFilter,
                    'commodity' => $commodityFilter,
                    'category' => $categoryFilter
                ],
                'available' => $availableFilters
            ],
            'data' => [
                'commodityGroups' => $commodityGroups,
                'lastUpdated' => date('Y-m-d H:i:s'),
                'totalGroups' => count($commodityGroups),
                'totalCommodities' => array_reduce($commodityGroups, function($carry, $group) {
                    return $carry + count($group['items']);
                }, 0),
                'calculation_info' => [
                    'countryPrice' => 'Average of the particular commodity in the country',
                    'eaPrice' => 'Average of the particular commodity across East Africa countries',
                    'change' => 'Percentage difference between current and previous period averages'
                ]
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