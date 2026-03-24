<?php
// earnings.php - Student Earnings & Rewards Page
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { 
    header('Location: login.php'); 
    exit; 
}

$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];

// Handle Activation/Claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_earning'])) {
    $earningId = (int)$_POST['earning_id'];
    
    // Check if earning belongs to student and is pending
    $check = $db->query("SELECT * FROM student_earnings WHERE id=$earningId AND student_id=$sid AND status='pending'");
    if ($check && $check->num_rows > 0) {
        $earning = $check->fetch_assoc();
        
        // Generate redemption code if not exists
        $redemptionCode = 'PADAK-' . strtoupper(bin2hex(random_bytes(4)));
        
        // Update status to active
        $db->query("UPDATE student_earnings 
                   SET status='active', 
                       activated_at=NOW(), 
                       redemption_code='$redemptionCode'
                   WHERE id=$earningId");
        
        // Create notification
        $title = $db->real_escape_string("Reward Activated: " . $earning['title']);
        $message = $db->real_escape_string("Your reward has been activated! " . ($earning['redemption_instructions'] ?: 'Contact admin for details.'));
        $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                   VALUES ($sid, '$title', '$message', 'system', NOW())");
        
        $_SESSION['earning_success'] = 'Reward activated successfully! Check your email for details.';
    }
    
    header('Location: earnings.php');
    exit;
}

