<?php
// edit_currency.php
include '../admin/includes/config.php';

$countries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Burundi'];
$currencies = ['KES', 'UGX', 'TZS', 'RWF', 'BIF', 'USD', 'EUR'];

// Get currency ID from URL parameter
$currency_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing currency data
$currency_data = null;
if ($currency_id > 0) {
    $stmt = $con->prepare("SELECT * FROM currencies WHERE id = ?");
    $stmt->bind_param("i", $currency_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $currency_data = $result->fetch_assoc();
    
    if (!$currency_data) {
        echo "<script>alert('Currency record not found'); window.location.href='../base/commodities_boilerplate.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('Invalid currency ID'); window.location.href='../base/commodities_boilerplate.php';</script>";
    exit;
}

// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // Sanitize input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $currency = mysqli_real_escape_string($con, $_POST['currency']);
    $rate = (float)$_POST['rate'];
    $date = mysqli_real_escape_string($con, $_POST['date']);

    // Validate required fields
    if (empty($country) || empty($currency) || $rate <= 0 || empty($date)) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // Get current date for record keeping
    $date_updated = date('Y-m-d H:i:s');
    $day = date('d', strtotime($date));
    $month = date('m', strtotime($date));
    $year = date('Y', strtotime($date));

    // Update currencies table
    $stmt = $con->prepare("UPDATE currencies SET 
                          country = ?, 
                          currency_code = ?, 
                          exchange_rate = ?, 
                          effective_date = ?,
                          date_updated = ?,
                          day = ?, 
                          month = ?, 
                          year = ?
                          WHERE id = ?");
    
    $stmt->bind_param("ssdssiiit", 
        $country,
        $currency,
        $rate,
        $date,
        $date_updated,
        $day,
        $month,
        $year,
        $currency_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Currency rate updated successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
    } else {
        echo "<script>alert('Error updating currency rate: " . $con->error . "');</script>";
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
    <title>Edit Currency Rate</title>
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
            height: 800px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative;
        }
        h2 {
            margin-bottom: 10px;
        }
        p {
            margin-bottom: 20px;
        }
        .form-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            width: 100%;
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
        .form-group-full {
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input, select {
            padding: 8px;
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
        .update-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
        }
        .update-btn:hover {
            background-color: rgba(160, 60, 30, 1);
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
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }
        .currency-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .currency-info h5 {
            margin-bottom: 10px;
            color: rgba(180, 80, 50, 1);
        }
        .currency-info p {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>
        <div class="form-container">
            <div>
                <h2>Edit Currency Rate</h2>
                <p>Update the currency exchange rate details below</p>
                
                <!-- Display current currency info -->
                <div class="currency-info">
                    <h5>Current Record Information</h5>
                    <p><strong>Country:</strong> <?= htmlspecialchars($currency_data['country']) ?></p>
                    <p><strong>Currency:</strong> <?= htmlspecialchars($currency_data['currency_code']) ?></p>
                    <p><strong>Current Rate:</strong> <?= number_format($currency_data['exchange_rate'], 4) ?></p>
                    <p><strong>Effective Date:</strong> <?= htmlspecialchars($currency_data['effective_date']) ?></p>
                    <p><strong>Last Updated:</strong> <?= htmlspecialchars($currency_data['date_created']) ?></p>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" required>
                            <option value="" disabled>Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= htmlspecialchars($country) ?>" 
                                        <?= ($country == $currency_data['country']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="currency">Currency Code *</label>
                        <select name="currency" id="currency" required>
                            <option value="" disabled>Select Currency</option>
                            <?php foreach ($currencies as $curr): ?>
                                <option value="<?= htmlspecialchars($curr) ?>"
                                        <?= ($curr == $currency_data['currency_code']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($curr) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="rate">Exchange Rate *</label>
                        <input type="number" 
                               name="rate" 
                               id="rate" 
                               step="0.0001" 
                               min="0.0001"
                               value="<?= htmlspecialchars($currency_data['exchange_rate']) ?>"
                               placeholder="Enter exchange rate" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="date">Effective Date *</label>
                        <input type="date" 
                               name="date" 
                               id="date" 
                               value="<?= htmlspecialchars($currency_data['effective_date']) ?>"
                               required>
                    </div>
                </div>

                <button type="submit" name="submit" class="update-btn">
                    <i class="fa fa-save"></i> Update Currency Rate
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rateInput = document.getElementById('rate');
            const dateInput = document.getElementById('date');
            
            // Auto-format rate input
            rateInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value) && value > 0) {
                    // Optional: You can add real-time formatting here
                }
            });

            // Set default date to today if empty
            if (!dateInput.value) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }

            // Form validation before submit
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const rate = parseFloat(rateInput.value);
                const date = dateInput.value;
                const country = document.getElementById('country').value;
                const currency = document.getElementById('currency').value;

                if (!country || !currency || !rate || rate <= 0 || !date) {
                    e.preventDefault();
                    alert('Please fill all required fields with valid values.');
                    return false;
                }

                // Confirm update
                const confirmUpdate = confirm(`Are you sure you want to update this currency rate?\n\nCountry: ${country}\nCurrency: ${currency}\nRate: ${rate}\nDate: ${date}`);
                if (!confirmUpdate) {
                    e.preventDefault();
                    return false;
                }
            });

            // Highlight changes
            const originalValues = {
                country: '<?= htmlspecialchars($currency_data['country']) ?>',
                currency: '<?= htmlspecialchars($currency_data['currency_code']) ?>',
                rate: '<?= htmlspecialchars($currency_data['exchange_rate']) ?>',
                date: '<?= htmlspecialchars($currency_data['effective_date']) ?>'
            };

            function highlightChanges() {
                const fields = ['country', 'currency', 'rate', 'date'];
                fields.forEach(field => {
                    const element = document.getElementById(field);
                    if (element.value !== originalValues[field]) {
                        element.style.borderColor = 'rgba(180, 80, 50, 0.8)';
                        element.style.backgroundColor = 'rgba(255, 248, 220, 0.5)';
                    } else {
                        element.style.borderColor = '#ccc';
                        element.style.backgroundColor = 'white';
                    }
                });
            }

            // Add event listeners to highlight changes
            ['country', 'currency', 'rate', 'date'].forEach(field => {
                document.getElementById(field).addEventListener('change', highlightChanges);
                document.getElementById(field).addEventListener('input', highlightChanges);
            });
        });
    </script>
</body>
</html>