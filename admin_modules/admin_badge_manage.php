<?php
// admin_badges_manage.php — Display only. POST handling goes in admin.php top (before HTML).
// Add these POST handlers to admin.php early POST section:
//   - create_badge / update_badge / delete_badge => tab=badges
//   - award_badge / revoke_badge                => tab=badges

// -----------------------------------------------------------------------
// Required POST handlers to paste into admin.php early POST section:
// -----------------------------------------------------------------------
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_badge'])) {
//     $name=$db->real_escape_string(trim($_POST['name']??''));
//     $desc=$db->real_escape_string(trim($_POST['description']??''));
//     $icon=$db->real_escape_string(trim($_POST['icon']??'🏅'));
//     $tier=$db->real_escape_string($_POST['tier']??'bronze');
//     $cat=$db->real_escape_string(trim($_POST['category']??'general'));
//     $pts=(int)($_POST['points_bonus']??0);
//     $awdFor=$db->real_escape_string(trim($_POST['awarded_for']??''));
//     $active=isset($_POST['is_active'])?1:0;
//     if(empty($name)){$_SESSION['admin_error']='Badge name required';}
//     else{
//         $db->query("INSERT INTO badges(name,description,icon,tier,category,points_bonus,awarded_for,is_active,created_at)VALUES('$name','$desc','$icon','$tier','$cat',$pts,'$awdFor',$active,NOW())");
//         $_SESSION['admin_success']='Badge created!';
//     }
//     ob_end_clean(); header('Location: admin.php?tab=badges'); exit;
// }
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_badge'])) {
//     $bid=(int)$_POST['badge_id'];
//     $name=$db->real_escape_string(trim($_POST['name']??''));
//     $desc=$db->real_escape_string(trim($_POST['description']??''));
//     $icon=$db->real_escape_string(trim($_POST['icon']??'🏅'));
//     $tier=$db->real_escape_string($_POST['tier']??'bronze');
//     $cat=$db->real_escape_string(trim($_POST['category']??'general'));
//     $pts=(int)($_POST['points_bonus']??0);
//     $awdFor=$db->real_escape_string(trim($_POST['awarded_for']??''));
//     $active=isset($_POST['is_active'])?1:0;
//     $db->query("UPDATE badges SET name='$name',description='$desc',icon='$icon',tier='$tier',category='$cat',points_bonus=$pts,awarded_for='$awdFor',is_active=$active,updated_at=NOW() WHERE id=$bid");
//     $_SESSION['admin_success']='Badge updated!';
//     ob_end_clean(); header('Location: admin.php?tab=badges'); exit;
// }
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_badge'])) {
//     $bid=(int)$_POST['badge_id'];
//     $db->query("DELETE FROM student_badges WHERE badge_id=$bid");
//     $db->query("DELETE FROM badges WHERE id=$bid");
//     $_SESSION['admin_success']='Badge deleted!';
//     ob_end_clean(); header('Location: admin.php?tab=badges'); exit;
// }
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_badge'])) {
//     $sid=(int)$_POST['student_id']; $bid=(int)$_POST['badge_id'];
//     $note=$db->real_escape_string(trim($_POST['award_note']??''));
//     $exists=$db->query("SELECT id FROM student_badges WHERE student_id=$sid AND badge_id=$bid")->num_rows;
//     if($exists){$_SESSION['admin_error']='Student already has this badge!';}
//     else{
//         $db->query("INSERT INTO student_badges(student_id,badge_id,award_note,awarded_by,awarded_at)VALUES($sid,$bid,'$note','Admin',NOW())");
//         $badge=$db->query("SELECT points_bonus,name FROM badges WHERE id=$bid")->fetch_assoc();
//         if($badge&&$badge['points_bonus']>0){
//             $reason=$db->real_escape_string('Badge awarded: '.$badge['name']);
//             $db->query("INSERT INTO student_points_log(student_id,points,reason,awarded_at)VALUES($sid,{$badge['points_bonus']},'$reason',NOW())");
//             $total=(int)$db->query("SELECT SUM(points) t FROM student_points_log WHERE student_id=$sid")->fetch_assoc()['t'];
//             $db->query("UPDATE internship_students SET total_points=$total WHERE id=$sid");
//         }
//         $msg=$db->real_escape_string('You earned the "'.$badge['name'].'" badge! '.($badge['points_bonus']>0?'+'.$badge['points_bonus'].' bonus points!':''));
//         $db->query("INSERT INTO student_notifications(student_id,title,message,type,created_at)VALUES($sid,'Badge Awarded!','$msg','system',NOW())");
//         $_SESSION['admin_success']='Badge awarded successfully!';
//     }
//     ob_end_clean(); header('Location: admin.php?tab=badges'); exit;
// }
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_badge'])) {
//     $sbid=(int)$_POST['student_badge_id'];
//     $db->query("DELETE FROM student_badges WHERE id=$sbid");
//     $_SESSION['admin_success']='Badge revoked!';
//     ob_end_clean(); header('Location: admin.php?tab=badges'); exit;
// }
// -----------------------------------------------------------------------

