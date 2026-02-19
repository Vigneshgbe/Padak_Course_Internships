<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: student_dashboard.php');
    exit;
}

// Check remember me cookie
if (!$auth->isLoggedIn() && isset($_COOKIE['padak_student_token'])) {
    $token = $_COOKIE['padak_student_token'];
    $db = getPadakDB();
    $stmt = $db->prepare("SELECT id, full_name, email FROM internship_students WHERE remember_token = ? AND token_expires_at > NOW() AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $_SESSION['student_id'] = $row['id'];
        $_SESSION['student_name'] = $row['full_name'];
        $_SESSION['student_email'] = $row['email'];
        header('Location: student_dashboard.php');
        exit;
    }
}

$errors = [];
$formData = ['email' => '', 'rememberMe' => false];
$successMessage = '';
$generalError = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    $formData['email'] = htmlspecialchars($email);
    $formData['rememberMe'] = $rememberMe;

    // Validate
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }

    if (empty($errors)) {
        $result = $auth->login($email, $password, $rememberMe);
        if ($result['success']) {
            $successMessage = 'Login successful! Redirecting...';
            header('Refresh: 1.5; URL=student_dashboard.php');
        } else {
            $generalError = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Padak Internships</title>
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
            --green-400: #4ade80;
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
        .blob-4 { width: 100px; height: 100px; background: rgba(251,146,60,0.08); top: 33%; right: 25%; animation-delay: 0.3s; }

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
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
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

        .input-wrap { position: relative; }
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
        /* Password input needs right padding for toggle */
        .form-input.has-toggle { padding-right: 44px; }

        .toggle-pwd {
            position: absolute;
            right: 0; top: 0;
            height: 100%;
            width: 42px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s, background 0.2s;
            border-radius: 0 8px 8px 0;
        }
        .toggle-pwd:hover { background: #fff3eb; color: var(--orange-600); }

        .field-error {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
            font-size: 0.8125rem;
            color: var(--red-500);
        }

        /* Remember + Forgot row */
        .row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-muted);
            user-select: none;
        }
        .checkbox-label input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--orange-500);
            cursor: pointer;
        }
        .link-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--orange-600);
            transition: color 0.2s;
            text-decoration: none;
        }
        .link-btn:hover { color: var(--orange-700); text-decoration: underline; }

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
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
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

        /* Sign-up link */
        .signup-row {
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        .signup-row a {
            color: var(--orange-600);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .signup-row a:hover { color: var(--orange-700); text-decoration: underline; }

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
        .toast-error { background: rgba(239,68,68,0.92); color: #fff; border: 1px solid rgba(252,165,165,0.6); }
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
<div class="blob blob-4"></div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer">
    <?php if (!empty($successMessage)): ?>
    <div class="toast toast-success" id="toast-success">
        <div class="toast-inner">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($successMessage); ?></span>
        </div>
        <button class="toast-close" onclick="removeToast('toast-success')"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($generalError) && empty($errors)): ?>
    <div class="toast toast-error" id="toast-general-err">
        <div class="toast-inner">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($generalError); ?></span>
        </div>
        <button class="toast-close" onclick="removeToast('toast-general-err')"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
</div>

<!-- Card -->
<div class="card">
    <div class="card-accent"></div>

    <div class="card-header">
        <div class="header-row">
            <button class="btn-back" onclick="history.back()" title="Go back">
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
        <h1 class="card-title">Welcome back</h1>
        <p class="card-desc">Sign in to your account to access internships</p>
    </div>

    <div class="card-content">
        <!-- General error alert (inline, not toast) -->
        <?php if (!empty($generalError)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($generalError); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>
            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="email">
                    Email <span class="required">*</span>
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
                >
                <?php if (isset($errors['email'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['email']); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="password">
                    Password <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input has-toggle <?php echo isset($errors['password']) ? 'is-error' : ''; ?>"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="toggle-pwd" id="togglePwd" title="Show/hide password">
                        <i class="fas fa-eye fa-sm" id="eyeIcon"></i>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?>
                <div class="field-error">
                    <i class="fas fa-circle-exclamation fa-xs"></i>
                    <?php echo htmlspecialchars($errors['password']); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Remember + Forgot -->
            <div class="row-between">
                <label class="checkbox-label">
                    <input
                        type="checkbox"
                        name="remember_me"
                        id="rememberMe"
                        <?php echo $formData['rememberMe'] ? 'checked' : ''; ?>
                    >
                    Remember me
                </label>
                <a href="forgot_password.php" class="link-btn">Forgot password?</a>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-submit" id="submitBtn">
                Sign in
            </button>

            <div class="signup-row">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </form>
    </div>

    <div class="card-corner"></div>
</div>

<script>
    // Password Toggle
    const togglePwd = document.getElementById('togglePwd');
    const pwdInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePwd.addEventListener('click', () => {
        const isText = pwdInput.type === 'text';
        pwdInput.type = isText ? 'password' : 'text';
        eyeIcon.className = isText ? 'fas fa-eye fa-sm' : 'fas fa-eye-slash fa-sm';
    });

    // Submit loading state
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Signing in...';
    });

    // Client-side field error clearing on input
    document.getElementById('email').addEventListener('input', function() {
        this.classList.remove('is-error');
        const err = this.parentElement.querySelector('.field-error');
        if (err) err.remove();
        clearGeneralAlert();
    });
    document.getElementById('password').addEventListener('input', function() {
        this.classList.remove('is-error');
        const wrap = this.closest('.form-group');
        const err = wrap.querySelector('.field-error');
        if (err) err.remove();
        clearGeneralAlert();
    });

    function clearGeneralAlert() {
        const alert = document.querySelector('.alert-error');
        if (alert) alert.style.display = 'none';
    }

    // Toast auto-dismiss + remove
    function removeToast(id) {
        const el = document.getElementById(id);
        if (el) { el.style.opacity = '0'; el.style.transform = 'translateX(30px)'; el.style.transition = '0.3s'; setTimeout(() => el.remove(), 300); }
    }

    // Auto-dismiss toasts after 5s
    document.querySelectorAll('.toast').forEach(t => {
        setTimeout(() => removeToast(t.id), 5000);
    });

    <?php if (!empty($successMessage)): ?>
    // Auto-dismiss success and redirect is already handled by PHP Refresh header
    <?php endif; ?>
</script>
</body>
</html>