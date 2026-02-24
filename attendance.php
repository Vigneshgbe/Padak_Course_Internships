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
$activePage = 'attendance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students Attendance - Padak</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
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
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;
    --shadow:0 1px 3px rgba(0,0,0,0.08),0 4px 16px rgba(0,0,0,0.05);
}
body{
    font-family:'Inter',sans-serif;
    background:linear-gradient(135deg,#fff7ed 0%,#fff 60%,#ffedd5 100%);
    color:var(--text);
    min-height:100vh;
}

/* Layout */
.page-wrap{
    margin-left:var(--sbw);
    min-height:100vh;
    display:flex;
    flex-direction:column;
    transition:margin-left 0.3s ease;
}

/* Topbar */
.topbar{
    position:sticky;
    top:0;
    z-index:100;
    background:rgba(248,250,252,0.92);
    backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:12px 28px;
    display:flex;
    align-items:center;
    gap:12px;
    box-shadow:0 2px 8px rgba(0,0,0,0.04);
}
.topbar-hamburger{
    display:none;
    background:none;
    border:none;
    cursor:pointer;
    color:var(--text2);
    padding:6px;
    border-radius:7px;
    font-size:1.1rem;
}
.topbar-hamburger:hover{
    background:var(--border);
}
.topbar-title{
    font-size:1rem;
    font-weight:600;
    color:var(--text);
    flex:1;
}
.topbar-breadcrumb{
    font-size:0.8125rem;
    color:var(--text3);
    flex:1;
}
.topbar-breadcrumb span{
    color:var(--text);
    font-weight:600;
}
.topbar-avatar{
    width:38px;
    height:38px;
    border-radius:50%;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:0.8125rem;
    font-weight:700;
    color:#fff;
    text-decoration:none;
    border:2px solid rgba(249,115,22,0.3);
    transition:transform 0.2s;
}
.topbar-avatar:hover{
    transform:scale(1.05);
}

/* Main Content */
.main-content{
    padding:24px 28px;
    flex:1;
    max-width:1400px;
    width:100%;
    margin:0 auto;
}

/* Page Header */
.page-header{
    margin-bottom:28px;
}
.page-header h1{
    font-size:1.75rem;
    font-weight:800;
    color:var(--text);
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:6px;
}
.page-header h1 i{
    color:var(--o5);
}
.page-subtitle{
    color:var(--text2);
    font-size:0.9rem;
}

/* Stats Grid */
.stats-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:16px;
    margin-bottom:28px;
}
.stat-card{
    background:var(--card);
    border-radius:12px;
    padding:20px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    text-align:center;
    position:relative;
    overflow:hidden;
    transition:transform 0.2s,box-shadow 0.2s;
}
.stat-card:hover{
    transform:translateY(-2px);
    box-shadow:0 4px 20px rgba(0,0,0,0.1);
}
.stat-card::before{
    content:'';
    position:absolute;
    top:-10px;
    right:-10px;
    width:60px;
    height:60px;
    border-radius:50%;
    opacity:0.08;
}
.stat-card.total::before{background:var(--text);}
.stat-card.present::before{background:var(--green);}
.stat-card.absent::before{background:var(--red);}
.stat-card.late::before{background:var(--yellow);}
.stat-card.percentage::before{background:var(--o5);}

.stat-value{
    font-size:2rem;
    font-weight:800;
    line-height:1;
    margin-bottom:8px;
}
.stat-card.total .stat-value{color:var(--text);}
.stat-card.present .stat-value{color:var(--green);}
.stat-card.absent .stat-value{color:var(--red);}
.stat-card.late .stat-value{color:var(--yellow);}
.stat-card.percentage .stat-value{color:var(--o5);}

.stat-label{
    font-size:0.8125rem;
    color:var(--text2);
    font-weight:500;
}

/* Alert Box */
.pct-alert{
    padding:16px 20px;
    border-radius:12px;
    margin-bottom:24px;
    font-size:0.9rem;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:12px;
    box-shadow:var(--shadow);
}
.pct-alert i{
    font-size:1.2rem;
    flex-shrink:0;
}
.alert-warn{
    background:#fef3c7;
    border:1px solid #fde68a;
    color:#92400e;
}
.alert-good{
    background:#dcfce7;
    border:1px solid #bbf7d0;
    color:#15803d;
}

