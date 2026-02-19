<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }

$student = $auth->getCurrentStudent();
if (!$student) { header('Location: logout.php'); exit; }

$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'dashboard';

// --- Stats ---
// $totalPoints = (int)$student['total_points'];

// Calculate total points from points log
$pointsResult = $db->query("SELECT COALESCE(SUM(points), 0) as total FROM student_points_log WHERE student_id=$sid");
$totalPoints = (int)$pointsResult->fetch_assoc()['total'];

// Tasks
$taskStats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN ts.status='approved' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN ts.status IN('submitted','under_review') THEN 1 ELSE 0 END) as pending_review,
    SUM(CASE WHEN t.due_date < NOW() AND (ts.status IS NULL OR ts.status NOT IN('approved')) THEN 1 ELSE 0 END) as overdue
    FROM internship_tasks t
    LEFT JOIN task_submissions ts ON ts.task_id=t.id AND ts.student_id=$sid
    WHERE t.status='active' AND (t.assigned_to_student IS NULL OR t.assigned_to_student=$sid)")->fetch_assoc();

$completedCount = (int)($taskStats['completed'] ?? 0);
$totalTaskCount = (int)($taskStats['total'] ?? 0);
$overdueCount   = (int)($taskStats['overdue'] ?? 0);
$pendingReview  = (int)($taskStats['pending_review'] ?? 0);

// Rank
$rankRow = $db->query("SELECT COUNT(*)+1 as rnk FROM internship_students WHERE total_points > $totalPoints AND is_active=1")->fetch_assoc();
$rank = (int)($rankRow['rnk'] ?? 1);

// Total students
$totalStudents = (int)$db->query("SELECT COUNT(*) as c FROM internship_students WHERE is_active=1")->fetch_assoc()['c'];

// Certificate eligibility
$certEligible = ($totalPoints >= 2000 && $completedCount >= 10);
$certRow = $db->query("SELECT * FROM internship_certificates WHERE student_id=$sid")->fetch_assoc();

