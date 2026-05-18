<?php
// ============================================
// MUST BE AT THE VERY TOP - NO OUTPUT BEFORE THIS
// ============================================
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

include '../admin/includes/config.php';

// ============================================
// 1. TOTAL COMMODITIES KPI
// ============================================
$total_commodities = 0;
$new_commodities   = 0;

$r = $con->query("SELECT COUNT(*) as total FROM commodities");
if ($r) $total_commodities = $r->fetch_assoc()['total'];

$r = $con->query("SELECT COUNT(*) as new_count FROM commodities WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($r) $new_commodities = $r->fetch_assoc()['new_count'];

// ============================================
// 2. TOTAL ENUMERATORS KPI
// ============================================
$total_enumerators = 0;
$r = $con->query("SELECT COUNT(*) as total FROM enumerators");
if ($r) $total_enumerators = $r->fetch_assoc()['total'];

// ============================================
// 3. TOTAL TRADEPOINTS KPI
// ============================================
$total_tradepoints = 0;
$countries_count   = 0;

$r = $con->query("SELECT COUNT(*) as total FROM markets");
if ($r) $total_tradepoints = $r->fetch_assoc()['total'];

$r = $con->query("SELECT COUNT(DISTINCT country) as countries FROM markets");
if ($r) $countries_count = $r->fetch_assoc()['countries'];

// ============================================
// 4. TOTAL ADMIN USERS KPI
// ============================================
$total_admin_users = 0;
$r = $con->query("SELECT COUNT(*) as total FROM admin_users");
if ($r) $total_admin_users = $r->fetch_assoc()['total'];

// ============================================
// 5. SUBMISSION TRENDS — last 7 days
// ============================================
$trends_data = [];

$r = $con->query(
    "SELECT DATE(date_posted) as submission_date, COUNT(*) as submission_count
     FROM market_prices
     WHERE date_posted >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(date_posted)
     ORDER BY submission_date ASC"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $trends_data[$row['submission_date']] = (int)$row['submission_count'];
    }
}

// ── Markets per day (dual-axis) ───────────────────────────────
$markets_per_day_raw = [];
$r = $con->query(
    "SELECT DATE(date_posted) as d, COUNT(DISTINCT market_id) as mc
     FROM market_prices
     WHERE date_posted >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(date_posted)"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $markets_per_day_raw[$row['d']] = (int)$row['mc'];
    }
}

// Build exactly 7 labelled data points, filling gaps with 0
$days_of_week        = [];
$chart_data_points   = [];
$markets_data_points = [];

for ($i = 6; $i >= 0; $i--) {
    $date                  = date('Y-m-d', strtotime("-$i days"));
    $days_of_week[]        = date('D', strtotime($date));
    $chart_data_points[]   = $trends_data[$date] ?? 0;
    $markets_data_points[] = $markets_per_day_raw[$date] ?? 0;
}

// JSON for JS
$js_submissions = json_encode($chart_data_points);
$js_markets     = json_encode($markets_data_points);
$js_days        = json_encode($days_of_week);

// ============================================
// 6. CURRENCY RATES TABLE
// ============================================
$currencies = [];

$r = $con->query(
    "SELECT currency_code, exchange_rate, effective_date
     FROM currencies
     WHERE effective_date = (SELECT MAX(effective_date) FROM currencies)
     ORDER BY currency_code"
);
if ($r) while ($row = $r->fetch_assoc()) $currencies[] = $row;

// ============================================
// 7. User info
// ============================================
$user_name = $_SESSION['admin_name'] ?? 'User Profile';
$user_role = isset($_SESSION['admin_role']) ? ucfirst($_SESSION['admin_role']) : 'Administrator';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RATIN Analytics - Admin Dashboard</title>
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
    .kpi-grid  { grid-template-columns:1fr 1fr!important; }
    .chart-row { grid-template-columns:1fr!important; }
}
@media (max-width:480px) {
    .kpi-grid { grid-template-columns:1fr!important; }
}

/* ── KPI grid ─────────────────────────────── */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }

/* ── Chart row ────────────────────────────── */
.chart-row { display:grid; grid-template-columns:1fr; gap:16px; }

