<?php
include '../admin/includes/config.php'; // DB connection

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $_SESSION['name'] = $_POST['name'];
    $_SESSION['email'] = $_POST['email'];
    $_SESSION['phone'] = $_POST['phone'];
    $_SESSION['gender'] = $_POST['gender'];
    $_SESSION['country'] = $_POST['country'];
    $_SESSION['county_district'] = $_POST['county_district'];
    $_SESSION['username'] = $_POST['username'];
    $_SESSION['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password

    header('Location: add_enumerator2.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Enumerator</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
    <style>
        <?php include 'assets/add_commodity.css'; ?>
    </style>
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
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
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
                <div class="step-circle inactive"></div>
                <span>Step 2</span>
            </div>
        </div>

        <div class="form-container">
            <h2>Add Enumerator</h2>
            <p>Provide the details below to create a new enumerator</p>
            <form method="POST" action="add_enumerator.php">
                <label for="name">Full Name *</label>
                <input type="text" name="name" id="name" required>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="text" name="phone" id="phone" required>
                    </div>
                </div>


                <label for="gender">Gender *</label>
                <select name="gender" id="gender" required>
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>

                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country (Admin 0) *</label>
                        <input type="text" name="country" id="country" required>
                    </div>
                    <div class="form-group">
                        <label for="county_district">County/District (Admin 1) *</label>
                        <input type="text" name="county_district" id="county_district" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Login Username *</label>
                        <input type="text" name="username" id="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                </div>


                <button type="submit" class="next-btn">Next &rarr;</button>
            </form>
        </div>
    </div>
</body>
</html>
