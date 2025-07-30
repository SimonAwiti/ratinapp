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
            background-image: url('https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            background-color: rgba(255, 255, 255, 0.9);
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
            position: relative;
            z-index: 1;
        }

        .welcome-content h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-content p {
            font-size: 1.25rem;
            color: var(--text-dark);
            max-width: 100%;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-speed) ease;
            border: none;
            cursor: pointer;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            opacity: 0.9;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--bg-color);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: 1px solid rgba(139, 69, 19, 0.1);
            box-shadow: var(--shadow-sm);
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
            display: flex;
            flex-direction: column;
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
            margin-top: auto;
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

        /* Footer */
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-light);
            font-size: 0.875rem;
            margin-top: 2rem;
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

        .section-card, .stat-card {
            animation: fadeIn 0.6s ease-out;
        }

        .section-card:nth-child(2) { animation-delay: 0.1s; }
        .section-card:nth-child(3) { animation-delay: 0.2s; }
        .section-card:nth-child(4) { animation-delay: 0.3s; }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: var(--text-dark);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.75rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="welcome-content">
                    <h1>Welcome to RATIN Trade Analytics</h1>
                    <p>Your comprehensive platform for managing cross-border trade data, market intelligence, and regional price monitoring across East Africa.</p>
                    <a href="#quick-stats" class="cta-button">Explore Dashboard</a>
                </div>
                <div class="user-info">
                    <div class="user-avatar">AD</div>
                    <div class="user-details">
                        <h3>Admin User</h3>
                        <p>Administrator</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats" id="quick-stats">
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
                <div class="stat-label">Total Trade Records</div>
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
                <div class="stat-label">Last Data Update</div>
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
                            <a href="../base/commodities_boilerplate.php">
                                <i class="fas fa-apple-alt"></i> Commodities
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage agricultural commodities and varieties</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../base/tradepoints_boilerplate.php">
                                <i class="fas fa-map-marker-alt"></i> Trade Points
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage markets, border points and millers</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../base/enumerator_boilerplate.php">
                                <i class="fas fa-user-tie"></i> Enumerators
                                <span class="badge updated">Updated</span>
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage field data collectors</span></span>
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
                            <a href="../data/marketprices_boilerplate.php">
                                <i class="fas fa-store-alt"></i> Market Prices
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">View and manage market price data</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../data/datasource_boilerplate.php">
                                <i class="fas fa-database"></i> Data Sources
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage data collection sources</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../data/xbtvol_boilerplate.php">
                                <i class="fas fa-exchange-alt"></i> XBT Volumes
                                <span class="badge new">New</span>
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Cross-border trade volume data</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../data/miller_price_boilerplate.php">
                                <i class="fas fa-industry"></i> Miller Prices
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage miller price data</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../data/currencies_boilerplate.php">
                                <i class="fas fa-money-bill-wave"></i> Currency Rates
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage currency exchange rates</span></span>
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
                            <a href="../reports/price_trends.php">
                                <i class="fas fa-chart-line"></i> Price Trends
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Analyze commodity price trends</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../reports/trade_flows.php">
                                <i class="fas fa-project-diagram"></i> Trade Flows
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">View cross-border trade patterns</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="#" style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fas fa-file-export"></i> Export Reports
                                <span class="badge" style="background: #6b7280; color: white;">Soon</span>
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Coming in next release</span></span>
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
                            <a href="../user/profile.php">
                                <i class="fas fa-user"></i> My Profile
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">View and edit your profile</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../user/settings.php">
                                <i class="fas fa-cog"></i> Settings
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">System and account settings</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                                <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Sign out of the system</span></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© 2023 RATIN Trade Analytics. All rights reserved. | Version 2.1.0</p>
        </div>
    </div>

    <script>
        // Enhanced JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats on load
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach((stat, index) => {
                const value = parseInt(stat.textContent);
                if (!isNaN(value)) {
                    animateValue(stat, 0, value, 1000 + (index * 200));
                }
            });

            // Add click animation to cards
            const cards = document.querySelectorAll('.section-card, .stat-card');
            cards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A') return;
                    
                    const link = this.querySelector('a');
                    if (link) {
                        link.click();
                    }
                });
            });
        });

        function animateValue(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.childNodes[0].textContent = value.toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>