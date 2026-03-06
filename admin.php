<?php
ob_start();
session_start();
require_once 'config.php';

$db = getPadakDB();

// ── EARLY POST HANDLERS (must run before any HTML output) ────────────────
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {

    // --- Create Task ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $taskType   = $_POST['task_type'] ?? 'individual';
        $priority   = $_POST['priority'] ?? 'medium';
        $maxPoints  = (int)($_POST['max_points'] ?? 100);
        $dueDate    = $_POST['due_date'] ?? '';
        $resUrl     = trim($_POST['resources_url'] ?? '');
        $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;
        if (empty($title)) {
            $_SESSION['admin_error'] = 'Task title is required';
        } else {
            $tE = $db->real_escape_string($title);
            $dE = $db->real_escape_string($desc);
            $rE = $db->real_escape_string($resUrl);
            $dv = $dueDate ? "'".$db->real_escape_string($dueDate)."'" : 'NULL';
            $av = $assignedTo ?: 'NULL';
            $sql = "INSERT INTO internship_tasks (title,description,task_type,priority,max_points,due_date,resources_url,assigned_to_student,status,created_by,created_at)
                    VALUES ('$tE','$dE','$taskType','$priority',$maxPoints,$dv,'$rE',$av,'active','Admin',NOW())";
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'Task created successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to create task: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=tasks');
        exit;
    }

    // --- Update Task ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $taskId     = (int)$_POST['task_id'];
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $taskType   = $_POST['task_type'] ?? 'individual';
        $priority   = $_POST['priority'] ?? 'medium';
        $maxPoints  = (int)($_POST['max_points'] ?? 100);
        $dueDate    = $_POST['due_date'] ?? '';
        $resUrl     = trim($_POST['resources_url'] ?? '');
        $status     = $_POST['status'] ?? 'active';
        $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;
        if (empty($title)) {
            $_SESSION['admin_error'] = 'Task title is required';
        } else {
            $tE = $db->real_escape_string($title);
            $dE = $db->real_escape_string($desc);
            $rE = $db->real_escape_string($resUrl);
            $dv = $dueDate ? "'".$db->real_escape_string($dueDate)."'" : 'NULL';
            $av = $assignedTo ?: 'NULL';
            $sql = "UPDATE internship_tasks SET
                    title='$tE', description='$dE', task_type='$taskType',
                    priority='$priority', max_points=$maxPoints, due_date=$dv,
                    resources_url='$rE', status='$status', assigned_to_student=$av,
                    updated_at=NOW() WHERE id=$taskId";
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'Task updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update task: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=tasks');
        exit;
    }

    // --- Review Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submission'])) {
        $subId        = (int)$_POST['submission_id'];
        $reviewStatus = $db->real_escape_string($_POST['review_status'] ?? 'under_review');
        $pointsEarned = isset($_POST['points_earned']) && $_POST['points_earned'] !== '' ? (int)$_POST['points_earned'] : null;
        $feedback     = $db->real_escape_string(trim($_POST['feedback'] ?? ''));
        $pointsValue  = $pointsEarned !== null ? $pointsEarned : 'NULL';

        $sql = "UPDATE task_submissions SET
                status='$reviewStatus', points_earned=$pointsValue,
                feedback='$feedback', reviewed_at=NOW(), reviewed_by='Admin'
                WHERE id=$subId";

        if ($db->query($sql)) {
            $subData = $db->query("SELECT student_id, task_id FROM task_submissions WHERE id=$subId")->fetch_assoc();
            if ($subData) {
                $studentId = (int)$subData['student_id'];
                $taskId    = (int)$subData['task_id'];
                $taskData  = $db->query("SELECT title FROM internship_tasks WHERE id=$taskId")->fetch_assoc();
                $taskTitle = $db->real_escape_string($taskData['title'] ?? 'Your task');

                if ($reviewStatus === 'approved' && $pointsEarned !== null && $pointsEarned > 0) {
                    $reasonEsc = $db->real_escape_string("Earned from task: " . ($taskData['title'] ?? ''));
                    $db->query("INSERT INTO student_points_log (student_id, points, reason, task_id, awarded_at)
                               VALUES ($studentId, $pointsEarned, '$reasonEsc', $taskId, NOW())");
                    $totalRes = $db->query("SELECT SUM(points) as total FROM student_points_log WHERE student_id=$studentId");
                    $total    = $totalRes ? (int)$totalRes->fetch_assoc()['total'] : 0;
                    $db->query("UPDATE internship_students SET total_points=$total WHERE id=$studentId");
                    $_SESSION['admin_success'] = "Submission approved! $pointsEarned points awarded.";
                } else {
                    $_SESSION['admin_success'] = 'Submission reviewed successfully!';
                }

                $notifMsg = $reviewStatus === 'approved'
                    ? "Your submission for \"$taskTitle\" has been approved! You earned $pointsEarned points."
                    : "Your submission for \"$taskTitle\" requires revision. Check feedback.";
                $notifMsgEsc = $db->real_escape_string($notifMsg);
                $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                           VALUES ($studentId, 'Submission Reviewed', '$notifMsgEsc', 'task', NOW())");
            }
        } else {
            $_SESSION['admin_error'] = 'Failed to review submission: ' . $db->error;
        }
        ob_end_clean();
        header('Location: admin.php?tab=reviews');
        exit;
    }

    // --- Update Single Submission Status (All Submissions tab) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission_status'])) {
        $submissionId = (int)$_POST['submission_id'];
        $newStatus    = $db->real_escape_string(trim($_POST['status'] ?? ''));
        $feedback     = $db->real_escape_string(trim($_POST['feedback'] ?? ''));
        $pointsEarned = (isset($_POST['points_earned']) && $_POST['points_earned'] !== '') ? (int)$_POST['points_earned'] : null;
        $reviewedBy   = $db->real_escape_string(trim($_SESSION['admin_username'] ?? 'Admin'));

        $allowed = ['submitted','under_review','approved','rejected','revision_requested'];
        if (in_array($newStatus, $allowed)) {
            $pointsSQL = ($newStatus === 'approved' && $pointsEarned !== null) ? ", points_earned=$pointsEarned" : '';
            $sql = "UPDATE task_submissions SET status='$newStatus', feedback='$feedback',
                    reviewed_by='$reviewedBy', reviewed_at=NOW() $pointsSQL, updated_at=NOW()
                    WHERE id=$submissionId";
            if ($db->query($sql)) {
                $subRow = $db->query("SELECT student_id FROM task_submissions WHERE id=$submissionId")->fetch_assoc();
                if ($subRow) {
                    $sid = (int)$subRow['student_id'];
                    if ($newStatus === 'approved') {
                        $db->query("UPDATE internship_students SET total_points = (
                            SELECT COALESCE(SUM(points_earned),0) FROM task_submissions
                            WHERE student_id=$sid AND status='approved'
                        ) WHERE id=$sid");
                    }
                    $statusLabel = $db->real_escape_string(ucfirst(str_replace('_',' ',$newStatus)));
                    $notifMsg    = $db->real_escape_string("Your submission has been updated to: $statusLabel." . ($feedback ? " Feedback: $feedback" : ''));
                    $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                                VALUES ($sid, '$statusLabel', '$notifMsg', 'task', NOW())");
                }
                $_SESSION['admin_success'] = 'Submission updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update submission: ' . $db->error;
            }
        } else {
            $_SESSION['admin_error'] = 'Invalid status value.';
        }
        ob_end_clean();
        header('Location: admin.php?tab=all_submissions');
        exit;
    }

    // --- Bulk Submission Status Update ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_submissions'])) {
        $ids        = $_POST['selected_ids'] ?? [];
        $bulkStatus = $db->real_escape_string(trim($_POST['bulk_status'] ?? ''));
        $allowed    = ['under_review','approved','rejected'];
        if (!empty($ids) && in_array($bulkStatus, $allowed)) {
            $idsInt     = array_map('intval', $ids);
            $idList     = implode(',', $idsInt);
            $reviewedBy = $db->real_escape_string($_SESSION['admin_username'] ?? 'Admin');
            $db->query("UPDATE task_submissions SET status='$bulkStatus', reviewed_by='$reviewedBy',
                        reviewed_at=NOW(), updated_at=NOW() WHERE id IN ($idList)");
            $cnt = count($idsInt);
            $_SESSION['admin_success'] = "$cnt submission(s) updated to " . ucfirst(str_replace('_',' ',$bulkStatus)) . '.';
        } else {
            $_SESSION['admin_error'] = 'Please select submissions and a valid bulk action.';
        }
        ob_end_clean();
        header('Location: admin.php?tab=all_submissions');
        exit;
    }

    // --- Save Announcement (Create or Update) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
        $title          = trim($_POST['title'] ?? '');
        $content        = trim($_POST['content'] ?? '');
        $type           = $_POST['type'] ?? 'general';
        $priority       = $_POST['priority'] ?? 'normal';
        $batch_id       = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        $coordinator_id = !empty($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : null;
        $target_all     = isset($_POST['target_all']) ? 1 : 0;
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $edit_id        = (int)($_POST['announcement_id'] ?? 0);

        $errors = [];
        if (empty($title))   $errors[] = 'Title is required';
        if (empty($content)) $errors[] = 'Content is required';
        if (!in_array($type,     ['general','task_deadline','certificate','attendance'])) $errors[] = 'Invalid type';
        if (!in_array($priority, ['urgent','important','normal']))                        $errors[] = 'Invalid priority';

        if (empty($errors)) {
            if ($edit_id > 0) {
                $stmt = $db->prepare("UPDATE announcements SET title=?,content=?,type=?,priority=?,batch_id=?,coordinator_id=?,target_all=?,is_active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->bind_param("ssssiiiii", $title, $content, $type, $priority, $batch_id, $coordinator_id, $target_all, $is_active, $edit_id);
                $_SESSION[$stmt->execute() ? 'admin_success' : 'admin_error'] = $stmt->execute() ? 'Announcement updated successfully' : 'Failed to update announcement';
            } else {
                $stmt = $db->prepare("INSERT INTO announcements (title,content,type,priority,batch_id,coordinator_id,target_all,is_active) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssiiii", $title, $content, $type, $priority, $batch_id, $coordinator_id, $target_all, $is_active);
                $_SESSION[$stmt->execute() ? 'admin_success' : 'admin_error'] = $stmt->execute() ? 'Announcement created successfully' : 'Failed to create announcement';
            }
        } else {
            $_SESSION['admin_error'] = implode(', ', $errors);
        }
        ob_end_clean();
        header('Location: admin.php?tab=announcements');
        exit;
    }

    // --- Delete Announcement ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
        $ann_id = (int)($_POST['announcement_id'] ?? 0);
        if ($ann_id > 0) {
            $stmt = $db->prepare("DELETE FROM announcements WHERE id=?");
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute()) {
                $db->query("DELETE FROM announcement_reads WHERE announcement_id=$ann_id");
                $_SESSION['admin_success'] = 'Announcement deleted successfully';
            } else {
                $_SESSION['admin_error'] = 'Failed to delete announcement';
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=announcements');
        exit;
    }

    // --- Mark Attendance ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
        $domainInterest = $_POST['domain_interest'] ?? '';
        $attendanceDate = $_POST['attendance_date'] ?? '';
        $attendanceData = $_POST['attendance'] ?? [];

        if (!$domainInterest || !$attendanceDate || empty($attendanceData)) {
            $_SESSION['admin_error'] = 'Please select domain, date, and mark at least one student';
        } else {
            // Ensure date column exists
            $columnCheck = $db->query("SHOW COLUMNS FROM student_attendance LIKE 'date'");
            if ($columnCheck->num_rows == 0) {
                $db->query("ALTER TABLE student_attendance ADD COLUMN date DATE NULL AFTER batch_id");
                $db->query("ALTER TABLE student_attendance ADD COLUMN marked_by VARCHAR(100) NULL");
                $db->query("ALTER TABLE student_attendance MODIFY status ENUM('active','inactive','completed','dropped','present','absent','late') DEFAULT 'active'");
            }

            $dateEsc = $db->real_escape_string($attendanceDate);
            $count   = 0;
            foreach ($attendanceData as $studentId => $status) {
                $studentId = (int)$studentId;
                if (empty($status)) continue;
                $statusEsc = $db->real_escape_string($status);
                $exists    = $db->query("SELECT id FROM student_attendance WHERE student_id=$studentId AND date='$dateEsc'")->fetch_assoc();
                if ($exists) {
                    $db->query("UPDATE student_attendance SET status='$statusEsc', enrolled_date=NOW() WHERE id={$exists['id']}");
                } else {
                    $db->query("INSERT INTO student_attendance (student_id, batch_id, date, status, enrolled_date) VALUES ($studentId, NULL, '$dateEsc', '$statusEsc', NOW())");
                }
                $count++;
            }
            $_SESSION['admin_success'] = "Attendance marked for $count students on " . date('M d, Y', strtotime($attendanceDate));
        }
        ob_end_clean();
        header('Location: admin.php?tab=attendance&domain=' . urlencode($domainInterest));
        exit;
    }

}
// ── END EARLY POST HANDLERS ──────────────────────────────────────────────


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

