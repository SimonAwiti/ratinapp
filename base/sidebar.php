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
        }

        .sidebar h6 {
            color: #6c757d;
            font-weight: bold;
        }

        .sidebar .nav-link {
            color: #333;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 5px;
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
            border-bottom: 0px solid #ddd;
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
            padding: 19px;
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
                <i class="fa fa-circle" style="color:#8B4513;"></i> Commodities
            </a>
            <a href="#" class="nav-link" onclick="loadContent('tradepoints_boilerplate.php', 'Base', 'Trade Points')">
                <i class="fa fa-circle text-secondary"></i> Trade Points
            </a>
            <a href="#" class="nav-link" onclick="loadContent('enumerator_boilerplate.php', 'Base', 'Enumerators')">
                <i class="fa fa-circle text-secondary"></i> Enumerators
            </a>
        </div>

        <a href="#" class="nav-link" onclick="toggleSubmenu('dataSubmenu', this)">
            <span><i class="fa fa-chart-line"></i> Data</span>
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="dataSubmenu">
            <a href="#" class="nav-link" onclick="toggleSubmenu('marketPricesSubmenu', this)">
                <span><i class="fa fa-store-alt"></i> Market Prices</span>
                <i class="fa fa-chevron-down"></i>
            </a>
            <div class="submenu" id="marketPricesSubmenu" style="padding-left: 20px;">
                <a href="#" class="nav-link" onclick="loadContent('../data/marketprices_boilerplate.php', 'Data', 'Market Prices')">
                    <i class="fa fa-list"></i> Prices
                </a>
                <a href="../data/add_datasource.php" class="nav-link">
                    <i class="fa fa-database"></i> Add Data Source
                </a>
            </div>
            
            <!-- Simplified XBT Volumes section without subsections -->
            <a href="#" class="nav-link" onclick="loadContent('../data/xbtvol_boilerplate.php', 'Data', 'XBT Volumes')">
                <i class="fa fa-exchange-alt"></i> XBT Volumes
            </a>
            
            <a href="#" class="nav-link"><i class="fa fa-table"></i> Reports</a>
            <a href="#" class="nav-link"><i class="fa fa-chart-bar"></i> Analytics</a>
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
            <a href="#" class="nav-link"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="flex-grow-1">
        <div class="header-container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb" id="breadcrumb">
                    <li class="breadcrumb-item"><a href="#"><i class="fa fa-home"></i></a></li>
                    <li class="breadcrumb-item"><a href="#">Management</a></li>
                    <li class="breadcrumb-item active" id="mainCategory">Base</li>
                    <li class="breadcrumb-item active" aria-current="page" id="subCategory">Commodities</li>
                </ol>
            </nav>
        </div>

        <div class="content-container" id="mainContent">
            <?php include 'commodities.php'; ?>
        </div>
    </div>
</div>

<script>
    function toggleSubmenu(submenuId, element) {
        let submenu = document.getElementById(submenuId);
        let icon = element.querySelector("i.fa-chevron-down");

        if (submenu.style.display === "block") {
            submenu.style.display = "none";
            icon.classList.remove("rotate");
        } else {
            submenu.style.display = "block";
            icon.classList.add("rotate");
        }
    }

    function updateBreadcrumb(mainCategory, subCategory) {
        document.getElementById("mainCategory").textContent = mainCategory;
        document.getElementById("subCategory").textContent = subCategory;
        const pageTitle = document.getElementById("pageTitle");
        if (pageTitle) pageTitle.textContent = subCategory;
    }

    document.addEventListener("DOMContentLoaded", function () {
        let sidebarLinks = document.querySelectorAll(".sidebar .nav-link");

        sidebarLinks.forEach(link => {
            link.addEventListener("click", function () {
                // Remove active class from all links
                sidebarLinks.forEach(l => l.classList.remove("active"));
                
                // Add active class to clicked link
                this.classList.add("active");

                // If submenu, update breadcrumb
                let parentMenu = this.closest(".submenu");
                if (parentMenu) {
                    let mainCategory = parentMenu.previousElementSibling.textContent.trim();
                    let subCategory = this.textContent.trim();
                    updateBreadcrumb(mainCategory, subCategory);
                }
            });
        });
    });

function loadContent(page, mainCategory, subCategory) {
        fetch(page)
            .then(response => {
                if (!response.ok) throw new Error('Failed to load page');
                return response.text();
            })
            .then(html => {
                // 1. Insert the new HTML content into the DOM
                document.getElementById("mainContent").innerHTML = html;
                updateBreadcrumb(mainCategory, subCategory);

                // 2. Remove any previously added dynamic scripts
                document.querySelectorAll('script.dynamic-script').forEach(script => script.remove());

                // 3. Dynamically load and initialize scripts based on the page
                if (page.includes('commodities_boilerplate.php')) {
                    loadScript('assets/filter.js');
                } else if (page.includes('tradepoints_boilerplate.php')) {
                    loadScript('assets/filter2.js');
                } else if (page.includes('enumerator_boilerplate.php')) {
                    loadScript('assets/filter3.js');
                } else if (page.includes('marketprices_boilerplate.php')) {
                    // This block is correct
                    const script = document.createElement('script');
                    script.src = 'assets/marketprices.js';
                    script.type = 'text/javascript';
                    script.className = 'dynamic-script';
                    script.onload = () => {
                        if (typeof initializeMarketPrices === 'function') {
                            initializeMarketPrices();
                        } else {
                            console.error("initializeMarketPrices function not found after script load.");
                        }
                    };
                    script.onerror = (error) => console.error(`Error loading script ${script.src}:`, error);
                    document.body.appendChild(script);
                } else if (page.includes('xbtvol_boilerplate.php')) { // Corrected from xbtvolumes_boilerplate.php
                    // This is the crucial part for xbtvols.js
                    const script = document.createElement('script');
                    script.src = 'assets/xbtvols.js'; // Ensure this path is correct
                    script.type = 'text/javascript';
                    script.className = 'dynamic-script';

                    script.onload = () => {
                        // **Call the initialization function *after* the script has loaded**
                        if (typeof initializeXBTVolumes === 'function') {
                            initializeXBTVolumes();
                        } else {
                            console.error("initializeXBTVolumes function not found after script load.");
                        }
                    };
                    script.onerror = (error) => console.error(`Error loading script ${script.src}:`, error);
                    document.body.appendChild(script);
                }
            })
            .catch(error => {
                document.getElementById("mainContent").innerHTML = `<div class="alert alert-danger">Error loading content: ${error}</div>`;
            });
    }

    // Your existing loadScript function is good for general scripts without specific initialization needs
    function loadScript(src) {
        const script = document.createElement('script');
        script.src = src;
        script.type = 'text/javascript';
        script.className = 'dynamic-script';
        script.onload = () => console.log(`${src} loaded successfully`);
        script.onerror = (error) => console.error(`Error loading script ${src}:`, error);
        document.body.appendChild(script);
    }
</script>

</body>
</html>
