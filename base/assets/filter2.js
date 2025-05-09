window.deleteSelected = function () {
    let selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert("Select items to delete.");
        return;
    }

    if (confirm("Delete selected items?")) {
        fetch('delete_tradepoints.php', { // Ensure the path is correct!
            method: 'POST',
            headers: {
                'Content-Type': 'application/json' // Important: Tell the server we're sending JSON
            },
            body: JSON.stringify({ ids: selectedIds }) // Convert the data to JSON
        })
        .then(response => response.json()) // Parse the JSON response
        .then(data => {
            if (data.success) {
                alert(data.message); // Display the message from the PHP script
                // Optionally, you can reload the page or update the table dynamically here
                location.reload(); // Simplest way: reload the page
            } else {
                alert(data.message); // Display the error message
            }
        })
        .catch(error => {
            console.error("Error:", error); // Log network errors
            alert("An error occurred while communicating with the server.");
        });
    }
};

//Keep the rest of the code in assets/filter2.js
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



window.exportSelected = function (type) {
    let selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert("Select items to export.");
        return;
    }
    console.log(`Exporting ${type}:`, selectedIds);
};

window.updateItemsPerPage = function (limit) {
    const params = new URLSearchParams(window.location.search);
    params.set('limit', limit);
    params.set('page', 1); // Reset to page 1 on limit change

    fetch('components/tradepoints_boilerplate.php?' + params.toString())
        .then(response => response.text())
        .then(html => {
            document.getElementById('main-content').innerHTML = html;

            // Update the URL in the address bar (optional)
            const newUrl = window.location.pathname + '?' + params.toString();
            history.pushState(null, '', newUrl);
        })
        .catch(err => {
            console.error("Failed to fetch tradepoints content:", err);
        });
};
