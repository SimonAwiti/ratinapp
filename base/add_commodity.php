<?php
include '../admin/includes/config.php'; // Include database configuration

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    // Store form data in session for use in the next step
    $_SESSION['category'] = $_POST['category'];
    $_SESSION['commodity_name'] = $_POST['commodity_name'];
    $_SESSION['variety'] = $_POST['variety'];

    // Store packaging and unit as arrays
    $_SESSION['packaging'] = $_POST['packaging'];
    $_SESSION['unit'] = $_POST['unit'];

    // Redirect to the next step
    header('Location: add_commodity2.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
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
            height: 700px; /* Fixed height */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative; /* Required for absolute positioning of the close button */
        }
        h2 {
            margin-bottom: 10px; /* Reduce gap below heading */
        }
        p {
            margin-bottom: 10px; /* Reduce gap below paragraph */
        }
        form label:first-of-type {
            margin-top: 10px; /* Ensure label isn't too close to the paragraph */
        }

        .form-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Ensure spacing between form elements */
            height: 100%;
        }
        .packaging-unit-container {
            flex-grow: 1;
            max-height: 200px; /* Reduce height slightly for better spacing */
            overflow-y: auto; 
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px; /* Ensure spacing between fields */
        }

        .packaging-unit-group {
            display: flex; /* Use flexbox to align items horizontally */
            gap: 10px; /* Add spacing between fields */
            margin-bottom: 15px;
            align-items: flex-end; /* Align fields at the bottom */
        }
        .packaging-unit-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .packaging-unit-group input,
        .packaging-unit-group select {
            flex: 1; /* Allow fields to grow and fill available space */
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
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
        }
        .add-more-btn:hover {
            background-color: #c4e6c4;
        }
        #variety {
            margin-bottom: 15px; /* Adjust spacing as needed */
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Close button on the top right -->
        <button class="close-btn" onclick="window.location.href='dashboard.php'">×</button>

        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 2</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Commodity</h2>
            <p>Provide the necessary details to add a new commodity</p>
            <form method="POST" action="add_commodity.php">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Oil seeds">Oil seeds</option>
                    <option value="Pulses">Pulses</option>
                    <option value="Cereals">Cereals</option>
                </select>
                <label for="commodity-name">Commodity name *</label>
                <input type="text" id="commodity-name" name="commodity_name" required>
                <label for="variety">Variety</label>
                <input type="text" id="variety" name="variety" required>

                <!-- Commodity Packaging and Unit (Dynamic Fields) -->
                <div id="packaging-unit-container" class="packaging-unit-container">
                    <!-- Default first packaging-unit group -->
                    <div class="packaging-unit-group">
                        <div style="flex: 1;">
                            <label>Commodity Packaging</label>
                            <input type="text" name="packaging[]" required>
                        </div>
                        <div style="flex: 1;">
                            <label for="unit">Measuring unit</label>
                            <select name="unit[]" required>
                                <option value="">Select unit</option>
                                <option value="Kg">Kg</option>
                                <option value="Tons">Tons</option>
                            </select>
                        </div>
                        <button type="button" class="remove-btn" onclick="removePackagingUnit(this)">×</button>
                    </div>

                    <!-- Default second packaging-unit group -->
                    <div class="packaging-unit-group">
                        <div style="flex: 1;">
                            <label>Commodity Packaging</label>
                            <input type="text" name="packaging[]" required>
                        </div>
                        <div style="flex: 1;">
                            <label for="unit">Measuring unit</label>
                            <select name="unit[]" required>
                                <option value="">Select unit</option>
                                <option value="Kg">Kg</option>
                                <option value="Tons">Tons</option>
                            </select>
                        </div>
                        <button type="button" class="remove-btn" onclick="removePackagingUnit(this)">×</button>
                    </div>
                </div>

                <!-- Button to Add More Packaging and Unit Fields -->
                <button type="button" class="add-more-btn" onclick="addMorePackagingUnit()">Add More Packaging & Unit</button>

                <button type="submit" class="next-btn">Next &rarr;</button>
            </form>
        </div>
    </div>

    <script>
        // Function to add more packaging and unit fields
        function addMorePackagingUnit() {
            const container = document.getElementById('packaging-unit-container');
            const newGroup = document.createElement('div');
            newGroup.className = 'packaging-unit-group';
            newGroup.innerHTML = `
                <div style="flex: 1;">
                    <label>Commodity Packaging</label>
                    <input type="text" name="packaging[]" required>
                </div>
                <div style="flex: 1;">
                    <label for="unit">Measuring unit</label>
                    <select name="unit[]" required>
                        <option value="">Select unit</option>
                        <option value="Kg">Kg</option>
                        <option value="Tons">Tons</option>
                    </select>
                </div>
                <button type="button" class="remove-btn" onclick="removePackagingUnit(this)">×</button>
            `;
            container.appendChild(newGroup);

            // Automatically scroll to the newly added fields
            container.scrollTop = container.scrollHeight;
        }

        // Function to remove a packaging-unit group
        function removePackagingUnit(button) {
            const group = button.closest('.packaging-unit-group');
            if (group) {
                group.remove();
            }
        }
    </script>
</body>
</html>