// Fetch all badges
$badgesRes = $db->query("SELECT b.*, (SELECT COUNT(*) FROM student_badges WHERE badge_id=b.id) as awarded_count FROM badges b ORDER BY b.tier ASC, b.name ASC");
$badges = [];
while ($r = $badgesRes->fetch_assoc()) $badges[] = $r;

// Fetch all students
$studentsRes = $db->query("SELECT id, full_name, email, domain_interest, total_points FROM internship_students WHERE is_active=1 ORDER BY full_name ASC");
$students = [];
while ($r = $studentsRes->fetch_assoc()) $students[] = $r;

// Fetch awarded badges log (recent)
$awardedRes = $db->query("
    SELECT sb.*, b.name as badge_name, b.icon, b.tier, b.points_bonus, s.full_name as student_name, s.email
    FROM student_badges sb
    JOIN badges b ON sb.badge_id = b.id
    JOIN internship_students s ON sb.student_id = s.id
    ORDER BY sb.awarded_at DESC
    LIMIT 100
");
$awardedLog = [];
while ($r = $awardedRes->fetch_assoc()) $awardedLog[] = $r;

// Badge data for JS
$badgeStore = [];
foreach ($badges as $b) {
    $badgeStore[$b['id']] = [
        'id' => (int)$b['id'], 'name' => $b['name'], 'description' => $b['description'],
        'icon' => $b['icon'], 'tier' => $b['tier'], 'category' => $b['category'],
        'points_bonus' => (int)$b['points_bonus'], 'awarded_for' => $b['awarded_for'],
        'is_active' => (int)$b['is_active'],
    ];
}

$tierIcons = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💠','diamond'=>'💎'];
$tierColors = ['bronze'=>'#cd7f32','silver'=>'#9ea7b0','gold'=>'#d4a017','platinum'=>'#5b7fa6','diamond'=>'#a855f7'];
?>

<style>
.bm-section{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:24px;}
.bm-section-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.bm-sh-title{font-size:1.1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;}
.bm-sh-title i{color:var(--o5);}
.bm-body{padding:24px;}
.bm-btn{padding:10px 18px;border-radius:9px;font-size:.875rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .2s;}
.bm-btn-primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
.bm-btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
.bm-btn-secondary{background:var(--card);border:1.5px solid var(--border);color:var(--text2);}
.bm-btn-secondary:hover{border-color:var(--o5);color:var(--o5);}
.bm-btn-danger{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.3);color:#dc2626;}
.bm-btn-danger:hover{background:rgba(239,68,68,0.2);}
.bm-btn-success{background:rgba(34,197,94,0.1);border:1.5px solid rgba(34,197,94,0.3);color:#16a34a;}
.bm-btn-success:hover{background:rgba(34,197,94,0.2);}
.bm-btn-sm{padding:6px 12px;font-size:.75rem;}

/* BADGE SHOWCASE GRID */
.bm-badges-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;}
.bm-badge-card{
    border-radius:14px;padding:20px 16px;text-align:center;border:2px solid;
    transition:all .2s;position:relative;
}
.bm-badge-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,0.12);}
.bm-badge-icon{width:68px;height:68px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:2rem;}
.bm-badge-name{font-size:.9rem;font-weight:800;color:var(--text);margin-bottom:4px;}
.bm-badge-desc{font-size:.72rem;color:var(--text2);margin-bottom:8px;line-height:1.4;}
.bm-badge-meta{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;}
.bm-badge-tier{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:5px;font-size:.65rem;font-weight:800;text-transform:uppercase;}
.bm-badge-pts{font-size:.72rem;font-weight:700;color:var(--o5);}
.bm-badge-awarded{font-size:.72rem;color:var(--text3);}
.bm-badge-actions{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;}
.bm-badge-inactive{opacity:.5;}

