<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Get commodity ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch commodity details
$commodity = null;
if ($id > 0) {
    $stmt = $con->prepare("SELECT * FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $commodity = $result->fetch_assoc();
    $stmt->close();
}

if (!$commodity) {
    echo "<script>alert('Commodity not found'); window.location.href='../base/sidebar.php';</script>";
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
$commodity_units_decoded = json_decode($commodity['units'] ?? '[]', true);
if (!is_array($commodity_units_decoded)) {
    $commodity_units_decoded = [];
}

$commodity_aliases_decoded = json_decode($commodity['commodity_alias'] ?? '[]', true);
if (!is_array($commodity_aliases_decoded)) {
    $commodity_aliases_decoded = [];
}

$countries_decoded = json_decode($commodity['country'] ?? '[]', true);
if (!is_array($countries_decoded)) {
    $countries_decoded = [];
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_name = $_POST['commodity_name'];
    $category_id = (int)$_POST['category'];
    $variety = $_POST['variety'];

    // Check for duplicate commodity (same category, commodity name, and variety) excluding current commodity
    $duplicate_check_sql = "SELECT id FROM commodities WHERE category_id = ? AND commodity_name = ? AND variety = ? AND id != ?";
    $duplicate_stmt = $con->prepare($duplicate_check_sql);
    $duplicate_stmt->bind_param('issi', $category_id, $commodity_name, $variety, $id);
    $duplicate_stmt->execute();
    $duplicate_result = $duplicate_stmt->get_result();
    
    if ($duplicate_result->num_rows > 0) {
        $error_message = "A commodity with the same category, name, and variety already exists. Please choose different details.";
    } else {
        // Initialize packaging and unit arrays to empty if not set
        $packaging_array = $_POST['packaging'] ?? [];
        $unit_array = $_POST['unit'] ?? [];

        $hs_code = $_POST['hs_code'];
        $posted_commodity_aliases = $_POST['commodity_alias'] ?? [];
        $posted_countries = $_POST['country'] ?? [];

        $image_url = $commodity['image_url']; // Keep current image if no new upload
        if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            $image_name = basename($_FILES['commodity_image']['name']);
            $image_path = $upload_dir . $image_name;
            if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $image_path)) {
                $image_url = $image_path;
            }
        }

        // Combine packaging and unit into arrays of objects
        $combined_units = [];
        if (is_array($packaging_array) && is_array($unit_array)) {
            for ($i = 0; $i < count($packaging_array); $i++) {
                $combined_units[] = [
                    'size' => $packaging_array[$i],
                    'unit' => $unit_array[$i]
                ];
            }
        }

        $units_json = json_encode($combined_units);
        $commodity_aliases_json = json_encode($posted_commodity_aliases);
        $countries_json = json_encode($posted_countries);

        $sql = "UPDATE commodities
                SET commodity_name = ?, category_id = ?, variety = ?, units = ?, hs_code = ?, commodity_alias = ?, country = ?, image_url = ?
                WHERE id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param(
            'sississsi',
            $commodity_name,
            $category_id,
            $variety,
            $units_json,
            $hs_code,
            $commodity_aliases_json,
            $countries_json,
            $image_url,
            $id
        );
        $stmt->execute();

        if ($stmt->errno) {
            $error_message = "MySQL Error: " . $stmt->error;
        } else {
            echo "<script>alert('Commodity updated successfully'); window.location.href='../base/sidebar.php';</script>";
            exit;
        }
    }
}

