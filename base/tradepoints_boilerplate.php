<?php
include '../admin/includes/config.php';

$query = "SELECT id, market_name, category, country, county_district FROM markets";
$result = $con->query($query);
$markets = $result->fetch_all(MYSQLI_ASSOC);

$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($markets);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;
$markets = array_slice($markets, $startIndex, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Markets Table</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS & custom styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css" />
    <link rel="stylesheet" href="assets/globals.css" />
    <link rel="stylesheet" href="assets/styleguide.css" />
    <style>
        /* Same style as your commodities_boilerplate */
        <?php include 'markets_style.css'; // Optionally extract to keep DRY ?>
    </style>
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
            background-color:  rgba(180, 80, 50, 1);;
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
    <div class="text-wrapper-8"><h3>Markets Management</h3></div>
    <p class="p">Manage everything related to Markets</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="img-wrapper"><img class="frame-38" src="img/frame-3.svg" /></div>
            <div class="text-wrapper-34">Markets</div>
            <div class="text-wrapper-35"><?= $totalItems ?></div>
        </div>
        <div class="overlap-7">
            <div class="overlap-8"><img class="frame-39" src="img/frame-26.svg" /></div>
            <div class="text-wrapper-36">Urban</div>
            <div class="text-wrapper-37">70</div>
        </div>
        <div class="overlap-9">
            <div class="overlap-10"><img class="frame-40" src="img/frame-27.svg" /></div>
            <div class="text-wrapper-38">Rural</div>
            <div class="text-wrapper-39">50</div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="add_market.php" class="btn btn-add-new">
                <img src="img/frame-10.svg" alt="Add New" style="width: 22px; height: 22px; margin-right: 5px;">
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()"> 
                <img src="img/frame-8.svg" alt="Delete" style="width: 20px; height: 20px; margin-right: 3px;">Delete
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <img src="img/frame-25.svg" alt="Export" style="width: 20px; height: 20px; margin-right: 3px;">
                    Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">Export to Excel</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">Export to PDF</a></li>
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
                    <th><input type="text" class="filter-input" id="filterCategory" placeholder="Filter category"></th>
                    <th><input type="text" class="filter-input" id="filterCountry" placeholder="Filter Admin 0"></th>
                    <th><input type="text" class="filter-input" id="filterCounty" placeholder="Filter Admin 1"></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="marketTable">
                <?php foreach ($markets as $market): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" value="<?= $market['id'] ?>"></td>
                        <td><?= htmlspecialchars($market['market_name']) ?></td>
                        <td><?= htmlspecialchars($market['category']) ?></td>
                        <td><?= htmlspecialchars($market['country']) ?></td>
                        <td><?= htmlspecialchars($market['county_district']) ?></td>
                        <td>
                            <a href="edit_market.php?id=<?= $market['id'] ?>">
                                <button class="btn btn-sm btn-warning">
                                    <img src="img/edit.svg" alt="Edit" style="width: 20px; height: 20px;">
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
                <select id="itemsPerPage" class="form-select d-inline w-auto" onchange="changeItemsPerPage()">
                    <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $itemsPerPage == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                </select>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page - 1 ?>&limit=<?= $itemsPerPage ?>">Prev</a></li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page + 1 ?>&limit=<?= $itemsPerPage ?>">Next</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
        function filterTable() {
            const name = document.getElementById('filterName').value.toUpperCase();
            const category = document.getElementById('filterCategory').value.toUpperCase();
            const country = document.getElementById('filterCountry').value.toUpperCase();
            const county = document.getElementById('filterCounty').value.toUpperCase();

            document.querySelectorAll('#marketTable tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const match =
                        cells[1].textContent.toUpperCase().includes(name) &&
                        cells[2].textContent.toUpperCase().includes(category) &&
                        cells[3].textContent.toUpperCase().includes(country) &&
                        cells[4].textContent.toUpperCase().includes(county);
                    row.style.display = match ? '' : 'none';
                }
            });
        }

        // Bind correct filters
        document.getElementById('filterName').addEventListener('input', filterTable);
        document.getElementById('filterCategory').addEventListener('input', filterTable);
        document.getElementById('filterCountry').addEventListener('input', filterTable);
        document.getElementById('filterCounty').addEventListener('input', filterTable);


    function changeItemsPerPage() {
        let limit = document.getElementById("itemsPerPage").value;
        window.location.href = "?page=1&limit=" + limit;
    }

    document.getElementById("selectAll").addEventListener("change", function () {
        document.querySelectorAll(".row-checkbox").forEach(cb => cb.checked = this.checked);
    });

    function deleteSelected() {
        let ids = getSelectedIds();
        if (!ids.length) return alert("Select items to delete.");
        if (confirm("Delete selected items?")) console.log("Deleting:", ids);
    }

    function exportSelected(type) {
        let ids = getSelectedIds();
        if (!ids.length) return alert("Select items to export.");
        console.log(`Exporting ${type}:`, ids);
    }

    function getSelectedIds() {
        return [...document.querySelectorAll(".row-checkbox:checked")].map(cb => cb.value);
    }
</script>
</body>
</html>
