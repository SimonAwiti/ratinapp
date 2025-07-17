<?php
// admin/includes/header.php

// Make sure session_start() is called only once at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['admin_logged_in'])) {
    // This header.php is included by files in 'base/', so '../admin/index.php' is correct
    header("Location: ../admin/index.php");
    exit;
}

// Determine the current page for active sidebar link highlighting
// This assumes your boilerplate files are in the 'base' folder
$currentPage = basename($_SERVER['PHP_SELF']);

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
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .wrapper {
            display: flex;
            height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 0px solid #ddd;
            padding: 15px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05); /* Added subtle shadow */
            position: relative; /* For the logo positioning */
            z-index: 1000; /* Ensure it stays above other content */
            overflow-y: auto; /* Enable scrolling for long sidebars */
        }

        .sidebar .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar .ratin-logo {
            max-width: 150px; /* Adjust as needed */
            height: auto;
        }

        .sidebar h6 {
            color: #6c757d;
            font-weight: bold;
            margin-top: 20px;
            padding-left: 10px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .sidebar .nav-link {
            color: #333;
            padding: 12px 10px; /* Increased padding for better click area */
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 5px;
            transition: all 0.2s ease-in-out; /* Smooth transition for hover */
            font-size: 1.0em;
        }

        .sidebar .nav-link i {
            margin-right: 10px; /* Space for icons */
            width: 20px; /* Fixed width for icons to align text */
            text-align: center;
        }
         .sidebar .nav-link i.fa-chevron-down {
            margin-right: 0; /* No margin for chevron */
            width: auto;
            transition: transform 0.3s ease; /* Smooth rotation */
        }

        .sidebar .nav-link.collapsed .fa-chevron-down {
            transform: rotate(0deg);
        }

        .sidebar .nav-link:not(.collapsed) .fa-chevron-down {
            transform: rotate(180deg);
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #f5d6c6;
            color: #8B4513;
        }

        .sidebar .submenu {
            padding-left: 10px;
            /* Bootstrap handles display:none/block for collapse, but ensure no conflicting styles */
        }

        /* Header */
        .header-container {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 20px;
            background-color: #fff;
            border-bottom: 1px solid #eee; /* Light border at the bottom */
            box-shadow: 0 2px 5px rgba(0,0,0,0.03); /* Subtle shadow */
            z-index: 999; /* Below sidebar */
            position: sticky;
            top: 0;
        }

        .breadcrumb {
            margin: 0;
            font-size: 17px;
            color: #6c757d;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #8B4513;
            font-weight: bold;
        }

        .breadcrumb-item.active {
            color: #8B4513;
            font-weight: bold;
        }

        /* Change breadcrumb separator to '>' */
        .breadcrumb-item + .breadcrumb-item::before {
            content: " > ";
            color: #6c757d;
        }

        /* Page Title */
        .content-container {
            padding: 20px;
            flex-grow: 1; /* Allow content to fill remaining space */
            overflow-y: auto; /* Enable scrolling for content area */
        }

        .user-display {
            display: flex;
            align-items: center;
            gap: 8px; /* Space between icon and username */
            font-weight: bold;
            color: #8B4513;
        }
        .user-display i {
            font-size: 1.2em; /* Adjust icon size as needed */
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <div class="logo">
            <img class="ratin-logo" src="img/Ratin-logo-1.png" alt="RATIN Logo">
        </div><br>
        <h6>Management</h6>

        <a href="#baseSubmenu" class="nav-link collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="baseSubmenu">
            <span><i class="fa fa-table"></i> Base</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="collapse submenu" id="baseSubmenu">
            <a href="commodities_boilerplate.php" class="nav-link <?= ($currentPage == 'commodities_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-box-open" style="color:#8B4513;"></i> Commodities
            </a>
            <a href="commodity_sources_boilerplate.php" class="nav-link <?= ($currentPage == 'commodity_sources_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-database" style="color:#8B4513;"></i> Sources
            </a>
            <a href="tradepoints_boilerplate.php" class="nav-link <?= ($currentPage == 'tradepoints_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-map-marker-alt" style="color:#8B4513;"></i> Trade Points
            </a>
            <a href="enumerator_boilerplate.php" class="nav-link <?= ($currentPage == 'enumerator_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-users" style="color:#8B4513;"></i> Enumerators
            </a>
        </div>

        <a href="#dataSubmenu" class="nav-link collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="dataSubmenu">
            <span><i class="fa fa-chart-line"></i> Data</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="collapse submenu" id="dataSubmenu">
            <a href="#marketPricesSubmenu" class="nav-link collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="marketPricesSubmenu">
                <span><i class="fa fa-store-alt"></i> Market Prices</span>
                <i class="fa fa-chevron-down"></i>
            </a>
            <div class="collapse submenu" id="marketPricesSubmenu">
                <a href="../data/marketprices_boilerplate.php" class="nav-link <?= ($currentPage == 'marketprices_boilerplate.php') ? 'active' : '' ?>">
                    <i class="fa fa-list"></i> Prices
                </a>
                <a href="../data/datasource_boilerplate.php" class="nav-link <?= ($currentPage == 'datasource_boilerplate.php') ? 'active' : '' ?>">
                    <i class="fa fa-database"></i> Data Sources
                </a>
            </div>
            <a href="../data/xbtvol_boilerplate.php" class="nav-link <?= ($currentPage == 'xbtvol_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-exchange-alt"></i> XBT Volumes
            </a>
            <a href="../data/miller_price_boilerplate.php" class="nav-link <?= ($currentPage == 'miller_price_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-industry"></i> Miller Prices
            </a>
            <a href="../data/currencies_boilerplate.php" class="nav-link <?= ($currentPage == 'currencies_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-money-bill-wave"></i> Currency Rates
            </a>
            <a href="../data/countries_boilerplate.php" class="nav-link <?= ($currentPage == 'countries_boilerplate.php') ? 'active' : '' ?>">
                <i class="fa fa-globe-africa"></i> Countries
            </a>
        </div>

        <a href="#webSubmenu" class="nav-link collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="webSubmenu">
            <span><i class="fa fa-globe"></i> Web</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="collapse submenu" id="webSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-link"></i> Website</a>
            <a href="../frontend/marketprices.php" class="nav-link"><i class="fa fa-link"></i> Data display</a>
        </div>

        <a href="#userSubmenu" class="nav-link collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="userSubmenu">
            <span><i class="fa fa-user-gear"></i> User</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="collapse submenu" id="userSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-user"></i> Profile</a>
            <a href="../admin/create_admin.php" class="nav-link <?= ($currentPage == 'create_admin.php') ? 'active' : '' ?>"><i class="fa fa-user-plus"></i> Create Admin</a>
            <a href="../admin/logout.php" class="nav-link"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="flex-grow-1">
        <div class="header-container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb" id="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="landing_page.php">
                            <i class="fa fa-home"></i>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" id="mainCategory">Dashboard</li>
                    <li class="breadcrumb-item active" aria-current="page" id="subCategory">Home</li>
                </ol>
            </nav>
            <?php if (isset($_SESSION['admin_username'])): ?>
                <div class="user-display">
                    <i class="fa fa-user-circle"></i> <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="content-container" id="mainContent">
