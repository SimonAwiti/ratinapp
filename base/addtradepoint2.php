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
        // Handle Markets submission
        $_SESSION['longitude'] = $_POST['longitude'];
        $_SESSION['latitude'] = $_POST['latitude'];
        $_SESSION['radius'] = $_POST['radius'];
        $_SESSION['currency'] = $autofill_currency;

        // Handle multiple image upload for markets
        $image_paths = array();
        
        if (isset($_FILES['marketImages'])) {
            foreach ($_FILES['marketImages']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['marketImages']['error'][$key] === UPLOAD_ERR_OK) {
                    $image_name = basename($_FILES['marketImages']['name'][$key]);
                    $image_path = $upload_dir . time() . '_' . uniqid() . '_' . $image_name;

                    if (move_uploaded_file($tmp_name, $image_path)) {
                        $image_paths[] = $image_path;
                    }
                }
            }
        }

        // Convert array of image paths to JSON string
        $_SESSION['image_urls'] = json_encode($image_paths);
        header("Location: addtradepoint3.php");
        exit;

    } elseif ($tradepoint_type == "Border Points") {
        // Handle Border Points submission (final step)
        $image_paths = array();
        
        if (isset($_FILES['borderImages'])) {
            foreach ($_FILES['borderImages']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['borderImages']['error'][$key] === UPLOAD_ERR_OK) {
                    $image_name = basename($_FILES['borderImages']['name'][$key]);
                    $image_path = $upload_dir . time() . '_' . uniqid() . '_' . $image_name;

                    if (move_uploaded_file($tmp_name, $image_path)) {
                        $image_paths[] = $image_path;
                    }
                }
            }
        }

        // Convert array of image paths to JSON string
        $images_json = json_encode($image_paths);

        // Insert border point info
        $stmt = $con->prepare("INSERT INTO border_points 
                              (name, country, county, longitude, latitude, radius, tradepoint, images) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddsss", 
            $_SESSION['border_name'], 
            $_SESSION['border_country'], 
            $_SESSION['border_county'], 
            $_SESSION['longitude'], 
            $_SESSION['latitude'], 
            $_SESSION['radius'],
            $_SESSION['tradepoint'],
            $images_json
        );
        
        if ($stmt->execute()) {
            session_unset();
            echo "<script>alert('Border Point added successfully!'); window.location.href='addtradepoint.php';</script>";
            exit;
        } else {
            // Delete uploaded images if database insert failed
            foreach ($image_paths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            echo "<script>alert('Failed to save border point!'); window.history.back();</script>";
        }

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
                    echo "<script>alert('Miller added successfully!');</script>";
                } else {
                    echo "<script>alert('Error adding miller: {$stmt->error}');</script>";
                }
                $stmt->close();
            } else {
                echo "<script>alert('Failed to prepare statement.');</script>";
            }
            // Do not exit here, let the page reload
        }
        
        // Handle miller deletion
        if (isset($_POST['delete_miller'])) {
            $miller_id = $_POST['miller_id'];
            $miller_name = $_SESSION['miller_name'];
            
            $stmt = $con->prepare("DELETE FROM millers WHERE id = ? AND miller_name = ?");
            if ($stmt) {
                $stmt->bind_param("is", $miller_id, $miller_name);
                if ($stmt->execute()) {
                    echo "<script>alert('Miller deleted successfully!');</script>";
                } else {
                    echo "<script>alert('Error deleting miller: {$stmt->error}');</script>";
                }
                $stmt->close();
            } else {
                echo "<script>alert('Failed to prepare delete statement.');</script>";
            }
        }
        
        // Go to step 3
        if (isset($_POST['next_step'])) {
            header("Location: addtradepoint3.php");
            exit;
        }
        
        // Handle final miller submission with images (if this is the final step)
        if (isset($_POST['final_submit'])) {
            $image_paths = array();
            
            if (isset($_FILES['millerImages'])) {
                foreach ($_FILES['millerImages']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['millerImages']['error'][$key] === UPLOAD_ERR_OK) {
                        $image_name = basename($_FILES['millerImages']['name'][$key]);
                        $image_path = $upload_dir . time() . '_' . uniqid() . '_' . $image_name;

                        if (move_uploaded_file($tmp_name, $image_path)) {
                            $image_paths[] = $image_path;
                        }
                    }
                }
            }

            // Convert array of image paths to JSON string
            $images_json = json_encode($image_paths);

            // Insert miller info
            $stmt = $con->prepare("INSERT INTO millers 
                                  (name, country, county_district, currency, tradepoint, images) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", 
                $_SESSION['miller_name'], 
                $_SESSION['country'], 
                $_SESSION['county_district'], 
                $_SESSION['currency'],
                $_SESSION['tradepoint'],
                $images_json
            );
            
            if ($stmt->execute()) {
                session_unset();
                echo "<script>alert('Miller added successfully!'); window.location.href='addtradepoint.php';</script>";
                exit;
            } else {
                // Delete uploaded images if database insert failed
                foreach ($image_paths as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
                echo "<script>alert('Failed to save miller!'); window.history.back();</script>";
            }
        }
    }
}

