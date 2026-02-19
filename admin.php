<?php
session_start();
require_once 'config.php';

$db = getPadakDB();

// ── Admin credentials (in production, use hashed passwords from DB) ──────
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Change this!

// ── Handle login ─────────────────────────────────────────────────────────
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

// ── Handle logout ────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    header('Location: admin.php');
    exit;
}

// ── Check if logged in ──────────────────────────────────────────────────
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// ── If not logged in, show login modal ──────────────────────────────────
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Padak</title>
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
        </style>
    </head>
    <body>
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

// ══════════════════════════════════════════════════════════════════════════
// ADMIN DASHBOARD (User is logged in)
// ══════════════════════════════════════════════════════════════════════════

$success = '';
$error = '';

// ── Handle task creation ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $taskType = $_POST['task_type'] ?? 'individual';
    $priority = $_POST['priority'] ?? 'medium';
    $maxPoints = (int)($_POST['max_points'] ?? 100);
    $dueDate = $_POST['due_date'] ?? '';
    $resourcesUrl = trim($_POST['resources_url'] ?? '');
    $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;
    
    if (empty($title)) {
        $error = 'Task title is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $descEsc = $db->real_escape_string($desc);
        $resEsc = $db->real_escape_string($resourcesUrl);
        $dueDateValue = $dueDate ? "'" . $db->real_escape_string($dueDate) . "'" : 'NULL';
        $assignedValue = $assignedTo ? $assignedTo : 'NULL';
        
        $sql = "INSERT INTO internship_tasks 
                (title, description, task_type, priority, max_points, due_date, resources_url, assigned_to_student, status, created_by, created_at)
                VALUES ('$titleEsc', '$descEsc', '$taskType', '$priority', $maxPoints, $dueDateValue, '$resEsc', $assignedValue, 'active', 'Admin', NOW())";
        
        if ($db->query($sql)) {
            $success = 'Task created successfully!';
        } else {
            $error = 'Failed to create task: ' . $db->error;
        }
    }
}

// ── Handle task update ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $taskId = (int)$_POST['task_id'];
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $taskType = $_POST['task_type'] ?? 'individual';
    $priority = $_POST['priority'] ?? 'medium';
    $maxPoints = (int)($_POST['max_points'] ?? 100);
    $dueDate = $_POST['due_date'] ?? '';
    $resourcesUrl = trim($_POST['resources_url'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;
    
    if (empty($title)) {
        $error = 'Task title is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $descEsc = $db->real_escape_string($desc);
        $resEsc = $db->real_escape_string($resourcesUrl);
        $dueDateValue = $dueDate ? "'" . $db->real_escape_string($dueDate) . "'" : 'NULL';
        $assignedValue = $assignedTo ? $assignedTo : 'NULL';
        
        $sql = "UPDATE internship_tasks SET
                title='$titleEsc',
                description='$descEsc',
                task_type='$taskType',
                priority='$priority',
                max_points=$maxPoints,
                due_date=$dueDateValue,
                resources_url='$resEsc',
                status='$status',
                assigned_to_student=$assignedValue,
                updated_at=NOW()
                WHERE id=$taskId";
        
        if ($db->query($sql)) {
            $success = 'Task updated successfully!';
        } else {
            $error = 'Failed to update task: ' . $db->error;
        }
    }
}

// ── Handle task deletion ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $taskId = (int)$_POST['task_id'];
    if ($db->query("UPDATE internship_tasks SET status='archived' WHERE id=$taskId")) {
        $success = 'Task archived successfully!';
    } else {
        $error = 'Failed to archive task';
    }
}

