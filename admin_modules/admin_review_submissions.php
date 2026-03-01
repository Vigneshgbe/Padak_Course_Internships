<?php
// Admin Review Submissions Module
// This file handles submission reviews and approvals

// Handle Submission Review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submission'])) {
    $subId = (int)$_POST['submission_id'];
    $reviewStatus = $_POST['review_status'];
    $pointsEarned = isset($_POST['points_earned']) ? (int)$_POST['points_earned'] : null;
    $feedback = trim($_POST['feedback'] ?? '');
    
    $feedbackEsc = $db->real_escape_string($feedback);
    $pointsValue = $pointsEarned !== null ? $pointsEarned : 'NULL';
    
    $sql = "UPDATE task_submissions SET
            status='$reviewStatus',
            points_earned=$pointsValue,
            feedback='$feedbackEsc',
            reviewed_at=NOW(),
            reviewed_by='Admin'
            WHERE id=$subId";
    
    if ($db->query($sql)) {
        $subData = $db->query("SELECT student_id, task_id FROM task_submissions WHERE id=$subId")->fetch_assoc();
        
        if ($subData) {
            $studentId = $subData['student_id'];
            $taskId = $subData['task_id'];
            $taskData = $db->query("SELECT title FROM internship_tasks WHERE id=$taskId")->fetch_assoc();
            $taskTitle = $db->real_escape_string($taskData['title'] ?? 'Your task');
            
            if ($reviewStatus === 'approved' && $pointsEarned !== null && $pointsEarned > 0) {
                $reasonEsc = $db->real_escape_string("Earned from task: $taskTitle");
                
                $db->query("INSERT INTO student_points_log (student_id, points, reason, task_id, awarded_at)
                           VALUES ($studentId, $pointsEarned, '$reasonEsc', $taskId, NOW())");
                
                $totalPointsResult = $db->query("SELECT SUM(points) as total FROM student_points_log WHERE student_id=$studentId");
                $totalPoints = $totalPointsResult ? (int)$totalPointsResult->fetch_assoc()['total'] : 0;
                
                $db->query("UPDATE internship_students SET total_points=$totalPoints WHERE id=$studentId");
                
                $success = "Submission approved! $pointsEarned points awarded to student.";
            } else {
                $success = 'Submission reviewed successfully!';
            }
            
            $notifMsg = $reviewStatus === 'approved' ? 
                "Your submission for \"$taskTitle\" has been approved! You earned $pointsEarned points." :
                "Your submission for \"$taskTitle\" requires revision. Check feedback.";
            $notifMsgEsc = $db->real_escape_string($notifMsg);
            
            $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                       VALUES ($studentId, 'Submission Reviewed', '$notifMsgEsc', 'task', NOW())");
        }
    } else {
        $error = 'Failed to review submission';
    }
}

