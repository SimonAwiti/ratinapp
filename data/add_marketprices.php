<?php
// Include your database configuration file
include '../admin/includes/config.php';

$markets = [];

if (isset($con)) {
    // Fetch market names and IDs from the database, and primary_commodity
    $markets_query = "SELECT m.id, m.market_name, m.primary_commodity, c.commodity_name
                      FROM markets m
                      LEFT JOIN commodities c ON m.primary_commodity = c.id";
    $markets_result = $con->query($markets_query);

    if ($markets_result) {
        if ($markets_result->num_rows > 0) {
            while ($row = $markets_result->fetch_assoc()) {
                $markets[] = [
                    'id' => $row['id'],
                    'market_name' => $row['market_name'],
                    'primary_commodity_id' => $row['primary_commodity'],
                    'commodity_name' => $row['commodity_name']
                ];
            }
        }
        $markets_result->free();
    } else {
        echo "Error fetching markets: " . $con->error;
    }
} else {
    echo "Error: Database connection not established.";
}

// Function to convert currency to USD (replace with actual conversion logic)
function convertToUSD($amount, $country) {
    // Ensure amount is numeric
    if (!is_numeric($amount)) {
        return 0;
    }
    
    // This is a placeholder for the actual conversion logic.
    switch ($country) {
        case 'Kenya':
            return round($amount / 150, 2); // 1 USD = 150 KES
        case 'Uganda':
            return round($amount / 3700, 2); // 1 USD = 3700 UGX
        case 'Tanzania':
            return round($amount / 2300, 2); // 1 USD = 2300 TZS
        case 'Rwanda':
            return round($amount / 1200, 2); // 1 USD = 1200 RWF
        case 'Burundi':
            return round($amount / 2000, 2); // 1 USD = 2000 BIF
        default:
            return round($amount, 2); // Default to USD if country not found
    }
}

