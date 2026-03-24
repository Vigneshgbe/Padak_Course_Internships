<?php
// admin_modules/admin_earnings_manage.php
// Admin interface for awarding and managing student earnings

// Get all active students for dropdown
$studentsRes = $db->query("SELECT id, full_name, email, domain_interest FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) {
    $students[] = $row;
}

// Get all earnings with student info
$earningsRes = $db->query("
    SELECT e.*, s.full_name, s.email, s.domain_interest
    FROM student_earnings e
    JOIN internship_students s ON s.id = e.student_id
    ORDER BY e.awarded_at DESC
    LIMIT 100
");
$allEarnings = [];
while ($row = $earningsRes->fetch_assoc()) {
    $allEarnings[] = $row;
}

// Get statistics
$earningsStats = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='redeemed' THEN 1 ELSE 0 END) as redeemed,
    SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) as expired,
    SUM(CASE WHEN earning_type='mentorship' THEN 1 ELSE 0 END) as mentorship,
    SUM(CASE WHEN earning_type='software_access' THEN 1 ELSE 0 END) as software,
    SUM(CASE WHEN earning_type='learning_resource' THEN 1 ELSE 0 END) as resources
    FROM student_earnings
")->fetch_assoc();

$earningCategories = [
    'Mentorship Rewards',
    'Software Access',
    'Learning Resources',
    'Exclusive Perks',
    'Bonus Rewards',
    'Skill Development',
    'Career Opportunities'
];
?>

<style>
.section{
    background:var(--card);
    border-radius:14px;
    border:1px solid var(--border);
    box-shadow:0 1px 3px rgba(0,0,0,0.06);
    margin-bottom:24px;
}
.section-header{
    padding:18px 24px;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:12px;
}
.sh-title{
    font-size:1.1rem;
    font-weight:700;
    color:var(--text);
    display:flex;
    align-items:center;
    gap:10px;
}
.sh-title i{color:var(--o5);}
.section-body{padding:24px;}

.btn{
    padding:10px 18px;
    border-radius:9px;
    font-size:.875rem;
    font-weight:600;
    font-family:inherit;
    cursor:pointer;
    border:none;
    display:inline-flex;
    align-items:center;
    gap:7px;
    text-decoration:none;
    transition:all .2s;
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
.btn-danger{
    background:#fef2f2;
    border:1.5px solid #fecaca;
    color:#dc2626;
}
.btn-danger:hover{
    background:#dc2626;
    color:#fff;
}
.btn-sm{padding:6px 12px;font-size:.75rem;}

.form-group{margin-bottom:18px;}
.form-label{
    display:block;
    font-size:.82rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:8px;
}
.form-label .required{color:var(--red);}
.form-input, .form-select, .form-textarea{
    width:100%;
    padding:11px 14px;
    border:1.5px solid var(--border);
    border-radius:9px;
    font-size:.875rem;
    font-family:inherit;
    color:var(--text);
    outline:none;
    transition:all .2s;
    background:var(--card);
}
.form-input:focus, .form-select:focus, .form-textarea:focus{
    border-color:var(--o5);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}
.form-textarea{
    min-height:100px;
    resize:vertical;
}
.form-row{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:16px;
}
.form-hint{
    font-size:.75rem;
    color:var(--text3);
    margin-top:4px;
}
.form-checkbox{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:8px;
}
.form-checkbox input[type="checkbox"]{
    width:18px;
    height:18px;
    cursor:pointer;
}
.form-checkbox label{
    font-size:.875rem;
    color:var(--text2);
    cursor:pointer;
}

/* Stats Grid */
.admin-stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:24px;
}
.admin-stat-card{
    background:var(--card);
    border-radius:10px;
    padding:16px;
    border:1px solid var(--border);
    box-shadow:0 1px 3px rgba(0,0,0,0.06);
}
.asc-icon{
    width:36px;
    height:36px;
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.9rem;
    margin-bottom:10px;
}
.asc-icon.orange{background:rgba(249,115,22,0.12);color:var(--o5);}
.asc-icon.blue{background:rgba(59,130,246,0.12);color:var(--blue);}
.asc-icon.green{background:rgba(34,197,94,0.12);color:var(--green);}
.asc-icon.purple{background:rgba(139,92,246,0.12);color:var(--purple);}
.asc-value{
    font-size:1.6rem;
    font-weight:800;
    color:var(--text);
    line-height:1;
    margin-bottom:4px;
}
.asc-label{
    font-size:.75rem;
    color:var(--text2);
    font-weight:500;
}

