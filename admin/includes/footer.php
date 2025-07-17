<?php
// admin/includes/footer.php
?>
        </div> </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript to handle Bootstrap submenu toggling and chevron rotation
    document.addEventListener("DOMContentLoaded", function() {
        // Handle initial active link and open parent submenus
        const activeLink = document.querySelector('.sidebar .nav-link.active');
        if (activeLink) {
            let parentCollapse = activeLink.closest('.collapse.submenu');
            while(parentCollapse) {
                // Programmatically show the parent collapse
                new bootstrap.Collapse(parentCollapse, { toggle: false }).show();

                // Find the nav-link that controls this parent collapse
                const correspondingNavLink = document.querySelector(`.sidebar .nav-link[href="#${parentCollapse.id}"]`);
                if (correspondingNavLink) {
                    correspondingNavLink.classList.remove('collapsed'); // Mark as not collapsed
                    correspondingNavLink.setAttribute('aria-expanded', 'true'); // Update aria attribute
                }
                parentCollapse = parentCollapse.parentElement.closest('.collapse.submenu');
            }
        }

        // Add a listener to collapse events to manage other open submenus
        // This ensures only one top-level menu is open at a time (optional, but good UX)
        document.querySelectorAll('.sidebar > .collapse.submenu').forEach(collapseElement => {
            collapseElement.addEventListener('show.bs.collapse', function() {
                // Close other top-level open submenus
                document.querySelectorAll('.sidebar > .collapse.submenu.show').forEach(openCollapse => {
                    if (openCollapse !== this) { // If it's not the currently opening one
                        new bootstrap.Collapse(openCollapse, { toggle: false }).hide();
                        const correspondingNavLink = document.querySelector(`.sidebar .nav-link[href="#${openCollapse.id}"]`);
                        if (correspondingNavLink) {
                            correspondingNavLink.classList.add('collapsed');
                            correspondingNavLink.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });
        });

        // Handle nested submenus (e.g., Market Prices within Data)
        document.querySelectorAll('.sidebar .submenu .collapse.submenu').forEach(nestedCollapseElement => {
            nestedCollapseElement.addEventListener('show.bs.collapse', function() {
                // Close other nested submenus within the same parent
                const parentOfCurrentNested = this.parentElement;
                parentOfCurrentNested.querySelectorAll('.collapse.submenu.show').forEach(openNestedCollapse => {
                    if (openNestedCollapse !== this) {
                        new bootstrap.Collapse(openNestedCollapse, { toggle: false }).hide();
                        const correspondingNavLink = openNestedCollapse.previousElementSibling; // The nav-link right before it
                        if (correspondingNavLink && correspondingNavLink.classList.contains('nav-link')) {
                            correspondingNavLink.classList.add('collapsed');
                            correspondingNavLink.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });
        });
    });

    // Function to update breadcrumbs (called from individual pages)
    function updateBreadcrumb(mainCategory, subCategory) {
        const breadcrumbList = document.getElementById("breadcrumb");
        // Clear previous breadcrumbs except the home icon
        while (breadcrumbList.children.length > 1) { // Keep the first child (home icon)
            breadcrumbList.removeChild(breadcrumbList.lastChild);
        }

        if (mainCategory) {
            const mainCatItem = document.createElement('li');
            mainCatItem.className = 'breadcrumb-item active';
            mainCatItem.textContent = mainCategory;
            breadcrumbList.appendChild(mainCatItem);
        }

        if (subCategory) {
            const subCatItem = document.createElement('li');
            subCatItem.className = 'breadcrumb-item active';
            subCatItem.setAttribute('aria-current', 'page');
            subCatItem.textContent = subCategory;
            breadcrumbList.appendChild(subCatItem);
        }
    }
</script>

</body>
</html>