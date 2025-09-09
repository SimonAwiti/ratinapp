<?php
// create_admin.php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Only allow 'admin' role to create new admins
if ($_SESSION['admin_role'] !== 'admin') {
    header("Location: base/comodi.php");
    exit;
}

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$error_message = '';
$success_message = '';

// Process admin creation form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error_message = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Check if username already exists
        if (isset($con)) {
            $stmt = $con->prepare("SELECT id FROM admin_users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error_message = "Username already exists. Please choose another.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new admin
                $insert_stmt = $con->prepare("INSERT INTO admin_users (username, password, full_name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $insert_stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Admin account created successfully!";
                    // Clear form fields
                    $_POST = array();
                } else {
                    $error_message = "Error creating admin account. Please try again.";
                }
            }
        } else {
            $error_message = "Database connection error. Please check configuration.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin - RATIN Trade Analytics</title>
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
        
        .admin-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .admin-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, rgba(180, 80, 50, 1) 0%, rgba(200, 100, 70, 1) 100%);
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .admin-header .logo {
            width: 60px;
            height: 60px;
            background: rgba(180, 80, 50, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border: 2px solid rgba(180, 80, 50, 0.2);
        }
        
        .admin-header .logo i {
            font-size: 25px;
            color: rgba(180, 80, 50, 1);
        }
        
        .admin-header h2 {
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .admin-header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 0 3px rgba(180, 80, 50, 0.1);
            background: white;
        }
        
        .form-group .input-icon {
            position: absolute;
            left: 15px;
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
            right: 15px;
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
        
        .create-btn {
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
        
        .create-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(180, 80, 50, 0.3);
        }
        
        .create-btn:active {
            transform: translateY(0);
        }
        
        .create-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .create-btn .spinner {
            display: none;
            margin-right: 10px;
        }
        
        .create-btn.loading .spinner {
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
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: rgba(180, 80, 50, 1);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: rgba(160, 60, 30, 1);
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .admin-container {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .admin-header h2 {
                font-size: 24px;
            }
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 5px;
            background: #eee;
            margin-top: 5px;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            background: #dc3545;
            transition: width 0.3s, background 0.3s;
        }
        
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="logo">
                <i class="fas fa-user-shield"></i>
            </div>
            <h2>Create Admin Account</h2>
            <p>RATIN Trade Analytics</p>
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
        
        <form method="POST" action="" id="adminForm">
            <div class="form-group">
                <label for="username">Username <span class="text-danger">*</span></label>
                <div class="input-wrapper">
                    <input type="text" 
                           name="username" 
                           id="username" 
                           placeholder="Enter username"
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           required>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name <span class="text-danger">*</span></label>
                <div class="input-wrapper">
                    <input type="text" 
                           name="full_name" 
                           id="full_name" 
                           placeholder="Enter full name"
                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
                           required>
                    <i class="fas fa-id-card input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <input type="email" 
                           name="email" 
                           id="email" 
                           placeholder="Enter email"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="role">Role <span class="text-danger">*</span></label>
                <div class="input-wrapper">
                    <select name="role" id="role" required>
                        <option value="">Select Role</option>
                        <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="editor" <?= (isset($_POST['role']) && $_POST['role'] === 'editor') ? 'selected' : '' ?>>Editor</option>
                        <option value="viewer" <?= (isset($_POST['role']) && $_POST['role'] === 'viewer') ? 'selected' : '' ?>>Viewer</option>
                    </select>
                    <i class="fas fa-user-tag input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password <span class="text-danger">*</span></label>
                <div class="input-wrapper">
                    <input type="password" 
                           name="password" 
                           id="password" 
                           placeholder="Enter password"
                           required
                           minlength="8">
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
                <div class="password-hint">Password must be at least 8 characters long</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-wrapper">
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password" 
                           placeholder="Confirm password"
                           required
                           minlength="8">
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                </div>
            </div>
            
            <button type="submit" name="create_admin" class="create-btn" id="createBtn">
                <i class="fas fa-spinner spinner"></i>
                <span class="btn-text">Create Admin</span>
            </button>
            
            <div class="text-center">
                <a href="../base/commodities_boilerplate.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
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
            const adminForm = document.getElementById('adminForm');
            const createBtn = document.getElementById('createBtn');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            
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
            adminForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return;
                }
                
                // Show loading state
                createBtn.classList.add('loading');
                createBtn.querySelector('.btn-text').textContent = 'Creating...';
            });
            
            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length
                if (password.length >= 8) strength += 25;
                if (password.length >= 12) strength += 25;
                
                // Complexity
                if (/[A-Z]/.test(password)) strength += 15;
                if (/[0-9]/.test(password)) strength += 15;
                if (/[^A-Za-z0-9]/.test(password)) strength += 20;
                
                // Update strength bar
                passwordStrengthBar.style.width = strength + '%';
                
                // Change color based on strength
                if (strength < 50) {
                    passwordStrengthBar.style.background = '#dc3545'; // Red
                } else if (strength < 75) {
                    passwordStrengthBar.style.background = '#ffc107'; // Yellow
                } else {
                    passwordStrengthBar.style.background = '#28a745'; // Green
                }
            });
            
            // Remove loading state on page load (in case of form errors)
            createBtn.classList.remove('loading');
            createBtn.querySelector('.btn-text').textContent = 'Create Admin';
            
            // Input validation feedback
            const requiredInputs = document.querySelectorAll('input[required], select[required]');
            requiredInputs.forEach(input => {
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