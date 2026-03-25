<?php
// admin_modules/admin_rewards_manage.php
// Updated: supports locked/unlocked/activate_requested/activated/claimed flow

// Get students
$studentsRes = $db->query("SELECT id, full_name, domain_interest FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) $students[] = $row;

// ── Handle activate (admin approves student's activation request) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_activate_reward'])) {
    $rewardId = (int)$_POST['reward_id'];
    $rewardData = $db->query("SELECT * FROM student_rewards WHERE id=$rewardId AND status='activate_requested'")->fetch_assoc();
    if ($rewardData) {
        $db->query("UPDATE student_rewards SET status='activated' WHERE id=$rewardId");
        $sid = (int)$rewardData['student_id'];
        $titleEsc = $db->real_escape_string($rewardData['title']);
        $notifMsg = $db->real_escape_string("Your reward \"$titleEsc\" has been activated! Go to My Earnings to claim it.");
        $db->query("INSERT INTO student_notifications (student_id, title, message, type, link, created_at)
                   VALUES ($sid, '✅ Reward Activated!', '$notifMsg', 'system', 'earnings.php', NOW())");
        $_SESSION['admin_success'] = 'Reward activated! Student has been notified.';
    }
    ob_end_clean();
    header('Location: admin.php?tab=earnings');
    exit;
}

// Get all rewards
$rewardsRes = $db->query("
    SELECT r.*, s.full_name, s.email
    FROM student_rewards r
    JOIN internship_students s ON s.id = r.student_id
    ORDER BY
        FIELD(r.status,'activate_requested','locked','activated','unlocked','claimed'),
        r.awarded_at DESC
    LIMIT 200
");
$allRewards = [];
while ($row = $rewardsRes->fetch_assoc()) $allRewards[] = $row;

// Stats
$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='locked'              THEN 1 ELSE 0 END) as locked,
    SUM(CASE WHEN status='unlocked'            THEN 1 ELSE 0 END) as unlocked,
    SUM(CASE WHEN status='activate_requested'  THEN 1 ELSE 0 END) as act_req,
    SUM(CASE WHEN status='activated'           THEN 1 ELSE 0 END) as activated,
    SUM(CASE WHEN status='claimed'             THEN 1 ELSE 0 END) as claimed
    FROM student_rewards
")->fetch_assoc();

// Pending activation requests
$actRequests = array_filter($allRewards, fn($r) => $r['status'] === 'activate_requested');

$rewardTemplates = [
    ['type'=>'mentorship','icon'=>'👨‍💼','title'=>'1:1 Mentorship Session',       'subtitle'=>'Personal guidance session',          'color'=>'purple'],
    ['type'=>'software',  'icon'=>'🎨','title'=>'Canva Premium Access',           'subtitle'=>'Pro design features unlocked',        'color'=>'blue'],
    ['type'=>'software',  'icon'=>'💻','title'=>'Figma Professional',             'subtitle'=>'Full design platform access',         'color'=>'purple'],
    ['type'=>'resource',  'icon'=>'⚛️','title'=>'React Advanced Course',          'subtitle'=>'Complete mastery program',            'color'=>'blue'],
    ['type'=>'resource',  'icon'=>'📚','title'=>'Premium Learning Platform',      'subtitle'=>'Unlimited course access',             'color'=>'green'],
    ['type'=>'perk',      'icon'=>'⭐','title'=>'VIP Support Access',             'subtitle'=>'Priority assistance',                 'color'=>'orange'],
    ['type'=>'perk',      'icon'=>'🎯','title'=>'Early Feature Access',           'subtitle'=>'Beta program invite',                 'color'=>'orange'],
    ['type'=>'bonus',     'icon'=>'☕','title'=>'Coffee Gift Card',               'subtitle'=>'Fuel your productivity',              'color'=>'orange'],
];
?>

<style>
.adm-rew{padding:0;font-family:'Inter',sans-serif;}
:root{
    --bg:#f8fafc;--card:#fff;--card2:#f1f5f9;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;--red:#ef4444;--amber:#f59e0b;
    --pink:#ec4899;
}

/* ── Quick Stats ─────────────────────────────────────────────────────── */
.rew-stats{
    display:grid;grid-template-columns:repeat(6,1fr);
    gap:12px;margin-bottom:28px;
}
.rs-box{
    background:var(--card);border:1px solid var(--border);
    border-radius:12px;padding:16px 14px;text-align:center;
}
.rs-val{
    font-size:2rem;font-weight:900;
    background:linear-gradient(135deg,var(--o5),var(--purple));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;line-height:1;margin-bottom:6px;
}
.rs-lbl{font-size:.72rem;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;font-weight:700;}

/* ── Activation Requests Banner ─────────────────────────────────────── */
.act-requests-banner{
    background:linear-gradient(120deg,#fffbeb,#fef3c7);
    border:1.5px solid #fde68a;border-radius:14px;
    padding:20px 24px;margin-bottom:24px;
}
.arb-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:16px;
}
.arb-title{
    font-size:1rem;font-weight:800;color:#92400e;
    display:flex;align-items:center;gap:8px;
}
.arb-badge{
    background:#f59e0b;color:#fff;
    font-size:.68rem;font-weight:800;
    padding:2px 8px;border-radius:12px;
}
.act-list{display:flex;flex-direction:column;gap:10px;}
.act-item{
    background:#fff;border:1px solid #fde68a;border-radius:10px;
    padding:14px 16px;
    display:grid;grid-template-columns:auto 1fr auto;gap:14px;align-items:center;
}
.act-icon{font-size:1.8rem;}
.act-info{}
.act-name{font-size:.9rem;font-weight:700;color:var(--text);}
.act-meta{font-size:.75rem;color:var(--text3);margin-top:2px;}
.act-meta i{margin-right:4px;}
.btn-activate-now{
    padding:8px 18px;border-radius:8px;
    background:var(--o5);color:#fff;border:none;
    font-size:.82rem;font-weight:700;cursor:pointer;
    font-family:inherit;transition:all .18s;white-space:nowrap;
    display:flex;align-items:center;gap:6px;
}
.btn-activate-now:hover{background:var(--o6);box-shadow:0 4px 14px rgba(249,115,22,.35);}

/* ── Award Form ──────────────────────────────────────────────────────── */
.award-section{
    background:var(--card);border:1px solid var(--border);
    border-radius:16px;padding:24px;margin-bottom:24px;
}
.sec-title{
    font-size:1rem;font-weight:800;color:var(--text);
    display:flex;align-items:center;gap:8px;margin-bottom:20px;
}
.sec-title i{color:var(--o5);}
.tmpl-grid{
    display:grid;grid-template-columns:repeat(4,1fr);
    gap:10px;margin-bottom:20px;
}
.tmpl-card{
    padding:12px;background:var(--bg);border:2px solid var(--border);
    border-radius:10px;text-align:center;cursor:pointer;transition:all .18s;
}
.tmpl-card:hover{border-color:var(--o5);transform:translateY(-2px);}
.tmpl-card.selected{border-color:var(--o5);background:#fff7ed;}
.tmpl-icon{font-size:1.6rem;margin-bottom:6px;}
.tmpl-name{font-size:.75rem;font-weight:700;color:var(--text);}

.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}
.fg{margin-bottom:0;}
.fg.full{grid-column:1/-1;}
.fl{display:block;font-size:.78rem;font-weight:700;color:var(--text);margin-bottom:6px;}
.fl .req{color:var(--red);}
.fi,.fs,.fta{
    width:100%;padding:10px 13px;
    background:var(--bg);border:1.5px solid var(--border);
    border-radius:9px;font-size:.87rem;font-family:inherit;
    color:var(--text);transition:all .18s;
}
.fi:focus,.fs:focus,.fta:focus{outline:none;border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,.1);}
.fta{min-height:72px;resize:vertical;}
.fh{font-size:.7rem;color:var(--text3);margin-top:4px;}

