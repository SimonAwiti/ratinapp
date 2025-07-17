<?php
// base/enumerator_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Function to fetch the actual name of a tradepoint based on ID and type
function getTradepointName($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';

    switch ($type) {
        case 'Market':
        case 'Markets':
            $tableName = 'markets';
            $nameColumn = 'market_name';
            break;
        case 'Border Point':
        case 'Border Points':
            $tableName = 'border_points';
            $nameColumn = 'name';
            break;
        case 'Miller':
        case 'Millers':
            $tableName = 'miller_details';
            $nameColumn = 'miller_name';
            break;
        default:
            return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)";
}

// --- Fetch all enumerators with their assigned tradepoints ---
$query = "
    SELECT
        id,
        name,
        email,
        phone,
        gender,
        country,
        county_district,
        tradepoints,
        latitude,
        longitude,
        token
    FROM enumerators
    ORDER BY name ASC
";
$result = $con->query($query);
$enumerators_raw = $result->fetch_all(MYSQLI_ASSOC);

// Process tradepoints for each enumerator
foreach ($enumerators_raw as &$enum) {
    $assigned_tradepoints_array = [];
    if (!empty($enum['tradepoints'])) {
        $tradepoints_json = json_decode($enum['tradepoints'], true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tradepoints_json)) {
            foreach ($tradepoints_json as $tp_data) {
                if (isset($tp_data['id']) && isset($tp_data['type'])) {
                    $tradepoint_id = $tp_data['id'];
                    $tradepoint_type = $tp_data['type'];
                    $actual_name = getTradepointName($con, $tradepoint_id, $tradepoint_type);
                    
                    if (!empty($actual_name)) {
                        $assigned_tradepoints_array[] = htmlspecialchars($actual_name) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    } else {
                        $assigned_tradepoints_array[] = "ID: " . htmlspecialchars($tradepoint_id) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    }
                }
            }
        } else {
            $assigned_tradepoints_array[] = 'Invalid JSON or No Tradepoints Defined';
        }
    } else {
        $assigned_tradepoints_array[] = 'No Tradepoints';
    }
    $enum['tradepoints_list'] = $assigned_tradepoints_array;
}
unset($enum);

// Calculate statistics
$totalEnumerators = count($enumerators_raw);
$activeEnumerators = 0;
$assignedEnumerators = 0;
$unassignedEnumerators = 0;

foreach ($enumerators_raw as $enum) {
    $activeEnumerators++;
    if (!empty($enum['tradepoints_list']) && !in_array('No Tradepoints', $enum['tradepoints_list'])) {
        $assignedEnumerators++;
    } else {
        $unassignedEnumerators++;
    }
}

