
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
        if (selectedIds.length === 0) {
            alert("Select items to delete.");
            return;
        }
        if (confirm("Delete selected items?")) {
            console.log("Deleted IDs:", selectedIds);
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

