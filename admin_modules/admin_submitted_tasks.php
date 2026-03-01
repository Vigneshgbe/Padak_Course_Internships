<?php
// admin_submitted_tasks.php
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

if (!isset($db)) {
    echo '<div style="padding:40px;text-align:center;color:#dc2626;"><h3>Database error</h3></div>';
    return;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission_status'])) {
    $submissionId = (int)$_POST['submission_id'];
    $newStatus = $db->real_escape_string($_POST['status']);
    $feedback = $db->real_escape_string(trim($_POST['feedback'] ?? ''));
    $pointsEarned = isset($_POST['points_earned']) ? (int)$_POST['points_earned'] : 0;
    
    $sql = "UPDATE task_submissions SET 
            status='$newStatus',
            feedback='$feedback',
            points_earned=$pointsEarned,
            reviewed_by='" . $db->real_escape_string($_SESSION['admin_username']) . "',
            reviewed_at=NOW()
            WHERE id=$submissionId";
    
    if ($db->query($sql)) {
        if ($newStatus === 'approved' && $pointsEarned > 0) {
            $res = $db->query("SELECT student_id FROM task_submissions WHERE id=$submissionId");
            if ($res && $row = $res->fetch_assoc()) {
                $db->query("UPDATE internship_students SET total_points=total_points+$pointsEarned WHERE id=" . $row['student_id']);
            }
        }
        $_SESSION['admin_success'] = 'Updated successfully!';
    } else {
        $_SESSION['admin_error'] = 'Update failed';
    }
    header('Location: admin.php#tab-submitted-tasks');
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission'])) {
    $submissionId = (int)$_POST['submission_id'];
    $db->query("DELETE FROM task_submissions WHERE id=$submissionId");
    $_SESSION['admin_success'] = 'Deleted successfully!';
    header('Location: admin.php#tab-submitted-tasks');
    exit;
}

// Filters
$statusFilter = $_GET['submission_status_filter'] ?? 'all';
$taskFilter = $_GET['task_filter'] ?? 'all';
$searchQuery = $_GET['submission_search'] ?? '';