/* ── Currency table responsive ────────────── */
@media (max-width:600px) {
    .currency-table th:last-child,
    .currency-table td:last-child { display:none; }
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

/* ── Chart card ───────────────────────────── */
.trend-card {
    background:#141414;
    border:1px solid #272727;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 4px 24px rgba(0,0,0,0.22);
}
.trend-card-header {
    display:flex;
    flex-wrap:wrap;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    padding:24px 24px 18px;
    border-bottom:1px solid #222;
}
.trend-canvas-wrap {
    position:relative;
    padding:20px 16px 12px;
    /* min-height so layout never collapses before JS runs */
    min-height:300px;
}
#submissionTrendChart {
    width:100%;
    display:block;
    cursor:crosshair;
}
#chartTooltip {
    display:none;
    position:absolute;
    pointer-events:none;
    background:rgba(22,22,22,0.96);
    border:1px solid #333;
    border-radius:10px;
    padding:12px 16px;
    min-width:168px;
    box-shadow:0 8px 28px rgba(0,0,0,0.55);
    z-index:10;
}
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
                        <li><a href="commodity_sources_boilerplate.php"    class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">map</span>Geographic Units</a></li>
                        <li><a href="commodities_boilerplate.php"          class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">inventory_2</span>Commodities</a></li>
                        <li><a href="tradepoints_boilerplate.php"          class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">location_on</span>Trade Points</a></li>
                        <li><a href="enumerator_boilerplate.php"           class="submenu-item flex items-center gap-3 px-4 py-2 text-primary-fixed hover:text-white transition-all rounded-lg text-sm"><span class="material-symbols-outlined text-sm">group</span>Enumerators</a></li>
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
                    </ul>
                </li>

                <!-- ADMIN -->
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
    <div class="px-4 mt-4 mb-2">
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

