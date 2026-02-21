<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'leaderboard';

$filter = $_GET['domain'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = "is_active=1";
if ($filter !== 'all') $where .= " AND domain_interest='" . $db->real_escape_string($filter) . "'";
if ($search) $where .= " AND full_name LIKE '%" . $db->real_escape_string($search) . "%'";

$students = [];
$res = $db->query("SELECT id, full_name, domain_interest, total_points, college_name, year_of_study, profile_photo FROM internship_students WHERE $where ORDER BY total_points DESC LIMIT 100");
if ($res) while ($r = $res->fetch_assoc()) $students[] = $r;

$domains = [];
$dr = $db->query("SELECT DISTINCT domain_interest FROM internship_students WHERE is_active=1 AND domain_interest IS NOT NULL AND domain_interest!=''");
if ($dr) while ($r = $dr->fetch_assoc()) $domains[] = $r['domain_interest'];

// $myPts = (int)$student['total_points'];

// Calculate total points from points log
$pointsResult = $db->query("SELECT COALESCE(SUM(points), 0) as total FROM student_points_log WHERE student_id=$sid");
$totalPoints = (int)$pointsResult->fetch_assoc()['total'];
$myPts = $totalPoints;
$myRank = '-';
foreach ($students as $i => $s) { if ($s['id'] == $sid) { $myRank = $i + 1; break; } }

$taskScores = [];
$tr = $db->query("SELECT student_id, COUNT(*) as cnt FROM task_submissions WHERE status='approved' GROUP BY student_id");
if ($tr) while ($r = $tr->fetch_assoc()) $taskScores[$r['student_id']] = (int)$r['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Intern Leaderboard</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--sbw:258px;--o5:#f97316;--o4:#fb923c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--green:#22c55e;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);}
.page-wrap{margin-left:var(--sbw);min-height:100vh;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(248,250,252,0.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;gap:12px;}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.main-content{padding:24px 28px;}
.lb-hero{background:linear-gradient(135deg,var(--o5) 0%,var(--o4) 100%);border-radius:16px;padding:28px;color:#fff;margin-bottom:24px;position:relative;overflow:hidden;}
.lb-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.08);}
.lb-hero-title{font-size:1.5rem;font-weight:800;position:relative;z-index:1;}
.lb-hero-sub{font-size:.88rem;opacity:.85;margin-top:6px;position:relative;z-index:1;}
.my-rank-chip{display:inline-flex;align-items:center;gap:8px;margin-top:14px;padding:9px 18px;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);border-radius:24px;font-size:.88rem;font-weight:700;position:relative;z-index:1;}
.podium-row{display:flex;justify-content:center;align-items:flex-end;gap:16px;margin-bottom:28px;}
.podium-card{background:var(--card);border-radius:14px;border:1px solid var(--border);padding:20px 16px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:transform .2s;min-width:140px;}
.podium-card:hover{transform:translateY(-4px);}
.podium-card.p1{border-color:rgba(251,191,36,0.5);box-shadow:0 4px 20px rgba(251,191,36,0.2);min-width:160px;}
.podium-card.me{border-color:rgba(249,115,22,0.4);}
.p-rank{font-size:1.5rem;margin-bottom:8px;}
.p-avatar{width:52px;height:52px;border-radius:50%;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;color:#fff;}
.podium-card.p1 .p-avatar{background:linear-gradient(135deg,#fbbf24,#f59e0b);width:60px;height:60px;}
.podium-card.p2 .p-avatar{background:linear-gradient(135deg,#9ca3af,#6b7280);}
.podium-card.p3 .p-avatar{background:linear-gradient(135deg,#c4873d,#b87333);}
.p-name{font-size:.85rem;font-weight:700;color:var(--text);margin-bottom:3px;}
.p-domain{font-size:.7rem;color:var(--text3);margin-bottom:6px;}
.p-pts{font-size:1rem;font-weight:800;color:var(--o5);}
.filter-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
.fchip{padding:6px 13px;border-radius:20px;border:1.5px solid var(--border);background:var(--card);font-size:.78rem;font-weight:500;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.fchip:hover{border-color:var(--o5);color:var(--o5);text-decoration:none;}
.fchip.active{background:var(--o5);border-color:var(--o5);color:#fff;}
.srch{padding:7px 12px;border:1.5px solid var(--border);border-radius:20px;font-size:.82rem;font-family:inherit;outline:none;transition:all .2s;width:200px;margin-left:auto;}
.srch:focus{border-color:var(--o5);}
.lb-card{background:var(--card);border-radius:14px;border:1px solid var(--border);overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
.lb-head{display:grid;grid-template-columns:60px 1fr 150px 90px 80px;padding:11px 20px;background:var(--bg);border-bottom:1px solid var(--border);font-size:.73rem;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;}
.lb-row{display:grid;grid-template-columns:60px 1fr 150px 90px 80px;padding:13px 20px;border-bottom:1px solid var(--border);align-items:center;transition:background .15s;}
.lb-row:last-child{border-bottom:none;}
.lb-row:hover{background:#fafafa;}
.lb-row.me{background:rgba(249,115,22,0.04);border-left:3px solid var(--o5);}
.rn{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;background:var(--bg);color:var(--text3);border:1px solid var(--border);}
.rn.r1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;border:none;}
.rn.r2{background:linear-gradient(135deg,#9ca3af,#6b7280);color:#fff;border:none;}
.rn.r3{background:linear-gradient(135deg,#c4873d,#b87333);color:#fff;border:none;}
.stu-cell{display:flex;align-items:center;gap:9px;}
.stu-ava{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.88rem;flex-shrink:0;}
.stu-n{font-size:.84rem;font-weight:600;color:var(--text);}
.stu-c{font-size:.7rem;color:var(--text3);}
.you-tag{font-size:.6rem;font-weight:700;padding:1px 5px;background:rgba(249,115,22,0.15);color:var(--o5);border-radius:4px;margin-left:4px;}
.cert-tag{font-size:.6rem;font-weight:700;padding:1px 5px;background:rgba(34,197,94,0.12);color:#16a34a;border-radius:4px;margin-left:4px;}
.dom-cell{font-size:.78rem;color:var(--text2);}
.tasks-cell{font-size:.82rem;font-weight:600;color:var(--text);}
.pts-cell{font-size:.9rem;font-weight:800;color:var(--o5);text-align:right;}
.hide2{}

@media(max-width:900px){.lb-head,.lb-row{grid-template-columns:50px 1fr 80px;}.hide2{display:none;}}
@media(max-width:768px){.page-wrap{margin-left:0;}.topbar-hamburger{display:flex;}.main-content{padding:16px;}.podium-card{min-width:100px;padding:12px 8px;}.srch{margin-left:0;width:100%;}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">Marks Leaderboard</div>
    </div>
    <div class="main-content">
        <div class="lb-hero">
            <div class="lb-hero-title"><i class="fas fa-trophy"></i> Marks Leaderboard</div>
            <div class="lb-hero-sub">Rankings based on total points earned. 1200+ points = FREE internship certificate. Top 3 earn Outstanding grade!</div>
            <div class="my-rank-chip"><i class="fas fa-medal"></i> Your rank: #<?php echo $myRank; ?> &bull; <?php echo number_format($myPts); ?> pts<?php if ($myPts>=1200): ?> &bull; 🎓 Cert Eligible<?php endif; ?></div>
        </div>

        <?php if (count($students) >= 3): ?>
        <div class="podium-row">
            <?php
            $order = [[1,'p2','🥈'],[0,'p1','🥇'],[2,'p3','🥉']];
            foreach ($order as [$pos,$cls,$medal]):
                if (!isset($students[$pos])) continue;
                $s = $students[$pos];
            ?>
            <div class="podium-card <?php echo $cls; ?> <?php echo $s['id']==$sid?'me':''; ?>">
                <div class="p-rank"><?php echo $medal; ?></div>
                <div class="p-avatar"><?php echo strtoupper(substr($s['full_name'],0,1)); ?></div>
                <div class="p-name"><?php echo htmlspecialchars(explode(' ',$s['full_name'])[0]); ?><?php echo $s['id']==$sid?' (you)':''; ?></div>
                <div class="p-domain"><?php echo htmlspecialchars($s['domain_interest']?:'General'); ?></div>
                <div class="p-pts"><?php echo number_format($s['total_points']); ?> pts</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="GET">
            <div class="filter-row">
                <a href="?domain=all" class="fchip <?php echo $filter==='all'?'active':''; ?>">All</a>
                <?php foreach ($domains as $d): ?>
                <a href="?domain=<?php echo urlencode($d); ?>" class="fchip <?php echo $filter===$d?'active':''; ?>"><?php echo htmlspecialchars($d); ?></a>
                <?php endforeach; ?>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="srch" placeholder="Search…" onchange="this.form.submit()">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($filter); ?>">
            </div>
        </form>

        <div class="lb-card">
            <div class="lb-head"><div>Rank</div><div>Student</div><div class="hide2">Domain</div><div class="hide2">Tasks Done</div><div>Points</div></div>
            <?php foreach ($students as $i => $s):
                $pos=$i+1; $isMe=$s['id']==$sid; $certEl=$s['total_points']>=2000; $tc=$taskScores[$s['id']]??0;
            ?>
            <div class="lb-row <?php echo $isMe?'me':''; ?>">
                <div><div class="rn <?php echo $pos<=3?'r'.$pos:''; ?>"><?php echo $pos; ?></div></div>
                <div class="stu-cell">
                    <div class="stu-ava"><?php echo strtoupper(substr($s['full_name'],0,1)); ?></div>
                    <div>
                        <div class="stu-n"><?php echo htmlspecialchars(implode(' ',array_slice(explode(' ',$s['full_name']),0,2))); ?><?php echo $isMe?'<span class="you-tag">you</span>':''; ?><?php echo $certEl?'<span class="cert-tag">🎓 Cert</span>':''; ?></div>
                        <div class="stu-c"><?php echo htmlspecialchars($s['college_name']?:'–'); ?></div>
                    </div>
                </div>
                <div class="dom-cell hide2"><?php echo htmlspecialchars($s['domain_interest']?:'–'); ?></div>
                <div class="tasks-cell hide2"><?php echo $tc; ?> tasks</div>
                <div class="pts-cell"><?php echo number_format($s['total_points']); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($students)): ?><div style="text-align:center;padding:40px;color:var(--text3);">No students found.</div><?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>