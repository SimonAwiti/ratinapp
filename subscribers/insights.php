<?php
// user_insights.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Check if config file exists
$config_path = '../admin/includes/config.php';
if (!file_exists($config_path)) {
    die("Configuration file not found. Please check the path: " . $config_path);
}
include $config_path;

// Check database connection
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 12;
$valid_limits = [12, 24, 48, 96];
if (!in_array($limit, $valid_limits)) $limit = 12;

// Get sort parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_direction = isset($_GET['dir']) && $_GET['dir'] == 'asc' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['id', 'title', 'created_at'];
if (!in_array($sort_column, $allowed_sort_columns)) $sort_column = 'created_at';

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$view_insight_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

// Count total insights with filters
$count_sql = "SELECT COUNT(*) as total FROM insights";
$params = [];
$types = "";

if (!empty($search)) {
    $count_sql .= " WHERE (title LIKE ? OR body LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param);
    $types .= "ss";
}
if (!empty($date_from)) {
    $count_sql .= (empty($search) ? " WHERE" : " AND") . " DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $count_sql .= (empty($search) && empty($date_from) ? " WHERE" : " AND") . " DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$count_stmt = $con->prepare($count_sql);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_items = 0;
}
$total_pages = $total_items > 0 ? ceil($total_items / $limit) : 1;

// Fetch insights with filters, sorting, and pagination
$offset = ($page - 1) * $limit;
$sql = "SELECT id, title, created_at, SUBSTRING(body, 1, 300) as excerpt, body FROM insights";

if (!empty($search)) {
    $sql .= " WHERE (title LIKE ? OR body LIKE ?)";
}
if (!empty($date_from)) {
    $sql .= (empty($search) ? " WHERE" : " AND") . " DATE(created_at) >= ?";
}
if (!empty($date_to)) {
    $sql .= (empty($search) && empty($date_from) ? " WHERE" : " AND") . " DATE(created_at) <= ?";
}

$sql .= " ORDER BY $sort_column $sort_direction LIMIT ? OFFSET ?";

