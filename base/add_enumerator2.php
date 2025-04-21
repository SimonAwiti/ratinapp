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

    $tradepoint_ids = $_POST['tradepoints_ids'] ?? [];
    $tradepoint_types = $_POST['tradepoints_types'] ?? [];

    // Insert enumerator data
    $stmt = $con->prepare("INSERT INTO enumerators (name, email, phone, gender, country, county_district, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $name, $email, $phone, $gender, $country, $county_district, $username, $password);
    $stmt->execute();
    $enumerator_id = $stmt->insert_id;

    // Insert tradepoint assignments
    $tp_stmt = $con->prepare("INSERT INTO enumerator_tradepoints (enumerator_id, tradepoint_id, tradepoint_type) VALUES (?, ?, ?)");
    for ($i = 0; $i < count($tradepoint_ids); $i++) {
        $tp_id = $tradepoint_ids[$i];
        $tp_type = $tradepoint_types[$i];
        $tp_stmt->bind_param("iis", $enumerator_id, $tp_id, $tp_type);
        $tp_stmt->execute();
    }

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
            county_district AS admin1
        FROM markets
        UNION ALL
        SELECT 
            id, 
            name AS name, 
            'Border Points' AS tradepoint_type, 
            country AS admin0, 
            county AS admin1
        FROM border_points
        UNION ALL
        SELECT 
            id, 
            miller_name AS name, 
            'Miller' AS tradepoint_type,  
            country AS admin0, 
            county_district AS admin1
        FROM miller_details
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
            background: #f8f8f8;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 60px;
            border-radius: 8px;
            width: 850px;
            height: 600px;
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
        .form-group { margin-bottom: 20px; }
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
            height: calc(100% - 45px - 305px);
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
                            data-type="<?= $tp['tradepoint_type'] ?>">
                            <?= htmlspecialchars("{$tp['name']} - {$tp['tradepoint_type']} ({$tp['admin1']}, {$tp['admin0']})") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tags-container" id="selected-tradepoints"></div>

            <!-- Hidden inputs will be appended here -->
            <div id="hidden-inputs"></div>

            <button type="submit">Finish</button>
        </form>
    </div>
</div>

<script>
    const select = document.getElementById('tradepoint-select');
    const selectedContainer = document.getElementById('selected-tradepoints');
    const hiddenInputs = document.getElementById('hidden-inputs');
    const selectedIds = new Set();

    select.addEventListener('change', () => {
        const selectedId = select.value;
        const selectedText = select.options[select.selectedIndex].text;
        const selectedType = select.options[select.selectedIndex].getAttribute('data-type');

        if (selectedId && !selectedIds.has(selectedId)) {
            selectedIds.add(selectedId);

            // Create tag
            const tag = document.createElement('div');
            tag.className = 'tag';
            tag.textContent = selectedText;

            const close = document.createElement('span');
            close.textContent = '×';
            close.onclick = () => {
                selectedContainer.removeChild(tag);
                hiddenInputs.removeChild(hiddenInputId);
                hiddenInputs.removeChild(hiddenInputType);
                selectedIds.delete(selectedId);
            };

            tag.appendChild(close);
            selectedContainer.appendChild(tag);

            // Hidden input for ID
            const hiddenInputId = document.createElement('input');
            hiddenInputId.type = 'hidden';
            hiddenInputId.name = 'tradepoints_ids[]';
            hiddenInputId.value = selectedId;
            hiddenInputs.appendChild(hiddenInputId);

            // Hidden input for Type
            const hiddenInputType = document.createElement('input');
            hiddenInputType.type = 'hidden';
            hiddenInputType.name = 'tradepoints_types[]';
            hiddenInputType.value = selectedType;
            hiddenInputs.appendChild(hiddenInputType);
        }

        select.value = "";
    });
</script>
</body>
</html>
