<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Home | RATIN Trade Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary-color: #8B4513;
            --primary-light: #f5d6c6;
            --secondary-color: #2c3e50;
            --accent-color: #e67e22;
            --text-dark: #333;
            --text-medium: #555;
            --text-light: #777;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --error-color: #e74c3c;
            --transition-speed: 0.3s;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .landing-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 30px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        /* Welcome Section */
        .welcome-section {
            text-align: center;
            margin-bottom: 50px;
            padding: 40px 30px;
            background: linear-gradient(135deg, var(--primary-light) 0%, #f8e9e1 100%);
            border-radius: 12px;
            color: var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: "";
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
        }

        .welcome-section::after {
            content: "";
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
        }

        .welcome-section h1 {
            margin: 0 0 15px 0;
            font-size: 2.8em;
            font-weight: 700;
            color: var(--primary-color);
            position: relative;
            z-index: 1;
        }

        .welcome-section p {
            font-size: 1.2em;
            max-width: 800px;
            margin: 0 auto;
            color: var(--text-dark);
            position: relative;
            z-index: 1;
        }

        /* Quick Stats Bar */
        .quick-stats {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            min-width: 180px;
        }

        .stat-value {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9em;
            color: var(--text-medium);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Sections Grid */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .section-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            padding: 30px;
            transition: all var(--transition-speed) ease;
            display: flex;
            flex-direction: column;
            border-left: 4px solid var(--primary-color);
            min-height: 280px;
        }

        .section-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .section-card h3 {
            color: var(--primary-color);
            font-size: 1.5em;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(139, 69, 19, 0.1);
        }

        .section-card h3 i {
            margin-right: 12px;
            font-size: 1.2em;
            color: var(--accent-color);
        }

        .section-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .section-card ul li {
            margin-bottom: 12px;
            position: relative;
        }

        .section-card ul li:last-child {
            margin-bottom: 0;
        }

        .section-card ul li a {
            text-decoration: none;
            color: var(--text-medium);
            font-size: 1.1em;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all var(--transition-speed) ease;
            background: rgba(0,0,0,0.02);
        }

        .section-card ul li a:hover {
            background-color: rgba(139, 69, 19, 0.08);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .section-card ul li a i {
            margin-right: 12px;
            color: var(--text-light);
            width: 20px;
            text-align: center;
            transition: color var(--transition-speed) ease;
        }

        .section-card ul li a:hover i {
            color: var(--accent-color);
        }

        /* Badges for new/updated features */
        .badge {
            display: inline-block;
            background-color: var(--accent-color);
            color: white;
            font-size: 0.7em;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* Placeholder links */
        .placeholder-link {
            color: var(--text-light);
            cursor: default;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            font-size: 1.1em;
            opacity: 0.7;
        }

        /* Recent Activity Section */
        .recent-activity {
            margin-top: 50px;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .recent-activity h2 {
            color: var(--secondary-color);
            margin-bottom: 20px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
        }

        .recent-activity h2 i {
            margin-right: 15px;
            color: var(--accent-color);
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2em;
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-text {
            font-size: 1em;
            color: var(--text-dark);
        }

        .activity-time {
            font-size: 0.85em;
            color: var(--text-light);
            margin-top: 3px;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .landing-container {
                padding: 25px;
            }
            
            .welcome-section h1 {
                font-size: 2.4em;
            }
        }

        @media (max-width: 768px) {
            .landing-container {
                margin: 10px;
                padding: 20px;
            }
            
            .welcome-section {
                padding: 30px 20px;
            }
            
            .welcome-section h1 {
                font-size: 2em;
            }
            
            .welcome-section p {
                font-size: 1em;
            }
            
            .sections-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-item {
                min-width: 120px;
            }
        }

        @media (max-width: 480px) {
            .welcome-section h1 {
                font-size: 1.8em;
            }
            
            .quick-stats {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="welcome-section">
            <h3>Welcome to RATIN Trade Analytics</h3>
            <p>Your comprehensive platform for managing cross-border trade data, market intelligence, and regional price monitoring.</p>
        </div>

        <div class="sections-grid">
            <!-- Base Management Card -->
            <div class="section-card">
                <h3><i class="fas fa-table"></i> Base Management</h3>
                <ul>
                    <li>
                        <a href="#" onclick="loadContent('commodities_boilerplate.php', 'Base', 'Commodities'); return false;">
                            <i class="fas fa-apple-alt"></i> Commodities
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('tradepoints_boilerplate.php', 'Base', 'Trade Points'); return false;">
                            <i class="fas fa-map-marker-alt"></i> Trade Points
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('enumerator_boilerplate.php', 'Base', 'Enumerators'); return false;">
                            <i class="fas fa-user-tie"></i> Enumerators
                            <span class="badge">Updated</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Data Management Card -->
            <div class="section-card">
                <h3><i class="fas fa-chart-line"></i> Data Management</h3>
                <ul>
                    <li>
                        <a href="#" onclick="loadContent('../data/marketprices_boilerplate.php', 'Data', 'Market Prices'); return false;">
                            <i class="fas fa-store-alt"></i> Market Prices
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('../data/datasource_boilerplate.php', 'Data', 'Data Sources'); return false;">
                            <i class="fas fa-database"></i> Data Sources
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('../data/xbtvol_boilerplate.php', 'Data', 'XBT Volumes'); return false;">
                            <i class="fas fa-exchange-alt"></i> XBT Volumes
                            <span class="badge">New</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('../data/miller_price_boilerplate.php', 'Data', 'Miller Prices'); return false;">
                            <i class="fas fa-industry"></i> Miller Prices
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('../data/currencies_boilerplate.php', 'Data', 'Currency Rates'); return false;">
                            <i class="fas fa-money-bill-wave"></i> Currency Rates
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Reports & Analytics Card -->
            <div class="section-card">
                <h3><i class="fas fa-chart-pie"></i> Reports & Analytics</h3>
                <ul>
                    <li>
                        <a href="#" onclick="loadContent('../reports/price_trends.php', 'Reports', 'Price Trends'); return false;">
                            <i class="fas fa-chart-line"></i> Price Trends
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('../reports/trade_flows.php', 'Reports', 'Trade Flows'); return false;">
                            <i class="fas fa-project-diagram"></i> Trade Flows
                        </a>
                    </li>
                    <li>
                        <span class="placeholder-link">
                            <i class="fas fa-file-export"></i> Export Reports (Coming Soon)
                        </span>
                    </li>
                </ul>
            </div>

            <!-- User & Admin Card -->
            <div class="section-card">
                <h3><i class="fas fa-user-cog"></i> User & Admin</h3>
                <ul>
                    <li>
                        <a href="#" onclick="loadContent('../user/profile.php', 'User', 'Profile'); return false;">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="loadContent('../user/settings.php', 'User', 'Settings'); return false;">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a href="#" onclick="logout(); return false;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

    </div>

    <script>
        // This would be in your main JS file
        function logout() {
            // Implement logout functionality
            console.log("Logout initiated");
            // window.location.href = '/logout.php';
        }
    </script>
</body>
</html>