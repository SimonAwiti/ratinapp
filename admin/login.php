<?php
// admin_login.php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ../base/landing_page.php");
    exit;
}

// Include config if available
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
}

$error_message = '';
$success_message = '';

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        // Here you would typically check against database
        // For now, using simple hardcoded check (replace with your database logic)
        
        if (isset($con)) {
            // Database authentication
            $stmt = $con->prepare("SELECT id, username, password, full_name, role FROM admin_users WHERE username = ? AND status = 'active'");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify password (assuming password is hashed)
                if (password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_name'] = $user['full_name'];
                    $_SESSION['admin_role'] = $user['role'];
                    
                    // Update last login
                    $update_stmt = $con->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    
                    header("Location: ../base/landing_page.php");
                    exit;
                } else {
                    $error_message = "Invalid username or password.";
                }
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            // Fallback authentication (replace with your actual credentials)
            if ($username === 'admin' && $password === 'password123') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_name'] = 'Administrator';
                $_SESSION['admin_role'] = 'admin';
                
                header("Location: ../base/landing_page.php");
                exit;
            } else {
                $error_message = "Invalid username or password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="UTF-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Login | RATIN Analytics</title>
<!-- Google Fonts: Inter & Material Symbols -->
<link href="https://fonts.googleapis.com" rel="preconnect">
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
                  "on-primary-container": "#90d689",
                  "error": "#ba1a1a",
                  "on-tertiary-container": "#73d4e0",
                  "on-primary-fixed-variant": "#0c5216",
                  "error-container": "#ffdad6",
                  "surface-container-low": "#f3f3f3",
                  "primary-fixed-dim": "#91d78a",
                  "on-tertiary-fixed-variant": "#004f56",
                  "tertiary": "#004248",
                  "on-error-container": "#93000a",
                  "on-tertiary": "#ffffff",
                  "tertiary-container": "#005b64",
                  "on-error": "#ffffff",
                  "on-secondary": "#ffffff",
                  "outline": "#717a6d",
                  "primary-container": "#1b5e20",
                  "on-surface-variant": "#41493e",
                  "surface-variant": "#e2e2e2",
                  "on-background": "#1a1c1c",
                  "on-surface": "#1a1c1c",
                  "on-secondary-container": "#5d1200",
                  "inverse-surface": "#2f3131",
                  "secondary-container": "#ff6338",
                  "on-primary": "#ffffff",
                  "surface-container-lowest": "#ffffff",
                  "surface-dim": "#dadada",
                  "on-primary-fixed": "#002203",
                  "tertiary-fixed": "#92f1fe",
                  "primary": "#00450d",
                  "surface-container-high": "#e8e8e8",
                  "surface": "#f9f9f9",
                  "primary-fixed": "#acf4a4",
                  "on-tertiary-fixed": "#001f23",
                  "surface-container-highest": "#e2e2e2",
                  "surface-bright": "#f9f9f9",
                  "secondary-fixed-dim": "#ffb5a1",
                  "inverse-on-surface": "#f1f1f1",
                  "on-secondary-fixed": "#3b0800",
                  "on-secondary-fixed-variant": "#881f00",
                  "outline-variant": "#c0c9bb",
                  "surface-tint": "#2a6b2c",
                  "secondary": "#b22c01",
                  "secondary-fixed": "#ffdbd1",
                  "background": "#f9f9f9",
                  "inverse-primary": "#91d78a",
                  "tertiary-fixed-dim": "#75d5e2",
                  "surface-container": "#eeeeee"
          },
          "borderRadius": {
                  "DEFAULT": "0.125rem",
                  "lg": "0.25rem",
                  "xl": "0.5rem",
                  "full": "0.75rem"
          },
          "spacing": {
                  "gutter": "16px",
                  "base": "8px",
                  "sidebar-width": "260px",
                  "container-padding": "24px",
                  "card-gap": "20px"
          },
          "fontFamily": {
                  "headline-lg-mobile": ["Inter"],
                  "body-lg": ["Inter"],
                  "body-md": ["Inter"],
                  "headline-md": ["Inter"],
                  "label-md": ["Inter"],
                  "headline-lg": ["Inter"],
                  "data-tabular": ["Inter"]
          },
          "fontSize": {
                  "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                  "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                  "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                  "headline-md": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                  "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "600"}],
                  "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                  "data-tabular": ["13px", {"lineHeight": "18px", "fontWeight": "400"}]
          }
        },
      },
    }
  </script>
<style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .auth-bg-gradient {
      background: radial-gradient(circle at top left, rgba(27, 94, 32, 0.05), transparent),
                  radial-gradient(circle at bottom right, rgba(178, 44, 1, 0.05), transparent);
    }
    .password-toggle-btn {
      cursor: pointer;
    }
