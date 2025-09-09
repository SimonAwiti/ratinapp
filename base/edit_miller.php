<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Get miller ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch miller details
$miller_data = null;
if ($id > 0) {
    $stmt = $con->prepare("SELECT * FROM miller_details WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $miller_data = $result->fetch_assoc();
    $stmt->close();
}

if (!$miller_data) {
    echo "<script>alert('Miller not found'); window.location.href='../base/commodities_boilerplate.php';</script>";
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

// Decode JSON fields for display
$selected_millers_decoded = json_decode($miller_data['miller'] ?? '[]', true);
if (!is_array($selected_millers_decoded)) {
    $selected_millers_decoded = [];
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $miller_name = $_POST['miller_name'];
    $country = $_POST['country'];
    $county_district = $_POST['county_district'];
    $selected_millers = $_POST['selected_millers'] ?? [];

    // Validate miller selection (minimum 1, maximum 2)
    if (empty($selected_millers)) {
        $error_message = "Please select at least one miller.";
    } elseif (count($selected_millers) > 2) {
        $error_message = "You can select a maximum of 2 millers only.";
    } else {
        // Convert array of millers into a JSON string
        $miller_array_json = json_encode($selected_millers);

        // Get currency based on country
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
        $currency = isset($currency_map[$country]) ? $currency_map[$country] : 'N/A';

        $sql = "UPDATE miller_details
                SET miller_name = ?, miller = ?, country = ?, county_district = ?, currency = ?
                WHERE id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param(
            'sssssi',
            $miller_name,
            $miller_array_json,
            $country,
            $county_district,
            $currency,
            $id
        );
        $stmt->execute();

        if ($stmt->errno) {
            $error_message = "MySQL Error: " . $stmt->error;
        } else {
            echo "<script>alert('Miller updated successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
            exit;
        }
    }
}

// Get available millers for the current miller name
$available_millers = [];
if ($miller_data['miller_name']) {
    $stmt = $con->prepare("SELECT miller FROM millers WHERE miller_name = ?");
    $stmt->bind_param("s", $miller_data['miller_name']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_millers[] = $row['miller'];
    }
    $stmt->close();
}

// Get currency
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
$current_currency = isset($currency_map[$miller_data['country']]) ? $currency_map[$miller_data['country']] : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Miller</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            max-width: 900px;
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
        input, select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }
        
        /* Current miller info */
        .miller-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .miller-info h5 {
            margin-bottom: 15px;
            color: rgba(180, 80, 50, 1);
        }
        .miller-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Miller selection styles */
        .miller-limit-info {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #1976d2;
        }
        .miller-limit-info i {
            margin-right: 8px;
        }
        
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
        
        .limit-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 14px;
            color: #856404;
            display: none;
        }
        .limit-warning i {
            margin-right: 8px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>
        
        <h2>Edit Miller</h2>
        <p>Update the miller details below</p>
        
        <!-- Display current miller info -->
        <div class="miller-info">
            <h5>Current Miller Information</h5>
            <p><strong>Miller Name:</strong> <?= htmlspecialchars($miller_data['miller_name']) ?></p>
            <p><strong>Country:</strong> <?= htmlspecialchars($miller_data['country']) ?></p>
            <p><strong>County/District:</strong> <?= htmlspecialchars($miller_data['county_district']) ?></p>
            <p><strong>Currency:</strong> <?= htmlspecialchars($current_currency) ?></p>
            <p><strong>Selected Millers:</strong> 
                <?php 
                if (!empty($selected_millers_decoded)) {
                    echo htmlspecialchars(implode(', ', $selected_millers_decoded));
                } else {
                    echo 'None';
                }
                ?>
            </p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_miller.php?id=<?= $id ?>">
            <div class="form-group-full">
                <label for="miller_name" class="required">Miller Name</label>
                <input type="text" id="miller_name" name="miller_name" 
                       value="<?= htmlspecialchars($miller_data['miller_name'] ?? '') ?>" required readonly>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="country" class="required">Country</label>
                    <select id="country" name="country" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" 
                                    <?= ($miller_data['country'] == $country) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="county_district" class="required">County/District</label>
                    <input type="text" id="county_district" name="county_district" 
                           value="<?= htmlspecialchars($miller_data['county_district'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Miller limit information -->
            <div class="miller-limit-info">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> You can select a maximum of 2 millers. Click on selected millers below to remove them and select different ones.
            </div>
            
            <div class="form-group-full">
                <label for="selected_millers" class="required">Select Millers (Maximum 2)</label>
                <select id="selected_millers" name="selected_millers[]" multiple="multiple" required>
                    <?php foreach ($available_millers as $miller): ?>
                        <option value="<?= htmlspecialchars($miller) ?>" 
                                <?= in_array($miller, $selected_millers_decoded) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($miller) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Limit warning message -->
                <div class="limit-warning" id="limit-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Maximum limit reached!</strong> You can only select 2 millers. Remove one to select another.
                </div>
                
                <!-- Display selected tags below the dropdown -->
                <div class="selected-tags" id="selected-tags-container">
                    <small class="text-muted">Selected millers will appear here (Maximum 2)</small>
                </div>
            </div>

            <button type="submit" class="update-btn">
                <i class="fa fa-save"></i> Update Miller
            </button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#selected_millers').select2({
                placeholder: "Select millers (Maximum 2)",
                tags: false,
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: Infinity,
                maximumSelectionLength: 2
            });

            // Store selected millers in an array
            var selectedMillers = <?= json_encode($selected_millers_decoded) ?>;
            const maxMillers = 2;

            // Set initial values
            $('#selected_millers').val(selectedMillers).trigger('change');
            updateTagsContainer();

            // Event listener to update the tags container when selections change
            $('#selected_millers').on('change', function() {
                var selectedOptions = $(this).val();
                selectedMillers = selectedOptions || [];
                
                if (selectedMillers.length >= maxMillers) {
                    $('#limit-warning').show();
                } else {
                    $('#limit-warning').hide();
                }
                
                updateTagsContainer();
            });

            // Prevent selection when limit is reached
            $('#selected_millers').on('select2:selecting', function(e) {
                if (selectedMillers.length >= maxMillers) {
                    e.preventDefault();
                    alert('You can only select a maximum of ' + maxMillers + ' millers. Please remove one to select another.');
                    return false;
                }
            });

            // Update the tags container
            function updateTagsContainer() {
                var tagsContainer = $('#selected-tags-container');
                tagsContainer.empty();
                
                if (selectedMillers.length > 0) {
                    selectedMillers.forEach(function(miller) {
                        var tag = $('<span>')
                            .text(miller)
                            .append('<span class="remove-tag" title="Click to remove">×</span>')
                            .click(function() {
                                var index = selectedMillers.indexOf(miller);
                                if (index > -1) {
                                    selectedMillers.splice(index, 1);
                                    $('#selected_millers').val(selectedMillers).trigger('change');
                                    
                                    if (selectedMillers.length < maxMillers) {
                                        $('#limit-warning').hide();
                                    }
                                    
                                    updateTagsContainer();
                                }
                            });
                        tagsContainer.append(tag);
                    });
                    
                    var counterInfo = $('<small class="text-muted d-block mt-2">')
                        .text(selectedMillers.length + '/' + maxMillers + ' millers selected');
                    tagsContainer.append(counterInfo);
                    
                } else {
                    tagsContainer.html('<small class="text-muted">Selected millers will appear here (Maximum 2)</small>');
                }
            }

            // Form validation
            $('form').on('submit', function(e) {
                const millerName = $('#miller_name').val();
                const country = $('#country').val();
                const countyDistrict = $('#county_district').val();
                const selectedMillersCount = selectedMillers.length;

                if (!millerName || !country || !countyDistrict) {
                    e.preventDefault();
                    alert('Please fill all required fields.');
                    return false;
                }

                if (selectedMillersCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one miller.');
                    return false;
                }

                const confirmUpdate = confirm('Are you sure you want to update this miller?');
                if (!confirmUpdate) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>