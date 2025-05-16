        function confirmAction(action, ids) {
            if (ids.length === 0) {
                alert('Please select items to ' + action + '.');
                return;
            }

            let message = 'Are you sure you want to ' + action + ' these items?';
            if (confirm(message)) {
                // Send an AJAX request to update the statuses
                console.log('Action:', action);  // Add console log
                console.log('IDs:', ids);        // Add console log

                fetch('update_status.php', { // Create this file
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: action,
                        ids: ids,
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Response:', data); // Add console log
                    if (data.success) {
                        alert('Items ' + action + ' successfully.');
                        // Reload the page or update the table dynamically
                        window.location.reload(); // Simplest way for now
                    } else {
                        alert('Failed to ' + action + ' items: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while ' + action + ' items.');
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const selectAllCheckbox = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('table tbody input[type="checkbox"]');
            const approveButton = document.querySelector('.toolbar .approve');
            const publishButton = document.querySelector('.toolbar .primary');
             const deleteButton = document.querySelector('.toolbar button:nth-of-type(2)');


            let selectedIds = [];

             function updateSelectedIds() {
                selectedIds = Array.from(checkboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => checkbox.getAttribute('data-id'));
                 console.log('Selected IDs:', selectedIds);
            }

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    updateSelectedIds();
                     if (checkboxes.length === document.querySelectorAll('table tbody input[type="checkbox"]:checked').length) {
                        selectAllCheckbox.checked = true;
                    } else {
                        selectAllCheckbox.checked = false;
                    }
                });
            });

            selectAllCheckbox.addEventListener('change', () => {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateSelectedIds();
            });


            approveButton.addEventListener('click', () => {
                confirmAction('approve', selectedIds);
            });

            publishButton.addEventListener('click', () => {
                 // Check if selected items are approved before publishing
                fetch('check_status.php', {  // Create this file
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids: selectedIds }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.allApproved) {
                        confirmAction('publish', selectedIds);
                    } else {
                        alert('Please approve the selected items before publishing.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while checking approval status.');
                });
            });
            deleteButton.addEventListener('click', () => {
                confirmAction('delete', selectedIds);
            });
        });