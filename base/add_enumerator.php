<?php
session_start(); // Start the session at the very beginning
include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding for the connection
mysqli_set_charset($con, "utf8mb4");

// Fetch all unique Admin-0 (countries) and Admin-1 (county/district) from commodity_sources
// This will be used to populate the searchable dropdowns
$commodity_sources_data = [];
// It's good practice to ensure both fields are not empty if they are expected to be linked
$sources_query = "SELECT DISTINCT admin0_country, admin1_county_district FROM commodity_sources WHERE admin0_country IS NOT NULL AND admin0_country != '' AND admin1_county_district IS NOT NULL AND admin1_county_district != '' ORDER BY admin0_country ASC, admin1_county_district ASC";
$sources_result = $con->query($sources_query);
if ($sources_result) {
    while ($row = $sources_result->fetch_assoc()) {
        $commodity_sources_data[] = [
            'country' => $row['admin0_country'],
            'county_district' => $row['admin1_county_district']
        ];
    }
} else {
    error_log('MySQL query error (commodity_sources): ' . $con->error);
}

// Safely encode JSON for JavaScript
$js_commodity_sources_data = json_encode($commodity_sources_data);
if ($js_commodity_sources_data === false) {
    error_log('JSON encoding failed for commodity_sources_data: ' . json_last_error_msg());
    $js_commodity_sources_data = '[]'; // Fallback to an empty array
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get the input data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // --- Duplicate Check ---
    // Prepare a SQL statement to check for existing records
    $stmt_check = $con->prepare("SELECT COUNT(*) FROM enumerators WHERE name = ? OR email = ? OR phone = ?");
    $stmt_check->bind_param("sss", $name, $email, $phone);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        $_SESSION['error_message'] = 'An enumerator with the provided full name, email, or phone number already exists. Please check your details.';
        header('Location: add_enumerator.php'); // Redirect back to this page to show error
        exit();
    }

    // If no duplicates, proceed to store data in session and redirect
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;
    $_SESSION['gender'] = $_POST['gender'];
    // --- IMPORTANT CHANGE: Use values from hidden inputs for country and county_district ---
    $_SESSION['country'] = $_POST['selected_admin0'] ?? ''; // Use the hidden field
    $_SESSION['county_district'] = $_POST['selected_admin1'] ?? ''; // Use the hidden field
    // --- END IMPORTANT CHANGE ---
    $_SESSION['username'] = $_POST['username'];
    $_SESSION['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password

    header('Location: add_enumerator2.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Enumerator - Step 1</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 8px;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex;
            min-height: 600px;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 30px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #333;
            z-index: 10;
        }
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }

        /* Left sidebar for steps */
        .steps-sidebar {
            width: 250px;
            background-color: #f8f9fa;
            padding: 40px 30px;
            border-radius: 8px 0 0 8px;
            border-right: 1px solid #e9ecef;
            position: relative;
        }

        .steps-sidebar h3 {
            color: #333;
            margin-bottom: 30px;
            font-size: 18px;
            font-weight: bold;
        }

        .steps-container {
            position: relative;
        }

        /* Vertical connecting line */
        .steps-container::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            bottom: 0;
            width: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }

        .step {
            display: flex;
            align-items: center;
            margin-bottom: 60px;
            position: relative;
            z-index: 2;
        }

        .step:last-child {
            margin-bottom: 0;
        }

        .step-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 16px;
            font-weight: bold;
            background-color: #e9ecef;
            color: #6c757d;
            position: relative;
            flex-shrink: 0;
        }

        .step-circle.active {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }

        .step-circle.completed {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }

        .step-circle.completed::after {
            content: '✓';
            font-size: 20px;
        }

        .step-circle.active::after {
            content: '✓';
            font-size: 20px;
        }

        .step-circle:not(.active):not(.completed)::after {
            content: attr(data-step);
        }

        .step-text {
            font-weight: 500;
            color: #6c757d;
        }

        .step.active .step-text {
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }

        .step.completed .step-text {
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }

        /* Main content area */
        .main-content {
            flex: 1;
            padding: 40px;
        }

        h2 {
            margin-bottom: 10px;
            color: #333;
        }
        p {
            margin-bottom: 30px;
            color: #666;
        }

        /* Form styling */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .form-group-full {
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        label {
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        input, select, textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            /* Remove margin-bottom from here to avoid double margin with custom-select-wrapper */
            /* margin-bottom: 15px; */
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }

        /* Password input with eye icon */
        .password-container {
            position: relative;
            margin-bottom: 15px; /* Add margin-bottom here */
        }
        .password-container input {
            margin-bottom: 0; /* Remove margin from input inside container */
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: #6c757d;
        }

        /* Button styling */
        .button-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
            gap: 20px;
        }
        .prev-btn, .next-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .prev-btn:hover, .next-btn:hover {
             filter: brightness(90%);
        }
        .prev-btn {
            background-color: #6c757d;
            color: white;
        }
        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                margin: 10px;
            }
            .steps-sidebar {
                width: 100%;
                border-radius: 8px 8px 0 0;
                border-right: none;
                border-bottom: 1px solid #e9ecef;
                padding: 20px;
            }
            .steps-container {
                display: flex;
                justify-content: center;
                gap: 30px;
            }
            .steps-container::before {
                display: none;
            }
            .step {
                margin-bottom: 0;
                flex-direction: column;
                text-align: center;
            }
            .step-circle {
                margin-right: 0;
                margin-bottom: 10px;
            }
            .main-content {
                padding: 20px;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        /* --- Styles for custom searchable dropdowns --- */
        .custom-select-wrapper {
            position: relative;
            margin-bottom: 15px; /* Adjust as needed */
            width: 100%; /* Ensure it takes full width of its parent */
        }

        .custom-select-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            background-color: #fff;
            box-sizing: border-box; /* Include padding in width */
        }

        .custom-select-input:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }

        .custom-select-dropdown {
            position: absolute;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
            z-index: 100; /* Ensure it's above other elements */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: none; /* Hidden by default */
        }

        .custom-select-dropdown .dropdown-item {
            padding: 10px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }

        .custom-select-dropdown .dropdown-item:hover,
        .custom-select-dropdown .dropdown-item.selected {
            background-color: #f0f0f0;
        }
        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: -10px; /* Adjust as needed for placement below the input */
            margin-bottom: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>

        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step active">
                    <div class="step-circle active" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Basic Info</small></div>
                </div>
                <div class="step">
                    <div class="step-circle" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Assign Tradepoints</small></div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <h2>Add Enumerator - Step 1</h2>
            <p>Please provide basic information for the new enumerator.</p>

            <?php
            // Display error message if it exists in the session
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert-danger">' . $_SESSION['error_message'] . '</div>';
                unset($_SESSION['error_message']); // Clear the message after displaying
            }
            ?>

            <form method="POST" action="add_enumerator.php" id="enumerator-form">
                <div class="form-group-full">
                    <label for="name" class="required">Full Name</label>
                    <input type="text" name="name" id="name" placeholder="Enter full name" required>
                    <div class="error-message" id="name_error">Full Name is required</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="required">Email Address</label>
                        <input type="email" name="email" id="email" placeholder="Enter email address" required>
                        <div class="error-message" id="email_error">Email Address is required</div>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="required">Phone Number</label>
                        <input type="text" name="phone" id="phone" placeholder="Enter phone number" required>
                        <div class="error-message" id="phone_error">Phone Number is required</div>
                    </div>
                </div>

                <div class="form-group-full">
                    <label for="gender" class="required">Gender</label>
                    <select name="gender" id="gender" required>
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                    <div class="error-message" id="gender_error">Gender is required</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="country_display" class="required">Country (Admin 0)</label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="country_display" class="custom-select-input" placeholder="Search or select country">
                            <div id="country_display_dropdown" class="custom-select-dropdown"></div>
                        </div>
                        <div class="error-message" id="country_display_error">Country is required</div>
                    </div>
                    <div class="form-group">
                        <label for="county_district_display" class="required">County/District (Admin 1)</label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="county_district_display" class="custom-select-input" placeholder="Search or select county/district">
                            <div id="county_district_display_dropdown" class="custom-select-dropdown"></div>
                        </div>
                        <div class="error-message" id="county_district_display_error">County/District is required</div>
                    </div>
                </div>

                <input type="hidden" id="selected_admin0" name="selected_admin0">
                <input type="hidden" id="selected_admin1" name="selected_admin1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="required">Username</label>
                        <input type="text" name="username" id="username" placeholder="Enter username" required>
                        <div class="error-message" id="username_error">Username is required</div>
                    </div>
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <div class="password-container">
                            <input type="password" name="password" id="password" placeholder="Enter password" required>
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                        <div class="error-message" id="password_error">Password is required</div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" class="next-btn">
                        Next Step <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Data passed from PHP for searchable dropdowns
        const commoditySourcesData = <?php echo $js_commodity_sources_data; ?>;

        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        if (togglePassword && password) { // Check if elements exist
            togglePassword.addEventListener('click', function (e) {
                // Toggle the type attribute
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                // Toggle the eye icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        // --- Custom Searchable Dropdown Logic (Re-used from addtradepoint.php, adapted) ---
        /**
         * Sets up a searchable dropdown for an input field.
         * @param {string} inputElementId - The ID of the text input field (e.g., 'country_display').
         * @param {string} dropdownElementId - The ID of the div that will serve as the dropdown (e.g., 'country_display_dropdown').
         * @param {string} hiddenValueInputId - The ID of the hidden input where the final selected value is stored (e.g., 'selected_admin0').
         * @param {Array<Object>} dataList - The full list of data to filter (e.g., commoditySourcesData).
         * @param {string} valueField - The key in the data object to use as the internal value (e.g., 'country').
         * @param {string} displayField - The key in the data object to display to the user (e.g., 'country').
         * @param {boolean} isAdmin1 - True if this is an Admin1 dropdown, which requires filtering by selected Admin0.
         */
        function setupSearchableDropdown(inputElementId, dropdownElementId, hiddenValueInputId, dataList, valueField, displayField, isAdmin1 = false) {
            const input = document.getElementById(inputElementId);
            const dropdown = document.getElementById(dropdownElementId);
            const hiddenInput = document.getElementById(hiddenValueInputId); // The hidden input for this specific field
            const hiddenAdmin0Input = document.getElementById('selected_admin0'); // Overall hidden for Admin0
            const hiddenAdmin1Input = document.getElementById('selected_admin1'); // Overall hidden for Admin1
            const errorElement = document.getElementById(inputElementId + '_error'); // Error for the *display* input

            if (!input || !dropdown || !hiddenInput) {
                console.warn(`Could not find elements for ${inputElementId}, ${dropdownElementId}, or ${hiddenValueInputId}`);
                return;
            }

            // Function to render dropdown items
            const renderDropdown = (filteredData) => {
                dropdown.innerHTML = ''; // Clear previous items
                if (filteredData.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'dropdown-item';
                    noResults.textContent = 'No results found';
                    dropdown.appendChild(noResults);
                    return;
                }

                filteredData.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'dropdown-item';
                    div.textContent = item[displayField]; // Display field (e.g., country name or county name)
                    // Store the full country/county pair as dataset attributes for easy access
                    div.dataset.country = item.country;
                    div.dataset.county = item.county_district;

                    div.addEventListener('click', (event) => {
                        event.stopPropagation(); // Prevent document click from closing immediately

                        input.value = item[displayField]; // Set visible input value
                        dropdown.style.display = 'none'; // Hide dropdown

                        // Update the correct overall hidden inputs based on which field is being set
                        if (valueField === 'country') {
                            hiddenAdmin0Input.value = item.country;
                            // Clear Admin1 if Admin0 changes, as the Admin1 selection might no longer be valid
                            hiddenAdmin1Input.value = '';
                            const relatedAdmin1Input = document.getElementById('county_district_display');
                            if(relatedAdmin1Input) relatedAdmin1Input.value = ''; // Clear visible Admin1
                        } else if (valueField === 'county_district') {
                            hiddenAdmin1Input.value = item.county_district;
                            // If Admin1 was selected, and Admin0 is not yet set (e.g., direct Admin1 search),
                            // try to infer Admin0 from the selected item and set the hidden input.
                            if (!hiddenAdmin0Input.value && item.country) {
                                hiddenAdmin0Input.value = item.country;
                                const relatedAdmin0Input = document.getElementById('country_display');
                                if (relatedAdmin0Input) {
                                    relatedAdmin0Input.value = item.country; // Update visible Admin-0 field too
                                }
                            }
                        }
                        // Manually trigger change event on the *hidden* input for validation purposes
                        hiddenInput.dispatchEvent(new Event('change'));

                        // If Admin-0 was selected, re-initialize its linked Admin-1 dropdown
                        if (valueField === 'country') {
                            // Re-setup Admin-1 dropdown to filter by the newly selected Admin-0 country
                            setupSearchableDropdown('county_district_display', 'county_district_display_dropdown', 'selected_admin1', commoditySourcesData, 'county_district', 'county_district', true);
                        }
                    });
                    dropdown.appendChild(div);
                });
                dropdown.style.display = 'block';
            };

            // Input event listener for searching
            input.addEventListener('input', () => {
                const searchTerm = input.value.toLowerCase();
                let filteredData = [];

                // Clear the hidden input value immediately on input, as the selection is no longer confirmed
                hiddenInput.value = '';
                if (valueField === 'country') {
                    hiddenAdmin1Input.value = ''; // Clear Admin1 if Admin0 input changes
                    const relatedAdmin1Input = document.getElementById('county_district_display');
                    if(relatedAdmin1Input) relatedAdmin1Input.value = ''; // Clear visible Admin1
                }

                if (valueField === 'country') { // Filtering for Admin-0 (Country)
                    const uniqueCountriesMap = new Map();
                    dataList.forEach(item => {
                        if (item.country && !uniqueCountriesMap.has(item.country)) {
                            uniqueCountriesMap.set(item.country, { country: item.country, county_district: '' });
                        }
                    });
                    const uniqueCountries = Array.from(uniqueCountriesMap.values());

                    filteredData = uniqueCountries.filter(item =>
                        item.country.toLowerCase().includes(searchTerm)
                    );
                } else if (valueField === 'county_district' && isAdmin1) { // Filtering for Admin-1 (County/District) linked to Admin-0
                    const selectedAdmin0Value = hiddenAdmin0Input.value; // Get the currently selected Admin-0
                    let relevantData = dataList;

                    if (selectedAdmin0Value) {
                        relevantData = dataList.filter(item => item.country === selectedAdmin0Value);
                    }

                    const uniqueCountiesMap = new Map();
                    relevantData.forEach(item => {
                        if (item.county_district && !uniqueCountiesMap.has(item.county_district)) {
                            uniqueCountiesMap.set(item.county_district, { country: item.country, county_district: item.county_district });
                        }
                    });
                    const uniqueCounties = Array.from(uniqueCountiesMap.values());

                    filteredData = uniqueCounties.filter(item =>
                        item.county_district.toLowerCase().includes(searchTerm)
                    );
                } else { // Fallback for other cases or initial load if not Admin1 specific logic
                    filteredData = dataList.filter(item =>
                        item[displayField].toLowerCase().includes(searchTerm)
                    );
                }

                renderDropdown(filteredData);
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', (event) => {
                if (!input.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                    // If the visible input is empty when blurring, ensure hidden value is also cleared
                    if (input.value.trim() === '') {
                        hiddenInput.value = '';
                        if (valueField === 'country') {
                            hiddenAdmin1Input.value = ''; // Clear Admin1 if Admin0 is cleared
                            const relatedAdmin1Input = document.getElementById('county_district_display');
                            if(relatedAdmin1Input) relatedAdmin1Input.value = ''; // Clear visible Admin1
                        }
                    } else if (hiddenInput.value.trim() === '') {
                        // If visible input has text but hidden is empty, it means no valid selection was made
                        // This indicates an invalid entry, so clear the input
                         input.value = '';
                    }
                    // Trigger validation for the associated hidden field on blur
                    validateField(hiddenInput.id, errorElement.id, (field) => field.value.trim() !== '');
                }
            });

            // Show dropdown on focus
            input.addEventListener('focus', () => {
                // If input is empty, show all relevant options on focus
                if (input.value.trim() === '' || dropdown.innerHTML === '') { // Also re-render if dropdown is empty
                    let initialData = [];
                    if (valueField === 'country') {
                        const uniqueCountriesMap = new Map();
                        commoditySourcesData.forEach(item => {
                            if (item.country && !uniqueCountriesMap.has(item.country)) {
                                uniqueCountriesMap.set(item.country, { country: item.country, county_district: '' });
                            }
                        });
                        initialData = Array.from(uniqueCountriesMap.values());
                    } else if (valueField === 'county_district' && isAdmin1) {
                        const selectedAdmin0Value = hiddenAdmin0Input.value;
                        let relevantData = commoditySourcesData;
                        if (selectedAdmin0Value) {
                            relevantData = commoditySourcesData.filter(item => item.country === selectedAdmin0Value);
                        }
                        const uniqueCountiesMap = new Map();
                        relevantData.forEach(item => {
                            if (item.county_district && !uniqueCountiesMap.has(item.county_district)) {
                                uniqueCountiesMap.set(item.county_district, { country: item.country, county_district: item.county_district });
                            }
                        });
                        initialData = Array.from(uniqueCountiesMap.values());
                    } else {
                        // If not country or admin1-specific, show all data
                        initialData = dataList;
                    }
                    renderDropdown(initialData);
                }
            });
        }
        // --- End Custom Searchable Dropdown Logic ---


        /**
         * Validates a form field.
         * @param {string} fieldId - The ID of the HTML element to validate (can be visible input or hidden input).
         * @param {string} errorId - The ID of the error message div.
         * @param {function} [customValidation=null] - Optional function for custom validation logic. Takes the field element as argument.
         */
        function validateField(fieldId, errorId, customValidation = null) {
            const field = document.getElementById(fieldId);
            const error = document.getElementById(errorId);

            if (!field) return true; // Field might not exist

            let isValid = true;

            if (customValidation) {
                isValid = customValidation(field);
            } else {
                isValid = field.value.trim() !== '';
            }

            // Determine the visual field to apply border styling to
            let visualField = field;
            if (field.id === 'selected_admin0') {
                visualField = document.getElementById('country_display');
            } else if (field.id === 'selected_admin1') {
                visualField = document.getElementById('county_district_display');
            }

            if (isValid) {
                if (error) error.style.display = 'none';
                if (visualField) visualField.style.borderColor = '#ccc';
            } else {
                if (error) error.style.display = 'block';
                if (visualField) visualField.style.borderColor = '#dc3545';
            }

            return isValid;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Setup searchable dropdowns on load
            setupSearchableDropdown('country_display', 'country_display_dropdown', 'selected_admin0', commoditySourcesData, 'country', 'country');
            setupSearchableDropdown('county_district_display', 'county_district_display_dropdown', 'selected_admin1', commoditySourcesData, 'county_district', 'county_district', true);

            // Form validation on submit
            document.getElementById('enumerator-form').addEventListener('submit', function(e) {
                let isValid = true;
                let firstErrorField = null;

                // Clear all previous error messages and reset borders
                document.querySelectorAll('.error-message').forEach(error => {
                    error.style.display = 'none';
                });
                document.querySelectorAll('input, select').forEach(input => {
                    input.style.borderColor = '#ccc';
                });

                // Validate standard fields
                const fields = [
                    'name',
                    'email',
                    'phone',
                    'gender',
                    'username',
                    'password'
                ];

                fields.forEach(fieldId => {
                    const fieldValid = validateField(fieldId, fieldId + '_error');
                    if (!fieldValid) {
                        isValid = false;
                        if (!firstErrorField) {
                            firstErrorField = document.getElementById(fieldId);
                        }
                    }
                });

                // Validate hidden Admin 0 and Admin 1 fields
                const admin0Valid = validateField('selected_admin0', 'country_display_error', (field) => field.value.trim() !== '');
                if (!admin0Valid) {
                    isValid = false;
                    if (!firstErrorField) {
                        firstErrorField = document.getElementById('country_display');
                    }
                }

                const admin1Valid = validateField('selected_admin1', 'county_district_display_error', (field) => field.value.trim() !== '');
                if (!admin1Valid) {
                    isValid = false;
                    // Set firstErrorField for admin1 only if admin0 was valid or if admin1 is the only error
                    if (!firstErrorField && admin0Valid) {
                        firstErrorField = document.getElementById('county_district_display');
                    }
                }

                if (!isValid) {
                    e.preventDefault(); // Stop form submission
                    if (firstErrorField) {
                        firstErrorField.focus(); // Focus on the first invalid field
                        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }

                return true;
            });

            // Add real-time validation for all inputs on blur and input events
            document.querySelectorAll('input:not(.custom-select-input), select').forEach(input => {
                input.addEventListener('blur', function() {
                    const errorId = this.id + '_error';
                    if (document.getElementById(errorId)) {
                        validateField(this.id, errorId);
                    }
                });
                input.addEventListener('input', function() {
                    const errorId = this.id + '_error';
                    if (document.getElementById(errorId)) {
                        validateField(this.id, errorId);
                    }
                });
            });

            // Add real-time validation for custom select inputs (Admin0/Admin1)
            document.querySelectorAll('.custom-select-input').forEach(input => {
                input.addEventListener('blur', function() {
                    const hiddenInputId = (this.id === 'country_display') ? 'selected_admin0' : 'selected_admin1';
                    const errorId = this.id + '_error';
                    validateField(hiddenInputId, errorId, (field) => field.value.trim() !== '');
                });

                input.addEventListener('input', function() {
                    // Clear the associated hidden input when the user types, indicating an unconfirmed selection
                    const hiddenInputId = (this.id === 'country_display') ? 'selected_admin0' : 'selected_admin1';
                    document.getElementById(hiddenInputId).value = '';
                    const errorId = this.id + '_error';
                    validateField(hiddenInputId, errorId, (field) => field.value.trim() !== '');
                });
            });

            // Add smooth transitions for better UX
            document.querySelectorAll('input, select, textarea').forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.transform = 'scale(1.01)'; /* Slightly smaller scale for smoother effect */
                    this.style.transition = 'transform 0.2s ease';
                });

                element.addEventListener('blur', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>