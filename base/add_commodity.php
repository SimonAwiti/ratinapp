<?php
include '../admin/includes/config.php'; // Include database configuration

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    // Store form data in session for use in the next step
    $_SESSION['category'] = $_POST['category'];
    $_SESSION['commodity_name'] = $_POST['commodity_name'];
    $_SESSION['variety'] = $_POST['variety'];
    $_SESSION['packaging'] = $_POST['packaging'];
    $_SESSION['unit'] = $_POST['unit'];

    // Redirect to the next step
    header('Location: add_commodity2.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
</head>
<body>
    <div class="container">
        <!-- Close button on the top right -->
        <button class="close-btn" onclick="window.location.href='dashboard.php'">×</button>

        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 2</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Commodity</h2>
            <p>Provide the necessary details to add a new commodity</p>
            <form method="POST" action="add_commodity.php">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Oil seeds">Oil seeds</option>
                    <option value="Pulses">Pulses</option>
                    <option value="Cereals">Cereals</option>
                </select>
                <label for="commodity-name">Commodity name *</label>
                <input type="text" id="commodity-name" name="commodity_name" required>
                <label for="variety">Variety</label>
                <input type="text" id="variety" name="variety" required>
                <label>Commodity Packaging</label>
                <input type="text" id="packaging" name="packaging" required>
                <label for="unit">Measuring unit</label>
                <select id="unit" name="unit" required>
                    <option value="">Select unit</option>
                    <option value="Kg">Kg</option>
                    <option value="Tons">Tons</option>
                </select>
                <button type="submit" class="next-btn">Next &rarr;</button>
            </form>
        </div>
    </div>
</body>
</html>