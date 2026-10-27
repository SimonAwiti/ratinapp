<?php
// reset_password.php - email OTP password reset
session_start();

if (file_exists('includes/config.php')) {
    include 'includes/config.php';
}
require_once __DIR__ . '/../includes/Mailer.php';   // Microsoft Graph sendMail()

// ---- Settings ----
const OTP_TTL_MINUTES   = 10;   // code validity
const OTP_MAX_ATTEMPTS  = 5;    // wrong guesses before the code is dead
const OTP_MAX_REQUESTS  = 3;    // codes per account per window
const OTP_WINDOW_MIN    = 15;
const MIN_PASSWORD_LEN  = 8;

$error_message   = '';
$success_message = '';
$otp_step        = isset($_SESSION['reset_pending']) && $_SESSION['reset_pending'] === true;
$redirect        = false;

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_ok(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function send_otp_email(string $to, string $name, string $otp): void {
    $name = htmlspecialchars($name);
    $mins = OTP_TTL_MINUTES;
    $html = "
    <div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#1a1c1c\">
      <h2 style=\"color:#00450d\">RATIN Analytics</h2>
      <p>Hello {$name},</p>
      <p>Use this code to reset your admin password:</p>
      <p style=\"font-size:32px;letter-spacing:8px;font-weight:bold;background:#f3f3f3;padding:16px;text-align:center;border-radius:6px\">{$otp}</p>
      <p>It expires in {$mins} minutes. If you did not request this, you can ignore this email; your password has not changed.</p>
      <p style=\"color:#717a6d;font-size:12px\">Never share this code with anyone.</p>
    </div>";
    sendMail($to, 'Your RATIN password reset code', $html, "Your RATIN password reset code is {$otp}. It expires in {$mins} minutes.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_ok()) {
        $error_message = "Your session expired. Please try again.";
    } elseif (!isset($con)) {
        $error_message = "Service unavailable. Please try again later.";
    }

    // ---------- Step 1: request a code ----------
    elseif (isset($_POST['send_otp'])) {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {
            $error_message = "Please enter both username and email address.";
        } else {
            $stmt = $con->prepare("SELECT id, email, full_name FROM admin_users WHERE username = ? AND email = ? AND status = 'active'");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Same outcome whether or not the account exists (no account enumeration)
            $_SESSION['reset_pending']  = true;
            $_SESSION['reset_admin_id'] = $user ? (int)$user['id'] : 0;
            $_SESSION['reset_email']    = $email;

            if ($user) {
                $admin_id = (int)$user['id'];

                // Throttle: max N codes per window
                $win = OTP_WINDOW_MIN;
                $q = $con->prepare("SELECT COUNT(*) FROM password_resets WHERE admin_id = ? AND created_at > (NOW() - INTERVAL {$win} MINUTE)");
                $q->bind_param("i", $admin_id);
                $q->execute();
                $q->bind_result($recent);
                $q->fetch();
                $q->close();

                if ($recent < OTP_MAX_REQUESTS) {
                    // Invalidate older unused codes
                    $inv = $con->prepare("UPDATE password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL");
                    $inv->bind_param("i", $admin_id);
                    $inv->execute();
                    $inv->close();

                    $otp  = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $hash = password_hash($otp, PASSWORD_DEFAULT);
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
                    $ttl  = OTP_TTL_MINUTES;

                    $ins = $con->prepare("INSERT INTO password_resets (admin_id, otp_hash, expires_at, request_ip) VALUES (?, ?, NOW() + INTERVAL {$ttl} MINUTE, ?)");
                    $ins->bind_param("iss", $admin_id, $hash, $ip);
                    $ins->execute();
                    $ins->close();

                    try {
                        send_otp_email($user['email'], $user['full_name'] ?: $username, $otp);
                    } catch (Throwable $e) {
                        error_log('RATIN OTP mail failed: ' . $e->getMessage());
                    }
                }
            }

            $otp_step        = true;
            $success_message = "If the details match an active account, a 6-digit code has been sent to that email address.";
        }
    }

    // ---------- Step 2: verify code + set new password ----------
    elseif (isset($_POST['update_password'])) {
        $otp_step   = true;
        $admin_id   = (int)($_SESSION['reset_admin_id'] ?? 0);
        $otp        = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $new        = $_POST['new_password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';

        if (!($_SESSION['reset_pending'] ?? false)) {
            $error_message = "Please request a new code.";
            $otp_step = false;
        } elseif (strlen($otp) !== 6) {
            $error_message = "Enter the 6-digit code from your email.";
        } elseif (strlen($new) < MIN_PASSWORD_LEN) {
            $error_message = "Password must be at least " . MIN_PASSWORD_LEN . " characters long.";
        } elseif ($new !== $confirm) {
            $error_message = "Passwords do not match.";
        } else {
            $generic = "Invalid or expired code. Please check the code or request a new one.";
            $row = null;

            if ($admin_id > 0) {
                $s = $con->prepare("SELECT id, otp_hash, attempts FROM password_resets
                                    WHERE admin_id = ? AND used_at IS NULL AND expires_at > NOW()
                                    ORDER BY id DESC LIMIT 1");
                $s->bind_param("i", $admin_id);
                $s->execute();
                $row = $s->get_result()->fetch_assoc();
                $s->close();
            }

            if (!$row || $row['attempts'] >= OTP_MAX_ATTEMPTS) {
                $error_message = $generic;
            } else {
                // Count the attempt before checking, so guesses can't be raced
                $a = $con->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?");
                $a->bind_param("i", $row['id']);
                $a->execute();
                $a->close();

                if (password_verify($otp, $row['otp_hash'])) {
                    $pw = password_hash($new, PASSWORD_DEFAULT);

                    $con->begin_transaction();
                    $u = $con->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                    $u->bind_param("si", $pw, $admin_id);
                    $ok1 = $u->execute();
                    $u->close();

                    $m = $con->prepare("UPDATE password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL");
                    $m->bind_param("i", $admin_id);
                    $ok2 = $m->execute();
                    $m->close();

                    if ($ok1 && $ok2) {
                        $con->commit();
                        unset($_SESSION['reset_pending'], $_SESSION['reset_admin_id'], $_SESSION['reset_email']);
                        session_regenerate_id(true);
                        $otp_step        = false;
                        $redirect        = true;
                        $success_message = "Password reset successfully! Redirecting to login...";
                    } else {
                        $con->rollback();
                        $error_message = "Failed to update password. Please try again.";
                    }
                } else {
                    $error_message = $generic;
                }
            }
        }
    }

    // ---------- Start over ----------
    elseif (isset($_POST['restart'])) {
        unset($_SESSION['reset_pending'], $_SESSION['reset_admin_id'], $_SESSION['reset_email']);
        $otp_step = false;
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Reset Your Password - RATIN Analytics</title>
<?php if ($redirect): ?><meta http-equiv="refresh" content="2;url=login.php"><?php endif; ?>
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
        body { background-color: #F5F5F5; font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .card-shadow { box-shadow: 0px 4px 12px rgba(0,0,0,0.05); }
        .header-gradient { background: linear-gradient(135deg, #f3f3f3 0%, #e2e2e2 100%); }
        .otp-input { letter-spacing: 0.6em; text-align: center; font-size: 24px; font-weight: 700; }
    </style>
</head>
<body class="flex flex-col min-h-screen">
<header class="bg-surface border-b border-outline-variant flex justify-between items-center w-full px-container-padding py-base fixed top-0 z-50">
<div class="text-headline-md font-headline-md font-bold text-primary">RATIN Analytics</div>
</header>
<main class="flex-grow flex items-center justify-center px-container-padding py-24">
<div class="w-full max-w-[480px]">
<div class="bg-white rounded-xl card-shadow overflow-hidden">
<div class="h-32 relative header-gradient overflow-hidden border-b border-outline-variant">
<div class="absolute inset-0 flex items-center justify-center">
<div class="bg-maroon-accent/5 p-4 rounded-full border border-maroon-accent/10">
<span class="material-symbols-outlined text-maroon-accent text-4xl"><?= $otp_step ? 'mark_email_read' : 'lock_reset' ?></span>
</div>
</div>
<div class="absolute top-0 right-0 w-24 h-full bg-secondary-container/10 -skew-x-12 transform translate-x-12"></div>
<div class="absolute top-0 left-0 w-1 h-full bg-maroon-accent"></div>
</div>
<div class="p-8">
<div class="mb-8 text-center">
<h1 class="font-headline-lg text-headline-lg text-on-surface mb-2">Reset Your Password</h1>
<p class="font-body-md text-body-md text-on-surface-variant">
<?= $otp_step
    ? 'Enter the 6-digit code we emailed you and choose a new password.'
    : 'Enter your username and email and we will send you a one-time code.' ?>
</p>
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
<?php endif; ?>

<form method="POST" action="" class="space-y-6" autocomplete="off">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

<?php if (!$otp_step): ?>
<!-- Step 1: request code -->
<div class="space-y-4">
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="username">Username</label>
<div class="relative">
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-secondary text-lg">person</span>
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
<span class="material-symbols-outlined text-secondary text-lg">mail</span>
</div>
<input class="w-full pl-10 pr-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md"
       id="email" name="email" placeholder="Enter your email address" type="email"
       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required/>
</div>
</div>
<button type="submit" name="send_otp" class="w-full py-4 bg-maroon-accent text-white font-label-md text-label-md rounded-lg hover:bg-[#660000] active:scale-[0.98] transition-all uppercase tracking-wider flex justify-center items-center gap-2 shadow-sm">
    Send Code
    <span class="material-symbols-outlined text-lg">send</span>
</button>
</div>

<?php elseif (!$redirect): ?>
<!-- Step 2: OTP + new password -->
<div class="space-y-4">
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="otp">Verification Code</label>
<input class="otp-input w-full px-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all"
       id="otp" name="otp" placeholder="••••••" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required/>
<p class="text-xs text-on-surface-variant mt-1">Code expires in <?= OTP_TTL_MINUTES ?> minutes.</p>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="new_password">New Password</label>
<input class="w-full px-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md"
       id="new_password" name="new_password" placeholder="Min. <?= MIN_PASSWORD_LEN ?> characters" type="password" minlength="<?= MIN_PASSWORD_LEN ?>" autocomplete="new-password" required/>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1" for="confirm_password">Confirm New Password</label>
<input class="w-full px-4 py-3 bg-surface rounded-lg border border-outline-variant focus:border-maroon-accent focus:ring-1 focus:ring-maroon-accent outline-none transition-all font-body-md text-body-md"
       id="confirm_password" name="confirm_password" placeholder="Confirm your new password" type="password" autocomplete="new-password" required/>
</div>
<button type="submit" name="update_password" class="w-full py-4 bg-maroon-accent text-white font-label-md text-label-md rounded-lg hover:bg-[#660000] active:scale-[0.98] transition-all uppercase tracking-wider shadow-sm">
    Update Password
</button>
<button type="submit" name="restart" formnovalidate class="w-full text-center text-secondary hover:text-maroon-accent hover:underline font-label-md text-label-md">
    Didn't get a code? Start over
</button>
</div>
<?php endif; ?>
</form>

<div class="mt-8 text-center">
<a class="font-label-md text-label-md text-secondary hover:text-maroon-accent hover:underline flex items-center justify-center gap-1 transition-colors" href="login.php">
<span class="material-symbols-outlined text-base">arrow_back</span>
    Back to Login
</a>
</div>
</div>
</div>
</div>
</main>
<footer class="bg-surface-container border-t border-outline-variant py-base px-container-padding flex flex-col md:flex-row justify-between items-center w-full mt-auto">
<div class="font-label-md text-label-md text-on-surface-variant">© <?= date('Y') ?> RATIN Analytics. All rights reserved.</div>
</footer>
</body>
</html>