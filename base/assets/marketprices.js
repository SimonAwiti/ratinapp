// marketprices.js

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

// IMPORTANT: This script no longer calls initializeMarketPrices() itself.
// It relies on sidebar.php's loadContent function to call initializeMarketPrices()
// once the marketprices_boilerplate.php content and this script are both loaded into the DOM.