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
            --primary-dark: #6b3410;
            --secondary-color: #2c3e50;
            --accent-color: #e67e22;
            --accent-light: #f39c12;
            --text-dark: #2c3e50;
            --text-medium: #555;
            --text-light: #777;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --transition-speed: 0.3s;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        .landing-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-content h1 {
            font-size: 2.0rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-content p {
            font-size: 1.125rem;
            color: var(--text-medium);
            max-width: 100%;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--bg-color);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 1px solid rgba(139, 69, 19, 0.1);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .user-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .user-details p {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-speed) ease;
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card:nth-child(2)::before { background: linear-gradient(180deg, var(--success-color) 0%, #059669 100%); }
        .stat-card:nth-child(3)::before { background: linear-gradient(180deg, var(--info-color) 0%, #2563eb 100%); }
        .stat-card:nth-child(4)::before { background: linear-gradient(180deg, var(--warning-color) 0%, #d97706 100%); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        }

        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-medium);
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .stat-change.positive {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stat-change.negative {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        /* Main Grid - Modified for two cards per row */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .section-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            transition: all var(--transition-speed) ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 280px;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--accent-color) 100%);
            transform: scaleX(0);
            transition: transform var(--transition-speed) ease;
        }

        .section-card:hover::before {
            transform: scaleX(1);
        }

        .section-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(230, 126, 34, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-color);
            font-size: 1.125rem;
        }

        .section-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .section-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .section-card ul li {
            margin-bottom: 0.5rem;
        }

        .section-card ul li:last-child {
            margin-bottom: 0;
        }

        .section-card ul li a {
            text-decoration: none;
            color: var(--text-medium);
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            transition: all var(--transition-speed) ease;
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid transparent;
        }

        .section-card ul li a:hover {
            background: rgba(139, 69, 19, 0.05);
            color: var(--primary-color);
            transform: translateX(4px);
            border-color: rgba(139, 69, 19, 0.1);
        }

        .section-card ul li a i {
            margin-right: 0.75rem;
            width: 18px;
            text-align: center;
            color: var(--text-light);
            transition: color var(--transition-speed) ease;
        }

        .section-card ul li a:hover i {
            color: var(--primary-color);
        }

        .badge {
            font-size: 0.625rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            margin-left: auto;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.new {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .badge.updated {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        /* Activity Panel */
        .activity-panel {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            height: fit-content;
        }

        .activity-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }

        .activity-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
        }

        .activity-header i {
            margin-right: 0.75rem;
            color: var(--accent-color);
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            transition: all var(--transition-speed) ease;
        }

        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-item:hover {
            background: rgba(139, 69, 19, 0.02);
            margin: 0 -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
            border-radius: 8px;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.875rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.125rem;
        }

        /* Notification Bell */
        .notification-bell {
            position: relative;
            background: var(--card-bg);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
        }

        .notification-bell:hover {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--error-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--primary-light);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sections-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .landing-container {
                padding: 1rem;
            }
            
            .header {
                padding: 1.5rem;
            }
            
            .welcome-content h1 {
                font-size: 2rem;
            }
            
            .sections-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .welcome-content h1 {
                font-size: 1.75rem;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                padding: 0.75rem 1rem;
            }
        }

        /* Micro Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-card, .stat-card, .activity-panel {
            animation: fadeIn 0.6s ease-out;
        }

        .section-card:nth-child(2) { animation-delay: 0.1s; }
        .section-card:nth-child(3) { animation-delay: 0.2s; }
        .section-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <div class="landing-container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="welcome-content">
                    <h1>RATIN Trade Analytics</h1>
                    <p>Your comprehensive platform for managing cross-border trade data, market intelligence, and regional price monitoring.</p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-value">
                    2,847
                    <span class="stat-change positive">+12%</span>
                </div>
                <div class="stat-label">Total Records</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>
                <div class="stat-value">
                    43
                    <span class="stat-change positive">+2</span>
                </div>
                <div class="stat-label">Active Trade Points</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value">
                    18
                    <span class="stat-change positive">+3</span>
                </div>
                <div class="stat-label">Active Enumerators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value">
                    24h
                    <span class="stat-change negative">+2h</span>
                </div>
                <div class="stat-label">Last Update</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="main-grid">
            <!-- Sections Grid -->
            <div class="sections-grid">
                <!-- Base Management Card -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-table"></i>
                        </div>
                        <h3>Base Management</h3>
                    </div>
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
                                <span class="badge updated">Updated</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Data Management Card -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Data Management</h3>
                    </div>
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
                                <span class="badge new">New</span>
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
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3>Reports & Analytics</h3>
                    </div>
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
                            <a href="#" style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fas fa-file-export"></i> Export Reports
                                <span class="badge" style="background: #6b7280; color: white;">Soon</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- User & Admin Card -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <h3>User & Admin</h3>
                    </div>
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
    </div>

    <script>
        // Enhanced JavaScript functionality
        function loadContent(url, section, title) {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.style.display = 'inline-block';
            }
            
            console.log(`Loading: ${section} - ${title} from ${url}`);
            
            // Simulate loading delay
            setTimeout(() => {
                if (spinner) {
                    spinner.style.display = 'none';
                }
                // Here you would typically load the actual content
                // window.location.href = url;
            }, 1000);
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                console.log("Logout initiated");
                // window.location.href = '/logout.php';
            }
        }

        function toggleNotifications() {
            alert('Notifications panel would open here');
        }

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats on load
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach((stat, index) => {
                const value = parseInt(stat.textContent);
                if (!isNaN(value)) {
                    animateValue(stat, 0, value, 1000 + (index * 200));
                }
            });
        });

        function animateValue(element, start, end, delay) {
            setTimeout(() => {
                const range = end - start;
                const minTimer = 50;
                const stepTime = Math.abs(Math.floor(1000 / range));
                const timer = Math.max(stepTime, minTimer);
                const startTime = new Date().getTime();
                const endTime = startTime + delay;
                
                const run = () => {
                    const now = new Date().getTime();
                    const remaining = Math.max((endTime - now) / delay, 0);
                    const value = Math.round(end - (remaining * range));
                    element.childNodes[0].textContent = value.toLocaleString();
                    
                    if (value === end) {
                        clearInterval(timer);
                    }
                };
                
                const timer_id = setInterval(run, timer);
                run();
            }, 100);
        }
    </script>
</body>
</html>