<?php
// Include database configuration
include '../admin/includes/config.php';

// Fetch enumerators with their assigned tradepoints
$query = "
    SELECT 
        e.id,
        e.name,
        e.email,
        e.phone,
        e.gender,
        e.country,
        e.county_district,
        et.tradepoint_id,
        tp.tradepoint_type,
        tp.tradepoint
    FROM enumerators e
    LEFT JOIN enumerator_tradepoints et ON e.id = et.enumerator_id
    LEFT JOIN (
        SELECT id AS tradepoint_id, market_name AS tradepoint, 'Markets' AS tradepoint_type FROM markets
        UNION ALL
        SELECT id AS tradepoint_id, name AS tradepoint, 'Border Points' AS tradepoint_type FROM border_points
        UNION ALL
        SELECT id AS tradepoint_id, miller_name AS tradepoint, 'Miller' AS tradepoint_type FROM miller_details
    ) tp ON et.tradepoint_id = tp.tradepoint_id AND et.tradepoint_type = tp.tradepoint_type
";

$result = $con->query($query);
$enumerators = $result->fetch_all(MYSQLI_ASSOC);

// Pagination setup
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($enumerators);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

// Slice data for current page
$enumerators = array_slice($enumerators, $startIndex, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enumerators Table</title>
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
            background-color: white;
        }
        .btn-group {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        .btn-add-new {
            background-color:  rgba(180, 80, 50, 1);
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
            gap: 20px;
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
        }
        .stats-section {
            text-align: left;
            margin-left: 11%;
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
</head>
<body>
    <div class="stats-section">
        <div class="text-wrapper-8"><h3>Enumerators Management</h3></div>
        <p class="p">Manage everything related to Enumerators</p>
        <div class="stats-container">
            <div class="overlap-6">
                <div class="img-wrapper"><img class="frame-38" src="img/frame-3.svg" /></div>
                <div class="text-wrapper-34">Enumerators</div>
                <div class="text-wrapper-35"><?= $totalItems ?></div>
            </div>
            <div class="overlap-7">
                <div class="overlap-8"><img class="frame-39" src="img/frame-26.svg" /></div>
                <div class="text-wrapper-36">Active</div>
                <div class="text-wrapper-37"><?= $totalItems ?></div>
            </div>
            <div class="overlap-9">
                <div class="overlap-10"><img class="frame-40" src="img/frame-27.svg" /></div>
                <div class="text-wrapper-38">Assigned</div>
                <div class="text-wrapper-39"><?= $totalItems ?></div>
            </div>
            <div class="overlap-9">
                <div class="overlap-10"><img class="frame-40" src="img/frame-3.svg" /></div>
                <div class="text-wrapper-38">Unassigned</div>
                <div class="text-wrapper-39">0</div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="table-container">
            <div class="btn-group">
                <a href="add_enumerator.php" class="btn btn-add-new">
                    <img src="img/frame-10.svg" alt="Add New" style="width: 22px; height: 22px; margin-right: 5px;">
                    Add New
                </a>
                <button class="btn btn-delete" id="deleteSelected">
                    <img src="img/frame-8.svg" alt="Delete" style="width: 20px; height: 20px; margin-right: 3px;">Delete
                </button>
                <div class="dropdown">
                    <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="img/frame-25.svg" alt="Export" style="width: 20px; height: 20px; margin-right: 3px;">
                        Export
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                        <li><a class="dropdown-item" href="#" id="exportExcel">Export to Excel</a></li>
                        <li><a class="dropdown-item" href="#" id="exportPDF">Export to PDF</a></li>
                    </ul>
                </div>
            </div>
            <table class="table table-striped table-hover">
                <thead>
                    <tr style="background-color: #d3d3d3 !important; color: black !important;">
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Name</th>
                        <th>Admin 0</th>
                        <th>Admin 1</th>
                        <th>Tradepoint</th>
                        <th>Tradepoint Type</th>
                        <th>Actions</th>
                    </tr>
                    <tr class="filter-row" style="background-color: white !important; color: black !important;">
                        <th></th>
                        <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                        <th><input type="text" class="filter-input" id="filterAdmin0" placeholder="Filter Admin 0"></th>
                        <th><input type="text" class="filter-input" id="filterAdmin1" placeholder="Filter Admin 1"></th>
                        <th><input type="text" class="filter-input" id="filterTradepoint" placeholder="Filter Tradepoint"></th>
                        <th><input type="text" class="filter-input" id="filterType" placeholder="Filter Type"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="enumeratorTable">
                    <?php foreach ($enumerators as $enum): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-checkbox" value="<?php echo $enum['id']; ?>">
                            </td>
                            <td><?= htmlspecialchars($enum['name']) ?></td>
                            <td><?= htmlspecialchars($enum['country']) ?></td>
                            <td><?= htmlspecialchars($enum['county_district']) ?></td>
                            <td><?= htmlspecialchars($enum['tradepoint']) ?></td>
                            <td><?= htmlspecialchars($enum['tradepoint_type']) ?></td>
                            <td>
                                <a href="edit_enumerator.php?id=<?= $enum['id'] ?>">
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
                    <select id="itemsPerPageSelect" class="form-select d-inline w-auto">
                        <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                        <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                    </select>
                </div>
                <nav>
                    <ul class="pagination mb-0" id="pagination">
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
    <script src="assets/filter3.js"></script>
</body>
</html>