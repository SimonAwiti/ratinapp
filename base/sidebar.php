<?php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/index.php");
    exit;
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
        }


        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #f5d6c6;
            color: #8B4513;
        }

        .sidebar .submenu {
            padding-left: 10px;
            display: none;
        }

        .rotate {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
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

        <a href="#" class="nav-link" onclick="toggleSubmenu('baseSubmenu', this)">
            <span><i class="fa fa-table"></i> Base</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="baseSubmenu">
            <a href="#" class="nav-link" onclick="loadContent('commodities_boilerplate.php', 'Base', 'Commodities')">
                <i class="fa fa-box-open" style="color:#8B4513;"></i> Commodities
            </a>
            <a href="#" class="nav-link" onclick="loadContent('commodity_sources_boilerplate.php', 'Base', 'Commodities Data Sources')">
                <i class="fa fa-database" style="color:#8B4513;"></i> Sources
            </a>
            <a href="#" class="nav-link" onclick="loadContent('tradepoints_boilerplate.php', 'Base', 'Trade Points')">
                <i class="fa fa-map-marker-alt" style="color:#8B4513;"></i> Trade Points
            </a>
            <a href="#" class="nav-link" onclick="loadContent('enumerator_boilerplate.php', 'Base', 'Enumerators')">
                <i class="fa fa-users" style="color:#8B4513;"></i> Enumerators
            </a>
        </div>

        <a href="#" class="nav-link" onclick="toggleSubmenu('dataSubmenu', this)">
            <span><i class="fa fa-chart-line"></i> Data</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="dataSubmenu">
            <a href="#" class="nav-link" onclick="toggleSubmenu('marketPricesSubmenu', this, event)">
                <span><i class="fa fa-store-alt"></i> Market Prices</span>
                <i class="fa fa-chevron-down"></i>
            </a>
            <div class="submenu" id="marketPricesSubmenu">
                <a href="#" class="nav-link" onclick="loadContent('../data/marketprices_boilerplate.php', 'Data', 'Market Prices')">
                    <i class="fa fa-list"></i> Prices
                </a>
                <a href="#" class="nav-link" onclick="loadContent('../data/datasource_boilerplate.php', 'Data', 'Data Sources')">
                    <i class="fa fa-database"></i> Data Sources
                </a>
            </div>
            <a href="#" class="nav-link" onclick="loadContent('../data/xbtvol_boilerplate.php', 'Data', 'XBT Volumes')">
                <i class="fa fa-exchange-alt"></i> XBT Volumes
            </a>

            <a href="#" class="nav-link" onclick="loadContent('../data/miller_price_boilerplate.php', 'Data', 'Miller Prices')">
                <i class="fa fa-industry"></i> Miller Prices
            </a>

            <a href="#" class="nav-link" onclick="loadContent('../data/currencies_boilerplate.php', 'Data', 'Currency Rates')">
                <i class="fa fa-money-bill-wave"></i> Currency Rates
            </a>

            <a href="#" class="nav-link" onclick="loadContent('../data/countries_boilerplate.php', 'Data', 'Countries')">
                <i class="fa fa-globe-africa"></i> Countries
            </a>
        </div>

        <a href="#" class="nav-link" onclick="toggleSubmenu('webSubmenu', this)">
            <span><i class="fa fa-globe"></i> Web</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="webSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-link"></i> Website</a>
        </div>

        <a href="#" class="nav-link" onclick="toggleSubmenu('userSubmenu', this)">
            <span><i class="fa fa-user-gear"></i> User</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="userSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-user"></i> Profile</a>
            <a href="../admin/create_admin.php" class="nav-link"><i class="fa fa-user-plus"></i> Create Admin</a>
            <a href="../admin/logout.php" class="nav-link"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="flex-grow-1">
        <div class="header-container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb" id="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="#" onclick="loadContent('landing_page.php', 'Dashboard', 'Home'); return false;">
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
            <?php include 'landing_page.php'; // This line now includes your landing page by default ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSubmenu(submenuId, element) {
        let submenu = document.getElementById(submenuId);
        let icon = element.querySelector("i.fa-chevron-down");
        
        // Toggle the current submenu
        if (submenu.style.display === "block") {
            submenu.style.display = "none";
            icon.classList.remove("rotate");
        } else {
            submenu.style.display = "block";
            icon.classList.add("rotate");
        }
        
        // Close other submenus at the same level (direct children of .sidebar or another .submenu)
        let parentContainer = element.closest('.submenu') || document.querySelector('.sidebar');
        parentContainer.querySelectorAll('.submenu').forEach(otherSubmenu => {
            // Only close if it's not the currently toggled submenu AND it's not the parent of the currently toggled submenu
            if (otherSubmenu.id !== submenuId && otherSubmenu !== submenu.parentElement) {
                otherSubmenu.style.display = "none";
                let otherIcon = otherSubmenu.previousElementSibling?.querySelector("i.fa-chevron-down");
                if (otherIcon) otherIcon.classList.remove("rotate");
            }
        });
        
        return false; // Prevent default action and stop propagation
    }

    function updateBreadcrumb(mainCategory, subCategory) {
        // Clear previous breadcrumbs (except the home icon)
        const breadcrumbList = document.getElementById("breadcrumb");
        while (breadcrumbList.children.length > 1) { // Keep the home icon
            breadcrumbList.removeChild(breadcrumbList.lastChild);
        }

        // Add main category if it's not "Dashboard" for the landing page
        if (mainCategory && mainCategory !== 'Dashboard') {
            const mainCatItem = document.createElement('li');
            mainCatItem.className = 'breadcrumb-item active';
            mainCatItem.textContent = mainCategory;
            breadcrumbList.appendChild(mainCatItem);
        }

        // Add sub category
        if (subCategory) {
            const subCatItem = document.createElement('li');
            subCatItem.className = 'breadcrumb-item active';
            subCatItem.setAttribute('aria-current', 'page');
            subCatItem.textContent = subCategory;
            breadcrumbList.appendChild(subCatItem);
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        let sidebarLinks = document.querySelectorAll(".sidebar .nav-link");

        sidebarLinks.forEach(link => {
            link.addEventListener("click", function () {
                // Remove active class from all links
                sidebarLinks.forEach(l => l.classList.remove("active"));
                // Add active class to clicked link
                this.classList.add("active");
            });
        });

        // Initialize the breadcrumb for the default landing page
        updateBreadcrumb('Dashboard', 'Home');
    });

    function loadContent(page, mainCategory, subCategory) {
        fetch(page)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                // 1. Insert the new HTML content into the DOM
                const mainContentDiv = document.getElementById("mainContent");
                mainContentDiv.innerHTML = html;
                updateBreadcrumb(mainCategory, subCategory);

                // 2. Remove any previously added dynamic scripts
                document.querySelectorAll('script.dynamic-script').forEach(script => script.remove());

                // 3. Dynamically load and initialize scripts based on the page
                // Note: For pages with inline scripts, the browser will execute them automatically.
                // This section is primarily for external JS files or specific initialization functions.
                if (page.includes('commodities_boilerplate.php')) {
                    loadScript('assets/filter.js'); // Assuming filter.js is needed
                } else if (page.includes('tradepoints_boilerplate.php')) {
                    loadScript('assets/filter2.js'); // Assuming filter2.js is needed
                } else if (page.includes('enumerator_boilerplate.php')) {
                    loadScript('assets/filter3.js'); // Assuming filter3.js is needed
                } else if (page.includes('marketprices_boilerplate.php')) {
                    loadScriptAndInitialize('assets/marketprices.js', 'initializeMarketPrices');
                } else if (page.includes('miller_price_boilerplate.php')) {
                    loadScriptAndInitialize('assets/miller_prices.js', 'initializeMillerPrices');
                } else if (page.includes('xbtvol_boilerplate.php')) {
                    loadScriptAndInitialize('assets/xbtvols.js', 'initializeXBTVolumes');
                } else if (page.includes('currencies_boilerplate.php')) {
                    loadScriptAndInitialize('assets/currencies.js', 'initializeCurrencies');
                } else if (page.includes('countries_boilerplate.php')) {
                    loadScriptAndInitialize('assets/countries.js', 'initializeCountries');
                } else if (page.includes('datasource_boilerplate.php')) {
                    loadScriptAndInitialize('assets/data_sources.js', 'initializeDataSources');
                } else if (page.includes('commodity_sources_boilerplate.php')) {
                    loadScriptAndInitialize('assets/commodity_sources.js', 'initializeDataSourceFilters');
                }
                // No specific script is typically needed for landing_page.php unless it has dynamic elements.
            })
            .catch(error => {
                document.getElementById("mainContent").innerHTML = `<div class="alert alert-danger">Error loading content: ${error.message}</div>`;
                console.error("Fetch error:", error);
            });
    }

    // Helper function to load script and optionally call an initialization function
    function loadScriptAndInitialize(src, initFunctionName = null) {
        const script = document.createElement('script');
        script.src = src;
        script.type = 'text/javascript';
        script.className = 'dynamic-script'; // Mark for later removal
        script.onload = () => {
            console.log(`${src} loaded successfully.`);
            if (initFunctionName && typeof window[initFunctionName] === 'function') {
                window[initFunctionName](); // Call the global initialization function
            }
        };
        script.onerror = (error) => console.error(`Error loading script ${src}:`, error);
        document.body.appendChild(script);
    }

    // You can also create a general loadScript if it just needs to be appended without init
    function loadScript(src) {
        const script = document.createElement('script');
        script.src = src;
        script.type = 'text/javascript';
        script.className = 'dynamic-script';
        script.onload = () => console.log(`${src} loaded.`);
        script.onerror = (error) => console.error(`Error loading script ${src}:`, error);
        document.body.appendChild(script);
    }
</script>

</body>
</html>