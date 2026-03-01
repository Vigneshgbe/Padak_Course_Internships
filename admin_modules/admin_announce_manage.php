<?php
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

// Handle Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = trim($_POST['type'] ?? 'general');
    $priority = trim($_POST['priority'] ?? 'normal');
    $batchId = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
    $coordinatorId = !empty($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : null;
    $targetAll = isset($_POST['target_all']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($title)) {
        $error = 'Title is required';
    } elseif (empty($content)) {
        $error = 'Content is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $contentEsc = $db->real_escape_string($content);
        $typeEsc = $db->real_escape_string($type);
        $priorityEsc = $db->real_escape_string($priority);
        
        $batchIdSql = $batchId !== null ? $batchId : 'NULL';
        $coordinatorIdSql = $coordinatorId !== null ? $coordinatorId : 'NULL';
        
        $sql = "INSERT INTO announcements (title, content, type, priority, batch_id, coordinator_id, target_all, is_active, created_at, updated_at)
                VALUES ('$titleEsc', '$contentEsc', '$typeEsc', '$priorityEsc', $batchIdSql, $coordinatorIdSql, $targetAll, $isActive, NOW(), NOW())";
        
        if ($db->query($sql)) {
            $_SESSION['admin_success'] = 'Announcement created successfully!';
            echo '<script>window.location.href="admin.php#tab-announcements";</script>';
            exit;
        } else {
            $error = 'Failed to create announcement: ' . $db->error;
        }
    }
}

// Handle Update Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    $announcementId = (int)$_POST['announcement_id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = trim($_POST['type'] ?? 'general');
    $priority = trim($_POST['priority'] ?? 'normal');
    $batchId = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
    $coordinatorId = !empty($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : null;
    $targetAll = isset($_POST['target_all']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($title)) {
        $error = 'Title is required';
    } elseif (empty($content)) {
        $error = 'Content is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $contentEsc = $db->real_escape_string($content);
        $typeEsc = $db->real_escape_string($type);
        $priorityEsc = $db->real_escape_string($priority);
        
        $batchIdSql = $batchId !== null ? $batchId : 'NULL';
        $coordinatorIdSql = $coordinatorId !== null ? $coordinatorId : 'NULL';
        
        $sql = "UPDATE announcements SET
                title='$titleEsc',
                content='$contentEsc',
                type='$typeEsc',
                priority='$priorityEsc',
                batch_id=$batchIdSql,
                coordinator_id=$coordinatorIdSql,
                target_all=$targetAll,
                is_active=$isActive,
                updated_at=NOW()
                WHERE id=$announcementId";
        
        if ($db->query($sql)) {
            $_SESSION['admin_success'] = 'Announcement updated successfully!';
            echo '<script>window.location.href="admin.php#tab-announcements";</script>';
            exit;
        } else {
            $error = 'Failed to update announcement: ' . $db->error;
        }
    }
}

// Handle Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $announcementId = (int)$_POST['announcement_id'];
    
    // Delete read records first (foreign key)
    $db->query("DELETE FROM announcement_reads WHERE announcement_id=$announcementId");
    
    // Delete announcement
    $sql = "DELETE FROM announcements WHERE id=$announcementId";
    
    if ($db->query($sql)) {
        $_SESSION['admin_success'] = 'Announcement deleted successfully!';
        echo '<script>window.location.href="admin.php#tab-announcements";</script>';
        exit;
    } else {
        $error = 'Failed to delete announcement: ' . $db->error;
    }
}

// Handle Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $announcementId = (int)$_POST['announcement_id'];
    $isActive = (int)$_POST['is_active'];
    
    $sql = "UPDATE announcements SET is_active=$isActive, updated_at=NOW() WHERE id=$announcementId";
    
    if ($db->query($sql)) {
        $statusText = $isActive ? 'activated' : 'deactivated';
        $_SESSION['admin_success'] = "Announcement $statusText successfully!";
        echo '<script>window.location.href="admin.php#tab-announcements";</script>';
        exit;
    } else {
        $error = 'Failed to update announcement status: ' . $db->error;
    }
}

