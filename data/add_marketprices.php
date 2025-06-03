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
    }
} else {
    error_log("Error: Database connection not established in add_marketprices.php.");
}

// Function to convert currency to USD (replace with actual conversion logic if needed)
function convertToUSD($amount, $country) {
    if (!is_numeric($amount)) {
        return 0;
    }

    switch ($country) {
        case 'Kenya':
            return round($amount / 150, 2);
        case 'Uganda':
            return round($amount / 3700, 2);
        case 'Tanzania':
            return round($amount / 2300, 2);
        case 'Rwanda':
            return round($amount / 1200, 2);
        case 'Burundi':
            return round($amount / 2000, 2);
        default:
            return round($amount, 2);
    }
}

// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    error_log("--- Form Submission ---");
    error_log("POST data received: " . print_r($_POST, true));

    // Initialize variables and sanitize input
    $country = isset($_POST['country']) ? mysqli_real_escape_string($con, $_POST['country']) : '';
    $market_id = isset($_POST['market']) ? (int)$_POST['market'] : 0;
    $category_name = isset($_POST['category']) ? mysqli_real_escape_string($con, $_POST['category']) : '';
    $commodity_id = isset($_POST['commodity']) ? (int)$_POST['commodity'] : 0;
    $packaging_unit = isset($_POST['packaging_unit']) ? mysqli_real_escape_string($con, $_POST['packaging_unit']) : '';
    $measuring_unit = isset($_POST['measuring_unit']) ? mysqli_real_escape_string($con, $_POST['measuring_unit']) : '';
    $variety = isset($_POST['variety']) ? mysqli_real_escape_string($con, $_POST['variety']) : '';
    $data_source = isset($_POST['data_source']) ? mysqli_real_escape_string($con, $_POST['data_source']) : '';
    $wholesale_price = isset($_POST['wholesale_price']) ? (float)$_POST['wholesale_price'] : 0;
    $retail_price = isset($_POST['retail_price']) ? (float)$_POST['retail_price'] : 0;

    // Validate required fields
    if (empty($country) || $market_id <= 0 || empty($category_name) || $commodity_id <= 0 ||
        empty($packaging_unit) || empty($measuring_unit) ||
        $wholesale_price <= 0 || $retail_price <= 0) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // Get current date and derived values
    $date_posted = date('Y-m-d H:i:s');
    $status = 'pending';
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $subject = "Market Prices";
    $country_admin_0 = $country;

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

    // Fixed SQL query - using commodity_name instead of commodity_id
    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Wholesale', ?, ?, ?, ?, ?, ?, ?, ?, ?),
                   (?, ?, ?, ?, ?, ?, ?, 'Retail', ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $con->prepare($sql);
    if ($stmt) {
        // Fixed bind_param - using commodity_name instead of commodity_id
        $stmt->bind_param(
            "sssisssdsiiisssssssisssdsiiissss",
            $category_name, $commodity_name, $country_admin_0, $market_id, $market_name, $packaging_unit, $measuring_unit, $wholesale_price_usd, $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source,
            $category_name, $commodity_name, $country_admin_0, $market_id, $market_name, $packaging_unit, $measuring_unit, $retail_price_usd, $subject, $day, $month, $year, $date_posted, $status, $variety, $data_source
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: Arial, sans-serif; /* Adjusted to your commodity page */
            background-color: #f8f8f8; /* Adjusted to your commodity page */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .container { /* Renamed from main-wrapper to container for consistency with commodity page */
            background: white;
            border-radius: 8px; /* Adjusted to your commodity page */
            max-width: 1200px; /* Increased max-width to accommodate sidebar, adjusted to your commodity page */
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Adjusted to your commodity page */
            position: relative;
            display: flex;
            min-height: 600px; /* Adjusted to your commodity page */
            overflow: hidden;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 30px; /* Adjusted to your commodity page */
            border: none;
            background: transparent;
            cursor: pointer;
            color: #333; /* Adjusted to your commodity page */
            z-index: 10;
            transition: color 0.2s ease-in-out;
        }
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }

        /* Left sidebar for steps */
        .steps-sidebar {
            width: 250px; /* Adjusted to your commodity page */
            background-color: #f8f9fa; /* Adjusted to your commodity page */
            padding: 40px 30px;
            border-radius: 8px 0 0 8px; /* Adjusted to your commodity page */
            border-right: 1px solid #e9ecef; /* Adjusted to your commodity page */
            position: relative;
            flex-shrink: 0;
            color: #333; /* Adjusted for consistency */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .steps-sidebar h3 {
            color: #333; /* Adjusted for consistency */
            margin-bottom: 30px;
            font-size: 18px; /* Adjusted to your commodity page */
            font-weight: bold;
            text-align: left; /* Aligned left for consistency */
        }
        .steps-container {
            position: relative;
            padding-left: 0; /* Adjusted for consistency */
        }
        /* Vertical connecting line */
        .steps-container::before {
            content: '';
            position: absolute;
            left: 22.5px; /* Adjusted to your commodity page */
            top: 45px; /* Adjusted to your commodity page */
            bottom: 0;
            width: 2px;
            background-color: #e9ecef; /* Adjusted to your commodity page */
            z-index: 1;
            display: block; /* Ensure it's visible */
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 60px; /* Adjusted to your commodity page */
            position: relative;
            z-index: 2;
        }
        .step:last-child {
            margin-bottom: 0;
        }
        .step-circle {
            width: 45px; /* Adjusted to your commodity page */
            height: 45px; /* Adjusted to your commodity page */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px; /* Adjusted to your commodity page */
            font-size: 16px; /* Adjusted to your commodity page */
            font-weight: bold;
            background-color: #e9ecef; /* Adjusted to your commodity page */
            color: #6c757d; /* Adjusted to your commodity page */
            position: relative;
            flex-shrink: 0;
            border: none; /* No border for consistency */
        }
        .step-circle.active {
            background-color: rgba(180, 80, 50, 1); /* Adjusted to your commodity page */
            color: white; /* Adjusted to your commodity page */
            box-shadow: none; /* No extra shadow for consistency */
        }
        .step-circle.active::after {
            content: ''; /* No checkmark for current active step */
        }
        .step-circle[data-step]::after {
            content: attr(data-step); /* Display step number */
        }
        .step-text {
            font-weight: 500; /* Adjusted to your commodity page */
            color: #6c757d; /* Adjusted to your commodity page */
        }
        .step.active .step-text {
            color: rgba(180, 80, 50, 1); /* Adjusted to your commodity page */
            font-weight: bold;
        }

        /* Main content area */
        .main-content {
            flex: 1;
            padding: 40px;
        }
        h2 {
            margin-bottom: 10px; /* Adjusted to your commodity page */
            color: #333; /* Adjusted to your commodity page */
            font-size: 2rem; /* Kept from previous for better heading size */
        }
        p {
            margin-bottom: 30px; /* Adjusted to your commodity page */
            color: #666; /* Adjusted to your commodity page */
            font-size: 1.1em; /* Kept from previous for better paragraph size */
        }

        /* Form styling */
        .form-group-full { /* Added for full width elements */
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 20px; /* Adjusted to your commodity page */
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px; /* Adjusted to your commodity page */
        }
        .form-row > .form-group {
            flex: 1;
            display: flex; /* Ensure labels and inputs stack */
            flex-direction: column;
            margin-bottom: 0; /* Override default margin-bottom */
        }
        label {
            margin-bottom: 8px; /* Adjusted to your commodity page */
            font-weight: bold; /* Adjusted to your commodity page */
            color: #333; /* Adjusted to your commodity page */
        }
        .required::after {
            content: " *";
            color: #dc3545;
            margin-left: 4px; /* Added for spacing */
        }
        input[type="text"],
        input[type="number"], /* Added number type */
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select {
            padding: 10px; /* Adjusted to your commodity page */
            border: 1px solid #ccc; /* Adjusted to your commodity page */
            border-radius: 5px; /* Adjusted to your commodity page */
            font-size: 14px; /* Adjusted to your commodity page */
            margin-bottom: 0;
            height: auto; /* Allow height to adjust */
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5); /* Adjusted to your commodity page */
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3); /* Adjusted to your commodity page */
        }
        input[readonly] {
            background-color: #e9ecef; /* Lighter background for readonly fields */
            opacity: 1;
            color: #6c757d;
            cursor: not-allowed;
        }

        /* Navigation buttons */
        .button-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px; /* Adjusted to your commodity page */
        }
        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 12px 30px; /* Adjusted to your commodity page */
            border: none;
            border-radius: 5px; /* Adjusted to your commodity page */
            cursor: pointer;
            font-size: 16px; /* Adjusted to your commodity page */
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .next-btn:hover {
            background-color: rgba(160, 60, 30, 1); /* Adjusted to your commodity page */
            transform: translateY(-2px);
        }
        .next-btn:active {
            transform: translateY(0);
        }

        /* Select2 customization to fit Bootstrap form-control height */
        .select2-container--default .select2-selection--single {
            height: auto; /* Allow height to adjust based on padding */
            padding: 10px; /* Match standard input padding */
            border: 1px solid #ccc; /* Match standard input border */
            border-radius: 5px; /* Match standard input border-radius */
            font-size: 14px; /* Match standard input font size */
            transition: all 0.2s ease-in-out;
            box-shadow: none; /* Remove default Select2 shadow */
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal; /* Reset line-height */
            padding: 0; /* Remove internal padding */
            color: #333;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: auto; /* Allow height to adjust */
            top: 50%;
            transform: translateY(-50%);
            right: 10px;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #6c757d;
        }
        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: rgba(180, 80, 50, 0.5); /* Match focus style */
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3); /* Match focus style */
        }
        /* Style for Select2 dropdown results */
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: rgba(180, 80, 50, 0.9);
            color: white;
        }
        .select2-container--default .select2-results__option--selected {
            background-color: #f8f9fa;
            color: #495057;
        }


        /* Responsive design */
        @media (max-width: 768px) { /* Adjusted breakpoint for consistency */
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
            .form-row > .form-group {
                margin-bottom: 20px; /* Restore margin when stacked */
            }
            .next-btn {
                width: 100%;
                margin-right: auto;
                margin-left: auto; /* Center button */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>

        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step completed">
                    <div class="step-circle completed" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Market Prices</small></div>
                </div>
                </div>
        </div>

        <div class="main-content">
            <h2>Add New Market Price</h2>
            <p>Please provide the details below to add new market price data.</p>
            <form method="POST" action="">
                <div class="form-group-full">
                    <label for="country" class="form-label required">Country</label>
                    <select name="country" id="country" class="form-select" required>
                        <option value="Kenya">Kenya</option>
                        <option value="Uganda">Uganda</option>
                        <option value="Tanzania">Tanzania</option>
                        <option value="Rwanda">Rwanda</option>
                        <option value="Burundi">Burundi</option>
                    </select>
                </div>

                <div class="form-group-full">
                    <label for="market" class="form-label required">Market</label>
                    <select name="market" id="market" class="form-select" required>
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
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category" class="form-label required">Category</label>
                        <select name="category" id="category" class="form-select" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="Cereals">Cereals</option>
                            <option value="Pulses">Pulses</option>
                            <option value="Oil seeds">Oil Seeds</option>
                             <option value="Vegetables">Vegetables</option>
                            <option value="Fruits">Fruits</option>
                            <option value="Livestock">Livestock</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="commodity" class="form-label required">Commodity</label>
                        <select name="commodity" id="commodity" class="form-select" required>
                            <option value="" disabled selected>Select Market first</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="packaging_unit" class="form-label required">Packaging Unit</label>
                        <input type="text" name="packaging_unit" id="packaging_unit" class="form-control" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="measuring_unit" class="form-label required">Measuring Unit</label>
                        <input type="text" name="measuring_unit" id="measuring_unit" class="form-control" required readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="variety" class="form-label">Variety</label>
                        <input type="text" name="variety" id="variety" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="data_source" class="form-label">Data Source</label>
                        <input type="text" name="data_source" id="data_source" class="form-control" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="wholesale_price" class="form-label required">Wholesale Price</label>
                        <input type="number" step="0.01" name="wholesale_price" id="wholesale_price"
                               placeholder="e.g., 150.00" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="retail_price" class="form-label required">Retail Price</label>
                        <input type="number" step="0.01" name="retail_price" id="retail_price"
                               placeholder="e.g., 180.50" class="form-control" required>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" name="submit" class="next-btn">
                        Add Market Price <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 on your select elements
        $(document).ready(function() {
            $('#country').select2({
                placeholder: "Select Country",
                allowClear: true,
                width: '100%' // Added for consistency
            });
            $('#market').select2({
                placeholder: "Select Market",
                allowClear: true,
                width: '100%' // Added for consistency
            });
            $('#category').select2({
                placeholder: "Select Category",
                allowClear: true,
                width: '100%' // Added for consistency
            });
            $('#commodity').select2({
                placeholder: "Select Commodity",
                allowClear: true,
                width: '100%' // Added for consistency
            });

            // Initial call if a market is pre-selected (e.g., after form submission error)
            if ($('#market').val()) {
                loadCommoditiesForMarket($('#market').val());
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const dataSourceInput = document.getElementById('data_source');
            const packagingUnitInput = document.getElementById('packaging_unit');
            const measuringUnitInput = document.getElementById('measuring_unit');
            const varietyInput = document.getElementById('variety');

            let currentMarketCommodities = [];

            function loadCommoditiesForMarket(marketId) {
                // Clear and reset Select2 for commodity
                $('#commodity').empty().append('<option value="" disabled selected>Loading commodities...</option>').trigger('change');
                // Clear and reset Select2 for category
                $('#category').val(null).trigger('change');
                dataSourceInput.value = '';
                packagingUnitInput.value = '';
                measuringUnitInput.value = '';
                varietyInput.value = '';
                currentMarketCommodities = [];

                if (!marketId) {
                    $('#commodity').empty().append('<option value="" disabled selected>Select Market first</option>').trigger('change');
                    return;
                }

                fetch(`get_market_commodities.php?market_id=${marketId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Clear existing options before adding new ones
                        $('#commodity').empty().append('<option value="" disabled selected>Select Commodity</option>');
                        
                        if (data.success && data.data && data.data.commodities && data.data.commodities.length > 0) {
                            currentMarketCommodities = data.data.commodities;
                            
                            // Populate commodity dropdown
                            data.data.commodities.forEach(commodity => {
                                const option = new Option(commodity.name, commodity.id, false, false);
                                $('#commodity').append(option);
                            });

                            // Set data source from market
                            if (data.data.data_source) {
                                dataSourceInput.value = data.data.data_source;
                            }

                        } else {
                            $('#commodity').append('<option value="" disabled selected>No commodities found for this market</option>');
                            if (data.message) {
                                console.warn("Server message:", data.message);
                            }
                        }
                        // Trigger Select2 to update its visual display after options are loaded
                        $('#commodity').trigger('change');
                    })
                    .catch(error => {
                        console.error('Error fetching commodities:', error);
                        $('#commodity').empty().append('<option value="" disabled selected>Error loading commodities</option>').trigger('change');
                    });
            }

            function setCommodityDetails(commodityId) {
                // Clear and reset Select2 for category
                $('#category').val(null).trigger('change');
                packagingUnitInput.value = '';
                measuringUnitInput.value = '';
                varietyInput.value = '';

                if (!commodityId) {
                    return;
                }

                // Find the selected commodity's details
                const selectedCommodity = currentMarketCommodities.find(
                    commodity => String(commodity.id) === String(commodityId)
                );

                if (selectedCommodity) {
                    // Set variety
                    if (selectedCommodity.variety) {
                        varietyInput.value = selectedCommodity.variety;
                    }

                    // Set packaging unit and measuring unit from units array
                    if (selectedCommodity.units && selectedCommodity.units.length > 0) {
                        const unit = selectedCommodity.units[0]; // Use first unit
                        packagingUnitInput.value = unit.size;
                        measuringUnitInput.value = unit.unit;
                    }

                    // Set category if available in selectedCommodity (assuming your get_market_commodities.php provides it)
                    if (selectedCommodity.category_name) {
                        // Use Select2's method to set value and trigger change
                        $('#category').val(selectedCommodity.category_name).trigger('change');
                    }
                } else {
                    console.warn(`Commodity with ID ${commodityId} not found in fetched list.`);
                }
            }

            // Event listeners - use jQuery for Select2-managed elements for consistency
            $('#market').on('change', function() {
                loadCommoditiesForMarket($(this).val());
            });

            $('#commodity').on('change', function() {
                setCommodityDetails($(this).val());
            });
        });
    </script>
</body>
</html>