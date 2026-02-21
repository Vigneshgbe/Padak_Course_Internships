<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];

$er = $db->query("SELECT se.batch_id, ib.batch_name FROM student_enrollments se JOIN internship_batches ib ON se.batch_id=ib.id WHERE se.student_id=$sid AND se.status='active' LIMIT 1");
$enrollment = $er ? $er->fetch_assoc() : null;
$batchId = $enrollment ? (int)$enrollment['batch_id'] : 0;

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$success = ''; $error = '';

// Fetch specific task if taskId set
$selectedTask = null;
if ($taskId && $batchId) {
    $tr = $db->query("SELECT ct.*, ts.id as sub_id, ts.submission_text, ts.github_link, ts.live_link, ts.status as sub_status, ts.marks_obtained, ts.coordinator_feedback, ts.submitted_at
        FROM coordinator_tasks ct
        LEFT JOIN task_submissions ts ON ct.id=ts.task_id AND ts.student_id=$sid
        WHERE ct.id=$taskId AND ct.batch_id=$batchId LIMIT 1");
    $selectedTask = $tr ? $tr->fetch_assoc() : null;
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task'])) {
    $tid = (int)$_POST['task_id'];
    $text = trim($_POST['submission_text'] ?? '');
    $github = trim($_POST['github_link'] ?? '');
    $live = trim($_POST['live_link'] ?? '');

    if (empty($text) && empty($github)) {
        $error = 'Please provide a submission description or GitHub link.';
    } else {
        // Check existing
        $chk = $db->query("SELECT id, status FROM task_submissions WHERE task_id=$tid AND student_id=$sid");
        $existing = $chk ? $chk->fetch_assoc() : null;

        if ($existing && !in_array($existing['status'], ['resubmit'])) {
            $error = 'You have already submitted this task.';
        } else {
            $textEsc = $db->real_escape_string($text);
            $ghEsc = $db->real_escape_string($github);
            $liveEsc = $db->real_escape_string($live);
            if ($existing) {
                $db->query("UPDATE task_submissions SET submission_text='$textEsc', github_link='$ghEsc', live_link='$liveEsc', status='submitted', submitted_at=NOW(), updated_at=NOW() WHERE id={$existing['id']}");
            } else {
                $db->query("INSERT INTO task_submissions (task_id, student_id, submission_text, github_link, live_link, status) VALUES ($tid, $sid, '$textEsc', '$ghEsc', '$liveEsc', 'submitted')");
            }
            // Notify
            $taskName = $db->real_escape_string($_POST['task_name'] ?? 'Task');
            $db->query("INSERT INTO student_notifications (student_id, title, message, type) VALUES ($sid, 'Task Submitted', 'Your submission for \"$taskName\" has been received.', 'task')");
            $success = 'Task submitted successfully!';
            // Reload selected task
            if ($taskId) {
                $tr2 = $db->query("SELECT ct.*, ts.id as sub_id, ts.submission_text, ts.github_link, ts.live_link, ts.status as sub_status, ts.marks_obtained, ts.coordinator_feedback, ts.submitted_at FROM coordinator_tasks ct LEFT JOIN task_submissions ts ON ct.id=ts.task_id AND ts.student_id=$sid WHERE ct.id=$taskId LIMIT 1");
                $selectedTask = $tr2 ? $tr2->fetch_assoc() : null;
            }
        }
    }
}

// All tasks list
$allTasks = [];
if ($batchId) {
    $ar = $db->query("SELECT ct.*, ts.id as sub_id, ts.status as sub_status, ts.marks_obtained, ts.submitted_at
        FROM coordinator_tasks ct
        LEFT JOIN task_submissions ts ON ct.id=ts.task_id AND ts.student_id=$sid
        WHERE ct.batch_id=$batchId AND ct.status='active'
        ORDER BY ct.due_date ASC");
    while ($ar && $row = $ar->fetch_assoc()) $allTasks[] = $row;
}

$initials = strtoupper(substr($student['full_name'],0,1).(isset(explode(' ',$student['full_name'])[1])?substr(explode(' ',$student['full_name'])[1],0,1):''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Task Submissions</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--or5:#f97316;--or4:#fb923c;--or6:#ea580c;--or1:#fff7ed;--or2:#ffedd5;--sb-width:260px;--sb-collapsed:70px;--transition:0.28s cubic-bezier(0.4,0,0.2,1);}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#fff7ed 0%,#fff 60%,#ffedd5 100%);min-height:100vh;color:#111827;}
.student-layout{display:flex;min-height:100vh;}
.main-content{flex:1;margin-left:var(--sb-width);transition:margin-left var(--transition);min-height:100vh;display:flex;flex-direction:column;}
body.sidebar-collapsed .main-content{margin-left:var(--sb-collapsed);}
.topbar{background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(249,115,22,0.1);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 16px rgba(0,0,0,0.06);}
.topbar-left{display:flex;align-items:center;gap:14px;}
.mobile-menu-btn{display:none;width:38px;height:38px;border-radius:8px;border:none;background:var(--or2);color:var(--or6);cursor:pointer;align-items:center;justify-content:center;font-size:1rem;}
.topbar-breadcrumb{font-size:0.8125rem;color:#6b7280;}.topbar-breadcrumb span{color:#111827;font-weight:600;}
.topbar-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--or5),var(--or4));display:flex;align-items:center;justify-content:center;font-size:0.8125rem;font-weight:700;color:#fff;text-decoration:none;border:2px solid rgba(249,115,22,0.3);}
.page-content{padding:28px;flex:1;}
.page-header{margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;}
.page-header h1{font-size:1.5rem;font-weight:800;color:#111827;}

.sub-layout{display:grid;grid-template-columns:320px 1fr;gap:20px;}
@media(max-width:900px){.sub-layout{grid-template-columns:1fr;}}

/* Task list panel */
.task-list-panel{background:#fff;border-radius:14px;border:1.5px solid #f3f4f6;box-shadow:0 2px 12px rgba(0,0,0,0.05);overflow:hidden;}
.panel-header{padding:16px 18px;border-bottom:1px solid #f3f4f6;font-size:0.9375rem;font-weight:700;color:#111827;background:#fafafa;}
.task-list-item{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid #f9fafb;cursor:pointer;text-decoration:none;transition:background 0.15s;}
.task-list-item:hover{background:var(--or1);}
.task-list-item.active{background:var(--or1);border-left:3px solid var(--or5);}
.task-list-item:last-child{border-bottom:none;}
.tli-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.tli-info{flex:1;min-width:0;}
.tli-name{font-size:0.8125rem;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tli-due{font-size:0.7rem;color:#9ca3af;margin-top:2px;}
.tli-pill{font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:10px;white-space:nowrap;}

/* Submission form */
.sub-form-panel{background:#fff;border-radius:14px;border:1.5px solid #f3f4f6;box-shadow:0 2px 12px rgba(0,0,0,0.05);overflow:hidden;}
.sub-task-header{padding:20px 24px;border-bottom:1px solid #f3f4f6;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;}
.sub-task-title{font-size:1.125rem;font-weight:800;margin-bottom:8px;}
.sub-task-meta{display:flex;gap:16px;flex-wrap:wrap;}
.sub-task-meta-item{font-size:0.75rem;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:5px;}
.sub-task-meta-item i{color:var(--or4);}
.sub-task-desc{font-size:0.8125rem;color:rgba(255,255,255,0.7);margin-top:10px;line-height:1.5;}
.sub-form-body{padding:24px;}
.alert{padding:12px 16px;border-radius:10px;font-size:0.875rem;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:10px;}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
.form-label{display:block;font-size:0.875rem;font-weight:600;color:#374151;margin-bottom:8px;}
.form-label .optional{font-size:0.75rem;font-weight:400;color:#9ca3af;}
.form-input,.form-textarea{width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:0.9375rem;font-family:inherit;color:#111827;transition:border-color 0.2s,box-shadow 0.2s;outline:none;background:#fff;}
.form-input:focus,.form-textarea:focus{border-color:var(--or5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.form-textarea{resize:vertical;min-height:120px;}
.form-group{margin-bottom:18px;}
.btn-primary{padding:13px 28px;border:none;border-radius:8px;background:linear-gradient(135deg,var(--or5),var(--or4));color:#fff;font-size:0.9375rem;font-weight:600;font-family:inherit;cursor:pointer;box-shadow:0 4px 16px rgba(249,115,22,0.3);transition:all 0.2s;}
.btn-primary:hover:not(:disabled){transform:scale(1.02);background:linear-gradient(135deg,var(--or6),var(--or5));}
.btn-primary:disabled{opacity:0.6;cursor:not-allowed;}

/* Status display */
.submission-status{padding:20px 24px;}
.sub-status-card{background:#f9fafb;border-radius:12px;padding:20px;border:1.5px solid #f3f4f6;}
.sub-status-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.sub-status-title{font-size:1rem;font-weight:700;color:#111827;}
.status-big{font-size:0.875rem;font-weight:700;padding:6px 14px;border-radius:20px;}
.marks-big{font-size:2rem;font-weight:800;color:var(--or6);text-align:center;padding:16px 0;}
.marks-big span{font-size:1rem;color:#9ca3af;font-weight:400;}
.feedback-card{background:#fff;border-radius:10px;padding:14px;border-left:4px solid var(--or5);margin-top:14px;}
.feedback-card label{font-size:0.75rem;font-weight:700;color:var(--or6);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;display:block;}
.feedback-card p{font-size:0.875rem;color:#374151;}
.link-display{display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;margin-top:8px;}
.link-display i{color:var(--or5);}
.link-display a{font-size:0.8125rem;color:#2563eb;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.link-display a:hover{text-decoration:underline;}

.no-task-selected{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;color:#9ca3af;text-align:center;}
.no-task-selected i{font-size:3rem;margin-bottom:16px;opacity:0.3;}
.no-task-selected h3{font-size:1.125rem;font-weight:700;color:#6b7280;margin-bottom:6px;}

@media(max-width:768px){.page-content{padding:16px;}.mobile-menu-btn{display:flex!important;}}
</style>
</head>
<body>
<div class="student-layout">
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" onclick="openMobileSidebar()"><i class="fas fa-bars"></i></button>
            <div class="topbar-breadcrumb">Padak &rsaquo; <span>Submissions</span></div>
        </div>
        <div class="topbar-right">
            <a href="profile.php" class="topbar-avatar"><?php echo $initials; ?></a>
        </div>
    </header>
    <div class="page-content">
        <div class="page-header">
            <h1><i class="fas fa-cloud-arrow-up" style="color:var(--or5);margin-right:8px;"></i>Task Submissions</h1>
        </div>

        <div class="sub-layout">
            <!-- Left: task list -->
            <div class="task-list-panel">
                <div class="panel-header">All Tasks (<?php echo count($allTasks); ?>)</div>
                <?php if (empty($allTasks)): ?>
                <div style="padding:30px;text-align:center;color:#9ca3af;font-size:0.875rem;">No tasks yet</div>
                <?php endif; ?>
                <?php foreach ($allTasks as $t):
                    $isOD = $t['due_date'] && strtotime($t['due_date']) < time() && empty($t['sub_id']);
                    $pillText = 'Pending'; $pillBg = '#fff7ed'; $pillColor = '#c2410c'; $dotColor = '#f97316';
                    if ($isOD) { $pillText='Overdue'; $pillBg='#fee2e2'; $pillColor='#dc2626'; $dotColor='#ef4444'; }
                    if ($t['sub_status']==='submitted') { $pillText='Submitted'; $pillBg='#dcfce7'; $pillColor='#15803d'; $dotColor='#22c55e'; }
                    if ($t['sub_status']==='accepted') { $pillText='Accepted'; $pillBg='#dcfce7'; $pillColor='#15803d'; $dotColor='#22c55e'; }
                    if ($t['sub_status']==='reviewed') { $pillText='Reviewed'; $pillBg='#dbeafe'; $pillColor='#1d4ed8'; $dotColor='#3b82f6'; }
                    if ($t['sub_status']==='resubmit') { $pillText='Resubmit'; $pillBg='#fef3c7'; $pillColor='#92400e'; $dotColor='#eab308'; }
                ?>
                <a href="?task_id=<?php echo $t['id']; ?>" class="task-list-item <?php echo $taskId===$t['id']?'active':''; ?>">
                    <div class="tli-dot" style="background:<?php echo $dotColor; ?>;"></div>
                    <div class="tli-info">
                        <div class="tli-name"><?php echo htmlspecialchars($t['title']); ?></div>
                        <div class="tli-due"><?php echo $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : 'No deadline'; ?></div>
                    </div>
                    <span class="tli-pill" style="background:<?php echo $pillBg; ?>;color:<?php echo $pillColor; ?>;"><?php echo $pillText; ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Right: submission form -->
            <div class="sub-form-panel">
                <?php if (!$selectedTask): ?>
                <div class="no-task-selected">
                    <i class="fas fa-hand-point-left"></i>
                    <h3>Select a task to submit</h3>
                    <p>Choose a task from the list to view details or submit your work.</p>
                </div>
                <?php else: ?>
                <div class="sub-task-header">
                    <div class="sub-task-title"><?php echo htmlspecialchars($selectedTask['title']); ?></div>
                    <div class="sub-task-meta">
                        <div class="sub-task-meta-item"><i class="fas fa-clock"></i><?php echo $selectedTask['due_date'] ? date('M d, Y', strtotime($selectedTask['due_date'])) : 'No deadline'; ?></div>
                        <div class="sub-task-meta-item"><i class="fas fa-star"></i><?php echo $selectedTask['max_marks']; ?> points</div>
                        <div class="sub-task-meta-item"><i class="fas fa-tag"></i><?php echo ucfirst($selectedTask['priority']); ?></div>
                        <div class="sub-task-meta-item"><i class="fas fa-users"></i><?php echo ucfirst($selectedTask['task_type']); ?></div>
                    </div>
                    <?php if ($selectedTask['description']): ?>
                    <div class="sub-task-desc"><?php echo htmlspecialchars($selectedTask['description']); ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($success)): ?>
                <div class="sub-form-body"><div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $success; ?></div></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                <div class="sub-form-body"><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div></div>
                <?php endif; ?>

                <?php if ($selectedTask['sub_id'] && $selectedTask['sub_status'] !== 'resubmit'): ?>
                <!-- Show submitted work -->
                <div class="submission-status">
                    <div class="sub-status-card">
                        <div class="sub-status-header">
                            <div class="sub-status-title">Your Submission</div>
                            <?php
                            $sp = 'status-submitted'; $st = 'Submitted';
                            if ($selectedTask['sub_status']==='reviewed') { $sp='status-reviewed'; $st='Reviewed'; }
                            if ($selectedTask['sub_status']==='accepted') { $sp=''; $st='Accepted'; }
                            ?>
                            <span class="status-big" style="background:<?php echo $selectedTask['sub_status']==='accepted'?'#dcfce7':'#dbeafe'; ?>;color:<?php echo $selectedTask['sub_status']==='accepted'?'#15803d':'#1d4ed8'; ?>;"><?php echo $st; ?></span>
                        </div>
                        <?php if ($selectedTask['marks_obtained'] !== null): ?>
                        <div class="marks-big"><?php echo $selectedTask['marks_obtained']; ?> <span>/ <?php echo $selectedTask['max_marks']; ?> pts</span></div>
                        <?php endif; ?>
                        <div style="font-size:0.8125rem;color:#6b7280;margin-bottom:10px;"><i class="fas fa-calendar-check" style="color:var(--or4);"></i> Submitted <?php echo date('M d, Y \a\t g:i A', strtotime($selectedTask['submitted_at'])); ?></div>
                        <?php if ($selectedTask['submission_text']): ?>
                        <div style="background:#fff;border-radius:8px;padding:12px;border:1px solid #f3f4f6;font-size:0.875rem;color:#374151;margin-bottom:10px;"><?php echo nl2br(htmlspecialchars($selectedTask['submission_text'])); ?></div>
                        <?php endif; ?>
                        <?php if ($selectedTask['github_link']): ?>
                        <div class="link-display"><i class="fab fa-github"></i><a href="<?php echo htmlspecialchars($selectedTask['github_link']); ?>" target="_blank"><?php echo htmlspecialchars($selectedTask['github_link']); ?></a></div>
                        <?php endif; ?>
                        <?php if ($selectedTask['live_link']): ?>
                        <div class="link-display"><i class="fas fa-globe"></i><a href="<?php echo htmlspecialchars($selectedTask['live_link']); ?>" target="_blank"><?php echo htmlspecialchars($selectedTask['live_link']); ?></a></div>
                        <?php endif; ?>
                        <?php if ($selectedTask['coordinator_feedback']): ?>
                        <div class="feedback-card">
                            <label><i class="fas fa-comment"></i> Coordinator Feedback</label>
                            <p><?php echo htmlspecialchars($selectedTask['coordinator_feedback']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- Submission form -->
                <div class="sub-form-body">
                    <?php if ($selectedTask['sub_status'] === 'resubmit'): ?>
                    <div class="alert alert-error"><i class="fas fa-rotate"></i>Resubmission required. Please review the feedback and resubmit.</div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="submit_task" value="1">
                        <input type="hidden" name="task_id" value="<?php echo $selectedTask['id']; ?>">
                        <input type="hidden" name="task_name" value="<?php echo htmlspecialchars($selectedTask['title']); ?>">
                        <div class="form-group">
                            <label class="form-label">Submission Description <span style="color:#ef4444;">*</span></label>
                            <textarea name="submission_text" class="form-textarea" placeholder="Describe your work, approach, challenges faced, and what you learned..."><?php echo htmlspecialchars($selectedTask['submission_text'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">GitHub Repository Link <span class="optional">(recommended)</span></label>
                            <input type="url" name="github_link" class="form-input" placeholder="https://github.com/username/repo" value="<?php echo htmlspecialchars($selectedTask['github_link'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Live Demo / Project Link <span class="optional">(optional)</span></label>
                            <input type="url" name="live_link" class="form-input" placeholder="https://your-project.netlify.app" value="<?php echo htmlspecialchars($selectedTask['live_link'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-cloud-arrow-up"></i> Submit Task</button>
                    </form>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<script>
const sb=document.getElementById('mainSidebar');
function syncBodyClass(){document.body.classList.toggle('sidebar-collapsed',sb.classList.contains('collapsed'));}
window.toggleSidebar=function(){sb.classList.toggle('collapsed');localStorage.setItem('sidebarCollapsed',sb.classList.contains('collapsed')?'1':'0');syncBodyClass();};
syncBodyClass();
</script>
</body>
</html>