// Get Pending Submissions
$pendingSubsRes = $db->query("SELECT ts.*, t.title as task_title, t.max_points, s.full_name as student_name, s.email as student_email
    FROM task_submissions ts
    JOIN internship_tasks t ON t.id = ts.task_id
    JOIN internship_students s ON s.id = ts.student_id
    WHERE ts.status IN ('submitted', 'under_review')
    ORDER BY ts.submitted_at DESC
    LIMIT 50");
$pendingSubs = [];
while ($row = $pendingSubsRes->fetch_assoc()) $pendingSubs[] = $row;
?>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-clipboard-check"></i>Pending Submissions</div>
    </div>
    <div class="section-body">
        <?php if (empty($pendingSubs)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>No pending submissions</h3>
            <p>All submissions have been reviewed!</p>
        </div>
        <?php else: ?>
        
        <?php foreach ($pendingSubs as $sub): ?>
        <div class="sub-card">
            <div class="sub-header">
                <div>
                    <div class="sub-title"><?php echo htmlspecialchars($sub['task_title']); ?></div>
                    <div class="sub-meta">
                        <span class="sub-meta-item"><i class="fas fa-user"></i><?php echo htmlspecialchars($sub['student_name']); ?></span>
                        <span class="sub-meta-item"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($sub['student_email']); ?></span>
                        <span class="sub-meta-item"><i class="fas fa-clock"></i><?php echo date('M d, Y g:i A', strtotime($sub['submitted_at'])); ?></span>
                        <span class="sub-meta-item"><i class="fas fa-star"></i>Max: <?php echo $sub['max_points']; ?> pts</span>
                    </div>
                </div>
                <span class="badge badge-<?php echo $sub['status']; ?>">
                    <?php echo $sub['status']==='under_review'?'In Review':'Submitted'; ?>
                </span>
            </div>
            
            <?php if ($sub['submission_text']): ?>
            <div class="sub-content">
                <strong>Description:</strong><br>
                <?php echo nl2br(htmlspecialchars(substr($sub['submission_text'], 0, 300))); ?>
                <?php if (strlen($sub['submission_text']) > 300) echo '...'; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($sub['github_link']): ?>
            <div style="margin-bottom:10px;">
                <strong style="font-size:.82rem;">GitHub:</strong> 
                <a href="<?php echo htmlspecialchars($sub['github_link']); ?>" target="_blank" style="color:var(--blue);font-size:.82rem;word-break:break-all;">
                    <i class="fab fa-github"></i> <?php echo htmlspecialchars($sub['github_link']); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($sub['submission_url']): ?>
            <div style="margin-bottom:10px;">
                <strong style="font-size:.82rem;">Live URL:</strong> 
                <a href="<?php echo htmlspecialchars($sub['submission_url']); ?>" target="_blank" style="color:var(--blue);font-size:.82rem;word-break:break-all;">
                    <i class="fas fa-globe"></i> <?php echo htmlspecialchars($sub['submission_url']); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($sub['file_name']): ?>
            <div style="margin-bottom:10px;">
                <strong style="font-size:.82rem;">File:</strong> 
                <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" download style="color:var(--blue);font-size:.82rem;">
                    <i class="fas fa-download"></i> <?php echo htmlspecialchars($sub['file_name']); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="sub-actions">
                <button class="btn btn-primary btn-sm" onclick='reviewSubmission(<?php echo json_encode($sub); ?>)'>
                    <i class="fas fa-clipboard-check"></i> Review
                </button>
                <button class="btn btn-secondary btn-sm" onclick='viewFullSubmission(<?php echo json_encode($sub); ?>)'>
                    <i class="fas fa-eye"></i> View Details
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title">Review Submission</div>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="submission_id" id="review_sub_id">
                
                <div id="reviewTaskInfo" style="padding:14px;background:var(--bg);border-radius:10px;margin-bottom:18px;"></div>
                
                <div class="form-group">
                    <label class="form-label">Review Status <span class="required">*</span></label>
                    <select name="review_status" id="review_status" class="form-select" required>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approve</option>
                        <option value="revision_requested">Request Revision</option>
                        <option value="rejected">Reject</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Points Earned</label>
                    <input type="number" name="points_earned" id="review_points" class="form-input" min="0" placeholder="Leave blank if not approving">
                    <div class="form-hint">Required when approving the submission</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Feedback</label>
                    <textarea name="feedback" id="review_feedback" class="form-textarea" placeholder="Provide constructive feedback to the student..." style="min-height:120px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
                <button type="submit" name="review_submission" class="btn btn-primary">
                    <i class="fas fa-check"></i> Submit Review
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title">Submission Details</div>
            <button class="modal-close" onclick="closeModal('viewDetailsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="fullSubmissionContent"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewDetailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
function reviewSubmission(sub) {
    document.getElementById('review_sub_id').value = sub.id;
    document.getElementById('review_points').max = sub.max_points;
    document.getElementById('review_points').placeholder = 'Max: ' + sub.max_points + ' points';
    
    const info = `<strong style="font-size:.95rem;">${sub.task_title}</strong><br>
        <div style="margin-top:8px;font-size:.8rem;color:var(--text3);">
            <i class="fas fa-user"></i> ${sub.student_name} &nbsp;•&nbsp;
            <i class="fas fa-star"></i> Max Points: ${sub.max_points}
        </div>`;
    document.getElementById('reviewTaskInfo').innerHTML = info;
    document.getElementById('reviewModal').classList.add('active');
}

function viewFullSubmission(sub) {
    let content = '<div style="line-height:1.8;">';
    content += '<h3 style="color:var(--o5);margin-bottom:16px;font-size:1.2rem;"><i class="fas fa-clipboard-list"></i> ' + sub.task_title + '</h3>';
    
    content += '<div style="background:var(--bg);padding:14px;border-radius:8px;margin-bottom:16px;">';
    content += '<strong style="color:var(--text);"><i class="fas fa-user"></i> Student:</strong> ' + sub.student_name + ' <span style="color:var(--text3);">(' + sub.student_email + ')</span><br>';
    content += '<strong style="color:var(--text);"><i class="fas fa-clock"></i> Submitted:</strong> ' + sub.submitted_at + '<br>';
    content += '<strong style="color:var(--text);"><i class="fas fa-star"></i> Max Points:</strong> ' + sub.max_points + ' pts<br>';
    content += '<strong style="color:var(--text);"><i class="fas fa-info-circle"></i> Status:</strong> <span class="badge badge-' + sub.status + '">' + (sub.status === 'under_review' ? 'In Review' : 'Submitted') + '</span>';
    content += '</div>';
    
    if (sub.submission_text) {
        content += '<div style="margin-bottom:16px;">';
        content += '<strong style="color:var(--text);display:block;margin-bottom:8px;"><i class="fas fa-align-left"></i> Description:</strong>';
        content += '<div style="background:var(--bg);padding:12px;border-radius:8px;white-space:pre-wrap;color:var(--text2);font-size:.9rem;line-height:1.6;">' + sub.submission_text + '</div>';
        content += '</div>';
    }
    
    if (sub.github_link) {
        content += '<div style="margin-bottom:12px;">';
        content += '<strong style="color:var(--text);"><i class="fab fa-github"></i> GitHub:</strong><br>';
        content += '<a href="' + sub.github_link + '" target="_blank" style="color:var(--blue);word-break:break-all;">' + sub.github_link + ' <i class="fas fa-external-link-alt fa-xs"></i></a>';
        content += '</div>';
    }
    
    if (sub.submission_url) {
        content += '<div style="margin-bottom:12px;">';
        content += '<strong style="color:var(--text);"><i class="fas fa-globe"></i> Live URL:</strong><br>';
        content += '<a href="' + sub.submission_url + '" target="_blank" style="color:var(--blue);word-break:break-all;">' + sub.submission_url + ' <i class="fas fa-external-link-alt fa-xs"></i></a>';
        content += '</div>';
    }
    
    if (sub.file_name) {
        content += '<div style="margin-bottom:12px;">';
        content += '<strong style="color:var(--text);"><i class="fas fa-file"></i> Attached File:</strong><br>';
        content += '<a href="' + sub.file_path + '" download style="color:var(--blue);"><i class="fas fa-download"></i> ' + sub.file_name + '</a>';
        content += '</div>';
    }
    
    content += '</div>';
    
    document.getElementById('fullSubmissionContent').innerHTML = content;
    document.getElementById('viewDetailsModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>