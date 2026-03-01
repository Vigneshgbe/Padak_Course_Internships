<?php
session_start();
require_once 'config.php';

$db = getPadakDB();

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'vigneshg091002');

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    header('Location: admin.php');
    exit;
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// ═══════════════════════════════════════════════════════════
// LOGIN PAGE
// ═══════════════════════════════════════════════════════════
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            *{margin:0;padding:0;box-sizing:border-box;}
            :root{--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;}
            body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
            .login-box{background:var(--card);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);width:100%;max-width:420px;overflow:hidden;}
            .login-header{background:linear-gradient(135deg,var(--o5),var(--o4));padding:32px 28px;text-align:center;color:#fff;}
            .login-header i{font-size:3rem;margin-bottom:12px;display:block;opacity:.9;}
            .login-header h1{font-size:1.5rem;font-weight:800;margin-bottom:6px;}
            .login-header p{font-size:.875rem;opacity:.85;}
            .login-body{padding:32px 28px;}
            .form-group{margin-bottom:20px;}
            .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
            .form-input{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;}
            .form-input:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
            .input-group{position:relative;}
            .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:.9rem;}
            .input-group .form-input{padding-left:42px;}
            .btn-login{width:100%;padding:13px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;font-size:.9rem;font-weight:700;font-family:inherit;cursor:pointer;transition:all .2s;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
            .btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
            .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:8px;font-size:.82rem;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
            .login-footer{padding:0 28px 28px;text-align:center;font-size:.75rem;color:var(--text3);}
            .back-btn-login{position:fixed;top:20px;left:20px;width:44px;height:44px;background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.25);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;text-decoration:none;transition:all .2s;z-index:1000;backdrop-filter:blur(10px);}
            .back-btn-login:hover{background:rgba(255,255,255,0.25);border-color:rgba(255,255,255,0.4);transform:translateX(-3px);}
            @media(max-width:480px){.login-box{max-width:calc(100vw - 32px);}.login-header{padding:24px 20px;}.login-body{padding:24px 20px;}.back-btn-login{top:12px;left:12px;width:40px;height:40px;}}
        </style>
    </head>
    <body>
        <a href="index.php" class="back-btn-login" title="Back to Home">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-shield-halved"></i>
                <h1>Admin Access</h1>
                <p>Task Management Portal</p>
            </div>
            <div class="login-body">
                <?php if ($loginError): ?>
                <div class="alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($loginError); ?>
                </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="username" class="form-input" placeholder="Enter username" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" class="form-input" placeholder="Enter password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
            </div>
            <div class="login-footer">
                <i class="fas fa-info-circle"></i> Authorized personnel only
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ═══════════════════════════════════════════════════════════
// ADMIN DASHBOARD - LOGGED IN
// ═══════════════════════════════════════════════════════════

$success = '';
$error = '';

// Determine active tab
$activeTab = $_GET['tab'] ?? 'tasks';

// Get Statistics
$statsRes = $db->query("SELECT 
    (SELECT COUNT(*) FROM internship_tasks WHERE status='active') as active_tasks,
    (SELECT COUNT(*) FROM task_submissions WHERE status='submitted' OR status='under_review') as pending_reviews,
    (SELECT COUNT(*) FROM task_submissions WHERE status='approved') as completed_tasks,
    (SELECT COUNT(DISTINCT id) FROM internship_students WHERE is_active=1) as total_students
");
$stats = $statsRes->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Padak</title>
    <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php include 'admin_modules/admin_styles.php'; ?>
</head>
<body>
    <?php include 'admin_modules/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-circle-check"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="sc-top">
                    <div class="sc-icon orange"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="sc-value"><?php echo $stats['active_tasks']; ?></div>
                <div class="sc-label">Active Tasks</div>
            </div>
            <div class="stat-card">
                <div class="sc-top">
                    <div class="sc-icon blue"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="sc-value"><?php echo $stats['pending_reviews']; ?></div>
                <div class="sc-label">Pending Reviews</div>
            </div>
            <div class="stat-card">
                <div class="sc-top">
                    <div class="sc-icon green"><i class="fas fa-circle-check"></i></div>
                </div>
                <div class="sc-value"><?php echo $stats['completed_tasks']; ?></div>
                <div class="sc-label">Completed Tasks</div>
            </div>
            <div class="stat-card">
                <div class="sc-top">
                    <div class="sc-icon purple"><i class="fas fa-users"></i></div>
                </div>
                <div class="sc-value"><?php echo $stats['total_students']; ?></div>
                <div class="sc-label">Active Students</div>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <div class="tabs">
            <button class="tab <?php echo $activeTab === 'tasks' ? 'active' : ''; ?>" onclick="window.location.href='?tab=tasks'">
                <i class="fas fa-tasks"></i> Manage Tasks
            </button>
            <button class="tab <?php echo $activeTab === 'reviews' ? 'active' : ''; ?>" onclick="window.location.href='?tab=reviews'">
                <i class="fas fa-clipboard-check"></i> Review Submissions
                <?php if ($stats['pending_reviews'] > 0): ?>
                <span class="badge badge-urgent"><?php echo $stats['pending_reviews']; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab <?php echo $activeTab === 'attendance' ? 'active' : ''; ?>" onclick="window.location.href='?tab=attendance'">
                <i class="fas fa-calendar-check"></i> Attendance
            </button>
        </div>
        
        <!-- Tab Content -->
        <div id="tab-tasks" class="tab-content" <?php echo $activeTab !== 'tasks' ? 'style="display:none;"' : ''; ?>>
            <?php include 'admin_modules/admin_manage_tasks.php'; ?>
        </div>
        
        <div id="tab-reviews" class="tab-content" <?php echo $activeTab !== 'reviews' ? 'style="display:none;"' : ''; ?>>
            <?php include 'admin_modules/admin_review_submissions.php'; ?>
        </div>
        
        <div id="tab-attendance" class="tab-content" <?php echo $activeTab !== 'attendance' ? 'style="display:none;"' : ''; ?>>
            <?php include 'admin_modules/admin_attendance_manage.php'; ?>
        </div>
    </div>
    
    <script>
        // Alert auto-dismiss
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Preserve tab state in URL
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (this.onclick) return; // Skip if already has onclick
                
                const tabName = this.textContent.toLowerCase().includes('manage') ? 'tasks' :
                               this.textContent.toLowerCase().includes('review') ? 'reviews' :
                               'attendance';
                
                window.location.href = '?tab=' + tabName;
            });
        });
    </script>
</body>
</html>