<?php
// Handles: Create, Edit, Delete, List announcements with filtering and read tracking

if (!defined('ADMIN_USERNAME')) {
    die('Direct access not permitted');
}

$db = getPadakDB();
$action = $_GET['action'] ?? 'list';
$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================
// HANDLE DELETE ACTION
// ============================================
if ($action === 'delete' && $announcement_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $announcement_id);
    if ($stmt->execute()) {
        // Also delete read tracking records
        $db->query("DELETE FROM announcement_reads WHERE announcement_id = $announcement_id");
        $_SESSION['admin_success'] = 'Announcement deleted successfully';
    } else {
        $_SESSION['admin_error'] = 'Failed to delete announcement';
    }
    echo '<script>window.location.href="admin.php#tab-announcements";</script>';
    exit;
}

// ============================================
// HANDLE CREATE/EDIT FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'general';
    $priority = $_POST['priority'] ?? 'normal';
    $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
    $coordinator_id = !empty($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : null;
    $target_all = isset($_POST['target_all']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $edit_id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
    
    // Validation
    $errors = [];
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($content)) $errors[] = 'Content is required';
    if (!in_array($type, ['general', 'task_deadline', 'certificate', 'attendance'])) $errors[] = 'Invalid type';
    if (!in_array($priority, ['urgent', 'important', 'normal'])) $errors[] = 'Invalid priority';
    
    if (empty($errors)) {
        if ($edit_id > 0) {
            // UPDATE existing announcement
            $stmt = $db->prepare("UPDATE announcements SET title=?, content=?, type=?, priority=?, batch_id=?, coordinator_id=?, target_all=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->bind_param("ssssiiii", $title, $content, $type, $priority, $batch_id, $coordinator_id, $target_all, $is_active, $edit_id);
            if ($stmt->execute()) {
                $_SESSION['admin_success'] = 'Announcement updated successfully';
                echo '<script>window.location.href="admin.php#tab-announcements";</script>';
                exit;
            } else {
                $_SESSION['admin_error'] = 'Failed to update announcement';
            }
        } else {
            // INSERT new announcement
            $stmt = $db->prepare("INSERT INTO announcements (title, content, type, priority, batch_id, coordinator_id, target_all, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiiii", $title, $content, $type, $priority, $batch_id, $coordinator_id, $target_all, $is_active);
            if ($stmt->execute()) {
                $_SESSION['admin_success'] = 'Announcement created successfully';
                echo '<script>window.location.href="admin.php#tab-announcements";</script>';
                exit;
            } else {
                $_SESSION['admin_error'] = 'Failed to create announcement';
            }
        }
    } else {
        $_SESSION['admin_error'] = implode(', ', $errors);
    }
}

// ============================================
// FETCH DATA FOR FORM
// ============================================
$batches = $db->query("SELECT id, batch_name, domain FROM internship_batches WHERE is_active=1 ORDER BY batch_name");
$coordinators = $db->query("SELECT id, full_name, email FROM coordinators WHERE is_active=1 ORDER BY full_name");

$announcement = null;
if ($action === 'edit' && $announcement_id > 0) {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $announcement = $stmt->get_result()->fetch_assoc();
    if (!$announcement) {
        $_SESSION['admin_error'] = 'Announcement not found';
        echo '<script>window.location.href="admin.php#tab-announcements";</script>';
        exit;
    }
}

// ============================================
// FETCH ANNOUNCEMENT LIST WITH FILTERS
// ============================================
$filter_type = $_GET['filter_type'] ?? '';
$filter_priority = $_GET['filter_priority'] ?? '';
$filter_active = $_GET['filter_active'] ?? '';
$search = trim($_GET['search'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_type)) {
    $where_clauses[] = "a.type = ?";
    $params[] = $filter_type;
    $types .= 's';
}
if (!empty($filter_priority)) {
    $where_clauses[] = "a.priority = ?";
    $params[] = $filter_priority;
    $types .= 's';
}
if ($filter_active !== '') {
    $where_clauses[] = "a.is_active = ?";
    $params[] = (int)$filter_active;
    $types .= 'i';
}
if (!empty($search)) {
    $where_clauses[] = "(a.title LIKE ? OR a.content LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "SELECT a.*, 
        b.batch_name,
        c.full_name as coordinator_name,
        (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) as read_count,
        (SELECT COUNT(*) FROM internship_students WHERE is_active=1) as total_students
        FROM announcements a
        LEFT JOIN internship_batches b ON a.batch_id = b.id
        LEFT JOIN coordinators c ON a.coordinator_id = c.id
        $where_sql
        ORDER BY a.created_at DESC";

if (!empty($params)) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $announcements_result = $stmt->get_result();
} else {
    $announcements_result = $db->query($sql);
}
?>

<style>
.ann-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
.ann-header h2{font-size:1.5rem;font-weight:800;color:var(--text);}
.btn-primary{padding:10px 20px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;box-shadow:0 2px 8px rgba(249,115,22,0.25);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(249,115,22,0.35);}
.btn-secondary{padding:8px 16px;background:var(--bg);color:var(--text2);border:1px solid var(--border);border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.btn-secondary:hover{background:var(--card);border-color:var(--text3);}
.btn-danger{padding:8px 16px;background:rgba(239,68,68,0.1);color:var(--red);border:1px solid rgba(239,68,68,0.3);border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.btn-danger:hover{background:rgba(239,68,68,0.2);border-color:var(--red);}
.btn-sm{padding:6px 12px;font-size:.75rem;}
.filters-bar{background:var(--card);padding:18px 20px;border-radius:12px;border:1px solid var(--border);margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.filter-group{display:flex;flex-direction:column;gap:6px;min-width:140px;}
.filter-label{font-size:.75rem;font-weight:600;color:var(--text2);}
.filter-select,.filter-input{padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:.82rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;}
.filter-select:focus,.filter-input:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.ann-table{width:100%;background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.ann-table table{width:100%;border-collapse:collapse;}
.ann-table thead{background:var(--bg);}
.ann-table th{padding:14px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);}
.ann-table td{padding:14px 16px;font-size:.875rem;color:var(--text);border-bottom:1px solid var(--border);}
.ann-table tbody tr:hover{background:var(--bg);}
.ann-table tbody tr:last-child td{border-bottom:none;}
.badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
.badge-urgent{background:rgba(239,68,68,0.12);color:#dc2626;}
.badge-important{background:rgba(249,115,22,0.12);color:var(--o6);}
.badge-normal{background:rgba(148,163,184,0.12);color:#64748b;}
.badge-active{background:rgba(34,197,94,0.12);color:#16a34a;}
.badge-inactive{background:rgba(148,163,184,0.12);color:#64748b;}
.badge-type{background:rgba(59,130,246,0.12);color:#2563eb;}
.read-stat{display:flex;align-items:center;gap:6px;font-size:.82rem;}
.read-stat i{color:var(--text3);}
.action-btns{display:flex;gap:6px;flex-wrap:wrap;}
.no-data{padding:40px;text-align:center;color:var(--text3);font-size:.875rem;}
.form-section{background:var(--card);padding:24px;border-radius:12px;border:1px solid var(--border);margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.form-section h3{font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid var(--border);}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;}
.form-group-full{grid-column:1/-1;}
.form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
.form-input,.form-select,.form-textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.form-textarea{resize:vertical;min-height:120px;line-height:1.6;}
.checkbox-group{display:flex;align-items:center;gap:10px;margin-top:8px;}
.checkbox-group input[type="checkbox"]{width:18px;height:18px;cursor:pointer;}
.checkbox-group label{font-size:.875rem;color:var(--text2);cursor:pointer;user-select:none;}
.form-actions{display:flex;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);}
.help-text{font-size:.75rem;color:var(--text3);margin-top:4px;}
.target-info{background:rgba(59,130,246,0.08);padding:12px;border-radius:6px;border:1px solid rgba(59,130,246,0.2);margin-top:12px;}
.target-info strong{color:var(--blue);font-size:.82rem;}
.empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
.empty-state i{font-size:3rem;margin-bottom:16px;opacity:.5;}
.empty-state h3{font-size:1.2rem;font-weight:700;margin-bottom:8px;color:var(--text2);}
@media(max-width:768px){.filters-bar{flex-direction:column;}.filter-group{width:100%;}.ann-table{overflow-x:auto;}.form-grid{grid-template-columns:1fr;}}
</style>

<?php if ($action === 'create' || $action === 'edit'): ?>
<!-- CREATE/EDIT FORM -->
<div class="ann-header">
    <h2><i class="fas fa-<?php echo $action === 'edit' ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $action === 'edit' ? 'Edit' : 'Create'; ?> Announcement</h2>
    <a href="admin.php?#tab-announcements" class="btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<form method="POST" action="">
    <?php if ($action === 'edit'): ?>
    <input type="hidden" name="announcement_id" value="<?php echo $announcement_id; ?>">
    <?php endif; ?>
    
    <div class="form-section">
        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
        <div class="form-grid">
            <div class="form-group form-group-full">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-input" 
                       value="<?php echo htmlspecialchars($announcement['title'] ?? ''); ?>" 
                       placeholder="Enter announcement title" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Type *</label>
                <select name="type" class="form-select" required>
                    <option value="general" <?php echo ($announcement['type'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                    <option value="task_deadline" <?php echo ($announcement['type'] ?? '') === 'task_deadline' ? 'selected' : ''; ?>>Task Deadline</option>
                    <option value="certificate" <?php echo ($announcement['type'] ?? '') === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                    <option value="attendance" <?php echo ($announcement['type'] ?? '') === 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Priority *</label>
                <select name="priority" class="form-select" required>
                    <option value="urgent" <?php echo ($announcement['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>🔴 Urgent</option>
                    <option value="important" <?php echo ($announcement['priority'] ?? '') === 'important' ? 'selected' : ''; ?>>🟠 Important</option>
                    <option value="normal" <?php echo ($announcement['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>⚪ Normal</option>
                </select>
            </div>
            
            <div class="form-group form-group-full">
                <label class="form-label">Content *</label>
                <textarea name="content" class="form-textarea" placeholder="Enter announcement content" required><?php echo htmlspecialchars($announcement['content'] ?? ''); ?></textarea>
                <div class="help-text">💡 Supports plain text. Keep it clear and concise.</div>
            </div>
        </div>
    </div>
    
    <div class="form-section">
        <h3><i class="fas fa-users"></i> Target Audience</h3>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Target Batch (Optional)</label>
                <select name="batch_id" class="form-select" id="batch-select">
                    <option value="">All Batches</option>
                    <?php while ($batch = $batches->fetch_assoc()): ?>
                    <option value="<?php echo $batch['id']; ?>" 
                            <?php echo ($announcement['batch_id'] ?? 0) == $batch['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($batch['batch_name'] . ' - ' . $batch['domain']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <div class="help-text">Select a specific batch to target, or leave blank for all</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Coordinator (Optional)</label>
                <select name="coordinator_id" class="form-select" id="coordinator-select">
                    <option value="">No specific coordinator</option>
                    <?php while ($coord = $coordinators->fetch_assoc()): ?>
                    <option value="<?php echo $coord['id']; ?>"
                            <?php echo ($announcement['coordinator_id'] ?? 0) == $coord['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($coord['full_name'] . ' (' . $coord['email'] . ')'); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <div class="help-text">Assign to a specific coordinator if needed</div>
            </div>
            
            <div class="form-group form-group-full">
                <div class="checkbox-group">
                    <input type="checkbox" name="target_all" id="target-all" value="1" 
                           <?php echo ($announcement['target_all'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="target-all">Target All Students (ignores batch/coordinator filters)</label>
                </div>
            </div>
            
            <div class="target-info">
                <strong><i class="fas fa-info-circle"></i> Targeting Logic:</strong>
                If "Target All" is checked, the announcement will be visible to all active students regardless of batch or coordinator selection. 
                If unchecked, it will only show to students matching the selected batch/coordinator filters.
            </div>
        </div>
    </div>
    
    <div class="form-section">
        <h3><i class="fas fa-toggle-on"></i> Status</h3>
        <div class="checkbox-group">
            <input type="checkbox" name="is_active" id="is-active" value="1" 
                   <?php echo ($announcement['is_active'] ?? 1) ? 'checked' : ''; ?>>
            <label for="is-active">Active (visible to students)</label>
        </div>
        <div class="help-text">Uncheck to hide this announcement from students without deleting it</div>
    </div>
    
    <div class="form-actions">
        <button type="submit" name="save_announcement" class="btn-primary">
            <i class="fas fa-save"></i> <?php echo $action === 'edit' ? 'Update' : 'Create'; ?> Announcement
        </button>
        <a href="admin.php?#tab-announcements" class="btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php else: ?>
<!-- LIST VIEW -->
<div class="ann-header">
    <h2><i class="fas fa-bullhorn"></i> Announcement Management</h2>
    <a href="admin.php?action=create#tab-announcements" class="btn-primary">
        <i class="fas fa-plus-circle"></i> Create New
    </a>
</div>

<!-- FILTERS -->
<form method="GET" class="filters-bar">
    <input type="hidden" name="#" value="tab-announcements">
    <div class="filter-group">
        <label class="filter-label">Type</label>
        <select name="filter_type" class="filter-select" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="general" <?php echo $filter_type === 'general' ? 'selected' : ''; ?>>General</option>
            <option value="task_deadline" <?php echo $filter_type === 'task_deadline' ? 'selected' : ''; ?>>Task Deadline</option>
            <option value="certificate" <?php echo $filter_type === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
            <option value="attendance" <?php echo $filter_type === 'attendance' ? 'selected' : ''; ?>>Attendance</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Priority</label>
        <select name="filter_priority" class="filter-select" onchange="this.form.submit()">
            <option value="">All Priorities</option>
            <option value="urgent" <?php echo $filter_priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
            <option value="important" <?php echo $filter_priority === 'important' ? 'selected' : ''; ?>>Important</option>
            <option value="normal" <?php echo $filter_priority === 'normal' ? 'selected' : ''; ?>>Normal</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Status</label>
        <select name="filter_active" class="filter-select" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>Inactive</option>
        </select>
    </div>
    
    <div class="filter-group" style="flex:1;min-width:220px;">
        <label class="filter-label">Search</label>
        <input type="text" name="search" class="filter-input" 
               value="<?php echo htmlspecialchars($search); ?>" 
               placeholder="Search title or content...">
    </div>
    
    <div class="filter-group">
        <label class="filter-label">&nbsp;</label>
        <button type="submit" class="btn-secondary">
            <i class="fas fa-search"></i> Filter
        </button>
    </div>
    
    <?php if ($filter_type || $filter_priority || $filter_active !== '' || $search): ?>
    <div class="filter-group">
        <label class="filter-label">&nbsp;</label>
        <a href="admin.php?#tab-announcements" class="btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- TABLE -->
<?php if ($announcements_result->num_rows > 0): ?>
<div class="ann-table">
    <table>
        <thead>
            <tr>
                <th style="width:5%;">ID</th>
                <th style="width:25%;">Title</th>
                <th style="width:10%;">Type</th>
                <th style="width:10%;">Priority</th>
                <th style="width:12%;">Target</th>
                <th style="width:10%;">Read Stats</th>
                <th style="width:8%;">Status</th>
                <th style="width:12%;">Created</th>
                <th style="width:8%;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($ann = $announcements_result->fetch_assoc()): ?>
            <tr>
                <td><strong>#<?php echo $ann['id']; ?></strong></td>
                <td>
                    <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                    <div style="font-size:.75rem;color:var(--text3);margin-top:4px;">
                        <?php echo htmlspecialchars(substr($ann['content'], 0, 60)) . (strlen($ann['content']) > 60 ? '...' : ''); ?>
                    </div>
                </td>
                <td><span class="badge badge-type"><?php echo htmlspecialchars($ann['type']); ?></span></td>
                <td>
                    <span class="badge badge-<?php echo $ann['priority']; ?>">
                        <?php echo htmlspecialchars(ucfirst($ann['priority'])); ?>
                    </span>
                </td>
                <td>
                    <?php if ($ann['target_all']): ?>
                        <span style="font-size:.82rem;"><i class="fas fa-users"></i> All Students</span>
                    <?php else: ?>
                        <?php if ($ann['batch_name']): ?>
                            <div style="font-size:.75rem;"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($ann['batch_name']); ?></div>
                        <?php endif; ?>
                        <?php if ($ann['coordinator_name']): ?>
                            <div style="font-size:.75rem;"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($ann['coordinator_name']); ?></div>
                        <?php endif; ?>
                        <?php if (!$ann['batch_name'] && !$ann['coordinator_name']): ?>
                            <span style="font-size:.82rem;color:var(--text3);">No filter</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="read-stat">
                        <i class="fas fa-eye"></i>
                        <span><?php echo $ann['read_count']; ?> / <?php echo $ann['total_students']; ?></span>
                    </div>
                    <?php 
                    $read_percentage = $ann['total_students'] > 0 ? round(($ann['read_count'] / $ann['total_students']) * 100) : 0;
                    ?>
                    <div style="font-size:.7rem;color:var(--text3);margin-top:2px;">
                        <?php echo $read_percentage; ?>% read
                    </div>
                </td>
                <td>
                    <span class="badge badge-<?php echo $ann['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $ann['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td style="font-size:.82rem;color:var(--text2);">
                    <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                    <div style="font-size:.7rem;color:var(--text3);margin-top:2px;">
                        <?php echo date('h:i A', strtotime($ann['created_at'])); ?>
                    </div>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="admin.php?action=edit&id=<?php echo $ann['id']; ?>#tab-announcements" 
                           class="btn-secondary btn-sm" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="btn-danger btn-sm" 
                                onclick="deleteAnnouncement(<?php echo $ann['id']; ?>)" 
                                title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-inbox"></i>
    <h3>No Announcements Found</h3>
    <p>Create your first announcement to get started</p>
    <a href="admin.php?action=create#tab-announcements" class="btn-primary" style="margin-top:16px;">
        <i class="fas fa-plus-circle"></i> Create Announcement
    </a>
</div>
<?php endif; ?>

<script>
function deleteAnnouncement(id) {
    if (!confirm('Are you sure you want to delete this announcement? This will also remove all read tracking data.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin.php?action=delete&id=' + id + '#tab-announcements';
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php endif; ?>