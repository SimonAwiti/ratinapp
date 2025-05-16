<?php
include '../admin/includes/config.php'; // Database connection

$borderpoint = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $con->prepare("SELECT * FROM border_points WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $borderpoint = $result->fetch_assoc();
    $stmt->close();

    if (!$borderpoint) {
        // Redirect if border point not found
        header("Location: tradepoints.php?status=notfound");
        exit;
    }
} else {
    // Redirect if no ID is provided
    header("Location: tradepoints.php?status=no_id");
    exit;
}

// Handle form submission for updating data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['border_name'];
    $country = $_POST['border_country'];
    $county = $_POST['border_county'];
    $longitude = $_POST['longitude'];
    $latitude = $_POST['latitude'];
    $radius = $_POST['radius'];
    $image_url = $borderpoint['image_url'] ?? ''; // Keep existing image if not new one uploaded

    // Handle Image Upload (Optional - Add your image upload logic here if applicable)
    if (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/'; // Ensure this directory exists and is writable
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_name = basename($_FILES['imageUpload']['name']);
        $image_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['imageUpload']['tmp_name'], $image_path)) {
            $image_url = $image_path; // Update to new image URL
        } else {
            echo "<script>alert('Error uploading image.');</script>";
        }
    }


    // Update query (adjust column names if they differ in your 'border_points' table)
    // Assuming columns for longitude, latitude, radius, and image_url exist
    $stmt = $con->prepare("UPDATE border_points SET name = ?, country = ?, county = ?, longitude = ?, latitude = ?, radius = ?, image_url = ? WHERE id = ?");
    $stmt->bind_param("sssdddssi", $name, $country, $county, $longitude, $latitude, $radius, $image_url, $id);

    if ($stmt->execute()) {
        header("Location: sidebar.php");
        exit;
    } else {
        echo "<script>alert('Error updating border point: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Border Point</title>
    <link rel="stylesheet" href="assets/add_commodity.css" /> <style>
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-bottom: 20px;
            text-align: center;
        }
        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        form input, form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-update {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
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
        /* Image Upload Styles - Added from add_market2.php */
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Border Point</h2>
        <?php if ($borderpoint): ?>
            <form method="POST" action="edit_borderpoint.php?id=<?= htmlspecialchars($borderpoint['id']) ?>" enctype="multipart/form-data">
                <label for="border_name">Name of Border *</label>
                <input type="text" id="border_name" name="border_name" value="<?= htmlspecialchars($borderpoint['name']) ?>" required>

                <label for="border_country">Country (Admin 0) *</label>
                <input type="text" id="border_country" name="border_country" value="<?= htmlspecialchars($borderpoint['country']) ?>" required>

                <label for="border_county">County/District (Admin 1) *</label>
                <input type="text" id="border_county" name="border_county" value="<?= htmlspecialchars($borderpoint['county']) ?>" required>

                <div class="location-container">
                    <label for="longitude">Longitude *
                        <input type="text" id="longitude" name="longitude" value="<?= htmlspecialchars($borderpoint['longitude']) ?>" required>
                    </label>
                    <label for="latitude">Latitude *
                        <input type="text" id="latitude" name="latitude" value="<?= htmlspecialchars($borderpoint['latitude']) ?>" required>
                    </label>
                </div>

                <label for="radius">Border radius *</label>
                <input type="text" id="radius" name="radius" value="<?= htmlspecialchars($borderpoint['radius']) ?>" required>

                <label for="imageUpload">Upload Image</label>
                <input type="file" id="imageUpload" name="imageUpload" accept="image/*">
                <?php if (!empty($borderpoint['image_url'])): ?>
                    <div id="imagePreview">
                        <div class="preview-image">
                            <img src="<?= htmlspecialchars($borderpoint['image_url']) ?>" alt="Current Image">
                            </div>
                    </div>
                <?php endif; ?>
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>


                <div class="button-container">
                    <button type="button" class="btn-cancel" onclick="window.location.href='sidebar.php'">Cancel</button>
                    <button type="submit" class="btn-update">Update Border Point</button>
                </div>
            </form>
        <?php else: ?>
            <p>Border Point not found or invalid ID.</p>
            <div class="button-container">
                <button type="button" class="btn-cancel" onclick="window.location.href='tradepoints.php'">Back to Tradepoints</button>
            </div>
        <?php endif; ?>
    <script>
        // Image Upload/Preview Script (copied from add_market2.php)
        document.getElementById("imageUpload").addEventListener("change", function(event) {
            const files = event.target.files;
            let preview = document.getElementById("imagePreview"); // Get the preview div
            const progressBar = document.getElementById("progressBar");
            const progressContainer = document.querySelector(".progress-bar-container");

            // Clear previous previews if any
            if (!preview) { // Create preview div if it doesn't exist
                preview = document.createElement("div");
                preview.id = "imagePreview";
                event.target.parentNode.insertBefore(preview, event.target.nextSibling);
            }
            preview.innerHTML = ""; // Clear existing images in preview

            if (files.length === 0) {
                progressContainer.style.display = "none";
                return;
            }

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
                        <button class="remove-img" type="button" onclick="this.parentElement.remove()">✖</button>
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
    </script>
</body>
</html>