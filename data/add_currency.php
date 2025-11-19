<?php
// add_currency.php
include '../admin/includes/config.php';

// East African countries with their currencies
$eastAfricanCountries = [
    'Kenya' => 'KES',
    'Uganda' => 'UGX',
    'Tanzania' => 'TZS',
    'Rwanda' => 'RWF',
    'Burundi' => 'BIF',
    'Ethiopia' => 'ETB',
    'South Sudan' => 'SSP',
    'Sudan' => 'SDG',
    'Somalia' => 'SOS',
    'Djibouti' => 'DJF',
    'Eritrea' => 'ERN',
    'DR Congo' => 'CDF',
    'Comoros' => 'KMF',
    'Seychelles' => 'SCR',
    'Mauritius' => 'MUR',
    'Madagascar' => 'MGA',
    'Malawi' => 'MWK',
    'Zambia' => 'ZMW',
    'Zimbabwe' => 'ZWL',
    'Mozambique' => 'MZN'
];

$currencies = array_unique(array_values($eastAfricanCountries));
// Add common international currencies
$additionalCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AED', 'INR'];
$currencies = array_merge($currencies, $additionalCurrencies);
sort($currencies);

// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // Sanitize input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $currency = mysqli_real_escape_string($con, $_POST['currency']);
    $rate = (float)$_POST['rate'];
    $date = mysqli_real_escape_string($con, $_POST['date']);

    // Validate required fields
    if (empty($country) || empty($currency) || $rate <= 0 || empty($date) ) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // Get current date for record keeping
    $date_created = date('Y-m-d H:i:s');
    $status = 'pending';
    $day = date('d');
    $month = date('m');
    $year = date('Y');

    // Insert into currencies table
    $stmt = $con->prepare("INSERT INTO currencies 
                          (country, currency_code, exchange_rate, effective_date, date_created,
                           day, month, year)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssdssiii", 
        $country,
        $currency,
        $rate,
        $date,
        $date_created,
        $day,
        $month,
        $year);
    
    if ($stmt->execute()) {
        echo "<script>alert('Currency rate added successfully'); window.location.href='currencies_boilerplate.php';</script>";
    } else {
        echo "<script>alert('Error adding currency rate: " . $con->error . "');</script>";
    }
}

// Fetch data sources for dropdown
$data_sources = [];
if (isset($con)) {
    $query = "SELECT id, data_source_name FROM data_sources ORDER BY data_source_name";
    $result = $con->query($query);
    while ($row = $result->fetch_assoc()) {
        $data_sources[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Currency Rate</title>
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
        .currency-info {
            font-size: 12px;
            color: #666;
            margin-top: -10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='currencies_boilerplate.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Currency Exchange Rate</h2>
            <p>Provide the details below to add a new currency exchange rate</p>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" required>
                            <option value="" disabled selected>Select Country</option>
                            <?php foreach ($eastAfricanCountries as $country => $currencyCode): ?>
                                <option value="<?= htmlspecialchars($country) ?>" data-currency="<?= htmlspecialchars($currencyCode) ?>">
                                    <?= htmlspecialchars($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency Code *</label>
                        <select name="currency" id="currency" required>
                            <option value="" disabled selected>Select Currency</option>
                            <?php foreach ($currencies as $curr): ?>
                                <option value="<?= htmlspecialchars($curr) ?>"><?= htmlspecialchars($curr) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="currency-info" id="currencyInfo">
                            Currency will be auto-selected when you choose a country
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="rate">Exchange Rate (to USD) *</label>
                        <input type="number" step="0.0001" name="rate" id="rate" required placeholder="Enter exchange rate">
                    </div>
                    <div class="form-group">
                        <label for="date">Effective Date *</label>
                        <input type="date" name="date" id="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <button type="submit" name="submit" class="next-btn">Submit →</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const countrySelect = document.getElementById('country');
            const currencySelect = document.getElementById('currency');
            const currencyInfo = document.getElementById('currencyInfo');

            countrySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const currencyCode = selectedOption.getAttribute('data-currency');
                
                if (currencyCode) {
                    // Set the currency select to the corresponding currency
                    for (let i = 0; i < currencySelect.options.length; i++) {
                        if (currencySelect.options[i].value === currencyCode) {
                            currencySelect.selectedIndex = i;
                            currencyInfo.textContent = `Auto-selected: ${currencyCode} for ${selectedOption.textContent}`;
                            currencyInfo.style.color = '#28a745';
                            break;
                        }
                    }
                } else {
                    currencyInfo.textContent = 'Please select a currency manually';
                    currencyInfo.style.color = '#dc3545';
                }
            });

            // Also update when currency is manually changed
            currencySelect.addEventListener('change', function() {
                const selectedCountry = countrySelect.options[countrySelect.selectedIndex];
                const expectedCurrency = selectedCountry.getAttribute('data-currency');
                
                if (expectedCurrency && this.value !== expectedCurrency) {
                    currencyInfo.textContent = `Note: ${this.value} is not the typical currency for ${selectedCountry.textContent} (usually ${expectedCurrency})`;
                    currencyInfo.style.color = '#ffc107';
                } else if (expectedCurrency) {
                    currencyInfo.textContent = `Correct currency: ${this.value} for ${selectedCountry.textContent}`;
                    currencyInfo.style.color = '#28a745';
                }
            });
        });
    </script>
</body>
</html>