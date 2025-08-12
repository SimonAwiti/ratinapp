<?php
// base/marketprices_boilerplate.php

// Include the configuration file first
include '../admin/includes/config.php';

// Include the shared header with the sidebar and initial HTML
include '../admin/includes/header.php';

// Function to fetch prices data from the database
function getPricesData($con, $limit = 10, $offset = 0) {
    $sql = "SELECT
                p.id,
                p.market,
                p.commodity, -- This is now the commodity ID from market_prices
                c.commodity_name, -- This will fetch the name from the commodities table
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source
            FROM
                market_prices p
            LEFT JOIN
                commodities c ON p.commodity = c.id -- Correct join: p.commodity (ID) = c.id
            ORDER BY
                p.date_posted DESC
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
        error_log("Error fetching prices data: " . $con->error); // Log error instead of echoing
    }
    return $data;
}

function getTotalPriceRecords($con){
    $sql = "SELECT count(*) as total FROM market_prices";
    $result = $con->query($sql);
     if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
     }
     return 0;
}

//Get total number of records
$total_records = getTotalPriceRecords($con);

// Set pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch prices data
$prices_data = getPricesData($con, $limit, $offset);

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
        case 'unpublished': // Add this new status display
            return '<span class="status-dot status-unpublished"></span> Unpublished';
        default:
            return '<span class="status-dot"></span> Unknown';
    }
}

/**
 * Calculates the Day-over-Day (DoD) price change.
 *
 * @param float $currentPrice The current day's price.
 * @param int $commodityId The commodity ID (now correctly passed as an ID).
 * @param string $market The market.
 * @param string $priceType The price type (e.g., 'Wholesale', 'Retail').
 * @param mysqli $con The database connection.
 *
 * @return string The DoD change as a percentage (e.g., '2.04%') or 'N/A' if data is insufficient.
 */
function calculateDoDChange($currentPrice, $commodityId, $market, $priceType, $con) {
    // Get yesterday's date
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Query to fetch yesterday's price for the same commodity ID, market, and price type
    $sql = "SELECT Price FROM market_prices
            WHERE commodity = " . (int)$commodityId . " -- Use commodity ID from market_prices
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) = '$yesterday'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $yesterdayData = $result->fetch_assoc();
        $yesterdayPrice = $yesterdayData['Price'];
        if($yesterdayPrice != 0){
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2) . '%';
        }
        else{
            return 'N/A';
        }

    } else {
        return 'N/A'; // Not Available
    }
}

/**
 * Calculates the Day-over-Month (DoM) price change.
 *
 * @param float $currentPrice The current day's price.
 * @param int $commodityId The commodity ID (now correctly passed as an ID).
 * @param string $market The market.
 * @param string $priceType.
 * @param mysqli $con The database connection.
 *
 * @return string The DoM change as a percentage or 'N/A' if data is insufficient.
 */
function calculateDoMChange($currentPrice, $commodityId, $market, $priceType, $con) {
    // Get the date range for the previous month
    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));

    // Query to get the average price for the previous month using commodity ID
    $sql = "SELECT AVG(Price) as avg_price FROM market_prices
            WHERE commodity = " . (int)$commodityId . " -- Use commodity ID from market_prices
            AND market = '" . $con->real_escape_string($market) . "'
            AND price_type = '" . $con->real_escape_string($priceType) . "'
            AND DATE(date_posted) BETWEEN '$firstDayOfLastMonth' AND '$lastDayOfLastMonth'";

    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        $monthData = $result->fetch_assoc();
        $averagePrice = $monthData['avg_price'];
        if($averagePrice != 0){
             $change = (($currentPrice - $averagePrice) / $averagePrice) * 100;
             return round($change, 2) . '%';
        }
        else{
            return 'N/A';
        }
    } else {
        return 'N/A'; // Not Available
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
    h2 {
        margin: 0 0 5px;
    }
    p.subtitle {
        color: #777;
        font-size: 14px;
        margin: 0 0 20px;
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
      background-color: rgba(180, 80, 50, 1); /* Keep this color, it's distinct from green approve */
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
    .status-unpublished { /* New status color */
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
    
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 50%;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .close-modal {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close-modal:hover {
        color: black;
    }
    
    /* Progress bar styles */
    #uploadProgress {
        margin-top: 20px;
        display: none;
    }
    
    #progressBar {
        height: 20px;
        width: 0%;
        background-color: rgba(180, 80, 50, 1);
        border-radius: 4px;
        text-align: center;
        color: white;
        line-height: 20px;
        transition: width 0.3s;
    }
    
    /* Results box styles */
    #uploadResults {
        margin-top: 20px;
        display: none;
    }
    
    #resultsContent {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 4px;
    }
    
    /* Form input styles */
    #bulkUploadForm input[type="file"],
    #bulkUploadForm input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    #bulkUploadForm label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
</style>

<div class="text-wrapper-8"><h3>Market Prices Management</h3></div>
<p class="p">Manage everything related to Market Prices data</p>

