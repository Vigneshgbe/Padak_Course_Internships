<?php
// admin_modules/admin_rewards_manage.php
// Streamlined admin interface for managing student rewards

// Get active students
$studentsRes = $db->query("SELECT id, full_name, email, domain_interest FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) $students[] = $row;

// Get all rewards
$rewardsRes = $db->query("
    SELECT r.*, s.full_name, s.email
    FROM student_rewards r
    JOIN internship_students s ON s.id = r.student_id
    ORDER BY r.awarded_at DESC
    LIMIT 100
");
$allRewards = [];
while ($row = $rewardsRes->fetch_assoc()) $allRewards[] = $row;

// Get stats
$stats = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='locked' THEN 1 ELSE 0 END) as locked,
    SUM(CASE WHEN status='unlocked' THEN 1 ELSE 0 END) as unlocked,
    SUM(CASE WHEN status='claimed' THEN 1 ELSE 0 END) as claimed
    FROM student_rewards
")->fetch_assoc();

$rewardTemplates = [
    ['type'=>'mentorship', 'icon'=>'👨‍💼', 'title'=>'1:1 Mentorship Session', 'subtitle'=>'Personal guidance session', 'color'=>'purple'],
    ['type'=>'software', 'icon'=>'🎨', 'title'=>'Canva Premium Access', 'subtitle'=>'Pro features unlocked', 'color'=>'blue'],
    ['type'=>'software', 'icon'=>'💻', 'title'=>'Figma Professional', 'subtitle'=>'Full design platform access', 'color'=>'purple'],
    ['type'=>'resource', 'icon'=>'⚛️', 'title'=>'React Advanced Course', 'subtitle'=>'Complete mastery program', 'color'=>'blue'],
    ['type'=>'resource', 'icon'=>'📚', 'title'=>'Premium Learning Platform', 'subtitle'=>'Unlimited course access', 'color'=>'green'],
    ['type'=>'perk', 'icon'=>'⭐', 'title'=>'VIP Support Access', 'subtitle'=>'Priority assistance', 'color'=>'orange'],
    ['type'=>'perk', 'icon'=>'🎯', 'title'=>'Early Feature Access', 'subtitle'=>'Beta program invite', 'color'=>'pink'],
    ['type'=>'bonus', 'icon'=>'☕', 'title'=>'Coffee Gift Card', 'subtitle'=>'Fuel your productivity', 'color'=>'orange'],
];
?>

<style>
.admin-rewards-wrap{
    background:var(--bg);
    border-radius:16px;
    padding:28px;
}

/* Quick Stats */
.quick-stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:32px;
}
.stat-box{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:12px;
    padding:20px;
    text-align:center;
}
.stat-value{
    font-size:2.5rem;
    font-weight:900;
    background:linear-gradient(135deg,var(--o5),var(--purple));
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    margin-bottom:8px;
}
.stat-label{
    font-size:0.85rem;
    color:var(--text2);
    text-transform:uppercase;
    letter-spacing:0.05em;
}

/* Award Form */
.award-section{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    padding:28px;
    margin-bottom:28px;
}
.section-title{
    font-size:1.3rem;
    font-weight:700;
    color:var(--text);
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:10px;
}
.section-title i{
    color:var(--o5);
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
    margin-bottom:20px;
}
.form-group{
    margin-bottom:0;
}
.form-group.full{
    grid-column:1/-1;
}
.form-label{
    display:block;
    font-size:0.85rem;
    font-weight:600;
    color:var(--text);
    margin-bottom:8px;
}
.form-label .req{
    color:var(--red);
}
.form-input, .form-select, .form-textarea{
    width:100%;
    padding:12px 16px;
    background:var(--bg);
    border:1.5px solid var(--border);
    border-radius:10px;
    font-size:0.9rem;
    font-family:inherit;
    color:var(--text);
    transition:all 0.2s;
}
.form-input:focus, .form-select:focus, .form-textarea:focus{
    outline:none;
    border-color:var(--o5);
    box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}
.form-textarea{
    min-height:80px;
    resize:vertical;
}
.form-hint{
    font-size:0.75rem;
    color:var(--text3);
    margin-top:6px;
}

/* Template Selector */
.template-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin-bottom:24px;
}
.template-card{
    padding:16px;
    background:var(--bg);
    border:2px solid var(--border);
    border-radius:12px;
    text-align:center;
    cursor:pointer;
    transition:all 0.2s;
}
.template-card:hover{
    border-color:var(--o5);
    transform:translateY(-2px);
}
.template-card.selected{
    border-color:var(--o5);
    background:rgba(249,115,22,0.1);
}
.template-icon{
    font-size:2rem;
    margin-bottom:8px;
}
.template-title{
    font-size:0.85rem;
    font-weight:600;
    color:var(--text);
}

