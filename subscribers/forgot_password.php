<?php
session_start();

// Include database config
if (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$error_message = '';
$success_message = '';
$verification_step = false; // Step 1: verify identity, Step 2: set new password
$verified_username = '';

// Process verification step
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    if (empty($username) || empty($email)) {
        $error_message = "Please enter both username and email address.";
    } else {
        if (isset($con)) {
            // Check if user exists with matching username and email in subscribed_users table
            $stmt = $con->prepare("SELECT id, username, email, full_name FROM subscribed_users WHERE username = ? AND email = ? AND status = 'active'");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $verification_step = true;
                $verified_username = $user['username'];
                $success_message = "Identity verified! You can now set a new password.";
            } else {
                $error_message = "No account found with the provided username and email combination.";
            }
            $stmt->close();
        } else {
            $error_message = "Database connection error. Please try again later.";
        }
    }
}

// Process password update step
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $username = trim($_POST['verified_username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = "Please enter and confirm your new password.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        if (isset($con)) {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in subscribed_users table
            $stmt = $con->prepare("UPDATE subscribed_users SET password = ? WHERE username = ?");
            $stmt->bind_param("ss", $hashed_password, $username);
            
            if ($stmt->execute()) {
                $success_message = "Password has been reset successfully! Redirecting to login...";
                // Log activity
                $activity_stmt = $con->prepare("INSERT INTO user_activity_log (user_id, activity_type, description) SELECT id, 'password_reset', 'Password reset via forgot password' FROM subscribed_users WHERE username = ?");
                $activity_stmt->bind_param("s", $username);
                $activity_stmt->execute();
                // Redirect after 2 seconds
                echo '<meta http-equiv="refresh" content="2;url=index.php">';
            } else {
                $error_message = "Failed to update password. Please try again.";
            }
            $stmt->close();
        } else {
            $error_message = "Database connection error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Reset Your Password - RATIN Analytics</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-fixed-dim": "#ffb5a1",
                        "on-secondary": "#ffffff",
                        "outline-variant": "#c0c9bb",
                        "secondary": "#b22c01",
                        "on-secondary-container": "#5d1200",
                        "inverse-on-surface": "#f1f1f1",
                        "on-tertiary-container": "#73d4e0",
                        "error": "#ba1a1a",
                        "surface-container-lowest": "#ffffff",
                        "on-error-container": "#93000a",
                        "inverse-primary": "#91d78a",
                        "tertiary-fixed-dim": "#75d5e2",
                        "background": "#f9f9f9",
                        "on-primary": "#ffffff",
                        "surface-container": "#eeeeee",
                        "tertiary": "#004248",
                        "primary-container": "#1b5e20",
                        "primary-fixed-dim": "#91d78a",
                        "surface": "#f9f9f9",
                        "on-tertiary": "#ffffff",
                        "on-secondary-fixed-variant": "#881f00",
                        "error-container": "#ffdad6",
                        "on-tertiary-fixed-variant": "#004f56",
                        "on-background": "#1a1c1c",
                        "tertiary-fixed": "#92f1fe",
                        "inverse-surface": "#2f3131",
                        "surface-container-low": "#f3f3f3",
                        "on-surface": "#1a1c1c",
                        "surface-tint": "#2a6b2c",
                        "surface-container-high": "#e8e8e8",
                        "primary-fixed": "#acf4a4",
                        "on-primary-container": "#90d689",
                        "tertiary-container": "#005b64",
                        "outline": "#717a6d",
                        "on-error": "#ffffff",
                        "primary": "#00450d",
                        "on-surface-variant": "#41493e",
                        "surface-container-highest": "#e2e2e2",
                        "surface-bright": "#f9f9f9",
                        "on-secondary-fixed": "#3b0800",
                        "surface-variant": "#e2e2e2",
                        "on-primary-fixed": "#002203",
                        "secondary-container": "#ff6338",
                        "on-tertiary-fixed": "#001f23",
                        "surface-dim": "#dadada",
                        "secondary-fixed": "#ffdbd1",
                        "on-primary-fixed-variant": "#0c5216",
                        "maroon-accent": "#800000"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "sidebar-width": "260px",
                        "base": "8px",
                        "card-gap": "20px",
                        "gutter": "16px",
                        "container-padding": "24px"
                    },
                    "fontFamily": {
                        "label-md": ["Inter"],
                        "headline-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "body-md": ["Inter"],
                        "body-lg": ["Inter"],
                        "data-tabular": ["Inter"]
                    },
                    "fontSize": {
                        "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "600"}],
                        "headline-md": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                        "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "data-tabular": ["13px", {"lineHeight": "18px", "fontWeight": "400"}]
                    }
                },
            },
        }
    </script>
