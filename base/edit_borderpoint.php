<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Get border point ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch border point details
$border_data = null;
if ($id > 0) {
    $stmt = $con->prepare("SELECT * FROM border_points WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $border_data = $result->fetch_assoc();
    $stmt->close();
}

if (!$border_data) {
    echo "<script>alert('Border point not found'); window.location.href='../base/sidebar.php';</script>";
    exit;
}

// Fetch countries from database
$countries = [];
$country_query = "SELECT country_name FROM countries ORDER BY country_name ASC";
$country_result = $con->query($country_query);
if ($country_result) {
    while ($row = $country_result->fetch_assoc()) {
        $countries[] = $row['country_name'];
    }
}

// Decode images for display
$border_images = [];
if (!empty($border_data['images'])) {
    $border_images = json_decode($border_data['images'], true);
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $border_name = $_POST['border_name'];
    $country = $_POST['country'];
    $county = $_POST['county'];
    $longitude = floatval($_POST['longitude']);
    $latitude = floatval($_POST['latitude']);
    $radius = floatval($_POST['radius']);
    $tradepoint = $_POST['tradepoint'];

    // Handle image uploads
    $new_images = $border_images; // Start with existing images
    
    if (isset($_FILES['borderImages'])) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['borderImages']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['borderImages']['error'][$key] === UPLOAD_ERR_OK) {
                $image_name = basename($_FILES['borderImages']['name'][$key]);
                $image_path = $upload_dir . time() . '_' . uniqid() . '_' . $image_name;

                if (move_uploaded_file($tmp_name, $image_path)) {
                    $new_images[] = $image_path;
                }
            }
        }
    }
    
    $images_json = json_encode($new_images);

    $sql = "UPDATE border_points 
            SET name = ?, country = ?, county = ?, longitude = ?, latitude = ?, 
                radius = ?, tradepoint = ?, images = ?
            WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param(
        'sssddsssi',
        $border_name,
        $country,
        $county,
        $longitude,
        $latitude,
        $radius,
        $tradepoint,
        $images_json,
        $id
    );
    $stmt->execute();

    if ($stmt->errno) {
        $error_message = "MySQL Error: " . $stmt->error;
    } else {
        echo "<script>alert('Border point updated successfully'); window.location.href='../base/sidebar.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Border Point</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        h2 {
            margin-bottom: 10px;
            color: #333;
        }
        p {
            margin-bottom: 30px;
            color: #666;
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
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }
        
        /* Form styling */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .form-group-full {
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        label {
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        input, select, textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }
        
        /* Current border point info */
        .border-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .border-info h5 {
            margin-bottom: 15px;
            color: rgba(180, 80, 50, 1);
        }
        .border-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Image gallery */
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .image-container {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .delete-img {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .image-container:hover .delete-img {
            opacity: 1;
        }
        
        /* Section headers */
        .section-header {
            background-color: rgba(180, 80, 50, 0.1);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .section-header h6 {
            margin: 0;
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        .section-header p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        /* File upload styling */
        .file-upload {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .file-upload:hover {
            border-color: rgba(180, 80, 50, 0.5);
            background-color: rgba(180, 80, 50, 0.05);
        }
        .file-upload i {
            font-size: 24px;
            color: rgba(180, 80, 50, 1);
            margin-bottom: 10px;
        }
        .file-upload p {
            margin: 0;
            color: #666;
        }
        
        /* Error message */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        /* Update button */
        .update-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
        }
        .update-btn:hover {
            background-color: rgba(160, 60, 30, 1);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .image-gallery {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        
        <h2>Edit Border Point</h2>
        <p>Update the border point details below</p>
        
        <!-- Display current border point info -->
        <div class="border-info">
            <h5>Current Border Point Information</h5>
            <p><strong>Border Name:</strong> <?= htmlspecialchars($border_data['name']) ?></p>
            <p><strong>Country:</strong> <?= htmlspecialchars($border_data['country']) ?></p>
            <p><strong>County:</strong> <?= htmlspecialchars($border_data['county']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($border_data['latitude']) ?>, <?= htmlspecialchars($border_data['longitude']) ?></p>
            <p><strong>Radius:</strong> <?= htmlspecialchars($border_data['radius']) ?> meters</p>
            <p><strong>Tradepoint Type:</strong> <?= htmlspecialchars($border_data['tradepoint']) ?></p>
            <p><strong>Images:</strong></p>
            <div class="image-gallery">
                <?php if (!empty($border_images)): ?>
                    <?php foreach ($border_images as $image): ?>
                        <div class="image-container">
                            <img src="<?= htmlspecialchars($image) ?>" alt="Border point image">
                            <button type="button" class="delete-img" onclick="removeImage('<?= htmlspecialchars($image) ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No images available</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_borderpoint.php?id=<?= $id ?>" enctype="multipart/form-data">
            <div class="section-header">
                <h6><i class="fas fa-map-marker-alt"></i> Basic Information</h6>
                <p>Update the basic details of the border point</p>
            </div>

            <div class="form-group-full">
                <label for="border_name" class="required">Border Point Name</label>
                <input type="text" id="border_name" name="border_name" 
                       value="<?= htmlspecialchars($border_data['name'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="country" class="required">Country</label>
                    <select id="country" name="country" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" 
                                    <?= ($border_data['country'] == $country) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="county" class="required">County</label>
                    <input type="text" id="county" name="county" 
                           value="<?= htmlspecialchars($border_data['county'] ?? '') ?>" required>
                </div>
            </div>

            <div class="section-header">
                <h6><i class="fas fa-globe-africa"></i> Location Details</h6>
                <p>Update geographical information of the border point</p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="longitude" class="required">Longitude</label>
                    <input type="number" step="any" id="longitude" name="longitude" 
                           value="<?= htmlspecialchars($border_data['longitude'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="latitude" class="required">Latitude</label>
                    <input type="number" step="any" id="latitude" name="latitude" 
                           value="<?= htmlspecialchars($border_data['latitude'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="radius" class="required">Radius (meters)</label>
                    <input type="number" id="radius" name="radius" 
                           value="<?= htmlspecialchars($border_data['radius'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="tradepoint" class="required">Tradepoint Type</label>
                    <select id="tradepoint" name="tradepoint" required>
                        <option value="">Select type</option>
                        <option value="Border Points" <?= ($border_data['tradepoint'] == 'Border Points') ? 'selected' : '' ?>>Border Point</option>
                        <option value="Major Border" <?= ($border_data['tradepoint'] == 'Major Border') ? 'selected' : '' ?>>Major Border</option>
                        <option value="Minor Border" <?= ($border_data['tradepoint'] == 'Minor Border') ? 'selected' : '' ?>>Minor Border</option>
                    </select>
                </div>
            </div>

            <div class="section-header">
                <h6><i class="fas fa-images"></i> Border Point Images</h6>
                <p>Add or remove images of the border point</p>
            </div>

            <div class="form-group-full">
                <label>Current Images</label>
                <div class="image-gallery" id="currentImages">
                    <?php if (!empty($border_images)): ?>
                        <?php foreach ($border_images as $image): ?>
                            <div class="image-container">
                                <img src="<?= htmlspecialchars($image) ?>" alt="Border point image">
                                <button type="button" class="delete-img" onclick="removeImage('<?= htmlspecialchars($image) ?>')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No images currently uploaded</p>
                    <?php endif; ?>
                </div>
                
                <label for="borderImages">Upload New Images</label>
                <div class="file-upload" onclick="document.getElementById('borderImages').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload images</p>
                    <p><small>Supports JPG, PNG (Max 5MB each)</small></p>
                </div>
                <input type="file" id="borderImages" name="borderImages[]" multiple accept="image/*" style="display: none;">
                <div id="newImagePreview" class="image-gallery"></div>
            </div>

            <button type="submit" class="update-btn">
                <i class="fa fa-save"></i> Update Border Point
            </button>
        </form>
    </div>

    <script>
        // Array to track removed images
        let removedImages = [];
        
        // Function to remove an image
        function removeImage(imagePath) {
            if (confirm('Are you sure you want to remove this image?')) {
                // Add to removed images array
                removedImages.push(imagePath);
                
                // Remove from DOM
                const containers = document.querySelectorAll('.image-container');
                containers.forEach(container => {
                    if (container.querySelector('img').src.includes(imagePath)) {
                        container.remove();
                    }
                });
                
                // If no images left, show message
                if (document.querySelectorAll('.image-container').length === 0) {
                    document.getElementById('currentImages').innerHTML = '<p>No images currently uploaded</p>';
                }
            }
        }
        
        // Handle new image preview
        document.getElementById('borderImages').addEventListener('change', function(event) {
            const files = event.target.files;
            const preview = document.getElementById('newImagePreview');
            preview.innerHTML = '';
            
            if (files.length > 0) {
                Array.from(files).forEach(file => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const container = document.createElement('div');
                        container.className = 'image-container';
                        container.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="delete-img" onclick="this.parentElement.remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        preview.appendChild(container);
                    };
                    
                    reader.readAsDataURL(file);
                });
            }
        });
        
        // Form submission handler
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';
            
            // Validate required fields
            const requiredFields = ['border_name', 'country', 'county', 'longitude', 'latitude', 'radius', 'tradepoint'];
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value || field.value.trim() === '') {
                    isValid = false;
                    errorMessage = 'Please fill all required fields.';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
                return false;
            }
            
            const confirmUpdate = confirm('Are you sure you want to update this border point?');
            if (!confirmUpdate) {
                e.preventDefault();
                return false;
            }
            
            // Add hidden input for removed images if any
            if (removedImages.length > 0) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'removed_images';
                hiddenInput.value = JSON.stringify(removedImages);
                this.appendChild(hiddenInput);
            }
        });
        
        // Add smooth transitions for better UX
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
                this.style.transition = 'transform 0.2s ease';
            });
            
            element.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>