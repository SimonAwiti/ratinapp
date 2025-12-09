<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Home | RATIN Trade Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* Header Styles - Consistent with other pages */
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-img {
            height: 40px;
        }

        .logo-text {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-color);
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: color var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .nav-links a i {
            font-size: 1rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
        }

        /* Main Content Styles */
        .main-container {
            display: flex;
            min-height: calc(100vh - 60px);
        }

        /* Sidebar Styles - Consistent with other pages */
        .sidebar {
            width: 250px;
            background-color: var(--card-bg);
            box-shadow: var(--shadow-md);
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-section {
            margin-bottom: 1.5rem;
        }

        .sidebar-section h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-medium);
            text-decoration: none;
            transition: all var(--transition-speed);
        }

        .sidebar-link:hover {
            background-color: rgba(139, 69, 19, 0.05);
            color: var(--primary-color);
        }

        .sidebar-link.active {
            background-color: rgba(139, 69, 19, 0.1);
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }

        .sidebar-link i {
            width: 24px;
            font-size: 1rem;
            margin-right: 0.75rem;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }

        .landing-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Dashboard Header */
        .dashboard-header {
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

        .dashboard-header::before {
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

        /* Sections Grid */
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

        /* Footer - Consistent with other pages */
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-light);
            font-size: 0.875rem;
            margin-top: 2rem;
            background-color: var(--card-bg);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Stats Section Styles */
        .stats-section {
            margin-bottom: 2rem;
        }
        
        .stats-container {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            width: 100%;
            max-width: 100%;
            margin: 0 auto 20px auto;
        }
        
        .stats-container > div {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .stats-icon {
            width: 40px;
            height: 40px;
            margin-bottom: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .total-icon {
            background-color: #9b59b6;
            color: white;
        }
        
        .cereals-icon {
            background-color: #f39c12;
            color: white;
        }
        
        .pulses-icon {
            background-color: #27ae60;
            color: white;
        }
        
        .oil-seeds-icon {
            background-color: #e74c3c;
            color: white;
        }
        
        .stats-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin: 8px 0 5px 0;
        }
        
        .stats-number {
            font-size: 24px;
            font-weight: 700;
            color: #34495e;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
                padding: 1rem 0;
            }
            
            .content-area {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 0;
                flex-direction: row;
                overflow-x: auto;
            }
            
            .sidebar-menu {
                display: flex;
                flex-wrap: nowrap;
                padding: 0.5rem;
            }
            
            .sidebar-section {
                margin-bottom: 0;
                margin-right: 1.5rem;
            }
            
            .sidebar-section h3 {
                display: none;
            }
            
            .sidebar-link {
                padding: 0.5rem 1rem;
                white-space: nowrap;
            }
            
            .dashboard-header {
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
            
            .stats-container {
                flex-wrap: wrap;
            }
            
            .stats-container > div {
                flex: 1 1 calc(50% - 10px);
                min-width: 150px;
            }
        }

        @media (max-width: 576px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                padding: 0.75rem;
            }
            
            .nav-links {
                margin-top: 0.75rem;
                width: 100%;
                justify-content: space-between;
            }
            
            .user-menu {
                margin-top: 0.75rem;
                width: 100%;
                justify-content: flex-end;
            }
            
            .welcome-content h1 {
                font-size: 1.75rem;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .stats-container > div {
                flex: 1 1 100%;
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
    </style>
</head>
<body>
    <!-- Header - Consistent with other pages -->
    <header class="header-container">
        <div class="logo">
            <img src="./img/Ratin-logo-1.png" alt="RATIN Logo" class="logo-img">
            <span class="logo-text">RATIN Analytics</span>
        </div>

        <div class="user-menu">
            <div class="user-avatar">
                <?php
                // Start session to get admin username
                session_start();
                if (isset($_SESSION['admin_username'])) {
                    $username = $_SESSION['admin_username'];
                    // Get initials from username
                    $initials = '';
                    $words = explode(' ', $username);
                    foreach ($words as $word) {
                        $initials .= strtoupper(substr($word, 0, 1));
                        if (strlen($initials) >= 2) break;
                    }
                    echo $initials ?: 'AD';
                } else {
                    echo 'AD';
                }
                ?>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="main-container">
        <!-- Sidebar - Consistent with other pages -->
        <aside class="sidebar">
            <div class="sidebar-menu">
                <!-- BASE MANAGEMENT Section -->
                <div class="sidebar-section">
                    <h3>Base Management</h3>
                    <a href="../data/countries_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-globe-africa"></i> Countries Covered
                    </a>
                    <a href="../base/commodity_sources_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-globe-africa"></i> Geographic Units
                    </a>
                    <a href="../base/commodities_boilerplate.php" class="sidebar-link">
                        <i class="fas fa-wheat-awn"></i> Commodities
                    </a>
                    <a href="../base/tradepoints_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-map-marker-alt"></i> Trade Points
                    </a>
                    <a href="../base/enumerator_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-users"></i> Enumerators
                    </a>
                </div>
                
                <!-- DATA MANAGEMENT Section -->
                <div class="sidebar-section">
                    <h3>Data Management</h3>
                    <a href="../data/marketprices_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-store"></i> Market Prices
                    </a>
                    <a href="../data/datasource_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-database"></i> Data Sources
                    </a>
                    <a href="../data/xbtvol_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-exchange-alt"></i> XBT Volumes
                        <span class="badge" style="background: #10b981; color: white; font-size: 0.625rem; padding: 2px 6px; border-radius: 10px; margin-left: auto;">NEW</span>
                    </a>
                    <a href="../data/miller_price_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-chart-bar"></i> Miller Prices
                    </a>
                    <a href="../data/currencies_boilerplate.php" class="sidebar-link">
                        <i class="fa fa-credit-card"></i> Currency Rates
                    </a>
                </div>

                <!-- WEB Section -->
                <div class="sidebar-section">
                    <h3>Web</h3>
                    <a href="https://ratin.net/home/" class="sidebar-link" target="_blank">
                        <i class="fa fa-monitor"></i> WebSite
                    </a>
                    <a href="../frontend/marketprices.php" class="sidebar-link">
                        <i class="fa fa-chart-line"></i> Data display
                    </a>
                    <a href="../news-system/index.php" class="sidebar-link">
                        <i class="fa fa-newspaper"></i> Website manager
                    </a>
                </div>
                
                <!-- ADMIN Section -->
                <div class="sidebar-section">
                    <h3>Admin</h3>
                    <a href="../admin/user_management.php" class="sidebar-link">
                        <i class="fa fa-user"></i> User subscription
                    </a>
                    <a href="../admin/logout.php" class="sidebar-link">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </aside>

        <!-- Content Area -->
        <main class="content-area">
            <div class="landing-container">
                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="header-content">
                        <div class="welcome-content">
                            <h1>Welcome to RATIN Trade Analytics</h1>
                            <p>Your comprehensive platform for managing cross-border trade data, market intelligence, and regional price monitoring across East Africa.</p>
                            <a href="#quick-stats" class="cta-button">Explore Dashboard</a>
                        </div>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="stats-section">
                    <h3>Commodities Overview</h3>
                    <p>Summary of agricultural commodities in the system</p>

                    <div class="stats-container">
                        <div class="overlap-6">
                            <div class="stats-icon total-icon">
                                <i class="fas fa-seedling"></i>
                            </div>
                            <div class="stats-title">Total Commodities</div>
                            <div class="stats-number">42</div>
                        </div>
                        
                        <div class="overlap-6">
                            <div class="stats-icon cereals-icon">
                                <i class="fas fa-wheat-awn"></i>
                            </div>
                            <div class="stats-title">Cereals</div>
                            <div class="stats-number">15</div>
                        </div>
                        
                        <div class="overlap-7">
                            <div class="stats-icon pulses-icon">
                                <i class="fas fa-dot-circle"></i>
                            </div>
                            <div class="stats-title">Pulses</div>
                            <div class="stats-number">12</div>
                        </div>
                        
                        <div class="overlap-7">
                            <div class="stats-icon oil-seeds-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div class="stats-title">Oil Seeds</div>
                            <div class="stats-number">8</div>
                        </div>
                    </div>
                </div>

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
                                <a href="../base/countries_boilerplate.php">
                                    <i class="fa fa-globe-africa"></i> Countries Covered
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage countries and regions covered by RATIN</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../base/commodity_sources_boilerplate.php">
                                    <i class="fa fa-globe-africa"></i> Geographic Units
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage geographic units and regions</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../base/commodities_boilerplate.php">
                                    <i class="fas fa-wheat-awn"></i> Commodities
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage agricultural commodities and varieties</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../base/tradepoints_boilerplate.php">
                                    <i class="fa fa-map-marker-alt"></i> Trade Points
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage markets, border points and millers</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../base/enumerator_boilerplate.php">
                                    <i class="fa fa-users"></i> Enumerators
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
                                    <i class="fa fa-store"></i> Market Prices
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">View and manage market price data</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../data/datasource_boilerplate.php">
                                    <i class="fa fa-database"></i> Data Sources
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage data collection sources</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../data/xbtvol_boilerplate.php">
                                    <i class="fa fa-exchange-alt"></i> XBT Volumes
                                    <span class="badge new">New</span>
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Cross-border trade volume data</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../data/miller_price_boilerplate.php">
                                    <i class="fa fa-chart-bar"></i> Miller Prices
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage miller price data</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../data/currencies_boilerplate.php">
                                    <i class="fa fa-credit-card"></i> Currency Rates
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage currency exchange rates</span></span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Web Management Card -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <h3>Web Management</h3>
                        </div>
                        <ul>
                            <li>
                                <a href="https://beta.ratin.net/frontend/" target="_blank">
                                    <i class="fa fa-monitor"></i> WebSite
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Visit the main RATIN website</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../frontend/marketprices.php">
                                    <i class="fa fa-chart-line"></i> Data display
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">View data display for public users</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../news-system/index.php">
                                    <i class="fa fa-newspaper"></i> Website manager
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage website content and news</span></span>
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
                                <a href="../admin/user_management.php">
                                    <i class="fa fa-user"></i> User subscription
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Manage user subscriptions and accounts</span></span>
                                </a>
                            </li>
                            <li>
                                <a href="../admin/logout.php">
                                    <i class="fa fa-sign-out-alt"></i> Logout
                                    <span class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext">Sign out of the system</span></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer - Consistent with other pages -->
    <footer class="footer">
        <p>© 2023 RATIN Trade Analytics. All rights reserved. | Version 2.1.0</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

            // Set active sidebar link based on current page
            const currentPath = window.location.pathname.split('/').pop() || 'index.php';
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            
            sidebarLinks.forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();
                if (currentPath === linkPath) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
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