/* Color picker */
.color-row{display:flex;gap:8px;margin-top:7px;}
.col-swatch{
    width:30px;height:30px;border-radius:7px;cursor:pointer;
    border:2.5px solid transparent;transition:all .18s;
}
.col-swatch:hover{transform:scale(1.1);}
.col-swatch.sel{border-color:#fff;box-shadow:0 0 0 2px var(--border);}
.cs-orange{background:var(--o5);}
.cs-purple{background:var(--purple);}
.cs-blue{background:var(--blue);}
.cs-green{background:var(--green);}
.cs-pink{background:var(--pink);}

.btn-group{display:flex;gap:10px;margin-top:20px;}
.btn{
    padding:10px 20px;border-radius:9px;font-size:.87rem;font-weight:700;
    font-family:inherit;cursor:pointer;border:none;transition:all .18s;
    display:inline-flex;align-items:center;gap:7px;
}
.btn-primary{
    background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;
    box-shadow:0 4px 14px rgba(249,115,22,.3);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 22px rgba(249,115,22,.45);}
.btn-ghost{
    background:var(--bg);border:1.5px solid var(--border);color:var(--text2);
}
.btn-ghost:hover{border-color:var(--o5);color:var(--o5);}

/* Bulk award */
.bulk-section{
    background:linear-gradient(120deg,#f0f9ff,#eff6ff);
    border:1.5px solid #bfdbfe;border-radius:14px;
    padding:20px 24px;margin-bottom:24px;
}

/* ── Rewards Table ───────────────────────────────────────────────────── */
.rewards-list{
    background:var(--card);border:1px solid var(--border);
    border-radius:16px;overflow:hidden;
}
.rl-header{
    padding:16px 22px;border-bottom:1px solid var(--border);
    background:var(--bg);display:flex;align-items:center;justify-content:space-between;
}
.rl-title{font-size:.95rem;font-weight:800;color:var(--text);}
.rl-search{
    padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;
    font-size:.82rem;font-family:inherit;color:var(--text);
    background:var(--card);width:200px;
}
.rl-search:focus{outline:none;border-color:var(--o5);}

.reward-row{
    padding:16px 22px;border-bottom:1px solid var(--border);
    display:grid;grid-template-columns:50px 1fr auto auto 140px;
    gap:16px;align-items:center;transition:background .15s;
}
.reward-row:hover{background:var(--bg);}
.reward-row:last-child{border-bottom:none;}

.rr-icon{
    width:48px;height:48px;border-radius:11px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.7rem;background:var(--bg);
    border:1px solid var(--border);flex-shrink:0;
}
.rr-info{}
.rr-title{font-size:.9rem;font-weight:700;color:var(--text);}
.rr-meta{
    font-size:.74rem;color:var(--text3);margin-top:3px;
    display:flex;gap:12px;flex-wrap:wrap;
}
.rr-meta i{margin-right:3px;}
.rr-student{font-weight:600;color:var(--text2);}

/* Status badges */
.sbadge{
    padding:4px 12px;border-radius:20px;
    font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;
    white-space:nowrap;
}
.sb-locked{background:#f1f5f9;color:var(--text3);}
.sb-unlocked{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.sb-activate_requested{background:#fffbeb;color:#b45309;border:1px solid #fde68a;animation:reqBlink 2s ease infinite;}
@keyframes reqBlink{0%,100%{opacity:1;}50%{opacity:.6;}}
.sb-activated{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.sb-claimed{background:#f1f5f9;color:var(--text3);}

.rr-actions{display:flex;gap:6px;justify-content:flex-end;}
.icon-btn{
    width:32px;height:32px;border-radius:7px;border:1.5px solid var(--border);
    background:var(--bg);cursor:pointer;display:flex;align-items:center;
    justify-content:center;color:var(--text3);transition:all .18s;font-size:.82rem;
}
.icon-btn:hover{border-color:var(--o5);color:var(--o5);}
.icon-btn.del:hover{border-color:var(--red);color:var(--red);}
.icon-btn.go:hover{border-color:var(--green);color:var(--green);}

.empty-list{
    padding:48px;text-align:center;color:var(--text3);
}
.empty-list i{font-size:2.4rem;opacity:.25;margin-bottom:12px;display:block;}

/* Tooltips via title */
@media(max-width:1100px){
    .rew-stats{grid-template-columns:repeat(3,1fr);}
    .tmpl-grid{grid-template-columns:repeat(2,1fr);}
    .form-grid{grid-template-columns:1fr;}
    .reward-row{grid-template-columns:48px 1fr auto;}
    .rr-student-col,.rr-actions{display:none;}
}
</style>

<div class="adm-rew">

    <!-- Quick Stats -->
    <div class="rew-stats">
        <div class="rs-box">
            <div class="rs-val"><?php echo number_format($stats['total']); ?></div>
            <div class="rs-lbl">Total</div>
        </div>
        <div class="rs-box">
            <div class="rs-val" style="background:linear-gradient(135deg,#94a3b8,#64748b);-webkit-background-clip:text;background-clip:text;"><?php echo number_format($stats['locked']); ?></div>
            <div class="rs-lbl">Locked</div>
        </div>
        <div class="rs-box">
            <div class="rs-val" style="background:linear-gradient(135deg,#22c55e,#16a34a);-webkit-background-clip:text;background-clip:text;"><?php echo number_format($stats['unlocked']); ?></div>
            <div class="rs-lbl">Unlocked</div>
        </div>
        <div class="rs-box">
            <div class="rs-val" style="background:linear-gradient(135deg,#f59e0b,#d97706);-webkit-background-clip:text;background-clip:text;"><?php echo number_format($stats['act_req']); ?></div>
            <div class="rs-lbl">Activation Req.</div>
        </div>
        <div class="rs-box">
            <div class="rs-val" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);-webkit-background-clip:text;background-clip:text;"><?php echo number_format($stats['activated']); ?></div>
            <div class="rs-lbl">Activated</div>
        </div>
        <div class="rs-box">
            <div class="rs-val" style="background:linear-gradient(135deg,#a855f7,#7c3aed);-webkit-background-clip:text;background-clip:text;"><?php echo number_format($stats['claimed']); ?></div>
            <div class="rs-lbl">Claimed</div>
        </div>
    </div>

    <!-- Activation Requests -->
    <?php if (!empty($actRequests)): ?>
    <div class="act-requests-banner">
        <div class="arb-header">
            <div class="arb-title">
                <i class="fas fa-bell"></i>
                Pending Activation Requests
                <span class="arb-badge"><?php echo count($actRequests); ?></span>
            </div>
        </div>
        <div class="act-list">
            <?php foreach ($actRequests as $ar): ?>
            <div class="act-item">
                <div class="act-icon"><?php echo $ar['icon'] ?? '🎁'; ?></div>
                <div class="act-info">
                    <div class="act-name"><?php echo htmlspecialchars($ar['title']); ?></div>
                    <div class="act-meta">
                        <span class="rr-student"><i class="fas fa-user"></i><?php echo htmlspecialchars($ar['full_name']); ?></span>
                        <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($ar['awarded_at'])); ?></span>
                        <?php if ($ar['value']): ?>
                        <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($ar['value']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="admin_activate_reward" value="1">
                    <input type="hidden" name="reward_id" value="<?php echo $ar['id']; ?>">
                    <button type="submit" class="btn-activate-now">
                        <i class="fas fa-bolt"></i> Activate Now
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Award Single Reward -->
    <div class="award-section">
        <div class="sec-title">
            <i class="fas fa-gift"></i>
            Award Reward to Student
        </div>

        <!-- Templates -->
        <div style="margin-bottom:16px;">
            <div style="font-size:.75rem;font-weight:700;color:var(--text);margin-bottom:10px;">Quick Templates</div>
            <div class="tmpl-grid">
                <?php foreach ($rewardTemplates as $tmpl): ?>
                <div class="tmpl-card" onclick="useTemplate(<?php echo htmlspecialchars(json_encode($tmpl)); ?>)">
                    <div class="tmpl-icon"><?php echo $tmpl['icon']; ?></div>
                    <div class="tmpl-name"><?php echo $tmpl['title']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="POST" action="admin.php" id="awardForm">
            <input type="hidden" name="award_reward" value="1">
            <div class="form-grid">
                <div class="fg">
                    <label class="fl">Student <span class="req">*</span></label>
                    <select name="student_id" class="fs" required>
                        <option value="">Select student...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label class="fl">Reward Type <span class="req">*</span></label>
                    <select name="reward_type" class="fs" required>
                        <option value="mentorship">Mentorship</option>
                        <option value="software">Software Access</option>
                        <option value="resource">Learning Resource</option>
                        <option value="perk">Exclusive Perk</option>
                        <option value="bonus">Bonus Reward</option>
                    </select>
                </div>
                <div class="fg">
                    <label class="fl">Title <span class="req">*</span></label>
                    <input type="text" name="title" id="tmpl_title" class="fi" placeholder="e.g., 1:1 CTO Mentorship" required>
                </div>
                <div class="fg">
                    <label class="fl">Subtitle</label>
                    <input type="text" name="subtitle" id="tmpl_subtitle" class="fi" placeholder="Short description">
                </div>
                <div class="fg">
                    <label class="fl">Icon / Emoji</label>
                    <input type="text" name="icon" id="tmpl_icon" class="fi" placeholder="🎁" maxlength="10">
                </div>
                <div class="fg">
                    <label class="fl">Value / Duration</label>
                    <input type="text" name="value" class="fi" placeholder="e.g., 60 min, 7 days, Lifetime">
                </div>
                <div class="fg">
                    <label class="fl">Color Theme</label>
                    <div class="color-row">
                        <div class="col-swatch cs-orange sel" onclick="selColor(this,'orange')" title="Orange"></div>
                        <div class="col-swatch cs-purple" onclick="selColor(this,'purple')" title="Purple"></div>
                        <div class="col-swatch cs-blue"   onclick="selColor(this,'blue')"   title="Blue"></div>
                        <div class="col-swatch cs-green"  onclick="selColor(this,'green')"  title="Green"></div>
                        <div class="col-swatch cs-pink"   onclick="selColor(this,'pink')"   title="Pink"></div>
                    </div>
                    <input type="hidden" name="color" id="tmpl_color" value="orange">
                </div>
                <div class="fg">
                    <label class="fl">Expiry Date</label>
                    <input type="date" name="expires_at" class="fi">
                    <div class="fh">Leave empty for no expiry</div>
                </div>
                <div class="fg full">
                    <label class="fl">Awarded For <span class="req">*</span></label>
                    <input type="text" name="awarded_for" class="fi" placeholder="e.g., Top performer – Web Dev Jan 2025" required>
                </div>
                <div class="fg full">
                    <label class="fl">Redemption Instructions</label>
                    <textarea name="instructions" class="fta" placeholder="How should the student redeem this? Keep it brief."></textarea>
                </div>
                <div class="fg" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="priority" value="1" id="chk_prio" style="width:auto;">
                    <label for="chk_prio" style="font-size:.82rem;font-weight:600;color:var(--text);cursor:pointer;">Mark as Featured</label>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><i class="fas fa-gift"></i> Award Reward</button>
                <button type="reset" class="btn btn-ghost"><i class="fas fa-undo"></i> Reset</button>
            </div>
        </form>
    </div>

    <!-- Bulk Award -->
    <div class="bulk-section">
        <div class="sec-title" style="margin-bottom:16px;"><i class="fas fa-users" style="color:var(--blue);"></i> Bulk Award (Multiple Students)</div>
        <form method="POST" action="admin.php">
            <input type="hidden" name="bulk_award_rewards" value="1">
            <div class="form-grid" style="margin-bottom:14px;">
                <div class="fg full" style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($students as $s): ?>
                    <label style="display:inline-flex;align-items:center;gap:5px;font-size:.8rem;font-weight:600;cursor:pointer;
                                  padding:5px 11px;background:#fff;border:1.5px solid var(--border);border-radius:20px;
                                  transition:all .15s;user-select:none;"
                           onmouseenter="this.style.borderColor='#3b82f6'" onmouseleave="this.style.borderColor='#e2e8f0'">
                        <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" style="accent-color:var(--blue);">
                        <?php echo htmlspecialchars($s['full_name']); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="fg">
                    <label class="fl">Title <span class="req">*</span></label>
                    <input type="text" name="title" class="fi" placeholder="Reward title" required>
                </div>
                <div class="fg">
                    <label class="fl">Awarded For <span class="req">*</span></label>
                    <input type="text" name="awarded_for" class="fi" placeholder="Reason for award" required>
                </div>
                <div class="fg">
                    <label class="fl">Icon</label>
                    <input type="text" name="icon" class="fi" placeholder="🎁" maxlength="10">
                </div>
                <div class="fg">
                    <label class="fl">Value</label>
                    <input type="text" name="value" class="fi" placeholder="60 min, Lifetime...">
                </div>
                <div class="fg">
                    <label class="fl">Type</label>
                    <select name="reward_type" class="fs">
                        <option value="bonus">Bonus</option>
                        <option value="mentorship">Mentorship</option>
                        <option value="software">Software</option>
                        <option value="resource">Resource</option>
                        <option value="perk">Perk</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="background:var(--blue);box-shadow:0 4px 14px rgba(59,130,246,.25);">
                <i class="fas fa-paper-plane"></i> Award to Selected Students
            </button>
        </form>
    </div>

    <!-- Rewards List -->
    <div class="rewards-list">
        <div class="rl-header">
            <div class="rl-title">All Rewards</div>
            <input type="text" class="rl-search" id="rewSearch" placeholder="🔍  Search rewards..." oninput="filterRewRows(this.value)">
        </div>

        <?php if (empty($allRewards)): ?>
        <div class="empty-list">
            <i class="fas fa-gift"></i>
            <p>No rewards awarded yet.</p>
        </div>
        <?php else: ?>
        <?php foreach ($allRewards as $r):
            $status = $r['status'];
            $sbCls  = 'sbadge sb-' . $status;
            $statusLabel = match($status) {
                'locked'             => 'Locked',
                'unlocked'           => 'Unlocked',
                'activate_requested' => '⏳ Activation Req.',
                'activated'          => 'Activated',
                'claimed'            => 'Claimed',
                default              => ucfirst($status),
            };
        ?>
        <div class="reward-row" data-search="<?php echo strtolower(htmlspecialchars($r['title'] . ' ' . $r['full_name'] . ' ' . $status)); ?>">
            <div class="rr-icon"><?php echo $r['icon'] ?? '🎁'; ?></div>
            <div class="rr-info">
                <div class="rr-title"><?php echo htmlspecialchars($r['title']); ?></div>
                <div class="rr-meta">
                    <span class="rr-student"><i class="fas fa-user"></i><?php echo htmlspecialchars($r['full_name']); ?></span>
                    <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($r['awarded_at'])); ?></span>
                    <?php if ($r['value']): ?><span><i class="fas fa-tag"></i><?php echo htmlspecialchars($r['value']); ?></span><?php endif; ?>
                    <?php if ($r['code']): ?><span><i class="fas fa-ticket-alt"></i><?php echo htmlspecialchars($r['code']); ?></span><?php endif; ?>
                </div>
            </div>
            <span class="<?php echo $sbCls; ?>"><?php echo $statusLabel; ?></span>
            <div class="rr-actions">
                <?php if ($status === 'activate_requested'): ?>
                <form method="POST" action="admin.php" style="display:inline;">
                    <input type="hidden" name="admin_activate_reward" value="1">
                    <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                    <button type="submit" class="icon-btn go" title="Activate this reward">
                        <i class="fas fa-bolt"></i>
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="admin.php" style="display:inline;"
                      onsubmit="return confirm('Delete reward for <?php echo htmlspecialchars($r['full_name']); ?>?')">
                    <input type="hidden" name="delete_reward" value="1">
                    <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                    <button type="submit" class="icon-btn del" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /adm-rew -->

<script>
function useTemplate(tmpl) {
    document.getElementById('tmpl_title').value    = tmpl.title;
    document.getElementById('tmpl_subtitle').value = tmpl.subtitle;
    document.getElementById('tmpl_icon').value     = tmpl.icon;
    const sw = document.querySelector('.col-swatch.cs-' + tmpl.color);
    if (sw) selColor(sw, tmpl.color);
    document.querySelectorAll('.tmpl-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

function selColor(el, color) {
    document.querySelectorAll('.col-swatch').forEach(s => s.classList.remove('sel'));
    el.classList.add('sel');
    document.getElementById('tmpl_color').value = color;
}

function filterRewRows(q) {
    const lq = q.toLowerCase();
    document.querySelectorAll('.reward-row').forEach(row => {
        row.style.display = row.dataset.search.includes(lq) ? '' : 'none';
    });
}
</script>