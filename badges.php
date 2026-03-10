<?php
ob_start();
session_start();
require_once 'config.php';

$db = getPadakDB();

if (!isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];

// Student info
$studentRes = $db->query("SELECT * FROM internship_students WHERE id=$studentId AND is_active=1");
if (!$studentRes || $studentRes->num_rows === 0) {
    header('Location: index.php');
    exit;
}
$student = $studentRes->fetch_assoc();

// Student's earned badges
$earnedRes = $db->query("
    SELECT sb.*, bd.name, bd.description, bd.icon, bd.color_from, bd.color_to,
           bd.badge_tier, bd.category, bd.points_reward, sb.is_featured
    FROM student_badges sb
    JOIN badge_definitions bd ON bd.id = sb.badge_id
    WHERE sb.student_id = $studentId
    ORDER BY sb.is_featured DESC, sb.awarded_at DESC
");
$earnedBadges = [];
while ($row = $earnedRes->fetch_assoc()) $earnedBadges[] = $row;
$earnedIds = array_column($earnedBadges, 'badge_id');

// All active badges (to show locked ones)
$allBadgesRes = $db->query("SELECT * FROM badge_definitions WHERE is_active=1 ORDER BY badge_tier DESC, name ASC");
$allBadges = [];
while ($row = $allBadgesRes->fetch_assoc()) $allBadges[] = $row;

// Stats
$totalEarned  = count($earnedBadges);
$totalBadges  = count($allBadges);
$featuredBadge= null;
foreach ($earnedBadges as $b) { if ($b['is_featured']) { $featuredBadge = $b; break; } }
if (!$featuredBadge && !empty($earnedBadges)) $featuredBadge = $earnedBadges[0];

$tierOrder    = ['platinum'=>4,'gold'=>3,'silver'=>2,'bronze'=>1,'special'=>5];
$tierColors   = [
    'bronze'  => ['from'=>'#b45309','to'=>'#d97706','glow'=>'rgba(180,83,9,0.4)'],
    'silver'  => ['from'=>'#64748b','to'=>'#94a3b8','glow'=>'rgba(100,116,139,0.4)'],
    'gold'    => ['from'=>'#d97706','to'=>'#fbbf24','glow'=>'rgba(217,119,6,0.5)'],
    'platinum'=> ['from'=>'#6366f1','to'=>'#818cf8','glow'=>'rgba(99,102,241,0.5)'],
    'special' => ['from'=>'#f97316','to'=>'#fb923c','glow'=>'rgba(249,115,22,0.5)'],
];

// Unread notifications count
$notifCount = (int)$db->query("SELECT COUNT(*) c FROM student_notifications WHERE student_id=$studentId AND is_read=0")->fetch_assoc()['c'];

// Leaderboard-style badge count among all students
$topStudentsRes = $db->query("
    SELECT s.id, s.full_name, s.domain_interest,
           COUNT(sb.id) as badge_count
    FROM internship_students s
    LEFT JOIN student_badges sb ON sb.student_id = s.id
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY badge_count DESC
    LIMIT 5
");
$topStudents = [];
while ($row = $topStudentsRes->fetch_assoc()) $topStudents[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Badges – Padak Internship</title>
    <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        :root{
            --o5:#f97316;--o4:#fb923c;--o6:#ea580c;--o1:#fff7ed;--o2:#ffedd5;
            --bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;
            --border:#e2e8f0;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

        /* ── Header ── */
        .header{background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 12px rgba(0,0,0,0.15);}
        .header-left{display:flex;align-items:center;gap:14px;}
        .header-logo{width:44px;height:44px;background:linear-gradient(135deg,var(--o5),var(--o4));border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;box-shadow:0 4px 14px rgba(249,115,22,0.35);}
        .header-title{font-size:1.2rem;font-weight:800;}
        .header-subtitle{font-size:.75rem;opacity:.65;margin-top:2px;}
        .header-right{display:flex;align-items:center;gap:12px;}
        .back-btn{display:flex;align-items:center;gap:7px;padding:9px 16px;background:rgba(255,255,255,0.1);border:1.5px solid rgba(255,255,255,0.15);color:#fff;border-radius:9px;font-size:.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
        .back-btn:hover{background:rgba(255,255,255,0.2);}
        .notif-btn{position:relative;width:40px;height:40px;background:rgba(255,255,255,0.1);border:1.5px solid rgba(255,255,255,0.15);border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;font-size:1rem;transition:all .2s;}
        .notif-btn:hover{background:rgba(255,255,255,0.2);}
        .notif-dot{position:absolute;top:6px;right:7px;width:8px;height:8px;background:#ef4444;border-radius:50%;border:1.5px solid #1e293b;}

        /* ── Container ── */
        .page{max-width:1200px;margin:0 auto;padding:32px 24px;}

        /* ── Hero ── */
        .hero{background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);border-radius:20px;padding:36px 40px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden;}
        .hero::before{content:'';position:absolute;top:-40px;right:-40px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(249,115,22,0.2),transparent 70%);}
        .hero::after{content:'';position:absolute;bottom:-60px;left:-40px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(249,115,22,0.1),transparent 70%);}
        .hero-content{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;}
        .hero-left h1{font-size:2rem;font-weight:900;margin-bottom:6px;}
        .hero-left h1 span{background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .hero-left p{font-size:.95rem;opacity:.75;}
        .hero-stats{display:flex;gap:24px;}
        .hero-stat{text-align:center;}
        .hero-stat-val{font-size:2.2rem;font-weight:900;color:var(--o4);}
        .hero-stat-label{font-size:.72rem;opacity:.65;font-weight:600;text-transform:uppercase;letter-spacing:.04em;}

        /* ── Featured Badge ── */
        .featured-wrap{margin-bottom:28px;}
        .featured-card{background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:18px;padding:28px 32px;display:flex;align-items:center;gap:32px;position:relative;overflow:hidden;border:1.5px solid rgba(249,115,22,0.3);}
        .featured-card::before{content:'FEATURED';position:absolute;top:16px;right:20px;font-size:.65rem;font-weight:800;letter-spacing:.1em;color:var(--o4);opacity:.7;}
        .featured-glow{position:absolute;top:-30px;left:100px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,var(--fg-color,rgba(249,115,22,0.3)),transparent 70%);pointer-events:none;}
        .featured-icon{width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 40px var(--fi-glow,rgba(249,115,22,0.5));}
        .featured-icon i{font-size:2.8rem;color:#fff;}
        .featured-info{color:#fff;flex:1;}
        .featured-info h2{font-size:1.5rem;font-weight:800;margin-bottom:6px;}
        .featured-info p{font-size:.9rem;opacity:.7;margin-bottom:12px;}
        .featured-tier{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:8px;font-size:.78rem;font-weight:700;}
        .featured-date{font-size:.78rem;opacity:.5;margin-top:10px;}

        /* ── Section headers ── */
        .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
        .section-title{font-size:1.15rem;font-weight:800;color:var(--text);display:flex;align-items:center;gap:10px;}
        .section-title i{color:var(--o5);}

        /* ── Filter tabs ── */
        .filter-tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;}
        .filter-tab{padding:7px 16px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.8rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .2s;}
        .filter-tab:hover{border-color:var(--o5);color:var(--o5);}
        .filter-tab.active{background:var(--o5);border-color:var(--o5);color:#fff;}

        /* ── Badge grid ── */
        .badges-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:16px;margin-bottom:28px;}
        .badge-card{background:var(--card);border-radius:16px;padding:24px 18px;text-align:center;border:1.5px solid var(--border);transition:all .3s;position:relative;overflow:hidden;cursor:pointer;}
        .badge-card.earned{border-color:transparent;}
        .badge-card.earned:hover{transform:translateY(-4px);}
        .badge-card.locked{opacity:.55;filter:grayscale(1);}
        .badge-card.locked .badge-icon-wrap{background:var(--border)!important;box-shadow:none!important;}
        .badge-card .earned-glow{position:absolute;inset:0;opacity:.07;pointer-events:none;}
        .badge-card .lock-overlay{position:absolute;top:10px;right:10px;width:24px;height:24px;border-radius:50%;background:rgba(100,116,139,0.2);display:flex;align-items:center;justify-content:center;font-size:.65rem;color:var(--text3);}
        .badge-card .earned-check{position:absolute;top:10px;right:10px;width:24px;height:24px;border-radius:50%;background:rgba(34,197,94,0.15);display:flex;align-items:center;justify-content:center;font-size:.65rem;color:#16a34a;}
        .badge-icon-wrap{width:68px;height:68px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;transition:all .3s;}
        .badge-card.earned .badge-icon-wrap{animation:badgePulse 3s ease-in-out infinite;}
        @keyframes badgePulse{0%,100%{transform:scale(1);}50%{transform:scale(1.06);}}
        .badge-icon-wrap i{font-size:1.8rem;color:#fff;}
        .badge-name{font-weight:700;font-size:.9rem;color:var(--text);margin-bottom:6px;}
        .badge-desc{font-size:.72rem;color:var(--text3);line-height:1.5;margin-bottom:10px;}
        .badge-tier-chip{display:inline-flex;align-items:center;padding:3px 10px;border-radius:6px;font-size:.68rem;font-weight:700;}
        .badge-pts{font-size:.7rem;color:var(--text3);margin-top:6px;}
        .badge-awarded-date{font-size:.68rem;color:var(--green);margin-top:5px;font-weight:600;}

        /* ── Shimmer on earned ── */
        .badge-card.earned::after{content:'';position:absolute;top:-50%;left:-60%;width:40%;height:200%;background:linear-gradient(105deg,transparent,rgba(255,255,255,0.15),transparent);transform:skewX(-25deg);animation:shimmer 4s ease-in-out infinite;}
        @keyframes shimmer{0%{left:-60%;}100%{left:160%;}}

        /* ── Two-col layout ── */
        .two-col{display:grid;grid-template-columns:1fr 320px;gap:24px;margin-bottom:28px;}

        /* ── Leaderboard ── */
        .leaderboard-card{background:var(--card);border-radius:16px;border:1px solid var(--border);overflow:hidden;}
        .lb-header{padding:18px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:.95rem;display:flex;align-items:center;gap:8px;}
        .lb-header i{color:var(--o5);}
        .lb-row{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border);transition:background .15s;}
        .lb-row:last-child{border-bottom:none;}
        .lb-row:hover{background:var(--bg);}
        .lb-row.me{background:var(--o1);border-left:3px solid var(--o5);}
        .lb-rank{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;flex-shrink:0;}
        .lb-rank.rank-1{background:linear-gradient(135deg,#d97706,#fbbf24);color:#fff;box-shadow:0 3px 10px rgba(217,119,6,0.4);}
        .lb-rank.rank-2{background:linear-gradient(135deg,#64748b,#94a3b8);color:#fff;}
        .lb-rank.rank-3{background:linear-gradient(135deg,#b45309,#d97706);color:#fff;}
        .lb-rank.rank-n{background:var(--bg);color:var(--text2);border:1px solid var(--border);}
        .lb-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.82rem;flex-shrink:0;}
        .lb-info{flex:1;}
        .lb-name{font-size:.85rem;font-weight:600;color:var(--text);}
        .lb-domain{font-size:.72rem;color:var(--text3);}
        .lb-count{font-size:.9rem;font-weight:800;color:var(--o5);}

        /* ── Empty / locked state ── */
        .empty-state{text-align:center;padding:48px 20px;color:var(--text3);}
        .empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
        .empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}

        /* ── Modal ── */
        .badge-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);}
        .badge-modal.active{display:flex;}
        .badge-modal-content{background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:20px;width:100%;max-width:460px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,0.4);color:#fff;border:1.5px solid rgba(255,255,255,0.08);}
        .bmc-top{padding:36px 32px;text-align:center;position:relative;}
        .bmc-icon{width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;}
        .bmc-icon i{font-size:2.8rem;color:#fff;}
        .bmc-name{font-size:1.5rem;font-weight:900;margin-bottom:8px;}
        .bmc-desc{font-size:.9rem;opacity:.7;line-height:1.6;}
        .bmc-bottom{background:rgba(0,0,0,0.25);padding:24px 32px;display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .bmc-stat{text-align:center;}
        .bmc-stat-val{font-size:1.3rem;font-weight:800;color:var(--o4);}
        .bmc-stat-label{font-size:.72rem;opacity:.55;margin-top:4px;}
        .bmc-footer{padding:20px 32px;border-top:1px solid rgba(255,255,255,0.08);display:flex;justify-content:center;}
        .bmc-close{padding:10px 28px;background:rgba(255,255,255,0.1);border:1.5px solid rgba(255,255,255,0.2);color:#fff;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s;}
        .bmc-close:hover{background:rgba(255,255,255,0.2);}
        .bmc-locked-banner{background:rgba(100,116,139,0.2);border:1px solid rgba(100,116,139,0.3);border-radius:10px;padding:12px 16px;text-align:center;font-size:.8rem;opacity:.7;margin-top:14px;}

        @media(max-width:900px){.two-col{grid-template-columns:1fr;}.hero-left h1{font-size:1.5rem;}}
        @media(max-width:600px){.badges-grid{grid-template-columns:repeat(2,1fr);}.page{padding:20px 14px;}}
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <div class="header-logo"><i class="fas fa-award"></i></div>
        <div>
            <div class="header-title">My Badges</div>
            <div class="header-subtitle">Padak Internship Program</div>
        </div>
    </div>
    <div class="header-right">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="notifications.php" class="notif-btn">
            <i class="fas fa-bell"></i>
            <?php if ($notifCount > 0): ?><div class="notif-dot"></div><?php endif; ?>
        </a>
    </div>
</div>

<div class="page">

    <!-- Hero -->
    <div class="hero">
        <div class="hero-content">
            <div class="hero-left">
                <h1>Your <span>Achievement</span> Wall</h1>
                <p>Badges you've earned through outstanding performance &amp; dedication.</p>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val"><?php echo $totalEarned; ?></div>
                    <div class="hero-stat-label">Earned</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?php echo $totalBadges; ?></div>
                    <div class="hero-stat-label">Total</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?php echo $totalBadges > 0 ? round(($totalEarned/$totalBadges)*100) : 0; ?>%</div>
                    <div class="hero-stat-label">Collected</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Badge -->
    <?php if ($featuredBadge):
        $ftc  = $tierColors[$featuredBadge['badge_tier']] ?? $tierColors['bronze'];
        $fGrad = "linear-gradient(135deg,{$ftc['from']},{$ftc['to']})";
    ?>
    <div class="featured-wrap">
        <div class="featured-card">
            <div class="featured-glow" style="--fg-color:<?php echo $ftc['glow']; ?>"></div>
            <div class="featured-icon"
                 style="background:<?php echo $fGrad; ?>;--fi-glow:<?php echo $ftc['glow']; ?>">
                <i class="<?php echo htmlspecialchars($featuredBadge['icon']); ?>"></i>
            </div>
            <div class="featured-info">
                <h2><?php echo htmlspecialchars($featuredBadge['name']); ?></h2>
                <p><?php echo htmlspecialchars($featuredBadge['description']); ?></p>
                <span class="featured-tier"
                      style="background:<?php echo $ftc['glow']; ?>;color:#fff;">
                    <?php echo strtoupper($featuredBadge['badge_tier']); ?> TIER
                </span>
                <?php if ($featuredBadge['award_reason']): ?>
                <p style="margin-top:10px;font-size:.82rem;opacity:.6;font-style:italic;">
                    "<?php echo htmlspecialchars($featuredBadge['award_reason']); ?>"
                </p>
                <?php endif; ?>
                <div class="featured-date">
                    <i class="fas fa-calendar-alt"></i>
                    Awarded <?php echo date('F d, Y', strtotime($featuredBadge['awarded_at'])); ?>
                    by <?php echo htmlspecialchars($featuredBadge['awarded_by']); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Two column layout -->
    <div class="two-col">
        <div>
            <!-- Earned Badges -->
            <div class="section-header">
                <div class="section-title"><i class="fas fa-trophy"></i> All Badges</div>
                <div class="filter-tabs">
                    <button class="filter-tab active" onclick="bmFilter('all',this)">All</button>
                    <button class="filter-tab" onclick="bmFilter('earned',this)">Earned (<?php echo $totalEarned; ?>)</button>
                    <button class="filter-tab" onclick="bmFilter('locked',this)">Locked (<?php echo $totalBadges - $totalEarned; ?>)</button>
                </div>
            </div>

            <?php if (empty($allBadges)): ?>
            <div class="empty-state">
                <i class="fas fa-medal"></i>
                <h3>No badges available yet</h3>
                <p>Check back after completing tasks!</p>
            </div>
            <?php else: ?>
            <div class="badges-grid" id="bmGrid">
                <?php foreach ($allBadges as $badge):
                    $isEarned  = in_array($badge['id'], $earnedIds);
                    $earnedInfo= null;
                    foreach ($earnedBadges as $eb) {
                        if ($eb['badge_id'] == $badge['id']) { $earnedInfo = $eb; break; }
                    }
                    $tc    = $tierColors[$badge['badge_tier']] ?? $tierColors['bronze'];
                    $grad  = "linear-gradient(135deg,{$tc['from']},{$tc['to']})";
                    $shadow= $isEarned ? "0 8px 24px {$tc['glow']}" : 'none';
                    $tierBg  = $isEarned ? 'rgba(249,115,22,0.1)' : 'rgba(100,116,139,0.1)';
                    $tierCol = $isEarned ? 'var(--o6)' : 'var(--text3)';
                ?>
                <div class="badge-card <?php echo $isEarned ? 'earned' : 'locked'; ?>"
                     data-earned="<?php echo $isEarned ? '1' : '0'; ?>"
                     onclick="bmShowDetail(<?php echo (int)$badge['id']; ?>, <?php echo $isEarned ? 'true' : 'false'; ?>)"
                     data-id="<?php echo (int)$badge['id']; ?>">

                    <?php if ($isEarned): ?>
                    <div class="earned-glow" style="background:<?php echo $grad; ?>"></div>
                    <div class="earned-check"><i class="fas fa-check"></i></div>
                    <?php else: ?>
                    <div class="lock-overlay"><i class="fas fa-lock"></i></div>
                    <?php endif; ?>

                    <div class="badge-icon-wrap"
                         style="background:<?php echo $isEarned ? $grad : '#e2e8f0'; ?>;box-shadow:<?php echo $shadow; ?>">
                        <i class="<?php echo htmlspecialchars($badge['icon']); ?>"
                           style="color:<?php echo $isEarned ? '#fff' : '#94a3b8'; ?>"></i>
                    </div>

                    <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                    <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>

                    <span class="badge-tier-chip"
                          style="background:<?php echo $tierBg; ?>;color:<?php echo $tierCol; ?>">
                        <?php echo ucfirst($badge['badge_tier']); ?>
                    </span>
                    <div class="badge-pts">+<?php echo $badge['points_reward']; ?> pts</div>

                    <?php if ($isEarned && $earnedInfo): ?>
                    <div class="badge-awarded-date">
                        <i class="fas fa-check-circle"></i>
                        <?php echo date('M d, Y', strtotime($earnedInfo['awarded_at'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Leaderboard -->
        <div>
            <div class="section-header">
                <div class="section-title"><i class="fas fa-ranking-star"></i> Badge Leaders</div>
            </div>
            <div class="leaderboard-card">
                <div class="lb-header"><i class="fas fa-medal"></i> Top Badge Collectors</div>
                <?php foreach ($topStudents as $idx => $ts):
                    $rank  = $idx + 1;
                    $rankClass = $rank <= 3 ? "rank-$rank" : "rank-n";
                    $isMe  = ($ts['id'] == $studentId);
                ?>
                <div class="lb-row <?php echo $isMe ? 'me' : ''; ?>">
                    <div class="lb-rank <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                    <div class="lb-avatar"><?php echo strtoupper(substr($ts['full_name'],0,2)); ?></div>
                    <div class="lb-info">
                        <div class="lb-name"><?php echo htmlspecialchars($ts['full_name']); ?> <?php echo $isMe ? '<span style="font-size:.68rem;color:var(--o5);font-weight:700">(You)</span>' : ''; ?></div>
                        <div class="lb-domain"><?php echo htmlspecialchars($ts['domain_interest'] ?: 'Intern'); ?></div>
                    </div>
                    <div class="lb-count"><?php echo $ts['badge_count']; ?> 🏅</div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($topStudents)): ?>
                <div style="padding:28px;text-align:center;color:var(--text3);font-size:.85rem;">No data yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Badge Detail Modal -->
<div id="bmModal" class="badge-modal">
    <div class="badge-modal-content">
        <div class="bmc-top">
            <div class="bmc-icon" id="bmModalIcon">
                <i id="bmModalIconI" class="fas fa-award"></i>
            </div>
            <div class="bmc-name" id="bmModalName">Badge Name</div>
            <div class="bmc-desc" id="bmModalDesc">Description here</div>
            <div id="bmModalReason" style="display:none;margin-top:12px;font-size:.82rem;opacity:.65;font-style:italic;"></div>
            <div id="bmModalLocked" class="bmc-locked-banner" style="display:none;">
                <i class="fas fa-lock"></i> Complete outstanding tasks to unlock this badge
            </div>
        </div>
        <div class="bmc-bottom" id="bmModalBottom">
            <div class="bmc-stat">
                <div class="bmc-stat-val" id="bmModalTier">Gold</div>
                <div class="bmc-stat-label">Tier</div>
            </div>
            <div class="bmc-stat">
                <div class="bmc-stat-val" id="bmModalPts">0</div>
                <div class="bmc-stat-label">Points</div>
            </div>
        </div>
        <div class="bmc-footer">
            <button class="bmc-close" onclick="document.getElementById('bmModal').classList.remove('active')">Close</button>
        </div>
    </div>
</div>

<!-- Badge data for JS -->
<script type="application/json" id="bmAllBadgesData">
<?php
$badgesForJS = [];
foreach ($allBadges as $b) {
    $isEarned   = in_array($b['id'], $earnedIds);
    $earnedInfo = null;
    foreach ($earnedBadges as $eb) {
        if ($eb['badge_id'] == $b['id']) { $earnedInfo = $eb; break; }
    }
    $tc = $tierColors[$b['badge_tier']] ?? $tierColors['bronze'];
    $badgesForJS[(int)$b['id']] = [
        'id'           => (int)$b['id'],
        'name'         => $b['name'],
        'description'  => $b['description'],
        'icon'         => $b['icon'],
        'color_from'   => $tc['from'],
        'color_to'     => $tc['to'],
        'glow'         => $tc['glow'],
        'badge_tier'   => $b['badge_tier'],
        'category'     => $b['category'],
        'points_reward'=> (int)$b['points_reward'],
        'earned'       => $isEarned,
        'awarded_at'   => $earnedInfo ? $earnedInfo['awarded_at'] : null,
        'award_reason' => $earnedInfo ? $earnedInfo['award_reason'] : null,
        'awarded_by'   => $earnedInfo ? $earnedInfo['awarded_by'] : null,
    ];
}
echo json_encode($badgesForJS, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>
</script>

<script>
(function() {
    var bmData = {};
    try { bmData = JSON.parse(document.getElementById('bmAllBadgesData').textContent); } catch(e) {}

    // Filter
    window.bmFilter = function(type, btn) {
        document.querySelectorAll('.filter-tab').forEach(function(t){ t.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.badge-card').forEach(function(c) {
            var earned = c.getAttribute('data-earned') === '1';
            if (type === 'all')    c.style.display = '';
            if (type === 'earned') c.style.display = earned ? '' : 'none';
            if (type === 'locked') c.style.display = !earned ? '' : 'none';
        });
    };

    // Detail modal
    window.bmShowDetail = function(id, isEarned) {
        var b = bmData[id];
        if (!b) return;
        var grad = 'linear-gradient(135deg,' + b.color_from + ',' + b.color_to + ')';

        var iconEl = document.getElementById('bmModalIcon');
        iconEl.style.background = isEarned ? grad : '#e2e8f0';
        iconEl.style.boxShadow  = isEarned ? '0 0 40px ' + b.glow : 'none';
        document.getElementById('bmModalIconI').className = b.icon;
        document.getElementById('bmModalIconI').style.color = isEarned ? '#fff' : '#94a3b8';
        document.getElementById('bmModalName').textContent = b.name;
        document.getElementById('bmModalDesc').textContent = b.description;
        document.getElementById('bmModalTier').textContent = b.badge_tier.charAt(0).toUpperCase() + b.badge_tier.slice(1);
        document.getElementById('bmModalPts').textContent  = '+' + b.points_reward;

        var reasonEl = document.getElementById('bmModalReason');
        var lockedEl = document.getElementById('bmModalLocked');
        if (isEarned && b.award_reason) {
            reasonEl.style.display = '';
            reasonEl.textContent = '"' + b.award_reason + '"';
        } else {
            reasonEl.style.display = 'none';
        }
        lockedEl.style.display = !isEarned ? '' : 'none';

        document.getElementById('bmModal').classList.add('active');
    };

    document.getElementById('bmModal').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
})();
</script>
</body>
</html>