// Recent tasks (5)
$recentTasks = [];
$rt = $db->query("SELECT t.*, ts.status as sub_status, ts.points_earned, ts.submitted_at
    FROM internship_tasks t
    LEFT JOIN task_submissions ts ON ts.task_id=t.id AND ts.student_id=$sid
    WHERE t.status='active' AND (t.assigned_to_student IS NULL OR t.assigned_to_student=$sid)
    ORDER BY t.due_date ASC LIMIT 5");
if ($rt) while ($r = $rt->fetch_assoc()) $recentTasks[] = $r;

// Recent messages (3)
$recentMsgs = [];
$rm = $db->query("SELECT cm.*, s.full_name as sender_name, cr.room_name, cr.room_type
    FROM chat_messages cm
    JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.student_id=$sid
    JOIN internship_students s ON s.id=cm.sender_id
    JOIN chat_rooms cr ON cr.id=cm.room_id
    WHERE cm.sender_id!=$sid AND cm.is_deleted=0
    ORDER BY cm.created_at DESC LIMIT 3");
if ($rm) while ($r = $rm->fetch_assoc()) $recentMsgs[] = $r;

// Announcements (3)
$announcements = [];
$an = $db->query("SELECT a.*, (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id=a.id AND student_id=$sid) as is_read
    FROM announcements a WHERE a.target_all=1 ORDER BY a.created_at DESC LIMIT 3");
if ($an) while ($r = $an->fetch_assoc()) $announcements[] = $r;

// Top 3 leaderboard
$topStudents = [];
$ts = $db->query("SELECT id, full_name, domain_interest, total_points, profile_photo FROM internship_students WHERE is_active=1 ORDER BY total_points DESC LIMIT 3");
if ($ts) while ($r = $ts->fetch_assoc()) $topStudents[] = $r;

// Points log (recent 5)
$pointsHistory = [];
$ph = $db->query("SELECT pl.*, t.title as task_title FROM student_points_log pl LEFT JOIN internship_tasks t ON t.id=pl.task_id WHERE pl.student_id=$sid ORDER BY pl.awarded_at DESC LIMIT 5");
if ($ph) while ($r = $ph->fetch_assoc()) $pointsHistory[] = $r;

// Internship progress % (approved tasks / total tasks * 100)
$progressPct = $totalTaskCount > 0 ? min(100, round(($completedCount / $totalTaskCount) * 100)) : 0;

// Get enrolled batch
$batchRow = $db->query("SELECT b.* FROM internship_batches b JOIN student_batch_enrollments e ON e.batch_id=b.id WHERE e.student_id=$sid LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Padak Internship</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --bg:#f8fafc;--card:#ffffff;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;
    --border:#e2e8f0;
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;--purple:#8b5cf6;
    --shadow:0 1px 3px rgba(0,0,0,0.08),0 4px 16px rgba(0,0,0,0.05);
    --shadow-md:0 4px 12px rgba(0,0,0,0.1),0 8px 24px rgba(0,0,0,0.06);
}
body{font-family:'Inter','Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
.page-wrap{margin-left:var(--sbw);min-height:100vh;display:flex;flex-direction:column;}
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(248,250,252,0.92);backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:12px 28px;display:flex;align-items:center;gap:12px;
}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-hamburger:hover{background:rgba(249,115,22,0.08);}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.topbar-date{font-size:.82rem;color:var(--text3);}
.topbar-notif{
    position:relative;background:none;border:none;cursor:pointer;
    color:var(--text2);padding:7px;border-radius:8px;transition:all .2s;
    display:flex;align-items:center;
}
.topbar-notif:hover{background:rgba(249,115,22,0.08);color:var(--o5);}
.notif-dot{
    position:absolute;top:4px;right:4px;
    width:8px;height:8px;border-radius:50%;
    background:var(--red);border:2px solid var(--bg);
}

.main-content{padding:24px 28px;flex:1;}
.greeting-row{margin-bottom:24px;}
.greeting-name{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.02em;}
.greeting-name span{background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.greeting-sub{font-size:.9rem;color:var(--text2);margin-top:4px;}

/* Stat cards */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{
    background:var(--card);border-radius:14px;padding:20px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    position:relative;overflow:hidden;
    transition:transform .2s,box-shadow .2s;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
.stat-card::before{
    content:'';position:absolute;top:-20px;right:-20px;
    width:80px;height:80px;border-radius:50%;opacity:.08;
}
.stat-card.orange::before{background:var(--o5);}
.stat-card.green::before{background:var(--green);}
.stat-card.blue::before{background:var(--blue);}
.stat-card.purple::before{background:var(--purple);}
.stat-icon{
    width:42px;height:42px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;margin-bottom:12px;
}
.stat-card.orange .stat-icon{background:rgba(249,115,22,0.12);color:var(--o5);}
.stat-card.green .stat-icon{background:rgba(34,197,94,0.12);color:var(--green);}
.stat-card.blue .stat-icon{background:rgba(59,130,246,0.12);color:var(--blue);}
.stat-card.purple .stat-icon{background:rgba(139,92,246,0.12);color:var(--purple);}
.stat-value{font-size:1.75rem;font-weight:800;color:var(--text);line-height:1;margin-bottom:4px;}
.stat-label{font-size:.78rem;color:var(--text2);font-weight:500;}
.stat-change{font-size:.72rem;margin-top:6px;display:flex;align-items:center;gap:3px;}
.stat-change.up{color:var(--green);}
.stat-change.warn{color:var(--yellow);}
.stat-change.down{color:var(--red);}

/* Progress banner */
.progress-banner{
    background:linear-gradient(135deg,var(--o5) 0%,var(--o4) 100%);
    border-radius:14px;padding:20px 24px;margin-bottom:24px;
    color:#fff;display:flex;align-items:center;gap:20px;
    box-shadow:0 6px 20px rgba(249,115,22,0.3);
    position:relative;overflow:hidden;
}
.progress-banner::before{
    content:'';position:absolute;top:-30px;right:-30px;
    width:160px;height:160px;border-radius:50%;
    background:rgba(255,255,255,0.08);
}
.progress-banner::after{
    content:'';position:absolute;bottom:-40px;right:100px;
    width:100px;height:100px;border-radius:50%;
    background:rgba(255,255,255,0.05);
}
.pb-left{flex:1;position:relative;z-index:1;}
.pb-title{font-size:.75rem;font-weight:600;opacity:.85;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.pb-value{font-size:1.5rem;font-weight:800;margin-bottom:10px;}
.pb-bar-bg{height:7px;background:rgba(255,255,255,0.25);border-radius:4px;overflow:hidden;}
.pb-bar{height:100%;background:#fff;border-radius:4px;transition:width .8s ease;}
.pb-meta{font-size:.75rem;opacity:.8;margin-top:6px;}
.pb-right{position:relative;z-index:1;text-align:center;flex-shrink:0;}
.pb-icon{font-size:2.2rem;opacity:.9;}
.pb-action{
    display:inline-block;margin-top:8px;padding:6px 14px;
    background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.35);
    border-radius:20px;font-size:.75rem;font-weight:600;color:#fff;
    text-decoration:none;transition:all .2s;
}
.pb-action:hover{background:rgba(255,255,255,0.3);text-decoration:none;color:#fff;}

/* 2-col layout */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}
.three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:24px;}
@media(max-width:1100px){.three-col{grid-template-columns:1fr 1fr;}}

/* Section card */
.section-card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
.section-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
}
.section-head-left{display:flex;align-items:center;gap:9px;}
.section-head-icon{
    width:32px;height:32px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;font-size:.82rem;
}
.section-head-icon.orange{background:rgba(249,115,22,0.1);color:var(--o5);}
.section-head-icon.blue{background:rgba(59,130,246,0.1);color:var(--blue);}
.section-head-icon.gold{background:rgba(234,179,8,0.12);color:var(--yellow);}
.section-head-icon.green{background:rgba(34,197,94,0.1);color:var(--green);}
.section-head-icon.purple{background:rgba(139,92,246,0.1);color:var(--purple);}
.section-title{font-size:.9rem;font-weight:700;color:var(--text);}
.section-link{font-size:.78rem;color:var(--o5);text-decoration:none;font-weight:500;}
.section-link:hover{text-decoration:underline;}

/* Task list */
.task-list{padding:8px 0;}
.task-item{
    display:flex;align-items:flex-start;gap:12px;
    padding:12px 20px;border-bottom:1px solid var(--border);
    transition:background .15s;
}
.task-item:last-child{border-bottom:none;}
.task-item:hover{background:#fafafa;}
.task-priority{
    width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;
}
.task-priority.urgent{background:#ef4444;}
.task-priority.high{background:#f97316;}
.task-priority.medium{background:#eab308;}
.task-priority.low{background:#22c55e;}
.task-info{flex:1;min-width:0;}
.task-title{font-size:.85rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.task-meta{font-size:.72rem;color:var(--text3);margin-top:2px;}
.task-tag{
    font-size:.68rem;font-weight:600;padding:2px 8px;border-radius:6px;white-space:nowrap;flex-shrink:0;
}
.tag-approved{background:rgba(34,197,94,0.12);color:#16a34a;}
.tag-submitted{background:rgba(59,130,246,0.12);color:#1d4ed8;}
.tag-overdue{background:rgba(239,68,68,0.12);color:#dc2626;}
.tag-pending{background:rgba(234,179,8,0.12);color:#854d0e;}
.tag-draft{background:rgba(148,163,184,0.15);color:#475569;}

/* Message list */
.msg-item{display:flex;align-items:flex-start;gap:10px;padding:12px 20px;border-bottom:1px solid var(--border);}
.msg-item:last-child{border-bottom:none;}
.msg-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0;}
.msg-body{flex:1;min-width:0;}
.msg-sender{font-size:.82rem;font-weight:600;color:var(--text);}
.msg-text{font-size:.78rem;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.msg-time{font-size:.68rem;color:var(--text3);}

/* Announcement */
.ann-item{padding:14px 20px;border-bottom:1px solid var(--border);}
.ann-item:last-child{border-bottom:none;}
.ann-top{display:flex;align-items:center;gap:8px;margin-bottom:5px;}
.ann-badge{
    font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:5px;text-transform:uppercase;
}
.ann-badge.general{background:rgba(59,130,246,0.12);color:#1d4ed8;}
.ann-badge.task{background:rgba(249,115,22,0.12);color:var(--o6);}
.ann-badge.certificate{background:rgba(234,179,8,0.12);color:#854d0e;}
.ann-badge.urgent{background:rgba(239,68,68,0.12);color:#dc2626;}
.ann-badge.deadline{background:rgba(139,92,246,0.12);color:#6d28d9;}
.ann-title{font-size:.85rem;font-weight:600;color:var(--text);}
.ann-content{font-size:.78rem;color:var(--text2);line-height:1.5;}
.ann-time{font-size:.68rem;color:var(--text3);margin-top:4px;}
.ann-new-dot{width:7px;height:7px;border-radius:50%;background:var(--o5);flex-shrink:0;}

/* Leaderboard */
.lb-item{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border);}
.lb-item:last-child{border-bottom:none;}
.lb-rank{
    width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-size:.78rem;font-weight:800;flex-shrink:0;
}
.lb-rank.r1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;}
.lb-rank.r2{background:linear-gradient(135deg,#9ca3af,#6b7280);color:#fff;}
.lb-rank.r3{background:linear-gradient(135deg,#c4873d,#b87333);color:#fff;}
.lb-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0;}
.lb-name{flex:1;font-size:.84rem;font-weight:600;color:var(--text);}
.lb-domain{font-size:.7rem;color:var(--text3);}
.lb-pts{font-size:.82rem;font-weight:700;color:var(--o5);}

/* Points log */
.pts-item{display:flex;align-items:center;gap:10px;padding:10px 20px;border-bottom:1px solid var(--border);}
.pts-item:last-child{border-bottom:none;}
.pts-icon{width:30px;height:30px;border-radius:8px;background:rgba(249,115,22,0.1);color:var(--o5);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.pts-reason{flex:1;font-size:.8rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pts-time{font-size:.68rem;color:var(--text3);}
.pts-val{font-size:.85rem;font-weight:700;color:var(--green);}

/* Empty state */
.empty-state{padding:32px;text-align:center;color:var(--text3);}
.empty-state i{font-size:2rem;margin-bottom:10px;display:block;opacity:.4;}
.empty-state p{font-size:.85rem;}

/* Cert card */
.cert-eligibility-card{
    margin:0 20px 20px;padding:14px 16px;border-radius:10px;
    display:flex;align-items:center;gap:12px;
}
.cert-eligibility-card.eligible{background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);}
.cert-eligibility-card.not-eligible{background:rgba(249,115,22,0.06);border:1px solid rgba(249,115,22,0.2);}
.cert-elig-icon{font-size:1.4rem;}
.cert-elig-text{flex:1;}
.cert-elig-title{font-size:.84rem;font-weight:700;}
.cert-elig-sub{font-size:.74rem;color:var(--text2);margin-top:2px;}
.cert-elig-btn{padding:6px 14px;border-radius:8px;font-size:.76rem;font-weight:600;text-decoration:none;transition:all .2s;}
.cert-elig-btn.eligible{background:var(--green);color:#fff;}
.cert-elig-btn.not-eligible{background:var(--o5);color:#fff;}
.cert-elig-btn:hover{opacity:.88;text-decoration:none;color:#fff;}

@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:900px){.two-col{grid-template-columns:1fr;}}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .topbar-date{display:none;}
    .main-content{padding:16px;}
    .stats-grid{grid-template-columns:repeat(2,1fr);}
    .progress-banner{flex-direction:column;gap:12px;}
    .three-col{grid-template-columns:1fr;}
}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="page-wrap">
    <!-- Topbar -->
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-date"><?php echo date('D, d M Y'); ?></div>
        <button class="topbar-notif" onclick="location.href='notifications.php'" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notifCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </button>
    </div>

    <div class="main-content">
        <!-- Greeting -->
        <div class="greeting-row">
            <div class="greeting-name">Good <?php echo (date('H')<12)?'Morning':((date('H')<17)?'Afternoon':'Evening'); ?>, <span><?php echo htmlspecialchars(explode(' ',$student['full_name'])[0]); ?>!</span> 👋</div>
            <div class="greeting-sub">
                <?php if ($batchRow): ?>
                    Enrolled in <strong><?php echo htmlspecialchars($batchRow['batch_name']); ?></strong> &bull;
                    <?php echo date('d M', strtotime($batchRow['start_date'])); ?> – <?php echo date('d M Y', strtotime($batchRow['end_date'])); ?>
                <?php else: ?>
                    Welcome to your Padak Internship Dashboard
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo number_format($totalPoints); ?></div>
                <div class="stat-label">Total Points</div>
                <div class="stat-change up"><i class="fas fa-arrow-up fa-xs"></i> Rank #<?php echo $rank; ?> of <?php echo $totalStudents; ?></div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $completedCount; ?></div>
                <div class="stat-label">Tasks Completed</div>
                <div class="stat-change <?php echo $overdueCount>0?'down':'up'; ?>">
                    <?php if ($overdueCount > 0): ?><i class="fas fa-exclamation fa-xs"></i> <?php echo $overdueCount; ?> overdue
                    <?php else: ?><i class="fas fa-check fa-xs"></i> All on track<?php endif; ?>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-value"><?php echo $pendingReview; ?></div>
                <div class="stat-label">Under Review</div>
                <div class="stat-change warn"><i class="fas fa-clock fa-xs"></i> Awaiting feedback</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-certificate"></i></div>
                <div class="stat-value"><?php echo $certEligible ? '✓' : $totalPoints.'/2000'; ?></div>
                <div class="stat-label">Certificate Status</div>
                <div class="stat-change <?php echo $certEligible?'up':'warn'; ?>">
                    <?php echo $certEligible ? '<i class="fas fa-check fa-xs"></i> Eligible!' : '<i class="fas fa-arrow-up fa-xs"></i> '.max(0,2000-$totalPoints).' pts to go'; ?>
                </div>
            </div>
        </div>

        <!-- Progress Banner -->
        <div class="progress-banner">
            <div class="pb-left">
                <div class="pb-title">Internship Completion Progress</div>
                <div class="pb-value"><?php echo $progressPct; ?>% Complete &mdash; <?php echo $completedCount; ?> / <?php echo $totalTaskCount; ?> Tasks</div>
                <div class="pb-bar-bg"><div class="pb-bar" style="width:<?php echo $progressPct; ?>%"></div></div>
                <div class="pb-meta">
                    <?php $certPoints = min(100, round(($totalPoints/2000)*100)); ?>
                    Certificate: <?php echo $certPoints; ?>% &bull; <?php echo max(0, 2000 - $totalPoints); ?> more points needed for free certificate
                </div>
            </div>
            <div class="pb-right">
                <div class="pb-icon"><i class="fas fa-award"></i></div>
                <a href="tasks.php" class="pb-action">View Tasks</a>
            </div>
        </div>

        <!-- Tasks + Messages -->
        <div class="two-col">
            <!-- Tasks -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-head-left">
                        <div class="section-head-icon orange"><i class="fas fa-tasks"></i></div>
                        <span class="section-title">Upcoming Tasks</span>
                    </div>
                    <a href="tasks.php" class="section-link">View all <i class="fas fa-arrow-right fa-xs"></i></a>
                </div>
                <?php if (empty($recentTasks)): ?>
                <div class="empty-state"><i class="fas fa-clipboard-list"></i><p>No active tasks yet.<br>Check back soon!</p></div>
                <?php else: ?>
                <div class="task-list">
                    <?php foreach ($recentTasks as $t):
                        $due = $t['due_date'] ? strtotime($t['due_date']) : null;
                        $isOverdue = $due && $due < time() && $t['sub_status'] !== 'approved';
                        $dueStr = $due ? date('d M', $due) : 'No deadline';
                    ?>
                    <div class="task-item">
                        <span class="task-priority <?php echo $t['priority']; ?>"></span>
                        <div class="task-info">
                            <div class="task-title"><?php echo htmlspecialchars($t['title']); ?></div>
                            <div class="task-meta">
                                <i class="fas fa-calendar fa-xs"></i> <?php echo $dueStr; ?>
                                &bull; <?php echo $t['max_points']; ?> pts
                                &bull; <?php echo ucfirst($t['task_type']); ?>
                            </div>
                        </div>
                        <?php
                        $tagClass = 'tag-pending'; $tagText = 'Pending';
                        if ($t['sub_status'] === 'approved') { $tagClass='tag-approved'; $tagText='Approved'; }
                        elseif ($t['sub_status'] === 'submitted') { $tagClass='tag-submitted'; $tagText='Submitted'; }
                        elseif ($t['sub_status'] === 'under_review') { $tagClass='tag-submitted'; $tagText='In Review'; }
                        elseif ($t['sub_status'] === 'revision_requested') { $tagClass='tag-overdue'; $tagText='Revise'; }
                        elseif ($isOverdue) { $tagClass='tag-overdue'; $tagText='Overdue'; }
                        ?>
                        <span class="task-tag <?php echo $tagClass; ?>"><?php echo $tagText; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Messages -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-head-left">
                        <div class="section-head-icon blue"><i class="fas fa-comments"></i></div>
                        <span class="section-title">Recent Messages</span>
                    </div>
                    <a href="messenger.php" class="section-link">Open chat <i class="fas fa-arrow-right fa-xs"></i></a>
                </div>
                <?php if (empty($recentMsgs)): ?>
                <div class="empty-state"><i class="fas fa-comment-slash"></i><p>No new messages.<br><a href="messenger.php" style="color:var(--o5)">Start a conversation</a></p></div>
                <?php else: ?>
                <?php foreach ($recentMsgs as $msg): ?>
                <div class="msg-item">
                    <div class="msg-avatar"><?php echo strtoupper(substr($msg['sender_name'],0,1)); ?></div>
                    <div class="msg-body">
                        <div class="msg-sender"><?php echo htmlspecialchars($msg['sender_name']); ?> <span style="font-weight:400;font-size:.72rem;color:var(--text3);">in <?php echo htmlspecialchars($msg['room_name'] ?: ucfirst($msg['room_type'])); ?></span></div>
                        <div class="msg-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                    </div>
                    <div class="msg-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements + Leaderboard + Points -->
        <div class="three-col">
            <!-- Announcements -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-head-left">
                        <div class="section-head-icon orange"><i class="fas fa-bullhorn"></i></div>
                        <span class="section-title">Announcements</span>
                    </div>
                </div>
                <?php if (empty($announcements)): ?>
                <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements yet.</p></div>
                <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="ann-item">
                    <div class="ann-top">
                        <span class="ann-badge <?php echo $ann['type']; ?>"><?php echo $ann['type']; ?></span>
                        <?php if (!$ann['is_read']): ?><span class="ann-new-dot" title="New"></span><?php endif; ?>
                    </div>
                    <div class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                    <div class="ann-content"><?php echo htmlspecialchars(substr($ann['content'],0,100)).(strlen($ann['content'])>100?'…':''); ?></div>
                    <div class="ann-time"><i class="fas fa-clock fa-xs"></i> <?php echo date('d M Y', strtotime($ann['created_at'])); ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Mini Leaderboard -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-head-left">
                        <div class="section-head-icon gold"><i class="fas fa-trophy"></i></div>
                        <span class="section-title">Top Performers</span>
                    </div>
                    <a href="leaderboard.php" class="section-link">Full board <i class="fas fa-arrow-right fa-xs"></i></a>
                </div>
                <?php if (empty($topStudents)): ?>
                <div class="empty-state"><i class="fas fa-trophy"></i><p>Leaderboard loading soon.</p></div>
                <?php else: ?>
                <?php foreach ($topStudents as $i => $s): $rn = $i+1; ?>
                <div class="lb-item <?php echo $s['id']==$sid ? 'style="background:#fff8f0;"' : ''; ?>">
                    <div class="lb-rank r<?php echo $rn; ?>"><?php echo $rn; ?></div>
                    <div class="lb-avatar"><?php echo strtoupper(substr($s['full_name'],0,1)); ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="lb-name"><?php echo htmlspecialchars(explode(' ',$s['full_name'])[0].' '.(explode(' ',$s['full_name'])[1]??'')); ?><?php echo $s['id']==$sid?' <span style="color:var(--o5);font-size:.68rem;">(you)</span>':''; ?></div>
                        <div class="lb-domain"><?php echo htmlspecialchars($s['domain_interest'] ?: 'General'); ?></div>
                    </div>
                    <div class="lb-pts"><?php echo number_format($s['total_points']); ?> pts</div>
                </div>
                <?php endforeach; ?>
                <!-- My rank if not in top 3 -->
                <?php $inTop = array_filter($topStudents, fn($s)=>$s['id']==$sid); if (empty($inTop)): ?>
                <div style="padding:10px 20px;background:#fff8f0;border-top:1px dashed var(--border);">
                    <div class="lb-item" style="padding:0;">
                        <div class="lb-rank" style="background:rgba(249,115,22,0.15);color:var(--o5);"><?php echo $rank; ?></div>
                        <div class="lb-avatar"><?php echo $initials; ?></div>
                        <div style="flex:1;min-width:0;">
                            <div class="lb-name"><?php echo htmlspecialchars(explode(' ',$student['full_name'])[0]); ?> <span style="color:var(--o5);font-size:.68rem;">(you)</span></div>
                            <div class="lb-domain"><?php echo htmlspecialchars($student['domain_interest'] ?: 'General'); ?></div>
                        </div>
                        <div class="lb-pts"><?php echo number_format($totalPoints); ?> pts</div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Points History -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-head-left">
                        <div class="section-head-icon green"><i class="fas fa-coins"></i></div>
                        <span class="section-title">Points History</span>
                    </div>
                </div>
                <?php if (empty($pointsHistory)): ?>
                <div class="empty-state"><i class="fas fa-coins"></i><p>No points earned yet.<br>Complete tasks to earn points!</p></div>
                <?php else: ?>
                <?php foreach ($pointsHistory as $pl): ?>
                <div class="pts-item">
                    <div class="pts-icon"><i class="fas fa-star"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div class="pts-reason"><?php echo htmlspecialchars($pl['reason'] ?: ($pl['task_title'] ? 'Task: '.$pl['task_title'] : 'Bonus points')); ?></div>
                        <div class="pts-time"><?php echo date('d M, h:i A', strtotime($pl['awarded_at'])); ?></div>
                    </div>
                    <div class="pts-val">+<?php echo $pl['points']; ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Certificate eligibility -->
        <div class="section-card" style="margin-bottom:24px;">
            <div class="section-head">
                <div class="section-head-left">
                    <div class="section-head-icon purple"><i class="fas fa-award"></i></div>
                    <span class="section-title">Internship Certificate</span>
                </div>
                <a href="certificate.php" class="section-link">View details <i class="fas fa-arrow-right fa-xs"></i></a>
            </div>
            <div class="cert-eligibility-card <?php echo $certEligible ? 'eligible' : 'not-eligible'; ?>">
                <div class="cert-elig-icon"><?php echo $certEligible ? '🎓' : '🎯'; ?></div>
                <div class="cert-elig-text">
                    <?php if ($certEligible): ?>
                        <div class="cert-elig-title" style="color:#16a34a;">You're eligible for a FREE certificate! 🎉</div>
                        <div class="cert-elig-sub">You've earned <?php echo $totalPoints; ?> points and completed <?php echo $completedCount; ?> tasks. Claim your internship completion certificate!</div>
                    <?php elseif ($certRow && $certRow['is_issued']): ?>
                        <div class="cert-elig-title" style="color:var(--o5);">Certificate Issued!</div>
                        <div class="cert-elig-sub">Your certificate #<?php echo $certRow['certificate_number']; ?> has been issued. Download it now.</div>
                    <?php else: ?>
                        <div class="cert-elig-title">Earn your Free Certificate</div>
                        <div class="cert-elig-sub">
                            Earn <strong>1200+ points</strong> & complete at least <strong>10 tasks</strong> to unlock a free internship completion certificate.
                            You need <strong><?php echo max(0,2000-$totalPoints); ?> more points</strong> and <strong><?php echo max(0,10-$completedCount); ?> more approved tasks</strong>.
                        </div>
                    <?php endif; ?>
                </div>
                <a href="certificate.php" class="cert-elig-btn <?php echo $certEligible ? 'eligible' : 'not-eligible'; ?>">
                    <?php echo $certEligible ? 'Claim Now' : 'Learn More'; ?>
                </a>
            </div>
        </div>

    </div><!-- /main-content -->
</div><!-- /page-wrap -->

<script>
// Auto-update greeting time
const hour = new Date().getHours();
</script>
</body>
</html>