// Processing the form submission only when all fields are submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // Initialize variables with default values
    $country = isset($_POST['country']) ? mysqli_real_escape_string($con, $_POST['country']) : '';
    $market_id = isset($_POST['market']) ? mysqli_real_escape_string($con, $_POST['market']) : '';
    $category = isset($_POST['category']) ? mysqli_real_escape_string($con, $_POST['category']) : '';
    $commodity = isset($_POST['commodity']) ? mysqli_real_escape_string($con, $_POST['commodity']) : '';
    $packaging_unit = isset($_POST['packaging_unit']) ? mysqli_real_escape_string($con, $_POST['packaging_unit']) : '';
    $measuring_unit = isset($_POST['measuring_unit']) ? mysqli_real_escape_string($con, $_POST['measuring_unit']) : '';
    $variety = isset($_POST['variety']) ? mysqli_real_escape_string($con, $_POST['variety']) : '';
    $data_source = isset($_POST['data_source']) ? mysqli_real_escape_string($con, $_POST['data_source']) : '';
    $wholesale_price = isset($_POST['wholesale_price']) ? (float)$_POST['wholesale_price'] : 0;
    $retail_price = isset($_POST['retail_price']) ? (float)$_POST['retail_price'] : 0;

    // Validate required fields
    if (empty($country) || empty($market_id) || empty($category) || empty($commodity) || 
        empty($packaging_unit) || empty($measuring_unit) || empty($variety) || 
        empty($data_source) || $wholesale_price <= 0 || $retail_price <= 0) {
        die("Please fill all required fields with valid values.");
    }

    // Get current date
    $date_posted = date('Y-m-d H:i:s');
    $status = 'pending';
    $day = date('d');
    $month = date('m');
    $year = date('Y');
    $subject = "Market Prices";
    $country_admin_0 = $country;

    // Convert prices to USD
    $wholesale_price_usd = convertToUSD($wholesale_price, $country);
    $retail_price_usd = convertToUSD($retail_price, $country);

    // Get the market name from the market ID
    $market_name = "Unknown Market";
    $market_name_query = "SELECT market_name FROM markets WHERE id = $market_id";
    $market_name_result = $con->query($market_name_query);
    if ($market_name_result && $market_name_result->num_rows > 0) {
        $market_name_row = $market_name_result->fetch_assoc();
        $market_name = $market_name_row['market_name'];
    }

    // Prepare and execute the SQL query
    $sql = "INSERT INTO market_prices (category, commodity, country_admin_0, market_id, market, weight, unit, price_type, Price, subject, day, month, year, date_posted, status, variety, data_source)
            VALUES ('$category', '$commodity', '$country_admin_0', '$market_id', '$market_name', '$packaging_unit', '$measuring_unit', 'Wholesale', '$wholesale_price_usd', '$subject', '$day', '$month', '$year', '$date_posted', '$status', '$variety', '$data_source'),
                   ('$category', '$commodity', '$country_admin_0', '$market_id', '$market_name', '$packaging_unit', '$measuring_unit', 'Retail', '$retail_price_usd', '$subject', '$day', '$month', '$year', '$date_posted', '$status', '$variety', '$data_source')";

    if ($con->multi_query($sql) === TRUE) {
        echo "<script>alert('New records created successfully'); window.location.href='../base/sidebar.php';</script>";
    } else {
        echo "Error: " . $sql . "<br>" . $con->error;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Price Data</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
    <style>
        <?php include '../base/assets/add_commodity.css'; ?>
    </style>
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
        form label:first-of-type {
            margin-top: 10px;
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
            background-color:rgba(180, 80, 50, 1);
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Market Price Data</h2>
            <p>Provide the details below to Add Market Price Data</p>
            <form method="POST" action="">
                <label for="country">Country *</label>
                <select name="country" id="country" required>
                    <option value="Kenya">Kenya</option>
                    <option value="Uganda">Uganda</option>
                    <option value="Tanzania">Tanzania</option>
                    <option value="Rwanda">Rwanda</option>
                    <option value="Burundi">Burundi</option>
                </select>

                <label for="market">Market *</label>
                <select name="market" id="market" required>
                    <?php if (empty($markets)): ?>
                        <option value="" disabled>No markets available</option>
                    <?php else: ?>
                        <option value="" disabled selected>Select Market</option>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?php echo htmlspecialchars($market['id']); ?>" 
                                <?php echo (isset($_POST['market']) && $_POST['market'] == $market['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($market['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select name="category" id="category" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="cereals" <?php echo (isset($_POST['category']) && $_POST['category'] == 'cereals') ? 'selected' : ''; ?>>Cereals</option>
                            <option value="pulses" <?php echo (isset($_POST['category']) && $_POST['category'] == 'pulses') ? 'selected' : ''; ?>>Pulses</option>
                            <option value="oil_seeds" <?php echo (isset($_POST['category']) && $_POST['category'] == 'oil_seeds') ? 'selected' : ''; ?>>Oil Seeds</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="commodity">Commodity *</label>
                        <select name="commodity" id="commodity" required>
                            <option value="" disabled selected>Select Commodity</option>
                            <?php 
                            if (isset($_POST['market'])) {
                                $selected_market_id = $_POST['market'];
                                foreach ($markets as $market_data) {
                                    if ($market_data['id'] == $selected_market_id) {
                                        echo '<option value="'.htmlspecialchars($market_data['primary_commodity_id']).'" 
                                              '.((isset($_POST['commodity']) && $_POST['commodity'] == $market_data['primary_commodity_id']) ? 'selected' : '').'>
                                              '.htmlspecialchars($market_data['commodity_name']).'</option>';
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="packaging_unit">Packaging Unit *</label>
                        <input type="text" name="packaging_unit" id="packaging_unit" 
                               value="<?php echo isset($_POST['packaging_unit']) ? htmlspecialchars($_POST['packaging_unit']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="measuring_unit">Measuring Unit *</label>
                        <select name="measuring_unit" id="measuring_unit" required>
                            <option value="" disabled selected>Select Unit</option>
                            <option value="kg" <?php echo (isset($_POST['measuring_unit']) && $_POST['measuring_unit'] == 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                            <option value="tons" <?php echo (isset($_POST['measuring_unit']) && $_POST['measuring_unit'] == 'tons') ? 'selected' : ''; ?>>Tons</option>

                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="variety">Variety *</label>
                        <input type="text" name="variety" id="variety" 
                               value="<?php echo isset($_POST['variety']) ? htmlspecialchars($_POST['variety']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="data_source">Data Source *</label>
                        <input type="text" name="data_source" id="data_source" 
                               value="<?php echo isset($_POST['data_source']) ? htmlspecialchars($_POST['data_source']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="wholesale_price">Wholesale Price *</label>
                        <input type="number" step="0.01" name="wholesale_price" id="wholesale_price" 
                               value="<?php echo isset($_POST['wholesale_price']) ? htmlspecialchars($_POST['wholesale_price']) : ''; ?>" 
                               placeholder="e.g., 150.00" required>
                    </div>
                    <div class="form-group">
                        <label for="retail_price">Retail Price *</label>
                        <input type="number" step="0.01" name="retail_price" id="retail_price" 
                               value="<?php echo isset($_POST['retail_price']) ? htmlspecialchars($_POST['retail_price']) : ''; ?>" 
                               placeholder="e.g., 180.50" required>
                    </div>
                </div>

                <button type="submit" name="submit" class="next-btn">Done →</button>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('market').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>