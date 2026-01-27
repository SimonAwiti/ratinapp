<?php
// base/market_submission_monitoring.php

// Start session at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header
include '../admin/includes/header.php';

// Default to today's date if not specified
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Calculate date range for the week
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($selected_date)));

// Get all markets
$markets_query = "SELECT id, market_name, county_district, country FROM markets ORDER BY market_name";
$markets_result = $con->query($markets_query);
$all_markets = [];
if ($markets_result) {
    while ($market = $markets_result->fetch_assoc()) {
        $all_markets[] = $market;
    }
}

// Function to get submissions for a specific date
function getSubmissionsForDate($con, $date) {
    $submissions = [];
    
    try {
        // Get all submissions for the date
        $query = "SELECT 
                    mp.market_id,
                    mp.market,
                    COUNT(mp.id) as submission_count,
                    GROUP_CONCAT(DISTINCT mp.commodity) as commodities,
                    MIN(mp.date_posted) as first_submission,
                    MAX(mp.date_posted) as last_submission
                  FROM market_prices mp
                  WHERE DATE(mp.date_posted) = ?
                  GROUP BY mp.market_id, mp.market
                  ORDER BY mp.market";
        
        $stmt = $con->prepare($query);
        if ($stmt) {
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $submissions[$row['market_id']] = $row;
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error in getSubmissionsForDate: " . $e->getMessage());
    }
    
    return $submissions;
}

// Get submissions for selected date
$submissions = getSubmissionsForDate($con, $selected_date);

// Function to get weekly submission summary
function getWeeklySubmissionSummary($con, $start_date, $end_date) {
    $weekly_summary = [];
    
    try {
        $query = "SELECT 
                    DATE(date_posted) as submission_date,
                    COUNT(DISTINCT market_id) as markets_count,
                    COUNT(id) as total_submissions,
                    COUNT(DISTINCT commodity) as commodities_count
                  FROM market_prices
                  WHERE DATE(date_posted) BETWEEN ? AND ?
                  GROUP BY DATE(date_posted)
                  ORDER BY submission_date";
        
        $stmt = $con->prepare($query);
        if ($stmt) {
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $weekly_summary[$row['submission_date']] = $row;
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error in getWeeklySubmissionSummary: " . $e->getMessage());
    }
    
    return $weekly_summary;
}

// Get weekly summary
$weekly_summary = getWeeklySubmissionSummary($con, $week_start, $week_end);

// Function to get market submission statistics - CORRECTED VERSION
function getMarketStatistics($con) {
    $statistics = [];
    
    try {
        // Overall statistics - FIXED QUERY
        $stats_query = "SELECT 
                         (SELECT COUNT(DISTINCT DATE(date_posted)) FROM market_prices) as active_days,
                         (SELECT COUNT(DISTINCT market_id) FROM market_prices) as active_markets,
                         (SELECT COUNT(*) FROM market_prices) as total_submissions,
                         COALESCE(
                           (SELECT AVG(daily_count) FROM 
                             (SELECT COUNT(*) as daily_count 
                              FROM market_prices 
                              GROUP BY DATE(date_posted)) as daily_counts), 
                           0
                         ) as avg_daily_submissions";
        
        $result = $con->query($stats_query);
        if ($result) {
            $statistics['overall'] = $result->fetch_assoc();
        } else {
            $statistics['overall'] = [
                'active_days' => 0,
                'active_markets' => 0,
                'total_submissions' => 0,
                'avg_daily_submissions' => 0
            ];
        }
        
        // Ensure avg_daily_submissions is not null
        if (!isset($statistics['overall']['avg_daily_submissions']) || $statistics['overall']['avg_daily_submissions'] === null) {
            $statistics['overall']['avg_daily_submissions'] = 0;
        }
        
        // Recent submissions (last 7 days)
        $recent_query = "SELECT 
                          DATE(date_posted) as submission_date,
                          COUNT(DISTINCT market_id) as markets_count,
                          COUNT(*) as submissions_count
                        FROM market_prices
                        WHERE date_posted >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY DATE(date_posted)
                        ORDER BY submission_date DESC";
        
        $result = $con->query($recent_query);
        $statistics['recent'] = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $statistics['recent'][] = $row;
            }
        }
        
        // Top performing markets
        $top_markets_query = "SELECT 
                              market_id,
                              market,
                              COUNT(*) as submission_count,
                              COUNT(DISTINCT DATE(date_posted)) as active_days
                            FROM market_prices
                            GROUP BY market_id, market
                            ORDER BY active_days DESC, submission_count DESC
                            LIMIT 5";
        
        $result = $con->query($top_markets_query);
        $statistics['top_markets'] = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $statistics['top_markets'][] = $row;
            }
        }
        
    } catch (Exception $e) {
        // Return empty statistics on error
        error_log("Error in getMarketStatistics: " . $e->getMessage());
        $statistics['overall'] = [
            'active_days' => 0,
            'active_markets' => 0,
            'total_submissions' => 0,
            'avg_daily_submissions' => 0
        ];
        $statistics['recent'] = [];
        $statistics['top_markets'] = [];
    }
    
    return $statistics;
}

