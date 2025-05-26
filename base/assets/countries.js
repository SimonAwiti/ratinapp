// countries.js
document.addEventListener('DOMContentLoaded', function() {
    initializeCountries();
});

function initializeCountries() {
    console.log("Initializing Countries functionality...");

    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('table tbody input[type="checkbox"][data-id]');

    // Debug: Log if elements are found
    console.log("Select All checkbox:", selectAllCheckbox);
    console.log("Item checkboxes found:", itemCheckboxes.length);

    // Exit if essential elements are not found
    if (!selectAllCheckbox || itemCheckboxes.length === 0) {
        console.log("Country elements not found, retrying in 500ms");
        setTimeout(initializeCountries, 500);
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

    if (confirm(`Are you sure you want to delete ${ids.length} selected country(ies)? This action cannot be undone.`)) {
        fetch('../data/delete_countries.php', {
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
                alert('Countries deleted successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to delete countries');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting countries: ' + error.message);
        });
    }
}