<!-- ═══════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════ -->
<main id="main-content" class="ml-[260px] pt-24 px-4 md:px-6 pb-16">

    <!-- Welcome -->
    <div class="mb-8">
        <h2 class="font-headline-lg text-headline-lg text-maroon text-2xl md:text-4xl">Market Intelligence Overview</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Real-time agricultural commodity tracking and administrative oversight.</p>
    </div>

    <!-- ── KPI Cards ───────────────────────────── -->
    <section class="kpi-grid mb-gutter">

        <div class="bg-surface-container-low border border-outline-variant p-5 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)]">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-maroon bg-maroon/10 p-2 rounded-full">inventory_2</span>
                <span class="text-maroon font-bold text-label-md">+<?= $new_commodities ?></span>
            </div>
            <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Total Commodities</p>
            <h3 class="font-headline-md text-headline-md text-on-surface mt-1"><?= number_format($total_commodities) ?></h3>
        </div>

        <div class="bg-surface-container-low border border-outline-variant p-5 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)]">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-secondary bg-secondary-fixed p-2 rounded-full">person_search</span>
                <span class="text-maroon font-bold text-label-md">Active</span>
            </div>
            <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Total Enumerators</p>
            <h3 class="font-headline-md text-headline-md text-on-surface mt-1"><?= number_format($total_enumerators) ?></h3>
        </div>

        <div class="bg-surface-container-low border border-outline-variant p-5 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)]">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-tertiary bg-tertiary-fixed p-2 rounded-full">location_on</span>
                <span class="text-maroon font-bold text-label-md"><?= $countries_count ?> Countries</span>
            </div>
            <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Total Tradepoints</p>
            <h3 class="font-headline-md text-headline-md text-on-surface mt-1"><?= number_format($total_tradepoints) ?></h3>
        </div>

        <div class="bg-surface-container-low border border-outline-variant p-5 rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)]">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-maroon bg-maroon/10 p-2 rounded-full">admin_panel_settings</span>
                <span class="text-maroon font-bold text-label-md">Verified</span>
            </div>
            <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Total Admin Users</p>
            <h3 class="font-headline-md text-headline-md text-on-surface mt-1"><?= number_format($total_admin_users) ?></h3>
        </div>

    </section>

    <!-- ── Submission Trends Chart ─────────────── -->
    <section class="chart-row mb-gutter">
        <div class="trend-card">

            <!-- Header -->
            <div class="trend-card-header">
                <div>
                    <h4 style="color:#f0f0f0; font-size:1.25rem; font-weight:700; margin:0 0 4px;">
                        Submission Trends
                    </h4>
                    <p style="color:#666; font-size:13px; margin:0;">
                        Agricultural data entries &amp; active markets per day — last 7 days
                    </p>
                </div>

                <!-- Legend -->
                <div style="display:flex; gap:24px; align-items:center; padding-top:2px;">
                    <span style="display:flex; align-items:center; gap:8px; color:#aaa; font-size:12px; font-weight:500;">
                        <svg width="30" height="12" style="flex-shrink:0">
                            <line x1="0" y1="6" x2="30" y2="6"
                                  stroke="#e53935" stroke-width="2.5" stroke-linecap="round"/>
                            <circle cx="15" cy="6" r="3.5" fill="#e53935" stroke="#141414" stroke-width="1.5"/>
                        </svg>
                        Submissions
                    </span>
                    <span style="display:flex; align-items:center; gap:8px; color:#aaa; font-size:12px; font-weight:500;">
                        <svg width="30" height="12" style="flex-shrink:0">
                            <line x1="0" y1="6" x2="30" y2="6"
                                  stroke="#43a047" stroke-width="2.5"
                                  stroke-dasharray="5,3" stroke-linecap="round"/>
                            <rect x="11.5" y="2.5" width="7" height="7"
                                  fill="#43a047" stroke="#141414" stroke-width="1.5"/>
                        </svg>
                        Markets
                    </span>
                </div>
            </div>

            <!-- Canvas -->
            <div class="trend-canvas-wrap">
                <canvas id="submissionTrendChart" height="290"></canvas>

                <!-- Tooltip -->
                <div id="chartTooltip">
                    <div id="tooltipDay"
                         style="font-weight:700; font-size:15px; color:#f0f0f0; margin-bottom:10px;"></div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                        <span style="width:10px; height:10px; background:#e53935;
                                     border-radius:2px; flex-shrink:0;"></span>
                        <span id="tooltipSubs" style="color:#ccc; font-size:13px;"></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:10px; height:10px; background:#43a047;
                                     border-radius:2px; flex-shrink:0;"></span>
                        <span id="tooltipMkts" style="color:#ccc; font-size:13px;"></span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ── Currency Rates Table ────────────────── -->
    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-[0px_4px_12px_rgba(0,0,0,0.05)] overflow-hidden">

        <div class="p-4 md:p-6 border-b border-outline-variant flex flex-wrap justify-between items-center gap-3">
            <h4 class="font-headline-md text-headline-md text-maroon text-xl md:text-2xl">Currency Rates</h4>
            <button onclick="location.reload()" class="flex items-center gap-2 text-on-surface-variant font-label-md text-label-md border border-outline-variant px-3 py-1.5 rounded-lg hover:bg-maroon/10 hover:text-maroon hover:border-maroon transition-all">
                <span class="material-symbols-outlined text-[18px]">sync</span>
                Refresh Rates
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left currency-table min-w-[400px]">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th class="px-4 md:px-6 py-4 font-label-md text-label-md text-on-surface-variant uppercase">Currency Pair</th>
                        <th class="px-4 md:px-6 py-4 font-label-md text-label-md text-on-surface-variant uppercase">Current Rate</th>
                        <th class="px-4 md:px-6 py-4 font-label-md text-label-md text-on-surface-variant uppercase">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($currencies)): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-on-surface-variant">No currency rates available</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($currencies as $currency):
                        $rate = number_format($currency['exchange_rate'], 4);
                        $date = date('M j, Y', strtotime($currency['effective_date']));
                    ?>
                    <tr class="hover:bg-maroon/5 transition-colors">
                        <td class="px-4 md:px-6 py-4">
                            <span class="font-bold text-on-surface"><?= strtoupper($currency['currency_code']) ?> / USD</span>
                        </td>
                        <td class="px-4 md:px-6 py-4 font-data-tabular text-data-tabular text-on-surface font-semibold"><?= $rate ?></td>
                        <td class="px-4 md:px-6 py-4 font-body-md text-body-md text-on-surface-variant"><?= $date ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-4 md:px-6 py-4 border-t border-outline-variant flex justify-between items-center flex-wrap gap-2">
            <span class="text-label-md text-on-surface-variant">Showing active cross-border currency pairs</span>
            <div class="flex gap-2">
                <button class="p-2 border border-outline-variant rounded hover:bg-maroon/10 hover:text-maroon transition-colors cursor-not-allowed opacity-50" disabled>
                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                </button>
                <button class="p-2 border border-outline-variant rounded hover:bg-maroon/10 hover:text-maroon transition-colors cursor-not-allowed opacity-50" disabled>
                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                </button>
            </div>
        </div>

    </section>

