<?php
// earnings.php - Gamified Rewards System
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

// Handle Box Opening (Unlock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_reward'])) {
    $rewardId = (int)$_POST['reward_id'];
    
    $check = $db->query("SELECT * FROM student_rewards WHERE id=$rewardId AND student_id=$sid AND status='locked'");
    if ($check && $check->num_rows > 0) {
        $reward = $check->fetch_assoc();
        
        // Generate code if not exists
        $code = $reward['code'] ?: 'PADAK-' . strtoupper(bin2hex(random_bytes(4)));
        $codeEsc = $db->real_escape_string($code);
        
        $db->query("UPDATE student_rewards 
                   SET status='unlocked', unlocked_at=NOW(), code='$codeEsc'
                   WHERE id=$rewardId");
        
        // Mark notification as read if exists
        $db->query("UPDATE student_notifications SET is_read=1 
                   WHERE student_id=$sid AND message LIKE '%reward%' AND is_read=0 
                   ORDER BY created_at DESC LIMIT 1");
        
        echo json_encode(['success' => true, 'reward' => $reward, 'code' => $code]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid reward']);
    }
    exit;
}

// Handle Claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_reward'])) {
    $rewardId = (int)$_POST['reward_id'];
    
    $db->query("UPDATE student_rewards 
               SET status='claimed', claimed_at=NOW()
               WHERE id=$rewardId AND student_id=$sid AND status='unlocked'");
    
    echo json_encode(['success' => true]);
    exit;
}

// Get all rewards
$rewardsQuery = $db->query("
    SELECT * FROM student_rewards 
    WHERE student_id=$sid
    ORDER BY position ASC, awarded_at DESC
");

$rewards = [];
if ($rewardsQuery) {
    while ($row = $rewardsQuery->fetch_assoc()) {
        $rewards[] = $row;
    }
}

// Ensure at least 6 placeholder boxes
while (count($rewards) < 6) {
    $rewards[] = [
        'id' => 0,
        'status' => 'empty',
        'title' => 'Empty Slot',
        'icon' => '📦',
        'color' => 'gray'
    ];
}

// Get stats
$stats = [
    'total' => count(array_filter($rewards, fn($r) => $r['id'] > 0)),
    'locked' => count(array_filter($rewards, fn($r) => isset($r['status']) && $r['status'] === 'locked')),
    'unlocked' => count(array_filter($rewards, fn($r) => isset($r['status']) && $r['status'] === 'unlocked')),
    'claimed' => count(array_filter($rewards, fn($r) => isset($r['status']) && $r['status'] === 'claimed'))
];

// Check for new rewards notification
$newRewardsCount = $db->query("SELECT COUNT(*) as c FROM student_rewards WHERE student_id=$sid AND status='locked'")->fetch_assoc()['c'];

$activePage = 'earnings';
$initials = strtoupper(substr($student['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Rewards - Padak</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --bg:#0a0a0a;--card:#1a1a1a;
    --text:#ffffff;--text2:#a0a0a0;--text3:#606060;
    --border:#2a2a2a;
    --purple:#a855f7;--blue:#3b82f6;--green:#10b981;--pink:#ec4899;--yellow:#eab308;
}
body{
    font-family:'Inter',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    overflow-x:hidden;
}

/* Animated Background */
.bg-animation{
    position:fixed;
    top:0;left:0;right:0;bottom:0;
    z-index:0;
    pointer-events:none;
    opacity:0.4;
}
.particle{
    position:absolute;
    width:4px;
    height:4px;
    background:var(--o5);
    border-radius:50%;
    animation:float 20s infinite;
    opacity:0.3;
}
@keyframes float{
    0%,100%{transform:translateY(0) translateX(0);}
    50%{transform:translateY(-100px) translateX(50px);}
}

.page-wrap{
    margin-left:var(--sbw);
    min-height:100vh;
    display:flex;
    flex-direction:column;
    position:relative;
    z-index:1;
}

/* Topbar */
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(10,10,10,0.95);
    backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    padding:16px 32px;
    display:flex;align-items:center;gap:16px;
}
.topbar-hamburger{
    display:none;background:none;border:none;
    cursor:pointer;color:var(--text2);
    padding:8px;border-radius:8px;
    transition:all 0.2s;
}
.topbar-hamburger:hover{background:var(--card);color:var(--text);}
.topbar-title{
    font-size:1.1rem;font-weight:700;color:var(--text);flex:1;
    display:flex;align-items:center;gap:12px;
}
.topbar-title i{
    width:36px;height:36px;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:1rem;
}
.topbar-avatar{
    width:40px;height:40px;border-radius:50%;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    display:flex;align-items:center;justify-content:center;
    font-size:0.9rem;font-weight:700;color:#fff;
    text-decoration:none;
    border:2px solid rgba(249,115,22,0.3);
    transition:transform 0.2s;
}
.topbar-avatar:hover{transform:scale(1.05);}

/* Main Content */
.main-content{
    padding:40px 32px;
    flex:1;
    max-width:1400px;
    width:100%;
    margin:0 auto;
}

/* Hero Header */
.hero-header{
    text-align:center;
    margin-bottom:60px;
    position:relative;
}
.hero-title{
    font-size:3.5rem;
    font-weight:900;
    background:linear-gradient(135deg,var(--o5),var(--o4),var(--purple));
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    margin-bottom:16px;
    line-height:1.1;
    animation:glow 3s ease-in-out infinite;
}
@keyframes glow{
    0%,100%{filter:drop-shadow(0 0 20px rgba(249,115,22,0.3));}
    50%{filter:drop-shadow(0 0 40px rgba(249,115,22,0.6));}
}
.hero-subtitle{
    font-size:1.2rem;
    color:var(--text2);
    max-width:600px;
    margin:0 auto 32px;
}
.hero-stats{
    display:inline-flex;
    gap:32px;
    padding:20px 40px;
    background:var(--card);
    border-radius:16px;
    border:1px solid var(--border);
}
.hero-stat{
    text-align:center;
}
.hero-stat-value{
    font-size:2rem;
    font-weight:800;
    background:linear-gradient(135deg,var(--o5),var(--purple));
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}
.hero-stat-label{
    font-size:0.85rem;
    color:var(--text3);
    text-transform:uppercase;
    letter-spacing:0.1em;
    margin-top:4px;
}

/* Rewards Grid */
.rewards-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    gap:32px;
    margin-bottom:60px;
    perspective:1000px;
}

/* Gift Box */
.gift-box{
    position:relative;
    aspect-ratio:1;
    cursor:pointer;
    transition:transform 0.3s ease;
    transform-style:preserve-3d;
}
.gift-box:hover{
    transform:translateY(-8px) scale(1.02);
}
.gift-box.opening{
    animation:shake 0.5s ease;
    pointer-events:none;
}
@keyframes shake{
    0%,100%{transform:rotate(0deg);}
    25%{transform:rotate(-5deg);}
    75%{transform:rotate(5deg);}
}

.box-inner{
    position:relative;
    width:100%;
    height:100%;
    border-radius:20px;
    background:var(--card);
    border:2px solid var(--border);
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:24px;
    overflow:hidden;
    transition:all 0.3s ease;
}
.gift-box:hover .box-inner{
    border-color:var(--o5);
    box-shadow:0 0 40px rgba(249,115,22,0.3);
}

/* Box States */
.box-inner.locked{
    background:linear-gradient(135deg,rgba(249,115,22,0.1),rgba(168,85,247,0.1));
}
.box-inner.unlocked{
    background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(59,130,246,0.15));
    border-color:var(--green);
}
.box-inner.claimed{
    opacity:0.6;
    background:var(--card);
    border-color:var(--border);
}
.box-inner.empty{
    opacity:0.3;
    cursor:default;
    border-style:dashed;
}
.gift-box.empty:hover{
    transform:none;
}

/* Box Icon */
.box-icon{
    font-size:4rem;
    margin-bottom:16px;
    filter:drop-shadow(0 4px 12px rgba(0,0,0,0.3));
    animation:bounce 2s infinite;
}
@keyframes bounce{
    0%,100%{transform:translateY(0);}
    50%{transform:translateY(-10px);}
}
.box-inner.locked .box-icon{
    filter:grayscale(0.5) brightness(0.8);
}
.box-inner.claimed .box-icon{
    filter:grayscale(1) brightness(0.5);
}

/* Box Content */
.box-title{
    font-size:1.1rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:8px;
    text-align:center;
}
.box-subtitle{
    font-size:0.85rem;
    color:var(--text2);
    text-align:center;
    margin-bottom:16px;
}
.box-value{
    padding:6px 16px;
    background:rgba(249,115,22,0.2);
    border-radius:20px;
    font-size:0.8rem;
    font-weight:600;
    color:var(--o5);
    margin-bottom:12px;
}

/* Box Badge */
.box-badge{
    position:absolute;
    top:12px;
    right:12px;
    padding:6px 12px;
    border-radius:20px;
    font-size:0.7rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.05em;
}
.badge-locked{background:rgba(249,115,22,0.2);color:var(--o5);}
.badge-unlocked{background:rgba(16,185,129,0.2);color:var(--green);}
.badge-claimed{background:rgba(96,96,96,0.2);color:var(--text3);}
.badge-priority{
    background:linear-gradient(135deg,var(--o5),var(--purple));
    color:#fff;
    animation:pulse 2s infinite;
}
@keyframes pulse{
    0%,100%{transform:scale(1);}
    50%{transform:scale(1.05);}
}

/* Lock Icon */
.lock-overlay{
    position:absolute;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    width:60px;
    height:60px;
    background:rgba(0,0,0,0.8);
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.5rem;
    color:var(--o5);
    backdrop-filter:blur(10px);
}

/* Click Hint */
.click-hint{
    position:absolute;
    bottom:16px;
    left:50%;
    transform:translateX(-50%);
    padding:8px 16px;
    background:rgba(249,115,22,0.9);
    color:#fff;
    border-radius:20px;
    font-size:0.75rem;
    font-weight:600;
    animation:float-hint 2s infinite;
}
@keyframes float-hint{
    0%,100%{transform:translateX(-50%) translateY(0);}
    50%{transform:translateX(-50%) translateY(-5px);}
}

/* Reward Modal */
.reward-modal{
    display:none;
    position:fixed;
    top:0;left:0;right:0;bottom:0;
    background:rgba(0,0,0,0.9);
    backdrop-filter:blur(10px);
    z-index:9999;
    align-items:center;
    justify-content:center;
    padding:20px;
    animation:fadeIn 0.3s ease;
}
.reward-modal.open{display:flex;}
@keyframes fadeIn{
    from{opacity:0;}
    to{opacity:1;}
}

.reward-card{
    background:var(--card);
    border-radius:24px;
    max-width:500px;
    width:100%;
    border:2px solid var(--o5);
    box-shadow:0 0 60px rgba(249,115,22,0.5);
    animation:slideUp 0.5s ease;
    position:relative;
    overflow:hidden;
}
@keyframes slideUp{
    from{transform:translateY(100px);opacity:0;}
    to{transform:translateY(0);opacity:1;}
}

.reward-card-header{
    padding:32px 32px 24px;
    text-align:center;
    background:linear-gradient(135deg,rgba(249,115,22,0.1),rgba(168,85,247,0.1));
    border-bottom:1px solid var(--border);
}
.reward-card-icon{
    font-size:5rem;
    margin-bottom:16px;
    animation:celebrate 1s ease;
}
@keyframes celebrate{
    0%{transform:scale(0) rotate(0deg);}
    50%{transform:scale(1.2) rotate(180deg);}
    100%{transform:scale(1) rotate(360deg);}
}
.reward-card-title{
    font-size:1.8rem;
    font-weight:800;
    color:var(--text);
    margin-bottom:8px;
}
.reward-card-subtitle{
    font-size:1rem;
    color:var(--text2);
}

.reward-card-body{
    padding:32px;
}
.reward-info-item{
    padding:16px;
    background:rgba(249,115,22,0.05);
    border-radius:12px;
    margin-bottom:16px;
    border-left:3px solid var(--o5);
}
.reward-info-label{
    font-size:0.75rem;
    font-weight:700;
    color:var(--o5);
    text-transform:uppercase;
    letter-spacing:0.1em;
    margin-bottom:6px;
}
.reward-info-text{
    font-size:0.95rem;
    color:var(--text);
    line-height:1.6;
}

.reward-code{
    padding:20px;
    background:rgba(168,85,247,0.1);
    border:2px dashed var(--purple);
    border-radius:16px;
    margin-bottom:24px;
}
.reward-code-label{
    font-size:0.75rem;
    font-weight:700;
    color:var(--purple);
    text-transform:uppercase;
    letter-spacing:0.1em;
    margin-bottom:10px;
}
.reward-code-value{
    font-size:1.5rem;
    font-weight:800;
    font-family:'Courier New',monospace;
    color:var(--text);
    letter-spacing:0.15em;
    text-align:center;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.copy-code-btn{
    padding:10px 20px;
    background:var(--purple);
    color:#fff;
    border:none;
    border-radius:10px;
    font-size:0.85rem;
    font-weight:600;
    cursor:pointer;
    transition:all 0.2s;
}
.copy-code-btn:hover{
    background:#9333ea;
    transform:translateY(-2px);
}

.reward-card-footer{
    padding:24px 32px;
    border-top:1px solid var(--border);
    display:flex;
    gap:12px;
}
.btn{
    flex:1;
    padding:14px 24px;
    border-radius:12px;
    font-size:0.95rem;
    font-weight:700;
    font-family:inherit;
    cursor:pointer;
    border:none;
    transition:all 0.2s;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.btn-primary{
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    box-shadow:0 4px 20px rgba(249,115,22,0.4);
}
.btn-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 30px rgba(249,115,22,0.6);
}
.btn-secondary{
    background:var(--card);
    border:2px solid var(--border);
    color:var(--text2);
}
.btn-secondary:hover{
    border-color:var(--o5);
    color:var(--o5);
}

/* Confetti */
.confetti{
    position:fixed;
    width:10px;
    height:10px;
    background:var(--o5);
    animation:confetti-fall 3s linear;
    z-index:10000;
    pointer-events:none;
}
@keyframes confetti-fall{
    to{
        transform:translateY(100vh) rotate(360deg);
        opacity:0;
    }
}

/* Responsive */
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .main-content{padding:24px 16px;}
    .hero-title{font-size:2.5rem;}
    .hero-subtitle{font-size:1rem;}
    .hero-stats{gap:20px;padding:16px 24px;flex-direction:column;}
    .rewards-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;}
    .box-icon{font-size:3rem;}
    .box-title{font-size:0.95rem;}
    .box-subtitle{font-size:0.75rem;}
    .reward-card{max-width:100%;}
    .reward-card-header{padding:24px 20px;}
    .reward-card-icon{font-size:4rem;}
    .reward-card-title{font-size:1.5rem;}
    .reward-card-body{padding:20px;}
    .reward-code-value{font-size:1.2rem;flex-direction:column;gap:12px;}
    .copy-code-btn{width:100%;}
    .reward-card-footer{flex-direction:column;}
}
</style>
</head>
<body>

<!-- Animated Background -->
<div class="bg-animation">
    <?php for($i=0;$i<20;$i++): ?>
    <div class="particle" style="
        left:<?php echo rand(0,100); ?>%;
        top:<?php echo rand(0,100); ?>%;
        animation-delay:<?php echo rand(0,10); ?>s;
    "></div>
    <?php endfor; ?>
</div>

<?php include 'sidebar.php'; ?>

<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <i class="fas fa-gift"></i>
            My Rewards
        </div>
        <a href="profile.php" class="topbar-avatar" title="<?php echo htmlspecialchars($student['full_name']); ?>">
            <?php echo $initials; ?>
        </a>
    </div>

    <div class="main-content">
        <!-- Hero Header -->
        <div class="hero-header">
            <h1 class="hero-title">🎁 Your Rewards Vault</h1>
            <p class="hero-subtitle">
                Click on the gift boxes to unlock your exclusive rewards and benefits!
            </p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-value"><?php echo $stats['total']; ?></div>
                    <div class="hero-stat-label">Total Rewards</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-value"><?php echo $stats['locked']; ?></div>
                    <div class="hero-stat-label">Ready to Open</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-value"><?php echo $stats['claimed']; ?></div>
                    <div class="hero-stat-label">Claimed</div>
                </div>
            </div>
        </div>

        <!-- Rewards Grid -->
        <div class="rewards-grid">
            <?php foreach ($rewards as $reward): ?>
            <div class="gift-box <?php echo $reward['status']; ?>" 
                 data-reward='<?php echo htmlspecialchars(json_encode($reward)); ?>'
                 onclick="<?php echo $reward['status'] === 'locked' ? 'openBox(this)' : ($reward['status'] === 'unlocked' ? 'viewReward(this)' : ''); ?>">
                <div class="box-inner <?php echo $reward['status']; ?>">
                    <?php if ($reward['status'] !== 'empty'): ?>
                        <!-- Badge -->
                        <div class="box-badge badge-<?php echo $reward['status']; ?> <?php echo !empty($reward['priority']) ? 'badge-priority' : ''; ?>">
                            <?php 
                            if ($reward['status'] === 'locked') echo '🔒 Locked';
                            elseif ($reward['status'] === 'unlocked') echo '✓ Unlocked';
                            elseif ($reward['status'] === 'claimed') echo 'Claimed';
                            ?>
                        </div>

                        <!-- Icon -->
                        <div class="box-icon"><?php echo $reward['icon'] ?? '🎁'; ?></div>

                        <!-- Title -->
                        <div class="box-title"><?php echo htmlspecialchars($reward['title']); ?></div>
                        
                        <?php if (!empty($reward['subtitle'])): ?>
                        <div class="box-subtitle"><?php echo htmlspecialchars($reward['subtitle']); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($reward['value']) && $reward['status'] !== 'claimed'): ?>
                        <div class="box-value"><?php echo htmlspecialchars($reward['value']); ?></div>
                        <?php endif; ?>

                        <!-- Lock Overlay for Locked -->
                        <?php if ($reward['status'] === 'locked'): ?>
                        <div class="lock-overlay">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="click-hint">
                            <i class="fas fa-hand-pointer"></i> Click to Open
                        </div>
                        <?php endif; ?>

                        <!-- View Button for Unlocked -->
                        <?php if ($reward['status'] === 'unlocked'): ?>
                        <div class="click-hint" style="background:var(--green);">
                            <i class="fas fa-eye"></i> View Details
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Empty Slot -->
                        <div class="box-icon">📦</div>
                        <div class="box-title">Empty Slot</div>
                        <div class="box-subtitle">Keep performing well!</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Reward Detail Modal -->
