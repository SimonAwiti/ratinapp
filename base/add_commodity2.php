<?php
session_start();
include '../admin/includes/config.php';

// Redirect if session data is missing (meaning step 1 wasn't completed)
if (!isset($_SESSION['category']) || !isset($_SESSION['commodity_name']) || !isset($_SESSION['packaging']) || !isset($_SESSION['unit'])) {
    header('Location: add_commodity.php');
    exit;
}

// Re-retrieve session data for display/pre-filling
$category_id = $_SESSION['category'];
$commodity_name = $_SESSION['commodity_name'];
$variety = $_SESSION['variety'] ?? '';
$packaging_array = $_SESSION['packaging'];
$unit_array = $_SESSION['unit'];

// Variables to hold POST data if form was submitted but failed validation
// These will be used to pre-fill the fields
$posted_hs_code = $_POST['hs_code'] ?? '';
$posted_commodity_aliases = isset($_POST['commodity_alias']) ? $_POST['commodity_alias'] : [];
$posted_countries = isset($_POST['country']) ? $_POST['country'] : [];

// If there are no previously posted aliases, ensure at least one empty entry for the initial display
if (empty($posted_commodity_aliases)) {
    $posted_commodity_aliases = [''];
    $posted_countries = [''];
}


// Fetch countries from database
$countries = [];
$country_query = "SELECT country_name FROM countries ORDER BY country_name ASC";
$country_result = $con->query($country_query);
if ($country_result) {
    while ($row = $country_result->fetch_assoc()) {
        $countries[] = $row['country_name'];
    }
}

$error_message = '';
$success_message = ''; // Added for consistency, though currently redirects on success

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hs_code = trim($_POST['hs_code'] ?? '');
    
    // Filter out empty alias/country pairs during processing
    $valid_alias_country_pairs = [];
    if (isset($_POST['commodity_alias']) && isset($_POST['country'])) {
        $alias_inputs = $_POST['commodity_alias'];
        $country_inputs = $_POST['country'];

        for ($i = 0; $i < count($alias_inputs); $i++) {
            $alias = trim($alias_inputs[$i] ?? '');
            $country = trim($country_inputs[$i] ?? '');
            // Only add if both alias and country are non-empty
            if (!empty($alias) && !empty($country)) {
                $valid_alias_country_pairs[] = [
                    'alias' => $alias,
                    'country' => $country
                ];
            }
        }
    }

    // Basic validation for HS Code and at least one valid alias/country pair
    if (empty($hs_code)) {
        $error_message = "HS Code is required.";
    } elseif (empty($valid_alias_country_pairs)) {
        $error_message = "Please add at least one Commodity Alias and Country pair.";
    }

    if (empty($error_message)) { // Proceed only if no validation errors so far
        // Check for duplicate commodity (same category, commodity name, and variety)
        $duplicate_check_sql = "SELECT id FROM commodities WHERE category_id = ? AND commodity_name = ? AND variety = ?";
        $duplicate_stmt = $con->prepare($duplicate_check_sql);
        $duplicate_stmt->bind_param('iss', $category_id, $commodity_name, $variety);
        $duplicate_stmt->execute();
        $duplicate_result = $duplicate_stmt->get_result();

        if ($duplicate_result->num_rows > 0) {
            $error_message = "A commodity with the same category, name, and variety already exists. Please choose different details.";
        } else {
            // Handle file upload
            $image_url = '';
            if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                // Ensure upload directory exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $image_name = uniqid('commodity_') . '_' . basename($_FILES['commodity_image']['name']); // Unique filename
                $image_path = $upload_dir . $image_name;
                if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $image_path)) {
                    $image_url = $image_path;
                } else {
                    $error_message = "Failed to upload image. " . $_FILES['commodity_image']['error'];
                }
            }

            // Combine packaging and unit into arrays of objects
            $combined_units = [];
            for ($i = 0; $i < count($packaging_array); $i++) {
                // Ensure both packaging and unit exist for the current index
                if (isset($packaging_array[$i]) && isset($unit_array[$i])) {
                    $combined_units[] = [
                        'size' => $packaging_array[$i],
                        'unit' => $unit_array[$i]
                    ];
                }
            }

            // Encode the combined array as JSON
            $units_json = json_encode($combined_units);

            // Encode commodity aliases (the filtered, valid pairs) as JSON
            $commodity_aliases_json = json_encode($valid_alias_country_pairs);

            // Collect unique countries from the submitted valid pairs for the 'country' column
            $unique_countries_from_pairs = [];
            foreach($valid_alias_country_pairs as $pair) {
                $unique_countries_from_pairs[] = $pair['country'];
            }
            $countries_json = json_encode(array_values(array_unique($unique_countries_from_pairs))); // Ensure unique values and reset keys


            // Start database transaction
            $con->begin_transaction();
            try {
                $sql = "INSERT INTO commodities
                    (commodity_name, category_id, variety, units, hs_code, commodity_alias, country, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $con->prepare($sql);
                $stmt->bind_param(
                    'sississs',
                    $commodity_name,
                    $category_id,
                    $variety,
                    $units_json,
                    $hs_code,
                    $commodity_aliases_json,
                    $countries_json,
                    $image_url
                );

                $stmt->execute();

                $con->commit();

                // Clear session data after successful insertion
                session_unset();
                session_destroy();

                // Redirect to a success page or commodity list
                header('Location: view_commodities.php?status=success'); // Redirect to a page where success message can be shown
                exit;

            } catch (Exception $e) {
                $con->rollback();
                $error_message = "Database error: " . $e->getMessage();
                error_log("Commodity add error: " . $e->getMessage());
            }
        }
    }
}
// Re-fetch category name for display if an error occurred and we're staying on the page
$cat_name_display = 'N/A';
$cat_stmt = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
if ($cat_stmt) {
    $cat_stmt->bind_param('i', $category_id);
    $cat_stmt->execute();
    $cat_result = $cat_stmt->get_result();
    if ($cat_row = $cat_result->fetch_assoc()) {
        $cat_name_display = $cat_row['name'];
    }
    $cat_stmt->close();
}

