<?php
// base/commodity_sources_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// --- Fetch all data for the table ---
$query = "
    SELECT
        id,
        admin0_country,
        admin1_county_district,
        created_at
    FROM
        commodity_sources
    ORDER BY admin0_country ASC, admin1_county_district ASC
";
$result = $con->query($query);
$commodity_sources = $result->fetch_all(MYSQLI_ASSOC);

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
$total_sources = $total_sources_result->fetch_assoc()['total'];

$distinct_countries_query = "SELECT COUNT(DISTINCT admin0_country) AS total FROM commodity_sources";
$distinct_countries_result = $con->query($distinct_countries_query);
$distinct_countries_count = $distinct_countries_result->fetch_assoc()['total'];

$distinct_counties_query = "SELECT COUNT(DISTINCT admin1_county_district) AS total FROM commodity_sources";
$distinct_counties_result = $con->query($distinct_counties_query);
$distinct_counties_count = $distinct_counties_result->fetch_assoc()['total'];

$kenya_sources_query = "SELECT COUNT(*) AS total FROM commodity_sources WHERE admin0_country = 'Kenya'";
$kenya_sources_result = $con->query($kenya_sources_query);
$kenya_sources_count = $kenya_sources_result->fetch_assoc()['total'];
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
    .btn-delete, .btn-export {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-delete:hover, .btn-export:hover {
        background-color: #f8f9fa;
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
            <div class="stats-number"><?= $total_sources ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon distinct-countries-icon">
                <i class="fas fa-flag"></i>
            </div>
            <div class="stats-title">Distinct Countries</div>
            <div class="stats-number"><?= $distinct_countries_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon distinct-counties-icon">
                <i class="fas fa-city"></i>
            </div>
            <div class="stats-title">Distinct Counties/Districts</div>
            <div class="stats-number"><?= $distinct_counties_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon kenya-sources-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stats-title">Sources from Kenya</div>
            <div class="stats-number"><?= $kenya_sources_count ?></div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="add_commodity_sources.php" class="btn btn-add-new">
                <i class="fas fa-plus" style="margin-right: 5px;"></i>
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelectedSources()">
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download" style="margin-right: 3px;"></i>
                    Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportSelectedSources('excel')">
                        <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export to Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelectedSources('pdf')">
                        <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export to PDF
                    </a></li>
                </ul>
            </div>
        </div>

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
                    <th><input type="text" class="filter-input" id="filterId" placeholder="Filter ID"></th>
                    <th><input type="text" class="filter-input" id="filterAdmin0" placeholder="Filter Country"></th>
                    <th><input type="text" class="filter-input" id="filterAdmin1" placeholder="Filter County/District"></th>
                    <th><input type="text" class="filter-input" id="filterCreatedAt" placeholder="Filter Date"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="commoditySourceTable">
                <?php foreach ($commodity_sources_paged as $source): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($source['id']) ?>">
                        </td>
                        <td><?= htmlspecialchars($source['id']) ?></td>
                        <td><?= htmlspecialchars($source['admin0_country']) ?></td>
                        <td><?= htmlspecialchars($source['admin1_county_district']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($source['created_at'])) ?></td>
                        <td>
                            <a href="edit_commodity_sources.php?id=<?= htmlspecialchars($source['id']) ?>">
                                <button class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                Displaying <?= $startIndex + 1 ?> to <?= min($startIndex + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> items
            </div>
            <div>
                <label for="itemsPerPage">Show:</label>
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="updateItemsPerPage(this.value)">
                    <option value="7" <?= $itemsPerPage == 7 ? 'selected' : '' ?>>7</option>
                    <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page <= 1 ? '#' : '?page=' . ($page - 1) . '&limit=' . $itemsPerPage ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $totalPages ? '#' : '?page=' . ($page + 1) . '&limit=' . $itemsPerPage ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize filter functionality
    const filterInputs = document.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('keyup', applyFilters);
    });

    // Initialize select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Commodity Sources');
    }
});

function applyFilters() {
    const filters = {
        id: document.getElementById('filterId').value.toLowerCase(),
        admin0: document.getElementById('filterAdmin0').value.toLowerCase(),
        admin1: document.getElementById('filterAdmin1').value.toLowerCase(),
        createdAt: document.getElementById('filterCreatedAt').value.toLowerCase()
    };

    const rows = document.querySelectorAll('#commoditySourceTable tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const matches = 
            cells[1].textContent.toLowerCase().includes(filters.id) &&
            cells[2].textContent.toLowerCase().includes(filters.admin0) &&
            cells[3].textContent.toLowerCase().includes(filters.admin1) &&
            cells[4].textContent.toLowerCase().includes(filters.createdAt);
        
        row.style.display = matches ? '' : 'none';
    });
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function deleteSelectedSources() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one source to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${checkedBoxes.length} selected source(s)?`)) {
        const ids = Array.from(checkedBoxes).map(cb => cb.value);
        // Implement your delete logic here
        console.log('Deleting sources with IDs:', ids);
        // Example: fetch('delete_sources.php', { method: 'POST', body: JSON.stringify({ ids }) })
        // .then(response => response.json())
        // .then(data => { if(data.success) location.reload(); });
    }
}

function exportSelectedSources(format) {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one source to export.');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    // Implement your export logic here
    console.log(`Exporting ${format} for sources with IDs:`, ids);
    // Example: window.location.href = `export_sources.php?format=${format}&ids=${ids.join(',')}`;
}
</script>

<?php include '../admin/includes/footer.php'; ?>