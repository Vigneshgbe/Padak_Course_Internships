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

$activePage = 'certificates';

// Get all certificates for this student
$certificatesQuery = $db->query("
    SELECT ic.*, ib.batch_name 
    FROM internship_certificates ic
    LEFT JOIN internship_batches ib ON ic.batch_id = ib.id
    WHERE ic.student_id = $sid
    ORDER BY ic.created_at DESC
");

$certificates = [];
if ($certificatesQuery) {
    while ($row = $certificatesQuery->fetch_assoc()) {
        $certificates[] = $row;
    }
}

// Get certificate stats
$statsQuery = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_issued = 1 THEN 1 ELSE 0 END) as issued,
    SUM(CASE WHEN completion_grade = 'Outstanding' THEN 1 ELSE 0 END) as outstanding,
    SUM(CASE WHEN completion_grade = 'Excellent' THEN 1 ELSE 0 END) as excellent,
    SUM(total_points_earned) as total_points
    FROM internship_certificates 
    WHERE student_id = $sid
");
$stats = $statsQuery->fetch_assoc();

// Set default values if no certificates exist
if (!$stats || $stats['total'] == 0) {
    $stats = [
        'total' => 0,
        'issued' => 0,
        'outstanding' => 0,
        'excellent' => 0,
        'total_points' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Certificates</title>
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
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;--yellow:#eab308;--gold:#fbbf24;
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
.cert-header{
    background:linear-gradient(135deg,var(--purple) 0%,var(--blue) 100%);
    border-radius:16px;padding:28px 32px;margin-bottom:24px;
    color:#fff;position:relative;overflow:hidden;
    box-shadow:0 8px 24px rgba(139,92,246,0.25);
}
.cert-header::before{
    content:'';position:absolute;top:-50px;right:-50px;
    width:200px;height:200px;border-radius:50%;
    background:rgba(255,255,255,0.1);
}
.cert-header::after{
    content:'';position:absolute;bottom:-30px;left:-30px;
    width:150px;height:150px;border-radius:50%;
    background:rgba(255,255,255,0.08);
}
.cert-header-content{position:relative;z-index:1;}
.cert-header h1{font-size:1.8rem;font-weight:800;margin-bottom:6px;text-shadow:0 2px 4px rgba(0,0,0,0.1);}
.cert-header p{font-size:.9rem;opacity:.9;}

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
.stat-card.purple::before{background:var(--purple);}
.stat-card.blue::before{background:var(--blue);}
.stat-card.gold::before{background:var(--gold);}
.stat-card.green::before{background:var(--green);}
.stat-card.orange::before{background:var(--o5);}
.stat-icon{
    width:36px;height:36px;border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:.9rem;margin-bottom:10px;
}
.stat-card.purple .stat-icon{background:rgba(139,92,246,0.12);color:var(--purple);}
.stat-card.blue .stat-icon{background:rgba(59,130,246,0.12);color:var(--blue);}
.stat-card.gold .stat-icon{background:rgba(251,191,36,0.12);color:var(--gold);}
.stat-card.green .stat-icon{background:rgba(34,197,94,0.12);color:var(--green);}
.stat-card.orange .stat-icon{background:rgba(249,115,22,0.12);color:var(--o5);}
.stat-value{font-size:1.4rem;font-weight:800;color:var(--text);line-height:1;margin-bottom:4px;}
.stat-label{font-size:.76rem;color:var(--text2);font-weight:500;}

/* Certificate Grid */
.cert-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px;margin-bottom:24px;}
.cert-card{
    background:var(--card);border-radius:14px;
    border:2px solid var(--border);box-shadow:var(--shadow);
    overflow:hidden;transition:all .3s;position:relative;
}
.cert-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,0.12);}
.cert-card.issued{border-color:var(--green);}
.cert-card.pending{border-color:var(--yellow);}