// Fetch categories from DB
$categories = [];
$sql_cat = "SELECT id, name FROM commodity_categories ORDER BY name ASC";
$result_cat = mysqli_query($con, $sql_cat);
if ($result_cat) {
    while ($row_cat = mysqli_fetch_assoc($result_cat)) {
        $categories[] = $row_cat;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Commodity</title>
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
        
        /* Current commodity info */
        .commodity-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .commodity-info h5 {
            margin-bottom: 15px;
            color: rgba(180, 80, 50, 1);
        }
        .commodity-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        .commodity-info img {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        /* Dynamic fields styling */
        .dynamic-section {
            margin-bottom: 25px;
        }
        .dynamic-container {
            border: 1px solid #e1e8ed;
            border-radius: 5px;
            padding: 15px;
            background-color: #fafbfc;
            max-height: 300px;
            overflow-y: auto;
        }
        .dynamic-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: end;
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #e1e8ed;
        }
        .dynamic-group > div {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .dynamic-group label {
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }
        .dynamic-group input,
        .dynamic-group select {
            margin-bottom: 0;
            padding: 8px;
            font-size: 13px;
        }
        .remove-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            height: 38px;
            min-width: 40px;
        }
        .remove-btn:hover {
            background-color: #c82333;
        }
        .add-more-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            margin-top: 10px;
        }
        .add-more-btn:hover {
            background-color: #218838;
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
            .dynamic-group {
                flex-direction: column;
                gap: 10px;
            }
            .dynamic-group > div {
                flex: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>
        
        <h2>Edit Commodity</h2>
        <p>Update the commodity details below</p>
        
        <!-- Display current commodity info -->
        <div class="commodity-info">
            <h5>Current Commodity Information</h5>
            <p><strong>Category:</strong> 
                <?php 
                foreach ($categories as $cat) {
                    if ($cat['id'] == $commodity['category_id']) {
                        echo htmlspecialchars($cat['name']);
                        break;
                    }
                }
                ?>
            </p>
            <p><strong>Name:</strong> <?= htmlspecialchars($commodity['commodity_name']) ?></p>
            <p><strong>Variety:</strong> <?= htmlspecialchars($commodity['variety'] ?? 'N/A') ?></p>
            <p><strong>HS Code:</strong> <?= htmlspecialchars($commodity['hs_code'] ?? 'N/A') ?></p>
            <?php if ($commodity['image_url']): ?>
                <p><strong>Current Image:</strong></p>
                <img src="<?= htmlspecialchars($commodity['image_url']) ?>" alt="Commodity Image">
            <?php endif; ?>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_commodity.php?id=<?= $id ?>" enctype="multipart/form-data">
            <div class="form-group-full">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($commodity['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="commodity-name">Commodity Name *</label>
                    <input type="text" id="commodity-name" name="commodity_name" 
                           value="<?= htmlspecialchars($commodity['commodity_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="variety">Variety</label>
                    <input type="text" id="variety" name="variety" 
                           value="<?= htmlspecialchars($commodity['variety'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group-full">
                <label for="hs_code">HS Code</label>
                <input type="text" name="hs_code" id="hs_code" 
                       value="<?= htmlspecialchars($commodity['hs_code'] ?? '') ?>">
            </div>

            <div class="dynamic-section">
                <label>Commodity Packaging & Unit</label>
                <div id="packaging-unit-container" class="dynamic-container">
                    <?php
                    if (!empty($commodity_units_decoded)) {
                        foreach ($commodity_units_decoded as $index => $unit_data) {
                            // Ensure $unit_data is an array and handle potential string values
                            if (is_array($unit_data)) {
                                $size_val = htmlspecialchars($unit_data['size'] ?? '');
                                $unit_val = htmlspecialchars($unit_data['unit'] ?? '');
                            } else {
                                // Handle case where unit_data might be a string
                                $size_val = '';
                                $unit_val = is_string($unit_data) ? htmlspecialchars($unit_data) : '';
                            }
                            echo '
                            <div class="dynamic-group">
                                <div>
                                    <label>Size</label>
                                    <input type="text" name="packaging[]" value="' . $size_val . '">
                                </div>
                                <div>
                                    <label>Unit</label>
                                    <select name="unit[]" required>
                                        <option value="">Select unit</option>
                                        <option value="Kg"' . ($unit_val === 'Kg' ? ' selected' : '') . '>Kg</option>
                                        <option value="Tons"' . ($unit_val === 'Tons' ? ' selected' : '') . '>Tons</option>
                                    </select>
                                </div>
                                <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
                            </div>';
                        }
                    } else {
                        echo '
                        <div class="dynamic-group">
                            <div>
                                <label>Size</label>
                                <input type="text" name="packaging[]" value="">
                            </div>
                            <div>
                                <label>Unit</label>
                                <select name="unit[]" required>
                                    <option value="">Select unit</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Tons">Tons</option>
                                </select>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
                        </div>';
                    }
                    ?>
                </div>
                <button type="button" class="add-more-btn" onclick="addPackagingUnitRow()">
                    <i class="fa fa-plus"></i> Add More Packaging & Unit
                </button>
            </div>

            <div class="dynamic-section">
                <label>Commodity Alias & Country</label>
                <div id="alias-country-container" class="dynamic-container">
                    <?php
                    $max_entries = max(count($commodity_aliases_decoded), count($countries_decoded));
                    if ($max_entries > 0) {
                        for ($i = 0; $i < $max_entries; $i++) {
                            // Ensure we're working with strings, not arrays
                            $alias_val = '';
                            $country_val = '';
                            
                            if (isset($commodity_aliases_decoded[$i])) {
                                $alias_val = is_string($commodity_aliases_decoded[$i]) ? htmlspecialchars($commodity_aliases_decoded[$i]) : '';
                            }
                            
                            if (isset($countries_decoded[$i])) {
                                $country_val = is_string($countries_decoded[$i]) ? htmlspecialchars($countries_decoded[$i]) : '';
                            }
                            
                            echo '<div class="dynamic-group">
                                <div>
                                    <label>Alias</label>
                                    <input type="text" name="commodity_alias[]" value="' . $alias_val . '">
                                </div>
                                <div>
                                    <label>Country</label>
                                    <select name="country[]" required>
                                        <option value="">Select country</option>';
                            foreach ($countries as $country) {
                                $selected = ($country_val === $country) ? ' selected' : '';
                                echo '<option value="' . htmlspecialchars($country) . '"' . $selected . '>' . htmlspecialchars($country) . '</option>';
                            }
                            echo '</select>
                                </div>
                                <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
                            </div>';
                        }
                    } else {
                        echo '<div class="dynamic-group">
                            <div>
                                <label>Alias</label>
                                <input type="text" name="commodity_alias[]" value="">
                            </div>
                            <div>
                                <label>Country</label>
                                <select name="country[]" required>
                                    <option value="">Select country</option>';
                        foreach ($countries as $country) {
                            echo '<option value="' . htmlspecialchars($country) . '">' . htmlspecialchars($country) . '</option>';
                        }
                        echo '</select>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
                        </div>';
                    }
                    ?>
                </div>
                <button type="button" class="add-more-btn" onclick="addAliasCountryRow()">
                    <i class="fa fa-plus"></i> Add More Alias & Country
                </button>
            </div>

            <div class="form-group-full">
                <label for="commodity_image">Commodity Image</label>
                <input type="file" name="commodity_image" id="commodity_image" accept="image/*">
            </div>

            <button type="submit" class="update-btn">
                <i class="fa fa-save"></i> Update Commodity
            </button>
        </form>
    </div>

    <script>
        // Store countries array for JavaScript use
        const countries = <?php echo json_encode($countries); ?>;

        // Generic function to remove a dynamic row
        function removeDynamicGroup(button) {
            const group = button.closest('.dynamic-group');
            if (group) {
                group.remove();
            }
        }

        function addPackagingUnitRow() {
            const container = document.getElementById('packaging-unit-container');
            const newGroup = document.createElement('div');
            newGroup.className = 'dynamic-group';
            newGroup.innerHTML = `
                <div>
                    <label>Size</label>
                    <input type="text" name="packaging[]" value="">
                </div>
                <div>
                    <label>Unit</label>
                    <select name="unit[]" required>
                        <option value="">Select unit</option>
                        <option value="Kg">Kg</option>
                        <option value="Tons">Tons</option>
                    </select>
                </div>
                <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
            `;
            container.appendChild(newGroup);
            container.scrollTop = container.scrollHeight;
        }

        function addAliasCountryRow() {
            const container = document.getElementById('alias-country-container');
            
            // Build country options dynamically
            let countryOptions = '<option value="">Select country</option>';
            countries.forEach(country => {
                countryOptions += `<option value="${country}">${country}</option>`;
            });

            const newGroup = document.createElement('div');
            newGroup.className = 'dynamic-group';
            newGroup.innerHTML = `
                <div>
                    <label>Alias</label>
                    <input type="text" name="commodity_alias[]" value="">
                </div>
                <div>
                    <label>Country</label>
                    <select name="country[]" required>
                        ${countryOptions}
                    </select>
                </div>
                <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
            `;
            container.appendChild(newGroup);
            container.scrollTop = container.scrollHeight;
        }

        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const commodityName = document.getElementById('commodity-name').value;
            const category = document.getElementById('category').value;

            if (!commodityName || !category) {
                e.preventDefault();
                alert('Please fill all required fields.');
                return false;
            }

            // Confirm update
            const confirmUpdate = confirm('Are you sure you want to update this commodity?');
            if (!confirmUpdate) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>