<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }

$student = $auth->getCurrentStudent();
if (!$student) { header('Location: logout.php'); exit; }

$activePage = 'certificates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification</title>
    <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --sbw: 258px;
            --or5: #f97316;
            --or4: #fb923c;
            --or6: #ea580c;
            --or1: #fff7ed;
            --or2: #ffedd5;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --text2: #475569;
            --text3: #94a3b8;
            --border: #e2e8f0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fff7ed 0%, var(--bg) 60%, #ffedd5 100%);
            min-height: 100vh;
            color: #111827;
        }
        
        .page-wrap {
            margin-left: var(--sbw);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(248, 250, 252, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 12px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .topbar-hamburger {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text2);
            padding: 6px;
            border-radius: 7px;
        }
        
        .topbar-hamburger:hover {
            background: rgba(249, 115, 22, 0.08);
        }
        
        .topbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            flex: 1;
        }
        
        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--or5), var(--or4));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(249, 115, 22, 0.25);
        }
        
        .logo i {
            font-size: 2.5rem;
            color: #fff;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .verify-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }
        
        .input-group {
            margin-bottom: 24px;
        }
        
        .input-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: var(--or5);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
        }
        
        .verify-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--or5), var(--or4));
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .verify-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(249, 115, 22, 0.3);
        }
        
        .verify-btn:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .verify-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .result-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            display: none;
            animation: slideIn 0.4s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .result-card.show {
            display: block;
        }
        
        .result-header {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 24px;
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2.5rem;
        }
        
        .status-icon.valid {
            background: #dcfce7;
            color: #15803d;
        }
        
        .status-icon.invalid {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .result-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .result-header h2.valid {
            color: #15803d;
        }
        
        .result-header h2.invalid {
            color: #dc2626;
        }
        
        .result-header p {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .detail-row {
            display: flex;
            padding: 16px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            flex: 0 0 200px;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-label i {
            color: var(--or4);
        }
        
        .detail-value {
            flex: 1;
            color: #111827;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .cert-number-display {
            background: var(--or1);
            padding: 12px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            font-weight: 700;
            color: var(--or6);
        }
        
        .verify-another-btn {
            width: 100%;
            padding: 14px;
            background: #f3f4f6;
            color: #374151;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 24px;
            transition: all 0.2s;
        }
        
        .verify-another-btn:hover {
            background: #e5e7eb;
        }
        
        .error-message {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 14px 18px;
            border-radius: 10px;
            margin-top: 16px;
            font-size: 0.875rem;
            font-weight: 600;
            display: none;
            animation: shake 0.4s ease;
        }
        
        .error-message.show {
            display: block;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 24px;
        }
        
        .info-box p {
            color: #0c4a6e;
            font-size: 0.875rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .info-box i {
            color: #0284c7;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .page-wrap {
                margin-left: 0;
            }
            
            .topbar-hamburger {
                display: flex;
            }
            
            .container {
                margin: 20px auto;
                padding: 16px;
            }
            
            .verify-card, .result-card {
                padding: 24px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 8px;
            }
            
            .detail-label {
                flex: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="page-wrap">
        <!-- Topbar -->
        <div class="topbar">
            <button class="topbar-hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars fa-sm"></i>
            </button>
            <div class="topbar-title">Certificate Verification</div>
        </div>
        
        <div class="container">
            <div class="header">
                <div class="logo">
                    <i class="fas fa-certificate"></i>
                </div>
                <h1>Certificate Verification</h1>
                <p>Verify the authenticity of Padak internship certificates</p>
            </div>
            
            <div class="verify-card" id="verifyCard">
                <div class="input-group">
                    <label for="certNumber">Certificate Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-hashtag"></i>
                        <input 
                            type="text" 
                            id="certNumber" 
                            placeholder="Enter certificate number (e.g., PADAK2024001)"
                            autocomplete="off"
                        >
                    </div>
                </div>
                
                <button class="verify-btn" id="verifyBtn" onclick="verifyCertificate()">
                    <i class="fas fa-shield-check"></i>
                    <span id="btnText">Verify Certificate</span>
                    <span id="btnSpinner" style="display: none;" class="spinner"></span>
                </button>
                
                <div class="error-message" id="errorMessage"></div>
                
                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle"></i>
                        Enter the unique certificate number found on your Padak internship certificate to verify its authenticity and view details.
                    </p>
                </div>
            </div>
            
            <div class="result-card" id="resultCard">
                <div class="result-header">
                    <div class="status-icon" id="statusIcon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 id="statusTitle">Certificate Verified</h2>
                    <p id="statusMessage">This certificate is authentic and valid</p>
                </div>
                
                <div id="certificateDetails"></div>
                
                <button class="verify-another-btn" onclick="verifyAnother()">
                    <i class="fas fa-rotate-right"></i> Verify Another Certificate
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Allow Enter key to submit
        document.getElementById('certNumber').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                verifyCertificate();
            }
        });
        
        async function verifyCertificate() {
            const certNumber = document.getElementById('certNumber').value.trim();
            const errorMsg = document.getElementById('errorMessage');
            const verifyBtn = document.getElementById('verifyBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            // Hide previous errors
            errorMsg.classList.remove('show');
            
            // Validate input
            if (!certNumber) {
                showError('Please enter a certificate number');
                return;
            }
            
            // Disable button and show loading
            verifyBtn.disabled = true;
            btnText.textContent = 'Verifying...';
            btnSpinner.style.display = 'inline-block';
            
            try {
                const formData = new FormData();
                formData.append('certificate_number', certNumber);
                
                const response = await fetch('verify_certificate_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.valid) {
                    showValidCertificate(data);
                } else {
                    showError(data.error || 'Certificate not found');
                }
            } catch (error) {
                showError('Error connecting to server. Please try again.');
                console.error('Verification error:', error);
            } finally {
                // Re-enable button
                verifyBtn.disabled = false;
                btnText.textContent = 'Verify Certificate';
                btnSpinner.style.display = 'none';
            }
        }
        
        function showValidCertificate(data) {
            const resultCard = document.getElementById('resultCard');
            const verifyCard = document.getElementById('verifyCard');
            const statusIcon = document.getElementById('statusIcon');
            const statusTitle = document.getElementById('statusTitle');
            const statusMessage = document.getElementById('statusMessage');
            const detailsDiv = document.getElementById('certificateDetails');
            
            // Update status
            statusIcon.className = 'status-icon valid';
            statusIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
            statusTitle.className = 'valid';
            statusTitle.textContent = 'Certificate Verified ✓';
            statusMessage.textContent = 'This certificate is authentic and valid';
            
            // Build details HTML
            const detailsHTML = `
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-hashtag"></i>
                        Certificate Number
                    </div>
                    <div class="detail-value">
                        <div class="cert-number-display">${escapeHtml(data.certificate_number)}</div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-user"></i>
                        Student Name
                    </div>
                    <div class="detail-value">${escapeHtml(data.student_name)}</div>
                </div>
                ${data.course_name ? `
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-book"></i>
                        Course
                    </div>
                    <div class="detail-value">${escapeHtml(data.course_name)}</div>
                </div>
                ` : ''}
                ${data.batch_name ? `
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-layer-group"></i>
                        Batch
                    </div>
                    <div class="detail-value">${escapeHtml(data.batch_name)}</div>
                </div>
                ` : ''}
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-calendar"></i>
                        Issued Date
                    </div>
                    <div class="detail-value">${escapeHtml(data.issued_date)}</div>
                </div>
                ${data.completion_grade ? `
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-trophy"></i>
                        Grade
                    </div>
                    <div class="detail-value">${escapeHtml(data.completion_grade)}</div>
                </div>
                ` : ''}
                ${data.total_points_earned ? `
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-star"></i>
                        Points Earned
                    </div>
                    <div class="detail-value">${escapeHtml(data.total_points_earned)}</div>
                </div>
                ` : ''}
            `;
            
            detailsDiv.innerHTML = detailsHTML;
            
            // Show result, hide form
            verifyCard.style.display = 'none';
            resultCard.classList.add('show');
        }
        
        function showError(message) {
            const errorMsg = document.getElementById('errorMessage');
            errorMsg.textContent = message;
            errorMsg.classList.add('show');
        }
        
        function verifyAnother() {
            const resultCard = document.getElementById('resultCard');
            const verifyCard = document.getElementById('verifyCard');
            const certNumberInput = document.getElementById('certNumber');
            const errorMsg = document.getElementById('errorMessage');
            
            // Reset form
            certNumberInput.value = '';
            errorMsg.classList.remove('show');
            
            // Show form, hide result
            resultCard.classList.remove('show');
            verifyCard.style.display = 'block';
            
            // Focus input
            certNumberInput.focus();
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>