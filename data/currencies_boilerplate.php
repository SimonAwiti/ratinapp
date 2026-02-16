<?php
// base/currencyrates_boilerplate.php
session_start();

// Initialize selected currencies in session if not exists
if (!isset($_SESSION['selected_currencies'])) {
    $_SESSION['selected_currencies'] = [];
}

// Handle selection updates via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_selection') {
    $id = $_POST['id'];
    $isSelected = $_POST['selected'] === 'true';
    
    if ($isSelected) {
        if (!in_array($id, $_SESSION['selected_currencies'])) {
            $_SESSION['selected_currencies'][] = $id;
        }
    } else {
        $key = array_search($id, $_SESSION['selected_currencies']);
        if ($key !== false) {
            unset($_SESSION['selected_currencies'][$key]);
            $_SESSION['selected_currencies'] = array_values($_SESSION['selected_currencies']);
        }
    }
    
    // Clear all selections
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        $_SESSION['selected_currencies'] = [];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['selected_currencies'])]);
    exit;
}

// Clear all selections if requested via GET
if (isset($_GET['clear_selections'])) {
    $_SESSION['selected_currencies'] = [];
}

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Handle CSV import
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // Skip header row
    fgetcsv($handle);
    
    $successCount = 0;
    $errorCount = 0;
    $errors = array();
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        $rowNumber = 1; // Track row numbers for better error reporting
        
        while (($data = fgetcsv($handle, 1000, ","))) {
            $rowNumber++;
            
            // Skip completely empty rows
            if (empty($data) || (count($data) == 1 && empty(trim($data[0])))) {
                continue;
            }
            
            // Validate required fields
            if (empty(trim($data[0]))) { // Country
                $errors[] = "Row $rowNumber: Country is required";
                $errorCount++;
                continue;
            }
            
            if (empty(trim($data[1]))) { // Currency Code
                $errors[] = "Row $rowNumber: Currency Code is required";
                $errorCount++;
                continue;
            }
            
            if (empty(trim($data[2])) || !is_numeric(trim($data[2]))) { // Exchange Rate
                $errors[] = "Row $rowNumber: Valid Exchange Rate is required";
                $errorCount++;
                continue;
            }
            
            if (empty(trim($data[3]))) { // Effective Date
                $errors[] = "Row $rowNumber: Effective Date is required";
                $errorCount++;
                continue;
            }
            
            // Prepare currency data
            $country = trim($data[0]);
            $currency_code = trim($data[1]);
            $exchange_rate = floatval(trim($data[2]));
            $effective_date = trim($data[3]);
            
            // Validate date format
            if (!strtotime($effective_date)) {
                $errors[] = "Row $rowNumber: Invalid date format for Effective Date";
                $errorCount++;
                continue;
            }
            
            // Format date properly
            $effective_date = date('Y-m-d', strtotime($effective_date));
            
            // Additional fields for record keeping
            $date_created = date('Y-m-d H:i:s');
            $day = date('d');
            $month = date('m');
            $year = date('Y');
            
            // Check if currency rate already exists for the same country, currency and date
            $check_query = "SELECT id FROM currencies WHERE country = ? AND currency_code = ? AND effective_date = ?";
            $check_stmt = $con->prepare($check_query);
            $check_stmt->bind_param('sss', $country, $currency_code, $effective_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                if (isset($_POST['overwrite_existing'])) {
                    // Update existing currency rate
                    $update_query = "UPDATE currencies SET 
                        exchange_rate = ?,
                        date_created = ?,
                        day = ?,
                        month = ?,
                        year = ?
                        WHERE country = ? AND currency_code = ? AND effective_date = ?";
                    
                    $update_stmt = $con->prepare($update_query);
                    if (!$update_stmt) {
                        $errors[] = "Row $rowNumber: Failed to prepare update statement: " . $con->error;
                        $errorCount++;
                        continue;
                    }
                    
                    $update_stmt->bind_param(
                        'dsiiisss',
                        $exchange_rate,
                        $date_created,
                        $day,
                        $month,
                        $year,
                        $country,
                        $currency_code,
                        $effective_date
                    );
                    
                    if ($update_stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Row $rowNumber: Update failed - " . $update_stmt->error;
                        $errorCount++;
                    }
                    $update_stmt->close();
                } else {
                    $errors[] = "Row $rowNumber: Currency rate for '$country' ($currency_code) on '$effective_date' already exists (use overwrite option to update)";
                    $errorCount++;
                }
                continue;
            }
            
            // Insert new currency rate
            $insert_query = "INSERT INTO currencies (
                country, 
                currency_code, 
                exchange_rate, 
                effective_date,
                date_created,
                day,
                month,
                year
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $con->prepare($insert_query);
            if (!$insert_stmt) {
                $errors[] = "Row $rowNumber: Failed to prepare insert statement: " . $con->error;
                $errorCount++;
                continue;
            }
            
            $insert_stmt->bind_param(
                'ssdssiii',
                $country,
                $currency_code,
                $exchange_rate,
                $effective_date,
                $date_created,
                $day,
                $month,
                $year
            );
            
            if ($insert_stmt->execute()) {
                $successCount++;
            } else {
                $errors[] = "Row $rowNumber: Insert failed - " . $insert_stmt->error;
                $errorCount++;
            }
            $insert_stmt->close();
        }
        
        // Commit transaction if no critical errors
        if ($errorCount === 0) {
            $con->commit();
            $import_message = "Successfully imported $successCount currency rates.";
            $import_status = 'success';
        } else {
            $con->rollback();
            $import_message = "Import rolled back due to $errorCount errors. Processed $successCount rows successfully. Errors: " . implode('<br>', $errors);
            $import_status = 'danger';
        }
        
    } catch (Exception $e) {
        $con->rollback();
        $import_message = "Import failed with exception: " . $e->getMessage();
        $import_status = 'danger';
    }
    
    fclose($handle);
} elseif (isset($_POST['import_csv'])) {
    $import_message = "Please select a valid CSV file to import.";
    $import_status = 'danger';
}