// ── Handle submission review ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submission'])) {
    $subId = (int)$_POST['submission_id'];
    $reviewStatus = $_POST['review_status'];
    $pointsEarned = isset($_POST['points_earned']) ? (int)$_POST['points_earned'] : null;
    $feedback = trim($_POST['feedback'] ?? '');
    
    $feedbackEsc = $db->real_escape_string($feedback);
    $pointsValue = $pointsEarned !== null ? $pointsEarned : 'NULL';
    
    $sql = "UPDATE task_submissions SET
            status='$reviewStatus',
            points_earned=$pointsValue,
            feedback='$feedbackEsc',
            reviewed_at=NOW(),
            reviewed_by='Admin'
            WHERE id=$subId";
    
    if ($db->query($sql)) {
        $success = 'Submission reviewed successfully!';
        
        // Send notification to student
        $subData = $db->query("SELECT student_id, task_id FROM task_submissions WHERE id=$subId")->fetch_assoc();
        if ($subData) {
            $taskData = $db->query("SELECT title FROM internship_tasks WHERE id={$subData['task_id']}")->fetch_assoc();
            $taskTitle = $db->real_escape_string($taskData['title'] ?? 'Your task');
            $notifMsg = $reviewStatus === 'approved' ? 
                "Your submission for \"$taskTitle\" has been approved! You earned $pointsEarned points." :
                "Your submission for \"$taskTitle\" requires revision. Check feedback.";
            $notifMsgEsc = $db->real_escape_string($notifMsg);
            
            $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                       VALUES ({$subData['student_id']}, 'Submission Reviewed', '$notifMsgEsc', 'task', NOW())");
        }
    } else {
        $error = 'Failed to review submission';
    }
}

