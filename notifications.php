<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }

$db = getPadakDB();
$sid = (int)$auth->getCurrentStudent()['id'];

// Fetch complete student data
$studentQuery = $db->query("SELECT * FROM internship_students WHERE id = $sid");
$student = $studentQuery->fetch_assoc();

$activePage = 'notifications';

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notifId = (int)$_POST['notification_id'];
    $db->query("UPDATE student_notifications SET is_read = 1 WHERE id = $notifId AND student_id = $sid");
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $db->query("UPDATE student_notifications SET is_read = 1 WHERE student_id = $sid AND is_read = 0");
    header('Location: notifications.php');
    exit;
}

// Handle delete notification
if (isset($_POST['delete_notification'])) {
    $notifId = (int)$_POST['notification_id'];
    $db->query("DELETE FROM student_notifications WHERE id = $notifId AND student_id = $sid");
    header('Location: notifications.php');
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$filterSQL = '';
if ($filter === 'unread') {
    $filterSQL = "AND is_read = 0";
} elseif ($filter === 'read') {
    $filterSQL = "AND is_read = 1";
}

// Get notifications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$notificationsQuery = $db->query("
    SELECT * FROM student_notifications 
    WHERE student_id = $sid $filterSQL
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");

$notifications = [];
if ($notificationsQuery) {
    while ($row = $notificationsQuery->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Get total count for pagination
$countQuery = $db->query("SELECT COUNT(*) as total FROM student_notifications WHERE student_id = $sid $filterSQL");
$totalNotifications = $countQuery->fetch_assoc()['total'];
$totalPages = ceil($totalNotifications / $perPage);

// Get unread count
$unreadQuery = $db->query("SELECT COUNT(*) as unread FROM student_notifications WHERE student_id = $sid AND is_read = 0");
$unreadCount = $unreadQuery->fetch_assoc()['unread'];

// Get notification stats
$statsQuery = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN type = 'task' THEN 1 ELSE 0 END) as tasks,
    SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as messages,
    SUM(CASE WHEN type = 'grade' THEN 1 ELSE 0 END) as grades
    FROM student_notifications 
    WHERE student_id = $sid
");
$stats = $statsQuery->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Padak Notifications</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;--o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --bg:#f8fafc;--card:#ffffff;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;
    --border:#e2e8f0;
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;--yellow:#eab308;
    --shadow:0 1px 3px rgba(0,0,0,0.08),0 4px 16px rgba(0,0,0,0.05);
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
.page-wrap{margin-left:var(--sbw);min-height:100vh;display:flex;flex-direction:column;}
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(248,250,252,0.92);backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:12px 28px;display:flex;align-items:center;gap:12px;
}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}

.main-content{padding:24px 28px;flex:1;max-width:1400px;width:100%;margin:0 auto;}

/* Header */
.notif-header{
    background:linear-gradient(135deg,var(--o5) 0%,var(--o4) 100%);
    border-radius:16px;padding:28px 32px;margin-bottom:24px;
    color:#fff;position:relative;overflow:hidden;
    box-shadow:0 8px 24px rgba(249,115,22,0.25);
    display:flex;align-items:center;justify-content:space-between;
}
.notif-header::before{
    content:'';position:absolute;top:-50px;right:-50px;
    width:200px;height:200px;border-radius:50%;
    background:rgba(255,255,255,0.1);
}
.notif-header-left{position:relative;z-index:1;}
.notif-header h1{font-size:1.8rem;font-weight:800;margin-bottom:6px;text-shadow:0 2px 4px rgba(0,0,0,0.1);}
.notif-header p{font-size:.9rem;opacity:.9;}
.notif-header-right{position:relative;z-index:1;}
.btn-mark-all{
    padding:10px 20px;border-radius:10px;border:2px solid rgba(255,255,255,0.3);
    background:rgba(255,255,255,0.15);color:#fff;
    font-size:.88rem;font-weight:600;cursor:pointer;
    backdrop-filter:blur(8px);transition:all .2s;
    display:flex;align-items:center;gap:6px;
}
.btn-mark-all:hover{background:rgba(255,255,255,0.25);transform:translateY(-2px);}

