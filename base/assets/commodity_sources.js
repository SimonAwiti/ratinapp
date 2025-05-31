// Function to filter the table based on input values for Commodity Sources
function filterDataSourcesTable() {
    const filterId = document.getElementById('filterId').value.toUpperCase();
    const filterAdmin0 = document.getElementById('filterAdmin0').value.toUpperCase();
    const filterAdmin1 = document.getElementById('filterAdmin1').value.toUpperCase();
    const filterCreatedAt = document.getElementById('filterCreatedAt').value.toUpperCase(); // For date filtering

    const rows = document.querySelectorAll('#commoditySourceTable tr'); // Select rows from the specific table

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) { // Ensure it's a data row, not a header/filter row
            const id = cells[1].textContent.toUpperCase(); // Column 1 for ID
            const admin0 = cells[2].textContent.toUpperCase(); // Column 2 for Admin-0
            const admin1 = cells[3].textContent.toUpperCase(); // Column 3 for Admin-1
            const createdAt = cells[4].textContent.toUpperCase(); // Column 4 for Created At

            const matchId = id.includes(filterId);
            const matchAdmin0 = admin0.includes(filterAdmin0);
            const matchAdmin1 = admin1.includes(filterAdmin1);
            const matchCreatedAt = createdAt.includes(filterCreatedAt);

            if (matchId && matchAdmin0 && matchAdmin1 && matchCreatedAt) {
                row.style.display = ''; // Show the row
            } else {
                row.style.display = 'none'; // Hide the row
            }
        }
    });
}

// Function to initialize event listeners for the filter inputs
function initializeDataSourceFilters() {
    // Add event listeners to the filter inputs
    const filterIdInput = document.getElementById('filterId');
    if (filterIdInput) filterIdInput.addEventListener('input', filterDataSourcesTable);

    const filterAdmin0Input = document.getElementById('filterAdmin0');
    if (filterAdmin0Input) filterAdmin0Input.addEventListener('input', filterDataSourcesTable);
    
    const filterAdmin1Input = document.getElementById('filterAdmin1');
    if (filterAdmin1Input) filterAdmin1Input.addEventListener('input', filterDataSourcesTable);

    const filterCreatedAtInput = document.getElementById('filterCreatedAt');
    if (filterCreatedAtInput) filterCreatedAtInput.addEventListener('input', filterDataSourcesTable);

    // Also call filter initially in case there are pre-filled values
    filterDataSourcesTable();
}


// --- Existing functions adapted for Data Sources ---

// Function to update items per page (already in dashboard.php but good to have here if it's externalized)
function updateItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', 1); // Reset to first page
    window.location.href = url.toString();
}

// Select all functionality (re-uses existing logic)
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#commoditySourceTable .row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Initialize filters on page load
    initializeDataSourceFilters();
});


// Delete selected function (adapted for commodity sources with AJAX)
function deleteSelectedSources() {
    const selected = document.querySelectorAll('#commoditySourceTable .row-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select items to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selected.length} selected commodity source(s)? This action cannot be undone.`)) {
        const idsToDelete = Array.from(selected).map(cb => cb.value);
        
        fetch('delete_commodity_sources.php', { // Ensure this path is correct
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: idsToDelete })
        })
        .then(response => {
            if (!response.ok) {
                // If response is not OK, throw an error to be caught by .catch()
                return response.json().then(errorData => {
                    throw new Error(errorData.message || 'Server error occurred during deletion.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message || 'Selected commodity sources deleted successfully!');
                window.location.reload(); // Reload the page to reflect changes
            } else {
                alert(data.message || 'Failed to delete commodity sources.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting commodity sources: ' + error.message);
        });
    }
}

// Export selected function (placeholder, adapted for commodity sources)
function exportSelectedSources(format) {
    const selected = document.querySelectorAll('#commoditySourceTable .row-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select items to export.');
        return;
    }
    
    const idsToExport = Array.from(selected).map(cb => cb.value);
    console.log(`Exporting ${selected.length} commodity sources to ${format}:`, idsToExport);
    
    // In a real application, you'd send an AJAX request or redirect to a backend script
    // that generates the Excel/PDF based on the selected IDs.
    // Example: window.location.href = `generate_sources_export.php?format=${format}&ids=${idsToExport.join(',')}`;
    alert(`Export functionality for ${format} is a placeholder. IDs to export: ${idsToExport.join(',')}`);
}

// Helper function to get selected IDs (re-uses existing logic)
function getSelectedIds() {
    return [...document.querySelectorAll("#commoditySourceTable .row-checkbox:checked")].map(checkbox => checkbox.value);
}