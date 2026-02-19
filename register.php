<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$formData = [
    'full_name'       => '',
    'email'           => '',
    'phone'           => '',
    'college_name'    => '',
    'degree'          => '',
    'year_of_study'   => '1st Year',
    'domain_interest' => '',
];
$successMessage = '';
$generalError   = '';

$yearOptions = ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'];
$domainOptions = [
    'Web Development', 'Mobile Development', 'Data Science', 'Machine Learning / AI',
    'UI/UX Design', 'DevOps / Cloud', 'Cybersecurity', 'Digital Marketing',
    'Business Analytics', 'Content Writing', 'Others'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize
    foreach (['full_name','email','phone','college_name','degree','year_of_study','domain_interest'] as $field) {
        $formData[$field] = trim($_POST[$field] ?? '');
    }
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // --- Validation ---
    if (empty($formData['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($formData['full_name']) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (!empty($formData['phone']) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $formData['phone'])) {
        $errors['phone'] = 'Enter a valid phone number';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number';
    }

    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (!in_array($formData['year_of_study'], $yearOptions)) {
        $errors['year_of_study'] = 'Please select a valid year';
    }

    if (empty($errors)) {
        $data = $formData;
        $data['password'] = $password;
        $result = $auth->register($data);

        if ($result['success']) {
            $successMessage = 'Account created successfully! Redirecting to login...';
            header('Refresh: 2; URL=login.php?registered=1');
            $formData = array_fill_keys(array_keys($formData), ''); // clear form
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
    <title>Create Account - Padak Internships</title>
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
            --shadow: 0 20px 60px rgba(0,0,0,0.12);
            --shadow-lg: 0 25px 70px rgba(0,0,0,0.18);
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 50%, #ffedd5 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        .blob {
            position: fixed;
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
            max-width: 520px;
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
        .card-desc { font-size: 0.875rem; color: var(--text-muted); }

        /* Step indicator */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin: 20px 0;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .step.active { color: var(--orange-600); }
        .step.done { color: #16a34a; }
        .step-num {
            width: 22px; height: 22px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700;
            background: var(--border); color: var(--text-muted);
        }
        .step.active .step-num { background: var(--orange-500); color: #fff; }
        .step.done .step-num { background: #16a34a; color: #fff; }
        .step-line { flex: 1; height: 2px; background: var(--border); margin: 0 6px; }
        .step-line.done { background: #16a34a; }

        /* Section header */
        .section-label {
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--orange-500);
            margin: 20px 0 14px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ffedd5;
        }

        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px; border-radius: 10px;
            font-size: 0.875rem; font-weight: 500;
            margin-bottom: 18px; animation: shake 0.4s ease;
        }
        .alert-error { background: var(--red-50); border: 1px solid var(--red-200); color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60% { transform: translateX(-4px); }
            40%,80% { transform: translateX(4px); }
        }

        /* Grid */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block; font-size: 0.875rem; font-weight: 600;
            color: var(--text); margin-bottom: 7px;
        }
        .form-label .required { color: var(--red-500); margin-left: 2px; }
        .form-label .optional { font-size: 0.75rem; font-weight: 400; color: var(--text-muted); }

        .input-wrap { position: relative; }
        .form-input, .form-select {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.9375rem; font-family: inherit;
            color: var(--text); background: var(--bg);
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
            appearance: none; -webkit-appearance: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--orange-500);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
        }
        .form-input.is-error, .form-select.is-error {
            border-color: var(--red-500);
        }
        .form-input.has-toggle { padding-right: 44px; }

        .select-wrap { position: relative; }
        .select-wrap::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted); pointer-events: none; font-size: 0.875rem;
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

        /* Terms */
        .terms-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 20px; padding: 12px 14px;
            background: #fff7ed; border-radius: 8px; border: 1px solid #ffedd5;
        }
        .terms-row input[type="checkbox"] { margin-top: 2px; accent-color: var(--orange-500); flex-shrink: 0; width: 15px; height: 15px; }
        .terms-row label { font-size: 0.8125rem; color: var(--text-muted); cursor: pointer; }
        .terms-row a { color: var(--orange-600); text-decoration: none; }
        .terms-row a:hover { text-decoration: underline; }

        .btn-submit {
            width: 100%; padding: 13px; border: none; border-radius: 8px;
            background: linear-gradient(135deg, var(--orange-500) 0%, var(--orange-400) 100%);
            color: #fff; font-size: 0.9375rem; font-weight: 600; font-family: inherit;
            cursor: pointer; box-shadow: 0 6px 20px rgba(249,115,22,0.35);
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
            margin-bottom: 18px;
        }
        .btn-submit:hover:not(:disabled) {
            transform: scale(1.02);
            background: linear-gradient(135deg, var(--orange-600) 0%, var(--orange-500) 100%);
            box-shadow: 0 8px 28px rgba(249,115,22,0.45);
        }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .signin-row {
            text-align: center; font-size: 0.875rem; color: var(--text-muted);
        }
        .signin-row a {
            color: var(--orange-600); font-weight: 600;
            text-decoration: none; transition: color 0.2s;
        }
        .signin-row a:hover { color: var(--orange-700); text-decoration: underline; }

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

        @media (max-width: 480px) {
            .card-header, .card-content { padding-left: 20px; padding-right: 20px; }
            .card-title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<!-- Toast -->
<div class="toast-container" id="toastContainer">
    <?php if (!empty($successMessage)): ?>
    <div class="toast toast-success" id="toast-success">
        <div class="toast-inner"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($successMessage); ?></span></div>
        <button class="toast-close" onclick="removeToast('toast-success')"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($generalError)): ?>
    <div class="toast toast-error" id="toast-general">
        <div class="toast-inner"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($generalError); ?></span></div>
        <button class="toast-close" onclick="removeToast('toast-general')"><i class="fas fa-times"></i></button>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-accent"></div>

    <div class="card-header">
        <div class="header-row">
            <button class="btn-back" onclick="history.back()" title="Go back">
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
        <h1 class="card-title">Create your account</h1>
        <p class="card-desc">Join Padak to access internships and courses</p>
    </div>

    <div class="card-content">
        <?php if (!empty($generalError)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($generalError); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($successMessage); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm" novalidate>

            <div class="section-label"><i class="fas fa-user fa-xs"></i> Personal Information</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                        class="form-input <?php echo isset($errors['full_name']) ? 'is-error' : ''; ?>"
                        placeholder="John Doe"
                        value="<?php echo htmlspecialchars($formData['full_name']); ?>"
                        autocomplete="name">
                    <?php if (isset($errors['full_name'])): ?>
                    <div class="field-error"><i class="fas fa-circle-exclamation fa-xs"></i><?php echo htmlspecialchars($errors['full_name']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone <span class="optional">(optional)</span></label>
                    <input type="tel" id="phone" name="phone"
                        class="form-input <?php echo isset($errors['phone']) ? 'is-error' : ''; ?>"
                        placeholder="+91 99999 99999"
                        value="<?php echo htmlspecialchars($formData['phone']); ?>"
                        autocomplete="tel">
                    <?php if (isset($errors['phone'])): ?>
                    <div class="field-error"><i class="fas fa-circle-exclamation fa-xs"></i><?php echo htmlspecialchars($errors['phone']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email"
                    class="form-input <?php echo isset($errors['email']) ? 'is-error' : ''; ?>"
                    placeholder="john@example.com"
                    value="<?php echo htmlspecialchars($formData['email']); ?>"
                    autocomplete="email">
                <?php if (isset($errors['email'])): ?>
                <div class="field-error"><i class="fas fa-circle-exclamation fa-xs"></i><?php echo htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>

            <div class="section-label"><i class="fas fa-graduation-cap fa-xs"></i> Academic Details</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="college_name">College / University <span class="optional">(optional)</span></label>
                    <input type="text" id="college_name" name="college_name"
                        class="form-input"
                        placeholder="MIT, IIT, etc."
                        value="<?php echo htmlspecialchars($formData['college_name']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="degree">Degree / Branch <span class="optional">(optional)</span></label>
                    <input type="text" id="degree" name="degree"
                        class="form-input"
                        placeholder="B.Tech CSE"
                        value="<?php echo htmlspecialchars($formData['degree']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="year_of_study">Year of Study</label>
                    <div class="select-wrap">
                        <select id="year_of_study" name="year_of_study"
                            class="form-select <?php echo isset($errors['year_of_study']) ? 'is-error' : ''; ?>">
                            <?php foreach ($yearOptions as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo $formData['year_of_study'] === $yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="domain_interest">Domain of Interest <span class="optional">(optional)</span></label>
                    <div class="select-wrap">
                        <select id="domain_interest" name="domain_interest" class="form-select">
                            <option value="">-- Select Domain --</option>
                            <?php foreach ($domainOptions as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $formData['domain_interest'] === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-label"><i class="fas fa-lock fa-xs"></i> Set Password</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                            class="form-input has-toggle <?php echo isset($errors['password']) ? 'is-error' : ''; ?>"
                            placeholder="Min. 6 characters"
                            autocomplete="new-password">
                        <button type="button" class="toggle-pwd" data-target="password" title="Toggle">
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
                    <?php if (isset($errors['password'])): ?>
                    <div class="field-error"><i class="fas fa-circle-exclamation fa-xs"></i><?php echo htmlspecialchars($errors['password']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password"
                            class="form-input has-toggle <?php echo isset($errors['confirm_password']) ? 'is-error' : ''; ?>"
                            placeholder="Repeat password"
                            autocomplete="new-password">
                        <button type="button" class="toggle-pwd" data-target="confirm_password" title="Toggle">
                            <i class="fas fa-eye fa-sm"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                    <div class="field-error"><i class="fas fa-circle-exclamation fa-xs"></i><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Terms -->
            <div class="terms-row">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    I agree to Padak's <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                    I consent to receiving internship updates via email.
                </label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                Create Account
            </button>

            <div class="signin-row">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>

    <div class="card-corner"></div>
</div>

<script>
    // Toggle password visibility for both fields
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
    const pwdInput = document.getElementById('password');
    const strengthWrap = document.getElementById('strengthWrap');
    const segs = [document.getElementById('seg1'), document.getElementById('seg2'), document.getElementById('seg3'), document.getElementById('seg4')];
    const strengthText = document.getElementById('strengthText');

    pwdInput.addEventListener('input', function() {
        const val = this.value;
        if (!val) { strengthWrap.style.display = 'none'; return; }
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

    // Live confirm password match
    document.getElementById('confirm_password').addEventListener('input', function() {
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

    // Terms checkbox check on submit
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const terms = document.getElementById('terms');
        if (!terms.checked) {
            e.preventDefault();
            terms.style.outline = '2px solid #ef4444';
            terms.closest('.terms-row').style.borderColor = '#ef4444';
            setTimeout(() => {
                terms.style.outline = '';
                terms.closest('.terms-row').style.borderColor = '#ffedd5';
            }, 2000);
            return;
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Creating account...';
    });

    // Toast
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
</script>
</body>
</html>