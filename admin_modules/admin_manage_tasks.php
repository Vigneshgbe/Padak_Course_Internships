<?php
// Admin Manage Tasks Module
// This file handles task creation, editing, and listing

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
        $error = 'Task title is required';
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
            $success = 'Task created successfully!';
        } else {
            $error = 'Failed to create task: ' . $db->error;
        }
    }
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
        $error = 'Task title is required';
    } else {
        $titleEsc = $db->real_escape_string($title);
        $descEsc = $db->real_escape_string($desc);
        $resEsc = $db->real_escape_string($resourcesUrl);
        $dueDateValue = $dueDate ? "'" . $db->real_escape_string($dueDate) . "'" : 'NULL';
        $assignedValue = $assignedTo ? $assignedTo : 'NULL';
        
        $sql = "UPDATE internship_tasks SET
                title='$titleEsc',
                description='$descEsc',
                task_type='$taskType',
                priority='$priority',
                max_points=$maxPoints,
                due_date=$dueDateValue,
                resources_url='$resEsc',
                status='$status',
                assigned_to_student=$assignedValue,
                updated_at=NOW()
                WHERE id=$taskId";
        
        if ($db->query($sql)) {
            $success = 'Task updated successfully!';
        } else {
            $error = 'Failed to update task: ' . $db->error;
        }
    }
}

// Get Tasks with filter
$filterStatus = $_GET['filter'] ?? 'active';
$whereClause = $filterStatus === 'all' ? "1=1" : "t.status='$filterStatus'";

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

// Get All Active Students for assignment dropdown
$studentsRes = $db->query("SELECT id, full_name, email FROM internship_students WHERE is_active=1 ORDER BY full_name");
$students = [];
while ($row = $studentsRes->fetch_assoc()) $students[] = $row;
?>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-clipboard-list"></i>All Tasks</div>
        <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Create New Task</button>
    </div>
    <div class="section-body">
        <div class="filter-bar">
            <a href="?tab=tasks&filter=active" class="filter-btn <?php echo $filterStatus==='active'?'active':''; ?>">
                Active (<?php echo count(array_filter($tasks, fn($t)=>$t['status']==='active')); ?>)
            </a>
            <a href="?tab=tasks&filter=archived" class="filter-btn <?php echo $filterStatus==='archived'?'active':''; ?>">Archived</a>
            <a href="?tab=tasks&filter=all" class="filter-btn <?php echo $filterStatus==='all'?'active':''; ?>">All Tasks</a>
        </div>
        
        <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No tasks found</h3>
            <p>Create your first task to get started</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task Title</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Points</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Submissions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): 
                        $dueDate = $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '—';
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
                            <?php echo $dueDate; ?>
                            <?php if ($isOverdue): ?>
                            <br><small><i class="fas fa-triangle-exclamation"></i> Overdue</small>
                            <?php endif; ?>
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
                            <button class="btn btn-secondary btn-sm" onclick='editTask(<?php echo json_encode($task); ?>)'>
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

<!-- Task Modal -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="mh-title" id="modalTitle">Create New Task</div>
            <button class="modal-close" onclick="closeModal('taskModal')">&times;</button>
        </div>
        <form method="POST" id="taskForm">
            <div class="modal-body">
                <input type="hidden" name="task_id" id="task_id">
                
                <div class="form-group">
                    <label class="form-label">Task Title <span class="required">*</span></label>
                    <input type="text" name="title" id="task_title" class="form-input" placeholder="e.g., Build a React Calculator App" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="task_description" class="form-textarea" placeholder="Detailed task requirements, deliverables, and instructions..."></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Task Type</label>
                        <select name="task_type" id="task_type" class="form-select">
                            <option value="individual">Individual</option>
                            <option value="team">Team</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" id="task_priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Max Points</label>
                        <input type="number" name="max_points" id="task_points" class="form-input" value="100" min="0" step="10">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="task_due_date" class="form-input">
                    </div>
                    
                    <div class="form-group full">
                        <label class="form-label">Resources URL <span style="font-weight:400;color:var(--text3);">(optional)</span></label>
                        <input type="url" name="resources_url" id="task_resources" class="form-input" placeholder="https://docs.example.com/task-guide">
                        <div class="form-hint">Link to documentation, tutorials, or reference materials</div>
                    </div>
                    
                    <div class="form-group full">
                        <label class="form-label">Assign to Student <span style="font-weight:400;color:var(--text3);">(optional - leave blank for all students)</span></label>
                        <select name="assigned_to_student" id="task_assigned" class="form-select">
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full" id="statusGroup" style="display:none;">
                        <label class="form-label">Status</label>
                        <select name="status" id="task_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('taskModal')">Cancel</button>
                <button type="submit" name="create_task" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Task
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create New Task';
    document.getElementById('taskForm').reset();
    document.getElementById('task_id').value = '';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-plus"></i> Create Task';
    document.getElementById('submitBtn').name = 'create_task';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('taskModal').classList.add('active');
}

function editTask(task) {
    document.getElementById('modalTitle').textContent = 'Edit Task';
    document.getElementById('task_id').value = task.id;
    document.getElementById('task_title').value = task.title;
    document.getElementById('task_description').value = task.description || '';
    document.getElementById('task_type').value = task.task_type;
    document.getElementById('task_priority').value = task.priority;
    document.getElementById('task_points').value = task.max_points;
    document.getElementById('task_due_date').value = task.due_date ? task.due_date.split(' ')[0] : '';
    document.getElementById('task_resources').value = task.resources_url || '';
    document.getElementById('task_assigned').value = task.assigned_to_student || '';
    document.getElementById('task_status').value = task.status;
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Task';
    document.getElementById('submitBtn').name = 'update_task';
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('taskModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on outside click
document.getElementById('taskModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('active');
    }
});
</script>