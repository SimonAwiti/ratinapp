<?php
// add_marketprices.php

// Include your database configuration file
include '../admin/includes/config.php';

$markets = [];

if (isset($con)) {
    // Fetch market names and IDs from the database
    $markets_query = "SELECT id, market_name FROM markets";
    $markets_result = $con->query($markets_query);

    if ($markets_result) {
        if ($markets_result->num_rows > 0) {
            while ($row = $markets_result->fetch_assoc()) {
                $markets[] = [
                    'id' => $row['id'],
                    'market_name' => $row['market_name'],
                ];
            }
        }
        $markets_result->free();
    } else {
        error_log("Error fetching markets: " . $con->error);
        // In a production environment, you might display a user-friendly message
        // echo "Error fetching market data. Please try again later.";
    }
} else {
    error_log("Error: Database connection not established in add_marketprices.php.");
    // In a production environment, you might display a user-friendly message
    // echo "Error: System is currently undergoing maintenance. Please try again later.";
}

// Function to convert currency to USD (replace with actual conversion logic if needed)
function convertToUSD($amount, $country) {
    // Ensure amount is numeric
    if (!is_numeric($amount)) {
        return 0;
    }

    // This is a placeholder for the actual conversion logic.
    switch ($country) {
        case 'Kenya':
            return round($amount / 150, 2); // 1 USD = 150 KES (Example rate)
        case 'Uganda':
            return round($amount / 3700, 2); // 1 USD = 3700 UGX (Example rate)
        case 'Tanzania':
            return round($amount / 2300, 2); // 1 USD = 2300 TZS (Example rate)
        case 'Rwanda':
            return round($amount / 1200, 2); // 1 USD = 1200 RWF (Example rate)
        case 'Burundi':
            return round($amount / 2000, 2); // 1 USD = 2000 BIF (Example rate)
        default:
            return round($amount, 2); // Default to USD if country not found
    }
}

