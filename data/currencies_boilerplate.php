<?php
// base/currencyrates_boilerplate.php

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

// Function to fetch currency rates data from the database
function getCurrencyRatesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                cr.id,
                cr.country,
                cr.currency_code,
                cr.exchange_rate,
                cr.effective_date
            FROM
                currencies cr
            ORDER BY
                cr.effective_date DESC, cr.country ASC
            LIMIT $limit OFFSET $offset";

    $result = $con->query($sql);
    $data = [];
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $result->free();
    } else {
        error_log("Error fetching currency rates data: " . $con->error);
    }
    return $data;
}

function getTotalCurrencyRecords($con) {
    $sql = "SELECT count(*) as total FROM currencies";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalCurrencyRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch currency rates data
$currency_rates_data = getCurrencyRatesData($con, $limit, $offset);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Function to format exchange rate
function formatExchangeRate($rate) {
    return number_format($rate, 4);
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
    }
    .toolbar-left {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .toolbar button {
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
        vertical-align: top;
    }
    table th {
        background-color: #f1f1f1;
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
    }
    .pagination .current {
        background-color: #cddc39;
    }
    select {
        padding: 6px;
        margin-left: 5px;
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
    }
    .btn-import, .btn-export {
        background-color: white;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 16px;
    }
    .btn-import:hover, .btn-export:hover {
        background-color: #f8f9fa;
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
</style>

<div class="text-wrapper-8"><h3>Currency Rates Management</h3></div>
<p class="p">Manage Currency Exchange Rate Data</p>

<?php if (isset($import_message)): ?>
    <div class="alert alert-<?= $import_status ?>">
        <?= $import_message ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_currency.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
                <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New
            </a>
            <button class="delete-btn">
                <i class="fa fa-trash" style="margin-right: 6px;"></i> Delete
            </button>
            
            <div class="dropdown">
                <button class="btn-export dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-file-export" style="margin-right: 6px;"></i> Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('csv')">
                        <i class="fas fa-file-csv" style="margin-right: 8px;"></i>Export to CSV
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSelected('pdf')">
                        <i class="fas fa-file-pdf" style="margin-right: 8px;"></i>Export to PDF
                    </a></li>
                </ul>
            </div>
            
            <button class="btn-import" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fa fa-upload" style="margin-right: 6px;"></i> Import
            </button>
            
            <button>
                <i class="fa fa-filter" style="margin-right: 6px;"></i> Filters
            </button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"/></th>
                <th>Country</th>
                <th>Currency</th>
                <th>Exchange Rate (to USD)</th>
                <th>Effective Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($currency_rates_data as $rate): ?>
                <tr>
                    <td><input type="checkbox" data-id="<?php echo $rate['id']; ?>"/></td>
                    <td><?php echo htmlspecialchars($rate['country']); ?></td>
                    <td><span class="currency-code"><?php echo htmlspecialchars($rate['currency_code']); ?></span></td>
                    <td class="exchange-rate"><?php echo formatExchangeRate($rate['exchange_rate']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($rate['effective_date'])); ?></td>
                    <td>
                        <a href="../data/edit_currency.php?id=<?= $rate['id'] ?>">
                            <button class="btn btn-sm btn-warning">
                                <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
                            </button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <div>
            Show
            <select>
                <option>10</option>
                <option>25</option>
                <option>50</option>
            </select>
            entries
        </div>
        <div>Displaying <?php echo ($offset + 1) . ' to ' . min($offset + $limit, $total_records) . ' of ' . $total_records; ?> items</div>
        <div class="pages">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="page">‹</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="page <?php echo ($page == $i) ? 'current' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="page">›</a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeCurrencies();
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Currency Rates');
    }
    
    // Show import modal if there was an error
    <?php if (isset($import_message) && $import_status === 'danger'): ?>
        var importModal = new bootstrap.Modal(document.getElementById('importModal'));
        importModal.show();
    <?php endif; ?>
});

function initializeCurrencies() {
    console.log("Initializing Currencies functionality...");

    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]');

    // Debug: Log if elements are found
    console.log("Select All checkbox:", selectAllCheckbox);
    console.log("Item checkboxes found:", itemCheckboxes.length);

    // Exit if essential elements are not found
    if (!selectAllCheckbox || itemCheckboxes.length === 0) {
        console.log("Currency elements not found, retrying in 500ms");
        setTimeout(initializeCurrencies, 500);
        return;
    }

    // Select All Functionality
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
    });

    // Setup Delete Button
    setupDeleteButton();
}

function setupDeleteButton() {
    const deleteButton = document.querySelector('.toolbar .delete-btn');
    if (deleteButton) {
        console.log("Found delete button");
        deleteButton.addEventListener('click', function() {
            const ids = getSelectedIds();
            console.log("Delete clicked, selected IDs:", ids);
            confirmDelete(ids);
        });
    }
}

function getSelectedIds() {
    const checkboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]:checked');
    return Array.from(checkboxes).map(checkbox => parseInt(checkbox.getAttribute('data-id')));
}

function confirmDelete(ids) {
    if (ids.length === 0) {
        alert('Please select items to delete.');
        return;
    }

    if (confirm(`Are you sure you want to delete ${ids.length} selected currency rate(s)? This action cannot be undone.`)) {
        fetch('../data/delete_currencies.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: ids }),
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Currency rates deleted successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to delete currency rates');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting currency rates: ' + error.message);
        });
    }
}

function exportSelected(format) {
    const checkedBoxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one currency rate to export.');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-id'));
    
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
    
    // Add selected IDs
    ids.forEach(id => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'selected_ids[]';
        idInput.value = id;
        form.appendChild(idInput);
    });
    
    // Submit the form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php include '../admin/includes/footer.php'; ?>