<?php
session_start();
include '../admin/includes/config.php'; // DB connection

if (!isset($_SESSION['email'])) {
    header("Location: add_enumerator.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_SESSION['name'];
    $email = $_SESSION['email'];
    $phone = $_SESSION['phone'];
    $gender = $_SESSION['gender'];
    $country = $_SESSION['country'];
    $county_district = $_SESSION['county_district'];
    $username = $_SESSION['username'];
    $password = $_SESSION['password'];

    $tradepoints = $_POST['tradepoints'];

    $stmt = $con->prepare("INSERT INTO enumerators (name, email, phone, gender, country, county_district, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $name, $email, $phone, $gender, $country, $county_district, $username, $password);
    $stmt->execute();
    $enumerator_id = $stmt->insert_id;

    $tp_stmt = $con->prepare("INSERT INTO enumerator_tradepoints (enumerator_id, tradepoint_id) VALUES (?, ?)");
    foreach ($tradepoints as $tp_id) {
        $tp_stmt->bind_param("ii", $enumerator_id, $tp_id);
        $tp_stmt->execute();
    }

    session_unset();
    session_destroy();

    echo "<script>alert('Enumerator and assigned tradepoints saved successfully!'); window.location.href='sidebar.php';</script>";
    exit;
}

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
    <title>Select Millers</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
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
            width: 850px;
            height: 600px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative;
        }
        h2 {
            margin-bottom: 25px;
            text-align: center;
            color: #333;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
        }
        select {
            width: 100%;
        }
        .selected-tags {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f8f8f8;
            min-height: 40px;
        }
        .selected-tags span {
            display: inline-block;
            margin-right: 8px;
            background-color: #a45c40;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        .selected-tags span:hover {
            background-color: #8a4933;
        }
        .selected-tags span .remove-tag {
            margin-left: 10px;
            font-weight: bold;
            color: #fff;
            cursor: pointer;
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
        button:hover {
            background-color: #8a4933;
        }
        .steps {
            padding-right: 40px;
            margin-right: 20px;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px;
            top: 45px;
            height: calc(100% - 45px - 10px);
            width: 1px;
            background-color: #a45c40;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 250px;
            position: relative;
        }
        .step:last-child {
            margin-bottom: 0;
        }
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
        .step-circle.active::before {
            display: block;
        }
        .step-circle.active {
            background-color: #a45c40;
        }
        .step-circle.inactive::before {
            content: '';
        }
        .step-circle.inactive {
            background-color: #ccc;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 1px; /* Positioned on the top right */
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #a45c40;
        }
        .close-btn:hover {
            background: #8a4933;
        }
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 100px;
        }

        .button-group button {
            width: 250px; /* Increased width here */
            padding: 12px 20px;
            font-size: 16px;
        }

        .form-content {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">


        <!-- Steps -->
        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 2</span>
            </div>
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 3</span>
            </div>
        </div>

        <div class="form-content">
        <h2>Select Millers for <em><?= htmlspecialchars($_SESSION['miller_name']) ?></em></h2>

        <form method="POST">
            <label for="selected_millers">Select Millers:</label>
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
            <div class="selected-tags" id="selected-tags-container"></div>

            <div class="button-group">
                <button type="button" onclick="window.location.href='add_miller2.php'">&larr; Back</button>
                <button type="submit" name="submit_millers">Finish</button>
            </div>
        </form>
        </div>
    </div>

    <!-- Select2 Scripts -->
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
                            .append('<span class="remove-tag">x</span>')
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
                }
            }
        });
    </script>
</body>
</html>
