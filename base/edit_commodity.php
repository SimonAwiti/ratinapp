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
    die("Commodity not found.");
}

// Decode JSON fields for display
// Ensure that if values are NULL or empty in DB, they default to empty arrays for easier handling
$commodity_units_decoded = json_decode($commodity['units'] ?? '[]', true);
if (!is_array($commodity_units_decoded)) {
    $commodity_units_decoded = []; // Ensure it's an array even if decoding failed
}

$commodity_aliases_decoded = json_decode($commodity['commodity_alias'] ?? '[]', true);
if (!is_array($commodity_aliases_decoded)) {
    $commodity_aliases_decoded = []; // Ensure it's an array
}

$countries_decoded = json_decode($commodity['country'] ?? '[]', true);
if (!is_array($countries_decoded)) {
    $countries_decoded = []; // Ensure it's an array
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_name = $_POST['commodity_name'];
    $category_id = (int)$_POST['category']; // Explicitly cast to integer
    $variety = $_POST['variety'];

    // Initialize packaging and unit arrays to empty if not set
    $packaging_array = $_POST['packaging'] ?? [];
    $unit_array = $_POST['unit'] ?? [];

    $hs_code = $_POST['hs_code'];
    // Get aliases and countries from POST, defaulting to empty array if not present
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
    // Note: 's' for JSON type is correct as it's treated as a string by bind_param
    $stmt->bind_param(
        'sississsi',
        $commodity_name,
        $category_id,
        $variety,
        $units_json,
        $hs_code,
        $commodity_aliases_json, // Use JSON string for aliases
        $countries_json,         // Use JSON string for countries
        $image_url,
        $id
    );
    $stmt->execute();

    if ($stmt->errno) {
        echo "MySQL Error: " . $stmt->error . "<br>";
        echo "SQL Query: " . $sql . "<br>";
        echo "Bound Parameters: ";
        var_dump([
            $commodity_name,
            $category_id,
            $variety,
            $units_json,
            $hs_code,
            $commodity_aliases_json,
            $countries_json,
            $image_url,
            $id
        ]);
        exit;
    }

    header('Location: sidebar.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Commodity</title>
    <link rel="stylesheet" href="assets/edit_commodity.css" />
    <style>
        /* General styling for dynamic groups */
        .dynamic-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
            border: 1px solid #eee; /* Light border to visually separate groups */
            padding: 10px;
            border-radius: 5px;
        }
        .dynamic-group > div {
            flex: 1; /* Make sub-divs take equal space */
        }
        .dynamic-group label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block; /* Ensure label is on its own line */
        }
        .dynamic-group input,
        .dynamic-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box; /* Include padding and border in element's total width and height */
        }
        .remove-btn {
            background-color: #f8d7da;
            color: red;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            flex-shrink: 0; /* Prevent button from shrinking */
        }
        .remove-btn:hover {
            background-color: #f5c6cb;
        }
        .add-more-btn {
            background-color: #d9f5d9;
            color: green;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
            width: auto; /* Allow button to size based on content */
        }
        .add-more-btn:hover {
            background-color: #c4e6c4;
        }
        /* Containers for scrollable dynamic fields */
        .dynamic-fields-container {
            max-height: 250px; /* Adjust height as needed */
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <button class="close-btn" onclick="window.location.href='commodities.php'">×</button>

    <div class="form-container">
        <h2>Edit Commodity</h2>
        <p>Update the details of the commodity</p>
        <form method="POST" action="edit_commodity.php?id=<?= $id ?>" enctype="multipart/form-data">
            <label for="category">Category *</label>
            <select id="category" name="category" required>
                <option value="">Select category</option>
                <?php
                // Fetch categories from DB
                $categories = [];
                $sql_cat = "SELECT id, name FROM commodity_categories ORDER BY name ASC";
                $result_cat = mysqli_query($con, $sql_cat);
                if ($result_cat) {
                    while ($row_cat = mysqli_fetch_assoc($result_cat)) {
                        $categories[] = $row_cat;
                    }
                }
                foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($commodity['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="commodity-name">Commodity name *</label>
            <input type="text" id="commodity-name" name="commodity_name" value="<?= htmlspecialchars($commodity['commodity_name'] ?? '') ?>" required>

            <label for="variety">Variety</label>
            <input type="text" id="variety" name="variety" value="<?= htmlspecialchars($commodity['variety'] ?? '') ?>">

            <label>Commodity Packaging & Unit</label>
            <div id="packaging-unit-container" class="dynamic-fields-container">
                <?php
                // Display existing packaging and units
                if (!empty($commodity_units_decoded)) {
                    foreach ($commodity_units_decoded as $index => $unit_data) {
                        $size_val = htmlspecialchars($unit_data['size'] ?? '');
                        $unit_val = htmlspecialchars($unit_data['unit'] ?? '');
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
                    // Display one empty row if no existing data
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
            <button type="button" class="add-more-btn" onclick="addPackagingUnitRow()">Add More Packaging & Unit</button>


            <label for="hs_code">HS Code</label>
            <input type="text" name="hs_code" id="hs_code" value="<?= htmlspecialchars($commodity['hs_code'] ?? '') ?>">

            <label>Commodity Alias & Country</label>
            <div id="alias-country-container" class="dynamic-fields-container">
                <?php
                // Display existing aliases and countries
                // Assuming aliases and countries are stored as simple arrays of strings
                $max_entries = max(count($commodity_aliases_decoded), count($countries_decoded));
                if ($max_entries > 0) {
                    for ($i = 0; $i < $max_entries; $i++) {
                        $alias_val = htmlspecialchars($commodity_aliases_decoded[$i] ?? '');
                        $country_val = htmlspecialchars($countries_decoded[$i] ?? '');
                        echo '
                        <div class="dynamic-group">
                            <div>
                                <label>Alias</label>
                                <input type="text" name="commodity_alias[]" value="' . $alias_val . '">
                            </div>
                            <div>
                                <label>Country</label>
                                <select name="country[]" required>
                                    <option value="">Select country</option>
                                    <option value="Rwanda"' . ($country_val === 'Rwanda' ? ' selected' : '') . '>Rwanda</option>
                                    <option value="Uganda"' . ($country_val === 'Uganda' ? ' selected' : '') . '>Uganda</option>
                                    <option value="Tanzania"' . ($country_val === 'Tanzania' ? ' selected' : '') . '>Tanzania</option>
                                    <option value="Kenya"' . ($country_val === 'Kenya' ? ' selected' : '') . '>Kenya</option>
                                    </select>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
                        </div>';
                    }
                } else {
                    // Display one empty row if no existing data
                    echo '
                    <div class="dynamic-group">
                        <div>
                            <label>Alias</label>
                            <input type="text" name="commodity_alias[]" value="">
                        </div>
                        <div>
                            <label>Country</label>
                            <select name="country[]" required>
                                <option value="">Select country</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="Kenya">Kenya</option>
                                </select>
                        </div>
                        <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
                    </div>';
                }
                ?>
            </div>
            <button type="button" class="add-more-btn" onclick="addAliasCountryRow()">Add More Alias & Country</button>

            <label for="commodity_image">Commodity Image</label>
            <input type="file" name="commodity_image" class="file-input">
            <?php if ($commodity['image_url']): ?>
                <p>Current Image: <img src="<?= htmlspecialchars($commodity['image_url']) ?>" alt="Commodity Image" style="max-width: 100px; height: auto; vertical-align: middle;"></p>
            <?php endif; ?>

            <div class="button-container">
                <button type="submit" class="update-btn">Update</button>
                <button type="button" class="cancel-btn" onclick="window.location.href='commodities.php'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Generic function to add a dynamic row
    function addDynamicRow(containerId, rowHtml) {
        const container = document.getElementById(containerId);
        const newGroup = document.createElement('div');
        newGroup.className = 'dynamic-group'; // Use a consistent class name
        newGroup.innerHTML = rowHtml;
        container.appendChild(newGroup);
        container.scrollTop = container.scrollHeight; // Scroll to new element
    }

    // Generic function to remove a dynamic row
    function removeDynamicGroup(button) {
        const group = button.closest('.dynamic-group');
        if (group) {
            group.remove();
        }
    }

    function addPackagingUnitRow() {
        const html = `
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
        addDynamicRow('packaging-unit-container', html);
    }

    function addAliasCountryRow() {
        const html = `
            <div>
                <label>Alias</label>
                <input type="text" name="commodity_alias[]" value="">
            </div>
            <div>
                <label>Country</label>
                <select name="country[]" required>
                    <option value="">Select country</option>
                    <option value="Rwanda">Rwanda</option>
                    <option value="Uganda">Uganda</option>
                    <option value="Tanzania">Tanzania</option>
                    <option value="Kenya">Kenya</option>
                    </select>
            </div>
            <button type="button" class="remove-btn" onclick="removeDynamicGroup(this)">×</button>
        `;
        addDynamicRow('alias-country-container', html);
    }
</script>
</body>
</html>