// Pagination and Filtering Logic
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($enumerators_raw);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$enumerators_paged = array_slice($enumerators_raw, $startIndex, $itemsPerPage);
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
    .total-icon {
        background-color: #3498db;
        color: white;
    }
    .active-icon {
        background-color: #27ae60;
        color: white;
    }
    .assigned-icon {
        background-color: #e74c3c;
        color: white;
    }
    .unassigned-icon {
        background-color: #f39c12;
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
    .tradepoints-container {
        max-width: 300px;
        position: relative;
    }
    .tradepoints-visible {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 5px;
    }
    .tradepoint-tag {
        background-color: #e9ecef;
        color: #495057;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        white-space: nowrap;
        border: 1px solid #dee2e6;
    }
    .tradepoint-tag.market {
        background-color: #ffeaea;
        color: #721c24;
        border-color: #f5c6cb;
    }
    .tradepoint-tag.border {
        background-color: #fff3cd;
        color: #856404;
        border-color: #ffeaa7;
    }
    .tradepoint-tag.miller {
        background-color: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
    }
    .show-more-btn {
        background: none;
        border: none;
        color: #007bff;
        font-size: 11px;
        padding: 2px 6px;
        cursor: pointer;
        text-decoration: underline;
    }
    .show-more-btn:hover {
        color: #0056b3;
    }
    .tradepoints-hidden {
        display: none;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px;
    }
    .tradepoints-hidden.show {
        display: flex;
    }
    .no-tradepoints {
        color: #6c757d;
        font-style: italic;
        font-size: 12px;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Enumerators Management</h3></div>
    <p class="p">Manage everything related to Enumerators and their assignments</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-title">Total Enumerators</div>
            <div class="stats-number"><?= $totalEnumerators ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon active-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-title">Active</div>
            <div class="stats-number"><?= $activeEnumerators ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon assigned-icon">
                <i class="fas fa-user-tag"></i>
            </div>
            <div class="stats-title">Assigned</div>
            <div class="stats-number"><?= $assignedEnumerators ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon unassigned-icon">
                <i class="fas fa-user-minus"></i>
            </div>
            <div class="stats-title">Unassigned</div>
            <div class="stats-number"><?= $unassignedEnumerators ?></div>
        </div>
    </div>
</div>

<div class="container">
    <div class="table-container">
        <div class="btn-group">
            <a href="add_enumerator.php" class="btn btn-add-new">
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
                    <th>Name</th>
                    <th>Country</th>
                    <th>Region</th>
                    <th>Assigned Tradepoints</th>
                    <th>Actions</th>
                </tr>
                <tr class="filter-row" style="background-color: white !important; color: black !important;">
                    <th></th>
                    <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                    <th><input type="text" class="filter-input" id="filterCountry" placeholder="Filter Country"></th>
                    <th><input type="text" class="filter-input" id="filterRegion" placeholder="Filter Region"></th>
                    <th><input type="text" class="filter-input" id="filterTradepoints" placeholder="Filter Tradepoints"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="enumeratorTable">
                <?php foreach ($enumerators_paged as $enumerator): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($enumerator['id']) ?>">
                        </td>
                        <td><?= htmlspecialchars($enumerator['name']) ?></td>
                        <td><?= htmlspecialchars($enumerator['country']) ?></td>
                        <td><?= htmlspecialchars($enumerator['county_district']) ?></td>
                        <td>
                            <div class="tradepoints-container">
                                <?php if (!empty($enumerator['tradepoints_list']) && !in_array('No Tradepoints', $enumerator['tradepoints_list'])): ?>
                                    <?php 
                                    $tradepoints = $enumerator['tradepoints_list'];
                                    $visibleCount = 3;
                                    $hasMore = count($tradepoints) > $visibleCount;
                                    ?>
                                    
                                    <div class="tradepoints-visible">
                                        <?php for ($i = 0; $i < min($visibleCount, count($tradepoints)); $i++): ?>
                                            <?php 
                                            $tp = $tradepoints[$i];
                                            $class = '';
                                            if (strpos($tp, '(Markets)') !== false || strpos($tp, '(Market)') !== false) {
                                                $class = 'market';
                                            } elseif (strpos($tp, '(Border Points)') !== false || strpos($tp, '(Border Point)') !== false) {
                                                $class = 'border';
                                            } elseif (strpos($tp, '(Millers)') !== false || strpos($tp, '(Miller)') !== false) {
                                                $class = 'miller';
                                            }
                                            ?>
                                            <span class="tradepoint-tag <?= $class ?>"><?= htmlspecialchars($tp) ?></span>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <?php if ($hasMore): ?>
                                        <button class="show-more-btn" onclick="toggleTradepoints(this)">
                                            +<?= count($tradepoints) - $visibleCount ?> more
                                        </button>
                                        <div class="tradepoints-hidden">
                                            <?php for ($i = $visibleCount; $i < count($tradepoints); $i++): ?>
                                                <?php 
                                                $tp = $tradepoints[$i];
                                                $class = '';
                                                if (strpos($tp, '(Markets)') !== false || strpos($tp, '(Market)') !== false) {
                                                    $class = 'market';
                                                } elseif (strpos($tp, '(Border Points)') !== false || strpos($tp, '(Border Point)') !== false) {
                                                    $class = 'border';
                                                } elseif (strpos($tp, '(Millers)') !== false || strpos($tp, '(Miller)') !== false) {
                                                    $class = 'miller';
                                                }
                                                ?>
                                                <span class="tradepoint-tag <?= $class ?>"><?= htmlspecialchars($tp) ?></span>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-tradepoints">No tradepoints assigned</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="edit_enumerator.php?id=<?= htmlspecialchars($enumerator['id']) ?>">
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
        updateBreadcrumb('Base', 'Enumerators');
    }
});

function toggleTradepoints(button) {
    const hiddenDiv = button.nextElementSibling;
    const isVisible = hiddenDiv.classList.contains('show');
    
    if (isVisible) {
        hiddenDiv.classList.remove('show');
        const totalHidden = hiddenDiv.children.length;
        button.textContent = `+${totalHidden} more`;
    } else {
        hiddenDiv.classList.add('show');
        button.textContent = 'Show less';
    }
}

function applyFilters() {
    const filters = {
        name: document.getElementById('filterName').value.toLowerCase(),
        country: document.getElementById('filterCountry').value.toLowerCase(),
        region: document.getElementById('filterRegion').value.toLowerCase(),
        tradepoints: document.getElementById('filterTradepoints').value.toLowerCase()
    };

    const rows = document.querySelectorAll('#enumeratorTable tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const matches = 
            cells[1].textContent.toLowerCase().includes(filters.name) &&
            cells[2].textContent.toLowerCase().includes(filters.country) &&
            cells[3].textContent.toLowerCase().includes(filters.region) &&
            cells[4].textContent.toLowerCase().includes(filters.tradepoints);
        
        row.style.display = matches ? '' : 'none';
    });
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function deleteSelected() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one enumerator to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${checkedBoxes.length} selected enumerator(s)?`)) {
        const ids = Array.from(checkedBoxes).map(cb => cb.value);
        // Implement your delete logic here
        console.log('Deleting enumerators with IDs:', ids);
        // Example: fetch('delete_enumerators.php', { method: 'POST', body: JSON.stringify({ ids }) })
        // .then(response => response.json())
        // .then(data => { if(data.success) location.reload(); });
    }
}

function exportSelected(format) {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one enumerator to export.');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    // Implement your export logic here
    console.log(`Exporting ${format} for enumerators with IDs:`, ids);
    // Example: window.location.href = `export_enumerators.php?format=${format}&ids=${ids.join(',')}`;
}
</script>

<?php include '../admin/includes/footer.php'; ?>