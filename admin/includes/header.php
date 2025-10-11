<?php
// admin/includes/header.php

// Make sure session_start() is called only once at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/index.php");
    exit;
}

// Determine the current page for active sidebar link highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

// Auto-detect the base path based on current directory
$currentDir = dirname($_SERVER['SCRIPT_FILENAME']);
$dirName = basename($currentDir);

// Set relative paths based on directory
if ($dirName === 'base') {
    $basePath = '';
    $dataPath = '../data/';
    $adminPath = '../admin/';
    $imgPath = 'img/';
} elseif ($dirName === 'data') {
    $basePath = '../base/';
    $dataPath = '';
    $adminPath = '../admin/';
    $imgPath = '../base/img/';
} elseif ($dirName === 'admin') {
    $basePath = '../base/';
    $dataPath = '../data/';
    $adminPath = '';
    $imgPath = '../base/img/';
} else {
    // Default fallback
    $basePath = '../base/';
    $dataPath = '../data/';
    $adminPath = '../admin/';
    $imgPath = '../base/img/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .wrapper {
            display: flex;
            height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 240px;
            background-color: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.04);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* Logo Section */
        .sidebar .logo-section {
            padding: 24px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar .ratin-logo {
            max-width: 40px;
            height: auto;
        }

        .sidebar .logo-text {
            font-size: 18px;
            font-weight: 700;
            color: #8B4513;
        }

        /* Section Headers */
        .sidebar .section-header {
            color: #6b7280;
            font-weight: 600;
            margin: 24px 0 8px 0;
            padding: 0 20px;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Navigation Links */
        .sidebar .nav-link {
            color: #374151;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 0;
            transition: all 0.15s ease;
            font-size: 14px;
            font-weight: 400;
            text-decoration: none;
            border-left: 3px solid transparent;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 16px;
            color: #6b7280;
        }

        .sidebar .nav-link:hover {
            background-color: #fef3e7;
            border-left-color: #f59e0b;
            color: #8B4513;
        }

        .sidebar .nav-link:hover i {
            color: #8B4513;
        }

        .sidebar .nav-link.active {
            background-color: #fef3e7;
            border-left-color: #8B4513;
            color: #8B4513;
            font-weight: 500;
        }

        .sidebar .nav-link.active i {
            color: #8B4513;
        }

        /* Submenu Styles */
        .sidebar .submenu {
            padding-left: 0;
            background-color: #fafafa;
        }

        .sidebar .submenu .nav-link {
            padding-left: 52px;
            font-size: 13px;
        }

        .sidebar .submenu .submenu .nav-link {
            padding-left: 72px;
            font-size: 13px;
        }

        /* Collapse Toggle */
        .sidebar .nav-link[data-bs-toggle="collapse"] {
            position: relative;
            cursor: pointer;
        }

        .sidebar .nav-link[data-bs-toggle="collapse"]::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 20px;
            transition: transform 0.2s ease;
            font-size: 12px;
            color: #9ca3af;
        }

        .sidebar .nav-link[data-bs-toggle="collapse"]:not(.collapsed)::after {
            transform: rotate(180deg);
        }

        /* Badge Styles */
        .badge-new {
            background-color: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: auto;
        }

        .badge-updated {
            background-color: #3b82f6;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: auto;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 240px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            width: calc(100% - 240px);
        }

        /* Header */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 32px;
            background-color: #fff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .breadcrumb {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            background: transparent;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #6b7280;
            transition: color 0.15s;
        }

        .breadcrumb a:hover {
            color: #8B4513;
        }

        .breadcrumb-item.active {
            color: #374151;
            font-weight: 500;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #d1d5db;
            font-size: 18px;
        }

        /* User Display */
        .user-display {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background-color: #f9fafb;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .user-display i {
            font-size: 18px;
            color: #8B4513;
        }

        /* Content Container */
        .content-container {
            padding: 32px;
            flex-grow: 1;
            overflow-y: auto;
            background-color: #f5f5f5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Logo Section -->
        <div class="logo-section">
            <img class="ratin-logo" src="<?= $imgPath ?>Ratin-logo-1.png" alt="RATIN">
            <span class="logo-text">RATIN Analytics</span>
        </div>

        <!-- BASE MANAGEMENT Section -->
        <div class="section-header">BASE MANAGEMENT</div>
        <a href="<?= $dataPath ?>countries_boilerplate.php" class="nav-link <?= ($currentPage == 'countries_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-globe-africa"></i>
            <span>Countries Covered</span>
        </a>
        <a href="<?= $dataPath ?>commodity_sources_boilerplate.php" class="nav-link <?= ($currentPage == 'countries_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-globe-africa"></i>
            <span>Geographic Units</span>
        </a>
        
        <a href="<?= $basePath ?>commodities_boilerplate.php" class="nav-link <?= ($currentPage == 'commodities_boilerplate.php') ? 'active' : '' ?>">
            <i class="fas fa-wheat-awn"></i>
            <span>Commodities</span>
        </a>

        <a href="<?= $basePath ?>tradepoints_boilerplate.php" class="nav-link <?= ($currentPage == 'tradepoints_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-map-marker-alt"></i>
            <span>Trade Points</span>
        </a>

        <a href="<?= $basePath ?>enumerator_boilerplate.php" class="nav-link <?= ($currentPage == 'enumerator_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-users"></i>
            <span>Enumerators</span>
        </a>

        <!-- DATA MANAGEMENT Section -->
        <div class="section-header">DATA MANAGEMENT</div>

        <a href="#marketPricesSubmenu" class="nav-link collapsed" data-bs-toggle="collapse" aria-expanded="false">
            <i class="fa fa-store"></i>
            <span>Market Prices</span>
        </a>
        <div class="collapse submenu" id="marketPricesSubmenu">
            <a href="<?= $dataPath ?>marketprices_boilerplate.php" class="nav-link <?= ($currentPage == 'marketprices_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-list"></i>
                <span>Prices</span>
            </a>
            <a href="<?= $dataPath ?>datasource_boilerplate.php" class="nav-link <?= ($currentPage == 'datasource_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-database"></i>
                <span>Data Sources</span>
            </a>
        </div>

        <a href="<?= $dataPath ?>xbtvol_boilerplate.php" class="nav-link <?= ($currentPage == 'xbtvol_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-exchange-alt"></i>
            <span>XBT Volumes</span>
            <span class="badge-new">NEW</span>
        </a>

        <a href="<?= $dataPath ?>miller_price_boilerplate.php" class="nav-link <?= ($currentPage == 'miller_price_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-chart-bar"></i>
            <span>Miller Prices</span>
        </a>
        <a href="<?= $dataPath ?>currencies_boilerplate.php" class="nav-link <?= ($currentPage == 'currencies_boilerplate.php') ? 'active' : '' ?>">
            <i class="fa fa-credit-card"></i>
            <span>Currency Rates</span>
        </a>

        <!-- WEB Section -->
        <div class="section-header">WEB</div>

        <a href="https://beta.ratin.net/frontend/" class="nav-link" target="_blank">
            <i class="fa fa-monitor"></i>
            <span>WebSite</span>
        </a>

        <a href="../frontend/marketprices.php" class="nav-link">
            <i class="fa fa-chart-line"></i>
            <span>Data display</span>
        </a>

        <a href="../news-system/index.php" class="nav-link">
            <i class="fa fa-newspaper"></i>
            <span>Website manager</span>
        </a>

        <!-- ADMIN Section -->
        <div class="section-header">ADMIN</div>

        <a href="<?= $adminPath ?>profile.php" class="nav-link <?= ($currentPage == 'profile.php') ? 'active' : '' ?>">
            <i class="fa fa-user"></i>
            <span>Profile</span>
        </a>

        <a href="<?= $adminPath ?>settings.php" class="nav-link <?= ($currentPage == 'settings.php') ? 'active' : '' ?>">
            <i class="fa fa-cog"></i>
            <span>Settings</span>
        </a>

        <a href="<?= $adminPath ?>logout.php" class="nav-link">
            <i class="fa fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb" id="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= $basePath ?>landing_page.php">
                            <i class="fa fa-home"></i>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" id="mainCategory">Dashboard</li>
                    <li class="breadcrumb-item active" aria-current="page" id="subCategory">Home</li>
                </ol>
            </nav>
            <?php if (isset($_SESSION['admin_username'])): ?>
                <div class="user-display">
                    <i class="fa fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Content Container -->
        <div class="content-container" id="mainContent">
            <!-- Page content will be inserted here by individual pages -->