// Get Filter Parameters
$filterType = $_GET['type_filter'] ?? 'all';
$filterPriority = $_GET['priority_filter'] ?? 'all';
$filterStatus = $_GET['status_filter'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = [];
if ($filterType !== 'all') {
    $filterTypeEsc = $db->real_escape_string($filterType);
    $whereConditions[] = "a.type='$filterTypeEsc'";
}
if ($filterPriority !== 'all') {
    $filterPriorityEsc = $db->real_escape_string($filterPriority);
    $whereConditions[] = "a.priority='$filterPriorityEsc'";
}
if ($filterStatus === 'active') {
    $whereConditions[] = "a.is_active=1";
} elseif ($filterStatus === 'inactive') {
    $whereConditions[] = "a.is_active=0";
}
if (!empty($searchQuery)) {
    $searchEsc = $db->real_escape_string($searchQuery);
    $whereConditions[] = "(a.title LIKE '%$searchEsc%' OR a.content LIKE '%$searchEsc%')";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get counts for filter buttons
$allCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements")->fetch_assoc()['cnt'];
$activeCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE is_active=1")->fetch_assoc()['cnt'];
$inactiveCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE is_active=0")->fetch_assoc()['cnt'];
$urgentCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE priority='urgent'")->fetch_assoc()['cnt'];

// Get type counts
$generalCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE type='general'")->fetch_assoc()['cnt'];
$taskCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE type='task'")->fetch_assoc()['cnt'];
$deadlineCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE type='deadline'")->fetch_assoc()['cnt'];
$certificateCount = (int)$db->query("SELECT COUNT(*) as cnt FROM announcements WHERE type='certificate'")->fetch_assoc()['cnt'];

// Get Announcements with read statistics
$announcementsRes = $db->query("SELECT a.*,
    (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id=a.id) as total_reads,
    (SELECT COUNT(*) FROM internship_students WHERE is_active=1) as total_students,
    (SELECT full_name FROM coordinators WHERE id=a.coordinator_id) as coordinator_name
    FROM announcements a
    $whereClause
    ORDER BY a.created_at DESC");
$announcements = [];
while ($row = $announcementsRes->fetch_assoc()) $announcements[] = $row;

// Get batches for dropdown
$batches = [];
$batchesRes = @$db->query("SELECT id, batch_name FROM batches ORDER BY batch_name ASC");
if ($batchesRes) {
    while ($row = $batchesRes->fetch_assoc()) $batches[] = $row;
}

// Get coordinators for dropdown
$coordinators = [];
$coordinatorsRes = @$db->query("SELECT id, full_name FROM coordinators ORDER BY full_name ASC");
if ($coordinatorsRes) {
    while ($row = $coordinatorsRes->fetch_assoc()) $coordinators[] = $row;
}
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
    .btn-warning{background:rgba(234,179,8,0.1);border:1.5px solid rgba(234,179,8,0.3);color:#ca8a04;}
    .btn-warning:hover{background:rgba(234,179,8,0.2);border-color:#ca8a04;}
    .btn-sm{padding:6px 12px;font-size:.75rem;}
    .form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
    .form-group{margin-bottom:18px;}
    .form-group.full{grid-column:1/-1;}
    .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
    .form-label .required{color:var(--red);}
    .form-input,.form-textarea,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
    .form-textarea{min-height:120px;resize:vertical;font-family:inherit;}
    .form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
    .form-checkbox{display:flex;align-items:center;gap:8px;cursor:pointer;}
    .form-checkbox input{width:18px;height:18px;cursor:pointer;}
    .table-responsive{overflow-x:auto;}
    .data-table{width:100%;border-collapse:collapse;}
    .data-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);}
    .data-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--text2);}
    .data-table tr:hover{background:var(--bg);}
    .data-table td:first-child{font-weight:600;color:var(--text);}
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
    .badge-active{background:rgba(34,197,94,0.12);color:#16a34a;}
    .badge-inactive{background:rgba(239,68,68,0.12);color:#dc2626;}
    .badge-urgent{background:rgba(239,68,68,0.12);color:#dc2626;animation:pulse 2s infinite;}
    .badge-important{background:rgba(234,179,8,0.12);color:#ca8a04;}
    .badge-normal{background:rgba(100,116,139,0.12);color:#475569;}
    .badge-general{background:rgba(59,130,246,0.12);color:#1d4ed8;}
    .badge-task{background:rgba(168,85,247,0.12);color:#7c3aed;}
    .badge-deadline{background:rgba(239,68,68,0.12);color:#dc2626;}
    .badge-certificate{background:rgba(34,197,94,0.12);color:#16a34a;}
    @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.5;}}
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
    .announcement-preview{background:var(--bg);padding:14px;border-radius:8px;margin-top:8px;border-left:3px solid var(--o5);}
    .announcement-preview h4{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:6px;}
    .announcement-preview p{font-size:.8rem;color:var(--text2);line-height:1.5;max-height:60px;overflow:hidden;}
    .action-buttons{display:flex;gap:6px;flex-wrap:wrap;}
    .stat-row{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--text3);}
    .stat-row i{color:var(--o5);}
    .info-box{background:var(--o1);border:1px solid var(--o2);border-radius:10px;padding:14px;margin-bottom:18px;}
    .info-box strong{color:var(--text);}
    .char-counter{font-size:.75rem;color:var(--text3);text-align:right;margin-top:4px;}
    @media(max-width:768px){.form-grid{grid-template-columns:1fr;}.search-box{max-width:100%;}}
</style>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-bullhorn"></i>Announcement Management</div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> New Announcement
        </button>
    </div>
    <div class="section-body">
        <?php if ($error): ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:20px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
            <i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filter-bar">
            <a href="?status_filter=all#tab-announcements" class="filter-btn <?php echo $filterStatus==='all'?'active':''; ?>">
                All (<?php echo $allCount; ?>)
            </a>
            <a href="?status_filter=active#tab-announcements" class="filter-btn <?php echo $filterStatus==='active'?'active':''; ?>">
                <i class="fas fa-check-circle"></i> Active (<?php echo $activeCount; ?>)
            </a>
            <a href="?status_filter=inactive#tab-announcements" class="filter-btn <?php echo $filterStatus==='inactive'?'active':''; ?>">
                <i class="fas fa-ban"></i> Inactive (<?php echo $inactiveCount; ?>)
            </a>
            
            <div style="width:2px;height:24px;background:var(--border);"></div>
            
            <a href="?type_filter=general#tab-announcements" class="filter-btn <?php echo $filterType==='general'?'active':''; ?>">
                <i class="fas fa-info-circle"></i> General (<?php echo $generalCount; ?>)
            </a>
            <a href="?type_filter=task#tab-announcements" class="filter-btn <?php echo $filterType==='task'?'active':''; ?>">
                <i class="fas fa-tasks"></i> Task (<?php echo $taskCount; ?>)
            </a>
            <a href="?type_filter=deadline#tab-announcements" class="filter-btn <?php echo $filterType==='deadline'?'active':''; ?>">
                <i class="fas fa-clock"></i> Deadline (<?php echo $deadlineCount; ?>)
            </a>
            <a href="?type_filter=certificate#tab-announcements" class="filter-btn <?php echo $filterType==='certificate'?'active':''; ?>">
                <i class="fas fa-certificate"></i> Certificate (<?php echo $certificateCount; ?>)
            </a>
            
            <div class="search-box">
                <form method="GET" style="margin:0;">
                    <input type="text" name="search" placeholder="Search announcements..." value="<?php echo htmlspecialchars($searchQuery); ?>" onchange="this.form.submit()">
                </form>
            </div>
        </div>
        
        <?php if ($urgentCount > 0): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-radius:10px;font-size:.85rem;font-weight:500;margin-bottom:20px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#dc2626;">
            <i class="fas fa-exclamation-triangle"></i>
            You have <?php echo $urgentCount; ?> urgent announcement<?php echo $urgentCount>1?'s':''; ?> active
        </div>
        <?php endif; ?>
        
        <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <i class="fas fa-bullhorn"></i>
            <h3>No announcements found</h3>
            <p><?php echo !empty($searchQuery) ? 'Try a different search term' : 'Create your first announcement'; ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Announcement</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Target</th>
                        <th>Reach</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $ann): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                            <div class="announcement-preview">
                                <p><?php echo htmlspecialchars(substr($ann['content'], 0, 100)) . (strlen($ann['content']) > 100 ? '...' : ''); ?></p>
                            </div>
                            <?php if ($ann['coordinator_name']): ?>
                            <div class="stat-row" style="margin-top:8px;">
                                <i class="fas fa-user-tie"></i>
                                <?php echo htmlspecialchars($ann['coordinator_name']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $ann['type']; ?>">
                                <?php 
                                $icons = ['general'=>'info-circle','task'=>'tasks','deadline'=>'clock','certificate'=>'certificate'];
                                echo '<i class="fas fa-'.$icons[$ann['type']].'"></i> ';
                                echo ucfirst($ann['type']); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $ann['priority']; ?>">
                                <?php 
                                if ($ann['priority'] === 'urgent') echo '<i class="fas fa-exclamation-triangle"></i> ';
                                elseif ($ann['priority'] === 'important') echo '<i class="fas fa-star"></i> ';
                                echo ucfirst($ann['priority']); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($ann['target_all']): ?>
                            <span class="badge" style="background:rgba(59,130,246,0.12);color:#1d4ed8;">
                                <i class="fas fa-users"></i> All Students
                            </span>
                            <?php elseif ($ann['batch_id']): ?>
                            <span class="badge" style="background:rgba(168,85,247,0.12);color:#7c3aed;">
                                <i class="fas fa-layer-group"></i> Batch #<?php echo $ann['batch_id']; ?>
                            </span>
                            <?php else: ?>
                            <span style="color:var(--text3);">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $readPercentage = $ann['total_students'] > 0 ? round(($ann['total_reads'] / $ann['total_students']) * 100) : 0;
                            ?>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;background:var(--bg);height:8px;border-radius:4px;overflow:hidden;">
                                    <div style="width:<?php echo $readPercentage; ?>%;height:100%;background:var(--o5);transition:width .3s;"></div>
                                </div>
                                <strong style="font-size:.8rem;color:var(--o5);"><?php echo $readPercentage; ?>%</strong>
                            </div>
                            <small style="color:var(--text3);font-size:.75rem;">
                                <?php echo $ann['total_reads']; ?>/<?php echo $ann['total_students']; ?> read
                            </small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $ann['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $ann['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size:.8rem;">
                                <div style="color:var(--text);">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                                </div>
                                <small style="color:var(--text3);">
                                    <?php echo date('h:i A', strtotime($ann['created_at'])); ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-secondary btn-sm" onclick='viewAnnouncement(<?php echo json_encode($ann); ?>)' title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick='editAnnouncement(<?php echo json_encode($ann); ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($ann['is_active']): ?>
                                <button class="btn btn-warning btn-sm" onclick='toggleStatus(<?php echo $ann['id']; ?>, 0, "<?php echo htmlspecialchars($ann['title']); ?>")' title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-success btn-sm" onclick='toggleStatus(<?php echo $ann['id']; ?>, 1, "<?php echo htmlspecialchars($ann['title']); ?>")' title="Activate">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick='deleteAnnouncement(<?php echo $ann['id']; ?>, "<?php echo htmlspecialchars($ann['title']); ?>")' title="Delete">
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

<!-- Create/Edit Announcement Modal -->
<div id="announcementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title" id="modalTitle">Create New Announcement</div>
            <button class="modal-close" onclick="closeModal('announcementModal')">&times;</button>
        </div>
        <form method="POST" id="announcementForm">
            <div class="modal-body">
                <input type="hidden" name="announcement_id" id="announcement_id">
                
                <div class="form-group full">
                    <label class="form-label">Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" class="form-input" placeholder="Enter announcement title" required maxlength="255" oninput="updateCharCount('title', 255)">
                    <div class="char-counter" id="title-counter">0/255</div>
                </div>
                
                <div class="form-group full">
                    <label class="form-label">Content <span class="required">*</span></label>
                    <textarea name="content" id="content" class="form-textarea" placeholder="Enter announcement content" required oninput="updateCharCount('content', 5000)"></textarea>
                    <div class="char-counter" id="content-counter">0/5000</div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Type <span class="required">*</span></label>
                        <select name="type" id="type" class="form-select" required>
                            <option value="general">General</option>
                            <option value="task">Task</option>
                            <option value="deadline">Deadline</option>
                            <option value="certificate">Certificate</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority <span class="required">*</span></label>
                        <select name="priority" id="priority" class="form-select" required>
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="info-box">
                    <strong><i class="fas fa-users"></i> Target Audience</strong>
                    <p style="font-size:.8rem;color:var(--text2);margin-top:6px;">
                        Select who should receive this announcement
                    </p>
                </div>
                
                <div class="form-group full">
                    <label class="form-checkbox">
                        <input type="checkbox" name="target_all" id="target_all" checked onchange="toggleTargeting()">
                        <span>Send to all students</span>
                    </label>
                </div>
                
                <div class="form-grid" id="targetingOptions" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">Batch</label>
                        <select name="batch_id" id="batch_id" class="form-select">
                            <option value="">Select Batch (Optional)</option>
                            <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['batch_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Coordinator</label>
                        <select name="coordinator_id" id="coordinator_id" class="form-select">
                            <option value="">Select Coordinator (Optional)</option>
                            <?php foreach ($coordinators as $coord): ?>
                            <option value="<?php echo $coord['id']; ?>"><?php echo htmlspecialchars($coord['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group full">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        <span>Publish immediately (active)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
                <button type="submit" name="create_announcement" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> <span id="submitBtnText">Create Announcement</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Announcement Modal -->
<div id="viewAnnouncementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title">Announcement Details</div>
            <button class="modal-close" onclick="closeModal('viewAnnouncementModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="viewAnnouncementContent"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewAnnouncementModal')">Close</button>
        </div>
    </div>
</div>

<!-- Toggle Status Form (Hidden) -->
<form method="POST" id="toggleStatusForm" style="display:none;">
    <input type="hidden" name="announcement_id" id="toggle_announcement_id">
    <input type="hidden" name="is_active" id="toggle_is_active">
    <input type="hidden" name="toggle_status" value="1">
</form>

<!-- Delete Form (Hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="announcement_id" id="delete_announcement_id">
    <input type="hidden" name="delete_announcement" value="1">
</form>

<script>
    function closeModal(id){
        document.getElementById(id).classList.remove('active');
    }
    
    function openCreateModal(){
        document.getElementById('modalTitle').textContent='Create New Announcement';
        document.getElementById('announcement_id').value='';
        document.getElementById('announcementForm').reset();
        document.getElementById('submitBtn').name='create_announcement';
        document.getElementById('submitBtnText').textContent='Create Announcement';
        document.getElementById('target_all').checked=true;
        document.getElementById('is_active').checked=true;
        toggleTargeting();
        updateCharCount('title', 255);
        updateCharCount('content', 5000);
        document.getElementById('announcementModal').classList.add('active');
    }
    
    function editAnnouncement(ann){
        document.getElementById('modalTitle').textContent='Edit Announcement';
        document.getElementById('announcement_id').value=ann.id;
        document.getElementById('title').value=ann.title;
        document.getElementById('content').value=ann.content;
        document.getElementById('type').value=ann.type;
        document.getElementById('priority').value=ann.priority;
        document.getElementById('batch_id').value=ann.batch_id||'';
        document.getElementById('coordinator_id').value=ann.coordinator_id||'';
        document.getElementById('target_all').checked=ann.target_all==1;
        document.getElementById('is_active').checked=ann.is_active==1;
        document.getElementById('submitBtn').name='update_announcement';
        document.getElementById('submitBtnText').textContent='Update Announcement';
        toggleTargeting();
        updateCharCount('title', 255);
        updateCharCount('content', 5000);
        document.getElementById('announcementModal').classList.add('active');
    }
    
    function viewAnnouncement(ann){
        const typeIcons={'general':'info-circle','task':'tasks','deadline':'clock','certificate':'certificate'};
        const typeBadges={'general':'#1d4ed8','task':'#7c3aed','deadline':'#dc2626','certificate':'#16a34a'};
        const priorityBadges={'urgent':'#dc2626','important':'#ca8a04','normal':'#475569'};
        
        const content=`
            <div style="margin-bottom:20px;">
                <h2 style="font-size:1.4rem;font-weight:700;color:var(--text);margin-bottom:12px;">
                    ${ann.title}
                </h2>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                    <span class="badge" style="background:rgba(${typeBadges[ann.type]},0.12);color:${typeBadges[ann.type]};">
                        <i class="fas fa-${typeIcons[ann.type]}"></i> ${ann.type.charAt(0).toUpperCase()+ann.type.slice(1)}
                    </span>
                    <span class="badge" style="background:rgba(${priorityBadges[ann.priority]},0.12);color:${priorityBadges[ann.priority]};">
                        ${ann.priority.charAt(0).toUpperCase()+ann.priority.slice(1)} Priority
                    </span>
                    <span class="badge badge-${ann.is_active?'active':'inactive'}">
                        ${ann.is_active?'Active':'Inactive'}
                    </span>
                </div>
            </div>
            
            <div style="background:var(--bg);padding:18px;border-radius:10px;margin-bottom:20px;">
                <div style="font-size:.95rem;color:var(--text);line-height:1.7;white-space:pre-wrap;">
                    ${ann.content}
                </div>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:20px;">
                <div style="background:var(--bg);padding:14px;border-radius:8px;">
                    <div style="font-size:.75rem;color:var(--text3);margin-bottom:4px;">TARGET</div>
                    <div style="font-weight:600;color:var(--text);">
                        ${ann.target_all?'<i class="fas fa-users"></i> All Students':'<i class="fas fa-user-group"></i> Specific Group'}
                    </div>
                </div>
                <div style="background:var(--bg);padding:14px;border-radius:8px;">
                    <div style="font-size:.75rem;color:var(--text3);margin-bottom:4px;">READ STATUS</div>
                    <div style="font-weight:600;color:var(--text);">
                        ${ann.total_reads}/${ann.total_students} students
                    </div>
                </div>
            </div>
            
            ${ann.coordinator_name?`
            <div style="background:var(--o1);border:1px solid var(--o2);padding:12px;border-radius:8px;margin-bottom:16px;">
                <i class="fas fa-user-tie"></i> Coordinator: <strong>${ann.coordinator_name}</strong>
            </div>
            `:''}
            
            <div style="font-size:.8rem;color:var(--text3);padding-top:16px;border-top:1px solid var(--border);">
                <div><i class="fas fa-calendar-plus"></i> Created: ${new Date(ann.created_at).toLocaleString()}</div>
                <div style="margin-top:4px;"><i class="fas fa-calendar-check"></i> Updated: ${new Date(ann.updated_at).toLocaleString()}</div>
            </div>
        `;
        
        document.getElementById('viewAnnouncementContent').innerHTML=content;
        document.getElementById('viewAnnouncementModal').classList.add('active');
    }
    
    function toggleTargeting(){
        const targetAll=document.getElementById('target_all').checked;
        document.getElementById('targetingOptions').style.display=targetAll?'none':'grid';
    }
    
    function toggleStatus(announcementId, isActive, title){
        const action=isActive?'activate':'deactivate';
        if(confirm(`Are you sure you want to ${action} "${title}"?`)){
            document.getElementById('toggle_announcement_id').value=announcementId;
            document.getElementById('toggle_is_active').value=isActive;
            document.getElementById('toggleStatusForm').submit();
        }
    }
    
    function deleteAnnouncement(announcementId, title){
        if(confirm(`Are you sure you want to delete "${title}"?\n\nThis action cannot be undone and will remove all read records associated with this announcement.`)){
            document.getElementById('delete_announcement_id').value=announcementId;
            document.getElementById('deleteForm').submit();
        }
    }
    
    function updateCharCount(fieldId, maxLength){
        const field=document.getElementById(fieldId);
        const counter=document.getElementById(fieldId+'-counter');
        const length=field.value.length;
        counter.textContent=`${length}/${maxLength}`;
        if(length>maxLength*0.9){
            counter.style.color='var(--red)';
        }else if(length>maxLength*0.7){
            counter.style.color='var(--o5)';
        }else{
            counter.style.color='var(--text3)';
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