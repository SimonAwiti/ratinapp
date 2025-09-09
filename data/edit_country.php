<?php
// edit_country.php
include '../admin/includes/config.php';

// Country-currency mapping
$country_currency = [
    'Kenya' => 'KES',
    'Tanzania' => 'TZS',
    'Uganda' => 'UGX',
    'Rwanda' => 'RWF',
    'Burundi' => 'BIF',
    'Ethiopia' => 'ETB'
];

// Get country ID from URL parameter
$country_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$country_data = null;

// Fetch existing country data
if ($country_id > 0) {
    $stmt = $con->prepare("SELECT * FROM countries WHERE id = ?");
    $stmt->bind_param("i", $country_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $country_data = $result->fetch_assoc();
    
    if (!$country_data) {
        echo "<script>alert('Country not found.'); window.location.href='../base/commodities_boilerplate.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('Invalid country ID.'); window.location.href='../base/commodities_boilerplate.php';</script>";
    exit;
}

// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // Sanitize input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $currency = mysqli_real_escape_string($con, $_POST['currency']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    
    // Validate required fields
    if (empty($country) || empty($currency)) {
        echo "<script>alert('Please fill all required fields.'); window.history.back();</script>";
        exit;
    }

    // Get current date for record keeping
    $date_updated = date('Y-m-d H:i:s');

    // Update countries table
    $stmt = $con->prepare("UPDATE countries 
                          SET country_name = ?, currency_code = ?, status = ?, date_updated = ?
                          WHERE id = ?");
    
    $stmt->bind_param("ssssi", 
        $country,
        $currency,
        $status,
        $date_updated,
        $country_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Country updated successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
    } else {
        echo "<script>alert('Error updating country: " . $con->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Country</title>
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
            height: 550px;
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
        .form-group.full-width {
            width: 100%;
        }
        input, select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
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
            background-color: rgba(160, 70, 40, 1);
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
        .info-section {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #8B4513;
        }
        .info-section small {
            color: #6c757d;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Edit</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Edit Country</h2>
            <p>Update the details below to modify the country information</p>
            
            <!-- Display current country info -->
            <div class="info-section">
                <strong>Current Record:</strong> <?= htmlspecialchars($country_data['country_name']) ?> 
                (<?= htmlspecialchars($country_data['currency_code']) ?>)
                <br>
                <small>
                    Created: <?= date('M d, Y H:i', strtotime($country_data['date_created'])) ?> | 
                    Last Updated: <?= $country_data['date_updated'] ? date('M d, Y H:i', strtotime($country_data['date_updated'])) : 'Never' ?> |
                    Status: <span class="status-<?= strtolower($country_data['status']) ?>"><?= ucfirst($country_data['status']) ?></span>
                </small>
            </div>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" required>
                            <option value="" disabled>Select Country</option>
                            <?php foreach ($country_currency as $country => $currency): ?>
                                <option value="<?= htmlspecialchars($country) ?>" 
                                        <?= ($country_data['country_name'] == $country) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country) ?>
                                </option>
                            <?php endforeach; ?>
                            <!-- Allow custom country if not in predefined list -->
                            <?php if (!array_key_exists($country_data['country_name'], $country_currency)): ?>
                                <option value="<?= htmlspecialchars($country_data['country_name']) ?>" selected>
                                    <?= htmlspecialchars($country_data['country_name']) ?> (Custom)
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency Code *</label>
                        <select name="currency" id="currency" required>
                            <option value="" disabled>Select Currency</option>
                            <?php foreach ($country_currency as $country => $curr): ?>
                                <option value="<?= htmlspecialchars($curr) ?>" 
                                        data-country="<?= htmlspecialchars($country) ?>"
                                        <?= ($country_data['currency_code'] == $curr) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($curr) ?>
                                </option>
                            <?php endforeach; ?>
                            <!-- Allow custom currency if not in predefined list -->
                            <?php if (!in_array($country_data['currency_code'], $country_currency)): ?>
                                <option value="<?= htmlspecialchars($country_data['currency_code']) ?>" selected>
                                    <?= htmlspecialchars($country_data['currency_code']) ?> (Custom)
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="status">Status *</label>
                        <select name="status" id="status" required>
                            <option value="active" <?= ($country_data['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($country_data['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="submit" class="next-btn">
                    <i class="fa fa-save"></i> Update Country
                </button>
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
                    // Only auto-update if the current currency is not custom
                    const currentCurrency = currencySelect.value;
                    const isCustomCurrency = !Object.values(countryCurrencyMap).includes(currentCurrency);
                    
                    if (isCustomCurrency || confirm('Do you want to update the currency to match the selected country?')) {
                        currencySelect.value = countryCurrencyMap[country];
                    }
                }
            });

            // When currency changes, verify it matches the country
            currencySelect.addEventListener('change', function() {
                const selectedCurrency = this.value;
                const selectedCountry = countrySelect.value;
                
                if (selectedCountry && countryCurrencyMap[selectedCountry] !== selectedCurrency) {
                    const isPreDefinedCurrency = Object.values(countryCurrencyMap).includes(selectedCurrency);
                    if (isPreDefinedCurrency) {
                        alert('Warning: This currency does not normally belong to the selected country.');
                    }
                }
            });

            // Confirm before updating
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to update this country record?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>