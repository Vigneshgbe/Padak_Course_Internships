<?php
// admin_all_submissions.php - Task Submissions Management Module
// Tables: task_submissions, internship_tasks (max_points), internship_students, student_notifications

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error   = '';

// ── Handle Submission Status Update ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission_status'])) {
    $submissionId = (int)$_POST['submission_id'];
    $newStatus    = trim($_POST['status'] ?? '');
    $feedback     = trim($_POST['feedback'] ?? '');
    $pointsEarned = (isset($_POST['points_earned']) && $_POST['points_earned'] !== '') ? (int)$_POST['points_earned'] : null;
    $reviewedBy   = trim($_SESSION['admin_username'] ?? 'Admin');

    $allowed = ['submitted', 'under_review', 'approved', 'rejected', 'revision_requested'];
    if (!in_array($newStatus, $allowed)) {
        $error = 'Invalid status value.';
    } else {
        $feedbackEsc   = $db->real_escape_string($feedback);
        $reviewedByEsc = $db->real_escape_string($reviewedBy);
        $pointsSQL     = ($newStatus === 'approved' && $pointsEarned !== null) ? ", points_earned=$pointsEarned" : '';

        $sql = "UPDATE task_submissions SET
                    status='$newStatus',
                    feedback='$feedbackEsc',
                    reviewed_by='$reviewedByEsc',
                    reviewed_at=NOW()
                    $pointsSQL,
                    updated_at=NOW()
                WHERE id=$submissionId";

        if ($db->query($sql)) {
            $subRow = $db->query("SELECT student_id FROM task_submissions WHERE id=$submissionId")->fetch_assoc();
            if ($subRow) {
                $sid = (int)$subRow['student_id'];
                // Recalculate total_points when approved
                if ($newStatus === 'approved') {
                    $db->query("UPDATE internship_students SET total_points = (
                        SELECT COALESCE(SUM(points_earned), 0) FROM task_submissions
                        WHERE student_id=$sid AND status='approved'
                    ) WHERE id=$sid");
                }
                // Notify student
                $statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
                $notifTitle  = $db->real_escape_string("Submission $statusLabel");
                $notifMsg    = "Your submission has been updated to: $statusLabel." . ($feedback ? " Feedback: $feedbackEsc" : '');
                $notifEsc    = $db->real_escape_string($notifMsg);
                $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                            VALUES ($sid, '$notifTitle', '$notifEsc', 'task', NOW())");
            }
            $_SESSION['admin_success'] = 'Submission updated successfully!';
            header('Location: admin.php?tab=all_submissions');
            exit;
        } else {
            $error = 'Failed to update submission: ' . $db->error;
        }
    }
}

