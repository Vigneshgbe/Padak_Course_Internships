<?php
// announcements.php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }

$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];

$er = $db->query("SELECT se.batch_id FROM student_enrollments se WHERE se.student_id=$sid AND se.status='active' LIMIT 1");
$enrollment = $er ? $er->fetch_assoc() : null;
$batchId = $enrollment ? (int)$enrollment['batch_id'] : 0;

$announcements = [];
$ar = $db->query("SELECT a.*, c.full_name as coordinator_name, ib.batch_name FROM announcements a
    LEFT JOIN coordinators c ON a.coordinator_id=c.id
    LEFT JOIN internship_batches ib ON a.batch_id=ib.id
    WHERE a.is_active=1 AND (a.batch_id IS NULL OR a.batch_id=$batchId)
    ORDER BY a.priority='urgent' DESC, a.priority='important' DESC, a.created_at DESC");
while ($ar && $row = $ar->fetch_assoc()) $announcements[] = $row;
$initials = strtoupper(substr($student['full_name'],0,1).(str_contains($student['full_name'],' ')?substr(explode(' ',$student['full_name'])[1],0,1):''));
?>

<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Announcements - Padak</title>
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
.page-header{margin-bottom:24px;}
.page-header h1{font-size:1.5rem;font-weight:800;color:#111827;margin-bottom:4px;}
.ann-card{background:#fff;border-radius:14px;border-left:4px solid #e5e7eb;box-shadow:0 2px 12px rgba(0,0,0,0.05);padding:20px 24px;margin-bottom:14px;transition:transform 0.2s,box-shadow 0.2s;position:relative;}
.ann-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,0.1);}
.ann-urgent{border-left-color:#ef4444;}
.ann-important{border-left-color:var(--or5);}
.ann-normal{border-left-color:#e5e7eb;}
.ann-priority-badge{position:absolute;top:16px;right:18px;padding:3px 10px;border-radius:20px;font-size:0.7rem;font-weight:700;}
.pb-urgent{background:#fee2e2;color:#dc2626;}
.pb-important{background:#fff7ed;color:#c2410c;}
.pb-normal{background:#f3f4f6;color:#6b7280;}
.ann-title{font-size:1rem;font-weight:700;color:#111827;margin-bottom:8px;padding-right:80px;}
.ann-content{font-size:0.875rem;color:#374151;line-height:1.6;margin-bottom:12px;}
.ann-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:0.75rem;color:#9ca3af;}
.ann-meta i{color:var(--or4);}
.empty-state{text-align:center;padding:60px;color:#9ca3af;}
.empty-state i{font-size:3rem;margin-bottom:14px;display:block;opacity:0.3;}
@media(max-width:768px){.page-content{padding:16px;}.mobile-menu-btn{display:flex!important;}}

</style></head><body>
<div class="student-layout">
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" onclick="openMobileSidebar()"><i class="fas fa-bars"></i></button>
            <div class="topbar-breadcrumb">Padak &rsaquo; <span>Announcements</span></div>
        </div>
        <div class="topbar-right"><a href="profile.php" class="topbar-avatar"><?php echo $initials; ?></a></div>
    </header>
    <div class="page-content">
        <div class="page-header">
            <h1><i class="fas fa-bullhorn" style="color:var(--or5);margin-right:8px;"></i>Announcements</h1>
            <p style="color:#6b7280;font-size:0.875rem;"><?php echo count($announcements); ?> announcements</p>
        </div>
        <?php if (empty($announcements)): ?>
        <div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements yet.</p></div>
        <?php else: ?>
        <?php foreach ($announcements as $a): ?>
        <div class="ann-card ann-<?php echo $a['priority']; ?>">
            <span class="ann-priority-badge pb-<?php echo $a['priority']; ?>"><?php echo ucfirst($a['priority']); ?></span>
            <div class="ann-title"><?php echo htmlspecialchars($a['title']); ?></div>
            <div class="ann-content"><?php echo nl2br(htmlspecialchars($a['content'])); ?></div>
            <div class="ann-meta">
                <span><i class="fas fa-user"></i><?php echo htmlspecialchars($a['coordinator_name'] ?? 'Padak Team'); ?></span>
                <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($a['created_at'])); ?></span>
                <?php if ($a['batch_name']): ?><span><i class="fas fa-layer-group"></i><?php echo htmlspecialchars($a['batch_name']); ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>
<script>
const sb=document.getElementById('mainSidebar');function syncBodyClass(){document.body.classList.toggle('sidebar-collapsed',sb.classList.contains('collapsed'));}
window.toggleSidebar=function(){sb.classList.toggle('collapsed');localStorage.setItem('sidebarCollapsed',sb.classList.contains('collapsed')?'1':'0');syncBodyClass();};syncBodyClass();
</script></body></html>