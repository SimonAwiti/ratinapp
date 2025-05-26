console.log("filter3.js loaded");

function changeItemsPerPage() {
    const limit = document.getElementById('itemsPerPageSelect').value;
    window.location.href = `?page=1&limit=${limit}`;
}

function deleteSelected() {
    console.log("deleteSelected() function called!");
    try {
        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(checkbox => {
            if (!checkbox.value) {
                console.error("Checkbox with no value found:", checkbox);
            }
            return checkbox.value;
        }).filter(id => id); // Filter out any undefined/null values

        console.log("Selected IDs:", selectedIds);

        if (selectedIds.length === 0) {
            alert('Please select at least one enumerator to delete.');
            return;
        }

        if (confirm(`Are you sure you want to delete ${selectedIds.length} selected enumerator(s)?`)) {
            console.log('Proceeding with deletion of IDs:', selectedIds);

            // Create a dynamic form to submit the IDs
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_enumerators.php'; // Verify this path is correct
            
            // Add CSRF token if you're using one
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }

            // Add hidden input fields for each selected ID
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });

            // Append the form to the body and submit it
            document.body.appendChild(form);
            console.log("Form contents before submission:", form.innerHTML);
            form.submit();
        } else {
            console.log("Deletion cancelled by user.");
        }
    } catch (error) {
        console.error("Error in deleteSelected:", error);
        alert("An error occurred while attempting to delete. Check console for details.");
    }
}

function exportSelected(format) {
    const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one enumerator to export');
        return;
    }
    console.log(`Exporting to ${format}:`, selected);
    // In a real application, you'd send an AJAX request to your server to handle the export
    //  and generate the file.  The server would then return the file (e.g., via a download).
    /*
    fetch('/export_enumerators.php', {  //  Corrected path
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: selected, format: format }),
    })
    .then(response => response.blob())  // Expect a file, not JSON
    .then(blob => {
        // Create a link and simulate a click to trigger download
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `enumerators.${format}`; //  filename
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    })
    .catch(error => {
        console.error('Error:', error);
        alert(`An error occurred while exporting to ${format}`);
    });
    */
}

// Main initialization function for enumerators
function initializeEnumerators() {
    console.log("Initializing enumerators functionality...");
    
    // Use a small delay to ensure DOM is ready
    setTimeout(() => {
        console.log("Setting up enumerators event listeners...");
        
        // Main functionality elements
        const selectAllCheckbox = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.row-checkbox');
        const deleteButton = document.getElementById('deleteSelected');
        const exportExcelButton = document.getElementById('exportExcel');
        const exportPDFButton = document.getElementById('exportPDF');
        const itemsPerPageSelect = document.getElementById('itemsPerPageSelect');

        // Filtering elements
        const filterNameInput = document.getElementById('filterName');
        const filterAdmin0Input = document.getElementById('filterAdmin0');
        const filterAdmin1Input = document.getElementById('filterAdmin1');
        const filterTradepointInput = document.getElementById('filterTradepoint');
        const enumeratorTable = document.getElementById('enumeratorTable');
        const tableRows = enumeratorTable ? enumeratorTable.getElementsByTagName('tr') : [];

        // Debug: Check if elements exist
        console.log("Delete button found:", !!deleteButton);
        console.log("Checkboxes found:", checkboxes.length);
        console.log("Select all checkbox found:", !!selectAllCheckbox);

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                const currentCheckboxes = document.querySelectorAll('.row-checkbox'); // Re-query in case DOM changed
                currentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
            console.log("Select all checkbox listener added");
        }

        // Delete button event listener with debug
        if (deleteButton) {
            console.log("Adding click event listener to delete button");
            // Remove any existing listeners to avoid duplicates
            deleteButton.removeEventListener('click', deleteSelected);
            deleteButton.addEventListener('click', function(e) {
                console.log("Delete button clicked!");
                e.preventDefault();
                e.stopPropagation();
                deleteSelected();
            });
            console.log("Delete button listener added successfully");
        } else {
            console.error("Delete button not found! Check if element with ID 'deleteSelected' exists");
        }

        // Export functionality
        if (exportExcelButton) {
            exportExcelButton.removeEventListener('click', () => exportSelected('excel'));
            exportExcelButton.addEventListener('click', (e) => {
                e.preventDefault();
                exportSelected('excel');
            });
            console.log("Excel export listener added");
        }
        
        if (exportPDFButton) {
            exportPDFButton.removeEventListener('click', () => exportSelected('pdf'));
            exportPDFButton.addEventListener('click', (e) => {
                e.preventDefault();
                exportSelected('pdf');
            });
            console.log("PDF export listener added");
        }
        
        // Items per page functionality
        if (itemsPerPageSelect) {
            itemsPerPageSelect.addEventListener('change', changeItemsPerPage);
            console.log("Items per page listener added");
        }

        // Filtering functionality
        function filterTable() {
            const nameFilter = filterNameInput ? filterNameInput.value.toLowerCase() : '';
            const admin0Filter = filterAdmin0Input ? filterAdmin0Input.value.toLowerCase() : '';
            const admin1Filter = filterAdmin1Input ? filterAdmin1Input.value.toLowerCase() : '';
            const tradepointFilter = filterTradepointInput ? filterTradepointInput.value.toLowerCase() : '';

            for (let i = 0; i < tableRows.length; i++) {
                const row = tableRows[i];
                
                // Get cell content safely
                const name = row.cells[1] ? row.cells[1].textContent.toLowerCase() : '';
                const admin0 = row.cells[2] ? row.cells[2].textContent.toLowerCase() : '';
                const admin1 = row.cells[3] ? row.cells[3].textContent.toLowerCase() : '';
                const tradepoint = row.cells[4] ? row.cells[4].textContent.toLowerCase() : '';

                if (
                    name.includes(nameFilter) &&
                    admin0.includes(admin0Filter) &&
                    admin1.includes(admin1Filter) &&
                    tradepoint.includes(tradepointFilter)
                ) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        // Add event listeners for filtering
        if (filterNameInput) {
            filterNameInput.addEventListener('input', filterTable);
            console.log("Name filter listener added");
        }
        if (filterAdmin0Input) {
            filterAdmin0Input.addEventListener('input', filterTable);
            console.log("Admin0 filter listener added");
        }
        if (filterAdmin1Input) {
            filterAdmin1Input.addEventListener('input', filterTable);
            console.log("Admin1 filter listener added");
        }
        if (filterTradepointInput) {
            filterTradepointInput.addEventListener('input', filterTable);
            console.log("Tradepoint filter listener added");
        }
        
        console.log("Enumerators initialization complete!");
        
    }, 100); // Small delay to ensure DOM is ready
}

// Auto-initialize if DOM is already loaded, or wait for it
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEnumerators);
} else {
    // DOM is already loaded, initialize immediately
    initializeEnumerators();
}