<?php
// admin_review_submissions.php 

// Get Pending Submissions
$pendingSubsRes = $db->query("SELECT ts.*, t.title as task_title, t.max_points, s.full_name as student_name, s.email as student_email
    FROM task_submissions ts
    JOIN internship_tasks t ON t.id = ts.task_id
    JOIN internship_students s ON s.id = ts.student_id
    WHERE ts.status IN ('submitted', 'under_review')
    ORDER BY ts.submitted_at DESC
    LIMIT 10");
$pendingSubs = [];
while ($row = $pendingSubsRes->fetch_assoc()) $pendingSubs[] = $row;
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
    .btn-sm{padding:6px 12px;font-size:.75rem;}
    .form-group{margin-bottom:18px;}
    .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
    .form-label .required{color:var(--red);}
    .form-input,.form-textarea,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
    .form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
    .form-textarea{resize:vertical;min-height:100px;}
    .form-hint{font-size:.73rem;color:var(--text3);margin-top:5px;}
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
    .badge-submitted{background:rgba(59,130,246,0.12);color:#1d4ed8;}
    .badge-under_review{background:rgba(139,92,246,0.12);color:#6d28d9;}
    .badge-approved{background:rgba(34,197,94,0.12);color:#16a34a;}
    .rev-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
    .rev-modal.active{display:flex;}
    .rev-modal-content{background:var(--card);border-radius:16px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
    .rev-modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .rev-mh-title{font-size:1.2rem;font-weight:700;color:var(--text);}
    .rev-modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:4px 8px;transition:color .2s;line-height:1;}
    .rev-modal-close:hover{color:var(--red);}
    .rev-modal-body{padding:24px;}
    .rev-modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}
    .empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
    .empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
    .empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
    .sub-card{border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:16px;transition:all .2s;}
    .sub-card:hover{box-shadow:0 4px 16px rgba(0,0,0,0.08);}
    .sub-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}
    .sub-title{font-size:.95rem;font-weight:700;color:var(--text);}
    .sub-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:.75rem;color:var(--text3);margin-bottom:12px;}
    .sub-meta-item{display:flex;align-items:center;gap:4px;}
    .sub-content{font-size:.82rem;color:var(--text2);line-height:1.6;margin-bottom:12px;background:var(--bg);padding:12px;border-radius:8px;}
    .sub-actions{display:flex;gap:8px;flex-wrap:wrap;}
</style>

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
                    <button type="button" class="btn btn-primary btn-sm" onclick="revOpenReview(<?php echo (int)$sub['id']; ?>, <?php echo (int)$sub['max_points']; ?>, '<?php echo htmlspecialchars(addslashes($sub['task_title'])); ?>', '<?php echo htmlspecialchars(addslashes($sub['student_name'])); ?>')">
                        <i class="fas fa-clipboard-check"></i> Review
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick='revViewDetails(<?php echo json_encode([
                        'id'              => (int)$sub['id'],
                        'task_title'      => $sub['task_title'],
                        'student_name'    => $sub['student_name'],
                        'student_email'   => $sub['student_email'],
                        'submitted_at'    => $sub['submitted_at'],
                        'max_points'      => (int)$sub['max_points'],
                        'status'          => $sub['status'],
                        'submission_text' => $sub['submission_text'] ?? '',
                        'github_link'     => $sub['github_link'] ?? '',
                        'submission_url'  => $sub['submission_url'] ?? '',
                        'file_name'       => $sub['file_name'] ?? '',
                        'file_path'       => $sub['file_path'] ?? '',
                    ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<div id="revReviewModal" class="rev-modal" role="dialog" aria-modal="true">
    <div class="rev-modal-content">
        <div class="rev-modal-header">
            <div class="rev-mh-title">Review Submission</div>
            <button type="button" class="rev-modal-close" id="revReviewCloseBtn">&times;</button>
        </div>
        <form method="POST" action="admin.php" id="revReviewForm">
            <div class="rev-modal-body">
                <input type="hidden" name="review_submission" value="1">
                <input type="hidden" name="submission_id" id="rev_sub_id">
                <div id="revTaskInfo" style="padding:14px;background:var(--bg);border-radius:10px;margin-bottom:18px;"></div>

                <div class="form-group">
                    <label class="form-label">Review Status <span class="required">*</span></label>
                    <select name="review_status" id="rev_status" class="form-select" required>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approve</option>
                        <option value="revision_requested">Request Revision</option>
                        <option value="rejected">Reject</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Points Earned</label>
                    <input type="number" name="points_earned" id="rev_points" class="form-input" min="0" placeholder="Leave blank if not approving">
                    <div class="form-hint">Required when approving the submission</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Feedback</label>
                    <textarea name="feedback" id="rev_feedback" class="form-textarea" placeholder="Provide constructive feedback..." style="min-height:120px;"></textarea>
                </div>
            </div>
            <div class="rev-modal-footer">
                <button type="button" class="btn btn-secondary" id="revReviewCancelBtn">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Submit Review
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<div id="revDetailsModal" class="rev-modal" role="dialog" aria-modal="true">
    <div class="rev-modal-content">
        <div class="rev-modal-header">
            <div class="rev-mh-title">Submission Details</div>
            <button type="button" class="rev-modal-close" id="revDetailsCloseBtn">&times;</button>
        </div>
        <div class="rev-modal-body">
            <div id="revDetailsContent"></div>
        </div>
        <div class="rev-modal-footer">
            <button type="button" class="btn btn-secondary" id="revDetailsCancelBtn">Close</button>
        </div>
    </div>
</div>

<script>
(function() {
    function revClose(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    window.revOpenReview = function(subId, maxPoints, taskTitle, studentName) {
        document.getElementById('rev_sub_id').value = subId;
        document.getElementById('rev_points').max = maxPoints;
        document.getElementById('rev_points').placeholder = 'Max: ' + maxPoints + ' points';
        document.getElementById('rev_feedback').value = '';
        document.getElementById('rev_status').value = 'under_review';
        document.getElementById('revTaskInfo').innerHTML =
            '<strong style="font-size:.95rem;">' + taskTitle + '</strong>' +
            '<div style="margin-top:8px;font-size:.8rem;color:var(--text3);"><i class="fas fa-user"></i> ' + studentName +
            ' &nbsp;&bull;&nbsp; <i class="fas fa-star"></i> Max Points: ' + maxPoints + '</div>';
        document.getElementById('revReviewModal').classList.add('active');
    };

    window.revViewDetails = function(sub) {
        var c = '<div style="line-height:1.8;">';
        c += '<h3 style="color:var(--o5);margin-bottom:16px;font-size:1.2rem;"><i class="fas fa-clipboard-list"></i> ' + sub.task_title + '</h3>';
        c += '<div style="background:var(--bg);padding:14px;border-radius:8px;margin-bottom:16px;">';
        c += '<strong><i class="fas fa-user"></i> Student:</strong> ' + sub.student_name + ' <span style="color:var(--text3);">(' + sub.student_email + ')</span><br>';
        c += '<strong><i class="fas fa-clock"></i> Submitted:</strong> ' + sub.submitted_at + '<br>';
        c += '<strong><i class="fas fa-star"></i> Max Points:</strong> ' + sub.max_points + ' pts<br>';
        c += '<strong><i class="fas fa-info-circle"></i> Status:</strong> ' + sub.status;
        c += '</div>';
        if (sub.submission_text) {
            c += '<div style="margin-bottom:16px;"><strong style="display:block;margin-bottom:8px;"><i class="fas fa-align-left"></i> Description:</strong>';
            c += '<div style="background:var(--bg);padding:12px;border-radius:8px;white-space:pre-wrap;color:var(--text2);font-size:.9rem;line-height:1.6;">' + sub.submission_text + '</div></div>';
        }
        if (sub.github_link) {
            c += '<div style="margin-bottom:12px;"><strong><i class="fab fa-github"></i> GitHub:</strong><br>';
            c += '<a href="' + sub.github_link + '" target="_blank" style="color:var(--blue);word-break:break-all;">' + sub.github_link + '</a></div>';
        }
        if (sub.submission_url) {
            c += '<div style="margin-bottom:12px;"><strong><i class="fas fa-globe"></i> Live URL:</strong><br>';
            c += '<a href="' + sub.submission_url + '" target="_blank" style="color:var(--blue);word-break:break-all;">' + sub.submission_url + '</a></div>';
        }
        if (sub.file_name) {
            c += '<div style="margin-bottom:12px;"><strong><i class="fas fa-file"></i> File:</strong><br>';
            c += '<a href="' + sub.file_path + '" download style="color:var(--blue);"><i class="fas fa-download"></i> ' + sub.file_name + '</a></div>';
        }
        c += '</div>';
        document.getElementById('revDetailsContent').innerHTML = c;
        document.getElementById('revDetailsModal').classList.add('active');
    };

    document.getElementById('revReviewCloseBtn').addEventListener('click', function() { revClose('revReviewModal'); });
    document.getElementById('revReviewCancelBtn').addEventListener('click', function() { revClose('revReviewModal'); });
    document.getElementById('revDetailsCloseBtn').addEventListener('click', function() { revClose('revDetailsModal'); });
    document.getElementById('revDetailsCancelBtn').addEventListener('click', function() { revClose('revDetailsModal'); });

    document.getElementById('revReviewModal').addEventListener('click', function(e) { if (e.target === this) revClose('revReviewModal'); });
    document.getElementById('revDetailsModal').addEventListener('click', function(e) { if (e.target === this) revClose('revDetailsModal'); });
})();
</script>