<?php
// sidebar.php
if (!isset($activePage)) $activePage = '';
$db = getPadakDB();
$sid = (int)$_SESSION['student_id'];

$notifCount = 0;
$r = $db->query("SELECT COUNT(*) as c FROM student_notifications WHERE student_id=$sid AND is_read=0");
if ($r) $notifCount = (int)$r->fetch_assoc()['c'];

$msgCount = 0;
$r2 = $db->query("SELECT COUNT(*) as c FROM chat_messages cm JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.student_id=$sid WHERE cm.sender_id!=$sid AND cm.is_deleted=0 AND (crm.last_read_at IS NULL OR cm.created_at > crm.last_read_at)");
if ($r2) $msgCount = (int)$r2->fetch_assoc()['c'];

$pendingTasks = 0;
$r3 = $db->query("SELECT COUNT(*) as c FROM internship_tasks t LEFT JOIN task_submissions ts ON ts.task_id=t.id AND ts.student_id=$sid WHERE t.status='active' AND ts.id IS NULL AND (t.assigned_to_student IS NULL OR t.assigned_to_student=$sid)");
if ($r3) $pendingTasks = (int)$r3->fetch_assoc()['c'];

// Calculate total points from student_points_log
$pointsResult = $db->query("SELECT COALESCE(SUM(points), 0) as total FROM student_points_log WHERE student_id=$sid");
$points = $pointsResult ? (int)$pointsResult->fetch_assoc()['total'] : 0;

// Update student's total_points if different (sync)
$db->query("UPDATE internship_students SET total_points=$points WHERE id=$sid AND total_points!=$points");

// Calculate rank based on total_points
$rankResult = $db->query("SELECT COUNT(*)+1 as rnk FROM internship_students WHERE total_points > $points AND is_active=1");
$rank = $rankResult ? (int)$rankResult->fetch_assoc()['rnk'] : '-';

$certThreshold = 2000;
$progress = min(100, round(($points / $certThreshold) * 100));

// Check if user is admin (adjust this condition based on your database structure)
$isAdmin = isset($student['is_admin']) && $student['is_admin'] == 1;

$navMain = [
    ['key'=>'dashboard',    'label'=>'Dashboard',     'icon'=>'fas fa-home',         'href'=>'dashboard.php'],
    ['key'=>'messenger',    'label'=>'Messenger',      'icon'=>'fas fa-comments',     'href'=>'messenger.php', 'badge'=>$msgCount],
];
$navInternship = [
    ['key'=>'tasks',        'label'=>'My Tasks',       'icon'=>'fas fa-tasks',        'href'=>'tasks.php',     'badge'=>$pendingTasks],
    // ['key'=>'submit',       'label'=>'Submit Task',    'icon'=>'fas fa-paper-plane',  'href'=>'submit.php'],
    ['key'=>'leaderboard',  'label'=>'Leaderboard',    'icon'=>'fas fa-trophy',       'href'=>'leaderboard.php'],
    ['key'=>'certificate',  'label'=>'Certificate', 'icon'=>'fas fa-certificate',  'href'=>'certificate.php'],
    ['key'=>'attendance',    'label'=>'Attendance',      'icon'=>'fas fa-book',         'href'=>'attendance.php'],
    ['key'=>'verify certificate',    'label'=>'Verify Certificate',      'icon'=>'fas fa-id-card',         'href'=>'verify_certificate.php'],
    ['key'=>'announcements',    'label'=>'Announcements',      'icon'=>'fas fa-bullhorn',         'href'=>'announcements.php'],
    ['key'=>'game',  'label'=>'Game Hub', 'icon'=>'fas fa-gamepad',  'href'=>'game.php'],
];
$navAdmin = [
        ['key'=>'admin',  'label'=>'Admin Panel', 'icon'=>'fas fa-user-shield',  'href'=>'admin.php'],
    ];

