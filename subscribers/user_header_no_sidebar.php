<?php
// user_header_no_sidebar.php - Reusable header for user pages (no sidebar)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Get user info
$user_name = $_SESSION['user_name'] ?? 'User Profile';
$subscription_type = $_SESSION['subscription_type'] ?? 'Free';
$subscription_display = ucfirst(str_replace('_', ' ', $subscription_type));
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RATIN Analytics - <?= ucfirst(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "surface-tint":              "#2a6b2c",
                "surface-container-highest": "#e2e2e2",
                "error-container":           "#ffdad6",
                "tertiary":                  "#004248",
                "on-error-container":        "#93000a",
                "on-surface":                "#1a1c1c",
                "on-primary-fixed":          "#002203",
                "on-primary-container":      "#90d689",
                "tertiary-fixed-dim":        "#75d5e2",
                "surface-container-lowest":  "#ffffff",
                "surface":                   "#f9f9f9",
                "surface-container":         "#eeeeee",
                "on-tertiary-fixed":         "#001f23",
                "surface-variant":           "#e2e2e2",
                "on-tertiary-container":     "#73d4e0",
                "on-primary":               "#ffffff",
                "secondary-container":       "#ff6338",
                "tertiary-fixed":            "#92f1fe",
                "surface-container-high":    "#e8e8e8",
                "secondary-fixed-dim":       "#ffb5a1",
                "background":                "#f9f9f9",
                "surface-bright":            "#f9f9f9",
                "outline-variant":           "#c0c9bb",
                "on-secondary-container":    "#5d1200",
                "primary":                   "#00450d",
                "primary-fixed-dim":         "#91d78a",
                "inverse-on-surface":        "#f1f1f1",
                "inverse-primary":           "#91d78a",
                "secondary":                 "#b22c01",
                "secondary-fixed":           "#ffdbd1",
                "on-primary-fixed-variant":  "#0c5216",
                "primary-container":         "#1b5e20",
                "on-secondary":             "#ffffff",
                "surface-container-low":     "#f3f3f3",
                "on-secondary-fixed-variant":"#881f00",
                "on-tertiary-fixed-variant": "#004f56",
                "on-background":             "#1a1c1c",
                "on-tertiary":              "#ffffff",
                "on-surface-variant":        "#41493e",
                "inverse-surface":           "#2f3131",
                "outline":                   "#717a6d",
                "tertiary-container":        "#005b64",
                "primary-fixed":             "#acf4a4",
                "surface-dim":               "#dadada",
                "on-error":                 "#ffffff",
                "on-secondary-fixed":        "#3b0800",
                "error":                     "#ba1a1a",
                "maroon":                    "#800000",
            },
            borderRadius: {
                DEFAULT: "0.125rem",
                lg:      "0.25rem",
                xl:      "0.5rem",
                full:    "0.75rem",
            },
            spacing: {
                "container-padding": "24px",
                "card-gap":          "20px",
                "gutter":            "16px",
                "base":              "8px",
            },
            fontFamily: {
                "headline-lg-mobile": ["Inter"],
                "body-lg":            ["Inter"],
                "data-tabular":       ["Inter"],
                "label-md":           ["Inter"],
                "headline-lg":        ["Inter"],
                "headline-md":        ["Inter"],
                "body-md":            ["Inter"],
            },
            fontSize: {
                "headline-lg-mobile": ["24px", {lineHeight:"32px", fontWeight:"700"}],
                "body-lg":            ["16px", {lineHeight:"24px", fontWeight:"400"}],
                "data-tabular":       ["13px", {lineHeight:"18px", fontWeight:"400"}],
                "label-md":           ["12px", {lineHeight:"16px", letterSpacing:"0.05em", fontWeight:"600"}],
                "headline-lg":        ["32px", {lineHeight:"40px", letterSpacing:"-0.02em", fontWeight:"700"}],
                "headline-md":        ["24px", {lineHeight:"32px", letterSpacing:"-0.01em", fontWeight:"600"}],
                "body-md":            ["14px", {lineHeight:"20px", fontWeight:"400"}],
            },
        },
    },
}
</script>

<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;
}
body { font-family:'Inter',sans-serif; }

/* User dropdown styles */
.user-dropdown {
    position: relative;
    display: inline-block;
}
.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    min-width: 220px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 50;
    border: 1px solid #e2e2e2;
}
.user-dropdown.active .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #1a1c1c;
    text-decoration: none;
    transition: background 0.2s ease;
    font-size: 14px;
}
.dropdown-item:hover {
    background: #f3f3f3;
}
.dropdown-item:first-child {
    border-radius: 12px 12px 0 0;
}
.dropdown-item:last-child {
    border-radius: 0 0 12px 12px;
    border-top: 1px solid #e2e2e2;
    color: #800000;
}
.dropdown-item:last-child:hover {
    background: #ffdad6;
}
.dropdown-divider {
    height: 1px;
    background: #e2e2e2;
    margin: 4px 0;
}
.subscription-badge {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
    display: inline-block;
}
.subscription-premium {
    background: linear-gradient(135deg, #ffd700, #ffb347);
    color: #5d3a00;
}
.subscription-professional {
    background: linear-gradient(135deg, #4a90e2, #357abd);
    color: white;
}
.subscription-basic {
    background: linear-gradient(135deg, #5cb85c, #449d44);
    color: white;
}
.subscription-free {
    background: #e0e0e0;
    color: #666;
}
</style>
</head>

<body class="bg-background text-on-background min-h-screen">

<!-- Top Navigation Bar -->
<header class="fixed top-0 left-0 right-0 h-16 flex justify-between items-center px-4 md:px-8 bg-surface shadow-sm z-50 border-b border-maroon/20">
    <div class="flex items-center gap-3">
        <a href="dashboard.php" class="flex items-center gap-2">
            <img src="../base/img/Ratin-logo-1.png" alt="RATIN Logo" class="h-10 w-auto object-contain">
            <span class="font-headline-md text-headline-md text-primary hidden sm:inline-block">RATIN Analytics</span>
        </a>
    </div>

    <div class="flex items-center gap-4">

        
        <div class="h-8 w-px bg-outline-variant hidden md:block"></div>
        
        <!-- User Dropdown -->
        <div class="user-dropdown" id="userDropdown">
            <div class="flex items-center gap-3 cursor-pointer" onclick="toggleUserDropdown()">
                <div class="text-right hidden sm:block">
                    <p class="font-label-md text-label-md text-on-surface"><?= htmlspecialchars($user_name) ?></p>
                    <div class="flex items-center justify-end gap-1 mt-0.5">
                        <span class="material-symbols-outlined text-xs text-on-surface-variant">card_membership</span>
                        <span class="subscription-badge subscription-<?= strtolower($subscription_type) ?>">
                            <?= htmlspecialchars($subscription_display) ?>
                        </span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-full bg-maroon/10 flex items-center justify-center text-maroon font-bold text-base border border-maroon/20">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant hidden sm:inline-block">expand_more</span>
            </div>
            
            <div class="dropdown-menu">
                <a href="user_settings.php" class="dropdown-item">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="dropdown-item">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Main Content Container (no sidebar margin) -->
<main class="pt-20 px-4 md:px-8 pb-8 max-w-7xl mx-auto">

<script>
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    if (!dropdown.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

// Close dropdown on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.remove('active');
    }
});
</script>