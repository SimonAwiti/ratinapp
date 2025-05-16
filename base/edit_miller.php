<?php
session_start(); // Ensure session is started
include '../admin/includes/config.php'; // Database connection

$miller_details = null; // Renamed from $miller to avoid confusion with individual millers
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);

    // Fetch the main miller_details record
    $stmt = $con->prepare("SELECT * FROM miller_details WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $miller_details = $result->fetch_assoc();
    $stmt->close();

    if (!$miller_details) {
        header("Location: tradepoints.php?status=notfound");
        exit;
    }

    // Decode the JSON string of individual millers
    $selected_millers_array = json_decode($miller_details['miller'], true);
    if (!is_array($selected_millers_array)) {
        $selected_millers_array = []; // Initialize as empty array if decode fails
    }

} else {
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
$autofill_currency = '';
if (isset($currency_map[$miller_details['country']])) {
    $autofill_currency = $currency_map[$miller_details['country']];
} else {
    // Fallback if country is not in the map, or if currency column is directly stored
    $autofill_currency = $miller_details['currency'] ?? 'N/A';
}


// Handle form submission for updating data
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_millers_edit'])) {
    $updated_miller_name = $_POST['miller_name'];
    $updated_country = $_POST['miller_country'];
    $updated_county_district = $_POST['miller_county_district'];
    $updated_currency = $_POST['miller_currency_display_value']; // From hidden input

    // Get updated individual millers from the dynamically added inputs
    $updated_selected_millers = $_POST['individual_miller_name'] ?? [];
    $updated_miller_array_json = json_encode($updated_selected_millers);

    // Update query
    $stmt = $con->prepare("UPDATE miller_details SET miller_name = ?, miller = ?, country = ?, county_district = ?, currency = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $updated_miller_name, $updated_miller_array_json, $updated_country, $updated_county_district, $updated_currency, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Miller details updated successfully!'); window.location.href='sidebar.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error updating miller details: " . $stmt->error . "'); window.history.back();</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Miller Details</title>
    <link rel="stylesheet" href="assets/add_commodity.css" /> <style>
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
            padding: 40px 60px; /* Adjusted padding for more content */
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
        form input[type="text"], form select { /* Target only text inputs and selects */
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
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
        .currency-display {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #e9ecef;
            font-size: 1rem;
            color: #495057;
            margin-top: 5px;
            margin-bottom: 10px;
            box-sizing: border-box;
            width: 100%;
        }

        /* Styles for dynamic miller inputs */
        .individual-miller-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }
        .individual-miller-group input {
            flex-grow: 1;
            margin-bottom: 0; /* Override default input margin */
        }
        .remove-miller-btn {
            background-color: #f8d7da;
            color: red;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 50%;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px; /* Make it a fixed size for circle */
            height: 35px; /* Make it a fixed size for circle */
            flex-shrink: 0; /* Prevent shrinking */
        }
        .add-miller-btn {
            background-color: #d9f5d9;
            color: green;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }
        #individualMillersContainer {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            max-height: 250px; /* Limit height for scroll */
            overflow-y: auto; /* Enable scrolling */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Miller Details</h2>
        <?php if ($miller_details): ?>
            <form method="POST" action="edit_miller.php?id=<?= htmlspecialchars($miller_details['id']) ?>">
                <input type="hidden" name="submit_millers_edit" value="1"> <label for="miller_name">Town/Group Name *</label>
                <input type="text" id="miller_name" name="miller_name" value="<?= htmlspecialchars($miller_details['miller_name']) ?>" required>

                <label for="miller_country">Country (Admin 0) *</label>
                <select id="miller_country" name="miller_country" required onchange="updateMillerCurrency()">
                    <option value="">Select country</option>
                    <option value="Kenya" <?= ($miller_details['country'] == 'Kenya') ? 'selected' : '' ?>>Kenya</option>
                    <option value="Uganda" <?= ($miller_details['country'] == 'Uganda') ? 'selected' : '' ?>>Uganda</option>
                    <option value="Tanzania" <?= ($miller_details['country'] == 'Tanzania') ? 'selected' : '' ?>>Tanzania</option>
                    <option value="Rwanda" <?= ($miller_details['country'] == 'Rwanda') ? 'selected' : '' ?>>Rwanda</option>
                    </select>

                <label for="miller_county_district">County/District (Admin 1) *</label>
                <input type="text" id="miller_county_district" name="miller_county_district" value="<?= htmlspecialchars($miller_details['county_district']) ?>" required>

                <label for="miller_currency_display">Currency *</label>
                <div class="currency-display" id="miller_currency_display"><?= htmlspecialchars($autofill_currency) ?></div>
                <input type="hidden" id="miller_currency_display_value" name="miller_currency_display_value" value="<?= htmlspecialchars($autofill_currency) ?>">

                <h3>Individual Millers</h3>
                <div id="individualMillersContainer">
                    <?php if (!empty($selected_millers_array)): ?>
                        <?php foreach ($selected_millers_array as $index => $miller_value): ?>
                            <div class="individual-miller-group">
                                <input type="text" name="individual_miller_name[]" value="<?= htmlspecialchars($miller_value) ?>" placeholder="Miller Name" required>
                                <button type="button" class="remove-miller-btn" onclick="this.closest('.individual-miller-group').remove()">✖</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <p>No individual millers added yet.</p>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-miller-btn" onclick="addIndividualMiller()">Add Another Miller</button>

                <div class="button-container">
                    <button type="button" class="btn-cancel" onclick="window.location.href='tradepoints.php'">Cancel</button>
                    <button type="submit" class="btn-update">Update Miller Details</button>
                </div>
            </form>
        <?php else: ?>
            <p>Miller details not found or invalid ID.</p>
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

        function addIndividualMiller() {
            const container = document.getElementById('individualMillersContainer');
            // Remove the "No individual millers added yet." message if it exists
            const noMillersMessage = container.querySelector('p');
            if (noMillersMessage && noMillersMessage.textContent === 'No individual millers added yet.') {
                noMillersMessage.remove();
            }

            const newMillerGroup = document.createElement('div');
            newMillerGroup.classList.add('individual-miller-group');
            newMillerGroup.innerHTML = `
                <input type="text" name="individual_miller_name[]" placeholder="Miller Name" required>
                <button type="button" class="remove-miller-btn" onclick="this.closest('.individual-miller-group').remove()">✖</button>
            `;
            container.appendChild(newMillerGroup);
        }

        // Call the function on page load to ensure currency is correctly displayed
        window.onload = updateMillerCurrency;
    </script>
    </div>
</body>
</html>