<?php
session_start();
include '../admin/includes/config.php';

$query = "SELECT 
            id, 
            market_name AS name, 
            'Markets' AS tradepoint_type, 
            country AS admin0, 
            county_district AS admin1
          FROM markets
          
          UNION ALL
          
          SELECT 
            id, 
            name AS name, 
            'Border Points' AS tradepoint_type, 
            country AS admin0, 
            county AS admin1
          FROM border_points
          
          UNION ALL
          
          SELECT 
            id, 
            miller_name AS name, 
            'Millers' AS tradepoint_type,  -- Assuming 'miller_name' is the column that holds the miller's name
            country AS admin0, 
            county_district AS admin1   -- Assuming you have county_district in miller_details
          FROM miller_details
          
          ORDER BY name ASC";

$result = $con->query($query);
$tradepoints = $result->fetch_all(MYSQLI_ASSOC);

$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($tradepoints);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;
$paginatedTradepoints = array_slice($tradepoints, $startIndex, $itemsPerPage);

// Count types
$typeCounts = ['Markets' => 0, 'Border Points' => 0, 'Millers' => 0];
foreach ($tradepoints as $tp) {
    $typeCounts[$tp['tradepoint_type']] = ($typeCounts[$tp['tradepoint_type']] ?? 0) + 1;
}

// Calculate total tradepoints
$totalTradepoints = array_sum($typeCounts);

// Get current URL without query parameters
$currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
?>

<style>
/* Same styles as before... (you can keep yours untouched) */
body {
    padding: 20px;
    background-color: #f8f9fa;
}
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
.total-icon {
    background-color: #3498db;
    color: white;
}
.markets-icon {
    background-color: #e74c3c;
    color: white;
}
.border-icon {
    background-color: #f39c12;
    color: white;
}
.millers-icon {
    background-color: #27ae60;
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
    <div class="text-wrapper-8"><h3>Tradepoint Management</h3></div>
    <p class="p">Manage everything related to Markets, Millers and Border Points</p>

    <div class="stats-container">
        <!-- Total Tradepoints -->
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="stats-title">Total Tradepoints</div>
            <div class="stats-number"><?= $totalTradepoints ?></div>
        </div>
        
        <!-- Markets -->
        <div class="overlap-6">
            <div class="stats-icon markets-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="stats-title">Markets</div>
            <div class="stats-number"><?= $typeCounts['Markets'] ?? 0 ?></div>
        </div>
        
        <!-- Border Points -->
        <div class="overlap-7">
            <div class="stats-icon border-icon">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stats-title">Border Points</div>
            <div class="stats-number"><?= $typeCounts['Border Points'] ?? 0 ?></div>
        </div>
        
        <!-- Millers -->
        <div class="overlap-7">
            <div class="stats-icon millers-icon">
                <i class="fas fa-industry"></i>
            </div>
            <div class="stats-title">Millers</div>
            <div class="stats-number"><?= $typeCounts['Millers'] ?? 0 ?></div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="addtradepoint.php" class="btn btn-add-new">
                <i class="fas fa-plus" style="margin-right: 5px;"></i>
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()"> 
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-download" style="margin-right: 3px;"></i>
                    Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">
                        <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Export to Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">
                        <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export to PDF
                    </a></li>
                </ul>
            </div>
        </div>

        <table class="table table-striped table-hover">
            <thead>
                <tr style="background-color: #d3d3d3 !important; color: black !important;">
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Name</th>
                    <th>Tradepoint</th>
                    <th>Admin 0</th>
                    <th>Admin 1</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row">
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                    <th><input type="text" class="filter-input" id="filterCategory" placeholder="Filter Tradepoint"></th>
                    <th><input type="text" class="filter-input" id="filterCountry" placeholder="Filter Admin 0"></th>
                    <th><input type="text" class="filter-input" id="filterCounty" placeholder="Filter Admin 1"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="marketTable">
                <?php foreach ($paginatedTradepoints as $tp): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" value="<?= $tp['id'] ?>"></td>
                        <td><?= htmlspecialchars($tp['name']) ?></td>
                        <td><?= htmlspecialchars($tp['tradepoint_type']) ?></td>
                        <td><?= htmlspecialchars($tp['admin0']) ?></td>
                        <td><?= htmlspecialchars($tp['admin1']) ?></td>
                        <td>
                            <?php
                            $editPage = '';
                            switch ($tp['tradepoint_type']) {
                                case 'Markets':
                                    $editPage = 'edit_market.php';
                                    break;
                                case 'Border Points':
                                    $editPage = 'edit_borderpoint.php';
                                    break;
                                case 'Millers':
                                    $editPage = 'edit_miller.php';
                                    break;
                                default:
                                    $editPage = '#';
                                    break;
                            }
                            ?>
                            <a href="<?= htmlspecialchars($editPage) ?>?id=<?= htmlspecialchars($tp['id']) ?>">
                                <button class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center">
            <div>Displaying <?= $startIndex + 1 ?> to <?= min($startIndex + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> items</div>
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
                        <a class="page-link" href="<?= $currentUrl ?>?page=<?= $page - 1 ?>&limit=<?= $itemsPerPage ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $currentUrl ?>?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $currentUrl ?>?page=<?= $page + 1 ?>&limit=<?= $itemsPerPage ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

