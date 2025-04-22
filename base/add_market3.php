<?php
session_start();
include '../admin/includes/config.php'; // Include database configuration

// Redirect if session data is missing
if (!isset($_SESSION['category'])) {
    header('Location: add_market.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve data from session and form
    $market_name = $_SESSION['market_name'];
    $category = $_SESSION['category'];
    $type = $_SESSION['type'];
    $country = $_SESSION['country'];
    $county_district = $_SESSION['county_district'];
    $longitude = $_SESSION['longitude'];
    $latitude = $_SESSION['latitude'];
    $radius = $_SESSION['radius'];
    $currency = $_SESSION['currency'];
    $primary_commodities = isset($_POST['primary_commodity']) ? $_POST['primary_commodity'] : [];
    $additional_datasource = $_POST['additional_datasource'] ?? '';
    $image_url = $_SESSION['image_url'] ?? '';

    // Validate that at least one primary commodity is selected
    if (empty($primary_commodities)) {
        die("Error: At least one primary commodity is required.");
    }

    // Convert array of commodities to a comma-separated string
    $commodities_str = implode(',', $primary_commodities);

    // Insert data into the database using a transaction
    $con->begin_transaction();
    try {
        $sql = "INSERT INTO markets (market_name, category, type, country, county_district, longitude, latitude, radius, currency, primary_commodity, additional_datasource, image_url, tradepoint) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $con->prepare($sql);

        $tradepoint = "Market"; // or "Markets" depending on your naming

        $stmt->bind_param("sssssssssssss", 
        $market_name, 
        $category, 
        $type, 
        $country, 
        $county_district, 
        $longitude, 
        $latitude, 
        $radius, 
        $currency, 
        $commodities_str, 
        $additional_datasource,
        $image_url,
        $tradepoint
        );

        
        $stmt->execute();
        $con->commit();
    } catch (Exception $e) {
        $con->rollback();
        die("Database error: " . $e->getMessage());
    }

    // Clear session data
    session_unset();
    session_destroy();

    header('Location: dashboard.php');
    exit;
}

// Fetch commodities from the database
$commodities_query = "SELECT id, commodity_name FROM commodities";
$commodities_result = $con->query($commodities_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tradepoint</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 60px;
            border-radius: 8px;
            width: 850px;
            height: 700px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative;
        }
        h2, p {
            margin-bottom: 10px;
        }
        .form-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 70%;
        }
        .selection {
            background: #f7f7d8;
            padding: 20px;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 120px;
            flex-wrap: nowrap;
        }
        .selection label {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .selection input[type="radio"] {
            margin: 0;
            width: 16px;
            height: 16px;
            vertical-align: middle;
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            height: calc(100% - 45px - 100px);
            width: 1px;
            background-color: #ccc;
        }
        .container2 {
            max-width: 800px;
        }
        .input-box {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 30px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .tags-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .tag {
            display: flex;
            align-items: center;
            background-color: #d7db89;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
        }
        .tag button {
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 5px;
            font-size: 16px;
        }
        .steps {
            padding-right: 40px;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            height: calc(100% - 45px - 100px);
            width: 1px;
            background-color: #a45c40;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 250px;
            position: relative;
        }
        .step:last-child {
            margin-bottom: 0;
        }
        .step-circle {
            width: 45px;
            height: 45px;
            border-radius: 70%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 20px;
            font-size: 20px;
            z-index: 1;
            background-color: #d3d3d3;
            color: white;
            position: relative;
        }
        .step-circle::before {
            content: '✓';
            display: none;
        }
        .step-circle.active::before {
            display: block;
        }
        .step-circle.inactive::before {
            content: '';
        }
        .step-circle.active {
            background-color: #a45c40;
        }
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background-color: #ddd;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background-color: green;
            border-radius: 5px;
            transition: width 0.3s ease-in-out;
        }
        #imagePreview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .preview-image {
            position: relative;
            display: inline-block;
        }
        .preview-image img {
            width: 80px;
            height: 80px;
            border-radius: 5px;
            object-fit: cover;
        }
        .remove-img {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border: none;
            padding: 3px 6px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
        }
        .location-container {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .location-container label {
            width: 100%;
        }
        .location-container input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 60px;
        }
        /* Commodity Selector Styles */
        .commodity-selector {
            position: relative;
            margin-top: 30px;
        }
        .select-box {
            display: flex;
            flex-direction: column;
        }
        .select-box select {
            font-size: 16px;
            color: black;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: white;
            width: 100%;
        }
        .select-box option {
            color: black;
        }
        /* Hidden input for storing selected commodities */
        #selected_commodities {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='dashboard.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 2</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 3</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Tradepoint</h2>
            <p>Provide the necessary details to add a new tradepoint</p>
            <div class="selection">
                <label><input type="radio" name="tradepoint" checked> Markets</label>
                <label><input type="radio" name="tradepoint"> Border Points</label>
                <label><input type="radio" name="tradepoint"> Millers</label>
            </div>
            <form method="POST" action="add_market3.php" enctype="multipart/form-data">
                <div class="commodity-selector">
                    <label for="commodity_select">Assign primary commodities</label>
                    <div class="select-box">
                        <select id="commodity_select" multiple>
                            <option value="">Select commodities</option>
                            <?php
                            // Repopulate dropdown with commodities from the database
                            if ($commodities_result->num_rows > 0) {
                                while ($row = $commodities_result->fetch_assoc()) {
                                    echo "<option value='" . $row['id'] . "' data-name='" . $row['commodity_name'] . "'>" . $row['commodity_name'] . "</option>";
                                }
                            } else {
                                echo "<option value=''>No commodities available</option>";
                            }
                            ?>
                        </select>
                        <!-- Hidden input to store selected commodity IDs for form submission -->
                        <input type="hidden" id="selected_commodities" name="primary_commodity[]" value="">
                    </div>
                    <div class="tags-container" id="commodity_tags"></div>
                </div>
                
                <label for="additional_datasource">Additional data sources *</label>
                <input type="text" id="additional_datasource" name="additional_datasource" required>

                <!-- Buttons on the same line -->
                <div class="button-container">
                    <button type="button" class="next-btn" onclick="window.location.href='add_market2.php'">&larr; Previous</button>
                    <button type="submit" class="next-btn">done &rarr;</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commoditySelect = document.getElementById('commodity_select');
            const commodityTags = document.getElementById('commodity_tags');
            const selectedCommoditiesInput = document.getElementById('selected_commodities');
            let selectedCommodities = [];

            // Function to update the tags display
            function updateTags() {
                commodityTags.innerHTML = '';
                selectedCommoditiesInput.value = selectedCommodities.join(',');
                
                selectedCommodities.forEach(id => {
                    const option = commoditySelect.querySelector(`option[value="${id}"]`);
                    if (option) {
                        const tag = document.createElement('div');
                        tag.className = 'tag';
                        tag.innerHTML = `
                            ${option.textContent}
                            <button type="button" onclick="removeCommodity('${id}')">×</button>
                        `;
                        commodityTags.appendChild(tag);
                    }
                });
            }

            // Function to remove a commodity
            window.removeCommodity = function(id) {
                selectedCommodities = selectedCommodities.filter(item => item !== id);
                updateTags();
            };

            // Handle selection from dropdown
            commoditySelect.addEventListener('change', function() {
                Array.from(this.selectedOptions).forEach(option => {
                    if (option.value && !selectedCommodities.includes(option.value)) {
                        selectedCommodities.push(option.value);
                    }
                });
                
                // Reset the select
                this.selectedIndex = 0;
                updateTags();
            });

            // Initialize with any previously selected commodities
            updateTags();
        });
    </script>
</body>
</html>