// Get Statistics
$statsRes = $db->query("SELECT 
    (SELECT COUNT(*) FROM internship_tasks WHERE status='active') as active_tasks,
    (SELECT COUNT(*) FROM task_submissions WHERE status='submitted' OR status='under_review') as pending_reviews,
    (SELECT COUNT(*) FROM task_submissions WHERE status='approved') as completed_tasks,
    (SELECT COUNT(DISTINCT id) FROM internship_students WHERE is_active=1) as total_students,
    (SELECT COUNT(*) FROM task_submissions) as total_submissions,
    (SELECT COUNT(*) FROM announcements WHERE is_active=1) as active_announcements
");
$stats = $statsRes->fetch_assoc();

// Get success/error messages from session
$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);
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
        .stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:28px;}
        .stat-card{background:var(--card);border-radius:12px;padding:16px 18px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:all .2s;}
        .stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.1);}
        .sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
        .sc-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
        .sc-icon.orange{background:var(--o1);color:var(--o6);}
        .sc-icon.blue{background:rgba(59,130,246,0.1);color:var(--blue);}
        .sc-icon.green{background:rgba(34,197,94,0.1);color:var(--green);}
        .sc-icon.purple{background:rgba(139,92,246,0.1);color:var(--purple);}
        .sc-icon.yellow{background:rgba(234,179,8,0.1);color:#ca8a04;}
        .sc-icon.cyan{background:rgba(6,182,212,0.1);color:#0891b2;}
        .sc-value{font-size:1.75rem;font-weight:900;color:var(--text);line-height:1;}
        .sc-label{font-size:.75rem;color:var(--text3);margin-top:6px;font-weight:600;letter-spacing:.2px;}
        .tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid var(--border);padding-bottom:0;flex-wrap:wrap;}
        .tab{padding:12px 20px;border-radius:10px 10px 0 0;border:none;background:none;font-size:.875rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .2s;position:relative;font-family:inherit;}
        .tab:hover{background:var(--bg);color:var(--text);}
        .tab.active{background:var(--card);color:var(--o5);border:1px solid var(--border);border-bottom:2px solid var(--card);margin-bottom:-2px;}
        .tab.active::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:var(--o5);}
        .tab-content{display:none;}
        .tab-content.active{display:block;}
        .badge-count{display:inline-flex;align-items:center;padding:2px 8px;border-radius:6px;font-size:.7rem;font-weight:700;background:rgba(239,68,68,0.12);color:#dc2626;margin-left:6px;}
        @media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr);}}
        @media(max-width:768px){.admin-header{flex-direction:column;align-items:flex-start;gap:12px;}.stats-grid{grid-template-columns:repeat(2,1fr);}.admin-container{padding:16px;}}
        @media(max-width:480px){.stats-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="ah-left">
            <div class="ah-logo"><i class="fas fa-tasks"></i></div>
            <div class="ah-title">
                <h1>Admin Dashboard</h1>
                <p>Task & Attendance Management</p>
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
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon orange"><i class="fas fa-clipboard-list"></i></div></div>
                <div class="sc-value"><?php echo $stats['active_tasks']; ?></div>
                <div class="sc-label">Active Tasks</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon blue"><i class="fas fa-hourglass-half"></i></div></div>
                <div class="sc-value"><?php echo $stats['pending_reviews']; ?></div>
                <div class="sc-label">Pending Reviews</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon green"><i class="fas fa-circle-check"></i></div></div>
                <div class="sc-value"><?php echo $stats['completed_tasks']; ?></div>
                <div class="sc-label">Completed Tasks</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon cyan"><i class="fas fa-bullhorn"></i></div></div>
                <div class="sc-value"><?php echo $stats['active_announcements']; ?></div>
                <div class="sc-label">Active Announcements</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon yellow"><i class="fas fa-paper-plane"></i></div></div>
                <div class="sc-value"><?php echo $stats['total_submissions']; ?></div>
                <div class="sc-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon purple"><i class="fas fa-users"></i></div></div>
                <div class="sc-value"><?php echo $stats['total_students']; ?></div>
                <div class="sc-label">Active Students</div>
            </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab" data-tab="tasks">
                <i class="fas fa-tasks"></i> Manage Tasks
            </button>
            <button class="tab" data-tab="reviews">
                <i class="fas fa-clipboard-check"></i> Review Submissions
                <?php if ($stats['pending_reviews'] > 0): ?>
                <span class="badge-count"><?php echo $stats['pending_reviews']; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab" data-tab="all_submissions">
                <i class="fas fa-inbox"></i> All Submissions
            </button>
            <button class="tab" data-tab="announcements">
                <i class="fas fa-bullhorn"></i> Announcements
            </button>
            <button class="tab" data-tab="attendance">
                <i class="fas fa-calendar-check"></i> Attendance
            </button>
            <button class="tab" data-tab="users">
                <i class="fas fa-users"></i> User Management
            </button>
            <button class="tab" data-tab="messages">
                <i class="fas fa-comments"></i> Messages
            </button>
        </div>
        
        <!-- Tab Content Panels -->
        <div id="tab-tasks" class="tab-content">
            <?php include 'admin_modules/admin_manage_tasks.php'; ?>
        </div>
        
        <div id="tab-reviews" class="tab-content">
            <?php include 'admin_modules/admin_review_submissions.php'; ?>
        </div>

        <div id="tab-all_submissions" class="tab-content">
            <?php include 'admin_modules/admin_all_submissions.php'; ?>
        </div>
        
        <div id="tab-announcements" class="tab-content">
            <?php include 'admin_modules/admin_announce_manage.php'; ?>
        </div>
        
        <div id="tab-attendance" class="tab-content">
            <?php include 'admin_modules/admin_attendance_manage.php'; ?>
        </div>
        
        <div id="tab-users" class="tab-content">
            <?php include 'admin_modules/admin_user_management.php'; ?>
        </div>

        <div id="tab-messages" class="tab-content">
            <?php include 'admin_modules/admin_all_messages.php'; ?>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(function(t) {
                t.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(function(c) {
                c.classList.remove('active');
            });
            var btn = document.querySelector('.tab[data-tab="' + tabName + '"]');
            if (btn) btn.classList.add('active');
            var panel = document.getElementById('tab-' + tabName);
            if (panel) panel.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab[data-tab]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    showTab(this.getAttribute('data-tab'));
                });
            });

            // Dismiss alerts after 5s
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(a) {
                    a.style.transition = 'opacity .3s';
                    a.style.opacity = '0';
                    setTimeout(function() { a.remove(); }, 300);
                });
            }, 5000);

            // Read active tab from ?tab= query param — no hash ever
            var params = new URLSearchParams(window.location.search);
            var activeTab = params.get('tab') || 'tasks';
            showTab(activeTab);
        });
    </script>
</body>
</html>