$stmt = $con->prepare($sql);
$insights = [];
if ($stmt) {
    $bind_params = [];
    $bind_types = "";

    if (!empty($search)) {
        $search_param = "%$search%";
        array_push($bind_params, $search_param, $search_param);
        $bind_types .= "ss";
    }
    if (!empty($date_from)) {
        $bind_params[] = $date_from;
        $bind_types .= "s";
    }
    if (!empty($date_to)) {
        $bind_params[] = $date_to;
        $bind_types .= "s";
    }

    $bind_params[] = $limit;
    $bind_params[] = $offset;
    $bind_types .= "ii";

    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    $stmt->execute();
    $insights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch insight for view modal if requested
$view_insight = null;
if ($view_insight_id > 0) {
    $view_stmt = $con->prepare("SELECT id, title, body, created_at FROM insights WHERE id = ?");
    if ($view_stmt) {
        $view_stmt->bind_param("i", $view_insight_id);
        $view_stmt->execute();
        $view_insight = $view_stmt->get_result()->fetch_assoc();
        $view_stmt->close();
    }
}

// Now include the header
include 'user_header.php';
?>

<style>
.insight-card {
    transition: all 0.3s ease;
    border: 1px solid #e2e2e2;
    background: white;
    cursor: pointer;
}
.insight-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    border-color: #800000;
}
.category-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 500;
    background-color: #f3f4f6;
    color: #4b5563;
}
.filter-input:focus {
    border-color: #800000;
    outline: none;
    ring: 2px solid rgba(128,0,0,0.2);
}
.pagination-btn {
    min-width: 36px;
    height: 36px;
    transition: all 0.2s ease;
}
.pagination-btn:hover:not(:disabled):not(.active-page) {
    background-color: #fef3e7;
    border-color: #800000;
    color: #800000;
}
.pagination-btn.active-page {
    background-color: #800000;
    border-color: #800000;
    color: white;
}
.sortable {
    cursor: pointer;
    user-select: none;
}
.sortable:hover {
    color: #800000;
}
.sort-icon {
    font-size: 0.7rem;
    margin-left: 0.2rem;
    vertical-align: middle;
}
.modal-content {
    max-height: 70vh;
    overflow-y: auto;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.loading-spinner {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #800000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.header-accent-gradient {
    background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
}
.auth-bg-gradient {
    background: radial-gradient(circle at top left, rgba(0, 69, 13, 0.03), transparent),
                radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.03), transparent);
}
.modal-gradient-header {
    background: linear-gradient(135deg, #800000 0%, #00450d 100%);
}
.prose {
    color: #1a1c1c;
    line-height: 1.6;
}
.prose h1, .prose h2, .prose h3 {
    color: #00450d;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}
.prose p {
    margin-bottom: 1em;
}
.prose img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
}
.prose ul, .prose ol {
    margin-left: 1.5rem;
    margin-bottom: 1rem;
}
</style>

<div class="auth-bg-gradient">
    <div class="max-w-7xl mx-auto py-6 px-container-padding">
        
        <!-- Header -->
        <div class="mb-8">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-maroon">Market Insights</h1>
                <p class="font-body-md text-body-md text-on-surface-variant mt-1">Expert analysis, market commentary, and predictive insights for agricultural professionals</p>
            </div>
            <div class="h-0.5 w-full header-accent-gradient mt-4 rounded-full"></div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-surface-container-lowest rounded-xl shadow-sm mb-6 p-4 border border-outline-variant">
            <form method="GET" action="" id="filterForm">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Search -->
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">search</span>
                        <input type="text" name="search" id="searchInput" placeholder="Search insights..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="filter-input w-full pl-9 pr-3 py-2 text-sm bg-surface border border-outline-variant rounded-lg focus:border-secondary font-body-md">
                    </div>
                    
                    <!-- Date From -->
                    <div>
                        <input type="date" name="date_from" id="dateFrom" value="<?= htmlspecialchars($date_from) ?>"
                               placeholder="From Date"
                               class="filter-input w-full px-3 py-2 text-sm bg-surface border border-outline-variant rounded-lg focus:border-secondary font-body-md">
                    </div>
                    
                    <!-- Date To -->
                    <div>
                        <input type="date" name="date_to" id="dateTo" value="<?= htmlspecialchars($date_to) ?>"
                               placeholder="To Date"
                               class="filter-input w-full px-3 py-2 text-sm bg-surface border border-outline-variant rounded-lg focus:border-secondary font-body-md">
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-3 py-2 bg-secondary text-white text-sm rounded-lg hover:bg-[#8a2201] transition-all font-label-md">
                            <span class="material-symbols-outlined text-base align-middle">filter_list</span>
                            Filter
                        </button>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1, 'search' => '', 'date_from' => '', 'date_to' => '', 'view' => ''])) ?>" 
                           class="px-3 py-2 border border-outline-variant rounded-lg text-on-surface-variant hover:text-secondary transition-all text-sm font-body-md">
                            Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sort Bar -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
            <div class="flex items-center gap-2 text-sm">
                <span class="text-on-surface-variant font-body-md">Sort by:</span>
                <div class="flex gap-1">
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'created_at', 'dir' => $sort_column == 'created_at' && $sort_direction == 'DESC' ? 'asc' : 'desc', 'page' => 1, 'view' => ''])) ?>" 
                       class="sortable px-2 py-1 rounded font-body-md <?= $sort_column == 'created_at' ? 'text-secondary font-semibold' : 'text-on-surface-variant' ?>">
                        Date
                        <?php if ($sort_column == 'created_at'): ?>
                            <span class="sort-icon"><?= $sort_direction == 'DESC' ? '↓' : '↑' ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'title', 'dir' => $sort_column == 'title' && $sort_direction == 'ASC' ? 'desc' : 'asc', 'page' => 1, 'view' => ''])) ?>" 
                       class="sortable px-2 py-1 rounded font-body-md <?= $sort_column == 'title' ? 'text-secondary font-semibold' : 'text-on-surface-variant' ?>">
                        Title
                        <?php if ($sort_column == 'title'): ?>
                            <span class="sort-icon"><?= $sort_direction == 'ASC' ? '↑' : '↓' ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="text-xs text-on-surface-variant font-body-md">Show:</span>
                <select id="itemsPerPageSelect" class="px-2 py-1 text-sm border border-outline-variant rounded-lg bg-surface font-body-md">
                    <option value="12" <?= $limit == 12 ? 'selected' : '' ?>>12</option>
                    <option value="24" <?= $limit == 24 ? 'selected' : '' ?>>24</option>
                    <option value="48" <?= $limit == 48 ? 'selected' : '' ?>>48</option>
                    <option value="96" <?= $limit == 96 ? 'selected' : '' ?>>96</option>
                </select>
                <span class="text-xs text-on-surface-variant font-body-md">per page</span>
            </div>
        </div>

        <!-- Insights Grid -->
        <?php if (empty($insights)): ?>
            <div class="bg-surface-container-lowest rounded-xl p-12 text-center border border-outline-variant">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant/30">lightbulb</span>
                <p class="text-on-surface-variant mt-3 font-body-md">No insights found matching your criteria.</p>
                <p class="text-sm text-on-surface-variant/60 mt-1 font-body-md">Try adjusting your filters or search term.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($insights as $insight): ?>
                    <div class="insight-card rounded-xl overflow-hidden bg-surface-container-lowest" onclick="viewInsight(<?= $insight['id'] ?>)">
                        <!-- Icon Header -->
                        <div class="h-24 bg-gradient-to-r from-primary-container/30 to-secondary-container/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-5xl text-secondary">lightbulb</span>
                        </div>
                        
                        <!-- Content -->
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-3">
                                <span class="category-badge font-label-md">Insight</span>
                                <span class="text-xs text-on-surface-variant/60 font-body-md"><?= date('M j, Y', strtotime($insight['created_at'])) ?></span>
                            </div>
                            <h3 class="font-headline-md text-lg text-on-surface mb-3 line-clamp-2"><?= htmlspecialchars($insight['title']) ?></h3>
                            <p class="text-sm text-on-surface-variant line-clamp-4 font-body-md"><?= strip_tags(htmlspecialchars_decode($insight['excerpt'])) ?>...</p>
                            <div class="mt-4 flex items-center text-secondary text-xs font-medium">
                                <span class="font-label-md">Read full insight</span>
                                <span class="material-symbols-outlined text-sm ml-1">arrow_forward</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex flex-wrap justify-between items-center gap-4">
                <div class="text-sm text-on-surface-variant font-body-md">
                    Showing <?= ($offset + 1) ?> to <?= min($offset + $limit, $total_items) ?> of <?= number_format($total_items) ?> insights
                </div>
                <div class="flex flex-wrap gap-1">
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1, 'view' => ''])) ?>" 
                       class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center font-body-md <?= $page == 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-secondary/10' ?>">
                        <span class="material-symbols-outlined text-sm">first_page</span>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'view' => ''])) ?>" 
                       class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center font-body-md <?= $page == 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-secondary/10' ?>">
                        <span class="material-symbols-outlined text-sm">chevron_left</span>
                    </a>
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1, 'view' => ''])) . '" class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center hover:bg-secondary/10 font-body-md">1</a>';
                        if ($start_page > 2) echo '<span class="w-9 h-9 flex items-center justify-center text-on-surface-variant font-body-md">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i, 'view' => ''])) ?>" 
                           class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center font-body-md <?= $i == $page ? 'active-page' : 'hover:bg-secondary/10' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor;
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span class="w-9 h-9 flex items-center justify-center text-on-surface-variant font-body-md">...</span>';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages, 'view' => ''])) . '" class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center hover:bg-secondary/10 font-body-md">' . $total_pages . '</a>';
                    }
                    ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1), 'view' => ''])) ?>" 
                       class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center font-body-md <?= $page == $total_pages ? 'opacity-40 pointer-events-none' : 'hover:bg-secondary/10' ?>">
                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages, 'view' => ''])) ?>" 
                       class="pagination-btn w-9 h-9 rounded border border-outline-variant flex items-center justify-center font-body-md <?= $page == $total_pages ? 'opacity-40 pointer-events-none' : 'hover:bg-secondary/10' ?>">
                        <span class="material-symbols-outlined text-sm">last_page</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Insight View Modal -->
