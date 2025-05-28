<?php
session_start();
include '../admin/includes/config.php';

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Check if we have session data from previous steps
if (!isset($_SESSION['tradepoint'])) {
    header('Location: addtradepoint.php');
    exit;
}

$tradepoint_type = $_SESSION['tradepoint'];

// Validate required session data based on tradepoint type
if ($tradepoint_type == "Markets") {
    $required_fields = ['market_name', 'category', 'type', 'country', 'county_district', 'longitude', 'latitude', 'radius', 'currency', 'image_url'];
    foreach ($required_fields as $field) {
        if (!isset($_SESSION[$field])) {
            header('Location: addtradepoint.php');
            exit;
        }
    }
} elseif ($tradepoint_type == "Millers") {
    $required_fields = ['miller_name', 'country', 'county_district'];
    foreach ($required_fields as $field) {
        if (!isset($_SESSION[$field])) {
            header('Location: addtradepoint.php');
            exit;
        }
    }
} else {
    // Border Points don't have step 3, redirect back
    header('Location: addtradepoint.php');
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if ($tradepoint_type == "Markets") {
        // Handle Markets final submission
        $primary_commodities = isset($_POST['primary_commodity']) ? $_POST['primary_commodity'] : [];
        $additional_datasource = $_POST['additional_datasource'] ?? '';

        // Validate that at least one primary commodity is selected
        if (empty($primary_commodities)) {
            echo "<script>alert('Error: At least one primary commodity is required.');</script>";
        } else {
            // Convert array of commodities to a comma-separated string
            $commodities_str = implode(',', $primary_commodities);

            // Insert data into the database using a transaction
            $con->begin_transaction();
            try {
                $sql = "INSERT INTO markets (market_name, category, type, country, county_district, longitude, latitude, radius, currency, primary_commodity, additional_datasource, image_url, tradepoint) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $con->prepare($sql);
                $tradepoint = "Markets";

                $stmt->bind_param("sssssdddsssss", 
                    $_SESSION['market_name'], 
                    $_SESSION['category'], 
                    $_SESSION['type'], 
                    $_SESSION['country'], 
                    $_SESSION['county_district'], 
                    $_SESSION['longitude'], 
                    $_SESSION['latitude'], 
                    $_SESSION['radius'], 
                    $_SESSION['currency'], 
                    $commodities_str, 
                    $additional_datasource,
                    $_SESSION['image_url'],
                    $tradepoint
                );

                $stmt->execute();
                $con->commit();
                
                // Clear session data
                session_unset();
                echo "<script>alert('Market added successfully!'); window.location.href='addtradepoint.php';</script>";
                exit;
                
            } catch (Exception $e) {
                $con->rollback();
                echo "<script>alert('Database error: " . $e->getMessage() . "'); window.history.back();</script>";
            }
        }
        
    } elseif ($tradepoint_type == "Millers") {
        // Handle Millers final submission
        $selected_millers = $_POST['selected_millers'] ?? [];

        if (!empty($selected_millers)) {
            // Convert array of millers into a JSON string
            $miller_array_json = json_encode($selected_millers);

            // Prepare a single insert statement to store millers as an array
            $stmt = $con->prepare("INSERT INTO miller_details (miller_name, miller, country, county_district, currency) VALUES (?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param("sssss", 
                    $_SESSION['miller_name'], 
                    $miller_array_json, 
                    $_SESSION['country'], 
                    $_SESSION['county_district'], 
                    $_SESSION['currency']
                );
                
                if ($stmt->execute()) {
                    $stmt->close();
                    // Clear session data
                    session_unset();
                    echo "<script>alert('Miller details saved successfully!'); window.location.href='addtradepoint.php';</script>";
                    exit;
                } else {
                    echo "<script>alert('Failed to save miller details!'); window.history.back();</script>";
                }
            } else {
                echo "<script>alert('Failed to prepare statement');</script>";
            }
        } else {
            echo "<script>alert('Please select at least one miller');</script>";
        }
    }
}

// Fetch commodities from the database (for Markets)
$commodities_result = null;
if ($tradepoint_type == "Markets") {
    $commodities_query = "SELECT id, commodity_name FROM commodities";
    $commodities_result = $con->query($commodities_query);
}