$whereConditions = [];
if ($statusFilter !== 'all') {
    $whereConditions[] = "ts.status='" . $db->real_escape_string($statusFilter) . "'";
}
if ($taskFilter !== 'all' && is_numeric($taskFilter)) {
    $whereConditions[] = "ts.task_id=" . (int)$taskFilter;
}
if (!empty($searchQuery)) {
    $whereConditions[] = "(s.full_name LIKE '%" . $db->real_escape_string($searchQuery) . "%' OR s.email LIKE '%" . $db->real_escape_string($searchQuery) . "%')";
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Counts
$allCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions")->fetch_assoc()['cnt'];
$submittedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='submitted'")->fetch_assoc()['cnt'];
$underReviewCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='under_review'")->fetch_assoc()['cnt'];
$approvedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='approved'")->fetch_assoc()['cnt'];
$rejectedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='rejected'")->fetch_assoc()['cnt'];

// Tasks
$allTasks = [];
$tasksRes = $db->query("SELECT id, task_title FROM internship_tasks ORDER BY task_title");
while ($row = $tasksRes->fetch_assoc()) $allTasks[] = $row;

// Submissions
$submissions = [];
$submissionsRes = $db->query("SELECT ts.*, t.task_title, t.points as task_max_points, t.difficulty, s.full_name as student_name, s.email as student_email, s.domain_interest
    FROM task_submissions ts
    JOIN internship_tasks t ON ts.task_id=t.id
    JOIN internship_students s ON ts.student_id=s.id
    $whereClause
    ORDER BY ts.submitted_at DESC");
while ($row = $submissionsRes->fetch_assoc()) $submissions[] = $row;
?>

<style>
.st-section{background:#fff;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:24px;}
.st-section-header{padding:18px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.st-sh-title{font-size:1.1rem;font-weight:700;display:flex;align-items:center;gap:10px;}
.st-section-body{padding:24px;}
.st-filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
.st-filter-btn{padding:8px 14px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;font-size:.8rem;font-weight:500;text-decoration:none;transition:all .2s;}
.st-filter-btn.active{background:#f97316;border-color:#f97316;color:#fff;}
.st-search-box{flex:1;max-width:300px;}
.st-search-box input{width:100%;padding:8px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.85rem;}
.st-table-responsive{overflow-x:auto;}
.st-data-table{width:100%;border-collapse:collapse;}
.st-data-table th{background:#f8fafc;padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;border-bottom:2px solid #e2e8f0;}
.st-data-table td{padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:.85rem;}
.st-data-table tr:hover{background:#f8fafc;}
.st-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;}
.st-badge-submitted{background:rgba(234,179,8,0.12);color:#854d0e;}
.st-badge-under_review{background:rgba(59,130,246,0.12);color:#1d4ed8;}
.st-badge-approved{background:rgba(34,197,94,0.12);color:#16a34a;}
.st-badge-rejected{background:rgba(239,68,68,0.12);color:#dc2626;}
.st-badge-easy{background:rgba(34,197,94,0.12);color:#16a34a;}
.st-badge-medium{background:rgba(234,179,8,0.12);color:#854d0e;}
.st-badge-hard{background:rgba(239,68,68,0.12);color:#dc2626;}
.st-btn{padding:10px 18px;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;transition:all .2s;}
.st-btn-primary{background:linear-gradient(135deg,#f97316,#fb923c);color:#fff;}
.st-btn-secondary{background:#fff;border:1.5px solid #e2e8f0;color:#475569;}
.st-btn-danger{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.3);color:#dc2626;}
.st-btn-info{background:rgba(59,130,246,0.1);border:1.5px solid rgba(59,130,246,0.3);color:#2563eb;}
.st-btn-sm{padding:6px 12px;font-size:.75rem;}
.st-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;}
.st-modal.active{display:flex;}
.st-modal-content{background:#fff;border-radius:16px;width:100%;max-width:800px;max-height:90vh;overflow-y:auto;}
.st-modal-header{padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;}
.st-mh-title{font-size:1.2rem;font-weight:700;}
.st-modal-close{background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;}
.st-modal-body{padding:24px;}
.st-modal-footer{padding:16px 24px;border-top:1px solid #e2e8f0;display:flex;gap:10px;justify-content:flex-end;}
.st-form-group{margin-bottom:18px;}
.st-form-label{display:block;font-size:.82rem;font-weight:700;margin-bottom:8px;}
.st-form-input,.st-form-textarea,.st-form-select{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.875rem;}
.st-form-textarea{min-height:100px;resize:vertical;}
.st-submission-details{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:16px;}
.st-sd-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;}
.st-sd-row:last-child{border-bottom:none;}
.st-student-info{display:flex;align-items:center;gap:10px;}
.st-student-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#f97316,#fb923c);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;}
.st-empty-state{text-align:center;padding:60px 20px;color:#94a3b8;}
.st-empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
.st-action-buttons{display:flex;gap:6px;flex-wrap:wrap;}
</style>

<div class="st-section">
    <div class="st-section-header">
        <div class="st-sh-title"><i class="fas fa-paper-plane"></i>All Submissions (<?php echo $allCount; ?>)</div>
    </div>
    <div class="st-section-body">
        <div class="st-filter-bar">
            <a href="?submission_status_filter=all#tab-submitted-tasks" class="st-filter-btn <?php echo $statusFilter==='all'?'active':''; ?>">All (<?php echo $allCount; ?>)</a>
            <a href="?submission_status_filter=submitted#tab-submitted-tasks" class="st-filter-btn <?php echo $statusFilter==='submitted'?'active':''; ?>">Submitted (<?php echo $submittedCount; ?>)</a>
            <a href="?submission_status_filter=under_review#tab-submitted-tasks" class="st-filter-btn <?php echo $statusFilter==='under_review'?'active':''; ?>">Under Review (<?php echo $underReviewCount; ?>)</a>
            <a href="?submission_status_filter=approved#tab-submitted-tasks" class="st-filter-btn <?php echo $statusFilter==='approved'?'active':''; ?>">Approved (<?php echo $approvedCount; ?>)</a>
            <a href="?submission_status_filter=rejected#tab-submitted-tasks" class="st-filter-btn <?php echo $statusFilter==='rejected'?'active':''; ?>">Rejected (<?php echo $rejectedCount; ?>)</a>
            
            <select class="st-form-select" style="min-width:200px;" onchange="window.location.href='?task_filter='+this.value+'#tab-submitted-tasks'">
                <option value="all">All Tasks</option>
                <?php foreach ($allTasks as $task): ?>
                <option value="<?php echo $task['id']; ?>" <?php echo $taskFilter==(string)$task['id']?'selected':''; ?>><?php echo htmlspecialchars($task['task_title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if (empty($submissions)): ?>
        <div class="st-empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No submissions found</h3>
        </div>
        <?php else: ?>
        <div class="st-table-responsive">
            <table class="st-data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Task</th>
                        <th>Difficulty</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td>
                            <div class="st-student-info">
                                <div class="st-student-avatar"><?php echo strtoupper(substr($sub['student_name'], 0, 2)); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($sub['student_name']); ?></strong>
                                    <br><small style="color:#94a3b8;"><?php echo htmlspecialchars($sub['student_email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><strong><?php echo htmlspecialchars($sub['task_title']); ?></strong></td>
                        <td><span class="st-badge st-badge-<?php echo $sub['difficulty']; ?>"><?php echo ucfirst($sub['difficulty']); ?></span></td>
                        <td><small><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></small></td>
                        <td><span class="st-badge st-badge-<?php echo $sub['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?></span></td>
                        <td><?php echo $sub['points_earned'] ?: '—'; ?> / <?php echo $sub['task_max_points']; ?> pts</td>
                        <td>
                            <div class="st-action-buttons">
                                <button class="st-btn st-btn-info st-btn-sm" onclick='viewSub(<?php echo json_encode($sub); ?>)'><i class="fas fa-eye"></i></button>
                                <button class="st-btn st-btn-primary st-btn-sm" onclick='reviewSub(<?php echo json_encode($sub); ?>)'><i class="fas fa-edit"></i></button>
                                <button class="st-btn st-btn-danger st-btn-sm" onclick='deleteSub(<?php echo $sub['id']; ?>)'><i class="fas fa-trash"></i></button>
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

<!-- View Modal -->
<div id="viewModal" class="st-modal">
    <div class="st-modal-content">
        <div class="st-modal-header">
            <div class="st-mh-title"><i class="fas fa-eye"></i> Submission Details</div>
            <button class="st-modal-close" onclick="document.getElementById('viewModal').classList.remove('active')">&times;</button>
        </div>
        <div class="st-modal-body">
            <div class="st-submission-details">
                <div class="st-sd-row"><span><strong>Student:</strong></span><span id="v_student"></span></div>
                <div class="st-sd-row"><span><strong>Task:</strong></span><span id="v_task"></span></div>
                <div class="st-sd-row"><span><strong>Status:</strong></span><span id="v_status"></span></div>
                <div class="st-sd-row"><span><strong>Submitted:</strong></span><span id="v_date"></span></div>
                <div class="st-sd-row"><span><strong>Points:</strong></span><span id="v_points"></span></div>
            </div>
            <div class="st-form-group">
                <label class="st-form-label">Submission Text:</label>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;max-height:300px;overflow-y:auto;" id="v_text"></div>
            </div>
            <div class="st-form-group" id="v_url_div" style="display:none;">
                <label class="st-form-label">URL:</label>
                <a href="#" id="v_url" target="_blank" style="color:#f97316;"></a>
            </div>
            <div class="st-form-group" id="v_github_div" style="display:none;">
                <label class="st-form-label">GitHub:</label>
                <a href="#" id="v_github" target="_blank" style="color:#f97316;"></a>
            </div>
            <div class="st-form-group" id="v_feedback_div" style="display:none;">
                <label class="st-form-label">Feedback:</label>
                <div style="background:#f8fafc;padding:12px;border-radius:8px;" id="v_feedback"></div>
            </div>
        </div>
        <div class="st-modal-footer">
            <button class="st-btn st-btn-secondary" onclick="document.getElementById('viewModal').classList.remove('active')">Close</button>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="st-modal">
    <div class="st-modal-content">
        <div class="st-modal-header">
            <div class="st-mh-title"><i class="fas fa-clipboard-check"></i> Review Submission</div>
            <button class="st-modal-close" onclick="document.getElementById('reviewModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <div class="st-modal-body">
                <input type="hidden" name="submission_id" id="r_id">
                <div style="padding:14px;background:#fff7ed;border:1px solid #ffedd5;border-radius:10px;margin-bottom:18px;">
                    <strong id="r_info"></strong>
                </div>
                <div class="st-form-group">
                    <label class="st-form-label">Status *</label>
                    <select name="status" id="r_status" class="st-form-select" required onchange="document.getElementById('r_points_div').style.display=this.value==='approved'?'block':'none'">
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="st-form-group" id="r_points_div" style="display:none;">
                    <label class="st-form-label">Points Earned *</label>
                    <input type="number" name="points_earned" id="r_points" class="st-form-input" min="0">
                    <small style="font-size:.75rem;color:#94a3b8;margin-top:5px;display:block;">Max: <strong id="r_max_points"></strong> points</small>
                </div>
                <div class="st-form-group">
                    <label class="st-form-label">Feedback</label>
                    <textarea name="feedback" id="r_feedback" class="st-form-textarea" placeholder="Feedback..."></textarea>
                </div>
            </div>
            <div class="st-modal-footer">
                <button type="button" class="st-btn st-btn-secondary" onclick="document.getElementById('reviewModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="update_submission_status" class="st-btn st-btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="submission_id" id="d_id">
    <input type="hidden" name="delete_submission" value="1">
</form>

<script>
function viewSub(s){
    document.getElementById('v_student').textContent=s.student_name;
    document.getElementById('v_task').textContent=s.task_title;
    document.getElementById('v_status').innerHTML='<span class="st-badge st-badge-'+s.status+'">'+s.status.replace('_',' ').toUpperCase()+'</span>';
    document.getElementById('v_date').textContent=new Date(s.submitted_at).toLocaleString();
    document.getElementById('v_points').textContent=(s.points_earned||'—')+' / '+s.task_max_points+' pts';
    document.getElementById('v_text').textContent=s.submission_text||'No text';
    
    if(s.submission_url){
        document.getElementById('v_url_div').style.display='block';
        document.getElementById('v_url').href=s.submission_url;
        document.getElementById('v_url').textContent=s.submission_url;
    }else{
        document.getElementById('v_url_div').style.display='none';
    }
    
    if(s.github_link){
        document.getElementById('v_github_div').style.display='block';
        document.getElementById('v_github').href=s.github_link;
        document.getElementById('v_github').textContent=s.github_link;
    }else{
        document.getElementById('v_github_div').style.display='none';
    }
    
    if(s.feedback){
        document.getElementById('v_feedback_div').style.display='block';
        document.getElementById('v_feedback').textContent=s.feedback;
    }else{
        document.getElementById('v_feedback_div').style.display='none';
    }
    
    document.getElementById('viewModal').classList.add('active');
}

function reviewSub(s){
    document.getElementById('r_id').value=s.id;
    document.getElementById('r_info').textContent=s.student_name+' - '+s.task_title;
    document.getElementById('r_status').value=s.status;
    document.getElementById('r_points').value=s.points_earned||'';
    document.getElementById('r_points').max=s.task_max_points;
    document.getElementById('r_max_points').textContent=s.task_max_points;
    document.getElementById('r_feedback').value=s.feedback||'';
    document.getElementById('r_points_div').style.display=s.status==='approved'?'block':'none';
    document.getElementById('reviewModal').classList.add('active');
}

function deleteSub(id){
    if(confirm('Delete this submission?')){
        document.getElementById('d_id').value=id;
        document.getElementById('deleteForm').submit();
    }
}

document.querySelectorAll('.st-modal').forEach(m=>m.addEventListener('click',function(e){
    if(e.target===this)this.classList.remove('active');
}));
</script>