/* STATUS badge */
.bm-status{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:5px;font-size:.68rem;font-weight:700;position:absolute;top:10px;right:10px;}
.bm-status-active{background:rgba(34,197,94,0.12);color:#16a34a;}
.bm-status-inactive{background:rgba(239,68,68,0.1);color:#dc2626;}

/* FORM */
.bm-form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}
.bm-form-group{margin-bottom:16px;}
.bm-form-group.full{grid-column:1/-1;}
.bm-form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:7px;}
.bm-form-label .req{color:var(--red);}
.bm-form-input,.bm-form-select,.bm-form-textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
.bm-form-input:focus,.bm-form-select:focus,.bm-form-textarea:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.bm-form-textarea{resize:vertical;min-height:80px;}
.bm-emoji-preview{font-size:2.5rem;display:block;margin-bottom:8px;text-align:center;}

/* AWARD SECTION */
.bm-award-form{background:linear-gradient(135deg,var(--o1),#fff);border:1.5px solid var(--o2);border-radius:14px;padding:24px;}
.bm-award-title{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.bm-award-title i{color:var(--o5);}

/* TABLE */
.bm-table-wrap{overflow-x:auto;}
.bm-table{width:100%;border-collapse:collapse;}
.bm-table th{background:var(--bg);padding:10px 14px;text-align:left;font-size:.73rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);}
.bm-table td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:.83rem;color:var(--text2);}
.bm-table tr:hover{background:var(--bg);}
.bm-tier-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:5px;font-size:.65rem;font-weight:800;}

/* MODAL */
.bm-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
.bm-modal.active{display:flex;}
.bm-modal-content{background:var(--card);border-radius:16px;width:100%;max-width:660px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.bm-modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.bm-modal-title{font-size:1.1rem;font-weight:700;color:var(--text);}
.bm-modal-close{background:none;border:none;font-size:1.4rem;color:var(--text3);cursor:pointer;padding:4px 8px;line-height:1;}
.bm-modal-close:hover{color:#dc2626;}
.bm-modal-body{padding:24px;}
.bm-modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}

/* INNER TABS */
.bm-tabs{display:flex;gap:6px;margin-bottom:20px;border-bottom:2px solid var(--border);flex-wrap:wrap;}
.bm-tab{padding:10px 18px;border-radius:8px 8px 0 0;border:none;background:none;font-size:.85rem;font-weight:600;color:var(--text2);cursor:pointer;font-family:inherit;transition:all .2s;position:relative;}
.bm-tab.active{background:var(--card);color:var(--o5);border:1.5px solid var(--border);border-bottom:2px solid var(--card);margin-bottom:-2px;}
.bm-tab-content{display:none;}
.bm-tab-content.active{display:block;}

.bm-empty{text-align:center;padding:40px;color:var(--text3);}
.bm-empty i{font-size:2.5rem;opacity:.3;margin-bottom:12px;display:block;}

@media(max-width:768px){.bm-badges-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));}.bm-form-grid{grid-template-columns:1fr;}}
</style>

