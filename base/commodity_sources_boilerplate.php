<?php
// base/commodity_sources_boilerplate.php

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

// Build query with filters
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

$query .= " ORDER BY admin0_country ASC, admin1_county_district ASC";

// Get all data with filters
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

// Pagination and Filtering Logic
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
    .btn-delete, .btn-export, .btn-bulk-export {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover, .btn-bulk-export:hover {
        background-color: #f8f9fa;
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

            <button class="btn btn-delete" onclick="confirmDelete()">
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
            </button>

            <form method="POST" action="export_current_page_sources.php" style="display: inline;">
                <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
                <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
                <input type="hidden" name="filters" value="<?php echo htmlspecialchars(json_encode($filters)); ?>">
                <button type="submit" class="btn-export">
                    <i class="fas fa-download" style="margin-right: 3px;"></i> Export (Current Page)
                </button>
            </form>

            <form method="POST" action="bulk_export_sources.php" style="display: inline;">
                <input type="hidden" name="filters" value="<?php echo htmlspecialchars(json_encode($filters)); ?>">
                <button type="submit" class="btn-bulk-export">
                    <i class="fas fa-database" style="margin-right: 3px;"></i> Bulk Export (All)
                </button>
            </form>
        </div>

        <form method="GET" action="" id="filterForm">
            <table class="table table-striped table-hover">
                <thead>
                    <tr style="background-color: #d3d3d3 !important; color: black !important;">
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Admin-0 (Country)</th>
                        <th>Admin-1 (County/District)</th>
                        <th>Created On</th>
                        <th>Actions</th>
                    </tr>
                    <tr class="filter-row" style="background-color: white !important; color: black !important;">
                        <th></th>
                        <th>
                            <input type="text" 
                                   class="filter-input" 
                                   name="filter_id" 
                                   placeholder="Filter ID"
                                   value="<?php echo htmlspecialchars($filters['id']); ?>"
                                   onkeyup="this.form.submit()">
                        </th>
                        <th>
                            <input type="text" 
                                   class="filter-input" 
                                   name="filter_admin0" 
                                   placeholder="Filter Country"
                                   value="<?php echo htmlspecialchars($filters['admin0']); ?>"
                                   onkeyup="this.form.submit()">
                        </th>
                        <th>
                            <input type="text" 
                                   class="filter-input" 
                                   name="filter_admin1" 
                                   placeholder="Filter County/District"
                                   value="<?php echo htmlspecialchars($filters['admin1']); ?>"
                                   onkeyup="this.form.submit()">
                        </th>
                        <th>
                            <input type="text" 
                                   class="filter-input" 
                                   name="filter_created_at" 
                                   placeholder="YYYY-MM-DD"
                                   value="<?php echo htmlspecialchars($filters['created_at']); ?>"
                                   onkeyup="this.form.submit()">
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
                                           value="<?php echo htmlspecialchars($source['id']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($source['id']); ?></td>
                                <td><?php echo htmlspecialchars($source['admin0_country']); ?></td>
                                <td><?php echo htmlspecialchars($source['admin1_county_district']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($source['created_at'])); ?></td>
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

            <!-- Hidden inputs for pagination -->
            <input type="hidden" name="page" value="<?php echo $page; ?>">
            <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
        </form>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?php echo $startIndex + 1; ?> to <?php echo min($startIndex + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> items
            </div>
            <div>
                <label for="itemsPerPage">Show:</label>
                <select name="limit" class="form-select d-inline w-auto" onchange="this.form.submit()">
                    <option value="7" <?php echo ($itemsPerPage == 7) ? 'selected' : ''; ?>>7</option>
                    <option value="10" <?php echo ($itemsPerPage == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo ($itemsPerPage == 20) ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo ($itemsPerPage == 50) ? 'selected' : ''; ?>>50</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&limit=<?php echo $itemsPerPage; ?>&<?php echo http_build_query($filters); ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $itemsPerPage; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&limit=<?php echo $itemsPerPage; ?>&<?php echo http_build_query($filters); ?>">Next</a>
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Update select-all when individual checkboxes change
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = document.querySelectorAll('.row-checkbox:checked').length;
            const total = document.querySelectorAll('.row-checkbox').length;
            document.getElementById('selectAll').checked = allChecked === total && total > 0;
        });
    });

    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Commodity Sources');
    }
});

function confirmDelete() {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one source to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selected.length + ' selected source(s)?')) {
        // Get the delete form
        const deleteForm = document.getElementById('deleteForm');
        
        // Remove any existing selected_ids inputs
        const existingInputs = deleteForm.querySelectorAll('input[name="selected_ids[]"]');
        existingInputs.forEach(input => input.remove());
        
        // Add selected IDs to the delete form
        selected.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = checkbox.value;
            deleteForm.appendChild(input);
        });
        
        // Submit the delete form
        deleteForm.submit();
    }
}
</script>

<?php include '../admin/includes/footer.php'; ?>