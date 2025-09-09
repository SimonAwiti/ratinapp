<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Get market ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch market details
$market_data = null;
if ($id > 0) {
    $stmt = $con->prepare("SELECT * FROM markets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $market_data = $result->fetch_assoc();
    $stmt->close();
}

if (!$market_data) {
    echo "<script>alert('Market not found'); window.location.href='../base/commodities_boilerplate.php';</script>";
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

// Fetch data sources from database
$data_sources = [];
$data_source_query = "SELECT data_source_name FROM data_sources ORDER BY data_source_name ASC";
$data_source_result = $con->query($data_source_query);
if ($data_source_result) {
    while ($row = $data_source_result->fetch_assoc()) {
        $data_sources[] = $row['data_source_name'];
    }
}

// Fetch commodities with their varieties from the database
$commodities_result = null;
$commodities_query = "SELECT id, commodity_name, variety FROM commodities";
$commodities_result = $con->query($commodities_query);

// Decode primary commodities for display
$selected_commodities = [];
if (!empty($market_data['primary_commodity'])) {
    $selected_commodities = explode(',', $market_data['primary_commodity']);
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $market_name = $_POST['market_name'];
    $category = $_POST['category'];
    $type = $_POST['type'];
    $country = $_POST['country'];
    $county_district = $_POST['county_district'];
    $longitude = floatval($_POST['longitude']);
    $latitude = floatval($_POST['latitude']);
    $radius = floatval($_POST['radius']);
    $currency = $_POST['currency'];
    $primary_commodities = isset($_POST['primary_commodity']) ? $_POST['primary_commodity'] : [];
    $additional_datasource = $_POST['additional_datasource'] ?? '';

    // Validate that at least one primary commodity is selected
    if (empty($primary_commodities)) {
        $error_message = "Please select at least one primary commodity.";
    } else {
        // Convert array of commodities to a comma-separated string
        $commodities_str = implode(',', $primary_commodities);

        // Get currency based on country if not manually set
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
        if (empty($currency)) {
            $currency = isset($currency_map[$country]) ? $currency_map[$country] : 'N/A';
        }

        $sql = "UPDATE markets 
                SET market_name = ?, category = ?, type = ?, country = ?, county_district = ?, 
                    longitude = ?, latitude = ?, radius = ?, currency = ?, primary_commodity = ?, 
                    additional_datasource = ?
                WHERE id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param(
            'sssssdddsssi',
            $market_name,
            $category,
            $type,
            $country,
            $county_district,
            $longitude,
            $latitude,
            $radius,
            $currency,
            $commodities_str,
            $additional_datasource,
            $id
        );
        $stmt->execute();

        if ($stmt->errno) {
            $error_message = "MySQL Error: " . $stmt->error;
        } else {
            echo "<script>alert('Market updated successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
            exit;
        }
    }
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
$current_currency = isset($currency_map[$market_data['country']]) ? $currency_map[$market_data['country']] : 'N/A';

// Get selected commodity names for display
$selected_commodity_names = [];
if (!empty($selected_commodities)) {
    $placeholders = str_repeat('?,', count($selected_commodities) - 1) . '?';
    $stmt = $con->prepare("SELECT id, commodity_name, variety FROM commodities WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($selected_commodities)), ...$selected_commodities);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $display_name = $row['commodity_name'];
        if (!empty($row['variety'])) {
            $display_name .= " (" . $row['variety'] . ")";
        }
        $selected_commodity_names[] = $display_name;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Market</title>
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
        
        /* Current market info */
        .market-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .market-info h5 {
            margin-bottom: 15px;
            color: rgba(180, 80, 50, 1);
        }
        .market-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        .market-info .commodity-list {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 3px;
            margin-top: 5px;
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
        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 10px;
            background-color: white;
        }
        .search-input:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
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
        
        <h2>Edit Market</h2>
        <p>Update the market details below</p>
        
        <!-- Display current market info -->
        <div class="market-info">
            <h5>Current Market Information</h5>
            <p><strong>Market Name:</strong> <?= htmlspecialchars($market_data['market_name']) ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($market_data['category']) ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($market_data['type']) ?></p>
            <p><strong>Country:</strong> <?= htmlspecialchars($market_data['country']) ?></p>
            <p><strong>County/District:</strong> <?= htmlspecialchars($market_data['county_district']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($market_data['latitude']) ?>, <?= htmlspecialchars($market_data['longitude']) ?></p>
            <p><strong>Radius:</strong> <?= htmlspecialchars($market_data['radius']) ?> km</p>
            <p><strong>Currency:</strong> <?= htmlspecialchars($current_currency) ?></p>
            <p><strong>Data Source:</strong> <?= htmlspecialchars($market_data['additional_datasource']) ?></p>
            <p><strong>Primary Commodities:</strong></p>
            <div class="commodity-list">
                <?php 
                if (!empty($selected_commodity_names)) {
                    echo htmlspecialchars(implode(', ', $selected_commodity_names));
                } else {
                    echo 'None';
                }
                ?>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_market.php?id=<?= $id ?>">
            <div class="section-header">
                <h6><i class="fas fa-store"></i> Basic Market Information</h6>
                <p>Update the basic details of the market</p>
            </div>

            <div class="form-group-full">
                <label for="market_name" class="required">Market Name</label>
                <input type="text" id="market_name" name="market_name" 
                       value="<?= htmlspecialchars($market_data['market_name'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category" class="required">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>
                        <option value="Regional" <?= ($market_data['category'] == 'Regional') ? 'selected' : '' ?>>Regional</option>
                        <option value="Wholesale" <?= ($market_data['category'] == 'Wholesale') ? 'selected' : '' ?>>Wholesale</option>
                        <option value="Retail" <?= ($market_data['category'] == 'Retail') ? 'selected' : '' ?>>Retail</option>
                        <option value="Terminal" <?= ($market_data['category'] == 'Terminal') ? 'selected' : '' ?>>Terminal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="type" class="required">Type</label>
                    <select id="type" name="type" required>
                        <option value="">Select type</option>
                        <option value="Primary" <?= ($market_data['type'] == 'Primary') ? 'selected' : '' ?>>Primary</option>
                        <option value="Secondary" <?= ($market_data['type'] == 'Secondary') ? 'selected' : '' ?>>Secondary</option>
                        <option value="Assembly" <?= ($market_data['type'] == 'Assembly') ? 'selected' : '' ?>>Assembly</option>
                        <option value="Terminal" <?= ($market_data['type'] == 'Terminal') ? 'selected' : '' ?>>Terminal</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="country" class="required">Country</label>
                    <select id="country" name="country" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" 
                                    <?= ($market_data['country'] == $country) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="county_district" class="required">County/District</label>
                    <input type="text" id="county_district" name="county_district" 
                           value="<?= htmlspecialchars($market_data['county_district'] ?? '') ?>" required>
                </div>
            </div>

            <div class="section-header">
                <h6><i class="fas fa-map-marker-alt"></i> Location & Coverage</h6>
                <p>Update geographical information and market coverage</p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="longitude" class="required">Longitude</label>
                    <input type="number" step="any" id="longitude" name="longitude" 
                           value="<?= htmlspecialchars($market_data['longitude'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="latitude" class="required">Latitude</label>
                    <input type="number" step="any" id="latitude" name="latitude" 
                           value="<?= htmlspecialchars($market_data['latitude'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="radius" class="required">Radius (km)</label>
                    <input type="number" step="any" id="radius" name="radius" 
                           value="<?= htmlspecialchars($market_data['radius'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="currency" class="required">Currency</label>
                    <select id="currency" name="currency" required>
                        <option value="">Select currency</option>
                        <option value="KES" <?= ($market_data['currency'] == 'KES') ? 'selected' : '' ?>>KES - Kenyan Shilling</option>
                        <option value="UGX" <?= ($market_data['currency'] == 'UGX') ? 'selected' : '' ?>>UGX - Ugandan Shilling</option>
                        <option value="TZS" <?= ($market_data['currency'] == 'TZS') ? 'selected' : '' ?>>TZS - Tanzanian Shilling</option>
                        <option value="RWF" <?= ($market_data['currency'] == 'RWF') ? 'selected' : '' ?>>RWF - Rwandan Franc</option>
                        <option value="BIF" <?= ($market_data['currency'] == 'BIF') ? 'selected' : '' ?>>BIF - Burundian Franc</option>
                        <option value="SSP" <?= ($market_data['currency'] == 'SSP') ? 'selected' : '' ?>>SSP - South Sudanese Pound</option>
                        <option value="ETB" <?= ($market_data['currency'] == 'ETB') ? 'selected' : '' ?>>ETB - Ethiopian Birr</option>
                        <option value="SOS" <?= ($market_data['currency'] == 'SOS') ? 'selected' : '' ?>>SOS - Somali Shilling</option>
                        <option value="CDF" <?= ($market_data['currency'] == 'CDF') ? 'selected' : '' ?>>CDF - Congolese Franc</option>
                    </select>
                </div>
            </div>

            <div class="section-header">
                <h6><i class="fas fa-tags"></i> Commodity Assignment</h6>
                <p>Update the primary commodities traded in this market</p>
            </div>
            
            <div class="commodity-selector">
                <div class="form-group-full">
                    <label for="commodity_select" class="required">Assign Primary Commodities</label>
                    <input type="text" id="commodity_search" class="search-input" placeholder="Search commodities...">
                    <div class="select-box">
                        <select id="commodity_select" multiple>
                            <option value="">Select commodities</option>
                            <?php
                            if ($commodities_result && $commodities_result->num_rows > 0) {
                                while ($row = $commodities_result->fetch_assoc()) {
                                    $display_name = $row['commodity_name'];
                                    if (!empty($row['variety'])) {
                                        $display_name .= " (" . $row['variety'] . ")";
                                    }
                                    echo "<option value='" . $row['id'] . "' data-name='" . htmlspecialchars($display_name) . "'>" . htmlspecialchars($display_name) . "</option>";
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
                <select id="additional_datasource" name="additional_datasource" required>
                    <option value="">Select data source</option>
                    <?php foreach ($data_sources as $source): ?>
                        <option value="<?= htmlspecialchars($source) ?>" 
                                <?= ($market_data['additional_datasource'] == $source) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($source) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="update-btn">
                <i class="fa fa-save"></i> Update Market
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commoditySelect = document.getElementById('commodity_select');
            const commodityTags = document.getElementById('commodity_tags');
            const selectedCommoditiesInput = document.getElementById('selected_commodities');
            const searchInput = document.getElementById('commodity_search');
            let selectedCommodities = <?= json_encode($selected_commodities) ?>;
            let allOptions = [];

            // Store all options for filtering
            function initializeOptions() {
                allOptions = Array.from(commoditySelect.options).map(option => ({
                    value: option.value,
                    text: option.textContent,
                    dataName: option.dataset.name,
                    element: option
                }));
            }

            // Filter options based on search
            function filterOptions(searchTerm) {
                const filteredOptions = allOptions.filter(option => {
                    if (!option.value) return true; // Keep the default "Select commodities" option
                    return option.text.toLowerCase().includes(searchTerm.toLowerCase());
                });

                // Clear and repopulate select
                commoditySelect.innerHTML = '';
                filteredOptions.forEach(option => {
                    commoditySelect.appendChild(option.element.cloneNode(true));
                });
            }

            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                filterOptions(searchTerm);
            });

            // Function to update the tags display
            function updateTags() {
                commodityTags.innerHTML = '';
                selectedCommoditiesInput.value = selectedCommodities.join(',');
                
                if (selectedCommodities.length === 0) {
                    commodityTags.innerHTML = '<small class="text-muted">Selected commodities will appear here</small>';
                    return;
                }
                
                selectedCommodities.forEach(id => {
                    const option = allOptions.find(opt => opt.value === id);
                    if (option) {
                        const tag = document.createElement('div');
                        tag.className = 'tag';
                        tag.innerHTML = `
                            ${option.dataName || option.text}
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

            // Initialize
            initializeOptions();
            updateTags();

            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                let isValid = true;
                let errorMessage = '';

                // Validate required fields
                const requiredFields = ['market_name', 'category', 'type', 'country', 'county_district', 
                                      'longitude', 'latitude', 'radius', 'currency', 'additional_datasource'];
                
                requiredFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (!field.value || field.value.trim() === '') {
                        isValid = false;
                        errorMessage = 'Please fill all required fields.';
                    }
                });

                // Validate commodities selection
                if (selectedCommodities.length === 0) {
                    isValid = false;
                    errorMessage = 'Please select at least one primary commodity.';
                }

                if (!isValid) {
                    e.preventDefault();
                    alert(errorMessage);
                    return false;
                }

                const confirmUpdate = confirm('Are you sure you want to update this market?');
                if (!confirmUpdate) {
                    e.preventDefault();
                    return false;
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
        });
    </script>
</body>
</html>