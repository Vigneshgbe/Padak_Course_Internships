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
/* ── Reset & Base ── */
*,*::before,*::after{box-sizing:border-box;}

/* ── Stats Row ── */
.em-stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin-bottom:20px;
}
.em-stat{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:12px;
    padding:18px 20px;
    display:flex;
    align-items:center;
    gap:14px;
    transition:box-shadow .2s;
}
.em-stat:hover{box-shadow:0 4px 16px rgba(0,0,0,0.07);}
.em-stat-icon{
    width:42px;height:42px;
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:.95rem;
    flex-shrink:0;
}
.em-stat-icon.o{background:rgba(249,115,22,0.1);color:#f97316;}
.em-stat-icon.b{background:rgba(59,130,246,0.1);color:#3b82f6;}
.em-stat-icon.g{background:rgba(34,197,94,0.1);color:#22c55e;}
.em-stat-icon.p{background:rgba(139,92,246,0.1);color:#8b5cf6;}
.em-stat-body{}
.em-stat-val{
    font-size:1.65rem;
    font-weight:800;
    color:var(--text);
    line-height:1;
    letter-spacing:-0.02em;
}
.em-stat-lbl{
    font-size:.72rem;
    font-weight:600;
    color:var(--text3);
    text-transform:uppercase;
    letter-spacing:.06em;
    margin-top:3px;
}

/* ── Panel ── */
.em-panel{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    margin-bottom:20px;
    overflow:hidden;
}
.em-panel-head{
    padding:16px 22px;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    background:var(--bg);
}
.em-panel-title{
    font-size:.95rem;
    font-weight:700;
    color:var(--text);
    display:flex;
    align-items:center;
    gap:9px;
}
.em-panel-title i{color:var(--o5);font-size:.9rem;}
.em-panel-body{padding:22px;}

/* ── Form Elements ── */
.em-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.em-fg{margin-bottom:16px;}
.em-fg:last-child{margin-bottom:0;}
.em-label{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:.775rem;
    font-weight:700;
    color:var(--text2);
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:7px;
}
.em-label i{color:var(--o5);font-size:.75rem;}
.em-label .req{color:#ef4444;margin-left:2px;}

.em-ctrl{
    width:100%;
    padding:10px 13px;
    border:1.5px solid var(--border);
    border-radius:9px;
    font-size:.875rem;
    font-family:inherit;
    color:var(--text);
    background:var(--card);
    outline:none;
    transition:border-color .18s, box-shadow .18s;
}
.em-ctrl:focus{
    border-color:var(--o5);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}
.em-ctrl::placeholder{color:var(--text3);}
textarea.em-ctrl{min-height:88px;resize:vertical;line-height:1.5;}
select.em-ctrl{cursor:pointer;}

.em-hint{
    font-size:.72rem;
    color:var(--text3);
    margin-top:5px;
    line-height:1.4;
}

.em-checkbox-row{
    display:flex;align-items:center;gap:9px;
    padding:12px 14px;
    background:rgba(249,115,22,0.04);
    border:1.5px solid rgba(249,115,22,0.15);
    border-radius:9px;
    cursor:pointer;
    margin-top:4px;
}
.em-checkbox-row input[type="checkbox"]{
    width:17px;height:17px;
    accent-color:var(--o5);
    cursor:pointer;
    flex-shrink:0;
}
.em-checkbox-row label{
    font-size:.85rem;
    font-weight:600;
    color:var(--text2);
    cursor:pointer;
    display:flex;align-items:center;gap:7px;
}
.em-checkbox-row label i{color:var(--o5);}

/* ── Buttons ── */
.em-btn{
    padding:10px 20px;
    border-radius:9px;
    font-size:.85rem;
    font-weight:700;
    font-family:inherit;
    cursor:pointer;
    border:none;
    display:inline-flex;align-items:center;gap:7px;
    text-decoration:none;
    transition:all .18s;
    letter-spacing:.01em;
}
.em-btn-primary{
    background:#f97316;
    color:#fff;
    box-shadow:0 3px 12px rgba(249,115,22,0.3);
}
.em-btn-primary:hover{
    background:#ea6c0a;
    box-shadow:0 5px 18px rgba(249,115,22,0.4);
    transform:translateY(-1px);
}
.em-btn-ghost{
    background:transparent;
    border:1.5px solid var(--border);
    color:var(--text2);
}
.em-btn-ghost:hover{border-color:var(--text2);color:var(--text);}
.em-btn-danger{
    background:#fff1f1;
    border:1.5px solid #fecaca;
    color:#dc2626;
}
.em-btn-danger:hover{background:#dc2626;color:#fff;border-color:#dc2626;}
.em-btn-icon{
    padding:7px 10px;
    border-radius:8px;
    font-size:.78rem;
}

.em-form-actions{
    display:flex;
    gap:10px;
    padding-top:18px;
    border-top:1px solid var(--border);
    margin-top:18px;
}

/* ── Divider label ── */
.em-section-divider{
    display:flex;align-items:center;gap:12px;
    margin:20px 0 16px;
}
.em-section-divider span{
    font-size:.7rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.1em;
    color:var(--text3);
    white-space:nowrap;
}
.em-section-divider::before,.em-section-divider::after{
    content:'';flex:1;
    height:1px;background:var(--border);
}

/* ── Table ── */
.em-table-wrap{overflow-x:auto;}
.em-table{
    width:100%;
    border-collapse:collapse;
    font-size:.845rem;
}
.em-table thead tr{border-bottom:2px solid var(--border);}
.em-table th{
    padding:11px 14px;
    font-size:.68rem;
    font-weight:800;
    color:var(--text3);
    text-transform:uppercase;
    letter-spacing:.07em;
    text-align:left;
    background:var(--bg);
    white-space:nowrap;
}
.em-table th:first-child{border-radius:0;}
.em-table tbody tr{
    border-bottom:1px solid var(--border);
    transition:background .12s;
}
.em-table tbody tr:last-child{border-bottom:none;}
.em-table tbody tr:hover{background:rgba(249,115,22,0.03);}
.em-table td{
    padding:13px 14px;
    color:var(--text);
    vertical-align:middle;
}

/* ── Table Cell Components ── */
.em-student{display:flex;align-items:center;gap:11px;}
.em-avatar{
    width:34px;height:34px;
    border-radius:9px;
    background:linear-gradient(135deg,#f97316,#fb923c);
    display:flex;align-items:center;justify-content:center;
    font-size:.8rem;font-weight:800;color:#fff;
    flex-shrink:0;
}
.em-student-name{font-weight:600;color:var(--text);font-size:.845rem;}
.em-student-email{font-size:.72rem;color:var(--text3);margin-top:1px;}

.em-earning-title{font-weight:600;color:var(--text);margin-bottom:4px;font-size:.845rem;}

/* ── Badges ── */
.em-type-tag{
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 9px;
    border-radius:5px;
    font-size:.68rem;font-weight:700;
    text-transform:uppercase;letter-spacing:.04em;
}
.em-type-tag.mentorship{background:rgba(139,92,246,0.1);color:#7c3aed;}
.em-type-tag.software_access{background:rgba(59,130,246,0.1);color:#2563eb;}
.em-type-tag.learning_resource{background:rgba(34,197,94,0.1);color:#15803d;}
.em-type-tag.exclusive_perk{background:rgba(234,179,8,0.1);color:#a16207;}
.em-type-tag.bonus_reward{background:rgba(249,115,22,0.1);color:#c2410c;}

.em-status{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;
    border-radius:20px;
    font-size:.7rem;font-weight:700;
    text-transform:uppercase;letter-spacing:.04em;
}
.em-status::before{
    content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0;
}
.em-status.pending{background:rgba(234,179,8,0.1);color:#a16207;}
.em-status.pending::before{background:#eab308;}
.em-status.active{background:rgba(34,197,94,0.1);color:#15803d;}
.em-status.active::before{background:#22c55e;}
.em-status.redeemed{background:rgba(139,92,246,0.1);color:#7c3aed;}
.em-status.redeemed::before{background:#8b5cf6;}
.em-status.expired{background:rgba(239,68,68,0.1);color:#dc2626;}
.em-status.expired::before{background:#ef4444;}
.em-status.revoked{background:rgba(100,116,139,0.1);color:#475569;}
.em-status.revoked::before{background:#94a3b8;}

.em-date{font-size:.8rem;color:var(--text2);}
.em-no-expiry{font-size:.78rem;color:var(--text3);font-style:italic;}

.em-actions{display:flex;gap:6px;align-items:center;}

/* ── Empty State ── */
.em-empty{
    text-align:center;
    padding:52px 24px;
    color:var(--text3);
}
.em-empty-icon{
    width:52px;height:52px;
    margin:0 auto 14px;
    background:var(--bg);
    border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;
    border:1px solid var(--border);
    color:var(--text3);
    opacity:.7;
}
.em-empty p{font-size:.875rem;font-weight:500;margin:0;}

/* ── Modal ── */
.em-modal{
    display:none;
    position:fixed;inset:0;
    background:rgba(0,0,0,0.55);
    backdrop-filter:blur(5px);
    z-index:9999;
    padding:20px;
    overflow-y:auto;
    align-items:center;justify-content:center;
}
.em-modal.open{display:flex;}
.em-modal-box{
    background:var(--card);
    border-radius:16px;
    max-width:640px;
    width:100%;
    box-shadow:0 24px 64px rgba(0,0,0,0.25);
    animation:emModalIn .25s ease;
}
@keyframes emModalIn{
    from{opacity:0;transform:translateY(-16px) scale(.98);}
    to{opacity:1;transform:none;}
}
.em-modal-head{
    padding:18px 22px;
    border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
}
.em-modal-title{
    font-size:1rem;font-weight:700;color:var(--text);
    display:flex;align-items:center;gap:9px;
}
.em-modal-title i{color:var(--o5);}
.em-modal-close{
    width:30px;height:30px;
    border-radius:8px;border:none;
    background:var(--bg);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    color:var(--text3);font-size:.85rem;
    transition:all .15s;
}
.em-modal-close:hover{background:var(--border);color:var(--text);}
.em-modal-body{
    padding:22px;
    max-height:68vh;
    overflow-y:auto;
}

/* ── Modal Detail Rows ── */
.em-detail-grid{display:grid;gap:14px;}
.em-detail-lbl{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:4px;}
.em-detail-val{font-size:.875rem;color:var(--text);font-weight:500;line-height:1.5;}
.em-detail-2col{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.em-detail-box{
    padding:11px 13px;
    border-radius:9px;
    font-size:.845rem;
    line-height:1.55;
}
.em-detail-box.orange{background:rgba(249,115,22,0.07);border:1px solid rgba(249,115,22,0.15);}
.em-detail-box.purple{
    background:rgba(139,92,246,0.07);
    border:2px dashed rgba(139,92,246,0.3);
    font-family:monospace;font-size:1rem;font-weight:700;
    color:#7c3aed;letter-spacing:.05em;
}
.em-detail-sep{border:none;border-top:1px solid var(--border);margin:4px 0;}

/* ── Responsive ── */
@media(max-width:1100px){.em-stats{grid-template-columns:repeat(2,1fr);}}
@media(max-width:820px){
    .em-stats{grid-template-columns:repeat(2,1fr);}
    .em-grid-2{grid-template-columns:1fr;}
}
@media(max-width:540px){
    .em-stats{grid-template-columns:1fr;}
    .em-detail-2col{grid-template-columns:1fr;}
}
</style>

<!-- ── Statistics ── -->
<div class="em-stats">
    <div class="em-stat">
        <div class="em-stat-icon o"><i class="fas fa-gift"></i></div>
        <div class="em-stat-body">
            <div class="em-stat-val"><?php echo number_format($earningsStats['total']); ?></div>
            <div class="em-stat-lbl">Total Earnings</div>
        </div>
    </div>
    <div class="em-stat">
        <div class="em-stat-icon b"><i class="fas fa-clock"></i></div>
        <div class="em-stat-body">
            <div class="em-stat-val"><?php echo number_format($earningsStats['pending']); ?></div>
            <div class="em-stat-lbl">Pending</div>
        </div>
    </div>
    <div class="em-stat">
        <div class="em-stat-icon g"><i class="fas fa-bolt"></i></div>
        <div class="em-stat-body">
            <div class="em-stat-val"><?php echo number_format($earningsStats['active']); ?></div>
            <div class="em-stat-lbl">Active</div>
        </div>
    </div>
    <div class="em-stat">
        <div class="em-stat-icon p"><i class="fas fa-check-circle"></i></div>
        <div class="em-stat-body">
            <div class="em-stat-val"><?php echo number_format($earningsStats['redeemed']); ?></div>
            <div class="em-stat-lbl">Redeemed</div>
        </div>
    </div>
</div>

<!-- ── Award New Earning ── -->
<div class="em-panel">
    <div class="em-panel-head">
        <div class="em-panel-title">
            <i class="fas fa-gift"></i>
            Award New Earning
        </div>
    </div>
    <div class="em-panel-body">
        <form method="POST" action="admin.php">
            <input type="hidden" name="award_earning" value="1">

            <!-- Row 1: Student + Type -->
            <div class="em-grid-2">
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-user"></i> Select Student <span class="req">*</span></label>
                    <select name="student_id" class="em-ctrl" required>
                        <option value="">Choose a student...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>">
                            <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['domain_interest'] ?: 'No domain'); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-tag"></i> Earning Type <span class="req">*</span></label>
                    <select name="earning_type" class="em-ctrl" required>
                        <option value="mentorship">Mentorship Session</option>
                        <option value="software_access">Software Access</option>
                        <option value="learning_resource">Learning Resource</option>
                        <option value="exclusive_perk">Exclusive Perk</option>
                        <option value="bonus_reward">Bonus Reward</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Category + Priority -->
            <div class="em-grid-2">
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-folder"></i> Category</label>
                    <select name="category" class="em-ctrl">
                        <option value="">Select category (optional)</option>
                        <?php foreach ($earningCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-star"></i> Priority</label>
                    <select name="priority" class="em-ctrl">
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <!-- Title -->
            <div class="em-fg">
                <label class="em-label"><i class="fas fa-heading"></i> Title <span class="req">*</span></label>
                <input type="text" name="title" class="em-ctrl" placeholder="e.g., 1:1 Mentorship with CTO" required>
            </div>

            <!-- Description -->
            <div class="em-fg">
                <label class="em-label"><i class="fas fa-align-left"></i> Description</label>
                <textarea name="description" class="em-ctrl" placeholder="Detailed description of this reward..."></textarea>
            </div>

            <!-- Row 3: Value + Quantity -->
            <div class="em-grid-2">
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-dollar-sign"></i> Value</label>
                    <input type="text" name="value" class="em-ctrl" placeholder="e.g., 1 Session, 24 Hours, 1 Month">
                    <div class="em-hint">Display value (e.g., "1× 60 min session", "24 hours access")</div>
                </div>
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-layer-group"></i> Quantity</label>
                    <input type="number" name="quantity" class="em-ctrl" value="1" min="1">
                    <div class="em-hint">How many times can this be used</div>
                </div>
            </div>

            <!-- Awarded For -->
            <div class="em-fg">
                <label class="em-label"><i class="fas fa-award"></i> Awarded For <span class="req">*</span></label>
                <textarea name="awarded_for" class="em-ctrl" placeholder="Reason for this reward (e.g., Top performer in Web Development - January 2024)" required></textarea>
                <div class="em-hint">This will be shown to the student explaining why they earned this</div>
            </div>

            <div class="em-section-divider"><span>Optional Details</span></div>

            <!-- Row 4: Expiry + Thumbnail -->
            <div class="em-grid-2">
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-calendar"></i> Expiry Date</label>
                    <input type="date" name="expires_at" class="em-ctrl">
                    <div class="em-hint">Leave empty for no expiry</div>
                </div>
                <div class="em-fg">
                    <label class="em-label"><i class="fas fa-image"></i> Thumbnail URL</label>
                    <input type="url" name="thumbnail_url" class="em-ctrl" placeholder="https://example.com/image.png">
                </div>
            </div>

            <!-- Redemption Instructions -->
            <div class="em-fg">
                <label class="em-label"><i class="fas fa-book"></i> Redemption Instructions</label>
                <textarea name="redemption_instructions" class="em-ctrl" placeholder="How should the student redeem this reward? (e.g., Contact admin@padak.com to schedule your session)"></textarea>
            </div>

            <!-- Featured checkbox -->
            <div class="em-checkbox-row">
                <input type="checkbox" name="is_featured" id="is_featured" value="1">
                <label for="is_featured">
                    <i class="fas fa-star"></i> Feature this earning — highlight it on the student dashboard
                </label>
            </div>

            <!-- Actions -->
            <div class="em-form-actions">
                <button type="submit" class="em-btn em-btn-primary">
                    <i class="fas fa-gift"></i> Award Earning
                </button>
                <button type="reset" class="em-btn em-btn-ghost">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── All Earnings ── -->
<div class="em-panel">
    <div class="em-panel-head">
        <div class="em-panel-title">
            <i class="fas fa-list"></i>
            All Student Earnings
        </div>
    </div>
    <div class="em-table-wrap">
        <table class="em-table">
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
                    <td colspan="7">
                        <div class="em-empty">
                            <div class="em-empty-icon"><i class="fas fa-inbox"></i></div>
                            <p>No earnings awarded yet</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($allEarnings as $earning): ?>
                    <?php
                        $initials = '';
                        $parts = explode(' ', trim($earning['full_name']));
                        foreach (array_slice($parts, 0, 2) as $p) $initials .= strtoupper($p[0] ?? '');
                    ?>
                    <tr>
                        <td>
                            <div class="em-student">
                                <div class="em-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                <div>
                                    <div class="em-student-name"><?php echo htmlspecialchars($earning['full_name']); ?></div>
                                    <div class="em-student-email"><?php echo htmlspecialchars($earning['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="em-earning-title"><?php echo htmlspecialchars($earning['title']); ?></div>
                            <span class="em-type-tag <?php echo $earning['earning_type']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $earning['earning_type'])); ?>
                            </span>
                        </td>
                        <td class="em-date"><?php echo htmlspecialchars($earning['value'] ?: '—'); ?></td>
                        <td>
                            <span class="em-status <?php echo $earning['status']; ?>">
                                <?php echo ucfirst($earning['status']); ?>
                            </span>
                        </td>
                        <td class="em-date"><?php echo date('M d, Y', strtotime($earning['awarded_at'])); ?></td>
                        <td>
                            <?php if ($earning['expires_at']): ?>
                                <span class="em-date"><?php echo date('M d, Y', strtotime($earning['expires_at'])); ?></span>
                            <?php else: ?>
                                <span class="em-no-expiry">No expiry</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="em-actions">
                                <button class="em-btn em-btn-ghost em-btn-icon"
                                        title="View details"
                                        onclick="emViewEarning(<?php echo htmlspecialchars(json_encode($earning)); ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <form method="POST" action="admin.php" style="display:inline;"
                                      onsubmit="return confirm('Revoke this earning?')">
                                    <input type="hidden" name="revoke_earning" value="1">
                                    <input type="hidden" name="earning_id" value="<?php echo $earning['id']; ?>">
                                    <button type="submit" class="em-btn em-btn-danger em-btn-icon" title="Revoke">
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

<!-- ── View Modal ── -->
<div class="em-modal" id="emViewModal">
    <div class="em-modal-box">
        <div class="em-modal-head">
            <div class="em-modal-title"><i class="fas fa-gift"></i> Earning Details</div>
            <button class="em-modal-close" onclick="emCloseModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="em-modal-body" id="emModalContent"></div>
    </div>
</div>

<script>
const emStatusColors = {
    pending:'#a16207', active:'#15803d', redeemed:'#7c3aed', expired:'#dc2626', revoked:'#475569'
};

function emViewEarning(e) {
    const fmtDate = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : null;

    let html = `<div class="em-detail-grid">
        <div>
            <div class="em-detail-lbl">Student</div>
            <div class="em-detail-val" style="font-weight:700;">${e.full_name}</div>
            <div class="em-detail-val" style="font-size:.78rem;color:var(--text3);">${e.email}</div>
        </div>
        <hr class="em-detail-sep">
        <div>
            <div class="em-detail-lbl">Title</div>
            <div class="em-detail-val" style="font-size:1rem;font-weight:700;">${e.title}</div>
        </div>
        ${e.description ? `<div>
            <div class="em-detail-lbl">Description</div>
            <div class="em-detail-val">${e.description}</div>
        </div>` : ''}
        <div class="em-detail-2col">
            <div>
                <div class="em-detail-lbl">Type</div>
                <div class="em-detail-val">${e.earning_type.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</div>
            </div>
            <div>
                <div class="em-detail-lbl">Value</div>
                <div class="em-detail-val">${e.value || '—'}</div>
            </div>
            <div>
                <div class="em-detail-lbl">Quantity Used</div>
                <div class="em-detail-val">${e.used_quantity} / ${e.quantity}</div>
            </div>
            <div>
                <div class="em-detail-lbl">Status</div>
                <div class="em-detail-val" style="font-weight:700;color:${emStatusColors[e.status]||'var(--text)'};">${e.status.toUpperCase()}</div>
            </div>
        </div>
        ${e.awarded_for ? `<div>
            <div class="em-detail-lbl">Awarded For</div>
            <div class="em-detail-box orange">${e.awarded_for}</div>
        </div>` : ''}
        ${e.redemption_instructions ? `<div>
            <div class="em-detail-lbl">Redemption Instructions</div>
            <div class="em-detail-val">${e.redemption_instructions}</div>
        </div>` : ''}
        ${e.redemption_code ? `<div>
            <div class="em-detail-lbl">Redemption Code</div>
            <div class="em-detail-box purple">${e.redemption_code}</div>
        </div>` : ''}
        <hr class="em-detail-sep">
        <div class="em-detail-2col">
            <div>
                <div class="em-detail-lbl">Awarded On</div>
                <div class="em-detail-val">${fmtDate(e.awarded_at)}</div>
            </div>
            ${e.expires_at ? `<div>
                <div class="em-detail-lbl">Expires On</div>
                <div class="em-detail-val">${fmtDate(e.expires_at)}</div>
            </div>` : ''}
            ${e.activated_at ? `<div>
                <div class="em-detail-lbl">Activated On</div>
                <div class="em-detail-val">${fmtDate(e.activated_at)}</div>
            </div>` : ''}
            ${e.redeemed_at ? `<div>
                <div class="em-detail-lbl">Redeemed On</div>
                <div class="em-detail-val">${fmtDate(e.redeemed_at)}</div>
            </div>` : ''}
            <div>
                <div class="em-detail-lbl">Awarded By</div>
                <div class="em-detail-val">${e.awarded_by}</div>
            </div>
        </div>
    </div>`;

    document.getElementById('emModalContent').innerHTML = html;
    document.getElementById('emViewModal').classList.add('open');
}

function emCloseModal() {
    document.getElementById('emViewModal').classList.remove('open');
}

document.getElementById('emViewModal').addEventListener('click', function(ev) {
    if (ev.target === this) emCloseModal();
});
</script>