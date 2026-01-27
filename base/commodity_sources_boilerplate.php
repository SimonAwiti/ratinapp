<?php
session_start();

// Initialize selected commodity sources in session if not exists
if (!isset($_SESSION['selected_commodity_sources'])) {
    $_SESSION['selected_commodity_sources'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_commodity_sources'])) {
            $_SESSION['selected_commodity_sources'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_commodity_sources']);
        if ($key !== false) {
            unset($_SESSION['selected_commodity_sources'][$key]);
            $_SESSION['selected_commodity_sources'] = array_values($_SESSION['selected_commodity_sources']); // Re-index
        }
    }
    
    // Clear all selections
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_commodity_sources'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_commodity_sources'])]);
    exit;
}

// Clear all selections if requested via GET
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_commodity_sources'] = [];
}

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Handle delete action
if (isset($_POST['delete_selected']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    
    // Convert all IDs to integers for safety
    $ids = array_map('intval', $selected_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $delete_sql = "DELETE FROM commodity_sources WHERE id IN ($placeholders)";
    $stmt = $con->prepare($delete_sql);
    
    if ($stmt) {
        // Build the types string (all integers)
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        
        if ($stmt->execute()) {
            $delete_message = "Successfully deleted " . $stmt->affected_rows . " source(s).";
            $delete_status = 'success';
            
            // Clear selections after deletion
            $_SESSION['selected_commodity_sources'] = [];
            
            // Refresh the page to show updated data
            echo '<script>setTimeout(function() { window.location.href = window.location.pathname + "?' . http_build_query($_GET) . '"; }, 1000);</script>';
        } else {
            $delete_message = "Error deleting sources: " . $stmt->error;
            $delete_status = 'danger';
        }
        $stmt->close();
    } else {
        $delete_message = "Error preparing delete statement: " . $con->error;
        $delete_status = 'danger';
    }
}

// Handle filters
$filters = [
    'id' => isset($_GET['filter_id']) ? trim($_GET['filter_id']) : '',
    'admin0' => isset($_GET['filter_admin0']) ? trim($_GET['filter_admin0']) : '',
    'admin1' => isset($_GET['filter_admin1']) ? trim($_GET['filter_admin1']) : '',
    'created_at' => isset($_GET['filter_created_at']) ? trim($_GET['filter_created_at']) : ''
];

// Build query with filters and sorting
$query = "
    SELECT
        id,
        admin0_country,
        admin1_county_district,
        created_at
    FROM
        commodity_sources
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($filters['id'])) {
    $query .= " AND id LIKE ?";
    $params[] = '%' . $filters['id'] . '%';
    $types .= 's';
}

if (!empty($filters['admin0'])) {
    $query .= " AND admin0_country LIKE ?";
    $params[] = '%' . $filters['admin0'] . '%';
    $types .= 's';
}

if (!empty($filters['admin1'])) {
    $query .= " AND admin1_county_district LIKE ?";
    $params[] = '%' . $filters['admin1'] . '%';
    $types .= 's';
}

if (!empty($filters['created_at'])) {
    $query .= " AND DATE(created_at) LIKE ?";
    $params[] = '%' . $filters['created_at'] . '%';
    $types .= 's';
}

// Apply sorting
$sortable_columns = ['id', 'admin0_country', 'admin1_county_district', 'created_at'];
$default_sort_column = 'admin0_country';
$default_sort_order = 'ASC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

// Map column names for database
$db_column_map = [
    'id' => 'id',
    'admin0_country' => 'admin0_country',
    'admin1_county_district' => 'admin1_county_district',
    'created_at' => 'created_at'
];

$db_sort_column = isset($db_column_map[$sort_column]) ? $db_column_map[$sort_column] : $db_column_map[$default_sort_column];
$query .= " ORDER BY $db_sort_column $sort_order";

// Get all data with filters and sorting
$stmt = $con->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $commodity_sources = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $commodity_sources = [];
}

// Pagination Logic (AFTER filtering and sorting)
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($commodity_sources);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$commodity_sources_paged = array_slice($commodity_sources, $startIndex, $itemsPerPage);

// --- Fetch counts for summary boxes ---
$total_sources_query = "SELECT COUNT(*) AS total FROM commodity_sources";
$total_sources_result = $con->query($total_sources_query);
$total_sources_row = $total_sources_result->fetch_assoc();
$total_sources = $total_sources_row['total'];

