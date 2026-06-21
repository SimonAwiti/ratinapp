<?php
// xbt_volumes.php
session_start();

// ============================================================
// EXPORT CSV — must run BEFORE admin_header.php is included
// ============================================================
if (isset($_GET['export_csv'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="xbt_volumes_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $search_border = $_GET['search_border'] ?? '';
    $search_commodity = $_GET['search_commodity'] ?? '';
    $search_source = $_GET['search_source'] ?? '';
    $search_destination = $_GET['search_destination'] ?? '';

    $where_export = "WHERE x.status = 'published'";
    if (!empty($search_border)) {
        $where_export .= " AND b.name LIKE '%" . $con->real_escape_string($search_border) . "%'";
    }
    if (!empty($search_commodity)) {
        $where_export .= " AND (c.commodity_name LIKE '%" . $con->real_escape_string($search_commodity) . "%' OR c.variety LIKE '%" . $con->real_escape_string($search_commodity) . "%')";
    }
    if (!empty($search_source)) {
        $where_export .= " AND x.source LIKE '%" . $con->real_escape_string($search_source) . "%'";
    }
    if (!empty($search_destination)) {
        $where_export .= " AND x.destination LIKE '%" . $con->real_escape_string($search_destination) . "%'";
    }

    $exp_query = "SELECT 
        x.id, b.name as border_name, 
        CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
        x.volume, x.source, x.destination, DATE(x.date_posted) as volume_date, 
        x.status, ds.data_source_name as data_source
        FROM xbt_volumes x
        LEFT JOIN border_points b ON x.border_id = b.id
        LEFT JOIN commodities c ON x.commodity_id = c.id
        LEFT JOIN data_sources ds ON x.data_source_id = ds.id
        $where_export
        ORDER BY x.date_posted DESC";
    
    $exp_result = $con->query($exp_query);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Border Point', 'Commodity', 'Volume (MT)', 'Source', 'Destination', 'Date', 'Status', 'Data Source']);

    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['border_name'], $row['commodity_display'],
            number_format($row['volume'], 2, '.', ''), $row['source'], $row['destination'],
            $row['volume_date'], $row['status'], $row['data_source']
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// CHECK ADMIN LOGIN
// ============================================================
require_once 'user_header.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

// ============================================================
// INCLUDE CONFIG
// ============================================================
if (file_exists('includes/config.php')) include 'includes/config.php';
elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

// ============================================================
// STATISTICS - Only published
// ============================================================
$total_volumes = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes WHERE status = 'published'")->fetch_assoc()['t'] ?? 0);
$published_count = (int)($con->query("SELECT COUNT(*) as t FROM xbt_volumes WHERE status = 'published'")->fetch_assoc()['t'] ?? 0);

// Get distinct borders for filter (only published)
$borders_result = $con->query("SELECT DISTINCT b.id, b.name FROM xbt_volumes x LEFT JOIN border_points b ON x.border_id = b.id WHERE x.status = 'published' ORDER BY b.name");
$distinct_borders = [];
while ($row = $borders_result->fetch_assoc()) {
    if ($row['name']) $distinct_borders[] = $row['name'];
}

// Get distinct sources/destinations (only published)
$sources_result = $con->query("SELECT DISTINCT source FROM xbt_volumes WHERE status = 'published' ORDER BY source");
$distinct_sources = [];
while ($row = $sources_result->fetch_assoc()) {
    if ($row['source']) $distinct_sources[] = $row['source'];
}

$destinations_result = $con->query("SELECT DISTINCT destination FROM xbt_volumes WHERE status = 'published' ORDER BY destination");
$distinct_destinations = [];
while ($row = $destinations_result->fetch_assoc()) {
    if ($row['destination']) $distinct_destinations[] = $row['destination'];
}

// ============================================================
// PAGINATION + SORTING + FILTERING - Only published
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts = ['id', 'border', 'commodity', 'volume', 'source', 'destination', 'date_posted', 'status', 'data_source'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'date_posted';

$search_border = trim($_GET['search_border'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');
$search_source = trim($_GET['search_source'] ?? '');
$search_destination = trim($_GET['search_destination'] ?? '');

$where = "WHERE x.status = 'published'";
$params = [];
$types = "";

if ($search_border !== '') {
    $where .= " AND b.name LIKE ?";
    $params[] = '%' . $search_border . '%';
    $types .= "s";
}
if ($search_commodity !== '') {
    $where .= " AND (c.commodity_name LIKE ? OR c.variety LIKE ?)";
    $params[] = '%' . $search_commodity . '%';
    $params[] = '%' . $search_commodity . '%';
    $types .= "ss";
}
if ($search_source !== '') {
    $where .= " AND x.source LIKE ?";
    $params[] = '%' . $search_source . '%';
    $types .= "s";
}
if ($search_destination !== '') {
    $where .= " AND x.destination LIKE ?";
    $params[] = '%' . $search_destination . '%';
    $types .= "s";
}

// Count total records
$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM xbt_volumes x LEFT JOIN border_points b ON x.border_id = b.id LEFT JOIN commodities c ON x.commodity_id = c.id LEFT JOIN data_sources ds ON x.data_source_id = ds.id $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($page - 1) * $limit;

// Map sort column to database column
$sort_map = [
    'id' => 'x.id',
    'border' => 'b.name',
    'commodity' => 'c.commodity_name',
    'volume' => 'x.volume',
    'source' => 'x.source',
    'destination' => 'x.destination',
    'date_posted' => 'x.date_posted',
    'status' => 'x.status',
    'data_source' => 'ds.data_source_name'
];
$order_by = $sort_map[$sort_column] ?? 'x.date_posted';
$dir = $sort_direction === 'ASC' ? 'ASC' : 'DESC';

// Fetch data
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$query = "SELECT 
    x.id, x.volume, x.source, x.destination, x.date_posted, x.status,
    b.name as border_name,
    CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
    ds.data_source_name as data_source
    FROM xbt_volumes x
    LEFT JOIN border_points b ON x.border_id = b.id
    LEFT JOIN commodities c ON x.commodity_id = c.id
    LEFT JOIN data_sources ds ON x.data_source_id = ds.id
    $where 
    ORDER BY $order_by $dir
    LIMIT ? OFFSET ?";

$data_stmt = $con->prepare($query);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$xbt_volumes = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

$showing_from = $filtered_records > 0 ? $offset + 1 : 0;
$showing_to = $filtered_records > 0 ? min($offset + $limit, $filtered_records) : 0;

function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="status-badge status-pending">Pending</span>';
        case 'approved': return '<span class="status-badge status-approved">Approved</span>';
        case 'published': return '<span class="status-badge status-published">Published</span>';
        case 'unpublished': return '<span class="status-badge status-unpublished">Unpublished</span>';
        default: return '<span class="status-badge">Unknown</span>';
    }
}
?>

<style>
.auth-bg-gradient{background:radial-gradient(circle at top left,rgba(0,69,13,.03),transparent),radial-gradient(circle at bottom right,rgba(128,0,0,.03),transparent)}
.header-accent-gradient{background:linear-gradient(90deg,#00450d 0%,#800000 50%,#00450d 100%)}
.table-row-hover:hover{background-color:#fefaf5;transition:all .2s ease}
.stat-card{transition:all .2s ease;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.search-input:focus{border-color:#800000;outline:none}
.pagination-btn{min-width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:.375rem;font-size:.75rem;transition:all .2s ease;cursor:pointer;border:1px solid #e5e7eb;background:white;color:#374151}
.pagination-btn:hover:not(:disabled):not(.active-page){background-color:#fef3e7;border-color:#800000;color:#800000}
.pagination-btn.active-page{background-color:#800000;border-color:#800000;color:white;font-weight:600}
.pagination-btn:disabled{opacity:.35;cursor:not-allowed}
.page-size-select{font-size:.75rem;padding:.25rem .5rem;border-radius:.375rem;border:1px solid #e5e7eb;background:white;cursor:pointer}
.sortable{cursor:pointer;user-select:none}
.sortable:hover{color:#800000}
.sort-icon{font-size:.7rem;margin-left:.2rem;vertical-align:middle}
.material-symbols-outlined{font-family:'Material Symbols Outlined'!important;font-style:normal;font-weight:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';font-feature-settings:'liga';-webkit-font-smoothing:antialiased}

.status-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:600}
.status-badge::before{content:'';width:7px;height:7px;border-radius:50%;display:inline-block}
.status-pending{background:#fef3c7;color:#92400e}
.status-pending::before{background:#d97706}
.status-approved{background:#e0f2fe;color:#075985}
.status-approved::before{background:#0891b2}
.status-published{background:#dcfce7;color:#166534}
.status-published::before{background:#16a34a}
.status-unpublished{background:#fee2e2;color:#991b1b}
.status-unpublished::before{background:#dc2626}

.volume-value{font-family:monospace;font-weight:700;font-size:.85rem}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Published XBT Volumes</h1>
                <p class="text-gray-600 text-sm mt-1">View published cross-border trade volume data</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_csv=1&search_border=<?= urlencode($search_border) ?>&search_commodity=<?= urlencode($search_commodity) ?>&search_source=<?= urlencode($search_source) ?>&search_destination=<?= urlencode($search_destination) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export CSV
                </a>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['import_message'])): ?>
    <div class="mb-4 p-3 rounded-lg flex items-center gap-2 text-sm <?= $_SESSION['import_status'] == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
        <span class="material-symbols-outlined text-base"><?= $_SESSION['import_status'] == 'success' ? 'check_circle' : 'error' ?></span>
        <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['import_message']) ?></span>
    </div>
    <?php 
        unset($_SESSION['import_message']); 
        unset($_SESSION['import_status']);
    endif; 
    ?>

    <!-- Stat Cards - Only published -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Published Volumes</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_volumes) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">bar_chart</span>
            </div>
        </div>
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-600">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Published</p><p class="text-xl font-bold text-green-600"><?= number_format($published_count) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-green-500/50">public</span>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-lg shadow-sm mb-5 p-3">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">warehouse</span>
                    <select id="searchBorder" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Borders</option>
                        <?php foreach ($distinct_borders as $border): ?>
                            <option value="<?= htmlspecialchars($border) ?>" <?= $search_border == $border ? 'selected' : '' ?>><?= htmlspecialchars($border) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[150px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">eco</span>
                    <input type="text" id="searchCommodity" placeholder="Search commodity..."
                        class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        value="<?= htmlspecialchars($search_commodity) ?>">
                </div>
            </div>
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">flight_takeoff</span>
                    <select id="searchSource" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Sources</option>
                        <?php foreach ($distinct_sources as $source): ?>
                            <option value="<?= htmlspecialchars($source) ?>" <?= $search_source == $source ? 'selected' : '' ?>><?= htmlspecialchars($source) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">flight_land</span>
                    <select id="searchDestination" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Destinations</option>
                        <?php foreach ($distinct_destinations as $dest): ?>
                            <option value="<?= htmlspecialchars($dest) ?>" <?= $search_destination == $dest ? 'selected' : '' ?>><?= htmlspecialchars($dest) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button onclick="applyFilters()" class="px-3 py-1.5 bg-maroon text-white text-sm rounded-lg hover:bg-[#660000] transition-all inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-base">filter_list</span>Filter
            </button>
        </div>
    </div>

    <!-- Main Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="id">ID<?php if($sort_column=='id') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="border">Border Point<?php if($sort_column=='border') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="commodity">Commodity<?php if($sort_column=='commodity') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="volume">Volume (MT)<?php if($sort_column=='volume') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="source">Source<?php if($sort_column=='source') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="destination">Destination<?php if($sort_column=='destination') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_posted">Date<?php if($sort_column=='date_posted') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="status">Status<?php if($sort_column=='status') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="data_source">Data Source<?php if($sort_column=='data_source') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($xbt_volumes)): ?>
                    <tr>
                        <td colspan="9" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">swap_horiz</span>
                            <p class="text-sm mt-1">No published XBT volumes found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($xbt_volumes as $volume): ?>
                    <tr class="table-row-hover" data-id="<?= $volume['id'] ?>">
                        <td class="px-3 py-2 text-xs text-gray-500"><?= $volume['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($volume['border_name']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($volume['commodity_display']) ?></td>
                        <td class="px-3 py-2 text-xs font-mono font-semibold text-gray-700"><?= number_format($volume['volume'], 2) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($volume['source']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= htmlspecialchars($volume['destination']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= date('M d, Y', strtotime($volume['date_posted'])) ?></td>
                        <td class="px-3 py-2"><?= getStatusBadge($volume['status']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($volume['data_source']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="border-t border-gray-200 px-4 py-3 bg-white">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="text-xs text-gray-500">
                    <?php if ($filtered_records === 0): ?>
                        No volumes found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> published volumes
                        <?php if ($search_border || $search_commodity || $search_source || $search_destination): ?>
                            <span class="ml-1 text-maroon">(filtered)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500" for="rowsPerPage">Rows:</label>
                        <select id="rowsPerPage" class="page-size-select" onchange="changeRowsPerPage()">
                            <?php foreach ([10,20,50,100] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $limit==$opt?'selected':'' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center gap-1">
                        <button class="pagination-btn" onclick="goToPage(1)" <?= $page<=1?'disabled':'' ?>><span class="material-symbols-outlined text-sm">first_page</span></button>
                        <button class="pagination-btn" onclick="goToPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>><span class="material-symbols-outlined text-sm">chevron_left</span></button>

                        <?php
                        $win = 2;
                        $sp = max(1, $page - $win);
                        $ep = min($total_pages, $page + $win);
                        if ($sp === 1) $ep = min($total_pages, 1 + $win * 2);
                        if ($ep === $total_pages) $sp = max(1, $total_pages - $win * 2);
                        if ($sp > 1): ?>
                            <button class="pagination-btn" onclick="goToPage(1)">1</button>
                            <?php if ($sp > 2): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $sp; $i <= $ep; $i++): ?>
                            <button class="pagination-btn <?= $i===$page ? 'active-page' : '' ?>" <?= $i===$page ? '' : "onclick=\"goToPage($i)\"" ?>><?= $i ?></button>
                        <?php endfor; ?>

                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?><span class="text-gray-400 text-xs px-1">…</span><?php endif; ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                        <?php endif; ?>

                        <button class="pagination-btn" onclick="goToPage(<?= $page+1 ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="material-symbols-outlined text-sm">chevron_right</span></button>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $page>=$total_pages?'disabled':'' ?>><span class="material-symbols-outlined text-sm">last_page</span></button>
                    </nav>
                    <?php endif; ?>
                </div>

                <a href="../base/landing_page.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-all">
                    <span class="material-symbols-outlined text-base">arrow_back</span>Back
                </a>
            </div>
        </div>
    </div>

</div>
</div>

<script>
// PHP → JS state
const PHP = {
    page: <?= $page ?>,
    limit: <?= $limit ?>,
    totalPages: <?= $total_pages ?>,
    sort: <?= json_encode($sort_column) ?>,
    dir: <?= json_encode(strtolower($sort_direction)) ?>,
    searchBorder: <?= json_encode($search_border) ?>,
    searchCommodity: <?= json_encode($search_commodity) ?>,
    searchSource: <?= json_encode($search_source) ?>,
    searchDestination: <?= json_encode($search_destination) ?>,
};

function buildUrl(overrides) {
    const p = {
        page: PHP.page,
        limit: PHP.limit,
        sort: PHP.sort,
        dir: PHP.dir,
        search_border: document.getElementById('searchBorder').value,
        search_commodity: document.getElementById('searchCommodity').value.trim(),
        search_source: document.getElementById('searchSource').value,
        search_destination: document.getElementById('searchDestination').value,
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);
    
    const q = new URLSearchParams();
    q.set('page', p.page);
    q.set('limit', p.limit);
    if (p.sort) q.set('sort', p.sort);
    if (p.dir) q.set('dir', p.dir);
    if (p.search_border) q.set('search_border', p.search_border);
    if (p.search_commodity) q.set('search_commodity', p.search_commodity);
    if (p.search_source) q.set('search_source', p.search_source);
    if (p.search_destination) q.set('search_destination', p.search_destination);
    return '?' + q.toString();
}

function goToPage(pg) {
    pg = parseInt(pg, 10);
    if (isNaN(pg) || pg < 1 || pg > PHP.totalPages) return;
    window.location.href = buildUrl({ page: pg });
}

function changeRowsPerPage() { window.location.href = buildUrl({ page: 1 }); }
function applyFilters() { window.location.href = buildUrl({ page: 1 }); }

function sortTable(col) {
    const newDir = (PHP.sort === col && PHP.dir === 'asc') ? 'desc' : 'asc';
    window.location.href = buildUrl({ page: 1, sort: col, dir: newDir });
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Sortable headers
    document.querySelectorAll('.sortable').forEach(th =>
        th.addEventListener('click', () => sortTable(th.dataset.sort))
    );
    
    // Enter key on search inputs
    ['searchCommodity'].forEach(id => {
        document.getElementById(id)?.addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    });
});
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>