<div class="container">
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="../data/add_marketprices.php" class="primary" style="display: inline-block; width: 302px; height: 52px; margin-right: 15px; text-align: center; line-height: 52px; text-decoration: none; color: white; background-color:rgba(180, 80, 50, 1); border: none; border-radius: 5px; cursor: pointer;">
                <i class="fa fa-plus" style="margin-right: 6px;"></i> Add New
            </a>
            <button class="bulk-upload-btn">
                <i class="fa fa-upload" style="margin-right: 6px;"></i> Bulk Upload
            </button>
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
                <th>Market</th>
                <th>Commodity</th>
                <th>Date</th>
                <th>Type</th>
                <th>Price($)</th>
                <th>Day Change(%)</th>
                <th>Month Change(%)</th>
                <th>Status</th>
                <th>Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $grouped_data = [];
            foreach ($prices_data as $price) {
                $date = date('Y-m-d', strtotime($price['date_posted']));
                // Group by commodity ID for consistency
                $group_key = $date . '_' . $price['market'] . '_' . $price['commodity'];
                $grouped_data[$group_key][] = $price;
            }

            foreach ($grouped_data as $group_key => $prices_in_group):
                $first_row = true;
                // Collect all individual price IDs for this group
                $group_price_ids = array_column($prices_in_group, 'id');
                $group_price_ids_json = htmlspecialchars(json_encode($group_price_ids));

                foreach($prices_in_group as $price):
                    // Pass 'commodity' (which is now the ID) to the functions
                    $day_change = calculateDoDChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                    $month_change = calculateDoMChange($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $con);
                ?>
                <tr>
                    <?php if ($first_row): ?>
                        <td rowspan="<?php echo count($prices_in_group); ?>">
                            <input type="checkbox"
                                   data-group-key="<?php echo $group_key; ?>"
                                   data-price-ids="<?php echo $group_price_ids_json; ?>"
                            />
                        </td>
                        <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['market']); ?></td>
                        <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo htmlspecialchars($price['commodity_name']); ?></td> <td rowspan="<?php echo count($prices_in_group); ?>"><?php echo date('Y-m-d', strtotime($price['date_posted'])); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($price['price_type']); ?></td>
                    <td><?php echo htmlspecialchars($price['Price']); ?></td>
                    <td><?php echo $day_change; ?></td>
                    <td><?php echo $month_change; ?></td>
                    <td><?php echo getStatusDisplay($price['status']); ?></td>
                    <td><?php echo htmlspecialchars($price['data_source']); ?></td>
                    <td>
                        <a href="../data/edit_marketprice.php?id=<?= $price['id'] ?>">
                            <button class="btn btn-sm btn-warning">
                                <img src="../base/img/edit.svg" alt="Edit" style="width: 20px; height: 20px; margin-right: 5px;">
                            </button>
                        </a>
                    </td>
                </tr>
                <?php
                $first_row = false;
                endforeach;
            endforeach;
            ?>
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

<!-- Bulk Upload Modal -->
<div id="bulkUploadModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Bulk Upload Market Prices</h3>
        <p>Upload a CSV file containing market price data. <a href="#" id="downloadTemplate" style="color: rgba(180, 80, 50, 1);">Download CSV template</a></p>
        
        <form id="bulkUploadForm" enctype="multipart/form-data" style="margin-top: 20px;">
            <div style="margin-bottom: 15px;">
                <label for="bulkFile">CSV File:</label>
                <input type="file" id="bulkFile" name="bulkFile" accept=".csv" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="dataSource">Data Source:</label>
                <input type="text" id="dataSource" name="dataSource" placeholder="Source of this data">
            </div>
            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="close-modal" style="padding: 10px 20px; background-color: #eee; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 10px 20px; background-color: rgba(180, 80, 50, 1); color: white; border: none; border-radius: 4px; cursor: pointer;">Upload</button>
            </div>
        </form>
        
        <div id="uploadProgress" style="margin-top: 20px; display: none;">
            <div style="width: 100%; background-color: #f1f1f1; border-radius: 4px;">
                <div id="progressBar" style="height: 20px; width: 0%; background-color: rgba(180, 80, 50, 1); border-radius: 4px; text-align: center; color: white; line-height: 20px;">0%</div>
            </div>
            <p id="progressText" style="text-align: center; margin-top: 5px;">Processing...</p>
        </div>
        
        <div id="uploadResults" style="margin-top: 20px; display: none;">
            <h4>Upload Results:</h4>
            <div id="resultsContent" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;"></div>
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
 * Initializes all event listeners for the market prices table.
 * This function should be called *after* the market prices HTML content is loaded into the DOM.
 */