<!-- JSON Data Store -->
<script type="application/json" id="bmBadgeStore">
<?php echo json_encode($badgeStore, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>
</script>

<div class="bm-section">
    <div class="bm-section-header">
        <div class="bm-sh-title"><i class="fas fa-medal"></i> Badge Management</div>
        <button class="bm-btn bm-btn-primary" onclick="bmOpenCreate()">
            <i class="fas fa-plus"></i> Create Badge
        </button>
    </div>
    <div class="bm-body">

        <!-- Inner Tabs -->
        <div class="bm-tabs">
            <button class="bm-tab active" data-bmtab="showcase">
                <i class="fas fa-th"></i> All Badges (<?php echo count($badges); ?>)
            </button>
            <button class="bm-tab" data-bmtab="award">
                <i class="fas fa-award"></i> Award Badge
            </button>
            <button class="bm-tab" data-bmtab="log">
                <i class="fas fa-history"></i> Award Log (<?php echo count($awardedLog); ?>)
            </button>
        </div>

        <!-- TAB: SHOWCASE -->
        <div class="bm-tab-content active" id="bmtab-showcase">
            <?php if (empty($badges)): ?>
            <div class="bm-empty"><i class="fas fa-medal"></i><p>No badges created yet. Create your first badge!</p></div>
            <?php else: ?>
            <div class="bm-badges-grid">
                <?php foreach ($badges as $b):
                    $tier = strtolower($b['tier'] ?? 'bronze');
                    $tIcon = $tierIcons[$tier] ?? '🏅';
                    $tColor = $tierColors[$tier] ?? '#cd7f32';
                    $bgColors = ['bronze'=>'#fdf3e7','silver'=>'#f3f5f7','gold'=>'#fefce8','platinum'=>'#eff6ff','diamond'=>'#faf5ff'];
                    $bg = $bgColors[$tier] ?? '#fff7ed';
                ?>
                <div class="bm-badge-card <?php echo !$b['is_active']?'bm-badge-inactive':''; ?>"
                     style="border-color:<?php echo $tColor; ?>;background:<?php echo $bg; ?>;">
                    <span class="bm-status <?php echo $b['is_active']?'bm-status-active':'bm-status-inactive'; ?>">
                        <?php echo $b['is_active']?'Active':'Off'; ?>
                    </span>
                    <div class="bm-badge-icon" style="background:linear-gradient(135deg,<?php echo $tColor; ?>,<?php echo $tColor; ?>aa);">
                        <?php echo htmlspecialchars($b['icon'] ?? '🏅'); ?>
                    </div>
                    <div class="bm-badge-name"><?php echo htmlspecialchars($b['name']); ?></div>
                    <div class="bm-badge-desc"><?php echo htmlspecialchars($b['description']); ?></div>
                    <div class="bm-badge-meta">
                        <span class="bm-badge-tier" style="background:<?php echo $tColor; ?>22;color:<?php echo $tColor; ?>;">
                            <?php echo $tIcon; ?> <?php echo ucfirst($tier); ?>
                        </span>
                        <?php if ($b['points_bonus'] > 0): ?>
                        <span class="bm-badge-pts"><i class="fas fa-bolt"></i> +<?php echo $b['points_bonus']; ?> pts</span>
                        <?php endif; ?>
                    </div>
                    <div class="bm-badge-awarded" style="margin-bottom:10px;">
                        <i class="fas fa-users"></i> Awarded to <?php echo $b['awarded_count']; ?> student<?php echo $b['awarded_count']!=1?'s':''; ?>
                    </div>
                    <div class="bm-badge-actions">
                        <button class="bm-btn bm-btn-secondary bm-btn-sm" onclick="bmOpenEdit(<?php echo (int)$b['id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="bm-btn bm-btn-danger bm-btn-sm" onclick="bmDeleteBadge(<?php echo (int)$b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['name'])); ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: AWARD -->
        <div class="bm-tab-content" id="bmtab-award">
            <div class="bm-award-form">
                <div class="bm-award-title"><i class="fas fa-gift"></i> Award a Badge to a Student</div>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="award_badge" value="1">
                    <div class="bm-form-grid">
                        <div class="bm-form-group">
                            <label class="bm-form-label">Select Student <span class="req">*</span></label>
                            <select name="student_id" class="bm-form-select" required>
                                <option value="">— Choose student —</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>">
                                    <?php echo htmlspecialchars($s['full_name']); ?>
                                    (<?php echo htmlspecialchars($s['domain_interest'] ?: $s['email']); ?>)
                                    — <?php echo $s['total_points']; ?> pts
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bm-form-group">
                            <label class="bm-form-label">Select Badge <span class="req">*</span></label>
                            <select name="badge_id" class="bm-form-select" required id="bmAwardBadgeSelect" onchange="bmPreviewAwardBadge(this.value)">
                                <option value="">— Choose badge —</option>
                                <?php foreach ($badges as $b):
                                    if (!$b['is_active']) continue;
                                    $tier = strtolower($b['tier']);
                                    $tIcon = $tierIcons[$tier] ?? '🏅';
                                ?>
                                <option value="<?php echo (int)$b['id']; ?>">
                                    <?php echo $tIcon; ?> <?php echo htmlspecialchars($b['name']); ?>
                                    [<?php echo ucfirst($tier); ?>]
                                    <?php if ($b['points_bonus'] > 0): ?>+<?php echo $b['points_bonus']; ?> pts<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bm-form-group full">
                            <label class="bm-form-label">Award Note / Reason <span style="color:var(--text3);font-weight:400;">(optional)</span></label>
                            <textarea name="award_note" class="bm-form-textarea" placeholder="e.g. Outstanding performance in Task 5 – Social Media Strategy. Submitted exceptional quality work ahead of deadline."></textarea>
                        </div>
                    </div>

                    <!-- Badge Preview -->
                    <div id="bmAwardPreview" style="display:none;padding:16px;background:var(--card);border-radius:12px;border:1.5px solid var(--border);text-align:center;margin-bottom:16px;">
                        <div id="bmPreviewIcon" style="font-size:3rem;margin-bottom:8px;"></div>
                        <div id="bmPreviewName" style="font-weight:800;font-size:1rem;color:var(--text);"></div>
                        <div id="bmPreviewDesc" style="font-size:.78rem;color:var(--text2);margin-top:4px;"></div>
                        <div id="bmPreviewPts" style="font-size:.8rem;color:var(--o5);font-weight:700;margin-top:6px;"></div>
                    </div>

                    <div style="text-align:right;">
                        <button type="submit" class="bm-btn bm-btn-primary" onclick="return confirm('Award this badge to the selected student?')">
                            <i class="fas fa-award"></i> Award Badge
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB: LOG -->
        <div class="bm-tab-content" id="bmtab-log">
            <?php if (empty($awardedLog)): ?>
            <div class="bm-empty"><i class="fas fa-history"></i><p>No badges awarded yet.</p></div>
            <?php else: ?>
            <div class="bm-table-wrap">
                <table class="bm-table">
                    <thead>
                        <tr>
                            <th>Badge</th><th>Tier</th><th>Student</th><th>Bonus Pts</th><th>Awarded By</th><th>Awarded At</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($awardedLog as $log):
                            $tier = strtolower($log['tier'] ?? 'bronze');
                            $tIcon = $tierIcons[$tier] ?? '🏅';
                            $tColor = $tierColors[$tier] ?? '#cd7f32';
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:1.5rem;"><?php echo htmlspecialchars($log['icon'] ?? '🏅'); ?></span>
                                    <strong><?php echo htmlspecialchars($log['badge_name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="bm-tier-pill" style="background:<?php echo $tColor; ?>22;color:<?php echo $tColor; ?>;">
                                    <?php echo $tIcon; ?> <?php echo ucfirst($tier); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['student_name']); ?></strong><br>
                                <small style="color:var(--text3);"><?php echo htmlspecialchars($log['email']); ?></small>
                            </td>
                            <td>
                                <?php if ($log['points_bonus'] > 0): ?>
                                <span style="color:var(--o5);font-weight:700;">+<?php echo $log['points_bonus']; ?> pts</span>
                                <?php else: ?><span style="color:var(--text3);">—</span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['awarded_by'] ?? 'Admin'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['awarded_at'])); ?></td>
                            <td>
                                <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Revoke this badge from <?php echo htmlspecialchars(addslashes($log['student_name'])); ?>?')">
                                    <input type="hidden" name="revoke_badge" value="1">
                                    <input type="hidden" name="student_badge_id" value="<?php echo (int)$log['id']; ?>">
                                    <button type="submit" class="bm-btn bm-btn-danger bm-btn-sm" title="Revoke Badge">
                                        <i class="fas fa-times"></i> Revoke
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Create/Edit Badge Modal -->
<div id="bmBadgeModal" class="bm-modal" role="dialog">
    <div class="bm-modal-content">
        <div class="bm-modal-header">
            <div class="bm-modal-title" id="bmModalTitle">Create Badge</div>
            <button class="bm-modal-close" onclick="bmCloseModal()">&times;</button>
        </div>
        <form method="POST" action="admin.php" id="bmBadgeForm">
            <input type="hidden" name="create_badge" value="1" id="bmFormAction">
            <input type="hidden" name="badge_id"     value=""  id="bmFormBadgeId">
            <div class="bm-modal-body">
                <!-- Emoji Preview -->
                <div style="text-align:center;margin-bottom:16px;">
                    <span class="bm-emoji-preview" id="bmEmojiPreview">🏅</span>
                    <div style="font-size:.75rem;color:var(--text3);">Emoji preview</div>
                </div>
                <div class="bm-form-grid">
                    <div class="bm-form-group full">
                        <label class="bm-form-label">Badge Icon (Emoji) <span class="req">*</span></label>
                        <input type="text" name="icon" id="bmFormIcon" class="bm-form-input"
                               placeholder="Paste an emoji e.g. 🏆 🥇 ⭐ 🔥 💡 🚀"
                               maxlength="10" oninput="document.getElementById('bmEmojiPreview').textContent=this.value||'🏅'" required>
                        <div style="font-size:.72rem;color:var(--text3);margin-top:5px;">
                            Suggested: 🏆 🥇 🥈 🥉 ⭐ 🌟 💡 🔥 🚀 🎯 💪 🎨 📊 📱 💎 👑 🦾 🎖️ 🏅 ✨
                        </div>
                    </div>
                    <div class="bm-form-group full">
                        <label class="bm-form-label">Badge Name <span class="req">*</span></label>
                        <input type="text" name="name" id="bmFormName" class="bm-form-input" placeholder="e.g. Social Media Star" required maxlength="100">
                    </div>
                    <div class="bm-form-group full">
                        <label class="bm-form-label">Description</label>
                        <textarea name="description" id="bmFormDesc" class="bm-form-textarea" placeholder="Short description shown on the badge"></textarea>
                    </div>
                    <div class="bm-form-group">
                        <label class="bm-form-label">Tier <span class="req">*</span></label>
                        <select name="tier" id="bmFormTier" class="bm-form-select" required>
                            <option value="bronze">🥉 Bronze</option>
                            <option value="silver">🥈 Silver</option>
                            <option value="gold">🥇 Gold</option>
                            <option value="platinum">💠 Platinum</option>
                            <option value="diamond">💎 Diamond</option>
                        </select>
                    </div>
                    <div class="bm-form-group">
                        <label class="bm-form-label">Category</label>
                        <input type="text" name="category" id="bmFormCategory" class="bm-form-input" placeholder="e.g. social_media, design, leadership">
                    </div>
                    <div class="bm-form-group">
                        <label class="bm-form-label">Bonus Points</label>
                        <input type="number" name="points_bonus" id="bmFormPts" class="bm-form-input" value="0" min="0" max="1000">
                    </div>
                    <div class="bm-form-group">
                        <label class="bm-form-label">Active</label>
                        <select name="is_active" id="bmFormActive" class="bm-form-select">
                            <option value="1">Yes – Visible to students</option>
                            <option value="0">No – Hidden</option>
                        </select>
                    </div>
                    <div class="bm-form-group full">
                        <label class="bm-form-label">Awarded For (criteria shown to students)</label>
                        <textarea name="awarded_for" id="bmFormAwardedFor" class="bm-form-textarea"
                                  placeholder="e.g. Awarded for completing a social media task with exceptional creativity and results that directly benefited Padak."></textarea>
                    </div>
                </div>
            </div>
            <div class="bm-modal-footer">
                <button type="button" class="bm-btn bm-btn-secondary" onclick="bmCloseModal()">Cancel</button>
                <button type="submit" class="bm-btn bm-btn-primary" id="bmFormSubmitBtn">
                    <i class="fas fa-save"></i> Create Badge
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Badge Hidden Form -->
<form method="POST" action="admin.php" id="bmDeleteForm" style="display:none;">
    <input type="hidden" name="delete_badge" value="1">
    <input type="hidden" name="badge_id"     id="bmDeleteBadgeId">