// Handle Mark as Redeemed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_redeemed'])) {
    $earningId = (int)$_POST['earning_id'];
    
    $db->query("UPDATE student_earnings 
               SET status='redeemed', 
                   redeemed_at=NOW(), 
                   used_quantity=quantity
               WHERE id=$earningId AND student_id=$sid AND status='active'");
    
    $_SESSION['earning_success'] = 'Marked as redeemed!';
    header('Location: earnings.php');
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$filterSQL = '';
if ($filter === 'pending') {
    $filterSQL = "AND status='pending'";
} elseif ($filter === 'active') {
    $filterSQL = "AND status='active'";
} elseif ($filter === 'redeemed') {
    $filterSQL = "AND status='redeemed'";
} elseif ($filter === 'expired') {
    $filterSQL = "AND status='expired'";
}

// Auto-expire old rewards
$db->query("UPDATE student_earnings 
           SET status='expired' 
           WHERE student_id=$sid 
           AND status IN ('pending','active') 
           AND expires_at IS NOT NULL 
           AND expires_at < NOW()");

// Get earnings
$earningsQuery = $db->query("
    SELECT * FROM student_earnings 
    WHERE student_id=$sid $filterSQL
    ORDER BY 
        FIELD(status, 'pending', 'active', 'redeemed', 'expired', 'revoked'),
        is_featured DESC,
        awarded_at DESC
");

$earnings = [];
if ($earningsQuery) {
    while ($row = $earningsQuery->fetch_assoc()) {
        $earnings[] = $row;
    }
}

// Get statistics
$statsQuery = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='redeemed' THEN 1 ELSE 0 END) as redeemed,
    SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) as expired,
    SUM(CASE WHEN earning_type='mentorship' THEN 1 ELSE 0 END) as mentorship,
    SUM(CASE WHEN earning_type='software_access' THEN 1 ELSE 0 END) as software,
    SUM(CASE WHEN earning_type='learning_resource' THEN 1 ELSE 0 END) as resources
    FROM student_earnings 
    WHERE student_id=$sid
");
$stats = $statsQuery->fetch_assoc();

// Get success/error messages
$success = $_SESSION['earning_success'] ?? '';
$error = $_SESSION['earning_error'] ?? '';
unset($_SESSION['earning_success'], $_SESSION['earning_error']);

$activePage = 'earnings';
$initials = strtoupper(substr($student['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Earnings - Padak</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;--o1:#fff7ed;--o2:#ffedd5;
    --bg:#f8fafc;--card:#ffffff;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;
    --border:#e2e8f0;
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;--yellow:#eab308;--cyan:#06b6d4;
    --shadow:0 1px 3px rgba(0,0,0,0.08),0 4px 16px rgba(0,0,0,0.05);
}
body{
    font-family:'Inter',sans-serif;
    background:linear-gradient(135deg,var(--o1) 0%,#fff 60%,var(--o2) 100%);
    color:var(--text);
    min-height:100vh;
}

.page-wrap{
    margin-left:var(--sbw);
    min-height:100vh;
    display:flex;
    flex-direction:column;
    transition:margin-left 0.3s ease;
}

/* Topbar */
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(248,250,252,0.92);
    backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:12px 28px;
    display:flex;align-items:center;gap:12px;
    box-shadow:0 2px 8px rgba(0,0,0,0.04);
}
.topbar-hamburger{
    display:none;background:none;border:none;
    cursor:pointer;color:var(--text2);
    padding:6px;border-radius:7px;font-size:1.1rem;
}
.topbar-hamburger:hover{background:var(--border);}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.topbar-breadcrumb{font-size:0.8125rem;color:var(--text3);flex:1;}
.topbar-breadcrumb span{color:var(--text);font-weight:600;}
.topbar-avatar{
    width:38px;height:38px;border-radius:50%;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    display:flex;align-items:center;justify-content:center;
    font-size:0.8125rem;font-weight:700;color:#fff;
    text-decoration:none;
    border:2px solid rgba(249,115,22,0.3);
    transition:transform 0.2s;
}
.topbar-avatar:hover{transform:scale(1.05);}

/* Main Content */
.main-content{
    padding:24px 28px;
    flex:1;
    max-width:1400px;
    width:100%;
    margin:0 auto;
}

/* Alert */
.alert{
    display:flex;align-items:flex-start;gap:12px;
    padding:14px 18px;border-radius:10px;
    font-size:.875rem;font-weight:500;
    margin-bottom:20px;
    animation:slideIn .3s ease;
}
.alert-success{
    background:#f0fdf4;
    border:1px solid #bbf7d0;
    color:#166534;
}
.alert-error{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#991b1b;
}
@keyframes slideIn{
    from{opacity:0;transform:translateY(-8px);}
    to{opacity:1;transform:translateY(0);}
}

/* Header */
.earnings-header{
    background:linear-gradient(135deg,var(--o5) 0%,var(--o4) 100%);
    border-radius:16px;
    padding:32px;
    margin-bottom:28px;
    color:#fff;
    position:relative;
    overflow:hidden;
    box-shadow:0 8px 24px rgba(249,115,22,0.25);
}
.earnings-header::before{
    content:'';
    position:absolute;
    top:-50px;right:-50px;
    width:200px;height:200px;
    border-radius:50%;
    background:rgba(255,255,255,0.1);
}
.earnings-header::after{
    content:'';
    position:absolute;
    bottom:-30px;left:-30px;
    width:150px;height:150px;
    border-radius:50%;
    background:rgba(255,255,255,0.08);
}
.eh-content{
    position:relative;
    z-index:1;
}
.eh-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:16px;
    flex-wrap:wrap;
    gap:16px;
}
.eh-title h1{
    font-size:2rem;
    font-weight:800;
    margin-bottom:6px;
    text-shadow:0 2px 4px rgba(0,0,0,0.1);
    display:flex;
    align-items:center;
    gap:12px;
}
.eh-title p{
    font-size:0.95rem;
    opacity:0.9;
}
.eh-badge{
    padding:8px 16px;
    background:rgba(255,255,255,0.2);
    border-radius:20px;
    font-size:0.875rem;
    font-weight:600;
    backdrop-filter:blur(8px);
    display:flex;
    align-items:center;
    gap:6px;
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
    top:-10px;right:-10px;
    width:60px;height:60px;
    border-radius:50%;
    opacity:0.08;
}
.stat-card.orange::before{background:var(--o5);}
.stat-card.blue::before{background:var(--blue);}
.stat-card.green::before{background:var(--green);}
.stat-card.purple::before{background:var(--purple);}
.stat-card.cyan::before{background:var(--cyan);}

.stat-icon{
    width:42px;height:42px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.1rem;
    margin-bottom:12px;
}
.stat-card.orange .stat-icon{background:rgba(249,115,22,0.12);color:var(--o5);}
.stat-card.blue .stat-icon{background:rgba(59,130,246,0.12);color:var(--blue);}
.stat-card.green .stat-icon{background:rgba(34,197,94,0.12);color:var(--green);}
.stat-card.purple .stat-icon{background:rgba(139,92,246,0.12);color:var(--purple);}
.stat-card.cyan .stat-icon{background:rgba(6,182,212,0.12);color:var(--cyan);}

.stat-value{
    font-size:1.8rem;
    font-weight:800;
    color:var(--text);
    line-height:1;
    margin-bottom:6px;
}
.stat-label{
    font-size:0.8rem;
    color:var(--text2);
    font-weight:500;
}

/* Filter Tabs */
.filter-tabs{
    display:flex;
    gap:8px;
    margin-bottom:24px;
    background:var(--card);
    padding:6px;
    border-radius:12px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    flex-wrap:wrap;
}
.filter-tab{
    padding:10px 20px;
    border-radius:8px;
    font-size:0.875rem;
    font-weight:600;
    color:var(--text2);
    background:transparent;
    border:none;
    cursor:pointer;
    transition:all 0.2s;
    display:flex;
    align-items:center;
    gap:6px;
    text-decoration:none;
}
.filter-tab:hover{
    background:var(--bg);
    text-decoration:none;
}
.filter-tab.active{
    background:var(--o5);
    color:#fff;
    box-shadow:0 2px 8px rgba(249,115,22,0.3);
}
.filter-badge{
    background:rgba(255,255,255,0.2);
    padding:2px 8px;
    border-radius:12px;
    font-size:0.72rem;
    font-weight:700;
}
.filter-tab.active .filter-badge{
    background:rgba(255,255,255,0.25);
}
.filter-tab:not(.active) .filter-badge{
    background:var(--border);
    color:var(--text3);
}

/* Earnings Grid */
.earnings-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
    gap:20px;
    margin-bottom:28px;
}

/* Earning Card */
.earning-card{
    background:var(--card);
    border-radius:14px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    overflow:hidden;
    transition:all 0.3s;
    position:relative;
}
.earning-card:hover{
    transform:translateY(-4px);
    box-shadow:0 8px 28px rgba(0,0,0,0.12);
}
.earning-card.featured{
    border:2px solid var(--o5);
}
.earning-card.featured::before{
    content:'FEATURED';
    position:absolute;
    top:12px;right:-28px;
    background:var(--o5);
    color:#fff;
    padding:4px 32px;
    font-size:0.65rem;
    font-weight:700;
    letter-spacing:0.05em;
    transform:rotate(45deg);
    z-index:10;
    box-shadow:0 2px 8px rgba(249,115,22,0.4);
}

.ec-header{
    padding:20px;
    background:linear-gradient(135deg,rgba(249,115,22,0.08),rgba(251,146,60,0.05));
    border-bottom:1px solid var(--border);
    position:relative;
}
.ec-type{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 12px;
    border-radius:20px;
    font-size:0.7rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.03em;
    margin-bottom:12px;
}
.ec-type.mentorship{background:rgba(139,92,246,0.12);color:var(--purple);}
.ec-type.software_access{background:rgba(59,130,246,0.12);color:var(--blue);}
.ec-type.learning_resource{background:rgba(34,197,94,0.12);color:var(--green);}
.ec-type.exclusive_perk{background:rgba(234,179,8,0.12);color:var(--yellow);}
.ec-type.bonus_reward{background:rgba(249,115,22,0.12);color:var(--o5);}

.ec-title{
    font-size:1.1rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:8px;
    line-height:1.3;
}
.ec-value{
    font-size:0.8rem;
    color:var(--o5);
    font-weight:600;
    display:flex;
    align-items:center;
    gap:6px;
}

.ec-body{
    padding:20px;
}
.ec-description{
    font-size:0.875rem;
    color:var(--text2);
    line-height:1.6;
    margin-bottom:16px;
}
.ec-reason{
    padding:12px;
    background:rgba(249,115,22,0.05);
    border-left:3px solid var(--o5);
    border-radius:6px;
    margin-bottom:16px;
}
.ec-reason-label{
    font-size:0.7rem;
    font-weight:700;
    color:var(--o6);
    text-transform:uppercase;
    letter-spacing:0.05em;
    margin-bottom:4px;
}
.ec-reason-text{
    font-size:0.825rem;
    color:var(--text2);
    line-height:1.5;
}

.ec-meta{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-bottom:16px;
    padding-top:12px;
    border-top:1px solid var(--border);
}
.ec-meta-item{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:0.8rem;
    color:var(--text3);
}
.ec-meta-item i{
    width:16px;
    text-align:center;
    font-size:0.75rem;
}
.ec-meta-item strong{
    color:var(--text);
}

.ec-footer{
    padding:16px 20px;
    background:var(--bg);
    border-top:1px solid var(--border);
    display:flex;
    gap:10px;
}

/* Status Badge */
.status-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 12px;
    border-radius:20px;
    font-size:0.75rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.03em;
}
.status-badge.pending{background:rgba(234,179,8,0.15);color:#ca8a04;}
.status-badge.active{background:rgba(34,197,94,0.15);color:#16a34a;}
.status-badge.redeemed{background:rgba(139,92,246,0.15);color:#7c3aed;}
.status-badge.expired{background:rgba(239,68,68,0.15);color:#dc2626;}
.status-badge.revoked{background:rgba(100,116,139,0.15);color:#475569;}

/* Buttons */
.btn{
    padding:10px 18px;
    border-radius:9px;
    font-size:0.875rem;
    font-weight:600;
    font-family:inherit;
    cursor:pointer;
    border:none;
    display:inline-flex;
    align-items:center;
    gap:7px;
    text-decoration:none;
    transition:all 0.2s;
    flex:1;
    justify-content:center;
}
.btn-primary{
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    box-shadow:0 4px 14px rgba(249,115,22,0.3);
}
.btn-primary:hover{
    transform:translateY(-1px);
    box-shadow:0 6px 20px rgba(249,115,22,0.45);
}
.btn-secondary{
    background:var(--card);
    border:1.5px solid var(--border);
    color:var(--text2);
}
.btn-secondary:hover{
    border-color:var(--o5);
    color:var(--o5);
}
.btn:disabled{
    opacity:0.5;
    cursor:not-allowed;
    pointer-events:none;
}

/* Redemption Code */
.redemption-code{
    padding:12px;
    background:rgba(139,92,246,0.08);
    border:2px dashed var(--purple);
    border-radius:8px;
    margin-bottom:16px;
}
.redemption-code-label{
    font-size:0.7rem;
    font-weight:700;
    color:var(--purple);
    text-transform:uppercase;
    letter-spacing:0.05em;
    margin-bottom:6px;
}
.redemption-code-value{
    font-size:1.1rem;
    font-weight:800;
    color:var(--text);
    font-family:'Courier New',monospace;
    letter-spacing:0.1em;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.copy-btn{
    padding:6px 12px;
    background:var(--purple);
    color:#fff;
    border-radius:6px;
    font-size:0.7rem;
    font-weight:700;
    cursor:pointer;
    border:none;
    transition:all 0.2s;
}
.copy-btn:hover{
    background:#6d28d9;
}

/* Empty State */
.empty-state{
    text-align:center;
    padding:80px 20px;
    background:var(--card);
    border-radius:14px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
}
.empty-state i{
    font-size:4rem;
    margin-bottom:20px;
    display:block;
    color:var(--text3);
    opacity:0.3;
}
.empty-state h3{
    font-size:1.3rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:8px;
}
.empty-state p{
    font-size:0.95rem;
    color:var(--text2);
    max-width:500px;
    margin:0 auto 24px;
    line-height:1.6;
}

/* Responsive */
@media(max-width:1200px){
    .stats-grid{grid-template-columns:repeat(3,1fr);}
    .earnings-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr));}
}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .topbar-breadcrumb{display:none;}
    .main-content{padding:16px;}
    .earnings-header{padding:24px 20px;}
    .eh-title h1{font-size:1.5rem;}
    .stats-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
    .stat-card{padding:16px;}
    .stat-value{font-size:1.5rem;}
    .filter-tabs{padding:4px;}
    .filter-tab{padding:8px 14px;font-size:0.8rem;}
    .earnings-grid{grid-template-columns:1fr;}
    .ec-footer{flex-direction:column;}
    .btn{flex:none;width:100%;}
}
@media(max-width:480px){
    .stats-grid{grid-template-columns:1fr;}
    .eh-top{flex-direction:column;align-items:flex-start;}
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
            Padak › <span>My Earnings</span>
        </div>
        <a href="profile.php" class="topbar-avatar" title="<?php echo htmlspecialchars($student['full_name']); ?>">
            <?php echo $initials; ?>
        </a>
    </div>

    <div class="main-content">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="earnings-header">
            <div class="eh-content">
                <div class="eh-top">
                    <div class="eh-title">
                        <h1>
                            <i class="fas fa-gift"></i>
                            My Earnings & Rewards
                        </h1>
                        <p>Exclusive benefits and perks earned through your outstanding performance</p>
                    </div>
                    <?php if ($stats['pending'] > 0): ?>
                    <div class="eh-badge">
                        <i class="fas fa-star"></i>
                        <?php echo $stats['pending']; ?> New Reward<?php echo $stats['pending'] > 1 ? 's' : ''; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-gift"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Earned</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo number_format($stats['redeemed']); ?></div>
                <div class="stat-label">Redeemed</div>
            </div>
            <div class="stat-card cyan">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo number_format($stats['mentorship']); ?></div>
                <div class="stat-label">Mentorships</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter==='all'?'active':''; ?>">
                <i class="fas fa-list"></i> All
                <span class="filter-badge"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter==='pending'?'active':''; ?>">
                <i class="fas fa-clock"></i> Pending
                <span class="filter-badge"><?php echo $stats['pending']; ?></span>
            </a>
            <a href="?filter=active" class="filter-tab <?php echo $filter==='active'?'active':''; ?>">
                <i class="fas fa-bolt"></i> Active
                <span class="filter-badge"><?php echo $stats['active']; ?></span>
            </a>
            <a href="?filter=redeemed" class="filter-tab <?php echo $filter==='redeemed'?'active':''; ?>">
                <i class="fas fa-check-circle"></i> Redeemed
                <span class="filter-badge"><?php echo $stats['redeemed']; ?></span>
            </a>
            <a href="?filter=expired" class="filter-tab <?php echo $filter==='expired'?'active':''; ?>">
                <i class="fas fa-hourglass-end"></i> Expired
                <span class="filter-badge"><?php echo $stats['expired']; ?></span>
            </a>
        </div>

        <!-- Earnings Grid -->
        <?php if (empty($earnings)): ?>
        <div class="empty-state">
            <i class="fas fa-gift"></i>
            <h3>No earnings yet</h3>
            <p>Keep performing well in your internship! Outstanding work and discipline will earn you exclusive rewards like mentorship sessions, premium software access, and more.</p>
        </div>
        <?php else: ?>
        <div class="earnings-grid">
            <?php foreach ($earnings as $earning): ?>
            <div class="earning-card <?php echo $earning['is_featured'] ? 'featured' : ''; ?>">
                <div class="ec-header">
                    <span class="ec-type <?php echo $earning['earning_type']; ?>">
                        <?php
                        $typeIcons = [
                            'mentorship' => 'users',
                            'software_access' => 'laptop-code',
                            'learning_resource' => 'graduation-cap',
                            'exclusive_perk' => 'star',
                            'bonus_reward' => 'gift'
                        ];
                        $icon = $typeIcons[$earning['earning_type']] ?? 'gift';
                        ?>
                        <i class="fas fa-<?php echo $icon; ?>"></i>
                        <?php echo ucwords(str_replace('_', ' ', $earning['earning_type'])); ?>
                    </span>
                    <div class="ec-title"><?php echo htmlspecialchars($earning['title']); ?></div>
                    <?php if ($earning['value']): ?>
                    <div class="ec-value">
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($earning['value']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="ec-body">
                    <?php if ($earning['description']): ?>
                    <div class="ec-description">
                        <?php echo htmlspecialchars($earning['description']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($earning['awarded_for']): ?>
                    <div class="ec-reason">
                        <div class="ec-reason-label">
                            <i class="fas fa-award"></i> Earned For
                        </div>
                        <div class="ec-reason-text">
                            <?php echo htmlspecialchars($earning['awarded_for']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($earning['status'] === 'active' && $earning['redemption_code']): ?>
                    <div class="redemption-code">
                        <div class="redemption-code-label">
                            <i class="fas fa-ticket-alt"></i> Redemption Code
                        </div>
                        <div class="redemption-code-value">
                            <span><?php echo htmlspecialchars($earning['redemption_code']); ?></span>
                            <button class="copy-btn" onclick="copyCode('<?php echo $earning['redemption_code']; ?>')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($earning['redemption_instructions'] && $earning['status'] !== 'expired'): ?>
                    <div class="ec-reason" style="border-left-color:var(--blue);background:rgba(59,130,246,0.05);">
                        <div class="ec-reason-label" style="color:var(--blue);">
                            <i class="fas fa-info-circle"></i> How to Redeem
                        </div>
                        <div class="ec-reason-text">
                            <?php echo htmlspecialchars($earning['redemption_instructions']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="ec-meta">
                        <div class="ec-meta-item">
                            <i class="fas fa-calendar"></i>
                            Awarded on <strong><?php echo date('M d, Y', strtotime($earning['awarded_at'])); ?></strong>
                        </div>
                        <?php if ($earning['expires_at']): ?>
                        <div class="ec-meta-item">
                            <i class="fas fa-hourglass-end"></i>
                            <?php
                            $expiry = strtotime($earning['expires_at']);
                            $now = time();
                            if ($earning['status'] === 'expired') {
                                echo 'Expired on <strong>' . date('M d, Y', $expiry) . '</strong>';
                            } else {
                                $daysLeft = ceil(($expiry - $now) / 86400);
                                echo 'Expires in <strong>' . $daysLeft . ' day' . ($daysLeft > 1 ? 's' : '') . '</strong>';
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($earning['activated_at']): ?>
                        <div class="ec-meta-item">
                            <i class="fas fa-bolt"></i>
                            Activated on <strong><?php echo date('M d, Y', strtotime($earning['activated_at'])); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($earning['redeemed_at']): ?>
                        <div class="ec-meta-item">
                            <i class="fas fa-check-circle"></i>
                            Redeemed on <strong><?php echo date('M d, Y', strtotime($earning['redeemed_at'])); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($earning['quantity'] > 1): ?>
                        <div class="ec-meta-item">
                            <i class="fas fa-layer-group"></i>
                            Uses: <strong><?php echo $earning['used_quantity']; ?> / <?php echo $earning['quantity']; ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ec-footer">
                    <span class="status-badge <?php echo $earning['status']; ?>">
                        <?php
                        $statusIcons = [
                            'pending' => 'clock',
                            'active' => 'bolt',
                            'redeemed' => 'check-circle',
                            'expired' => 'times-circle',
                            'revoked' => 'ban'
                        ];
                        $statusIcon = $statusIcons[$earning['status']] ?? 'question';
                        ?>
                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                        <?php echo ucfirst($earning['status']); ?>
                    </span>
                    
                    <?php if ($earning['status'] === 'pending'): ?>
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="earning_id" value="<?php echo $earning['id']; ?>">
                        <button type="submit" name="activate_earning" class="btn btn-primary">
                            <i class="fas fa-bolt"></i> Activate Now
                        </button>
                    </form>
                    <?php elseif ($earning['status'] === 'active'): ?>
                    <form method="POST" style="flex:1;" onsubmit="return confirm('Mark this reward as fully redeemed?')">
                        <input type="hidden" name="earning_id" value="<?php echo $earning['id']; ?>">
                        <button type="submit" name="mark_redeemed" class="btn btn-secondary">
                            <i class="fas fa-check"></i> Mark as Used
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Copy redemption code
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        alert('Redemption code copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy. Code: ' + code);
    });
}

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        alert.style.transition = 'all 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);
</script>

</body>
</html>