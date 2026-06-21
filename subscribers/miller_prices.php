<?php
// miller_prices.php
session_start();

// ============================================================
// EXPORT CSV — must run BEFORE admin_header.php is included
// ============================================================
if (isset($_GET['export_csv'])) {
    if (file_exists('includes/config.php')) include 'includes/config.php';
    elseif (file_exists('../admin/includes/config.php')) include '../admin/includes/config.php';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="miller_prices_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $search_country = $_GET['search_country'] ?? '';
    $search_town = $_GET['search_town'] ?? '';
    $search_commodity = $_GET['search_commodity'] ?? '';

    $where_export = "WHERE mp.status = 'published'";
    if (!empty($search_country)) {
        $where_export .= " AND mp.country LIKE '%" . $con->real_escape_string($search_country) . "%'";
    }
    if (!empty($search_town)) {
        $where_export .= " AND mp.town LIKE '%" . $con->real_escape_string($search_town) . "%'";
    }
    if (!empty($search_commodity)) {
        $where_export .= " AND (c.commodity_name LIKE '%" . $con->real_escape_string($search_commodity) . "%' OR c.variety LIKE '%" . $con->real_escape_string($search_commodity) . "%')";
    }

    $exp_query = "SELECT 
        mp.id, mp.country, mp.town, 
        CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
        mp.price, mp.price_usd, mp.day_change, mp.month_change,
        DATE(mp.date_posted) as price_date, mp.status, ds.data_source_name as data_source
        FROM miller_prices mp
        LEFT JOIN commodities c ON mp.commodity_id = c.id
        LEFT JOIN data_sources ds ON mp.data_source_id = ds.id
        $where_export
        ORDER BY mp.date_posted DESC";
    
    $exp_result = $con->query($exp_query);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Country', 'Town', 'Commodity', 'Price (Local)', 'Price (USD)', 'Day Change %', 'Month Change %', 'Date', 'Status', 'Data Source']);

    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['country'], $row['town'], $row['commodity_display'],
            number_format($row['price'], 2, '.', ''), number_format($row['price_usd'], 2, '.', ''),
            $row['day_change'] !== null ? $row['day_change'] . '%' : 'N/A',
            $row['month_change'] !== null ? $row['month_change'] . '%' : 'N/A',
            $row['price_date'], $row['status'], $row['data_source']
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
$total_prices = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices WHERE status = 'published'")->fetch_assoc()['t'] ?? 0);
$published_count = (int)($con->query("SELECT COUNT(*) as t FROM miller_prices WHERE status = 'published'")->fetch_assoc()['t'] ?? 0);

// Get distinct towns for filter (only published)
$towns_result = $con->query("SELECT DISTINCT town FROM miller_prices WHERE status = 'published' ORDER BY town");
$distinct_towns = [];
while ($row = $towns_result->fetch_assoc()) {
    $distinct_towns[] = $row['town'];
}

// Get distinct countries for filter (only published)
$countries_result = $con->query("SELECT DISTINCT country FROM miller_prices WHERE status = 'published' ORDER BY country");
$distinct_countries = [];
while ($row = $countries_result->fetch_assoc()) {
    $distinct_countries[] = $row['country'];
}

// ============================================================
// PAGINATION + SORTING + FILTERING - Only published
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;

$sort_column = $_GET['sort'] ?? 'date_posted';
$sort_direction = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';
$allowed_sorts = ['id', 'country', 'town', 'commodity', 'price_usd', 'day_change', 'month_change', 'date_posted', 'status', 'data_source'];
if (!in_array($sort_column, $allowed_sorts)) $sort_column = 'date_posted';

$search_country = trim($_GET['search_country'] ?? '');
$search_town = trim($_GET['search_town'] ?? '');
$search_commodity = trim($_GET['search_commodity'] ?? '');

$where = "WHERE mp.status = 'published'";
$params = [];
$types = "";

if ($search_country !== '') {
    $where .= " AND mp.country LIKE ?";
    $params[] = '%' . $search_country . '%';
    $types .= "s";
}
if ($search_town !== '') {
    $where .= " AND mp.town LIKE ?";
    $params[] = '%' . $search_town . '%';
    $types .= "s";
}
if ($search_commodity !== '') {
    $where .= " AND (c.commodity_name LIKE ? OR c.variety LIKE ?)";
    $params[] = '%' . $search_commodity . '%';
    $params[] = '%' . $search_commodity . '%';
    $types .= "ss";
}

// Count total records
$count_stmt = $con->prepare("SELECT COUNT(*) as total FROM miller_prices mp LEFT JOIN commodities c ON mp.commodity_id = c.id LEFT JOIN data_sources ds ON mp.data_source_id = ds.id $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$filtered_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($filtered_records / $limit));
$page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($page - 1) * $limit;

// Map sort column to database column
$sort_map = [
    'id' => 'mp.id',
    'country' => 'mp.country',
    'town' => 'mp.town',
    'commodity' => 'c.commodity_name',
    'price_usd' => 'mp.price_usd',
    'day_change' => 'mp.day_change',
    'month_change' => 'mp.month_change',
    'date_posted' => 'mp.date_posted',
    'status' => 'mp.status',
    'data_source' => 'ds.data_source_name'
];
$order_by = $sort_map[$sort_column] ?? 'mp.date_posted';
$dir = $sort_direction === 'ASC' ? 'ASC' : 'DESC';

// Fetch data
$data_params = array_merge($params, [$limit, $offset]);
$data_types = $types . "ii";

$query = "SELECT 
    mp.id, mp.country, mp.town, mp.commodity_id, mp.commodity_name,
    mp.price, mp.price_usd, mp.day_change, mp.month_change,
    mp.date_posted, mp.status, ds.data_source_name as data_source,
    CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display
    FROM miller_prices mp
    LEFT JOIN commodities c ON mp.commodity_id = c.id
    LEFT JOIN data_sources ds ON mp.data_source_id = ds.id
    $where 
    ORDER BY $order_by $dir
    LIMIT ? OFFSET ?";

$data_stmt = $con->prepare($query);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$miller_prices = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

function getChangeClass($change) {
    if ($change === null) return 'flat';
    return $change >= 0 ? 'up' : 'down';
}

function getChangeIcon($change) {
    if ($change === null) return '–';
    return $change >= 0 ? '▲' : '▼';
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

.change-indicator{display:inline-flex;align-items:center;gap:2px;font-size:.7rem;font-weight:600;padding:2px 6px;border-radius:4px}
.change-up{background:#dcfce7;color:#16a34a}
.change-down{background:#fee2e2;color:#dc2626}
.change-flat{background:#f3f4f6;color:#6b7280}

.price-value{font-family:monospace;font-weight:700;font-size:.85rem}
</style>

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
<div class="max-w-7xl mx-auto">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-maroon">Published Miller Prices</h1>
                <p class="text-gray-600 text-sm mt-1">View published miller price data across towns and commodities</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="?export_csv=1&search_country=<?= urlencode($search_country) ?>&search_town=<?= urlencode($search_town) ?>&search_commodity=<?= urlencode($search_commodity) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">download</span>Export CSV
                </a>
            </div>
        </div>
        <div class="h-0.5 w-full header-accent-gradient mt-3 rounded-full"></div>
    </div>

    <!-- Stat Cards - Only published -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
        <div class="stat-card bg-white rounded-lg p-3 shadow-sm border-l-4 border-maroon">
            <div class="flex items-center justify-between">
                <div><p class="text-xs text-gray-400 uppercase tracking-wide">Total Published Prices</p><p class="text-xl font-bold text-gray-800"><?= number_format($total_prices) ?></p></div>
                <span class="material-symbols-outlined text-3xl text-maroon/40">attach_money</span>
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
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">public</span>
                    <select id="searchCountry" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Countries</option>
                        <?php foreach ($distinct_countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $search_country == $country ? 'selected' : '' ?>><?= htmlspecialchars($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex-1 min-w-[130px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">location_city</span>
                    <select id="searchTown" class="search-input w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20">
                        <option value="">All Towns</option>
                        <?php foreach ($distinct_towns as $town): ?>
                            <option value="<?= htmlspecialchars($town) ?>" <?= $search_town == $town ? 'selected' : '' ?>><?= htmlspecialchars($town) ?></option>
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
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="country">Country<?php if($sort_column=='country') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="town">Town<?php if($sort_column=='town') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="commodity">Commodity<?php if($sort_column=='commodity') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="price_usd">Price (USD)<?php if($sort_column=='price_usd') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Day Δ</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Month Δ</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="date_posted">Date<?php if($sort_column=='date_posted') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="status">Status<?php if($sort_column=='status') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase sortable" data-sort="data_source">Data Source<?php if($sort_column=='data_source') echo '<span class="sort-icon">'.($sort_direction=='ASC'?'↑':'↓').'</span>'; ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($miller_prices)): ?>
                    <tr>
                        <td colspan="10" class="px-3 py-8 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl text-gray-300 block">agriculture</span>
                            <p class="text-sm mt-1">No published miller prices found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($miller_prices as $price): 
                        $day_change = $price['day_change'] !== null ? round($price['day_change'], 2) . '%' : 'N/A';
                        $month_change = $price['month_change'] !== null ? round($price['month_change'], 2) . '%' : 'N/A';
                        $day_class = getChangeClass($price['day_change']);
                        $month_class = getChangeClass($price['month_change']);
                        $day_icon = getChangeIcon($price['day_change']);
                        $month_icon = getChangeIcon($price['month_change']);
                    ?>
                    <tr class="table-row-hover" data-id="<?= $price['id'] ?>">
                        <td class="px-3 py-2 text-xs text-gray-500"><?= $price['id'] ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($price['country']) ?></td>
                        <td class="px-3 py-2 text-xs font-medium text-gray-800"><?= htmlspecialchars($price['town']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($price['commodity_display']) ?></td>
                        <td class="px-3 py-2 text-xs font-mono font-semibold text-gray-700">$<?= number_format($price['price_usd'], 2) ?></td>
                        <td class="px-3 py-2"><span class="change-indicator change-<?= $day_class ?>"><?= $day_icon ?> <?= $day_change ?></span></td>
                        <td class="px-3 py-2"><span class="change-indicator change-<?= $month_class ?>"><?= $month_icon ?> <?= $month_change ?></span></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?= date('M d, Y', strtotime($price['date_posted'])) ?></td>
                        <td class="px-3 py-2"><?= getStatusBadge($price['status']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($price['data_source']) ?></td>
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
                        No prices found
                    <?php else: ?>
                        Showing <strong><?= $showing_from ?></strong> – <strong><?= $showing_to ?></strong>
                        of <strong><?= number_format($filtered_records) ?></strong> published prices
                        <?php if ($search_country || $search_town || $search_commodity): ?>
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
    searchCountry: <?= json_encode($search_country) ?>,
    searchTown: <?= json_encode($search_town) ?>,
    searchCommodity: <?= json_encode($search_commodity) ?>,
};

function buildUrl(overrides) {
    const p = {
        page: PHP.page,
        limit: PHP.limit,
        sort: PHP.sort,
        dir: PHP.dir,
        search_country: document.getElementById('searchCountry').value,
        search_town: document.getElementById('searchTown').value,
        search_commodity: document.getElementById('searchCommodity').value.trim(),
    };
    p.limit = document.getElementById('rowsPerPage').value;
    Object.assign(p, overrides);
    
    const q = new URLSearchParams();
    q.set('page', p.page);
    q.set('limit', p.limit);
    if (p.sort) q.set('sort', p.sort);
    if (p.dir) q.set('dir', p.dir);
    if (p.search_country) q.set('search_country', p.search_country);
    if (p.search_town) q.set('search_town', p.search_town);
    if (p.search_commodity) q.set('search_commodity', p.search_commodity);
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
