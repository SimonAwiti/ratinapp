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

// Get current URL without query parameters
$currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
?>

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

<script src="assets/filter2.js"></script>