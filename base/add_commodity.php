<?php
session_start();
include '../admin/includes/config.php'; // DB connection

// Initialize variables to hold session data if it exists (for pre-filling form on back navigation)
$session_category = $_SESSION['category'] ?? '';
$session_commodity_name = $_SESSION['commodity_name'] ?? '';
$session_variety = $_SESSION['variety'] ?? '';
$session_packaging = $_SESSION['packaging'] ?? [];
$session_unit = $_SESSION['unit'] ?? [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['category'] = $_POST['category'] ?? '';
    $_SESSION['commodity_name'] = $_POST['commodity_name'] ?? '';
    $_SESSION['variety'] = $_POST['variety'] ?? '';
    $_SESSION['packaging'] = $_POST['packaging'] ?? [];
    $_SESSION['unit'] = $_POST['unit'] ?? [];

    header('Location: add_commodity2.php');
    exit;
}

// Fetch categories from DB
$categories = [];
$sql = "SELECT id, name FROM commodity_categories ORDER BY name ASC";
$result = mysqli_query($con, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}
mysqli_close($con); // Close DB connection for step 1
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity - Step 1</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
            border-radius: 8px;
            max-width: 1200px; /* Increased max-width to accommodate sidebar */
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex; /* Use flexbox for sidebar and main content */
            min-height: 600px; /* Ensure enough height for the form */
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
            z-index: 10; /* Ensure it's above other elements */
        }
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }

        /* Left sidebar for steps (Copied from enumerator.php) */
        .steps-sidebar {
            width: 250px;
            background-color: #f8f9fa;
            padding: 40px 30px;
            border-radius: 8px 0 0 8px;
            border-right: 1px solid #e9ecef;
            position: relative;
            flex-shrink: 0; /* Prevent shrinking */
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
            font-family: 'Font Awesome 6 Free'; /* For consistent checkmark icon */
            font-weight: 900;
            font-size: 20px;
        }

        .step-circle.active::after {
            content: ''; /* No checkmark for current active step */
            /* Add custom styling for current step, or leave empty if just number is desired */
        }

        .step-circle:not(.active):not(.completed)::after {
            content: attr(data-step); /* Display step number if not active or completed */
        }
        .step-circle.active[data-step="1"]::after,
        .step-circle.active[data-step="2"]::after {
            content: attr(data-step); /* Display step number if it's the current active step */
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
            flex: 1; /* Takes remaining space */
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
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 0; /* Important for dynamic fields */
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }

        /* Dynamic Packaging/Unit Fields */
        #packaging-unit-container {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            max-height: 250px; /* Set max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrolling */
            background-color: #fcfcfc;
        }
        .packaging-unit-group {
            display: flex;
            gap: 15px;
            align-items: flex-end; /* Align inputs to the bottom */
            margin-bottom: 15px;
            padding-bottom: 5px; /* Little padding for separation */
            border-bottom: 1px dashed #eee; /* Visual separator */
        }
        .packaging-unit-group:last-of-type {
            margin-bottom: 0;
            border-bottom: none;
        }
        .packaging-unit-group > div {
            flex: 1; /* Distribute space evenly */
            display: flex;
            flex-direction: column;
        }
        .remove-btn {
            background-color: #f8d7da;
            color: #dc3545;
            border: 1px solid #f5c6cb;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px; /* Fixed width for better alignment */
            height: 38px; /* Fixed height */
            flex-shrink: 0; /* Prevent shrinking */
            margin-left: 10px;
        }
        .remove-btn:hover {
            background-color: #f5c6cb;
        }
        .add-more-btn {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 15px;
            font-weight: bold;
            display: block; /* Make it a block element */
            width: fit-content; /* Adjust width to content */
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .add-more-btn:hover {
            background-color: #c3e6cb;
        }

        /* Navigation buttons */
        .button-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .next-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
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
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .packaging-unit-group {
                flex-direction: column;
                align-items: stretch;
            }
            .packaging-unit-group > div {
                width: 100%;
            }
            .remove-btn {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
            .add-more-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='sidebar.php'">×</button>

        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step completed">
                    <div class="step-circle completed" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Basic Info</small></div>
                </div>
                <div class="step">
                    <div class="step-circle" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Additional Details</small></div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <h2>Add New Commodity</h2>
            <p>Start by providing the basic details for the commodity.</p>

            <form method="POST" action="add_commodity.php" onsubmit="return validateStep1()">
                <div class="form-group-full">
                    <label for="category" class="required">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>"
                                <?= ($session_category == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-full">
                    <label for="commodity-name" class="required">Commodity Name</label>
                    <input type="text" id="commodity-name" name="commodity_name"
                           value="<?= htmlspecialchars($session_commodity_name) ?>" required>
                </div>

                <div class="form-group-full">
                    <label for="variety">Variety </label>
                    <input type="text" id="variety" name="variety"
                           value="<?= htmlspecialchars($session_variety) ?>" required>
                </div>

                <label class="required">Commodity Packaging & Unit</label>
                <div id="packaging-unit-container">
                    <?php if (!empty($session_packaging)): ?>
                        <?php for ($i = 0; $i < count($session_packaging); $i++): ?>
                            <div class="packaging-unit-group">
                                <div>
                                    <label>Packaging</label>
                                    <input type="text" name="packaging[]"
                                           value="<?= htmlspecialchars($session_packaging[$i]) ?>" required>
                                </div>
                                <div>
                                    <label>Measuring Unit</label>
                                    <select name="unit[]" required>
                                        <option value="">Select unit</option>
                                        <option value="Kg" <?= ($session_unit[$i] ?? '') == 'Kg' ? 'selected' : '' ?>>Kg</option>
                                        <option value="Tons" <?= ($session_unit[$i] ?? '') == 'Tons' ? 'selected' : '' ?>>Tons</option>
                                        <option value="Other" <?= ($session_unit[$i] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <button type="button" class="remove-btn" onclick="removePackagingUnit(this)">×</button>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <div class="packaging-unit-group">
                            <div>
                                <label>Packaging</label>
                                <input type="text" name="packaging[]" required>
                            </div>
                            <div>
                                <label>Measuring Unit</label>
                                <select name="unit[]" required>
                                    <option value="">Select unit</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Tons">Tons</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <button type="button" class="remove-btn" onclick="removePackagingUnit(this)">×</button>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="add-more-btn" onclick="addMorePackagingUnit()">
                    <i class="fas fa-plus"></i> Add More Packaging & Unit
                </button>

                <div class="button-container">
                    <button type="submit" class="next-btn">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#category').select2({
                placeholder: "Select category",
                allowClear: true,
                width: '100%'
            });
        });

        // Function to add more packaging and unit fields
        function addMorePackagingUnit() {
            const container = document.getElementById('packaging-unit-container');
            const newGroup = document.createElement('div');
            newGroup.className = 'packaging-unit-group';
            newGroup.innerHTML = `
                <div>
                    <label>Packaging</label>
                    <input type="text" name="packaging[]" required>
                </div>
                <div>
                    <label>Measuring Unit</label>
                    <select name="unit[]" required>
                        <option value="">Select unit</option>
                        <option value="Kg">Kg</option>
                        <option value="Tons">Tons</option>
                        <option value="Other">Other</option>
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
            const container = document.getElementById('packaging-unit-container');
            const group = button.closest('.packaging-unit-group');
            if (group) {
                // Ensure at least one packaging-unit group remains
                if (container.children.length > 1) {
                    group.remove();
                } else {
                    alert("You must have at least one packaging and unit entry.");
                }
            }
        }

        function validateStep1() {
            const category = document.getElementById('category').value;
            const commodityName = document.getElementById('commodity-name').value.trim();
            const packagingInputs = document.querySelectorAll('#packaging-unit-container input[name="packaging[]"]');
            const unitSelects = document.querySelectorAll('#packaging-unit-container select[name="unit[]"]');

            if (!category) {
                alert('Please select a commodity category.');
                return false;
            }
            if (!commodityName) {
                alert('Please enter a commodity name.');
                return false;
            }

            let allPackagingValid = true;
            packagingInputs.forEach(input => {
                if (!input.value.trim()) {
                    allPackagingValid = false;
                }
            });

            let allUnitsValid = true;
            unitSelects.forEach(select => {
                if (!select.value) {
                    allUnitsValid = false;
                }
            });

            if (!allPackagingValid || !allUnitsValid) {
                alert('Please ensure all packaging and measuring unit fields are filled.');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>