<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }

$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];

// Fetch complete student data including total_points
$studentQuery = $db->query("SELECT * FROM internship_students WHERE id = $sid");
$student = $studentQuery->fetch_assoc();

$activePage = 'profile';

$errors = [];
$successMessage = '';
$generalError = '';

// Predefined avatar options
$avatarOptions = [
    'avatar1.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix&backgroundColor=b6e3f4',
    'avatar2.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Aneka&backgroundColor=c0aede',
    'avatar3.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Jasmine&backgroundColor=ffd5dc',
    'avatar4.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Max&backgroundColor=ffdfbf',
    'avatar5.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Luna&backgroundColor=d1f4dd',
    'avatar6.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Charlie&backgroundColor=ffeaa7',
    'avatar7.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Oliver&backgroundColor=74b9ff',
    'avatar8.png' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=Sophie&backgroundColor=fab1a0',
];

$domainOptions = [
    'Web Development', 'Mobile Development', 'Data Science', 'Machine Learning / AI',
    'UI/UX Design', 'DevOps / Cloud', 'Cybersecurity', 'Digital Marketing',
    'Business Analytics', 'Content Writing', 'Others'
];

$yearOptions = ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $collegeName = trim($_POST['college_name'] ?? '');
    $degree = trim($_POST['degree'] ?? '');
    $yearOfStudy = trim($_POST['year_of_study'] ?? '');
    $domainInterest = trim($_POST['domain_interest'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
    $githubUrl = trim($_POST['github_url'] ?? '');
    $profilePhoto = trim($_POST['profile_photo'] ?? '');
    
    // Validation
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($fullName) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters';
    }
    
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number';
    }
    
    if (!empty($linkedinUrl) && !filter_var($linkedinUrl, FILTER_VALIDATE_URL)) {
        $errors['linkedin_url'] = 'Enter a valid LinkedIn URL';
    }
    
    if (!empty($githubUrl) && !filter_var($githubUrl, FILTER_VALIDATE_URL)) {
        $errors['github_url'] = 'Enter a valid GitHub URL';
    }
    
    if (!in_array($yearOfStudy, $yearOptions)) {
        $yearOfStudy = '1st Year';
    }
    
    if (empty($errors)) {
        // Update profile
        $stmt = $db->prepare("UPDATE internship_students SET 
            full_name = ?,
            phone = ?,
            college_name = ?,
            degree = ?,
            year_of_study = ?,
            domain_interest = ?,
            bio = ?,
            linkedin_url = ?,
            github_url = ?,
            profile_photo = ?
            WHERE id = ?");
        
        $stmt->bind_param("ssssssssssi", 
            $fullName, $phone, $collegeName, $degree, $yearOfStudy,
            $domainInterest, $bio, $linkedinUrl, $githubUrl, $profilePhoto, $sid
        );
        
        if ($stmt->execute()) {
            $successMessage = 'Profile updated successfully!';
            // Refresh student data
            $_SESSION['student_name'] = $fullName;
            $student = $auth->getCurrentStudent();
        } else {
            $generalError = 'Failed to update profile. Please try again.';
        }
    }
}

