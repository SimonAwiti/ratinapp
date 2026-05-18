<?php
// user_settings.php
require_once '../admin/includes/admin_header.php';

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}

$user_id = $_SESSION['admin_id'];
$message = '';
$message_type = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Fetch current user data
$user_query = $con->prepare("SELECT username, full_name, email, role, created_at FROM admin_users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    if (empty($full_name) || empty($username)) {
        $message = "Full name and username are required.";
        $message_type = "error";
    } else {
        // Check if username already exists (excluding current user)
        $check_stmt = $con->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "Username already exists. Please choose another.";
            $message_type = "error";
        } else {
            $update_stmt = $con->prepare("UPDATE admin_users SET full_name = ?, username = ?, email = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $full_name, $username, $email, $user_id);
            
            if ($update_stmt->execute()) {
                // Update session variables
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['admin_username'] = $username;
                
                $message = "Profile updated successfully!";
                $message_type = "success";
                
                // Refresh user data
                $user_data['full_name'] = $full_name;
                $user_data['username'] = $username;
                $user_data['email'] = $email;
            } else {
                $message = "Error updating profile: " . $update_stmt->error;
                $message_type = "error";
            }
            $update_stmt->close();
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
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = "error";
    } else {
        // Verify current password
        $pass_stmt = $con->prepare("SELECT password FROM admin_users WHERE id = ?");
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $pass_result = $pass_stmt->get_result();
        $pass_data = $pass_result->fetch_assoc();
        
        if (password_verify($current_password, $pass_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_stmt = $con->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $update_pass_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_pass_stmt->execute()) {
                $message = "Password updated successfully!";
                $message_type = "success";
                $active_tab = 'password'; // Stay on password tab
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

<style>
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

<div class="auth-bg-gradient -m-4 -mt-20 p-4 pt-24 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-maroon">User Settings</h1>
                    <p class="text-gray-600 mt-1">Manage your account settings and preferences</p>
                </div>
            </div>
            <div class="h-1 w-full header-accent-gradient mt-4 rounded-full"></div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center gap-3 <?= $message_type == 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-600' : 'bg-red-100 text-red-700 border-l-4 border-red-600' ?>">
                <span class="material-symbols-outlined"><?= $message_type == 'success' ? 'check_circle' : 'error' ?></span>
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 mb-8">
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
        <div class="bg-white rounded-xl shadow-sm overflow-hidden settings-card">
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Profile Information</h2>
                <p class="text-sm text-gray-500 mt-1">Update your account profile information</p>
            </div>
            
            <form method="POST" action="" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">badge</span>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars($user_data['full_name']) ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">person</span>
                            <input type="text" name="username" required value="<?= htmlspecialchars($user_data['username']) ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">mail</span>
                            <input type="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" 
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Your email address is used for account recovery</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">shield</span>
                            <input type="text" value="<?= ucfirst(str_replace('_', ' ', $user_data['role'])) ?>" disabled 
                                   class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg text-gray-600">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Role cannot be changed here. Contact super admin for role changes.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Member Since</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">calendar_today</span>
                            <input type="text" value="<?= date('F j, Y', strtotime($user_data['created_at'])) ?>" disabled 
                                   class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg text-gray-600">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end pt-4 border-t border-gray-200">
                    <button type="submit" name="update_profile" class="px-6 py-2.5 bg-maroon text-white rounded-lg hover:bg-[#660000] transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Change Password Tab -->
        <?php if ($active_tab == 'password'): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden settings-card">
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Change Password</h2>
                <p class="text-sm text-gray-500 mt-1">Update your password to keep your account secure</p>
            </div>
            
            <form method="POST" action="" class="p-6 space-y-6" id="passwordForm">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">lock</span>
                        <input type="password" name="current_password" id="current_password" required 
                               class="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                        <button type="button" onclick="togglePassword('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">lock_open</span>
                        <input type="password" name="new_password" id="new_password" required 
                               class="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                        <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                    <div class="mt-2">
                        <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                            <div class="password-strength-bar h-full w-0 rounded-full" id="passwordStrengthBar"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" id="passwordHint">Password must be at least 8 characters long</p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">lock_reset</span>
                        <input type="password" name="confirm_password" id="confirm_password" required 
                               class="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                        <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1" id="matchMessage"></p>
                </div>
                
                <div class="flex justify-end pt-4 border-t border-gray-200">
                    <button type="submit" name="update_password" class="px-6 py-2.5 bg-maroon text-white rounded-lg hover:bg-[#660000] transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">key</span>
                        Update Password
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Info Card -->
        <div class="mt-8 bg-blue-50 rounded-lg p-4 border border-blue-200">
            <div class="flex gap-3">
                <span class="material-symbols-outlined text-blue-600">info</span>
                <div class="text-sm text-blue-800">
                    <p class="font-medium">Security Tips:</p>
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
        
        if (password.length >= 8) strength += 25;
        if (password.length >= 12) strength += 25;
        if (/[A-Z]/.test(password)) strength += 15;
        if (/[0-9]/.test(password)) strength += 15;
        if (/[^A-Za-z0-9]/.test(password)) strength += 20;
        
        strength = Math.min(strength, 100);
        strengthBar.style.width = strength + '%';
        
        if (strength < 50) {
            strengthBar.style.background = '#dc3545';
            passwordHint.innerHTML = 'Weak password - add more characters, numbers, and symbols';
            passwordHint.style.color = '#dc3545';
        } else if (strength < 75) {
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
        
        if (newPass.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long.');
            return false;
        }
    });
}
</script>

<?php require_once '../admin/includes/admin_footer.php'; ?>