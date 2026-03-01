<?php
// admin_submitted_tasks.php - Submitted Tasks Management Module
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

// Handle Task Review/Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission_status'])) {
    $submissionId = (int)$_POST['submission_id'];
    $newStatus = $_POST['status'];
    $feedback = trim($_POST['feedback'] ?? '');
    $pointsEarned = isset($_POST['points_earned']) ? (int)$_POST['points_earned'] : null;
    
    if (!in_array($newStatus, ['submitted', 'under_review', 'approved', 'rejected'])) {
        $error = 'Invalid status selected';
    } else {
        $feedbackEsc = $db->real_escape_string($feedback);
        $reviewedBy = $db->real_escape_string($_SESSION['admin_username']);
        
        $sql = "UPDATE task_submissions SET 
                status='$newStatus',
                feedback=" . ($feedback ? "'$feedbackEsc'" : "NULL") . ",
                points_earned=" . ($pointsEarned !== null ? $pointsEarned : "NULL") . ",
                reviewed_by='$reviewedBy',
                reviewed_at=NOW(),
                updated_at=NOW()
                WHERE id=$submissionId";
        
        if ($db->query($sql)) {
            // Get submission details for notification
            $subData = $db->query("SELECT ts.*, t.task_title, t.points as task_points, s.full_name 
                                   FROM task_submissions ts
                                   JOIN internship_tasks t ON ts.task_id=t.id
                                   JOIN internship_students s ON ts.student_id=s.id
                                   WHERE ts.id=$submissionId")->fetch_assoc();
            
            // Update student points if approved
            if ($newStatus === 'approved' && $pointsEarned !== null) {
                $studentId = $subData['student_id'];
                $db->query("UPDATE internship_students SET total_points=total_points+$pointsEarned WHERE id=$studentId");
            }
            
            // Send notification to student
            if ($subData) {
                $notifTitle = '';
                $notifMsg = '';
                $notifType = 'info';
                
                if ($newStatus === 'approved') {
                    $notifTitle = 'Task Approved! 🎉';
                    $notifMsg = "Your submission for '{$subData['task_title']}' has been approved! You earned $pointsEarned points.";
                    $notifType = 'success';
                } elseif ($newStatus === 'rejected') {
                    $notifTitle = 'Task Needs Revision';
                    $notifMsg = "Your submission for '{$subData['task_title']}' needs revision. Feedback: " . ($feedback ?: 'Please resubmit with improvements.');
                    $notifType = 'warning';
                } elseif ($newStatus === 'under_review') {
                    $notifTitle = 'Task Under Review';
                    $notifMsg = "Your submission for '{$subData['task_title']}' is now under review by the admin team.";
                    $notifType = 'info';
                }
                
                if ($notifTitle) {
                    $notifTitleEsc = $db->real_escape_string($notifTitle);
                    $notifMsgEsc = $db->real_escape_string($notifMsg);
                    $studentId = $subData['student_id'];
                    $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                               VALUES ($studentId, '$notifTitleEsc', '$notifMsgEsc', '$notifType', NOW())");
                }
            }
            
            $_SESSION['admin_success'] = 'Submission status updated successfully!';
            echo '<script>window.location.href="admin.php#tab-submitted-tasks";</script>';
            exit;
        } else {
            $error = 'Failed to update submission status: ' . $db->error;
        }
    }
}

// Handle Delete Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission'])) {
    $submissionId = (int)$_POST['submission_id'];
    
    // Get submission details before deletion
    $subData = $db->query("SELECT student_id, status, points_earned FROM task_submissions WHERE id=$submissionId")->fetch_assoc();
    
    if ($subData) {
        // If submission was approved, deduct points
        if ($subData['status'] === 'approved' && $subData['points_earned']) {
            $studentId = $subData['student_id'];
            $points = $subData['points_earned'];
            $db->query("UPDATE internship_students SET total_points=GREATEST(0, total_points-$points) WHERE id=$studentId");
        }
        
        if ($db->query("DELETE FROM task_submissions WHERE id=$submissionId")) {
            $_SESSION['admin_success'] = 'Submission deleted successfully!';
            echo '<script>window.location.href="admin.php#tab-submitted-tasks";</script>';
            exit;
        } else {
            $error = 'Failed to delete submission: ' . $db->error;
        }
    }
}