// Get user stats
$statsQuery = $db->query("SELECT 
    (SELECT COUNT(*) FROM task_submissions WHERE student_id=$sid AND status='approved') as completed_tasks,
    (SELECT COUNT(*) FROM chat_messages WHERE sender_id=$sid) as messages_sent,
    (SELECT COUNT(*) FROM chat_room_members WHERE student_id=$sid) as chat_rooms
");
$stats = $statsQuery->fetch_assoc();

// Get recent activity
$recentActivity = [];
$activityQuery = $db->query("
    SELECT 'task' as type, t.title as title, ts.submitted_at as date 
    FROM task_submissions ts 
    JOIN internship_tasks t ON t.id = ts.task_id 
    WHERE ts.student_id = $sid 
    UNION ALL
    SELECT 'points' as type, pl.reason as title, pl.awarded_at as date 
    FROM student_points_log pl 
    WHERE pl.student_id = $sid
    ORDER BY date DESC LIMIT 5
");
if ($activityQuery) {
    while ($row = $activityQuery->fetch_assoc()) {
        $recentActivity[] = $row;
    }
}

$initials = strtoupper(substr($student['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile - Padak Internships</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;--o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --bg:#f8fafc;--card:#ffffff;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;
    --border:#e2e8f0;
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;
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

/* Profile Header */
.profile-header{
    background:linear-gradient(135deg,var(--o5) 0%,var(--o4) 100%);
    border-radius:16px;padding:32px;margin-bottom:24px;
    color:#fff;position:relative;overflow:hidden;
    box-shadow:0 8px 24px rgba(249,115,22,0.25);
}
.profile-header::before{
    content:'';position:absolute;top:-50px;right:-50px;
    width:200px;height:200px;border-radius:50%;
    background:rgba(255,255,255,0.1);
}
.profile-header::after{
    content:'';position:absolute;bottom:-30px;left:-30px;
    width:150px;height:150px;border-radius:50%;
    background:rgba(255,255,255,0.08);
}
.profile-top{display:flex;align-items:flex-start;gap:24px;position:relative;z-index:1;}
.profile-avatar-wrap{position:relative;}
.profile-avatar{
    width:120px;height:120px;border-radius:50%;
    background:rgba(255,255,255,0.2);
    display:flex;align-items:center;justify-content:center;
    font-size:2.5rem;font-weight:800;color:#fff;
    border:4px solid rgba(255,255,255,0.3);
    box-shadow:0 8px 24px rgba(0,0,0,0.2);
}
.profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.edit-avatar-btn{
    position:absolute;bottom:0;right:0;
    width:36px;height:36px;border-radius:50%;
    background:var(--card);color:var(--o5);
    border:3px solid var(--o5);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all .2s;
    box-shadow:0 2px 8px rgba(0,0,0,0.15);
}
.edit-avatar-btn:hover{transform:scale(1.1);}
.profile-info{flex:1;}
.profile-name{font-size:1.8rem;font-weight:800;margin-bottom:4px;text-shadow:0 2px 4px rgba(0,0,0,0.1);}
.profile-email{font-size:.9rem;opacity:.9;margin-bottom:12px;}
.profile-meta{display:flex;gap:20px;flex-wrap:wrap;}
.profile-meta-item{display:flex;align-items:center;gap:6px;font-size:.85rem;opacity:.95;}
.profile-meta-item i{opacity:.8;}
.profile-actions{display:flex;gap:10px;}
.btn-edit{
    padding:10px 20px;border-radius:10px;border:2px solid rgba(255,255,255,0.3);
    background:rgba(255,255,255,0.15);color:#fff;
    font-size:.88rem;font-weight:600;cursor:pointer;
    backdrop-filter:blur(8px);transition:all .2s;
    display:flex;align-items:center;gap:6px;
}
.btn-edit:hover{background:rgba(255,255,255,0.25);transform:translateY(-2px);}

/* Stats Cards */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{
    background:var(--card);border-radius:12px;padding:20px;
    border:1px solid var(--border);box-shadow:var(--shadow);
    position:relative;overflow:hidden;transition:transform .2s;
}
.stat-card:hover{transform:translateY(-2px);}
.stat-card::before{
    content:'';position:absolute;top:-10px;right:-10px;
    width:60px;height:60px;border-radius:50%;opacity:.08;
}
.stat-card.orange::before{background:var(--o5);}
.stat-card.blue::before{background:var(--blue);}
.stat-card.green::before{background:var(--green);}
.stat-card.purple::before{background:var(--purple);}
.stat-icon{
    width:40px;height:40px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:1rem;margin-bottom:12px;
}
.stat-card.orange .stat-icon{background:rgba(249,115,22,0.12);color:var(--o5);}
.stat-card.blue .stat-icon{background:rgba(59,130,246,0.12);color:var(--blue);}
.stat-card.green .stat-icon{background:rgba(34,197,94,0.12);color:var(--green);}
.stat-card.purple .stat-icon{background:rgba(139,92,246,0.12);color:var(--purple);}
.stat-value{font-size:1.6rem;font-weight:800;color:var(--text);line-height:1;margin-bottom:4px;}
.stat-label{font-size:.78rem;color:var(--text2);font-weight:500;}

/* Two Column Layout */
.two-col{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;}

/* Section Card */
.section-card{
    background:var(--card);border-radius:14px;
    border:1px solid var(--border);box-shadow:var(--shadow);
    overflow:hidden;
}
.section-head{
    padding:18px 20px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
}
.section-title{font-size:.95rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.section-body{padding:20px;}

/* Form Styles */
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:.84rem;font-weight:600;color:var(--text);margin-bottom:7px;}
.form-label .optional{font-size:.72rem;font-weight:400;color:var(--text3);}
.form-input,.form-textarea,.form-select{
    width:100%;padding:11px 14px;
    border:1.5px solid var(--border);border-radius:9px;
    font-size:.9rem;font-family:inherit;color:var(--text);
    background:var(--card);outline:none;
    transition:border-color .2s,box-shadow .2s;
}
.form-input:focus,.form-textarea:focus,.form-select:focus{
    border-color:var(--o5);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}
.form-input.is-error,.form-textarea.is-error,.form-select.is-error{
    border-color:var(--red);
}
.form-textarea{resize:vertical;min-height:80px;}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23475569' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;background-size:20px;padding-right:36px;}
.field-error{display:flex;align-items:center;gap:4px;margin-top:5px;font-size:.78rem;color:var(--red);}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* Avatar Grid */
.avatar-grid{
    display:grid;grid-template-columns:repeat(4,1fr);gap:12px;
    margin-bottom:16px;
}
.avatar-option{
    aspect-ratio:1;border-radius:12px;
    border:3px solid var(--border);
    cursor:pointer;transition:all .2s;
    overflow:hidden;position:relative;
}
.avatar-option img{width:100%;height:100%;object-fit:cover;}
.avatar-option:hover{border-color:var(--o4);transform:scale(1.05);}
.avatar-option.selected{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.2);}
.avatar-option.selected::after{
    content:'\f058';font-family:'Font Awesome 6 Free';font-weight:900;
    position:absolute;top:6px;right:6px;
    width:24px;height:24px;border-radius:50%;
    background:var(--o5);color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-size:.7rem;
}

/* Buttons */
.btn-primary{
    padding:11px 24px;border-radius:10px;border:none;
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;font-size:.88rem;font-weight:600;
    cursor:pointer;box-shadow:0 4px 12px rgba(249,115,22,0.3);
    transition:all .2s;font-family:inherit;
}
.btn-primary:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 6px 20px rgba(249,115,22,0.4);}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;}
.btn-secondary{
    padding:11px 24px;border-radius:10px;
    border:1.5px solid var(--border);background:var(--card);
    color:var(--text2);font-size:.88rem;font-weight:600;
    cursor:pointer;transition:all .2s;font-family:inherit;
}
.btn-secondary:hover{background:var(--bg);}

/* Activity List */
.activity-list{display:flex;flex-direction:column;gap:4px;}
.activity-item{
    display:flex;align-items:center;gap:10px;
    padding:12px 14px;border-radius:9px;
    transition:background .15s;
}
.activity-item:hover{background:#fafafa;}
.activity-icon{
    width:32px;height:32px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-size:.8rem;flex-shrink:0;
}
.activity-icon.task{background:rgba(59,130,246,0.12);color:var(--blue);}
.activity-icon.points{background:rgba(249,115,22,0.12);color:var(--o5);}
.activity-info{flex:1;min-width:0;}
.activity-title{font-size:.84rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.activity-time{font-size:.72rem;color:var(--text3);margin-top:2px;}

.empty-state{text-align:center;padding:40px 20px;color:var(--text3);}
.empty-state i{font-size:2rem;margin-bottom:10px;display:block;opacity:.3;}

/* Alert */
.alert{
    display:flex;align-items:center;gap:10px;
    padding:12px 16px;border-radius:10px;
    font-size:.86rem;font-weight:500;
    margin-bottom:16px;animation:slideDown .3s ease;
}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}

/* Modal */
.modal-bg{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,0.5);z-index:999;
    align-items:center;justify-content:center;
    backdrop-filter:blur(4px);
}
.modal-bg.open{display:flex;}
.modal{
    background:var(--card);border-radius:16px;
    padding:28px;width:90%;max-width:500px;
    box-shadow:0 20px 60px rgba(0,0,0,0.3);
    animation:modalSlide .3s ease;
}
@keyframes modalSlide{from{opacity:0;transform:scale(0.9);}to{opacity:1;transform:scale(1);}}
.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;}

@media(max-width:1200px){
    .stats-grid{grid-template-columns:repeat(2,1fr);}
    .two-col{grid-template-columns:1fr;}
}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .profile-top{flex-direction:column;text-align:center;}
    .profile-avatar{width:100px;height:100px;font-size:2rem;}
    .profile-actions{justify-content:center;width:100%;}
    .stats-grid{grid-template-columns:1fr;}
    .form-row{grid-template-columns:1fr;}
    .avatar-grid{grid-template-columns:repeat(3,1fr);}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">My Profile</div>
    </div>

    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-top">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar">
                        <?php if ($student['profile_photo']): ?>
                            <img src="<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <button class="edit-avatar-btn" onclick="openAvatarModal()" title="Change avatar">
                        <i class="fas fa-camera fa-sm"></i>
                    </button>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                    <div class="profile-email"><i class="fas fa-envelope fa-xs"></i> <?php echo htmlspecialchars($student['email']); ?></div>
                    <div class="profile-meta">
                        <?php if ($student['college_name']): ?>
                        <div class="profile-meta-item"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($student['college_name']); ?></div>
                        <?php endif; ?>
                        <?php if ($student['domain_interest']): ?>
                        <div class="profile-meta-item"><i class="fas fa-code"></i> <?php echo htmlspecialchars($student['domain_interest']); ?></div>
                        <?php endif; ?>
                        <div class="profile-meta-item"><i class="fas fa-calendar"></i> Joined <?php echo date('M Y', strtotime($student['created_at'])); ?></div>
                    </div>
                </div>
                <div class="profile-actions">
                    <button class="btn-edit" onclick="scrollToEdit()">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo number_format($student['total_points']); ?></div>
                <div class="stat-label">Total Points</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo number_format($student['total_points'] ?? 0); ?></div>
                <div class="stat-label">Tasks Completed</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-comments"></i></div>
                <div class="stat-value"><?php echo $stats['chat_rooms'] ?? 0; ?></div>
                <div class="stat-label">Chat Rooms</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-value"><?php echo $stats['messages_sent'] ?? 0; ?></div>
                <div class="stat-label">Messages Sent</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="two-col">
            <!-- Edit Profile Form -->
            <div class="section-card" id="editSection">
                <div class="section-head">
                    <div class="section-title">
                        <i class="fas fa-user-edit" style="color:var(--o5)"></i>
                        Edit Profile Information
                    </div>
                </div>
                <div class="section-body">
                    <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($generalError): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($generalError); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="profileForm">
                        <input type="hidden" name="profile_photo" id="profilePhotoInput" value="<?php echo htmlspecialchars($student['profile_photo'] ?? ''); ?>">
                        
                        <div class="form-group">
                            <label class="form-label" for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-input <?php echo isset($errors['full_name'])?'is-error':''; ?>" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                            <div class="field-error"><i class="fas fa-exclamation-circle fa-xs"></i> <?php echo $errors['full_name']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone <span class="optional">(optional)</span></label>
                                <input type="tel" id="phone" name="phone" class="form-input <?php echo isset($errors['phone'])?'is-error':''; ?>" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                <?php if (isset($errors['phone'])): ?>
                                <div class="field-error"><i class="fas fa-exclamation-circle fa-xs"></i> <?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="year_of_study">Year of Study</label>
                                <select id="year_of_study" name="year_of_study" class="form-select">
                                    <?php foreach ($yearOptions as $yr): ?>
                                    <option value="<?php echo $yr; ?>" <?php echo $student['year_of_study']===$yr?'selected':''; ?>><?php echo $yr; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="college_name">College / University <span class="optional">(optional)</span></label>
                                <input type="text" id="college_name" name="college_name" class="form-input" value="<?php echo htmlspecialchars($student['college_name'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="degree">Degree / Branch <span class="optional">(optional)</span></label>
                                <input type="text" id="degree" name="degree" class="form-input" value="<?php echo htmlspecialchars($student['degree'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="domain_interest">Domain of Interest <span class="optional">(optional)</span></label>
                            <select id="domain_interest" name="domain_interest" class="form-select">
                                <option value="">-- Select Domain --</option>
                                <?php foreach ($domainOptions as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $student['domain_interest']===$d?'selected':''; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="bio">Bio <span class="optional">(optional)</span></label>
                            <textarea id="bio" name="bio" class="form-textarea" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($student['bio'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="linkedin_url">LinkedIn Profile <span class="optional">(optional)</span></label>
                                <input type="url" id="linkedin_url" name="linkedin_url" class="form-input <?php echo isset($errors['linkedin_url'])?'is-error':''; ?>" value="<?php echo htmlspecialchars($student['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                                <?php if (isset($errors['linkedin_url'])): ?>
                                <div class="field-error"><i class="fas fa-exclamation-circle fa-xs"></i> <?php echo $errors['linkedin_url']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="github_url">GitHub Profile <span class="optional">(optional)</span></label>
                                <input type="url" id="github_url" name="github_url" class="form-input <?php echo isset($errors['github_url'])?'is-error':''; ?>" value="<?php echo htmlspecialchars($student['github_url'] ?? ''); ?>" placeholder="https://github.com/username">
                                <?php if (isset($errors['github_url'])): ?>
                                <div class="field-error"><i class="fas fa-exclamation-circle fa-xs"></i> <?php echo $errors['github_url']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                            <button type="button" class="btn-secondary" onclick="location.reload()">Cancel</button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-title">
                        <i class="fas fa-clock" style="color:var(--blue)"></i>
                        Recent Activity
                    </div>
                </div>
                <div class="section-body" style="padding:8px 0;">
                    <?php if (empty($recentActivity)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No recent activity</p>
                    </div>
                    <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['type']; ?>">
                                <i class="fas fa-<?php echo $activity['type']==='task'?'tasks':'star'; ?>"></i>
                            </div>
                            <div class="activity-info">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                <div class="activity-time"><?php echo date('M d, h:i A', strtotime($activity['date'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Selection Modal -->
<div class="modal-bg" id="avatarModal" onclick="if(event.target===this)closeAvatarModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <h3><i class="fas fa-image" style="color:var(--o5)"></i> Choose Your Avatar</h3>
        <div class="avatar-grid">
            <?php foreach ($avatarOptions as $key => $url): ?>
            <div class="avatar-option" data-url="<?php echo htmlspecialchars($url); ?>" onclick="selectAvatar('<?php echo htmlspecialchars($url); ?>', this)">
                <img src="<?php echo htmlspecialchars($url); ?>" alt="Avatar">
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeAvatarModal()">Cancel</button>
            <button class="btn-primary" onclick="saveAvatar()">
                <i class="fas fa-check"></i> Save Avatar
            </button>
        </div>
    </div>
</div>

<script>
let selectedAvatarUrl = '<?php echo htmlspecialchars($student['profile_photo'] ?? ''); ?>';

function openAvatarModal() {
    document.getElementById('avatarModal').classList.add('open');
    // Pre-select current avatar
    if (selectedAvatarUrl) {
        const currentOption = document.querySelector(`.avatar-option[data-url="${selectedAvatarUrl}"]`);
        if (currentOption) currentOption.classList.add('selected');
    }
}

function closeAvatarModal() {
    document.getElementById('avatarModal').classList.remove('open');
}

function selectAvatar(url, element) {
    // Remove selected class from all
    document.querySelectorAll('.avatar-option').forEach(el => el.classList.remove('selected'));
    // Add to clicked
    element.classList.add('selected');
    selectedAvatarUrl = url;
}

function saveAvatar() {
    if (!selectedAvatarUrl) {
        alert('Please select an avatar');
        return;
    }
    
    // Update hidden input and submit form
    document.getElementById('profilePhotoInput').value = selectedAvatarUrl;
    
    // Update display avatar immediately
    const avatarImg = document.querySelector('.profile-avatar img');
    if (avatarImg) {
        avatarImg.src = selectedAvatarUrl;
    } else {
        const avatarDiv = document.querySelector('.profile-avatar');
        avatarDiv.innerHTML = `<img src="${selectedAvatarUrl}" alt="Profile">`;
    }
    
    closeAvatarModal();
    
    // Auto-submit form to save
    document.getElementById('profileForm').submit();
}

function scrollToEdit() {
    document.getElementById('editSection').scrollIntoView({behavior: 'smooth', block: 'start'});
}

// Auto-hide success message
setTimeout(() => {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }
}, 5000);
</script>
</body>
</html>