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
            padding: 50px;
            border-radius: 15px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, rgba(180, 80, 50, 1) 0%, rgba(200, 100, 70, 1) 100%);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .register-header .logo {
            width: 80px;
            height: 80px;
            background: rgba(180, 80, 50, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 3px solid rgba(180, 80, 50, 0.2);
        }
        
        .register-header .logo img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .register-header h2 {
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .register-header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group .input-wrapper {
            position: relative;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 0 3px rgba(180, 80, 50, 0.1);
            background: white;
        }
        
        .form-group .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
        }
        
        .form-group input:focus + .input-icon,
        .form-group select:focus + .input-icon {
            color: rgba(180, 80, 50, 1);
        }
        
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
        }
        
        .password-toggle:hover {
            color: rgba(180, 80, 50, 1);
        }
        
        .package-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .package-option {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: #f8f9fa;
        }
        
        .package-option:hover {
            border-color: rgba(180, 80, 50, 0.5);
            transform: translateY(-2px);
        }
        
        .package-option.selected {
            border-color: rgba(180, 80, 50, 1);
            background-color: rgba(180, 80, 50, 0.05);
            box-shadow: 0 5px 15px rgba(180, 80, 50, 0.1);
        }
        
        .package-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .package-price {
            color: rgba(180, 80, 50, 1);
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .package-features {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            text-align: left;
        }
        
        .package-features ul {
            padding-left: 15px;
            margin-bottom: 0;
        }
        
        .package-features li {
            margin-bottom: 5px;
        }
        
        .register-btn {
            width: 100%;
            background: linear-gradient(135deg, rgba(180, 80, 50, 1) 0%, rgba(200, 100, 70, 1) 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(180, 80, 50, 0.3);
        }
        
        .register-btn:active {
            transform: translateY(0);
        }
        
        .register-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .register-btn .spinner {
            display: none;
            margin-right: 10px;
        }
        
        .register-btn.loading .spinner {
            display: inline-block;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 13px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: rgba(180, 80, 50, 1);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: rgba(160, 60, 30, 1);
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .register-header h2 {
                font-size: 24px;
            }
            
            .package-options {
                grid-template-columns: 1fr;
            }
        }
        
        /* Loading animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Floating animation for logo */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .register-header .logo {
            animation: float 3s ease-in-out infinite;
        }
        
        .required-field::after {
            content: " *";
            color: rgba(180, 80, 50, 1);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <img class="ratin-logo" src="../base/img/Ratin-logo-1.png" alt="RATIN Logo">
            </div>
            <h2>Create Account</h2>
            <p>Subscribe to  RATIN Trade Analytics to get access to a rich databse of trade data across Africa</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="full_name" class="required-field">Full Name</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="full_name" 
                                   id="full_name" 
                                   placeholder="Enter your full name"
                                   value="<?= $_POST['full_name'] ?? '' ?>" 
                                   required>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="company">Company</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="company" 
                                   id="company" 
                                   placeholder="Enter your company name"
                                   value="<?= $_POST['company'] ?? '' ?>">
                            <i class="fas fa-building input-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="username" class="required-field">Username</label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="username" 
                                   id="username" 
                                   placeholder="Choose a username"
                                   value="<?= $_POST['username'] ?? '' ?>" 
                                   required>
                            <i class="fas fa-user-tag input-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="email" class="required-field">Email</label>
                        <div class="input-wrapper">
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   placeholder="Enter your email address"
                                   value="<?= $_POST['email'] ?? '' ?>" 
                                   required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <div class="input-wrapper">
                            <input type="tel" 
                                   name="phone" 
                                   id="phone" 
                                   placeholder="Enter your phone number"
                                   value="<?= $_POST['phone'] ?? '' ?>">
                            <i class="fas fa-phone input-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password" class="required-field">Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   placeholder="Create a password"
                                   required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="confirm_password" class="required-field">Confirm Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   placeholder="Confirm your password"
                                   required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="required-field">Subscription Package</label>
                <div class="package-options">
                    <?php foreach ($packages as $package): 
                        $features = json_decode($package['features'], true);
                    ?>
                        <div class="package-option" onclick="selectPackage('<?= $package['code'] ?>', this)">
                            <div class="package-name"><?= $package['name'] ?></div>
                            <div class="package-price">$<?= $package['price'] ?></div>
                            <div class="package-features">
                                <ul>
                                    <?php foreach ($features as $feature): ?>
                                        <li><?= $feature ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="subscription_type" id="subscription_type" required>
            </div>
            
            <button type="submit" name="register" class="register-btn" id="registerBtn">
                <i class="fas fa-spinner spinner"></i>
                <span class="btn-text">Create Account</span>
            </button>
            
            <div class="login-link">
                <a href="index.php">Already have an account? Login</a>
            </div>
        </form>
        
        <div class="footer-text">
            <p>&copy; <?= date('Y') ?> RATIN Trade Analytics All rights reserved.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Toggle confirm password visibility
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                
                // Toggle icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Form submission with loading state
            registerForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const subscriptionType = document.getElementById('subscription_type').value;
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
                
                if (!subscriptionType) {
                    e.preventDefault();
                    alert('Please select a subscription package.');
                    return;
                }
                
                // Show loading state
                registerBtn.classList.add('loading');
                registerBtn.querySelector('.btn-text').textContent = 'Creating Account...';
            });
            
            // Focus on first field
            document.getElementById('full_name').focus();
            
            // Remove loading state on page load (in case of form errors)
            registerBtn.classList.remove('loading');
            registerBtn.querySelector('.btn-text').textContent = 'Create Account';
            
            // Input validation feedback
            const inputs = document.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.style.borderColor = '#28a745';
                    }
                });
                
                input.addEventListener('input', function() {
                    if (this.style.borderColor === '#dc3545' && this.value.trim() !== '') {
                        this.style.borderColor = '#e1e5e9';
                    }
                });
            });
        });
        
        function selectPackage(packageCode, element) {
            document.getElementById('subscription_type').value = packageCode;
            
            // Update UI
            document.querySelectorAll('.package-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            element.classList.add('selected');
        }
        
        // Select first package by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstPackage = document.querySelector('.package-option');
            if (firstPackage) {
                selectPackage(firstPackage.querySelector('.package-name').textContent.toLowerCase(), firstPackage);
            }
        });
    </script>
</body>
</html>