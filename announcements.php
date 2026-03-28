<?php
// announcements.php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }

// Mark all announcements as read
$sid = (int)$_SESSION['student_id'];
$db = getPadakDB();
$studentData = $db->query("SELECT batch_id FROM internship_students WHERE id=$sid")->fetch_assoc();
$batchId = $studentData['batch_id'] ?? null;

$batchCondition = $batchId ? "OR a.batch_id=$batchId" : "";
$announcementsToMark = $db->query("SELECT id FROM announcements 
    WHERE is_active=1 
    AND (target_all=1 OR batch_id IS NULL" . ($batchId ? " OR batch_id=$batchId" : "") . ")");

if ($announcementsToMark && $announcementsToMark->num_rows > 0) {
    while ($ann = $announcementsToMark->fetch_assoc()) {
        $annId = (int)$ann['id'];
        $db->query("INSERT IGNORE INTO announcement_reads (announcement_id, student_id, read_at) 
                   VALUES ($annId, $sid, NOW())");
    }
}

$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];

$er = $db->query("SELECT se.batch_id FROM student_attendance se WHERE se.student_id=$sid AND se.status='active' LIMIT 1");
$enrollment = $er ? $er->fetch_assoc() : null;
$batchId = $enrollment ? (int)$enrollment['batch_id'] : 0;

$announcements = [];
$ar = $db->query("SELECT a.*, c.full_name as coordinator_name, ib.batch_name FROM announcements a
    LEFT JOIN coordinators c ON a.coordinator_id=c.id
    LEFT JOIN internship_batches ib ON a.batch_id=ib.id
    WHERE a.is_active=1 
    AND (
        a.target_all=1 
        OR a.batch_id IS NULL 
        OR a.batch_id=$batchId
    )
    ORDER BY a.priority='urgent' DESC, a.priority='important' DESC, a.created_at DESC");

while ($ar && $row = $ar->fetch_assoc()) $announcements[] = $row;
$initials = strtoupper(substr($student['full_name'],0,1).(str_contains($student['full_name'],' ')?substr(explode(' ',$student['full_name'])[1],0,1):''));
$activePage = 'announcements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - Padak</title>
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
    max-width:1200px;
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
    display:flex;
    align-items:center;
    gap:6px;
}
.page-subtitle .count-badge{
    background:rgba(249,115,22,0.1);
    color:var(--o5);
    padding:2px 10px;
    border-radius:12px;
    font-weight:600;
    font-size:0.8125rem;
}

/* Announcements Container */
.announcements-container{
    display:flex;
    flex-direction:column;
    gap:16px;
}

/* Announcement Card */
.ann-card{
    background:var(--card);
    border-radius:14px;
    border-left:4px solid var(--border);
    box-shadow:var(--shadow);
    padding:20px 24px;
    transition:transform 0.2s,box-shadow 0.2s;
    position:relative;
    overflow:hidden;
}
.ann-card:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 24px rgba(0,0,0,0.12);
}
.ann-card::before{
    content:'';
    position:absolute;
    top:0;
    right:0;
    width:120px;
    height:120px;
    border-radius:50%;
    opacity:0.04;
    pointer-events:none;
}

/* Priority Styling */
.ann-urgent{
    border-left-color:var(--red);
}
.ann-urgent::before{
    background:var(--red);
}
.ann-important{
    border-left-color:var(--o5);
}
.ann-important::before{
    background:var(--o5);
}
.ann-normal{
    border-left-color:var(--border);
}
.ann-normal::before{
    background:var(--text3);
}

/* Priority Badge */
.ann-priority-badge{
    position:absolute;
    top:18px;
    right:20px;
    padding:4px 12px;
    border-radius:20px;
    font-size:0.7rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.03em;
    z-index:1;
}
.pb-urgent{
    background:#fee2e2;
    color:#dc2626;
}
.pb-important{
    background:#fff7ed;
    color:#c2410c;
}
.pb-normal{
    background:#f3f4f6;
    color:#6b7280;
}

/* Card Content */
.ann-header{
    margin-bottom:12px;
}
.ann-title{
    font-size:1.1rem;
    font-weight:700;
    color:var(--text);
    line-height:1.4;
    padding-right:100px;
    margin-bottom:4px;
}
.ann-content{
    font-size:0.9rem;
    color:var(--text2);
    line-height:1.7;
    margin-bottom:16px;
    word-wrap:break-word;
    overflow-wrap:break-word;
}

/* Meta Information */
.ann-meta{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    font-size:0.8125rem;
    color:var(--text3);
    padding-top:12px;
    border-top:1px solid #f9fafb;
}
.ann-meta-item{
    display:flex;
    align-items:center;
    gap:6px;
}
.ann-meta-item i{
    color:var(--o4);
    font-size:0.75rem;
}

/* Empty State */
.empty-state{
    text-align:center;
    padding:80px 20px;
    color:var(--text3);
}
.empty-state i{
    font-size:3.5rem;
    margin-bottom:20px;
    display:block;
    opacity:0.3;
    color:var(--o4);
}
.empty-state p{
    font-size:1rem;
    font-weight:500;
}

/* Filter/Sort Bar (Optional Enhancement) */
.filter-bar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:20px;
    padding:12px 16px;
    background:var(--card);
    border-radius:12px;
    box-shadow:var(--shadow);
}
.filter-bar .filter-label{
    font-size:0.875rem;
    color:var(--text2);
    font-weight:600;
}

