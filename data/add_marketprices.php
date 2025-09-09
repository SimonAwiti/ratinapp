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

// Function to convert currency to USD using database exchange rates
function convertToUSD($amount, $country, $con) {
    if (!is_numeric($amount)) {
        return 0;
    }

    $exchangeRate = 1; // Default to 1 (assuming 1:1 for USD or if no rate is found)

    // Fetch the most recent exchange rate for the given country from the database.
    // ORDER BY effective_date DESC, date_created DESC ensures the latest rate is picked.
    $stmt = $con->prepare("SELECT exchange_rate FROM currencies WHERE country = ? ORDER BY effective_date DESC, date_created DESC LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("s", $country);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $exchangeRate = (float)$row['exchange_rate'];
        } else {
            // Log a warning if no exchange rate is found for the country in the database.
            // The function will proceed with the default $exchangeRate = 1.
            error_log("No recent exchange rate found in DB for " . $country . ". Using default rate: " . $exchangeRate);
        }
        $stmt->close();
    } else {
        error_log("Error preparing currency query for " . $country . ": " . $con->error);
    }

    // Ensure exchangeRate is not zero to prevent division by zero errors.
    if ($exchangeRate == 0) {
        error_log("Exchange rate for " . $country . " is zero or invalid. Returning 0 for conversion to prevent division by zero.");
        return 0;
    }

    return round($amount / $exchangeRate, 2);
}


// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    error_log("--- Form Submission ---");
    error_log("POST data received: " . print_r($_POST, true));

    // Initialize variables and sanitize input
    $country = isset($_POST['country']) ? mysqli_real_escape_string($con, $_POST['country']) : '';
    $market_id = isset($_POST['market']) ? (int)$_POST['market'] : 0;
    $category_name = isset($_POST['category']) ? mysqli_real_escape_string($con, $_POST['category']) : '';
    $commodity_id = isset($_POST['commodity']) ? (int)$_POST['commodity'] : 0; // This is correctly the ID now
    $packaging_unit_raw = isset($_POST['packaging_unit']) ? mysqli_real_escape_string($con, $_POST['packaging_unit']) : ''; // e.g., "50 kg", "1 Ton"
    $measuring_unit = isset($_POST['measuring_unit']) ? mysqli_real_escape_string($con, $_POST['measuring_unit']) : ''; // e.g., "Kg", "Ton"
    $variety = isset($_POST['variety']) ? mysqli_real_escape_string($con, $_POST['variety']) : '';
    $data_source = isset($_POST['data_source']) ? mysqli_real_escape_string($con, $_POST['data_source']) : '';
    $wholesale_price_input = isset($_POST['wholesale_price']) ? (float)$_POST['wholesale_price'] : 0; // User entered price for the packaging unit
    $retail_price_input = isset($_POST['retail_price']) ? (float)$_POST['retail_price'] : 0; // User entered price (assumed per measuring_unit)

    // Validate required fields
    if (empty($country) || $market_id <= 0 || empty($category_name) || $commodity_id <= 0 ||
        empty($packaging_unit_raw) || empty($measuring_unit) ||
        $wholesale_price_input <= 0 || $retail_price_input <= 0) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // --- Wholesale Price per Single Measuring Unit Calculation ---
    $wholesale_price_per_measuring_unit = 0;
    $packaging_unit_number = 0;

    // Extract the numerical part from packaging_unit_raw (e.g., "50" from "90" (from POST data))
    // This regex looks for a number (integer or decimal) at the beginning of the string.
    if (preg_match('/^(\d+(\.\d+)?)/', $packaging_unit_raw, $matches)) {
        $packaging_unit_number = (float)$matches[1];
    } else {
        // If it's just a number like "90" without text, preg_match will still capture it.
        // If it's pure text or fails for some reason, default to 1.
        // Given your POST data example `[packaging_unit] => 90`, this regex will correctly extract 90.
        $packaging_unit_number = (float)$packaging_unit_raw; // Fallback to direct conversion if regex fails for pure number
        if ($packaging_unit_number == 0) { // If still 0 after direct conversion
             error_log("Warning: Could not extract valid number from packaging unit '{$packaging_unit_raw}'. Defaulting to 1 for calculation.");
             $packaging_unit_number = 1; // Default to 1 to avoid division by zero
        }
    }


    // Ensure packaging_unit_number is not zero to prevent division by zero
    if ($packaging_unit_number == 0) {
        error_log("Error: Numerical value for packaging unit '{$packaging_unit_raw}' is zero or could not be extracted. Cannot calculate wholesale price per measuring unit.");
        echo "<script>alert('Error: Invalid packaging unit for wholesale price calculation. Please check the commodity details.'); window.history.back();</script>";
        exit;
    }

    // Calculate wholesale price per single measuring unit
    $wholesale_price_per_measuring_unit = $wholesale_price_input / $packaging_unit_number;


    // Retail price calculation (as-is, assuming user enters per measuring_unit)
    $retail_price_per_measuring_unit = $retail_price_input;


    // Convert prices per measuring unit to USD
    $wholesale_price_usd = convertToUSD($wholesale_price_per_measuring_unit, $country, $con);
    $retail_price_usd = convertToUSD($retail_price_per_measuring_unit, $country, $con);


    // Get current date and derived values
    $date_posted = date('Y-m-d H:i:s');
    $status = 'pending';
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $subject = "Market Prices";
    $country_admin_0 = $country;

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

    // No longer strictly need commodity_name for INSERT, but fetching it for completeness or other uses
    // as it was in previous versions of the code.
    $commodity_name = ""; // Keep this line to avoid undefined variable notice if it's used elsewhere
    $stmt_commodity = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt_commodity->bind_param("i", $commodity_id);
    $stmt_commodity->execute();
    $commodity_name_result = $stmt_commodity->get_result();
    if ($commodity_name_result && $commodity_name_result->num_rows > 0) {
        $commodity_name_row = $commodity_name_result->fetch_assoc();
        $commodity_name = $commodity_name_row['commodity_name'];
    }
    $stmt_commodity->close();


    // SQL QUERY: Targeting 'commodity' column with 'commodity_id' (which is INT)
    // The columns are: category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source
    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Wholesale', ?, ?, ?, ?, ?, ?, ?, ?, ?),
                   (?, ?, ?, ?, ?, ?, ?, 'Retail', ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $con->prepare($sql);
    if ($stmt) {
        // BIND_PARAM: 's' (for category), 'i' (for commodity_id), 's' (for country_admin_0), 'i' (for market_id), etc.
        // The type string for one set of values is: "sissssdsiiisssss"
        // Repeated for two sets of values: "sissssdsiiissssssissssdsiiisssss"
        $stmt->bind_param(
            "sissssdsiiissssssissssdsiiisssss",
            // Wholesale Record Parameters
            $category_name,
            $commodity_id,        // Binding commodity_id (integer) to the 'commodity' column
            $country_admin_0,
            $market_id,
            $market_name,
            $packaging_unit_raw,  // Stores original packaging unit (e.g., "90")
            $measuring_unit,      // Stores the calculated unit (e.g., "Kg")
            $wholesale_price_usd,
            $subject,
            $day,
            $month,
            $year,
            $date_posted,
            $status,
            $variety,
            $data_source,

            // Retail Record Parameters
            $category_name,
            $commodity_id,        // Binding commodity_id (integer) to the 'commodity' column
            $country_admin_0,
            $market_id,
            $market_name,
            $packaging_unit_raw,  // Assuming for retail, you want to store the same 'weight' description
            $measuring_unit,      // Stores the unit for retail price (e.g., "Kg")
            $retail_price_usd,
            $subject,
            $day,
            $month,
            $year,
            $date_posted,
            $status,
            $variety,
            $data_source
        );

        if ($stmt->execute()) {
            echo "<script>alert('New records created successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
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
    
        .step.completed .step-text {
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        .step-circle.completed::after {
            content: '✓';
            font-family: 'Font Awesome 6 Free'; /* For consistent checkmark icon */
            font-weight: 900;
            font-size: 20px;
        }
        .step-circle.completed {
            background-color: rgba(180, 80, 50, 1);
            color: white;
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
        <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>

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
                               placeholder="Enter the price for **entire packaging unit displayed above**" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="retail_price" class="form-label required">Retail Price</label>
                        <input type="number" step="0.01" name="retail_price" id="retail_price"
                               placeholder="Enter the price **per measuring unit** " class="form-control" required>
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