$navAccount = [
    ['key'=>'profile',         'label'=>'My Profile',     'icon'=>'fas fa-user-circle',  'href'=>'profile.php'],
    ['key'=>'notifications',   'label'=>'Notifications',  'icon'=>'fas fa-bell',         'href'=>'notifications.php', 'badge'=>$notifCount],
];

// Admin navigation (only shown to admins)
// $navAdmin = [];
// if ($isAdmin) {
//     $navAdmin = [
//         ['key'=>'admin',  'label'=>'Admin Panel', 'icon'=>'fas fa-user-shield',  'href'=>'admin.php'],
//     ];
// }

$firstName = explode(' ', trim($student['full_name']))[0] ?? $student['full_name'];
$initials = strtoupper(substr($student['full_name'], 0, 1));
?>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="student-sidebar" id="studentSidebar">
    <div class="sb-logo">
        <!-- <div class="sb-logo-mark">
            <img src="https://github.com/Sweety-Vigneshg/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true"
                 alt="P" onerror="this.style.display='none';document.getElementById('sbLogoFallback').style.display='flex';">
            <div id="sbLogoFallback" style="display:none;align-items:center;justify-content:center;width:100%;height:100%;font-weight:800;font-size:17px;color:#fff;">P</div>
        </div> -->
        <div class="sb-logo-text">
            <!-- <span class="sb-brand">Padak</span> -->
            <span class="sb-tagline">Padak Internship Portal</span>
        </div>
        <button class="sb-close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
    </div>

    <div class="sb-profile">
        <div class="sb-avatar-wrap">
            <?php if (!empty($student['profile_photo'])): ?>
                <img src="<?php echo htmlspecialchars($student['profile_photo']); ?>" 
                     alt="" 
                     class="sb-avatar-img"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="sb-avatar-letter" style="display:none;"><?php echo $initials; ?></div>
            <?php else: ?>
                <div class="sb-avatar-letter"><?php echo $initials; ?></div>
            <?php endif; ?>
            <span class="sb-online-dot"></span>
        </div>
        <div class="sb-user-info">
            <div class="sb-user-name"><?php echo htmlspecialchars(substr($student['full_name'], 0, 20)); ?></div>
            <div class="sb-user-role"><?php echo htmlspecialchars($student['domain_interest'] ?: 'Intern'); ?></div>
            <div class="sb-user-pts"><i class="fas fa-star fa-xs"></i> <?php echo number_format($points); ?> pts &bull; Rank #<?php echo $rank; ?></div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="sb-nav-group">
            <span class="sb-group-label">MAIN</span>
            <?php foreach ($navMain as $item): $active = $activePage === $item['key']; ?>
            <a href="<?php echo $item['href']; ?>" class="sb-nav-item <?php echo $active ? 'active' : ''; ?>">
                <i class="<?php echo $item['icon']; ?> sb-nav-icon"></i>
                <span><?php echo $item['label']; ?></span>
                <?php if (!empty($item['badge']) && $item['badge'] > 0): ?><span class="sb-badge"><?php echo min($item['badge'],99); ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="sb-nav-group">
            <span class="sb-group-label">INTERNSHIP</span>
            <?php foreach ($navInternship as $item): $active = $activePage === $item['key']; ?>
            <a href="<?php echo $item['href']; ?>" class="sb-nav-item <?php echo $active ? 'active' : ''; ?>">
                <i class="<?php echo $item['icon']; ?> sb-nav-icon"></i>
                <span><?php echo $item['label']; ?></span>
                <?php if (!empty($item['badge']) && $item['badge'] > 0): ?><span class="sb-badge"><?php echo min($item['badge'],99); ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($navAdmin)): ?>
        <div class="sb-nav-group">
            <span class="sb-group-label">ADMIN</span>
            <?php foreach ($navAdmin as $item): $active = $activePage === $item['key']; ?>
            <a href="<?php echo $item['href']; ?>" class="sb-nav-item <?php echo $active ? 'active' : ''; ?>">
                <i class="<?php echo $item['icon']; ?> sb-nav-icon"></i>
                <span><?php echo $item['label']; ?></span>
                <?php if (!empty($item['badge']) && $item['badge'] > 0): ?><span class="sb-badge"><?php echo min($item['badge'],99); ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="sb-nav-group">
            <span class="sb-group-label">ACCOUNT</span>
            <?php foreach ($navAccount as $item): $active = $activePage === $item['key']; ?>
            <a href="<?php echo $item['href']; ?>" class="sb-nav-item <?php echo $active ? 'active' : ''; ?>">
                <i class="<?php echo $item['icon']; ?> sb-nav-icon"></i>
                <span><?php echo $item['label']; ?></span>
                <?php if (!empty($item['badge']) && $item['badge'] > 0): ?><span class="sb-badge"><?php echo min($item['badge'],99); ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="sb-cert-card">
        <div class="sb-cert-head">
            <i class="fas fa-award"></i>
            <span>Certificate Progress</span>
            <?php if ($progress >= 100): ?><span class="sb-cert-ready">Ready!</span><?php endif; ?>
        </div>
        <div class="sb-cert-bar-bg"><div class="sb-cert-bar" style="width:<?php echo $progress; ?>%"></div></div>
        <div class="sb-cert-foot"><?php echo $points; ?> / <?php echo $certThreshold; ?> pts &bull; <?php echo $progress; ?>% complete</div>
    </div>

    <div class="sb-footer">
        <a href="logout.php" class="sb-logout" onclick="return confirm('Sign out of Padak?')">
            <i class="fas fa-sign-out-alt"></i><span>Sign Out</span>
        </a>
    </div>
