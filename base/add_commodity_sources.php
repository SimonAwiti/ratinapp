<?php
session_start();
include '../admin/includes/config.php'; // Your DB connection

$error_message = '';
$success_message = '';

// Initialize arrays for pre-filling form if a submission failed
// This will hold the data from the previous POST attempt
$posted_admin0 = isset($_POST['admin0_country']) ? $_POST['admin0_country'] : ['']; // Default to one empty field for initial load
$posted_admin1 = isset($_POST['admin1_county_district']) ? $_POST['admin1_county_district'] : ['']; // Default to one empty field for initial load


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin0_countries_input = $_POST['admin0_country'] ?? [];
    $admin1_county_districts_input = $_POST['admin1_county_district'] ?? [];

    $sources_to_insert = [];
    $seen_pairs_in_submission = []; // To track duplicates within the current submission

    // Process submitted data, filter out empty fields, and check for internal duplicates
    for ($i = 0; $i < count($admin0_countries_input); $i++) {
        $admin0 = trim($admin0_countries_input[$i]);
        $admin1 = trim($admin1_county_districts_input[$i]);

        // Only consider non-empty pairs
        if (!empty($admin0) && !empty($admin1)) {
            // Normalize for case-insensitive duplicate checking
            $pair_key = strtolower($admin0) . '|' . strtolower($admin1);

            if (in_array($pair_key, $seen_pairs_in_submission)) {
                $error_message = "Duplicate entry '" . htmlspecialchars($admin0) . " - " . htmlspecialchars($admin1) . "' found in your submission. Please remove it.";
                break; // Stop processing and show error
            }

            $sources_to_insert[] = ['admin0' => $admin0, 'admin1' => $admin1];
            $seen_pairs_in_submission[] = $pair_key;
        }
    }

    if (empty($error_message)) { // Only proceed if no validation errors from within the submission
        if (empty($sources_to_insert)) {
            $error_message = "Please add at least one Commodity Source (Country and County/District).";
        } else {
            // Start database transaction
            $con->begin_transaction();
            $all_inserted = true;
            $failed_inserts_messages = [];

            $insert_sql = "INSERT INTO commodity_sources (admin0_country, admin1_county_district) VALUES (?, ?)";
            $stmt = $con->prepare($insert_sql);

            if ($stmt === false) {
                $error_message = "Database prepare error: " . $con->error;
                $all_inserted = false;
            } else {
                foreach ($sources_to_insert as $source) {
                    $stmt->bind_param('ss', $source['admin0'], $source['admin1']);
                    try {
                        $stmt->execute();
                    } catch (mysqli_sql_exception $e) {
                        // Check for duplicate entry error (error code 1062)
                        if ($e->getCode() == 1062) {
                            $failed_inserts_messages[] = "'" . htmlspecialchars($source['admin0']) . " - " . htmlspecialchars($source['admin1']) . "' (already exists in database)";
                        } else {
                            $failed_inserts_messages[] = "'" . htmlspecialchars($source['admin0']) . " - " . htmlspecialchars($source['admin1']) . "' (DB error: " . $e->getMessage() . ")";
                        }
                        $all_inserted = false;
                    }
                }
                $stmt->close();
            }

            if ($all_inserted) {
                $con->commit();
                $success_message = "All commodity sources added successfully!";
                // Clear the posted data for a clean form after successful submission
                $posted_admin0 = [''];
                $posted_admin1 = [''];
            } else {
                $con->rollback();
                // If there were failed inserts, combine them into the error message
                $error_message = "Some commodity sources could not be added: " . implode("; ", $failed_inserts_messages) . ". Please correct and try again.";
            }
        }
    }
}
mysqli_close($con);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Commodity Source - Step 1</title>
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
            max-width: 1200px; /* Consistent with your other forms */
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
            font-family: 'Font Awesome 6 Free'; /* For consistent checkmark icon */
            font-weight: 900;
            font-size: 20px;
        }

        .step-circle.active[data-step="1"]::after {
            content: attr(data-step); /* Display step number if it's the current active step */
        }
        .step-circle:not(.active):not(.completed)::after {
            content: attr(data-step); /* Display step number if not active or completed */
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
        input[type="text"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 0; /* Important for dynamic fields */
        }
        input:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }

        /* Dynamic Source Fields */
        #source-container {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            max-height: 300px; /* Set max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrolling */
            background-color: #fcfcfc;
        }
        .source-group {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #eee;
        }
        .source-group:last-of-type {
            margin-bottom: 0;
            border-bottom: none;
        }
        .source-group > div {
            flex: 1;
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
            width: 38px;
            height: 38px;
            flex-shrink: 0;
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
            display: block;
            width: fit-content;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .add-more-btn:hover {
            background-color: #c3e6cb;
        }

        .button-container {
            display: flex;
            justify-content: flex-end; /* Align to right for "Save" */
            margin-top: 30px;
        }
        .submit-btn {
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
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            background-color: rgba(160, 60, 30, 1);
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

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
            .source-group {
                flex-direction: column;
                align-items: stretch;
            }
            .source-group > div {
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
            .button-container {
                flex-direction: column;
                gap: 15px;
            }
            .submit-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='commodity_sources_boilerplate.php'">×</button>

        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step completed">
                    <div class="step-circle completed" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Add Source</small></div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <h2>Add New Commodity Source</h2>
            <p>Define the geographical sources for commodities.</p>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_commodity_sources.php" onsubmit="return validateSourceForm()">
                <label class="required">Source Location</label>
                <div id="source-container">
                    <?php
                    // Loop through posted data or provide one empty field if no data
                    for ($i = 0; $i < count($posted_admin0); $i++):
                        $admin0_val = $posted_admin0[$i] ?? '';
                        $admin1_val = $posted_admin1[$i] ?? '';
                    ?>
                        <div class="source-group">
                            <div>
                                <label>Admin-0 (Country)</label>
                                <input type="text" name="admin0_country[]" value="<?= htmlspecialchars($admin0_val) ?>" required>
                            </div>
                            <div>
                                <label>Admin-1 (County/District)</label>
                                <input type="text" name="admin1_county_district[]" value="<?= htmlspecialchars($admin1_val) ?>" required>
                            </div>
                            <button type="button" class="remove-btn" onclick="removeSource(this)">×</button>
                        </div>
                    <?php endfor; ?>
                </div>

                <button type="button" class="add-more-btn" onclick="addMoreSource()">
                    <i class="fas fa-plus"></i> Add More Source
                </button>

                <div class="button-container">
                    <button type="submit" class="submit-btn">
                        Save Sources <i class="fas fa-save"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to add more source fields
        function addMoreSource() {
            const container = document.getElementById('source-container');
            const newGroup = document.createElement('div');
            newGroup.className = 'source-group';
            newGroup.innerHTML = `
                <div>
                    <label>Admin-0 (Country)</label>
                    <input type="text" name="admin0_country[]" required>
                </div>
                <div>
                    <label>Admin-1 (County/District)</label>
                    <input type="text" name="admin1_county_district[]" required>
                </div>
                <button type="button" class="remove-btn" onclick="removeSource(this)">×</button>
            `;
            container.appendChild(newGroup);

            // Automatically scroll to the newly added fields
            container.scrollTop = container.scrollHeight;
        }

        // Function to remove a source group
        function removeSource(button) {
            const container = document.getElementById('source-container');
            const group = button.closest('.source-group');
            if (group) {
                // Ensure at least one group remains
                if (container.children.length > 1) {
                    group.remove();
                } else {
                    alert("You must have at least one Commodity Source entry.");
                }
            }
        }

        function validateSourceForm() {
            const admin0Inputs = document.querySelectorAll('#source-container input[name="admin0_country[]"]');
            const admin1Inputs = document.querySelectorAll('#source-container input[name="admin1_county_district[]"]');

            const seenPairs = new Set();
            let hasValidEntry = false;

            for (let i = 0; i < admin0Inputs.length; i++) {
                const admin0 = admin0Inputs[i].value.trim();
                const admin1 = admin1Inputs[i].value.trim();

                // Check if both fields in a pair are filled
                if (!admin0 || !admin1) {
                    alert('Please fill in all "Admin-0 (Country)" and "Admin-1 (County/District)" fields, or remove the empty pair.');
                    return false;
                }

                // Check for duplicates within the current submission
                // Case-insensitive comparison for the pair
                const pairKey = (admin0 + '|' + admin1).toLowerCase();
                if (seenPairs.has(pairKey)) {
                    alert(`Duplicate entry '${admin0} - ${admin1}' found in your submission. Please remove one.`);
                    return false;
                }
                seenPairs.add(pairKey);
                hasValidEntry = true;
            }

            if (!hasValidEntry) {
                alert('Please add at least one Commodity Source (Country and County/District).');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>