<?php
session_start();
include '../admin/includes/config.php';

// If user is already logged in, redirect based on subscription
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header("Location: marketprices.php");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        $stmt = $con->prepare("SELECT id, username, password, full_name, subscription_type, status FROM subscribed_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    // Login successful
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['subscription_type'] = $user['subscription_type'];
                    
                    // Update last login
                    $update_stmt = $con->prepare("UPDATE subscribed_users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    
                    // Log activity
                    $activity_stmt = $con->prepare("INSERT INTO user_activity_log (user_id, activity_type) VALUES (?, 'login')");
                    $activity_stmt->bind_param("i", $user['id']);
                    $activity_stmt->execute();
                    
                    // Redirect based on subscription (currently all go to marketprices)
                    header("Location: marketprices.php");
                    exit;
                } elseif ($user['status'] === 'pending') {
                    $error_message = "Your account is pending approval. Please wait for admin approval.";
                } elseif ($user['status'] === 'suspended') {
                    $error_message = "Your account has been suspended. Please contact support.";
                } elseif ($user['status'] === 'rejected') {
                    $error_message = "Your registration was rejected. Please contact support for more information.";
                }
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - RATIN Trade Analytics</title>
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
        
        .login-container {
            background: white;
            padding: 50px;
            border-radius: 15px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, rgba(180, 80, 50, 1) 0%, rgba(200, 100, 70, 1) 100%);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header .logo {
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
        
        .login-header .logo img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .login-header h2 {
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .login-header p {
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
        
        .form-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
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
        
        .form-group input:focus + .input-icon {
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
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
        }
        
        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            accent-color: rgba(180, 80, 50, 1);
        }
        
        .forgot-password {
            color: rgba(180, 80, 50, 1);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: rgba(160, 60, 30, 1);
            text-decoration: underline;
        }
        
        .login-btn {
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
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(180, 80, 50, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .login-btn .spinner {
            display: none;
            margin-right: 10px;
        }
        
        .login-btn.loading .spinner {
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
        
        .register-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .register-link a {
            color: rgba(180, 80, 50, 1);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .register-link a:hover {
            color: rgba(160, 60, 30, 1);
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .login-header h2 {
                font-size: 24px;
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
        
        .login-header .logo {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <img class="ratin-logo" src="../base/img/Ratin-logo-1.png" alt="RATIN Logo">
            </div>
            <h2>User Login</h2>
            <p>RATIN Trade Analytics</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" 
                           name="username" 
                           id="username" 
                           placeholder="Enter your username"
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           required>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" 
                           name="password" 
                           id="password" 
                           placeholder="Enter your password"
                           required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>
            
            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    Remember me
                </label>
                <a href="#" class="forgot-password">Forgot Password?</a>
            </div>
            
            <button type="submit" name="login" class="login-btn" id="loginBtn">
                <i class="fas fa-spinner spinner"></i>
                <span class="btn-text">Sign In</span>
            </button>
            
            <div class="register-link">
                <a href="register.php">Don't have an account? Register</a>
            </div>
        </form>
        
        <div class="footer-text">
            <p>&copy; <?= date('Y') ?> RATIN Trade Analytics All rights reserved.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const usernameInput = document.getElementById('username');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Form submission with loading state
            loginForm.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password.');
                    return;
                }
                
                // Show loading state
                loginBtn.classList.add('loading');
                loginBtn.querySelector('.btn-text').textContent = 'Signing In...';
            });
            
            // Focus on username field
            usernameInput.focus();
            
            // Add enter key support
            passwordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loginForm.submit();
                }
            });
            
            // Remove loading state on page load (in case of form errors)
            loginBtn.classList.remove('loading');
            loginBtn.querySelector('.btn-text').textContent = 'Sign In';
            
            // Input validation feedback
            const inputs = [usernameInput, passwordInput];
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
    </script>
</body>
</html>