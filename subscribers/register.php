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
<html class="light" lang="en">
<head>
<meta charset="UTF-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Register | RATIN Analytics</title>
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
    .package-option {
      transition: all 0.2s ease;
    }
    .package-option.selected {
      border-color: #b22c01 !important;
      background-color: rgba(178, 44, 1, 0.05);
    }
    /* Custom scrollbar for package features */
    .package-features ul {
      padding-left: 1rem;
      margin-bottom: 0;
    }
    .package-features li {
      margin-bottom: 0.25rem;
      font-size: 0.75rem;
    }
</style>
</head>
<body class="bg-background font-body-md text-on-background min-h-screen flex items-center justify-center auth-bg-gradient">
<main class="w-full max-w-[900px] px-container-padding py-12">
<!-- Register Card -->
<div class="bg-surface-container-lowest rounded-xl shadow-[0px_4px_24px_rgba(0,0,0,0.06)] overflow-hidden border border-outline-variant">
<!-- Top Accent Bar -->
<div class="h-1.5 w-full bg-secondary"></div>
<div class="p-8 md:p-10">
<!-- Logo Section -->
<div class="flex flex-col items-center mb-8">
<div class="mb-6 flex items-center justify-center">
    <img class="ratin-logo h-14 w-auto object-contain" src="../base/img/Ratin-logo-1.png" alt="RATIN Logo">
</div>
<h1 class="font-headline-md text-headline-md text-on-surface mb-1 text-center">Create Account</h1>
<p class="font-body-md text-body-md text-on-surface-variant text-center">Subscribe to RATIN Trade Analytics to get access to a rich database of trade data across Africa</p>
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

<!-- Registration Form -->
<form method="POST" action="" id="registerForm" class="space-y-5">
<div class="grid md:grid-cols-2 gap-5">
    <!-- Full Name -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="full_name">
            Full Name <span class="text-secondary">*</span>
        </label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">badge</span>
            <input class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="full_name" name="full_name" placeholder="Enter your full name" type="text" 
                   value="<?= $_POST['full_name'] ?? '' ?>" required>
        </div>
    </div>

    <!-- Company -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="company">Company</label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">business</span>
            <input class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="company" name="company" placeholder="Enter your company name" type="text" 
                   value="<?= $_POST['company'] ?? '' ?>">
        </div>
    </div>

    <!-- Username -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="username">
            Username <span class="text-secondary">*</span>
        </label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">person</span>
            <input class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="username" name="username" placeholder="Choose a username" type="text" 
                   value="<?= $_POST['username'] ?? '' ?>" required>
        </div>
    </div>

    <!-- Email -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="email">
            Email <span class="text-secondary">*</span>
        </label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">mail</span>
            <input class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="email" name="email" placeholder="Enter your email address" type="email" 
                   value="<?= $_POST['email'] ?? '' ?>" required>
        </div>
    </div>

    <!-- Phone -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="phone">Phone</label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">call</span>
            <input class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="phone" name="phone" placeholder="Enter your phone number" type="tel" 
                   value="<?= $_POST['phone'] ?? '' ?>">
        </div>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-5">
    <!-- Password -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="password">
            Password <span class="text-secondary">*</span>
        </label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">lock</span>
            <input class="w-full pl-12 pr-12 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="password" name="password" placeholder="Create a password" type="password" required>
            <button class="password-toggle-btn absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors" type="button" id="togglePassword">
                <span class="material-symbols-outlined">visibility</span>
            </button>
        </div>
        <p class="text-xs text-on-surface-variant mt-1">Minimum 6 characters</p>
    </div>

    <!-- Confirm Password -->
    <div class="space-y-2">
        <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="confirm_password">
            Confirm Password <span class="text-secondary">*</span>
        </label>
        <div class="relative group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant transition-colors group-focus-within:text-secondary">lock</span>
            <input class="w-full pl-12 pr-12 py-3 bg-surface border border-outline-variant rounded-lg text-body-md transition-all outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" 
                   id="confirm_password" name="confirm_password" placeholder="Confirm your password" type="password" required>
            <button class="password-toggle-btn absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors" type="button" id="toggleConfirmPassword">
                <span class="material-symbols-outlined">visibility</span>
            </button>
        </div>
    </div>
</div>

<!-- Subscription Packages -->
<div class="space-y-3">
    <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block">
        Subscription Package <span class="text-secondary">*</span>
    </label>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($packages as $package): 
            $features = json_decode($package['features'], true);
        ?>
            <div class="package-option border border-outline-variant rounded-xl p-4 cursor-pointer transition-all hover:border-secondary" onclick="selectPackage('<?= $package['code'] ?>', this)">
                <div class="font-headline-md text-lg text-primary mb-1"><?= htmlspecialchars($package['name']) ?></div>
                <div class="text-2xl font-bold text-secondary mb-3">$<?= number_format($package['price'], 2) ?></div>
                <div class="package-features">
                    <ul class="text-xs text-on-surface-variant space-y-1">
                        <?php foreach ($features as $feature): ?>
                            <li class="flex items-start gap-1">
                                <span class="material-symbols-outlined text-xs text-secondary">check_circle</span>
                                <span><?= htmlspecialchars($feature) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="subscription_type" id="subscription_type" required>
