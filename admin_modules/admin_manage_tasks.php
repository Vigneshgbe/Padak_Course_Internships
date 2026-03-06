<?php
// admin_manage_tasks.php - Task Management Module
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

// Handle Task Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $taskType = $_POST['task_type'] ?? 'individual';
    $priority = $_POST['priority'] ?? 'medium';
    $maxPoints = (int)($_POST['max_points'] ?? 100);
    $dueDate = $_POST['due_date'] ?? '';
    $resourcesUrl = trim($_POST['resources_url'] ?? '');
    $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;

    if (empty($title)) {
        $_SESSION['admin_error'] = 'Task title is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $descEsc = $db->real_escape_string($desc);
        $resEsc = $db->real_escape_string($resourcesUrl);
        $dueDateValue = $dueDate ? "'" . $db->real_escape_string($dueDate) . "'" : 'NULL';
        $assignedValue = $assignedTo ? $assignedTo : 'NULL';
        $sql = "INSERT INTO internship_tasks 
                (title, description, task_type, priority, max_points, due_date, resources_url, assigned_to_student, status, created_by, created_at)
                VALUES ('$titleEsc', '$descEsc', '$taskType', '$priority', $maxPoints, $dueDateValue, '$resEsc', $assignedValue, 'active', 'Admin', NOW())";
        if ($db->query($sql)) {
            $_SESSION['admin_success'] = 'Task created successfully!';
        } else {
            $_SESSION['admin_error'] = 'Failed to create task: ' . $db->error;
        }
    }
    header('Location: admin.php?tab=tasks');
    exit;
}

