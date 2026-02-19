<?php
// attendance.php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();

$sid = (int)$student['id'];

// Get attendance logs directly from student_attendance table where date is not null
$attLogs = [];
$ar = $db->query("SELECT * FROM student_attendance WHERE student_id=$sid AND date IS NOT NULL AND status IN ('present','absent','late') ORDER BY date DESC LIMIT 60");
while ($ar && $row = $ar->fetch_assoc()) {
    $attLogs[] = $row;
}

$total = count($attLogs);
$present = count(array_filter($attLogs, fn($a) => $a['status']==='present'));
$absent = count(array_filter($attLogs, fn($a) => $a['status']==='absent'));
$late = count(array_filter($attLogs, fn($a) => $a['status']==='late'));
$pct = $total > 0 ? round(($present/$total)*100) : 0;
$initials = strtoupper(substr($student['full_name'],0,1).(str_contains($student['full_name'],' ')?substr(explode(' ',$student['full_name'])[1],0,1):''));

// Get student's domain for display
$domain = $student['domain_interest'] ?? 'Your domain';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Attendance - Padak</title>
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
.topbar-left{display:flex;align-items:center;gap:14px;}.mobile-menu-btn{display:none;width:38px;height:38px;border-radius:8px;border:none;background:var(--or2);color:var(--or6);cursor:pointer;align-items:center;justify-content:center;font-size:1rem;}
.topbar-breadcrumb{font-size:0.8125rem;color:#6b7280;}.topbar-breadcrumb span{color:#111827;font-weight:600;}
.topbar-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--or5),var(--or4));display:flex;align-items:center;justify-content:center;font-size:0.8125rem;font-weight:700;color:#fff;text-decoration:none;border:2px solid rgba(249,115,22,0.3);}
.page-content{padding:28px;flex:1;}
.page-header{margin-bottom:24px;}
.page-header h1{font-size:1.5rem;font-weight:800;color:#111827;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px;}
.stat-box{background:#fff;border-radius:12px;padding:18px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
.stat-box .val{font-size:2rem;font-weight:800;}.stat-box .lbl{font-size:0.8125rem;color:#6b7280;margin-top:2px;}
.att-table-wrap{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.05);overflow:hidden;}
.att-table{width:100%;border-collapse:collapse;}
.att-table thead tr{background:#fafafa;border-bottom:2px solid #f3f4f6;}
.att-table th{padding:12px 18px;font-size:0.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;text-align:left;}
.att-table tbody tr{border-bottom:1px solid #f9fafb;}
.att-table td{padding:12px 18px;font-size:0.875rem;color:#374151;}
.status-pill{padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;}
.sp-present{background:#dcfce7;color:#15803d;}
.sp-absent{background:#fee2e2;color:#dc2626;}
.sp-late{background:#fef3c7;color:#92400e;}
.pct-alert{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:0.875rem;font-weight:600;}
.alert-warn{background:#fef3c7;border:1px solid #fde68a;color:#92400e;}
.alert-good{background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;}
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
            <div class="topbar-breadcrumb">Padak &rsaquo; <span>Attendance</span></div>
        </div>
        <div class="topbar-right"><a href="profile.php" class="topbar-avatar"><?php echo $initials; ?></a></div>
    </header>
    <div class="page-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check" style="color:var(--or5);margin-right:8px;"></i>Attendance</h1>
            <p style="color:#6b7280;font-size:0.875rem;"><?php echo htmlspecialchars($domain); ?> - Your attendance record</p>
        </div>
        <div class="stats-row">
            <div class="stat-box"><div class="val" style="color:#111827;"><?php echo $total; ?></div><div class="lbl">Total Sessions</div></div>
            <div class="stat-box"><div class="val" style="color:#22c55e;"><?php echo $present; ?></div><div class="lbl">Present</div></div>
            <div class="stat-box"><div class="val" style="color:#ef4444;"><?php echo $absent; ?></div><div class="lbl">Absent</div></div>
            <div class="stat-box"><div class="val" style="color:#eab308;"><?php echo $late; ?></div><div class="lbl">Late</div></div>
            <div class="stat-box"><div class="val" style="color:<?php echo $pct>=75?'#22c55e':'#ef4444'; ?>;"><?php echo $pct; ?>%</div><div class="lbl">Attendance %</div></div>
        </div>
        <?php if ($total > 0): ?>
        <div class="pct-alert <?php echo $pct>=75?'alert-good':'alert-warn'; ?>">
            <i class="fas fa-<?php echo $pct>=75?'circle-check':'triangle-exclamation'; ?>"></i>
            <?php echo $pct>=75 ? "Great! Your attendance is $pct%. You meet the 75% requirement for certificate." : "Warning: Your attendance is $pct%. Minimum 75% required for certificate eligibility."; ?>
        </div>
        <?php endif; ?>
        <div class="att-table-wrap">
            <?php if (empty($attLogs)): ?>
            <div class="empty-state"><i class="fas fa-calendar-xmark"></i><p>No attendance records yet. Records will appear once your coordinator marks attendance.</p></div>
            <?php else: ?>
            <table class="att-table">
                <thead><tr><th>#</th><th>Date</th><th>Day</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($attLogs as $i => $att): ?>
                    <tr>
                        <td style="font-weight:600;color:#9ca3af;"><?php echo $i+1; ?></td>
                        <td><?php echo date('M d, Y', strtotime($att['date'])); ?></td>
                        <td style="color:#6b7280;"><?php echo date('l', strtotime($att['date'])); ?></td>
                        <td><span class="status-pill sp-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<script>
const sb=document.getElementById('mainSidebar');function syncBodyClass(){document.body.classList.toggle('sidebar-collapsed',sb.classList.contains('collapsed'));}
window.toggleSidebar=function(){sb.classList.toggle('collapsed');localStorage.setItem('sidebarCollapsed',sb.classList.contains('collapsed')?'1':'0');syncBodyClass();};syncBodyClass();
</script></body></html>