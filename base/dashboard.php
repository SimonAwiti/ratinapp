<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <!-- Bootstrap & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Custom Styles -->
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
    <!-- Sidebar -->
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
            <a href="#" class="nav-link" onclick="updateBreadcrumb('Base', 'Commodities')">
                <i class="fa fa-circle" style="color:#8B4513;"></i> Commodities
            </a>
            <a href="#" class="nav-link" onclick="updateBreadcrumb('Base', 'Trade Points')">
                <i class="fa fa-circle text-secondary"></i> Trade Points
            </a>
            <a href="#" class="nav-link" onclick="updateBreadcrumb('Base', 'Enumerators')">
                <i class="fa fa-circle text-secondary"></i> Enumerators
            </a>
        </div>

        <!-- Data Section -->
        <a href="#" class="nav-link" onclick="toggleSubmenu('dataSubmenu', this)">
            <span><i class="fa fa-chart-line"></i> Data</span> 
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="dataSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-database"></i> Reports</a>
            <a href="#" class="nav-link"><i class="fa fa-table"></i> Analytics</a>
        </div>

        <!-- Web Section -->
        <a href="#" class="nav-link" onclick="toggleSubmenu('webSubmenu', this)">
            <span><i class="fa fa-globe"></i> Web</span> 
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="webSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-link"></i> Website</a>
        </div>

        <!-- User Section -->
        <a href="#" class="nav-link" onclick="toggleSubmenu('userSubmenu', this)">
            <span><i class="fa fa-user-gear"></i> User</span> 
            <i class="fa fa-chevron-down"></i>
        </a>
        <div class="submenu" id="userSubmenu">
            <a href="#" class="nav-link"><i class="fa fa-user"></i> Profile</a>
            <a href="#" class="nav-link"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1">
        <!-- Header -->
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

        <!-- Main Content -->
        <div class="content-container">
            <?php include 'commodities_boilerplate.php'; ?>
        </div>
    </div>
</div>

<!-- JavaScript -->

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
        document.getElementById("pageTitle").textContent = subCategory;
    }

    document.addEventListener("DOMContentLoaded", function () {
        let sidebarLinks = document.querySelectorAll(".sidebar .nav-link");

        sidebarLinks.forEach(link => {
            link.addEventListener("click", function () {
                // Remove active class from all links
                sidebarLinks.forEach(l => l.classList.remove("active"));
                
                // Add active class to clicked link
                this.classList.add("active");
                
                // Check if the clicked link belongs to a submenu
                let parentMenu = this.closest(".submenu");
                if (parentMenu) {
                    let mainCategory = parentMenu.previousElementSibling.textContent.trim();
                    let subCategory = this.textContent.trim();
                    updateBreadcrumb(mainCategory, subCategory);
                }
            });
        });
    });
</script>


</body>
</html>