</div>

<!-- Submit Button -->
<button type="submit" name="register" id="registerBtn" class="w-full text-on-secondary font-bold py-3.5 px-6 rounded-lg shadow-sm hover:shadow-md transition-all active:scale-[0.98] focus:ring-4 focus:ring-secondary/20 bg-secondary hover:bg-[#8a2201] mt-6">
    <span class="btn-text">Create Account</span>
</button>

<!-- Login Link -->
<div class="mt-6 text-center">
    <a class="font-body-md text-body-md font-semibold transition-colors text-secondary hover:text-[#600000]" href="index.php">Already have an account? Login</a>
</div>

<!-- Footer Info -->
<div class="mt-8 pt-6 border-t border-outline-variant/50 text-center">
    <p class="font-label-md text-label-md text-on-surface-variant opacity-60">
        © <?= date('Y') ?> RATIN Trade Analytics All rights reserved.
    </p>
</div>
</form>
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
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    
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
    
    // Toggle confirm password visibility
    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            const iconSpan = this.querySelector('.material-symbols-outlined');
            if (iconSpan) {
                iconSpan.textContent = type === 'password' ? 'visibility' : 'visibility_off';
            }
        });
    }
    
    // Form submission with loading state
    if (registerForm && registerBtn) {
        registerForm.addEventListener('submit', function(e) {
            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
            const subscriptionType = document.getElementById('subscription_type') ? document.getElementById('subscription_type').value : '';
            
            if (password.length < 6) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-6 p-4 bg-error-container text-on-error-container rounded-lg flex items-center gap-3';
                errorDiv.innerHTML = '<span class="material-symbols-outlined text-error">error</span><span class="text-sm font-medium">Password must be at least 6 characters long.</span>';
                const formContainer = registerForm.parentElement;
                const existingError = formContainer.querySelector('.bg-error-container');
                if (existingError) existingError.remove();
                formContainer.insertBefore(errorDiv, registerForm);
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-6 p-4 bg-error-container text-on-error-container rounded-lg flex items-center gap-3';
                errorDiv.innerHTML = '<span class="material-symbols-outlined text-error">error</span><span class="text-sm font-medium">Passwords do not match.</span>';
                const formContainer = registerForm.parentElement;
                const existingError = formContainer.querySelector('.bg-error-container');
                if (existingError) existingError.remove();
                formContainer.insertBefore(errorDiv, registerForm);
                return;
            }
            
            if (!subscriptionType) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-6 p-4 bg-error-container text-on-error-container rounded-lg flex items-center gap-3';
                errorDiv.innerHTML = '<span class="material-symbols-outlined text-error">error</span><span class="text-sm font-medium">Please select a subscription package.</span>';
                const formContainer = registerForm.parentElement;
                const existingError = formContainer.querySelector('.bg-error-container');
                if (existingError) existingError.remove();
                formContainer.insertBefore(errorDiv, registerForm);
                return;
            }
            
            // Show loading state
            registerBtn.classList.add('loading');
            const btnText = registerBtn.querySelector('.btn-text');
            if (btnText) btnText.textContent = 'Creating Account...';
        });
    }
    
    // Focus on first field
    const fullNameInput = document.getElementById('full_name');
    if (fullNameInput) fullNameInput.focus();
    
    // Remove loading state on page load (in case of form errors)
    if (registerBtn) {
        registerBtn.classList.remove('loading');
        const btnText = registerBtn.querySelector('.btn-text');
        if (btnText) btnText.textContent = 'Create Account';
    }
});

function selectPackage(packageCode, element) {
    document.getElementById('subscription_type').value = packageCode;
    
    // Update UI
    document.querySelectorAll('.package-option').forEach(option => {
        option.classList.remove('selected');
        option.style.borderColor = '';
        option.style.backgroundColor = '';
    });
    
    element.classList.add('selected');
    element.style.borderColor = '#b22c01';
    element.style.backgroundColor = 'rgba(178, 44, 1, 0.05)';
}

// Select first package by default
document.addEventListener('DOMContentLoaded', function() {
    const firstPackage = document.querySelector('.package-option');
    if (firstPackage) {
        const packageNameElement = firstPackage.querySelector('.font-headline-md');
        if (packageNameElement) {
            const packageCode = packageNameElement.textContent.trim().toLowerCase();
            selectPackage(packageCode, firstPackage);
        }
    }
});
</script>
</body>
</html>