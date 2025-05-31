<?php
session_start();
include '../admin/includes/config.php';

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Check if we have session data from step 1
if (!isset($_SESSION['tradepoint'])) {
    header('Location: addtradepoint.php');
    exit;
}

$tradepoint_type = $_SESSION['tradepoint'];

// Validate required session data based on tradepoint type
if ($tradepoint_type == "Markets" && !isset($_SESSION['market_name'])) {
    header('Location: addtradepoint.php');
    exit;
} elseif ($tradepoint_type == "Border Points" && !isset($_SESSION['border_name'])) {
    header('Location: addtradepoint.php');
    exit;
} elseif ($tradepoint_type == "Millers" && !isset($_SESSION['miller_name'])) {
    header('Location: addtradepoint.php');
    exit;
}

// Define currency mapping for markets (if needed)
$currency_map = [
    'Kenya' => 'KES',
    'Uganda' => 'UGX',
    'Tanzania' => 'TZS',
    'Rwanda' => 'RWF',
    'Burundi' => 'BIF',
    'South Sudan' => 'SSP',
    'Ethiopia' => 'ETB',
    'Somalia' => 'SOS',
    'Democratic Republic of Congo' => 'CDF',
];

// Get autofill currency for markets
$autofill_currency = '';
if ($tradepoint_type == "Markets" && isset($_SESSION['country'])) {
    $selected_country = $_SESSION['country'];
    $autofill_currency = isset($currency_map[$selected_country]) ? $currency_map[$selected_country] : 'N/A';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if ($tradepoint_type == "Markets") {
        // Handle Markets submission (same as before)
        // ... [existing markets code] ...
    } elseif ($tradepoint_type == "Border Points") {
        // Handle Border Points submission (same as before)
        // ... [existing border points code] ...
    } elseif ($tradepoint_type == "Millers") {
        // Handle Millers submission - Updated functionality
        
        // If adding miller data with coordinates and radius
        if (isset($_POST['add_miller'])) {
            $miller = $_POST['miller'];
            $longitude = $_POST['longitude'];
            $latitude = $_POST['latitude'];
            $radius = $_POST['radius'];
            $miller_name = $_SESSION['miller_name'];

            $stmt = $con->prepare("INSERT INTO millers (miller_name, miller, longitude, latitude, radius) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssdii", $miller_name, $miller, $longitude, $latitude, $radius);
                if ($stmt->execute()) {
                    // Get the inserted miller data to return as JSON
                    $inserted_id = $stmt->insert_id;
                    $query = "SELECT * FROM millers WHERE id = ?";
                    $stmt2 = $con->prepare($query);
                    $stmt2->bind_param("i", $inserted_id);
                    $stmt2->execute();
                    $result = $stmt2->get_result();
                    $miller_data = $result->fetch_assoc();
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Miller added successfully!',
                        'html' => '<div class="miller-item" data-id="'.$miller_data['id'].'">
                                    <span>'.$miller_data['miller'].' ('.$miller_data['longitude'].', '.$miller_data['latitude'].') - Radius: '.$miller_data['radius'].'m</span>
                                    <button type="button" class="remove-miller">×</button>
                                  </div>'
                    ]);
                    exit;
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error adding miller: '.$stmt->error]);
                    exit;
                }
                $stmt->close();
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
                exit;
            }
        }
        
        // Handle miller deletion
        if (isset($_POST['delete_miller'])) {
            $miller_id = $_POST['miller_id'];
            $stmt = $con->prepare("DELETE FROM millers WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $miller_id);
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Miller deleted successfully!']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error deleting miller: '.$stmt->error]);
                }
                $stmt->close();
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement.']);
            }
            exit;
        }
        
        // Go to step 3
        if (isset($_POST['next_step'])) {
            // Verify at least one miller exists
            $miller_name = $_SESSION['miller_name'];
            $query = "SELECT COUNT(*) as count FROM millers WHERE miller_name = ?";
            $stmt = $con->prepare($query);
            $stmt->bind_param("s", $miller_name);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                header("Location: addtradepoint3.php");
                exit;
            } else {
                echo "<script>alert('Please add at least one miller before proceeding.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tradepoint - Step 2</title>
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
            border-radius: 8px;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex;
            min-height: 600px;
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
            z-index: 10;
        }
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }
        
        /* Left sidebar for steps */
        .steps-sidebar {
            width: 250px;
            background-color: #f8f9fa;
            padding: 40px 30px;
            border-radius: 8px 0 0 8px;
            border-right: 1px solid #e9ecef;
            position: relative;
        }
        
        .steps-sidebar h3 {
            color: #333;
            margin-bottom: 30px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .steps-container {
            position: relative;
        }
        
        /* Vertical connecting line */
        .steps-container::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            bottom: 0;
            width: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 60px;
            position: relative;
            z-index: 2;
        }
        
        .step:last-child {
            margin-bottom: 0;
        }
        
        .step-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 16px;
            font-weight: bold;
            background-color: #e9ecef;
            color: #6c757d;
            position: relative;
            flex-shrink: 0;
        }
        
        .step-circle.active {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        
        .step-circle.completed {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        
        .step-circle.completed::after {
            content: '✓';
            font-size: 20px;
        }
        
        .step-circle.active::after {
            content: '✓';
            font-size: 20px;
        }
        
        .step-circle:not(.active):not(.completed)::after {
            content: attr(data-step);
        }
        
        .step-text {
            font-weight: 500;
            color: #6c757d;
        }
        
        .step.active .step-text {
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        
        .step.completed .step-text {
            color: rgba(180, 80, 50, 1);
            font-weight: bold;
        }
        
        /* Main content area */
        .main-content {
            flex: 1;
            padding: 40px;
        }
        
        h2 {
            margin-bottom: 10px;
            color: #333;
        }
        p {
            margin-bottom: 30px;
            color: #666;
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
        
        /* Currency display */
        .currency-display {
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            background-color: #f8f9fa;
            font-size: 14px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 500;
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
            background-color: #28a745;
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
            width: 100px;
            height: 100px;
            border-radius: 5px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
        .remove-img {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border: none;
            padding: 3px 6px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
        }
        .remove-img:hover {
            background: #c82333;
        }
        
        /* Button styling */
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 20px;
        }
        .prev-btn, .next-btn, .add-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .prev-btn {
            background-color: #6c757d;
            color: white;
        }
        .prev-btn:hover {
            background-color: #5a6268;
        }
        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .next-btn:hover {
            background-color: rgba(160, 60, 30, 1);
        }
        .add-btn {
            background-color: #28a745;
            color: white;
        }
        .add-btn:hover {
            background-color: #218838;
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
        
        /* Tradepoint sections */
        .tradepoint-section {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        .tradepoint-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* File input styling */
        input[type="file"] {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        input[type="file"]:hover {
            border-color: rgba(180, 80, 50, 0.5);
            background-color: rgba(180, 80, 50, 0.05);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                margin: 10px;
            }
            .steps-sidebar {
                width: 100%;
                border-radius: 8px 8px 0 0;
                border-right: none;
                border-bottom: 1px solid #e9ecef;
                padding: 20px;
            }
            .steps-container {
                display: flex;
                justify-content: center;
                gap: 30px;
            }
            .steps-container::before {
                display: none;
            }
            .step {
                margin-bottom: 0;
                flex-direction: column;
                text-align: center;
            }
            .step-circle {
                margin-right: 0;
                margin-bottom: 10px;
            }
            .main-content {
                padding: 20px;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .button-container {
                flex-direction: column;
            }
        }
        
        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        
        .toast {
            background-color: rgba(0, 0, 0, 0.9);
            color: white;
            border-radius: 5px;
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(150%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background-color: #28a745;
        }
        
        .toast.error {
            background-color: #dc3545;
        }
        
        .toast i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        /* Loading spinner */
        .spinner-border {
            display: none;
            margin-left: 10px;
        }
        
        .btn-loading .spinner-border {
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        
        <!-- Left Sidebar with Steps -->
        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step completed">
                    <div class="step-circle completed" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Basic Info</small></div>
                </div>
                <div class="step active">
                    <div class="step-circle active" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Details</small></div>
                </div>
                <?php if ($tradepoint_type == "Markets" || $tradepoint_type == "Millers"): ?>
                <div class="step">
                    <div class="step-circle" data-step="3"></div>
                    <div class="step-text">Step 3<br><small>Final</small></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <h2>Add <?= htmlspecialchars($tradepoint_type) ?> - Step 2</h2>
            <p>Please provide additional details for your <?= strtolower($tradepoint_type) ?>.</p>
            
            <!-- Toast notifications container -->
            <div class="toast-container" id="toastContainer"></div>
            
            <!-- Markets and Border Points forms remain the same -->
            <?php if ($tradepoint_type == "Markets" || $tradepoint_type == "Border Points"): ?>
                <!-- [Previous Markets and Border Points form sections remain exactly the same] -->
            <?php endif; ?>
            
            <!-- Updated Millers Section -->
            <?php if ($tradepoint_type == "Millers"): ?>
            <div class="tradepoint-section active">
                <div class="section-header">
                    <h6><i class="fas fa-industry"></i> Miller Details</h6>
                    <p>Provide miller location coordinates and additional details</p>
                </div>
                
                <!-- Added millers list -->
                <div class="form-group-full" id="millerListContainer" style="margin-bottom: 20px;">
                    <label>Added Millers</label>
                    <div id="millerList" style="border: 1px solid #ddd; border-radius: 5px; padding: 10px; min-height: 50px;">
                        <?php
                        // Fetch millers for this session
                        if (isset($_SESSION['miller_name'])) {
                            $miller_name = $_SESSION['miller_name'];
                            $query = "SELECT * FROM millers WHERE miller_name = ?";
                            $stmt = $con->prepare($query);
                            $stmt->bind_param("s", $miller_name);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<div class="miller-item" data-id="'.$row['id'].'">
                                            <span>'.$row['miller'].' ('.$row['longitude'].', '.$row['latitude'].') - Radius: '.$row['radius'].'m</span>
                                            <button type="button" class="remove-miller">×</button>
                                          </div>';
                                }
                            } else {
                                echo '<p style="color: #999; text-align: center;">No millers added yet</p>';
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Separate form for adding millers -->
                <form id="add-miller-form" method="POST">
                    <div class="form-group-full">
                        <label for="miller" class="required">Miller Description</label>
                        <input type="text" id="miller" name="miller" placeholder="Add miller name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="longitude" class="required">Longitude</label>
                            <input type="number" step="any" id="longitude" name="longitude" placeholder="e.g., 36.8219" required>
                        </div>
                        <div class="form-group">
                            <label for="latitude" class="required">Latitude</label>
                            <input type="number" step="any" id="latitude" name="latitude" placeholder="e.g., -1.2921" required>
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="radius" class="required">Service Radius (m)</label>
                        <input type="number" id="radius" name="radius" placeholder="Enter service radius in meters" required>
                    </div>
                    
                    
                        <div style="text-align: center;">
                            <button type="submit" name="add_miller" class="add-btn" id="saveMillerBtn">
                                <i class="fas fa-save"></i> Save Miller Data
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                   
                </form>
                
                <!-- Separate form for proceeding to next step -->
                <form id="next-step-form" method="POST" style="margin-top: 20px;">
                    <div class="button-container">
                        <button type="button" class="prev-btn" onclick="window.location.href='addtradepoint.php'">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="submit" name="next_step" class="next-btn">
                            Next Step <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show toast notification
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            toastContainer.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Hide after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Handle file upload based on tradepoint type
        const tradepointType = '<?= $tradepoint_type ?>';
        let fileInputId = '';
        
        if (tradepointType === 'Markets') {
            fileInputId = 'marketImages';
        } else if (tradepointType === 'Border Points') {
            fileInputId = 'borderImages';
        }

        // Only setup file upload handlers for Markets and Border Points
        if (fileInputId && document.getElementById(fileInputId)) {
            document.getElementById(fileInputId).addEventListener("change", function(event) {
                const files = event.target.files;
                const preview = document.getElementById("imagePreview");
                const progressBar = document.getElementById("progressBar");
                const progressContainer = document.querySelector(".progress-bar-container");

                preview.innerHTML = "";
                if (files.length === 0) return;

                progressContainer.style.display = "block";
                progressBar.style.width = "0%";

                let loaded = 0;
                const total = files.length;

                Array.from(files).forEach((file, index) => {
                    const reader = new FileReader();

                    reader.onload = function(event) {
                        const imgDiv = document.createElement("div");
                        imgDiv.classList.add("preview-image");
                        imgDiv.innerHTML = `
                            <img src="${event.target.result}" alt="Uploaded Image">
                            <button type="button" class="remove-img" onclick="removeImage(this, ${index})">×</button>
                        `;
                        preview.appendChild(imgDiv);

                        loaded++;
                        progressBar.style.width = `${(loaded / total) * 100}%`;

                        if (loaded === total) {
                            setTimeout(() => { 
                                progressContainer.style.display = "none"; 
                            }, 1000);
                        }
                    };

                    reader.readAsDataURL(file);
                });
            });
        }

        function removeImage(button, index) {
            const input = document.getElementById(fileInputId);
            const preview = document.getElementById("imagePreview");
            
            // Remove the preview element
            button.parentElement.remove();
            
            // For multiple file inputs, we need to recreate the file list
            if (tradepointType !== 'Markets') {
                const files = Array.from(input.files);
                files.splice(index, 1);
                
                const dataTransfer = new DataTransfer();
                files.forEach(file => dataTransfer.items.add(file));
                input.files = dataTransfer.files;
                
                // Refresh preview with new indices
                refreshPreview();
            } else {
                // For single file input, just clear it
                input.value = '';
                preview.innerHTML = '';
            }
        }

        // Handle miller deletion
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-miller')) {
                const millerItem = e.target.closest('.miller-item');
                const millerId = millerItem.getAttribute('data-id');
                
                if (confirm('Are you sure you want to delete this miller?')) {
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'delete_miller=1&miller_id=' + millerId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            millerItem.remove();
                            
                            // If no millers left, show message
                            if (document.querySelectorAll('.miller-item').length === 0) {
                                document.getElementById('millerList').innerHTML = '<p style="color: #999; text-align: center;">No millers added yet</p>';
                            }
                        } else {
                            showToast(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred while deleting the miller', 'error');
                    });
                }
            }
        });

        // Handle miller addition via AJAX
        if (document.getElementById('add-miller-form')) {
            document.getElementById('add-miller-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const saveBtn = document.getElementById('saveMillerBtn');
                saveBtn.classList.add('btn-loading');
                saveBtn.disabled = true;
                
                const formData = new FormData(this);
                formData.append('add_miller', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    saveBtn.classList.remove('btn-loading');
                    saveBtn.disabled = false;
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        
                        // Add the new miller to the list
                        const millerList = document.getElementById('millerList');
                        
                        // If "No millers" message exists, remove it
                        if (millerList.querySelector('p')) {
                            millerList.innerHTML = '';
                        }
                        
                        millerList.insertAdjacentHTML('beforeend', data.html);
                        
                        // Clear the form
                        this.reset();
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    saveBtn.classList.remove('btn-loading');
                    saveBtn.disabled = false;
                    showToast('An error occurred while adding the miller', 'error');
                    console.error('Error:', error);
                });
            });
        }

        // Form validation for next step
        if (document.getElementById('next-step-form')) {
            document.getElementById('next-step-form').addEventListener('submit', function(e) {
                const millerItems = document.querySelectorAll('.miller-item');
                if (millerItems.length === 0) {
                    e.preventDefault();
                    showToast('Please add at least one miller before proceeding', 'error');
                }
            });
        }

        // Add smooth transitions for better UX
        document.querySelectorAll('input, select').forEach(element => {
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