// Get currency for millers
$miller_currency = '';
if ($tradepoint_type == "Millers" && isset($_SESSION['country'])) {
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
    $selected_country = $_SESSION['country'];
    $miller_currency = isset($currency_map[$selected_country]) ? $currency_map[$selected_country] : 'N/A';
    $_SESSION['currency'] = $miller_currency;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tradepoint - Step 3</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <?php if ($tradepoint_type == "Millers"): ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <?php endif; ?>
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
        
        /* Button styling */
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 20px;
        }
        .prev-btn, .next-btn, .finish-btn {
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
        .next-btn, .finish-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
        }
        .next-btn:hover, .finish-btn:hover {
            background-color: rgba(160, 60, 30, 1);
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
        
        /* Commodity Selector Styles */
        .commodity-selector {
            margin-bottom: 20px;
        }
        .select-box select {
            font-size: 16px;
            color: black;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: white;
            width: 100%;
        }
        .tags-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            background-color: #f8f9fa;
            min-height: 50px;
        }
        .tag {
            display: flex;
            align-items: center;
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .tag button {
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 8px;
            font-size: 16px;
            color: white;
            font-weight: bold;
        }
        .tag button:hover {
            color: #ffcccc;
        }
        
        /* Miller-specific styles */
        .selected-tags {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f8f9fa;
            min-height: 60px;
        }
        .selected-tags span {
            display: inline-block;
            margin-right: 8px;
            margin-bottom: 8px;
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
        }
        .selected-tags span:hover {
            background-color: rgba(160, 60, 30, 1);
        }
        .selected-tags span .remove-tag {
            margin-left: 10px;
            font-weight: bold;
            color: #fff;
            cursor: pointer;
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
            .button-container {
                flex-direction: column;
            }
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
                <div class="step completed">
                    <div class="step-circle completed" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Details</small></div>
                </div>
                <div class="step active">
                    <div class="step-circle active" data-step="3"></div>
                    <div class="step-text">Step 3<br><small>Final</small></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <h2>Add <?= htmlspecialchars($tradepoint_type) ?> - Step 3</h2>
            <p>Complete the final details for your <?= strtolower($tradepoint_type) ?>.</p>
            
            <form id="tradepoint-form" method="POST" action="">
                
                <!-- Markets Section -->
                <?php if ($tradepoint_type == "Markets"): ?>
                <div class="section-header">
                    <h6><i class="fas fa-tags"></i> Commodity Assignment</h6>
                    <p>Select the primary commodities traded in this market</p>
                </div>
                
                <div class="commodity-selector">
                    <div class="form-group-full">
                        <label for="commodity_select" class="required">Assign Primary Commodities</label>
                        <div class="select-box">
                            <select id="commodity_select" multiple>
                                <option value="">Select commodities</option>
                                <?php
                                if ($commodities_result && $commodities_result->num_rows > 0) {
                                    while ($row = $commodities_result->fetch_assoc()) {
                                        echo "<option value='" . $row['id'] . "' data-name='" . htmlspecialchars($row['commodity_name']) . "'>" . htmlspecialchars($row['commodity_name']) . "</option>";
                                    }
                                } else {
                                    echo "<option value=''>No commodities available</option>";
                                }
                                ?>
                            </select>
                            <!-- Hidden input to store selected commodity IDs for form submission -->
                            <input type="hidden" id="selected_commodities" name="primary_commodity[]" value="">
                        </div>
                        <div class="tags-container" id="commodity_tags">
                            <small class="text-muted">Selected commodities will appear here</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group-full">
                    <label for="additional_datasource" class="required">Additional Data Sources</label>
                    <input type="text" id="additional_datasource" name="additional_datasource" placeholder="Enter additional data sources" required>
                </div>
                <?php endif; ?>

                <!-- Millers Section -->
                <?php if ($tradepoint_type == "Millers"): ?>
                <div class="section-header">
                    <h6><i class="fas fa-industry"></i> Miller Selection</h6>
                    <p>Select millers for <strong><?= htmlspecialchars($_SESSION['miller_name']) ?></strong></p>
                </div>
                
                <div class="form-group-full">
                    <label for="selected_millers" class="required">Select Millers</label>
                    <select id="selected_millers" name="selected_millers[]" multiple="multiple" required>
                        <?php
                        $stmt = $con->prepare("SELECT miller FROM millers WHERE miller_name = ?");
                        $stmt->bind_param("s", $_SESSION['miller_name']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($row['miller']) . '">' . htmlspecialchars($row['miller']) . '</option>';
                        }
                        $stmt->close();
                        ?>
                    </select>
                    
                    <!-- Display selected tags below the dropdown -->
                    <div class="selected-tags" id="selected-tags-container">
                        <small class="text-muted">Selected millers will appear here</small>
                    </div>
                </div>
                <?php endif; ?>

                <div class="button-container">
                    <button type="button" class="prev-btn" onclick="window.location.href='add_tradepoint2.php'">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="submit" class="finish-btn">
                        <i class="fas fa-check"></i> Complete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($tradepoint_type == "Millers"): ?>
    <!-- Select2 Scripts for Millers -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 with the "minimum results for search" option
            $('#selected_millers').select2({
                placeholder: "Select millers",
                tags: false,
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: Infinity  // Disable the search box in the dropdown
            });

            // Store selected millers in an array
            var selectedMillers = [];

            // Event listener to update the tags container when selections change
            $('#selected_millers').on('change', function() {
                var selectedOptions = $(this).val();

                // Update the selectedMillers array
                selectedMillers = selectedOptions || [];
                updateTagsContainer();
            });

            // Update the tags container with selected millers
            function updateTagsContainer() {
                var tagsContainer = $('#selected-tags-container');
                tagsContainer.empty();  // Clear existing tags
                
                if (selectedMillers.length > 0) {
                    selectedMillers.forEach(function(miller) {
                        var tag = $('<span>')
                            .text(miller)
                            .append('<span class="remove-tag">×</span>')
                            .click(function() {
                                // Remove tag from selected array
                                var index = selectedMillers.indexOf(miller);
                                if (index > -1) {
                                    selectedMillers.splice(index, 1);
                                    $('#selected_millers').val(selectedMillers).trigger('change');
                                    updateTagsContainer(); // Update tags view
                                }
                            });
                        tagsContainer.append(tag);
                    });
                } else {
                    tagsContainer.html('<small class="text-muted">Selected millers will appear here</small>');
                }
            }
        });
    </script>
    <?php endif; ?>

    <?php if ($tradepoint_type == "Markets"): ?>
    <!-- Commodity Selection Script for Markets -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commoditySelect = document.getElementById('commodity_select');
            const commodityTags = document.getElementById('commodity_tags');
            const selectedCommoditiesInput = document.getElementById('selected_commodities');
            let selectedCommodities = [];

            // Function to update the tags display
            function updateTags() {
                commodityTags.innerHTML = '';
                selectedCommoditiesInput.value = selectedCommodities.join(',');
                
                if (selectedCommodities.length === 0) {
                    commodityTags.innerHTML = '<small class="text-muted">Selected commodities will appear here</small>';
                    return;
                }
                
                selectedCommodities.forEach(id => {
                    const option = commoditySelect.querySelector(`option[value="${id}"]`);
                    if (option) {
                        const tag = document.createElement('div');
                        tag.className = 'tag';
                        tag.innerHTML = `
                            ${option.textContent}
                            <button type="button" onclick="removeCommodity('${id}')">×</button>
                        `;
                        commodityTags.appendChild(tag);
                    }
                });
            }

            // Function to remove a commodity
            window.removeCommodity = function(id) {
                selectedCommodities = selectedCommodities.filter(item => item !== id);
                updateTags();
            };

            // Handle selection from dropdown
            commoditySelect.addEventListener('change', function() {
                Array.from(this.selectedOptions).forEach(option => {
                    if (option.value && !selectedCommodities.includes(option.value)) {
                        selectedCommodities.push(option.value);
                    }
                });
                
                // Reset the select
                this.selectedIndex = 0;
                updateTags();
            });

            // Initialize with any previously selected commodities
            updateTags();
        });
    </script>
    <?php endif; ?>

    <script>
        // Form validation
        document.getElementById('tradepoint-form').addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';

            <?php if ($tradepoint_type == "Markets"): ?>
            // Validate commodities selection
            const selectedCommodities = document.getElementById('selected_commodities').value;
            if (!selectedCommodities || selectedCommodities.trim() === '') {
                isValid = false;
                errorMessage = 'Please select at least one primary commodity.';
            }
            
            // Validate additional data source
            const additionalDataSource = document.getElementById('additional_datasource').value;
            if (!additionalDataSource || additionalDataSource.trim() === '') {
                isValid = false;
                errorMessage = 'Please provide additional data sources.';
            }
            <?php endif; ?>

            <?php if ($tradepoint_type == "Millers"): ?>
            // Validate miller selection
            const selectedMillers = $('#selected_millers').val();
            if (!selectedMillers || selectedMillers.length === 0) {
                isValid = false;
                errorMessage = 'Please select at least one miller.';
            }
            <?php endif; ?>

            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
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