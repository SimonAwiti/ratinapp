<?php
// user_settings.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Include config
if (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Fetch current user data from subscribed_users table - removed created_at column
$user_query = $con->prepare("SELECT username, full_name, email, subscription_type, phone, company, status, registration_date FROM subscribed_users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $company = trim($_POST['company']);
    
    if (empty($full_name) || empty($username)) {
        $message = "Full name and username are required.";
        $message_type = "error";
    } else {
        // Check if username already exists (excluding current user)
        $check_stmt = $con->prepare("SELECT id FROM subscribed_users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "Username already exists. Please choose another.";
            $message_type = "error";
        } else {
            // Check if email already exists (excluding current user)
            $check_email_stmt = $con->prepare("SELECT id FROM subscribed_users WHERE email = ? AND id != ?");
            $check_email_stmt->bind_param("si", $email, $user_id);
            $check_email_stmt->execute();
            $check_email_stmt->store_result();
            
            if ($check_email_stmt->num_rows > 0) {
                $message = "Email already exists. Please use another email address.";
                $message_type = "error";
            } else {
                $update_stmt = $con->prepare("UPDATE subscribed_users SET full_name = ?, username = ?, email = ?, phone = ?, company = ? WHERE id = ?");
                $update_stmt->bind_param("sssssi", $full_name, $username, $email, $phone, $company, $user_id);
                
                if ($update_stmt->execute()) {
                    // Update session variables
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_username'] = $username;
                    
                    $message = "Profile updated successfully!";
                    $message_type = "success";
                    
                    // Refresh user data
                    $user_data['full_name'] = $full_name;
                    $user_data['username'] = $username;
                    $user_data['email'] = $email;
                    $user_data['phone'] = $phone;
                    $user_data['company'] = $company;
                } else {
                    $message = "Error updating profile: " . $update_stmt->error;
                    $message_type = "error";
                }
                $update_stmt->close();
            }
            $check_email_stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Password Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all password fields.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        // Verify current password
        $pass_stmt = $con->prepare("SELECT password FROM subscribed_users WHERE id = ?");
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $pass_result = $pass_stmt->get_result();
        $pass_data = $pass_result->fetch_assoc();
        
        if (password_verify($current_password, $pass_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_stmt = $con->prepare("UPDATE subscribed_users SET password = ? WHERE id = ?");
            $update_pass_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_pass_stmt->execute()) {
                $message = "Password updated successfully!";
                $message_type = "success";
                $active_tab = 'password'; // Stay on password tab
                
                // Log activity - check if user_activity_log table exists
                $activity_stmt = $con->prepare("INSERT INTO user_activity_log (user_id, activity_type, description) VALUES (?, 'password_change', 'User changed their password')");
                if ($activity_stmt) {
                    $activity_stmt->bind_param("i", $user_id);
                    $activity_stmt->execute();
                    $activity_stmt->close();
                }
            } else {
                $message = "Error updating password: " . $update_pass_stmt->error;
                $message_type = "error";
            }
            $update_pass_stmt->close();
        } else {
            $message = "Current password is incorrect.";
            $message_type = "error";
        }
        $pass_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>User Settings - RATIN Analytics</title>
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
        .auth-bg-gradient {
            background: radial-gradient(circle at top left, rgba(0, 69, 13, 0.05), transparent),
                        radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.05), transparent);
        }
        .header-accent-gradient {
            background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
        }
        .settings-card {
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            transform: translateY(-2px);
        }
        .password-strength-bar {
            transition: width 0.3s, background 0.3s;
        }
        .tab-active {
            border-bottom: 2px solid #800000;
            color: #800000;
        }
        .tab-inactive {
            color: #6b7280;
            border-bottom: 2px solid transparent;
        }
        .tab-inactive:hover {
            color: #800000;
            border-bottom-color: #800000;
        }
    </style>
</head>
<body class="bg-background font-body-md text-on-background">
<div class="auth-bg-gradient min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-container-padding">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="font-headline-lg text-headline-lg text-maroon-accent">Account Settings</h1>
                    <p class="font-body-md text-body-md text-on-surface-variant mt-1">Manage your account settings and preferences</p>
                </div>
                <a href="landing_page.php" class="flex items-center gap-2 px-4 py-2 bg-surface border border-outline-variant rounded-lg text-on-surface-variant hover:text-secondary transition-all">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    Back to Dashboard
                </a>
            </div>
            <div class="h-1 w-full header-accent-gradient mt-4 rounded-full"></div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center gap-3 <?= $message_type == 'success' ? 'bg-primary-container text-on-primary-container border-l-4 border-primary' : 'bg-error-container text-on-error-container border-l-4 border-error' ?>">
                <span class="material-symbols-outlined"><?= $message_type == 'success' ? 'check_circle' : 'error' ?></span>
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex border-b border-outline-variant mb-8">
            <a href="?tab=profile" class="px-6 py-3 text-sm font-medium transition-all <?= $active_tab == 'profile' ? 'tab-active' : 'tab-inactive' ?>">
                <span class="material-symbols-outlined text-base align-middle mr-2">person</span>
                Profile Settings
            </a>
            <a href="?tab=password" class="px-6 py-3 text-sm font-medium transition-all <?= $active_tab == 'password' ? 'tab-active' : 'tab-inactive' ?>">
                <span class="material-symbols-outlined text-base align-middle mr-2">lock</span>
                Change Password
            </a>
        </div>

        <!-- Profile Settings Tab -->
        <?php if ($active_tab == 'profile'): ?>
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden settings-card border border-outline-variant">
            <div class="p-6 border-b border-outline-variant bg-surface">
                <h2 class="font-headline-md text-lg text-on-surface">Profile Information</h2>
                <p class="text-sm text-on-surface-variant mt-1">Update your account profile information</p>
            </div>
            
            <form method="POST" action="" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Full Name *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">badge</span>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars($user_data['full_name'] ?? '') ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Username *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">person</span>
                            <input type="text" name="username" required value="<?= htmlspecialchars($user_data['username'] ?? '') ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Email Address</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">mail</span>
                            <input type="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        </div>
                        <p class="text-xs text-on-surface-variant mt-1">Your email address is used for account recovery</p>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Phone Number</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">call</span>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Company</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">business</span>
                            <input type="text" name="company" value="<?= htmlspecialchars($user_data['company'] ?? '') ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Subscription Plan</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">card_membership</span>
                            <input type="text" value="<?= ucfirst(str_replace('_', ' ', $user_data['subscription_type'] ?? 'Free')) ?>" disabled 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-on-surface-variant opacity-75">
                        </div>
                        <p class="text-xs text-on-surface-variant mt-1">Contact support to change your subscription plan</p>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Account Status</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">verified</span>
                            <input type="text" value="<?= ucfirst($user_data['status'] ?? 'Active') ?>" disabled 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-on-surface-variant opacity-75">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Member Since</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">calendar_today</span>
                            <input type="text" value="<?= isset($user_data['registration_date']) && $user_data['registration_date'] ? date('F j, Y', strtotime($user_data['registration_date'])) : 'N/A' ?>" disabled 
                                   class="w-full pl-10 pr-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-on-surface-variant opacity-75">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end pt-4 border-t border-outline-variant">
                    <button type="submit" name="update_profile" class="px-6 py-2.5 bg-secondary text-white rounded-lg hover:bg-[#8a2201] transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Change Password Tab -->
        <?php if ($active_tab == 'password'): ?>
        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden settings-card border border-outline-variant">
            <div class="p-6 border-b border-outline-variant bg-surface">
                <h2 class="font-headline-md text-lg text-on-surface">Change Password</h2>
                <p class="text-sm text-on-surface-variant mt-1">Update your password to keep your account secure</p>
            </div>
            
            <form method="POST" action="" class="p-6 space-y-6" id="passwordForm">
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Current Password *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">lock</span>
                        <input type="password" name="current_password" id="current_password" required 
                               class="w-full pl-10 pr-12 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        <button type="button" onclick="togglePassword('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-secondary">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2">New Password *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">lock_open</span>
                        <input type="password" name="new_password" id="new_password" required 
                               class="w-full pl-10 pr-12 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-secondary">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                    <div class="mt-2">
                        <div class="h-1.5 bg-outline-variant/30 rounded-full overflow-hidden">
                            <div class="password-strength-bar h-full w-0 rounded-full" id="passwordStrengthBar"></div>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-1" id="passwordHint">Password must be at least 6 characters long</p>
                    </div>
                </div>
                
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Confirm New Password *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">lock_reset</span>
                        <input type="password" name="confirm_password" id="confirm_password" required 
                               class="w-full pl-10 pr-12 py-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-secondary">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                    <p class="text-xs mt-1" id="matchMessage"></p>
                </div>
                
                <div class="flex justify-end pt-4 border-t border-outline-variant">
                    <button type="submit" name="update_password" class="px-6 py-2.5 bg-secondary text-white rounded-lg hover:bg-[#8a2201] transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">key</span>
                        Update Password
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Info Card -->
        <div class="mt-8 bg-primary-container/20 rounded-lg p-4 border border-primary-container/30">
            <div class="flex gap-3">
                <span class="material-symbols-outlined text-primary">info</span>
                <div class="text-sm text-on-surface-variant">
                    <p class="font-medium text-primary">Security Tips:</p>
                    <ul class="list-disc list-inside mt-1 space-y-1">
                        <li>Use a strong, unique password that you don't use elsewhere</li>
                        <li>Enable two-factor authentication for additional security (coming soon)</li>
                        <li>Never share your password with anyone</li>
                        <li>If you suspect unauthorized access, change your password immediately</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('.material-symbols-outlined');
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        field.type = 'password';
        icon.textContent = 'visibility';
    }
}

// Password strength indicator
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const strengthBar = document.getElementById('passwordStrengthBar');
const passwordHint = document.getElementById('passwordHint');
const matchMessage = document.getElementById('matchMessage');

if (newPassword) {
    newPassword.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 6) strength += 20;
        if (password.length >= 10) strength += 20;
        if (/[A-Z]/.test(password)) strength += 20;
        if (/[0-9]/.test(password)) strength += 20;
        if (/[^A-Za-z0-9]/.test(password)) strength += 20;
        
        strength = Math.min(strength, 100);
        strengthBar.style.width = strength + '%';
        
        if (strength < 40) {
            strengthBar.style.background = '#dc3545';
            passwordHint.innerHTML = 'Weak password - add more characters, numbers, and symbols';
            passwordHint.style.color = '#dc3545';
        } else if (strength < 70) {
            strengthBar.style.background = '#ffc107';
            passwordHint.innerHTML = 'Medium password - good, but could be stronger';
            passwordHint.style.color = '#856404';
        } else {
            strengthBar.style.background = '#28a745';
            passwordHint.innerHTML = 'Strong password!';
            passwordHint.style.color = '#155724';
        }
        
        checkPasswordMatch();
    });
}

function checkPasswordMatch() {
    if (confirmPassword && confirmPassword.value) {
        if (newPassword.value === confirmPassword.value) {
            matchMessage.innerHTML = '✓ Passwords match';
            matchMessage.style.color = '#28a745';
        } else {
            matchMessage.innerHTML = '✗ Passwords do not match';
            matchMessage.style.color = '#dc3545';
        }
    } else {
        matchMessage.innerHTML = '';
    }
}

if (confirmPassword) {
    confirmPassword.addEventListener('input', checkPasswordMatch);
}

// Form submission validation
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        const newPass = newPassword.value;
        const confirmPass = confirmPassword.value;
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match.');
            return false;
        }
        
        if (newPass.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long.');
            return false;
        }
    });
}
</script>
</body>
</html>