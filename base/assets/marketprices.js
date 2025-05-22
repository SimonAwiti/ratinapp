// marketprices.js

// Moved the confirmAction function outside of initializeMarketPrices
// as it's a utility function that doesn't strictly need to be "initialized"
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
                // If the server response is not OK, try to read the error message
                return response.json().catch(() => {
                    throw new Error(`HTTP error! status: ${response.status} - No JSON response.`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Items ' + action + ' successfully.');
                // Instead of full page reload, you might want to update the table dynamically
                window.location.reload();
            } else {
                alert('Failed to ' + action + ' items: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(error => {
            console.error('Fetch error:', error); // Log the actual error for debugging
            alert('An error occurred while ' + action + ' items: ' + error.message);
        });
    }
}


function initializeMarketPrices() {
    // Only initialize if the elements actually exist in the DOM
    const selectAllCheckbox = document.getElementById('select-all');
    const groupCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-group-key]');

    // If these elements aren't present, it means marketprices_boilerplate.php isn't loaded yet
    // or has been removed. So, we exit.
    if (!selectAllCheckbox || groupCheckboxes.length === 0) {
        // console.log("Market prices elements not found, skipping initialization.");
        return;
    }

    let selectedPriceIdsForAction = new Set();

    function updateSelectedIdsForAction() {
        selectedPriceIdsForAction.clear();
        groupCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                try {
                    const groupPriceIds = JSON.parse(checkbox.getAttribute('data-price-ids'));
                    groupPriceIds.forEach(id => selectedPriceIdsForAction.add(id));
                } catch (e) {
                    console.error("Error parsing data-price-ids:", e);
                }
            }
        });
        return Array.from(selectedPriceIdsForAction);
    }

    // Ensure listeners are only added once per element if this function is called multiple times
    // This is a safety measure, but the primary solution is to call initializeMarketPrices only once.
    // We'll remove existing listeners if they exist to prevent duplicates.
    // This part is more complex to implement robustly without storing references to the listeners.
    // A simpler approach is to ensure initializeMarketPrices is only called once per content load.

    // Add event listeners (ensure they are attached to the *newly loaded* elements)
    if (selectAllCheckbox) { // Check if it exists before adding listener
        selectAllCheckbox.addEventListener('change', () => {
            groupCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectedIdsForAction();
        });
    }

    groupCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            updateSelectedIdsForAction();
            const allChecked = Array.from(groupCheckboxes).every(cb => cb.checked);
            if (selectAllCheckbox) { // Check if it exists
                selectAllCheckbox.checked = allChecked;
            }
        });
    });

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

    // Delete button (assuming this is the one with type 2 in the toolbar-left)
    const deleteButton = document.querySelector('.toolbar-left button:nth-of-type(2)');
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

// NO self-execution here. initializeMarketPrices will be called by sidebar.php