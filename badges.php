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

$activePage = 'badges';
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
            --sbw:258px;
            --o5:#f97316;--o4:#fb923c;--o6:#ea580c;--o1:#fff7ed;--o2:#ffedd5;
            --bg:#f1f5f9;--card:#ffffff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;
            --bronze-c:#cd7f32;--bronze-bg:#fdf3e7;
            --silver-c:#8fa0b0;--silver-bg:#f3f5f7;
            --gold-c:#d4a017;--gold-bg:#fefce8;
            --platinum-c:#5b7fa6;--platinum-bg:#eff6ff;
            --diamond-c:#a855f7;--diamond-bg:#faf5ff;
            --green:#22c55e;--red:#ef4444;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}

        /* ── LAYOUT ── */
        .layout{display:flex;width:100%;min-height:100vh;}
        .main-wrap{margin-left:var(--sbw);flex:1;min-width:0;display:flex;flex-direction:column;transition:margin-left .3s;}

        /* ── TOP BAR ── */
        .topbar{
            background:var(--card);border-bottom:1px solid var(--border);
            padding:0 28px;height:58px;display:flex;align-items:center;
            justify-content:space-between;position:sticky;top:0;z-index:50;
            box-shadow:0 1px 4px rgba(0,0,0,0.06);
        }
        .topbar-left{display:flex;align-items:center;gap:14px;}
        .topbar-hamburger{display:none;background:none;border:none;font-size:1.2rem;color:var(--text2);cursor:pointer;padding:6px;border-radius:7px;}
        .topbar-hamburger:hover{background:var(--bg);}
        .topbar-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.82rem;color:var(--text3);}
        .topbar-breadcrumb a{color:var(--text3);text-decoration:none;transition:color .2s;}
        .topbar-breadcrumb a:hover{color:var(--o5);}
        .topbar-breadcrumb .sep{color:var(--border);}
        .topbar-breadcrumb .current{color:var(--text);font-weight:600;}
        .topbar-right{display:flex;align-items:center;gap:10px;}
        .topbar-title{font-size:1rem;font-weight:700;color:var(--text);}

        /* ── PAGE CONTENT ── */
        .page-content{padding:24px 28px 48px;flex:1;}

        /* ── HERO BANNER ── */
        .hero-banner{
            background:linear-gradient(135deg,#1e293b 0%,#0f172a 55%,#1e1b4b 100%);
            border-radius:16px;padding:36px 32px;margin-bottom:22px;
            position:relative;overflow:hidden;
        }
        .hero-banner::before{
            content:'';position:absolute;inset:0;pointer-events:none;
            background:radial-gradient(circle at 20% 50%,rgba(249,115,22,0.15),transparent 55%),
                        radial-gradient(circle at 80% 30%,rgba(168,85,247,0.12),transparent 50%);
        }
        .hero-inner{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
        .hero-text{}
        .hero-text h1{font-size:1.7rem;font-weight:900;color:#fff;margin-bottom:6px;line-height:1.2;}
        .hero-text h1 span{background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .hero-text p{font-size:.85rem;color:rgba(255,255,255,0.55);margin-bottom:20px;}
        .hero-stats{display:flex;gap:22px;flex-wrap:wrap;}
        .hs{text-align:center;}
        .hs-val{font-size:1.6rem;font-weight:900;color:#fff;line-height:1;}
        .hs-lbl{font-size:.65rem;color:rgba(255,255,255,0.45);margin-top:3px;text-transform:uppercase;letter-spacing:.06em;}
        .hero-trophy{font-size:5rem;animation:float 3s ease-in-out infinite;filter:drop-shadow(0 8px 24px rgba(249,115,22,0.3));flex-shrink:0;}
        @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-12px);}}

        /* ── TIER CARDS ── */
        .tier-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:22px;}
        .tc{
            background:var(--card);border-radius:12px;padding:16px 10px;text-align:center;
            border:2px solid var(--border);box-shadow:0 1px 4px rgba(0,0,0,0.05);
            transition:all .2s;
        }
        .tc:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.1);}
        .tc.t-bronze{border-color:var(--bronze-c);background:var(--bronze-bg);}
        .tc.t-silver{border-color:var(--silver-c);background:var(--silver-bg);}
        .tc.t-gold  {border-color:var(--gold-c);  background:var(--gold-bg);}
        .tc.t-platinum{border-color:var(--platinum-c);background:var(--platinum-bg);}
        .tc.t-diamond {border-color:var(--diamond-c); background:var(--diamond-bg);}
        .tc-emoji{font-size:1.7rem;margin-bottom:5px;line-height:1;}
        .tc-num{font-size:1.5rem;font-weight:900;line-height:1;}
        .tc-lbl{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:3px;}
        .t-bronze .tc-num,.t-bronze .tc-lbl{color:var(--bronze-c);}
        .t-silver .tc-num,.t-silver .tc-lbl{color:var(--silver-c);}
        .t-gold   .tc-num,.t-gold   .tc-lbl{color:var(--gold-c);}
        .t-platinum .tc-num,.t-platinum .tc-lbl{color:var(--platinum-c);}
        .t-diamond  .tc-num,.t-diamond  .tc-lbl{color:var(--diamond-c);}

        /* ── SECTION ── */
        .section-card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 4px rgba(0,0,0,0.05);margin-bottom:20px;}
        .sc-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
        .sc-title{font-size:1rem;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}
        .sc-title i{color:var(--o5);}
        .sc-count{font-size:.75rem;color:var(--text3);font-weight:500;}
        .sc-body{padding:22px;}

        /* ── CAT FILTER ── */
        .cat-bar{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:18px;}
        .cat-btn{padding:6px 14px;border-radius:20px;border:1.5px solid var(--border);background:var(--card);font-size:.76rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .2s;font-family:inherit;}
        .cat-btn:hover{border-color:var(--o5);color:var(--o5);}
        .cat-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;box-shadow:0 3px 10px rgba(249,115,22,0.3);}

        /* ── BADGES GRID ── */
        .badges-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:15px;}

        /* ── BADGE CARD ── */
        .badge-card{
            border-radius:14px;padding:20px 14px;text-align:center;
            border:2px solid var(--border);background:var(--card);
            box-shadow:0 1px 4px rgba(0,0,0,0.05);
            transition:all .3s;position:relative;overflow:hidden;
        }
        .badge-card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,0,0,0.1);}
        /* earned tier styles */
        .badge-card.earned.bt-bronze{border-color:var(--bronze-c);background:linear-gradient(160deg,#fff 60%,var(--bronze-bg));}
        .badge-card.earned.bt-silver{border-color:var(--silver-c);background:linear-gradient(160deg,#fff 60%,var(--silver-bg));}
        .badge-card.earned.bt-gold  {border-color:var(--gold-c);  background:linear-gradient(160deg,#fff 60%,var(--gold-bg));}
        .badge-card.earned.bt-platinum{border-color:var(--platinum-c);background:linear-gradient(160deg,#fff 60%,var(--platinum-bg));}
        .badge-card.earned.bt-diamond {border-color:var(--diamond-c); background:linear-gradient(160deg,#fff 60%,var(--diamond-bg));}
        /* locked */
        .badge-card.locked{opacity:.52;filter:grayscale(.55);}
        .badge-card.locked:hover{transform:none;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
        /* shine on earned */
        .badge-card.earned::before{
            content:'';position:absolute;top:-50%;left:-70%;width:42%;height:200%;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,0.5),transparent);
            transform:skewX(-20deg);transition:left .55s ease;
        }
        .badge-card.earned:hover::before{left:130%;}
        /* NEW ribbon */
        .new-rib{
            position:absolute;top:10px;right:-7px;
            background:var(--o5);color:#fff;font-size:.58rem;font-weight:800;
            padding:3px 14px 3px 7px;
            clip-path:polygon(0 0,100% 0,100% 100%,0 100%,7px 50%);
            text-transform:uppercase;letter-spacing:.04em;
        }
        /* icon circle */
        .bi-wrap{width:70px;height:70px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.9rem;position:relative;}
        .bt-bronze .bi-wrap{background:linear-gradient(135deg,var(--bronze-c),#e8a95c);box-shadow:0 5px 16px rgba(205,127,50,0.35);}
        .bt-silver .bi-wrap{background:linear-gradient(135deg,var(--silver-c),#c8d0d8);box-shadow:0 5px 16px rgba(143,160,176,0.35);}
        .bt-gold   .bi-wrap{background:linear-gradient(135deg,var(--gold-c),#f0c040);  box-shadow:0 5px 16px rgba(212,160,23,0.4);}
        .bt-platinum .bi-wrap{background:linear-gradient(135deg,var(--platinum-c),#8fb3d4);box-shadow:0 5px 16px rgba(91,127,166,0.4);}
        .bt-diamond  .bi-wrap{background:linear-gradient(135deg,var(--diamond-c),#c084fc); box-shadow:0 5px 16px rgba(168,85,247,0.4);}
        .locked .bi-wrap{background:linear-gradient(135deg,#94a3b8,#cbd5e1);box-shadow:none;}
        .lock-dot{position:absolute;bottom:-1px;right:-1px;width:21px;height:21px;background:#64748b;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#fff;border:2px solid var(--card);}
        /* tier pill */
        .tier-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:20px;font-size:.61rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin-bottom:7px;}
        .bt-bronze .tier-pill{background:var(--bronze-bg);color:var(--bronze-c);}
        .bt-silver .tier-pill{background:var(--silver-bg);color:var(--silver-c);}
        .bt-gold   .tier-pill{background:var(--gold-bg);  color:var(--gold-c);}
        .bt-platinum .tier-pill{background:var(--platinum-bg);color:var(--platinum-c);}
        .bt-diamond  .tier-pill{background:var(--diamond-bg); color:var(--diamond-c);}
        .locked .tier-pill{background:#f1f5f9;color:#64748b;}
        .badge-name{font-size:.87rem;font-weight:800;color:var(--text);margin-bottom:5px;line-height:1.3;}
        .badge-desc{font-size:.7rem;color:var(--text2);line-height:1.45;margin-bottom:8px;}
        .badge-pts{font-size:.7rem;color:var(--o5);font-weight:700;}
        .badge-foot{font-size:.62rem;margin-top:5px;}
        .badge-foot.earned-tag{color:#16a34a;font-weight:600;}
        .badge-foot.lock-tag{color:var(--text3);}

        /* ── EMPTY STATE ── */
        .empty-state{text-align:center;padding:48px 20px;color:var(--text3);}
        .empty-state i{font-size:3rem;opacity:.25;margin-bottom:12px;display:block;}
        .empty-state h3{font-size:1rem;color:var(--text2);margin-bottom:5px;font-weight:700;}
        .empty-state p{font-size:.8rem;}

        /* ── RESPONSIVE ── */
        @media(max-width:1100px){
            .tier-grid{grid-template-columns:repeat(5,1fr);}
        }
        @media(max-width:900px){
            .main-wrap{margin-left:0;}
            .topbar-hamburger{display:flex;align-items:center;justify-content:center;}
            .tier-grid{grid-template-columns:repeat(3,1fr);}
            .badges-grid{grid-template-columns:repeat(auto-fill,minmax(165px,1fr));}
            .hero-trophy{font-size:3.5rem;}
            .hero-text h1{font-size:1.4rem;}
        }
        @media(max-width:600px){
            .page-content{padding:16px 14px 40px;}
            .topbar{padding:0 14px;}
            .hero-banner{padding:22px 18px;}
            .hero-trophy{display:none;}
            .hero-text h1{font-size:1.25rem;}
            .hero-stats{gap:16px;}
            .hs-val{font-size:1.3rem;}
            .tier-grid{grid-template-columns:repeat(3,1fr);gap:8px;}
            .tc{padding:12px 6px;}
            .tc-emoji{font-size:1.3rem;}
            .tc-num{font-size:1.2rem;}
            .badges-grid{grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:11px;}
            .sc-body{padding:14px;}
            .sc-head{padding:13px 14px;}
        }
        @media(max-width:380px){
            .tier-grid{grid-template-columns:repeat(2,1fr);}
            .badges-grid{grid-template-columns:1fr 1fr;}
        }
    </style>
</head>
<body>
<div class="layout">

    <?php include 'sidebar.php'; ?>

    <div class="main-wrap">

        <!-- PAGE CONTENT -->
        <div class="page-content">

            <!-- HERO BANNER -->
            <div class="hero-banner">
                <div class="hero-inner">
                    <div class="hero-text">
                        <h1>Your <span>Achievement</span> Wall</h1>
                        <p>Badges awarded for outstanding performance during your Padak internship</p>
                        <div class="hero-stats">
                            <div class="hs"><div class="hs-val"><?php echo $totalBadges; ?></div><div class="hs-lbl">Badges Earned</div></div>
                            <div class="hs"><div class="hs-val"><?php echo count($allBadges); ?></div><div class="hs-lbl">Total Available</div></div>
                            <div class="hs"><div class="hs-val">+<?php echo $totalBonusPts; ?></div><div class="hs-lbl">Bonus Points</div></div>
                            <div class="hs"><div class="hs-val"><?php echo (int)$student['total_points']; ?></div><div class="hs-lbl">Total Points</div></div>
                        </div>
                    </div>
                    <div class="hero-trophy">🏆</div>
                </div>
            </div>

            <!-- TIER SUMMARY -->
            <div class="tier-grid">
                <div class="tc t-bronze">
                    <div class="tc-emoji">🥉</div>
                    <div class="tc-num"><?php echo $tierCounts['bronze']; ?></div>
                    <div class="tc-lbl">Bronze</div>
                </div>
                <div class="tc t-silver">
                    <div class="tc-emoji">🥈</div>
                    <div class="tc-num"><?php echo $tierCounts['silver']; ?></div>
                    <div class="tc-lbl">Silver</div>
                </div>
                <div class="tc t-gold">
                    <div class="tc-emoji">🥇</div>
                    <div class="tc-num"><?php echo $tierCounts['gold']; ?></div>
                    <div class="tc-lbl">Gold</div>
                </div>
                <div class="tc t-platinum">
                    <div class="tc-emoji">💠</div>
                    <div class="tc-num"><?php echo $tierCounts['platinum']; ?></div>
                    <div class="tc-lbl">Platinum</div>
                </div>
                <div class="tc t-diamond">
                    <div class="tc-emoji">💎</div>
                    <div class="tc-num"><?php echo $tierCounts['diamond']; ?></div>
                    <div class="tc-lbl">Diamond</div>
                </div>
            </div>

            <!-- MY EARNED BADGES -->
            <?php if (!empty($myBadges)): ?>
            <div class="section-card">
                <div class="sc-head">
                    <div class="sc-title"><i class="fas fa-star"></i> My Earned Badges</div>
                    <span class="sc-count"><?php echo $totalBadges; ?> badge<?php echo $totalBadges!==1?'s':''; ?> earned</span>
                </div>
                <div class="sc-body">
                    <div class="badges-grid">
                        <?php foreach ($myBadges as $badge):
                            $tier   = strtolower($badge['tier'] ?? 'bronze');
                            $tIcons = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💠','diamond'=>'💎'];
                            $isNew  = strtotime($badge['awarded_at']) > strtotime('-7 days');
                        ?>
                        <div class="badge-card earned bt-<?php echo $tier; ?>">
                            <?php if ($isNew): ?><div class="new-rib">New!</div><?php endif; ?>
                            <div class="bi-wrap"><?php echo htmlspecialchars($badge['icon'] ?? '🏅'); ?></div>
                            <span class="tier-pill"><?php echo $tIcons[$tier]??''; ?> <?php echo ucfirst($tier); ?></span>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                            <?php if ($badge['points_bonus'] > 0): ?>
                            <div class="badge-pts"><i class="fas fa-bolt"></i> +<?php echo $badge['points_bonus']; ?> bonus pts</div>
                            <?php endif; ?>
                            <div class="badge-foot earned-tag"><i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($badge['awarded_at'])); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- BADGE COLLECTION -->
            <div class="section-card">
                <div class="sc-head">
                    <div class="sc-title"><i class="fas fa-medal"></i> Badge Collection</div>
                    <span class="sc-count"><?php echo count($allBadges); ?> available</span>
                </div>
                <div class="sc-body">
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
                        <div class="badge-card bt-<?php echo $tier; ?> <?php echo $earned?'earned':'locked'; ?>"
                             data-cat="<?php echo htmlspecialchars($badge['category'] ?? 'general'); ?>">
                            <div class="bi-wrap">
                                <?php echo htmlspecialchars($badge['icon'] ?? '🏅'); ?>
                                <?php if (!$earned): ?><div class="lock-dot"><i class="fas fa-lock"></i></div><?php endif; ?>
                            </div>
                            <span class="tier-pill"><?php echo $tIcons[$tier]??''; ?> <?php echo ucfirst($tier); ?></span>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                            <?php if ($badge['points_bonus'] > 0): ?>
                            <div class="badge-pts"><i class="fas fa-bolt"></i> +<?php echo $badge['points_bonus']; ?> bonus pts</div>
                            <?php endif; ?>
                            <?php if ($earned): ?>
                            <div class="badge-foot earned-tag"><i class="fas fa-check-circle"></i> Earned!</div>
                            <?php else: ?>
                            <div class="badge-foot lock-tag"><i class="fas fa-lock"></i> Not yet earned</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /page-content -->
    </div><!-- /main-wrap -->
</div><!-- /layout -->

<script>
// Category filter
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