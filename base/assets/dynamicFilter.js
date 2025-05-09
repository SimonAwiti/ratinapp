// assets/dynamicFilter.js

function filterTable(tableId, filterConfig) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        let match = true;
        const cells = row.querySelectorAll('td');

        filterConfig.forEach(filterInfo => {
            const inputElement = document.getElementById(filterInfo.id);
            if (inputElement) {
                const filterValue = inputElement.value.toUpperCase();
                const cellIndex = filterInfo.index;

                if (cells[cellIndex] && !cells[cellIndex].textContent.toUpperCase().includes(filterValue)) {
                    match = false;
                }
            }
        });

        row.style.display = match ? '' : 'none';
    });
}

function initializeDynamicFilters(tableId, filterConfig) {
    filterConfig.forEach(filterInfo => {
        const inputElement = document.getElementById(filterInfo.id);
        if (inputElement) {
            inputElement.addEventListener('input', () => filterTable(tableId, filterConfig));
        }
    });
}

function setupPagination(tableId, itemsPerPageSelectId) {
    const itemsPerPageSelect = document.getElementById(itemsPerPageSelectId);
    if (itemsPerPageSelect) {
        itemsPerPageSelect.onchange = function() {
            const limit = this.value;
            const currentURL = new URL(window.location.href);
            currentURL.searchParams.set('limit', limit);
            currentURL.searchParams.set('page', 1);
            const pathParts = currentURL.pathname.split('/');
            const filename = pathParts[pathParts.length - 1];
            loadContent(filename + currentURL.search, getCurrentMainCategory(), getCurrentSubCategory());
        };
    }

    const paginationContainer = document.querySelector(`#${tableId}`).nextElementSibling?.querySelector('.pagination');
    if (paginationContainer) {
        const paginationLinks = paginationContainer.querySelectorAll('a.page-link');
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                const href = this.getAttribute('href');
                loadContent(href, getCurrentMainCategory(), getCurrentSubCategory());
            });
        });
    }
}

function getCurrentMainCategory() {
    return document.getElementById('mainCategory')?.textContent || '';
}

function getCurrentSubCategory() {
    return document.getElementById('subCategory')?.textContent || '';
}