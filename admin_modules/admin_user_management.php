<?php
// admin_user_management.php - User Management Module
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $studentId = (int)$_POST['student_id'];
    $newPassword = trim($_POST['new_password'] ?? '');
    
    if (empty($newPassword)) {
        $error = 'New password is required';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $passwordEsc = $db->real_escape_string($hashedPassword);
        
        $sql = "UPDATE internship_students SET password='$passwordEsc', updated_at=NOW() WHERE id=$studentId";
        
        if ($db->query($sql)) {
            $studentData = $db->query("SELECT full_name, email FROM internship_students WHERE id=$studentId")->fetch_assoc();
            
            // Send notification to student
            if ($studentData) {
                $notifMsg = "Your password has been reset by an administrator. Please log in with your new credentials.";
                $notifMsgEsc = $db->real_escape_string($notifMsg);
                $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                           VALUES ($studentId, 'Password Reset', '$notifMsgEsc', 'system', NOW())");
            }
            
            $_SESSION['admin_success'] = 'Password reset successfully!';
            echo '<script>window.location.href="admin.php#tab-users";</script>';
            exit;
        } else {
            $error = 'Failed to reset password: ' . $db->error;
        }
    }
}

// Handle User Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $studentId = (int)$_POST['student_id'];
    $isActive = (int)$_POST['is_active'];
    
    $sql = "UPDATE internship_students SET is_active=$isActive, updated_at=NOW() WHERE id=$studentId";
    
    if ($db->query($sql)) {
        $statusText = $isActive ? 'activated' : 'deactivated';
        $_SESSION['admin_success'] = "User $statusText successfully!";
        echo '<script>window.location.href="admin.php#tab-users";</script>';
        exit;
    } else {
        $error = 'Failed to update user status: ' . $db->error;
    }
}

// Handle User Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $studentId = (int)$_POST['student_id'];
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $collegeName = trim($_POST['college_name'] ?? '');
    $degree = trim($_POST['degree'] ?? '');
    $yearOfStudy = trim($_POST['year_of_study'] ?? '');
    $domainInterest = trim($_POST['domain_interest'] ?? '');
    $internshipStatus = trim($_POST['internship_status'] ?? 'active');
    
    if (empty($fullName) || empty($email)) {
        $error = 'Name and email are required';
    } else {
        $fullNameEsc = $db->real_escape_string($fullName);
        $emailEsc = $db->real_escape_string($email);
        $phoneEsc = $db->real_escape_string($phone);
        $collegeNameEsc = $db->real_escape_string($collegeName);
        $degreeEsc = $db->real_escape_string($degree);
        $yearOfStudyEsc = $db->real_escape_string($yearOfStudy);
        $domainInterestEsc = $db->real_escape_string($domainInterest);
        
        // Check if email already exists for another user
        $emailCheck = $db->query("SELECT id FROM internship_students WHERE email='$emailEsc' AND id != $studentId");
        if ($emailCheck->num_rows > 0) {
            $error = 'Email already exists for another user';
        } else {
            $sql = "UPDATE internship_students SET
                    full_name='$fullNameEsc',
                    email='$emailEsc',
                    phone='$phoneEsc',
                    college_name='$collegeNameEsc',
                    degree='$degreeEsc',
                    year_of_study='$yearOfStudyEsc',
                    domain_interest='$domainInterestEsc',
                    internship_status='$internshipStatus',
                    updated_at=NOW()
                    WHERE id=$studentId";
            
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'User updated successfully!';
                echo '<script>window.location.href="admin.php#tab-users";</script>';
                exit;
            } else {
                $error = 'Failed to update user: ' . $db->error;
            }
        }
    }
}