/* Stats Cards */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.stat-card{
    background:var(--card);border-radius:12px;padding:18px;
    border:1px solid var(--border);box-shadow:var(--shadow);
    position:relative;overflow:hidden;transition:transform .2s;
}
.stat-card:hover{transform:translateY(-2px);}
.stat-card::before{
    content:'';position:absolute;top:-10px;right:-10px;
    width:50px;height:50px;border-radius:50%;opacity:.08;
}
.stat-card.orange::before{background:var(--o5);}
.stat-card.blue::before{background:var(--blue);}
.stat-card.green::before{background:var(--green);}
.stat-card.purple::before{background:var(--purple);}
.stat-card.yellow::before{background:var(--yellow);}
.stat-icon{
    width:36px;height:36px;border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:.9rem;margin-bottom:10px;
}
.stat-card.orange .stat-icon{background:rgba(249,115,22,0.12);color:var(--o5);}
.stat-card.blue .stat-icon{background:rgba(59,130,246,0.12);color:var(--blue);}
.stat-card.green .stat-icon{background:rgba(34,197,94,0.12);color:var(--green);}
.stat-card.purple .stat-icon{background:rgba(139,92,246,0.12);color:var(--purple);}
.stat-card.yellow .stat-icon{background:rgba(234,179,8,0.12);color:var(--yellow);}
.stat-value{font-size:1.4rem;font-weight:800;color:var(--text);line-height:1;margin-bottom:4px;}
.stat-label{font-size:.76rem;color:var(--text2);font-weight:500;}

/* Filter Tabs */
.filter-tabs{
    display:flex;gap:8px;margin-bottom:20px;
    background:var(--card);padding:6px;border-radius:12px;
    border:1px solid var(--border);box-shadow:var(--shadow);
}
.filter-tab{
    padding:10px 20px;border-radius:8px;
    font-size:.88rem;font-weight:600;
    color:var(--text2);background:transparent;
    border:none;cursor:pointer;transition:all .2s;
    display:flex;align-items:center;gap:6px;
}
.filter-tab:hover{background:var(--bg);}
.filter-tab.active{background:var(--o5);color:#fff;box-shadow:0 2px 8px rgba(249,115,22,0.3);}
.filter-badge{
    background:rgba(255,255,255,0.2);
    padding:2px 8px;border-radius:12px;
    font-size:.72rem;font-weight:700;
}
.filter-tab.active .filter-badge{background:rgba(255,255,255,0.25);}
.filter-tab:not(.active) .filter-badge{background:var(--border);color:var(--text3);}

/* Notifications List */
.notifications-container{
    background:var(--card);border-radius:14px;
    border:1px solid var(--border);box-shadow:var(--shadow);
    overflow:hidden;
}
.notification-item{
    display:flex;align-items:flex-start;gap:16px;
    padding:18px 20px;border-bottom:1px solid var(--border);
    transition:background .15s;position:relative;
}
.notification-item:last-child{border-bottom:none;}
.notification-item:hover{background:#fafafa;}
.notification-item.unread{background:rgba(249,115,22,0.03);}
.notification-item.unread::before{
    content:'';position:absolute;left:0;top:0;bottom:0;
    width:4px;background:var(--o5);
}
.notif-icon{
    width:44px;height:44px;border-radius:11px;
    display:flex;align-items:center;justify-content:center;
    font-size:1rem;flex-shrink:0;
}
.notif-icon.task{background:rgba(59,130,246,0.12);color:var(--blue);}
.notif-icon.message{background:rgba(139,92,246,0.12);color:var(--purple);}
.notif-icon.grade{background:rgba(34,197,94,0.12);color:var(--green);}
.notif-icon.certificate{background:rgba(234,179,8,0.12);color:var(--yellow);}
.notif-icon.announcement{background:rgba(249,115,22,0.12);color:var(--o5);}
.notif-content{flex:1;min-width:0;}
.notif-title{font-size:.92rem;font-weight:600;color:var(--text);margin-bottom:4px;}
.notif-message{font-size:.84rem;color:var(--text2);line-height:1.5;margin-bottom:6px;}
.notif-meta{display:flex;align-items:center;gap:12px;font-size:.74rem;color:var(--text3);}
.notif-time{display:flex;align-items:center;gap:4px;}
.notif-link{color:var(--o5);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;}
.notif-link:hover{text-decoration:underline;}
.notif-actions{display:flex;gap:6px;align-items:center;}
.btn-icon{
    width:32px;height:32px;border-radius:8px;
    border:1.5px solid var(--border);background:var(--card);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all .2s;
    color:var(--text3);font-size:.8rem;
}
.btn-icon:hover{background:var(--bg);border-color:var(--o5);color:var(--o5);}
.btn-icon.delete:hover{border-color:var(--red);color:var(--red);}

/* Empty State */
.empty-state{
    text-align:center;padding:60px 20px;
}
.empty-state i{font-size:3rem;margin-bottom:16px;display:block;color:var(--text3);opacity:.3;}
.empty-state h3{font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:6px;}
.empty-state p{font-size:.88rem;color:var(--text2);}

/* Pagination */
.pagination{
    display:flex;align-items:center;justify-content:center;
    gap:8px;margin-top:24px;
}
.page-btn{
    padding:10px 16px;border-radius:9px;
    border:1.5px solid var(--border);background:var(--card);
    color:var(--text2);font-size:.86rem;font-weight:600;
    cursor:pointer;transition:all .2s;
    text-decoration:none;display:inline-flex;align-items:center;gap:6px;
}
.page-btn:hover{background:var(--bg);border-color:var(--o5);color:var(--o5);}
.page-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;}
.page-btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none;}