</main>

<!-- ═══════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════ -->
<script>
// ── Sidebar toggle ────────────────────────────────────────────
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

// ── Dropdown menus ────────────────────────────────────────────
function toggleDropdown(el) {
    const menu  = el.nextElementSibling;
    const arrow = el.querySelector('.dropdown-arrow');
    const open  = !menu.classList.contains('hidden');
    menu.classList.toggle('hidden', open);
    if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
}
function toggleNestedDropdown(el) {
    const menu  = el.nextElementSibling;
    const arrow = el.querySelector('.nested-arrow');
    const open  = !menu.classList.contains('hidden');
    menu.classList.toggle('hidden', open);
    if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
}
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) closeSidebar();
});
</script>

<script>
// ═══════════════════════════════════════════
//  DUAL-AXIS CANVAS CHART
// ═══════════════════════════════════════════
(function () {
    /* ── PHP data ── */
    const submissions = <?= $js_submissions ?>;
    const markets     = <?= $js_markets ?>;
    const days        = <?= $js_days ?>;

    /* ── DOM refs ── */
    const canvas  = document.getElementById('submissionTrendChart');
    const wrap    = canvas.parentElement;
    const tooltip = document.getElementById('chartTooltip');
    const ttDay   = document.getElementById('tooltipDay');
    const ttSubs  = document.getElementById('tooltipSubs');
    const ttMkts  = document.getElementById('tooltipMkts');

    /* ── layout ── */
    const PAD_L = 54,   // left  – submission labels (red)
          PAD_R = 50,   // right – market labels (green)
          PAD_T = 24,
          PAD_B = 38;   // bottom – day names

    /* ── helpers ── */
    function niceMax(arr) {
        const m   = Math.max(...arr, 1);
        const mag = Math.pow(10, Math.floor(Math.log10(m)));
        return Math.ceil(m / mag) * mag;
    }

    /*
     * Monotone cubic interpolation (Fritsch-Carlson).
     * Guarantees the curve NEVER overshoots between data points,
     * so it can never dip below 0 or above the chart ceiling.
     */
    function smoothPath(ctx, pts) {
        const n = pts.length;
        if (!n) return;
        if (n === 1) { ctx.moveTo(pts[0].x, pts[0].y); return; }

        // 1. Compute secant slopes and x-deltas between consecutive points
        const dx = [], slope = [];
        for (let i = 0; i < n - 1; i++) {
            dx[i]    = pts[i + 1].x - pts[i].x;
            slope[i] = (pts[i + 1].y - pts[i].y) / dx[i];
        }

        // 2. Initialise tangent magnitudes (m) at each point
        const m = new Array(n);
        m[0]     = slope[0];
        m[n - 1] = slope[n - 2];
        for (let i = 1; i < n - 1; i++) {
            // Flat when slopes change sign (local extremum) — prevents overshoot
            if (slope[i - 1] * slope[i] <= 0) {
                m[i] = 0;
            } else {
                m[i] = (slope[i - 1] + slope[i]) / 2;
            }
        }

        // 3. Fritsch-Carlson monotonicity constraint
        for (let i = 0; i < n - 1; i++) {
            if (Math.abs(slope[i]) < 1e-10) {
                m[i] = m[i + 1] = 0;
            } else {
                const alpha = m[i]     / slope[i];
                const beta  = m[i + 1] / slope[i];
                const s     = alpha * alpha + beta * beta;
                if (s > 9) {
                    const t    = 3 / Math.sqrt(s);
                    m[i]       = t * alpha * slope[i];
                    m[i + 1]   = t * beta  * slope[i];
                }
            }
        }

        // 4. Draw cubic bezier segments using the constrained tangents
        ctx.moveTo(pts[0].x, pts[0].y);
        for (let i = 0; i < n - 1; i++) {
            const h    = dx[i];
            const cp1x = pts[i].x     + h / 3;
            const cp1y = pts[i].y     + m[i]     * h / 3;
            const cp2x = pts[i+1].x  - h / 3;
            const cp2y = pts[i+1].y  - m[i + 1] * h / 3;
            ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, pts[i+1].x, pts[i+1].y);
        }
    }

    /* ── main draw ── */
    let cachedSubPts = [];

    function draw(highlightIdx = -1) {
        const dpr   = window.devicePixelRatio || 1;
        const W     = canvas.offsetWidth || wrap.offsetWidth;
        const H     = 290;
        canvas.width  = W * dpr;
        canvas.height = H * dpr;
        canvas.style.height = H + 'px';

        const ctx   = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        const plotW = W - PAD_L - PAD_R;
        const plotH = H - PAD_T - PAD_B;
        const n     = submissions.length;

        /* scales */
        const subMax = niceMax(submissions);
        const mktMax = niceMax(markets);
        const GRIDS  = 5;

        function xOf(i)  { return PAD_L + (i / (n - 1)) * plotW; }
        function ySub(v) { return PAD_T + plotH - (v / subMax) * plotH; }
        function yMkt(v) { return PAD_T + plotH - (v / mktMax) * plotH; }

        /* background */
        ctx.fillStyle = '#141414';
        ctx.fillRect(0, 0, W, H);

        /* horizontal grid */
        ctx.setLineDash([3, 4]);
        ctx.lineWidth = 1;
        for (let g = 0; g <= GRIDS; g++) {
            const y = PAD_T + (g / GRIDS) * plotH;
            ctx.strokeStyle = g === GRIDS ? '#2a2a2a' : '#1f1f1f';
            ctx.beginPath();
            ctx.moveTo(PAD_L, y);
            ctx.lineTo(PAD_L + plotW, y);
            ctx.stroke();
        }
        ctx.setLineDash([]);

        /* Y-axis labels — left (submissions, red) */
        ctx.font      = '11px Inter, sans-serif';
        ctx.textAlign = 'right';
        for (let g = 0; g <= GRIDS; g++) {
            const y   = PAD_T + (g / GRIDS) * plotH;
            const val = Math.round(subMax * (1 - g / GRIDS));
            ctx.fillStyle = 'rgba(229,57,53,0.75)';
            ctx.fillText(val, PAD_L - 8, y + 4);
        }

        /* Y-axis labels — right (markets, green) */
        ctx.textAlign = 'left';
        for (let g = 0; g <= GRIDS; g++) {
            const y   = PAD_T + (g / GRIDS) * plotH;
            const val = Math.round(mktMax * (1 - g / GRIDS));
            ctx.fillStyle = 'rgba(67,160,71,0.75)';
            ctx.fillText(val, PAD_L + plotW + 10, y + 4);
        }

        /* X-axis labels */
        ctx.textAlign = 'center';
        ctx.fillStyle = '#555';
        ctx.font      = '11px Inter, sans-serif';
        for (let i = 0; i < n; i++) {
            ctx.fillText(days[i], xOf(i), PAD_T + plotH + 22);
        }

        /* build point arrays */
        const subPts = submissions.map((v, i) => ({ x: xOf(i), y: ySub(v), v }));
        const mktPts = markets.map((v, i)     => ({ x: xOf(i), y: yMkt(v), v }));
        cachedSubPts = subPts;

        /* ── area fill – submissions ── */
        const areaGradSub = ctx.createLinearGradient(0, PAD_T, 0, PAD_T + plotH);
        areaGradSub.addColorStop(0,   'rgba(229,57,53,0.20)');
        areaGradSub.addColorStop(0.7, 'rgba(229,57,53,0.04)');
        areaGradSub.addColorStop(1,   'rgba(229,57,53,0)');
        ctx.save();
        ctx.beginPath();
        smoothPath(ctx, subPts);
        ctx.lineTo(xOf(n - 1), PAD_T + plotH);
        ctx.lineTo(PAD_L, PAD_T + plotH);
        ctx.closePath();
        ctx.fillStyle = areaGradSub;
        ctx.fill();
        ctx.restore();

        /* ── area fill – markets ── */
        const areaGradMkt = ctx.createLinearGradient(0, PAD_T, 0, PAD_T + plotH);
        areaGradMkt.addColorStop(0,   'rgba(67,160,71,0.14)');
        areaGradMkt.addColorStop(0.7, 'rgba(67,160,71,0.03)');
        areaGradMkt.addColorStop(1,   'rgba(67,160,71,0)');
        ctx.save();
        ctx.beginPath();
        smoothPath(ctx, mktPts);
        ctx.lineTo(xOf(n - 1), PAD_T + plotH);
        ctx.lineTo(PAD_L, PAD_T + plotH);
        ctx.closePath();
        ctx.fillStyle = areaGradMkt;
        ctx.fill();
        ctx.restore();

        /* ── highlight crosshair ── */
        if (highlightIdx >= 0) {
            const hx = subPts[highlightIdx].x;
            ctx.save();
            ctx.strokeStyle = 'rgba(255,255,255,0.10)';
            ctx.lineWidth   = 1;
            ctx.setLineDash([3, 4]);
            ctx.beginPath();
            ctx.moveTo(hx, PAD_T);
            ctx.lineTo(hx, PAD_T + plotH);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.restore();
        }

        /* ── line – submissions (solid red) ── */
        ctx.save();
        ctx.beginPath();
        smoothPath(ctx, subPts);
        ctx.strokeStyle = '#e53935';
        ctx.lineWidth   = 2.5;
        ctx.lineJoin    = 'round';
        ctx.lineCap     = 'round';
        ctx.stroke();
        ctx.restore();

        /* ── line – markets (dashed green) ── */
        ctx.save();
        ctx.setLineDash([6, 4]);
        ctx.beginPath();
        smoothPath(ctx, mktPts);
        ctx.strokeStyle = '#43a047';
        ctx.lineWidth   = 2.5;
        ctx.lineJoin    = 'round';
        ctx.lineCap     = 'round';
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.restore();

        /* ── dots – submissions (circles) ── */
        subPts.forEach((p, i) => {
            const isHl = i === highlightIdx;
            const r    = isHl ? 6 : (p.v > 0 ? 4.5 : 3);
            ctx.beginPath();
            ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
            ctx.fillStyle   = p.v > 0 ? '#e53935' : '#2a2a2a';
            ctx.strokeStyle = isHl ? '#fff' : '#141414';
            ctx.lineWidth   = isHl ? 2 : 1.5;
            ctx.fill();
            ctx.stroke();
        });

        /* ── dots – markets (squares) ── */
        mktPts.forEach((p, i) => {
            const isHl = i === highlightIdx;
            const s    = isHl ? 9 : (p.v > 0 ? 7 : 5);
            ctx.fillStyle   = p.v > 0 ? '#43a047' : '#2a2a2a';
            ctx.strokeStyle = isHl ? '#fff' : '#141414';
            ctx.lineWidth   = isHl ? 2 : 1.5;
            ctx.beginPath();
            ctx.rect(p.x - s / 2, p.y - s / 2, s, s);
            ctx.fill();
            ctx.stroke();
        });
    }

    /* initial render */
    draw();
    window.addEventListener('resize', () => draw());

    /* ── hover / tooltip ── */
    canvas.addEventListener('mousemove', function (e) {
        const rect   = canvas.getBoundingClientRect();
        const mx     = e.clientX - rect.left;
        const n      = cachedSubPts.length;
        if (!n) return;

        /* find nearest data point by x */
        let best = 0, bestD = Infinity;
        for (let i = 0; i < n; i++) {
            const d = Math.abs(cachedSubPts[i].x - mx);
            if (d < bestD) { bestD = d; best = i; }
        }

        /* ignore if cursor is far from any point */
        const plotW = (canvas.offsetWidth || wrap.offsetWidth) - PAD_L - PAD_R;
        if (bestD > plotW / n) {
            tooltip.style.display = 'none';
            draw();
            return;
        }

        /* tooltip position — flip left if near right edge */
        const wrapRect = wrap.getBoundingClientRect();
        let tx = e.clientX - wrapRect.left + 14;
        if (tx + 185 > wrap.offsetWidth) tx = e.clientX - wrapRect.left - 185;
        const ty = e.clientY - wrapRect.top - 36;

        tooltip.style.display = 'block';
        tooltip.style.left    = Math.max(0, tx) + 'px';
        tooltip.style.top     = Math.max(0, ty) + 'px';

        ttDay.textContent  = days[best];
        ttSubs.textContent = submissions[best].toLocaleString() + ' submissions';
        ttMkts.textContent = markets[best].toLocaleString() + ' markets';

        draw(best);
    });

    canvas.addEventListener('mouseleave', () => {
        tooltip.style.display = 'none';
        draw();
    });
})();
</script>

</body>
</html>