</aside>

<style>
:root {
    --sbw: 258px;
    --sb-bg: #0d1117;
    --sb-border: rgba(255,255,255,0.06);
    --sb-hover: rgba(249,115,22,0.1);
    --sb-active: rgba(249,115,22,0.18);
    --o5: #f97316; --o4: #fb923c; --o6: #ea580c;
    --tdim: rgba(255,255,255,0.42);
    --tsoft: rgba(255,255,255,0.72);
    --tbright: #ffffff;
}
.student-sidebar {
    position: fixed; left:0; top:0; bottom:0; width:var(--sbw);
    background: var(--sb-bg);
    display: flex; flex-direction: column;
    z-index:200; overflow:hidden;
    border-right: 1px solid var(--sb-border);
    box-shadow: 4px 0 32px rgba(0,0,0,0.35);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
}
.student-sidebar::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background: linear-gradient(160deg, rgba(249,115,22,0.05) 0%, transparent 45%);
}
/* Logo */
.sb-logo { display:flex; align-items:center; gap:9px; padding:18px 16px 14px; border-bottom:1px solid var(--sb-border); flex-shrink:0; }
.sb-logo-mark { width:34px; height:34px; border-radius:8px; background:linear-gradient(135deg,var(--o5),var(--o4)); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; box-shadow:0 3px 10px rgba(249,115,22,0.4); }
.sb-logo-mark img { width:20px; height:20px; object-fit:contain; }
.sb-logo-text { flex:1; }
.sb-brand { display:block; font-size:.95rem; font-weight:700; color:var(--tbright); line-height:1.1; }
.sb-tagline { display:block; font-size:.6rem; color:var(--tdim); text-transform:uppercase; letter-spacing:.1em; }
.sb-close { display:none; background:none; border:none; cursor:pointer; color:var(--tdim); padding:4px 6px; border-radius:6px; transition:all .2s; }
.sb-close:hover { color:var(--tbright); background:rgba(255,255,255,0.07); }
/* Profile */
.sb-profile { display:flex; align-items:center; gap:9px; padding:12px 16px; background:rgba(255,255,255,0.03); border-bottom:1px solid var(--sb-border); flex-shrink:0; }
.sb-avatar-wrap { position:relative; flex-shrink:0; }
.sb-avatar-img, .sb-avatar-letter { width:40px; height:40px; border-radius:50%; border:2px solid rgba(249,115,22,0.45); }
.sb-avatar-img { object-fit:cover; display:block; }
.sb-avatar-letter { background:linear-gradient(135deg,var(--o5),var(--o4)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:1rem; }
.sb-online-dot { position:absolute; bottom:1px; right:1px; width:9px; height:9px; border-radius:50%; background:#22c55e; border:2px solid var(--sb-bg); }
.sb-user-info { flex:1; min-width:0; }
.sb-user-name { font-size:.83rem; font-weight:600; color:var(--tbright); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sb-user-role { font-size:.68rem; color:var(--tdim); margin-top:1px; }
.sb-user-pts { font-size:.67rem; color:var(--o4); margin-top:2px; display:flex; align-items:center; gap:3px; }
/* Nav */
.sb-nav { flex:1; overflow-y:auto; padding:8px 8px 0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,0.08) transparent; }
.sb-nav::-webkit-scrollbar { width:3px; }
.sb-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.08); border-radius:2px; }
.sb-nav-group { margin-bottom:4px; }
.sb-group-label { display:block; font-size:.58rem; font-weight:700; color:var(--tdim); letter-spacing:.12em; text-transform:uppercase; padding:10px 8px 3px; }
.sb-nav-item { display:flex; align-items:center; gap:9px; padding:9px 10px; border-radius:8px; margin-bottom:1px; text-decoration:none; color:var(--tsoft); font-size:.84rem; font-weight:500; transition:all .18s ease; }
.sb-nav-item:hover { background:var(--sb-hover); color:var(--tbright); text-decoration:none; transform:translateX(2px); }
.sb-nav-item:hover .sb-nav-icon { color:var(--o4); }
.sb-nav-item.active { background:var(--sb-active); color:var(--o4); box-shadow:inset 3px 0 0 var(--o5); }
.sb-nav-item.active .sb-nav-icon { color:var(--o4); }
.sb-nav-icon { width:16px; text-align:center; font-size:.82rem; color:var(--tdim); flex-shrink:0; transition:color .18s; }
.sb-badge { background:var(--o5); color:#fff; font-size:.6rem; font-weight:700; padding:1px 5px; border-radius:9px; min-width:16px; text-align:center; margin-left:auto; }
/* Cert */
.sb-cert-card { margin:8px 8px; padding:11px 13px; background:rgba(249,115,22,0.07); border:1px solid rgba(249,115,22,0.18); border-radius:9px; flex-shrink:0; }
.sb-cert-head { display:flex; align-items:center; gap:6px; color:var(--o4); font-size:.73rem; font-weight:600; margin-bottom:7px; }
.sb-cert-ready { margin-left:auto; background:rgba(34,197,94,0.2); color:#4ade80; font-size:.6rem; padding:1px 6px; border-radius:6px; border:1px solid rgba(74,222,128,0.3); }
.sb-cert-bar-bg { height:4px; background:rgba(255,255,255,0.08); border-radius:2px; overflow:hidden; margin-bottom:5px; }
.sb-cert-bar { height:100%; background:linear-gradient(90deg,var(--o5),var(--o4)); border-radius:2px; transition:width .8s ease; }
.sb-cert-foot { font-size:.63rem; color:var(--tdim); }
/* Footer */
.sb-footer { padding:10px 8px; border-top:1px solid var(--sb-border); flex-shrink:0; }
.sb-logout { display:flex; align-items:center; gap:9px; padding:9px 10px; border-radius:8px; text-decoration:none; color:rgba(239,68,68,0.75); font-size:.84rem; font-weight:500; transition:all .2s; }
.sb-logout:hover { background:rgba(239,68,68,0.1); color:#ef4444; text-decoration:none; }
.sb-logout i { width:16px; text-align:center; }
/* Overlay */
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:199; backdrop-filter:blur(2px); }
@media(max-width:768px) {
    .student-sidebar { transform:translateX(-100%); }
    .student-sidebar.open { transform:translateX(0); }
    .sidebar-overlay.open { display:block; }
    .sb-close { display:flex; }
}
</style>
<script>
function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
</script>