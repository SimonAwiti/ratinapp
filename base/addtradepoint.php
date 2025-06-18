<?php
session_start();
include '../admin/includes/config.php';

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Function to check if market already exists
function checkMarketExists($con, $name, $category, $type) {
    $stmt = $con->prepare("SELECT id FROM markets WHERE market_name = ? AND category = ? AND type = ?");
    $stmt->bind_param("sss", $name, $category, $type);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Function to check if border point already exists
function checkBorderExists($con, $name) {
    $stmt = $con->prepare("SELECT id FROM border_points WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Function to check if miller already exists
function checkMillerExists($con, $name) {
    $stmt = $con->prepare("SELECT id FROM miller_details WHERE miller_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Fetch unique countries from commodity_sources
$countries = [];
$country_query = "SELECT DISTINCT admin0_country FROM commodity_sources ORDER BY admin0_country ASC";
$country_result = $con->query($country_query);
if ($country_result) {
    while ($row = $country_result->fetch_assoc()) {
        $countries[] = $row['admin0_country'];
    }
}

// Define the currency mapping (PHP-side, for initial load if 'Millers' is default)
$currency_map = [
    'Kenya' => 'KES',
    'Uganda' => 'UGX',
    'Tanzania' => 'TZS',
    'Rwanda' => 'RWF',
    'Burundi' => 'BIF',
    'South Sudan' => 'SSP',
    'Ethiopia' => 'ETB',
    'Somalia' => 'SOS',
    'Democratic Republic of Congo' => 'CDF',
    // Add more country-currency mappings as needed
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tradepoint = $_POST['tradepoint'];
    $_SESSION['tradepoint'] = $tradepoint;

    if ($tradepoint == "Markets") {
        if (
            empty($_POST['market_name']) ||
            empty($_POST['category']) ||
            empty($_POST['type']) ||
            empty($_POST['country']) ||
            empty($_POST['county_district'])
        ) {
            echo "<script>alert('All market fields are required!'); window.history.back();</script>";
            exit();
        }

        // Check if market already exists
        if (checkMarketExists($con, $_POST['market_name'], $_POST['category'], $_POST['type'])) {
            echo "<script>alert('A market with this name, category and type already exists!'); window.history.back();</script>";
            exit();
        }

        $_SESSION['market_name'] = $_POST['market_name'];
        $_SESSION['category'] = $_POST['category'];
        $_SESSION['type'] = $_POST['type'];
        $_SESSION['country'] = $_POST['country'];
        $_SESSION['county_district'] = $_POST['county_district'];

        header("Location: addtradepoint2.php");
        exit;
    }

    elseif ($tradepoint == "Border Points") {
        if (
            empty($_POST['border_name']) ||
            empty($_POST['border_country']) ||
            empty($_POST['border_county']) ||
            empty($_POST['longitude']) ||
            empty($_POST['latitude']) ||
            empty($_POST['radius'])
        ) {
            echo "<script>alert('All border point fields are required!'); window.history.back();</script>";
            exit();
        }

        // Check if border point already exists
        if (checkBorderExists($con, $_POST['border_name'])) {
            echo "<script>alert('A border point with this name already exists!'); window.history.back();</script>";
            exit();
        }

        $_SESSION['border_name'] = $_POST['border_name'];
        $_SESSION['border_country'] = $_POST['border_country'];
        $_SESSION['border_county'] = $_POST['border_county'];
        $_SESSION['longitude'] = $_POST['longitude'];
        $_SESSION['latitude'] = $_POST['latitude'];
        $_SESSION['radius'] = $_POST['radius'];

        header("Location: addtradepoint2.php");
        exit;
    }

    elseif ($tradepoint == "Millers") {
        // Retrieve the determined currency from the hidden input
        $miller_currency = $_POST['miller_currency_display_value']; // Get value from hidden input

        if (
            empty($_POST['miller_name']) ||
            empty($_POST['miller_country']) ||
            empty($_POST['miller_county_district']) ||
            empty($miller_currency) // Validate the hidden currency field
        ) {
            echo "<script>alert('All miller fields are required!'); window.history.back();</script>";
            exit();
        }

        // Check if miller already exists
        if (checkMillerExists($con, $_POST['miller_name'])) {
            echo "<script>alert('This town already has millers added, consider editing the town!'); window.history.back();</script>";
            exit();
        }

        $_SESSION['miller_name'] = $_POST['miller_name'];
        $_SESSION['country'] = $_POST['miller_country'];
        $_SESSION['county_district'] = $_POST['miller_county_district'];
        $_SESSION['currency'] = $miller_currency; // Store the collected currency

        header("Location: addtradepoint2.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tradepoint - Step 1</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Add Select2 CSS -->
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
            left: 22.5px; /* Center with step circles */
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
        
        .step-circle.active::after {
            content: '✓';
            font-size: 20px;
        }
        
        .step-circle:not(.active)::after {
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
        
        /* Tradepoint Selection */
        .tradepoint-selection {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .tradepoint-selection h5 {
            margin-bottom: 20px;
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        .selection-options {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .selection-options label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: #333;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .selection-options label:hover {
            background-color: rgba(180, 80, 50, 0.1);
        }
        .selection-options input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: rgba(180, 80, 50, 1);
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
        input, select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }
        
        /* Currency display */
        .currency-display {
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            background-color: #f8f9fa;
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        /* Tradepoint sections */
        .tradepoint-section {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        .tradepoint-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Next button */
        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .next-btn:hover {
            background-color: rgba(160, 60, 30, 1);
        }
        
        /* Section headers */
        .section-header {
            background-color: rgba(180, 80, 50, 0.1);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .section-header h6 {
            margin: 0;
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        .section-header p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Error messages */
        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        
        /* Select2 custom styling */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        .select2-container--default .select2-results__option--highlighted {
            background-color: rgba(180, 80, 50, 1);
        }
        .select2-container--default .select2-results__option--selected {
            background-color: rgba(180, 80, 50, 0.7);
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
            .selection-options {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        
        <!-- Left Sidebar with Steps -->
        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step active">
                    <div class="step-circle active" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Basic Info</small></div>
                </div>
                <div class="step">
                    <div class="step-circle" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Details</small></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <h2>Add Tradepoint - Step 1</h2>
            <p>Please provide the basic information for your tradepoint.</p>
            
            <!-- Tradepoint Selection -->
            <div class="tradepoint-selection">
                <h5>Select Tradepoint Type</h5>
                <div class="selection-options">
                    <label>
                        <input type="radio" name="tradepoint" value="Markets" checked>
                        <i class="fas fa-store"></i>
                        Markets
                    </label>
                    <label>
                        <input type="radio" name="tradepoint" value="Border Points">
                        <i class="fas fa-map-marker-alt"></i>
                        Border Points
                    </label>
                    <label>
                        <input type="radio" name="tradepoint" value="Millers">
                        <i class="fas fa-industry"></i>
                        Millers
                    </label>
                </div>
            </div>

            <form id="tradepoint-form" method="POST" action="">
                <!-- Add hidden input for tradepoint type -->
                <input type="hidden" id="tradepoint-type" name="tradepoint" value="Markets">
                
                <!-- Markets Section -->
                <div class="tradepoint-section active" id="market-fields">
                    <div class="section-header">
                        <h6><i class="fas fa-store"></i> Market Information</h6>
                        <p>Provide details about the market location and type</p>
                    </div>
                    
                    <div class="form-group-full">
                        <label for="market_name" class="required">Name of Market</label>
                        <input type="text" id="market_name" name="market_name" placeholder="Enter market name">
                        <div class="error-message" id="market_name_error">Market name is required</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category" class="required">Market Category</label>
                            <select id="category" name="category">
                                <option value="">Select category</option>
                                <option value="Consumer">Consumer</option>
                                <option value="Producer">Producer</option>
                            </select>
                            <div class="error-message" id="category_error">Market category is required</div>
                        </div>
                        <div class="form-group">
                            <label for="type" class="required">Market Type</label>
                            <select id="type" name="type">
                                <option value="">Select type</option>
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                            </select>
                            <div class="error-message" id="type_error">Market type is required</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="country" class="required">Country (Admin 0)</label>
                            <select id="country" name="country" class="select2-country">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="error-message" id="country_error">Country is required</div>
                        </div>
                        <div class="form-group">
                            <label for="county_district" class="required">County/District (Admin 1)</label>
                            <select id="county_district" name="county_district" class="select2-county">
                                <option value="">Select county/district</option>
                            </select>
                            <div class="error-message" id="county_district_error">County/District is required</div>
                        </div>
                    </div>
                </div>

                <!-- Border Points Section -->
                <div class="tradepoint-section" id="border-fields">
                    <div class="section-header">
                        <h6><i class="fas fa-map-marker-alt"></i> Border Point Information</h6>
                        <p>Provide details about the border crossing location and coordinates</p>
                    </div>
                    
                    <div class="form-group-full">
                        <label for="border_name" class="required">Name of Border</label>
                        <input type="text" id="border_name" name="border_name" placeholder="Enter border point name">
                        <div class="error-message" id="border_name_error">Border name is required</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="border_country" class="required">Country (Admin 0)</label>
                            <select id="border_country" name="border_country" class="select2-country">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="error-message" id="border_country_error">Country is required</div>
                        </div>
                        <div class="form-group">
                            <label for="border_county" class="required">County/District (Admin 1)</label>
                            <select id="border_county" name="border_county" class="select2-county">
                                <option value="">Select county/district</option>
                            </select>
                            <div class="error-message" id="border_county_error">County/District is required</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="longitude" class="required">Longitude</label>
                            <input type="number" step="any" id="longitude" name="longitude" placeholder="e.g., 36.8219">
                            <div class="error-message" id="longitude_error">Longitude is required</div>
                        </div>
                        <div class="form-group">
                            <label for="latitude" class="required">Latitude</label>
                            <input type="number" step="any" id="latitude" name="latitude" placeholder="e.g., -1.2921">
                            <div class="error-message" id="latitude_error">Latitude is required</div>
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="radius" class="required">Border Radius (m)</label>
                        <input type="number" id="radius" name="radius" placeholder="Enter radius in meters">
                        <div class="error-message" id="radius_error">Border radius is required</div>
                    </div>
                </div>

                <!-- Millers Section -->
                <div class="tradepoint-section" id="miller-fields">
                    <div class="section-header">
                        <h6><i class="fas fa-industry"></i> Miller Information</h6>
                        <p>Provide details about the milling facility location</p>
                    </div>
                    
                    <div class="form-group-full">
                        <label for="miller_name" class="required">Town Name</label>
                        <input type="text" id="miller_name" name="miller_name" placeholder="Enter town name">
                        <div class="error-message" id="miller_name_error">Town name is required</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="miller_country" class="required">Country (Admin 0)</label>
                            <select id="miller_country" name="miller_country" class="select2-country">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="error-message" id="miller_country_error">Country is required</div>
                        </div>
                        <div class="form-group">
                            <label for="miller_county_district" class="required">County/District (Admin 1)</label>
                            <select id="miller_county_district" name="miller_county_district" class="select2-county">
                                <option value="">Select county/district</option>
                            </select>
                            <div class="error-message" id="miller_county_district_error">County/District is required</div>
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="miller_currency_display" class="required">Currency</label>
                        <div class="currency-display" id="miller_currency_display">Select a country to see currency</div>
                        <input type="hidden" id="miller_currency_display_value" name="miller_currency_display_value">
                        <div class="error-message" id="miller_currency_error">Please select a country with available currency</div>
                    </div>
                </div>

                <button type="submit" class="next-btn">
                    <i class="fas fa-arrow-right"></i> Next Step
                </button>
            </form>
        </div>
    </div>

    <!-- Add jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Define the currency map for JavaScript (must be kept in sync with PHP)
        const jsCurrencyMap = <?php echo json_encode($currency_map); ?>;

        // Initialize Select2 for all country dropdowns
        $(document).ready(function() {
            $('.select2-country').select2({
                placeholder: "Select a country",
                allowClear: true
            });
            
            $('.select2-county').select2({
                placeholder: "Select a county/district",
                allowClear: true
            });
            
            // Initialize all country dropdowns
            initializeCountryDropdowns();
            
            // Set up event listeners for country changes
            setupCountryChangeListeners();
        });

        function initializeCountryDropdowns() {
            // Initialize all country dropdowns with the same class
            $('.select2-country').each(function() {
                $(this).on('change', function() {
                    const country = $(this).val();
                    const countyDropdown = $(this).closest('.form-row').find('.select2-county');
                    
                    // Clear previous options
                    countyDropdown.val(null).trigger('change');
                    countyDropdown.empty();
                    countyDropdown.append('<option value="">Select county/district</option>');
                    
                    if (country) {
                        // Fetch counties/districts for the selected country via AJAX
                        $.ajax({
                            url: 'get_counties.php',
                            method: 'POST',
                            data: { country: country },
                            dataType: 'json',
                            success: function(response) {
                                if (response.length > 0) {
                                    $.each(response, function(index, county) {
                                        countyDropdown.append($('<option>', {
                                            value: county,
                                            text: county
                                        }));
                                    });
                                } else {
                                    countyDropdown.append($('<option>', {
                                        value: '',
                                        text: 'No counties/districts found'
                                    }));
                                }
                            },
                            error: function() {
                                countyDropdown.append($('<option>', {
                                    value: '',
                                    text: 'Error loading counties/districts'
                                }));
                            }
                        });
                    }
                    
                    // If this is the miller country dropdown, update currency
                    if ($(this).attr('id') === 'miller_country') {
                        updateMillerCurrency();
                    }
                });
            });
        }

        function setupCountryChangeListeners() {
            // For miller country specifically, also update currency
            $('#miller_country').on('change', updateMillerCurrency);
        }

        function updateMillerCurrency() {
            const countrySelect = $('#miller_country');
            const currencyDisplayDiv = $('#miller_currency_display');
            const currencyHiddenInput = $('#miller_currency_display_value');

            const selectedCountry = countrySelect.val();
            let currency = '';

            if (selectedCountry && jsCurrencyMap[selectedCountry]) {
                currency = jsCurrencyMap[selectedCountry];
                currencyDisplayDiv.css('color', '#333');
                currencyDisplayDiv.html(`<i class="fas fa-coins"></i> ${currency}`);
            } else {
                currency = '';
                currencyDisplayDiv.css('color', '#6c757d');
                currencyDisplayDiv.html(selectedCountry ? 'Currency not available for selected country' : 'Select a country to see currency');
            }

            currencyHiddenInput.val(currency);
        }

        function showRelevantFields() {
            const selected = document.querySelector('input[name="tradepoint"]:checked').value;
            
            // Update hidden input
            document.getElementById('tradepoint-type').value = selected;
            
            // Hide all sections
            document.querySelectorAll('.tradepoint-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Clear all error messages
            document.querySelectorAll('.error-message').forEach(error => {
                error.style.display = 'none';
            });
            
            // Show selected section
            if (selected === 'Markets') {
                document.getElementById('market-fields').classList.add('active');
            } else if (selected === 'Border Points') {
                document.getElementById('border-fields').classList.add('active');
            } else if (selected === 'Millers') {
                document.getElementById('miller-fields').classList.add('active');
                updateMillerCurrency();
            }
        }

        function validateField(fieldId, errorId, customValidation = null) {
            const field = document.getElementById(fieldId);
            const error = document.getElementById(errorId);
            
            if (!field) return true;
            
            let isValid = true;
            
            if (customValidation) {
                isValid = customValidation(field);
            } else {
                isValid = field.value.trim() !== '';
            }
            
            if (isValid) {
                error.style.display = 'none';
                field.style.borderColor = '#ccc';
            } else {
                error.style.display = 'block';
                field.style.borderColor = '#dc3545';
            }
            
            return isValid;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            showRelevantFields();
            
            // Listen to tradepoint radio changes
            document.querySelectorAll('input[name="tradepoint"]').forEach(radio => {
                radio.addEventListener('change', showRelevantFields);
            });

            // Form validation
            document.getElementById('tradepoint-form').addEventListener('submit', function(e) {
                const selected = document.querySelector('input[name="tradepoint"]:checked').value;
                let isValid = true;
                let firstErrorField = null;

                // Clear all previous error messages
                document.querySelectorAll('.error-message').forEach(error => {
                    error.style.display = 'none';
                });

                if (selected === 'Markets') {
                    const fields = [
                        'market_name',
                        'category', 
                        'type',
                        'country',
                        'county_district'
                    ];
                    
                    fields.forEach(fieldId => {
                        const errorId = fieldId + '_error';
                        const fieldValid = validateField(fieldId, errorId);
                        if (!fieldValid) {
                            isValid = false;
                            if (!firstErrorField) {
                                firstErrorField = document.getElementById(fieldId);
                            }
                        }
                    });
                }
                else if (selected === 'Border Points') {
                    const fields = [
                        'border_name',
                        'border_country',
                        'border_county',
                        'longitude',
                        'latitude',
                        'radius'
                    ];
                    
                    fields.forEach(fieldId => {
                        const errorId = fieldId + '_error';
                        const fieldValid = validateField(fieldId, errorId);
                        if (!fieldValid) {
                            isValid = false;
                            if (!firstErrorField) {
                                firstErrorField = document.getElementById(fieldId);
                            }
                        }
                    });
                }
                else if (selected === 'Millers') {
                    const fields = [
                        'miller_name',
                        'miller_country',
                        'miller_county_district'
                    ];
                    
                    fields.forEach(fieldId => {
                        const errorId = fieldId + '_error';
                        const fieldValid = validateField(fieldId, errorId);
                        if (!fieldValid) {
                            isValid = false;
                            if (!firstErrorField) {
                                firstErrorField = document.getElementById(fieldId);
                            }
                        }
                    });
                    
                    // Check currency
                    const currencyValue = document.getElementById('miller_currency_display_value').value;
                    if (!currencyValue || currencyValue === 'N/A') {
                        isValid = false;
                        document.getElementById('miller_currency_error').style.display = 'block';
                        if (!firstErrorField) {
                            firstErrorField = document.getElementById('miller_country');
                        }
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    if (firstErrorField) {
                        firstErrorField.focus();
                        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                
                return true;
            });

            // Add real-time validation
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
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
        });
    </script>
</body>
</html>