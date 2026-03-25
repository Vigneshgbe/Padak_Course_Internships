<?php
// earnings.php — Gamified Rewards System (Redesigned v2 - FIXED FONT)
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

// Ensure DB supports new statuses (safe to run every time)
@$db->query("ALTER TABLE student_rewards MODIFY COLUMN status ENUM('locked','unlocked','activate_requested','activated','claimed') NOT NULL DEFAULT 'locked'");

// ── POST HANDLERS (JSON responses) ─────────────────────────────────────

// Open box
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_box'])) {
    $rewardId = (int)$_POST['reward_id'];
    $check = $db->query("SELECT * FROM student_rewards WHERE id=$rewardId AND student_id=$sid AND status='locked'");
    if ($check && $check->num_rows > 0) {
        $reward = $check->fetch_assoc();
        $code = $reward['code'] ?: 'PADAK-' . strtoupper(bin2hex(random_bytes(4)));
        $codeEsc = $db->real_escape_string($code);
        $db->query("UPDATE student_rewards SET status='unlocked', unlocked_at=NOW(), code='$codeEsc' WHERE id=$rewardId");
        echo json_encode(['success' => true, 'reward' => array_merge($reward, ['code' => $code, 'status' => 'unlocked'])]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Request activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_activate'])) {
    $rewardId = (int)$_POST['reward_id'];
    $check = $db->query("SELECT * FROM student_rewards WHERE id=$rewardId AND student_id=$sid AND status='unlocked'");
    if ($check && $check->num_rows > 0) {
        $reward = $check->fetch_assoc();
        $db->query("UPDATE student_rewards SET status='activate_requested' WHERE id=$rewardId");
        $rewardTitle = $db->real_escape_string($reward['title']);
        $studentName = $db->real_escape_string($student['full_name']);
        // Notify the student that request was sent
        $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                   VALUES ($sid, 'Activation Requested', 'Your activation request for &quot;$rewardTitle&quot; has been sent to the admin.', 'system', NOW())");
        // Store request in rewards table (status already updated above)
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Claim reward
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_reward'])) {
    $rewardId = (int)$_POST['reward_id'];
    $updated = $db->query("UPDATE student_rewards SET status='claimed', claimed_at=NOW() WHERE id=$rewardId AND student_id=$sid AND status='activated'");
    echo json_encode(['success' => (bool)$updated && $db->affected_rows > 0]);
    exit;
}

// ── FETCH DATA ──────────────────────────────────────────────────────────

$rewardsQuery = $db->query("SELECT * FROM student_rewards WHERE student_id=$sid ORDER BY position ASC, awarded_at DESC");
$realRewards = [];
if ($rewardsQuery) {
    while ($row = $rewardsQuery->fetch_assoc()) $realRewards[] = $row;
}

// 6 box slots always
$slots = [];
for ($i = 0; $i < 6; $i++) $slots[$i] = $realRewards[$i] ?? null;

// Stats
$totalRewards  = count($realRewards);
$pendingCount  = count(array_filter($realRewards, fn($r) => $r['status'] === 'locked'));
$activeCount   = count(array_filter($realRewards, fn($r) => in_array($r['status'], ['unlocked','activate_requested','activated'])));
$claimedCount  = count(array_filter($realRewards, fn($r) => $r['status'] === 'claimed'));
$mentorCount   = count(array_filter($realRewards, fn($r) => $r['reward_type'] === 'mentorship'));

// Has new rewards (locked)?
$hasNew = $pendingCount > 0;

$activePage = 'earnings';
$initials   = strtoupper(substr($student['full_name'], 0, 1));

// Filter for reward cards list
$filter          = $_GET['filter'] ?? 'all';
$displayRewards  = match($filter) {
    'pending'  => array_filter($realRewards, fn($r) => $r['status'] === 'locked'),
    'active'   => array_filter($realRewards, fn($r) => in_array($r['status'], ['unlocked','activate_requested','activated'])),
    'redeemed' => array_filter($realRewards, fn($r) => $r['status'] === 'claimed'),
    'expired'  => array_filter($realRewards, fn($r) => !empty($r['expires_at']) && strtotime($r['expires_at']) < time()),
    default    => $realRewards,
};

// Type meta for badge colors / labels
function rewardTypeMeta(string $type): array {
    return match($type) {
        'mentorship' => ['label' => 'Mentorship',      'cls' => 'type-mentorship', 'icon' => '👥'],
        'software'   => ['label' => 'Software Access', 'cls' => 'type-software',   'icon' => '💻'],
        'resource'   => ['label' => 'Learning',        'cls' => 'type-resource',   'icon' => '📚'],
        'perk'       => ['label' => 'Exclusive Perk',  'cls' => 'type-perk',       'icon' => '⭐'],
        default      => ['label' => 'Bonus',           'cls' => 'type-bonus',      'icon' => '🎁'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Earnings – Padak</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset & Variables ─────────────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw: 258px;
    --bg: #f8fafc;
    --card: #ffffff;
    --card2: #f1f5f9;
    --text: #0f172a;
    --text2: #475569;
    --text3: #94a3b8;
    --border: #e2e8f0;

    --orange: #f97316;
    --orange2: #fb923c;
    --orange3: #ea580c;
    --orange-bg: #fff7ed;
    --orange-border: #fed7aa;

    --green: #22c55e;
    --green-bg: #f0fdf4;
    --green-border: #bbf7d0;

    --blue: #3b82f6;
    --blue-bg: #eff6ff;
    --blue-border: #bfdbfe;

    --purple: #8b5cf6;
    --purple-bg: #f5f3ff;
    --purple-border: #ddd6fe;

    --amber: #f59e0b;
    --amber-bg: #fffbeb;
    --amber-border: #fde68a;

    --red: #ef4444;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.1);
}
*{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;}
body{background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;}

/* ── Layout ────────────────────────────────────────────────────────────── */
.page-wrap{margin-left:var(--sbw);min-height:100vh;display:flex;flex-direction:column;}

/* ── Topbar ────────────────────────────────────────────────────────────── */
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(248,250,252,.95);backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    padding:14px 32px;
    display:flex;align-items:center;gap:16px;
}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:8px;border-radius:8px;transition:all .2s;}
.topbar-hamburger:hover{background:var(--card2);color:var(--text);}
.topbar-title{
    font-size:1.1rem;font-weight:800;
    color:var(--text);flex:1;display:flex;align-items:center;gap:10px;
    letter-spacing:-.01em;
}
.t-icon{
    width:32px;height:32px;
    background:linear-gradient(135deg,var(--orange),var(--orange2));
    border-radius:8px;display:flex;align-items:center;justify-content:center;
    font-size:.85rem;color:#fff;
}
.topbar-avatar{
    width:36px;height:36px;border-radius:50%;
    background:linear-gradient(135deg,var(--orange),var(--orange2));
    display:flex;align-items:center;justify-content:center;
    font-size:.8rem;font-weight:700;color:#fff;
    text-decoration:none;border:2px solid var(--orange-border);
    transition:transform .2s;
}
.topbar-avatar:hover{transform:scale(1.06);}

.main-content{padding:32px 36px;flex:1;max-width:1140px;width:100%;margin:0 auto;}

/* ── Hero Banner ───────────────────────────────────────────────────────── */
.hero-banner{
    background:linear-gradient(120deg,#f97316 0%,#fb923c 60%,#f59e0b 100%);
    border-radius:20px;padding:32px 36px;margin-bottom:28px;
    display:flex;align-items:center;justify-content:space-between;
    position:relative;overflow:hidden;
}
.hero-banner::before{
    content:'';position:absolute;inset:0;
    background:url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='50' cy='50' r='40' fill='none' stroke='rgba(255,255,255,0.06)' stroke-width='1'/%3E%3C/svg%3E") repeat;
    pointer-events:none;
}
.hero-banner::after{
    content:'🎁';position:absolute;right:140px;top:-10px;
    font-size:7rem;opacity:.12;transform:rotate(15deg);
    pointer-events:none;
}
.hero-left h1{
    font-size:1.85rem;font-weight:800;
    color:#fff;margin-bottom:8px;letter-spacing:-.02em;
}
.hero-left p{color:rgba(255,255,255,.9);font-size:.95rem;max-width:420px;line-height:1.55;font-weight:500;}
.hero-badge{
    background:rgba(255,255,255,.22);backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.35);
    color:#fff;padding:10px 18px;border-radius:50px;
    font-size:.82rem;font-weight:700;white-space:nowrap;
    display:flex;align-items:center;gap:7px;flex-shrink:0;
}
.hero-badge i{color:#fde68a;}

/* ── Stats Row ─────────────────────────────────────────────────────────── */
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:32px;}
.stat-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:14px;padding:18px 16px;
    display:flex;align-items:center;gap:14px;
    box-shadow:var(--shadow-sm);transition:box-shadow .2s,transform .2s;
}
.stat-card:hover{box-shadow:var(--shadow-md);transform:translateY(-1px);}
.stat-icon{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;font-size:1.1rem;
    flex-shrink:0;
}
.si-orange{background:var(--orange-bg);color:var(--orange3);}
.si-amber{background:var(--amber-bg);color:#b45309;}
.si-green{background:var(--green-bg);color:#16a34a;}
.si-blue{background:var(--blue-bg);color:#1d4ed8;}
.si-purple{background:var(--purple-bg);color:#7c3aed;}
.stat-info{}
.stat-val{font-size:1.65rem;font-weight:900;color:var(--text);line-height:1;letter-spacing:-.02em;}
.stat-lbl{font-size:.72rem;color:var(--text3);margin-top:4px;font-weight:700;letter-spacing:.01em;}

/* ── Section Header ────────────────────────────────────────────────────── */
.section-hdr{
    display:flex;align-items:center;gap:12px;margin-bottom:20px;
}
.section-hdr h2{font-size:1.05rem;font-weight:800;color:var(--text);letter-spacing:-.01em;}
.section-hdr::after{content:'';flex:1;height:1px;background:var(--border);}
.section-hdr .hdr-badge{
    background:var(--orange-bg);color:var(--orange3);
    border:1px solid var(--orange-border);
    font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:20px;
    text-transform:uppercase;letter-spacing:.06em;
}

/* ── Gift Boxes Grid ───────────────────────────────────────────────────── */
.boxes-section{margin-bottom:40px;}
.boxes-grid{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:14px;
}

.gift-box{
    aspect-ratio:1/1.1;
    border-radius:16px;
    position:relative;overflow:hidden;
    transition:transform .25s ease,box-shadow .25s ease;
    cursor:default;
}

/* EMPTY */
.gift-box.empty{
    background:var(--card2);
    border:2px dashed #cbd5e1;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;gap:8px;
}
.gb-empty-icon{font-size:1.8rem;opacity:.25;}
.gb-empty-lbl{font-size:.62rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;font-weight:700;}

/* LOCKED — ready to open */
.gift-box.locked{
    background:#fff;
    border:2px solid var(--orange-border);
    cursor:pointer;
    box-shadow:0 0 0 0 rgba(249,115,22,0);
    animation:boxPulse 2.4s ease-in-out infinite;
}
@keyframes boxPulse{
    0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.25),var(--shadow-sm);}
    50%{box-shadow:0 0 0 8px rgba(249,115,22,0),var(--shadow-md);}
}
.gift-box.locked:hover{transform:translateY(-6px) scale(1.03);box-shadow:0 12px 32px rgba(249,115,22,.18);}
.gift-box.locked:active{transform:scale(.97);}

/* UNLOCKED */
.gift-box.unlocked{
    background:#fff;border:2px solid var(--green-border);
    cursor:pointer;box-shadow:0 0 0 4px rgba(34,197,94,.06);
}
.gift-box.unlocked:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(34,197,94,.14);}

/* ACTIVATE_REQUESTED */
.gift-box.activate_requested{
    background:#fff;border:2px solid var(--amber-border);
    box-shadow:0 0 0 4px rgba(245,158,11,.06);
}

/* ACTIVATED */
.gift-box.activated{
    background:#fff;border:2px solid var(--blue-border);
    cursor:pointer;box-shadow:0 0 0 4px rgba(59,130,246,.06);
}
.gift-box.activated:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(59,130,246,.14);}

/* CLAIMED */
.gift-box.claimed{
    background:var(--card2);border:2px solid var(--border);
    opacity:.55;filter:grayscale(.6);
}

/* Box Inner */
.gb-inner{
    width:100%;height:100%;
    padding:14px 10px 12px;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;gap:7px;
    position:relative;
}

/* Shine sweep on locked */
.gb-shine{
    position:absolute;inset:0;
    background:linear-gradient(115deg,transparent 30%,rgba(255,255,255,.55) 50%,transparent 70%);
    background-size:250% 100%;
    animation:shineSweep 2.8s ease-in-out infinite;
    pointer-events:none;border-radius:14px;
}
@keyframes shineSweep{
    0%{background-position:250% center;}
    100%{background-position:-250% center;}
}

.gb-present{
    font-size:2.6rem;display:block;
    filter:drop-shadow(0 3px 10px rgba(249,115,22,.35));
    animation:presentFloat 2.6s ease-in-out infinite;
}
.gift-box.claimed .gb-present,
.gift-box.unlocked .gb-present,
.gift-box.activate_requested .gb-present,
.gift-box.activated .gb-present{animation:none;filter:none;}

@keyframes presentFloat{
    0%,100%{transform:translateY(0) rotate(0deg);}
    30%{transform:translateY(-7px) rotate(-5deg);}
    70%{transform:translateY(-3px) rotate(4deg);}
}

.gb-title{
    font-size:.75rem;font-weight:700;color:var(--text);
    text-align:center;line-height:1.35;letter-spacing:-.01em;
}
.gb-sub{font-size:.62rem;color:var(--text3);text-align:center;font-weight:500;}

/* NEW badge */
.gb-new{
    position:absolute;top:8px;right:8px;
    background:linear-gradient(135deg,var(--orange),var(--orange2));
    color:#fff;font-size:.55rem;font-weight:800;
    padding:2px 7px;border-radius:20px;
    text-transform:uppercase;letter-spacing:.05em;
    animation:newPop 1.6s ease infinite;
}
@keyframes newPop{0%,100%{transform:scale(1);}50%{transform:scale(1.1);}}

/* Status chip */
.gb-chip{
    position:absolute;bottom:0;left:0;right:0;
    font-size:.6rem;font-weight:800;text-align:center;padding:6px;
    text-transform:uppercase;letter-spacing:.05em;border-radius:0 0 14px 14px;
}
.gb-chip.chip-open{background:linear-gradient(90deg,var(--orange3),var(--orange));color:#fff;}
.gb-chip.chip-view{background:linear-gradient(90deg,#16a34a,var(--green));color:#fff;}
.gb-chip.chip-pending{background:var(--amber-bg);color:#b45309;}
.gb-chip.chip-claim{background:var(--blue-bg);color:#1d4ed8;}
.gb-chip.chip-done{background:var(--card2);color:var(--text3);}

/* ── Filter Tabs ───────────────────────────────────────────────────────── */
.filter-tabs{
    display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;
}
.filter-tab{
    padding:8px 18px;border-radius:50px;font-size:.82rem;font-weight:700;
    border:1.5px solid var(--border);background:var(--card);color:var(--text2);
    cursor:pointer;text-decoration:none;transition:all .18s;
    display:inline-flex;align-items:center;gap:6px;
}
.filter-tab:hover{border-color:var(--orange);color:var(--orange);}
.filter-tab.active{background:var(--orange);border-color:var(--orange);color:#fff;box-shadow:0 2px 10px rgba(249,115,22,.25);}
.ft-count{
    font-size:.65rem;font-weight:800;padding:1px 6px;border-radius:10px;
    background:rgba(255,255,255,.25);
}
.filter-tab:not(.active) .ft-count{background:var(--card2);color:var(--text3);}

/* ── Reward Cards ──────────────────────────────────────────────────────── */
.rewards-section{margin-bottom:48px;}
.rewards-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
}

.reward-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:16px;overflow:hidden;
    box-shadow:var(--shadow-sm);
    transition:transform .22s,box-shadow .22s;
    position:relative;
    display:flex;flex-direction:column;
}
.reward-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);}

/* FEATURED ribbon */
.reward-card.featured::before{
    content:'FEATURED';
    position:absolute;top:14px;right:-26px;
    background:var(--orange);color:#fff;
    font-size:.55rem;font-weight:900;
    padding:4px 32px;letter-spacing:.08em;
    transform:rotate(45deg);box-shadow:0 2px 8px rgba(249,115,22,.4);
    z-index:2;
}

.rc-top{padding:20px 20px 14px;}
.rc-type-row{display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.rc-type-badge{
    display:inline-flex;align-items:center;gap:5px;
    font-size:.68rem;font-weight:800;padding:4px 10px;border-radius:20px;
    text-transform:uppercase;letter-spacing:.04em;
}
.type-mentorship{background:var(--purple-bg);color:var(--purple);border:1px solid var(--purple-border);}
.type-software{background:var(--blue-bg);color:var(--blue);border:1px solid var(--blue-border);}
.type-resource{background:var(--green-bg);color:#16a34a;border:1px solid var(--green-border);}
.type-perk{background:var(--orange-bg);color:var(--orange3);border:1px solid var(--orange-border);}
.type-bonus{background:#fdf4ff;color:#9333ea;border:1px solid #f3e8ff;}

.rc-status-dot{
    margin-left:auto;width:8px;height:8px;border-radius:50%;flex-shrink:0;
}
.dot-locked{background:#e2e8f0;}
.dot-unlocked{background:var(--green);box-shadow:0 0 6px rgba(34,197,94,.5);}
.dot-activate_requested{background:var(--amber);animation:dotBlink 1.4s ease infinite;}
.dot-activated{background:var(--blue);box-shadow:0 0 6px rgba(59,130,246,.5);}
.dot-claimed{background:#cbd5e1;}
@keyframes dotBlink{0%,100%{opacity:1;}50%{opacity:.3;}}

.rc-icon-row{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
.rc-big-icon{font-size:2rem;}
.rc-title{font-size:1.05rem;font-weight:800;color:var(--text);line-height:1.3;letter-spacing:-.01em;}
.rc-value-pill{
    display:inline-flex;align-items:center;gap:5px;
    background:var(--orange-bg);color:var(--orange3);
    border:1px solid var(--orange-border);
    font-size:.7rem;font-weight:800;padding:3px 10px;border-radius:20px;
    margin-top:8px;
}

.rc-body{padding:0 20px 14px;flex:1;}
.rc-desc{font-size:.82rem;color:var(--text2);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-weight:500;}

.rc-footer{
    padding:12px 20px;border-top:1px solid var(--border);
    display:flex;align-items:center;gap:8px;background:var(--bg);
}
.rc-code-snippet{
    flex:1;font-size:.72rem;color:var(--text3);font-family:'Courier New',monospace;font-weight:600;
    display:flex;align-items:center;gap:6px;
    background:var(--card2);padding:6px 10px;border-radius:8px;overflow:hidden;
}
.rc-code-snippet span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.btn-sm{
    padding:7px 14px;border-radius:8px;font-size:.76rem;font-weight:800;
    border:none;cursor:pointer;transition:all .18s;white-space:nowrap;
    display:inline-flex;align-items:center;gap:5px;font-family:inherit;
}
.btn-activate{background:var(--orange);color:#fff;box-shadow:0 2px 8px rgba(249,115,22,.25);}
.btn-activate:hover{background:var(--orange3);box-shadow:0 4px 14px rgba(249,115,22,.4);}
.btn-claim{background:var(--blue);color:#fff;box-shadow:0 2px 8px rgba(59,130,246,.2);}
.btn-claim:hover{background:#2563eb;box-shadow:0 4px 14px rgba(59,130,246,.35);}
.btn-view{background:var(--card2);color:var(--text2);border:1px solid var(--border);}
.btn-view:hover{border-color:var(--orange);color:var(--orange);}
.btn-copy{background:var(--card2);color:var(--text2);border:1px solid var(--border);}
.btn-copy:hover{border-color:var(--blue);color:var(--blue);}
.btn-copy.copied{background:var(--green-bg);color:#16a34a;border-color:var(--green-border);}

/* Status label */
.rc-status-label{
    font-size:.67rem;font-weight:800;padding:3px 10px;border-radius:20px;
    text-transform:uppercase;letter-spacing:.04em;
}
.sl-pending{background:#f1f5f9;color:var(--text3);}
.sl-activate_requested{background:var(--amber-bg);color:#b45309;}
.sl-activated{background:var(--blue-bg);color:#1d4ed8);}
.sl-claimed{background:var(--green-bg);color:#16a34a;}

/* Empty state */
.empty-state{
    grid-column:1/-1;text-align:center;padding:60px 20px;
    color:var(--text3);
}
.empty-state i{font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;}
.empty-state p{font-size:.9rem;font-weight:500;}

/* ── Reward Reveal Modal ────────────────────────────────────────────────── */
.rmodal{
    display:none;position:fixed;inset:0;
    background:rgba(15,23,42,.75);backdrop-filter:blur(16px);
    z-index:10000;align-items:center;justify-content:center;padding:20px;
}
.rmodal.open{display:flex;animation:mFade .28s ease;}
@keyframes mFade{from{opacity:0;}to{opacity:1;}}

.rmodal-card{
    background:var(--card);border-radius:24px;
    max-width:440px;width:100%;
    border:1px solid var(--border);
    box-shadow:0 30px 80px rgba(0,0,0,.18),0 0 0 1px rgba(0,0,0,.04);
    overflow:hidden;
    animation:cardPop .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes cardPop{
    from{transform:translateY(40px) scale(.92);opacity:0;}
    to{transform:none;opacity:1;}
}

.rmc-header{
    padding:32px 28px 22px;text-align:center;
    background:linear-gradient(160deg,#fff7ed,#fff);
    border-bottom:1px solid var(--border);position:relative;overflow:hidden;
}
.rmc-header::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 50% -10%,rgba(249,115,22,.1),transparent 60%);
}
.rmc-big-icon{
    font-size:4.5rem;display:block;margin-bottom:12px;
    animation:iconPop .6s cubic-bezier(.34,1.56,.64,1) .1s both;
}
@keyframes iconPop{
    from{transform:scale(0) rotate(-180deg);opacity:0;}
    to{transform:scale(1) rotate(0);opacity:1;}
}
.rmc-eyebrow{font-size:.62rem;font-weight:800;color:var(--orange);text-transform:uppercase;letter-spacing:.18em;margin-bottom:5px;}
.rmc-title{font-size:1.6rem;font-weight:900;color:var(--text);margin-bottom:3px;letter-spacing:-.02em;}
.rmc-sub{font-size:.87rem;color:var(--text2);font-weight:500;}

.rmc-body{padding:20px 24px;}
.rmc-pill-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
.rmc-pill{
    display:inline-flex;align-items:center;gap:5px;
    background:var(--card2);color:var(--text2);
    font-size:.72rem;font-weight:700;padding:5px 12px;border-radius:20px;
    border:1px solid var(--border);
}
.rmc-pill i{color:var(--orange);}

.rmc-code-box{
    background:var(--card2);border:1.5px dashed var(--border);
    border-radius:12px;padding:14px 16px;margin-bottom:14px;
}
.rmc-code-lbl{font-size:.62rem;font-weight:800;color:var(--purple);text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;display:flex;align-items:center;gap:5px;}
.rmc-code-val{
    font-size:1.2rem;font-weight:800;font-family:'Courier New',monospace;color:var(--text);
    letter-spacing:.08em;display:flex;align-items:center;justify-content:space-between;gap:10px;
}

.rmc-footer{padding:0 24px 22px;display:flex;gap:10px;}
.btn-rmc{
    flex:1;padding:12px 16px;border-radius:12px;font-size:.86rem;font-weight:800;
    cursor:pointer;border:none;display:flex;align-items:center;justify-content:center;
    gap:7px;transition:all .18s;font-family:inherit;
}
.btn-rmc-primary{background:var(--orange);color:#fff;box-shadow:0 4px 14px rgba(249,115,22,.3);}
.btn-rmc-primary:hover{background:var(--orange3);transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,.45);}
.btn-rmc-secondary{background:var(--card2);border:1.5px solid var(--border);color:var(--text2);}
.btn-rmc-secondary:hover{border-color:var(--orange);color:var(--orange);}

/* ── Confetti particle ─────────────────────────────────────────────────── */
.blast-p{position:fixed;pointer-events:none;z-index:9999;border-radius:50%;}

/* ── Responsive ────────────────────────────────────────────────────────── */
@media(max-width:1100px){
    .stats-row{grid-template-columns:repeat(3,1fr);}
    .rewards-grid{grid-template-columns:repeat(2,1fr);}
    .boxes-grid{grid-template-columns:repeat(3,1fr);}
}
@media(max-width:900px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .main-content{padding:24px 16px;}
    .hero-banner{flex-direction:column;gap:16px;text-align:center;}
    .hero-left p{max-width:100%;}
}
@media(max-width:640px){
    .stats-row{grid-template-columns:repeat(2,1fr);}
    .rewards-grid{grid-template-columns:1fr;}
    .boxes-grid{grid-template-columns:repeat(3,1fr);gap:10px;}
    .rmc-footer{flex-direction:column;}
    .filter-tabs{gap:6px;}
}
@media(max-width:400px){
    .boxes-grid{grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="page-wrap">

    <!-- Topbar -->
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">
            <div class="t-icon"><i class="fas fa-gift"></i></div>
            My Earnings
        </div>
        <a href="profile.php" class="topbar-avatar" title="<?php echo htmlspecialchars($student['full_name']); ?>">
            <?php echo $initials; ?>
        </a>
    </div>

    <div class="main-content">

        <!-- Hero Banner -->
        <div class="hero-banner">
            <div class="hero-left">
                <h1>🎁 My Earnings & Rewards</h1>
                <p>Exclusive benefits and perks earned through your outstanding performance</p>
            </div>
            <?php if ($pendingCount > 0): ?>
            <div class="hero-badge">
                <i class="fas fa-star"></i>
                <?php echo $pendingCount; ?> New Reward<?php echo $pendingCount > 1 ? 's' : ''; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon si-orange"><i class="fas fa-gift"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $totalRewards; ?></div>
                    <div class="stat-lbl">Total Earned</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-amber"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $pendingCount; ?></div>
                    <div class="stat-lbl">Pending</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-green"><i class="fas fa-bolt"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $activeCount; ?></div>
                    <div class="stat-lbl">Active</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-blue"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $claimedCount; ?></div>
                    <div class="stat-lbl">Redeemed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-purple"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $mentorCount; ?></div>
                    <div class="stat-lbl">Mentorships</div>
                </div>
            </div>
        </div>

        <!-- Gift Boxes Section -->
        <div class="boxes-section">
            <div class="section-hdr">
                <h2>Your Gift Boxes</h2>
                <?php if ($pendingCount > 0): ?>
                <span class="hdr-badge"><?php echo $pendingCount; ?> ready to open</span>
                <?php endif; ?>
            </div>
            <div class="boxes-grid">
                <?php foreach ($slots as $i => $reward): ?>

                <?php if ($reward === null): ?>
                    <!-- Empty slot -->
                    <div class="gift-box empty">
                        <div class="gb-empty-icon">📦</div>
                        <div class="gb-empty-lbl">Empty</div>
                    </div>

                <?php elseif ($reward['status'] === 'locked'): ?>
                    <!-- Locked — ready to open -->
                    <div class="gift-box locked"
                         data-reward='<?php echo htmlspecialchars(json_encode($reward), ENT_QUOTES); ?>'
                         onclick="handleBoxClick(this)">
                        <div class="gb-shine"></div>
                        <div class="gb-inner">
                            <div class="gb-new">New!</div>
                            <span class="gb-present">🎁</span>
                            <div class="gb-title"><?php echo htmlspecialchars($reward['title']); ?></div>
                        </div>
                        <div class="gb-chip chip-open"><i class="fas fa-hand-pointer"></i> &nbsp;Tap to Open</div>
                    </div>

                <?php elseif ($reward['status'] === 'unlocked'): ?>
                    <!-- Unlocked — can view/activate -->
                    <div class="gift-box unlocked"
                         data-reward='<?php echo htmlspecialchars(json_encode($reward), ENT_QUOTES); ?>'
                         onclick="viewReward(this)">
                        <div class="gb-inner">
                            <span class="gb-present"><?php echo $reward['icon'] ?? '🎁'; ?></span>
                            <div class="gb-title"><?php echo htmlspecialchars($reward['title']); ?></div>
                        </div>
                        <div class="gb-chip chip-view"><i class="fas fa-eye"></i> &nbsp;View Reward</div>
                    </div>

                <?php elseif ($reward['status'] === 'activate_requested'): ?>
                    <!-- Activation pending admin -->
                    <div class="gift-box activate_requested">
                        <div class="gb-inner">
                            <span class="gb-present"><?php echo $reward['icon'] ?? '⏳'; ?></span>
                            <div class="gb-title"><?php echo htmlspecialchars($reward['title']); ?></div>
                        </div>
                        <div class="gb-chip chip-pending"><i class="fas fa-hourglass-half"></i> &nbsp;Pending</div>
                    </div>

                <?php elseif ($reward['status'] === 'activated'): ?>
                    <!-- Activated — ready to claim -->
                    <div class="gift-box activated"
                         data-reward='<?php echo htmlspecialchars(json_encode($reward), ENT_QUOTES); ?>'
                         onclick="viewReward(this)">
                        <div class="gb-inner">
                            <span class="gb-present"><?php echo $reward['icon'] ?? '✅'; ?></span>
                            <div class="gb-title"><?php echo htmlspecialchars($reward['title']); ?></div>
                        </div>
                        <div class="gb-chip chip-claim"><i class="fas fa-check-circle"></i> &nbsp;Ready to Claim</div>
                    </div>

                <?php else: ?>
                    <!-- Claimed -->
                    <div class="gift-box claimed">
                        <div class="gb-inner">
                            <span class="gb-present"><?php echo $reward['icon'] ?? '✅'; ?></span>
                            <div class="gb-title"><?php echo htmlspecialchars($reward['title']); ?></div>
                        </div>
                        <div class="gb-chip chip-done"><i class="fas fa-check"></i> &nbsp;Claimed</div>
                    </div>
                <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reward Cards List -->
        <?php if (!empty($realRewards)): ?>
        <div class="rewards-section">
            <div class="section-hdr">
                <h2>All Rewards</h2>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <?php
                $tabs = [
                    'all'      => ['All',      $totalRewards],
                    'pending'  => ['Pending',  $pendingCount],
                    'active'   => ['Active',   $activeCount],
                    'redeemed' => ['Redeemed', $claimedCount],
                ];
                foreach ($tabs as $key => [$label, $cnt]):
                    $active = $filter === $key ? ' active' : '';
                ?>
                <a href="?filter=<?php echo $key; ?>" class="filter-tab<?php echo $active; ?>">
                    <?php echo $label; ?>
                    <span class="ft-count"><?php echo $cnt; ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Cards Grid -->
            <div class="rewards-grid">
                <?php if (empty($displayRewards)): ?>
                <div class="empty-state">
                    <i class="fas fa-gift"></i>
                    <p>No rewards in this category yet</p>
                </div>
                <?php else: ?>

                <?php foreach ($displayRewards as $r):
                    $meta    = rewardTypeMeta($r['reward_type'] ?? 'bonus');
                    $isFeat  = (int)($r['priority'] ?? 0) === 1;
                    $status  = $r['status'];
                    $dotCls  = 'dot-' . $status;
                    $isExpired = !empty($r['expires_at']) && strtotime($r['expires_at']) < time();
                ?>
                <div class="reward-card<?php echo $isFeat ? ' featured' : ''; ?>"
                     data-reward='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>'>

                    <div class="rc-top">
                        <div class="rc-type-row">
                            <span class="rc-type-badge <?php echo $meta['cls']; ?>">
                                <?php echo $meta['icon']; ?> <?php echo $meta['label']; ?>
                            </span>
                            <span class="rc-status-dot <?php echo $dotCls; ?>"></span>
                        </div>
                        <div class="rc-icon-row">
                            <span class="rc-big-icon"><?php echo $r['icon'] ?? $meta['icon']; ?></span>
                            <div>
                                <div class="rc-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                <?php if ($r['value']): ?>
                                <div class="rc-value-pill">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($r['value']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($r['subtitle'] || $r['awarded_for']): ?>
                    <div class="rc-body">
                        <div class="rc-desc">
                            <?php echo htmlspecialchars($r['subtitle'] ?: $r['awarded_for']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="rc-footer">
                        <?php if ($status === 'locked'): ?>
                            <span style="flex:1;font-size:.72rem;color:var(--text3);font-weight:500;">Open the box above ↑</span>

                        <?php elseif ($status === 'unlocked'): ?>
                            <?php if ($r['code']): ?>
                            <div class="rc-code-snippet">
                                <i class="fas fa-ticket-alt" style="color:var(--purple);flex-shrink:0;"></i>
                                <span><?php echo htmlspecialchars($r['code']); ?></span>
                            </div>
                            <button class="btn-sm btn-copy" onclick="copyCode('<?php echo htmlspecialchars($r['code']); ?>',this)">
                                <i class="fas fa-copy"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn-sm btn-activate"
                                    onclick="requestActivate(<?php echo $r['id']; ?>,this)">
                                <i class="fas fa-bolt"></i> Activate
                            </button>

                        <?php elseif ($status === 'activate_requested'): ?>
                            <span class="rc-status-label sl-activate_requested"><i class="fas fa-hourglass-half"></i> Activation Pending</span>

                        <?php elseif ($status === 'activated'): ?>
                            <?php if ($r['code']): ?>
                            <div class="rc-code-snippet">
                                <i class="fas fa-ticket-alt" style="color:var(--blue);flex-shrink:0;"></i>
                                <span><?php echo htmlspecialchars($r['code']); ?></span>
                            </div>
                            <button class="btn-sm btn-copy" onclick="copyCode('<?php echo htmlspecialchars($r['code']); ?>',this)">
                                <i class="fas fa-copy"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn-sm btn-claim"
                                    onclick="claimReward(<?php echo $r['id']; ?>,this)">
                                <i class="fas fa-check-circle"></i> Claim
                            </button>

                        <?php else: ?>
                            <span class="rc-status-label sl-claimed"><i class="fas fa-check"></i> Claimed</span>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /main-content -->
</div><!-- /page-wrap -->

<!-- Reward Reveal Modal -->
<div class="rmodal" id="rewardModal">
    <div class="rmodal-card" id="rewardCard"></div>
</div>

<script>
// ── Box Click (locked → unlock) ─────────────────────────────────────────
function handleBoxClick(el) {
    const reward = JSON.parse(el.dataset.reward);
    if (!reward || reward.status !== 'locked') return;

    el.style.pointerEvents = 'none';

    // Shake sequence
    let s = 0;
    const iv = setInterval(() => {
        el.style.transform = s % 2 === 0
            ? 'rotate(-8deg) scale(1.05)'
            : 'rotate(8deg) scale(1.05)';
        s++;
        if (s >= 6) {
            clearInterval(iv);
            el.style.transform = 'scale(1.1)';
            doBlast(el, reward);
        }
    }, 70);
}

function doBlast(el, reward) {
    const rect = el.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;

    // White flash
    const flash = document.createElement('div');
    flash.style.cssText = 'position:fixed;inset:0;background:#fff;opacity:0;z-index:9998;pointer-events:none;transition:opacity .12s';
    document.body.appendChild(flash);
    requestAnimationFrame(() => flash.style.opacity = '.6');
    setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 200); }, 120);

    // Particles
    const cols = ['#f97316','#fb923c','#8b5cf6','#3b82f6','#22c55e','#ec4899','#eab308','#fff','#f43f5e'];
    for (let i = 0; i < 80; i++) fireParticle(cx, cy, cols[i % cols.length]);

    // Fetch unlock
    fetch('earnings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `open_box=1&reward_id=${reward.id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            setTimeout(() => {
                el.style.display = 'none';
                showModal(data.reward);
            }, 650);
        } else {
            el.style.pointerEvents = '';
        }
    })
    .catch(() => { el.style.pointerEvents = ''; });
}

function fireParticle(cx, cy, color) {
    const p = document.createElement('div');
    p.className = 'blast-p';
    const sz = 5 + Math.random() * 11;
    const angle = Math.random() * Math.PI * 2;
    const speed = 80 + Math.random() * 280;
    const dx = Math.cos(angle) * speed;
    const dy = Math.sin(angle) * speed - 100;
    const rot = (Math.random() - .5) * 720;
    const dur = 600 + Math.random() * 500;
    const shapes = ['50%','3px','0'];
    p.style.cssText = `width:${sz}px;height:${sz}px;background:${color};border-radius:${shapes[~~(Math.random()*3)]};left:${cx-sz/2}px;top:${cy-sz/2}px;opacity:1;transition:transform ${dur}ms cubic-bezier(.15,.8,.4,1),opacity ${dur}ms ease;`;
    document.body.appendChild(p);
    requestAnimationFrame(() => {
        p.style.transform = `translate(${dx}px,${dy}px) rotate(${rot}deg)`;
        p.style.opacity = '0';
    });
    setTimeout(() => p.remove(), dur + 60);
}

// ── View Unlocked/Activated Reward ─────────────────────────────────────
function viewReward(el) {
    const reward = JSON.parse(el.dataset.reward);
    showModal(reward);
}

// ── Modal ───────────────────────────────────────────────────────────────
function showModal(reward) {
    const card = document.getElementById('rewardCard');
    const status = reward.status;
    const canActivate = status === 'unlocked';
    const canClaim    = status === 'activated';

    card.innerHTML = `
        <div class="rmc-header">
            <span class="rmc-big-icon">${reward.icon || '🎁'}</span>
            <div class="rmc-eyebrow">🎉 You Earned a Reward!</div>
            <div class="rmc-title">${x(reward.title)}</div>
            ${reward.subtitle ? `<div class="rmc-sub">${x(reward.subtitle)}</div>` : ''}
        </div>
        <div class="rmc-body">
            <div class="rmc-pill-row">
                ${reward.value ? `<span class="rmc-pill"><i class="fas fa-clock"></i>${x(reward.value)}</span>` : ''}
                ${reward.awarded_for ? `<span class="rmc-pill"><i class="fas fa-award"></i>${x(reward.awarded_for)}</span>` : ''}
            </div>
            ${reward.code ? `
            <div class="rmc-code-box">
                <div class="rmc-code-lbl"><i class="fas fa-ticket-alt"></i> Redemption Code</div>
                <div class="rmc-code-val">
                    <span>${x(reward.code)}</span>
                    <button class="btn-sm btn-copy" onclick="copyCode('${x(reward.code)}',this)"><i class="fas fa-copy"></i></button>
                </div>
            </div>` : ''}
            ${reward.instructions ? `<p style="font-size:.82rem;color:var(--text2);line-height:1.6;font-weight:500;">${x(reward.instructions)}</p>` : ''}
        </div>
        <div class="rmc-footer">
            ${canActivate ? `<button class="btn-rmc btn-rmc-primary" onclick="requestActivate(${reward.id},this,true)"><i class="fas fa-bolt"></i> Activate Reward</button>` : ''}
            ${canClaim    ? `<button class="btn-rmc btn-rmc-primary" onclick="claimReward(${reward.id},this,true)" style="background:var(--blue);box-shadow:0 4px 14px rgba(59,130,246,.3)"><i class="fas fa-check-circle"></i> Claim Reward</button>` : ''}
            <button class="btn-rmc btn-rmc-secondary" onclick="closeModal()"><i class="fas fa-times"></i> Close</button>
        </div>
    `;

    document.getElementById('rewardModal').classList.add('open');

    // Mini confetti burst
    setTimeout(() => {
        const cols = ['#f97316','#8b5cf6','#3b82f6','#22c55e','#ec4899','#fbbf24'];
        for (let i = 0; i < 30; i++) {
            fireParticle(
                window.innerWidth  * (.2 + Math.random() * .6),
                window.innerHeight * .2 + Math.random() * 60,
                cols[i % cols.length]
            );
        }
    }, 250);
}

function x(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

function closeModal() {
    document.getElementById('rewardModal').classList.remove('open');
    location.reload();
}

// ── Activate Request ───────────────────────────────────────────────────
function requestActivate(id, btn, fromModal = false) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    fetch('earnings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `request_activate=1&reward_id=${id}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Requested!';
            btn.style.background = 'var(--green)';
            setTimeout(() => { fromModal ? closeModal() : location.reload(); }, 1200);
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Try again';
        }
    });
}

// ── Claim Reward ────────────────────────────────────────────────────────
function claimReward(id, btn, fromModal = false) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';
    fetch('earnings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `claim_reward=1&reward_id=${id}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Claimed!';
            btn.style.background = 'var(--green)';
            setTimeout(() => { fromModal ? closeModal() : location.reload(); }, 1200);
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Try again';
        }
    });
}

// ── Copy Code ───────────────────────────────────────────────────────────
function copyCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="fas fa-copy"></i>';
        }, 2200);
    });
}

// Close modal on backdrop click
document.getElementById('rewardModal').addEventListener('click', e => {
    if (e.target.id === 'rewardModal') closeModal();
});
</script>
</body>
</html>