</form>

<script>
(function() {
    var bmStore = {};
    try { bmStore = JSON.parse(document.getElementById('bmBadgeStore').textContent); } catch(e) {}

    // Inner tabs
    document.querySelectorAll('.bm-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = this.dataset.bmtab;
            document.querySelectorAll('.bm-tab').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.bm-tab-content').forEach(function(c){ c.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('bmtab-'+tab).classList.add('active');
        });
    });

    // Modal helpers
    window.bmCloseModal = function() {
        document.getElementById('bmBadgeModal').classList.remove('active');
    };
    document.getElementById('bmBadgeModal').addEventListener('click', function(e) {
        if (e.target === this) bmCloseModal();
    });

    window.bmOpenCreate = function() {
        document.getElementById('bmModalTitle').textContent     = 'Create Badge';
        document.getElementById('bmFormSubmitBtn').textContent  = '💾 Create Badge';
        document.getElementById('bmFormAction').name            = 'create_badge';
        document.getElementById('bmFormBadgeId').value          = '';
        document.getElementById('bmFormIcon').value             = '🏅';
        document.getElementById('bmFormName').value             = '';
        document.getElementById('bmFormDesc').value             = '';
        document.getElementById('bmFormTier').value             = 'bronze';
        document.getElementById('bmFormCategory').value         = '';
        document.getElementById('bmFormPts').value              = '0';
        document.getElementById('bmFormActive').value           = '1';
        document.getElementById('bmFormAwardedFor').value       = '';
        document.getElementById('bmEmojiPreview').textContent   = '🏅';
        document.getElementById('bmBadgeModal').classList.add('active');
    };

    window.bmOpenEdit = function(id) {
        var b = bmStore[id];
        if (!b) return;
        document.getElementById('bmModalTitle').textContent     = 'Edit Badge';
        document.getElementById('bmFormSubmitBtn').innerHTML    = '<i class="fas fa-save"></i> Update Badge';
        document.getElementById('bmFormAction').name            = 'update_badge';
        document.getElementById('bmFormBadgeId').value          = b.id;
        document.getElementById('bmFormIcon').value             = b.icon;
        document.getElementById('bmFormName').value             = b.name;
        document.getElementById('bmFormDesc').value             = b.description;
        document.getElementById('bmFormTier').value             = b.tier;
        document.getElementById('bmFormCategory').value         = b.category;
        document.getElementById('bmFormPts').value              = b.points_bonus;
        document.getElementById('bmFormActive').value           = b.is_active ? '1' : '0';
        document.getElementById('bmFormAwardedFor').value       = b.awarded_for;
        document.getElementById('bmEmojiPreview').textContent   = b.icon || '🏅';
        document.getElementById('bmBadgeModal').classList.add('active');
    };

    window.bmDeleteBadge = function(id, name) {
        if (!confirm('Delete badge "' + name + '"? This will also revoke it from all students. This cannot be undone!')) return;
        document.getElementById('bmDeleteBadgeId').value = id;
        document.getElementById('bmDeleteForm').submit();
    };

    // Badge award preview
    window.bmPreviewAwardBadge = function(id) {
        var b = bmStore[id];
        var preview = document.getElementById('bmAwardPreview');
        if (!b || !id) { preview.style.display = 'none'; return; }
        document.getElementById('bmPreviewIcon').textContent = b.icon || '🏅';
        document.getElementById('bmPreviewName').textContent = b.name;
        document.getElementById('bmPreviewDesc').textContent = b.description;
        document.getElementById('bmPreviewPts').textContent  = b.points_bonus > 0 ? '+' + b.points_bonus + ' bonus points will be awarded' : '';
        preview.style.display = 'block';
    };
})();
</script>