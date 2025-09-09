<?php
// edit_marketprice.php

// Include your database configuration file
include '../admin/includes/config.php';

// Check if price ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid price ID");
}
$price_id = (int)$_GET['id'];

// Fetch the price record to edit
$sql = "SELECT * FROM market_prices WHERE id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $price_id);
$stmt->execute();
$result = $stmt->get_result();
$price_data = $result->fetch_assoc();
$stmt->close();

if (!$price_data) {
    die("Price record not found");
}

// Fetch all necessary data for dropdowns
$countries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Burundi'];
$categories = ['Cereals', 'Pulses', 'Oil seeds'];
$measuring_units = ['kg', 'tons'];

// Fetch markets for dropdown
$markets = [];
$markets_query = "SELECT id, market_name FROM markets";
$markets_result = $con->query($markets_query);
if ($markets_result) {
    while ($row = $markets_result->fetch_assoc()) {
        $markets[] = $row;
    }
    $markets_result->free();
}

// Fetch commodities for dropdown
$commodities = [];
$commodities_query = "SELECT id, commodity_name FROM commodities";
$commodities_result = $con->query($commodities_query);
if ($commodities_result) {
    while ($row = $commodities_result->fetch_assoc()) {
        $commodities[] = $row;
    }
    $commodities_result->free();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Sanitize and validate input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $market_id = (int)$_POST['market'];
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $commodity_id = (int)$_POST['commodity'];
    $packaging_unit = mysqli_real_escape_string($con, $_POST['packaging_unit']);
    $measuring_unit = mysqli_real_escape_string($con, $_POST['measuring_unit']);
    $variety = mysqli_real_escape_string($con, $_POST['variety']);
    $price_type = mysqli_real_escape_string($con, $_POST['price_type']);
    $price = (float)$_POST['price'];
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $data_source = mysqli_real_escape_string($con, $_POST['data_source']);

    // Update the record
    $update_sql = "UPDATE market_prices SET 
                    country_admin_0 = ?,
                    market_id = ?,
                    market = ?,
                    category = ?,
                    commodity = ?,
                    weight = ?,
                    unit = ?,
                    variety = ?,
                    price_type = ?,
                    Price = ?,
                    status = ?,
                    data_source = ?
                   WHERE id = ?";
    
    // Get market name
    $market_name = '';
    foreach ($markets as $market) {
        if ($market['id'] == $market_id) {
            $market_name = $market['market_name'];
            break;
        }
    }
    
    // Get commodity name
    $commodity_name = '';
    foreach ($commodities as $commodity) {
        if ($commodity['id'] == $commodity_id) {
            $commodity_name = $commodity['commodity_name'];
            break;
        }
    }
    
    $stmt = $con->prepare($update_sql);
    $stmt->bind_param("sisssssssdssi", 
        $country,
        $market_id,
        $market_name,
        $category,
        $commodity_id,
        $packaging_unit,
        $measuring_unit,
        $variety,
        $price_type,
        $price,
        $status,
        $data_source,
        $price_id
    );
    
    if ($stmt->execute()) {
        header("Location: ../base/commodities_boilerplate.php?success=1");
        exit();
    } else {
        $error = "Error updating record: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Market Price</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-top: 0;
            color: #333;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: rgba(160, 70, 40, 1);
        }
        .btn-cancel {
            background-color: #6c757d;
            margin-left: 10px;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        .error {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .success {
            color: #28a745;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Market Price</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="country">Country *</label>
                    <select name="country" id="country" required>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country; ?>"
                                <?php if ($country == $price_data['country_admin_0']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="market">Market *</label>
                    <select name="market" id="market" required>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?php echo $market['id']; ?>"
                                <?php if ($market['id'] == $price_data['market_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($market['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select name="category" id="category" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>"
                                <?php if ($category == $price_data['category']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="commodity">Commodity *</label>
                    <select name="commodity" id="commodity" required>
                        <?php foreach ($commodities as $commodity): ?>
                            <option value="<?php echo $commodity['id']; ?>"
                                <?php if ($commodity['id'] == $price_data['commodity']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($commodity['commodity_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="packaging_unit">Packaging Unit *</label>
                    <input type="text" name="packaging_unit" id="packaging_unit" 
                           value="<?php echo htmlspecialchars($price_data['weight']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="measuring_unit">Measuring Unit *</label>
                    <select name="measuring_unit" id="measuring_unit" required>
                        <?php foreach ($measuring_units as $unit): ?>
                            <option value="<?php echo $unit; ?>"
                                <?php if ($unit == $price_data['unit']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($unit); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="variety">Variety</label>
                    <input type="text" name="variety" id="variety" 
                           value="<?php echo htmlspecialchars($price_data['variety']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="data_source">Data Source</label>
                    <input type="text" name="data_source" id="data_source" 
                           value="<?php echo htmlspecialchars($price_data['data_source']); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="price_type">Price Type *</label>
                    <select name="price_type" id="price_type" required>
                        <option value="Wholesale" <?php if ($price_data['price_type'] == 'Wholesale') echo 'selected'; ?>>Wholesale</option>
                        <option value="Retail" <?php if ($price_data['price_type'] == 'Retail') echo 'selected'; ?>>Retail</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price">Price ($) *</label>
                    <input type="number" step="0.01" name="price" id="price" 
                           value="<?php echo htmlspecialchars($price_data['Price']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="status">Status *</label>
                <select name="status" id="status" required>
                    <option value="pending" <?php if ($price_data['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="published" <?php if ($price_data['status'] == 'published') echo 'selected'; ?>>Published</option>
                    <option value="approved" <?php if ($price_data['status'] == 'approved') echo 'selected'; ?>>Approved</option>
                    <option value="unpublished" <?php if ($price_data['status'] == 'unpublished') echo 'selected'; ?>>Unpublished</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" name="submit" class="btn">Update Price</button>
                <a href="../base/commodities_boilerplate.php" class="btn btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>