// Get market statistics
$market_statistics = getMarketStatistics($con);

// Function to get submission timeline for selected date
function getSubmissionTimeline($con, $date) {
    $timeline = [];
    
    try {
        $query = "SELECT 
                    HOUR(date_posted) as hour,
                    COUNT(*) as submissions_count,
                    COUNT(DISTINCT market_id) as markets_count
                  FROM market_prices
                  WHERE DATE(date_posted) = ?
                  GROUP BY HOUR(date_posted)
                  ORDER BY hour";
        
        $stmt = $con->prepare($query);
        if ($stmt) {
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $timeline[$row['hour']] = $row;
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error in getSubmissionTimeline: " . $e->getMessage());
    }
    
    // Fill in missing hours
    $complete_timeline = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $complete_timeline[$hour] = isset($timeline[$hour]) ? $timeline[$hour] : [
            'hour' => $hour,
            'submissions_count' => 0,
            'markets_count' => 0
        ];
    }
    
    return $complete_timeline;
}

// Get submission timeline
$submission_timeline = getSubmissionTimeline($con, $selected_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Submission Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Main Container */
        .monitoring-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.success {
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 5px 0;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Date Picker Section */
        .date-picker-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .date-picker-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .date-input-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-input {
            flex: 1;
            min-width: 200px;
        }

        .date-input input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .date-input input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .week-navigation {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }

        .week-btn {
            padding: 8px 16px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            color: #495057;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .week-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Market Status Grid */
        .market-status-section {
            margin-bottom: 40px;
        }

        .section-title {
            color: #2c3e50;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .market-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .market-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .market-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #28a745;
        }

        .market-card.no-submission::before {
            background: #dc3545;
        }

        .market-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .market-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .market-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
            flex: 1;
        }

        .submission-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-submitted {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-pending {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .market-details {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .market-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #495057;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-weight: 700;
            color: #2c3e50;
            font-size: 14px;
        }

        .stat-label-small {
            font-size: 11px;
            opacity: 0.7;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .chart-title {
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Timeline Chart */
        .timeline-chart {
            height: 200px;
            position: relative;
        }

        .timeline-bar {
            height: 30px;
            background: #f8f9fa;
            border-radius: 4px;
            margin: 5px 0;
            overflow: hidden;
            position: relative;
        }

        .timeline-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 0.5s ease;
            min-width: 2px;
        }

        .timeline-hour {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            display: flex;
            align-items: center;
            padding-left: 10px;
            font-size: 12px;
            color: #495057;
            font-weight: 500;
        }

        .timeline-value {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: white;
            font-weight: 600;
        }

        /* Weekly Calendar */
        .weekly-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .day-cell {
            text-align: center;
            padding: 15px 5px;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .day-cell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .day-cell.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .day-cell.has-data {
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid #28a745;
        }

        .day-name {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 5px;
        }

        .day-date {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .day-stats {
            font-size: 11px;
            opacity: 0.8;
        }

        /* Summary Cards */
        .summary-section {
            margin-top: 40px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .summary-title {
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .top-market-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .top-market-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .top-market-item:last-child {
            border-bottom: none;
        }

        .market-rank {
            width: 24px;
            height: 24px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .top-market-name {
            flex: 1;
            padding: 0 15px;
            font-weight: 500;
        }

        .top-market-stats {
            font-size: 12px;
            color: #6c757d;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action i {
            font-size: 14px;
        }

        .btn-export {
            background: #28a745;
            color: white;
        }

        .btn-export:hover {
            background: #218838;
        }

        .btn-remind {
            background: #ffc107;
            color: #212529;
        }

        .btn-remind:hover {
            background: #e0a800;
        }

        .btn-report {
            background: #17a2b8;
            color: white;
        }

        .btn-report:hover {
            background: #138496;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .market-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .weekly-calendar {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .date-input-group {
                flex-direction: column;
            }
            
            .date-input {
                width: 100%;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        .loading {
            animation: pulse 1.5s infinite;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        /* Tooltips */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 30px;
        }

        .text-wrapper-8 h3 {
            color: #2c3e50;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stats-section .p {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="stats-section">
            <div class="text-wrapper-8"><h3>Market Prices Monitoring Dashboard</h3></div>
            <p class="p">Track daily submissions, monitor market activity, and identify gaps in data collection</p>
        </div>

        <div class="container">
            <div class="monitoring-container">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-value"><?= htmlspecialchars(count($all_markets)) ?></div>
                        <div class="stat-label">Total Markets</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= htmlspecialchars(count($submissions)) ?></div>
                        <div class="stat-label">Markets Submitted Today</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?= htmlspecialchars(count($all_markets) - count($submissions)) ?></div>
                        <div class="stat-label">Markets Pending</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value"><?= htmlspecialchars($market_statistics['overall']['active_days'] ?? 0) ?></div>
                        <div class="stat-label">Active Days</div>
                    </div>
                </div>

                <!-- Date Picker Section -->
                <div class="date-picker-section">
                    <h4>Select Date to Monitor</h4>
                    <form method="GET" action="" id="dateForm">
                        <div class="date-input-group">
                            <div class="date-input">
                                <input type="date" name="date" id="selectedDate" 
                                       value="<?= htmlspecialchars($selected_date) ?>" 
                                       max="<?= date('Y-m-d') ?>"
                                       onchange="document.getElementById('dateForm').submit()">
                            </div>
                            <button type="submit" class="btn-action btn-export">
                                <i class="fas fa-search"></i> View Date
                            </button>
                        </div>
                    </form>
                    
                    <div class="week-navigation">
                        <button class="week-btn" onclick="navigateToDate('<?= date('Y-m-d', strtotime($selected_date . ' -1 day')) ?>')">
                            <i class="fas fa-chevron-left"></i> Previous Day
                        </button>
                        <span style="flex: 1; text-align: center; color: #495057; font-weight: 500;">
                            <?= htmlspecialchars(date('l, F j, Y', strtotime($selected_date))) ?>
                        </span>
                        <?php if ($selected_date < date('Y-m-d')): ?>
                        <button class="week-btn" onclick="navigateToDate('<?= date('Y-m-d', strtotime($selected_date . ' +1 day')) ?>')">
                            Next Day <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Market Status Grid -->
                <div class="market-status-section">
                    <div class="section-title">Market Submission Status for <?= htmlspecialchars(date('F j, Y', strtotime($selected_date))) ?></div>
                    
                    <?php if (empty($all_markets)): ?>
                        <div class="empty-state">
                            <i class="fas fa-store-slash"></i>
                            <h4>No Markets Found</h4>
                            <p>Add markets to the system to start monitoring submissions.</p>
                        </div>
                    <?php else: ?>
                        <div class="market-grid">
                            <?php foreach ($all_markets as $market): 
                                $has_submission = isset($submissions[$market['id']]);
                                $submission_data = $has_submission ? $submissions[$market['id']] : null;
                            ?>
                            <div class="market-card <?= $has_submission ? '' : 'no-submission' ?>" 
                                 data-tooltip="<?= $has_submission ? 'Click to view details' : 'No submissions today' ?>"
                                 onclick="<?= $has_submission ? "showMarketDetails({$market['id']}, '" . htmlspecialchars($selected_date) . "')" : '' ?>">
                                <div class="market-header">
                                    <div class="market-name"><?= htmlspecialchars($market['market_name']) ?></div>
                                    <div class="submission-status <?= $has_submission ? 'status-submitted' : 'status-pending' ?>">
                                        <?= $has_submission ? 'Submitted' : 'Pending' ?>
                                    </div>
                                </div>
                                
                                <div class="market-details">
                                    <div><?= htmlspecialchars($market['county_district']) ?>, <?= htmlspecialchars($market['country']) ?></div>
                                </div>
                                
                                <?php if ($has_submission): ?>
                                <div class="market-stats">
                                    <div class="stat-item">
                                        <div class="stat-number"><?= htmlspecialchars($submission_data['submission_count']) ?></div>
                                        <div class="stat-label-small">Submissions</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number">
                                            <?= htmlspecialchars(count(explode(',', $submission_data['commodities']))) ?>
                                        </div>
                                        <div class="stat-label-small">Commodities</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number">
                                            <?= htmlspecialchars(date('H:i', strtotime($submission_data['last_submission']))) ?>
                                        </div>
                                        <div class="stat-label-small">Last Update</div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="market-stats">
                                    <div class="stat-item" style="width: 100%; text-align: center;">
                                        <div class="stat-number" style="color: #dc3545;">No Data</div>
                                        <div class="stat-label-small">Awaiting submission</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Charts Section -->
                <div class="charts-section">

                    <!-- Weekly Calendar -->
                    <div class="chart-container">
                        <div class="chart-title">Weekly Submission Calendar (<?= htmlspecialchars(date('M d', strtotime($week_start))) ?> - <?= htmlspecialchars(date('M d', strtotime($week_end))) ?>)</div>
                        <div class="weekly-calendar">
                            <?php
                            $current_date = $week_start;
                            for ($i = 0; $i < 7; $i++):
                                $day_data = isset($weekly_summary[$current_date]) ? $weekly_summary[$current_date] : null;
                                $is_active = $current_date == $selected_date;
                                $has_data = $day_data !== null;
                            ?>
                            <div class="day-cell <?= $is_active ? 'active' : '' ?> <?= $has_data ? 'has-data' : '' ?>" 
                                 onclick="navigateToDate('<?= htmlspecialchars($current_date) ?>')"
                                 data-tooltip="Click to view <?= htmlspecialchars(date('M j', strtotime($current_date))) ?>">
                                <div class="day-name"><?= htmlspecialchars(date('D', strtotime($current_date))) ?></div>
                                <div class="day-date"><?= htmlspecialchars(date('j', strtotime($current_date))) ?></div>
                                <?php if ($has_data): ?>
                                <div class="day-stats">
                                    <?= htmlspecialchars($day_data['markets_count']) ?> markets
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php
                                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                            endfor;
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="summary-section">
                    <div class="section-title">Performance Insights</div>
                    <div class="summary-grid">
                        <!-- Top Performing Markets -->
                        <div class="summary-card">
                            <div class="summary-title">Top Performing Markets</div>
                            <ul class="top-market-list">
                                <?php foreach ($market_statistics['top_markets'] as $index => $market): ?>
                                <li class="top-market-item">
                                    <div class="market-rank"><?= $index + 1 ?></div>
                                    <div class="top-market-name"><?= htmlspecialchars($market['market']) ?></div>
                                    <div class="top-market-stats">
                                        <?= htmlspecialchars($market['active_days']) ?> days, <?= htmlspecialchars($market['submission_count']) ?> submissions
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Recent Activity -->
                        <div class="summary-card">
                            <div class="summary-title">Recent Activity (Last 7 Days)</div>
                            <div style="height: 250px; overflow-y: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #e9ecef;">
                                            <th style="padding: 8px; text-align: left;">Date</th>
                                            <th style="padding: 8px; text-align: right;">Markets</th>
                                            <th style="padding: 8px; text-align: right;">Submissions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($market_statistics['recent'] as $activity): ?>
                                        <tr style="border-bottom: 1px solid #f0f0f0;">
                                            <td style="padding: 8px;"><?= htmlspecialchars(date('M j', strtotime($activity['submission_date']))) ?></td>
                                            <td style="padding: 8px; text-align: right;">
                                                <span class="badge bg-success"><?= htmlspecialchars($activity['markets_count']) ?></span>
                                            </td>
                                            <td style="padding: 8px; text-align: right;">
                                                <span class="badge bg-info"><?= htmlspecialchars($activity['submissions_count']) ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Market Details Modal -->
    <div class="modal fade" id="marketDetailsModal" tabindex="-1" aria-labelledby="marketDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="marketDetailsModalLabel">Market Submission Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="marketDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        initTooltips();
    });

    function navigateToDate(date) {
        window.location.href = `?date=${date}`;
    }

    function showMarketDetails(marketId, date) {
        // Show loading state
        document.getElementById('marketDetailsContent').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading market details...</p>
            </div>
        `;
        
        // Fetch market details via AJAX
        fetch(`get_market_details.php?market_id=${marketId}&date=${date}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('marketDetailsContent').innerHTML = data;
                const modal = new bootstrap.Modal(document.getElementById('marketDetailsModal'));
                modal.show();
            })
            .catch(error => {
                document.getElementById('marketDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error loading market details: ${error.message}
                    </div>
                `;
                const modal = new bootstrap.Modal(document.getElementById('marketDetailsModal'));
                modal.show();
            });
    }

    function exportDailyReport() {
        const date = document.getElementById('selectedDate').value;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'export_daily_report.php';
        
        const dateInput = document.createElement('input');
        dateInput.type = 'hidden';
        dateInput.name = 'date';
        dateInput.value = date;
        form.appendChild(dateInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function sendReminders() {
        const date = document.getElementById('selectedDate').value;
        if (confirm(`Send reminder emails to markets without submissions for ${date}?`)) {
            // Show loading
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            btn.disabled = true;
            
            fetch('send_reminders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ date: date })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Reminders sent to ${data.recipient_count} markets`);
                    // Refresh page to show updated status
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Error sending reminders: ' + error.message);
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }
    }

    function generateGapReport() {
        const date = document.getElementById('selectedDate').value;
        window.open(`gap_analysis_report.php?date=${date}`, '_blank');
    }

    function initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.getAttribute('data-tooltip');
                tooltip.style.position = 'absolute';
                tooltip.style.background = 'rgba(0,0,0,0.8)';
                tooltip.style.color = 'white';
                tooltip.style.padding = '6px 12px';
                tooltip.style.borderRadius = '4px';
                tooltip.style.fontSize = '12px';
                tooltip.style.zIndex = '1000';
                tooltip.style.whiteSpace = 'nowrap';
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) + 'px';
                tooltip.style.top = (rect.top - 10) + 'px';
                tooltip.style.transform = 'translateX(-50%) translateY(-100%)';
                
                document.body.appendChild(tooltip);
                this._tooltipElement = tooltip;
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltipElement) {
                    this._tooltipElement.remove();
                    this._tooltipElement = null;
                }
            });
        });
    }

    // Auto-refresh every 5 minutes if on today's date
    <?php if ($selected_date == date('Y-m-d')): ?>
    setTimeout(() => {
        location.reload();
    }, 5 * 60 * 1000); // 5 minutes
    <?php endif; ?>
    </script>
</body>
</html>