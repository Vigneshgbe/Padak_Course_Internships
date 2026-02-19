<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'tasks';

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = "t.status='active' AND (t.assigned_to_student IS NULL OR t.assigned_to_student=$sid)";
if ($search) $where .= " AND t.title LIKE '%" . $db->real_escape_string($search) . "%'";

if ($filter === 'pending')   $where .= " AND ts.id IS NULL";
if ($filter === 'submitted') $where .= " AND ts.status IN('submitted','under_review')";
if ($filter === 'approved')  $where .= " AND ts.status='approved'";
if ($filter === 'overdue')   $where .= " AND t.due_date < NOW() AND (ts.status IS NULL OR ts.status NOT IN('approved'))";

$tasks = [];
$res = $db->query("SELECT t.*, ts.status as sub_status, ts.points_earned, ts.submitted_at, ts.feedback, ts.id as sub_id
    FROM internship_tasks t
    LEFT JOIN task_submissions ts ON ts.task_id=t.id AND ts.student_id=$sid
    WHERE $where ORDER BY
        CASE WHEN t.priority='urgent' THEN 1 WHEN t.priority='high' THEN 2 WHEN t.priority='medium' THEN 3 ELSE 4 END,
        t.due_date ASC");
if ($res) while ($r = $res->fetch_assoc()) $tasks[] = $r;

// Counts for filter tabs
$counts = ['all'=>0,'pending'=>0,'submitted'=>0,'approved'=>0,'overdue'=>0];
$cr = $db->query("SELECT ts.status as sub_status, t.due_date
    FROM internship_tasks t LEFT JOIN task_submissions ts ON ts.task_id=t.id AND ts.student_id=$sid
    WHERE t.status='active' AND (t.assigned_to_student IS NULL OR t.assigned_to_student=$sid)");
if ($cr) while ($row = $cr->fetch_assoc()) {
    $counts['all']++;
    $due = $row['due_date'] ? strtotime($row['due_date']) : null;
    $isOverdue = $due && $due < time() && !in_array($row['sub_status'],['approved']);
    if (!$row['sub_status']) $counts['pending']++;
    elseif (in_array($row['sub_status'],['submitted','under_review'])) $counts['submitted']++;
    elseif ($row['sub_status'] === 'approved') $counts['approved']++;
    if ($isOverdue) $counts['overdue']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Tasks - Padak</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--sbw:258px;--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);}
.page-wrap{margin-left:var(--sbw);min-height:100vh;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(248,250,252,0.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;gap:12px;}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.main-content{padding:24px 28px;}
/* Filter bar */
.filter-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap;}
.filter-tab{padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.8rem;font-weight:500;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.filter-tab:hover{border-color:var(--o5);color:var(--o5);text-decoration:none;}
.filter-tab.active{background:var(--o5);border-color:var(--o5);color:#fff;}
.tab-cnt{font-size:.7rem;background:rgba(255,255,255,0.2);padding:1px 5px;border-radius:5px;margin-left:4px;}
.filter-tab:not(.active) .tab-cnt{background:var(--border);color:var(--text3);}
.search-form{margin-left:auto;display:flex;gap:8px;}
.search-input{padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;outline:none;transition:all .2s;width:200px;}
.search-input:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
/* Task grid */
.tasks-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;}
.task-card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);overflow:hidden;transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column;}
.task-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,0.1);}
.task-card-head{padding:16px 18px 12px;position:relative;}
.task-card-head::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.task-card-head.urgent::before{background:var(--red);}
.task-card-head.high::before{background:var(--o5);}
.task-card-head.medium::before{background:var(--yellow);}
.task-card-head.low::before{background:var(--green);}
.tc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px;}
.tc-title{font-size:.9rem;font-weight:700;color:var(--text);line-height:1.3;flex:1;}
.tc-badge{font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:6px;white-space:nowrap;flex-shrink:0;}
.badge-approved{background:rgba(34,197,94,0.12);color:#16a34a;}
.badge-submitted{background:rgba(59,130,246,0.12);color:#1d4ed8;}
.badge-review{background:rgba(139,92,246,0.12);color:#6d28d9;}
.badge-overdue{background:rgba(239,68,68,0.12);color:#dc2626;}
.badge-pending{background:rgba(234,179,8,0.12);color:#854d0e;}
.badge-revise{background:rgba(239,68,68,0.12);color:#dc2626;}
.tc-desc{font-size:.8rem;color:var(--text2);line-height:1.5;margin-bottom:10px;}
.tc-meta{display:flex;gap:14px;flex-wrap:wrap;}
.tc-meta-item{display:flex;align-items:center;gap:4px;font-size:.73rem;color:var(--text3);}
.tc-meta-item i{font-size:.7rem;}
.tc-meta-item.overdue-item{color:var(--red);}
.task-card-foot{padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:8px;margin-top:auto;}
.tc-btn{flex:1;padding:8px;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;text-align:center;text-decoration:none;transition:all .2s;display:block;}
.tc-btn.primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 3px 10px rgba(249,115,22,0.3);}
.tc-btn.primary:hover{opacity:.9;text-decoration:none;color:#fff;}
.tc-btn.secondary{background:var(--bg);border:1.5px solid var(--border);color:var(--text2);}
.tc-btn.secondary:hover{border-color:var(--o5);color:var(--o5);text-decoration:none;}
.tc-btn.success{background:rgba(34,197,94,0.1);color:var(--green);border:1.5px solid rgba(34,197,94,0.25);}
.pts-chip{font-size:.72rem;font-weight:700;padding:4px 10px;background:rgba(249,115,22,0.1);color:var(--o5);border-radius:6px;align-self:center;}
/* Feedback */
.tc-feedback{margin:0 18px 12px;padding:10px 12px;background:#f0fdf4;border-left:3px solid var(--green);border-radius:0 8px 8px 0;font-size:.78rem;color:#166534;}
.tc-feedback.negative{background:#fef2f2;border-left-color:var(--red);color:#991b1b;}
/* Empty */
.empty-page{text-align:center;padding:60px 20px;color:var(--text3);}
.empty-page i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
.empty-page h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
.empty-page p{font-size:.85rem;}
@media(max-width:768px){.page-wrap{margin-left:0;}.topbar-hamburger{display:flex;}.main-content{padding:16px;}.tasks-grid{grid-template-columns:1fr;}.search-form{margin-left:0;width:100%;}.filter-row{flex-direction:column;align-items:flex-start;}.search-input{width:100%;}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">My Tasks</div>
        <a href="submit.php" style="padding:7px 14px;border-radius:8px;background:var(--o5);color:#fff;font-size:.82rem;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-paper-plane fa-xs"></i> Submit Task
        </a>
    </div>
    <div class="main-content">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-tabs">
                    <?php $tabs = ['all'=>'All','pending'=>'Pending','submitted'=>'Submitted','approved'=>'Approved','overdue'=>'Overdue']; ?>
                    <?php foreach ($tabs as $k=>$v): ?>
                    <a href="?filter=<?php echo $k; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
                       class="filter-tab <?php echo $filter===$k?'active':''; ?>">
                        <?php echo $v; ?><span class="tab-cnt"><?php echo $counts[$k]; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="search-form">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="search-input" placeholder="Search tasks…">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                </div>
            </div>
        </form>

        <?php if (empty($tasks)): ?>
        <div class="empty-page">
            <i class="fas fa-clipboard-list"></i>
            <h3>No tasks found</h3>
            <p>
                <?php if ($filter !== 'all'): ?>
                No <?php echo $tabs[$filter]; ?> tasks. <a href="?filter=all" style="color:var(--o5)">View all tasks</a>
                <?php else: ?>
                Your coordinator hasn't assigned any tasks yet. Check back soon!
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="tasks-grid">
            <?php foreach ($tasks as $t):
                $due = $t['due_date'] ? strtotime($t['due_date']) : null;
                $isOverdue = $due && $due < time() && !in_array($t['sub_status'],['approved']);
                $daysLeft = $due ? ceil(($due - time()) / 86400) : null;

                $badge = 'badge-pending'; $badgeText = 'Pending';
                if ($t['sub_status']==='approved') { $badge='badge-approved'; $badgeText='✓ Approved'; }
                elseif ($t['sub_status']==='under_review') { $badge='badge-review'; $badgeText='In Review'; }
                elseif ($t['sub_status']==='submitted') { $badge='badge-submitted'; $badgeText='Submitted'; }
                elseif ($t['sub_status']==='revision_requested') { $badge='badge-revise'; $badgeText='Revision Needed'; }
                elseif ($isOverdue) { $badge='badge-overdue'; $badgeText='Overdue'; }
            ?>
            <div class="task-card">
                <div class="task-card-head <?php echo $t['priority']; ?>">
                    <div class="tc-top">
                        <div class="tc-title"><?php echo htmlspecialchars($t['title']); ?></div>
                        <span class="tc-badge <?php echo $badge; ?>"><?php echo $badgeText; ?></span>
                    </div>
                    <div class="tc-desc"><?php echo htmlspecialchars(substr($t['description']??'No description.',0,100)).(strlen($t['description']??'')>100?'…':''); ?></div>
                    <div class="tc-meta">
                        <span class="tc-meta-item <?php echo $isOverdue?'overdue-item':''; ?>">
                            <i class="fas fa-calendar"></i>
                            <?php if ($due): ?>
                                <?php if ($isOverdue): ?>Overdue <?php echo abs($daysLeft); ?>d
                                <?php elseif ($daysLeft === 0): ?>Due today!
                                <?php elseif ($daysLeft == 1): ?>Due tomorrow
                                <?php else: ?><?php echo $daysLeft; ?>d left
                                <?php endif; ?>
                            <?php else: ?>No deadline<?php endif; ?>
                        </span>
                        <span class="tc-meta-item"><i class="fas fa-star"></i><?php echo $t['max_points']; ?> pts</span>
                        <span class="tc-meta-item"><i class="fas fa-users"></i><?php echo ucfirst($t['task_type']); ?></span>
                        <span class="tc-meta-item">
                            <i class="fas fa-flag" style="color:<?php
                                echo $t['priority']==='urgent'?'var(--red)':
                                    ($t['priority']==='high'?'var(--o5)':
                                    ($t['priority']==='medium'?'var(--yellow)':'var(--green)'));?>"></i>
                            <?php echo ucfirst($t['priority']); ?>
                        </span>
                        <?php if ($t['sub_status']==='approved' && $t['points_earned'] !== null): ?>
                        <span class="tc-meta-item" style="color:var(--green);"><i class="fas fa-trophy"></i><?php echo $t['points_earned']; ?> earned</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($t['feedback'] && in_array($t['sub_status'],['approved','revision_requested','rejected'])): ?>
                <div class="tc-feedback <?php echo in_array($t['sub_status'],['revision_requested','rejected'])?'negative':''; ?>">
                    <i class="fas fa-comment-alt fa-xs"></i> <strong>Feedback:</strong> <?php echo htmlspecialchars(substr($t['feedback'],0,120)); ?>
                </div>
                <?php endif; ?>
                <div class="task-card-foot">
                    <?php if (in_array($t['sub_status'],['approved'])): ?>
                        <span class="tc-btn success"><i class="fas fa-check"></i> Completed</span>
                        <?php if ($t['points_earned']): ?><span class="pts-chip">+<?php echo $t['points_earned']; ?> pts</span><?php endif; ?>
                    <?php elseif (in_array($t['sub_status'],['submitted','under_review'])): ?>
                        <span class="tc-btn secondary"><i class="fas fa-hourglass-half"></i> Awaiting Review</span>
                    <?php elseif ($t['sub_status']==='revision_requested'): ?>
                        <a href="submit.php?task_id=<?php echo $t['id']; ?>" class="tc-btn primary"><i class="fas fa-edit"></i> Resubmit</a>
                    <?php else: ?>
                        <a href="submit.php?task_id=<?php echo $t['id']; ?>" class="tc-btn primary"><i class="fas fa-paper-plane"></i> Submit Now</a>
                    <?php endif; ?>
                    <?php if ($t['resources_url']): ?>
                    <a href="<?php echo htmlspecialchars($t['resources_url']); ?>" target="_blank" class="tc-btn secondary"><i class="fas fa-link"></i> Resources</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>