/* Modal */
.modal{
    display:none;
    position:fixed;
    top:0;left:0;right:0;bottom:0;
    background:rgba(0,0,0,0.6);
    backdrop-filter:blur(4px);
    z-index:9999;
    padding:20px;
    overflow-y:auto;
}
.modal.open{display:flex;align-items:center;justify-content:center;}
.modal-content{
    background:var(--card);
    border-radius:14px;
    max-width:700px;
    width:100%;
    box-shadow:0 20px 60px rgba(0,0,0,0.3);
    animation:modalSlide .3s ease;
}
@keyframes modalSlide{
    from{opacity:0;transform:translateY(-20px);}
    to{opacity:1;transform:translateY(0);}
}
.modal-header{
    padding:20px 24px;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.modal-title{
    font-size:1.1rem;
    font-weight:700;
    color:var(--text);
    display:flex;
    align-items:center;
    gap:10px;
}
.modal-close{
    width:32px;
    height:32px;
    border-radius:8px;
    border:none;
    background:var(--bg);
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--text3);
    transition:all .2s;
}
.modal-close:hover{
    background:var(--border);
    color:var(--text);
}
.modal-body{padding:24px;max-height:70vh;overflow-y:auto;}
.modal-footer{
    padding:16px 24px;
    border-top:1px solid var(--border);
    display:flex;
    gap:12px;
    justify-content:flex-end;
}

/* Table */
.earnings-table{
    width:100%;
    border-collapse:collapse;
    font-size:.875rem;
}
.earnings-table thead tr{
    background:var(--bg);
    border-bottom:2px solid var(--border);
}
.earnings-table th{
    padding:12px 14px;
    font-size:.75rem;
    font-weight:700;
    color:var(--text2);
    text-transform:uppercase;
    letter-spacing:.05em;
    text-align:left;
}
.earnings-table tbody tr{
    border-bottom:1px solid var(--border);
    transition:background .15s;
}
.earnings-table tbody tr:hover{background:var(--bg);}
.earnings-table td{
    padding:14px;
    color:var(--text);
}

.student-info{
    display:flex;
    flex-direction:column;
    gap:4px;
}
.student-name{
    font-weight:600;
    color:var(--text);
}
.student-email{
    font-size:.75rem;
    color:var(--text3);
}

