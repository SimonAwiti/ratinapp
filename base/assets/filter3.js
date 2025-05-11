console.log("filter3.js loaded");
function changeItemsPerPage() {
    const limit = document.getElementById('itemsPerPageSelect').value;
    window.location.href = `?page=1&limit=${limit}`;
}

function deleteSelected() {
    console.log("deleteSelected() function called!");
    const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one enumerator to delete');
        return;
    }
    if (confirm(`Are you sure you want to delete ${selected.length} selected enumerator(s)?`)) {
        // Perform deletion via AJAX or form submission
        console.log('Deleting:', selected);
        fetch('delete_enumerators.php', {  // Corrected path
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: selected }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Remove deleted rows from the table
                selected.forEach(id => {
                    const row = document.querySelector(`.row-checkbox[value="${id}"]`).closest('tr');
                    if (row) {
                        row.remove();
                    }
                });
                alert(data.message);
                // Optionally, update the total count and pagination
                // updateTableSummary();
                 window.location.reload();

            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting enumerators');
        });
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

document.addEventListener('DOMContentLoaded', () => {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const deleteButton = document.getElementById('deleteSelected');
    const exportExcelButton = document.getElementById('exportExcel');
    const exportPDFButton = document.getElementById('exportPDF');
    const itemsPerPageSelect = document.getElementById('itemsPerPageSelect');


    selectAllCheckbox.addEventListener('change', function () {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    deleteButton.addEventListener('click', deleteSelected);
    exportExcelButton.addEventListener('click', () => exportSelected('excel'));
    exportPDFButton.addEventListener('click', () => exportSelected('pdf'));
    itemsPerPageSelect.addEventListener('change', changeItemsPerPage);
});



// Filtering
document.addEventListener('DOMContentLoaded', () => {
    const filterNameInput = document.getElementById('filterName');
    const filterAdmin0Input = document.getElementById('filterAdmin0');
    const filterAdmin1Input = document.getElementById('filterAdmin1');
    const filterTradepointInput = document.getElementById('filterTradepoint');
    const filterTypeInput = document.getElementById('filterType');
    const enumeratorTable = document.getElementById('enumeratorTable');
    const tableRows = enumeratorTable.getElementsByTagName('tr');

    function filterTable() {
        const nameFilter = filterNameInput.value.toLowerCase();
        const admin0Filter = filterAdmin0Input.value.toLowerCase();
        const admin1Filter = filterAdmin1Input.value.toLowerCase();
        const tradepointFilter = filterTradepointInput.value.toLowerCase();
        const typeFilter = filterTypeInput.value.toLowerCase();

        for (let i = 0; i < tableRows.length; i++) {
            const row = tableRows[i];
            if (i === 0) {
                continue; // Skip the header row.
            }
             if (i === 1) {
                continue; // Skip the filter row.
            }
            const name = row.cells[1].textContent.toLowerCase();
            const admin0 = row.cells[2].textContent.toLowerCase();
            const admin1 = row.cells[3].textContent.toLowerCase();
            const tradepoint = row.cells[4].textContent.toLowerCase();
            const type = row.cells[5].textContent.toLowerCase();

            if (
                name.includes(nameFilter) &&
                admin0.includes(admin0Filter) &&
                admin1.includes(admin1Filter) &&
                tradepoint.includes(tradepointFilter) &&
                type.includes(typeFilter)
            ) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
    filterNameInput.addEventListener('input', filterTable);
    filterAdmin0Input.addEventListener('input', filterTable);
    filterAdmin1Input.addEventListener('input', filterTable);
    filterTradepointInput.addEventListener('input', filterTable);
    filterTypeInput.addEventListener('input', filterTable);
});