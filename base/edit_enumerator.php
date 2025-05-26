<?php
session_start();
include '../admin/includes/config.php'; // DB connection

// Ensure an enumerator ID is provided for editing
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect back to the enumerators list if no valid ID is provided
    header("Location: enumerator_boilerplate.php");
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
    header("Location: enumerator_boilerplate.php");
    exit;
}

// If no enumerator found with that ID, redirect
if (!$enumerator_data) {
    $_SESSION['error_message'] = "Enumerator not found.";
    header("Location: enumerator_boilerplate.php");
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
        case 'Miller':
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
            header("Location: sidebar.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Error updating enumerator: " . $stmt->error;
            error_log("Error updating enumerator: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error preparing update statement: " . $con->error;
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
    <style>
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
            padding: 40px; /* Reduced padding */
            border-radius: 8px;
            width: 800px;
            /* height: auto; Adjust height dynamically */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column; /* Changed to column for simpler layout */
            gap: 20px;
        }
        h2 { margin-bottom: 20px; text-align: center; }
        .form-section {
            display: flex;
            gap: 40px;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .form-group {
            flex: 1; /* Distribute space evenly */
            min-width: 300px; /* Minimum width for each group */
            display: flex;
            flex-direction: column;
            margin-bottom: 15px; /* Spacing between groups */
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box; /* Include padding in width */
        }
        .tag {
            display: inline-block;
            background: #e0e0e0;
            color: #333;
            padding: 6px 10px;
            border-radius: 20px;
            margin: 5px;
            cursor: default;
        }
        .tag span {
            margin-left: 8px;
            color: red;
            cursor: pointer;
        }
        .tags-container {
            margin-top: 15px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 5px;
            min-height: 50px; /* Ensure visibility even if empty */
        }
        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background-color: #a45c40;
            border: none;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover { background-color: #8c4a30; } /* Darker hover */
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Enumerator: <?= htmlspecialchars($enumerator_data['name']) ?></h2>
    <form method="POST">
        <input type="hidden" name="enumerator_id" value="<?= htmlspecialchars($enumerator_id) ?>">

        <div class="form-section">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($enumerator_data['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($enumerator_data['email']) ?>" required>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($enumerator_data['phone']) ?>" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender:</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?= ($enumerator_data['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($enumerator_data['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($enumerator_data['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="country">Country:</label>
                <input type="text" id="country" name="country" value="<?= htmlspecialchars($enumerator_data['country']) ?>" required>
            </div>
            <div class="form-group">
                <label for="county_district">County/District:</label>
                <input type="text" id="county_district" name="county_district" value="<?= htmlspecialchars($enumerator_data['county_district']) ?>" required>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($enumerator_data['username']) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">New Password (leave blank to keep current):</label>
                <input type="password" id="password" name="password">
            </div>
        </div>

        <div class="form-group">
            <label for="tradepoint-select">Assign Tradepoint(s):</label>
            <select id="tradepoint-select">
                <option value="">-- Select Tradepoint --</option>
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

        <div class="tags-container" id="selected-tradepoints"></div>

        <div id="hidden-inputs"></div>

        <button type="submit">Update Enumerator</button>
    </form>
</div>

<script>
    const select = document.getElementById('tradepoint-select');
    const selectedContainer = document.getElementById('selected-tradepoints');
    const hiddenInputs = document.getElementById('hidden-inputs');
    const selectedTradepoints = new Map(); // Stores tradepoint objects {id, type, name, longitude, latitude, radius}

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


    select.addEventListener('change', () => {
        const selectedOption = select.options[select.selectedIndex];
        const selectedId = selectedOption.value;

        if (selectedId && !selectedTradepoints.has(selectedId)) {
            const selectedType = selectedOption.getAttribute('data-type');
            const selectedName = selectedOption.getAttribute('data-name'); // Get the actual name from data attribute
            const longitude = selectedOption.getAttribute('data-longitude');
            const latitude = selectedOption.getAttribute('data-latitude');
            const radius = selectedOption.getAttribute('data-radius');

            // Store the full details
            selectedTradepoints.set(selectedId, {
                id: selectedId,
                type: selectedType,
                name: selectedName, // Store actual name
                longitude: longitude,
                latitude: latitude,
                radius: radius
            });

            // Create tag with the actual name
            createTag(selectedId, selectedName + ' (' + selectedType + ')');

            // Create hidden inputs for all tradepoint details
            createHiddenInputs(selectedId);
        }

        select.value = ""; // Reset select
    });

    function createTag(id, displayText) {
        const tag = document.createElement('div');
        tag.className = 'tag';
        tag.textContent = displayText; // Use the formatted text for display

        const close = document.createElement('span');
        close.textContent = '×';
        close.onclick = () => {
            selectedContainer.removeChild(tag);
            removeHiddenInputs(id);
            selectedTradepoints.delete(id);
        };

        tag.appendChild(close);
        selectedContainer.appendChild(tag);
    }

    function createHiddenInputs(selectedId) {
        const tradepoint = selectedTradepoints.get(selectedId);

        // All hidden inputs for a given tradepoint should be grouped or named uniquely
        // We'll use a prefix to identify them later for removal
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
        // Select all inputs with the specific data-tp-id attribute
        const inputsToRemove = hiddenInputs.querySelectorAll('[data-tp-id="tradepoints_' + selectedId + '"]');
        inputsToRemove.forEach(input => hiddenInputs.removeChild(input));
    }
</script>
</body>
</html>