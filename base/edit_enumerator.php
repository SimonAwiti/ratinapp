<?php
session_start();
include '../admin/includes/config.php'; // DB connection

// Ensure an enumerator ID is provided for editing
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect back to the enumerators list if no valid ID is provided
    header("Location: commodities_boilerplate.php");
    exit;
}

$enumerator_id = intval($_GET['id']);
$enumerator_data = null; // Will store the enumerator's existing data

// --- 1. Fetch existing enumerator data ---
$stmt = $con->prepare("SELECT id, name, email, phone, gender, country, county_district, username, password, tradepoints FROM enumerators WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $enumerator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $enumerator_data = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    error_log("Error preparing enumerator fetch statement: " . $con->error);
    $_SESSION['error_message'] = "Error fetching enumerator data.";
    header("Location: commodities_boilerplate.php");
    exit;
}

// If no enumerator found with that ID, redirect
if (!$enumerator_data) {
    $_SESSION['error_message'] = "Enumerator not found.";
    header("Location: commodities_boilerplate.php");
    exit;
}

// Parse the JSON tradepoints for pre-selection
$initial_tradepoints = [];
if (!empty($enumerator_data['tradepoints'])) {
    $decoded_tradepoints = json_decode($enumerator_data['tradepoints'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tradepoints)) {
        foreach ($decoded_tradepoints as $tp_id => $tp_details) {
            // Ensure necessary keys exist in the JSON
            if (isset($tp_details['id']) && isset($tp_details['type']) && isset($tp_details['longitude']) && isset($tp_details['latitude']) && isset($tp_details['radius'])) {
                $initial_tradepoints[$tp_id] = [
                    'id' => $tp_details['id'],
                    'type' => $tp_details['type'],
                    'longitude' => $tp_details['longitude'],
                    'latitude' => $tp_details['latitude'],
                    'radius' => $tp_details['radius']
                ];
                // For display in the tag, we also need the actual name
                // Call getTradepointName to get the name, but store it as a display name
                // as the JSON itself doesn't contain the actual names.
                $initial_tradepoints[$tp_id]['name'] = getTradepointName($con, $tp_details['id'], $tp_details['type']);

            }
        }
    }
}

// Function to fetch the actual name of a tradepoint based on ID and type
// (Copy this function from your enumerator_boilerplate.php to edit_enumerator.php)
function getTradepointName($con, $id, $type) {
    $tableName = '';
    $nameColumn = '';

    switch ($type) {
        case 'Market':
        case 'Markets':
            $tableName = 'markets';
            $nameColumn = 'market_name';
            break;
        case 'Border Point':
        case 'Border Points':
            $tableName = 'border_points';
            $nameColumn = 'name';
            break;
        case 'Miller':
        case 'Miller': // Note: The 'Miller' case had 'Miller' twice, kept for consistency with original.
            $tableName = 'miller_details';
            $nameColumn = 'miller_name';
            break;
        default:
            return "Unknown Type: " . htmlspecialchars($type);
    }

    if (!empty($tableName) && !empty($nameColumn)) {
        $stmt = $con->prepare("SELECT " . $nameColumn . " FROM " . $tableName . " WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row[$nameColumn];
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for tradepoint name lookup: " . $con->error);
        }
    }
    return "ID: " . htmlspecialchars($id) . " (Name Not Found)";
}

