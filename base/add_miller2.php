<?php
        session_start();
        include '../admin/includes/config.php';

        // Redirect if step 1 not done
        if (!isset($_SESSION['miller_name'])) {
            header('Location: addtradepoint.php');
            exit;
        }

        // Insert miller data with coordinates and radius into DB
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_miller'])) {
            $miller = $_POST['miller'];
            $longitude = $_POST['longitude'];
            $latitude = $_POST['latitude'];
            $radius = $_POST['radius'];
            $miller_name = $_SESSION['miller_name'];

            $stmt = $con->prepare("INSERT INTO millers (miller_name, miller, longitude, latitude, radius) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssdii", $miller_name, $miller, $longitude, $latitude, $radius);
                if ($stmt->execute()) {
                    echo "<script>alert('Miller added successfully!');</script>";
                } else {
                    echo "<script>alert('Error adding miller: {$stmt->error}');</script>";
                }
                $stmt->close();
            } else {
                echo "<script>alert('Failed to prepare statement.');</script>";
            }
            // Do not exit here, let the page reload
        }

        // Go to step 3
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['next_step'])) {
            header("Location: add_miller3.php");
            exit;
        }
        ?>

        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Add Millers - Step 2</title>
            <link rel="stylesheet" href="assets/add_commodity.css" />
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
                    height: auto; /* Adjust height to content */
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                    display: flex;
                    position: relative;
                }
                h2 {
                    margin-bottom: 20px;
                }
                input, textarea {
                    width: 100%;
                    padding: 8px;
                    margin: 5px 0 15px;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    box-sizing: border-box; /* Ensure padding and border are included in the element's total width and height */
                }
                .step-actions {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 20px;
                    gap: 100px; /* Adds space between the buttons */
                }
                button {
                    padding: 10px 20px;
                    background: #a45c40;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                }
                button:hover {
                    background: #8a4933;
                }
                .miller-container {
                    background: #f1f1f1;
                    padding: 15px;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .miller-form {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    margin-bottom: 15px;
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
                    height: calc(100% - 45px - 100px);
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
                    right: 20px; /* Positioned on the top right */
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #a45c40;
                }
                .close-btn:hover {
                    background: #8a4933;
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
                <button class="close-btn" onclick="window.location.href='dashboard.php'">×</button>

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
                        <div class="step-circle inactive"></div>
                        <span>Step 3</span>
                    </div>
                </div>

                <div class="form-content">
                    <div class="miller-container">
                        <h2>Add Miller Details</h2>
                        <form method="POST" class="miller-form">
                            <label for="longitude">Longitude *</label>
                            <input type="text" id="longitude" name="longitude" value="<?= $_SESSION['longitude'] ?? '' ?>" required>

                            <label for="latitude">Latitude *</label>
                            <input type="text" id="latitude" name="latitude" value="<?= $_SESSION['latitude'] ?? '' ?>" required>

                            <label for="radius">Radius (in meters) *</label>
                            <input type="number" id="radius" name="radius" required>

                            <label for="miller">Miller Name *</label>
                            <input type="text" id="miller" name="miller" required>

                            <button type="submit" name="add_miller">Add Miller</button>
                        </form>
                    </div>

                    <div class="step-actions">
                        <button type="button" onclick="window.location.href='addtradepoint.php'">&larr; Back</button>
                        <form method="POST">
                            <button type="submit" name="next_step">Next Step &rarr;</button>
                        </form>
                    </div>
                </div>
            </div>
        </body>
        </html>