// ── Fetch stats ──────────────────────────────────────────────────────────
// FIXED: Changed 'students' to 'internship_students'
$statsRes = $db->query("SELECT 
    (SELECT COUNT(*) FROM internship_tasks WHERE status='active') as active_tasks,
    (SELECT COUNT(*) FROM task_submissions WHERE status='submitted' OR status='under_review') as pending_reviews,
    (SELECT COUNT(*) FROM task_submissions WHERE status='approved') as completed_tasks,
    (SELECT COUNT(DISTINCT id) FROM internship_students WHERE is_active=1) as total_students
");
$stats = $statsRes->fetch_assoc();

// ── Fetch all tasks ──────────────────────────────────────────────────────
$filterStatus = $_GET['filter'] ?? 'active';
$whereClause = $filterStatus === 'all' ? "1=1" : "t.status='$filterStatus'";

$tasksRes = $db->query("SELECT t.*, 
    COUNT(DISTINCT ts.id) as submission_count,
    SUM(CASE WHEN ts.status IN ('submitted','under_review') THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN ts.status='approved' THEN 1 ELSE 0 END) as approved_count
    FROM internship_tasks t
    LEFT JOIN task_submissions ts ON ts.task_id = t.id
    WHERE $whereClause
    GROUP BY t.id
    ORDER BY t.created_at DESC");
$tasks = [];
while ($row = $tasksRes->fetch_assoc()) $tasks[] = $row;

// ── Fetch pending submissions ────────────────────────────────────────────
// FIXED: Changed 'students' to 'internship_students'
$pendingSubsRes = $db->query("SELECT ts.*, t.title as task_title, t.max_points, s.full_name as student_name, s.email as student_email
    FROM task_submissions ts
    JOIN internship_tasks t ON t.id = ts.task_id
    JOIN internship_students s ON s.id = ts.student_id
    WHERE ts.status IN ('submitted', 'under_review')
    ORDER BY ts.submitted_at DESC
    LIMIT 10");
$pendingSubs = [];
while ($row = $pendingSubsRes->fetch_assoc()) $pendingSubs[] = $row;

// ── Fetch all students for assignment dropdown ───────────────────────────
// FIXED: Changed 'students' to 'internship_students'
$studentsRes = $db->query("SELECT id, full_name, email FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) $students[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Padak</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        :root{--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--o1:#fff7ed;--o2:#ffedd5;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;--purple:#8b5cf6;}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        .admin-header{background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;padding:20px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        .ah-left{display:flex;align-items:center;gap:16px;}
        .ah-logo{width:48px;height:48px;background:linear-gradient(135deg,var(--o5),var(--o4));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
        .ah-title h1{font-size:1.5rem;font-weight:800;margin-bottom:2px;}
        .ah-title p{font-size:.82rem;opacity:.7;}
        .ah-right{display:flex;align-items:center;gap:14px;}
        .ah-user{display:flex;align-items:center;gap:10px;padding:8px 16px;background:rgba(255,255,255,0.1);border-radius:10px;font-size:.85rem;}
        .ah-user i{color:var(--o4);}
        .btn-logout{padding:8px 16px;background:rgba(239,68,68,0.2);border:1.5px solid rgba(239,68,68,0.3);color:#fca5a5;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:6px;}
        .btn-logout:hover{background:rgba(239,68,68,0.3);border-color:rgba(239,68,68,0.5);}
        .admin-container{max-width:1400px;margin:0 auto;padding:28px;}
        .alert{display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:20px;animation:slideIn .3s ease;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
        @keyframes slideIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:32px;}
        .stat-card{background:var(--card);border-radius:14px;padding:22px 24px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:all .2s;}
        .stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.1);}
        .sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
        .sc-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;}
        .sc-icon.orange{background:var(--o1);color:var(--o6);}
        .sc-icon.blue{background:rgba(59,130,246,0.1);color:var(--blue);}
        .sc-icon.green{background:rgba(34,197,94,0.1);color:var(--green);}
        .sc-icon.purple{background:rgba(139,92,246,0.1);color:var(--purple);}
        .sc-value{font-size:2rem;font-weight:900;color:var(--text);line-height:1;}
        .sc-label{font-size:.82rem;color:var(--text3);margin-top:6px;font-weight:500;}
        .tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid var(--border);padding-bottom:0;}
        .tab{padding:12px 20px;border-radius:10px 10px 0 0;border:none;background:none;font-size:.875rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .2s;position:relative;font-family:inherit;}
        .tab:hover{background:var(--bg);color:var(--text);}
        .tab.active{background:var(--card);color:var(--o5);border:1px solid var(--border);border-bottom:2px solid var(--card);margin-bottom:-2px;}
        .tab.active::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:var(--o5);}
        .section{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:24px;}
        .section-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .sh-title{font-size:1.1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;}
        .sh-title i{color:var(--o5);}
        .section-body{padding:24px;}
        .btn{padding:10px 18px;border-radius:9px;font-size:.875rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .2s;}
        .btn-primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
        .btn-secondary{background:var(--card);border:1.5px solid var(--border);color:var(--text2);}
        .btn-secondary:hover{border-color:var(--o5);color:var(--o5);}
        .btn-success{background:rgba(34,197,94,0.1);border:1.5px solid rgba(34,197,94,0.25);color:var(--green);}
        .btn-danger{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.25);color:var(--red);}
        .btn-sm{padding:6px 12px;font-size:.75rem;}
        .form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
        .form-group{margin-bottom:18px;}
        .form-group.full{grid-column:1/-1;}
        .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
        .form-label .required{color:var(--red);}
        .form-input,.form-textarea,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
        .form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
        .form-textarea{resize:vertical;min-height:100px;}
        .form-hint{font-size:.73rem;color:var(--text3);margin-top:5px;}
        .table-responsive{overflow-x:auto;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);}
        .data-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--text2);}
        .data-table tr:hover{background:var(--bg);}
        .data-table td:first-child{font-weight:600;color:var(--text);}
        .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
        .badge-active{background:rgba(34,197,94,0.12);color:#16a34a;}
        .badge-archived{background:rgba(100,116,139,0.12);color:#475569;}
        .badge-urgent{background:rgba(239,68,68,0.12);color:#dc2626;}
        .badge-high{background:rgba(249,115,22,0.12);color:var(--o6);}
        .badge-medium{background:rgba(234,179,8,0.12);color:#854d0e;}
        .badge-low{background:rgba(34,197,94,0.12);color:#16a34a;}
        .badge-individual{background:rgba(59,130,246,0.12);color:#1d4ed8;}
        .badge-team{background:rgba(139,92,246,0.12);color:#6d28d9;}
        .badge-submitted{background:rgba(59,130,246,0.12);color:#1d4ed8;}
        .badge-review{background:rgba(139,92,246,0.12);color:#6d28d9;}
        .badge-approved{background:rgba(34,197,94,0.12);color:#16a34a;}
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
        .modal.active{display:flex;}
        .modal-content{background:var(--card);border-radius:16px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
        .modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .mh-title{font-size:1.2rem;font-weight:700;color:var(--text);}
        .modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:4px;transition:color .2s;}
        .modal-close:hover{color:var(--red);}
        .modal-body{padding:24px;}
        .modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}
        .empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
        .empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
        .empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
        .sub-card{border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:16px;transition:all .2s;}
        .sub-card:hover{box-shadow:0 4px 16px rgba(0,0,0,0.08);}
        .sub-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}
        .sub-title{font-size:.95rem;font-weight:700;color:var(--text);}
        .sub-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:.75rem;color:var(--text3);margin-bottom:12px;}
        .sub-meta-item{display:flex;align-items:center;gap:4px;}
        .sub-content{font-size:.82rem;color:var(--text2);line-height:1.6;margin-bottom:12px;background:var(--bg);padding:12px;border-radius:8px;}
        .sub-actions{display:flex;gap:8px;}
        .filter-bar{display:flex;gap:8px;margin-bottom:20px;}
        .filter-btn{padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.8rem;font-weight:500;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;}
        .filter-btn:hover{border-color:var(--o5);color:var(--o5);}
        .filter-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;}
        @media(max-width:768px){.admin-header{flex-direction:column;align-items:flex-start;gap:12px;}.form-grid{grid-template-columns:1fr;}.stats-grid{grid-template-columns:1fr;}.admin-container{padding:16px;}}
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="ah-left">
            <div class="ah-logo"><i class="fas fa-tasks"></i></div>
            <div class="ah-title">
                <h1>Admin Dashboard</h1>
                <p>Task Management & Review Portal</p>
            </div>
        </div>
        <div class="ah-right">
            <div class="ah-user">
                <i class="fas fa-user-shield"></i>
                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </div>
            <a href="?logout=1" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    <div class="admin-container">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-circle-check"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="sc-top"><div class="sc-icon orange"><i class="fas fa-clipboard-list"></i></div></div><div class="sc-value"><?php echo $stats['active_tasks']; ?></div><div class="sc-label">Active Tasks</div></div>
            <div class="stat-card"><div class="sc-top"><div class="sc-icon blue"><i class="fas fa-hourglass-half"></i></div></div><div class="sc-value"><?php echo $stats['pending_reviews']; ?></div><div class="sc-label">Pending Reviews</div></div>
            <div class="stat-card"><div class="sc-top"><div class="sc-icon green"><i class="fas fa-circle-check"></i></div></div><div class="sc-value"><?php echo $stats['completed_tasks']; ?></div><div class="sc-label">Completed Tasks</div></div>
            <div class="stat-card"><div class="sc-top"><div class="sc-icon purple"><i class="fas fa-users"></i></div></div><div class="sc-value"><?php echo $stats['total_students']; ?></div><div class="sc-label">Active Students</div></div>
        </div>
        <div class="tabs">
            <button class="tab active" onclick="showTab('tasks')"><i class="fas fa-tasks"></i> Manage Tasks</button>
            <button class="tab" onclick="showTab('reviews')"><i class="fas fa-clipboard-check"></i> Review Submissions<?php if ($stats['pending_reviews'] > 0): ?><span class="badge badge-urgent"><?php echo $stats['pending_reviews']; ?></span><?php endif; ?></button>
        </div>
        <div id="tab-tasks" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <div class="sh-title"><i class="fas fa-clipboard-list"></i>All Tasks</div>
                    <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Create New Task</button>
                </div>
                <div class="section-body">
                    <div class="filter-bar">
                        <a href="?filter=active" class="filter-btn <?php echo $filterStatus==='active'?'active':''; ?>">Active (<?php echo count(array_filter($tasks, fn($t)=>$t['status']==='active')); ?>)</a>
                        <a href="?filter=archived" class="filter-btn <?php echo $filterStatus==='archived'?'active':''; ?>">Archived</a>
                        <a href="?filter=all" class="filter-btn <?php echo $filterStatus==='all'?'active':''; ?>">All Tasks</a>
                    </div>
                    <?php if (empty($tasks)): ?>
                    <div class="empty-state"><i class="fas fa-clipboard-list"></i><h3>No tasks found</h3><p>Create your first task to get started</p></div>
                    <?php else: ?>
                    <div class="table-responsive"><table class="data-table"><thead><tr><th>Task Title</th><th>Type</th><th>Priority</th><th>Points</th><th>Due Date</th><th>Status</th><th>Submissions</th><th>Actions</th></tr></thead><tbody><?php foreach ($tasks as $task): $dueDate = $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '—';$isOverdue = $task['due_date'] && strtotime($task['due_date']) < time();?><tr><td><strong><?php echo htmlspecialchars($task['title']); ?></strong><?php if ($task['assigned_to_student']): ?><br><small style="color:var(--text3);"><i class="fas fa-user fa-xs"></i> Assigned to specific student</small><?php endif; ?></td><td><span class="badge badge-<?php echo $task['task_type']; ?>"><?php echo ucfirst($task['task_type']); ?></span></td><td><span class="badge badge-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span></td><td><?php echo $task['max_points']; ?> pts</td><td style="<?php echo $isOverdue?'color:var(--red);font-weight:600;':''; ?>"><?php echo $dueDate; ?><?php if ($isOverdue): ?><br><small><i class="fas fa-triangle-exclamation"></i> Overdue</small><?php endif; ?></td><td><span class="badge badge-<?php echo $task['status']; ?>"><?php echo ucfirst($task['status']); ?></span></td><td><?php if ($task['submission_count'] > 0): ?><strong><?php echo $task['submission_count']; ?></strong> total<?php if ($task['pending_count'] > 0): ?><br><small style="color:var(--blue);"><i class="fas fa-hourglass-half"></i> <?php echo $task['pending_count']; ?> pending</small><?php endif; ?><?php else: ?><span style="color:var(--text3);">No submissions</span><?php endif; ?></td><td><button class="btn btn-secondary btn-sm" onclick='editTask(<?php echo json_encode($task); ?>)'><i class="fas fa-edit"></i> Edit</button></td></tr><?php endforeach; ?></tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="tab-reviews" class="tab-content" style="display:none;">
            <div class="section">
                <div class="section-header"><div class="sh-title"><i class="fas fa-clipboard-check"></i>Pending Submissions</div></div>
                <div class="section-body">
                    <?php if (empty($pendingSubs)): ?><div class="empty-state"><i class="fas fa-clipboard-check"></i><h3>No pending submissions</h3><p>All submissions have been reviewed!</p></div><?php else: ?><?php foreach ($pendingSubs as $sub): ?><div class="sub-card"><div class="sub-header"><div><div class="sub-title"><?php echo htmlspecialchars($sub['task_title']); ?></div><div class="sub-meta"><span class="sub-meta-item"><i class="fas fa-user"></i><?php echo htmlspecialchars($sub['student_name']); ?></span><span class="sub-meta-item"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($sub['student_email']); ?></span><span class="sub-meta-item"><i class="fas fa-clock"></i><?php echo date('M d, Y g:i A', strtotime($sub['submitted_at'])); ?></span><span class="sub-meta-item"><i class="fas fa-star"></i>Max: <?php echo $sub['max_points']; ?> pts</span></div></div><span class="badge badge-<?php echo $sub['status']; ?>"><?php echo $sub['status']==='under_review'?'In Review':'Submitted'; ?></span></div><?php if ($sub['submission_text']): ?><div class="sub-content"><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars(substr($sub['submission_text'], 0, 300))); ?><?php if (strlen($sub['submission_text']) > 300) echo '...'; ?></div><?php endif; ?><?php if ($sub['github_link']): ?><div style="margin-bottom:10px;"><strong style="font-size:.82rem;">GitHub:</strong><a href="<?php echo htmlspecialchars($sub['github_link']); ?>" target="_blank" style="color:var(--blue);font-size:.82rem;word-break:break-all;"><i class="fab fa-github"></i> <?php echo htmlspecialchars($sub['github_link']); ?></a></div><?php endif; ?><?php if ($sub['submission_url']): ?><div style="margin-bottom:10px;"><strong style="font-size:.82rem;">Live URL:</strong><a href="<?php echo htmlspecialchars($sub['submission_url']); ?>" target="_blank" style="color:var(--blue);font-size:.82rem;word-break:break-all;"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($sub['submission_url']); ?></a></div><?php endif; ?><?php if ($sub['file_name']): ?><div style="margin-bottom:10px;"><strong style="font-size:.82rem;">File:</strong><a href="<?php echo htmlspecialchars($sub['file_path']); ?>" download style="color:var(--blue);font-size:.82rem;"><i class="fas fa-download"></i> <?php echo htmlspecialchars($sub['file_name']); ?></a></div><?php endif; ?><div class="sub-actions"><button class="btn btn-primary btn-sm" onclick='reviewSubmission(<?php echo json_encode($sub); ?>)'><i class="fas fa-clipboard-check"></i> Review</button><button class="btn btn-secondary btn-sm" onclick='viewFullSubmission(<?php echo json_encode($sub); ?>)'><i class="fas fa-eye"></i> View Details</button></div></div><?php endforeach; ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div id="taskModal" class="modal"><div class="modal-content"><div class="modal-header"><div class="mh-title" id="modalTitle">Create New Task</div><button class="modal-close" onclick="closeModal('taskModal')">&times;</button></div><form method="POST" id="taskForm"><div class="modal-body"><input type="hidden" name="task_id" id="task_id"><div class="form-group"><label class="form-label">Task Title <span class="required">*</span></label><input type="text" name="title" id="task_title" class="form-input" placeholder="e.g., Build a React Calculator App" required></div><div class="form-group"><label class="form-label">Description</label><textarea name="description" id="task_description" class="form-textarea" placeholder="Detailed task requirements, deliverables, and instructions..."></textarea></div><div class="form-grid"><div class="form-group"><label class="form-label">Task Type</label><select name="task_type" id="task_type" class="form-select"><option value="individual">Individual</option><option value="team">Team</option></select></div><div class="form-group"><label class="form-label">Priority</label><select name="priority" id="task_priority" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div><div class="form-group"><label class="form-label">Max Points</label><input type="number" name="max_points" id="task_points" class="form-input" value="100" min="0" step="10"></div><div class="form-group"><label class="form-label">Due Date</label><input type="date" name="due_date" id="task_due_date" class="form-input"></div><div class="form-group full"><label class="form-label">Resources URL <span style="font-weight:400;color:var(--text3);">(optional)</span></label><input type="url" name="resources_url" id="task_resources" class="form-input" placeholder="https://docs.example.com/task-guide"><div class="form-hint">Link to documentation, tutorials, or reference materials</div></div><div class="form-group full"><label class="form-label">Assign to Student <span style="font-weight:400;color:var(--text3);">(optional - leave blank for all students)</span></label><select name="assigned_to_student" id="task_assigned" class="form-select"><option value="">All Students</option><?php foreach ($students as $student): ?><option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)</option><?php endforeach; ?></select></div><div class="form-group full" id="statusGroup" style="display:none;"><label class="form-label">Status</label><select name="status" id="task_status" class="form-select"><option value="active">Active</option><option value="archived">Archived</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('taskModal')">Cancel</button><button type="submit" name="create_task" id="submitBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Create Task</button></div></form></div></div>
    <div id="reviewModal" class="modal"><div class="modal-content"><div class="modal-header"><div class="mh-title">Review Submission</div><button class="modal-close" onclick="closeModal('reviewModal')">&times;</button></div><form method="POST"><div class="modal-body"><input type="hidden" name="submission_id" id="review_sub_id"><div id="reviewTaskInfo" style="padding:14px;background:var(--bg);border-radius:10px;margin-bottom:18px;"></div><div class="form-group"><label class="form-label">Review Status <span class="required">*</span></label><select name="review_status" id="review_status" class="form-select" required><option value="under_review">Under Review</option><option value="approved">Approve</option><option value="revision_requested">Request Revision</option><option value="rejected">Reject</option></select></div><div class="form-group"><label class="form-label">Points Earned</label><input type="number" name="points_earned" id="review_points" class="form-input" min="0" placeholder="Leave blank if not approving"><div class="form-hint">Required when approving the submission</div></div><div class="form-group"><label class="form-label">Feedback</label><textarea name="feedback" id="review_feedback" class="form-textarea" placeholder="Provide constructive feedback to the student..." style="min-height:120px;"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal')">Cancel</button><button type="submit" name="review_submission" class="btn btn-primary"><i class="fas fa-check"></i> Submit Review</button></div></form></div></div>
    <div id="viewDetailsModal" class="modal"><div class="modal-content"><div class="modal-header"><div class="mh-title">Submission Details</div><button class="modal-close" onclick="closeModal('viewDetailsModal')">&times;</button></div><div class="modal-body"><div id="fullSubmissionContent"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('viewDetailsModal')">Close</button></div></div></div>
    <script>
        function showTab(tab){document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.tab-content').forEach(c=>c.style.display='none');event.target.closest('.tab').classList.add('active');document.getElementById('tab-'+tab).style.display='block';}
        function closeModal(id){document.getElementById(id).classList.remove('active');}
        function openCreateModal(){document.getElementById('modalTitle').textContent='Create New Task';document.getElementById('taskForm').reset();document.getElementById('task_id').value='';document.getElementById('submitBtn').innerHTML='<i class="fas fa-plus"></i> Create Task';document.getElementById('submitBtn').name='create_task';document.getElementById('statusGroup').style.display='none';document.getElementById('taskModal').classList.add('active');}
        function editTask(task){document.getElementById('modalTitle').textContent='Edit Task';document.getElementById('task_id').value=task.id;document.getElementById('task_title').value=task.title;document.getElementById('task_description').value=task.description||'';document.getElementById('task_type').value=task.task_type;document.getElementById('task_priority').value=task.priority;document.getElementById('task_points').value=task.max_points;document.getElementById('task_due_date').value=task.due_date?task.due_date.split(' ')[0]:'';document.getElementById('task_resources').value=task.resources_url||'';document.getElementById('task_assigned').value=task.assigned_to_student||'';document.getElementById('task_status').value=task.status;document.getElementById('submitBtn').innerHTML='<i class="fas fa-save"></i> Update Task';document.getElementById('submitBtn').name='update_task';document.getElementById('statusGroup').style.display='block';document.getElementById('taskModal').classList.add('active');}
        function reviewSubmission(sub){document.getElementById('review_sub_id').value=sub.id;document.getElementById('review_points').max=sub.max_points;document.getElementById('review_points').placeholder='Max: '+sub.max_points+' points';const info=`<strong style="font-size:.95rem;">${sub.task_title}</strong><br><div style="margin-top:8px;font-size:.8rem;color:var(--text3);"><i class="fas fa-user"></i> ${sub.student_name} &nbsp;•&nbsp;<i class="fas fa-star"></i> Max Points: ${sub.max_points}</div>`;document.getElementById('reviewTaskInfo').innerHTML=info;document.getElementById('reviewModal').classList.add('active');}
        function viewFullSubmission(sub){let content='<div style="line-height:1.8;"><h3 style="color:var(--o5);margin-bottom:16px;font-size:1.2rem;"><i class="fas fa-clipboard-list"></i> '+sub.task_title+'</h3>';content+='<div style="background:var(--bg);padding:14px;border-radius:8px;margin-bottom:16px;"><strong style="color:var(--text);"><i class="fas fa-user"></i> Student:</strong> '+sub.student_name+' <span style="color:var(--text3);">('+sub.student_email+')</span><br><strong style="color:var(--text);"><i class="fas fa-clock"></i> Submitted:</strong> '+sub.submitted_at+'<br><strong style="color:var(--text);"><i class="fas fa-star"></i> Max Points:</strong> '+sub.max_points+' pts<br><strong style="color:var(--text);"><i class="fas fa-info-circle"></i> Status:</strong> <span class="badge badge-'+sub.status+'">'+(sub.status==='under_review'?'In Review':'Submitted')+'</span></div>';if(sub.submission_text){content+='<div style="margin-bottom:16px;"><strong style="color:var(--text);display:block;margin-bottom:8px;"><i class="fas fa-align-left"></i> Description:</strong><div style="background:var(--bg);padding:12px;border-radius:8px;white-space:pre-wrap;color:var(--text2);font-size:.9rem;line-height:1.6;">'+sub.submission_text+'</div></div>';}if(sub.github_link){content+='<div style="margin-bottom:12px;"><strong style="color:var(--text);"><i class="fab fa-github"></i> GitHub:</strong><br><a href="'+sub.github_link+'" target="_blank" style="color:var(--blue);word-break:break-all;">'+sub.github_link+' <i class="fas fa-external-link-alt fa-xs"></i></a></div>';}if(sub.submission_url){content+='<div style="margin-bottom:12px;"><strong style="color:var(--text);"><i class="fas fa-globe"></i> Live URL:</strong><br><a href="'+sub.submission_url+'" target="_blank" style="color:var(--blue);word-break:break-all;">'+sub.submission_url+' <i class="fas fa-external-link-alt fa-xs"></i></a></div>';}if(sub.file_name){content+='<div style="margin-bottom:12px;"><strong style="color:var(--text);"><i class="fas fa-file"></i> Attached File:</strong><br><a href="'+sub.file_path+'" download style="color:var(--blue);"><i class="fas fa-download"></i> '+sub.file_name+'</a></div>';}content+='</div>';document.getElementById('fullSubmissionContent').innerHTML=content;document.getElementById('viewDetailsModal').classList.add('active');}
        document.querySelectorAll('.modal').forEach(modal=>{modal.addEventListener('click',function(e){if(e.target===this){this.classList.remove('active');}});});
        setTimeout(()=>{document.querySelectorAll('.alert').forEach(alert=>{alert.style.opacity='0';setTimeout(()=>alert.remove(),300);});},5000);
    </script>
</body>
</html>