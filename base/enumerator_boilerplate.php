<?php
// Include database configuration
include '../admin/includes/config.php';

// Function to fetch the actual name of a tradepoint based on ID and type
function getTradepointName($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';

    // Determine the table and name column based on the type
    switch ($type) {
        case 'Market':
        case 'Markets': // Handle both singular and plural if necessary based on your data
            $tableName = 'markets';
            $nameColumn = 'market_name';
            break;
        case 'Border Point':
        case 'Border Points': // Handle both singular and plural
            $tableName = 'border_points';
            $nameColumn = 'name';
            break;
        case 'Miller':
        case 'Miller': // Handle both singular and plural
            $tableName = 'miller_details';
            $nameColumn = 'miller_name';
            break;
        default:
            return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        // Prepare a safe query to prevent SQL injection
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id); // Assuming ID is an integer
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for tradepoint name lookup: " . $con->error);
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)"; // Fallback if name not found or type is unknown
}


// Fetch enumerators with their assigned tradepoints (JSON string)
$query = "
    SELECT
        id,
        name,
        email,
        phone,
        gender,
        country,
        county_district,
        tradepoints, -- Directly select the JSON column
        latitude,
        longitude,
        token
    FROM enumerators
    ORDER BY name ASC
";

$result = $con->query($query);
$enumerators_raw = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $enumerators_raw[] = $row;
    }
}

// Process tradepoints for each enumerator
foreach ($enumerators_raw as &$enum) { // Use & for reference to modify original array
    $assigned_tradepoints_array = [];
    if (!empty($enum['tradepoints'])) {
        $tradepoints_json = json_decode($enum['tradepoints'], true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($tradepoints_json)) {
            foreach ($tradepoints_json as $key => $tp_data) {
                // Ensure 'id' and 'type' keys exist within the nested array
                if (isset($tp_data['id']) && isset($tp_data['type'])) {
                    $tradepoint_id = $tp_data['id'];
                    $tradepoint_type = $tp_data['type'];

                    // Fetch the actual name using the new function
                    $actual_name = getTradepointName($con, $tradepoint_id, $tradepoint_type);

                    // Format for display: "Actual Name (Type)"
                    if (!empty($actual_name) && $actual_name !== "ID: " . htmlspecialchars($tradepoint_id) . " (Name Not Found)") {
                        $assigned_tradepoints_array[] = htmlspecialchars($actual_name) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    } else {
                        // Fallback if name is not found
                        $assigned_tradepoints_array[] = "ID: " . htmlspecialchars($tradepoint_id) . " (" . htmlspecialchars($tradepoint_type) . ")";
                    }
                } else {
                    // Handle cases where 'id' or 'type' might be missing in a tradepoint entry
                    $assigned_tradepoints_array[] = "Malformed Tradepoint Data";
                }
            }
        } else {
            // Handle JSON decoding error or non-array JSON
            $assigned_tradepoints_array[] = 'Invalid JSON or No Tradepoints Defined';
        }
    } else {
        $assigned_tradepoints_array[] = 'No Tradepoints';
    }
    // Store as a pipe-separated string for display
    $enum['formatted_tradepoints'] = implode('|||', $assigned_tradepoints_array);
}
unset($enum); // Unset the reference to avoid unintended modifications

// Pagination setup
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
$totalItems = count($enumerators_raw);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

// Slice data for current page
$enumerators_display = array_slice($enumerators_raw, $startIndex, $itemsPerPage);
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
        /* New styles for tradepoint tags */
        .tradepoints-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .tradepoint-tag {
            background-color: #e0e0e0;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            white-space: nowrap; /* Prevent tags from breaking */
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
                        <th>Assigned Tradepoints</th> <th>Actions</th>
                    </tr>
                    <tr class="filter-row" style="background-color: white !important; color: black !important;">
                        <th></th>
                        <th><input type="text" class="filter-input" id="filterName" placeholder="Filter Name"></th>
                        <th><input type="text" class="filter-input" id="filterAdmin0" placeholder="Filter Admin 0"></th>
                        <th><input type="text" class="filter-input" id="filterAdmin1" placeholder="Filter Admin 1"></th>
                        <th><input type="text" class="filter-input" id="filterTradepoint" placeholder="Filter Tradepoint"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="enumeratorTable">
                    <?php foreach ($enumerators_display as $enum): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-checkbox" value="<?php echo $enum['id']; ?>">
                            </td>
                            <td><?= htmlspecialchars($enum['name']) ?></td>
                            <td><?= htmlspecialchars($enum['country']) ?></td>
                            <td><?= htmlspecialchars($enum['county_district']) ?></td>
                            <td>
                                <div class="tradepoints-list">
                                    <?php
                                    // Split the formatted string into individual tradepoint entries
                                    if (!empty($enum['formatted_tradepoints'])) {
                                        $tradepoints_display = explode('|||', $enum['formatted_tradepoints']);
                                        foreach ($tradepoints_display as $tp_entry):
                                    ?>
                                            <span class="tradepoint-tag"><?= htmlspecialchars($tp_entry) ?></span>
                                    <?php
                                        endforeach;
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </td>
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