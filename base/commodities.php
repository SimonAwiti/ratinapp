<?php
// Include database configuration
include '../admin/includes/config.php';

// Fetch all data
$query = "SELECT id, hs_code, category, product_name AS commodity, product_ABBRV AS variety, image FROM product";
$result = $con->query($query);
$commodities = $result->fetch_all(MYSQLI_ASSOC);

// Pagination setup
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$totalItems = count($commodities);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

// Slice data for current page
$commodities = array_slice($commodities, $startIndex, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commodities Table</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
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
            background-color: maroon; /* Maroon color for Add New button */
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
    </style>
</head>
<body>

<div class="container">
    <div class="table-container">
        <h4 class="mb-3">Commodities Management</h4>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="add_commodity.php" class="btn btn-add-new">➕ Add New</a>
            <button class="btn btn-delete" onclick="deleteSelected()">🗑 Delete</button>
            <!-- Export Dropdown -->
            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    📊 Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('excel')">Export to Excel</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">Export to PDF</a></li>
                </ul>
            </div>
        </div>

        <!-- Table -->
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>HS Code</th>
                    <th>Category</th>
                    <th>Commodity</th>
                    <th>Variety</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row"> <!-- White background for filter row -->
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
                <?php foreach ($commodities as $commodity): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?php echo $commodity['id']; ?>">
                        </td>
                        <td><?php echo $commodity['hs_code']; ?></td>
                        <td><?php echo $commodity['category']; ?></td>
                        <td><?php echo $commodity['commodity']; ?></td>
                        <td><?php echo $commodity['variety']; ?></td>
                        <td>
                            <?php if (!empty($commodity['image_url'])): ?>
                                <a href="<?php echo $commodity['image_url']; ?>" target="_blank">View</a>
                            <?php else: ?>
                                <span class="text-muted">No Image</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning">✏ Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
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

<!-- Bootstrap & JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to filter the table based on input values
    function filterTable() {
        const filterHsCode = document.getElementById('filterHsCode').value.toUpperCase();
        const filterCategory = document.getElementById('filterCategory').value.toUpperCase();
        const filterCommodity = document.getElementById('filterCommodity').value.toUpperCase();
        const filterVariety = document.getElementById('filterVariety').value.toUpperCase();

        const rows = document.querySelectorAll('#commodityTable tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                const hsCode = cells[1].textContent.toUpperCase();
                const category = cells[2].textContent.toUpperCase();
                const commodity = cells[3].textContent.toUpperCase();
                const variety = cells[4].textContent.toUpperCase();

                const matchHsCode = hsCode.includes(filterHsCode);
                const matchCategory = category.includes(filterCategory);
                const matchCommodity = commodity.includes(filterCommodity);
                const matchVariety = variety.includes(filterVariety);

                if (matchHsCode && matchCategory && matchCommodity && matchVariety) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }

    // Add event listeners to the filter inputs
    document.getElementById('filterHsCode').addEventListener('input', filterTable);
    document.getElementById('filterCategory').addEventListener('input', filterTable);
    document.getElementById('filterCommodity').addEventListener('input', filterTable);
    document.getElementById('filterVariety').addEventListener('input', filterTable);

    // Existing functions
    function changeItemsPerPage() {
        let limit = document.getElementById("itemsPerPage").value;
        window.location.href = "?page=1&limit=" + limit;
    }

    document.getElementById("selectAll").addEventListener("change", function() {
        let checkboxes = document.querySelectorAll(".row-checkbox");
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });

    function deleteSelected() {
        let selectedIds = getSelectedIds();
        if (selectedIds.length === 0) {
            alert("Select items to delete.");
            return;
        }
        if (confirm("Delete selected items?")) {
            console.log("Deleted IDs:", selectedIds);
        }
    }

    function exportSelected(type) {
        let selectedIds = getSelectedIds();
        if (selectedIds.length === 0) {
            alert("Select items to export.");
            return;
        }
        console.log(`Exporting ${type}:`, selectedIds);
    }

    function getSelectedIds() {
        return [...document.querySelectorAll(".row-checkbox:checked")].map(checkbox => checkbox.value);
    }
</script>

</body>
</html>
