<?php
// admin_attendance_manage.php - Attendance Management Module
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

// Handle Attendance Marking (Domain-based)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $domainInterest = $_POST['domain_interest'] ?? '';
    $attendanceDate = $_POST['attendance_date'] ?? '';
    $attendanceData = $_POST['attendance'] ?? [];
    
    if (!$domainInterest || !$attendanceDate || empty($attendanceData)) {
        $error = 'Please select domain, date, and mark at least one student';
    } else {
        // Check if student_attendance table has date column, if not add it
        $columnCheck = $db->query("SHOW COLUMNS FROM student_attendance LIKE 'date'");
        if ($columnCheck->num_rows == 0) {
            // Add date column to student_attendance
            $db->query("ALTER TABLE student_attendance ADD COLUMN date DATE NULL AFTER batch_id");
            $db->query("ALTER TABLE student_attendance ADD COLUMN marked_by VARCHAR(100) NULL");
            // Modify status enum to include attendance statuses
            $db->query("ALTER TABLE student_attendance MODIFY status ENUM('active','inactive','completed','dropped','present','absent','late') DEFAULT 'active'");
        }
        
        $dateEsc = $db->real_escape_string($attendanceDate);
        $successCount = 0;
        
        foreach ($attendanceData as $studentId => $status) {
            $studentId = (int)$studentId;
            $statusEsc = $db->real_escape_string($status);
            
            // Check if attendance record exists for this student on this date
            $exists = $db->query("SELECT id FROM student_attendance WHERE student_id=$studentId AND date='$dateEsc'")->fetch_assoc();
            
            if ($exists) {
                // Update existing record
                $db->query("UPDATE student_attendance SET status='$statusEsc', enrolled_date=NOW() WHERE id={$exists['id']}");
            } else {
                // Insert new attendance record (batch_id can be NULL for daily attendance)
                $db->query("INSERT INTO student_attendance (student_id, batch_id, date, status, enrolled_date) 
                           VALUES ($studentId, NULL, '$dateEsc', '$statusEsc', NOW())");
            }
            $successCount++;
        }
        
        $_SESSION['admin_success'] = "Attendance marked for $successCount students on " . date('M d, Y', strtotime($attendanceDate));
        header('Location: admin.php#tab-attendance');
        exit;
    }
}

// Get Unique Domain Interests
$domainsRes = $db->query("SELECT DISTINCT domain_interest FROM internship_students WHERE is_active=1 AND domain_interest IS NOT NULL AND domain_interest != '' ORDER BY domain_interest");
$domains = [];
while ($row = $domainsRes->fetch_assoc()) {
    if (!empty($row['domain_interest'])) {
        $domains[] = $row['domain_interest'];
    }
}