$distinct_countries_query = "SELECT COUNT(DISTINCT admin0_country) AS total FROM commodity_sources";
$distinct_countries_result = $con->query($distinct_countries_query);
$distinct_countries_row = $distinct_countries_result->fetch_assoc();
$distinct_countries_count = $distinct_countries_row['total'];

$distinct_counties_query = "SELECT COUNT(DISTINCT admin1_county_district) AS total FROM commodity_sources";
$distinct_counties_result = $con->query($distinct_counties_query);
$distinct_counties_row = $distinct_counties_result->fetch_assoc();
$distinct_counties_count = $distinct_counties_row['total'];

$kenya_sources_query = "SELECT COUNT(*) AS total FROM commodity_sources WHERE admin0_country = 'Kenya'";
$kenya_sources_result = $con->query($kenya_sources_query);
$kenya_sources_row = $kenya_sources_result->fetch_assoc();
$kenya_sources_count = $kenya_sources_row['total'];
?>

<style>
    .table-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .filter-row {
        background-color: white;
    }
    .btn-group {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn-add-new {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        padding: 10px 20px;
        font-size: 16px;
        border: none;
    }
    .btn-add-new:hover {
        background-color: darkred;
    }
    .btn-delete, .btn-export, .btn-bulk-export, .btn-clear-selections {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover, .btn-bulk-export:hover, .btn-clear-selections:hover {
        background-color: #f8f9fa;
    }
    .btn-clear-selections {
        background-color: #ffc107;
        color: black;
    }
    .btn-clear-selections:hover {
        background-color: #e0a800;
    }
    .btn-bulk-export {
        background-color: #17a2b8;
        color: white;
    }
    .btn-bulk-export:hover {
        background-color: #138496;
    }
    .dropdown-menu {
        min-width: 120px;
    }
    .dropdown-item {
        cursor: pointer;
    }
    .filter-input {
        width: 100%;
        border: none;
        background: white;
        padding: 5px;
        border-radius: 5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .filter-input:focus {
        outline: none;
        background: white;
    }
    .stats-container {
        display: flex;
        gap: 15px;
        justify-content: space-between;
        align-items: center;
        flex-wrap: nowrap;
        width: 87%;
        max-width: 100%;
        margin: 0 auto 20px auto;
        margin-left: 0.7%;
    }
    .stats-container > div {
        flex: 1;
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        text-align: center;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .stats-icon {
        width: 40px;
        height: 40px;
        margin-bottom: 10px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    .total-sources-icon {
        background-color: #3498db;
        color: white;
    }
    .distinct-countries-icon {
        background-color: #8e44ad;
        color: white;
    }
    .distinct-counties-icon {
        background-color: #1abc9c;
        color: white;
    }
    .kenya-sources-icon {
        background-color: #e67e22;
        color: white;
    }
    .stats-section {
        text-align: left;
        margin-left: 11%;
    }
    .stats-title {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin: 8px 0 5px 0;
    }
    .stats-number {
        font-size: 24px;
        font-weight: 700;
        color: #34495e;
    }
    .modal-content {
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
        background-color: #2c3e50;
        color: white;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }
    .modal-header .btn-close {
        color: white;
        filter: invert(1);
    }
    .form-control {
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 8px;
    }
    .form-control:focus {
        outline: none;
        border-color: rgba(180, 80, 50, 1);
        box-shadow: 0 0 5px rgba(180, 80, 50, 0.5);
    }
    .btn-primary {
        background-color: rgba(180, 80, 50, 1);
        border: none;
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 5px;
        color: white;
        cursor: pointer;
    }
    .btn-primary:hover {
        background-color: darkred;
    }
    .alert {
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid transparent;
    }
    .alert-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
    /* Sorting styles */
    .sortable {
        cursor: pointer;
        position: relative;
        user-select: none;
    }
    .sortable:hover {
        background-color: #f0f0f0;
    }
    .sort-icon {
        display: inline-block;
        margin-left: 5px;
        font-size: 0.8em;
        opacity: 0.7;
    }
    .sort-asc .sort-icon::after {
        content: "↑";
    }
    .sort-desc .sort-icon::after {
        content: "↓";
    }
    .sortable.sort-asc,
    .sortable.sort-desc {
        background-color: #e9ecef;
        font-weight: bold;
    }
    .date-added {
        font-size: 0.85em;
        color: #6c757d;
    }
    .selected-count {
        display: inline-block;
        background-color: rgba(180, 80, 50, 0.1);
        color: rgba(180, 80, 50, 1);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        margin-left: 5px;
        font-weight: bold;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Commodity Sources Management</h3></div>
    <p class="p">Manage geographical origins of commodities</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-sources-icon">
                <i class="fas fa-globe-americas"></i>
            </div>
            <div class="stats-title">Total Sources</div>
            <div class="stats-number"><?php echo $total_sources; ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon distinct-countries-icon">
                <i class="fas fa-flag"></i>
            </div>
            <div class="stats-title">Distinct Countries</div>
            <div class="stats-number"><?php echo $distinct_countries_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon distinct-counties-icon">
                <i class="fas fa-city"></i>
            </div>
            <div class="stats-title">Distinct Counties/Districts</div>
            <div class="stats-number"><?php echo $distinct_counties_count; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon kenya-sources-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stats-title">Sources from Kenya</div>
            <div class="stats-number"><?php echo $kenya_sources_count; ?></div>
        </div>
    </div>
</div>

<?php if (isset($delete_message)): ?>
    <div class="alert alert-<?php echo $delete_status; ?>" style="margin: 20px; width: auto;">
        <?php echo $delete_message; ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="add_commodity_sources.php" class="btn btn-add-new">
                <i class="fas fa-plus" style="margin-right: 5px;"></i>
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()">
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
                <?php if (count($_SESSION['selected_commodity_sources']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_commodity_sources']); ?></span>
                <?php endif; ?>
            </button>

            <button class="btn btn-clear-selections" onclick="clearAllSelections()">
                <i class="fas fa-times-circle" style="margin-right: 3px;"></i>
                Clear Selections
            </button>

            <form method="POST" action="export_current_page_sources.php" style="display: inline;">
                <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
                <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
                <input type="hidden" name="filters" value="<?php echo htmlspecialchars(json_encode($filters)); ?>">
                <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
                <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
                <button type="submit" class="btn-export">
                    <i class="fas fa-download" style="margin-right: 3px;"></i> Export (Current Page)
                </button>
            </form>

            <form method="POST" action="bulk_export_sources.php" style="display: inline;">
                <input type="hidden" name="filters" value="<?php echo htmlspecialchars(json_encode($filters)); ?>">
                <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
                <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
                <button type="submit" class="btn-bulk-export">
                    <i class="fas fa-database" style="margin-right: 3px;"></i> Bulk Export (All)
                </button>
            </form>
        </div>

        <table class="table table-striped table-hover">
            <thead>
                <tr style="background-color: #d3d3d3 !important; color: black !important;">
                    <th><input type="checkbox" id="selectAll"></th>
                    <th class="sortable <?php echo getSortClass('id'); ?>" onclick="sortTable('id')">
                        ID
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('admin0_country'); ?>" onclick="sortTable('admin0_country')">
                        Admin-0 (Country)
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('admin1_county_district'); ?>" onclick="sortTable('admin1_county_district')">
                        Admin-1 (County/District)
                        <span class="sort-icon"></span>
                    </th>
                    <th class="sortable <?php echo getSortClass('created_at'); ?>" onclick="sortTable('created_at')">
                        Created On
                        <span class="sort-icon"></span>
                    </th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th>
                        <input type="text" 
                               class="filter-input" 
                               id="filterId" 
                               placeholder="Filter ID"
                               value="<?php echo htmlspecialchars($filters['id']); ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" 
                               class="filter-input" 
                               id="filterAdmin0" 
                               placeholder="Filter Country"
                               value="<?php echo htmlspecialchars($filters['admin0']); ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" 
                               class="filter-input" 
                               id="filterAdmin1" 
                               placeholder="Filter County/District"
                               value="<?php echo htmlspecialchars($filters['admin1']); ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <input type="text" 
                               class="filter-input" 
                               id="filterCreatedAt" 
                               placeholder="YYYY-MM-DD"
                               value="<?php echo htmlspecialchars($filters['created_at']); ?>"
                               onkeyup="applyFilters()">
                    </th>
                    <th>
                        <?php if (!empty($filters['id']) || !empty($filters['admin0']) || !empty($filters['admin1']) || !empty($filters['created_at'])): ?>
                            <a href="?" class="btn btn-sm" style="background-color: #6c757d; color: white; text-decoration: none;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </th>
                </tr>
            </thead>
            <tbody id="commoditySourceTable">
                <?php if (empty($commodity_sources_paged)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                            No commodity sources found<?php echo (!empty($filters['id']) || !empty($filters['admin0']) || !empty($filters['admin1']) || !empty($filters['created_at'])) ? ' matching your filters' : ''; ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($commodity_sources_paged as $source): ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       class="row-checkbox" 
                                       name="selected_ids[]" 
                                       value="<?php echo htmlspecialchars($source['id']); ?>"
                                       <?php echo in_array($source['id'], $_SESSION['selected_commodity_sources']) ? 'checked' : ''; ?>
                                       onchange="updateSelection(this, <?php echo $source['id']; ?>)">
                            </td>
                            <td><?php echo htmlspecialchars($source['id']); ?></td>
                            <td><?php echo htmlspecialchars($source['admin0_country']); ?></td>
                            <td><?php echo htmlspecialchars($source['admin1_county_district']); ?></td>
                            <td class="date-added">
                                <?php 
                                if (!empty($source['created_at'])) {
                                    echo date('Y-m-d', strtotime($source['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="edit_commodity_sources.php?id=<?php echo htmlspecialchars($source['id']); ?>">
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?php echo $startIndex + 1; ?> to <?php echo min($startIndex + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> items
                <?php if (count($_SESSION['selected_commodity_sources']) > 0): ?>
                    <span class="selected-count"><?php echo count($_SESSION['selected_commodity_sources']); ?> selected across all pages</span>
                <?php endif; ?>
                <?php if (!empty($sort_column)): ?>
                    <span class="text-muted ms-2">Sorted by: <?php echo ucfirst(str_replace('_', ' ', $sort_column)); ?> (<?php echo $sort_order; ?>)</span>
                <?php endif; ?>
            </div>
            <div>
                <label for="itemsPerPage">Show:</label>
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                    <option value="7" <?php echo ($itemsPerPage == 7) ? 'selected' : ''; ?>>7</option>
                    <option value="10" <?php echo ($itemsPerPage == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo ($itemsPerPage == 20) ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo ($itemsPerPage == 50) ? 'selected' : ''; ?>>50</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : getPageUrl($page - 1, $itemsPerPage, $sort_column, $sort_order); ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo getPageUrl($i, $itemsPerPage, $sort_column, $sort_order); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $totalPages) ? '#' : getPageUrl($page + 1, $itemsPerPage, $sort_column, $sort_order); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Delete form (separate from filter form) -->
<form method="POST" action="" id="deleteForm" style="display: none;">
    <input type="hidden" name="delete_selected" value="1">
</form>

<?php
// Helper function to generate page URLs with filters and sorting
function getPageUrl($pageNum, $itemsPerPage, $sortColumn = null, $sortOrder = null) {
    $url = '?page=' . $pageNum . '&limit=' . $itemsPerPage;
    
    // Add sort parameters if provided
    if ($sortColumn) {
        $url .= '&sort=' . urlencode($sortColumn);
    }
    if ($sortOrder) {
        $url .= '&order=' . urlencode($sortOrder);
    }
    
    // Add filter parameters if they exist
    $filterParams = ['filter_id', 'filter_admin0', 'filter_admin1', 'filter_created_at'];
    foreach ($filterParams as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url .= '&' . $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $url;
}

// Helper function to get sort CSS class
function getSortClass($column) {
    $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'admin0_country';
    $current_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
    
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? 'sort-asc' : 'sort-desc';
    }
    return '';
}
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize select all checkbox based on current page selections
    updateSelectAllCheckbox();
    
    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Commodity Sources');
    }
});

// Update selection function
function updateSelection(checkbox, id) {
    const isSelected = checkbox.checked;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_selection&id=${id}&selected=${isSelected}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Selection updated:', data);
        updateSelectAllCheckbox();
        updateSelectionCount();
    })
    .catch(error => console.error('Error updating selection:', error));
}

function updateSelectionCount() {
    // This would refresh the selection count display
    console.log('Selection count updated');
}

function sortTable(column) {
    const url = new URL(window.location);
    const currentSort = url.searchParams.get('sort');
    const currentOrder = url.searchParams.get('order');
    
    // Toggle order if clicking the same column
    if (currentSort === column) {
        const newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
        url.searchParams.set('order', newOrder);
    } else {
        // New column, default to ASC for most, DESC for created_at
        const defaultOrder = column === 'created_at' ? 'DESC' : 'ASC';
        url.searchParams.set('sort', column);
        url.searchParams.set('order', defaultOrder);
    }
    
    // Reset to page 1 when sorting
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function applyFilters() {
    const filters = {
        id: document.getElementById('filterId').value,
        admin0: document.getElementById('filterAdmin0').value,
        admin1: document.getElementById('filterAdmin1').value,
        createdAt: document.getElementById('filterCreatedAt').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.id) url.searchParams.set('filter_id', filters.id);
    else url.searchParams.delete('filter_id');
    
    if (filters.admin0) url.searchParams.set('filter_admin0', filters.admin0);
    else url.searchParams.delete('filter_admin0');
    
    if (filters.admin1) url.searchParams.set('filter_admin1', filters.admin1);
    else url.searchParams.delete('filter_admin1');
    
    if (filters.createdAt) url.searchParams.set('filter_created_at', filters.createdAt);
    else url.searchParams.delete('filter_created_at');
    
    // Reset to page 1 when filtering
    url.searchParams.set('page', '1');
    
    // Navigate to filtered URL
    window.location.href = url.toString();
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAll');
    
    if (checkboxes.length === 0) {
        selectAll.checked = false;
        return;
    }
    
    // Check if all checkboxes on current page are checked
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const someChecked = Array.from(checkboxes).some(cb => cb.checked);
    
    selectAll.checked = allChecked;
    selectAll.indeterminate = !allChecked && someChecked;
}

document.getElementById('selectAll').addEventListener('change', function() {
    const isChecked = this.checked;
    const checkboxes = document.querySelectorAll('.row-checkbox');
    
    // Update all checkboxes on current page
    checkboxes.forEach(checkbox => {
        if (checkbox.checked !== isChecked) {
            checkbox.checked = isChecked;
            // Trigger the update for each checkbox
            if (checkbox.onchange) {
                checkbox.onchange();
            }
        }
    });
    
    // Clear all selections if unchecking
    if (!isChecked) {
        clearAllSelectionsSilent();
    }
});

function clearAllSelections() {
    if (confirm('Clear all selections across all pages?')) {
        clearAllSelectionsSilent();
        alert('All selections cleared.');
        location.reload();
    }
}

function clearAllSelectionsSilent() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_selection&clear_all=true'
    })
    .catch(error => console.error('Error clearing selections:', error));
}

function deleteSelected() {
    // Get count from session (across all pages)
    const selectedCount = <?php echo count($_SESSION['selected_commodity_sources']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one source to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selectedCount + ' selected source(s) across all pages?')) {
        // Get the delete form
        const deleteForm = document.getElementById('deleteForm');
        
        // Remove any existing selected_ids inputs
        const existingInputs = deleteForm.querySelectorAll('input[name="selected_ids[]"]');
        existingInputs.forEach(input => input.remove());
        
        // Add selected IDs from session
        <?php foreach ($_SESSION['selected_commodity_sources'] as $id): ?>
            const idInput<?php echo $id; ?> = document.createElement('input');
            idInput<?php echo $id; ?>.type = 'hidden';
            idInput<?php echo $id; ?>.name = 'selected_ids[]';
            idInput<?php echo $id; ?>.value = '<?php echo $id; ?>';
            deleteForm.appendChild(idInput<?php echo $id; ?>);
        <?php endforeach; ?>
        
        // Submit the delete form
        deleteForm.submit();
    }
}

function exportSelected(format) {
    const selectedCount = <?php echo count($_SESSION['selected_commodity_sources']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one source to export.');
        return;
    }
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_sources.php';
    
    // Add format parameter
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'export_format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    // Add sort parameters
    const sortInput = document.createElement('input');
    sortInput.type = 'hidden';
    sortInput.name = 'sort';
    sortInput.value = '<?php echo $sort_column; ?>';
    form.appendChild(sortInput);
    
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = 'order';
    orderInput.value = '<?php echo $sort_order; ?>';
    form.appendChild(orderInput);
    
    // Add selected IDs from session
    <?php foreach ($_SESSION['selected_commodity_sources'] as $id): ?>
        const idInput<?php echo $id; ?> = document.createElement('input');
        idInput<?php echo $id; ?>.type = 'hidden';
        idInput<?php echo $id; ?>.name = 'selected_ids[]';
        idInput<?php echo $id; ?>.value = '<?php echo $id; ?>';
        form.appendChild(idInput<?php echo $id; ?>);
    <?php endforeach; ?>
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include '../admin/includes/footer.php'; ?>