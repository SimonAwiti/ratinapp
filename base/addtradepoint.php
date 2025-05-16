<?php
session_start();
include '../admin/includes/config.php';

// Define the currency mapping (PHP-side, for initial load if 'Millers' is default)
$currency_map = [
    'Kenya' => 'KES',
    'Uganda' => 'UGX',
    'Tanzania' => 'TZS',
    'Rwanda' => 'RWF',
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
    <title>Add Tradepoint - Step 1</title>
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
            width: 100%;
        }
        .packaging-unit-container {
            flex-grow: 1;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .packaging-unit-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        .packaging-unit-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .packaging-unit-group input,
        .packaging-unit-group select {
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
            border-radius: 50%; /* Changed to circle for remove buttons */
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px; /* Make it a fixed size for circle */
            height: 30px; /* Make it a fixed size for circle */
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
        #variety {
            margin-bottom: 15px;
        }
        .selection {
            background: #f7f7d8;
            padding: 10px;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 120px;
            flex-wrap: nowrap;
            margin-bottom: 20px;
        }

        .selection label {
            display: flex;
            align-items: center;
            gap: 8px;
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

        .selection input[type="radio"] {
            margin: 0;
            width: 16px;
            height: 16px;
            vertical-align: middle;
        }

        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            border: none;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 20px 0;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
        }

        .next-btn:hover {
            background-color: rgba(180, 80, 50, 1);
        }

        .tradepoint-section {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .tradepoint-section label {
            display: block;
            margin-top: 10px;
        }

        .tradepoint-section input,
        .tradepoint-section select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        /* Style for the read-only currency display */
        .currency-display {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #e9ecef; /* Light grey background for read-only fields */
            font-size: 1rem;
            color: #495057;
            margin-top: 5px;
            margin-bottom: 10px; /* Match spacing of other form elements */
            box-sizing: border-box; /* Include padding and border in width */
            width: 100%; /* Take full width */
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='sidebar.php'">×</button>

        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 2</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 3</span>
            </div>
        </div>

        <form id="tradepoint-form" method="POST" action="">
            <div class="form-container">
                <div class="selection">
                    <label><input type="radio" name="tradepoint" value="Markets" checked> Markets</label>
                    <label><input type="radio" name="tradepoint" value="Border Points"> Border Points</label>
                    <label><input type="radio" name="tradepoint" value="Millers"> Millers</label>
                </div>

                <div class="tradepoint-section" id="market-fields">
                    <label for="market_name">Name of Market *</label>
                    <input type="text" id="market_name" name="market_name">

                    <label for="category">Market Category *</label>
                    <select id="category" name="category">
                        <option value="">Select category</option>
                        <option value="Consumer">Consumer</option>
                        <option value="Producer">Producer</option>
                    </select>

                    <label for="type">Market Type *</label>
                    <select id="type" name="type">
                        <option value="">Select type</option>
                        <option value="Primary">Primary</option>
                        <option value="Secondary">Secondary</option>
                    </select>

                    <label for="country">Country (Admin 0) *</label>
                    <input type="text" id="country" name="country">

                    <label for="county_district">County/District (Admin 1) *</label>
                    <input type="text" id="county_district" name="county_district">
                </div>

                <div class="tradepoint-section" id="border-fields" style="display:none;">
                    <label for="border_name">Name of Border *</label>
                    <input type="text" id="border_name" name="border_name">

                    <label for="border_country">Country (Admin 0) *</label>
                    <input type="text" id="border_country" name="border_country">

                    <label for="border_county">County/District (Admin 1) *</label>
                    <input type="text" id="border_county" name="border_county">

                    <label for="longitude">Longitude *</label>
                    <input type="text" id="longitude" name="longitude">

                    <label for="latitude">Latitude *</label>
                    <input type="text" id="latitude" name="latitude">

                    <label for="radius">Border radius *</label>
                    <input type="text" id="radius" name="radius">
                </div>

                <div class="tradepoint-section" id="miller-fields" style="display:none;">
                    <label for="miller_name">Town name *</label>
                    <input type="text" id="miller_name" name="miller_name">

                    <label for="miller_country">Country (Admin 0) *</label>
                    <select id="miller_country" name="miller_country">
                        <option value="">Select country</option>
                        <option value="Kenya">Kenya</option>
                        <option value="Uganda">Uganda</option>
                        <option value="Tanzania">Tanzania</option>
                        <option value="Rwanda">Rwanda</option>
                        </select>

                    <label for="miller_county_district">County/District (Admin 1) *</label>
                    <input type="text" id="miller_county_district" name="miller_county_district">

                    <label for="miller_currency_display">Currency *</label>
                    <div class="currency-display" id="miller_currency_display"></div>
                    <input type="hidden" id="miller_currency_display_value" name="miller_currency_display_value">
                </div>

                <button type="submit" class="next-btn">Next &rarr;</button>
            </div>
        </form>
    </div>

    <script>
        // Define the currency map for JavaScript (must be kept in sync with PHP)
        const jsCurrencyMap = {
            'Kenya': 'KES',
            'Uganda': 'UGX',
            'Tanzania': 'TZS',
            'Rwanda': 'RWF',
            // Add more country-currency mappings here to match the PHP array
        };

        function updateMillerCurrency() {
            const countrySelect = document.getElementById('miller_country');
            const currencyDisplayDiv = document.getElementById('miller_currency_display');
            const currencyHiddenInput = document.getElementById('miller_currency_display_value');

            const selectedCountry = countrySelect.value;
            let currency = '';

            if (jsCurrencyMap[selectedCountry]) {
                currency = jsCurrencyMap[selectedCountry];
            } else {
                currency = 'N/A'; // Fallback if country not in map
            }

            currencyDisplayDiv.textContent = currency;
            currencyHiddenInput.value = currency; // Update hidden input
        }

        function showRelevantFields() {
            const selected = document.querySelector('input[name="tradepoint"]:checked').value;
            document.getElementById('market-fields').style.display = selected === 'Markets' ? 'block' : 'none';
            document.getElementById('border-fields').style.display = selected === 'Border Points' ? 'block' : 'none';
            document.getElementById('miller-fields').style.display = selected === 'Millers' ? 'block' : 'none';

            // If Millers section is displayed, update currency
            if (selected === 'Millers') {
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

            if (selected === 'Markets') {
                const requiredFields = ['market_name', 'category', 'type', 'country', 'county_district'];
                requiredFields.forEach(field => {
                    if (!document.getElementById(field).value.trim()) {
                        alert(`Please fill in the ${field.replace('_', ' ')} field`);
                        document.getElementById(field).focus();
                        isValid = false;
                        return;
                    }
                });
            }
            else if (selected === 'Border Points') {
                const requiredFields = ['border_name', 'border_country', 'border_county', 'longitude', 'latitude', 'radius'];
                requiredFields.forEach(field => {
                    if (!document.getElementById(field).value.trim()) {
                        alert(`Please fill in the ${field.replace('_', ' ')} field`);
                        document.getElementById(field).focus();
                        isValid = false;
                        return;
                    }
                });
            }
            else if (selected === 'Millers') {
                // Now validate the hidden currency field as well
                const requiredFields = ['miller_name', 'miller_country', 'miller_county_district'];
                requiredFields.forEach(field => {
                    if (!document.getElementById(field).value.trim()) {
                        alert(`Please fill in the ${field.replace('_', ' ')} field`);
                        document.getElementById(field).focus();
                        isValid = false;
                        return;
                    }
                });
                // Check if the currency value is 'N/A' or empty (if a country mapping isn't found)
                if (document.getElementById('miller_currency_display_value').value === '' ||
                    document.getElementById('miller_currency_display_value').value === 'N/A') {
                    alert('Please select a valid country for the currency.');
                    document.getElementById('miller_country').focus();
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>