<?php
// price_calculations.php - Shared calculation functions

function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $sql = "SELECT Price FROM market_prices
            WHERE commodity = ? 
            AND market = ?
            AND price_type = ?
            AND DATE(date_posted) < DATE(?)
            ORDER BY date_posted DESC
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) return 'N/A';
    
    $stmt->bind_param('isss', $commodityId, $market, $priceType, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $previousData = $result->fetch_assoc();
        $previousPrice = $previousData['Price'];
        if ($previousPrice != 0) {
            $change = (($currentPrice - $previousPrice) / $previousPrice) * 100;
            $stmt->close();
            return round($change, 2) . '%';
        }
    }
    $stmt->close();
    return 'N/A';
}

function calculateMoMChange($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $thirtyDaysAgo = date('Y-m-d', strtotime($currentDate . ' -30 days'));
    
    $sql = "SELECT Price, ABS(DATEDIFF(DATE(date_posted), ?)) as date_diff 
            FROM market_prices
            WHERE commodity = ?
            AND market = ?
            AND price_type = ?
            AND DATE(date_posted) BETWEEN DATE_SUB(?, INTERVAL 35 DAY) AND DATE_SUB(?, INTERVAL 25 DAY)
            ORDER BY date_diff ASC
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) return 'N/A';
    
    $stmt->bind_param('sissss', $thirtyDaysAgo, $commodityId, $market, $priceType, $thirtyDaysAgo, $thirtyDaysAgo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $monthAgoData = $result->fetch_assoc();
        $monthAgoPrice = $monthAgoData['Price'];
        if ($monthAgoPrice != 0) {
            $change = (($currentPrice - $monthAgoPrice) / $monthAgoPrice) * 100;
            $stmt->close();
            return round($change, 2) . '%';
        }
    }
    $stmt->close();
    return 'N/A';
}
?>