// Processing the form submission only when all fields are submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // --- DEBUGGING: Log POST data ---
    error_log("--- Form Submission ---");
    error_log("POST data received: " . print_r($_POST, true));
    // -----------------------------------

    // Initialize variables and sanitize input
    $country = isset($_POST['country']) ? mysqli_real_escape_string($con, $_POST['country']) : '';
    $market_id = isset($_POST['market']) ? (int)$_POST['market'] : 0;
    $category_name = isset($_POST['category']) ? mysqli_real_escape_string($con, $_POST['category']) : ''; // This is the category name, not ID
    $commodity_id = isset($_POST['commodity']) ? (int)$_POST['commodity'] : 0;
    $packaging_unit = isset($_POST['packaging_unit']) ? mysqli_real_escape_string($con, $_POST['packaging_unit']) : '';
    $measuring_unit = isset($_POST['measuring_unit']) ? mysqli_real_escape_string($con, $_POST['measuring_unit']) : '';
    $variety = isset($_POST['variety']) ? mysqli_real_escape_string($con, $_POST['variety']) : '';
    $data_source = isset($_POST['data_source']) ? mysqli_real_escape_string($con, $_POST['data_source']) : '';
    $wholesale_price = isset($_POST['wholesale_price']) ? (float)$_POST['wholesale_price'] : 0;
    $retail_price = isset($_POST['retail_price']) ? (float)$_POST['retail_price'] : 0;

    // Validate required fields (variety and data_source are now optional in PHP based on NULLable columns)
    if (empty($country) || $market_id <= 0 || empty($category_name) || $commodity_id <= 0 ||
        empty($packaging_unit) || empty($measuring_unit) ||
        $wholesale_price <= 0 || $retail_price <= 0) {
        echo "<script>alert('Please fill all required fields with valid values (except Variety and Data Source which are optional).'); window.history.back();</script>";
        exit;
    }

    // Get current date and derived values
    $date_posted = date('Y-m-d H:i:s');
    $status = 'pending';
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $subject = "Market Prices";
    $country_admin_0 = $country; // Assuming this maps to country

    // Convert prices to USD
    $wholesale_price_usd = convertToUSD($wholesale_price, $country);
    $retail_price_usd = convertToUSD($retail_price, $country);

    // Fetch market name based on market_id
    $market_name = "";
    $stmt_market = $con->prepare("SELECT market_name FROM markets WHERE id = ?");
    $stmt_market->bind_param("i", $market_id);
    $stmt_market->execute();
    $market_name_result = $stmt_market->get_result();
    if ($market_name_result && $market_name_result->num_rows > 0) {
        $market_name_row = $market_name_result->fetch_assoc();
        $market_name = $market_name_row['market_name'];
    }
    $stmt_market->close();

    // Fetch commodity name based on commodity_id
    $commodity_name = "";
    $stmt_commodity = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt_commodity->bind_param("i", $commodity_id);
    $stmt_commodity->execute();
    $commodity_name_result = $stmt_commodity->get_result();
    if ($commodity_name_result && $commodity_name_result->num_rows > 0) {
        $commodity_name_row = $commodity_name_result->fetch_assoc();
        $commodity_name = $commodity_name_row['commodity_name'];
    }
    $stmt_commodity->close();

    // Prepare and execute the SQL query using prepared statements for security
    // Note: The 'category' column in market_prices stores the name, not ID.
    // 'commodity' column stores the name, not ID.
    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Wholesale', ?, ?, ?, ?, ?, ?, ?, ?, ?),
                   (?, ?, ?, ?, ?, ?, ?, 'Retail', ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $con->prepare($sql);
    if ($stmt) {
        // CORRECTED LINE from previous debugging: The type definition string now accurately matches the 32 bind variables.
        $stmt->bind_param(
            "sssisssdsiiisssssssisssdsiiissss", // 16 variables * 2 rows = 32 characters
            $category_name, $commodity_id, $country_admin_0, $market_id, $market_name, $packaging_unit, $measuring_unit, $wholesale_price_usd, $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source,
            $category_name, $commodity_id, $country_admin_0, $market_id, $market_name, $packaging_unit, $measuring_unit, $retail_price_usd, $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source
        );

        if ($stmt->execute()) {
            echo "<script>alert('New records created successfully'); window.location.href='../base/sidebar.php';</script>";
        } else {
            error_log("Error inserting market prices: " . $stmt->error);
            echo "<script>alert('Error inserting records: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        error_log("Error preparing market prices insert statement: " . $con->error);
        echo "<script>alert('Error preparing statement: " . $con->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Market Price Data</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
    <style>
        /* Embedding CSS directly for simplicity in this example */
        <?php include '../base/assets/add_commodity.css'; ?>
    </style>
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
            width: 800px;
            height: 700px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative;
        }
        h2 {
            margin-bottom: 10px;
        }
        p {
            margin-bottom: 10px;
        }
        form label:first-of-type {
            margin-top: 10px;
        }

        .form-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        input, select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        .next-btn:hover {
            background-color:rgba(180, 80, 50, 1);
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
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Market Price Data</h2>
            <p>Provide the details below to Add Market Price Data</p>
            <form method="POST" action="">
                <label for="country">Country *</label>
                <select name="country" id="country" required>
                    <option value="Kenya">Kenya</option>
                    <option value="Uganda">Uganda</option>
                    <option value="Tanzania">Tanzania</option>
                    <option value="Rwanda">Rwanda</option>
                    <option value="Burundi">Burundi</option>
                </select>

                <label for="market">Market *</label>
                <select name="market" id="market" required>
                    <?php if (empty($markets)): ?>
                        <option value="" disabled>No markets available</option>
                    <?php else: ?>
                        <option value="" disabled selected>Select Market</option>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?php echo htmlspecialchars($market['id']); ?>">
                                <?php echo htmlspecialchars($market['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select name="category" id="category" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="Cereals">Cereals</option>
                            <option value="Pulses">Pulses</option>
                            <option value="Oil seeds">Oil Seeds</option>
                            </select>
                    </div>
                    <div class="form-group">
                        <label for="commodity">Commodity *</label>
                        <select name="commodity" id="commodity" required>
                            <option value="" disabled selected>Select Commodity</option>
                            </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="packaging_unit">Packaging Unit *</label>
                        <input type="text" name="packaging_unit" id="packaging_unit"
                               value="<?php echo isset($_POST['packaging_unit']) ? htmlspecialchars($_POST['packaging_unit']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="measuring_unit">Measuring Unit *</label>
                        <select name="measuring_unit" id="measuring_unit" required>
                            <option value="" disabled selected>Select Unit</option>
                            <option value="kg" <?php echo (isset($_POST['measuring_unit']) && $_POST['measuring_unit'] == 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                            <option value="tons" <?php echo (isset($_POST['measuring_unit']) && $_POST['measuring_unit'] == 'tons') ? 'selected' : ''; ?>>Tons</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="variety">Variety *</label>
                        <input type="text" name="variety" id="variety"
                               value="<?php echo isset($_POST['variety']) ? htmlspecialchars($_POST['variety']) : ''; ?>"> </div>
                    <div class="form-group">
                        <label for="data_source">Data Source *</label>
                        <input type="text" name="data_source" id="data_source"
                               value="<?php echo isset($_POST['data_source']) ? htmlspecialchars($_POST['data_source']) : ''; ?>"> </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="wholesale_price">Wholesale Price *</label>
                        <input type="number" step="0.01" name="wholesale_price" id="wholesale_price"
                               value="<?php echo isset($_POST['wholesale_price']) ? htmlspecialchars($_POST['wholesale_price']) : ''; ?>"
                               placeholder="e.g., 150.00" required>
                    </div>
                    <div class="form-group">
                        <label for="retail_price">Retail Price *</label>
                        <input type="number" step="0.01" name="retail_price" id="retail_price"
                               value="<?php echo isset($_POST['retail_price']) ? htmlspecialchars($_POST['retail_price']) : ''; ?>"
                               placeholder="e.g., 180.50" required>
                    </div>
                </div>

                <button type="submit" name="submit" class="next-btn">Done →</button>
            </form>
        </div>
    </div>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const marketSelect = document.getElementById('market');
            const commoditySelect = document.getElementById('commodity');
            const categorySelect = document.getElementById('category');
            const dataSourceInput = document.getElementById('data_source');

            // Store fetched commodities data to easily look up category/data_source when commodity changes
            let currentMarketCommodities = []; 

            function loadCommoditiesForMarket(marketId) {
                // Reset commodity dropdown and auto-filled fields
                commoditySelect.innerHTML = '<option value="" disabled selected>Loading commodities...</option>'; // Changed text
                categorySelect.value = ""; // Clear selected category
                dataSourceInput.value = ''; // Clear data source
                currentMarketCommodities = []; // Clear stored data

                if (!marketId) {
                    commoditySelect.innerHTML = '<option value="" disabled selected>Select Market first</option>';
                    return;
                }

                fetch(`../data/get_commodities_by_market.php?market_id=${marketId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        commoditySelect.innerHTML = '<option value="" disabled selected>Select Commodity</option>';
                        if (data.success && data.data && data.data.length > 0) {
                            currentMarketCommodities = data.data; // Store all commodities returned
                            
                            // Populate commodity dropdown
                            data.data.forEach(commodity => {
                                const option = document.createElement('option');
                                option.value = commodity.commodity_id;
                                option.textContent = commodity.commodity_name;
                                commoditySelect.appendChild(option);
                            });

                            // Auto-select the first commodity if only one is returned, or if desired
                            // For Rongai, this will populate with both 34 and 35
                            if (data.data.length > 0) { // Check if any commodities are loaded
                                commoditySelect.value = data.data[0].commodity_id; // Select the first one by default
                                setCategoryAndDataSourceForCommodity(data.data[0].commodity_id);
                            }

                        } else {
                            commoditySelect.innerHTML = '<option value="" disabled selected>No commodities found for this market</option>';
                            if (data.message) {
                                console.warn("Server message:", data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching commodities:', error);
                        commoditySelect.innerHTML = '<option value="" disabled selected>Error loading commodities</option>';
                    });
            }

            function setCategoryAndDataSourceForCommodity(commodityId) {
                categorySelect.value = ""; // Reset before setting
                dataSourceInput.value = ''; // Reset before setting

                if (!commodityId) {
                    return; // No commodity selected
                }

                // Find the selected commodity's details from the `currentMarketCommodities` array
                const selectedCommodity = currentMarketCommodities.find(
                    commodity => String(commodity.commodity_id) === String(commodityId)
                );

                if (selectedCommodity) {
                    // Set Category dropdown based on 'category_name' from fetched data
                    if (selectedCommodity.category_name) {
                        const dbCategory = selectedCommodity.category_name.trim();
                        let foundCategory = false;
                        for (let i = 0; i < categorySelect.options.length; i++) {
                            const option = categorySelect.options[i];
                            if (option.value.trim() === dbCategory || option.textContent.trim() === dbCategory) {
                                option.selected = true;
                                foundCategory = true;
                                break;
                            }
                        }
                        if (!foundCategory) {
                            console.warn(`Category "${dbCategory}" from selected commodity is not a predefined option in the form.`);
                            categorySelect.value = ""; 
                        }
                    } else {
                        console.log("No category_name found for this commodity in fetched data.");
                        categorySelect.value = ""; 
                    }

                    // Set Data Source input field based on 'data_source' from fetched data
                    if (selectedCommodity.data_source) {
                       dataSourceInput.value = selectedCommodity.data_source.trim();
                    } else {
                       console.log("No data source found for this commodity from markets table.");
                       dataSourceInput.value = ''; 
                    }
                } else {
                    console.warn(`Commodity with ID ${commodityId} not found in fetched list.`);
                }
            }


            // Event listener for when the market selection changes
            marketSelect.addEventListener('change', function() {
                loadCommoditiesForMarket(this.value);
            });

            // Event listener for when the commodity selection changes
            commoditySelect.addEventListener('change', function() {
                setCategoryAndDataSourceForCommodity(this.value);
            });

            // Initial load: If a market was pre-selected (e.g., after a form submission error)
            if (marketSelect.value) {
                loadCommoditiesForMarket(marketSelect.value);
            } else {
                commoditySelect.innerHTML = '<option value="" disabled selected>Select Market first</option>';
            }
        });
    </script>
</body>
</html>