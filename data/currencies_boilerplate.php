<?php
// base/currencyrates_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

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
</style>

<div class="text-wrapper-8"><h3>Currency Rates Management</h3></div>
<p class="p">Manage Currency Exchange Rate Data</p>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_currency.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
                <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New
            </a>
            <button class="delete-btn">
                <i class="fa fa-trash" style="margin-right: 6px;"></i> Delete
            </button>
            <button>
                <i class="fa fa-file-export" style="margin-right: 6px;"></i> Export
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeCurrencies();
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Currency Rates');
    }
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
</script>

<?php include '../admin/includes/footer.php'; ?>