<div class="reward-modal" id="rewardModal">
    <div class="reward-card" id="rewardCard">
        <!-- Content populated by JavaScript -->
    </div>
</div>

<script>
// Open Box Animation
function openBox(element) {
    const reward = JSON.parse(element.dataset.reward);
    if (!reward || reward.status !== 'locked') return;

    // Add opening animation
    element.classList.add('opening');

    // Confetti
    createConfetti();

    // Unlock reward via AJAX
    setTimeout(() => {
        fetch('earnings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `unlock_reward=1&reward_id=${reward.id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                reward.status = 'unlocked';
                reward.code = data.code;
                showRewardModal(reward);
                
                // Update box
                setTimeout(() => {
                    location.reload();
                }, 3000);
            }
        });
    }, 500);
}

// View Already Unlocked Reward
function viewReward(element) {
    const reward = JSON.parse(element.dataset.reward);
    if (!reward || reward.status !== 'unlocked') return;
    showRewardModal(reward);
}

// Show Reward Modal
function showRewardModal(reward) {
    const modal = document.getElementById('rewardModal');
    const card = document.getElementById('rewardCard');

    const colorMap = {
        'orange': 'var(--o5)',
        'purple': 'var(--purple)',
        'blue': 'var(--blue)',
        'green': 'var(--green)',
        'pink': 'var(--pink)'
    };
    const color = colorMap[reward.color] || 'var(--o5)';

    card.innerHTML = `
        <div class="reward-card-header">
            <div class="reward-card-icon">${reward.icon || '🎁'}</div>
            <div class="reward-card-title">${reward.title}</div>
            ${reward.subtitle ? `<div class="reward-card-subtitle">${reward.subtitle}</div>` : ''}
        </div>
        <div class="reward-card-body">
            ${reward.value ? `
            <div class="reward-info-item">
                <div class="reward-info-label"><i class="fas fa-clock"></i> Validity</div>
                <div class="reward-info-text">${reward.value}</div>
            </div>
            ` : ''}
            
            ${reward.awarded_for ? `
            <div class="reward-info-item">
                <div class="reward-info-label"><i class="fas fa-award"></i> Earned For</div>
                <div class="reward-info-text">${reward.awarded_for}</div>
            </div>
            ` : ''}
            
            ${reward.code ? `
            <div class="reward-code">
                <div class="reward-code-label"><i class="fas fa-ticket-alt"></i> Redemption Code</div>
                <div class="reward-code-value">
                    <span>${reward.code}</span>
                    <button class="copy-code-btn" onclick="copyCode('${reward.code}')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
            ` : ''}
            
            ${reward.instructions ? `
            <div class="reward-info-item" style="border-left-color:${color};">
                <div class="reward-info-label" style="color:${color};"><i class="fas fa-info-circle"></i> How to Redeem</div>
                <div class="reward-info-text">${reward.instructions}</div>
            </div>
            ` : ''}
        </div>
        <div class="reward-card-footer">
            ${reward.status === 'unlocked' ? `
                <button class="btn btn-primary" onclick="claimReward(${reward.id})">
                    <i class="fas fa-check"></i> Mark as Claimed
                </button>
            ` : ''}
            <button class="btn btn-secondary" onclick="closeModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    `;

    modal.classList.add('open');
}

// Close Modal
function closeModal() {
    document.getElementById('rewardModal').classList.remove('open');
}

// Claim Reward
function claimReward(rewardId) {
    fetch('earnings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `claim_reward=1&reward_id=${rewardId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeModal();
            setTimeout(() => location.reload(), 500);
        }
    });
}

// Copy Code
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        alert('✓ Code copied to clipboard!');
    });
}

// Confetti Effect
function createConfetti() {
    const colors = ['#f97316', '#a855f7', '#3b82f6', '#10b981', '#ec4899'];
    for (let i = 0; i < 50; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.animationDelay = Math.random() * 0.5 + 's';
        confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
        document.body.appendChild(confetti);
        setTimeout(() => confetti.remove(), 3000);
    }
}

// Close modal on outside click
document.getElementById('rewardModal').addEventListener('click', (e) => {
    if (e.target.id === 'rewardModal') closeModal();
});

// Notification sound (optional - requires audio file)
// const celebrationSound = new Audio('/path/to/celebration.mp3');
// function playSound() { celebrationSound.play(); }
</script>

</body>
</html>