<?php
session_start();
include '../admin/includes/config.php'; // Include database configuration

// Redirect if session data is missing
if (!isset($_SESSION['market_name'])) {
    header('Location: add_market.php');
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['longitude'] = $_POST['longitude'];
    $_SESSION['latitude'] = $_POST['latitude'];
    $_SESSION['radius'] = $_POST['radius'];
    $_SESSION['currency'] = $_POST['currency'];

    // Handle Image Upload (Using Commodity Image Handling Approach)
    $image_url = '';
    if (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/'; // Ensure this directory exists and is writable
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_name = basename($_FILES['imageUpload']['name']);
        $image_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['imageUpload']['tmp_name'], $image_path)) {
            $image_url = $image_path;
        }
    }

    // Store image URL in session
    $_SESSION['image_url'] = $image_url;

    session_write_close(); // Save session before redirect
    header("Location: add_market3.php");
    exit;
}
?>



<!-- Your HTML form would go here -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tradepoint</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
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
            width: 850px;
            height: 700px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative;
        }
        h2, p {
            margin-bottom: 10px;
        }
        .form-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 90%;
        }
        .selection {
            background: #f7f7d8;
            padding: 10px;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center; /* Ensures proper vertical alignment */
            gap: 120px; /* Adds space between elements */
            flex-wrap: nowrap; /* Ensures all items remain in one row */
        }

        .selection label {
            display: flex;
            align-items: center;
            gap: 8px; /* Adds space between the radio button and text */
        }
        .selection input[type="radio"] {
            margin: 0;
            width: 16px;
            height: 16px;
            vertical-align: middle;
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            height: calc(100% - 45px - 100px);
            width: 1px;
            background-color: #ccc;
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
    height: calc(100% - 45px - 100px); /* Height to connect Step 1 and Step 2 */
    width: 1px;
    background-color: #a45c40; /* Line color */
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
        /* Image Upload Styles */
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background-color: #ddd;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background-color: green;
            border-radius: 5px;
            transition: width 0.3s ease-in-out;
        }
        #imagePreview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .preview-image {
            position: relative;
            display: inline-block;
        }
        .preview-image img {
            width: 80px;
            height: 80px;
            border-radius: 5px;
            object-fit: cover;
        }
        .remove-img {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border: none;
            padding: 3px 6px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
        }
        /* Longitude & Latitude on the same line */
        .location-container {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .location-container label {
            width: 100%;
        }
        .location-container input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 60px; /* Adds space between the buttons */
        }

    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='dashboard.php'">×</button>
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 2</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 3</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Tradepoint</h2>
            <p>Provide the necessary details to add a new tradepoint</p>
            <div class="selection">
                <label><input type="radio" name="tradepoint" checked> Markets</label>
                <label><input type="radio" name="tradepoint"> Border Points</label>
                <label><input type="radio" name="tradepoint"> Millers</label>
            </div>
            <form method="POST" action="add_market2.php" enctype="multipart/form-data">
                <!-- Longitude & Latitude in one row -->
                <div class="location-container">
                    <label for="longitude">Longitude *
                        <input type="text" id="longitude" name="longitude" required>
                    </label>
                    <label for="latitude">Latitude *
                        <input type="text" id="latitude" name="latitude" required>
                    </label>
                </div>

                <label for="radius">Market radius *</label>
                <input type="text" id="radius" name="radius" required>

                <label for="currency">Currency *</label>
                <select id="currency" name="currency" required>
                    <option value="">Select currency</option>
                    <option value="KES">KES</option>
                    <option value="TSH">TSH</option>
                </select>

                <!-- Image Upload Section -->
                    <label for="imageUpload">Upload Images *</label>
                    <input type="file" id="imageUpload" name="imageUpload" accept="image/*">
                
                <!-- Progress Bar -->
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>

                <!-- Buttons on the same line -->
                <div class="button-container">
                    <button type="button" class="next-btn" onclick="window.location.href='add_market.php'">&larr; Previous</button>
                    <button type="submit" class="next-btn">Next &rarr;</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById("imageUpload").addEventListener("change", function(event) {
            const files = event.target.files;
            const preview = document.getElementById("imagePreview");
            const progressBar = document.getElementById("progressBar");
            const progressContainer = document.querySelector(".progress-bar-container");

            preview.innerHTML = ""; 
            if (files.length === 0) return;

            progressContainer.style.display = "block";
            progressBar.style.width = "0%";

            let uploaded = 0;
            const total = files.length;
            
            Array.from(files).forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const imgDiv = document.createElement("div");
                    imgDiv.classList.add("preview-image");
                    imgDiv.innerHTML = `
                        <img src="${event.target.result}" alt="Uploaded Image">
                        <button class="remove-img" onclick="removeImage(this)">✖</button>
                    `;
                    preview.appendChild(imgDiv);
                    
                    uploaded++;
                    progressBar.style.width = `${(uploaded / total) * 100}%`;

                    if (uploaded === total) {
                        setTimeout(() => { progressContainer.style.display = "none"; }, 500);
                    }
                };
                
                reader.readAsDataURL(file);
            });
        });

        function removeImage(button) {
            button.parentElement.remove();
        }
    </script>
</body>
</html>