// --- 2. Handle form submission (UPDATE operation) ---
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get updated data from the form
    $name = $_POST['name'] ?? $enumerator_data['name'];
    $email = $_POST['email'] ?? $enumerator_data['email'];
    $phone = $_POST['phone'] ?? $enumerator_data['phone'];
    $gender = $_POST['gender'] ?? $enumerator_data['gender'];
    $country = $_POST['country'] ?? $enumerator_data['country'];
    $county_district = $_POST['county_district'] ?? $enumerator_data['county_district'];
    $username = $_POST['username'] ?? $enumerator_data['username'];

    // --- Start of Password Fix ---
    // Always start with the existing password hash from the database
    $password_hash = $enumerator_data['password'];

    // Only hash and update if a new password is provided in the form field AND it's not an empty string
    if (isset($_POST['password']) && $_POST['password'] !== '') {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
    // --- End of Password Fix ---

    $tradepoints_data = $_POST['tradepoints'] ?? [];
    $tradepoints_json = json_encode($tradepoints_data);

    // Prepare the UPDATE statement
    $stmt = $con->prepare("UPDATE enumerators SET name = ?, email = ?, phone = ?, gender = ?, country = ?, county_district = ?, username = ?, password = ?, tradepoints = ? WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param("sssssssssi", $name, $email, $phone, $gender, $country, $county_district, $username, $password_hash, $tradepoints_json, $enumerator_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Enumerator updated successfully!";
            header("Location: commodities_boilerplate.php");
            exit;
        } else {
            $error_message = "Error updating enumerator: " . $stmt->error;
            error_log("Error updating enumerator: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error_message = "Error preparing update statement: " . $con->error;
        error_log("Error preparing update statement: " . $con->error);
    }
}


// --- 3. Fetch all tradepoints for the select dropdown ---
$all_tradepoints_for_select = [];
$sql = "SELECT
            id,
            market_name AS name,
            'Markets' AS tradepoint_type,
            country AS admin0,
            county_district AS admin1,
            longitude,
            latitude,
            radius
        FROM markets
        UNION ALL
        SELECT
            id,
            name AS name,
            'Border Points' AS tradepoint_type,
            country AS admin0,
            county AS admin1,
            longitude,
            latitude,
            radius
        FROM border_points
        UNION ALL
        SELECT
            md.id,
            md.miller_name AS name,
            'Miller' AS tradepoint_type,
            md.country AS admin0,
            md.county_district AS admin1,
            m.longitude,
            m.latitude,
            m.radius
        FROM miller_details md
        JOIN millers m ON md.miller_name = m.miller_name
        WHERE md.miller_name IS NOT NULL AND m.miller_name IS NOT NULL
        ORDER BY name ASC";

$result = $con->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_tradepoints_for_select[] = $row;
    }
} else {
    error_log("Error fetching all tradepoints: " . $con->error);
}