// Handle Task Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $taskId = (int)$_POST['task_id'];
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $taskType = $_POST['task_type'] ?? 'individual';
    $priority = $_POST['priority'] ?? 'medium';
    $maxPoints = (int)($_POST['max_points'] ?? 100);
    $dueDate = $_POST['due_date'] ?? '';
    $resourcesUrl = trim($_POST['resources_url'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;

    if (empty($title)) {
        $_SESSION['admin_error'] = 'Task title is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $descEsc = $db->real_escape_string($desc);
        $resEsc = $db->real_escape_string($resourcesUrl);
        $dueDateValue = $dueDate ? "'" . $db->real_escape_string($dueDate) . "'" : 'NULL';
        $assignedValue = $assignedTo ? $assignedTo : 'NULL';
        $sql = "UPDATE internship_tasks SET
                title='$titleEsc', description='$descEsc', task_type='$taskType',
                priority='$priority', max_points=$maxPoints, due_date=$dueDateValue,
                resources_url='$resEsc', status='$status', assigned_to_student=$assignedValue,
                updated_at=NOW() WHERE id=$taskId";
        if ($db->query($sql)) {
            $_SESSION['admin_success'] = 'Task updated successfully!';
        } else {
            $_SESSION['admin_error'] = 'Failed to update task: ' . $db->error;
        }
    }
    header('Location: admin.php?tab=tasks');
    exit;
}

// Get Filter Status
$filterStatus = $_GET['filter_tasks'] ?? 'active';
$filterStatusEsc = $db->real_escape_string($filterStatus);
$whereClause = $filterStatus === 'all' ? "1=1" : "t.status='$filterStatusEsc'";

$activeCount = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_tasks WHERE status='active'")->fetch_assoc()['cnt'];
$draftCount  = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_tasks WHERE status='draft'")->fetch_assoc()['cnt'];
$closedCount = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_tasks WHERE status='closed'")->fetch_assoc()['cnt'];
$allCount    = (int)$db->query("SELECT COUNT(*) as cnt FROM internship_tasks")->fetch_assoc()['cnt'];

$tasksRes = $db->query("SELECT t.*,
    COUNT(DISTINCT ts.id) as submission_count,
    SUM(CASE WHEN ts.status IN ('submitted','under_review') THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN ts.status='approved' THEN 1 ELSE 0 END) as approved_count
    FROM internship_tasks t
    LEFT JOIN task_submissions ts ON ts.task_id = t.id
    WHERE $whereClause
    GROUP BY t.id
    ORDER BY t.created_at DESC");
$tasks = [];
while ($row = $tasksRes->fetch_assoc()) $tasks[] = $row;

$studentsRes = $db->query("SELECT id, full_name, email FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) $students[] = $row;
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
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
.form-group{margin-bottom:18px;}
.form-group.full{grid-column:1/-1;}
.form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
.form-label .required{color:var(--red);}
.form-input,.form-textarea,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
.form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.form-textarea{resize:vertical;min-height:100px;}
.form-hint{font-size:.73rem;color:var(--text3);margin-top:5px;}
.table-responsive{overflow-x:auto;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border);}
.data-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.85rem;color:var(--text2);}
.data-table tr:hover{background:var(--bg);}
.data-table td:first-child{font-weight:600;color:var(--text);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.badge-active{background:rgba(34,197,94,0.12);color:#16a34a;}
.badge-draft{background:rgba(234,179,8,0.12);color:#854d0e;}
.badge-closed{background:rgba(100,116,139,0.12);color:#475569;}
.badge-urgent{background:rgba(239,68,68,0.12);color:#dc2626;}
.badge-high{background:rgba(249,115,22,0.12);color:var(--o6);}
.badge-medium{background:rgba(234,179,8,0.12);color:#854d0e;}
.badge-low{background:rgba(34,197,94,0.12);color:#16a34a;}
.badge-individual{background:rgba(59,130,246,0.12);color:#1d4ed8;}
.badge-team{background:rgba(139,92,246,0.12);color:#6d28d9;}
.tasks-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
.tasks-modal.active{display:flex;}
.tasks-modal-content{background:var(--card);border-radius:16px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.tasks-modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.tmh-title{font-size:1.2rem;font-weight:700;color:var(--text);}
.tasks-modal-close{background:none;border:none;font-size:1.5rem;color:var(--text3);cursor:pointer;padding:4px 8px;transition:color .2s;line-height:1;}
.tasks-modal-close:hover{color:var(--red);}
.tasks-modal-body{padding:24px;}
.tasks-modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}
.empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
.empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
.empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
.filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
.filter-btn{padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--card);font-size:.8rem;font-weight:500;color:var(--text2);cursor:pointer;text-decoration:none;transition:all .2s;}
.filter-btn:hover{border-color:var(--o5);color:var(--o5);}
.filter-btn.active{background:var(--o5);border-color:var(--o5);color:#fff;}
@media(max-width:768px){.form-grid{grid-template-columns:1fr;}}
</style>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-clipboard-list"></i>All Tasks</div>
        <button class="btn btn-primary" onclick="tasksOpenCreateModal()"><i class="fas fa-plus"></i> Create New Task</button>
    </div>
    <div class="section-body">
        <div class="filter-bar">
            <button class="filter-btn <?php echo $filterStatus==='active'?'active':''; ?>" onclick="tasksSetFilter('active')">Active (<?php echo $activeCount; ?>)</button>
            <button class="filter-btn <?php echo $filterStatus==='draft'?'active':''; ?>" onclick="tasksSetFilter('draft')">Draft (<?php echo $draftCount; ?>)</button>
            <button class="filter-btn <?php echo $filterStatus==='closed'?'active':''; ?>" onclick="tasksSetFilter('closed')">Closed (<?php echo $closedCount; ?>)</button>
            <button class="filter-btn <?php echo $filterStatus==='all'?'active':''; ?>" onclick="tasksSetFilter('all')">All Tasks (<?php echo $allCount; ?>)</button>
        </div>

        <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No <?php echo $filterStatus !== 'all' ? $filterStatus : ''; ?> tasks found</h3>
            <p><?php
                if ($filterStatus === 'draft') echo 'Save tasks as draft to see them here';
                elseif ($filterStatus === 'closed') echo 'Close tasks to see them here';
                else echo 'Create your first task to get started';
            ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task Title</th><th>Type</th><th>Priority</th><th>Points</th>
                        <th>Due Date</th><th>Status</th><th>Submissions</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task):
                        $dueDateFmt = $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '—';
                        $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time();
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                            <?php if ($task['assigned_to_student']): ?>
                            <br><small style="color:var(--text3);"><i class="fas fa-user fa-xs"></i> Assigned to specific student</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $task['task_type']; ?>"><?php echo ucfirst($task['task_type']); ?></span></td>
                        <td><span class="badge badge-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                        <td><?php echo $task['max_points']; ?> pts</td>
                        <td style="<?php echo $isOverdue?'color:var(--red);font-weight:600;':''; ?>">
                            <?php echo $dueDateFmt; ?>
                            <?php if ($isOverdue): ?><br><small><i class="fas fa-triangle-exclamation"></i> Overdue</small><?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $task['status']; ?>"><?php echo ucfirst($task['status']); ?></span></td>
                        <td>
                            <?php if ($task['submission_count'] > 0): ?>
                                <strong><?php echo $task['submission_count']; ?></strong> total
                                <?php if ($task['pending_count'] > 0): ?>
                                <br><small style="color:var(--blue);"><i class="fas fa-hourglass-half"></i> <?php echo $task['pending_count']; ?> pending</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text3);">No submissions</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="tasksEditTask(<?php echo (int)$task['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script type="application/json" id="tasksDataStore">
<?php echo json_encode(array_map(function($t) {
    return [
        'id'                  => (int)$t['id'],
        'title'               => $t['title'],
        'description'         => $t['description'] ?? '',
        'task_type'           => $t['task_type'],
        'priority'            => $t['priority'],
        'max_points'          => (int)$t['max_points'],
        'due_date'            => $t['due_date'] ? substr($t['due_date'], 0, 10) : '',
        'resources_url'       => $t['resources_url'] ?? '',
        'assigned_to_student' => $t['assigned_to_student'] ?? '',
        'status'              => $t['status'],
    ];
}, $tasks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>
</script>

<!-- Task Modal -->
<div id="tasksModal" class="tasks-modal" role="dialog" aria-modal="true">
    <div class="tasks-modal-content">
        <div class="tasks-modal-header">
            <div class="tmh-title" id="tasksModalTitle">Create New Task</div>
            <button type="button" class="tasks-modal-close" id="tasksModalCloseBtn">&times;</button>
        </div>
        <form method="POST" action="admin.php" id="tasksForm">
            <div class="tasks-modal-body">
                <input type="hidden" name="task_id" id="tasks_task_id">
                <input type="hidden" name="filter_tasks" id="tasks_filter_hidden" value="<?php echo htmlspecialchars($filterStatus); ?>">
                <div class="form-group">
                    <label class="form-label">Task Title <span class="required">*</span></label>
                    <input type="text" name="title" id="tasks_title" class="form-input" placeholder="e.g., Build a React Calculator App" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="tasks_description" class="form-textarea" placeholder="Detailed task requirements..."></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Task Type</label>
                        <select name="task_type" id="tasks_type" class="form-select">
                            <option value="individual">Individual</option>
                            <option value="team">Team</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" id="tasks_priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Points</label>
                        <input type="number" name="max_points" id="tasks_points" class="form-input" value="100" min="0" step="10">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="tasks_due_date" class="form-input">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Resources URL <span style="font-weight:400;color:var(--text3);">(optional)</span></label>
                        <input type="url" name="resources_url" id="tasks_resources" class="form-input" placeholder="https://docs.example.com/task-guide">
                        <div class="form-hint">Link to documentation, tutorials, or reference materials</div>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Assign to Student <span style="font-weight:400;color:var(--text3);">(optional)</span></label>
                        <select name="assigned_to_student" id="tasks_assigned" class="form-select">
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full" id="tasksStatusGroup" style="display:none;">
                        <label class="form-label">Status</label>
                        <select name="status" id="tasks_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="tasks-modal-footer">
                <button type="button" class="btn btn-secondary" id="tasksModalCancelBtn">Cancel</button>
                <button type="submit" name="create_task" id="tasksSubmitBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Task
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var tasksData = {};
    try {
        JSON.parse(document.getElementById('tasksDataStore').textContent)
            .forEach(function(t) { tasksData[t.id] = t; });
    } catch(e) {}

    function tasksCloseModal() {
        document.getElementById('tasksModal').classList.remove('active');
    }

    window.tasksOpenCreateModal = function() {
        document.getElementById('tasksModalTitle').textContent = 'Create New Task';
        document.getElementById('tasksForm').reset();
        document.getElementById('tasks_task_id').value = '';
        document.getElementById('tasksSubmitBtn').innerHTML = '<i class="fas fa-plus"></i> Create Task';
        document.getElementById('tasksSubmitBtn').name = 'create_task';
        document.getElementById('tasksStatusGroup').style.display = 'none';
        document.getElementById('tasksModal').classList.add('active');
    };

    window.tasksEditTask = function(taskId) {
        var task = tasksData[taskId];
        if (!task) return;
        document.getElementById('tasksModalTitle').textContent = 'Edit Task';
        document.getElementById('tasks_task_id').value = task.id;
        document.getElementById('tasks_title').value = task.title;
        document.getElementById('tasks_description').value = task.description;
        document.getElementById('tasks_type').value = task.task_type;
        document.getElementById('tasks_priority').value = task.priority;
        document.getElementById('tasks_points').value = task.max_points;
        document.getElementById('tasks_due_date').value = task.due_date;
        document.getElementById('tasks_resources').value = task.resources_url;
        document.getElementById('tasks_assigned').value = task.assigned_to_student || '';
        document.getElementById('tasks_status').value = task.status;
        document.getElementById('tasksSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Task';
        document.getElementById('tasksSubmitBtn').name = 'update_task';
        document.getElementById('tasksStatusGroup').style.display = 'block';
        document.getElementById('tasksModal').classList.add('active');
    };

    window.tasksSetFilter = function(val) {
        var u = new URL(window.location.href);
        u.searchParams.set('filter_tasks', val);
        u.searchParams.set('tab', 'tasks');
        u.hash = '';
        window.location.href = u.pathname + u.search;
    };

    document.getElementById('tasksModalCloseBtn').addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation(); tasksCloseModal();
    });
    document.getElementById('tasksModalCancelBtn').addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation(); tasksCloseModal();
    });
    document.getElementById('tasksModal').addEventListener('click', function(e) {
        if (e.target === this) tasksCloseModal();
    });
})();
</script>