/* Color Picker */
.color-picker{
    display:flex;
    gap:10px;
    margin-top:8px;
}
.color-option{
    width:36px;
    height:36px;
    border-radius:8px;
    cursor:pointer;
    border:3px solid transparent;
    transition:all 0.2s;
}
.color-option:hover{
    transform:scale(1.1);
}
.color-option.selected{
    border-color:#fff;
    box-shadow:0 0 0 2px var(--border);
}
.color-option.orange{background:var(--o5);}
.color-option.purple{background:var(--purple);}
.color-option.blue{background:var(--blue);}
.color-option.green{background:var(--green);}
.color-option.pink{background:var(--pink);}

/* Buttons */
.btn-group{
    display:flex;
    gap:12px;
    margin-top:24px;
}
.btn{
    padding:12px 24px;
    border-radius:10px;
    font-size:0.9rem;
    font-weight:600;
    font-family:inherit;
    cursor:pointer;
    border:none;
    transition:all 0.2s;
    display:inline-flex;
    align-items:center;
    gap:8px;
}
.btn-primary{
    background:linear-gradient(135deg,var(--o5),var(--o4));
    color:#fff;
    box-shadow:0 4px 16px rgba(249,115,22,0.3);
}
.btn-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 24px rgba(249,115,22,0.5);
}
.btn-secondary{
    background:var(--bg);
    border:2px solid var(--border);
    color:var(--text2);
}
.btn-secondary:hover{
    border-color:var(--o5);
    color:var(--o5);
}

/* Rewards List */
.rewards-list{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
}
.list-header{
    padding:20px 24px;
    border-bottom:1px solid var(--border);
    background:var(--bg);
}
.list-title{
    font-size:1.2rem;
    font-weight:700;
    color:var(--text);
}

.reward-item{
    padding:20px 24px;
    border-bottom:1px solid var(--border);
    display:grid;
    grid-template-columns:60px 1fr auto auto;
    gap:20px;
    align-items:center;
    transition:background 0.2s;
}
.reward-item:hover{
    background:var(--bg);
}
.reward-item:last-child{
    border-bottom:none;
}

.reward-icon-box{
    width:60px;
    height:60px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:2rem;
    background:rgba(249,115,22,0.1);
}

.reward-info{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.reward-title{
    font-size:1rem;
    font-weight:700;
    color:var(--text);
}
.reward-meta{
    font-size:0.8rem;
    color:var(--text3);
    display:flex;
    gap:16px;
}
.reward-meta i{
    margin-right:4px;
}

.reward-status{
    padding:6px 16px;
    border-radius:20px;
    font-size:0.75rem;
    font-weight:700;
    text-transform:uppercase;
}
.status-locked{background:rgba(249,115,22,0.2);color:var(--o5);}
.status-unlocked{background:rgba(16,185,129,0.2);color:var(--green);}
.status-claimed{background:rgba(96,96,96,0.2);color:var(--text3);}

.reward-actions{
    display:flex;
    gap:8px;
}
.icon-btn{
    width:36px;
    height:36px;
    border-radius:8px;
    border:1.5px solid var(--border);
    background:var(--bg);
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--text2);
    transition:all 0.2s;
}
.icon-btn:hover{
    border-color:var(--o5);
    color:var(--o5);
}
.icon-btn.delete:hover{
    border-color:var(--red);
    color:var(--red);
}

/* Empty State */
.empty-state{
    padding:60px 20px;
    text-align:center;
    color:var(--text3);
}
.empty-state i{
    font-size:3rem;
    opacity:0.3;
    margin-bottom:16px;
}

@media(max-width:1200px){
    .quick-stats{grid-template-columns:repeat(2,1fr);}
    .form-grid{grid-template-columns:1fr;}
    .template-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:768px){
    .quick-stats{grid-template-columns:1fr;}
    .template-grid{grid-template-columns:1fr;}
    .reward-item{grid-template-columns:1fr;gap:12px;}
    .reward-actions{width:100%;justify-content:flex-end;}
}
</style>