mysqli_close($con); // Close DB connection for step 2
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity - Step 2</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            max-width: 1200px; /* Increased max-width to accommodate sidebar */
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex; /* Use flexbox for sidebar and main content */
            min-height: 600px; /* Ensure enough height for the form */
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
            z-index: 10; /* Ensure it's above other elements */
        }
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }

        /* Left sidebar for steps (Copied from enumerator.php) */
        .steps-sidebar {
            width: 250px;
            background-color: #f8f9fa;
            padding: 40px 30px;
            border-radius: 8px 0 0 8px;
            border-right: 1px solid #e9ecef;
            position: relative;
            flex-shrink: 0; /* Prevent shrinking */
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
            font-family: 'Font Awesome 6 Free'; /* For consistent checkmark icon */
            font-weight: 900;
            font-size: 20px;
        }

        .step-circle.active::after {
            content: ''; /* No checkmark for current active step */
        }
        .step-circle.active[data-step="1"]::after,
        .step-circle.active[data-step="2"]::after {
            content: attr(data-step); /* Display step number if it's the current active step */
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
            flex: 1; /* Takes remaining space */
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
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        input[type="file"],
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 0; /* Important for dynamic fields */
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }

        /* Dynamic Alias/Country Fields */
        #alias-country-container {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            max-height: 250px; /* Set max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrolling */
            background-color: #fcfcfc;
        }
        .alias-country-group {
            display: flex;
            gap: 15px;
            align-items: flex-end; /* Align inputs to the bottom */
            margin-bottom: 15px;
            padding-bottom: 5px; /* Little padding for separation */
            border-bottom: 1px dashed #eee; /* Visual separator */
        }
        .alias-country-group:last-of-type {
            margin-bottom: 0;
            border-bottom: none;
        }
        .alias-country-group > div {
            flex: 1; /* Distribute space evenly */
            display: flex;
            flex-direction: column;
        }
        .remove-btn {
            background-color: #f8d7da;
            color: #dc3545;
            border: 1px solid #f5c6cb;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px; /* Fixed width for better alignment */
            height: 38px; /* Fixed height */
            flex-shrink: 0; /* Prevent shrinking */
            margin-left: 10px;
        }
        .remove-btn:hover {
            background-color: #f5c6cb;
        }
        .add-more-btn {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 15px;
            font-weight: bold;
            display: block; /* Make it a block element */
            width: fit-content; /* Adjust width to content */
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .add-more-btn:hover {
            background-color: #c3e6cb;
        }

        /* Navigation buttons */
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
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
        .prev-btn {
            background-color: #6c757d;
            color: white;
        }
        .prev-btn:hover {
            background-color: #5a6268;
        }
        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .next-btn:hover {
            background-color: rgba(160, 60, 30, 1);
        }

        /* Error message styling */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        /* Current Commodity Info (Optional, but good for context) */
        .current-commodity-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .current-commodity-info h5 {
            margin-bottom: 15px;
            color: rgba(180, 80, 50, 1);
        }
        .current-commodity-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        .current-commodity-info p strong {
            display: inline-block;
            min-width: 120px; /* Align labels */
        }

        /* Style for file input */
        input[type="file"] {
            border: 1px solid #ccc;
            padding: 8px;
            border-radius: 5px;
            background-color: #fff;
            cursor: pointer;
        }
        input[type="file"]::file-selector-button {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 3px;
            padding: 8px 12px;
            margin-right: 15px;
            cursor: pointer;
            transition: background-color .2s ease-in-out;
        }
        input[type="file"]::file-selector-button:hover {
            background-color: #dee2e6;
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
            .alias-country-group {
                flex-direction: column;
                align-items: stretch;
            }
            .alias-country-group > div {
                width: 100%;
            }
            .remove-btn {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
            .add-more-btn {
                width: 100%;
            }
            .button-container {
                flex-direction: column;
                gap: 15px;
            }
            .prev-btn, .next-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='sidebar.php'">×</button>

        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step completed">
                    <div class="step-circle completed" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Basic Info</small></div>
                </div>
                <div class="step completed">
                    <div class="step-circle completed" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Details</small></div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <h2>Add New Commodity</h2>
            <p>Complete the commodity details and submit.</p>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="current-commodity-info">
                <h5>Commodity Details (from Step 1)</h5>
                <p><strong>Category:</strong>
                    <?= htmlspecialchars($cat_name_display); ?>
                </p>
                <p><strong>Commodity Name:</strong> <?= htmlspecialchars($commodity_name) ?></p>
                <p><strong>Variety:</strong> <?= htmlspecialchars($variety ?: 'N/A') ?></p>
                <p><strong>Packaging & Units:</strong>
                    <?php
                    if (!empty($packaging_array)) {
                        $display_units = [];
                        for ($i = 0; $i < count($packaging_array); $i++) {
                            $display_units[] = htmlspecialchars($packaging_array[$i] . ' ' . ($unit_array[$i] ?? ''));
                        }
                        echo implode(', ', $display_units);
                    } else {
                        echo 'None specified';
                    }
                    ?>
                </p>
            </div>

            <form method="POST" action="add_commodity2.php" enctype="multipart/form-data" onsubmit="return validateStep2()">
                <div class="form-group-full">
                    <label for="hs-code" class="required">HS Code</label>
                    <input type="text" id="hs-code" name="hs_code"
                           value="<?= htmlspecialchars($posted_hs_code) ?>" required>
                </div>

                <label class="required">Commodity Aliases & Countries</label>
                <div id="alias-country-container">
                    <?php 
                    // Loop through the posted data to re-populate fields
                    // If no data was posted, $posted_commodity_aliases will have one empty entry
                    for ($i = 0; $i < count($posted_commodity_aliases); $i++):
                        $alias_val = $posted_commodity_aliases[$i] ?? '';
                        $country_val = $posted_countries[$i] ?? '';
                    ?>
                        <div class="alias-country-group">
                            <div>
                                <label>Alias</label>
                                <input type="text" name="commodity_alias[]"
                                       value="<?= htmlspecialchars($alias_val) ?>">
                            </div>
                            <div>
                                <label>Country</label>
                                <select name="country[]" class="country-select" required>
                                    <option value="">Select country</option>
                                    <?php foreach ($countries as $country_name): ?>
                                        <option value="<?= htmlspecialchars($country_name) ?>"
                                            <?= ($country_val === $country_name) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($country_name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeAliasCountry(this)">×</button>
                        </div>
                    <?php endfor; ?>
                </div>

                <button type="button" class="add-more-btn" onclick="addMoreAliasCountry()">
                    <i class="fas fa-plus"></i> Add More Alias & Country
                </button>

                <div class="form-group-full">
                    <label for="commodity-image">Commodity Image (optional)</label>
                    <input type="file" id="commodity-image" name="commodity_image" accept="image/*">
                </div>

                <div class="button-container">
                    <button type="button" class="prev-btn" onclick="window.location.href='add_commodity.php'">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="submit" class="next-btn">
                        Done <i class="fas fa-check"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Store countries array for JavaScript use
        const countries = <?php echo json_encode($countries); ?>;

        $(document).ready(function() {
            // Initialize Select2 for all existing country selects that have the class 'country-select'
            $('.country-select').select2({
                placeholder: "Select country",
                allowClear: true,
                width: '100%'
            });
        });

        // Function to add more commodity alias and country fields
        function addMoreAliasCountry() {
            const container = document.getElementById('alias-country-container');
            const newGroup = document.createElement('div');
            newGroup.className = 'alias-country-group';

            // Build country options dynamically
            let countryOptions = '<option value="">Select country</option>';
            countries.forEach(country => {
                countryOptions += `<option value="${country}">${country}</option>`;
            });

            newGroup.innerHTML = `
                <div>
                    <label>Alias</label>
                    <input type="text" name="commodity_alias[]">
                </div>
                <div>
                    <label>Country</label>
                    <select name="country[]" class="country-select" required>
                        ${countryOptions}
                    </select>
                </div>
                <button type="button" class="remove-btn" onclick="removeAliasCountry(this)">×</button>
            `;
            container.appendChild(newGroup);

            // Initialize Select2 for the newly added select element
            $(newGroup).find('.country-select').select2({ // Use class selector here
                placeholder: "Select country",
                allowClear: true,
                width: '100%'
            });

            // Automatically scroll to the newly added fields
            container.scrollTop = container.scrollHeight;
        }

        // Function to remove an alias-country group
        function removeAliasCountry(button) {
            const container = document.getElementById('alias-country-container');
            const group = button.closest('.alias-country-group');
            if (group) {
                // Ensure at least one alias-country group remains
                if (container.children.length > 1) {
                    group.remove();
                } else {
                    alert("You must have at least one commodity alias and country entry.");
                }
            }
        }

        function validateStep2() {
            const hsCode = document.getElementById('hs-code').value.trim();
            const aliasInputs = document.querySelectorAll('#alias-country-container input[name="commodity_alias[]"]');
            const countrySelects = document.querySelectorAll('#alias-country-container select[name="country[]"]');

            if (!hsCode) {
                alert('Please enter the HS Code.');
                return false;
            }

            let hasValidAliasCountryPair = false;
            for (let i = 0; i < aliasInputs.length; i++) {
                const alias = aliasInputs[i].value.trim();
                const country = countrySelects[i].value;
                if (alias && country) {
                    hasValidAliasCountryPair = true;
                    break;
                }
            }

            if (!hasValidAliasCountryPair) {
                alert('Please ensure you have at least one valid Commodity Alias and Country pair.');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>