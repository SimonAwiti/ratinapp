// Function to get the IDs of all selected checkboxes
function getSelectedIds() {
    // Select all checkboxes with the class 'row-checkbox' that are checked
    // Use the spread operator (...) to convert NodeList to an array,
    // then map over it to get the 'value' attribute of each checkbox.
    return [...document.querySelectorAll(".row-checkbox:checked")].map(checkbox => checkbox.value);
}

// --- Filtering Functionality ---
// Function to filter the table based on input values
function filterTable() {
    const filterHsCode = document.getElementById('filterHsCode')?.value.toUpperCase() || '';
    const filterCategory = document.getElementById('filterCategory')?.value.toUpperCase() || '';
    const filterCommodity = document.getElementById('filterCommodity')?.value.toUpperCase() || '';
    const filterVariety = document.getElementById('filterVariety')?.value.toUpperCase() || '';

    // Select all table rows within the commodityTable (excluding the header if applicable)
    const rows = document.querySelectorAll('#commodityTable tr');

    rows.forEach(row => {
        // Get all table data cells within the current row
        const cells = row.querySelectorAll('td');
        // Ensure the row has cells (to skip header rows or empty rows)
        if (cells.length > 0) {
            // Get text content from specific cell indices (adjust if your column order changes)
            const hsCode = cells[1]?.textContent.toUpperCase() || '';
            const category = cells[2]?.textContent.toUpperCase() || '';
            const commodity = cells[3]?.textContent.toUpperCase() || '';
            const variety = cells[4]?.textContent.toUpperCase() || '';

            // Check if each filter matches the corresponding cell content
            const matchHsCode = hsCode.includes(filterHsCode);
            const matchCategory = category.includes(filterCategory);
            const matchCommodity = commodity.includes(filterCommodity);
            const matchVariety = variety.includes(filterVariety);

            // Set row display style based on all filter matches
            row.style.display = (matchHsCode && matchCategory && matchCommodity && matchVariety) ? '' : 'none';
        }
    });
}

// Add event listeners to the filter input fields
// The '?' (optional chaining) safely handles cases where the element might not exist
document.getElementById('filterHsCode')?.addEventListener('input', filterTable);
document.getElementById('filterCategory')?.addEventListener('input', filterTable);
document.getElementById('filterCommodity')?.addEventListener('input', filterTable);
document.getElementById('filterVariety')?.addEventListener('input', filterTable);

// --- Pagination Functionality ---
// Function to handle changing the number of items displayed per page
window.updateItemsPerPage = function (limit) {
    // Get current URL search parameters
    const params = new URLSearchParams(window.location.search);
    // Set or update the 'limit' parameter
    params.set('limit', limit);
    // Always reset to the first page when the limit changes
    params.set('page', 1);

    // Construct the URL for the fetch request
    const fetchUrl = 'components/tradepoints_boilerplate.php?' + params.toString();

    // Fetch the new content
    fetch(fetchUrl)
        .then(response => {
            // Check if the network request was successful
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Parse the response as plain text (HTML content)
            return response.text();
        })
        .then(html => {
            // Update the main content area with the fetched HTML
            const mainContentElement = document.getElementById('main-content');
            if (mainContentElement) {
                mainContentElement.innerHTML = html;
            } else {
                console.warn("Element with ID 'main-content' not found. Cannot update content.");
            }

            // Update the URL in the browser's address bar without reloading the page
            const newUrl = window.location.pathname + '?' + params.toString();
            history.pushState(null, '', newUrl);

            // Re-attach event listeners if elements were dynamically loaded
            // (e.g., if filter inputs or pagination controls are part of tradepoints_boilerplate.php)
            // You might need to call filterTable() again here if the table content itself reloads.
            // Or re-attach specific listeners for newly loaded elements if they exist.
        })
        .catch(err => {
            console.error("Failed to fetch tradepoints content:", err);
            alert("Failed to load content for the selected items per page.");
        });
};

// Listen for changes on the items per page dropdown (if it exists)
document.getElementById("itemsPerPage")?.addEventListener("change", function() {
    window.updateItemsPerPage(this.value);
});


// --- Select All Checkbox Functionality ---
// Event listener for the "Select All" checkbox
document.getElementById("selectAll")?.addEventListener("change", function () {
    // Select all individual row checkboxes
    let checkboxes = document.querySelectorAll(".row-checkbox");
    // Set their checked state to match the "Select All" checkbox
    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
});

// --- Delete Selected Items Functionality ---
// Function to delete selected items
window.deleteSelected = function () {
    let selectedIds = getSelectedIds();
    console.log("Selected IDs for deletion:", selectedIds); // For debugging

    if (selectedIds.length === 0) {
        alert("Please select items to delete.");
        return;
    }

    // Confirm deletion with the user
    if (confirm("Are you sure you want to delete the selected items? This action cannot be undone.")) {
        // Use Fetch API to send a POST request to the PHP backend
        fetch('delete_tradepoints.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json' // Crucial: Inform the server we're sending JSON
            },
            // Convert the array of IDs into a JSON string within an object
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => {
            console.log("Response status from PHP:", response.status, response.statusText);
            // Check if the HTTP response was successful (status code 200-299)
            if (!response.ok) {
                // If not successful, throw an error with the response text for debugging
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                });
            }
            // Parse the JSON response from the PHP script
            return response.json();
        })
        .then(data => {
            console.log("Response data from PHP:", data); // For debugging
            if (data.success) {
                alert(data.message); // Display success message from PHP
                location.reload(); // Reload the page to reflect the changes
            } else {
                alert("Error: " + (data.message || "An unknown error occurred on the server.")); // Display error message from PHP
            }
        })
        .catch(error => {
            console.error("Fetch error during deletion:", error); // Log any network or parsing errors
            alert("An unexpected error occurred while communicating with the server. Please check the console for details.");
        });
    } else {
        console.log("Deletion cancelled by user.");
    }
};

// --- Export Selected Items Functionality ---
// Function to export selected items (e.g., to CSV or PDF)
window.exportSelected = function (type) {
    let selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert("Select items to export.");
        return;
    }
    console.log(`Exporting ${type} for IDs:`, selectedIds);

    // In a real application, you would make a fetch request here,
    // similar to deleteSelected, but pointing to an export PHP script
    // and potentially handling file download.
    alert(`Exporting ${selectedIds.length} item(s) to ${type}. (Functionality to be implemented on server-side)`);
    // Example of a fetch call for export (you'd need export_commodity.php):
    /*
    fetch('export_commodity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids: selectedIds, format: type })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // For file downloads, you might check content-disposition header
        // and create a Blob to trigger download
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `commodities.${type}`; // e.g., commodities.csv
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
        alert("Export successful!");
    })
    .catch(error => {
        console.error("Error exporting:", error);
        alert("An error occurred during export.");
    });
    */
};

// Initial call to filterTable in case there are initial filter values or data
// window.addEventListener('load', filterTable); // Uncomment if you want to run filter on page load