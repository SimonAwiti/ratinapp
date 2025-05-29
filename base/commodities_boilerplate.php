<?php
session_start();
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
$commodities_paged = array_slice($commodities, $startIndex, $itemsPerPage);

// --- New: Fetch counts for summary boxes ---

// Total Commodities
$total_commodities_query = "SELECT COUNT(*) AS total FROM commodities";
$total_commodities_result = $con->query($total_commodities_query);
$total_commodities = $total_commodities_result->fetch_assoc()['total'];

// Count for Cereals
$cereals_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id = (SELECT id FROM commodity_categories WHERE name = 'Cereals')";
$cereals_result = $con->query($cereals_query);
$cereals_count = $cereals_result->fetch_assoc()['total'];

// Count for Pulses
$pulses_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id = (SELECT id FROM commodity_categories WHERE name = 'Pulses')";
$pulses_result = $con->query($pulses_query);
$pulses_count = $pulses_result->fetch_assoc()['total'];

// Count for Oil Seeds
$oil_seeds_query = "SELECT COUNT(*) AS total FROM commodities WHERE category_id = (SELECT id FROM commodity_categories WHERE name = 'Oil seeds')";
$oil_seeds_result = $con->query($oil_seeds_query);
$oil_seeds_count = $oil_seeds_result->fetch_assoc()['total'];

// Get current URL without query parameters
$currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commodities Management</title>

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
            background-color: #9b59b6;
            color: white;
        }
        .cereals-icon {
            background-color: #f39c12;
            color: white;
        }
        .pulses-icon {
            background-color: #27ae60;
            color: white;
        }
        .oil-seeds-icon {
            background-color: #e74c3c;
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
        .image-preview {
            max-width: 40px;
            max-height: 40px;
            border-radius: 5px;
            object-fit: cover;
        }
        .no-image {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Commodities Management</h3></div>
    <p class="p">Manage everything related to Agricultural Commodities</p>

    <div class="stats-container">
        <!-- Total Commodities -->
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="stats-title">Total Commodities</div>
            <div class="stats-number"><?= $total_commodities ?></div>
        </div>
        
        <!-- Cereals -->
        <div class="overlap-6">
            <div class="stats-icon cereals-icon">
                <i class="fas fa-wheat-awn"></i>
            </div>
            <div class="stats-title">Cereals</div>
            <div class="stats-number"><?= $cereals_count ?></div>
        </div>
        
        <!-- Pulses -->
        <div class="overlap-7">
            <div class="stats-icon pulses-icon">
                <i class="fas fa-dot-circle"></i>
            </div>
            <div class="stats-title">Pulses</div>
            <div class="stats-number"><?= $pulses_count ?></div>
        </div>
        
        <!-- Oil Seeds -->
        <div class="overlap-7">
            <div class="stats-icon oil-seeds-icon">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stats-title">Oil Seeds</div>
            <div class="stats-number"><?= $oil_seeds_count ?></div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="add_commodity.php" class="btn btn-add-new">
                <i class="fas fa-plus" style="margin-right: 5px;"></i>
                Add New
            </a>

            <button class="btn btn-delete" onclick="deleteSelected()">
                <i class="fas fa-trash" style="margin-right: 3px;"></i>
                Delete
            </button>

            <div class="dropdown">
                <button class="btn btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download" style="margin-right: 3px;"></i>
                    Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
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
                <?php foreach ($commodities_paged as $commodity): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($commodity['id']) ?>">
                        </td>
                        <td><?= htmlspecialchars($commodity['hs_code']) ?></td>
                        <td><?= htmlspecialchars($commodity['category']) ?></td>
                        <td><?= htmlspecialchars($commodity['commodity_name']) ?></td>
                        <td><?= htmlspecialchars($commodity['variety']) ?></td>
                        <td>
                            <?php if (!empty($commodity['image_url'])): ?>
                                <img src="<?= htmlspecialchars($commodity['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($commodity['commodity_name']) ?>" 
                                     class="image-preview" 
                                     onclick="showImageModal('<?= htmlspecialchars($commodity['image_url']) ?>', '<?= htmlspecialchars($commodity['commodity_name']) ?>')">
                            <?php else: ?>
                                <span class="no-image">No Image</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_commodity.php?id=<?= htmlspecialchars($commodity['id']) ?>">
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

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Commodity Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 400px;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/filter.js"></script>

<script>
// Function to show image in modal
function showImageModal(imageUrl, commodityName) {
    document.getElementById('modalImage').src = imageUrl;
    document.getElementById('modalImage').alt = commodityName;
    document.getElementById('imageModalLabel').textContent = commodityName + ' - Image';
    
    const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    imageModal.show();
}

// Function to update items per page
function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', 1); // Reset to first page
    window.location.href = url.toString();
}

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Delete selected function (placeholder)
function deleteSelected() {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select items to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selected.length} selected item(s)?`)) {
        // Add your delete logic here
        console.log('Deleting selected items:', Array.from(selected).map(cb => cb.value));
    }
}

// Export selected function (placeholder)
function exportSelected(format) {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select items to export.');
        return;
    }
    
    console.log(`Exporting ${selected.length} items to ${format}`);
    // Add your export logic here
}
</script>

</body>
</html>