<div class="admin-rewards-wrap">
    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Rewards</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format($stats['locked']); ?></div>
            <div class="stat-label">Locked</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format($stats['unlocked']); ?></div>
            <div class="stat-label">Unlocked</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format($stats['claimed']); ?></div>
            <div class="stat-label">Claimed</div>
        </div>
    </div>

    <!-- Award Form -->
    <div class="award-section">
        <div class="section-title">
            <i class="fas fa-gift"></i>
            Award New Reward
        </div>

        <!-- Template Selector -->
        <div class="form-group full">
            <label class="form-label">Quick Templates (Optional)</label>
            <div class="template-grid">
                <?php foreach ($rewardTemplates as $i => $tmpl): ?>
                <div class="template-card" onclick="useTemplate(<?php echo htmlspecialchars(json_encode($tmpl)); ?>)">
                    <div class="template-icon"><?php echo $tmpl['icon']; ?></div>
                    <div class="template-title"><?php echo $tmpl['title']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="POST" action="admin.php" id="awardForm">
            <input type="hidden" name="award_reward" value="1">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        Student <span class="req">*</span>
                    </label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Select student...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>">
                            <?php echo htmlspecialchars($s['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Reward Type <span class="req">*</span>
                    </label>
                    <select name="reward_type" class="form-select" required>
                        <option value="mentorship">Mentorship</option>
                        <option value="software">Software Access</option>
                        <option value="resource">Learning Resource</option>
                        <option value="perk">Exclusive Perk</option>
                        <option value="bonus">Bonus Reward</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Title <span class="req">*</span>
                    </label>
                    <input type="text" name="title" id="title" class="form-input" 
                           placeholder="e.g., 1:1 CTO Mentorship" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Subtitle
                    </label>
                    <input type="text" name="subtitle" id="subtitle" class="form-input" 
                           placeholder="Short description">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Icon/Emoji
                    </label>
                    <input type="text" name="icon" id="icon" class="form-input" 
                           placeholder="🎁" maxlength="10">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Value (Duration/Quantity)
                    </label>
                    <input type="text" name="value" class="form-input" 
                           placeholder="e.g., 60 min, 7 days, Lifetime">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Color Theme
                    </label>
                    <div class="color-picker">
                        <div class="color-option orange selected" onclick="selectColor(this, 'orange')"></div>
                        <div class="color-option purple" onclick="selectColor(this, 'purple')"></div>
                        <div class="color-option blue" onclick="selectColor(this, 'blue')"></div>
                        <div class="color-option green" onclick="selectColor(this, 'green')"></div>
                        <div class="color-option pink" onclick="selectColor(this, 'pink')"></div>
                    </div>
                    <input type="hidden" name="color" id="color" value="orange">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Expiry Date
                    </label>
                    <input type="date" name="expires_at" class="form-input">
                    <div class="form-hint">Leave empty for no expiry</div>
                </div>

                <div class="form-group full">
                    <label class="form-label">
                        Awarded For <span class="req">*</span>
                    </label>
                    <input type="text" name="awarded_for" class="form-input" 
                           placeholder="e.g., Top performer - Web Development January 2024" required>
                </div>

                <div class="form-group full">
                    <label class="form-label">
                        Redemption Instructions
                    </label>
                    <textarea name="instructions" class="form-textarea" 
                              placeholder="How should the student redeem this? Keep it brief and clear."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="priority" value="1" style="width:auto;margin-right:8px;">
                        Mark as Priority/Featured
                    </label>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-gift"></i>
                    Award Reward
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i>
                    Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Rewards List -->
    <div class="rewards-list">
        <div class="list-header">
            <div class="list-title">All Rewards</div>
        </div>
        <?php if (empty($allRewards)): ?>
        <div class="empty-state">
            <i class="fas fa-gift"></i>
            <p>No rewards awarded yet</p>
        </div>
        <?php else: ?>
            <?php foreach ($allRewards as $r): ?>
            <div class="reward-item">
                <div class="reward-icon-box">
                    <?php echo $r['icon'] ?? '🎁'; ?>
                </div>
                <div class="reward-info">
                    <div class="reward-title"><?php echo htmlspecialchars($r['title']); ?></div>
                    <div class="reward-meta">
                        <span><i class="fas fa-user"></i><?php echo htmlspecialchars($r['full_name']); ?></span>
                        <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($r['awarded_at'])); ?></span>
                        <?php if ($r['value']): ?>
                        <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($r['value']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="reward-status status-<?php echo $r['status']; ?>">
                    <?php echo ucfirst($r['status']); ?>
                </div>
                <div class="reward-actions">
                    <button class="icon-btn" onclick="viewRewardDetails(<?php echo htmlspecialchars(json_encode($r)); ?>)" 
                            title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <form method="POST" action="admin.php" style="display:inline;" 
                          onsubmit="return confirm('Delete this reward?')">
                        <input type="hidden" name="delete_reward" value="1">
                        <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                        <button type="submit" class="icon-btn delete" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Template Selection
function useTemplate(tmpl) {
    document.getElementById('title').value = tmpl.title;
    document.getElementById('subtitle').value = tmpl.subtitle;
    document.getElementById('icon').value = tmpl.icon;
    selectColor(document.querySelector('.color-option.' + tmpl.color), tmpl.color);
}

// Color Selection
function selectColor(element, color) {
    document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('color').value = color;
}

// View Details (can implement modal if needed)
function viewRewardDetails(reward) {
    alert(`Reward: ${reward.title}\nStudent: ${reward.full_name}\nStatus: ${reward.status}\nCode: ${reward.code || 'Not yet generated'}`);
}
</script>