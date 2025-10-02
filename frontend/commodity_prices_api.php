<?php
// commodity_prices_weekly_avg_fixed.php

include '../admin/includes/config.php';
header('Content-Type: application/json');

class WeeklyAvgCommodityPricesAPI {
    private $con;
    private $eaCountries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Ethiopia'];
    
    public function __construct($connection) {
        $this->con = $connection;
    }
    
    public function getCommodityPrices($params = []) {
        $daysBack = $params['days'] ?? 7; // Default to last 7 days
        $limit = $params['limit'] ?? 3;
        $category = $params['category'] ?? null;
        
        $commodityGroups = [];
        $categories = $this->getCommodityCategories($category);
        
        foreach ($categories as $cat) {
            $categoryGroups = $this->buildCategoryGroups($cat, $daysBack, $limit);
            if (!empty($categoryGroups)) {
                $commodityGroups = array_merge($commodityGroups, $categoryGroups);
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'commodityGroups' => $commodityGroups,
                'metadata' => [
                    'period' => 'last_' . $daysBack . '_days',
                    'totalGroups' => count($commodityGroups)
                ]
            ]
        ];
    }
    
    private function buildCategoryGroups($category, $daysBack, $limit = 3) {
        $categoryGroups = [];
        $commodities = $this->getCommoditiesByCategory($category['id'], $limit);
        
        foreach ($commodities as $commodity) {
            $countryPrices = $this->getCountryPrices($commodity['id'], $daysBack);
            
            if ($this->hasPriceData($countryPrices)) {
                $categoryGroups[] = [
                    'title' => $commodity['name'] . " Prices",
                    'category' => $category['name'],
                    'icon' => "/assets/icons/" . $this->generateIconName($commodity['name']) . ".svg",
                    'items' => $countryPrices
                ];
            }
        }
        
        return $categoryGroups;
    }
    
    private function getCountryPrices($commodityId, $daysBack) {
        $countriesData = [];
        
        foreach ($this->eaCountries as $country) {
            $countriesData[] = [
                'country' => $country,
                'prices' => [
                    'wholesale' => $this->getWeeklyPriceData($commodityId, $country, 'Wholesale', $daysBack),
                    'retail' => $this->getWeeklyPriceData($commodityId, $country, 'Retail', $daysBack)
                ]
            ];
        }
        
        return $countriesData;
    }
    
    private function getWeeklyPriceData($commodityId, $country, $priceType, $daysBack) {
        $currentAvg = $this->getWeeklyAveragePrice($commodityId, $country, $priceType, $daysBack, 0);
        $previousAvg = $this->getWeeklyAveragePrice($commodityId, $country, $priceType, $daysBack, $daysBack);
        
        $change = 0;
        if ($previousAvg !== null && $currentAvg !== null && $previousAvg > 0) {
            $change = round((($currentAvg - $previousAvg) / $previousAvg) * 100, 2);
        }
        
        $eaAvg = $this->getEAWeeklyAveragePrice($commodityId, $priceType, $daysBack, 0);
        
        return [
            'countryPrice' => $currentAvg,
            'eaPrice' => $eaAvg,
            'change' => $change
        ];
    }
    
    private function getWeeklyAveragePrice($commodityId, $country, $priceType, $daysBack, $offsetDays) {
        $endDate = date('Y-m-d', strtotime("-$offsetDays days"));
        $startDate = date('Y-m-d', strtotime("-$offsetDays days -$daysBack days"));
        
        $sql = "SELECT AVG(p.Price) as avg_price, COUNT(*) as data_points 
                FROM market_prices p 
                WHERE p.commodity = ? 
                AND p.country_admin_0 = ? 
                AND p.price_type = ? 
                AND DATE(p.date_posted) BETWEEN ? AND ? 
                AND p.status IN ('published', 'approved')";
        
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("issss", $commodityId, $country, $priceType, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['data_points'] > 0 ? round($row['avg_price'], 2) : null;
        }
        
        return null;
    }
    
    private function getEAWeeklyAveragePrice($commodityId, $priceType, $daysBack, $offsetDays) {
        $endDate = date('Y-m-d', strtotime("-$offsetDays days"));
        $startDate = date('Y-m-d', strtotime("-$offsetDays days -$daysBack days"));
        
        // Create placeholders for countries
        $placeholders = str_repeat('?,', count($this->eaCountries) - 1) . '?';
        
        $sql = "SELECT AVG(p.Price) as avg_price, COUNT(*) as data_points 
                FROM market_prices p 
                WHERE p.commodity = ? 
                AND p.price_type = ? 
                AND p.country_admin_0 IN ($placeholders)
                AND DATE(p.date_posted) BETWEEN ? AND ? 
                AND p.status IN ('published', 'approved')";
        
        $stmt = $this->con->prepare($sql);
        
        // Build parameters: commodityId, priceType, countries..., startDate, endDate
        $params = array_merge([$commodityId, $priceType], $this->eaCountries, [$startDate, $endDate]);
        
        // Build types string: i (commodityId), s (priceType), s... for countries, s (startDate), s (endDate)
        $types = 'is' . str_repeat('s', count($this->eaCountries)) . 'ss';
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['data_points'] > 0 ? round($row['avg_price'], 2) : null;
        }
        
        return null;
    }
    
    private function getCommodityCategories($specificCategory = null) {
        $categories = [];
        
        $sql = "SELECT id, name FROM commodity_categories 
                WHERE name IN ('Cereals', 'Pulses', 'Oil seeds')";
        
        if ($specificCategory) {
            $sql .= " AND name = ?";
        }
        
        $sql .= " ORDER BY name";
        
        $stmt = $this->con->prepare($sql);
        if ($specificCategory) {
            $stmt->bind_param("s", $specificCategory);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        return $categories;
    }
    
    private function getCommoditiesByCategory($categoryId, $limit = 3) {
        $commodities = [];
        
        $sql = "SELECT id, commodity_name 
                FROM commodities 
                WHERE category_id = ? 
                ORDER BY commodity_name 
                LIMIT ?";
        
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $categoryId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $commodities[] = [
                'id' => $row['id'],
                'name' => $row['commodity_name']
            ];
        }
        
        return $commodities;
    }
    
    private function hasPriceData($countryPrices) {
        foreach ($countryPrices as $countryData) {
            if ($countryData['prices']['wholesale']['countryPrice'] !== null || 
                $countryData['prices']['retail']['countryPrice'] !== null) {
                return true;
            }
        }
        return false;
    }
    
    private function generateIconName($commodityName) {
        $iconName = strtolower(str_replace(' ', '_', $commodityName));
        $specialCases = [
            'oil seeds' => 'oilseeds',
            'green gram' => 'green_gram',
            'groundnuts shelled' => 'groundnuts'
        ];
        
        return $specialCases[$commodityName] ?? $iconName;
    }
}

// Handle API request
try {
    $api = new WeeklyAvgCommodityPricesAPI($con);
    
    $params = $_GET;
    $response = $api->getCommodityPrices($params);
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}

$con->close();
?>