// filter2.js (updated)

function filterMarketsTable() {
    const filterName = document.getElementById('filterName').value.toUpperCase();
    const filterCategory = document.getElementById('filterCategory').value.toUpperCase();
    const filterCountry = document.getElementById('filterCountry').value.toUpperCase();
    const filterCounty = document.getElementById('filterCounty').value.toUpperCase();

    const rows = document.querySelectorAll('#marketTable tr');

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
            const name = cells[1].textContent.toUpperCase();
            const category = cells[2].textContent.toUpperCase();
            const country = cells[3].textContent.toUpperCase();
            const county = cells[4].textContent.toUpperCase();

            const matchName = name.includes(filterName);
            const matchCategory = category.includes(filterCategory);
            const matchCountry = country.includes(filterCountry);
            const matchCounty = county.includes(filterCounty);

            row.style.display = (matchName && matchCategory && matchCountry && matchCounty) ? '' : 'none';
        }
    });
}

document.getElementById('filterName')?.addEventListener('input', filterMarketsTable);
document.getElementById('filterCategory')?.addEventListener('input', filterMarketsTable);
document.getElementById('filterCountry')?.addEventListener('input', filterMarketsTable);
document.getElementById('filterCounty')?.addEventListener('input', filterMarketsTable);

document.getElementById("selectAll")?.addEventListener("change", function () {
    let checkboxes = document.querySelectorAll(".row-checkbox");
    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
});

function getSelectedIds() {
    return [...document.querySelectorAll(".row-checkbox:checked")].map(checkbox => checkbox.value);
}

window.deleteSelected = function () {
    let selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert("Select items to delete.");
        return;
    }

    if (confirm("Delete selected items?")) {
        fetch('../delete_tradepoints.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Deleted successfully.");
                // Optionally reload data or page
                location.reload();
            } else {
                alert("Failed to delete: " + data.message);
            }
        })
        .catch(err => {
            console.error("Error:", err);
            alert("Something went wrong while deleting.");
        });
    }
}


window.exportSelected = function (type) {
    let selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert("Select items to export.");
        return;
    }
    console.log(`Exporting ${type}:`, selectedIds);
};

window.updateItemsPerPage = function (limit) {
    const url = new URL(window.location.href, window.location.origin);
    
    // If it's being run from a fragment like sidebar.php, make sure we redirect the parent
    if (url.pathname.includes('sidebar.php') || url.pathname.includes('tradepoints_boilerplate.php')) {
        window.location.href = 'dashboard.php?page=1&limit=' + limit;
        return;
    }

    const params = url.searchParams;
    params.set('limit', limit);
    params.set('page', 1); // Reset to first page
    window.location.href = url.pathname + '?' + params.toString();
};
