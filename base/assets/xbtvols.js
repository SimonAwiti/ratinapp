// xbtvols.js - Updated version

document.addEventListener('DOMContentLoaded', function() {
    initializeXBTVolumes();
});

function initializeXBTVolumes() {
    console.log("Initializing XBT Volumes functionality...");

    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]');

    // Debug: Log if elements are found
    console.log("Select All checkbox:", selectAllCheckbox);
    console.log("Item checkboxes found:", itemCheckboxes.length);

    // Exit if essential elements are not found
    if (!selectAllCheckbox || itemCheckboxes.length === 0) {
        console.log("XBT volumes elements not found, retrying in 500ms");
        setTimeout(initializeXBTVolumes, 500); // Retry after short delay
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
        ? '../data/check_xbt_status.php' 
        : '../data/check_xbt_status_for_unpublish.php';

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
        fetch('../data/update_xbt_status.php', {
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