<?php
// create_admin.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Only allow 'super_admin' role to create new admins
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("Location: ../base/landing_page.php");
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
    } elseif (empty($role)) {
        $error_message = "Please select an administrative role.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        if (isset($con)) {
            // Check if username already exists
            $stmt = $con->prepare("SELECT id FROM admin_users WHERE username = ?");
            if (!$stmt) {
                $error_message = "Database error: " . $con->error;
            } else {
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

                    if (!$insert_stmt) {
                        $error_message = "Database error: " . $con->error;
                    } else {
                        $insert_stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);

                        if ($insert_stmt->execute()) {
                            $success_message = "Admin account created successfully!";
                            $_POST = array();
                        } else {
                            $error_message = "Error creating admin account: " . $insert_stmt->error;
                        }

                        $insert_stmt->close();
                    }
                }

                $stmt->close();
            }
        } else {
            $error_message = "Database connection error. Please check configuration.";
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Add New Admin User - RATIN Analytics</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "surface-container-lowest": "#ffffff",
                    "surface-container-highest": "#e2e2e2",
                    "surface-container": "#eeeeee",
                    "primary-container": "#1b5e20",
                    "on-tertiary-fixed-variant": "#004f56",
                    "on-background": "#1a1c1c",
                    "on-secondary-fixed-variant": "#881f00",
                    "inverse-surface": "#2f3131",
                    "surface-tint": "#2a6b2c",
                    "on-error-container": "#93000a",
                    "surface-bright": "#f9f9f9",
                    "surface-container-low": "#f3f3f3",
                    "on-tertiary": "#ffffff",
                    "on-surface-variant": "#41493e",
                    "inverse-primary": "#91d78a",
                    "on-secondary-container": "#5d1200",
                    "error-container": "#ffdad6",
                    "tertiary": "#004248",
                    "surface-variant": "#e2e2e2",
                    "on-primary-fixed-variant": "#0c5216",
                    "secondary-container": "#ff6338",
                    "on-secondary": "#ffffff",
                    "on-primary": "#ffffff",
                    "secondary": "#b22c01",
                    "primary-fixed": "#acf4a4",
                    "on-tertiary-fixed": "#001f23",
                    "secondary-fixed-dim": "#ffb5a1",
                    "background": "#f9f9f9",
                    "on-secondary-fixed": "#3b0800",
                    "on-primary-container": "#90d689",
                    "on-tertiary-container": "#73d4e0",
                    "on-surface": "#1a1c1c",
                    "tertiary-fixed-dim": "#75d5e2",
                    "surface": "#f9f9f9",
                    "on-primary-fixed": "#002203",
                    "error": "#ba1a1a",
                    "primary": "#00450d",
                    "outline": "#717a6d",
                    "outline-variant": "#c0c9bb",
                    "secondary-fixed": "#ffdbd1",
                    "surface-container-high": "#e8e8e8",
                    "inverse-on-surface": "#f1f1f1",
                    "on-error": "#ffffff",
                    "primary-fixed-dim": "#91d78a",
                    "tertiary-container": "#005b64",
                    "tertiary-fixed": "#92f1fe",
                    "surface-dim": "#dadada",
                    "maroon-accent": "#800000"
                },
                "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
                },
                "spacing": {
                    "container-padding": "24px",
                    "base": "8px",
                    "gutter": "16px",
                    "sidebar-width": "260px",
                    "card-gap": "20px"
                },
                "fontFamily": {
                    "headline-lg-mobile": ["Inter"],
                    "data-tabular": ["Inter"],
                    "body-md": ["Inter"],
                    "label-md": ["Inter"],
                    "body-lg": ["Inter"],
                    "headline-md": ["Inter"],
                    "headline-lg": ["Inter"]
                },
                "fontSize": {
                    "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                    "data-tabular": ["13px", {"lineHeight": "18px", "fontWeight": "400"}],
                    "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "600"}],
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "headline-md": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}]
                }
            },
        }
    }
