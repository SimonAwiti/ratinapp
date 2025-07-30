<?php
// base/xbtvolumes_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Function to fetch XBT volumes data from the database
function getXBTVolumesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                x.id,
                b.name AS border_name,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                x.volume,
                x.source,
                x.destination,
                x.date_posted,
                x.status,
                ds.data_source_name AS data_source
            FROM
                xbt_volumes x
            LEFT JOIN
                border_points b ON x.border_id = b.id
            LEFT JOIN
                commodities c ON x.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON x.data_source_id = ds.id
            ORDER BY
                x.date_posted DESC
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
        error_log("Error fetching XBT volumes data: " . $con->error);
    }
    return $data;
}

function getTotalXBTVolumeRecords($con) {
    $sql = "SELECT count(*) as total FROM xbt_volumes";
    $result = $con->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

// Get total number of records
$total_records = getTotalXBTVolumeRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch XBT volumes data
$xbt_volumes_data = getXBTVolumesData($con, $limit, $offset);

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
</style>

<div class="text-wrapper-8"><h3>XBT Volumes Management</h3></div>
<p class="p">Manage Cross Border Trade Volume Data</p>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_xbtvol.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
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
                <th>Border Point</th>
                <th>Commodity</th>
                <th>Volume (MT)</th>
                <th>Source</th>
                <th>Destination</th>
                <th>Date</th>
                <th>Status</th>
                <th>Data Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($xbt_volumes_data as $volume): ?>
                <tr>
                    <td><input type="checkbox" data-id="<?php echo $volume['id']; ?>"/></td>
                    <td><?php echo htmlspecialchars($volume['border_name']); ?></td>
                    <td><?php echo htmlspecialchars($volume['commodity_display']); ?></td>
                    <td><?php echo htmlspecialchars($volume['volume']); ?></td>
                    <td><?php echo htmlspecialchars($volume['source']); ?></td>
                    <td><?php echo htmlspecialchars($volume['destination']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($volume['date_posted'])); ?></td>
                    <td><?php echo getStatusDisplay($volume['status']); ?></td>
                    <td><?php echo htmlspecialchars($volume['data_source']); ?></td>
                    <td>
                        <a href="../data/edit_xbt_volume.php?id=<?= $volume['id'] ?>">
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
/**
 * Displays a confirmation dialog and sends a request to update item status or delete items.
 *
 * @param {string} action - The action to perform ('approve', 'publish', 'unpublish', 'delete').
 * @param {Array<number>} ids - An array of item IDs to apply the action to.
 */
function confirmAction(action, ids) {
    if (ids.length === 0) {
        alert('Please select items to ' + action + '.');
        return;
    }

    let message = 'Are you sure you want to ' + action + ' these items?';
    if (confirm(message)) {
        fetch('../data/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                ids: ids,
            }),
        })
        .then(response => {
            if (!response.ok) {
                // Attempt to parse JSON error message from server response
                return response.json().catch(() => {
                    // If JSON parsing fails, throw a generic error with status
                    throw new Error(`HTTP error! status: ${response.status} - No JSON response from server.`);
                });
            }
            return response.json(); // Parse the successful JSON response
        })
        .then(data => {
            if (data.success) {
                alert('Items ' + action + ' successfully.');
                // Reload the page to reflect the changes
                window.location.reload();
            } else {
                // Display the error message from the server
                alert('Failed to ' + action + ' items: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(error => {
            // Catch network errors or errors from the .then() blocks
            console.error('Fetch error during ' + action + ':', error);
            alert('An error occurred while ' + action + ' items: ' + error.message);
        });
    }
}

/**
 * Initializes all event listeners for the XBT volumes table.
 * This function should be called *after* the XBT volumes HTML content is loaded into the DOM.
 */
function initializeXBTVolumes() {
    console.log("Initializing XBT Volumes functionality...");

    const selectAllCheckbox = document.getElementById('select-all');
    const rowCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]');

    // Exit if essential elements are not found, meaning the content isn't loaded yet.
    if (!selectAllCheckbox || rowCheckboxes.length === 0) {
        console.log("XBT volumes elements (checkboxes) not found, skipping initialization.");
        return;
    }

    // A Set to store unique volume IDs for the currently selected rows
    let selectedVolumeIdsForAction = new Set();

    /**
     * Updates the `selectedVolumeIdsForAction` Set based on currently checked checkboxes.
     * @returns {Array<number>} An array of the unique selected volume IDs.
     */
    function updateSelectedIdsForAction() {
        selectedVolumeIdsForAction.clear(); // Clear previous selections
        rowCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedVolumeIdsForAction.add(parseInt(checkbox.getAttribute('data-id')));
            }
        });
        return Array.from(selectedVolumeIdsForAction); // Convert Set to Array
    }

    // Event listener for the "Select All" checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            const isChecked = selectAllCheckbox.checked;
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked; // Set all row checkboxes to match "Select All"
            });
            updateSelectedIdsForAction(); // Update the selected IDs
        });
    }

    // Event listener for individual row checkboxes
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            updateSelectedIdsForAction(); // Update selected IDs on individual checkbox change
            // Check if all row checkboxes are checked to update "Select All" checkbox state
            const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
            }
        });
    });

    // --- Button Event Listeners ---

    // Approve button
    const approveButton = document.querySelector('.toolbar .approve');
    if (approveButton) {
        approveButton.addEventListener('click', () => {
            const ids = updateSelectedIdsForAction();
            confirmAction('approve', ids);
        });
    }

    // Publish button
    const publishButton = document.querySelector('.toolbar-right .primary');
    if (publishButton) {
        publishButton.addEventListener('click', () => {
            const ids = updateSelectedIdsForAction();
            if (ids.length === 0) {
                alert('Please select items to publish.');
                return;
            }

            // First, check if all selected items are approved before publishing
            fetch('../data/check_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: ids }),
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().catch(() => {
                        throw new Error(`HTTP error checking status! status: ${response.status} - No JSON response.`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.allApproved) {
                    confirmAction('publish', ids);
                } else {
                    alert('Cannot publish. All selected items must be approved first. ' + (data.message || ''));
                }
            })
            .catch(error => {
                console.error('Fetch error checking approval status:', error);
                alert('An error occurred while checking approval status: ' + error.message);
            });
        });
    }

    // Delete button
    const deleteButton = document.querySelector('.toolbar .delete-btn');
    if (deleteButton) {
        deleteButton.addEventListener('click', () => {
            const ids = updateSelectedIdsForAction();
            confirmAction('delete', ids);
        });
    }

    // Unpublish button
    const unpublishButton = document.querySelector('.toolbar .unpublish');
    if (unpublishButton) {
        unpublishButton.addEventListener('click', () => {
            const ids = updateSelectedIdsForAction();
            if (ids.length === 0) {
                alert('Please select items to unpublish.');
                return;
            }

            // First, check if all selected items are currently published before unpublishing
            fetch('../data/check_status_for_unpublish.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: ids }),
            })
            .then(response => {
                if (!response.ok) {
                     return response.json().catch(() => {
                        throw new Error(`HTTP error checking unpublish status! status: ${response.status} - No JSON response.`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.allPublished) {
                    confirmAction('unpublish', ids);
                } else {
                    alert('Cannot unpublish. All selected items must currently be in "Published" status.');
                }
            })
            .catch(error => {
                console.error('Fetch error checking status for unpublish:', error);
                alert('An error occurred while checking status for unpublish: ' + error.message);
            });
        });
    }
}

// Initialize the XBT volumes functionality when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeXBTVolumes();
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'XBT Volumes');
    }
});
</script>

<?php include '../admin/includes/footer.php'; ?>