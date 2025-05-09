<?php
session_start();
include '../admin/includes/config.php'; // DB connection

// Redirect if required session values are not set
if (!isset($_SESSION['email'])) {
    header("Location: add_enumerator.php");
    exit;
}

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_SESSION['name'];
    $email = $_SESSION['email'];
    $phone = $_SESSION['phone'];
    $gender = $_SESSION['gender'];
    $country = $_SESSION['country'];
    $county_district = $_SESSION['county_district'];
    $username = $_SESSION['username'];
    $password = $_SESSION['password'];

    $tradepoints_data = $_POST['tradepoints'] ?? []; // This will now contain an array of objects

    // Insert enumerator data, including tradepoints as JSON
    $stmt = $con->prepare("INSERT INTO enumerators 
    (name, email, phone, gender, country, county_district, username, password, tradepoints) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $tradepoints_json = json_encode($tradepoints_data);
    $stmt->bind_param("sssssssss", $name, $email, $phone, $gender, $country, $county_district, $username, $password, $tradepoints_json);

    $stmt->execute();

    session_unset();
    session_destroy();
    header("Location: dashboard.php");
    exit;
}

// Fetch tradepoints
$tradepoints = [];
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
        JOIN millers m ON md.miller_name = m.miller_name  -- Join using miller_name
        WHERE md.miller_name IS NOT NULL AND m.miller_name IS NOT NULL
        ORDER BY name ASC";

$result = $con->query($sql);
while ($row = $result->fetch_assoc()) {
    $tradepoints[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Tradepoints</title>
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
            padding: 60px;
            border-radius: 8px;
            width: 800px;
            height: 700px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative;
        }
        h2 { margin-bottom: 20px; }
        select, .tag, button { font-size: 16px; }
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
        }
        .form-row .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
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
        button:hover { background-color: #a45c40; }
        .steps {
            padding-right: 40px;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            height: calc(100% - 45px - 385px);
            width: 1px;
            background-color: #a45c40;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 250px;
            position: relative;
        }
        .step:last-child { margin-bottom: 0; }
        .step-circle {
            width: 45px;
            height: 45px;
            border-radius: 70%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 20px;
            font-size: 20px;
            z-index: 1;
            background-color: #d3d3d3;
            color: white;
            position: relative;
        }
        .step-circle::before {
            content: '✓';
            display: none;
        }
        .step-circle.active::before { display: block; }
        .step-circle.inactive::before { content: ''; }
        .step-circle.active { background-color: #a45c40; }
        .form-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 12px;
        }
        .form-row {
            display: flex;
            gap: 40px;
            margin-bottom: 15px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
        }

    </style>
</head>
<body>
<div class="container">
    <div class="steps">
        <div class="step">
            <div class="step-circle active"></div>
            <span>Step 1</span>
        </div>
        <div class="step">
            <div class="step-circle active"></div>
            <span>Step 2</span>
        </div>
    </div>
    <div class="form-content">
        <h2>Assign Tradepoints</h2>
        <form method="POST">
            <div class="form-group">
                <label for="tradepoint-select">Select Tradepoint(s):</label>
                <select id="tradepoint-select">
                    <option value="">-- Select Tradepoint --</option>
                    <?php foreach ($tradepoints as $tp): ?>
                        <option
                            value="<?= $tp['id'] ?>"
                            data-type="<?= $tp['tradepoint_type'] ?>"
                            data-longitude="<?= $tp['longitude'] ?>"
                            data-latitude="<?= $tp['latitude'] ?>"
                            data-radius="<?= $tp['radius'] ?>"
                        >
                            <?= htmlspecialchars("{$tp['name']} - {$tp['tradepoint_type']} ({$tp['admin1']}, {$tp['admin0']})") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tags-container" id="selected-tradepoints"></div>

            <div id="hidden-inputs"></div>

            <button type="submit">Finish</button>
        </form>
    </div>
</div>

<script>
    const select = document.getElementById('tradepoint-select');
    const selectedContainer = document.getElementById('selected-tradepoints');
    const hiddenInputs = document.getElementById('hidden-inputs');
    const selectedTradepoints = new Map();

    select.addEventListener('change', () => {
        const selectedId = select.value;
        const selectedText = select.options[select.selectedIndex].text;
        const selectedType = select.options[select.selectedIndex].getAttribute('data-type');
        const longitude = select.options[select.selectedIndex].getAttribute('data-longitude');
        const latitude = select.options[select.selectedIndex].getAttribute('data-latitude');
        const radius = select.options[select.selectedIndex].getAttribute('data-radius');


        if (selectedId && !selectedTradepoints.has(selectedId)) {
            selectedTradepoints.set(selectedId, {
                id: selectedId,
                type: selectedType,
                name: selectedText,
                longitude: longitude,
                latitude: latitude,
                radius: radius
            });

            // Create tag
            const tag = document.createElement('div');
            tag.className = 'tag';
            tag.textContent = selectedText;

            const close = document.createElement('span');
            close.textContent = '×';
            close.onclick = () => {
                selectedContainer.removeChild(tag);
                removeHiddenInputs(selectedId);
                selectedTradepoints.delete(selectedId);
            };

            tag.appendChild(close);
            selectedContainer.appendChild(tag);

            // Create hidden inputs for all tradepoint details
            createHiddenInputs(selectedId);
        }

        select.value = "";
    });

    function createHiddenInputs(selectedId) {
        const tradepoint = selectedTradepoints.get(selectedId);

        const hiddenInputId = document.createElement('input');
        hiddenInputId.type = 'hidden';
        hiddenInputId.name = 'tradepoints[' + selectedId + '][id]';
        hiddenInputId.value = tradepoint.id;
        hiddenInputs.appendChild(hiddenInputId);

        const hiddenInputType = document.createElement('input');
        hiddenInputType.type = 'hidden';
        hiddenInputType.name = 'tradepoints[' + selectedId + '][type]';
        hiddenInputType.value = tradepoint.type;
        hiddenInputs.appendChild(hiddenInputType);

        const hiddenInputLongitude = document.createElement('input');
        hiddenInputLongitude.type = 'hidden';
        hiddenInputLongitude.name = 'tradepoints[' + selectedId + '][longitude]';
        hiddenInputLongitude.value = tradepoint.longitude;
        hiddenInputs.appendChild(hiddenInputLongitude);

        const hiddenInputLatitude = document.createElement('input');
        hiddenInputLatitude.type = 'hidden';
        hiddenInputLatitude.name = 'tradepoints[' + selectedId + '][latitude]';
        hiddenInputLatitude.value = tradepoint.latitude;
        hiddenInputs.appendChild(hiddenInputLatitude);

         const hiddenInputRadius = document.createElement('input');
        hiddenInputRadius.type = 'hidden';
        hiddenInputRadius.name = 'tradepoints[' + selectedId + '][radius]';
        hiddenInputRadius.value = tradepoint.radius;
        hiddenInputs.appendChild(hiddenInputRadius);
    }

    function removeHiddenInputs(selectedId) {
        const inputsToRemove = Array.from(hiddenInputs.querySelectorAll('input')).filter(input =>
            input.name.startsWith('tradepoints[' + selectedId + ']')
        );
        inputsToRemove.forEach(input => hiddenInputs.removeChild(input));
    }
</script>
</body>
</html>