<div id="insightViewModal" class="fixed inset-0 bg-black/60 hidden z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-surface-container-lowest rounded-xl w-full max-w-4xl shadow-xl border border-outline-variant">
            <div class="modal-gradient-header px-6 py-4 flex justify-between items-center sticky top-0 rounded-t-xl">
                <h3 id="modalTitle" class="text-lg font-semibold text-white font-headline-md">Market Insight</h3>
                <button onclick="closeInsightModal()" class="text-white/80 hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="modalBody" class="p-6 modal-content">
                <?php if ($view_insight): ?>
                    <div class="max-w-none">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="category-badge font-label-md">Insight</span>
                            <span class="flex items-center gap-1 font-body-md text-sm text-on-surface-variant">
                                <span class="material-symbols-outlined text-sm">calendar_today</span>
                                <?= date('F j, Y', strtotime($view_insight['created_at'])) ?>
                            </span>
                        </div>
                        <h2 class="font-headline-lg text-2xl text-on-surface mb-6"><?= htmlspecialchars($view_insight['title']) ?></h2>
                        <div class="prose max-w-none font-body-md">
                            <?= $view_insight['body'] ?>
                        </div>
                        <div class="mt-6 pt-4 border-t border-outline-variant flex justify-between items-center">
                            <button onclick="closeInsightModal()" class="px-4 py-2 bg-secondary text-white rounded-lg hover:bg-[#8a2201] transition-all font-label-md">
                                Close
                            </button>
                            <div class="flex gap-2">
                                <button onclick="shareInsight(<?= $view_insight['id'] ?>, '<?= htmlspecialchars($view_insight['title']) ?>')" class="p-2 text-on-surface-variant hover:text-secondary transition-colors" title="Share">
                                    <span class="material-symbols-outlined">share</span>
                                </button>
                                <button onclick="window.print()" class="p-2 text-on-surface-variant hover:text-secondary transition-colors" title="Print">
                                    <span class="material-symbols-outlined">print</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-5xl text-on-surface-variant/30">lightbulb</span>
                        <p class="text-on-surface-variant mt-3 font-body-md">Select an insight to read</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Change items per page
