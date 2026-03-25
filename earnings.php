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
        $code = $reward['code'] ?: 'PADAK-' . strtoupper(bin2hex(random_bytes(4)));
        $codeEsc = $db->real_escape_string($code);
        $db->query("UPDATE student_rewards SET status='unlocked', unlocked_at=NOW(), code='$codeEsc' WHERE id=$rewardId");
        $db->query("UPDATE student_notifications SET is_read=1 WHERE student_id=$sid AND message LIKE '%reward%' AND is_read=0 ORDER BY created_at DESC LIMIT 1");
        echo json_encode(['success' => true, 'reward' => $reward, 'code' => $code]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid reward']);
    }
    exit;
}

// Handle Claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_reward'])) {
    $rewardId = (int)$_POST['reward_id'];
    $db->query("UPDATE student_rewards SET status='claimed', claimed_at=NOW() WHERE id=$rewardId AND student_id=$sid AND status='unlocked'");
    echo json_encode(['success' => true]);
    exit;
}

// Get real rewards for this student
$rewardsQuery = $db->query("
    SELECT * FROM student_rewards 
    WHERE student_id=$sid
    ORDER BY position ASC, awarded_at DESC
");
$realRewards = [];
if ($rewardsQuery) {
    while ($row = $rewardsQuery->fetch_assoc()) {
        $realRewards[] = $row;
    }
}

// Build exactly 6 slots: fill with real rewards first, rest are empty
$slots = [];
for ($i = 0; $i < 6; $i++) {
    if (isset($realRewards[$i])) {
        $slots[] = $realRewards[$i];
    } else {
        $slots[] = ['id' => 0, 'status' => 'empty'];
    }
}

$stats = [
    'total'    => count($realRewards),
    'locked'   => count(array_filter($realRewards, fn($r) => $r['status'] === 'locked')),
    'unlocked' => count(array_filter($realRewards, fn($r) => $r['status'] === 'unlocked')),
    'claimed'  => count(array_filter($realRewards, fn($r) => $r['status'] === 'claimed')),
];

$activePage = 'earnings';
$initials = strtoupper(substr($student['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Rewards – Padak</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --bg:#0a0a0f;
    --card:#13131a;
    --card2:#1c1c26;
    --text:#f0f0ff;--text2:#8888aa;--text3:#44445a;
    --border:rgba(255,255,255,0.07);
    --purple:#a855f7;--blue:#3b82f6;--green:#10b981;--pink:#ec4899;--yellow:#eab308;
    --glow-o:0 0 40px rgba(249,115,22,0.35);
}
body{
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    overflow-x:hidden;
}

/* ── Subtle grid bg ── */
body::before{
    content:'';
    position:fixed;inset:0;
    background-image:
        linear-gradient(rgba(249,115,22,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(249,115,22,0.03) 1px, transparent 1px);
    background-size:60px 60px;
    pointer-events:none;
    z-index:0;
}

.page-wrap{
    margin-left:var(--sbw);
    min-height:100vh;
    position:relative;
    z-index:1;
}

/* ── Topbar ── */
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(10,10,15,0.92);
    backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    padding:14px 32px;
    display:flex;align-items:center;gap:16px;
}
.topbar-hamburger{
    display:none;background:none;border:none;
    cursor:pointer;color:var(--text2);padding:8px;border-radius:8px;transition:all .2s;
}
.topbar-hamburger:hover{background:var(--card);color:var(--text);}
.topbar-title{
    font-family:'Syne',sans-serif;
    font-size:1.1rem;font-weight:800;color:var(--text);flex:1;
    display:flex;align-items:center;gap:12px;
}
.topbar-title .tb-icon{
    width:34px;height:34px;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:.9rem;
    box-shadow:var(--glow-o);
}
.topbar-avatar{
    width:38px;height:38px;border-radius:50%;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    display:flex;align-items:center;justify-content:center;
    font-size:.85rem;font-weight:700;color:#fff;
    text-decoration:none;
    border:2px solid rgba(249,115,22,0.4);
    transition:transform .2s;
}
.topbar-avatar:hover{transform:scale(1.06);}

/* ── Main ── */
.main-content{
    padding:48px 40px;
    max-width:1100px;
    margin:0 auto;
}

/* ── Hero ── */
.hero{
    text-align:center;
    margin-bottom:56px;
}
.hero-eyebrow{
    display:inline-block;
    font-size:.72rem;
    font-weight:600;
    letter-spacing:.18em;
    text-transform:uppercase;
    color:var(--o5);
    background:rgba(249,115,22,0.1);
    border:1px solid rgba(249,115,22,0.25);
    padding:5px 14px;
    border-radius:20px;
    margin-bottom:20px;
}
.hero-title{
    font-family:'Syne',sans-serif;
    font-size:3rem;
    font-weight:900;
    line-height:1.05;
    margin-bottom:14px;
    background:linear-gradient(135deg, #fff 30%, var(--o4) 70%, var(--purple) 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}
.hero-sub{
    font-size:1rem;
    color:var(--text2);
    max-width:480px;
    margin:0 auto 32px;
    line-height:1.7;
}
.hero-stats{
    display:inline-flex;
    gap:0;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
}
.hstat{
    padding:16px 28px;
    border-right:1px solid var(--border);
    text-align:center;
}
.hstat:last-child{border-right:none;}
.hstat-v{
    font-family:'Syne',sans-serif;
    font-size:1.8rem;
    font-weight:800;
    color:var(--o5);
}
.hstat-l{
    font-size:.7rem;
    color:var(--text3);
    text-transform:uppercase;
    letter-spacing:.1em;
    margin-top:3px;
}

/* ── Boxes Grid ── */
.boxes-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:24px;
    margin-bottom:60px;
}

/* ── Individual Box ── */
.reward-slot{
    aspect-ratio:1;
    position:relative;
    cursor:default;
}

/* Empty slot */
.reward-slot.empty .box-shell{
    background:var(--card);
    border:2px dashed rgba(255,255,255,0.06);
    border-radius:20px;
    width:100%;height:100%;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:12px;
    opacity:.35;
}
.empty-icon{font-size:2.5rem;filter:grayscale(1);}
.empty-label{font-size:.75rem;color:var(--text3);letter-spacing:.06em;}

/* Locked reward box */
.reward-slot.locked .box-shell,
.reward-slot.unlocked .box-shell,
.reward-slot.claimed .box-shell{
    width:100%;height:100%;
    border-radius:20px;
    position:relative;
    overflow:hidden;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:20px;
    transition:transform .3s ease, box-shadow .3s ease;
}

/* Locked = sealed box look */
.reward-slot.locked .box-shell{
    background:linear-gradient(145deg, #1a1a28, #0f0f1a);
    border:2px solid rgba(249,115,22,0.3);
    cursor:pointer;
}
.reward-slot.locked .box-shell:hover{
    transform:translateY(-6px) scale(1.02);
    border-color:var(--o5);
    box-shadow:0 20px 60px rgba(249,115,22,0.3), 0 0 0 1px rgba(249,115,22,0.2);
}

/* Pulse glow for locked boxes with a reward waiting */
.reward-slot.locked .box-shell::before{
    content:'';
    position:absolute;inset:-2px;
    border-radius:22px;
    background:linear-gradient(135deg,var(--o5),var(--purple),var(--o4),var(--purple));
    background-size:300% 300%;
    animation:gradient-spin 3s linear infinite;
    z-index:-1;
    opacity:.6;
}
@keyframes gradient-spin{
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}
.reward-slot.locked .box-shell::after{
    content:'';
    position:absolute;inset:2px;
    border-radius:18px;
    background:linear-gradient(145deg,#1a1a28,#0f0f1a);
    z-index:0;
}

/* Unlocked */
.reward-slot.unlocked .box-shell{
    background:linear-gradient(145deg,rgba(16,185,129,0.12),rgba(16,185,129,0.04));
    border:2px solid rgba(16,185,129,0.4);
    cursor:pointer;
}
.reward-slot.unlocked .box-shell:hover{
    transform:translateY(-4px);
    box-shadow:0 12px 40px rgba(16,185,129,0.25);
}

/* Claimed */
.reward-slot.claimed .box-shell{
    background:var(--card);
    border:2px solid var(--border);
    opacity:.5;
    cursor:default;
    filter:grayscale(.6);
}

/* Box inner content (z-index above ::after) */
.box-content{
    position:relative;
    z-index:1;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    width:100%;height:100%;
    gap:10px;
}

/* The gift icon on a locked box */
.box-gift-wrap{
    position:relative;
    width:80px;height:80px;
    margin-bottom:4px;
}
.box-gift-body{
    width:100%;height:60%;
    position:absolute;bottom:0;
    background:linear-gradient(135deg,var(--o5),var(--o6));
    border-radius:8px;
}
.box-gift-lid{
    width:110%;height:35%;
    position:absolute;top:0;left:-5%;
    background:linear-gradient(135deg,var(--o4),var(--o5));
    border-radius:6px;
}
.box-gift-ribbon-h{
    position:absolute;top:0;bottom:0;left:50%;
    width:14%;margin-left:-7%;
    background:rgba(255,255,255,0.25);
}
.box-gift-ribbon-v{
    position:absolute;left:0;right:0;top:35%;
    height:14%;
    background:rgba(255,255,255,0.25);
}
.box-gift-bow{
    position:absolute;top:-10px;left:50%;transform:translateX(-50%);
    font-size:1.8rem;
    animation:bow-bounce 2s ease-in-out infinite;
}
@keyframes bow-bounce{
    0%,100%{transform:translateX(-50%) scale(1);}
    50%{transform:translateX(-50%) scale(1.15) rotate(5deg);}
}

/* Locked box label */
.box-title-text{
    font-family:'Syne',sans-serif;
    font-size:.95rem;
    font-weight:800;
    color:var(--text);
    text-align:center;
    line-height:1.2;
}
.box-sub-text{
    font-size:.72rem;
    color:var(--text2);
    text-align:center;
}

/* Click me badge */
.click-me{
    position:absolute;
    bottom:14px;
    left:50%;transform:translateX(-50%);
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    font-size:.7rem;
    font-weight:700;
    letter-spacing:.06em;
    padding:6px 16px;
    border-radius:20px;
    box-shadow:0 4px 20px rgba(249,115,22,0.5);
    white-space:nowrap;
    animation:click-pulse 1.5s ease-in-out infinite;
    z-index:2;
}
@keyframes click-pulse{
    0%,100%{transform:translateX(-50%) scale(1);}
    50%{transform:translateX(-50%) scale(1.07);}
}

/* Unlocked icon */
.unlocked-icon{
    font-size:3.5rem;
    animation:wiggle 3s ease-in-out infinite;
}
@keyframes wiggle{
    0%,100%{transform:rotate(0);}
    20%{transform:rotate(-8deg);}
    40%{transform:rotate(8deg);}
    60%{transform:rotate(-4deg);}
    80%{transform:rotate(4deg);}
}

/* Status badge */
.slot-badge{
    position:absolute;
    top:12px;right:12px;
    padding:4px 10px;
    border-radius:20px;
    font-size:.65rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.06em;
    z-index:2;
}
.badge-unlocked{background:rgba(16,185,129,0.2);color:#10b981;border:1px solid rgba(16,185,129,0.3);}
.badge-claimed{background:rgba(96,96,96,0.2);color:var(--text3);}

/* ── BLAST OVERLAY ── */
.blast-overlay{
    display:none;
    position:fixed;inset:0;
    z-index:8000;
    pointer-events:none;
}
.blast-overlay.active{display:block;}

/* ── REWARD MODAL ── */
.reward-modal{
    display:none;
    position:fixed;inset:0;
    background:rgba(0,0,0,0.88);
    backdrop-filter:blur(12px);
    z-index:9000;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.reward-modal.open{display:flex;}

.reward-card{
    background:var(--card2);
    border-radius:24px;
    max-width:460px;width:100%;
    border:1px solid rgba(249,115,22,0.4);
    box-shadow:0 0 80px rgba(249,115,22,0.4), 0 0 160px rgba(168,85,247,0.2);
    animation:modal-pop .5s cubic-bezier(.34,1.56,.64,1);
    overflow:hidden;
    position:relative;
}
@keyframes modal-pop{
    from{transform:scale(.7) translateY(60px);opacity:0;}
    to{transform:scale(1) translateY(0);opacity:1;}
}

.rc-glow-bar{
    height:4px;
    background:linear-gradient(90deg,var(--o5),var(--purple),var(--o4));
    background-size:200% 100%;
    animation:bar-move 2s linear infinite;
}
@keyframes bar-move{
    0%{background-position:0% 0%;}
    100%{background-position:200% 0%;}
}

.rc-header{
    padding:32px 28px 24px;
    text-align:center;
    background:linear-gradient(145deg,rgba(249,115,22,0.08),rgba(168,85,247,0.06));
    border-bottom:1px solid var(--border);
}
.rc-icon{
    font-size:5rem;
    margin-bottom:12px;
    display:block;
    animation:icon-celebrate 1s cubic-bezier(.34,1.56,.64,1);
}
@keyframes icon-celebrate{
    0%{transform:scale(0) rotate(-180deg);}
    70%{transform:scale(1.2) rotate(20deg);}
    100%{transform:scale(1) rotate(0);}
}
.rc-title{
    font-family:'Syne',sans-serif;
    font-size:1.7rem;
    font-weight:900;
    color:var(--text);
    margin-bottom:6px;
}
.rc-sub{
    font-size:.9rem;
    color:var(--text2);
}

.rc-body{padding:24px 28px;}

.rc-info{
    padding:14px 16px;
    background:rgba(249,115,22,0.06);
    border-left:3px solid var(--o5);
    border-radius:0 10px 10px 0;
    margin-bottom:14px;
}
.rc-info-label{
    font-size:.65rem;font-weight:700;color:var(--o5);
    text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;
}
.rc-info-val{font-size:.9rem;color:var(--text);line-height:1.6;}

.rc-code-box{
    padding:18px;
    background:rgba(168,85,247,0.08);
    border:1.5px dashed rgba(168,85,247,0.4);
    border-radius:14px;
    margin-bottom:20px;
}
.rc-code-label{
    font-size:.65rem;font-weight:700;color:var(--purple);
    text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px;
}
.rc-code-row{
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;
}
.rc-code-val{
    flex:1;
    font-family:'Courier New',monospace;
    font-size:1.3rem;
    font-weight:800;
    letter-spacing:.12em;
    color:var(--text);
}
.btn-copy{
    padding:9px 18px;
    background:var(--purple);
    color:#fff;border:none;border-radius:8px;
    font-size:.8rem;font-weight:700;
    cursor:pointer;transition:all .2s;white-space:nowrap;
}
.btn-copy:hover{background:#9333ea;transform:translateY(-1px);}

.rc-footer{
    padding:20px 28px;
    border-top:1px solid var(--border);
    display:flex;gap:12px;
}
.btn{
    flex:1;padding:13px 20px;border-radius:10px;
    font-size:.88rem;font-weight:700;font-family:inherit;
    cursor:pointer;border:none;transition:all .2s;
    display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-primary{
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;box-shadow:0 4px 20px rgba(249,115,22,0.35);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 30px rgba(249,115,22,0.5);}
.btn-secondary{
    background:var(--card);border:1.5px solid var(--border);color:var(--text2);
}
.btn-secondary:hover{border-color:var(--o5);color:var(--o5);}

/* ── Particles ── */
.particle{
    position:fixed;
    width:10px;height:10px;
    border-radius:50%;
    pointer-events:none;
    z-index:8500;
    animation:particle-fly var(--dur, 1.5s) ease-out forwards;
}
@keyframes particle-fly{
    0%{transform:translate(0,0) scale(1);opacity:1;}
    100%{transform:translate(var(--tx,0),var(--ty,0)) scale(0);opacity:0;}
}

/* ── Responsive ── */
@media(max-width:900px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .main-content{padding:32px 20px;}
    .hero-title{font-size:2.2rem;}
    .boxes-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:520px){
    .boxes-grid{grid-template-columns:repeat(2,1fr);gap:14px;}
    .hero-stats{flex-direction:column;width:100%;}
    .hstat{border-right:none;border-bottom:1px solid var(--border);}
    .hstat:last-child{border-bottom:none;}
    .reward-card{max-width:100%;}
    .rc-code-row{flex-direction:column;}
    .btn-copy{width:100%;}
    .rc-footer{flex-direction:column;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <div class="tb-icon"><i class="fas fa-gift"></i></div>
            My Rewards
        </div>
        <a href="profile.php" class="topbar-avatar" title="<?php echo htmlspecialchars($student['full_name']); ?>">
            <?php echo $initials; ?>
        </a>
    </div>

    <div class="main-content">

        <!-- Hero -->
        <div class="hero">
            <div class="hero-eyebrow">🎁 Rewards Vault</div>
            <h1 class="hero-title">Your Exclusive Perks</h1>
            <p class="hero-sub">Earn rewards by completing tasks and leveling up. Click any glowing box to reveal what's inside!</p>
            <div class="hero-stats">
                <div class="hstat">
                    <div class="hstat-v"><?php echo $stats['total']; ?></div>
                    <div class="hstat-l">Earned</div>
                </div>
                <div class="hstat">
                    <div class="hstat-v"><?php echo $stats['locked']; ?></div>
                    <div class="hstat-l">Unopened</div>
                </div>
                <div class="hstat">
                    <div class="hstat-v"><?php echo $stats['claimed']; ?></div>
                    <div class="hstat-l">Claimed</div>
                </div>
            </div>
        </div>

        <!-- 6 Boxes -->
        <div class="boxes-grid">
            <?php foreach ($slots as $slot): ?>
            <?php
            $st = $slot['status'];
            $hasReward = $slot['id'] > 0;
            $dataAttr = $hasReward ? "data-reward='" . htmlspecialchars(json_encode($slot), ENT_QUOTES) . "'" : '';
            $onclick = '';
            if ($st === 'locked')    $onclick = "onclick='openBox(this)'";
            if ($st === 'unlocked')  $onclick = "onclick='viewReward(this)'";
            ?>
            <div class="reward-slot <?php echo $st; ?>" <?php echo $dataAttr; ?> <?php echo $onclick; ?>>
                <?php if ($st === 'empty'): ?>
                    <!-- Empty placeholder -->
                    <div class="box-shell">
                        <div class="empty-icon">📦</div>
                        <div class="empty-label">Empty Slot</div>
                    </div>

                <?php elseif ($st === 'locked'): ?>
                    <!-- Locked reward: glow border + gift box visual -->
                    <div class="box-shell">
                        <div class="box-content">
                            <div class="box-gift-wrap">
                                <div class="box-gift-body"></div>
                                <div class="box-gift-lid"></div>
                                <div class="box-gift-ribbon-h"></div>
                                <div class="box-gift-ribbon-v"></div>
                                <div class="box-gift-bow">🎀</div>
                            </div>
                            <div class="box-title-text">Mystery Reward</div>
                            <div class="box-sub-text">A surprise awaits you!</div>
                        </div>
                        <div class="click-me"><i class="fas fa-hand-pointer"></i> &nbsp;OPEN ME</div>
                    </div>

                <?php elseif ($st === 'unlocked'): ?>
                    <!-- Unlocked: show icon, title -->
                    <div class="box-shell">
                        <div class="slot-badge badge-unlocked">✓ Unlocked</div>
                        <div class="box-content">
                            <div class="unlocked-icon"><?php echo $slot['icon'] ?? '🎁'; ?></div>
                            <div class="box-title-text"><?php echo htmlspecialchars($slot['title']); ?></div>
                            <div class="box-sub-text"><?php echo htmlspecialchars($slot['subtitle'] ?? ''); ?></div>
                        </div>
                        <div class="click-me" style="background:var(--green);box-shadow:0 4px 20px rgba(16,185,129,0.5);">
                            <i class="fas fa-eye"></i> &nbsp;VIEW
                        </div>
                    </div>

                <?php elseif ($st === 'claimed'): ?>
                    <div class="box-shell">
                        <div class="slot-badge badge-claimed">Claimed</div>
                        <div class="box-content">
                            <div style="font-size:3rem;filter:grayscale(1) brightness(.5);"><?php echo $slot['icon'] ?? '🎁'; ?></div>
                            <div class="box-title-text" style="color:var(--text3);"><?php echo htmlspecialchars($slot['title']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div><!-- /main-content -->
</div><!-- /page-wrap -->

<!-- Blast overlay (particles rendered here) -->
<div class="blast-overlay" id="blastOverlay"></div>

<!-- Reward Modal -->
<div class="reward-modal" id="rewardModal">
    <div class="reward-card" id="rewardCard"></div>
</div>

<script>
/* ─── Open Locked Box ─── */
function openBox(el) {
    const reward = JSON.parse(el.dataset.reward);
    if (!reward || reward.status !== 'locked') return;

    el.style.pointerEvents = 'none';
    el.querySelector('.box-shell').style.animation = 'none';

    // 1. Blast particles from center of the box
    blast(el);

    // 2. After 600ms, AJAX unlock → show modal
    setTimeout(() => {
        fetch('earnings.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`unlock_reward=1&reward_id=${reward.id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                reward.status  = 'unlocked';
                reward.code    = data.code;
                showModal(reward);
                // Reload after modal close to reflect new status
            }
        });
    }, 600);
}

/* ─── View Already Unlocked ─── */
function viewReward(el) {
    const reward = JSON.parse(el.dataset.reward);
    if (!reward || reward.status !== 'unlocked') return;
    showModal(reward);
}

/* ─── Show Reward Modal ─── */
function showModal(r) {
    const modal = document.getElementById('rewardModal');
    const card  = document.getElementById('rewardCard');

    card.innerHTML = `
        <div class="rc-glow-bar"></div>
        <div class="rc-header">
            <span class="rc-icon">${r.icon || '🎁'}</span>
            <div class="rc-title">${escHtml(r.title)}</div>
            ${r.subtitle ? `<div class="rc-sub">${escHtml(r.subtitle)}</div>` : ''}
        </div>
        <div class="rc-body">
            ${r.awarded_for ? `
            <div class="rc-info">
                <div class="rc-info-label"><i class="fas fa-award"></i> Earned For</div>
                <div class="rc-info-val">${escHtml(r.awarded_for)}</div>
            </div>` : ''}
            ${r.value ? `
            <div class="rc-info">
                <div class="rc-info-label"><i class="fas fa-clock"></i> Validity</div>
                <div class="rc-info-val">${escHtml(r.value)}</div>
            </div>` : ''}
            ${r.instructions ? `
            <div class="rc-info" style="border-left-color:var(--purple);">
                <div class="rc-info-label" style="color:var(--purple);"><i class="fas fa-info-circle"></i> How to Redeem</div>
                <div class="rc-info-val">${escHtml(r.instructions)}</div>
            </div>` : ''}
            ${r.code ? `
            <div class="rc-code-box">
                <div class="rc-code-label"><i class="fas fa-ticket-alt"></i> Redemption Code</div>
                <div class="rc-code-row">
                    <span class="rc-code-val">${escHtml(r.code)}</span>
                    <button class="btn-copy" onclick="copyCode('${escHtml(r.code)}')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>` : ''}
        </div>
        <div class="rc-footer">
            ${r.status === 'unlocked' ? `
            <button class="btn btn-primary" onclick="claimReward(${r.id})">
                <i class="fas fa-check"></i> Mark as Claimed
            </button>` : ''}
            <button class="btn btn-secondary" onclick="closeModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    `;

    modal.classList.add('open');

    // Celebration confetti on first open
    if (r.status === 'unlocked' || r._justOpened) {
        setTimeout(() => celebrationBurst(), 300);
    }
}

/* ─── Claim ─── */
function claimReward(id) {
    fetch('earnings.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`claim_reward=1&reward_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { closeModal(); setTimeout(() => location.reload(), 400); }
    });
}

/* ─── Close Modal ─── */
function closeModal() {
    document.getElementById('rewardModal').classList.remove('open');
    setTimeout(() => location.reload(), 300);
}

/* ─── Copy Code ─── */
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.querySelector('.btn-copy');
        if (btn) { btn.textContent = '✓ Copied!'; setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i> Copy', 2000); }
    });
}

/* ─── BLAST animation from box ─── */
function blast(el) {
    const rect = el.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const colors = ['#f97316','#fb923c','#a855f7','#3b82f6','#10b981','#ec4899','#eab308','#ffffff'];

    for (let i = 0; i < 60; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const angle = Math.random() * Math.PI * 2;
        const dist  = 120 + Math.random() * 280;
        const tx = Math.cos(angle) * dist;
        const ty = Math.sin(angle) * dist - (Math.random() * 100);
        const size = 6 + Math.random() * 12;
        const dur  = .8 + Math.random() * 1.2;
        const shapes = ['50%','4px','0'];
        const shape = shapes[Math.floor(Math.random() * shapes.length)];
        Object.assign(p.style, {
            left: cx + 'px',
            top:  cy + 'px',
            width: size + 'px',
            height: size + 'px',
            borderRadius: shape,
            background: colors[Math.floor(Math.random() * colors.length)],
            '--tx': tx + 'px',
            '--ty': ty + 'px',
            '--dur': dur + 's',
            animationDelay: (Math.random() * .15) + 's',
        });
        document.body.appendChild(p);
        setTimeout(() => p.remove(), (dur + .2) * 1000);
    }
}

/* ─── Celebration confetti from top ─── */
function celebrationBurst() {
    const colors = ['#f97316','#a855f7','#3b82f6','#10b981','#ec4899','#eab308'];
    for (let i = 0; i < 80; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const tx = (Math.random() - .5) * window.innerWidth;
        const ty = window.innerHeight * .8 + Math.random() * 200;
        const size = 7 + Math.random() * 10;
        const dur  = 1.5 + Math.random() * 1.5;
        Object.assign(p.style, {
            left: (Math.random() * window.innerWidth) + 'px',
            top:  '-20px',
            width: size + 'px',
            height: size + 'px',
            borderRadius: Math.random() > .5 ? '50%' : '2px',
            background: colors[Math.floor(Math.random() * colors.length)],
            '--tx': tx + 'px',
            '--ty': ty + 'px',
            '--dur': dur + 's',
            animationDelay: (i * .02) + 's',
        });
        document.body.appendChild(p);
        setTimeout(() => p.remove(), (dur + .5) * 1000);
    }
}

/* ─── Close on outside click ─── */
document.getElementById('rewardModal').addEventListener('click', e => {
    if (e.target.id === 'rewardModal') closeModal();
});

/* ─── HTML escape ─── */
function escHtml(s) {
    if (!s) return '';
    return String(s)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}
</script>
</body>
</html>