<?php
include '../admin/includes/config.php'; // Include database configuration

// Fetch the commodity data based on the ID from the URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Ensure the ID is an integer
    $sql = "SELECT * FROM commodities WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $commodity = $result->fetch_assoc();

    if (!$commodity) {
        die("Commodity not found.");
    }
} else {
    die("Invalid request.");
}

// Handle form submission for updating the commodity
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_name = $_POST['commodity_name'];
    $category = $_POST['category'];
    $variety = $_POST['variety'];
    $packaging = $_POST['packaging'];
    $unit = $_POST['unit'];
    $hs_code = $_POST['hs_code'];
    $commodity_alias = $_POST['commodity_alias'];
    $country = $_POST['country'];

    // Handle file upload
    $image_url = $commodity['image_url']; // Keep the existing image if no new file is uploaded
    if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/'; // Ensure this directory exists and is writable
        $image_name = basename($_FILES['commodity_image']['name']);
        $image_path = $upload_dir . $image_name;
        if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $image_path)) {
            $image_url = $image_path;
        }
    }

    // Update the commodity in the database
    $sql = "UPDATE commodities 
            SET commodity_name = ?, category = ?, variety = ?, size = ?, unit = ?, hs_code = ?, commodity_alias = ?, country = ?, image_url = ?
            WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param(
        'sssssssssi',
        $commodity_name,
        $category,
        $variety,
        $packaging,
        $unit,
        $hs_code,
        $commodity_alias,
        $country,
        $image_url,
        $id
    );
    $stmt->execute();

    // Redirect to the commodities list page
    header('Location: commodities.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Commodity</title>
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
            height: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative; /* Required for absolute positioning of the close button */
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px; /* Positioned on the top right */
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #a45c40;
        }
        .steps {
            padding-right: 40px;
            position: relative; /* Required for the vertical line */
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px; /* Center the line with the step circles (half of 45px circle width) */
            top: 45px; /* Start from the bottom of the first step circle */
            height: calc(250px - 45px + 45px); /* Height to connect Step 1 and Step 2 */
            width: 1px;
            background-color: #ccc; /* Line color */
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 250px; /* Increased margin to 250px */
            position: relative; /* Ensure steps are above the line */
        }
        .step:last-child {
            margin-bottom: 0; /* Remove margin for the last step */
        }
        .step-circle {
            width: 45px;
            height: 45px;
            border-radius: 70%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 20px;
            font-size: 20px;
            z-index: 1; /* Ensure circles are above the line */
            background-color: #d3d3d3; /* Default inactive color */
            color: white;
            position: relative;
        }
        .step-circle::before {
            content: '✓'; /* Checkmark for active step */
            display: none; /* Hidden by default */
        }
        .step-circle.active::before {
            display: block; /* Show checkmark for active step */
        }
        .step-circle.inactive::before {
            content: ''; /* No checkmark for inactive step */
        }
        .step-circle.active {
            background-color: #a45c40; /* Active step color */
        }
        .form-container {
            flex-grow: 1;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }
        input, select, .file-input {
            width: 100%; /* Ensure full width */
            padding: 12px; /* Consistent padding */
            margin-top: 10px; /* Consistent margin */
            border: 1px solid #ccc; /* Consistent border */
            border-radius: 5px; /* Consistent border radius */
            font-size: 16px; /* Consistent font size */
            box-sizing: border-box; /* Include padding and border in width */
        }
        .file-input {
            background-color: white; /* Match the background color */
            cursor: pointer; /* Indicate it's clickable */
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .next-btn {
            background-color: #a45c40;
            color: white;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 5px;
            width: 48%; /* Adjusted width to fit both buttons */
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Close button on the top right -->
        <button class="close-btn" onclick="window.location.href='commodities.php'">×</button>

        <div class="form-container">
            <h2>Edit Commodity</h2>
            <p>Update the details of the commodity</p>
            <form method="POST" action="edit_commodity.php?id=<?= $id ?>" enctype="multipart/form-data">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="Oil seeds" <?= $commodity['category'] === 'Oil seeds' ? 'selected' : '' ?>>Oil seeds</option>
                    <option value="Pulses" <?= $commodity['category'] === 'Pulses' ? 'selected' : '' ?>>Pulses</option>
                    <option value="Cereals" <?= $commodity['category'] === 'Cereals' ? 'selected' : '' ?>>Cereals</option>
                </select>
                <label for="commodity-name">Commodity name *</label>
                <input type="text" id="commodity-name" name="commodity_name" value="<?= htmlspecialchars($commodity['commodity_name']) ?>" required>
                <label for="variety">Variety</label>
                <input type="text" id="variety" name="variety" value="<?= htmlspecialchars($commodity['variety']) ?>" required>
                <label>Commodity Packaging</label>
                <input type="text" id="packaging" name="packaging" value="<?= htmlspecialchars($commodity['size']) ?>" required>
                <label for="unit">Measuring unit</label>
                <select id="unit" name="unit" required>
                    <option value="Kg" <?= $commodity['unit'] === 'Kg' ? 'selected' : '' ?>>Kg</option>
                    <option value="Tons" <?= $commodity['unit'] === 'Tons' ? 'selected' : '' ?>>Tons</option>
                </select>
                <label for="hs-code">HS Code*</label>
                <input type="text" id="hs-code" name="hs_code" value="<?= htmlspecialchars($commodity['hs_code']) ?>" required>
                <label for="commodity-alias">Commodity Alias</label>
                <input type="text" id="commodity-alias" name="commodity_alias" value="<?= htmlspecialchars($commodity['commodity_alias']) ?>">
                <label for="country">Country</label>
                <select id="country" name="country" required>
                    <option value="Rwanda" <?= $commodity['country'] === 'Rwanda' ? 'selected' : '' ?>>Rwanda</option>
                    <option value="Uganda" <?= $commodity['country'] === 'Uganda' ? 'selected' : '' ?>>Uganda</option>
                    <option value="Tanzania" <?= $commodity['country'] === 'Tanzania' ? 'selected' : '' ?>>Tanzania</option>
                    <option value="Kenya" <?= $commodity['country'] === 'Kenya' ? 'selected' : '' ?>>Kenya</option>
                </select>
                <label for="commodity-image">Commodity Image</label>
                <input type="file" id="commodity-image" name="commodity_image" accept="image/*">
                <?php if ($commodity['image_url']): ?>
                    <p>Current Image: <img src="<?= htmlspecialchars($commodity['image_url']) ?>" alt="Commodity Image" width="100"></p>
                <?php endif; ?>

                <!-- Buttons on the same line -->
                <div class="button-container">
                    <button type="button" class="next-btn" onclick="window.location.href='dashboard.php'">&larr; Cancel</button>
                    <button type="submit" class="next-btn">Update &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>