// --- Fetch all data for the table with filtering and sorting ---
$query = "
    SELECT
        cr.id,
        cr.country,
        cr.currency_code,
        cr.exchange_rate,
        cr.effective_date,
        cr.date_created
    FROM
        currencies cr
    WHERE 1=1
";

// Apply filters from GET parameters
$filterConditions = [];
$params = [];
$types = '';

if (isset($_GET['filter_id']) && !empty($_GET['filter_id'])) {
    $filterConditions[] = "cr.id = ?";
    $params[] = $_GET['filter_id'];
    $types .= 'i';
}

if (isset($_GET['filter_country']) && !empty($_GET['filter_country'])) {
    $filterConditions[] = "cr.country LIKE ?";
    $params[] = '%' . $_GET['filter_country'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_currency']) && !empty($_GET['filter_currency'])) {
    $filterConditions[] = "cr.currency_code LIKE ?";
    $params[] = '%' . $_GET['filter_currency'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_rate']) && !empty($_GET['filter_rate'])) {
    $filterConditions[] = "cr.exchange_rate LIKE ?";
    $params[] = '%' . $_GET['filter_rate'] . '%';
    $types .= 's';
}

if (isset($_GET['filter_date']) && !empty($_GET['filter_date'])) {
    $filterConditions[] = "cr.effective_date LIKE ?";
    $params[] = '%' . $_GET['filter_date'] . '%';
    $types .= 's';
}

if (!empty($filterConditions)) {
    $query .= " AND " . implode(" AND ", $filterConditions);
}

// Apply sorting
$sortable_columns = ['id', 'country', 'currency_code', 'exchange_rate', 'effective_date', 'date_created'];
$default_sort_column = 'effective_date';
$default_sort_order = 'DESC';

$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : $default_sort_column;
$sort_order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : $default_sort_order;

// Map column names for database
$db_column_map = [
    'id' => 'cr.id',
    'country' => 'cr.country',
    'currency_code' => 'cr.currency_code',
    'exchange_rate' => 'cr.exchange_rate',
    'effective_date' => 'cr.effective_date',
    'date_created' => 'cr.date_created'
];

$db_sort_column = isset($db_column_map[$sort_column]) ? $db_column_map[$sort_column] : $db_column_map[$default_sort_column];
$query .= " ORDER BY $db_sort_column $sort_order";

// Prepare and execute query with filters and sorting
$stmt = $con->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$all_currencies = $result->fetch_all(MYSQLI_ASSOC);

// Pagination Logic
$itemsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$totalItems = count($all_currencies);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$startIndex = ($page - 1) * $itemsPerPage;

$currencies_paged = array_slice($all_currencies, $startIndex, $itemsPerPage);

// --- Fetch counts for summary boxes ---
$total_currencies_query = "SELECT COUNT(*) AS total FROM currencies";
$total_currencies_result = $con->query($total_currencies_query);
$total_currencies = 0;
if ($total_currencies_result) {
    $row = $total_currencies_result->fetch_assoc();
    $total_currencies = $row['total'];
}

// Get unique countries count
$unique_countries_query = "SELECT COUNT(DISTINCT country) AS total FROM currencies";
$unique_countries_result = $con->query($unique_countries_query);
$unique_countries = 0;
if ($unique_countries_result) {
    $row = $unique_countries_result->fetch_assoc();
    $unique_countries = $row['total'];
}

// Get latest update date
$latest_update_query = "SELECT MAX(effective_date) AS latest FROM currencies";
$latest_update_result = $con->query($latest_update_query);
$latest_update = 'N/A';
if ($latest_update_result && $row = $latest_update_result->fetch_assoc()) {
    $latest_update = $row['latest'] ? date('Y-m-d', strtotime($row['latest'])) : 'N/A';
}
?>

<style>
    .container {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin: 20px;
    }
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .toolbar-left {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .toolbar-right {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .toolbar button, .toolbar .primary {
        padding: 12px 20px;
        font-size: 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        background-color: #eee;
    }
    .toolbar .primary {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        text-decoration: none;
        display: inline-block;
        line-height: normal;
    }
    .btn-add-new {
        background-color: rgba(180, 80, 50, 1);
        color: white;
        padding: 10px 20px;
        font-size: 16px;
        border: none;
        border-radius: 8px;
        text-decoration: none;
        display: inline-block;
    }
    .btn-add-new:hover {
        background-color: darkred;
    }
    .btn-delete, .btn-export, .btn-import, .btn-bulk-export, .btn-clear-selections {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
        border-radius: 8px;
    }
    .btn-delete:hover, .btn-export:hover, .btn-import:hover, .btn-bulk-export:hover, .btn-clear-selections:hover {
        background-color: #f8f9fa;
    }
    .btn-clear-selections {
        background-color: #ffc107;
        color: black;
    }
    .btn-clear-selections:hover {
        background-color: #e0a800;
    }
    .btn-bulk-export {
        background-color: #17a2b8;
        color: white;
    }
    .btn-bulk-export:hover {
        background-color: #138496;
    }
    .search-box {
        display: flex;
        align-items: center;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 8px 12px;
        min-width: 300px;
    }
    .search-box input {
        border: none;
        outline: none;
        flex: 1;
        padding: 4px 8px;
        font-size: 14px;
    }
    .search-box button {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: #666;
    }
    .search-box button:hover {
        color: #333;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    table th, table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        text-align: left;
        vertical-align: middle;
    }
    table th {
        background-color: #f1f1f1;
        font-weight: 600;
    }
    table tr:nth-child(even) {
        background-color: #fafafa;
    }
    .actions {
        display: flex;
        gap: 8px;
    }
    .pagination {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        font-size: 14px;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .pagination .pages {
        display: flex;
        gap: 5px;
    }
    .pagination .page {
        padding: 6px 10px;
        border-radius: 6px;
        background-color: #eee;
        cursor: pointer;
        text-decoration: none;
        color: #333;
    }
    .pagination .current {
        background-color: #cddc39;
    }
    select {
        padding: 6px;
        margin-left: 5px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .currency-code {
        font-weight: bold;
        color: #333;
    }
    .exchange-rate {
        font-family: monospace;
    }
    .btn-group {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .dropdown-menu {
        min-width: 120px;
    }
    .dropdown-item {
        cursor: pointer;
    }
    .import-instructions {
        background-color: #f8f9fa;
        border-left: 4px solid rgba(180, 80, 50, 1);
        padding: 15px;
        margin-bottom: 20px;
    }
    .import-instructions h5 {
        color: rgba(180, 80, 50, 1);
        margin-top: 0;
    }
    .download-template {
        display: inline-block;
        margin-top: 10px;
        color: rgba(180, 80, 50, 1);
        text-decoration: none;
    }
    .download-template:hover {
        text-decoration: underline;
    }
    .alert {
        margin-bottom: 20px;
    }
    .filter-row {
        background-color: #f8f9fa;
    }
    .filter-input {
        width: 100%;
        border: 1px solid #ddd;
        background: white;
        padding: 5px;
        border-radius: 4px;
        font-size: 0.85rem;
    }
    .filter-input:focus {
        outline: none;
        border-color: rgba(180, 80, 50, 0.5);
        box-shadow: 0 0 3px rgba(180, 80, 50, 0.3);
    }
    .selected-count {
        display: inline-block;
        background-color: rgba(180, 80, 50, 0.1);
        color: rgba(180, 80, 50, 1);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        margin-left: 5px;
        font-weight: bold;
    }
    .sortable {
        cursor: pointer;
        position: relative;
        user-select: none;
    }
    .sortable:hover {
        background-color: #e0e0e0;
    }
    .sort-icon {
        display: inline-block;
        margin-left: 5px;
        font-size: 0.8em;
        opacity: 0.7;
    }
    .sort-asc .sort-icon::after {
        content: "↑";
    }
    .sort-desc .sort-icon::after {
        content: "↓";
    }
    .sortable.sort-asc,
    .sortable.sort-desc {
        background-color: #d4d4d4;
        font-weight: bold;
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
    .countries-icon {
        background-color: #f39c12;
        color: white;
    }
    .latest-icon {
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
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
</style>

<div class="stats-section">
    <div class="text-wrapper-8"><h3>Currency Rates Management</h3></div>
    <p class="p">Manage Currency Exchange Rate Data</p>

    <div class="stats-container">
        <div class="overlap-6">
            <div class="stats-icon total-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stats-title">Total Currency Rates</div>
            <div class="stats-number"><?php echo $total_currencies; ?></div>
        </div>
        
        <div class="overlap-6">
            <div class="stats-icon countries-icon">
                <i class="fas fa-globe"></i>
            </div>
            <div class="stats-title">Unique Countries</div>
            <div class="stats-number"><?php echo $unique_countries; ?></div>
        </div>
        
        <div class="overlap-7">
            <div class="stats-icon latest-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stats-title">Latest Update</div>
            <div class="stats-number"><?php echo $latest_update; ?></div>
        </div>
    </div>
</div>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?php echo $import_status; ?>">
        <?php echo $import_message; ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="btn-group">
        <a href="../data/add_currency.php" class="btn btn-add-new">
            <i class="fas fa-plus" style="margin-right: 5px;"></i>
            Add New
        </a>

        <button class="btn btn-delete" onclick="deleteSelected()">
            <i class="fas fa-trash" style="margin-right: 3px;"></i>
            Delete
            <?php if (count($_SESSION['selected_currencies']) > 0): ?>
                <span class="selected-count"><?php echo count($_SESSION['selected_currencies']); ?></span>
            <?php endif; ?>
        </button>

        <button class="btn btn-clear-selections" onclick="clearAllSelections()">
            <i class="fas fa-times-circle" style="margin-right: 3px;"></i>
            Clear Selections
        </button>

        <form method="POST" action="export_current_page_currencies.php" style="display: inline;">
            <input type="hidden" name="limit" value="<?php echo $itemsPerPage; ?>">
            <input type="hidden" name="offset" value="<?php echo $startIndex; ?>">
            <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
            <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
            <button type="submit" class="btn-export">
                <i class="fas fa-download" style="margin-right: 3px;"></i> Export (Current Page)
            </button>
        </form>

        <div class="dropdown" style="display: inline-block;">
            <button class="btn btn-bulk-export dropdown-toggle" type="button" id="exportAllDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-database" style="margin-right: 3px;"></i> Bulk Export
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportAllDropdown">
                <li><a class="dropdown-item" href="#" onclick="exportAll('csv')">
                    <i class="fas fa-file-csv" style="margin-right: 8px;"></i>All to CSV
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportSelected('csv')">
                    <i class="fas fa-file-csv" style="margin-right: 8px;"></i>Selected to CSV
                </a></li>
            </ul>
        </div>
        
        <button class="btn btn-import" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-upload" style="margin-right: 3px;"></i>
            Import
        </button>
    </div>



    <table>
        <thead>
            <tr style="background-color: #d3d3d3 !important; color: black !important;">
                <th><input type="checkbox" id="selectAll"></th>
                <th class="sortable <?php echo getSortClass('id'); ?>" onclick="sortTable('id')">
                    ID
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('country'); ?>" onclick="sortTable('country')">
                    Country
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('currency_code'); ?>" onclick="sortTable('currency_code')">
                    Currency
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('exchange_rate'); ?>" onclick="sortTable('exchange_rate')">
                    Exchange Rate (to USD)
                    <span class="sort-icon"></span>
                </th>
                <th class="sortable <?php echo getSortClass('effective_date'); ?>" onclick="sortTable('effective_date')">
                    Effective Date
                    <span class="sort-icon"></span>
                </th>
                <th>Actions</th>
            </tr>
            <tr class="filter-row">
                <th></th>
                <th>
                    <input type="text" class="filter-input" id="filterId" placeholder="Filter ID"
                           value="<?php echo isset($_GET['filter_id']) ? htmlspecialchars($_GET['filter_id']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterCountry" placeholder="Filter Country"
                           value="<?php echo isset($_GET['filter_country']) ? htmlspecialchars($_GET['filter_country']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterCurrency" placeholder="Filter Currency"
                           value="<?php echo isset($_GET['filter_currency']) ? htmlspecialchars($_GET['filter_currency']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterRate" placeholder="Filter Rate"
                           value="<?php echo isset($_GET['filter_rate']) ? htmlspecialchars($_GET['filter_rate']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th>
                    <input type="text" class="filter-input" id="filterDate" placeholder="Filter Date (YYYY-MM-DD)"
                           value="<?php echo isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : ''; ?>"
                           onkeyup="applyFilters()">
                </th>
                <th></th>
            </tr>
        </thead>
        <tbody id="currencyRatesTable">
            <?php if (!empty($currencies_paged)): ?>
                <?php foreach ($currencies_paged as $rate): ?>
                    <tr>
                        <td>
                            <input type="checkbox" 
                                   class="row-checkbox" 
                                   value="<?php echo htmlspecialchars($rate['id']); ?>"
                                   data-id="<?php echo $rate['id']; ?>"
                                   <?php echo in_array($rate['id'], $_SESSION['selected_currencies']) ? 'checked' : ''; ?>
                                   onchange="updateSelection(this, <?php echo $rate['id']; ?>)">
                        </td>
                        <td><?php echo htmlspecialchars($rate['id']); ?></td>
                        <td><?php echo htmlspecialchars($rate['country']); ?></td>
                        <td><span class="currency-code"><?php echo htmlspecialchars($rate['currency_code']); ?></span></td>
                        <td class="exchange-rate"><?php echo number_format($rate['exchange_rate'], 4); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($rate['effective_date'])); ?></td>
                        <td>
                            <a href="../data/edit_currency.php?id=<?= $rate['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        No currency rates found<?php echo !empty($_GET['search']) ? ' matching your search criteria' : ''; ?>.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <div>
            Displaying <?php echo $startIndex + 1; ?> to <?php echo min($startIndex + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> items
            <?php if (count($_SESSION['selected_currencies']) > 0): ?>
                <span class="selected-count"><?php echo count($_SESSION['selected_currencies']); ?> selected across all pages</span>
            <?php endif; ?>
            <?php if (!empty($sort_column)): ?>
                <span class="text-muted ms-2">Sorted by: <?php echo ucfirst(str_replace('_', ' ', $sort_column)); ?> (<?php echo $sort_order; ?>)</span>
            <?php endif; ?>
        </div>
        <div>
            <label for="itemsPerPage">Show:</label>
            <select id="itemsPerPage" onchange="updateItemsPerPage(this.value)">
                <option value="10" <?php echo ($itemsPerPage == 10) ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo ($itemsPerPage == 25) ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo ($itemsPerPage == 50) ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo ($itemsPerPage == 100) ? 'selected' : ''; ?>>100</option>
            </select>
        </div>
        <div class="pages">
            <?php if ($page > 1): ?>
                <a href="<?php echo getPageUrl($page - 1, $itemsPerPage, $sort_column, $sort_order); ?>" class="page">‹</a>
            <?php endif; ?>

            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($totalPages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="<?php echo getPageUrl($i, $itemsPerPage, $sort_column, $sort_order); ?>" class="page <?php echo ($page == $i) ? 'current' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?php echo getPageUrl($page + 1, $itemsPerPage, $sort_column, $sort_order); ?>" class="page">›</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Currency Rates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="import-instructions">
                    <h5>CSV Format</h5>
                    <p>Your CSV file needs these 4 columns:</p>
                    <ul>
                        <li><strong>Country</strong> (e.g., "Kenya", "Ethiopia")</li>
                        <li><strong>Currency Code</strong> (e.g., "KES", "ETB")</li>
                        <li><strong>Exchange Rate</strong> (e.g., "128.24")</li>
                        <li><strong>Effective Date</strong> (e.g., "2025-11-13")</li>
                    </ul>
                    <a href="../data/generate_currency_template.php" class="download-template">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="overwriteExisting" name="overwrite_existing">
                        <label class="form-check-label" for="overwriteExisting">
                            Overwrite existing currency rates with matching Country, Currency Code and Effective Date
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="importForm" name="import_csv" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to generate page URLs with filters and sorting
function getPageUrl($pageNum, $itemsPerPage, $sortColumn = null, $sortOrder = null) {
    $url = '?page=' . $pageNum . '&limit=' . $itemsPerPage;
    
    // Add sort parameters if provided
    if ($sortColumn) {
        $url .= '&sort=' . urlencode($sortColumn);
    }
    if ($sortOrder) {
        $url .= '&order=' . urlencode($sortOrder);
    }
    
    // Add search parameter if exists
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $url .= '&search=' . urlencode($_GET['search']);
    }
    
    // Add filter parameters if they exist
    $filterParams = ['filter_id', 'filter_country', 'filter_currency', 'filter_rate', 'filter_date'];
    foreach ($filterParams as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url .= '&' . $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $url;
}

// Helper function to get sort CSS class
function getSortClass($column) {
    $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'effective_date';
    $current_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
    
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? 'sort-asc' : 'sort-desc';
    }
    return '';
}
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize filter inputs with current values
    const filterInputs = document.querySelectorAll('.filter-input');
    
    // Initialize select all checkbox based on current page selections
    updateSelectAllCheckbox();
    
    // Update breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Currency Rates');
    }
    
    // Show import modal if there was an error
    <?php if (isset($import_message) && $import_status === 'danger'): ?>
        var importModal = new bootstrap.Modal(document.getElementById('importModal'));
        importModal.show();
    <?php endif; ?>
});

// Update selection function
function updateSelection(checkbox, id) {
    const isSelected = checkbox.checked;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_selection&id=${id}&selected=${isSelected}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Selection updated:', data);
        updateSelectAllCheckbox();
        updateSelectionCount();
    })
    .catch(error => console.error('Error updating selection:', error));
}

function updateSelectionCount() {
    // Update the selection count display
    const count = <?php echo count($_SESSION['selected_currencies']); ?>;
    const countElements = document.querySelectorAll('.selected-count');
    countElements.forEach(el => {
        if (el) el.textContent = count + ' selected';
    });
}

function sortTable(column) {
    const url = new URL(window.location);
    const currentSort = url.searchParams.get('sort');
    const currentOrder = url.searchParams.get('order');
    
    // Toggle order if clicking the same column
    if (currentSort === column) {
        const newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
        url.searchParams.set('order', newOrder);
    } else {
        // New column, default to DESC for ID and date, ASC for others
        const defaultOrder = (column === 'id' || column === 'effective_date') ? 'DESC' : 'ASC';
        url.searchParams.set('sort', column);
        url.searchParams.set('order', defaultOrder);
    }
    
    // Reset to page 1 when sorting
    url.searchParams.set('page', '1');
    
    window.location.href = url.toString();
}

function applyFilters() {
    const filters = {
        id: document.getElementById('filterId').value,
        country: document.getElementById('filterCountry').value,
        currency: document.getElementById('filterCurrency').value,
        rate: document.getElementById('filterRate').value,
        date: document.getElementById('filterDate').value
    };

    // Build URL with filter parameters
    const url = new URL(window.location);
    
    // Set filter parameters
    if (filters.id) url.searchParams.set('filter_id', filters.id);
    else url.searchParams.delete('filter_id');
    
    if (filters.country) url.searchParams.set('filter_country', filters.country);
    else url.searchParams.delete('filter_country');
    
    if (filters.currency) url.searchParams.set('filter_currency', filters.currency);
    else url.searchParams.delete('filter_currency');
    
    if (filters.rate) url.searchParams.set('filter_rate', filters.rate);
    else url.searchParams.delete('filter_rate');
    
    if (filters.date) url.searchParams.set('filter_date', filters.date);
    else url.searchParams.delete('filter_date');
    
    // Reset to page 1 when filtering
    url.searchParams.set('page', '1');
    
    // Navigate to filtered URL
    window.location.href = url.toString();
}

function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('selectAll');
    
    if (!selectAll || checkboxes.length === 0) {
        return;
    }
    
    // Check if all checkboxes on current page are checked
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const someChecked = Array.from(checkboxes).some(cb => cb.checked);
    
    selectAll.checked = allChecked;
    selectAll.indeterminate = !allChecked && someChecked;
}

document.getElementById('selectAll').addEventListener('change', function() {
    const isChecked = this.checked;
    const checkboxes = document.querySelectorAll('.row-checkbox');
    
    // Update all checkboxes on current page
    checkboxes.forEach(checkbox => {
        if (checkbox.checked !== isChecked) {
            checkbox.checked = isChecked;
            // Trigger the update for each checkbox
            const id = checkbox.getAttribute('data-id');
            if (id) {
                updateSelection(checkbox, id);
            }
        }
    });
});

function clearAllSelections() {
    if (confirm('Clear all selections across all pages?')) {
        clearAllSelectionsSilent();
        alert('All selections cleared.');
        location.reload();
    }
}

function clearAllSelectionsSilent() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_selection&clear_all=true'
    })
    .catch(error => console.error('Error clearing selections:', error));
}