</script>
<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    body {
        background-color: #f5f5f5;
        font-family: 'Inter', sans-serif;
    }
    .form-card {
        box-shadow: 0px 4px 32px rgba(0,0,0,0.08);
    }
    .auth-bg-gradient {
        background: radial-gradient(circle at top left, rgba(0, 69, 13, 0.05), transparent),
                    radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.05), transparent);
    }
    .header-accent-gradient {
        background: linear-gradient(90deg, #00450d 0%, #800000 50%, #00450d 100%);
    }
    .password-strength-bar {
        transition: width 0.3s, background 0.3s;
    }
    input:focus, select:focus {
        outline: none;
    }
</style>
</head>
<body class="bg-surface text-on-surface min-h-screen flex items-center justify-center auth-bg-gradient">
<main class="flex-grow flex items-center justify-center px-container-padding py-12 relative z-10">
<div class="w-full max-w-2xl bg-surface-container-lowest rounded-xl border border-outline-variant/30 form-card overflow-hidden">
    <div class="h-1.5 w-full header-accent-gradient"></div>

    <div class="p-8 md:p-10">
        <div class="mb-6">
            <a href="manage_admin.php" class="inline-flex items-center gap-2 text-on-surface-variant hover:text-maroon-accent transition-colors font-body-md">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Manage Admins
            </a>
        </div>

        <div class="flex flex-col items-center mb-8 text-center">
            <div class="p-4 bg-primary/5 rounded-full border border-primary/10 mb-6">
                <span class="material-symbols-outlined text-primary text-4xl">person_add</span>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">Add New Admin User</h2>
            <p class="font-body-md text-body-md text-on-surface-variant max-w-md">Create a new administrative account with specific permissions for the RATIN Analytics platform.</p>
        </div>

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
            <div class="text-center">
                <a href="../base/landing_page.php" class="inline-flex items-center gap-2 px-6 py-3 bg-maroon-accent text-white rounded-lg hover:bg-[#660000] transition-colors">
                    <span class="material-symbols-outlined text-lg">dashboard</span>
                    Go to Dashboard
                </a>
            </div>
        <?php else: ?>
        <form method="POST" action="" id="adminForm">
            <!-- FIX: Hidden field ensures create_admin is always in POST regardless of button state -->
            <input type="hidden" name="create_admin" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block px-1">Username <span class="text-error">*</span></label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline transition-colors group-focus-within:text-primary">alternate_email</span>
                        <input class="w-full pl-12 pr-4 py-3.5 bg-surface border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md outline-none"
                               name="username" id="username" placeholder="e.g. jdoe_admin" type="text"
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block px-1">Full Name <span class="text-error">*</span></label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline transition-colors group-focus-within:text-primary">badge</span>
                        <input class="w-full pl-12 pr-4 py-3.5 bg-surface border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md outline-none"
                               name="full_name" id="full_name" placeholder="John Doe" type="text"
                               value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>" required>
                    </div>
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block px-1">Email Address</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline transition-colors group-focus-within:text-primary">mail</span>
                        <input class="w-full pl-12 pr-4 py-3.5 bg-surface border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md outline-none"
                               name="email" id="email" placeholder="john.doe@ratin.com" type="email"
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block px-1">Administrative Role <span class="text-error">*</span></label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline transition-colors group-focus-within:text-primary">shield_person</span>
                        <select class="w-full pl-12 pr-10 py-3.5 bg-surface border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md appearance-none outline-none"
                                name="role" id="role" required>
                            <option value="">Select a role...</option>
                            <option value="super_admin" <?= (isset($_POST['role']) && $_POST['role'] === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="content_manager" <?= (isset($_POST['role']) && $_POST['role'] === 'content_manager') ? 'selected' : '' ?>>Content Manager</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline pointer-events-none">expand_more</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block px-1">Password <span class="text-error">*</span></label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline transition-colors group-focus-within:text-primary">lock</span>
                        <input class="w-full pl-12 pr-12 py-3.5 bg-surface border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md outline-none"
                               name="password" id="password" placeholder="••••••••" type="password" required minlength="8">
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline cursor-pointer hover:text-primary transition-colors" id="togglePassword">visibility</span>
                    </div>
                    <div class="mt-1 h-1 bg-outline-variant/30 rounded-full overflow-hidden">
                        <div class="password-strength-bar h-full w-0 rounded-full" id="passwordStrengthBar"></div>
                    </div>
                    <p class="text-xs text-on-surface-variant mt-1">Password must be at least 8 characters long</p>
                </div>

                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block px-1">Confirm Password <span class="text-error">*</span></label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline transition-colors group-focus-within:text-primary">lock_reset</span>
                        <input class="w-full pl-12 pr-12 py-3.5 bg-surface border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md outline-none"
                               name="confirm_password" id="confirm_password" placeholder="••••••••" type="password" required minlength="8">
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-outline cursor-pointer hover:text-primary transition-colors" id="toggleConfirmPassword">visibility</span>
                    </div>
                </div>
            </div>

            <div class="p-5 bg-surface border border-outline-variant/30 rounded-xl flex gap-4 mt-6">
                <span class="material-symbols-outlined text-primary">info</span>
                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed text-sm">
                    Password must contain at least 8 characters, including a capital letter, a number, and a special character.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-6">
                <button class="w-full sm:w-auto px-10 py-4 font-bold text-white bg-maroon-accent rounded-lg shadow-sm hover:bg-[#660000] active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer"
                        type="submit" id="createBtn">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    Create User
                </button>
                <a href="../base/landing_page.php" class="w-full sm:w-auto px-10 py-4 font-label-md text-label-md text-on-surface-variant border border-outline-variant rounded-lg hover:bg-surface transition-all text-center">
                    Cancel
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="p-6 border-t border-outline-variant/30 bg-surface-container-low text-center">
        <p class="font-label-md text-label-md text-on-surface-variant opacity-60">
            © <?= date('Y') ?> RATIN Analytics Data &amp; Logistics Portal.
        </p>
    </div>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const adminForm = document.getElementById('adminForm');
    const createBtn = document.getElementById('createBtn');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar');

    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
    }

    if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            const password = this.value;
            let strength = 0;
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 25;
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            strength = Math.min(strength, 100);
            passwordStrengthBar.style.width = strength + '%';
            if (strength < 50) {
                passwordStrengthBar.style.background = '#dc3545';
            } else if (strength < 75) {
                passwordStrengthBar.style.background = '#ffc107';
            } else {
                passwordStrengthBar.style.background = '#28a745';
            }
        });
    }

    if (adminForm) {
        adminForm.addEventListener('submit', function (e) {
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

            // FIX: Only disable AFTER submit goes through — button state no longer
            // affects the hidden field that carries the create_admin flag.
            if (createBtn) {
                createBtn.disabled = true;
                createBtn.innerHTML = '<span class="material-symbols-outlined text-xl">progress_activity</span> Creating...';
            }
        });
    }
});
</script>
</body>
</html>