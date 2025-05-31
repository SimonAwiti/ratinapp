<?php
session_start();
// Include database configuration
include '../admin/includes/config.php';

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

// Pagination setup
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($commodity_sources);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

// Slice data for current page
$commodity_sources_paged = array_slice($commodity_sources, $startIndex, $itemsPerPage);

// --- Fetch counts for summary boxes ---

// Total Commodity Sources
$total_sources_query = "SELECT COUNT(*) AS total FROM commodity_sources";
$total_sources_result = $con->query($total_sources_query);
$total_sources = $total_sources_result->fetch_assoc()['total'];

// Count by distinct countries (Admin-0)
$distinct_countries_query = "SELECT COUNT(DISTINCT admin0_country) AS total FROM commodity_sources";
$distinct_countries_result = $con->query($distinct_countries_query);
$distinct_countries_count = $distinct_countries_result->fetch_assoc()['total'];

// Count by distinct counties/districts (Admin-1)
$distinct_counties_query = "SELECT COUNT(DISTINCT admin1_county_district) AS total FROM commodity_sources";
$distinct_counties_result = $con->query($distinct_counties_query);
$distinct_counties_count = $distinct_counties_result->fetch_assoc()['total'];

// You could add more specific counts if needed, e.g., sources from a specific country
// For now, let's just use total, distinct countries, distinct counties
// Example: Sources from Kenya (if you anticipate a high count for a specific country)
$kenya_sources_query = "SELECT COUNT(*) AS total FROM commodity_sources WHERE admin0_country = 'Kenya'";
$kenya_sources_result = $con->query($kenya_sources_query);
$kenya_sources_count = $kenya_sources_result->fetch_assoc()['total'];


// Get current URL without query parameters
$currentUrl = strtok($_SERVER["REQUEST_URI"], '?');

// Close the database connection
$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commodity Sources Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css" />
    <link rel="stylesheet" href="assets/globals.css" />
    <link rel="stylesheet" href="assets/styleguide.css" />
    
    <style>
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
            width: 87%; /* Adjusted for consistent alignment with your example */
            max-width: 100%;
            margin: 0 auto 20px auto;
            margin-left: 0.7%; /* Adjusted for consistent alignment with your example */
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
        /* Custom colors for commodity sources stats */
        .total-sources-icon {
            background-color: #3498db; /* Blue */
            color: white;
        }
        .distinct-countries-icon {
            background-color: #8e44ad; /* Purple */
            color: white;
        }
        .distinct-counties-icon {
            background-color: #1abc9c; /* Turquoise */
            color: white;
        }
        .kenya-sources-icon { /* Example for specific country */
            background-color: #e67e22; /* Orange */
            color: white;
        }

        .stats-section {
            text-align: left;
            margin-left: 11%; /* Adjusted for consistent alignment with your example */
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
            color: white; /* Ensure close button is visible on dark background */
            filter: invert(1); /* Tries to make it white, might need specific Bootstrap overrides */
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
        /* Style for text in table cells for consistency */
        td {
            vertical-align: middle; /* Center content vertically */
        }
    </style>
</head>
<body>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Commodity Sources Management</h3></div>
    <p class="p">Manage geographical origins of commodities</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-sources-icon">
                <i class="fas fa-globe-americas"></i> </div>
            <div class="stats-title">Total Sources</div>
            <div class="stats-number"><?= $total_sources ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon distinct-countries-icon">
                <i class="fas fa-flag"></i> </div>
            <div class="stats-title">Distinct Countries</div>
            <div class="stats-number"><?= $distinct_countries_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon distinct-counties-icon">
                <i class="fas fa-city"></i> </div>
            <div class="stats-title">Distinct Counties/Districts</div>
            <div class="stats-number"><?= $distinct_counties_count ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon kenya-sources-icon">
                <i class="fas fa-map-marker-alt"></i> </div>
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
                    <th>Created At</th>
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
                <?php if (!empty($commodity_sources_paged)): ?>
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
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No commodity sources found.</td>
                    </tr>
                <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>