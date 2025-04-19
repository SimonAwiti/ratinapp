<?php
session_start();
include '../admin/includes/config.php';

if (!isset($_SESSION['border_name'])) {
    header('Location: add_borderpoint.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Insert border point info first
    $stmt = $con->prepare("INSERT INTO border_points (name, country, county, longitude, latitude, radius, tradepoint) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssddds", 
        $_SESSION['border_name'], 
        $_SESSION['border_country'], 
        $_SESSION['border_county'], 
        $_SESSION['longitude'], 
        $_SESSION['latitude'], 
        $_SESSION['radius'],
        $_SESSION['tradepoint'] // This should already be set in Step 1
    );
    
    if ($stmt->execute()) {
        $border_point_id = $stmt->insert_id;

        // Handle image uploads
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['borderImages']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['borderImages']['error'][$key] === UPLOAD_ERR_OK) {
                $image_name = basename($_FILES['borderImages']['name'][$key]);
                $image_path = $upload_dir . time() . '_' . $image_name;

                if (move_uploaded_file($tmp_name, $image_path)) {
                    $img_stmt = $con->prepare("INSERT INTO border_images (border_point_id, image_path) VALUES (?, ?)");
                    $img_stmt->bind_param("is", $border_point_id, $image_path);
                    $img_stmt->execute();
                }
            }
        }

        // Clear session & redirect
        session_unset();
        echo "<script>alert('Border Point added successfully!'); window.location.href='addtradepoint.php';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to save border point!'); window.history.back();</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Border Point - Step 2</title>
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
            height: 40%;
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
    height: calc(100% - 45px - 405px); /* Height to connect Step 1 and Step 2 */
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
        <div class="steps">
            <div class="step"><div class="step-circle active"></div><span>Step 1</span></div>
            <div class="step"><div class="step-circle active"></div><span>Step 2</span></div>
        </div>
        <div class="form-container">
            <h2>Add Border Point</h2>
            <p>Upload one or more images of the border point</p>
            <form method="POST" action="" enctype="multipart/form-data">
                <label for="borderImages">Upload Images *</label>
                <input type="file" id="borderImages" name="borderImages[]" multiple accept="image/*" required>

                <div class="button-container">
                    <button type="button" class="next-btn" onclick="window.location.href='addtradepoint.php'">&larr; Previous</button>
                    <button type="submit" class="next-btn">done &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