// Close connection
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Enumerator: <?= htmlspecialchars($enumerator_data['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Include Select2 CSS -->
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
        
        /* Current info section */
        .miller-info { /* Re-using miller-info for general info display */
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

        /* Tradepoint selection styles (adapted from miller selection) */
        .miller-limit-info { /* Re-using this class for general info */
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
        
        .selected-tags { /* Used for tradepoint tags */
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
        
        .limit-warning { /* Re-using for general warnings, currently hidden */
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
        
        /* Select2 Custom Styling */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 10px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: rgba(180, 80, 50, 0.8);
            color: white;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: rgba(180, 80, 50, 1);
            color: white;
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

        /* Password Toggle Specific Styles */
        .password-container {
            position: relative;
            width: 100%;
        }
        .password-container input[type="password"],
        .password-container input[type="text"] {
            padding-right: 40px; /* Make space for the icon */
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
        }
        .form-group .password-container {
            margin-bottom: 15px; /* Adjust margin for this container */
        }
    </style>
</head>
<body>
<div class="container">
    <button class="close-btn" onclick="window.location.href='../base/commodities_boilerplate.php'">×</button>
    
    <h2>Edit Enumerator: <?= htmlspecialchars($enumerator_data['name']) ?></h2>
    <p>Update the enumerator's details below</p>

    <div class="miller-info">
        <h5>Current Enumerator Information</h5>
        <p><strong>Name:</strong> <?= htmlspecialchars($enumerator_data['name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($enumerator_data['email']) ?></p>
        <p><strong>Phone:</strong> <?= htmlspecialchars($enumerator_data['phone']) ?></p>
        <p><strong>Gender:</strong> <?= htmlspecialchars($enumerator_data['gender']) ?></p>
        <p><strong>Country:</strong> <?= htmlspecialchars($enumerator_data['country']) ?></p>
        <p><strong>County/District:</strong> <?= htmlspecialchars($enumerator_data['county_district']) ?></p>
        <p><strong>Username:</strong> <?= htmlspecialchars($enumerator_data['username']) ?></p>
        <p><strong>Assigned Tradepoints:</strong> 
            <?php 
            if (!empty($initial_tradepoints)) {
                $tp_names = array_column($initial_tradepoints, 'name');
                echo htmlspecialchars(implode(', ', $tp_names));
            } else {
                echo 'None';
            }
            ?>
        </p>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="enumerator_id" value="<?= htmlspecialchars($enumerator_id) ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="name" class="required">Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($enumerator_data['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email" class="required">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($enumerator_data['email']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone" class="required">Phone:</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($enumerator_data['phone']) ?>" required>
            </div>
            <div class="form-group">
                <label for="gender" class="required">Gender:</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?= ($enumerator_data['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($enumerator_data['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($enumerator_data['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="country" class="required">Country:</label>
                <input type="text" id="country" name="country" value="<?= htmlspecialchars($enumerator_data['country']) ?>" required>
            </div>
            <div class="form-group">
                <label for="county_district" class="required">County/District:</label>
                <input type="text" id="county_district" name="county_district" value="<?= htmlspecialchars($enumerator_data['county_district']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="username" class="required">Username:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($enumerator_data['username']) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">New Password (leave blank to keep current):</label>
                <div class="password-container">
                    <input type="password" id="password" name="password">
                    <span class="toggle-password" onclick="togglePasswordVisibility()">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="form-group-full">
            <label for="tradepoint-select">Assign Tradepoint(s):</label>
            <select id="tradepoint-select" class="form-control" multiple="multiple" style="width: 100%;">
                <?php foreach ($all_tradepoints_for_select as $tp): ?>
                    <option
                        value="<?= $tp['id'] ?>"
                        data-type="<?= $tp['tradepoint_type'] ?>"
                        data-longitude="<?= $tp['longitude'] ?>"
                        data-latitude="<?= $tp['latitude'] ?>"
                        data-radius="<?= $tp['radius'] ?>"
                        data-name="<?= htmlspecialchars($tp['name']) ?>" >
                        <?= htmlspecialchars("{$tp['name']} - {$tp['tradepoint_type']} ({$tp['admin1']}, {$tp['admin0']})") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="selected-tags" id="selected-tradepoints"></div>

        <div id="hidden-inputs"></div>

        <button type="submit" class="update-btn">Update Enumerator</button>
    </form>
</div>

<!-- Include jQuery and Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Initialize Select2
    $(document).ready(function() {
        $('#tradepoint-select').select2({
            placeholder: "Search and select tradepoints...",
            allowClear: true,
            width: 'resolve'
        });
    });

    const select = document.getElementById('tradepoint-select');
    const selectedContainer = document.getElementById('selected-tradepoints');
    const hiddenInputs = document.getElementById('hidden-inputs');
    const selectedTradepoints = new Map(); // Stores tradepoint objects {id, type, name, longitude, latitude, radius}

    // --- Password Toggle Functionality ---
    function togglePasswordVisibility() {
        const passwordField = document.getElementById('password');
        const toggleIcon = document.querySelector('.toggle-password i');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // --- Pre-populate tradepoints from existing data ---
    const initialTradepointsData = <?= json_encode($initial_tradepoints); ?>;

    function initializeTradepoints() {
        for (const id in initialTradepointsData) {
            const tp = initialTradepointsData[id];
            // Add to map
            selectedTradepoints.set(tp.id, {
                id: tp.id,
                type: tp.type,
                name: tp.name, // Use the name fetched by PHP
                longitude: tp.longitude,
                latitude: tp.latitude,
                radius: tp.radius
            });
            // Create tag
            createTag(tp.id, tp.name + ' (' + tp.type + ')'); // Display name and type
            // Create hidden inputs
            createHiddenInputs(tp.id);
        }
    }

    // Call initialization function on page load
    document.addEventListener('DOMContentLoaded', initializeTradepoints);

    // Update the event listener for Select2
    $('#tradepoint-select').on('change', function() {
        const selectedOptions = $(this).select2('data');
        
        selectedOptions.forEach(function(selectedOption) {
            const selectedId = selectedOption.id;
            
            if (selectedId && !selectedTradepoints.has(selectedId)) {
                const selectedType = selectedOption.element.getAttribute('data-type');
                const selectedName = selectedOption.element.getAttribute('data-name');
                const longitude = selectedOption.element.getAttribute('data-longitude');
                const latitude = selectedOption.element.getAttribute('data-latitude');
                const radius = selectedOption.element.getAttribute('data-radius');

                // Store the full details
                selectedTradepoints.set(selectedId, {
                    id: selectedId,
                    type: selectedType,
                    name: selectedName,
                    longitude: longitude,
                    latitude: latitude,
                    radius: radius
                });

                // Create tag with the actual name
                createTag(selectedId, selectedName + ' (' + selectedType + ')');

                // Create hidden inputs for all tradepoint details
                createHiddenInputs(selectedId);
            }
        });

        // Clear the selection after adding
        $(this).val(null).trigger('change');
    });

    function createTag(id, displayText) {
        const tag = document.createElement('span');
        tag.className = 'tag';
        tag.innerHTML = displayText + ' <span class="remove-tag">×</span>';

        tag.querySelector('.remove-tag').onclick = () => {
            selectedContainer.removeChild(tag);
            removeHiddenInputs(id);
            selectedTradepoints.delete(id);
        };

        selectedContainer.appendChild(tag);
    }

    function createHiddenInputs(selectedId) {
        const tradepoint = selectedTradepoints.get(selectedId);

        const prefix = 'tradepoints_' + selectedId;

        const hiddenInputId = document.createElement('input');
        hiddenInputId.type = 'hidden';
        hiddenInputId.name = 'tradepoints[' + selectedId + '][id]';
        hiddenInputId.value = tradepoint.id;
        hiddenInputId.setAttribute('data-tp-id', prefix);
        hiddenInputs.appendChild(hiddenInputId);

        const hiddenInputType = document.createElement('input');
        hiddenInputType.type = 'hidden';
        hiddenInputType.name = 'tradepoints[' + selectedId + '][type]';
        hiddenInputType.value = tradepoint.type;
        hiddenInputType.setAttribute('data-tp-id', prefix);
        hiddenInputs.appendChild(hiddenInputType);

        const hiddenInputLongitude = document.createElement('input');
        hiddenInputLongitude.type = 'hidden';
        hiddenInputLongitude.name = 'tradepoints[' + selectedId + '][longitude]';
        hiddenInputLongitude.value = tradepoint.longitude;
        hiddenInputLongitude.setAttribute('data-tp-id', prefix);
        hiddenInputs.appendChild(hiddenInputLongitude);

        const hiddenInputLatitude = document.createElement('input');
        hiddenInputLatitude.type = 'hidden';
        hiddenInputLatitude.name = 'tradepoints[' + selectedId + '][latitude]';
        hiddenInputLatitude.value = tradepoint.latitude;
        hiddenInputLatitude.setAttribute('data-tp-id', prefix);
        hiddenInputs.appendChild(hiddenInputLatitude);

        const hiddenInputRadius = document.createElement('input');
        hiddenInputRadius.type = 'hidden';
        hiddenInputRadius.name = 'tradepoints[' + selectedId + '][radius]';
        hiddenInputRadius.value = tradepoint.radius;
        hiddenInputRadius.setAttribute('data-tp-id', prefix);
        hiddenInputs.appendChild(hiddenInputRadius);
    }

    function removeHiddenInputs(selectedId) {
        const inputsToRemove = hiddenInputs.querySelectorAll('[data-tp-id="tradepoints_' + selectedId + '"]');
        inputsToRemove.forEach(input => hiddenInputs.removeChild(input));
    }
</script>
</body>
</html>