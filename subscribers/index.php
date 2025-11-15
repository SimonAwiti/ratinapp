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
    <title>Login - RATIN Trade Analytics</title>
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
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>User Login</h2>
            <p>RATIN Trade Analytics</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= $_POST['username'] ?? '' ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
            
            <div class="text-center mt-3">
                <a href="register.php">Don't have an account? Register</a>
            </div>
        </form>
    </div>
</body>
</html>