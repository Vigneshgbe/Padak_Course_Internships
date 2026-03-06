<?php
// admin_user_management.php - Display only. POST handling is in admin.php top (before HTML output).

// Filter params
$filterStatus = $_GET['status_filter'] ?? 'all';
$searchQuery  = $_GET['search'] ?? '';

$whereConditions = [];
if ($filterStatus === 'active')   $whereConditions[] = "is_active=1";
elseif ($filterStatus === 'inactive') $whereConditions[] = "is_active=0";
if (!empty($searchQuery)) {
    $searchEsc = $db->real_escape_string($searchQuery);
    $whereConditions[] = "(full_name LIKE '%$searchEsc%' OR email LIKE '%$searchEsc%' OR domain_interest LIKE '%$searchEsc%')";
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$allCount      = (int)$db->query("SELECT COUNT(*) c FROM internship_students")->fetch_assoc()['c'];
$activeCount   = (int)$db->query("SELECT COUNT(*) c FROM internship_students WHERE is_active=1")->fetch_assoc()['c'];
$inactiveCount = (int)$db->query("SELECT COUNT(*) c FROM internship_students WHERE is_active=0")->fetch_assoc()['c'];

$usersRes = $db->query("SELECT s.*,
    (SELECT COUNT(*) FROM task_submissions WHERE student_id=s.id) as total_submissions,
    (SELECT COUNT(*) FROM task_submissions WHERE student_id=s.id AND status='approved') as approved_submissions
    FROM internship_students s $whereClause ORDER BY s.created_at DESC");
$users = [];
while ($row = $usersRes->fetch_assoc()) $users[] = $row;

// Safe data store for JS (no inline json_encode in onclick)
$usersDataStore = [];
foreach ($users as $u) {
    $usersDataStore[(int)$u['id']] = [
        'id'               => (int)$u['id'],
        'full_name'        => $u['full_name'],
        'email'            => $u['email'],
        'phone'            => $u['phone'] ?? '',
        'college_name'     => $u['college_name'] ?? '',
        'degree'           => $u['degree'] ?? '',
        'year_of_study'    => $u['year_of_study'] ?? '1st Year',
        'domain_interest'  => $u['domain_interest'] ?? '',
        'internship_status'=> $u['internship_status'] ?? 'active',
    ];
}

function umFilterUrl($status, $search) {
    $p = ['tab' => 'users', 'status_filter' => $status];
    if ($search !== '') $p['search'] = $search;
    return 'admin.php?' . http_build_query($p);
}
?>

<style>
.um-section{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);margin-bottom:24px;}
.um-section-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.um-sh-title{font-size:1.1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;}
.um-sh-title i{color:var(--o5);}
.um-section-body{padding:24px;}
.um-btn{padding:10px 18px;border-radius:9px;font-size:.875rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .2s;}
.um-btn-primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
.um-btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
.um-btn-secondary{background:var(--card);border:1.5px solid var(--border);color:var(--text2);}
.um-btn-secondary:hover{border-color:var(--o5);color:var(--o5);}
.um-btn-danger{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.3);color:#dc2626;}
.um-btn-danger:hover{background:rgba(239,68,68,0.2);border-color:#dc2626;}
.um-btn-success{background:rgba(34,197,94,0.1);border:1.5px solid rgba(34,197,94,0.3);color:#16a34a;}
.um-btn-success:hover{background:rgba(34,197,94,0.2);border-color:#16a34a;}
.um-btn-sm{padding:6px 12px;font-size:.75rem;}
.um-form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
.um-form-group{margin-bottom:18px;}
.um-form-group.full{grid-column:1/-1;}
.um-form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
.um-form-label .required{color:var(--red);}
.um-form-input,.um-form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
.um-form-input:focus,.um-form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.um-table-wrap{overflow-x:auto;}
.um-table{width:100%;border-collapse:collapse;}
.um-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);}
.um-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--text2);}
.um-table tr:hover{background:var(--bg);}
.um-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.um-badge-active{background:rgba(34,197,94,0.12);color:#16a34a;}
.um-badge-inactive{background:rgba(239,68,68,0.12);color:#dc2626;}
.um-badge-pending{background:rgba(234,179,8,0.12);color:#854d0e;}
.um-badge-completed{background:rgba(59,130,246,0.12);color:#1d4ed8;}
.um-badge-withdrawn{background:rgba(100,116,139,0.12);color:#475569;}
.um-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
.um-modal.active{display:flex;}
.um-modal-content{background:var(--card);border-radius:16px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.um-modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.um-mh-title{font-size:1.2rem;font-weight:700;color:var(--text);}
.um-modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:4px 8px;line-height:1;transition:color .2s;}
.um-modal-close:hover{color:#dc2626;}
.um-modal-body{padding:24px;}
.um-modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}
.um-empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
.um-empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
.um-empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
.um-filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
.um-filter-btn{padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.8rem;font-weight:500;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;}
.um-filter-btn:hover{border-color:var(--o5);color:var(--o5);}
.um-filter-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;}
.um-search-box{flex:1;max-width:300px;}
.um-search-box input{width:100%;padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;outline:none;font-family:inherit;background:var(--card);color:var(--text);}
.um-search-box input:focus{border-color:var(--o5);}
.um-user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;flex-shrink:0;}
.um-user-info{display:flex;align-items:center;gap:12px;}
.um-action-btns{display:flex;gap:6px;flex-wrap:wrap;}
@media(max-width:768px){.um-form-grid{grid-template-columns:1fr;}.um-search-box{max-width:100%;}}
</style>

<!-- Safe JSON data store -->
<script type="application/json" id="umUsersDataStore">
<?php echo json_encode($usersDataStore, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>
</script>

<div class="um-section">
    <div class="um-section-header">
        <div class="um-sh-title"><i class="fas fa-users"></i>User Management</div>
    </div>
    <div class="um-section-body">

        <div class="um-filter-bar">
            <a href="<?php echo umFilterUrl('all', $searchQuery); ?>"      class="um-filter-btn <?php echo $filterStatus==='all'?'active':''; ?>">All Users (<?php echo $allCount; ?>)</a>
            <a href="<?php echo umFilterUrl('active', $searchQuery); ?>"   class="um-filter-btn <?php echo $filterStatus==='active'?'active':''; ?>">Active (<?php echo $activeCount; ?>)</a>
            <a href="<?php echo umFilterUrl('inactive', $searchQuery); ?>" class="um-filter-btn <?php echo $filterStatus==='inactive'?'active':''; ?>">Inactive (<?php echo $inactiveCount; ?>)</a>

            <div class="um-search-box">
                <form method="GET" action="admin.php" style="margin:0;">
                    <input type="hidden" name="tab"           value="users">
                    <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($filterStatus); ?>">
                    <input type="text"   name="search" placeholder="Search users..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <?php if (empty($users)): ?>
        <div class="um-empty-state">
            <i class="fas fa-users"></i>
            <h3>No users found</h3>
            <p><?php echo !empty($searchQuery) ? 'Try a different search term' : 'No users registered yet'; ?></p>
        </div>
        <?php else: ?>
        <div class="um-table-wrap">
            <table class="um-table">
                <thead>
                    <tr>
                        <th>User</th><th>Contact</th><th>College</th><th>Domain</th>
                        <th>Points</th><th>Submissions</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="um-user-info">
                                <div class="um-user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 2)); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                    <small style="color:var(--text3);"><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($user['phone']): ?>
                            <i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($user['phone']); ?>
                            <?php else: ?><span style="color:var(--text3);">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['college_name']): ?>
                            <?php echo htmlspecialchars($user['college_name']); ?><br>
                            <small style="color:var(--text3);"><?php echo htmlspecialchars($user['degree'] ?: '—'); ?></small>
                            <?php else: ?><span style="color:var(--text3);">—</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['domain_interest'] ?: '—'); ?></td>
                        <td><strong style="color:var(--o5);"><?php echo $user['total_points']; ?></strong> pts</td>
                        <td>
                            <?php if ($user['total_submissions'] > 0): ?>
                            <?php echo $user['approved_submissions']; ?>/<?php echo $user['total_submissions']; ?> approved
                            <?php else: ?><span style="color:var(--text3);">None</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="um-badge um-badge-<?php echo $user['is_active']?'active':'inactive'; ?>">
                                <?php echo $user['is_active']?'Active':'Inactive'; ?>
                            </span><br>
                            <span class="um-badge um-badge-<?php echo htmlspecialchars($user['internship_status']); ?>" style="margin-top:4px;">
                                <?php echo ucfirst($user['internship_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="um-action-btns">
                                <button type="button" class="um-btn um-btn-secondary um-btn-sm"
                                        onclick="umEditUser(<?php echo (int)$user['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="um-btn um-btn-danger um-btn-sm"
                                        onclick="umResetPassword(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if ($user['is_active']): ?>
                                <button type="button" class="um-btn um-btn-danger um-btn-sm"
                                        onclick="umToggleStatus(<?php echo (int)$user['id']; ?>, 0, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="um-btn um-btn-success um-btn-sm"
                                        onclick="umToggleStatus(<?php echo (int)$user['id']; ?>, 1, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')" title="Activate">
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
<div id="umEditUserModal" class="um-modal" role="dialog" aria-modal="true">
    <div class="um-modal-content">
        <div class="um-modal-header">
            <div class="um-mh-title">Edit User</div>
            <button type="button" class="um-modal-close" id="umEditCloseBtn">&times;</button>
        </div>
        <form method="POST" action="admin.php" id="umEditUserForm">
            <input type="hidden" name="update_user"  value="1">
            <input type="hidden" name="student_id"   id="um_edit_student_id">
            <div class="um-modal-body">
                <div class="um-form-grid">
                    <div class="um-form-group">
                        <label class="um-form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" id="um_edit_full_name" class="um-form-input" required>
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="um_edit_email" class="um-form-input" required>
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">Phone</label>
                        <input type="text" name="phone" id="um_edit_phone" class="um-form-input">
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">College Name</label>
                        <input type="text" name="college_name" id="um_edit_college_name" class="um-form-input">
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">Degree</label>
                        <input type="text" name="degree" id="um_edit_degree" class="um-form-input">
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">Year of Study</label>
                        <select name="year_of_study" id="um_edit_year_of_study" class="um-form-select">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">Domain Interest</label>
                        <input type="text" name="domain_interest" id="um_edit_domain_interest" class="um-form-input">
                    </div>
                    <div class="um-form-group">
                        <label class="um-form-label">Internship Status</label>
                        <select name="internship_status" id="um_edit_internship_status" class="um-form-select">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="withdrawn">Withdrawn</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="um-modal-footer">
                <button type="button" class="um-btn um-btn-secondary" id="umEditCancelBtn">Cancel</button>
                <button type="submit" class="um-btn um-btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="umResetPwdModal" class="um-modal" role="dialog" aria-modal="true">
    <div class="um-modal-content">
        <div class="um-modal-header">
            <div class="um-mh-title">Reset Password</div>
            <button type="button" class="um-modal-close" id="umPwdCloseBtn">&times;</button>
        </div>
        <form method="POST" action="admin.php" id="umResetPwdForm">
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="student_id"     id="um_reset_student_id">
            <div class="um-modal-body">
                <div style="padding:14px;background:var(--o1);border:1px solid var(--o2);border-radius:10px;margin-bottom:18px;">
                    <strong style="color:var(--text);"><i class="fas fa-user"></i> User:</strong>
                    <span id="um_reset_user_name"></span>
                </div>
                <div class="um-form-group">
                    <label class="um-form-label">New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="um_new_password" class="um-form-input"
                           placeholder="Enter new password (min 6 characters)" required minlength="6">
                    <div style="font-size:.73rem;color:var(--text3);margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Password must be at least 6 characters long
                    </div>
                </div>
                <div class="um-form-group">
                    <label class="um-form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="um_confirm_password" class="um-form-input"
                           placeholder="Confirm new password" required>
                </div>
            </div>
            <div class="um-modal-footer">
                <button type="button" class="um-btn um-btn-secondary" id="umPwdCancelBtn">Cancel</button>
                <button type="button" class="um-btn um-btn-danger" onclick="umSubmitPasswordReset()">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toggle Status Hidden Form -->
<form method="POST" action="admin.php" id="umToggleStatusForm" style="display:none;">
    <input type="hidden" name="update_user_status" value="1">
    <input type="hidden" name="student_id"         id="um_toggle_student_id">
    <input type="hidden" name="is_active"          id="um_toggle_is_active">
</form>

<script>
(function() {
    var umData = {};
    try {
        var raw = document.getElementById('umUsersDataStore');
        if (raw) umData = JSON.parse(raw.textContent);
    } catch(e) {}

    function umClose(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    window.umEditUser = function(id) {
        var u = umData[id];
        if (!u) return;
        document.getElementById('um_edit_student_id').value       = u.id;
        document.getElementById('um_edit_full_name').value        = u.full_name;
        document.getElementById('um_edit_email').value            = u.email;
        document.getElementById('um_edit_phone').value            = u.phone;
        document.getElementById('um_edit_college_name').value     = u.college_name;
        document.getElementById('um_edit_degree').value           = u.degree;
        document.getElementById('um_edit_year_of_study').value    = u.year_of_study || '1st Year';
        document.getElementById('um_edit_domain_interest').value  = u.domain_interest;
        document.getElementById('um_edit_internship_status').value= u.internship_status;
        document.getElementById('umEditUserModal').classList.add('active');
    };

    window.umResetPassword = function(id, name) {
        document.getElementById('um_reset_student_id').value = id;
        document.getElementById('um_reset_user_name').textContent = name;
        document.getElementById('um_new_password').value = '';
        document.getElementById('um_confirm_password').value = '';
        document.getElementById('umResetPwdModal').classList.add('active');
    };

    window.umSubmitPasswordReset = function() {
        var np = document.getElementById('um_new_password').value;
        var cp = document.getElementById('um_confirm_password').value;
        if (np !== cp)    { alert('Passwords do not match!'); return; }
        if (np.length < 6){ alert('Password must be at least 6 characters long!'); return; }
        if (!confirm("Are you sure you want to reset this user's password?")) return;
        document.getElementById('umResetPwdForm').submit();
    };

    window.umToggleStatus = function(id, isActive, name) {
        var action = isActive ? 'activate' : 'deactivate';
        if (!confirm('Are you sure you want to ' + action + ' ' + name + '?')) return;
        document.getElementById('um_toggle_student_id').value = id;
        document.getElementById('um_toggle_is_active').value  = isActive;
        document.getElementById('umToggleStatusForm').submit();
    };

    // Close buttons
    document.getElementById('umEditCloseBtn').addEventListener('click',  function() { umClose('umEditUserModal'); });
    document.getElementById('umEditCancelBtn').addEventListener('click', function() { umClose('umEditUserModal'); });
    document.getElementById('umPwdCloseBtn').addEventListener('click',   function() { umClose('umResetPwdModal'); });
    document.getElementById('umPwdCancelBtn').addEventListener('click',  function() { umClose('umResetPwdModal'); });

    // Click outside to close
    document.getElementById('umEditUserModal').addEventListener('click', function(e) { if(e.target===this) umClose('umEditUserModal'); });
    document.getElementById('umResetPwdModal').addEventListener('click', function(e) { if(e.target===this) umClose('umResetPwdModal'); });
})();
</script>