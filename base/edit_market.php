<?php
include '../admin/includes/config.php'; // Database connection

$market = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);

    // Fetch market data
    $stmt = $con->prepare("SELECT * FROM markets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $market = $result->fetch_assoc();
    $stmt->close();

    if (!$market) {
        // Redirect if market not found
        header("Location: tradepoints.php?status=notfound");
        exit;
    }
} else {
    // Redirect if no ID is provided
    header("Location: tradepoints.php?status=no_id");
    exit;
}

// Define the currency mapping (PHP-side, must match your addtradepoint.php)
$currency_map = [
    'Kenya' => 'KES',
    'Uganda' => 'UGX',
    'Tanzania' => 'TZS',
    'Rwanda' => 'RWF',
    // Add more country-currency mappings as needed
];

// Determine the currency to display based on the stored country
// Assuming 'country' column exists in your 'markets' table for markets as well
$autofill_currency = '';
if (isset($currency_map[$market['country']])) {
    $autofill_currency = $currency_map[$market['country']];
} else {
    $autofill_currency = 'N/A'; // Fallback
}


// Handle form submission for updating data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $market_name = $_POST['market_name'];
    $category = $_POST['category'];
    $type = $_POST['type'];
    $country = $_POST['country'];
    $county_district = $_POST['county_district'];
    $longitude = $_POST['longitude']; // Assuming these are also part of market data
    $latitude = $_POST['latitude'];   // Assuming these are also part of market data
    $radius = $_POST['radius'];       // Assuming these are also part of market data
    $currency = $_POST['currency_display_value']; // Get value from hidden input

    // Update query
    // Adjust column names if they differ in your 'markets' table
    $stmt = $con->prepare("UPDATE markets SET market_name = ?, category = ?, type = ?, country = ?, county_district = ?, longitude = ?, latitude = ?, radius = ?, currency = ? WHERE id = ?");
    $stmt->bind_param("ssssssddsi", $market_name, $category, $type, $country, $county_district, $longitude, $latitude, $radius, $currency, $id);

    if ($stmt->execute()) {
        header("Location: sidebar.php");
        exit;
    } else {
        echo "<script>alert('Error updating market: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Market</title>
    <link rel="stylesheet" href="assets/add_commodity.css" /> <style>
        /* Add or adjust styles as needed for your edit page */
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-bottom: 20px;
            text-align: center;
        }
        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        form input, form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box; /* Ensures padding doesn't expand width */
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-update {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
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
        /* Longitude & Latitude on the same line */
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Market</h2>
        <?php if ($market): ?>
            <form method="POST" action="edit_market.php?id=<?= htmlspecialchars($market['id']) ?>">
                <label for="market_name">Name of Market *</label>
                <input type="text" id="market_name" name="market_name" value="<?= htmlspecialchars($market['market_name']) ?>" required>

                <label for="category">Market Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Consumer" <?= ($market['category'] == 'Consumer') ? 'selected' : '' ?>>Consumer</option>
                    <option value="Producer" <?= ($market['category'] == 'Producer') ? 'selected' : '' ?>>Producer</option>
                </select>

                <label for="type">Market Type *</label>
                <select id="type" name="type" required>
                    <option value="">Select type</option>
                    <option value="Primary" <?= ($market['type'] == 'Primary') ? 'selected' : '' ?>>Primary</option>
                    <option value="Secondary" <?= ($market['type'] == 'Secondary') ? 'selected' : '' ?>>Secondary</option>
                </select>

                <label for="country">Country (Admin 0) *</label>
                <select id="country" name="country" required onchange="updateMarketCurrency()">
                    <option value="">Select country</option>
                    <option value="Kenya" <?= ($market['country'] == 'Kenya') ? 'selected' : '' ?>>Kenya</option>
                    <option value="Uganda" <?= ($market['country'] == 'Uganda') ? 'selected' : '' ?>>Uganda</option>
                    <option value="Tanzania" <?= ($market['country'] == 'Tanzania') ? 'selected' : '' ?>>Tanzania</option>
                    <option value="Rwanda" <?= ($market['country'] == 'Rwanda') ? 'selected' : '' ?>>Rwanda</option>
                    </select>

                <label for="county_district">County/District (Admin 1) *</label>
                <input type="text" id="county_district" name="county_district" value="<?= htmlspecialchars($market['county_district']) ?>" required>

                <div class="location-container">
                    <label for="longitude">Longitude *
                        <input type="text" id="longitude" name="longitude" value="<?= htmlspecialchars($market['longitude']) ?>" required>
                    </label>
                    <label for="latitude">Latitude *
                        <input type="text" id="latitude" name="latitude" value="<?= htmlspecialchars($market['latitude']) ?>" required>
                    </label>
                </div>

                <label for="radius">Market radius *</label>
                <input type="text" id="radius" name="radius" value="<?= htmlspecialchars($market['radius']) ?>" required>

                <label for="currency_display">Currency *</label>
                <div class="currency-display" id="currency_display"><?= htmlspecialchars($autofill_currency) ?></div>
                <input type="hidden" id="currency_display_value" name="currency_display_value" value="<?= htmlspecialchars($autofill_currency) ?>">


                <div class="button-container">
                    <button type="button" class="btn-cancel" onclick="window.location.href='tradepoints.php'">Cancel</button>
                    <button type="submit" class="btn-update">Update Market</button>
                </div>
            </form>
        <?php else: ?>
            <p>Market not found or invalid ID.</p>
            <div class="button-container">
                <button type="button" class="btn-cancel" onclick="window.location.href='sidebar.php'">Back to Tradepoints</button>
            </div>
        <?php endif; ?>

    <script>
        // Define the currency map for JavaScript (must be kept in sync with PHP)
        const jsCurrencyMap = {
            'Kenya': 'KES',
            'Uganda': 'UGX',
            'Tanzania': 'TZS',
            'Rwanda': 'RWF',
            // Add more country-currency mappings here to match the PHP array
        };

        function updateMarketCurrency() {
            const countrySelect = document.getElementById('country'); // Note: ID is 'country' here for markets
            const currencyDisplayDiv = document.getElementById('currency_display');
            const currencyHiddenInput = document.getElementById('currency_display_value');

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

        // Call the function on page load to ensure currency is correctly displayed
        window.onload = updateMarketCurrency;
    </script>
    </div>
</body>
</html>