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
    header("Location: commodities_boilerplate.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Enumerator - Step 2</title>
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
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }
        
        /* Tags styling */
        .tag {
            display: inline-block;
            background: #e0e0e0;
            color: #333;
            padding: 6px 12px;
            border-radius: 20px;
            margin: 5px;
            font-size: 14px;
        }
        .tag span {
            margin-left: 8px;
            color: red;
            cursor: pointer;
        }
        .tags-container {
            margin-top: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            min-height: 50px;
            padding: 10px;
            border: 1px dashed #ccc;
            border-radius: 5px;
        }
        
        /* Button styling */
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 20px;
        }
        .prev-btn, .next-btn {
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
        <button class="close-btn" onclick="window.location.href='commodities_boilerplate.php'">×</button>
        
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
                    <div class="step-text">Step 2<br><small>Assign Tradepoints</small></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <h2>Add Enumerator - Step 2</h2>
            <p>Assign tradepoints that this enumerator will be responsible for.</p>
            
            <form method="POST">
                <div class="form-group-full">
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

                <div class="form-group-full">
                    <label>Selected Tradepoints:</label>
                    <div class="tags-container" id="selected-tradepoints"></div>
                </div>

                <div id="hidden-inputs"></div>

                <div class="button-container">
                    <button type="button" class="prev-btn" onclick="window.location.href='add_enumerator.php'">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="submit" class="next-btn">
                        Finish <i class="fas fa-check"></i>
                    </button>
                </div>
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
                tag.innerHTML = `${selectedText} <span onclick="removeTradepoint('${selectedId}')">×</span>`;
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

        function removeTradepoint(selectedId) {
            // Remove the tag
            const tags = document.querySelectorAll('.tag');
            tags.forEach(tag => {
                if (tag.textContent.includes(selectedTradepoints.get(selectedId).name)) {
                    tag.remove();
                }
            });
            
            // Remove hidden inputs
            const inputsToRemove = Array.from(hiddenInputs.querySelectorAll('input')).filter(input =>
                input.name.startsWith('tradepoints[' + selectedId + ']')
            );
            inputsToRemove.forEach(input => hiddenInputs.removeChild(input));
            
            // Remove from map
            selectedTradepoints.delete(selectedId);
        }

        // Add smooth transitions for better UX
        document.querySelectorAll('select').forEach(element => {
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