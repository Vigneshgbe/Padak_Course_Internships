<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Step tracking: 'email' | 'verify_code' | 'reset_password'
$step = $_GET['step'] ?? 'email';
$errors = [];
$formData = ['email' => '', 'code' => '', 'new_password' => '', 'confirm_password' => ''];
$successMessage = '';
$generalError = '';
$info = '';

// Store email in session for verification steps
if (isset($_SESSION['reset_email'])) {
    $formData['email'] = $_SESSION['reset_email'];
}

// ==================== STEP 1: REQUEST RESET ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    $email = trim($_POST['email'] ?? '');
    $formData['email'] = htmlspecialchars($email);

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (empty($errors)) {
        $db = getPadakDB();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id, full_name FROM internship_students WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Generate 6-digit verification code
            $code = sprintf('%06d', mt_rand(0, 999999));
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Store reset code
            $updateStmt = $db->prepare("UPDATE internship_students SET reset_code = ?, reset_code_expires = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $codeHash, $expiresAt, $email);
            $updateStmt->execute();

            // In production: Send email with code
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user['id'];

            $successMessage = "Verification code sent to your email!";
            $info = "Demo Mode: Your code is <strong>$code</strong> (In production, this will be sent via email)";
            
            // Move to verification step
            header('Location: forgot_password.php?step=verify_code');
            exit;
        } else {
            // Security: Don't reveal if email exists or not
            $successMessage = "If an account exists with this email, you will receive a verification code shortly.";
            $info = "Please check your email inbox and spam folder.";
        }
    }
}

// ==================== STEP 2: VERIFY CODE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    $code = trim($_POST['code'] ?? '');
    $email = $_SESSION['reset_email'] ?? '';

    if (empty($email)) {
        $generalError = 'Session expired. Please start over.';
        header('Location: forgot_password.php?step=email');
        exit;
    }

    if (empty($code)) {
        $errors['code'] = 'Verification code is required';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $errors['code'] = 'Code must be 6 digits';
    }

    if (empty($errors)) {
        $db = getPadakDB();
        $stmt = $db->prepare("SELECT id, reset_code, reset_code_expires FROM internship_students WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Check if code expired
            if (strtotime($user['reset_code_expires']) < time()) {
                $generalError = 'Verification code has expired. Please request a new one.';
            } elseif (password_verify($code, $user['reset_code'])) {
                // Code is valid - move to password reset
                $_SESSION['reset_verified'] = true;
                $_SESSION['reset_user_id'] = $user['id'];
                header('Location: forgot_password.php?step=reset_password');
                exit;
            } else {
                $errors['code'] = 'Invalid verification code';
            }
        } else {
            $generalError = 'Session error. Please start over.';
        }
    }
}

// ==================== STEP 3: RESET PASSWORD ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!isset($_SESSION['reset_verified']) || !$_SESSION['reset_verified']) {
        $generalError = 'Unauthorized. Please verify your code first.';
        header('Location: forgot_password.php?step=email');
        exit;
    }

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate new password
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($newPassword) < 6) {
        $errors['new_password'] = 'Password must be at least 6 characters';
    } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one letter and one number';
    }

    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (empty($errors)) {
        $db = getPadakDB();
        $userId = $_SESSION['reset_user_id'];
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and clear reset tokens
        $stmt = $db->prepare("UPDATE internship_students SET password = ?, reset_code = NULL, reset_code_expires = NULL, remember_token = NULL, token_expires_at = NULL WHERE id = ?");
        $stmt->bind_param("si", $passwordHash, $userId);
        
        if ($stmt->execute()) {
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_user_id']);

            $successMessage = 'Password reset successful! Redirecting to login...';
            header('Refresh: 2; URL=login.php?reset=success');
        } else {
            $generalError = 'Failed to reset password. Please try again.';
        }
    }
}