// Get Filter Status
$filterStatus = $_GET['status_filter'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = [];
if ($filterStatus === 'active') {
    $whereConditions[] = "is_active=1";
} elseif ($filterStatus === 'inactive') {
    $whereConditions[] = "is_active=0";
}

if (!empty($searchQuery)) {
    $searchEsc = $db->real_escape_string($searchQuery);
    $whereConditions[] = "(full_name LIKE '%$searchEsc%' OR email LIKE '%$searchEsc%' OR domain_interest LIKE '%$searchEsc%')";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get counts for filter buttons
$allCount = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_students")->fetch_assoc()['cnt'];
$activeCount = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_students WHERE is_active=1")->fetch_assoc()['cnt'];
$inactiveCount = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_students WHERE is_active=0")->fetch_assoc()['cnt'];

// Get Users
$usersRes = $db->query("SELECT s.*,
    (SELECT COUNT(*) FROM task_submissions WHERE student_id=s.id) as total_submissions,
    (SELECT COUNT(*) FROM task_submissions WHERE student_id=s.id AND status='approved') as approved_submissions
    FROM internship_students s
    $whereClause
    ORDER BY s.created_at DESC");
$users = [];
while ($row = $usersRes->fetch_assoc()) $users[] = $row;
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
    .btn-sm{padding:6px 12px;font-size:.75rem;}
    .form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
    .form-group{margin-bottom:18px;}
    .form-group.full{grid-column:1/-1;}
    .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
    .form-label .required{color:var(--red);}
    .form-input,.form-textarea,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
    .form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
    .table-responsive{overflow-x:auto;}
    .data-table{width:100%;border-collapse:collapse;}
    .data-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);}
    .data-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--text2);}
    .data-table tr:hover{background:var(--bg);}
    .data-table td:first-child{font-weight:600;color:var(--text);}
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
    .badge-active{background:rgba(34,197,94,0.12);color:#16a34a;}
    .badge-inactive{background:rgba(239,68,68,0.12);color:#dc2626;}
    .badge-pending{background:rgba(234,179,8,0.12);color:#854d0e;}
    .badge-completed{background:rgba(59,130,246,0.12);color:#1d4ed8;}
    .badge-withdrawn{background:rgba(100,116,139,0.12);color:#475569;}
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
    .modal.active{display:flex;}
    .modal-content{background:var(--card);border-radius:16px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
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
    .user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;}
    .user-info{display:flex;align-items:center;gap:12px;}
    .action-buttons{display:flex;gap:6px;flex-wrap:wrap;}
    @media(max-width:768px){.form-grid{grid-template-columns:1fr;}.search-box{max-width:100%;}}
</style>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-users"></i>User Management</div>
    </div>
    <div class="section-body">
        <?php if ($error): ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:20px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
            <i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="filter-bar">
            <a href="?status_filter=all<?php echo !empty($searchQuery) ? '&search='.urlencode($searchQuery) : ''; ?>#tab-users" class="filter-btn <?php echo $filterStatus==='all'?'active':''; ?>">All Users (<?php echo $allCount; ?>)</a>
            <a href="?status_filter=active<?php echo !empty($searchQuery) ? '&search='.urlencode($searchQuery) : ''; ?>#tab-users" class="filter-btn <?php echo $filterStatus==='active'?'active':''; ?>">Active (<?php echo $activeCount; ?>)</a>
            <a href="?status_filter=inactive<?php echo !empty($searchQuery) ? '&search='.urlencode($searchQuery) : ''; ?>#tab-users" class="filter-btn <?php echo $filterStatus==='inactive'?'active':''; ?>">Inactive (<?php echo $inactiveCount; ?>)</a>
            
            <div class="search-box">
                <form method="GET" style="margin:0;">
                    <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($filterStatus); ?>">
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($searchQuery); ?>" onchange="this.form.submit()">
                </form>
            </div>
        </div>
        
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No users found</h3>
            <p><?php echo !empty($searchQuery) ? 'Try a different search term' : 'No users registered yet'; ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>College</th>
                        <th>Domain</th>
                        <th>Points</th>
                        <th>Submissions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 2)); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    <br><small style="color:var(--text3);"><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($user['phone']): ?>
                            <i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($user['phone']); ?>
                            <?php else: ?>
                            <span style="color:var(--text3);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['college_name']): ?>
                            <?php echo htmlspecialchars($user['college_name']); ?>
                            <br><small style="color:var(--text3);"><?php echo htmlspecialchars($user['degree'] ?: '—'); ?></small>
                            <?php else: ?>
                            <span style="color:var(--text3);">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['domain_interest'] ?: '—'); ?></td>
                        <td><strong style="color:var(--o5);"><?php echo $user['total_points']; ?></strong> pts</td>
                        <td>
                            <?php if ($user['total_submissions'] > 0): ?>
                            <?php echo $user['approved_submissions']; ?>/<?php echo $user['total_submissions']; ?> approved
                            <?php else: ?>
                            <span style="color:var(--text3);">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <br>
                            <span class="badge badge-<?php echo $user['internship_status']; ?>" style="margin-top:4px;">
                                <?php echo ucfirst($user['internship_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-secondary btn-sm" onclick='editUser(<?php echo json_encode($user); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick='resetPassword(<?php echo $user['id']; ?>, "<?php echo htmlspecialchars($user['full_name']); ?>")'>
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if ($user['is_active']): ?>
                                <button class="btn btn-danger btn-sm" onclick='toggleStatus(<?php echo $user['id']; ?>, 0, "<?php echo htmlspecialchars($user['full_name']); ?>")' title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-success btn-sm" onclick='toggleStatus(<?php echo $user['id']; ?>, 1, "<?php echo htmlspecialchars($user['full_name']); ?>")' title="Activate">
                                    <i class="fas fa-check"></i>
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
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title">Edit User</div>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" id="editUserForm">
            <div class="modal-body">
                <input type="hidden" name="student_id" id="edit_student_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">College Name</label>
                        <input type="text" name="college_name" id="edit_college_name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Degree</label>
                        <input type="text" name="degree" id="edit_degree" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year of Study</label>
                        <select name="year_of_study" id="edit_year_of_study" class="form-select">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Domain Interest</label>
                        <input type="text" name="domain_interest" id="edit_domain_interest" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Internship Status</label>
                        <select name="internship_status" id="edit_internship_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="withdrawn">Withdrawn</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" name="update_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title">Reset Password</div>
            <button class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
        </div>
        <form method="POST" id="resetPasswordForm">
            <div class="modal-body">
                <input type="hidden" name="student_id" id="reset_student_id">
                <div style="padding:14px;background:var(--o1);border:1px solid var(--o2);border-radius:10px;margin-bottom:18px;">
                    <strong style="color:var(--text);"><i class="fas fa-user"></i> User:</strong> <span id="reset_user_name"></span>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Enter new password (min 6 characters)" required minlength="6">
                    <div style="font-size:.73rem;color:var(--text3);margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Password must be at least 6 characters long
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" class="form-input" placeholder="Confirm new password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" name="reset_password" class="btn btn-danger" onclick="return validatePassword()">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toggle Status Form (Hidden) -->
<form method="POST" id="toggleStatusForm" style="display:none;">
    <input type="hidden" name="student_id" id="toggle_student_id">
    <input type="hidden" name="is_active" id="toggle_is_active">
    <input type="hidden" name="update_status" value="1">
</form>

<script>
    function closeModal(id){
        document.getElementById(id).classList.remove('active');
    }
    
    function editUser(user){
        document.getElementById('edit_student_id').value=user.id;
        document.getElementById('edit_full_name').value=user.full_name;
        document.getElementById('edit_email').value=user.email;
        document.getElementById('edit_phone').value=user.phone||'';
        document.getElementById('edit_college_name').value=user.college_name||'';
        document.getElementById('edit_degree').value=user.degree||'';
        document.getElementById('edit_year_of_study').value=user.year_of_study||'1st Year';
        document.getElementById('edit_domain_interest').value=user.domain_interest||'';
        document.getElementById('edit_internship_status').value=user.internship_status;
        document.getElementById('editUserModal').classList.add('active');
    }
    
    function resetPassword(studentId, userName){
        document.getElementById('reset_student_id').value=studentId;
        document.getElementById('reset_user_name').textContent=userName;
        document.getElementById('resetPasswordForm').reset();
        document.getElementById('resetPasswordModal').classList.add('active');
    }
    
    function validatePassword(){
        const newPass=document.getElementById('new_password').value;
        const confirmPass=document.getElementById('confirm_password').value;
        
        if(newPass !== confirmPass){
            alert('Passwords do not match!');
            return false;
        }
        
        if(newPass.length < 6){
            alert('Password must be at least 6 characters long!');
            return false;
        }
        
        return confirm('Are you sure you want to reset this user\'s password?');
    }
    
    function toggleStatus(studentId, isActive, userName){
        const action = isActive ? 'activate' : 'deactivate';
        if(confirm(`Are you sure you want to ${action} ${userName}?`)){
            document.getElementById('toggle_student_id').value=studentId;
            document.getElementById('toggle_is_active').value=isActive;
            document.getElementById('toggleStatusForm').submit();
        }
    }
    
    document.querySelectorAll('.modal').forEach(modal=>{
        modal.addEventListener('click',function(e){
            if(e.target===this){
                this.classList.remove('active');
            }
        });
    });
</script>