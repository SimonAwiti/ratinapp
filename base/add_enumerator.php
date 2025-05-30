<?php
include '../admin/includes/config.php'; // DB connection

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();

    // Sanitize and get the input data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // --- Duplicate Check ---
    // Prepare a SQL statement to check for existing records
    $stmt_check = $con->prepare("SELECT COUNT(*) FROM enumerators WHERE name = ? OR email = ? OR phone = ?");
    $stmt_check->bind_param("sss", $name, $email, $phone);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
            echo "<script>alert('An enumerator with the provided full name, email, or phone number already exists. Please check your details.'); window.history.back();</script>";
            exit();
        }
    
    // --- End Duplicate Check ---

    // If no duplicates, proceed to store data in session and redirect
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;
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
    <title>Add Enumerator - Step 1</title>
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
        input, select, textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }

        /* Password input with eye icon */
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: #6c757d;
        }

        /* Button styling */
        .button-container {
            display: flex;
            justify-content: flex-end;
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
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='sidebar.php'">×</button>

        <div class="steps-sidebar">
            <h3>Progress</h3>
            <div class="steps-container">
                <div class="step active">
                    <div class="step-circle active" data-step="1"></div>
                    <div class="step-text">Step 1<br><small>Basic Info</small></div>
                </div>
                <div class="step">
                    <div class="step-circle" data-step="2"></div>
                    <div class="step-text">Step 2<br><small>Assign Tradepoints</small></div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <h2>Add Enumerator - Step 1</h2>
            <p>Please provide basic information for the new enumerator.</p>

            <?php
            // Display error message if it exists in the session
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert-danger">' . $_SESSION['error_message'] . '</div>';
                unset($_SESSION['error_message']); // Clear the message after displaying
            }
            ?>

            <form method="POST" action="add_enumerator.php">
                <div class="form-group-full">
                    <label for="name" class="required">Full Name</label>
                    <input type="text" name="name" id="name" placeholder="Enter full name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="required">Email Address</label>
                        <input type="email" name="email" id="email" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="required">Phone Number</label>
                        <input type="text" name="phone" id="phone" placeholder="Enter phone number" required>
                    </div>
                </div>

                <div class="form-group-full">
                    <label for="gender" class="required">Gender</label>
                    <select name="gender" id="gender" required>
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="country" class="required">Country (Admin 0)</label>
                        <input type="text" name="country" id="country" placeholder="Enter country" required>
                    </div>
                    <div class="form-group">
                        <label for="county_district" class="required">County/District (Admin 1)</label>
                        <input type="text" name="county_district" id="county_district" placeholder="Enter county/district" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="required">Username</label>
                        <input type="text" name="username" id="username" placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <div class="password-container">
                            <input type="password" name="password" id="password" placeholder="Enter password" required>
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" class="next-btn">
                        Next Step <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // Toggle the eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Add smooth transitions for better UX
        document.querySelectorAll('input, select').forEach(element => {
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