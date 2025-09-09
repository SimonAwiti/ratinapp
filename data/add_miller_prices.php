<?php
// add_miller_prices.php
include '../admin/includes/config.php';

// Initialize variables
$towns = [];
$commodities = [];
$data_sources = [];

// Fetch data from database
if (isset($con)) {
    // Fetch towns from miller_details
    $towns_query = "SELECT DISTINCT miller_name FROM miller_details ORDER BY miller_name";
    $towns_result = $con->query($towns_query);
    while ($row = $towns_result->fetch_assoc()) {
        $towns[] = $row['miller_name'];
    }

    // Fetch commodities
    $commodities_query = "SELECT id, commodity_name FROM commodities ORDER BY commodity_name";
    $commodities_result = $con->query($commodities_query);
    while ($row = $commodities_result->fetch_assoc()) {
        $commodities[] = $row;
    }

    // Fetch data sources
    $data_sources_query = "SELECT id, data_source_name FROM data_sources ORDER BY data_source_name";
    $data_sources_result = $con->query($data_sources_query);
    while ($row = $data_sources_result->fetch_assoc()) {
        $data_sources[] = $row;
    }
}

// Function to convert currency to USD
function convertToUSD($amount, $country) {
    if (!is_numeric($amount)) return 0;

    switch ($country) {
        case 'Kenya': return round($amount / 150, 2);   // 1 USD = 150 KES
        case 'Uganda': return round($amount / 3700, 2); // 1 USD = 3700 UGX
        case 'Tanzania': return round($amount / 2300, 2); // 1 USD = 2300 TZS
        case 'Rwanda': return round($amount / 1200, 2);  // 1 USD = 1200 RWF
        case 'Burundi': return round($amount / 2000, 2); // 1 USD = 2000 BIF
        default: return round($amount, 2);
    }
}

// Function to calculate day change percentage
function calculateDayChange($currentPrice, $commodityId, $town, $con) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $stmt = $con->prepare("SELECT price FROM miller_prices 
                          WHERE commodity_id = ? AND town = ? AND DATE(date_posted) = ?");
    $stmt->bind_param("iss", $commodityId, $town, $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $yesterdayPrice = $result->fetch_assoc()['price'];
        if ($yesterdayPrice > 0) {
            $change = (($currentPrice - $yesterdayPrice) / $yesterdayPrice) * 100;
            return round($change, 2);
        }
    }
    return null;
}

// Function to calculate month change percentage
function calculateMonthChange($currentPrice, $commodityId, $town, $con) {
    $firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));
    
    $stmt = $con->prepare("SELECT AVG(price) as avg_price FROM miller_prices 
                          WHERE commodity_id = ? AND town = ? 
                          AND DATE(date_posted) BETWEEN ? AND ?");
    $stmt->bind_param("isss", $commodityId, $town, $firstDayOfLastMonth, $lastDayOfLastMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $avgPrice = $result->fetch_assoc()['avg_price'];
        if ($avgPrice > 0) {
            $change = (($currentPrice - $avgPrice) / $avgPrice) * 100;
            return round($change, 2);
        }
    }
    return null;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Sanitize input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $town = mysqli_real_escape_string($con, $_POST['town']);
    $commodity_id = (int)$_POST['commodity'];
    $price = (float)$_POST['price'];
    $data_source_id = (int)$_POST['data_source'];
    $date = mysqli_real_escape_string($con, $_POST['date']);

    // Validate required fields
    if (empty($country) || empty($town) || $commodity_id <= 0 || 
        $price <= 0 || $data_source_id <= 0 || empty($date)) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // Convert price to USD
    $price_usd = convertToUSD($price, $country);
    
    // Calculate day and month change percentages
    $day_change = calculateDayChange($price, $commodity_id, $town, $con);
    $month_change = calculateMonthChange($price, $commodity_id, $town, $con);
    
    // Get commodity name
    $commodity_name = "";
    $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $commodity_name = $result->fetch_assoc()['commodity_name'];
    }
    
    // Get data source name
    $data_source_name = "";
    $stmt = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
    $stmt->bind_param("i", $data_source_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $data_source_name = $result->fetch_assoc()['data_source_name'];
    }
    
    // Prepare date values
    $date_posted = date('Y-m-d H:i:s', strtotime($date));
    $day = date('d', strtotime($date));
    $month = date('m', strtotime($date));
    $year = date('Y', strtotime($date));
    $status = 'pending';
    
    // Insert into database
    $stmt = $con->prepare("INSERT INTO miller_prices 
                          (country, town, commodity_id, commodity_name, price, price_usd, 
                           day_change, month_change, data_source_id, data_source_name, 
                           date_posted, day, month, year, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssisddddissiiis", 
        $country, $town, $commodity_id, $commodity_name, $price, $price_usd,
        $day_change, $month_change, $data_source_id, $data_source_name,
        $date_posted, $day, $month, $year, $status);
    
    if ($stmt->execute()) {
        echo "<script>alert('Miller price added successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
    } else {
        echo "<script>alert('Error adding miller price: " . $con->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Miller Price</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/add_commodity.css" />
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
            background-color: rgba(180, 80, 50, 0.9);
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
        /* Steps styling */
        .steps {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 10px;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 5px;
        }
        .step-circle.active {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .step-text {
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
        </div>
        
        <div class="form-container">
            <h2>Add Miller Price</h2>
            <p>Provide the details below to add a new miller price</p>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" required>
                            <option value="">Select Country</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Burundi">Burundi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="town">Town *</label>
                        <select name="town" id="town" required>
                            <option value="">Select Town</option>
                            <?php foreach ($towns as $town): ?>
                                <option value="<?= htmlspecialchars($town) ?>"><?= htmlspecialchars($town) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="commodity">Commodity *</label>
                        <select name="commodity" id="commodity" required>
                            <option value="">Select Commodity</option>
                            <?php foreach ($commodities as $commodity): ?>
                                <option value="<?= $commodity['id'] ?>">
                                    <?= htmlspecialchars($commodity['commodity_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (Local Currency) *</label>
                        <input type="number" step="0.01" name="price" id="price" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="data_source">Data Source *</label>
                        <select name="data_source" id="data_source" required>
                            <option value="">Select Data Source</option>
                            <?php foreach ($data_sources as $source): ?>
                                <option value="<?= $source['id'] ?>">
                                    <?= htmlspecialchars($source['data_source_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date">Date *</label>
                        <input type="date" name="date" id="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <button type="submit" name="submit" class="next-btn">Done →</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus the first field
            document.getElementById('country').focus();
            
            // When country changes, update currency symbol
            document.getElementById('country').addEventListener('change', function() {
                const currencySymbols = {
                    'Kenya': 'KES',
                    'Uganda': 'UGX',
                    'Tanzania': 'TZS',
                    'Rwanda': 'RWF',
                    'Burundi': 'BIF'
                };
                const symbol = currencySymbols[this.value] || '';
                document.querySelector('label[for="price"]').textContent = 
                    `Price (${symbol}) *`;
            });
        });
    </script>
</body>
</html>