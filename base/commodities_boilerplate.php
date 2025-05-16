<?php
// Include database configuration
include '../admin/includes/config.php';

// --- Fetch all data for the table (existing logic) ---
$query = "
    SELECT
        c.id,
        c.hs_code,
        cc.name AS category,
        c.commodity_name,
        c.variety,
        c.image_url
    FROM
        commodities c
    JOIN
        commodity_categories cc ON c.category_id = cc.id
";

$result = $con->query($query);
$commodities = $result->fetch_all(MYSQLI_ASSOC);

// Pagination setup (existing logic)
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($commodities);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

// Slice data for current page (existing logic)
$commodities_paged = array_slice($commodities, $startIndex, $itemsPerPage); // Renamed to avoid confusion with total

// --- New: Fetch counts for summary boxes ---

// Total Commodities
$total_commodities_query = "SELECT COUNT(*) AS total FROM commodities";
$total_commodities_result = $con->query($total_commodities_query);
$total_commodities = $total_commodities_result->fetch_assoc()['total'];

// Count for Cereals (assuming 'Cereals' category has id 3 from your previous code)
$cereals_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id = (SELECT id FROM commodity_categories WHERE name = 'Cereals')";
$cereals_result = $con->query($cereals_query);
$cereals_count = $cereals_result->fetch_assoc()['total'];

// Count for Pulses (assuming 'Pulses' category has id 2 from your previous code)
$pulses_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id = (SELECT id FROM commodity_categories WHERE name = 'Pulses')";
$pulses_result = $con->query($pulses_query);
$pulses_count = $pulses_result->fetch_assoc()['total'];

// Count for Oil Seeds (assuming 'Oil seeds' category has id 1 from your previous code)
$oil_seeds_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id = (SELECT id FROM commodity_categories WHERE name = 'Oil seeds')";
$oil_seeds_result = $con->query($oil_seeds_query);
$oil_seeds_count = $oil_seeds_result->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commodities Table</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background-color: white; /* White background for filter row */
        }
        .btn-group {
            margin-bottom: 15px;
            display: flex;
            gap: 10px; /* Space between buttons */
        }
        .btn-add-new {
            background-color:  rgba(180, 80, 50, 1);
            color: white;
            padding: 10px 20px; /* Larger button */
            font-size: 16px;
            border: none;
        }
        .btn-add-new:hover {
            background-color: darkred; /* Darker maroon on hover */
        }
        .btn-delete, .btn-export {
            background-color: white; /* White background for Delete and Export buttons */
            color: black;
            border: 1px solid #ddd; /* Light border */
            padding: 8px 16px;
        }
        .btn-delete:hover, .btn-export:hover {
            background-color: #f8f9fa; /* Light gray on hover */
        }
        .dropdown-menu {
            min-width: 120px; /* Adjust dropdown width */
        }
        .dropdown-item {
            cursor: pointer; /* Show pointer cursor on dropdown items */
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
            gap: 20px; /* Space between items */
            justify-content: space-between; /* Distributes evenly */
            align-items: center;
            flex-wrap: nowrap; /* Prevent wrapping */
            width: 87%; /* Reduce width to 60% */
            max-width: 100%; /* Ensure responsiveness */
            margin: 0 auto 20px auto; /* Centers the div horizontally */
            margin-left: 0.7%;
        }

        .stats-container > div {
            flex: 1; /* Make all items take equal width */
            background: white; /* Match table styling */
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .stats-section {
            text-align: left;
            margin-left: 11%; /* Adjust to align with stats-container */
        }

        /* Modal Styles */
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
</head>
<body>
<div class="stats-section">
    <div class="text-wrapper-8"><h3>Commodities Management</h3></div>
    <p class="p">Manage everything related to Commodity</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="img-wrapper"><img class="frame-38" src="img/frame-3.svg" /></div>
            <div class="text-wrapper-34">Commodities</div>
            <div class="text-wrapper-35"><?= $total_commodities ?></div>
        </div>
        <div class="overlap-7">
            <div class="overlap-8"><img class="frame-39" src="img/frame-26.svg" /></div>
            <div class="text-wrapper-36">Cereals</div>
            <div class="text-wrapper-37"><?= $cereals_count ?></div>
        </div>
        <div class="overlap-9">
            <div class="overlap-10"><img class="frame-40" src="img/frame-27.svg" /></div>
            <div class="text-wrapper-38">Pulses</div>
            <div class="text-wrapper-39"><?= $pulses_count ?></div>
        </div>
        <div class="overlap-9">
            <div class="overlap-10"><img class="frame-40" src="img/frame-3.svg" /></div>
            <div class="text-wrapper-38">Oil Seeds</div>
            <div class="text-wrapper-39"><?= $oil_seeds_count ?></div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">

        <div class="btn-group">
            <a href="add_commodity.php" class="btn btn-add-new">
                <img src="img/frame-10.svg" alt="Add New" style="width: 22px; height: 22px; margin-right: 5px;">
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()">
                <img src="img/frame-8.svg" alt="Delete" style="width: 20px; height: 20px; margin-right: 3px;">Delete
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="img/frame-25.svg" alt="Export" style="width: 20px; height: 20px; margin-right: 3px;">
                    Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">Export to Excel</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">Export to PDF</a></li>
                </ul>
            </div>
        </div>

        <table class="table table-striped table-hover">
            <thead>
                <tr style="background-color: #d3d3d3 !important; color: black !important;">
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>HS Code</th>
                    <th>Category</th>
                    <th>Commodity</th>
                    <th>Variety</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterHsCode" placeholder="Filter HS Code"></th>
                    <th><input type="text" class="filter-input" id="filterCategory" placeholder="Filter Category"></th>
                    <th><input type="text" class="filter-input" id="filterCommodity" placeholder="Filter Commodity"></th>
                    <th><input type="text" class="filter-input" id="filterVariety" placeholder="Filter Variety"></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="commodityTable">
                <?php foreach ($commodities_paged as $commodity): // Use $commodities_paged here ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?php echo $commodity['id']; ?>">
                        </td>
                        <td><?php echo $commodity['hs_code']; ?></td>
                        <td><?php echo $commodity['category']; ?></td>
                        <td><?php echo $commodity['commodity_name']; ?></td>
                        <td><?php echo $commodity['variety']; ?></td>
                        <td>
                            <?php if (!empty($commodity['image_url'])): ?>
                                <a href="<?php echo $commodity['image_url']; ?>" target="_blank">View</a>
                            <?php else: ?>
                                <span class="text-muted">No Image</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_commodity.php?id=<?= $commodity['id'] ?>">
                                <button class="btn btn-sm btn-warning">
                                    <img src="img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
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
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="changeItemsPerPage()">
                    <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $itemsPerPage ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $itemsPerPage ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/filter.js"></script>
</body>
</html>