/* Table Section */
.table-section{
    background:var(--card);
    border-radius:14px;
    box-shadow:var(--shadow);
    border:1px solid var(--border);
    overflow:hidden;
}
.table-header{
    padding:18px 24px;
    border-bottom:1px solid var(--border);
    background:#fafafa;
}
.table-header h2{
    font-size:1rem;
    font-weight:700;
    color:var(--text);
    display:flex;
    align-items:center;
    gap:8px;
}
.table-header h2 i{
    color:var(--o5);
}

/* Table Wrapper for Horizontal Scroll */
.table-wrapper{
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
}

/* Attendance Table */
.att-table{
    width:100%;
    border-collapse:collapse;
    min-width:600px; /* Ensures table doesn't collapse too much */
}
.att-table thead tr{
    background:#fafafa;
    border-bottom:2px solid var(--border);
}
.att-table th{
    padding:14px 18px;
    font-size:0.75rem;
    font-weight:700;
    color:var(--text2);
    text-transform:uppercase;
    letter-spacing:0.05em;
    text-align:left;
    white-space:nowrap;
}
.att-table tbody tr{
    border-bottom:1px solid #f9fafb;
    transition:background 0.15s;
}
.att-table tbody tr:hover{
    background:#fafafa;
}
.att-table tbody tr:last-child{
    border-bottom:none;
}
.att-table td{
    padding:14px 18px;
    font-size:0.875rem;
    color:var(--text);
    white-space:nowrap;
}
.att-table td:first-child{
    font-weight:600;
    color:var(--text3);
}

/* Status Pills */
.status-pill{
    display:inline-flex;
    align-items:center;
    padding:4px 12px;
    border-radius:20px;
    font-size:0.75rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.02em;
}
.sp-present{
    background:#dcfce7;
    color:#15803d;
}
.sp-absent{
    background:#fee2e2;
    color:#dc2626;
}
.sp-late{
    background:#fef3c7;
    color:#92400e;
}

/* Empty State */
.empty-state{
    text-align:center;
    padding:60px 20px;
    color:var(--text3);
}
.empty-state i{
    font-size:3rem;
    margin-bottom:16px;
    display:block;
    opacity:0.3;
}
.empty-state p{
    font-size:0.9rem;
    max-width:400px;
    margin:0 auto;
    line-height:1.6;
}

/* Responsive Design */
@media(max-width:1200px){
    .stats-grid{
        grid-template-columns:repeat(3,1fr);
    }
    .stat-card.percentage{
        grid-column:span 3;
    }
}

@media(max-width:768px){
    /* Remove sidebar margin */
    .page-wrap{
        margin-left:0;
    }
    
    /* Show hamburger menu */
    .topbar-hamburger{
        display:flex;
    }
    
    /* Adjust topbar */
    .topbar{
        padding:12px 16px;
    }
    .topbar-breadcrumb{
        display:none; /* Hide breadcrumb on mobile, show title instead */
    }
    .topbar-title{
        display:block;
    }
    
    /* Main content padding */
    .main-content{
        padding:16px;
    }
    
    /* Page header */
    .page-header{
        margin-bottom:20px;
    }
    .page-header h1{
        font-size:1.4rem;
    }
    
    /* Stats grid - 2 columns */
    .stats-grid{
        grid-template-columns:repeat(2,1fr);
        gap:12px;
        margin-bottom:20px;
    }
    .stat-card{
        padding:16px;
    }
    .stat-value{
        font-size:1.6rem;
    }
    .stat-label{
        font-size:0.75rem;
    }
    .stat-card.percentage{
        grid-column:span 2;
    }
    
    /* Alert */
    .pct-alert{
        padding:12px 16px;
        font-size:0.8125rem;
        margin-bottom:16px;
    }
    .pct-alert i{
        font-size:1rem;
    }
    
    /* Table section */
    .table-header{
        padding:14px 16px;
    }
    .table-header h2{
        font-size:0.9rem;
    }
    
    /* Table adjustments */
    .att-table{
        min-width:550px; /* Slightly smaller min-width for mobile */
    }
    .att-table th,
    .att-table td{
        padding:12px 14px;
        font-size:0.8125rem;
    }
    
    /* Status pills smaller */
    .status-pill{
        padding:3px 10px;
        font-size:0.7rem;
    }
    
    /* Empty state */
    .empty-state{
        padding:40px 20px;
    }
    .empty-state i{
        font-size:2.5rem;
    }
    .empty-state p{
        font-size:0.85rem;
    }
}