// Fetch existing millers for the current miller_name (for display)
$existing_millers = [];
if ($tradepoint_type == "Millers" && isset($_SESSION['miller_name'])) {
    $miller_name = $_SESSION['miller_name'];
    $stmt = $con->prepare("SELECT id, miller, longitude, latitude, radius FROM millers WHERE miller_name = ?");
    if ($stmt) {
        $stmt->bind_param("s", $miller_name);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existing_millers[] = $row;
        }
        $stmt->close();
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
        /* Miller List Section Styles */
        .millers-list {
            margin-top: 30px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .millers-list h6 {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .millers-list h6 i {
            color: #3498db;
            font-size: 1rem;
        }

        /* Individual Miller Item */
        .miller-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .miller-item:hover {
            border-color: #3498db;
            box-shadow: 0 2px 12px rgba(52, 152, 219, 0.1);
            transform: translateY(-1px);
        }

        .miller-item:last-child {
            margin-bottom: 0;
        }

        /* Miller Details */
        .miller-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .miller-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
            line-height: 1.4;
        }

        .miller-coords {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .miller-coords i {
            color: #e74c3c;
            font-size: 0.8rem;
        }

        /* Delete Button */
        .delete-miller-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            flex-shrink: 0;
            margin-left: 15px;
        }

        .delete-miller-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        .delete-miller-btn:active {
            transform: scale(0.95);
        }

        /* No Millers State */
        .no-millers {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 30px 20px;
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        /* Button Container for Miller Section */
        .button-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            gap: 15px;
            flex-wrap: wrap;
        }

        /* Button Styles */
        .add-btn, .next-btn, .prev-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            min-width: 120px;
            justify-content: center;
        }

        .add-btn {
            background: #27ae60;
            color: white;
        }

        .add-btn:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .next-btn {
            background: #3498db;
            color: white;
        }

        .next-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .prev-btn {
            background: #95a5a6;
            color: white;
        }

        .prev-btn:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .miller-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px;
            }
            
            .delete-miller-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                margin-left: 0;
            }
            
            .miller-details {
                width: 100%;
                padding-right: 40px;
            }
            
            .button-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .button-container button {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .millers-list {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .miller-coords {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }
            
            .miller-name {
                font-size: 0.95rem;
            }
            
            .miller-coords {
                font-size: 0.8rem;
            }
            
            .delete-miller-btn {
                width: 24px;
                height: 24px;
                font-size: 14px;
            }
        }

        /* Animation for new miller items */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .miller-item {
            animation: slideIn 0.3s ease-out;
        }

        /* Empty state enhancement */
        .no-millers::before {
            content: "📋";
            display: block;
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Success/Error message styling (if needed) */
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>
        
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
            
            <form id="tradepoint-form" method="POST" action="" enctype="multipart/form-data">
                
                <!-- Markets Section -->
                <?php if ($tradepoint_type == "Markets"): ?>
                <div class="tradepoint-section active">
                    <div class="section-header">
                        <h6><i class="fas fa-map-marker-alt"></i> Location & Details</h6>
                        <p>Provide location coordinates and market details</p>
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
                        <label for="radius" class="required">Market Radius (m)</label>
                        <input type="number" id="radius" name="radius" placeholder="Enter radius in meters" required>
                    </div>

                    <div class="form-group-full">
                        <label class="required">Currency</label>
                        <div class="currency-display">
                            <i class="fas fa-coins"></i> <?= htmlspecialchars($autofill_currency) ?>
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="marketImages" class="required">Upload Market Images</label>
                        <input type="file" id="marketImages" name="marketImages[]" multiple accept="image/*" required>
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div id="imagePreview"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Border Points Section -->
                <?php if ($tradepoint_type == "Border Points"): ?>
                <div class="tradepoint-section active">
                    <div class="section-header">
                        <h6><i class="fas fa-images"></i> Border Point Images</h6>
                        <p>Upload one or more images of the border point</p>
                    </div>
                    
                    <div class="form-group-full">
                        <label for="borderImages" class="required">Upload Images</label>
                        <input type="file" id="borderImages" name="borderImages[]" multiple accept="image/*" required>
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div id="imagePreview"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Millers Section - Updated -->
                <?php if ($tradepoint_type == "Millers"): ?>
                <div class="tradepoint-section active">
                    <div class="section-header">
                        <h6><i class="fas fa-industry"></i> Miller Details</h6>
                        <p>Provide miller location coordinates and additional details</p>
                    </div>
                    
                    <div class="form-group-full">
                        <label for="miller" class="required">Miller Description</label>
                        <input id="miller" name="miller" rows="4" placeholder="Add miller name" ></input>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="longitude" class="required">Longitude</label>
                            <input type="number" step="any" id="longitude" name="longitude" placeholder="e.g., 36.8219" >
                        </div>
                        <div class="form-group">
                            <label for="latitude" class="required">Latitude</label>
                            <input type="number" step="any" id="latitude" name="latitude" placeholder="e.g., -1.2921" >
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label for="radius" class="required">Service Radius (m)</label>
                        <input type="number" id="radius" name="radius" placeholder="Enter service radius in meters" >
                    </div>
                    
                    <!-- Added millers list section -->
                    <?php if (!empty($existing_millers)): ?>
                    <div class="millers-list">
                        <h6><i class="fas fa-list"></i> Added Millers</h6>
                        <?php foreach ($existing_millers as $miller): ?>
                        <div class="miller-item">
                            <div class="miller-details">
                                <div class="miller-name"><?= htmlspecialchars($miller['miller']) ?></div>
                                <div class="miller-coords">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    Lat: <?= htmlspecialchars($miller['latitude']) ?>, 
                                    Lng: <?= htmlspecialchars($miller['longitude']) ?>, 
                                    Radius: <?= htmlspecialchars($miller['radius']) ?>m
                                </div>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="miller_id" value="<?= $miller['id'] ?>">
                                <button type="submit" name="delete_miller" class="delete-miller-btn" 
                                        onclick="return confirm('Are you sure you want to delete this miller?')">
                                    ×
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="millers-list">
                        <div class="no-millers">No millers added yet. Add your first miller using the form above.</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="button-container">
                        <button type="button" class="prev-btn" onclick="window.location.href='addtradepoint.php'">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="submit" name="add_miller" class="add-btn">
                            <i class="fas fa-save"></i> Save Miller Data
                        </button>
                        <button type="submit" name="next_step" class="next-btn">
                            Next Step <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($tradepoint_type != "Millers"): ?>
                <div class="button-container">
                    <button type="button" class="prev-btn" onclick="window.location.href='addtradepoint.php'">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="submit" class="next-btn">
                        <?php if ($tradepoint_type == "Markets"): ?>
                            Next Step <i class="fas fa-arrow-right"></i>
                        <?php else: ?>
                            Complete <i class="fas fa-check"></i>
                        <?php endif; ?>
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // Handle file upload based on tradepoint type
        const tradepointType = '<?= $tradepoint_type ?>';
        let fileInputId = '';
        
        if (tradepointType === 'Markets') {
            fileInputId = 'marketImages';
        } else if (tradepointType === 'Border Points') {
            fileInputId = 'borderImages';
        } else if (tradepointType === 'Millers') {
            fileInputId = 'millerImages';
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

        function refreshPreview() {
            const input = document.getElementById(fileInputId);
            const preview = document.getElementById("imagePreview");
            preview.innerHTML = "";
            
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const imgDiv = document.createElement("div");
                    imgDiv.classList.add("preview-image");
                    imgDiv.innerHTML = `
                        <img src="${event.target.result}" alt="Uploaded Image">
                        <button type="button" class="remove-img" onclick="removeImage(this, ${index})">×</button>
                    `;
                    preview.appendChild(imgDiv);
                };
                reader.readAsDataURL(file);
            });
        }

        // Form validation
        document.getElementById('tradepoint-form').addEventListener('submit', function(e) {
            let isValid = true;
            let firstErrorField = null;

            if (tradepointType === 'Markets') {
                const requiredFields = [
                    {id: 'longitude', name: 'Longitude'},
                    {id: 'latitude', name: 'Latitude'},
                    {id: 'radius', name: 'Market Radius'},
                    {id: 'marketImages', name: 'Market Images', type: 'file'}
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (field.type === 'file') {
                        if (!element.files || element.files.length === 0) {
                            if (!firstErrorField) firstErrorField = element;
                            isValid = false;
                        }
                    } else {
                        if (!element.value.trim()) {
                            if (!firstErrorField) firstErrorField = element;
                            isValid = false;
                        }
                    }
                });
            } else {
                // For Border Points and Millers, just check if files are uploaded
                const fileInput = document.getElementById(fileInputId);
                if (!fileInput.files || fileInput.files.length === 0) {
                    isValid = false;
                    firstErrorField = fileInput;
                }
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields and upload the required images.');
                if (firstErrorField) {
                    firstErrorField.focus();
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

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