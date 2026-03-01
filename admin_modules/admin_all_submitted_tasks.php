<?php
// admin_modules/admin_all_submitted_tasks.php
// NOTE: admin.php wraps this file in <div id="tab-all-submissions" class="tab-content">
// DO NOT add any div with id="tab-all-submissions" inside this file - it causes the freeze.

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

$subSuccess = '';
$subError   = '';

// ── Handle Update Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission'])) {
    $submissionId = (int)$_POST['submission_id'];
    $newStatus    = trim($_POST['sub_status'] ?? '');
    $feedback     = trim($_POST['sub_feedback'] ?? '');
    $pointsEarned = max(0, (int)$_POST['sub_points_earned']);
    $reviewedBy   = trim($_POST['sub_reviewed_by'] ?? 'Admin');

    $allowed = ['draft','submitted','under_review','approved','rejected','revision_requested'];
    if (!in_array($newStatus, $allowed)) {
        $subError = 'Invalid status.';
    } else {
        $feedbackEsc   = $db->real_escape_string($feedback);
        $reviewedByEsc = $db->real_escape_string($reviewedBy);

        $updateSql = "UPDATE task_submissions SET
                        status         = '$newStatus',
                        feedback       = '$feedbackEsc',
                        points_earned  = $pointsEarned,
                        reviewed_by    = '$reviewedByEsc',
                        reviewed_at    = NOW(),
                        updated_at     = NOW()
                      WHERE id = $submissionId";

        if ($db->query($updateSql)) {
            if ($newStatus === 'approved') {
                $sd = $db->query("SELECT student_id FROM task_submissions WHERE id=$submissionId")->fetch_assoc();
                if ($sd) {
                    $sid = (int)$sd['student_id'];
                    $db->query("UPDATE internship_students SET total_points = total_points + $pointsEarned WHERE id = $sid");
                    $nt = $db->real_escape_string('Task Approved');
                    $nm = $db->real_escape_string("Your submission was approved! You earned $pointsEarned points.");
                    $db->query("INSERT INTO student_notifications (student_id,title,message,type,created_at) VALUES ($sid,'$nt','$nm','grade',NOW())");
                }
            } elseif (in_array($newStatus, ['rejected','revision_requested'])) {
                $sd = $db->query("SELECT student_id FROM task_submissions WHERE id=$submissionId")->fetch_assoc();
                if ($sd) {
                    $sid = (int)$sd['student_id'];
                    $nt  = $db->real_escape_string($newStatus === 'rejected' ? 'Submission Rejected' : 'Revision Requested');
                    $nm  = $db->real_escape_string($feedback ?: 'Please check your submission.');
                    $db->query("INSERT INTO student_notifications (student_id,title,message,type,created_at) VALUES ($sid,'$nt','$nm','task',NOW())");
                }
            }
            $_SESSION['admin_success'] = 'Submission updated successfully!';
            header('Location: admin.php#tab-submissions');
            exit;
        } else {
            $subError = 'Update failed: ' . $db->error;
        }
    }
}

