<?php
ob_start();
session_start();
require_once 'config.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$db = getPadakDB();
$studentId = (int)$_SESSION['student_id'];

$student = $db->query("SELECT * FROM internship_students WHERE id=$studentId AND is_active=1")->fetch_assoc();
if (!$student) { header('Location: index.php'); exit; }

// Earned badges
$myBadgesRes = $db->query("
    SELECT sb.*, b.name, b.description, b.icon, b.tier, b.category, b.points_bonus, b.awarded_for
    FROM student_badges sb
    JOIN badges b ON sb.badge_id = b.id
    WHERE sb.student_id = $studentId
    ORDER BY sb.awarded_at DESC
");
$myBadges = [];
while ($row = $myBadgesRes->fetch_assoc()) $myBadges[] = $row;
$myBadgeIds = array_column($myBadges, 'badge_id');

// All badges
$allBadgesRes = $db->query("SELECT * FROM badges WHERE is_active=1 ORDER BY FIELD(tier,'diamond','platinum','gold','silver','bronze'), name ASC");
$allBadges = [];
while ($row = $allBadgesRes->fetch_assoc()) $allBadges[] = $row;

$totalBadges   = count($myBadges);
$totalBonusPts = array_sum(array_column($myBadges, 'points_bonus'));
$tierCounts    = ['bronze'=>0,'silver'=>0,'gold'=>0,'platinum'=>0,'diamond'=>0];
foreach ($myBadges as $b) {
    $t = strtolower($b['tier'] ?? 'bronze');
    if (isset($tierCounts[$t])) $tierCounts[$t]++;
}

$notifCount = (int)$db->query("SELECT COUNT(*) c FROM student_notifications WHERE student_id=$studentId AND is_read=0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Badges - Padak Internship</title>
    <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        :root{
            --o5:#f97316;--o4:#fb923c;--o6:#ea580c;--o1:#fff7ed;--o2:#ffedd5;
            --bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;
            --bronze-c:#cd7f32;--bronze-bg:#fdf3e7;
            --silver-c:#8fa0b0;--silver-bg:#f3f5f7;
            --gold-c:#d4a017;--gold-bg:#fefce8;
            --platinum-c:#5b7fa6;--platinum-bg:#eff6ff;
            --diamond-c:#a855f7;--diamond-bg:#faf5ff;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

        /* HEADER */
        .ph{background:linear-gradient(135deg,#1e293b,#0f172a);padding:0;position:sticky;top:0;z-index:100;box-shadow:0 4px 20px rgba(0,0,0,0.2);}
        .ph-inner{max-width:1200px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;}
        .ph-logo{display:flex;align-items:center;gap:12px;}
        .ph-logo-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--o5),var(--o4));border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
        .ph-logo-text h1{font-size:1.1rem;font-weight:800;color:#fff;}
        .ph-logo-text p{font-size:.7rem;color:rgba(255,255,255,0.45);}
        .ph-nav{display:flex;align-items:center;gap:6px;}
        .nav-a{padding:7px 13px;border-radius:8px;font-size:.8rem;font-weight:500;color:rgba(255,255,255,0.65);text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:5px;}
        .nav-a:hover{background:rgba(255,255,255,0.1);color:#fff;}
        .nav-a.active{background:rgba(249,115,22,0.2);color:var(--o4);}
        .ph-notif{position:relative;}
        .ph-notif-btn{width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,0.1);border:none;color:rgba(255,255,255,0.7);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;}
        .ph-notif-dot{position:absolute;top:-3px;right:-3px;width:16px;height:16px;background:#ef4444;border-radius:50%;font-size:.6rem;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid #0f172a;}

        /* HERO */
        .hero{background:linear-gradient(135deg,#1e293b,#0f172a,#1e1b4b);padding:48px 24px 64px;text-align:center;position:relative;overflow:hidden;}
        .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 50%,rgba(249,115,22,0.12),transparent 60%),radial-gradient(circle at 70% 50%,rgba(168,85,247,0.1),transparent 60%);}
        .hero-inner{position:relative;z-index:1;max-width:680px;margin:0 auto;}
        .hero-trophy{font-size:4rem;margin-bottom:14px;display:block;animation:float 3s ease-in-out infinite;}
        @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-10px);}}
        .hero-title{font-size:2rem;font-weight:900;color:#fff;margin-bottom:8px;}
        .hero-title span{background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .hero-sub{font-size:.9rem;color:rgba(255,255,255,0.55);margin-bottom:28px;}
        .hero-stats{display:flex;justify-content:center;gap:28px;flex-wrap:wrap;}
        .hs-item{text-align:center;}
        .hs-val{font-size:1.8rem;font-weight:900;color:#fff;line-height:1;}
        .hs-label{font-size:.68rem;color:rgba(255,255,255,0.45);margin-top:3px;text-transform:uppercase;letter-spacing:.05em;}

        /* TIER ROW */
        .tier-row{max-width:1200px;margin:-26px auto 0;padding:0 24px;position:relative;z-index:2;}
        .tier-cards{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;}
        .tc{background:var(--card);border-radius:12px;padding:14px;text-align:center;border:2px solid var(--border);box-shadow:0 4px 14px rgba(0,0,0,0.07);transition:all .2s;}
        .tc:hover{transform:translateY(-2px);}
        .tc.bronze{border-color:var(--bronze-c);background:var(--bronze-bg);}
        .tc.silver{border-color:var(--silver-c);background:var(--silver-bg);}
        .tc.gold  {border-color:var(--gold-c);background:var(--gold-bg);}
        .tc.platinum{border-color:var(--platinum-c);background:var(--platinum-bg);}
        .tc.diamond{border-color:var(--diamond-c);background:var(--diamond-bg);}
        .tc-emoji{font-size:1.6rem;margin-bottom:4px;}
        .tc-num{font-size:1.4rem;font-weight:900;}
        .tc-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:2px;}
        .bronze .tc-num,.bronze .tc-lbl{color:var(--bronze-c);}
        .silver .tc-num,.silver .tc-lbl{color:var(--silver-c);}
        .gold   .tc-num,.gold   .tc-lbl{color:var(--gold-c);}
        .platinum .tc-num,.platinum .tc-lbl{color:var(--platinum-c);}
        .diamond .tc-num,.diamond .tc-lbl{color:var(--diamond-c);}

        /* MAIN */
        .main{max-width:1200px;margin:36px auto;padding:0 24px 60px;}
        .sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
        .sec-title{font-size:1.1rem;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}
        .sec-title i{color:var(--o5);}
        .sec-count{font-size:.78rem;color:var(--text3);}

        /* CAT FILTER */
        .cat-bar{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:18px;}
        .cat-btn{padding:5px 13px;border-radius:7px;border:1.5px solid var(--border);background:var(--card);font-size:.76rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .2s;font-family:inherit;}
        .cat-btn:hover{border-color:var(--o5);color:var(--o5);}
        .cat-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;}

        /* BADGES GRID */
        .badges-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;}
        .badge-card{background:var(--card);border-radius:15px;padding:22px 14px;text-align:center;border:2px solid var(--border);box-shadow:0 2px 8px rgba(0,0,0,0.05);transition:all .3s;position:relative;overflow:hidden;}
        .badge-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,0.1);}
        /* earned tiers */
        .badge-card.earned.t-bronze{border-color:var(--bronze-c);background:linear-gradient(160deg,#fff,var(--bronze-bg));}
        .badge-card.earned.t-silver{border-color:var(--silver-c);background:linear-gradient(160deg,#fff,var(--silver-bg));}
        .badge-card.earned.t-gold  {border-color:var(--gold-c);background:linear-gradient(160deg,#fff,var(--gold-bg));}
        .badge-card.earned.t-platinum{border-color:var(--platinum-c);background:linear-gradient(160deg,#fff,var(--platinum-bg));}
        .badge-card.earned.t-diamond{border-color:var(--diamond-c);background:linear-gradient(160deg,#fff,var(--diamond-bg));}
        .badge-card.locked{opacity:.5;filter:grayscale(.6);}
        .badge-card.locked:hover{transform:none;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
        /* shine */
        .badge-card.earned::before{content:'';position:absolute;top:-50%;left:-70%;width:45%;height:200%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.45),transparent);transform:skewX(-20deg);transition:left .5s;}
        .badge-card.earned:hover::before{left:130%;}
        /* new ribbon */
        .new-rib{position:absolute;top:9px;right:-6px;background:var(--o5);color:#fff;font-size:.58rem;font-weight:800;padding:3px 12px 3px 6px;clip-path:polygon(0 0,100% 0,100% 100%,0 100%,7px 50%);text-transform:uppercase;}
        /* icon circle */
        .bi-wrap{width:72px;height:72px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:2rem;position:relative;}
        .t-bronze .bi-wrap{background:linear-gradient(135deg,var(--bronze-c),#e8a95c);box-shadow:0 5px 18px rgba(205,127,50,0.35);}
        .t-silver .bi-wrap{background:linear-gradient(135deg,var(--silver-c),#c8d0d8);box-shadow:0 5px 18px rgba(143,160,176,0.35);}
        .t-gold   .bi-wrap{background:linear-gradient(135deg,var(--gold-c),#f0c040);box-shadow:0 5px 18px rgba(212,160,23,0.4);}
        .t-platinum .bi-wrap{background:linear-gradient(135deg,var(--platinum-c),#8fb3d4);box-shadow:0 5px 18px rgba(91,127,166,0.4);}
        .t-diamond .bi-wrap{background:linear-gradient(135deg,var(--diamond-c),#c084fc);box-shadow:0 5px 18px rgba(168,85,247,0.4);}
        .locked .bi-wrap{background:linear-gradient(135deg,#94a3b8,#cbd5e1);box-shadow:none;}
        .lock-dot{position:absolute;bottom:-1px;right:-1px;width:22px;height:22px;background:#64748b;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.62rem;color:#fff;border:2px solid var(--card);}
        .badge-tier-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:5px;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin-bottom:7px;}
        .t-bronze .badge-tier-pill{background:var(--bronze-bg);color:var(--bronze-c);}
        .t-silver .badge-tier-pill{background:var(--silver-bg);color:var(--silver-c);}
        .t-gold   .badge-tier-pill{background:var(--gold-bg);color:var(--gold-c);}
        .t-platinum .badge-tier-pill{background:var(--platinum-bg);color:var(--platinum-c);}
        .t-diamond .badge-tier-pill{background:var(--diamond-bg);color:var(--diamond-c);}
        .locked .badge-tier-pill{background:#f1f5f9;color:#64748b;}
        .badge-name{font-size:.88rem;font-weight:800;color:var(--text);margin-bottom:5px;}
        .badge-desc{font-size:.7rem;color:var(--text2);line-height:1.4;margin-bottom:8px;}
        .badge-pts{font-size:.7rem;color:var(--o5);font-weight:700;}
        .badge-date{font-size:.62rem;color:var(--text3);margin-top:4px;}
        .badge-earned-tag{font-size:.62rem;color:#16a34a;margin-top:4px;font-weight:700;}

        /* EMPTY */
        .empty-state{text-align:center;padding:50px 20px;color:var(--text3);}
        .empty-state i{font-size:3rem;opacity:.3;margin-bottom:12px;display:block;}
        .empty-state h3{font-size:1rem;color:var(--text2);margin-bottom:6px;}

        @media(max-width:768px){
            .tier-cards{grid-template-columns:repeat(3,1fr);}
            .badges-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));}
            .hero-title{font-size:1.6rem;}
            .ph-nav .nav-a span{display:none;}
        }
        @media(max-width:480px){
            .tier-cards{grid-template-columns:repeat(2,1fr);}
            .hero-stats{gap:18px;}
        }
    </style>
</head>
<body>

<header class="ph">
    <div class="ph-inner">
        <div class="ph-logo">
            <div class="ph-logo-icon"><i class="fas fa-trophy"></i></div>
            <div class="ph-logo-text"><h1>Padak Internship</h1><p>Achievement Center</p></div>
        </div>
        <nav class="ph-nav">
            <a href="dashboard.php" class="nav-a"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="tasks.php"     class="nav-a"><i class="fas fa-tasks"></i><span>Tasks</span></a>
            <a href="badges.php"    class="nav-a active"><i class="fas fa-medal"></i><span>Badges</span></a>
            <a href="leaderboard.php" class="nav-a"><i class="fas fa-ranking-star"></i><span>Leaderboard</span></a>
            <div class="ph-notif">
                <button class="ph-notif-btn" onclick="location.href='notifications.php'">
                    <i class="fas fa-bell"></i>
                </button>
                <?php if ($notifCount > 0): ?>
                <div class="ph-notif-dot"><?php echo $notifCount > 9 ? '9+' : $notifCount; ?></div>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<div class="hero">
    <div class="hero-inner">
        <span class="hero-trophy">🏆</span>
        <h1 class="hero-title">Your <span>Achievement</span> Wall</h1>
        <p class="hero-sub">Badges awarded for outstanding performance during your Padak internship</p>
        <div class="hero-stats">
            <div class="hs-item"><div class="hs-val"><?php echo $totalBadges; ?></div><div class="hs-label">Badges Earned</div></div>
            <div class="hs-item"><div class="hs-val"><?php echo count($allBadges); ?></div><div class="hs-label">Total Available</div></div>
            <div class="hs-item"><div class="hs-val">+<?php echo $totalBonusPts; ?></div><div class="hs-label">Bonus Points</div></div>
            <div class="hs-item"><div class="hs-val"><?php echo (int)$student['total_points']; ?></div><div class="hs-label">Total Points</div></div>
        </div>
    </div>
</div>

<div class="tier-row">
    <div class="tier-cards">
        <div class="tc bronze"><div class="tc-emoji">🥉</div><div class="tc-num"><?php echo $tierCounts['bronze']; ?></div><div class="tc-lbl">Bronze</div></div>
        <div class="tc silver"><div class="tc-emoji">🥈</div><div class="tc-num"><?php echo $tierCounts['silver']; ?></div><div class="tc-lbl">Silver</div></div>
        <div class="tc gold">  <div class="tc-emoji">🥇</div><div class="tc-num"><?php echo $tierCounts['gold']; ?></div><div class="tc-lbl">Gold</div></div>
        <div class="tc platinum"><div class="tc-emoji">💠</div><div class="tc-num"><?php echo $tierCounts['platinum']; ?></div><div class="tc-lbl">Platinum</div></div>
        <div class="tc diamond"><div class="tc-emoji">💎</div><div class="tc-num"><?php echo $tierCounts['diamond']; ?></div><div class="tc-lbl">Diamond</div></div>
    </div>
</div>

<div class="main">

    <?php if (!empty($myBadges)): ?>
    <div style="margin-bottom:44px;">
        <div class="sec-hdr">
            <div class="sec-title"><i class="fas fa-star"></i> My Earned Badges</div>
            <span class="sec-count"><?php echo $totalBadges; ?> badge<?php echo $totalBadges!==1?'s':''; ?> earned</span>
        </div>
        <div class="badges-grid">
            <?php foreach ($myBadges as $badge):
                $tier = strtolower($badge['tier'] ?? 'bronze');
                $tIcons = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💠','diamond'=>'💎'];
                $isNew  = strtotime($badge['awarded_at']) > strtotime('-7 days');
            ?>
            <div class="badge-card earned t-<?php echo $tier; ?>">
                <?php if ($isNew): ?><div class="new-rib">New!</div><?php endif; ?>
                <div class="bi-wrap"><?php echo htmlspecialchars($badge['icon'] ?? '🏅'); ?></div>
                <span class="badge-tier-pill"><?php echo $tIcons[$tier]??''; ?> <?php echo ucfirst($tier); ?></span>
                <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                <?php if ($badge['points_bonus'] > 0): ?>
                <div class="badge-pts"><i class="fas fa-bolt"></i> +<?php echo $badge['points_bonus']; ?> bonus pts</div>
                <?php endif; ?>
                <div class="badge-date"><i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($badge['awarded_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Full collection -->
    <div>
        <div class="sec-hdr">
            <div class="sec-title"><i class="fas fa-medal"></i> Badge Collection</div>
        </div>

        <?php
        $cats = array_unique(array_filter(array_column($allBadges, 'category')));
        if (!empty($cats)):
        ?>
        <div class="cat-bar" id="catBar">
            <button class="cat-btn active" data-cat="all">All</button>
            <?php foreach ($cats as $cat): ?>
            <button class="cat-btn" data-cat="<?php echo htmlspecialchars($cat); ?>">
                <?php echo ucwords(str_replace('_',' ',$cat)); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($allBadges)): ?>
        <div class="empty-state">
            <i class="fas fa-medal"></i>
            <h3>No badges available yet</h3>
            <p>The admin will add badges soon. Keep performing well!</p>
        </div>
        <?php else: ?>
        <div class="badges-grid" id="allGrid">
            <?php foreach ($allBadges as $badge):
                $tier   = strtolower($badge['tier'] ?? 'bronze');
                $earned = in_array($badge['id'], $myBadgeIds);
                $tIcons = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💠','diamond'=>'💎'];
            ?>
            <div class="badge-card t-<?php echo $tier; ?> <?php echo $earned?'earned':'locked'; ?>"
                 data-cat="<?php echo htmlspecialchars($badge['category'] ?? 'general'); ?>">
                <div class="bi-wrap">
                    <?php echo htmlspecialchars($badge['icon'] ?? '🏅'); ?>
                    <?php if (!$earned): ?><div class="lock-dot"><i class="fas fa-lock"></i></div><?php endif; ?>
                </div>
                <span class="badge-tier-pill"><?php echo $tIcons[$tier]??''; ?> <?php echo ucfirst($tier); ?></span>
                <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                <?php if ($badge['points_bonus'] > 0): ?>
                <div class="badge-pts"><i class="fas fa-bolt"></i> +<?php echo $badge['points_bonus']; ?> bonus pts</div>
                <?php endif; ?>
                <?php if ($earned): ?>
                <div class="badge-earned-tag"><i class="fas fa-check-circle"></i> Earned!</div>
                <?php else: ?>
                <div class="badge-date"><i class="fas fa-lock"></i> Not yet earned</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
var catBar = document.getElementById('catBar');
if (catBar) {
    catBar.addEventListener('click', function(e) {
        var btn = e.target.closest('.cat-btn');
        if (!btn) return;
        catBar.querySelectorAll('.cat-btn').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        var cat = btn.dataset.cat;
        document.querySelectorAll('#allGrid .badge-card').forEach(function(card) {
            card.style.display = (cat === 'all' || card.dataset.cat === cat) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>