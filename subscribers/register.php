<?php
session_start();
include '../admin/includes/config.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $company = trim($_POST['company']);
    $phone = trim($_POST['phone']);
    $subscription_type = $_POST['subscription_type'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($subscription_type)) {
        $error_message = "Please fill all required fields.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Check if username or email already exists
        $check_stmt = $con->prepare("SELECT id FROM subscribed_users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Username or email already exists.";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $con->prepare("INSERT INTO subscribed_users (username, email, password, full_name, company, phone, subscription_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insert_stmt->bind_param("sssssss", $username, $email, $hashed_password, $full_name, $company, $phone, $subscription_type);
            
            if ($insert_stmt->execute()) {
                $success_message = "Registration successful! Your account is pending admin approval. You will receive an email once approved.";
                
                // Log activity
                $user_id = $insert_stmt->insert_id;
                $activity_stmt = $con->prepare("INSERT INTO user_activity_log (user_id, activity_type, description) VALUES (?, 'registration', ?)");
                $activity_desc = "User registered with subscription: " . $subscription_type;
                $activity_stmt->bind_param("is", $user_id, $activity_desc);
                $activity_stmt->execute();
                
                // Clear form
                $_POST = array();
            } else {
                $error_message = "Registration failed. Please try again.";
            }
        }
    }
}

// Get subscription packages
$packages_stmt = $con->prepare("SELECT * FROM subscription_packages WHERE is_active = TRUE");
$packages_stmt->execute();
$packages_result = $packages_stmt->get_result();
$packages = [];
while ($row = $packages_result->fetch_assoc()) {
    $packages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - RATIN Trade Analytics</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .package-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .package-option {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .package-option:hover {
            border-color: rgba(180, 80, 50, 0.5);
        }
        
        .package-option.selected {
            border-color: rgba(180, 80, 50, 1);
            background-color: rgba(180, 80, 50, 0.05);
        }
        
        .package-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .package-price {
            color: rgba(180, 80, 50, 1);
            font-size: 18px;
            font-weight: bold;
        }
        
        .package-features {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2>Create Account</h2>
            <p>Join RATIN Trade Analytics</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?= $_POST['full_name'] ?? '' ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" class="form-control" value="<?= $_POST['company'] ?? '' ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" value="<?= $_POST['username'] ?? '' ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= $_POST['email'] ?? '' ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= $_POST['phone'] ?? '' ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Subscription Package *</label>
                <div class="package-options">
                    <?php foreach ($packages as $package): ?>
                        <div class="package-option" onclick="selectPackage('<?= $package['code'] ?>')">
                            <div class="package-name"><?= $package['name'] ?></div>
                            <div class="package-price">$<?= $package['price'] ?></div>
                            <div class="package-features">
                                <?= implode(', ', json_decode($package['features'], true)) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="subscription_type" id="subscription_type" required>
            </div>
            
            <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
            
            <div class="text-center mt-3">
                <a href="index.php">Already have an account? Login</a>
            </div>
        </form>
    </div>

    <script>
        function selectPackage(packageCode) {
            document.getElementById('subscription_type').value = packageCode;
            
            // Update UI
            document.querySelectorAll('.package-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
        }
        
        // Select basic by default
        document.addEventListener('DOMContentLoaded', function() {
            selectPackage('basic');
        });
    </script>
</body>
</html>