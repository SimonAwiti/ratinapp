<?php
session_start();
include '../admin/includes/config.php';

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Fetch countries from database
$countries = [];
$country_query = "SELECT country_name FROM countries ORDER BY country_name ASC";
$country_result = $con->query($country_query);
if ($country_result) {
    while ($row = $country_result->fetch_assoc()) {
        $countries[] = $row['country_name'];
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

        $_SESSION['market_name'] = $_POST['market_name'];
        $_SESSION['category'] = $_POST['category'];
        $_SESSION['type'] = $_POST['type'];
        $_SESSION['country'] = $_POST['country'];
        $_SESSION['county_district'] = $_POST['county_district'];

        header("Location: add_market2.php");
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

        $_SESSION['border_name'] = $_POST['border_name'];
        $_SESSION['border_country'] = $_POST['border_country'];
        $_SESSION['border_county'] = $_POST['border_county'];
        $_SESSION['longitude'] = $_POST['longitude'];
        $_SESSION['latitude'] = $_POST['latitude'];
        $_SESSION['radius'] = $_POST['radius'];

        header("Location: add_borderpoint2.php");
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

        $_SESSION['miller_name'] = $_POST['miller_name'];
        $_SESSION['country'] = $_POST['miller_country'];
        $_SESSION['county_district'] = $_POST['miller_county_district'];
        $_SESSION['currency'] = $miller_currency; // Store the collected currency

        header("Location: add_miller2.php");
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
                <!-- Markets Section -->
                <div class="tradepoint-section active" id="market-fields">
                    <div class="section-header">
                        <h6><i class="fas fa-store"></i> Market Information</h6>
                        <p>Provide details about the market location and type</p>
                    </div>
                    
                    <div class="form-group-full">
                        <label for="market_name" class="required">Name of Market</label>
                        <input type="text" id="market_name" name="market_name" placeholder="Enter market name">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category" class="required">Market Category</label>
                            <select id="category" name="category">
                                <option value="">Select category</option>
                                <option value="Consumer">Consumer</option>
                                <option value="Producer">Producer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="type" class="required">Market Type</label>
                            <select id="type" name="type">
                                <option value="">Select type</option>
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="country" class="required">Country (Admin 0)</label>
                            <select id="country" name="country">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="county_district" class="required">County/District (Admin 1)</label>
                            <input type="text" id="county_district" name="county_district" placeholder="Enter county or district">
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
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="border_country" class="required">Country (Admin 0)</label>
                            <select id="border_country" name="border_country">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="border_county" class="required">County/District (Admin 1)</label>
                            <input type="text" id="border_county" name="border_county" placeholder="Enter county or district">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="longitude" class="required">Longitude</label>
                            <input type="number" step="any" id="longitude" name="longitude" placeholder="e.g., 36.8219">
                        </div>
                        <div class="form-group">
                            <label for="latitude" class="required">Latitude</label>
                            <input type="number" step="any" id="latitude" name="latitude" placeholder="e.g., -1.2921">
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="radius" class="required">Border Radius (km)</label>
                        <input type="number" id="radius" name="radius" placeholder="Enter radius in kilometers">
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
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="miller_country" class="required">Country (Admin 0)</label>
                            <select id="miller_country" name="miller_country">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= htmlspecialchars($country) ?>">
                                        <?= htmlspecialchars($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="miller_county_district" class="required">County/District (Admin 1)</label>
                            <input type="text" id="miller_county_district" name="miller_county_district" placeholder="Enter county or district">
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="miller_currency_display" class="required">Currency</label>
                        <div class="currency-display" id="miller_currency_display">Select a country to see currency</div>
                        <input type="hidden" id="miller_currency_display_value" name="miller_currency_display_value">
                    </div>
                </div>

                <button type="submit" class="next-btn">
                    <i class="fas fa-arrow-right"></i> Next Step
                </button>
            </form>
        </div>
    </div>

    <script>
        // Define the currency map for JavaScript (must be kept in sync with PHP)
        const jsCurrencyMap = <?php echo json_encode($currency_map); ?>;

        function updateMillerCurrency() {
            const countrySelect = document.getElementById('miller_country');
            const currencyDisplayDiv = document.getElementById('miller_currency_display');
            const currencyHiddenInput = document.getElementById('miller_currency_display_value');

            const selectedCountry = countrySelect.value;
            let currency = '';

            if (jsCurrencyMap[selectedCountry]) {
                currency = jsCurrencyMap[selectedCountry];
                currencyDisplayDiv.style.color = '#333';
                currencyDisplayDiv.innerHTML = `<i class="fas fa-coins"></i> ${currency}`;
            } else {
                currency = '';
                currencyDisplayDiv.style.color = '#6c757d';
                currencyDisplayDiv.innerHTML = selectedCountry ? 'Currency not available for selected country' : 'Select a country to see currency';
            }

            currencyHiddenInput.value = currency;
        }

        function showRelevantFields() {
            const selected = document.querySelector('input[name="tradepoint"]:checked').value;
            
            // Hide all sections
            document.querySelectorAll('.tradepoint-section').forEach(section => {
                section.classList.remove('active');
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

        // Initialize on page load
        window.onload = function() {
            showRelevantFields();
            // Attach event listener to miller_country select for real-time updates
            document.getElementById('miller_country').addEventListener('change', updateMillerCurrency);
        };

        // Listen to tradepoint radio changes
        document.querySelectorAll('input[name="tradepoint"]').forEach(radio => {
            radio.addEventListener('change', showRelevantFields);
        });

        // Form validation
        document.getElementById('tradepoint-form').addEventListener('submit', function(e) {
            const selected = document.querySelector('input[name="tradepoint"]:checked').value;
            let isValid = true;
            let firstErrorField = null;

            if (selected === 'Markets') {
                const requiredFields = [
                    {id: 'market_name', name: 'Market Name'},
                    {id: 'category', name: 'Market Category'},
                    {id: 'type', name: 'Market Type'},
                    {id: 'country', name: 'Country'},
                    {id: 'county_district', name: 'County/District'}
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element.value.trim()) {
                        if (!firstErrorField) firstErrorField = element;
                        isValid = false;
                    }
                });
            }
            else if (selected === 'Border Points') {
                const requiredFields = [
                    {id: 'border_name', name: 'Border Name'},
                    {id: 'border_country', name: 'Country'},
                    {id: 'border_county', name: 'County/District'},
                    {id: 'longitude', name: 'Longitude'},
                    {id: 'latitude', name: 'Latitude'},
                    {id: 'radius', name: 'Border Radius'}
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element.value.trim()) {
                        if (!firstErrorField) firstErrorField = element;
                        isValid = false;
                    }
                });
            }
            else if (selected === 'Millers') {
                const requiredFields = [
                    {id: 'miller_name', name: 'Town Name'},
                    {id: 'miller_country', name: 'Country'},
                    {id: 'miller_county_district', name: 'County/District'}
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element.value.trim()) {
                        if (!firstErrorField) firstErrorField = element;
                        isValid = false;
                    }
                });
                
                // Check currency
                const currencyValue = document.getElementById('miller_currency_display_value').value;
                if (!currencyValue || currencyValue === 'N/A') {
                    if (!firstErrorField) firstErrorField = document.getElementById('miller_country');
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                if (firstErrorField) {
                    firstErrorField.focus();
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        // Add smooth transitions for better UX
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
                this.style.transition = 'transform 0.2s ease';
            });
            
            element.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>