/* Responsive Design */
@media(max-width:1024px){
    .main-content{
        max-width:100%;
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
        display:none;
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
        flex-wrap:wrap;
    }
    .page-subtitle{
        font-size:0.8125rem;
    }
    
    /* Announcement cards */
    .announcements-container{
        gap:12px;
    }
    .ann-card{
        padding:16px 18px;
    }
    
    /* Priority badge positioning */
    .ann-priority-badge{
        position:static;
        display:inline-block;
        margin-bottom:8px;
    }
    
    /* Card title */
    .ann-title{
        font-size:1rem;
        padding-right:0;
        margin-bottom:8px;
    }
    
    /* Card content */
    .ann-content{
        font-size:0.875rem;
        line-height:1.6;
        margin-bottom:12px;
    }
    
    /* Meta information */
    .ann-meta{
        font-size:0.75rem;
        gap:12px;
        padding-top:10px;
    }
    .ann-meta-item i{
        font-size:0.7rem;
    }
    
    /* Empty state */
    .empty-state{
        padding:60px 20px;
    }
    .empty-state i{
        font-size:3rem;
        margin-bottom:16px;
    }
    .empty-state p{
        font-size:0.9rem;
    }
}

@media(max-width:480px){
    /* Very small screens */
    .page-header h1{
        font-size:1.25rem;
    }
    
    .ann-card{
        padding:14px 16px;
        border-left-width:3px;
    }
    
    .ann-title{
        font-size:0.95rem;
    }
    
    .ann-content{
        font-size:0.8125rem;
    }
    
    .ann-meta{
        flex-direction:column;
        gap:8px;
        align-items:flex-start;
    }
    
    .ann-priority-badge{
        font-size:0.65rem;
        padding:3px 10px;
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

/* Animation for cards */
@keyframes slideUp{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

.ann-card{
    animation:slideUp 0.4s ease-out backwards;
}

.ann-card:nth-child(1){animation-delay:0.05s;}
.ann-card:nth-child(2){animation-delay:0.1s;}
.ann-card:nth-child(3){animation-delay:0.15s;}
.ann-card:nth-child(4){animation-delay:0.2s;}
.ann-card:nth-child(5){animation-delay:0.25s;}

/* Loading state (if needed) */
.loading-skeleton{
    background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);
    background-size:200% 100%;
    animation:loading 1.5s ease-in-out infinite;
}

@keyframes loading{
    0%{background-position:200% 0;}
    100%{background-position:-200% 0;}
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
            Padak › <span>Announcements</span>
        </div>
        <div class="topbar-title">Announcements</div>
        <a href="profile.php" class="topbar-avatar" title="<?php echo htmlspecialchars($student['full_name']); ?>">
            <?php echo $initials; ?>
        </a>
    </div>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-bullhorn"></i>
                Announcements
            </h1>
            <p class="page-subtitle">
                <span class="count-badge"><?php echo count($announcements); ?></span>
                <?php echo count($announcements) === 1 ? 'announcement' : 'announcements'; ?>
            </p>
        </div>

        <!-- Announcements List -->
        <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <i class="fas fa-bullhorn"></i>
            <p>No announcements yet. Check back later for updates from your coordinators.</p>
        </div>
        <?php else: ?>
        <div class="announcements-container">
            <?php foreach ($announcements as $a): ?>
            <div class="ann-card ann-<?php echo $a['priority']; ?>">
                <span class="ann-priority-badge pb-<?php echo $a['priority']; ?>">
                    <?php echo ucfirst($a['priority']); ?>
                </span>
                <div class="ann-header">
                    <div class="ann-title">
                        <?php echo htmlspecialchars($a['title']); ?>
                    </div>
                </div>
                <div class="ann-content">
                    <?php echo nl2br(htmlspecialchars($a['content'])); ?>
                </div>
                <div class="ann-meta">
                    <div class="ann-meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($a['coordinator_name'] ?? 'Padak Team'); ?></span>
                    </div>
                    <div class="ann-meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('M d, Y', strtotime($a['created_at'])); ?></span>
                    </div>
                    <div class="ann-meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('g:i A', strtotime($a['created_at'])); ?></span>
                    </div>
                    <?php if ($a['batch_name']): ?>
                    <div class="ann-meta-item">
                        <i class="fas fa-layer-group"></i>
                        <span><?php echo htmlspecialchars($a['batch_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Sidebar toggle functionality
function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}


// Add "New" indicator for announcements posted in last 24 hours
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.ann-card');
    const now = new Date();
    
    cards.forEach(card => {
        const dateText = card.querySelector('.ann-meta-item .fa-calendar').parentElement.textContent.trim();
        // You can add logic here to highlight new announcements
    });
});

// Smooth scroll to top function
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Add scroll-to-top button if many announcements
if (document.querySelectorAll('.ann-card').length > 5) {
    const scrollBtn = document.createElement('button');
    scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollBtn.style.cssText = 'position:fixed;bottom:30px;right:30px;width:50px;height:50px;border-radius:50%;background:var(--o5);color:#fff;border:none;box-shadow:0 4px 12px rgba(249,115,22,0.4);cursor:pointer;display:none;z-index:999;transition:all 0.3s;';
    scrollBtn.onclick = scrollToTop;
    document.body.appendChild(scrollBtn);
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollBtn.style.display = 'flex';
            scrollBtn.style.alignItems = 'center';
            scrollBtn.style.justifyContent = 'center';
        } else {
            scrollBtn.style.display = 'none';
        }
    });
}
</script>

</body>
</html>