function initializeMarketPrices() {
    console.log("Initializing Market Prices functionality...");

    const selectAllCheckbox = document.getElementById('select-all');
    // Select all group checkboxes based on their data attribute
    const groupCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-group-key]');

    // Exit if essential elements are not found, meaning the content isn't loaded yet.
    if (!selectAllCheckbox || groupCheckboxes.length === 0) {
        console.log("Market prices elements (checkboxes) not found, skipping initialization.");
        return;
    }

    // A Set to store unique price IDs for the currently selected rows
    let selectedPriceIdsForAction = new Set();

    /**
     * Updates the `selectedPriceIdsForAction` Set based on currently checked group checkboxes.
     * @returns {Array<number>} An array of the unique selected price IDs.
     */
    function updateSelectedIdsForAction() {
        selectedPriceIdsForAction.clear(); // Clear previous selections
        groupCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                try {
                    // Parse the JSON string from data-price-ids attribute
                    const groupPriceIds = JSON.parse(checkbox.getAttribute('data-price-ids'));
                    groupPriceIds.forEach(id => selectedPriceIdsForAction.add(id));
                } catch (e) {
                    console.error("Error parsing data-price-ids for checkbox:", checkbox, e);
                }
            }
        });
        return Array.from(selectedPriceIdsForAction); // Convert Set to Array
    }

    // Event listener for the "Select All" checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            const isChecked = selectAllCheckbox.checked;
            groupCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked; // Set all group checkboxes to match "Select All"
            });
            updateSelectedIdsForAction(); // Update the selected IDs
        });
    }

    // Event listener for individual group checkboxes
    groupCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            updateSelectedIdsForAction(); // Update selected IDs on individual checkbox change
            // Check if all group checkboxes are checked to update "Select All" checkbox state
            const allChecked = Array.from(groupCheckboxes).every(cb => cb.checked);
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

    // Delete button (assuming it now has the class 'delete-btn')
    const deleteButton = document.querySelector('.toolbar .delete-btn');
    if (deleteButton) {
        deleteButton.addEventListener('click', () => {
            const ids = updateSelectedIdsForAction();
            confirmAction('delete', ids); // Call confirmAction with 'delete'
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

// Bulk Upload functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize market prices as before
    initializeMarketPrices();
    
    // Bulk Upload Modal Handling
    const modal = document.getElementById('bulkUploadModal');
    const btn = document.querySelector('.bulk-upload-btn');
    const closeButtons = document.querySelectorAll('.close-modal');
    const downloadTemplate = document.getElementById('downloadTemplate');
    const bulkUploadForm = document.getElementById('bulkUploadForm');
    
    if (btn) {
        btn.addEventListener('click', function() {
            modal.style.display = 'block';
        });
    }
    
    closeButtons.forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            // Reset form and results when closing
            bulkUploadForm.reset();
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('uploadResults').style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
            // Reset form and results when closing
            bulkUploadForm.reset();
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('uploadResults').style.display = 'none';
        }
    });
    
    // Download CSV template
    if (downloadTemplate) {
        downloadTemplate.addEventListener('click', function(e) {
            e.preventDefault();
            
            // CSV template content
            const csvContent = "market_id,market,commodity_id,commodity_name,wholesale_price,retail_price,date_posted\n" +
                              "1,Nairobi Market,1,Maize,50.00,55.00," + new Date().toISOString().split('T')[0] + "\n" +
                              "2,Mombasa Market,2,Rice,80.00,85.00," + new Date().toISOString().split('T')[0];
            
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'market_prices_template.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }
    
    // Handle bulk upload form submission
    if (bulkUploadForm) {
        bulkUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('bulkFile');
            const dataSource = document.getElementById('dataSource').value;
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const uploadResults = document.getElementById('uploadResults');
            const resultsContent = document.getElementById('resultsContent');
            
            if (!fileInput.files.length) {
                alert('Please select a CSV file to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('data_source', dataSource);
            
            // Show progress bar
            document.getElementById('uploadProgress').style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressText.textContent = 'Processing...';
            
            // Hide previous results
            uploadResults.style.display = 'none';
            
            fetch('../data/bulk_upload_marketprices.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Update progress bar to 100%
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                progressText.textContent = 'Upload complete!';
                
                // Show results
                uploadResults.style.display = 'block';
                
                if (data.success) {
                    resultsContent.innerHTML = `
                        <p><strong>Success:</strong> ${data.message}</p>
                        <p>Records processed: ${data.processed}</p>
                        <p>Records inserted: ${data.inserted}</p>
                        ${data.errors && data.errors.length ? `
                            <p><strong>Errors:</strong></p>
                            <ul>
                                ${data.errors.map(error => `<li>Row ${error.row}: ${error.message}</li>`).join('')}
                            </ul>
                        ` : ''}
                    `;
                    
                    // Reload the page after 3 seconds to show new data
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    resultsContent.innerHTML = `
                        <p><strong>Error:</strong> ${data.message}</p>
                        ${data.errors && data.errors.length ? `
                            <p><strong>Details:</strong></p>
                            <ul>
                                ${data.errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        ` : ''}
                    `;
                }
            })
            .catch(error => {
                console.error('Error during bulk upload:', error);
                progressText.textContent = 'Upload failed!';
                
                // Show error in results
                uploadResults.style.display = 'block';
                resultsContent.innerHTML = `<p><strong>Error:</strong> ${error.message}</p>`;
            });
        });
    }
    
    // Update breadcrumb if the function exists
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb('Base', 'Market Prices');
    }
});
</script>

<?php include '../admin/includes/footer.php'; ?>