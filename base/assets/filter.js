// assets/filter.js

document.addEventListener("DOMContentLoaded", function() {
    // Initialize filter functionality
    initFilters();
    
    // Initialize pagination if needed
    if (document.querySelector('.pagination')) {
        initPagination();
    }
});

function initFilters() {
    const filterInputs = document.querySelectorAll('.filter-input');
    if (filterInputs.length > 0) {
        filterInputs.forEach(input => {
            input.addEventListener('keyup', applyFilters);
        });
        console.log("Filter inputs initialized");
    }
}

function applyFilters() {
    try {
        const filters = {
            hsCode: document.getElementById('filterHsCode')?.value.toLowerCase() || '',
            category: document.getElementById('filterCategory')?.value.toLowerCase() || '',
            commodity: document.getElementById('filterCommodity')?.value.toLowerCase() || '',
            variety: document.getElementById('filterVariety')?.value.toLowerCase() || ''
        };

        const rows = document.querySelectorAll('#commodityTable tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 5) { // Ensure we have enough columns
                const matches = 
                    cells[1].textContent.toLowerCase().includes(filters.hsCode) &&
                    cells[2].textContent.toLowerCase().includes(filters.category) &&
                    cells[3].textContent.toLowerCase().includes(filters.commodity) &&
                    cells[4].textContent.toLowerCase().includes(filters.variety);
                
                row.style.display = matches ? '' : 'none';
            }
        });
    } catch (error) {
        console.error("Error applying filters:", error);
    }
}

function initPagination() {
    // Handle items per page change
    const itemsPerPageSelect = document.getElementById('itemsPerPage');
    if (itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', function() {
            const url = new URL(window.location);
            url.searchParams.set('limit', this.value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });
    }

    // Handle pagination clicks (event delegation)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.page-link')) {
            e.preventDefault();
            const pageLink = e.target.closest('.page-link');
            if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                const url = new URL(window.location);
                url.searchParams.set('page', pageLink.textContent.trim());
                window.location.href = url.toString();
            }
        }
    });
}

// Make functions available globally if needed
window.applyFilters = applyFilters;
window.initFilters = initFilters;