<style>
        body {
            background-color: #F5F5F5;
            font-family: 'Inter', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .card-shadow {
            box-shadow: 0px 4px 12px rgba(0,0,0,0.05);
        }
        .header-gradient {
            background: linear-gradient(135deg, #f3f3f3 0%, #e2e2e2 100%);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
<!-- TopAppBar -->
<header class="bg-surface border-b border-outline-variant flex justify-between items-center w-full px-container-padding py-base fixed top-0 z-50">
<div class="text-headline-md font-headline-md font-bold text-primary">RATIN Analytics</div>
<div class="flex items-center gap-base">
<button class="p-2 hover:bg-surface-container transition-colors rounded-full text-on-surface-variant">
<span class="material-symbols-outlined" data-icon="help">help</span>
</button>
<button class="p-2 hover:bg-surface-container transition-colors rounded-full text-on-surface-variant">
<span class="material-symbols-outlined" data-icon="settings">settings</span>
</button>
</div>
</header>
<main class="flex-grow flex items-center justify-center px-container-padding py-24">
<div class="w-full max-w-[480px]">
<!-- Reset Card -->
<div class="bg-white rounded-xl card-shadow overflow-hidden">
<!-- Branding/Visual Header -->
<div class="h-32 relative header-gradient overflow-hidden border-b border-outline-variant">
<img alt="Background" class="w-full h-full object-cover opacity-10 mix-blend-multiply grayscale" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA8EgPewyxVtCNiM1DkUdagFrZHU6ktLO9kcWgaa_sv8fl63TgIS7x7bL3WLLB-eey7maBZE8MBgXNdvDjhhnola-wTIljaqk0QyWISYB4V_UgDlBWN6c9WXF46-az9oCm6_apV8BEVe8saIOeOrrig4am5VUuYxKgROkFQEA-Nq_MK9o3-J6Bqxfn6mX8C71CcYBNwQrUaqbf1mp6bm7b7ydjISqUw9gh4VuDkyKQhTHCqyax-EhQQxolUWLOoWxNEw76OyCTd24E"/>
<div class="absolute inset-0 flex items-center justify-center">
<div class="bg-maroon-accent/5 p-4 rounded-full border border-maroon-accent/10">
<span class="material-symbols-outlined text-maroon-accent text-4xl" data-icon="lock_reset">lock_reset</span>
</div>
</div>
<!-- Decorative accents -->
<div class="absolute top-0 right-0 w-24 h-full bg-secondary-container/10 -skew-x-12 transform translate-x-12"></div>
<div class="absolute top-0 left-0 w-1 h-full bg-maroon-accent"></div>
</div>
<div class="p-8">
<div class="mb-8 text-center">
<h1 class="font-headline-lg text-headline-lg text-on-surface mb-2">Reset Your Password</h1>
<p class="font-body-md text-body-md text-on-surface-variant">Enter your details to verify your account and set a new password.</p>
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

<form method="POST" action="" class="space-y-6">
<?php if (!$verification_step): ?>
<!-- Step 1: Verification Section -->
<div class="space-y-4">
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="username">Username</label>
<div class="relative">
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-secondary text-lg" data-icon="person">person</span>
</div>
<input class="w-full pl-10 pr-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md" 
       id="username" name="username" placeholder="Enter your username" type="text" 
       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required/>
</div>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="email">Email Address</label>
<div class="relative">
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-secondary text-lg" data-icon="mail">mail</span>
</div>
<input class="w-full pl-10 pr-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md" 
       id="email" name="email" placeholder="Enter your email address" type="email" 
       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required/>
</div>
</div>
<button type="submit" name="verify" class="w-full py-4 bg-maroon-accent text-white font-label-md text-label-md rounded-lg hover:bg-[#660000] active:scale-[0.98] transition-all uppercase tracking-wider flex justify-center items-center gap-2 shadow-sm">
    Verify Identity
    <span class="material-symbols-outlined text-lg" data-icon="verified_user">verified_user</span>
</button>
</div>
<?php else: ?>
<!-- Step 2: Password Update Section (Now Active) -->
<input type="hidden" name="verified_username" value="<?= htmlspecialchars($verified_username) ?>">
<div class="space-y-4">
<div class="flex items-center gap-2 text-maroon-accent font-label-md text-label-md mb-2">
<span class="material-symbols-outlined text-lg" data-icon="lock_open">lock_open</span>
<span>Set New Password</span>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="new_password">New Password</label>
<div class="relative">
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-secondary text-lg" data-icon="lock">lock</span>
</div>
<input class="w-full pl-10 pr-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md" 
       id="new_password" name="new_password" placeholder="Enter new password (min. 6 characters)" type="password" required/>
</div>
<p class="text-xs text-on-surface-variant mt-1">Minimum 6 characters</p>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="confirm_password">Confirm New Password</label>
<div class="relative">
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-secondary text-lg" data-icon="lock">lock</span>
</div>
<input class="w-full pl-10 pr-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md" 
       id="confirm_password" name="confirm_password" placeholder="Confirm your new password" type="password" required/>
</div>
</div>
<button type="submit" name="update_password" class="w-full py-4 bg-maroon-accent text-white font-label-md text-label-md rounded-lg hover:bg-[#660000] active:scale-[0.98] transition-all uppercase tracking-wider shadow-sm">
    Update Password
</button>
</div>
<?php endif; ?>
</form>

<div class="mt-8 text-center">
<a class="font-label-md text-label-md text-secondary hover:text-maroon-accent hover:underline flex items-center justify-center gap-1 transition-colors" href="index.php">
<span class="material-symbols-outlined text-base" data-icon="arrow_back">arrow_back</span>
    Back to Login
</a>
</div>
</div>
</div>
<!-- Support Information -->
<div class="mt-8 flex justify-center gap-gutter text-on-surface-variant font-label-md text-label-md">
<a class="hover:text-secondary transition-colors" href="#">Privacy Policy</a>
<span class="text-outline-variant">|</span>
<a class="hover:text-secondary transition-colors" href="#">Terms of Service</a>
<span class="text-outline-variant">|</span>
<a class="hover:text-secondary transition-colors" href="#">Support</a>
</div>
</div>
</main>
<!-- Footer -->
<footer class="bg-surface-container border-t border-outline-variant py-base px-container-padding flex flex-col md:flex-row justify-between items-center w-full mt-auto">
<div class="font-label-md text-label-md text-on-surface-variant">© <?= date('Y') ?> RATIN Analytics. All rights reserved.</div>
<div class="flex gap-4 mt-4 md:mt-0">
<span class="font-label-md text-label-md text-on-surface-variant">v2.1 Stable Build</span>
</div>
</footer>
</body>
</html>