<?php
// api/market_prices_filtered.php

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

// Function to get commodity groups with prices and filters
function getCommodityGroups($con, $countryFilter = '', $commodityFilter = '', $categoryFilter = '') {
    // Build the base query
    $query = "
        SELECT 
            c.commodity_name,
            c.variety,
            mp.country_admin_0 as country,
            mp.price_type,
            mp.Price,
            mp.date_posted
        FROM market_prices mp
        JOIN commodities c ON mp.commodity = c.id
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
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY c.commodity_name, mp.country_admin_0, mp.price_type";
    
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

    // Group data by commodity category and name
    $commodityGroups = [];
    
    foreach ($pricesData as $price) {
        $commodityName = $price['commodity_name'];
        $variety = $price['variety'];
        $country = $price['country'];
        $priceType = strtolower($price['price_type']);
        $priceValue = floatval($price['Price']);
        
        // Create display name
        $displayName = $commodityName;
        if (!empty($variety)) {
            $displayName .= " (" . $variety . ")";
        }
        
        // Determine category
        $category = getCommodityCategory($commodityName);
        
        // Apply category filter if specified
        if (!empty($categoryFilter) && $category !== $categoryFilter) {
            continue;
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
                $item['prices'][$priceType]['eaPrice'] = $priceValue; // Same for now
                $item['prices'][$priceType]['change'] = 0; // Zero for now
                $found = true;
                break;
            }
        }
        
        if (!$found) {
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
            $commodityGroups[$category]['items'][$lastIndex]['prices'][$priceType]['eaPrice'] = $priceValue;
        }
    }

    // Calculate actual EA averages
    foreach ($commodityGroups as &$category) {
        foreach ($category['items'] as &$item) {
            $commodityName = $item['commodity'];
            $currentCountry = $item['country'];
            
            // Calculate EA average for this commodity (across all countries except current)
            foreach (['wholesale', 'retail'] as $priceType) {
                if ($item['prices'][$priceType]['countryPrice'] > 0) {
                    $total = 0;
                    $count = 0;
                    
                    foreach ($category['items'] as $otherItem) {
                        if ($otherItem['commodity'] === $commodityName && $otherItem['country'] !== $currentCountry) {
                            if ($otherItem['prices'][$priceType]['countryPrice'] > 0) {
                                $total += $otherItem['prices'][$priceType]['countryPrice'];
                                $count++;
                            }
                        }
                    }
                    
                    if ($count > 0) {
                        $item['prices'][$priceType]['eaPrice'] = round($total / $count, 2);
                    }
                }
            }
        }
    }

    // Convert to indexed array
    return array_values($commodityGroups);
}

// Function to get available filters (for dropdowns)
function getAvailableFilters($con) {
    $filters = [
        'countries' => [],
        'commodities' => [],
        'categories' => []
    ];
    
    // Get available countries
    $countriesQuery = "SELECT DISTINCT country_admin_0 FROM market_prices WHERE status IN ('published', 'approved') ORDER BY country_admin_0";
    $result = $con->query($countriesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['countries'][] = $row['country_admin_0'];
    }
    
    // Get available commodities
    $commoditiesQuery = "SELECT DISTINCT commodity_name FROM commodities ORDER BY commodity_name";
    $result = $con->query($commoditiesQuery);
    while ($row = $result->fetch_assoc()) {
        $filters['commodities'][] = $row['commodity_name'];
    }
    
    // Get available categories
    $filters['categories'] = ['Maize', 'Cereals', 'Pulses', 'Oil Seeds', 'Other'];
    
    return $filters;
}

function getCommodityCategory($commodityName) {
    $categories = [
        'Maize' => 'Maize',
        'Wheat' => 'Cereals',
        'Rice' => 'Cereals',
        'Sorghum' => 'Cereals',
        'Millet' => 'Cereals',
        'Beans' => 'Pulses',
        'Cowpeas' => 'Pulses',
        'Green Gram' => 'Pulses',
        'Pigeon Peas' => 'Pulses',
        'Soybeans' => 'Oil Seeds',
        'Groundnuts' => 'Oil Seeds',
        'Sunflower' => 'Oil Seeds'
    ];
    
    return $categories[$commodityName] ?? 'Other';
}

function getCategoryIcon($category) {
    $icons = [
        'Maize' => '/assets/icons/maize.svg',
        'Cereals' => '/assets/icons/cereals.svg',
        'Pulses' => '/assets/icons/pulses.svg',
        'Oil Seeds' => '/assets/icons/oil-seeds.svg',
        'Other' => '/assets/icons/default.svg'
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
                }, 0)
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