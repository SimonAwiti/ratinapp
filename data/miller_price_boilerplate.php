<?php
// base/millerprices_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Function to fetch miller prices data from the database
function getMillerPricesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                mp.id,
                mp.town,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                mp.price_usd,
                mp.day_change,
                mp.month_change,
                mp.date_posted,
                mp.status,
                ds.data_source_name AS data_source
            FROM
                miller_prices mp
            LEFT JOIN
                commodities c ON mp.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON mp.data_source_id = ds.id
            ORDER BY
                mp.date_posted DESC
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
        error_log("Error fetching miller prices data: " . $con->error);
    }
    return $data;
}

function getTotalMillerPriceRecords($con) {
    $sql = "SELECT count(*) as total FROM miller_prices";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalMillerPriceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch miller prices data
$miller_prices_data = getMillerPricesData($con, $limit, $offset);

// Calculate total pages
$total_pages = ceil($total_records / $limit);

// Function to get status display
function getStatusDisplay($status) {
    switch ($status) {
        case 'pending':
            return '<span class="status-dot status-pending"></span> Pending';
        case 'published':
            return '<span class="status-dot status-published"></span> Published';
        case 'approved':
            return '<span class="status-dot status-approved"></span> Approved';
        case 'unpublished':
            return '<span class="status-dot status-unpublished"></span> Unpublished';
        default:
            return '<span class="status-dot"></span> Unknown';
    }
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
    .toolbar-left,
    .toolbar-right {
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
    .toolbar .approve {
        background-color: #218838;
        color: white;
    }
    .toolbar .unpublish {
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
    .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
    }
    .status-pending {
        background-color: orange;
    }
    .status-published {
        background-color: blue;
    }
    .status-approved {
        background-color: green;
    }
    .status-unpublished {
        background-color: grey;
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
    .positive-change {
        color: green;
    }
    .negative-change {
        color: red;
    }
</style>

<div class="text-wrapper-8"><h3>Miller Prices Management</h3></div>
<p class="p">Manage Miller Price Data</p>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_miller_prices.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
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
        <div class="toolbar-right">
            <button class="approve">
                <i class="fa fa-check-circle" style="margin-right: 6px;"></i> Approve
            </button>
            <button class="unpublish">
                <i class="fa fa-ban" style="margin-right: 6px;"></i> Unpublish
            </button>
            <button class="primary">
                <i class="fa fa-upload" style="margin-right: 6px;"></i> Publish
            </button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"/></th>
                <th>Town</th>
                <th>Commodity</th>
                <th>Price</th>
                <th>Day Change %</th>
                <th>Month Change %</th>
                <th>Date</th>
                <th>Status</th>
                <th>Data Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($miller_prices_data as $price): ?>
                <tr>
                    <td><input type="checkbox" data-id="<?php echo $price['id']; ?>"/></td>
                    <td><?php echo htmlspecialchars($price['town']); ?></td>
                    <td><?php echo htmlspecialchars($price['commodity_display']); ?></td>
                    <td><?php echo htmlspecialchars($price['price_usd']); ?></td>
                    <td class="<?php echo ($price['day_change'] > 0) ? 'positive-change' : 'negative-change'; ?>">
                        <?php echo ($price['day_change'] !== null) ? htmlspecialchars($price['day_change']) . '%' : 'N/A'; ?>
                    </td>
                    <td class="<?php echo ($price['month_change'] > 0) ? 'positive-change' : 'negative-change'; ?>">
                        <?php echo ($price['month_change'] !== null) ? htmlspecialchars($price['month_change']) . '%' : 'N/A'; ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($price['date_posted'])); ?></td>
                    <td><?php echo getStatusDisplay($price['status']); ?></td>
                    <td><?php echo htmlspecialchars($price['data_source']); ?></td>
                    <td>
                        <a href="../data/edit_miller_price.php?id=<?= $price['id'] ?>">
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
    initializeMillerPrices();
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Miller Prices');
    }
});

function initializeMillerPrices() {
    console.log("Initializing Miller Prices functionality...");

    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]');

    // Debug: Log if elements are found
    console.log("Select All checkbox:", selectAllCheckbox);
    console.log("Item checkboxes found:", itemCheckboxes.length);

    // Exit if essential elements are not found
    if (!selectAllCheckbox || itemCheckboxes.length === 0) {
        console.log("Miller prices elements not found, retrying in 500ms");
        setTimeout(initializeMillerPrices, 500); // Retry after short delay
        return;
    }

    // --- Select All Functionality ---
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
    });

    // --- Button Event Listeners ---
    setupActionButtons();
}

function setupActionButtons() {
    // Debug: Log that we're setting up buttons
    console.log("Setting up action buttons...");

    // Approve button
    const approveButton = document.querySelector('.toolbar .approve');
    if (approveButton) {
        console.log("Found approve button");
        approveButton.addEventListener('click', function() {
            const ids = getSelectedIds();
            console.log("Approve clicked, selected IDs:", ids);
            confirmAction('approve', ids);
        });
    }

    // Publish button
    const publishButton = document.querySelector('.toolbar-right .primary:not(.approve)');
    if (publishButton) {
        console.log("Found publish button");
        publishButton.addEventListener('click', function() {
            const ids = getSelectedIds();
            console.log("Publish clicked, selected IDs:", ids);
            if (ids.length === 0) {
                alert('Please select items to publish.');
                return;
            }
            checkStatusBeforeAction(ids, 'publish');
        });
    }

    // Delete button
    const deleteButton = document.querySelector('.toolbar .delete-btn');
    if (deleteButton) {
        console.log("Found delete button");
        deleteButton.addEventListener('click', function() {
            const ids = getSelectedIds();
            console.log("Delete clicked, selected IDs:", ids);
            confirmAction('delete', ids);
        });
    }

    // Unpublish button
    const unpublishButton = document.querySelector('.toolbar .unpublish');
    if (unpublishButton) {
        console.log("Found unpublish button");
        unpublishButton.addEventListener('click', function() {
            const ids = getSelectedIds();
            console.log("Unpublish clicked, selected IDs:", ids);
            if (ids.length === 0) {
                alert('Please select items to unpublish.');
                return;
            }
            checkStatusBeforeAction(ids, 'unpublish');
        });
    }
}

function getSelectedIds() {
    const checkboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]:checked');
    return Array.from(checkboxes).map(checkbox => parseInt(checkbox.getAttribute('data-id')));
}

function checkStatusBeforeAction(ids, action) {
    const endpoint = action === 'publish' 
        ? '../data/check_miller_status.php' 
        : '../data/check_miller_status_for_unpublish.php';

    fetch(endpoint, {
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
            if ((action === 'publish' && data.allApproved) || 
                (action === 'unpublish' && data.allPublished)) {
                confirmAction(action, ids);
            } else {
                alert(action === 'publish' 
                    ? 'Cannot publish. All selected items must be approved first.'
                    : 'Cannot unpublish. All selected items must be published.');
            }
        } else {
            alert(data.message || 'Error checking status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error checking status: ' + error.message);
    });
}

function confirmAction(action, ids) {
    if (ids.length === 0) {
        alert('Please select items to ' + action + '.');
        return;
    }

    if (confirm(`Are you sure you want to ${action} ${ids.length} selected item(s)?`)) {
        fetch('../data/update_miller_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                ids: ids
            }),
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(`Items ${action}d successfully`);
                window.location.reload();
            } else {
                alert(data.message || `Failed to ${action} items`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(`Error during ${action}: ` + error.message);
        });
    }
}
</script>

<?php include '../admin/includes/footer.php'; ?>