</style>
</head>
<body class="bg-background font-body-md text-on-background min-h-screen flex items-center justify-center auth-bg-gradient">
<main class="w-full max-w-[440px] px-container-padding py-12">
<!-- Login Card -->
<div class="bg-surface-container-lowest rounded-xl shadow-[0px_4px_24px_rgba(0,0,0,0.06)] overflow-hidden border border-outline-variant">
<!-- Top Accent Bar -->
<div class="h-1.5 w-full bg-secondary"></div>
<div class="p-8 md:p-10">
<!-- Logo Section -->
<div class="flex flex-col items-center mb-10">
<div class="mb-6 flex items-center justify-center">
    <img class="ratin-logo h-14 w-auto object-contain" src="../base/img/Ratin-logo-1.png" alt="RATIN Logo">
</div>
<h1 class="font-headline-md text-headline-md text-on-surface mb-1 text-center">Admin Login</h1>
<p class="font-body-md text-body-md text-on-surface-variant text-center">RATIN Trade Analytics</p>
</div>

<!-- Error/Success Messages -->
<?php if (!empty($error_message)): ?>
<div class="mb-6 p-4 bg-error-container text-on-error-container rounded-lg flex items-center gap-3">
    <span class="material-symbols-outlined text-error">error</span>
    <span class="text-sm font-medium"><?= htmlspecialchars($error_message) ?></span>
</div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
<div class="mb-6 p-4 bg-primary-container text-on-primary-container rounded-lg flex items-center gap-3">
    <span class="material-symbols-outlined text-primary">check_circle</span>
    <span class="text-sm font-medium"><?= htmlspecialchars($success_message) ?></span>
</div>
<?php endif; ?>

<!-- Login Form - FULLY FUNCTIONAL PHP -->
<form method="POST" action="" id="loginForm" class="space-y-6">
<!-- Username Field -->
<div class="space-y-2">
    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="username">Username</label>
    <div class="relative group">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">person</span>
        <input class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
               id="username" name="username" placeholder="Enter your username" type="text" 
               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
    </div>
</div>

<!-- Password Field -->
<div class="space-y-2">
    <div class="flex justify-between items-end">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider" for="password">Password</label>
    </div>
    <div class="relative group">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">lock</span>
        <input class="w-full pl-12 pr-12 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
               id="password" name="password" placeholder="Enter your password" type="password" required>
        <button class="password-toggle-btn absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors" type="button" id="togglePassword">
            <span class="material-symbols-outlined">visibility</span>
        </button>
    </div>
</div>

<!-- Actions Row -->
<div class="flex items-center justify-between">
    <label class="flex items-center gap-3 cursor-pointer group">
        <input class="w-4 h-4 rounded border-outline-variant cursor-pointer text-secondary focus:ring-secondary/20" type="checkbox" name="remember" id="remember">
        <span class="font-body-md text-body-md text-on-surface-variant group-hover:text-on-surface transition-colors">Remember me</span>
    </label>
    <a class="font-body-md text-body-md font-semibold transition-colors text-secondary hover:text-[#600000]" href="reset_password.php">Forgot Password?</a>
</div>

<!-- Sign In Button - CRITICAL: name="login" attribute added for PHP to detect -->
<button type="submit" name="login" class="w-full text-on-secondary font-bold py-3.5 px-6 rounded-lg shadow-sm hover:shadow-md transition-all active:scale-[0.98] focus:ring-4 focus:ring-secondary/20 bg-secondary hover:bg-[#8a2201]">
    Sign In
</button>
</form>

<!-- Footer Info -->
<div class="mt-10 pt-6 border-t border-outline-variant/50 text-center">
    <p class="font-label-md text-label-md text-on-surface-variant opacity-60">
        © <?= date('Y') ?> RATIN Trade Analytics All rights reserved.
    </p>
</div>
</div>
</div>

<!-- External Brand Elements -->
<div class="mt-8 flex justify-center gap-6">
    <a class="font-label-md text-label-md text-on-surface-variant transition-colors uppercase tracking-widest hover:text-secondary" href="#">Privacy Policy</a>
    <a class="font-label-md text-label-md text-on-surface-variant transition-colors uppercase tracking-widest hover:text-secondary" href="#">Terms</a>
    <a class="font-label-md text-label-md text-on-surface-variant transition-colors uppercase tracking-widest hover:text-secondary" href="#">Support</a>
</div>
</main>

<!-- Visual Background Accents -->
<div class="fixed top-0 left-0 w-full h-full -z-10 pointer-events-none overflow-hidden opacity-20">
    <div class="absolute -top-[10%] -left-[5%] w-[40%] h-[60%] bg-primary/10 blur-[120px] rounded-full"></div>
    <div class="absolute -bottom-[10%] -right-[5%] w-[35%] h-[50%] bg-secondary/10 blur-[100px] rounded-full"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    
    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const iconSpan = this.querySelector('.material-symbols-outlined');
            if (iconSpan) {
                iconSpan.textContent = type === 'password' ? 'visibility' : 'visibility_off';
            }
        });
    }
    
    // Focus on username field
    if (usernameInput) usernameInput.focus();
});
</script>
</body>
</html>