<?php
// admin_header.php - Reusable header and sidebar for admin pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit;
}

// Get user info
$user_name = $_SESSION['admin_name'] ?? 'User Profile';
$user_role = isset($_SESSION['admin_role']) ? ucfirst($_SESSION['admin_role']) : 'Administrator';
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
                "sidebar-width":     "260px",
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
/* ── Shared ───────────────────────────────── */
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;
}
body { font-family:'Inter',sans-serif; }

/* ── Sidebar scroll ───────────────────────── */
.sidebar-scroll { overflow-y:auto; overflow-x:hidden; flex:1; }
.sidebar-scroll::-webkit-scrollbar { width:4px; }
.sidebar-scroll::-webkit-scrollbar-track { background:#1a3a1a; }
.sidebar-scroll::-webkit-scrollbar-thumb { background:#4a6a4a; border-radius:4px; }

/* ── Dropdown ─────────────────────────────── */
.dropdown-arrow { transition:transform .2s ease; }
.submenu-item:hover { background-color:rgba(255,255,255,.15)!important; color:#fff!important; }
.submenu-item:hover .material-symbols-outlined { color:#fff!important; }
.nested-item:hover  { background-color:rgba(255,255,255,.12)!important; color:#e2e2e2!important; }

/* ── Logout button ────────────────────────── */
.logout-btn {
    background-color:#800000;
    transition:all .2s ease;
}
.logout-btn:hover {
    background-color:#5e0000;
    transform:translateY(-1px);
}

/* ── Settings button ──────────────────────── */
.settings-btn {
    transition:all .2s ease;
}
.settings-btn:hover {
    background-color:rgba(255,255,255,.1);
}
.settings-btn:hover .material-symbols-outlined {
    transform:rotate(15deg);
}

/* ── Home button ──────────────────────────── */
.home-btn {
    transition:all .2s ease;
}
.home-btn:hover {
    background-color:rgba(128,0,0,0.1);
    transform:translateY(-1px);
}
.home-btn:active {
    transform:translateY(0);
}

/* ── Mobile sidebar overlay ───────────────── */
#sidebar-overlay {
    display:none;
    position:fixed;inset:0;
    background:rgba(0,0,0,.45);
    z-index:39;
}
#sidebar-overlay.active { display:block; }

/* ── Responsive sidebar ───────────────────── */
@media (max-width:768px) {
    #main-sidebar {
        transform:translateX(-100%);
        transition:transform .25s ease;
        z-index:40;
    }
    #main-sidebar.open { transform:translateX(0); }
    #main-header { left:0!important; width:100%!important; }
    #main-content {
        margin-left:0!important;
        padding-left:16px!important;
        padding-right:16px!important;
    }
}
@media (max-width:480px) {
    .kpi-grid { grid-template-columns:1fr!important; }
}

/* ── Hamburger ────────────────────────────── */
.hamburger-btn {
    display:none;
    align-items:center;
    justify-content:center;
    width:40px; height:40px;
    border-radius:8px;
    border:none;
    background:transparent;
    cursor:pointer;
    color:#41493e;
}
@media (max-width:768px) { .hamburger-btn { display:flex; } }
</style>
</head>

<body class="bg-background text-on-background min-h-screen">

<!-- Mobile overlay -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<aside id="main-sidebar" class="fixed left-0 top-0 h-screen w-[260px] bg-primary flex flex-col py-6">

    <!-- Logo -->
    <div class="px-6 mb-6 flex justify-center">
        <img src="../base/img/Ratin-logo-1.png" alt="RATIN Logo" class="h-16 w-auto object-contain">
    </div>

    <div class="px-6 mb-4">
        <h1 class="text-headline-md font-headline-md font-bold text-on-primary text-center">RATIN Analytics</h1>
        <p class="font-body-md text-body-md text-primary-fixed opacity-80 text-center">Agricultural Data Platform</p>
    </div>

    <div class="flex-grow sidebar-scroll">
        <nav>
            <ul class="space-y-1">

                <!-- BASE MANAGEMENT -->
                <li>
                    <div onclick="toggleDropdown(this)" class="flex items-center justify-between px-4 py-3 text-on-primary hover:bg-white/10 transition-all cursor-pointer rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">analytics</span>
                            <span class="font-body-md text-body-md">Base Management</span>
                        </div>
                        <span class="material-symbols-outlined dropdown-arrow text-sm">chevron_right</span>
                    </div>
                    <ul class="dropdown-menu hidden ml-4 space-y-1">
                        <li><a href="../data/countries_boilerplate.php"    class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">flag</span>Countries Covered</a></li>
                        <li><a href="../base/commodity_sources_boilerplate.php"    class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">map</span>Geographic Units</a></li>
                        <li><a href="../base/commodities_boilerplate.php"          class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">inventory_2</span>Commodities</a></li>
                        <li><a href="../base/tradepoints_boilerplate.php"          class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">location_on</span>Trade Points</a></li>
                        <li><a href="../base/enumerator_boilerplate.php"           class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">group</span>Enumerators</a></li>
                    </ul>
                </li>

                <!-- DATA MANAGEMENT -->
                <li>
                    <div onclick="toggleDropdown(this)" class="flex items-center justify-between px-4 py-3 text-on-primary hover:bg-white/10 transition-all cursor-pointer rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">database</span>
                            <span class="font-body-md text-body-md">Data Management</span>
                        </div>
                        <span class="material-symbols-outlined dropdown-arrow text-sm">chevron_right</span>
                    </div>
                    <ul class="dropdown-menu hidden ml-4 space-y-1">
                        <li>
                            <div onclick="toggleNestedDropdown(this)" class="submenu-item flex items-center justify-between px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-sm">store</span>
                                    <span>Market Prices</span>
                                </div>
                                <span class="material-symbols-outlined nested-arrow text-xs">chevron_right</span>
                            </div>
                            <ul class="nested-dropdown hidden ml-6 space-y-1">
                                <li><a href="../data/marketprices_boilerplate.php" class="nested-item flex items-center gap-3 px-4 py-2 text-primary-fixed/80 hover:text-white transition-all rounded-lg text-xs"><span class="material-symbols-outlined text-xs">list</span>Prices</a></li>
                                <li><a href="../data/datasource_boilerplate.php"   class="nested-item flex items-center gap-3 px-4 py-2 text-primary-fixed/80 hover:text-white transition-all rounded-lg text-xs"><span class="material-symbols-outlined text-xs">database</span>Data Sources</a></li>
                            </ul>
                        </li>
                        <li><a href="../data/xbtvol_boilerplate.php"            class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">swap_horiz</span>XBT Volumes</a></li>
                        <li><a href="../data/miller_price_boilerplate.php"      class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">factory</span>Miller Prices</a></li>
                        <li><a href="../data/post_harvest_data_dash.php" class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">grass</span>Post Harvest Data</a></li>
                        <li><a href="../data/currencies_boilerplate.php"        class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">attach_money</span>Currency Rates</a></li>
                        <li><a href="../data/market_submission_monitoring.php"  class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">monitoring</span>Submission monitor</a></li>
                    </ul>
                </li>

                <!-- WEB MANAGEMENT -->
                <li>
                    <div onclick="toggleDropdown(this)" class="flex items-center justify-between px-4 py-3 text-on-primary hover:bg-white/10 transition-all cursor-pointer rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">web</span>
                            <span class="font-body-md text-body-md">Web Management</span>
                        </div>
                        <span class="material-symbols-outlined dropdown-arrow text-sm">chevron_right</span>
                    </div>
                    <ul class="dropdown-menu hidden ml-4 space-y-1">
                        <li><a href="https://ratin.net/home/" target="_blank"  class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">language</span>WebSite</a></li>
                        <li><a href="../frontend/marketprices.php"             class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">show_chart</span>Data display</a></li>
                        <li><a href="../news-system/index.php"                 class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">news</span>Website manager</a></li>
                        <!-- NEW: User Feedback Dashboard -->
                        <li><a href="../admin/user_feedback_dashboard.php"     class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">feedback</span>User Feedback</a></li>
                        <li><a href="../frontend/predictive_analysis.php"     class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">monitoring </span>Predictive Analysis</a></li>
                    </ul>
                </li>

                <!-- ADMIN with Admin Management submenu -->
                <li class="mt-2">
                    <div onclick="toggleDropdown(this)" class="flex items-center justify-between px-4 py-3 text-on-primary hover:bg-white/10 transition-all cursor-pointer rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                            <span class="font-body-md text-body-md">Admin</span>
                        </div>
                        <span class="material-symbols-outlined dropdown-arrow text-sm">chevron_right</span>
                    </div>
                    <ul class="dropdown-menu hidden ml-4 space-y-1">
                        <li><a href="../admin/user_management.php" class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">manage_accounts</span>User subscription</a></li>
                        <li><a href="../admin/manage_admin.php" class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">admin_panel_settings</span>Admin Management</a></li>
                    </ul>
                </li>

            </ul>
        </nav>
    </div>

    <!-- SETTINGS MENU - Just above logout button -->
    <div class="px-4 mb-2">
        <a href="../admin/user_settings.php" class="settings-btn flex items-center gap-3 px-4 py-2.5 rounded-lg text-primary-fixed hover:text-white transition-all">
            <span class="material-symbols-outlined text-lg">settings</span>
            <span class="font-body-md text-body-md">Settings</span>
        </a>
    </div>

    <!-- LOGOUT -->
    <div class="px-4 mb-2">
        <a href="../admin/logout.php" class="logout-btn flex items-center justify-center gap-2 w-full py-2.5 rounded-lg text-white font-medium transition-all">
            <span class="material-symbols-outlined text-lg">logout</span>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- ═══════════════════════════════════════════
     TOP BAR
═══════════════════════════════════════════ -->
<header id="main-header" class="fixed top-0 left-[260px] right-0 h-16 flex justify-between items-center px-4 md:px-6 bg-surface shadow-sm z-10 border-b border-maroon/20">
    <div class="flex items-center gap-3 flex-1 min-w-0">
        <button class="hamburger-btn" onclick="openSidebar()" aria-label="Open menu">
            <span class="material-symbols-outlined">menu</span>
        </button>
        
        <!-- HOME BUTTON -->
        <a href="../base/landing_page.php" class="home-btn flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-on-surface-variant hover:text-maroon transition-all group" title="Go to Dashboard">
            <span class="material-symbols-outlined text-xl group-hover:scale-110 transition-transform">home</span>
            <span class="text-sm font-medium hidden sm:inline-block group-hover:text-maroon">Dashboard</span>
        </a>
    </div>

    <div class="flex items-center gap-3">
        <div class="h-8 w-px bg-outline-variant mx-1 hidden sm:block"></div>
        <div class="flex items-center gap-2 pl-2 cursor-pointer group">
            <div class="text-right hidden sm:block">
                <p class="font-label-md text-label-md text-on-surface group-hover:text-maroon transition-colors"><?= htmlspecialchars($user_name) ?></p>
                <p class="text-[10px] text-on-surface-variant"><?= htmlspecialchars($user_role) ?></p>
            </div>
            <div class="w-9 h-9 rounded-full bg-maroon/10 flex items-center justify-center text-maroon font-bold text-base border border-maroon/20">
                <?= strtoupper(substr($user_name, 0, 1)) ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Content Container -->
<main id="main-content" class="ml-[260px] pt-20 px-4 md:px-6 pb-8">

<script>
function openSidebar() {
    document.getElementById('main-sidebar').classList.add('open');
    document.getElementById('sidebar-overlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('main-sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('active');
    document.body.style.overflow = '';
}
function toggleDropdown(el) {
    const menu = el.nextElementSibling;
    const arrow = el.querySelector('.dropdown-arrow');
    const open = !menu.classList.contains('hidden');
    menu.classList.toggle('hidden', open);
    if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
}
function toggleNestedDropdown(el) {
    const menu = el.nextElementSibling;
    const arrow = el.querySelector('.nested-arrow');
    const open = !menu.classList.contains('hidden');
    menu.classList.toggle('hidden', open);
    if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
</script>