// ── Handle Delete Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission'])) {
    $submissionId = (int)$_POST['submission_id'];
    if ($db->query("DELETE FROM task_submissions WHERE id = $submissionId")) {
        $_SESSION['admin_success'] = 'Submission deleted.';
        header('Location: admin.php#tab-submissions');
        exit;
    } else {
        $subError = 'Delete failed: ' . $db->error;
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$subFilterStatus = $_GET['ssf'] ?? 'all';
$subFilterSearch = trim($_GET['sss'] ?? '');
$subFilterTask   = (int)($_GET['sst'] ?? 0);

$subWhere = [];
if ($subFilterStatus !== 'all') {
    $sfe = $db->real_escape_string($subFilterStatus);
    $subWhere[] = "ts.status = '$sfe'";
}
if ($subFilterSearch !== '') {
    $sse = $db->real_escape_string($subFilterSearch);
    $subWhere[] = "(s.full_name LIKE '%$sse%' OR s.email LIKE '%$sse%' OR t.title LIKE '%$sse%')";
}
if ($subFilterTask > 0) {
    $subWhere[] = "ts.task_id = $subFilterTask";
}
$subWhereSQL = $subWhere ? 'WHERE ' . implode(' AND ', $subWhere) : '';

// ── Status counts ────────────────────────────────────────────────────────────
$cntRes    = $db->query("SELECT status, COUNT(*) as c FROM task_submissions GROUP BY status");
$subCounts = ['all' => 0];
while ($r = $cntRes->fetch_assoc()) {
    $subCounts[$r['status']] = (int)$r['c'];
    $subCounts['all'] += (int)$r['c'];
}

// ── Tasks dropdown ───────────────────────────────────────────────────────────
$taskDropList = [];
$tdr = $db->query("SELECT id, title FROM internship_tasks ORDER BY title ASC");
while ($r = $tdr->fetch_assoc()) $taskDropList[] = $r;

// ── Main query ───────────────────────────────────────────────────────────────
$subsRes = $db->query("
    SELECT  ts.*,
            s.full_name, s.email,
            t.title AS task_title, t.points AS task_max_pts, t.difficulty
    FROM    task_submissions ts
    LEFT JOIN internship_students s ON ts.student_id = s.id
    LEFT JOIN internship_tasks    t ON ts.task_id    = t.id
    $subWhereSQL
    ORDER BY ts.submitted_at DESC
");
$allSubs = [];
while ($r = $subsRes->fetch_assoc()) $allSubs[] = $r;

$statusLabel = [
    'draft'              => 'Draft',
    'submitted'          => 'Submitted',
    'under_review'       => 'Under Review',
    'approved'           => 'Approved',
    'rejected'           => 'Rejected',
    'revision_requested' => 'Revision Req.',
];
$statusStyle = [
    'draft'              => 'background:rgba(100,116,139,.12);color:#475569',
    'submitted'          => 'background:rgba(59,130,246,.12);color:#1d4ed8',
    'under_review'       => 'background:rgba(234,179,8,.12);color:#854d0e',
    'approved'           => 'background:rgba(34,197,94,.12);color:#16a34a',
    'rejected'           => 'background:rgba(239,68,68,.12);color:#dc2626',
    'revision_requested' => 'background:rgba(139,92,246,.12);color:#6d28d9',
];
?>

<?php if ($subSuccess): ?>
<div style="display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:18px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;">
    <i class="fas fa-circle-check"></i><?php echo htmlspecialchars($subSuccess); ?>
</div>
<?php endif; ?>
<?php if ($subError): ?>
<div style="display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:18px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
    <i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($subError); ?>
</div>
<?php endif; ?>

<style>
/* All classes prefixed asub_ to avoid collisions with existing admin styles */
.asub_card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:24px;}
.asub_card_head{padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.asub_card_title{font-size:1.05rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:9px;}
.asub_card_title i{color:#f97316;}
.asub_card_body{padding:22px;}

.asub_pills{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px;}
.asub_pill{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.78rem;font-weight:600;text-decoration:none;color:#475569;transition:all .15s;}
.asub_pill:hover{border-color:#f97316;color:#f97316;}
.asub_pill.on{background:#f97316;border-color:#f97316;color:#fff;}

.asub_filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center;}
.asub_sel{padding:7px 11px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.81rem;font-family:inherit;color:#0f172a;outline:none;background:#fff;}
.asub_sel:focus{border-color:#f97316;}
.asub_txt{padding:7px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.81rem;font-family:inherit;color:#0f172a;outline:none;min-width:200px;}
.asub_txt:focus{border-color:#f97316;}
.asub_clr{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.78rem;font-weight:600;color:#475569;text-decoration:none;transition:all .15s;}
.asub_clr:hover{border-color:#f97316;color:#f97316;}

.asub_table_wrap{overflow-x:auto;}
.asub_table{width:100%;border-collapse:collapse;}
.asub_table th{background:#f8fafc;padding:10px 13px;text-align:left;font-size:.71rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e2e8f0;white-space:nowrap;}
.asub_table td{padding:12px 13px;border-bottom:1px solid #e2e8f0;font-size:.82rem;color:#475569;vertical-align:middle;}
.asub_table tr:last-child td{border-bottom:none;}
.asub_table tr:hover td{background:#f8fafc;}
.asub_av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#f97316,#fb923c);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.77rem;flex-shrink:0;}
.asub_bdg{display:inline-flex;align-items:center;padding:3px 9px;border-radius:6px;font-size:.69rem;font-weight:700;white-space:nowrap;}

.asub_btn{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:7px;font-size:.74rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;transition:all .15s;text-decoration:none;}
.asub_btn_v{background:#eff6ff;border:1.5px solid #bfdbfe;color:#1d4ed8;}
.asub_btn_v:hover{background:#dbeafe;}
.asub_btn_e{background:linear-gradient(135deg,#f97316,#fb923c);color:#fff;box-shadow:0 3px 10px rgba(249,115,22,.25);}
.asub_btn_e:hover{box-shadow:0 5px 16px rgba(249,115,22,.4);}
.asub_btn_d{background:#fef2f2;border:1.5px solid #fecaca;color:#dc2626;}
.asub_btn_d:hover{background:#fee2e2;}
.asub_btn_cancel{background:#fff;border:1.5px solid #e2e8f0;color:#475569;padding:9px 20px;font-size:.84rem;}
.asub_btn_cancel:hover{border-color:#f97316;color:#f97316;}
.asub_btn_save{background:linear-gradient(135deg,#f97316,#fb923c);color:#fff;border:none;padding:9px 22px;font-size:.84rem;box-shadow:0 4px 14px rgba(249,115,22,.3);}
.asub_btn_save:hover{box-shadow:0 6px 20px rgba(249,115,22,.45);}
.asub_btn_del_c{background:#dc2626;color:#fff;border:none;padding:9px 22px;font-size:.84rem;}
.asub_btn_del_c:hover{background:#b91c1c;}

.asub_empty{text-align:center;padding:52px 20px;color:#94a3b8;}
.asub_empty i{font-size:2.5rem;margin-bottom:12px;display:block;opacity:.3;}

/* Modals — unique IDs, unique class names, high z-index */
.asub_overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);}
.asub_overlay.asub_show{display:flex;}
.asub_mbox{background:#fff;border-radius:16px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.28);}
.asub_mbox_lg{max-width:680px;}
.asub_mbox_sm{max-width:440px;}
.asub_mhead{padding:17px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;}
.asub_mtitle{font-size:1rem;font-weight:700;color:#0f172a;}
.asub_mx{background:none;border:none;font-size:1.4rem;color:#94a3b8;cursor:pointer;line-height:1;padding:2px 6px;}
.asub_mx:hover{color:#ef4444;}
.asub_mbody{padding:22px;}
.asub_mfoot{padding:13px 22px;border-top:1px solid #e2e8f0;display:flex;gap:10px;justify-content:flex-end;}

.asub_frow{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.asub_fg{margin-bottom:13px;}
.asub_fg_full{grid-column:1/-1;}
.asub_fl{display:block;font-size:.77rem;font-weight:700;color:#0f172a;margin-bottom:6px;}
.asub_fi,.asub_fsel,.asub_fta{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.84rem;font-family:inherit;color:#0f172a;outline:none;background:#fff;transition:border-color .2s;}
.asub_fi:focus,.asub_fsel:focus,.asub_fta:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.1);}
.asub_fta{resize:vertical;min-height:86px;}

.asub_igrid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.asub_icell{background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:11px 13px;}
.asub_icell_full{grid-column:1/-1;}
.asub_ilbl{font-size:.69rem;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;}
.asub_ival{font-size:.83rem;font-weight:600;color:#0f172a;word-break:break-word;}
.asub_cbox{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:11px;font-size:.81rem;color:#475569;line-height:1.65;white-space:pre-wrap;word-break:break-word;margin-top:5px;}
.asub_lchip{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:6px;font-size:.74rem;color:#1d4ed8;text-decoration:none;font-weight:600;}
.asub_lchip:hover{background:rgba(59,130,246,.18);}

@media(max-width:600px){.asub_frow{grid-template-columns:1fr;}.asub_igrid{grid-template-columns:1fr;}}
</style>

<!-- ══════════════════════════════════ MAIN CARD ══════════════════════════ -->
<div class="asub_card">
    <div class="asub_card_head">
        <div class="asub_card_title">
            <i class="fas fa-paper-plane"></i> All Task Submissions
        </div>
        <span style="font-size:.82rem;color:#94a3b8;"><?php echo count($allSubs); ?> result<?php echo count($allSubs)!==1?'s':''; ?></span>
    </div>
    <div class="asub_card_body">

        <!-- Status pill filters -->
        <?php
        $pillDefs = [
            'all'               => ['All',          'fa-list'],
            'submitted'         => ['Submitted',     'fa-paper-plane'],
            'under_review'      => ['Under Review',  'fa-eye'],
            'approved'          => ['Approved',      'fa-circle-check'],
            'rejected'          => ['Rejected',      'fa-circle-xmark'],
            'revision_requested'=> ['Revision Req.', 'fa-rotate'],
            'draft'             => ['Draft',         'fa-file-pen'],
        ];
        $baseHref = '?';
        if ($subFilterTask)   $baseHref .= 'sst='.$subFilterTask.'&';
        if ($subFilterSearch) $baseHref .= 'sss='.urlencode($subFilterSearch).'&';
        ?>
        <div class="asub_pills">
            <?php foreach ($pillDefs as $pk => $pd): ?>
            <a href="<?php echo $baseHref; ?>ssf=<?php echo $pk; ?>#tab-submissions"
               class="asub_pill <?php echo $subFilterStatus===$pk?'on':''; ?>">
                <i class="fas <?php echo $pd[1]; ?>"></i>
                <?php echo $pd[0]; ?> (<?php echo $subCounts[$pk]??0; ?>)
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Task + search filters -->
        <form method="GET" id="asubForm" style="margin:0;">
            <input type="hidden" name="ssf" value="<?php echo htmlspecialchars($subFilterStatus); ?>">
            <div class="asub_filters">
                <select name="sst" class="asub_sel" onchange="document.getElementById('asubForm').submit()">
                    <option value="0">All Tasks</option>
                    <?php foreach ($taskDropList as $td): ?>
                    <option value="<?php echo $td['id']; ?>" <?php echo $subFilterTask==$td['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars(mb_strimwidth($td['title'],0,55,'…')); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="sss" class="asub_txt"
                       placeholder="Search name, email or task…"
                       value="<?php echo htmlspecialchars($subFilterSearch); ?>"
                       onchange="document.getElementById('asubForm').submit()">
                <?php if ($subFilterSearch||$subFilterTask||$subFilterStatus!=='all'): ?>
                <a href="admin.php#tab-submissions" class="asub_clr"><i class="fas fa-xmark"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table -->
        <?php if (empty($allSubs)): ?>
        <div class="asub_empty">
            <i class="fas fa-inbox"></i>
            <p><?php echo ($subFilterSearch||$subFilterStatus!=='all'||$subFilterTask)?'No submissions match your filters.':'No submissions yet.'; ?></p>
        </div>
        <?php else: ?>
        <div class="asub_table_wrap">
            <table class="asub_table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Task</th>
                        <th>Status</th>
                        <th>Points</th>
                        <th>Submitted</th>
                        <th>Reviewed By</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSubs as $sub): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="asub_av"><?php echo strtoupper(substr($sub['full_name']??'?',0,2)); ?></div>
                                <div>
                                    <div style="font-weight:600;color:#0f172a;"><?php echo htmlspecialchars($sub['full_name']??'—'); ?></div>
                                    <div style="font-size:.71rem;color:#94a3b8;"><?php echo htmlspecialchars($sub['email']??''); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;color:#0f172a;max-width:190px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($sub['task_title']??''); ?>">
                                <?php echo htmlspecialchars($sub['task_title']??'—'); ?>
                            </div>
                            <?php if ($sub['difficulty']): ?>
                            <span style="font-size:.69rem;font-weight:700;color:#854d0e;text-transform:capitalize;"><?php echo $sub['difficulty']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="asub_bdg" style="<?php echo $statusStyle[$sub['status']]??'background:#f1f5f9;color:#475569'; ?>">
                                <?php echo $statusLabel[$sub['status']]??ucfirst($sub['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sub['points_earned']!==null): ?>
                            <strong style="color:#f97316;"><?php echo $sub['points_earned']; ?></strong>
                            <span style="color:#94a3b8;font-size:.74rem;"> / <?php echo $sub['task_max_pts']??'—'; ?></span>
                            <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                        </td>
                        <td style="font-size:.76rem;color:#94a3b8;white-space:nowrap;">
                            <?php echo $sub['submitted_at']?date('d M Y',strtotime($sub['submitted_at'])):'—'; ?>
                        </td>
                        <td style="font-size:.79rem;"><?php echo htmlspecialchars($sub['reviewed_by']??'—'); ?></td>
                        <td>
                            <div style="display:flex;gap:5px;justify-content:flex-end;">
                                <button type="button" class="asub_btn asub_btn_v"
                                        onclick="asubView(<?php echo htmlspecialchars(json_encode($sub),ENT_QUOTES); ?>)"
                                        title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="asub_btn asub_btn_e"
                                        onclick="asubEdit(<?php echo htmlspecialchars(json_encode($sub),ENT_QUOTES); ?>)"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="asub_btn asub_btn_d"
                                        onclick="asubDel(<?php echo $sub['id']; ?>,'<?php echo htmlspecialchars(addslashes($sub['full_name']??''),ENT_QUOTES); ?>')"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>


<!-- ══════════════════════════════════ VIEW MODAL ═════════════════════════ -->
<div id="asubViewModal" class="asub_overlay">
    <div class="asub_mbox asub_mbox_lg">
        <div class="asub_mhead">
            <div class="asub_mtitle"><i class="fas fa-eye" style="color:#f97316;margin-right:7px;"></i>Submission Details</div>
            <button type="button" class="asub_mx" onclick="asubClose('asubViewModal')">&times;</button>
        </div>
        <div class="asub_mbody" id="asubViewBody"></div>
        <div class="asub_mfoot">
            <button type="button" class="asub_btn asub_btn_cancel" onclick="asubClose('asubViewModal')">Close</button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════ EDIT MODAL ═════════════════════════ -->
<div id="asubEditModal" class="asub_overlay">
    <div class="asub_mbox asub_mbox_lg">
        <div class="asub_mhead">
            <div class="asub_mtitle"><i class="fas fa-edit" style="color:#f97316;margin-right:7px;"></i>Edit / Review Submission</div>
            <button type="button" class="asub_mx" onclick="asubClose('asubEditModal')">&times;</button>
        </div>
        <form method="POST" action="admin.php#tab-submissions">
            <div class="asub_mbody">
                <input type="hidden" name="submission_id" id="asubEid">
                <div style="padding:10px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:9px;margin-bottom:16px;font-size:.83rem;">
                    <strong id="asubEname" style="color:#0f172a;"></strong><span style="color:#94a3b8;"> — </span><span id="asubEtask" style="color:#ea580c;font-weight:600;"></span>
                </div>
                <div class="asub_frow">
                    <div class="asub_fg">
                        <label class="asub_fl">Status <span style="color:#ef4444;">*</span></label>
                        <select name="sub_status" id="asubEstat" class="asub_fsel" required>
                            <option value="submitted">Submitted</option>
                            <option value="under_review">Under Review</option>
                            <option value="approved">Approved ✅</option>
                            <option value="rejected">Rejected ❌</option>
                            <option value="revision_requested">Revision Requested 🔄</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div class="asub_fg">
                        <label class="asub_fl">Points Earned</label>
                        <input type="number" name="sub_points_earned" id="asubEpts" class="asub_fi" min="0" max="9999" placeholder="0">
                    </div>
                    <div class="asub_fg">
                        <label class="asub_fl">Reviewed By</label>
                        <input type="text" name="sub_reviewed_by" id="asubErev" class="asub_fi" placeholder="Admin">
                    </div>
                    <div class="asub_fg asub_fg_full">
                        <label class="asub_fl">Feedback for Student</label>
                        <textarea name="sub_feedback" id="asubEfb" class="asub_fta" placeholder="Write feedback…"></textarea>
                    </div>
                </div>
            </div>
            <div class="asub_mfoot">
                <button type="button" class="asub_btn asub_btn_cancel" onclick="asubClose('asubEditModal')">Cancel</button>
                <button type="submit" name="update_submission" class="asub_btn asub_btn_save"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════ DELETE MODAL ══════════════════════ -->
<div id="asubDelModal" class="asub_overlay">
    <div class="asub_mbox asub_mbox_sm">
        <div class="asub_mhead">
            <div class="asub_mtitle" style="color:#dc2626;"><i class="fas fa-triangle-exclamation" style="margin-right:7px;"></i>Confirm Delete</div>
            <button type="button" class="asub_mx" onclick="asubClose('asubDelModal')">&times;</button>
        </div>
        <form method="POST" action="admin.php#tab-submissions">
            <div class="asub_mbody">
                <input type="hidden" name="submission_id" id="asubDid">
                <p style="font-size:.86rem;color:#475569;line-height:1.65;">
                    Delete submission by <strong id="asubDname" style="color:#0f172a;"></strong>? This cannot be undone.
                </p>
            </div>
            <div class="asub_mfoot">
                <button type="button" class="asub_btn asub_btn_cancel" onclick="asubClose('asubDelModal')">Cancel</button>
                <button type="submit" name="delete_submission" class="asub_btn asub_btn_del_c"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>


<script>
(function(){
    window.asubClose = function(id){
        document.getElementById(id).classList.remove('asub_show');
    };

    window.asubEdit = function(sub){
        document.getElementById('asubEid').value   = sub.id;
        document.getElementById('asubEstat').value = sub.status || 'submitted';
        document.getElementById('asubEpts').value  = (sub.points_earned !== null && sub.points_earned !== undefined) ? sub.points_earned : '';
        document.getElementById('asubEfb').value   = sub.feedback || '';
        document.getElementById('asubErev').value  = sub.reviewed_by || 'Admin';
        document.getElementById('asubEname').textContent = sub.full_name || '—';
        document.getElementById('asubEtask').textContent = sub.task_title || '—';
        document.getElementById('asubEditModal').classList.add('asub_show');
    };

    window.asubView = function(sub){
        function e(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '—'; }
        function fd(d){
            if(!d) return '—';
            var dt = new Date(d);
            return isNaN(dt) ? d : dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
        }
        var sL={submitted:'Submitted',under_review:'Under Review',approved:'Approved',rejected:'Rejected',revision_requested:'Revision Requested',draft:'Draft'};
        var sC={submitted:'#1d4ed8',under_review:'#854d0e',approved:'#16a34a',rejected:'#dc2626',revision_requested:'#6d28d9',draft:'#475569'};

        var links = '';
        if(sub.submission_url) links += '<a href="'+e(sub.submission_url)+'" target="_blank" rel="noopener" class="asub_lchip"><i class="fas fa-link"></i>View URL</a> ';
        if(sub.github_link)    links += '<a href="'+e(sub.github_link)+'" target="_blank" rel="noopener" class="asub_lchip"><i class="fab fa-github"></i>GitHub</a> ';
        if(sub.file_name)      links += '<span class="asub_lchip" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.25);color:#16a34a;"><i class="fas fa-file"></i>'+e(sub.file_name)+'</span>';

        var html = '<div class="asub_igrid">'
            + '<div class="asub_icell"><div class="asub_ilbl">Student</div><div class="asub_ival">'+e(sub.full_name)+'</div><div style="font-size:.71rem;color:#94a3b8;margin-top:2px;">'+e(sub.email)+'</div></div>'
            + '<div class="asub_icell"><div class="asub_ilbl">Task</div><div class="asub_ival">'+e(sub.task_title)+'</div>'+(sub.difficulty?'<span style="font-size:.69rem;font-weight:700;color:#854d0e;text-transform:capitalize;">'+e(sub.difficulty)+'</span>':'')+'</div>'
            + '<div class="asub_icell"><div class="asub_ilbl">Status</div><div class="asub_ival" style="color:'+(sC[sub.status]||'#475569')+'">'+(sL[sub.status]||sub.status)+'</div></div>'
            + '<div class="asub_icell"><div class="asub_ilbl">Points</div><div class="asub_ival" style="color:#f97316;">'+(sub.points_earned!==null&&sub.points_earned!==undefined ? sub.points_earned+' / '+(sub.task_max_pts||'—') : '—')+'</div></div>'
            + '<div class="asub_icell"><div class="asub_ilbl">Submitted</div><div class="asub_ival" style="font-size:.81rem;">'+fd(sub.submitted_at)+'</div></div>'
            + '<div class="asub_icell"><div class="asub_ilbl">Reviewed By</div><div class="asub_ival" style="font-size:.81rem;">'+e(sub.reviewed_by)+'</div><div style="font-size:.71rem;color:#94a3b8;margin-top:2px;">'+fd(sub.reviewed_at)+'</div></div>';

        if(sub.submission_text)
            html += '<div class="asub_icell asub_icell_full"><div class="asub_ilbl">Submission Text</div><div class="asub_cbox">'+e(sub.submission_text)+'</div></div>';
        if(links)
            html += '<div class="asub_icell asub_icell_full"><div class="asub_ilbl" style="margin-bottom:7px;">Links / Files</div><div style="display:flex;flex-wrap:wrap;gap:7px;">'+links+'</div></div>';
        if(sub.feedback)
            html += '<div class="asub_icell asub_icell_full" style="background:#fff7ed;border-color:#fed7aa;"><div class="asub_ilbl">Admin Feedback</div><div class="asub_cbox" style="background:transparent;border:none;padding:4px 0;">'+e(sub.feedback)+'</div></div>';

        html += '</div>';
        document.getElementById('asubViewBody').innerHTML = html;
        document.getElementById('asubViewModal').classList.add('asub_show');
    };

    window.asubDel = function(id, name){
        document.getElementById('asubDid').value = id;
        document.getElementById('asubDname').textContent = name;
        document.getElementById('asubDelModal').classList.add('asub_show');
    };

    // close on backdrop click
    ['asubViewModal','asubEditModal','asubDelModal'].forEach(function(id){
        var el = document.getElementById(id);
        if(el) el.addEventListener('click', function(ev){ if(ev.target===this) this.classList.remove('asub_show'); });
    });
})();
</script>