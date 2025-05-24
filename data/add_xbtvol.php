<?php
// add_xbt_volumes.php

// Include your database configuration file
include '../admin/includes/config.php';

$border_points = [];
$commodities = [];
$categories = [];
$data_sources = [];
$varieties = [];

if (isset($con)) {
    // Fetch border points from the database
    $border_query = "SELECT id, name FROM border_points";
    $border_result = $con->query($border_query);
    if ($border_result) {
        while ($row = $border_result->fetch_assoc()) {
            $border_points[] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
        $border_result->free();
    }

    // Fetch commodities from the database
    $commodities_query = "SELECT id, commodity_name, variety, category_id FROM commodities";
    $commodities_result = $con->query($commodities_query);
    if ($commodities_result) {
        while ($row = $commodities_result->fetch_assoc()) {
            $commodities[] = [
                'id' => $row['id'],
                'commodity_name' => $row['commodity_name'],
                'variety' => $row['variety'],
                'category_id' => $row['category_id']
            ];
            
            // Store unique varieties
            if (!empty($row['variety']) && !in_array($row['variety'], $varieties)) {
                $varieties[] = $row['variety'];
            }
        }
        $commodities_result->free();
    }

    // Fetch categories from the database
    $categories_query = "SELECT id, name FROM commodity_categories";
    $categories_result = $con->query($categories_query);
    if ($categories_result) {
        while ($row = $categories_result->fetch_assoc()) {
            $categories[] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
        $categories_result->free();
    }

    // Fetch data sources from the database
    $data_sources_query = "SELECT id, data_source_name FROM data_sources";
    $data_sources_result = $con->query($data_sources_query);
    if ($data_sources_result) {
        while ($row = $data_sources_result->fetch_assoc()) {
            $data_sources[] = [
                'id' => $row['id'],
                'source_name' => $row['data_source_name']
            ];
        }
        $data_sources_result->free();
    }
}

// Processing the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($con) && isset($_POST['submit'])) {
    // Sanitize and validate input
    $country = isset($_POST['country']) ? mysqli_real_escape_string($con, $_POST['country']) : '';
    $border_id = isset($_POST['border']) ? (int)$_POST['border'] : 0;
    $commodity_id = isset($_POST['commodity']) ? (int)$_POST['commodity'] : 0;
    $category_id = isset($_POST['category']) ? (int)$_POST['category'] : 0;
    $variety = isset($_POST['variety']) ? mysqli_real_escape_string($con, $_POST['variety']) : '';
    $volume = isset($_POST['volume']) ? (float)$_POST['volume'] : 0;
    $source = isset($_POST['source']) ? mysqli_real_escape_string($con, $_POST['source']) : '';
    $destination = isset($_POST['destination']) ? mysqli_real_escape_string($con, $_POST['destination']) : '';
    $data_source_id = isset($_POST['data_source']) ? (int)$_POST['data_source'] : 0;

    // Validate required fields
    if (empty($country) || $border_id <= 0 || $commodity_id <= 0 || $category_id <= 0 || 
        $volume <= 0 || empty($source) || empty($destination) || $data_source_id <= 0) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // Get current date
    $date_posted = date('Y-m-d H:i:s');
    $status = 'pending';
    $day = date('d');
    $month = date('m');
    $year = date('Y');

    // Fetch border point name
    $border_name = "";
    $stmt_border = $con->prepare("SELECT name FROM border_points WHERE id = ?");
    $stmt_border->bind_param("i", $border_id);
    $stmt_border->execute();
    $border_result = $stmt_border->get_result();
    if ($border_result && $border_result->num_rows > 0) {
        $border_row = $border_result->fetch_assoc();
        $border_name = $border_row['name'];
    }
    $stmt_border->close();

    // Fetch commodity name and variety
    $commodity_name = "";
    $commodity_variety = "";
    $stmt_commodity = $con->prepare("SELECT commodity_name, variety FROM commodities WHERE id = ?");
    $stmt_commodity->bind_param("i", $commodity_id);
    $stmt_commodity->execute();
    $commodity_result = $stmt_commodity->get_result();
    if ($commodity_result && $commodity_result->num_rows > 0) {
        $commodity_row = $commodity_result->fetch_assoc();
        $commodity_name = $commodity_row['commodity_name'];
        $commodity_variety = $commodity_row['variety'];
    }
    $stmt_commodity->close();

    // Fetch category name
    $category_name = "";
    $stmt_category = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
    $stmt_category->bind_param("i", $category_id);
    $stmt_category->execute();
    $category_result = $stmt_category->get_result();
    if ($category_result && $category_result->num_rows > 0) {
        $category_row = $category_result->fetch_assoc();
        $category_name = $category_row['name'];
    }
    $stmt_category->close();

    // Fetch data source name
    $data_source_name = "";
    $stmt_data_source = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
    $stmt_data_source->bind_param("i", $data_source_id);
    $stmt_data_source->execute();
    $data_source_result = $stmt_data_source->get_result();
    if ($data_source_result && $data_source_result->num_rows > 0) {
        $data_source_row = $data_source_result->fetch_assoc();
        $data_source_name = $data_source_row['data_source_name'];
    }
    $stmt_data_source->close();

    // Insert into xbt_volumes table
    $sql = "INSERT INTO xbt_volumes (country, border_id, border_name, commodity_id, commodity_name, 
            category_id, category_name, variety, volume, source, destination, data_source_id, 
            data_source_name, date_posted, status, day, month, year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "sissssissssssssiii",
            $country, $border_id, $border_name, $commodity_id, $commodity_name,
            $category_id, $category_name, $variety, $volume, $source, $destination,
            $data_source_id, $data_source_name, $date_posted, $status, $day, $month, $year
        );

        if ($stmt->execute()) {
            echo "<script>alert('New XBT volume record created successfully'); window.location.href='../base/sidebar.php';</script>";
        } else {
            error_log("Error inserting XBT volume: " . $stmt->error);
            echo "<script>alert('Error inserting record: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        error_log("Error preparing XBT volume insert statement: " . $con->error);
        echo "<script>alert('Error preparing statement: " . $con->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add XBT Volume Data</title>
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
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add XBT Volume Data</h2>
            <p>Provide the details below to add Cross Border Trade Volume Data</p>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" required>
                            <option value="" disabled selected>Select Country</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Burundi">Burundi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="border">Border Point *</label>
                        <select name="border" id="border" required>
                            <option value="" disabled selected>Select Border Point</option>
                            <?php foreach ($border_points as $border): ?>
                                <option value="<?php echo htmlspecialchars($border['id']); ?>">
                                    <?php echo htmlspecialchars($border['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="commodity">Commodity *</label>
                        <select name="commodity" id="commodity" required>
                            <option value="" disabled selected>Select Commodity</option>
                            <?php foreach ($commodities as $commodity): ?>
                                <option value="<?php echo htmlspecialchars($commodity['id']); ?>">
                                    <?php echo htmlspecialchars($commodity['commodity_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select name="category" id="category" required>
                            <option value="" disabled selected>Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="variety">Variety</label>
                        <select name="variety" id="variety">
                            <option value="" selected>Select Variety</option>
                            <?php foreach ($varieties as $variety): ?>
                                <option value="<?php echo htmlspecialchars($variety); ?>">
                                    <?php echo htmlspecialchars($variety); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="volume">Volume (MT) *</label>
                        <input type="number" step="0.01" name="volume" id="volume" 
                               placeholder="Enter volume in metric tons" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="source">Source Country *</label>
                        <select name="source" id="source" required>
                            <option value="" disabled selected>Select Source</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Burundi">Burundi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="destination">Destination Country *</label>
                        <select name="destination" id="destination" required>
                            <option value="" disabled selected>Select Destination</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Burundi">Burundi</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="data_source">Data Source *</label>
                        <select name="data_source" id="data_source" required>
                            <option value="" disabled selected>Select Data Source</option>
                            <?php foreach ($data_sources as $source): ?>
                                <option value="<?php echo htmlspecialchars($source['id']); ?>">
                                    <?php echo htmlspecialchars($source['source_name']); ?>
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
            const commoditySelect = document.getElementById('commodity');
            const categorySelect = document.getElementById('category');
            const varietySelect = document.getElementById('variety');

            // Store commodities data for JavaScript
            const commoditiesData = <?php echo json_encode($commodities); ?>;
            
            // When commodity changes, update the category and variety
            commoditySelect.addEventListener('change', function() {
                const commodityId = this.value;
                if (!commodityId) return;

                // Find the selected commodity
                const selectedCommodity = commoditiesData.find(
                    commodity => String(commodity.id) === String(commodityId)
                );

                if (selectedCommodity) {
                    // Update category
                    if (selectedCommodity.category_id) {
                        categorySelect.value = selectedCommodity.category_id;
                    }
                    
                    // Update variety
                    if (selectedCommodity.variety) {
                        // Check if the variety exists in the dropdown
                        let varietyFound = false;
                        for (let i = 0; i < varietySelect.options.length; i++) {
                            if (varietySelect.options[i].value === selectedCommodity.variety) {
                                varietySelect.value = selectedCommodity.variety;
                                varietyFound = true;
                                break;
                            }
                        }
                        
                        // If variety not found in dropdown, add it
                        if (!varietyFound && selectedCommodity.variety) {
                            const newOption = new Option(selectedCommodity.variety, selectedCommodity.variety);
                            varietySelect.add(newOption);
                            varietySelect.value = selectedCommodity.variety;
                        }
                    } else {
                        varietySelect.value = '';
                    }
                }
            });

            // If a commodity is pre-selected (form submission error), trigger the change event
            if (commoditySelect.value) {
                commoditySelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>