// ── Handle Bulk Status Update ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $ids        = $_POST['selected_ids'] ?? [];
    $bulkStatus = trim($_POST['bulk_status'] ?? '');
    $allowed    = ['under_review', 'approved', 'rejected'];

    if (!empty($ids) && in_array($bulkStatus, $allowed)) {
        $idsInt     = array_map('intval', $ids);
        $idList     = implode(',', $idsInt);
        $reviewedBy = $db->real_escape_string($_SESSION['admin_username'] ?? 'Admin');
        $db->query("UPDATE task_submissions SET status='$bulkStatus', reviewed_by='$reviewedBy',
                    reviewed_at=NOW(), updated_at=NOW() WHERE id IN ($idList)");
        $_SESSION['admin_success'] = count($idsInt) . ' submission(s) updated to ' . ucfirst(str_replace('_', ' ', $bulkStatus)) . '.';
        header('Location: admin.php?tab=all_submissions');
        exit;
    } else {
        $error = 'Please select submissions and a valid bulk action.';
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filterStatus = $_GET['sub_status'] ?? 'all';
$searchQuery  = $_GET['sub_search'] ?? '';
$filterTask   = (int)($_GET['task_filter'] ?? 0);
$sortBy       = $_GET['sort'] ?? 'newest';

$where = [];
if ($filterStatus !== 'all') {
    $statusEsc = $db->real_escape_string($filterStatus);
    $where[]   = "ts.status='$statusEsc'";
}
if (!empty($searchQuery)) {
    $sq      = $db->real_escape_string($searchQuery);
    $where[] = "(s.full_name LIKE '%$sq%' OR s.email LIKE '%$sq%' OR t.title LIKE '%$sq%')";
}
if ($filterTask > 0) {
    $where[] = "ts.task_id=$filterTask";
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// PHP 7 compatible sort (no match expression)
$orderByMap = [
    'oldest' => 'ts.submitted_at ASC',
    'name'   => 's.full_name ASC',
    'points' => 'ts.points_earned DESC',
];
$orderBy = isset($orderByMap[$sortBy]) ? $orderByMap[$sortBy] : 'ts.submitted_at DESC';

// ── Counts ───────────────────────────────────────────────────────────────────
$countAll         = (int)$db->query("SELECT COUNT(*) c FROM task_submissions")->fetch_assoc()['c'];
$countSubmitted   = (int)$db->query("SELECT COUNT(*) c FROM task_submissions WHERE status='submitted'")->fetch_assoc()['c'];
$countUnderReview = (int)$db->query("SELECT COUNT(*) c FROM task_submissions WHERE status='under_review'")->fetch_assoc()['c'];
$countApproved    = (int)$db->query("SELECT COUNT(*) c FROM task_submissions WHERE status='approved'")->fetch_assoc()['c'];
$countRejected    = (int)$db->query("SELECT COUNT(*) c FROM task_submissions WHERE status='rejected'")->fetch_assoc()['c'];
$countRevision    = (int)$db->query("SELECT COUNT(*) c FROM task_submissions WHERE status='revision_requested'")->fetch_assoc()['c'];

// ── Task dropdown — uses internship_tasks table ───────────────────────────────
$tasksRes  = $db->query("SELECT id, title FROM internship_tasks ORDER BY title ASC");
$tasksList = [];
if ($tasksRes) { while ($t = $tasksRes->fetch_assoc()) $tasksList[] = $t; }

// ── Main query — joins internship_tasks (max_points) ─────────────────────────
$submissionsRes = $db->query("
    SELECT ts.*,
           s.full_name, s.email, s.college_name, s.domain_interest,
           t.title      AS task_title,
           t.max_points AS task_max_points
    FROM task_submissions ts
    LEFT JOIN internship_students s ON s.id = ts.student_id
    LEFT JOIN internship_tasks    t ON t.id = ts.task_id
    $whereClause
    ORDER BY $orderBy
");
$submissions = [];
if ($submissionsRes) { while ($row = $submissionsRes->fetch_assoc()) $submissions[] = $row; }

// URL helper — hash always points to this tab
function buildSubUrl($params = []) {
    $base = [
        'sub_status'  => $_GET['sub_status']  ?? 'all',
        'sub_search'  => $_GET['sub_search']  ?? '',
        'task_filter' => $_GET['task_filter'] ?? 0,
        'sort'        => $_GET['sort']        ?? 'newest',
    ];
    return '?' . http_build_query(array_merge($base, $params)) . '#tab-all_submissions';
}
?>

<style>
.ts-wrap{margin-bottom:24px;}
.ts-section{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px;}
.ts-section-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.ts-sh-title{font-size:1.1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;}
.ts-sh-title i{color:var(--o5);}
.ts-section-body{padding:24px;}
.ts-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px;}
.ts-stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;transition:box-shadow .2s;}
.ts-stat:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);}
.ts-stat-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);}
.ts-stat-value{font-size:1.8rem;font-weight:800;color:var(--text);line-height:1;}
.ts-stat-sub{font-size:.75rem;color:var(--text3);}
.ts-stat.orange .ts-stat-value{color:var(--o5);}
.ts-stat.green  .ts-stat-value{color:#16a34a;}
.ts-stat.red    .ts-stat-value{color:#dc2626;}
.ts-stat.yellow .ts-stat-value{color:#b45309;}
.ts-stat.purple .ts-stat-value{color:#7c3aed;}
.ts-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:18px;}
.ts-filter-tabs{display:flex;gap:6px;flex-wrap:wrap;}
.ts-tab{padding:7px 13px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.78rem;font-weight:600;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.ts-tab:hover{border-color:var(--o5);color:var(--o5);}
.ts-tab.active{background:var(--o5);border-color:var(--o5);color:#fff;}
.ts-tab .cnt{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:0 6px;font-size:.7rem;min-width:20px;height:18px;margin-left:4px;background:var(--bg);color:var(--text2);}
.ts-tab.active .cnt{background:rgba(255,255,255,.25);color:#fff;}
.ts-search{flex:1;min-width:180px;max-width:260px;position:relative;}
.ts-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:.8rem;}
.ts-search input{width:100%;padding:8px 14px 8px 32px;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;font-family:inherit;background:var(--card);color:var(--text);outline:none;}
.ts-search input:focus{border-color:var(--o5);}
.ts-select{padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;font-family:inherit;background:var(--card);color:var(--text);outline:none;cursor:pointer;}
.ts-select:focus{border-color:var(--o5);}
.ts-bulk-bar{display:none;align-items:center;gap:10px;padding:10px 16px;background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.2);border-radius:10px;margin-bottom:14px;flex-wrap:wrap;}
.ts-bulk-bar.show{display:flex;}
.ts-bulk-count{font-size:.85rem;font-weight:700;color:var(--o5);}
.ts-table-wrap{overflow-x:auto;}
.ts-table{width:100%;border-collapse:collapse;}
.ts-table th{background:var(--bg);padding:11px 14px;text-align:left;font-size:.72rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);white-space:nowrap;}
.ts-table th.center{text-align:center;}
.ts-table td{padding:13px 14px;border-bottom:1px solid var(--border);font-size:.84rem;color:var(--text2);vertical-align:middle;}
.ts-table tr:hover{background:var(--bg);}
.ts-table tr.selected-row{background:rgba(249,115,22,.05);}
.ts-student{display:flex;align-items:center;gap:10px;}
.ts-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0;}
.ts-student-name{font-weight:600;color:var(--text);font-size:.85rem;}
.ts-student-meta{font-size:.72rem;color:var(--text3);}
.ts-task-title{font-weight:600;color:var(--text);font-size:.84rem;}
.ts-task-pts{font-size:.72rem;color:var(--text3);}
.ts-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.ts-badge-submitted          {background:rgba(59,130,246,.1); color:#1d4ed8;}
.ts-badge-under_review       {background:rgba(234,179,8,.12); color:#854d0e;}
.ts-badge-approved           {background:rgba(34,197,94,.12); color:#16a34a;}
.ts-badge-rejected           {background:rgba(239,68,68,.12); color:#dc2626;}
.ts-badge-revision_requested {background:rgba(168,85,247,.12);color:#7c3aed;}
.ts-badge-draft              {background:rgba(100,116,139,.1);color:#475569;}
.ts-pts{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:.75rem;font-weight:700;}
.ts-pts-earned{background:rgba(34,197,94,.12);color:#16a34a;}
.ts-pts-null{background:var(--bg);color:var(--text3);}
.ts-link{display:inline-flex;align-items:center;gap:4px;font-size:.75rem;color:var(--o5);text-decoration:none;padding:3px 8px;border-radius:6px;background:rgba(249,115,22,.08);transition:background .2s;margin:2px 0;}
.ts-link:hover{background:rgba(249,115,22,.18);}
.ts-actions{display:flex;gap:6px;flex-wrap:wrap;}
.ts-btn{padding:7px 12px;border-radius:8px;font-size:.75rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .2s;}
.ts-btn-primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 3px 10px rgba(249,115,22,.25);}
.ts-btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(249,115,22,.4);}
.ts-btn-secondary{background:var(--card);border:1.5px solid var(--border);color:var(--text2);}
.ts-btn-secondary:hover{border-color:var(--o5);color:var(--o5);}
.ts-btn-success{background:rgba(34,197,94,.1);border:1.5px solid rgba(34,197,94,.3);color:#16a34a;}
.ts-btn-success:hover{background:rgba(34,197,94,.2);border-color:#16a34a;}
.ts-btn-danger{background:rgba(239,68,68,.1);border:1.5px solid rgba(239,68,68,.3);color:#dc2626;}
.ts-btn-danger:hover{background:rgba(239,68,68,.2);border-color:#dc2626;}
.ts-btn-sm{padding:5px 10px;font-size:.72rem;}
.ts-empty{text-align:center;padding:60px 20px;color:var(--text3);}
.ts-empty i{font-size:3rem;margin-bottom:14px;display:block;opacity:.25;}
.ts-empty h3{font-size:1rem;color:var(--text2);margin-bottom:6px;}
.ts-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
.ts-modal.active{display:flex;}
.ts-modal-content{background:var(--card);border-radius:16px;width:100%;max-width:680px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.28);}
.ts-modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--card);z-index:1;}
.ts-modal-title{font-size:1.15rem;font-weight:700;color:var(--text);}
.ts-modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:4px;line-height:1;transition:color .2s;}
.ts-modal-close:hover{color:#dc2626;}
.ts-modal-body{padding:24px;}
.ts-modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
.ts-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;}
.ts-detail-label{font-size:.72rem;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.ts-detail-value{font-size:.88rem;color:var(--text);font-weight:500;}
.ts-submission-content{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px;font-size:.85rem;color:var(--text);line-height:1.6;margin-bottom:18px;max-height:160px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;}
.ts-divider{border:none;border-top:1px solid var(--border);margin:20px 0;}
.ts-form-group{margin-bottom:16px;}
.ts-form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:7px;}
.ts-form-label .req{color:#dc2626;}
.ts-form-input,.ts-form-textarea,.ts-form-select{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);background:var(--card);outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;}
.ts-form-input:focus,.ts-form-textarea:focus,.ts-form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,.1);}
.ts-form-textarea{resize:vertical;min-height:90px;}
.ts-form-hint{font-size:.73rem;color:var(--text3);margin-top:5px;}
.ts-check{width:16px;height:16px;accent-color:var(--o5);cursor:pointer;}
@media(max-width:768px){.ts-detail-grid{grid-template-columns:1fr;}.ts-stats{grid-template-columns:repeat(2,1fr);}}
</style>

<div class="ts-wrap">

<?php if ($error): ?>
<div style="display:flex;align-items:flex-start;gap:10px;padding:13px 17px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:18px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
    <i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="ts-stats">
    <div class="ts-stat"><div class="ts-stat-label">Total</div><div class="ts-stat-value"><?php echo $countAll; ?></div><div class="ts-stat-sub">All submissions</div></div>
    <div class="ts-stat orange"><div class="ts-stat-label">Awaiting</div><div class="ts-stat-value"><?php echo $countSubmitted; ?></div><div class="ts-stat-sub">Need review</div></div>
    <div class="ts-stat yellow"><div class="ts-stat-label">In Review</div><div class="ts-stat-value"><?php echo $countUnderReview; ?></div><div class="ts-stat-sub">Under review</div></div>
    <div class="ts-stat green"><div class="ts-stat-label">Approved</div><div class="ts-stat-value"><?php echo $countApproved; ?></div><div class="ts-stat-sub">Points awarded</div></div>
    <div class="ts-stat red"><div class="ts-stat-label">Rejected</div><div class="ts-stat-value"><?php echo $countRejected; ?></div><div class="ts-stat-sub">Not accepted</div></div>
    <div class="ts-stat purple"><div class="ts-stat-label">Revision</div><div class="ts-stat-value"><?php echo $countRevision; ?></div><div class="ts-stat-sub">Changes needed</div></div>
</div>

<!-- MAIN SECTION -->
<div class="ts-section">
    <div class="ts-section-header">
        <div class="ts-sh-title"><i class="fas fa-inbox"></i> All Task Submissions</div>
        <form method="GET" style="margin:0;">
            <input type="hidden" name="sub_status"  value="<?php echo htmlspecialchars($filterStatus); ?>">
            <input type="hidden" name="sub_search"  value="<?php echo htmlspecialchars($searchQuery); ?>">
            <input type="hidden" name="task_filter" value="<?php echo $filterTask; ?>">
            <select name="sort" class="ts-select" onchange="this.form.submit()">
                <option value="newest" <?php echo $sortBy==='newest'?'selected':''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sortBy==='oldest'?'selected':''; ?>>Oldest First</option>
                <option value="name"   <?php echo $sortBy==='name'?'selected':''; ?>>Student Name</option>
                <option value="points" <?php echo $sortBy==='points'?'selected':''; ?>>Points Earned</option>
            </select>
        </form>
    </div>
    <div class="ts-section-body">

        <!-- Toolbar -->
        <div class="ts-toolbar">
            <div class="ts-filter-tabs">
                <a href="<?php echo buildSubUrl(['sub_status'=>'all']); ?>"               class="ts-tab <?php echo $filterStatus==='all'?'active':''; ?>">All <span class="cnt"><?php echo $countAll; ?></span></a>
                <a href="<?php echo buildSubUrl(['sub_status'=>'submitted']); ?>"          class="ts-tab <?php echo $filterStatus==='submitted'?'active':''; ?>">Submitted <span class="cnt"><?php echo $countSubmitted; ?></span></a>
                <a href="<?php echo buildSubUrl(['sub_status'=>'under_review']); ?>"       class="ts-tab <?php echo $filterStatus==='under_review'?'active':''; ?>">In Review <span class="cnt"><?php echo $countUnderReview; ?></span></a>
                <a href="<?php echo buildSubUrl(['sub_status'=>'approved']); ?>"           class="ts-tab <?php echo $filterStatus==='approved'?'active':''; ?>">Approved <span class="cnt"><?php echo $countApproved; ?></span></a>
                <a href="<?php echo buildSubUrl(['sub_status'=>'rejected']); ?>"           class="ts-tab <?php echo $filterStatus==='rejected'?'active':''; ?>">Rejected <span class="cnt"><?php echo $countRejected; ?></span></a>
                <a href="<?php echo buildSubUrl(['sub_status'=>'revision_requested']); ?>" class="ts-tab <?php echo $filterStatus==='revision_requested'?'active':''; ?>">Revision <span class="cnt"><?php echo $countRevision; ?></span></a>
            </div>
            <form method="GET" style="margin:0;display:contents;">
                <input type="hidden" name="sub_status"  value="<?php echo htmlspecialchars($filterStatus); ?>">
                <input type="hidden" name="task_filter" value="<?php echo $filterTask; ?>">
                <input type="hidden" name="sort"        value="<?php echo htmlspecialchars($sortBy); ?>">
                <div class="ts-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" name="sub_search" placeholder="Search student, task…"
                           value="<?php echo htmlspecialchars($searchQuery); ?>" onchange="this.form.submit()">
                </div>
            </form>
            <form method="GET" style="margin:0;">
                <input type="hidden" name="sub_status"  value="<?php echo htmlspecialchars($filterStatus); ?>">
                <input type="hidden" name="sub_search"  value="<?php echo htmlspecialchars($searchQuery); ?>">
                <input type="hidden" name="sort"        value="<?php echo htmlspecialchars($sortBy); ?>">
                <select name="task_filter" class="ts-select" onchange="this.form.submit()">
                    <option value="0">All Tasks</option>
                    <?php foreach ($tasksList as $tl): ?>
                    <option value="<?php echo $tl['id']; ?>" <?php echo $filterTask==$tl['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($tl['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Bulk form -->
        <form method="POST" id="bulkForm">
            <input type="hidden" name="bulk_update" value="1">
            <div class="ts-bulk-bar" id="bulkBar">
                <span class="ts-bulk-count"><i class="fas fa-check-circle"></i> <span id="selectedCount">0</span> selected</span>
                <select name="bulk_status" class="ts-select" style="padding:6px 10px;font-size:.78rem;">
                    <option value="">Choose action…</option>
                    <option value="under_review">Mark as Under Review</option>
                    <option value="approved">Approve All</option>
                    <option value="rejected">Reject All</option>
                </select>
                <button type="submit" class="ts-btn ts-btn-primary ts-btn-sm" onclick="return confirmBulk()">
                    <i class="fas fa-bolt"></i> Apply
                </button>
                <button type="button" class="ts-btn ts-btn-secondary ts-btn-sm" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>

            <?php if (empty($submissions)): ?>
            <div class="ts-empty">
                <i class="fas fa-inbox"></i>
                <h3>No submissions found</h3>
                <p><?php echo !empty($searchQuery) ? 'Try a different search term' : 'No submissions match the current filter'; ?></p>
            </div>
            <?php else: ?>
            <div class="ts-table-wrap">
                <table class="ts-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" class="ts-check" id="checkAll" title="Select all"></th>
                            <th>Student</th>
                            <th>Task</th>
                            <th>Submitted</th>
                            <th class="center">Links</th>
                            <th class="center">Points</th>
                            <th>Status</th>
                            <th>Reviewed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <tr id="row-<?php echo $sub['id']; ?>">
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $sub['id']; ?>" class="ts-check row-check" onchange="updateBulkBar()"></td>
                            <td>
                                <div class="ts-student">
                                    <div class="ts-avatar"><?php echo strtoupper(substr($sub['full_name'] ?? '?', 0, 2)); ?></div>
                                    <div>
                                        <div class="ts-student-name"><?php echo htmlspecialchars($sub['full_name'] ?? '—'); ?></div>
                                        <div class="ts-student-meta"><?php echo htmlspecialchars($sub['email'] ?? ''); ?></div>
                                        <?php if (!empty($sub['college_name'])): ?>
                                        <div class="ts-student-meta"><i class="fas fa-building-columns fa-xs"></i> <?php echo htmlspecialchars($sub['college_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="ts-task-title"><?php echo htmlspecialchars($sub['task_title'] ?? '—'); ?></div>
                                <?php if (!empty($sub['task_max_points'])): ?>
                                <div class="ts-task-pts"><i class="fas fa-star fa-xs"></i> Max <?php echo $sub['task_max_points']; ?> pts</div>
                                <?php endif; ?>
                                <div class="ts-task-pts">ID #<?php echo $sub['task_id']; ?></div>
                            </td>
                            <td>
                                <div style="font-size:.82rem;color:var(--text);"><?php echo date('d M Y', strtotime($sub['submitted_at'])); ?></div>
                                <div style="font-size:.72rem;color:var(--text3);"><?php echo date('h:i A', strtotime($sub['submitted_at'])); ?></div>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:flex;flex-direction:column;align-items:flex-start;gap:3px;">
                                    <?php if (!empty($sub['submission_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($sub['submission_url']); ?>" target="_blank" class="ts-link"><i class="fas fa-link fa-xs"></i> URL</a>
                                    <?php endif; ?>
                                    <?php if (!empty($sub['github_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($sub['github_link']); ?>" target="_blank" class="ts-link"><i class="fab fa-github fa-xs"></i> GitHub</a>
                                    <?php endif; ?>
                                    <?php if (!empty($sub['file_name'])): ?>
                                    <a href="/<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank" class="ts-link"><i class="fas fa-file fa-xs"></i> <?php echo htmlspecialchars(substr($sub['file_name'],0,12).(strlen($sub['file_name'])>12?'…':'')); ?></a>
                                    <?php endif; ?>
                                    <?php if (empty($sub['submission_url']) && empty($sub['github_link']) && empty($sub['file_name'])): ?>
                                    <span style="color:var(--text3);font-size:.75rem;">Text only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($sub['points_earned'] !== null && $sub['points_earned'] !== ''): ?>
                                <span class="ts-pts ts-pts-earned"><i class="fas fa-star fa-xs"></i> <?php echo $sub['points_earned']; ?></span>
                                <?php else: ?>
                                <span class="ts-pts ts-pts-null">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusIcons = [
                                    'submitted'=>'fa-paper-plane','under_review'=>'fa-magnifying-glass',
                                    'approved'=>'fa-circle-check','rejected'=>'fa-circle-xmark',
                                    'revision_requested'=>'fa-rotate','draft'=>'fa-pen'
                                ];
                                $ic = isset($statusIcons[$sub['status']]) ? $statusIcons[$sub['status']] : 'fa-circle';
                                ?>
                                <span class="ts-badge ts-badge-<?php echo htmlspecialchars($sub['status']); ?>">
                                    <i class="fas <?php echo $ic; ?> fa-xs"></i>
                                    <?php echo ucwords(str_replace('_',' ',$sub['status'])); ?>
                                </span>
                                <?php if (!empty($sub['reviewed_at'])): ?>
                                <div style="font-size:.7rem;color:var(--text3);margin-top:4px;"><?php echo date('d M y', strtotime($sub['reviewed_at'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($sub['reviewed_by'])): ?>
                                <span style="font-size:.82rem;color:var(--text);"><?php echo htmlspecialchars($sub['reviewed_by']); ?></span>
                                <?php else: ?>
                                <span style="color:var(--text3);font-size:.78rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="ts-actions">
                                    <button type="button" class="ts-btn ts-btn-secondary ts-btn-sm"
                                            onclick='openReviewModal(<?php echo json_encode($sub); ?>)' title="View & Review">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($sub['status'] !== 'approved'): ?>
                                    <button type="button" class="ts-btn ts-btn-success ts-btn-sm"
                                            onclick="quickAction(<?php echo $sub['id']; ?>,'approved')" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'rejected'): ?>
                                    <button type="button" class="ts-btn ts-btn-danger ts-btn-sm"
                                            onclick="quickAction(<?php echo $sub['id']; ?>,'rejected')" title="Reject">
                                        <i class="fas fa-xmark"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </form>

    </div>
</div>
</div>

<!-- REVIEW MODAL -->
<div id="tsReviewModal" class="ts-modal">
    <div class="ts-modal-content">
        <div class="ts-modal-header">
            <div class="ts-modal-title"><i class="fas fa-clipboard-check" style="color:var(--o5);margin-right:8px;"></i>Review Submission</div>
            <button class="ts-modal-close" onclick="closeTsModal()">&times;</button>
        </div>
        <form method="POST" id="reviewForm">
            <div class="ts-modal-body">
                <input type="hidden" name="submission_id"            id="rev_submission_id">
                <input type="hidden" name="update_submission_status" value="1">
                <div class="ts-detail-grid">
                    <div>
                        <div class="ts-detail-label"><i class="fas fa-user fa-xs"></i> Student</div>
                        <div class="ts-detail-value" id="rev_student_name">—</div>
                        <div style="font-size:.75rem;color:var(--text3);" id="rev_student_email"></div>
                    </div>
                    <div>
                        <div class="ts-detail-label"><i class="fas fa-tasks fa-xs"></i> Task</div>
                        <div class="ts-detail-value" id="rev_task_title">—</div>
                        <div style="font-size:.75rem;color:var(--text3);" id="rev_task_pts"></div>
                    </div>
                    <div>
                        <div class="ts-detail-label"><i class="fas fa-clock fa-xs"></i> Submitted At</div>
                        <div class="ts-detail-value" id="rev_submitted_at">—</div>
                    </div>
                    <div>
                        <div class="ts-detail-label"><i class="fas fa-circle-dot fa-xs"></i> Current Status</div>
                        <div id="rev_current_status"></div>
                    </div>
                </div>
                <div id="rev_text_section" style="display:none;margin-bottom:16px;">
                    <div class="ts-detail-label" style="margin-bottom:6px;"><i class="fas fa-align-left fa-xs"></i> Submission Text</div>
                    <div class="ts-submission-content" id="rev_submission_text"></div>
                </div>
                <div id="rev_links_section" style="display:none;margin-bottom:16px;">
                    <div class="ts-detail-label" style="margin-bottom:8px;"><i class="fas fa-link fa-xs"></i> Submission Links</div>
                    <div id="rev_links_container" style="display:flex;flex-direction:column;gap:6px;"></div>
                </div>
                <div id="rev_prev_feedback_wrap" style="display:none;margin-bottom:16px;">
                    <div class="ts-detail-label" style="margin-bottom:6px;"><i class="fas fa-comment-dots fa-xs"></i> Previous Feedback</div>
                    <div id="rev_prev_feedback" style="background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:12px 14px;font-size:.84rem;color:var(--text2);line-height:1.55;"></div>
                </div>
                <hr class="ts-divider">
                <div class="ts-form-group">
                    <label class="ts-form-label">Update Status <span class="req">*</span></label>
                    <select name="status" id="rev_status" class="ts-form-select" onchange="togglePointsField()">
                        <option value="submitted">Submitted (Awaiting Review)</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved ✓</option>
                        <option value="rejected">Rejected ✗</option>
                        <option value="revision_requested">Revision Requested ↺</option>
                    </select>
                </div>
                <div class="ts-form-group" id="pointsGroup" style="display:none;">
                    <label class="ts-form-label">Points to Award <span class="req">*</span></label>
                    <input type="number" name="points_earned" id="rev_points" class="ts-form-input" min="0" max="9999" placeholder="Enter points…">
                    <div class="ts-form-hint"><i class="fas fa-info-circle"></i> Max points for this task: <strong id="rev_max_pts">—</strong></div>
                </div>
                <div class="ts-form-group">
                    <label class="ts-form-label">Feedback / Comments</label>
                    <textarea name="feedback" id="rev_feedback" class="ts-form-textarea" placeholder="Provide constructive feedback to the student…"></textarea>
                    <div class="ts-form-hint"><i class="fas fa-bell fa-xs"></i> This will be sent to the student as a notification.</div>
                </div>
            </div>
            <div class="ts-modal-footer">
                <button type="button" class="ts-btn ts-btn-secondary" onclick="closeTsModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="ts-btn ts-btn-primary"><i class="fas fa-save"></i> Save Review</button>
            </div>
        </form>
    </div>
</div>

<!-- Quick-action form -->
<form method="POST" id="quickActionForm" style="display:none;">
    <input type="hidden" name="submission_id"            id="qa_id">
    <input type="hidden" name="status"                   id="qa_status">
    <input type="hidden" name="feedback"                 value="">
    <input type="hidden" name="update_submission_status" value="1">
</form>

<script>
function closeTsModal() { document.getElementById('tsReviewModal').classList.remove('active'); }
document.getElementById('tsReviewModal').addEventListener('click', function(e){ if(e.target===this) closeTsModal(); });

function openReviewModal(sub) {
    document.getElementById('rev_submission_id').value   = sub.id;
    document.getElementById('rev_student_name').textContent  = sub.full_name  || '—';
    document.getElementById('rev_student_email').textContent = sub.email      || '';
    document.getElementById('rev_task_title').textContent    = sub.task_title || '—';
    document.getElementById('rev_task_pts').textContent      = sub.task_max_points ? 'Max '+sub.task_max_points+' pts' : '';
    document.getElementById('rev_max_pts').textContent       = sub.task_max_points || '—';
    document.getElementById('rev_points').max                = sub.task_max_points || 9999;

    var d = new Date(sub.submitted_at);
    document.getElementById('rev_submitted_at').textContent =
        d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) + ' ' +
        d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});

    var bm = {
        submitted:          ['#1d4ed8','rgba(59,130,246,.1)',  'fa-paper-plane'],
        under_review:       ['#854d0e','rgba(234,179,8,.12)',  'fa-magnifying-glass'],
        approved:           ['#16a34a','rgba(34,197,94,.12)',  'fa-circle-check'],
        rejected:           ['#dc2626','rgba(239,68,68,.12)',  'fa-circle-xmark'],
        revision_requested: ['#7c3aed','rgba(168,85,247,.12)','fa-rotate'],
        draft:              ['#475569','rgba(100,116,139,.1)','fa-pen']
    };
    var b = bm[sub.status] || ['#475569','rgba(100,116,139,.1)','fa-circle'];
    document.getElementById('rev_current_status').innerHTML =
        '<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:700;background:'+b[1]+';color:'+b[0]+';">' +
        '<i class="fas '+b[2]+' fa-xs"></i> ' +
        sub.status.replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();}) + '</span>';

    document.getElementById('rev_status').value = sub.status;
    document.getElementById('rev_points').value = sub.points_earned || '';
    document.getElementById('rev_feedback').value = '';
    togglePointsField();

    var txtSec = document.getElementById('rev_text_section');
    if (sub.submission_text && sub.submission_text.trim()) {
        document.getElementById('rev_submission_text').textContent = sub.submission_text;
        txtSec.style.display = 'block';
    } else { txtSec.style.display = 'none'; }

    var linksSec = document.getElementById('rev_links_section');
    var linksCon = document.getElementById('rev_links_container');
    linksCon.innerHTML = '';
    var links = [];
    if (sub.submission_url) links.push({label:'Submission URL',icon:'fa-link',href:sub.submission_url});
    if (sub.github_link)    links.push({label:'GitHub',icon:'fa-github',href:sub.github_link,fab:true});
    if (sub.file_path)      links.push({label:sub.file_name||'Attached File',icon:'fa-file',href:'/'+sub.file_path});
    if (links.length) {
        links.forEach(function(l){
            var a=document.createElement('a'); a.href=l.href; a.target='_blank'; a.className='ts-link';
            a.innerHTML='<i class="'+(l.fab?'fab':'fas')+' '+l.icon+' fa-xs"></i> '+l.label;
            linksCon.appendChild(a);
        });
        linksSec.style.display='block';
    } else { linksSec.style.display='none'; }

    var pfWrap = document.getElementById('rev_prev_feedback_wrap');
    if (sub.feedback && sub.feedback.trim()) {
        document.getElementById('rev_prev_feedback').textContent = sub.feedback;
        pfWrap.style.display = 'block';
    } else { pfWrap.style.display = 'none'; }

    document.getElementById('tsReviewModal').classList.add('active');
}

function togglePointsField() {
    var status = document.getElementById('rev_status').value;
    var pg = document.getElementById('pointsGroup');
    var pi = document.getElementById('rev_points');
    pg.style.display = (status==='approved') ? 'block' : 'none';
    pi.required      = (status==='approved');
}

function quickAction(id, status) {
    var labels = {approved:'approve',rejected:'reject',under_review:'mark as Under Review'};
    if (!confirm('Are you sure you want to '+(labels[status]||status)+' this submission?')) return;
    document.getElementById('qa_id').value=id;
    document.getElementById('qa_status').value=status;
    document.getElementById('quickActionForm').submit();
}

document.getElementById('checkAll').addEventListener('change', function(){
    document.querySelectorAll('.row-check').forEach(function(c){ c.checked=this.checked; },this);
    updateBulkBar();
});
function updateBulkBar() {
    var checked = document.querySelectorAll('.row-check:checked');
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('bulkBar').classList.toggle('show', checked.length>0);
    document.querySelectorAll('.row-check').forEach(function(c){
        var row=c.closest('tr'); if(row) row.classList.toggle('selected-row',c.checked);
    });
}
function clearSelection() {
    document.querySelectorAll('.row-check').forEach(function(c){c.checked=false;});
    document.getElementById('checkAll').checked=false;
    updateBulkBar();
}
function confirmBulk() {
    var cnt=document.querySelectorAll('.row-check:checked').length;
    var action=document.querySelector('#bulkForm select[name="bulk_status"]').value;
    if(!action){alert('Please choose a bulk action first.');return false;}
    return confirm('Apply "'+action.replace(/_/g,' ')+'" to '+cnt+' submission(s)?');
}
</script>