function deleteSelected() {
    // Get count from session (across all pages)
    const selectedCount = <?php echo count($_SESSION['selected_currencies']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one currency rate to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete ' + selectedCount + ' selected currency rate(s) across all pages? This action cannot be undone.')) {
        // Pass all selected IDs from session
        fetch('../data/delete_currencies.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: <?php echo json_encode($_SESSION['selected_currencies']); ?> })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message || 'Currency rates deleted successfully');
                // Clear selections after deletion
                clearAllSelectionsSilent();
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete currency rates'));
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Request failed: ' + error.message);
        });
    }
}

function exportSelected(format) {
    const selectedCount = <?php echo count($_SESSION['selected_currencies']); ?>;
    
    if (selectedCount === 0) {
        alert('Please select at least one currency rate to export.');
        return;
    }
    
    // Create a form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../data/export_currencies.php';
    
    // Add format parameter
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'export_format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    // Add sort parameters
    const sortInput = document.createElement('input');
    sortInput.type = 'hidden';
    sortInput.name = 'sort';
    sortInput.value = '<?php echo $sort_column; ?>';
    form.appendChild(sortInput);
    
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = 'order';
    orderInput.value = '<?php echo $sort_order; ?>';
    form.appendChild(orderInput);
    
    // Add selected IDs from session
    <?php foreach ($_SESSION['selected_currencies'] as $id): ?>
        const idInput<?php echo $id; ?> = document.createElement('input');
        idInput<?php echo $id; ?>.type = 'hidden';
        idInput<?php echo $id; ?>.name = 'selected_ids[]';
        idInput<?php echo $id; ?>.value = '<?php echo $id; ?>';
        form.appendChild(idInput<?php echo $id; ?>);
    <?php endforeach; ?>
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function exportAll(format) {
    // Get current search parameters
    const searchParams = new URLSearchParams(window.location.search);
    const search = searchParams.get('search') || '';
    
    // Create a form to submit the export request for ALL records
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../data/export_currencies.php';
    
    // Add format parameter
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'export_format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    // Add export_all parameter to indicate we want all records
    const allInput = document.createElement('input');
    allInput.type = 'hidden';
    allInput.name = 'export_all';
    allInput.value = '1';
    form.appendChild(allInput);
    
    // Add sort parameters
    const sortInput = document.createElement('input');
    sortInput.type = 'hidden';
    sortInput.name = 'sort';
    sortInput.value = '<?php echo $sort_column; ?>';
    form.appendChild(sortInput);
    
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = 'order';
    orderInput.value = '<?php echo $sort_order; ?>';
    form.appendChild(orderInput);
    
    // Add search parameter if exists
    if (search) {
        const searchInput = document.createElement('input');
        searchInput.type = 'hidden';
        searchInput.name = 'search';
        searchInput.value = search;
        form.appendChild(searchInput);
    }
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include '../admin/includes/footer.php'; ?>