.earning-details{
    display:flex;
    flex-direction:column;
    gap:4px;
}
.earning-title{
    font-weight:600;
    color:var(--text);
}
.earning-type-badge{
    display:inline-block;
    padding:2px 8px;
    border-radius:4px;
    font-size:.7rem;
    font-weight:700;
    text-transform:uppercase;
}
.earning-type-badge.mentorship{background:rgba(139,92,246,0.15);color:var(--purple);}
.earning-type-badge.software_access{background:rgba(59,130,246,0.15);color:var(--blue);}
.earning-type-badge.learning_resource{background:rgba(34,197,94,0.15);color:var(--green);}
.earning-type-badge.exclusive_perk{background:rgba(234,179,8,0.15);color:#ca8a04;}
.earning-type-badge.bonus_reward{background:rgba(249,115,22,0.15);color:var(--o5);}

.status-pill{
    display:inline-block;
    padding:4px 10px;
    border-radius:12px;
    font-size:.7rem;
    font-weight:700;
    text-transform:uppercase;
}
.status-pill.pending{background:rgba(234,179,8,0.15);color:#ca8a04;}
.status-pill.active{background:rgba(34,197,94,0.15);color:#16a34a;}
.status-pill.redeemed{background:rgba(139,92,246,0.15);color:#7c3aed;}
.status-pill.expired{background:rgba(239,68,68,0.15);color:#dc2626;}
.status-pill.revoked{background:rgba(100,116,139,0.15);color:#475569;}

.action-btns{
    display:flex;
    gap:6px;
}

@media(max-width:1200px){
    .admin-stats{grid-template-columns:repeat(2,1fr);}
    .form-row{grid-template-columns:1fr;}
}
@media(max-width:768px){
    .admin-stats{grid-template-columns:1fr;}
}
</style>

<!-- Statistics -->
<div class="admin-stats">
    <div class="admin-stat-card">
        <div class="asc-icon orange"><i class="fas fa-gift"></i></div>
        <div class="asc-value"><?php echo number_format($earningsStats['total']); ?></div>
        <div class="asc-label">Total Earnings</div>
    </div>
    <div class="admin-stat-card">
        <div class="asc-icon blue"><i class="fas fa-clock"></i></div>
        <div class="asc-value"><?php echo number_format($earningsStats['pending']); ?></div>
        <div class="asc-label">Pending</div>
    </div>
    <div class="admin-stat-card">
        <div class="asc-icon green"><i class="fas fa-bolt"></i></div>
        <div class="asc-value"><?php echo number_format($earningsStats['active']); ?></div>
        <div class="asc-label">Active</div>
    </div>
    <div class="admin-stat-card">
        <div class="asc-icon purple"><i class="fas fa-check-circle"></i></div>
        <div class="asc-value"><?php echo number_format($earningsStats['redeemed']); ?></div>
        <div class="asc-label">Redeemed</div>
    </div>
</div>

<!-- Award New Earning -->
<div class="section">
    <div class="section-header">
        <div class="sh-title">
            <i class="fas fa-gift"></i>
            Award New Earning
        </div>
    </div>
    <div class="section-body">
        <form method="POST" action="admin.php">
            <input type="hidden" name="award_earning" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Select Student <span class="required">*</span>
                    </label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Choose a student...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>">
                            <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['domain_interest'] ?: 'No domain'); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Earning Type <span class="required">*</span>
                    </label>
                    <select name="earning_type" class="form-select" required>
                        <option value="mentorship">Mentorship Session</option>
                        <option value="software_access">Software Access</option>
                        <option value="learning_resource">Learning Resource</option>
                        <option value="exclusive_perk">Exclusive Perk</option>
                        <option value="bonus_reward">Bonus Reward</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-folder"></i> Category
                    </label>
                    <select name="category" class="form-select">
                        <option value="">Select category (optional)</option>
                        <?php foreach ($earningCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-star"></i> Priority
                    </label>
                    <select name="priority" class="form-select">
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-heading"></i> Title <span class="required">*</span>
                </label>
                <input type="text" name="title" class="form-input" 
                       placeholder="e.g., 1:1 Mentorship with CTO" required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-align-left"></i> Description
                </label>
                <textarea name="description" class="form-textarea" 
                          placeholder="Detailed description of this reward..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-dollar-sign"></i> Value
                    </label>
                    <input type="text" name="value" class="form-input" 
                           placeholder="e.g., 1 Session, 24 Hours, 1 Month">
                    <div class="form-hint">Display value (e.g., "1x 60 min session", "24 hours access")</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-layer-group"></i> Quantity
                    </label>
                    <input type="number" name="quantity" class="form-input" value="1" min="1">
                    <div class="form-hint">How many times can this be used</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-award"></i> Awarded For <span class="required">*</span>
                </label>
                <textarea name="awarded_for" class="form-textarea" 
                          placeholder="Reason for this reward (e.g., Top performer in Web Development - January 2024)" required></textarea>
                <div class="form-hint">This will be shown to the student explaining why they earned this</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i> Expiry Date
                    </label>
                    <input type="date" name="expires_at" class="form-input">
                    <div class="form-hint">Leave empty for no expiry</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-image"></i> Thumbnail URL
                    </label>
                    <input type="url" name="thumbnail_url" class="form-input" 
                           placeholder="https://example.com/image.png">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-book"></i> Redemption Instructions
                </label>
                <textarea name="redemption_instructions" class="form-textarea" 
                          placeholder="How should the student redeem this reward? (e.g., Contact admin@padak.com to schedule your session)"></textarea>
            </div>

            <div class="form-checkbox">
                <input type="checkbox" name="is_featured" id="is_featured" value="1">
                <label for="is_featured">
                    <i class="fas fa-star"></i> Feature this earning (highlight on dashboard)
                </label>
            </div>

            <div style="margin-top:24px;display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-gift"></i> Award Earning
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
            </div>
        </form>
    </div>
</div>

<!-- All Earnings List -->
<div class="section">
    <div class="section-header">
        <div class="sh-title">
            <i class="fas fa-list"></i>
            All Student Earnings
        </div>
    </div>
    <div class="section-body" style="padding:0;overflow-x:auto;">
        <table class="earnings-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Earning Details</th>
                    <th>Value</th>
                    <th>Status</th>
                    <th>Awarded</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allEarnings)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:var(--text3);">
                        <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                        No earnings awarded yet
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($allEarnings as $earning): ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <span class="student-name"><?php echo htmlspecialchars($earning['full_name']); ?></span>
                                <span class="student-email"><?php echo htmlspecialchars($earning['email']); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="earning-details">
                                <span class="earning-title"><?php echo htmlspecialchars($earning['title']); ?></span>
                                <span class="earning-type-badge <?php echo $earning['earning_type']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $earning['earning_type'])); ?>
                                </span>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($earning['value'] ?: '-'); ?></td>
                        <td>
                            <span class="status-pill <?php echo $earning['status']; ?>">
                                <?php echo ucfirst($earning['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($earning['awarded_at'])); ?></td>
                        <td>
                            <?php 
                            if ($earning['expires_at']) {
                                echo date('M d, Y', strtotime($earning['expires_at']));
                            } else {
                                echo '<span style="color:var(--text3);">No expiry</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="viewEarning(<?php echo htmlspecialchars(json_encode($earning)); ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <form method="POST" action="admin.php" style="display:inline;" 
                                      onsubmit="return confirm('Revoke this earning?')">
                                    <input type="hidden" name="revoke_earning" value="1">
                                    <input type="hidden" name="earning_id" value="<?php echo $earning['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Earning Modal -->
<div class="modal" id="viewEarningModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-gift"></i>
                Earning Details
            </div>
            <button class="modal-close" onclick="closeModal('viewEarningModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="earningDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
function viewEarning(earning) {
    const modal = document.getElementById('viewEarningModal');
    const content = document.getElementById('earningDetailsContent');
    
    const statusColors = {
        'pending': '#ca8a04',
        'active': '#16a34a',
        'redeemed': '#7c3aed',
        'expired': '#dc2626',
        'revoked': '#475569'
    };
    
    let html = `
        <div style="display:grid;gap:16px;">
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">STUDENT</div>
                <div style="font-weight:600;">${earning.full_name}</div>
                <div style="font-size:0.875rem;color:var(--text2);">${earning.email}</div>
            </div>
            
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">TITLE</div>
                <div style="font-weight:600;font-size:1.1rem;">${earning.title}</div>
            </div>
            
            ${earning.description ? `
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">DESCRIPTION</div>
                <div style="font-size:0.875rem;line-height:1.6;">${earning.description}</div>
            </div>
            ` : ''}
            
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">TYPE</div>
                    <div style="font-weight:600;">${earning.earning_type.replace('_', ' ').toUpperCase()}</div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">VALUE</div>
                    <div style="font-weight:600;">${earning.value || '-'}</div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">QUANTITY</div>
                    <div style="font-weight:600;">${earning.used_quantity} / ${earning.quantity} used</div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">STATUS</div>
                    <div style="font-weight:600;color:${statusColors[earning.status] || 'var(--text)'};">
                        ${earning.status.toUpperCase()}
                    </div>
                </div>
            </div>
            
            ${earning.awarded_for ? `
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">AWARDED FOR</div>
                <div style="padding:12px;background:rgba(249,115,22,0.08);border-radius:8px;font-size:0.875rem;line-height:1.6;">
                    ${earning.awarded_for}
                </div>
            </div>
            ` : ''}
            
            ${earning.redemption_instructions ? `
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">REDEMPTION INSTRUCTIONS</div>
                <div style="font-size:0.875rem;line-height:1.6;">${earning.redemption_instructions}</div>
            </div>
            ` : ''}
            
            ${earning.redemption_code ? `
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">REDEMPTION CODE</div>
                <div style="padding:12px;background:rgba(139,92,246,0.08);border:2px dashed var(--purple);border-radius:8px;font-family:monospace;font-size:1.1rem;font-weight:700;">
                    ${earning.redemption_code}
                </div>
            </div>
            ` : ''}
            
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;padding-top:12px;border-top:1px solid var(--border);">
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">AWARDED ON</div>
                    <div style="font-size:0.875rem;">${new Date(earning.awarded_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})}</div>
                </div>
                ${earning.expires_at ? `
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">EXPIRES ON</div>
                    <div style="font-size:0.875rem;">${new Date(earning.expires_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})}</div>
                </div>
                ` : ''}
                ${earning.activated_at ? `
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">ACTIVATED ON</div>
                    <div style="font-size:0.875rem;">${new Date(earning.activated_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})}</div>
                </div>
                ` : ''}
                ${earning.redeemed_at ? `
                <div>
                    <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">REDEEMED ON</div>
                    <div style="font-size:0.875rem;">${new Date(earning.redeemed_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})}</div>
                </div>
                ` : ''}
            </div>
            
            <div>
                <div style="font-size:0.75rem;color:var(--text3);margin-bottom:4px;">AWARDED BY</div>
                <div style="font-size:0.875rem;">${earning.awarded_by}</div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
    modal.classList.add('open');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('open');
        }
    });
});
</script>