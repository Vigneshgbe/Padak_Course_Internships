<?php
// Admin Attendance Management Module
// This file handles domain-based attendance marking

// Handle Attendance Marking
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
            $db->query("ALTER TABLE student_attendance ADD COLUMN date DATE NULL AFTER batch_id");
            $db->query("ALTER TABLE student_attendance ADD COLUMN marked_by VARCHAR(100) NULL");
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
                // Insert new attendance record
                $db->query("INSERT INTO student_attendance (student_id, batch_id, date, status, enrolled_date) 
                           VALUES ($studentId, NULL, '$dateEsc', '$statusEsc', NOW())");
            }
            $successCount++;
        }
        
        $success = "Attendance marked for $successCount students on " . date('M d, Y', strtotime($attendanceDate));
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

<div class="section">
    <div class="section-header">
        <div class="sh-title"><i class="fas fa-calendar-check"></i>Mark Attendance</div>
    </div>
    <div class="section-body">
        <div class="domain-selector">
            <label><i class="fas fa-lightbulb"></i> Select Domain:</label>
            <select class="form-select" onchange="window.location.href='?tab=attendance&domain='+encodeURIComponent(this.value)">
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
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
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
            
            <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;">
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
function selectStatus(btn) {
    const studentId = btn.dataset.student;
    const status = btn.dataset.status;
    const inputField = document.getElementById('status-' + studentId);
    const allBtns = document.querySelectorAll(`[data-student="${studentId}"]`);
    
    // Remove all selections for this student
    allBtns.forEach(b => {
        b.classList.remove('selected-present', 'selected-absent', 'selected-late');
    });
    
    // Toggle selection
    if (inputField.value === status) {
        inputField.value = '';
    } else {
        inputField.value = status;
        btn.classList.add('selected-' + status);
    }
}

function markAllStatus(status) {
    const allInputs = document.querySelectorAll('[name^="attendance["]');
    allInputs.forEach(input => {
        const studentId = input.id.replace('status-', '');
        input.value = status;
        
        const allBtns = document.querySelectorAll(`[data-student="${studentId}"]`);
        allBtns.forEach(btn => {
            btn.classList.remove('selected-present', 'selected-absent', 'selected-late');
            if (btn.dataset.status === status) {
                btn.classList.add('selected-' + status);
            }
        });
    });
}

function clearAllStatus() {
    const allInputs = document.querySelectorAll('[name^="attendance["]');
    allInputs.forEach(input => {
        input.value = '';
    });
    
    document.querySelectorAll('.ar-btn').forEach(btn => {
        btn.classList.remove('selected-present', 'selected-absent', 'selected-late');
    });
}

function validateAttendance() {
    const allInputs = document.querySelectorAll('[name^="attendance["]');
    let hasSelection = false;
    
    allInputs.forEach(input => {
        if (input.value !== '') {
            hasSelection = true;
        }
    });
    
    if (!hasSelection) {
        alert('Please mark attendance for at least one student before saving.');
        return false;
    }
    
    return confirm('Save attendance for the selected date?');
}
</script>