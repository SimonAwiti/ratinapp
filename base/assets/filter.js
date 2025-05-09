
    // Function to filter the table based on input values
    function filterTable() {
        const filterHsCode = document.getElementById('filterHsCode').value.toUpperCase();
        const filterCategory = document.getElementById('filterCategory').value.toUpperCase();
        const filterCommodity = document.getElementById('filterCommodity').value.toUpperCase();
        const filterVariety = document.getElementById('filterVariety').value.toUpperCase();

        const rows = document.querySelectorAll('#commodityTable tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                const hsCode = cells[1].textContent.toUpperCase();
                const category = cells[2].textContent.toUpperCase();
                const commodity = cells[3].textContent.toUpperCase();
                const variety = cells[4].textContent.toUpperCase();

                const matchHsCode = hsCode.includes(filterHsCode);
                const matchCategory = category.includes(filterCategory);
                const matchCommodity = commodity.includes(filterCommodity);
                const matchVariety = variety.includes(filterVariety);

                if (matchHsCode && matchCategory && matchCommodity && matchVariety) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }

    // Add event listeners to the filter inputs
    document.getElementById('filterHsCode').addEventListener('input', filterTable);
    document.getElementById('filterCategory').addEventListener('input', filterTable);
    document.getElementById('filterCommodity').addEventListener('input', filterTable);
    document.getElementById('filterVariety').addEventListener('input', filterTable);

    // Existing functions
    function changeItemsPerPage() {
        let limit = document.getElementById("itemsPerPage").value;
        window.location.href = "?page=1&limit=" + limit;
    }

    document.getElementById("selectAll").addEventListener("change", function() {
        let checkboxes = document.querySelectorAll(".row-checkbox");
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });

    function deleteSelected() {
        let selectedIds = getSelectedIds();
        console.log("Selected IDs:", selectedIds); // Check if IDs are being retrieved correctly
        if (selectedIds.length === 0) {
            alert("Select items to delete.");
            return;
        }
        if (confirm("Delete selected items?")) {
            console.log("Confirmation: Yes"); // Check if confirmation is successful
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_commodity.php';
            console.log("Form created:", form); // Check if form is created
            console.log("Form action:", form.action); // Check the form's action
    
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'ids[]';
            selectedIds.forEach(id => {
                const newInput = idsInput.cloneNode();
                newInput.value = id;
                form.appendChild(newInput);
                console.log("Added ID to form:", id, newInput); // Inspect the input element
            });
    
            console.log("Form children before append:", form.children); // Check form contents
    
            document.body.appendChild(form);
            console.log("Form appended to body:", form); // Check if form is in the DOM
    
            console.log("Attempting to submit form:", form);
            form.submit();
            console.log("Form submitted (maybe?)"); // See if this log appears
        } else {
            console.log("Confirmation: No");
        }
    }

    function exportSelected(type) {
        let selectedIds = getSelectedIds();
        if (selectedIds.length === 0) {
            alert("Select items to export.");
            return;
        }
        console.log(`Exporting ${type}:`, selectedIds);
    }

    function getSelectedIds() {
        return [...document.querySelectorAll(".row-checkbox:checked")].map(checkbox => checkbox.value);
    }