// Determine current step for UI
$currentStep = $step;
if (!in_array($currentStep, ['email', 'verify_code', 'reset_password'])) {
    $currentStep = 'email';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --orange-400: #fb923c;
            --orange-500: #f97316;
            --orange-600: #ea580c;
            --orange-700: #c2410c;
            --bg: #ffffff;
            --text: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --red-500: #ef4444;
            --red-50: #fef2f2;
            --red-200: #fecaca;
            --blue-50: #eff6ff;
            --blue-200: #bfdbfe;
            --blue-600: #2563eb;
            --shadow: 0 20px 60px rgba(0,0,0,0.12);
            --shadow-lg: 0 25px 70px rgba(0,0,0,0.18);
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 50%, #ffedd5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            position: relative;
            overflow: hidden;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
            animation: pulse-blob 4s ease-in-out infinite;
        }
        .blob-1 { width: 200px; height: 200px; background: rgba(249,115,22,0.12); top: 5%; left: 3%; animation-delay: 0s; }
        .blob-2 { width: 150px; height: 150px; background: rgba(251,146,60,0.10); bottom: 5%; right: 3%; animation-delay: 1s; }
        .blob-3 { width: 120px; height: 120px; background: rgba(249,115,22,0.06); top: 50%; left: 20%; animation-delay: 0.5s; }

        @keyframes pulse-blob {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }

        .card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            background: rgba(255,255,255,0.93);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: slide-up 0.5s ease-out;
        }

        @keyframes slide-up {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-accent {
            height: 4px;
            background: linear-gradient(90deg, var(--orange-500) 0%, var(--orange-400) 100%);
        }

        .card-header { padding: 28px 32px 0; }
        .card-content { padding: 20px 32px 32px; }

        .header-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-back {
            display: flex; align-items: center; justify-content: center;
            width: 34px; height: 34px;
            border-radius: 8px; border: none; background: transparent;
            cursor: pointer; color: var(--text-muted);
            transition: background 0.2s, color 0.2s; flex-shrink: 0;
        }
        .btn-back:hover { background: #fff3eb; color: var(--orange-600); }

        .logo-group { display: flex; align-items: center; gap: 8px; }
        .logo-icon {
            width: 36px; height: 36px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; box-shadow: 0 4px 12px rgba(249,115,22,0.3);
            transition: transform 0.3s;
        }
        .logo-icon:hover { transform: scale(1.1); }
        .logo-icon img { width: 24px; height: 24px; object-fit: contain; }
        .logo-icon .fallback { display: none; color: var(--orange-500); font-weight: 800; font-size: 18px; }
        .logo-text {
            font-size: 1.2rem; font-weight: 700;
            background: linear-gradient(135deg, var(--orange-500) 0%, var(--orange-400) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        .card-title { font-size: 1.5rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .card-desc { font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; }

        /* Progress Steps */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin: 24px 0 28px;
            padding: 0 8px;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted);
            flex: 1;
        }
        .step.active { color: var(--orange-600); }
        .step.done { color: #16a34a; }
        .step-num {
            width: 26px; height: 26px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700;
            background: var(--border); color: var(--text-muted);
            flex-shrink: 0;
        }
        .step.active .step-num { background: var(--orange-500); color: #fff; }
        .step.done .step-num { background: #16a34a; color: #fff; }
        .step.done .step-num::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; }
        .step.done .step-num span { display: none; }
        
        .step-line { 
            flex: 1; height: 2px; background: var(--border); 
            margin: 0 8px; min-width: 20px;
        }
        .step-line.done { background: #16a34a; }
        .step-line.active { background: linear-gradient(90deg, #16a34a 50%, var(--border) 50%); }
        
        .step-label { display: none; }
        @media (min-width: 420px) {
            .step-label { display: inline; }
        }

        /* Alerts */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px; border-radius: 10px;
            font-size: 0.875rem; font-weight: 500;
            margin-bottom: 18px; animation: shake 0.4s ease;
        }
        .alert-error { background: var(--red-50); border: 1px solid var(--red-200); color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-info { background: var(--blue-50); border: 1px solid var(--blue-200); color: #1e40af; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60% { transform: translateX(-4px); }
            40%,80% { transform: translateX(4px); }
        }

        /* Form */
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block; font-size: 0.875rem; font-weight: 600;
            color: var(--text); margin-bottom: 8px;
        }
        .form-label .required { color: var(--red-500); margin-left: 2px; }
        .form-label .helper { display: block; font-weight: 400; color: var(--text-muted); margin-top: 4px; font-size: 0.8125rem; }

        .input-wrap { position: relative; }
        .form-input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.9375rem; font-family: inherit;
            color: var(--text); background: var(--bg);
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .form-input:focus {
            border-color: var(--orange-500);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
        }
        .form-input.is-error { border-color: var(--red-500); }
        .form-input.has-toggle { padding-right: 44px; }

        /* Code input styling */
        .code-input {
            text-align: center;
            letter-spacing: 0.5em;
            font-size: 1.5rem;
            font-weight: 700;
            padding: 14px;
        }

        .toggle-pwd {
            position: absolute; right: 0; top: 0;
            height: 100%; width: 42px; border: none; background: transparent;
            cursor: pointer; color: var(--text-muted);
            display: flex; align-items: center; justify-content: center;
            transition: color 0.2s, background 0.2s; border-radius: 0 8px 8px 0;
        }
        .toggle-pwd:hover { background: #fff3eb; color: var(--orange-600); }

        .field-error {
            display: flex; align-items: center; gap: 4px;
            margin-top: 5px; font-size: 0.8125rem; color: var(--red-500);
        }

        /* Password strength */
        .pwd-strength { margin-top: 8px; }
        .strength-bar { display: flex; gap: 4px; margin-bottom: 4px; }
        .strength-segment {
            flex: 1; height: 3px; border-radius: 2px;
            background: var(--border); transition: background 0.3s;
        }
        .strength-text { font-size: 0.75rem; color: var(--text-muted); }

        /* Buttons */
        .btn-submit {
            width: 100%; padding: 13px; border: none; border-radius: 8px;
            background: linear-gradient(135deg, var(--orange-500) 0%, var(--orange-400) 100%);
            color: #fff; font-size: 0.9375rem; font-weight: 600; font-family: inherit;
            cursor: pointer; box-shadow: 0 6px 20px rgba(249,115,22,0.35);
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
            margin-bottom: 16px;
        }
        .btn-submit:hover:not(:disabled) {
            transform: scale(1.02);
            background: linear-gradient(135deg, var(--orange-600) 0%, var(--orange-500) 100%);
            box-shadow: 0 8px 28px rgba(249,115,22,0.45);
        }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .btn-secondary {
            width: 100%; padding: 11px; border: 1.5px solid var(--border);
            border-radius: 8px; background: var(--bg);
            color: var(--text); font-size: 0.875rem; font-weight: 500;
            cursor: pointer; transition: all 0.2s; font-family: inherit;
        }
        .btn-secondary:hover { background: #f9fafb; border-color: var(--orange-500); }

        .link-row {
            text-align: center; font-size: 0.875rem; color: var(--text-muted);
            margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);
        }
        .link-row a {
            color: var(--orange-600); font-weight: 600;
            text-decoration: none; transition: color 0.2s;
        }
        .link-row a:hover { color: var(--orange-700); text-decoration: underline; }

        /* Resend timer */
        .resend-row {
            text-align: center; margin-top: 12px;
            font-size: 0.8125rem; color: var(--text-muted);
        }
        .resend-btn {
            background: none; border: none; color: var(--orange-600);
            font-weight: 600; cursor: pointer; font-family: inherit;
            font-size: 0.8125rem; transition: color 0.2s;
        }
        .resend-btn:hover:not(:disabled) { color: var(--orange-700); text-decoration: underline; }
        .resend-btn:disabled { color: var(--text-muted); cursor: not-allowed; }

        /* Toast */
        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 18px; border-radius: 10px; max-width: 380px;
            font-size: 0.875rem; font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            backdrop-filter: blur(8px); animation: toast-in 0.35s ease;
        }
        .toast-success { background: rgba(34,197,94,0.92); color: #fff; border: 1px solid rgba(74,222,128,0.6); }
        .toast-error { background: rgba(239,68,68,0.92); color: #fff; border: 1px solid rgba(252,165,165,0.6); }
        .toast-inner { display: flex; align-items: center; gap: 8px; }
        .toast-close { background: none; border: none; cursor: pointer; color: rgba(255,255,255,0.75); transition: color 0.2s; display: flex; align-items: center; }
        .toast-close:hover { color: #fff; }

        @keyframes toast-in {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .card-corner {
            position: absolute; bottom: -12px; right: -12px;
            width: 80px; height: 80px;
            background: rgba(249,115,22,0.05);
            border-radius: 50%; filter: blur(16px); pointer-events: none;
        }

        /* Icon circle */
        .icon-circle {
            width: 56px; height: 56px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.5rem;
        }
        .icon-circle.orange { background: #fff7ed; color: var(--orange-500); }
        .icon-circle.green { background: #f0fdf4; color: #16a34a; }

        @media (max-width: 480px) {
            .card-header, .card-content { padding-left: 22px; padding-right: 22px; }
            .card-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<!-- Toast -->
<div class="toast-container" id="toastContainer">
    <?php if (!empty($successMessage) && empty($info)): ?>
    <div class="toast toast-success" id="toast-success">
        <div class="toast-inner"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($successMessage); ?></span></div>
        <button class="toast-close" onclick="removeToast('toast-success')"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($generalError) && empty($errors)): ?>
    <div class="toast toast-error" id="toast-error">
        <div class="toast-inner"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($generalError); ?></span></div>
        <button class="toast-close" onclick="removeToast('toast-error')"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-accent"></div>

    <div class="card-header">
        <div class="header-row">
            <button class="btn-back" onclick="window.location.href='login.php'" title="Back to login">
                <i class="fas fa-arrow-left fa-sm"></i>
            </button>
            <div class="logo-group">
                <div class="logo-icon">
                    <img src="https://github.com/Sweety-Vigneshg/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true"
                         alt="Padak" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                    <span class="fallback">P</span>
                </div>
                <span class="logo-text">Padak</span>
            </div>
        </div>

        <?php if ($currentStep === 'email'): ?>
            <div class="icon-circle orange">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="card-title">Forgot your password?</h1>
            <p class="card-desc">No worries! Enter your email address and we'll send you a verification code to reset your password.</p>
        <?php elseif ($currentStep === 'verify_code'): ?>
            <div class="icon-circle orange">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h1 class="card-title">Verify your identity</h1>
            <p class="card-desc">We've sent a 6-digit verification code to <strong><?php echo htmlspecialchars($formData['email']); ?></strong></p>
        <?php else: ?>
            <div class="icon-circle green">
                <i class="fas fa-lock"></i>
            </div>
            <h1 class="card-title">Set new password</h1>
            <p class="card-desc">Create a strong password to secure your account</p>
        <?php endif; ?>

        <!-- Progress Steps -->
        <div class="steps">
            <div class="step <?php echo $currentStep === 'email' ? 'active' : ($currentStep !== 'email' ? 'done' : ''); ?>">
                <div class="step-num"><span>1</span></div>
                <span class="step-label">Email</span>
            </div>
            <div class="step-line <?php echo $currentStep !== 'email' ? 'done' : ($currentStep === 'email' ? '' : ''); ?>"></div>
            
            <div class="step <?php echo $currentStep === 'verify_code' ? 'active' : ($currentStep === 'reset_password' ? 'done' : ''); ?>">
                <div class="step-num"><span>2</span></div>
                <span class="step-label">Verify</span>
            </div>
            <div class="step-line <?php echo $currentStep === 'reset_password' ? 'done' : ''; ?>"></div>
            
            <div class="step <?php echo $currentStep === 'reset_password' ? 'active' : ''; ?>">
                <div class="step-num"><span>3</span></div>
                <span class="step-label">Reset</span>
            </div>
        </div>
    </div>

    <div class="card-content">
        <!-- Inline alerts -->
        <?php if (!empty($generalError)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $generalError; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $successMessage; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($info)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span><?php echo $info; ?></span>
        </div>
        <?php endif; ?>

        <!-- ========== STEP 1: EMAIL FORM ========== -->
        <?php if ($currentStep === 'email'): ?>
        <form method="POST" action="" id="emailForm" novalidate>
            <input type="hidden" name="action" value="request_reset">
            
            <div class="form-group">
                <label class="form-label" for="email">
                    Email Address <span class="required">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input <?php echo isset($errors['email']) ? 'is-error' : ''; ?>"
                    placeholder="john@example.com"
                    value="<?php echo htmlspecialchars($formData['email']); ?>"
                    autocomplete="email"
                    autofocus
                    required
                >
                <?php if (isset($errors['email'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['email']); ?>
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Send Verification Code
            </button>
        </form>
        <?php endif; ?>

        <!-- ========== STEP 2: VERIFY CODE FORM ========== -->
        <?php if ($currentStep === 'verify_code'): ?>
        <form method="POST" action="" id="verifyForm" novalidate>
            <input type="hidden" name="action" value="verify_code">
            
            <div class="form-group">
                <label class="form-label" for="code">
                    Verification Code <span class="required">*</span>
                    <span class="helper">Enter the 6-digit code sent to your email</span>
                </label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    class="form-input code-input <?php echo isset($errors['code']) ? 'is-error' : ''; ?>"
                    placeholder="000000"
                    maxlength="6"
                    pattern="\d{6}"
                    autocomplete="off"
                    autofocus
                    required
                >
                <?php if (isset($errors['code'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['code']); ?>
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-submit" id="verifyBtn">
                <i class="fas fa-check-circle"></i> Verify Code
            </button>

            <div class="resend-row">
                Didn't receive the code? 
                <button type="button" class="resend-btn" id="resendBtn" onclick="resendCode()">
                    Resend Code
                </button>
                <span id="timer"></span>
            </div>
        </form>
        <?php endif; ?>

        <!-- ========== STEP 3: RESET PASSWORD FORM ========== -->
        <?php if ($currentStep === 'reset_password'): ?>
        <form method="POST" action="" id="resetForm" novalidate>
            <input type="hidden" name="action" value="reset_password">
            
            <div class="form-group">
                <label class="form-label" for="new_password">
                    New Password <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-input has-toggle <?php echo isset($errors['new_password']) ? 'is-error' : ''; ?>"
                        placeholder="Min. 6 characters"
                        autocomplete="new-password"
                        autofocus
                        required
                    >
                    <button type="button" class="toggle-pwd" data-target="new_password" title="Toggle">
                        <i class="fas fa-eye fa-sm"></i>
                    </button>
                </div>
                <div class="pwd-strength" id="strengthWrap" style="display:none;">
                    <div class="strength-bar">
                        <div class="strength-segment" id="seg1"></div>
                        <div class="strength-segment" id="seg2"></div>
                        <div class="strength-segment" id="seg3"></div>
                        <div class="strength-segment" id="seg4"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>
                <?php if (isset($errors['new_password'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['new_password']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">
                    Confirm Password <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-input has-toggle <?php echo isset($errors['confirm_password']) ? 'is-error' : ''; ?>"
                        placeholder="Repeat password"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="toggle-pwd" data-target="confirm_password" title="Toggle">
                        <i class="fas fa-eye fa-sm"></i>
                    </button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['confirm_password']); ?>
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-submit" id="resetBtn">
                <i class="fas fa-lock"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>

        <!-- Back to login link -->
        <div class="link-row">
            <i class="fas fa-arrow-left fa-xs"></i> Back to <a href="login.php">Sign In</a>
        </div>
    </div>

    <div class="card-corner"></div>
</div>

<script>
// ========== GENERAL ==========
function removeToast(id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateX(30px)';
        el.style.transition = '0.3s';
        setTimeout(() => el.remove(), 300);
    }
}

document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => removeToast(t.id), 5000);
});

// ========== STEP 1: EMAIL FORM ==========
const emailForm = document.getElementById('emailForm');
if (emailForm) {
    emailForm.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    });
}

// ========== STEP 2: VERIFY CODE ==========
const verifyForm = document.getElementById('verifyForm');
if (verifyForm) {
    const codeInput = document.getElementById('code');
    
    // Auto-format code input (digits only)
    codeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    // Auto-submit when 6 digits entered
    codeInput.addEventListener('input', function() {
        if (this.value.length === 6) {
            setTimeout(() => verifyForm.submit(), 200);
        }
    });

    verifyForm.addEventListener('submit', function() {
        const btn = document.getElementById('verifyBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    });

    // Resend timer
    let resendCountdown = 60;
    const resendBtn = document.getElementById('resendBtn');
    const timerSpan = document.getElementById('timer');

    function startResendTimer() {
        resendBtn.disabled = true;
        const interval = setInterval(() => {
            resendCountdown--;
            timerSpan.textContent = `(${resendCountdown}s)`;
            if (resendCountdown <= 0) {
                clearInterval(interval);
                resendBtn.disabled = false;
                timerSpan.textContent = '';
                resendCountdown = 60;
            }
        }, 1000);
    }

    // Start timer on page load
    startResendTimer();
}

function resendCode() {
    // In production: Make AJAX call to resend code
    window.location.href = 'forgot_password.php?step=email';
}

// ========== STEP 3: RESET PASSWORD ==========
const resetForm = document.getElementById('resetForm');
if (resetForm) {
    // Password toggle
    document.querySelectorAll('.toggle-pwd').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = document.getElementById(this.dataset.target);
            const isText = target.type === 'text';
            target.type = isText ? 'password' : 'text';
            const icon = this.querySelector('i');
            icon.className = isText ? 'fas fa-eye fa-sm' : 'fas fa-eye-slash fa-sm';
        });
    });

    // Password strength
    const pwdInput = document.getElementById('new_password');
    const strengthWrap = document.getElementById('strengthWrap');
    const segs = [
        document.getElementById('seg1'),
        document.getElementById('seg2'),
        document.getElementById('seg3'),
        document.getElementById('seg4')
    ];
    const strengthText = document.getElementById('strengthText');

    pwdInput.addEventListener('input', function() {
        const val = this.value;
        if (!val) {
            strengthWrap.style.display = 'none';
            return;
        }
        strengthWrap.style.display = 'block';
        
        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;

        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['Weak', 'Fair', 'Good', 'Strong'];
        
        segs.forEach((s, i) => {
            s.style.background = i < score ? colors[score - 1] : '#e5e7eb';
        });
        
        strengthText.textContent = labels[score - 1] || '';
        strengthText.style.color = colors[score - 1] || '#6b7280';
    });

    // Confirm password match
    const confirmInput = document.getElementById('confirm_password');
    confirmInput.addEventListener('input', function() {
        const pwd = pwdInput.value;
        const conf = this.value;
        if (conf && pwd !== conf) {
            this.style.borderColor = '#ef4444';
        } else if (conf) {
            this.style.borderColor = '#22c55e';
        } else {
            this.style.borderColor = '';
        }
    });

    resetForm.addEventListener('submit', function() {
        const btn = document.getElementById('resetBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
    });
}
</script>
</body>
</html>