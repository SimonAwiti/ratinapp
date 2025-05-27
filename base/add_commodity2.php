<?php
include '../admin/includes/config.php';
session_start();

// Redirect if session data is missing
if (!isset($_SESSION['category'])) {
    header('Location: add_commodity.php');
    exit;
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve data from session and form
    $category_id = $_SESSION['category'];
    $commodity_name = $_SESSION['commodity_name'];
    $variety = $_SESSION['variety'];
    $packaging_array = $_SESSION['packaging'];
    $unit_array = $_SESSION['unit'];

    $hs_code = $_POST['hs_code'];

    // Check for duplicate commodity (same category, commodity name, and variety)
    $duplicate_check_sql = "SELECT id FROM commodities WHERE category_id = ? AND commodity_name = ? AND variety = ?";
    $duplicate_stmt = $con->prepare($duplicate_check_sql);
    $duplicate_stmt->bind_param('iss', $category_id, $commodity_name, $variety);
    $duplicate_stmt->execute();
    $duplicate_result = $duplicate_stmt->get_result();
    
    if ($duplicate_result->num_rows > 0) {
        $error_message = "A commodity with the same category, name, and variety already exists. Please choose different details.";
    } else {
        // Retrieve arrays for commodity aliases and countries
        // Use empty array if not set, to ensure json_encode works correctly
        $commodity_aliases_array = isset($_POST['commodity_alias']) ? $_POST['commodity_alias'] : [];
        $country_array = isset($_POST['country']) ? $_POST['country'] : [];

        // Handle file upload
        $image_url = '';
        if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            $image_name = basename($_FILES['commodity_image']['name']);
            $image_path = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $image_path)) {
                $image_url = $image_path;
            }
        }

        // Combine packaging and unit into arrays of objects
        $combined_units = [];
        for ($i = 0; $i < count($packaging_array); $i++) {
            $combined_units[] = [
                'size' => $packaging_array[$i],
                'unit' => $unit_array[$i]
            ];
        }

        // Encode the combined array as JSON
        $units_json = json_encode($combined_units);

        // Encode commodity aliases and countries as JSON
        $commodity_aliases_json = json_encode($commodity_aliases_array);
        $countries_json = json_encode($country_array);

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
                $commodity_aliases_json, // Insert JSON string for aliases
                $countries_json,         // Insert JSON string for countries
                $image_url
            );

            $stmt->execute();

            $con->commit();
            
            // Clear session
            session_unset();
            session_destroy();

            // Redirect
            header('Location: sidebar.php');
            exit;
            
        } catch (Exception $e) {
            $con->rollback();
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity</title>
    <link rel="stylesheet" href="assets/add_commodity2.css" />
    <style>
        /* Add some basic styling for the new dynamic fields, similar to packaging */
        .alias-country-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        .alias-country-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .alias-country-group input,
        .alias-country-group select {
            flex: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .remove-btn {
            background-color: #f8d7da;
            color: red;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
        }
        .remove-btn:hover {
            background-color: #f5c6cb;
        }
        .add-more-btn {
            background-color: #d9f5d9;
            color: green;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }
        .add-more-btn:hover {
            background-color: #c4e6c4;
        }
        /* Style the container for aliases and countries */
        #alias-country-container {
            flex-grow: 1;
            max-height: 200px; /* Adjust height as needed */
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        /* Error message styling */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='sidebar.php'">&times;</button>

        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 2</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Commodity</h2>
            <p>Provide the necessary details to add a new commodity</p>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="add_commodity2.php" enctype="multipart/form-data">
                <label for="hs-code">HS Code*</label>
                <input type="text" id="hs-code" name="hs_code" value="<?php echo isset($_POST['hs_code']) ? htmlspecialchars($_POST['hs_code']) : ''; ?>" required>

                <div id="alias-country-container">
                    <div class="alias-country-group">
                        <div style="flex: 1;">
                            <label>Commodity Alias</label>
                            <input type="text" name="commodity_alias[]" value="<?php echo isset($_POST['commodity_alias'][0]) ? htmlspecialchars($_POST['commodity_alias'][0]) : ''; ?>">
                        </div>
                        <div style="flex: 1;">
                            <label>Country</label>
                            <select name="country[]" required>
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" 
                                        <?php echo (isset($_POST['country'][0]) && $_POST['country'][0] === $country) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="remove-btn" onclick="removeAliasCountry(this)">×</button>
                    </div>
                </div>

                <button type="button" class="add-more-btn" onclick="addMoreAliasCountry()">Add More Alias & Country</button>

                <label for="commodity-image">Commodity Image</label>
                <input type="file" id="commodity-image" name="commodity_image" accept="image/*">

                <div class="button-container">
                    <button type="button" class="next-btn" onclick="window.location.href='add_commodity.php'">&larr; Previous</button>
                    <button type="submit" class="next-btn">Done &rarr;</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Store countries array for JavaScript use
        const countries = <?php echo json_encode($countries); ?>;
        
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
                <div style="flex: 1;">
                    <label>Commodity Alias</label>
                    <input type="text" name="commodity_alias[]">
                </div>
                <div style="flex: 1;">
                    <label>Country</label>
                    <select name="country[]" required>
                        ${countryOptions}
                    </select>
                </div>
                <button type="button" class="remove-btn" onclick="removeAliasCountry(this)">×</button>
            `;
            container.appendChild(newGroup);

            // Automatically scroll to the newly added fields
            container.scrollTop = container.scrollHeight;
        }

        // Function to remove an alias-country group
        function removeAliasCountry(button) {
            const group = button.closest('.alias-country-group');
            if (group) {
                group.remove();
            }
        }
    </script>
</body>
</html>