// Get Students by Selected Domain
$selectedDomain = $_GET['domain'] ?? '';
$domainStudents = [];
if ($selectedDomain) {
    $domainEsc = $db->real_escape_string($selectedDomain);
    $domainStudentsRes = $db->query("SELECT s.id, s.full_name, s.email, s.domain_interest
        FROM internship_students s
        WHERE s.is_active=1 AND s.domain_interest='$domainEsc'
        ORDER BY s.full_name");
    while ($row = $domainStudentsRes->fetch_assoc()) $domainStudents[] = $row;
}

$todayDate = date('Y-m-d');
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
    .form-input,.form-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:.875rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;background:var(--card);}
    .form-input:focus,.form-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
    .empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
    .empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
    .empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
    .ar-btn{padding:6px 12px;border:1.5px solid var(--border);background:var(--card);border-radius:7px;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit;}
    .ar-btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.1);}
    .ar-btn.selected-present{background:rgba(34,197,94,0.15);border-color:#22c55e;color:#16a34a;}
    .ar-btn.selected-absent{background:rgba(239,68,68,0.15);border-color:#ef4444;color:#dc2626;}
    .ar-btn.selected-late{background:rgba(234,179,8,0.15);border-color:#eab308;color:#854d0e;}
    .domain-selector{background:var(--o1);border:1px solid var(--o2);border-radius:10px;padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
    .domain-selector label{font-weight:700;font-size:.875rem;}
    .domain-selector select{max-width:350px;}
</style>

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-calendar-check"></i>Mark Attendance</div>
    </div>
    <div class="section-body">
        <?php if ($error): ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:20px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
            <i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="domain-selector">
            <label><i class="fas fa-lightbulb"></i> Select Domain:</label>
            <select class="form-select" onchange="window.location.href='?domain='+encodeURIComponent(this.value)+'#tab-attendance'">
                <option value="">Choose a domain...</option>
                <?php foreach ($domains as $domain): ?>
                <option value="<?php echo htmlspecialchars($domain); ?>" <?php echo $selectedDomain === $domain ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($domain); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($selectedDomain && !empty($domainStudents)): ?>
        <form method="POST">
            <input type="hidden" name="mark_attendance" value="1">
            <input type="hidden" name="domain_interest" value="<?php echo htmlspecialchars($selectedDomain); ?>">
            
            <div class="form-group">
                <label class="form-label"><i class="fas fa-calendar"></i> Attendance Date <span class="required">*</span></label>
                <input type="date" name="attendance_date" class="form-input" value="<?php echo $todayDate; ?>" required style="max-width:250px;">
            </div>
            
            <div style="display:flex;gap:8px;margin:16px 0;flex-wrap:wrap;">
                <button type="button" class="btn btn-sm" style="background:rgba(34,197,94,0.1);border:1.5px solid rgba(34,197,94,0.25);color:#22c55e;" onclick="markAllStatus('present')">
                    <i class="fas fa-check-circle"></i> Mark All Present
                </button>
                <button type="button" class="btn btn-sm" style="background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.25);color:#ef4444;" onclick="markAllStatus('absent')">
                    <i class="fas fa-times-circle"></i> Mark All Absent
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllStatus()">
                    <i class="fas fa-eraser"></i> Clear All
                </button>
            </div>
            
            <h3 style="font-size:.95rem;font-weight:700;margin-bottom:16px;">
                <i class="fas fa-users"></i> Students in <?php echo htmlspecialchars($selectedDomain); ?> (<?php echo count($domainStudents); ?>)
            </h3>
            
            <?php foreach ($domainStudents as $student): ?>
            <div style="display:grid;grid-template-columns:1fr auto;align-items:center;padding:14px;border:1px solid var(--border);border-radius:10px;margin-bottom:12px;background:var(--bg);gap:12px;">
                <div>
                    <div style="font-weight:700;font-size:.9rem;"><?php echo htmlspecialchars($student['full_name']); ?></div>
                    <div style="font-size:.75rem;color:var(--text3);"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="ar-btn" data-student="<?php echo $student['id']; ?>" data-status="present" onclick="selectStatus(this)">
                        <i class="fas fa-check"></i> Present
                    </button>
                    <button type="button" class="ar-btn" data-student="<?php echo $student['id']; ?>" data-status="absent" onclick="selectStatus(this)">
                        <i class="fas fa-times"></i> Absent
                    </button>
                    <button type="button" class="ar-btn" data-student="<?php echo $student['id']; ?>" data-status="late" onclick="selectStatus(this)">
                        <i class="fas fa-clock"></i> Late
                    </button>
                </div>
                <input type="hidden" name="attendance[<?php echo $student['id']; ?>]" id="status-<?php echo $student['id']; ?>" value="">
            </div>
            <?php endforeach; ?>
            
            <div style="margin-top:24px;display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary" onclick="return validateAttendance()">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearAllStatus()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
        <?php elseif ($selectedDomain): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No students found</h3>
            <p>There are no active students in this domain</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-lightbulb"></i>
            <h3>Select a domain</h3>
            <p>Choose a domain from the dropdown above to mark attendance</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function selectStatus(btn){
        const studentId=btn.dataset.student;
        const status=btn.dataset.status;
        const inputField=document.getElementById('status-'+studentId);
        const allBtns=document.querySelectorAll(`[data-student="${studentId}"]`);
        
        allBtns.forEach(b=>{
            b.classList.remove('selected-present','selected-absent','selected-late');
        });
        
        if(inputField.value===status){
            inputField.value='';
        }else{
            inputField.value=status;
            btn.classList.add('selected-'+status);
        }
    }
    
    function markAllStatus(status){
        const allInputs=document.querySelectorAll('[name^="attendance["]');
        allInputs.forEach(input=>{
            const studentId=input.id.replace('status-','');
            input.value=status;
            const allBtns=document.querySelectorAll(`[data-student="${studentId}"]`);
            allBtns.forEach(btn=>{
                btn.classList.remove('selected-present','selected-absent','selected-late');
                if(btn.dataset.status===status){
                    btn.classList.add('selected-'+status);
                }
            });
        });
    }
    
    function clearAllStatus(){
        const allInputs=document.querySelectorAll('[name^="attendance["]');
        allInputs.forEach(input=>{
            input.value='';
        });
        document.querySelectorAll('.ar-btn').forEach(btn=>{
            btn.classList.remove('selected-present','selected-absent','selected-late');
        });
    }
    
    function validateAttendance(){
        const allInputs=document.querySelectorAll('[name^="attendance["]');
        let hasSelection=false;
        allInputs.forEach(input=>{
            if(input.value!==''){
                hasSelection=true;
            }
        });
        if(!hasSelection){
            alert('Please mark attendance for at least one student before saving.');
            return false;
        }
        return confirm('Save attendance for the selected date?');
    }
</script>