// Get Filter Parameters
$statusFilter = $_GET['submission_status_filter'] ?? 'all';
$taskFilter = $_GET['task_filter'] ?? 'all';
$searchQuery = $_GET['submission_search'] ?? '';

// Build WHERE clause
$whereConditions = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "ts.status='$statusFilter'";
}

if ($taskFilter !== 'all' && is_numeric($taskFilter)) {
    $whereConditions[] = "ts.task_id=" . (int)$taskFilter;
}

if (!empty($searchQuery)) {
    $searchEsc = $db->real_escape_string($searchQuery);
    $whereConditions[] = "(s.full_name LIKE '%$searchEsc%' OR s.email LIKE '%$searchEsc%' OR t.task_title LIKE '%$searchEsc%')";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get submission counts for filter buttons
$allCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions")->fetch_assoc()['cnt'];
$submittedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='submitted'")->fetch_assoc()['cnt'];
$underReviewCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='under_review'")->fetch_assoc()['cnt'];
$approvedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='approved'")->fetch_assoc()['cnt'];
$rejectedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM task_submissions WHERE status='rejected'")->fetch_assoc()['cnt'];

// Get all tasks for filter dropdown
$tasksRes = $db->query("SELECT id, task_title FROM internship_tasks ORDER BY task_title");
$allTasks = [];
while ($row = $tasksRes->fetch_assoc()) $allTasks[] = $row;

// Get Submissions with JOIN
$submissionsRes = $db->query("SELECT ts.*,
    t.task_title,
    t.points as task_max_points,
    t.difficulty,
    s.full_name as student_name,
    s.email as student_email,
    s.domain_interest
    FROM task_submissions ts
    JOIN internship_tasks t ON ts.task_id=t.id
    JOIN internship_students s ON ts.student_id=s.id
    $whereClause
    ORDER BY ts.submitted_at DESC");

$submissions = [];
while ($row = $submissionsRes->fetch_assoc()) $submissions[] = $row;
?>

<style>
    .section{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:24px;}
    .section-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
    .sh-title{font-size:1.1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;}
    .sh-title i{color:var(--o5);}
    .section-body{padding:24px;}
    .btn{padding:10px 18px;border-radius:9px;font-size:.875rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .2s;}
    .btn-primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
    .btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
    .btn-secondary{background:var(--card);border:1.5px solid var(--border);color:var(--text2);}
    .btn-secondary:hover{border-color:var(--o5);color:var(--o5);}
    .btn-danger{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.3);color:#dc2626;}
    .btn-danger:hover{background:rgba(239,68,68,0.2);border-color:#dc2626;}
    .btn-success{background:rgba(34,197,94,0.1);border:1.5px solid rgba(34,197,94,0.3);color:#16a34a;}
    .btn-success:hover{background:rgba(34,197,94,0.2);border-color:#16a34a;}
    .btn-info{background:rgba(59,130,246,0.1);border:1.5px solid rgba(59,130,246,0.3);color:#2563eb;}
    .btn-info:hover{background:rgba(59,130,246,0.2);border-color:#2563eb;}
    .btn-sm{padding:6px 12px;font-size:.75rem;}
    .form-group{margin-bottom:18px;}
    .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
    .form-label .required{color:var(--red);}
    .form-input,.form-textarea,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
    .form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
    .form-textarea{resize:vertical;min-height:100px;}
    .table-responsive{overflow-x:auto;}
    .data-table{width:100%;border-collapse:collapse;}
    .data-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);white-space:nowrap;}
    .data-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--text2);}
    .data-table tr:hover{background:var(--bg);}
    .data-table td:first-child{font-weight:600;color:var(--text);}
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
    .badge-submitted{background:rgba(234,179,8,0.12);color:#854d0e;}
    .badge-under_review{background:rgba(59,130,246,0.12);color:#1d4ed8;}
    .badge-approved{background:rgba(34,197,94,0.12);color:#16a34a;}
    .badge-rejected{background:rgba(239,68,68,0.12);color:#dc2626;}
    .badge-easy{background:rgba(34,197,94,0.12);color:#16a34a;}
    .badge-medium{background:rgba(234,179,8,0.12);color:#854d0e;}
    .badge-hard{background:rgba(239,68,68,0.12);color:#dc2626;}
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
    .modal.active{display:flex;}
    .modal-content{background:var(--card);border-radius:16px;width:100%;max-width:800px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
    .modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .mh-title{font-size:1.2rem;font-weight:700;color:var(--text);}
    .modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:4px;transition:color .2s;}
    .modal-close:hover{color:var(--red);}
    .modal-body{padding:24px;}
    .modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}
    .empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
    .empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
    .empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
    .filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
    .filter-btn{padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.8rem;font-weight:500;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;}
    .filter-btn:hover{border-color:var(--o5);color:var(--o5);}
    .filter-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;}
    .search-box{flex:1;max-width:300px;}
    .search-box input{width:100%;padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;outline:none;}
    .search-box input:focus{border-color:var(--o5);}
    .task-select{min-width:200px;}
    .submission-details{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px;}
    .sd-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);}
    .sd-row:last-child{border-bottom:none;}
    .sd-label{font-weight:600;color:var(--text2);font-size:.82rem;}
    .sd-value{color:var(--text);font-size:.85rem;}
    .submission-content{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px;max-height:300px;overflow-y:auto;}
    .action-buttons{display:flex;gap:6px;flex-wrap:wrap;}
    .student-info{display:flex;align-items:center;gap:10px;}
    .student-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;}
    .alert{display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:20px;}
    .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
    @media(max-width:768px){.search-box{max-width:100%;}.task-select{min-width:100%;}}
</style>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-paper-plane"></i>All Submitted Tasks</div>
        <div style="font-size:.85rem;color:var(--text3);">
            <i class="fas fa-info-circle"></i> Total: <?php echo $allCount; ?> submissions
        </div>
    </div>
    <div class="section-body">
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="filter-bar">
            <a href="?submission_status_filter=all<?php echo !empty($taskFilter) && $taskFilter!=='all' ? '&task_filter='.$taskFilter : ''; ?><?php echo !empty($searchQuery) ? '&submission_search='.urlencode($searchQuery) : ''; ?>#tab-submitted-tasks" 
               class="filter-btn <?php echo $statusFilter==='all'?'active':''; ?>">
                All (<?php echo $allCount; ?>)
            </a>
            <a href="?submission_status_filter=submitted<?php echo !empty($taskFilter) && $taskFilter!=='all' ? '&task_filter='.$taskFilter : ''; ?><?php echo !empty($searchQuery) ? '&submission_search='.urlencode($searchQuery) : ''; ?>#tab-submitted-tasks" 
               class="filter-btn <?php echo $statusFilter==='submitted'?'active':''; ?>">
                Submitted (<?php echo $submittedCount; ?>)
            </a>
            <a href="?submission_status_filter=under_review<?php echo !empty($taskFilter) && $taskFilter!=='all' ? '&task_filter='.$taskFilter : ''; ?><?php echo !empty($searchQuery) ? '&submission_search='.urlencode($searchQuery) : ''; ?>#tab-submitted-tasks" 
               class="filter-btn <?php echo $statusFilter==='under_review'?'active':''; ?>">
                Under Review (<?php echo $underReviewCount; ?>)
            </a>
            <a href="?submission_status_filter=approved<?php echo !empty($taskFilter) && $taskFilter!=='all' ? '&task_filter='.$taskFilter : ''; ?><?php echo !empty($searchQuery) ? '&submission_search='.urlencode($searchQuery) : ''; ?>#tab-submitted-tasks" 
               class="filter-btn <?php echo $statusFilter==='approved'?'active':''; ?>">
                Approved (<?php echo $approvedCount; ?>)
            </a>
            <a href="?submission_status_filter=rejected<?php echo !empty($taskFilter) && $taskFilter!=='all' ? '&task_filter='.$taskFilter : ''; ?><?php echo !empty($searchQuery) ? '&submission_search='.urlencode($searchQuery) : ''; ?>#tab-submitted-tasks" 
               class="filter-btn <?php echo $statusFilter==='rejected'?'active':''; ?>">
                Rejected (<?php echo $rejectedCount; ?>)
            </a>
            
            <select class="form-select task-select" onchange="filterByTask(this.value)">
                <option value="all">All Tasks</option>
                <?php foreach ($allTasks as $task): ?>
                <option value="<?php echo $task['id']; ?>" <?php echo $taskFilter==(string)$task['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($task['task_title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <div class="search-box">
                <form method="GET" style="margin:0;">
                    <input type="hidden" name="submission_status_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <input type="hidden" name="task_filter" value="<?php echo htmlspecialchars($taskFilter); ?>">
                    <input type="text" name="submission_search" placeholder="Search submissions..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           onchange="this.form.submit()">
                </form>
            </div>
        </div>
        
        <?php if (empty($submissions)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No submissions found</h3>
            <p><?php echo !empty($searchQuery) ? 'Try a different search term' : 'No task submissions yet'; ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Task</th>
                        <th>Difficulty</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Points</th>
                        <th>Reviewed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <div class="student-avatar"><?php echo strtoupper(substr($sub['student_name'], 0, 2)); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($sub['student_name']); ?></strong>
                                    <br><small style="color:var(--text3);"><?php echo htmlspecialchars($sub['student_email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($sub['task_title']); ?></strong>
                            <?php if ($sub['domain_interest']): ?>
                            <br><small style="color:var(--text3);"><i class="fas fa-tag fa-xs"></i> <?php echo htmlspecialchars($sub['domain_interest']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $sub['difficulty']; ?>">
                                <?php echo ucfirst($sub['difficulty']); ?>
                            </span>
                        </td>
                        <td>
                            <small style="color:var(--text2);">
                                <i class="fas fa-clock fa-xs"></i>
                                <?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?>
                                <br><?php echo date('h:i A', strtotime($sub['submitted_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $sub['status']; ?>">
                                <i class="fas fa-circle fa-xs"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sub['points_earned']): ?>
                            <strong style="color:var(--green);"><?php echo $sub['points_earned']; ?></strong>
                            <?php else: ?>
                            <span style="color:var(--text3);">—</span>
                            <?php endif; ?>
                            / <?php echo $sub['task_max_points']; ?> pts
                        </td>
                        <td>
                            <?php if ($sub['reviewed_by']): ?>
                            <small style="color:var(--text2);">
                                <i class="fas fa-user-check fa-xs"></i> <?php echo htmlspecialchars($sub['reviewed_by']); ?>
                                <br><?php echo date('M d, Y', strtotime($sub['reviewed_at'])); ?>
                            </small>
                            <?php else: ?>
                            <span style="color:var(--text3);">Not reviewed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-info btn-sm" onclick='viewSubmission(<?php echo json_encode($sub); ?>)' title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-primary btn-sm" onclick='reviewSubmission(<?php echo json_encode($sub); ?>)' title="Review">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick='deleteSubmission(<?php echo $sub['id']; ?>, "<?php echo htmlspecialchars($sub['student_name']); ?>")' title="Delete">
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

<!-- View Submission Modal -->
<div id="viewSubmissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title"><i class="fas fa-eye"></i> Submission Details</div>
            <button class="modal-close" onclick="closeModal('viewSubmissionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="submission-details">
                <div class="sd-row">
                    <span class="sd-label">Student:</span>
                    <span class="sd-value" id="view_student_name"></span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Email:</span>
                    <span class="sd-value" id="view_student_email"></span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Task:</span>
                    <span class="sd-value" id="view_task_title"></span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Difficulty:</span>
                    <span class="sd-value" id="view_difficulty"></span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Max Points:</span>
                    <span class="sd-value" id="view_max_points"></span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Submitted At:</span>
                    <span class="sd-value" id="view_submitted_at"></span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Status:</span>
                    <span class="sd-value" id="view_status"></span>
                </div>
                <div class="sd-row" id="view_points_row" style="display:none;">
                    <span class="sd-label">Points Earned:</span>
                    <span class="sd-value" id="view_points_earned"></span>
                </div>
                <div class="sd-row" id="view_reviewed_row" style="display:none;">
                    <span class="sd-label">Reviewed By:</span>
                    <span class="sd-value" id="view_reviewed_by"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Submission Text:</label>
                <div class="submission-content" id="view_submission_text"></div>
            </div>
            
            <div class="form-group" id="view_url_group" style="display:none;">
                <label class="form-label">Submission URL:</label>
                <a href="#" id="view_submission_url" target="_blank" style="color:var(--o5);text-decoration:none;font-size:.85rem;">
                    <i class="fas fa-external-link-alt"></i> <span id="view_url_text"></span>
                </a>
            </div>
            
            <div class="form-group" id="view_github_group" style="display:none;">
                <label class="form-label">GitHub Link:</label>
                <a href="#" id="view_github_link" target="_blank" style="color:var(--o5);text-decoration:none;font-size:.85rem;">
                    <i class="fab fa-github"></i> <span id="view_github_text"></span>
                </a>
            </div>
            
            <div class="form-group" id="view_file_group" style="display:none;">
                <label class="form-label">Uploaded File:</label>
                <div style="padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;">
                    <i class="fas fa-file"></i> <span id="view_file_name"></span>
                </div>
            </div>
            
            <div class="form-group" id="view_feedback_group" style="display:none;">
                <label class="form-label">Admin Feedback:</label>
                <div class="submission-content" id="view_feedback"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewSubmissionModal')">Close</button>
        </div>
    </div>
</div>

<!-- Review Submission Modal -->
<div id="reviewSubmissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title"><i class="fas fa-clipboard-check"></i> Review Submission</div>
            <button class="modal-close" onclick="closeModal('reviewSubmissionModal')">&times;</button>
        </div>
        <form method="POST" id="reviewSubmissionForm">
            <div class="modal-body">
                <input type="hidden" name="submission_id" id="review_submission_id">
                
                <div style="padding:14px;background:var(--o1);border:1px solid var(--o2);border-radius:10px;margin-bottom:18px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                        <div class="student-avatar" id="review_avatar"></div>
                        <div>
                            <strong id="review_student_name"></strong>
                            <br><small style="color:var(--text3);" id="review_student_email"></small>
                        </div>
                    </div>
                    <div style="font-size:.82rem;color:var(--text2);margin-top:10px;">
                        <strong>Task:</strong> <span id="review_task_title"></span> 
                        (<span id="review_max_points"></span> points max)
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Submission Preview:</label>
                    <div class="submission-content" id="review_submission_preview"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status <span class="required">*</span></label>
                    <select name="status" id="review_status" class="form-select" required onchange="togglePointsField()">
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group" id="points_group" style="display:none;">
                    <label class="form-label">Points Earned <span class="required">*</span></label>
                    <input type="number" name="points_earned" id="review_points_earned" class="form-input" min="0" step="1">
                    <small style="font-size:.75rem;color:var(--text3);margin-top:5px;display:block;">
                        Max points for this task: <strong id="review_points_max"></strong>
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Feedback</label>
                    <textarea name="feedback" id="review_feedback" class="form-textarea" 
                              placeholder="Provide feedback to the student..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reviewSubmissionModal')">Cancel</button>
                <button type="submit" name="update_submission_status" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Submission Form (Hidden) -->
<form method="POST" id="deleteSubmissionForm" style="display:none;">
    <input type="hidden" name="submission_id" id="delete_submission_id">
    <input type="hidden" name="delete_submission" value="1">
</form>

<script>
    function closeModal(id){
        document.getElementById(id).classList.remove('active');
    }
    
    function viewSubmission(sub){
        document.getElementById('view_student_name').textContent=sub.student_name;
        document.getElementById('view_student_email').textContent=sub.student_email;
        document.getElementById('view_task_title').textContent=sub.task_title;
        document.getElementById('view_difficulty').innerHTML=`<span class="badge badge-${sub.difficulty}">${sub.difficulty.charAt(0).toUpperCase()+sub.difficulty.slice(1)}</span>`;
        document.getElementById('view_max_points').textContent=sub.task_max_points + ' points';
        document.getElementById('view_submitted_at').textContent=new Date(sub.submitted_at).toLocaleString();
        document.getElementById('view_status').innerHTML=`<span class="badge badge-${sub.status}">${sub.status.replace('_',' ').toUpperCase()}</span>`;
        
        // Submission text
        document.getElementById('view_submission_text').textContent=sub.submission_text || 'No text provided';
        
        // URL
        if(sub.submission_url){
            document.getElementById('view_url_group').style.display='block';
            document.getElementById('view_submission_url').href=sub.submission_url;
            document.getElementById('view_url_text').textContent=sub.submission_url;
        }else{
            document.getElementById('view_url_group').style.display='none';
        }
        
        // GitHub
        if(sub.github_link){
            document.getElementById('view_github_group').style.display='block';
            document.getElementById('view_github_link').href=sub.github_link;
            document.getElementById('view_github_text').textContent=sub.github_link;
        }else{
            document.getElementById('view_github_group').style.display='none';
        }
        
        // File
        if(sub.file_name){
            document.getElementById('view_file_group').style.display='block';
            document.getElementById('view_file_name').textContent=sub.file_name;
        }else{
            document.getElementById('view_file_group').style.display='none';
        }
        
        // Points earned
        if(sub.points_earned){
            document.getElementById('view_points_row').style.display='flex';
            document.getElementById('view_points_earned').innerHTML=`<strong style="color:var(--green);">${sub.points_earned}</strong> / ${sub.task_max_points} points`;
        }else{
            document.getElementById('view_points_row').style.display='none';
        }
        
        // Reviewed by
        if(sub.reviewed_by){
            document.getElementById('view_reviewed_row').style.display='flex';
            document.getElementById('view_reviewed_by').textContent=`${sub.reviewed_by} on ${new Date(sub.reviewed_at).toLocaleDateString()}`;
        }else{
            document.getElementById('view_reviewed_row').style.display='none';
        }
        
        // Feedback
        if(sub.feedback){
            document.getElementById('view_feedback_group').style.display='block';
            document.getElementById('view_feedback').textContent=sub.feedback;
        }else{
            document.getElementById('view_feedback_group').style.display='none';
        }
        
        document.getElementById('viewSubmissionModal').classList.add('active');
    }
    
    function reviewSubmission(sub){
        document.getElementById('review_submission_id').value=sub.id;
        document.getElementById('review_avatar').textContent=sub.student_name.substring(0,2).toUpperCase();
        document.getElementById('review_student_name').textContent=sub.student_name;
        document.getElementById('review_student_email').textContent=sub.student_email;
        document.getElementById('review_task_title').textContent=sub.task_title;
        document.getElementById('review_max_points').textContent=sub.task_max_points;
        document.getElementById('review_points_max').textContent=sub.task_max_points + ' points';
        
        let preview = '';
        if(sub.submission_text) preview += sub.submission_text.substring(0,200) + (sub.submission_text.length>200?'...':'');
        if(sub.submission_url) preview += '\n\nURL: ' + sub.submission_url;
        if(sub.github_link) preview += '\n\nGitHub: ' + sub.github_link;
        if(sub.file_name) preview += '\n\nFile: ' + sub.file_name;
        document.getElementById('review_submission_preview').textContent=preview || 'No content preview available';
        
        document.getElementById('review_status').value=sub.status;
        document.getElementById('review_points_earned').value=sub.points_earned||'';
        document.getElementById('review_points_earned').max=sub.task_max_points;
        document.getElementById('review_feedback').value=sub.feedback||'';
        
        togglePointsField();
        
        document.getElementById('reviewSubmissionModal').classList.add('active');
    }
    
    function togglePointsField(){
        const status=document.getElementById('review_status').value;
        const pointsGroup=document.getElementById('points_group');
        const pointsInput=document.getElementById('review_points_earned');
        
        if(status==='approved'){
            pointsGroup.style.display='block';
            pointsInput.required=true;
        }else{
            pointsGroup.style.display='none';
            pointsInput.required=false;
        }
    }
    
    function deleteSubmission(submissionId, studentName){
        if(confirm(`Are you sure you want to delete this submission by ${studentName}?\n\nThis action cannot be undone!`)){
            document.getElementById('delete_submission_id').value=submissionId;
            document.getElementById('deleteSubmissionForm').submit();
        }
    }
    
    function filterByTask(taskId){
        const currentUrl=new URL(window.location.href);
        currentUrl.searchParams.set('task_filter', taskId);
        currentUrl.hash='tab-submitted-tasks';
        window.location.href=currentUrl.toString();
    }
    
    document.querySelectorAll('.modal').forEach(modal=>{
        modal.addEventListener('click',function(e){
            if(e.target===this){
                this.classList.remove('active');
            }
        });
    });
</script>