@media(max-width:480px){
    /* Very small screens */
    .stats-grid{
        grid-template-columns:1fr;
    }
    .stat-card.percentage{
        grid-column:span 1;
    }
    
    .page-header h1{
        font-size:1.25rem;
        flex-wrap:wrap;
    }
    
    /* Table even more compact */
    .att-table{
        min-width:100%;
        font-size:0.75rem;
    }
    .att-table th,
    .att-table td{
        padding:10px 12px;
    }
    
    /* Hide day column on very small screens */
    .att-table th:nth-child(3),
    .att-table td:nth-child(3){
        display:none;
    }
}

/* Smooth scroll */
html{
    scroll-behavior:smooth;
}

/* Selection color */
::selection{
    background:rgba(249,115,22,0.2);
    color:var(--text);
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars fa-sm"></i>
        </button>
        <div class="topbar-breadcrumb">
            Padak › <span>Attendance</span>
        </div>
        <div class="topbar-title">Attendance</div>
        <a href="profile.php" class="topbar-avatar" title="<?php echo htmlspecialchars($student['full_name']); ?>">
            <?php echo $initials; ?>
        </a>
    </div>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-calendar-check"></i>
                Attendance Record
            </h1>
            <p class="page-subtitle">
                <?php echo htmlspecialchars($domain); ?> - Track your attendance history
            </p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-value"><?php echo $total; ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card present">
                <div class="stat-value"><?php echo $present; ?></div>
                <div class="stat-label">Present</div>
            </div>
            <div class="stat-card absent">
                <div class="stat-value"><?php echo $absent; ?></div>
                <div class="stat-label">Absent</div>
            </div>
            <div class="stat-card late">
                <div class="stat-value"><?php echo $late; ?></div>
                <div class="stat-label">Late</div>
            </div>
            <div class="stat-card percentage">
                <div class="stat-value" style="color:<?php echo $pct>=75?'var(--green)':'var(--red)'; ?>;">
                    <?php echo $pct; ?>%
                </div>
                <div class="stat-label">Attendance Percentage</div>
            </div>
        </div>

        <!-- Alert Message -->
        <?php if ($total > 0): ?>
        <div class="pct-alert <?php echo $pct>=75?'alert-good':'alert-warn'; ?>">
            <i class="fas fa-<?php echo $pct>=75?'circle-check':'triangle-exclamation'; ?>"></i>
            <span>
                <?php if ($pct >= 75): ?>
                    Excellent! Your attendance is <?php echo $pct; ?>%. You meet the 75% requirement for certificate eligibility.
                <?php else: ?>
                    Warning: Your attendance is <?php echo $pct; ?>%. Minimum 75% required for certificate eligibility.
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Attendance Table -->
        <div class="table-section">
            <div class="table-header">
                <h2>
                    <i class="fas fa-list"></i>
                    Attendance History
                </h2>
            </div>
            
            <?php if (empty($attLogs)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-xmark"></i>
                <p>No attendance records yet. Records will appear once your coordinator marks attendance.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attLogs as $i => $att): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo date('M d, Y', strtotime($att['date'])); ?></td>
                            <td style="color:var(--text2);">
                                <?php echo date('l', strtotime($att['date'])); ?>
                            </td>
                            <td>
                                <span class="status-pill sp-<?php echo $att['status']; ?>">
                                    <?php echo ucfirst($att['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
    }
}

// Initialize sidebar state from localStorage
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    if (sidebar && localStorage.getItem('sidebarCollapsed') === '1') {
        sidebar.classList.add('collapsed');
    }
});

// Auto-hide alert after 8 seconds
setTimeout(() => {
    const alert = document.querySelector('.pct-alert');
    if (alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        alert.style.transition = 'all 0.3s ease';
        setTimeout(() => alert.style.display = 'none', 300);
    }
}, 8000);
</script>

</body>
</html>