<?php
// ============================================================
// verify.php  →  thepadak.com/verify.php
// ============================================================

// Handle AJAX POST internally (same file acts as its own API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['certificate_number'])) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);

    header('Content-Type: application/json');

    try {
        // Config is inside /Internships/ folder — one level down from public_html
        require_once __DIR__ . '/Internships/config.php';
        $db = getPadakDB();

        if (!$db) {
            throw new Exception('Database connection failed');
        }
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => 'Database connection error']);
        exit;
    }

    $certNumber = trim($_POST['certificate_number'] ?? '');

    if (empty($certNumber)) {
        echo json_encode(['valid' => false, 'error' => 'Certificate number required']);
        exit;
    }

    try {
        // Exact same query as Internships/verify_certificate_api.php
        $stmt = $db->prepare("
            SELECT ic.*, ist.full_name AS student_name, ib.batch_name
            FROM internship_certificates ic
            JOIN internship_students ist ON ic.student_id = ist.id
            LEFT JOIN internship_batches ib ON ic.batch_id = ib.id
            WHERE ic.certificate_number = ? AND ic.is_issued = 1
        ");

        if (!$stmt) {
            throw new Exception('Query preparation failed: ' . $db->error);
        }

        $stmt->bind_param("s", $certNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['valid' => false, 'error' => 'Certificate not found or not yet issued']);
            exit;
        }

        $cert = $result->fetch_assoc();

        echo json_encode([
            'valid'              => true,
            'certificate_number' => $cert['certificate_number'],
            'student_name'       => $cert['student_name'],
            'course_name'        => $cert['course_name']        ?? 'Internship Program',
            'batch_name'         => $cert['batch_name']         ?? 'N/A',
            'issued_date'        => $cert['issued_date']
                                        ? date('M d, Y', strtotime($cert['issued_date']))
                                        : 'N/A',
            'completion_grade'   => $cert['completion_grade']   ?? 'Good',
            'total_points_earned'=> $cert['total_points_earned'] ?? 0,
            'certificate_url'    => $cert['certificate_url']    ?? null,
        ]);

    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => 'Verification error. Please try again.']);
    }
    exit; // Stop — don't render HTML for AJAX calls
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification — Padak</title>
    <meta name="description" content="Verify the authenticity of your Padak internship certificate instantly.">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
          href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --or5: #f97316;
            --or4: #fb923c;
            --or6: #ea580c;
            --or1: #fff7ed;
            --or2: #ffedd5;
            --bg:  #f8fafc;
            --card: #ffffff;
            --text:  #0f172a;
            --text2: #475569;
            --text3: #94a3b8;
            --border: #e2e8f0;
            --green: #15803d;
            --green-bg: #dcfce7;
            --red: #dc2626;
            --red-bg: #fee2e2;
        }

        /* ── Base ── */
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fff7ed 0%, var(--bg) 55%, #ffedd5 100%);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 200;
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-brand img {
            height: 34px;
            width: auto;
        }

        .navbar-brand span {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--or5);
            letter-spacing: -0.5px;
        }

        .navbar-link {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text2);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.18s;
        }

        .navbar-link:hover {
            background: var(--or2);
            color: var(--or6);
        }

        /* ── Hero strip ── */
        .hero {
            background: linear-gradient(135deg, var(--or6) 0%, var(--or5) 60%, var(--or4) 100%);
            padding: 52px 24px 48px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 100px;
            margin-bottom: 18px;
        }

        .hero h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }

        .hero p {
            color: rgba(255,255,255,0.82);
            font-size: 1rem;
            max-width: 480px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ── Container ── */
        .container {
            max-width: 660px;
            margin: -28px auto 60px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        /* ── Card base ── */
        .card {
            background: var(--card);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05),
                        0 16px 48px -8px rgba(0,0,0,0.10);
            border: 1px solid rgba(255,255,255,0.8);
        }

        /* ── Input ── */
        .input-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text2);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 1rem;
            pointer-events: none;
        }

        .cert-input {
            width: 100%;
            padding: 15px 16px 15px 46px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            color: var(--text);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafafa;
        }

        .cert-input::placeholder {
            text-transform: none;
            font-weight: 400;
            letter-spacing: 0;
            color: var(--text3);
        }

        .cert-input:focus {
            outline: none;
            border-color: var(--or5);
            box-shadow: 0 0 0 4px rgba(249,115,22,0.12);
            background: #fff;
        }

        /* ── Button ── */
        .verify-btn {
            width: 100%;
            margin-top: 20px;
            padding: 16px;
            background: linear-gradient(135deg, var(--or6), var(--or5), var(--or4));
            background-size: 200% 200%;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform 0.18s, box-shadow 0.18s, opacity 0.18s;
        }

        .verify-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(249,115,22,0.35);
        }

        .verify-btn:active:not(:disabled) { transform: translateY(0); }

        .verify-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* ── Spinner ── */
        .spinner {
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
            display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Error ── */
        .error-box {
            display: none;
            margin-top: 16px;
            background: var(--red-bg);
            border: 1px solid #fecaca;
            color: var(--red);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            animation: shake 0.38s ease;
        }

        .error-box.show { display: flex; align-items: center; gap: 8px; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%      { transform: translateX(-8px); }
            75%      { transform: translateX(8px); }
        }

        /* ── Info hint ── */
        .hint {
            margin-top: 22px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .hint i { color: #0284c7; margin-top: 2px; flex-shrink: 0; }

        .hint p {
            color: #0c4a6e;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        /* ── Result card ── */
        .result-card {
            display: none;
            animation: slideUp 0.38s cubic-bezier(0.22,1,0.36,1);
        }

        .result-card.show { display: block; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Status header */
        .result-header {
            text-align: center;
            padding-bottom: 28px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 8px;
        }

        .status-circle {
            width: 84px; height: 84px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            margin: 0 auto 16px;
        }

        .status-circle.valid   { background: var(--green-bg); color: var(--green); }
        .status-circle.invalid { background: var(--red-bg);   color: var(--red); }

        .status-title {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .status-title.valid   { color: var(--green); }
        .status-title.invalid { color: var(--red); }

        .status-sub { color: var(--text2); font-size: 0.9rem; }

        /* Detail rows */
        .detail-row {
            display: flex;
            align-items: flex-start;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 12px;
        }

        .detail-row:last-child { border-bottom: none; }

        .detail-label {
            flex: 0 0 180px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text2);
            font-size: 0.83rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding-top: 2px;
        }

        .detail-label i { color: var(--or4); font-size: 0.9rem; }

        .detail-value {
            flex: 1;
            font-weight: 700;
            color: var(--text);
            font-size: 0.95rem;
        }

        .cert-pill {
            display: inline-block;
            background: var(--or1);
            color: var(--or6);
            font-family: 'Courier New', monospace;
            font-weight: 800;
            font-size: 0.9rem;
            letter-spacing: 1.2px;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid var(--or2);
        }

        /* Grade badge */
        .grade-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--green-bg);
            color: var(--green);
            font-weight: 800;
            font-size: 0.85rem;
            padding: 5px 12px;
            border-radius: 100px;
        }

        /* Verify another */
        .reset-btn {
            width: 100%;
            margin-top: 28px;
            padding: 14px;
            background: #f1f5f9;
            color: var(--text2);
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.18s, color 0.18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .reset-btn:hover { background: var(--or2); color: var(--or6); }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 32px 20px;
            color: var(--text3);
            font-size: 0.82rem;
        }

        .footer a {
            color: var(--or5);
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover { text-decoration: underline; }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .navbar { padding: 0 16px; }
            .hero   { padding: 40px 16px 44px; }
            .card   { padding: 26px 20px; }

            .detail-row    { flex-direction: column; gap: 6px; }
            .detail-label  { flex: none; }
        }
    </style>
</head>
<body>

<!-- ════════════ NAVBAR ════════════ -->
<nav class="navbar">
    <a class="navbar-brand" href="https://thepadak.com">
        <img src="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true"
             alt="Padak Logo" onerror="this.style.display='none'">
        <span>Padak</span>
    </a>
    <a class="navbar-link" href="https://thepadak.com/Internships/login.php">
        <i class="fas fa-arrow-right-to-bracket"></i> Intern Login
    </a>
</nav>

<!-- ════════════ HERO ════════════ -->
<div class="hero">
    <div class="hero-badge">
        <i class="fas fa-shield-halved"></i>
        Instant Verification
    </div>
    <h1>Certificate Verification</h1>
    <p>Verify the authenticity of any Padak internship certificate in seconds.</p>
</div>

<!-- ════════════ MAIN ════════════ -->
<div class="container">

    <!-- Verify Form -->
    <div class="card" id="verifyCard">
        <label class="input-label" for="certNumber">
            <i class="fas fa-hashtag" style="color:var(--or5);margin-right:6px;"></i>
            Certificate Number
        </label>
        <div class="input-wrap">
            <i class="fas fa-certificate"></i>
            <input
                class="cert-input"
                type="text"
                id="certNumber"
                placeholder="e.g. CERT-20260406000"
                autocomplete="off"
                spellcheck="false"
            >
        </div>

        <button class="verify-btn" id="verifyBtn" onclick="verifyCertificate()">
            <i class="fas fa-shield-check" id="btnIcon"></i>
            <span id="btnText">Verify Certificate</span>
            <span class="spinner" id="btnSpinner"></span>
        </button>

        <div class="error-box" id="errorBox">
            <i class="fas fa-circle-xmark"></i>
            <span id="errorText"></span>
        </div>

        <div class="hint">
            <i class="fas fa-circle-info"></i>
            <p>Enter the unique certificate number printed on your Padak internship certificate.</p>
        </div>
    </div>

    <!-- Result -->
    <div class="card result-card" id="resultCard">

        <div class="result-header">
            <div class="status-circle" id="statusCircle">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="status-title" id="statusTitle">Certificate Verified ✓</div>
            <div class="status-sub"  id="statusSub">This certificate is authentic and valid</div>
        </div>

        <div id="certDetails"></div>

        <button class="reset-btn" onclick="resetForm()">
            <i class="fas fa-rotate-right"></i> Verify Another Certificate
        </button>

    </div>

</div>

<!-- ════════════ FOOTER ════════════ -->
<footer class="footer">
    <p>
        © <?= date('Y') ?> <a href="https://thepadak.com" target="_blank">Padak Pvt Ltd</a>
        &nbsp;·&nbsp;
        Certificate verification portal for Padak internship graduates.
    </p>
</footer>

<script>
    // Allow Enter key
    document.getElementById('certNumber').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') verifyCertificate();
    });

    async function verifyCertificate() {
        const input     = document.getElementById('certNumber');
        const certNum   = input.value.trim();
        const errorBox  = document.getElementById('errorBox');
        const verifyBtn = document.getElementById('verifyBtn');
        const btnText   = document.getElementById('btnText');
        const btnSpinner= document.getElementById('btnSpinner');
        const btnIcon   = document.getElementById('btnIcon');

        // Clear previous error
        errorBox.classList.remove('show');

        if (!certNum) {
            showError('Please enter a certificate number');
            input.focus();
            return;
        }

        // Loading state
        verifyBtn.disabled     = true;
        btnText.textContent    = 'Verifying…';
        btnSpinner.style.display = 'inline-block';
        btnIcon.style.display  = 'none';

        try {
            const fd = new FormData();
            fd.append('certificate_number', certNum);

            // POST to self (same file handles JSON response at the top)
            const res  = await fetch('verify.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.valid) {
                showResult(data);
            } else {
                showError(data.error || 'Certificate not found. Please check the number and try again.');
            }
        } catch (err) {
            showError('Unable to connect. Please check your internet connection and try again.');
        } finally {
            verifyBtn.disabled     = false;
            btnText.textContent    = 'Verify Certificate';
            btnSpinner.style.display = 'none';
            btnIcon.style.display  = 'inline-block';
        }
    }

    function showResult(data) {
        // Populate header
        document.getElementById('statusCircle').className = 'status-circle valid';
        document.getElementById('statusCircle').innerHTML = '<i class="fas fa-check-circle"></i>';
        document.getElementById('statusTitle').className  = 'status-title valid';
        document.getElementById('statusTitle').textContent = 'Certificate Verified ✓';
        document.getElementById('statusSub').textContent   = 'This certificate is authentic and valid';

        // Build detail rows
        const rows = [
            { icon: 'fa-hashtag',     label: 'Certificate No.',  value: `<div class="cert-pill">${esc(data.certificate_number)}</div>` },
            { icon: 'fa-user',        label: 'Student Name',     value: esc(data.student_name) },
            { icon: 'fa-book-open',   label: 'Course',           value: esc(data.course_name)  },
            { icon: 'fa-layer-group', label: 'Batch',            value: esc(data.batch_name)   },
            { icon: 'fa-calendar-check', label: 'Issued On',     value: esc(data.issued_date)  },
            { icon: 'fa-trophy',      label: 'Grade',            value: `<span class="grade-badge"><i class="fas fa-star"></i>${esc(data.completion_grade)}</span>` },
            { icon: 'fa-star',        label: 'Points Earned',    value: esc(data.total_points_earned) },
        ].filter(r => r.value && r.value !== 'null' && r.value !== '0' && r.value !== 'N/A');

        document.getElementById('certDetails').innerHTML = rows.map(r => `
            <div class="detail-row">
                <div class="detail-label"><i class="fas ${r.icon}"></i>${r.label}</div>
                <div class="detail-value">${r.value}</div>
            </div>
        `).join('');

        // Toggle views
        document.getElementById('verifyCard').style.display = 'none';
        const rc = document.getElementById('resultCard');
        rc.classList.add('show');
        rc.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showError(msg) {
        const box = document.getElementById('errorBox');
        document.getElementById('errorText').textContent = msg;
        box.classList.add('show');
    }

    function resetForm() {
        document.getElementById('resultCard').classList.remove('show');
        const vc = document.getElementById('verifyCard');
        vc.style.display = 'block';
        document.getElementById('certNumber').value = '';
        document.getElementById('errorBox').classList.remove('show');
        document.getElementById('certNumber').focus();
        vc.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
    }
</script>

</body>
</html>