var itemsPerPageSelect = document.getElementById('itemsPerPageSelect');
if (itemsPerPageSelect) {
    itemsPerPageSelect.addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('limit', this.value);
        url.searchParams.set('page', 1);
        url.searchParams.delete('view');
        window.location.href = url.toString();
    });
}

// Auto-submit filters
var searchTimeout;
var searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            var url = new URL(window.location.href);
            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('page', 1);
            url.searchParams.delete('view');
            window.location.href = url.toString();
        }, 500);
    });
}

var dateFrom = document.getElementById('dateFrom');
if (dateFrom) {
    dateFrom.addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('date_from', this.value);
        url.searchParams.set('page', 1);
        url.searchParams.delete('view');
        window.location.href = url.toString();
    });
}

var dateTo = document.getElementById('dateTo');
if (dateTo) {
    dateTo.addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('date_to', this.value);
        url.searchParams.set('page', 1);
        url.searchParams.delete('view');
        window.location.href = url.toString();
    });
}

// View insight function
function viewInsight(insightId) {
    var url = new URL(window.location.href);
    url.searchParams.set('view', insightId);
    window.location.href = url.toString();
}

function closeInsightModal() {
    var url = new URL(window.location.href);
    url.searchParams.delete('view');
    window.location.href = url.toString();
}

function shareInsight(id, title) {
    var url = window.location.origin + window.location.pathname + '?view=' + id;
    if (navigator.share) {
        navigator.share({
            title: title,
            text: 'Check out this market insight on RATIN Analytics',
            url: url
        }).catch(function(err) { console.log('Error sharing:', err); });
    } else {
        navigator.clipboard.writeText(url).then(function() {
            alert('Link copied to clipboard!');
        });
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeInsightModal();
    }
});

// Auto-open modal if view parameter is present
<?php if ($view_insight_id > 0 && $view_insight): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('insightViewModal').classList.remove('hidden');
});
<?php endif; ?>
</script>
