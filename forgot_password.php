<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$formData = ['email' => ''];
$successMessage = '';
$generalError = '';
$step = 1; // Step 1: Email input, Step 2: Success message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        // Check if email exists in database
        $stmt = $db->prepare("SELECT id, full_name FROM internship_students WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token in database
            $updateStmt = $db->prepare("UPDATE internship_students SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $resetToken, $expiresAt, $user['id']);
            $updateStmt->execute();

            // In production, send email with reset link
            // For now, we'll just show success message
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $resetToken;
            
            // TODO: Send email with $resetLink
            // mail($email, "Reset Your Padak Password", "Click here to reset: $resetLink");
            
            $step = 2;
            $successMessage = "Password reset instructions have been sent to your email address.";
        } else {
            // For security, show success even if email doesn't exist
            // This prevents email enumeration attacks
            $step = 2;
            $successMessage = "If an account exists with this email, you will receive password reset instructions.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Padak Internships</title>
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
            --bg-muted: #f9fafb;
            --text: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --red-500: #ef4444;
            --red-50: #fef2f2;
            --red-200: #fecaca;
            --green-500: #22c55e;
            --blue-500: #3b82f6;
            --blue-50: #eff6ff;
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
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        /* Floating background blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
            animation: pulse-blob 4s ease-in-out infinite;
        }
        .blob-1 { width: 200px; height: 200px; background: rgba(249,115,22,0.12); top: 5%; left: 3%; animation-delay: 0s; }
        .blob-2 { width: 150px; height: 150px; background: rgba(251,146,60,0.10); bottom: 5%; right: 3%; animation-delay: 1s; }
        .blob-3 { width: 120px; height: 120px; background: rgba(249,115,22,0.06); top: 50%; left: 25%; animation-delay: 0.5s; }

        @keyframes pulse-blob {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }

        /* Card */
        .card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: box-shadow 0.3s ease;
            animation: slide-up 0.5s ease-out;
        }
        .card:hover { box-shadow: var(--shadow-lg); }

        @keyframes slide-up {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Orange top accent */
        .card-accent {
            height: 4px;
            background: linear-gradient(90deg, var(--orange-500) 0%, var(--orange-400) 100%);
        }

        .card-header { padding: 28px 32px 0; }
        .card-content { padding: 20px 32px 32px; }

        /* Back + Logo row */
        .header-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--text-muted);
            transition: background 0.2s, color 0.2s;
            flex-shrink: 0;
        }
        .btn-back:hover { background: #fff3eb; color: var(--orange-600); }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(249,115,22,0.3);
            transition: transform 0.3s ease;
        }
        .logo-icon:hover { transform: scale(1.1); }
        .logo-icon img { width: 24px; height: 24px; object-fit: contain; }
        .logo-icon .fallback { display: none; color: var(--orange-500); font-weight: 800; font-size: 18px; }
        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--orange-500) 0%, var(--orange-400) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }
        .card-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Icon circle */
        .icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.5rem;
        }
        .icon-circle.info {
            background: linear-gradient(135deg, rgba(249,115,22,0.15) 0%, rgba(251,146,60,0.1) 100%);
            color: var(--orange-500);
        }
        .icon-circle.success {
            background: linear-gradient(135deg, rgba(34,197,94,0.15) 0%, rgba(74,222,128,0.1) 100%);
            color: var(--green-500);
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 18px;
            animation: shake 0.4s ease;
        }
        .alert-error { background: var(--red-50); border: 1px solid var(--red-200); color: #991b1b; }
        .alert-info { background: var(--blue-50); border: 1px solid #bfdbfe; color: #1e40af; }
        .alert i { flex-shrink: 0; margin-top: 1px; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60% { transform: translateX(-4px); }
            40%,80% { transform: translateX(4px); }
        }

        /* Form */
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        .form-label .required { color: var(--red-500); margin-left: 2px; }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.9375rem;
            font-family: inherit;
            color: var(--text);
            background: var(--bg);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--orange-500);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
        }
        .form-input.is-error {
            border-color: var(--red-500);
        }
        .form-input.is-error:focus {
            border-color: var(--red-500);
            box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
        }

        .field-error {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
            font-size: 0.8125rem;
            color: var(--red-500);
        }

        /* Info box */
        .info-box {
            background: #fff7ed;
            border: 1px solid #ffedd5;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .info-box-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--orange-700);
            margin-bottom: 6px;
        }
        .info-box-text {
            font-size: 0.8125rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--orange-500) 0%, var(--orange-400) 100%);
            color: #fff;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(249,115,22,0.35);
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
            margin-bottom: 16px;
        }
        .btn-submit:hover:not(:disabled) {
            transform: scale(1.02);
            background: linear-gradient(135deg, var(--orange-600) 0%, var(--orange-500) 100%);
            box-shadow: 0 8px 28px rgba(249,115,22,0.45);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Secondary button (back to login) */
        .btn-secondary {
            width: 100%;
            padding: 13px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--bg);
            color: var(--text);
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-secondary:hover {
            border-color: var(--orange-500);
            background: #fff7ed;
        }

        /* Success state */
        .success-content {
            text-align: center;
            animation: fade-in 0.5s ease;
        }
        .success-content .icon-circle {
            animation: scale-in 0.5s ease;
        }

        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scale-in {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .success-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }
        .success-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .resend-link {
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 16px;
        }
        .resend-link button {
            background: none;
            border: none;
            color: var(--orange-600);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s;
            font-family: inherit;
            font-size: inherit;
        }
        .resend-link button:hover {
            color: var(--orange-700);
            text-decoration: underline;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 10px;
            max-width: 380px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            backdrop-filter: blur(8px);
            animation: toast-in 0.35s ease;
        }
        .toast-success { background: rgba(34,197,94,0.92); color: #fff; border: 1px solid rgba(74,222,128,0.6); }
        .toast-info { background: rgba(59,130,246,0.92); color: #fff; border: 1px solid rgba(147,197,253,0.6); }
        .toast-inner { display: flex; align-items: center; gap: 8px; }
        .toast-close {
            background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,0.75); transition: color 0.2s;
            display: flex; align-items: center;
        }
        .toast-close:hover { color: #fff; }

        @keyframes toast-in {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Subtle corner blob inside card */
        .card-corner {
            position: absolute;
            bottom: -12px; right: -12px;
            width: 80px; height: 80px;
            background: rgba(249,115,22,0.05);
            border-radius: 50%;
            filter: blur(16px);
            pointer-events: none;
        }

        @media (max-width: 480px) {
            .card-header, .card-content { padding-left: 22px; padding-right: 22px; }
            .card-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<!-- Background Blobs -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Card -->
<div class="card">
    <div class="card-accent"></div>

    <div class="card-header">
        <div class="header-row">
            <button class="btn-back" onclick="window.location.href='login.php'" title="Back to login">
                <i class="fas fa-arrow-left fa-sm"></i>
            </button>
            <div class="logo-group">
                <div class="logo-icon">
                    <img
                        src="https://github.com/Sweety-Vigneshg/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true"
                        alt="Padak"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                    >
                    <span class="fallback">P</span>
                </div>
                <span class="logo-text">Padak</span>
            </div>
        </div>

        <?php if ($step === 1): ?>
        <div class="icon-circle info">
            <i class="fas fa-key"></i>
        </div>
        <h1 class="card-title">Forgot your password?</h1>
        <p class="card-desc">No worries! Enter your email address and we'll send you instructions to reset your password.</p>
        <?php endif; ?>
    </div>

    <div class="card-content">
        <?php if ($step === 1): ?>
        <!-- Step 1: Email Input Form -->
        <?php if (!empty($generalError)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($generalError); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="forgotPasswordForm" novalidate>
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
                    value="<?php echo $formData['email']; ?>"
                    autocomplete="email"
                    required
                    autofocus
                >
                <?php if (isset($errors['email'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['email']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-box">
                <div class="info-box-title">
                    <i class="fas fa-info-circle fa-sm"></i>
                    <span>What happens next?</span>
                </div>
                <div class="info-box-text">
                    We'll send you an email with a secure link to reset your password. The link will expire in 1 hour for security reasons.
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane fa-sm"></i>&nbsp; Send Reset Link
            </button>

            <a href="login.php" class="btn-secondary">
                <i class="fas fa-arrow-left fa-sm"></i>
                Back to Login
            </a>
        </form>

        <?php else: ?>
        <!-- Step 2: Success Message -->
        <div class="success-content">
            <div class="icon-circle success">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="success-title">Check your email</h2>
            <p class="success-desc">
                We've sent password reset instructions to <strong><?php echo htmlspecialchars($formData['email']); ?></strong>. 
                Please check your inbox and follow the link to reset your password.
            </p>

            <div class="alert alert-info">
                <i class="fas fa-clock"></i>
                <span>The reset link will expire in 1 hour. If you don't see the email, check your spam folder.</span>
            </div>

            <a href="login.php" class="btn-submit" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                <i class="fas fa-sign-in-alt fa-sm"></i>
                Back to Login
            </a>

            <div class="resend-link">
                Didn't receive the email? 
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>">
                    <button type="submit" id="resendBtn">Resend</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card-corner"></div>
</div>

<script>
    // Submit loading state
    const form = document.getElementById('forgotPasswordForm');
    if (form) {
        form.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin fa-sm"></i>&nbsp; Sending...';
        });
    }

    // Resend button
    const resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        resendBtn.closest('form').addEventListener('submit', function() {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';
            
            // Show toast after form submission
            setTimeout(() => {
                showToast('Email resent! Check your inbox.', 'info');
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend';
            }, 1000);
        });
    }

    // Client-side field error clearing on input
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            this.classList.remove('is-error');
            const err = this.parentElement.querySelector('.field-error');
            if (err) err.remove();
            
            const alert = document.querySelector('.alert-error');
            if (alert) alert.style.display = 'none';
        });
    }

    // Toast functions
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toastId = 'toast-' + Date.now();
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.id = toastId;
        toast.innerHTML = `
            <div class="toast-inner">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="removeToast('${toastId}')">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        setTimeout(() => removeToast(toastId), 5000);
    }

    function removeToast(id) {
        const el = document.getElementById(id);
        if (el) {
            el.style.opacity = '0';
            el.style.transform = 'translateX(30px)';
            el.style.transition = '0.3s';
            setTimeout(() => el.remove(), 300);
        }
    }

    // Auto-dismiss existing toasts
    document.querySelectorAll('.toast').forEach(t => {
        setTimeout(() => removeToast(t.id), 5000);
    });
</script>
</body>
</html>