.cert-visual{
    height:200px;position:relative;
    background:linear-gradient(135deg,var(--purple) 0%,var(--blue) 100%);
    display:flex;align-items:center;justify-content:center;
    overflow:hidden;
}
.cert-visual::before{
    content:'';position:absolute;inset:0;
    background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.cert-icon-big{
    font-size:4rem;color:rgba(255,255,255,0.3);
    position:relative;z-index:1;
}
.cert-badge{
    position:absolute;top:16px;right:16px;
    padding:6px 14px;border-radius:20px;
    font-size:.72rem;font-weight:700;
    backdrop-filter:blur(8px);
    display:flex;align-items:center;gap:5px;
}
.cert-badge.issued{background:rgba(34,197,94,0.2);color:#fff;border:1.5px solid rgba(255,255,255,0.3);}
.cert-badge.pending{background:rgba(234,179,8,0.2);color:#fff;border:1.5px solid rgba(255,255,255,0.3);}

.cert-body{padding:20px;}
.cert-batch{font-size:.8rem;color:var(--text3);font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
.cert-number{font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:12px;}
.cert-details{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}
.cert-detail-item{display:flex;align-items:center;gap:10px;font-size:.84rem;}
.cert-detail-icon{
    width:32px;height:32px;border-radius:8px;
    background:var(--bg);display:flex;align-items:center;justify-content:center;
    font-size:.8rem;color:var(--text3);
}
.cert-detail-text{flex:1;}
.cert-detail-label{font-size:.72rem;color:var(--text3);margin-bottom:2px;}
.cert-detail-value{font-weight:600;color:var(--text);}
.grade-badge{
    display:inline-flex;align-items:center;gap:4px;
    padding:4px 10px;border-radius:6px;
    font-size:.75rem;font-weight:700;
}
.grade-badge.outstanding{background:rgba(251,191,36,0.15);color:#ca8a04;}
.grade-badge.excellent{background:rgba(34,197,94,0.15);color:#16a34a;}
.grade-badge.good{background:rgba(59,130,246,0.15);color:#2563eb;}
.grade-badge.satisfactory{background:rgba(139,92,246,0.15);color:#7c3aed;}

.cert-actions{display:flex;gap:8px;}
.btn-cert{
    flex:1;padding:10px 16px;border-radius:9px;
    font-size:.84rem;font-weight:600;cursor:pointer;
    transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;
    text-decoration:none;border:none;
}
.btn-download{
    background:linear-gradient(135deg,var(--purple),var(--blue));
    color:#fff;box-shadow:0 4px 12px rgba(139,92,246,0.3);
}
.btn-download:hover{opacity:.9;transform:translateY(-2px);}
.btn-verify{
    background:var(--card);border:1.5px solid var(--border);
    color:var(--text2);
}
.btn-verify:hover{background:var(--bg);border-color:var(--purple);color:var(--purple);}
.btn-pending{
    background:var(--bg);border:1.5px solid var(--border);
    color:var(--text3);cursor:not-allowed;
}

/* Empty State */
.empty-state{
    text-align:center;padding:80px 20px;
    background:var(--card);border-radius:14px;
    border:1px solid var(--border);box-shadow:var(--shadow);
}
.empty-state i{font-size:4rem;margin-bottom:20px;display:block;color:var(--text3);opacity:.2;}
.empty-state h3{font-size:1.2rem;font-weight:700;color:var(--text);margin-bottom:8px;}
.empty-state p{font-size:.9rem;color:var(--text2);margin-bottom:20px;}
.empty-state .btn-primary{
    display:inline-flex;align-items:center;gap:8px;
    padding:12px 24px;border-radius:10px;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;font-size:.88rem;font-weight:600;
    text-decoration:none;box-shadow:0 4px 12px rgba(249,115,22,0.3);
}
.empty-state .btn-primary:hover{opacity:.9;transform:translateY(-2px);}

/* Verification Modal */
.modal-bg{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,0.6);z-index:999;
    align-items:center;justify-content:center;
    backdrop-filter:blur(4px);
}
.modal-bg.open{display:flex;}
.modal{
    background:var(--card);border-radius:16px;
    padding:32px;width:90%;max-width:500px;
    box-shadow:0 20px 60px rgba(0,0,0,0.3);
    animation:modalSlide .3s ease;
}
@keyframes modalSlide{from{opacity:0;transform:scale(0.9);}to{opacity:1;transform:scale(1);}}
.modal h3{font-size:1.2rem;font-weight:800;margin-bottom:8px;display:flex;align-items:center;gap:10px;}
.modal p{font-size:.88rem;color:var(--text2);margin-bottom:20px;}
.verify-input-group{margin-bottom:20px;}
.verify-input-group label{display:block;font-size:.84rem;font-weight:600;color:var(--text);margin-bottom:8px;}
.verify-input{
    width:100%;padding:12px 14px;
    border:2px solid var(--border);border-radius:10px;
    font-size:.95rem;font-family:'Courier New',monospace;
    font-weight:600;letter-spacing:1px;
    text-transform:uppercase;color:var(--text);
    background:var(--card);outline:none;
    transition:border-color .2s,box-shadow .2s;
}
.verify-input:focus{
    border-color:var(--purple);
    box-shadow:0 0 0 3px rgba(139,92,246,0.1);
}
.verify-result{
    padding:16px;border-radius:10px;
    font-size:.88rem;font-weight:600;
    display:none;align-items:center;gap:10px;
    margin-bottom:20px;
}
.verify-result.success{
    background:#f0fdf4;border:1.5px solid #bbf7d0;color:#166534;
    display:flex;
}
.verify-result.error{
    background:#fef2f2;border:1.5px solid #fecaca;color:#991b1b;
    display:flex;
}
.modal-actions{display:flex;gap:10px;}
.btn-modal{
    flex:1;padding:12px 20px;border-radius:10px;
    font-size:.88rem;font-weight:600;cursor:pointer;
    transition:all .2s;border:none;font-family:inherit;
}
.btn-modal-primary{
    background:linear-gradient(135deg,var(--purple),var(--blue));
    color:#fff;box-shadow:0 4px 12px rgba(139,92,246,0.3);
}
.btn-modal-primary:hover{opacity:.9;transform:translateY(-2px);}
.btn-modal-secondary{
    background:var(--card);border:1.5px solid var(--border);color:var(--text2);
}
.btn-modal-secondary:hover{background:var(--bg);}

@media(max-width:1200px){
    .stats-grid{grid-template-columns:repeat(3,1fr);}
    .cert-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr));}
}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .stats-grid{grid-template-columns:repeat(2,1fr);}
    .cert-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">My Certificates</div>
    </div>

    <div class="main-content">
        <!-- Header -->
        <div class="cert-header">
            <div class="cert-header-content">
                <h1><i class="fas fa-certificate"></i> My Certificates</h1>
                <p>Your earned certificates and internship completion records</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-certificate"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Certificates</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo number_format($stats['issued']); ?></div>
                <div class="stat-label">Issued</div>
            </div>
            <div class="stat-card gold">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-value"><?php echo number_format($stats['outstanding']); ?></div>
                <div class="stat-label">Outstanding</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-award"></i></div>
                <div class="stat-value"><?php echo number_format($stats['excellent']); ?></div>
                <div class="stat-label">Excellent</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_points']); ?></div>
                <div class="stat-label">Total Points</div>
            </div>
        </div>

        <!-- Certificates Grid -->
        <?php if (empty($certificates)): ?>
        <div class="empty-state">
            <i class="fas fa-certificate"></i>
            <h3>No Certificates Yet</h3>
            <p>Complete your internship tasks to earn certificates!</p>
            <a href="tasks.php" class="btn-primary">
                <!-- <i class="fas fa-tasks"></i> --> View Tasks
            </a>
        </div>
        <?php else: ?>
        <div class="cert-grid">
            <?php foreach ($certificates as $cert): ?>
            <div class="cert-card <?php echo $cert['is_issued'] ? 'issued' : 'pending'; ?>">
                <div class="cert-visual">
                    <i class="fas fa-certificate cert-icon-big"></i>
                    <div class="cert-badge <?php echo $cert['is_issued'] ? 'issued' : 'pending'; ?>">
                        <?php if ($cert['is_issued']): ?>
                            <i class="fas fa-check-circle"></i> Issued
                        <?php else: ?>
                            <i class="fas fa-clock"></i> Pending
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cert-body">
                    <?php if (!empty($cert['batch_name'])): ?>
                    <div class="cert-batch"><?php echo htmlspecialchars($cert['batch_name']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cert['certificate_number'])): ?>
                    <div class="cert-number">
                        <i class="fas fa-id-card fa-sm" style="color:var(--text3);"></i>
                        <?php echo htmlspecialchars($cert['certificate_number']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="cert-details">
                        <?php if (!empty($cert['issued_date'])): ?>
                        <div class="cert-detail-item">
                            <div class="cert-detail-icon"><i class="fas fa-calendar"></i></div>
                            <div class="cert-detail-text">
                                <div class="cert-detail-label">Issued Date</div>
                                <div class="cert-detail-value"><?php echo date('M d, Y', strtotime($cert['issued_date'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($cert['completion_grade'])): ?>
                        <div class="cert-detail-item">
                            <div class="cert-detail-icon"><i class="fas fa-star"></i></div>
                            <div class="cert-detail-text">
                                <div class="cert-detail-label">Grade</div>
                                <div class="cert-detail-value">
                                    <span class="grade-badge <?php echo strtolower($cert['completion_grade']); ?>">
                                        <?php echo htmlspecialchars($cert['completion_grade']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="cert-detail-item">
                            <div class="cert-detail-icon"><i class="fas fa-coins"></i></div>
                            <div class="cert-detail-text">
                                <div class="cert-detail-label">Points Earned</div>
                                <div class="cert-detail-value"><?php echo number_format($cert['total_points_earned'] ?? 0); ?> points</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cert-actions">
                        <?php if ($cert['is_issued']): ?>
                            <?php if (!empty($cert['certificate_file'])): ?>
                            <a href="<?php echo htmlspecialchars($cert['certificate_file']); ?>" target="_blank" class="btn-cert btn-download">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($cert['certificate_number'])): ?>
                            <button class="btn-cert btn-verify" onclick="openVerifyModal('<?php echo htmlspecialchars($cert['certificate_number']); ?>')">
                                <i class="fas fa-shield-alt"></i> Verify
                            </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-cert btn-pending" disabled>
                                <i class="fas fa-clock"></i> Certificate Pending
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal-bg" id="verifyModal" onclick="if(event.target===this)closeVerifyModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <h3><i class="fas fa-shield-alt" style="color:var(--purple);"></i> Verify Certificate</h3>
        <p>Enter a certificate number to verify its authenticity</p>
        
        <div class="verify-input-group">
            <label for="certNumber">Certificate Number</label>
            <input type="text" id="certNumber" class="verify-input" placeholder="CERT-XXXX-XXXX" autocomplete="off">
        </div>
        
        <div class="verify-result success" id="verifySuccess">
            <i class="fas fa-check-circle fa-lg"></i>
            <div>
                <div style="font-weight:800;margin-bottom:4px;">Certificate Verified!</div>
                <div style="font-size:.8rem;font-weight:500;opacity:.8;" id="verifyDetails"></div>
            </div>
        </div>
        
        <div class="verify-result error" id="verifyError">
            <i class="fas fa-times-circle fa-lg"></i>
            <div>Please check the verify certificate section!</div>
        </div>
        
        <div class="modal-actions">
            <button class="btn-modal btn-modal-secondary" onclick="closeVerifyModal()">Cancel</button>
            <button class="btn-modal btn-modal-primary" onclick="verifyCertificate()">
                <i class="fas fa-search"></i> Verify
            </button>
        </div>
    </div>
</div>

<script>
function openVerifyModal(certNumber = '') {
    document.getElementById('verifyModal').classList.add('open');
    document.getElementById('certNumber').value = certNumber;
    document.getElementById('verifySuccess').style.display = 'none';
    document.getElementById('verifyError').style.display = 'none';
    if (certNumber) {
        verifyCertificate();
    }
}

function closeVerifyModal() {
    document.getElementById('verifyModal').classList.remove('open');
    document.getElementById('certNumber').value = '';
    document.getElementById('verifySuccess').style.display = 'none';
    document.getElementById('verifyError').style.display = 'none';
}

function verifyCertificate() {
    const certNumber = document.getElementById('certNumber').value.trim().toUpperCase();
    if (!certNumber) {
        alert('Please enter a certificate number');
        return;
    }
    
    // Hide previous results
    document.getElementById('verifySuccess').style.display = 'none';
    document.getElementById('verifyError').style.display = 'none';
    
    // Make AJAX call to verify
    fetch('verify_certificate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'certificate_number=' + encodeURIComponent(certNumber)
    })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            document.getElementById('verifyDetails').innerHTML = 
                `Issued to <strong>${data.student_name}</strong> on ${data.issued_date}`;
            document.getElementById('verifySuccess').style.display = 'flex';
        } else {
            document.getElementById('verifyError').style.display = 'flex';
        }
    })
    .catch(err => {
        console.error('Verification error:', err);
        document.getElementById('verifyError').style.display = 'flex';
    });
}

// Allow Enter key to verify
document.getElementById('certNumber').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        verifyCertificate();
    }
});
</script>
</body>
</html>