@media(max-width:1200px){
    .stats-grid{grid-template-columns:repeat(3,1fr);}
}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .notif-header{flex-direction:column;align-items:flex-start;gap:16px;}
    .notif-header-right{width:100%;}
    .btn-mark-all{width:100%;justify-content:center;}
    .stats-grid{grid-template-columns:repeat(2,1fr);}
    .filter-tabs{flex-wrap:wrap;}
    .notification-item{flex-direction:column;gap:12px;}
    .notif-actions{width:100%;justify-content:flex-end;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">Notifications</div>
    </div>

    <div class="main-content">
        <!-- Header -->
        <div class="notif-header">
            <div class="notif-header-left">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <p>Stay updated with your latest activities and announcements</p>
            </div>
            <div class="notif-header-right">
                <?php if ($unreadCount > 0): ?>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="mark_all_read" class="btn-mark-all">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-bell"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-value"><?php echo number_format($stats['unread']); ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                <div class="stat-value"><?php echo number_format($stats['tasks']); ?></div>
                <div class="stat-label">Tasks</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-comments"></i></div>
                <div class="stat-value"><?php echo number_format($stats['messages']); ?></div>
                <div class="stat-label">Messages</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo number_format($stats['grades']); ?></div>
                <div class="stat-label">Grades</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter==='all'?'active':''; ?>">
                <i class="fas fa-list"></i> All
                <span class="filter-badge"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?filter=unread" class="filter-tab <?php echo $filter==='unread'?'active':''; ?>">
                <i class="fas fa-envelope"></i> Unread
                <span class="filter-badge"><?php echo $stats['unread']; ?></span>
            </a>
            <a href="?filter=read" class="filter-tab <?php echo $filter==='read'?'active':''; ?>">
                <i class="fas fa-envelope-open"></i> Read
                <span class="filter-badge"><?php echo $stats['total'] - $stats['unread']; ?></span>
            </a>
        </div>

        <!-- Notifications List -->
        <div class="notifications-container">
            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications here</h3>
                <p>You're all caught up! Check back later for updates.</p>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notif-icon <?php echo htmlspecialchars($notif['type']); ?>">
                        <?php
                        $icon = 'bell';
                        if ($notif['type'] === 'task') $icon = 'tasks';
                        elseif ($notif['type'] === 'message') $icon = 'comments';
                        elseif ($notif['type'] === 'grade') $icon = 'star';
                        elseif ($notif['type'] === 'certificate') $icon = 'certificate';
                        elseif ($notif['type'] === 'announcement') $icon = 'bullhorn';
                        ?>
                        <i class="fas fa-<?php echo $icon; ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                        <?php if ($notif['message']): ?>
                        <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                        <?php endif; ?>
                        <div class="notif-meta">
                            <div class="notif-time">
                                <i class="fas fa-clock"></i>
                                <?php
                                $time = strtotime($notif['created_at']);
                                $diff = time() - $time;
                                if ($diff < 60) echo 'Just now';
                                elseif ($diff < 3600) echo floor($diff/60) . ' min ago';
                                elseif ($diff < 86400) echo floor($diff/3600) . ' hr ago';
                                elseif ($diff < 604800) echo floor($diff/86400) . ' days ago';
                                else echo date('M d, Y', $time);
                                ?>
                            </div>
                            <?php if ($notif['link']): ?>
                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="notif-link">
                                View <i class="fas fa-arrow-right fa-xs"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notif-actions">
                        <?php if (!$notif['is_read']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                            <button type="submit" name="mark_read" class="btn-icon" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this notification?')">
                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                            <button type="submit" name="delete_notification" class="btn-icon delete" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>" class="page-btn">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" class="page-btn <?php echo $i==$page?'active':''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                <span class="page-btn" style="cursor:default;border:none;">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>" class="page-btn">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-refresh unread count every 30 seconds
setInterval(() => {
    fetch('notifications.php?ajax=1')
        .then(r => r.json())
        .then(data => {
            if (data.unread !== <?php echo $unreadCount; ?>) {
                location.reload();
            }
        })
        .catch(err => console.log('Refresh check failed'));
}, 30000);
</script>
</body>
</html>