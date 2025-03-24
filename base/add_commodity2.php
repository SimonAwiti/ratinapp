<?php
include '../admin/includes/config.php'; // Include database configuration
session_start();

// Redirect if session data is missing
if (!isset($_SESSION['category'])) {
    header('Location: add_commodity.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve data from session and form
    $category = $_SESSION['category'];
    $commodity_name = $_SESSION['commodity_name'];
    $variety = $_SESSION['variety'];
    $packaging = $_SESSION['packaging']; // Array of packaging sizes
    $unit = $_SESSION['unit']; // Array of corresponding units

    $hs_code = $_POST['hs_code'];
    $commodity_alias = $_POST['commodity_alias'];
    $country = $_POST['country'];

    // Handle file upload
    $image_url = '';
    if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/'; // Ensure this directory exists and is writable
        $image_name = basename($_FILES['commodity_image']['name']);
        $image_path = $upload_dir . $image_name;
        if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $image_path)) {
            $image_url = $image_path;
        }
    }

    // Insert data into the database using a transaction
    $con->begin_transaction();
    try {
        $sql = "INSERT INTO commodities (commodity_name, category, variety, size, unit, hs_code, commodity_alias, country, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $con->prepare($sql);

        // Insert each packaging size with its respective unit
        for ($i = 0; $i < count($packaging); $i++) {
            $size = $packaging[$i];
            $measure_unit = $unit[$i];
            
            $stmt->bind_param('sssssssss', 
                $commodity_name, 
                $category, 
                $variety, 
                $size, 
                $measure_unit, 
                $hs_code, 
                $commodity_alias, 
                $country, 
                $image_url
            );
            $stmt->execute();
        }
        
        $con->commit();
    } catch (Exception $e) {
        $con->rollback();
        die("Database error: " . $e->getMessage());
    }

    // Clear session data
    session_unset();
    session_destroy();

    // Redirect to a success page or commodities list
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity</title>
    <link rel="stylesheet" href="assets/add_commodity2.css" />
</head>
<body>
    <div class="container">
        <!-- Close button on the top right -->
        <button class="close-btn" onclick="window.location.href='dashboard.php'">&times;</button>

        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 2</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Commodity</h2>
            <p>Provide the necessary details to add a new commodity</p>
            <form method="POST" action="add_commodity2.php" enctype="multipart/form-data">
                <label for="hs-code">HS Code*</label>
                <input type="text" id="hs-code" name="hs_code" required>
                <label for="commodity-alias">Commodity Alias</label>
                <input type="text" id="commodity-alias" name="commodity_alias">
                <label for="country">Country</label>
                <select id="country" name="country" required>
                    <option value="">Select country</option>
                    <option value="Rwanda">Rwanda</option>
                    <option value="Uganda">Uganda</option>
                    <option value="Tanzania">Tanzania</option>
                    <option value="Kenya">Kenya</option>
                </select>
                <label for="commodity-image">Commodity Image</label>
                <input type="file" id="commodity-image" name="commodity_image" accept="image/*">

                <!-- Buttons on the same line -->
                <div class="button-container">
                    <button type="button" class="next-btn" onclick="window.location.href='add_commodity.php'">&larr; Previous</button>
                    <button type="submit" class="next-btn">Done &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>