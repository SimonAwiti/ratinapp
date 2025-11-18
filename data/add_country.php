<?php
// add_country.php
include '../admin/includes/config.php';

// Country-currency mapping
$country_currency = [
    'Kenya' => 'KES',
    'Tanzania' => 'TZS',
    'Uganda' => 'UGX',
    'Rwanda' => 'RWF',
    'Burundi' => 'BIF',
    'Ethiopia' => 'ETB',
    'South Sudan' => 'SSP',
    'Somalia' => 'SOS',
    'Djibouti' => 'DJF',
    'Eritrea' => 'ERN',
    'Sudan' => 'SDG',
    'Madagascar' => 'MGA',
    'Mauritius' => 'MUR',
    'Comoros' => 'KMF',
    'Seychelles' => 'SCR',
    'Malawi' => 'MWK',
    'Zambia' => 'ZMW',
    'Mozambique' => 'MZN'
];

// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // Sanitize input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $currency = mysqli_real_escape_string($con, $_POST['currency']);
    
    // Validate required fields
    if (empty($country) || empty($currency)) {
        echo "<script>alert('Please fill all required fields.'); window.history.back();</script>";
        exit;
    }

    // Get current date for record keeping
    $date_created = date('Y-m-d H:i:s');

    // Insert into countries table (assuming you have one)
    $stmt = $con->prepare("INSERT INTO countries 
                          (country_name, currency_code, date_created)
                          VALUES (?, ?, ?)");
    
    $stmt->bind_param("sss", 
        $country,
        $currency,
        $date_created);
    
    if ($stmt->execute()) {
        echo "<script>alert('Country added successfully'); window.location.href='countries_boilerplate.php';</script>";
    } else {
        echo "<script>alert('Error adding country: " . $con->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Country</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        <?php include '../base/assets/add_commodity.css'; ?>
        
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
            height: 500px;
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
            background-color: rgba(180, 80, 50, 1);
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
        <button class="close-btn" onclick="window.location.href='countries_boilerplate.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Country with Currency</h2>
            <p>Provide the details below to add a new country with its currency</p>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" required>
                            <option value="" disabled selected>Select Country</option>
                            <?php foreach ($country_currency as $country => $currency): ?>
                                <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency Code *</label>
                        <select name="currency" id="currency" required>
                            <option value="" disabled selected>Select Currency</option>
                            <?php foreach ($country_currency as $country => $curr): ?>
                                <option value="<?= htmlspecialchars($curr) ?>" data-country="<?= htmlspecialchars($country) ?>">
                                    <?= htmlspecialchars($curr) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" name="submit" class="next-btn">Submit →</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default currency based on country selection
            const countryCurrencyMap = <?php echo json_encode($country_currency); ?>;
            
            const countrySelect = document.getElementById('country');
            const currencySelect = document.getElementById('currency');

            // When country changes, update currency
            countrySelect.addEventListener('change', function() {
                const country = this.value;
                if (country in countryCurrencyMap) {
                    currencySelect.value = countryCurrencyMap[country];
                }
            });

            // When currency changes, verify it matches the country
            currencySelect.addEventListener('change', function() {
                const selectedCurrency = this.value;
                const selectedCountry = countrySelect.value;
                
                if (selectedCountry && countryCurrencyMap[selectedCountry] !== selectedCurrency) {
                    alert('Warning: This currency does not normally belong to the selected country.');
                }
            });
        });
    </script>
</body>
</html>