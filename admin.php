<?php
ob_start();
session_start();
require_once 'config.php';

$db = getPadakDB();

// ── EARLY POST HANDLERS (must run before any HTML output) ────────────────
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {

    // --- Create Task ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $taskType   = $_POST['task_type'] ?? 'individual';
        $priority   = $_POST['priority'] ?? 'medium';
        $maxPoints  = (int)($_POST['max_points'] ?? 100);
        $dueDate    = $_POST['due_date'] ?? '';
        $resUrl     = trim($_POST['resources_url'] ?? '');
        $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;
        if (empty($title)) {
            $_SESSION['admin_error'] = 'Task title is required';
        } else {
            $tE = $db->real_escape_string($title);
            $dE = $db->real_escape_string($desc);
            $rE = $db->real_escape_string($resUrl);
            $dv = $dueDate ? "'".$db->real_escape_string($dueDate)."'" : 'NULL';
            $av = $assignedTo ?: 'NULL';
            $sql = "INSERT INTO internship_tasks (title,description,task_type,priority,max_points,due_date,resources_url,assigned_to_student,status,created_by,created_at)
                    VALUES ('$tE','$dE','$taskType','$priority',$maxPoints,$dv,'$rE',$av,'active','Admin',NOW())";
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'Task created successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to create task: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=tasks');
        exit;
    }

    // --- Update Task ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $taskId     = (int)$_POST['task_id'];
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $taskType   = $_POST['task_type'] ?? 'individual';
        $priority   = $_POST['priority'] ?? 'medium';
        $maxPoints  = (int)($_POST['max_points'] ?? 100);
        $dueDate    = $_POST['due_date'] ?? '';
        $resUrl     = trim($_POST['resources_url'] ?? '');
        $status     = $_POST['status'] ?? 'active';
        $assignedTo = !empty($_POST['assigned_to_student']) ? (int)$_POST['assigned_to_student'] : null;
        if (empty($title)) {
            $_SESSION['admin_error'] = 'Task title is required';
        } else {
            $tE = $db->real_escape_string($title);
            $dE = $db->real_escape_string($desc);
            $rE = $db->real_escape_string($resUrl);
            $dv = $dueDate ? "'".$db->real_escape_string($dueDate)."'" : 'NULL';
            $av = $assignedTo ?: 'NULL';
            $sql = "UPDATE internship_tasks SET
                    title='$tE', description='$dE', task_type='$taskType',
                    priority='$priority', max_points=$maxPoints, due_date=$dv,
                    resources_url='$rE', status='$status', assigned_to_student=$av,
                    updated_at=NOW() WHERE id=$taskId";
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'Task updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update task: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=tasks');
        exit;
    }

    // --- Review Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submission'])) {
        $subId        = (int)$_POST['submission_id'];
        $reviewStatus = $db->real_escape_string($_POST['review_status'] ?? 'under_review');
        $pointsEarned = isset($_POST['points_earned']) && $_POST['points_earned'] !== '' ? (int)$_POST['points_earned'] : null;
        $feedback     = $db->real_escape_string(trim($_POST['feedback'] ?? ''));
        $pointsValue  = $pointsEarned !== null ? $pointsEarned : 'NULL';

        $sql = "UPDATE task_submissions SET
                status='$reviewStatus', points_earned=$pointsValue,
                feedback='$feedback', reviewed_at=NOW(), reviewed_by='Admin'
                WHERE id=$subId";

        if ($db->query($sql)) {
            $subData = $db->query("SELECT student_id, task_id FROM task_submissions WHERE id=$subId")->fetch_assoc();
            if ($subData) {
                $studentId = (int)$subData['student_id'];
                $taskId    = (int)$subData['task_id'];
                $taskData  = $db->query("SELECT title FROM internship_tasks WHERE id=$taskId")->fetch_assoc();
                $taskTitle = $db->real_escape_string($taskData['title'] ?? 'Your task');

                if ($reviewStatus === 'approved' && $pointsEarned !== null && $pointsEarned > 0) {
                    $reasonEsc = $db->real_escape_string("Earned from task: " . ($taskData['title'] ?? ''));
                    $db->query("INSERT INTO student_points_log (student_id, points, reason, task_id, awarded_at)
                               VALUES ($studentId, $pointsEarned, '$reasonEsc', $taskId, NOW())");
                    $totalRes = $db->query("SELECT SUM(points) as total FROM student_points_log WHERE student_id=$studentId");
                    $total    = $totalRes ? (int)$totalRes->fetch_assoc()['total'] : 0;
                    $db->query("UPDATE internship_students SET total_points=$total WHERE id=$studentId");
                    $_SESSION['admin_success'] = "Submission approved! $pointsEarned points awarded.";
                } else {
                    $_SESSION['admin_success'] = 'Submission reviewed successfully!';
                }

                $notifMsg = $reviewStatus === 'approved'
                    ? "Your submission for \"$taskTitle\" has been approved! You earned $pointsEarned points."
                    : "Your submission for \"$taskTitle\" requires revision. Check feedback.";
                $notifMsgEsc = $db->real_escape_string($notifMsg);
                $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                           VALUES ($studentId, 'Submission Reviewed', '$notifMsgEsc', 'task', NOW())");
            }
        } else {
            $_SESSION['admin_error'] = 'Failed to review submission: ' . $db->error;
        }
        ob_end_clean();
        header('Location: admin.php?tab=reviews');
        exit;
    }

    // --- Update Single Submission Status (All Submissions tab) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_submission_status'])) {
        $submissionId = (int)$_POST['submission_id'];
        $newStatus    = $db->real_escape_string(trim($_POST['status'] ?? ''));
        $feedback     = $db->real_escape_string(trim($_POST['feedback'] ?? ''));
        $pointsEarned = (isset($_POST['points_earned']) && $_POST['points_earned'] !== '') ? (int)$_POST['points_earned'] : null;
        $reviewedBy   = $db->real_escape_string(trim($_SESSION['admin_username'] ?? 'Admin'));

        $allowed = ['submitted','under_review','approved','rejected','revision_requested'];
        if (in_array($newStatus, $allowed)) {
            $pointsSQL = ($newStatus === 'approved' && $pointsEarned !== null) ? ", points_earned=$pointsEarned" : '';
            $sql = "UPDATE task_submissions SET status='$newStatus', feedback='$feedback',
                    reviewed_by='$reviewedBy', reviewed_at=NOW() $pointsSQL, updated_at=NOW()
                    WHERE id=$submissionId";
            if ($db->query($sql)) {
                $subRow = $db->query("SELECT student_id FROM task_submissions WHERE id=$submissionId")->fetch_assoc();
                if ($subRow) {
                    $sid = (int)$subRow['student_id'];
                    if ($newStatus === 'approved') {
                        $db->query("UPDATE internship_students SET total_points = (
                            SELECT COALESCE(SUM(points_earned),0) FROM task_submissions
                            WHERE student_id=$sid AND status='approved'
                        ) WHERE id=$sid");
                    }
                    $statusLabel = $db->real_escape_string(ucfirst(str_replace('_',' ',$newStatus)));
                    $notifMsg    = $db->real_escape_string("Your submission has been updated to: $statusLabel." . ($feedback ? " Feedback: $feedback" : ''));
                    $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                                VALUES ($sid, '$statusLabel', '$notifMsg', 'task', NOW())");
                }
                $_SESSION['admin_success'] = 'Submission updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update submission: ' . $db->error;
            }
        } else {
            $_SESSION['admin_error'] = 'Invalid status value.';
        }
        ob_end_clean();
        header('Location: admin.php?tab=all_submissions');
        exit;
    }

    // --- Bulk Submission Status Update ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_submissions'])) {
        $ids        = $_POST['selected_ids'] ?? [];
        $bulkStatus = $db->real_escape_string(trim($_POST['bulk_status'] ?? ''));
        $allowed    = ['under_review','approved','rejected'];
        if (!empty($ids) && in_array($bulkStatus, $allowed)) {
            $idsInt     = array_map('intval', $ids);
            $idList     = implode(',', $idsInt);
            $reviewedBy = $db->real_escape_string($_SESSION['admin_username'] ?? 'Admin');
            $db->query("UPDATE task_submissions SET status='$bulkStatus', reviewed_by='$reviewedBy',
                        reviewed_at=NOW(), updated_at=NOW() WHERE id IN ($idList)");
            $cnt = count($idsInt);
            $_SESSION['admin_success'] = "$cnt submission(s) updated to " . ucfirst(str_replace('_',' ',$bulkStatus)) . '.';
        } else {
            $_SESSION['admin_error'] = 'Please select submissions and a valid bulk action.';
        }
        ob_end_clean();
        header('Location: admin.php?tab=all_submissions');
        exit;
    }

    // --- Save Announcement (Create or Update) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
        $title          = trim($_POST['title'] ?? '');
        $content        = trim($_POST['content'] ?? '');
        $type           = $_POST['type'] ?? 'general';
        $priority       = $_POST['priority'] ?? 'normal';
        $batch_id       = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        $coordinator_id = !empty($_POST['coordinator_id']) ? (int)$_POST['coordinator_id'] : null;
        $target_all     = isset($_POST['target_all']) ? 1 : 0;
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $edit_id        = (int)($_POST['announcement_id'] ?? 0);

        $errors = [];
        if (empty($title))   $errors[] = 'Title is required';
        if (empty($content)) $errors[] = 'Content is required';
        if (!in_array($type,     ['general','task_deadline','certificate','attendance'])) $errors[] = 'Invalid type';
        if (!in_array($priority, ['urgent','important','normal']))                        $errors[] = 'Invalid priority';

       if (empty($errors)) {
            if ($edit_id > 0) {
                $stmt = $db->prepare("UPDATE announcements SET title=?,content=?,type=?,priority=?,batch_id=?,coordinator_id=?,target_all=?,is_active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->bind_param("ssssiiiii", $title, $content, $type, $priority, $batch_id, $coordinator_id, $target_all, $is_active, $edit_id);
                $ok = $stmt->execute();
                $_SESSION[$ok ? 'admin_success' : 'admin_error'] = $ok ? 'Announcement updated successfully' : 'Failed to update announcement';
            } else {
                $stmt = $db->prepare("INSERT INTO announcements (title,content,type,priority,batch_id,coordinator_id,target_all,is_active) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssiiii", $title, $content, $type, $priority, $batch_id, $coordinator_id, $target_all, $is_active);
                $ok = $stmt->execute();
                $_SESSION[$ok ? 'admin_success' : 'admin_error'] = $ok ? 'Announcement created successfully' : 'Failed to create announcement';
            }
        } else {
            $_SESSION['admin_error'] = implode(', ', $errors);
        }
        ob_end_clean();
        header('Location: admin.php?tab=announcements');
        exit;
    }

    // --- Delete Announcement ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
        $ann_id = (int)($_POST['announcement_id'] ?? 0);
        if ($ann_id > 0) {
            $stmt = $db->prepare("DELETE FROM announcements WHERE id=?");
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute()) {
                $db->query("DELETE FROM announcement_reads WHERE announcement_id=$ann_id");
                $_SESSION['admin_success'] = 'Announcement deleted successfully';
            } else {
                $_SESSION['admin_error'] = 'Failed to delete announcement';
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=announcements');
        exit;
    }

    // --- Mark Attendance ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
        $domainInterest = $_POST['domain_interest'] ?? '';
        $attendanceDate = $_POST['attendance_date'] ?? '';
        $attendanceData = $_POST['attendance'] ?? [];

        if (!$domainInterest || !$attendanceDate || empty($attendanceData)) {
            $_SESSION['admin_error'] = 'Please select domain, date, and mark at least one student';
        } else {
            // Ensure date column exists
            $columnCheck = $db->query("SHOW COLUMNS FROM student_attendance LIKE 'date'");
            if ($columnCheck->num_rows == 0) {
                $db->query("ALTER TABLE student_attendance ADD COLUMN date DATE NULL AFTER batch_id");
                $db->query("ALTER TABLE student_attendance ADD COLUMN marked_by VARCHAR(100) NULL");
                $db->query("ALTER TABLE student_attendance MODIFY status ENUM('active','inactive','completed','dropped','present','absent','late') DEFAULT 'active'");
            }

            $dateEsc = $db->real_escape_string($attendanceDate);
            $count   = 0;
            foreach ($attendanceData as $studentId => $status) {
                $studentId = (int)$studentId;
                if (empty($status)) continue;
                $statusEsc = $db->real_escape_string($status);
                $exists    = $db->query("SELECT id FROM student_attendance WHERE student_id=$studentId AND date='$dateEsc'")->fetch_assoc();
                if ($exists) {
                    $db->query("UPDATE student_attendance SET status='$statusEsc', enrolled_date=NOW() WHERE id={$exists['id']}");
                } else {
                    $db->query("INSERT INTO student_attendance (student_id, batch_id, date, status, enrolled_date) VALUES ($studentId, NULL, '$dateEsc', '$statusEsc', NOW())");
                }
                $count++;
            }
            $_SESSION['admin_success'] = "Attendance marked for $count students on " . date('M d, Y', strtotime($attendanceDate));
        }
        ob_end_clean();
        header('Location: admin.php?tab=attendance&domain=' . urlencode($domainInterest));
        exit;
    }

    // --- Reset Student Password ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $studentId   = (int)$_POST['student_id'];
        $newPassword = trim($_POST['new_password'] ?? '');
        if (empty($newPassword)) {
            $_SESSION['admin_error'] = 'New password is required';
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['admin_error'] = 'Password must be at least 6 characters';
        } else {
            $hashed     = password_hash($newPassword, PASSWORD_DEFAULT);
            $hashedEsc  = $db->real_escape_string($hashed);
            if ($db->query("UPDATE internship_students SET password='$hashedEsc', updated_at=NOW() WHERE id=$studentId")) {
                $notifMsg = $db->real_escape_string('Your password has been reset by an administrator. Please log in with your new credentials.');
                $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                            VALUES ($studentId, 'Password Reset', '$notifMsg', 'system', NOW())");
                $_SESSION['admin_success'] = 'Password reset successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to reset password: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=users');
        exit;
    }

    // --- Update User Status (activate/deactivate) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_status'])) {
        $studentId = (int)$_POST['student_id'];
        $isActive  = (int)$_POST['is_active'];
        if ($db->query("UPDATE internship_students SET is_active=$isActive, updated_at=NOW() WHERE id=$studentId")) {
            $_SESSION['admin_success'] = 'User ' . ($isActive ? 'activated' : 'deactivated') . ' successfully!';
        } else {
            $_SESSION['admin_error'] = 'Failed to update user status: ' . $db->error;
        }
        ob_end_clean();
        header('Location: admin.php?tab=users');
        exit;
    }

    // --- Update User Details ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
        $studentId        = (int)$_POST['student_id'];
        $fullName         = trim($_POST['full_name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $phone            = $db->real_escape_string(trim($_POST['phone'] ?? ''));
        $collegeName      = $db->real_escape_string(trim($_POST['college_name'] ?? ''));
        $degree           = $db->real_escape_string(trim($_POST['degree'] ?? ''));
        $yearOfStudy      = $db->real_escape_string(trim($_POST['year_of_study'] ?? ''));
        $domainInterest   = $db->real_escape_string(trim($_POST['domain_interest'] ?? ''));
        $internshipStatus = $db->real_escape_string(trim($_POST['internship_status'] ?? 'active'));

        if (empty($fullName) || empty($email)) {
            $_SESSION['admin_error'] = 'Name and email are required';
        } else {
            $fnEsc    = $db->real_escape_string($fullName);
            $emEsc    = $db->real_escape_string($email);
            $dupCheck = $db->query("SELECT id FROM internship_students WHERE email='$emEsc' AND id != $studentId");
            if ($dupCheck->num_rows > 0) {
                $_SESSION['admin_error'] = 'Email already exists for another user';
            } else {
                $sql = "UPDATE internship_students SET full_name='$fnEsc', email='$emEsc', phone='$phone',
                        college_name='$collegeName', degree='$degree', year_of_study='$yearOfStudy',
                        domain_interest='$domainInterest', internship_status='$internshipStatus',
                        updated_at=NOW() WHERE id=$studentId";
                if ($db->query($sql)) {
                    $_SESSION['admin_success'] = 'User updated successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to update user: ' . $db->error;
                }
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=users');
        exit;
    }

    
    // ── BADGE POST HANDLERS ─────────────────────────────────────────────────

    // --- Create Badge ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_badge'])) {
        $name   = $db->real_escape_string(trim($_POST['name'] ?? ''));
        $desc   = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $icon   = $db->real_escape_string(trim($_POST['icon'] ?? '🏅'));
        $tier   = $db->real_escape_string($_POST['tier'] ?? 'bronze');
        $cat    = $db->real_escape_string(trim($_POST['category'] ?? 'general'));
        $pts    = (int)($_POST['points_bonus'] ?? 0);
        $awdFor = $db->real_escape_string(trim($_POST['awarded_for'] ?? ''));
        $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        $allowedTiers = ['bronze','silver','gold','platinum','diamond'];
        if (empty($name)) {
            $_SESSION['admin_error'] = 'Badge name is required';
        } elseif (!in_array($tier, $allowedTiers)) {
            $_SESSION['admin_error'] = 'Invalid tier selected';
        } else {
            $sql = "INSERT INTO badges (name, description, icon, tier, category, points_bonus, awarded_for, is_active, created_at)
                    VALUES ('$name','$desc','$icon','$tier','$cat',$pts,'$awdFor',$active,NOW())";
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'Badge "' . $name . '" created successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to create badge: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=badges');
        exit;
    }

    // --- Update Badge ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_badge'])) {
        $bid    = (int)$_POST['badge_id'];
        $name   = $db->real_escape_string(trim($_POST['name'] ?? ''));
        $desc   = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $icon   = $db->real_escape_string(trim($_POST['icon'] ?? '🏅'));
        $tier   = $db->real_escape_string($_POST['tier'] ?? 'bronze');
        $cat    = $db->real_escape_string(trim($_POST['category'] ?? 'general'));
        $pts    = (int)($_POST['points_bonus'] ?? 0);
        $awdFor = $db->real_escape_string(trim($_POST['awarded_for'] ?? ''));
        $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        $allowedTiers = ['bronze','silver','gold','platinum','diamond'];
        if (empty($name)) {
            $_SESSION['admin_error'] = 'Badge name is required';
        } elseif ($bid <= 0) {
            $_SESSION['admin_error'] = 'Invalid badge ID';
        } elseif (!in_array($tier, $allowedTiers)) {
            $_SESSION['admin_error'] = 'Invalid tier selected';
        } else {
            $sql = "UPDATE badges SET
                    name='$name', description='$desc', icon='$icon', tier='$tier',
                    category='$cat', points_bonus=$pts, awarded_for='$awdFor',
                    is_active=$active, updated_at=NOW()
                    WHERE id=$bid";
            if ($db->query($sql)) {
                $_SESSION['admin_success'] = 'Badge updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update badge: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=badges');
        exit;
    }

    // --- Delete Badge ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_badge'])) {
        $bid = (int)$_POST['badge_id'];
        if ($bid <= 0) {
            $_SESSION['admin_error'] = 'Invalid badge ID';
        } else {
            $db->query("DELETE FROM student_badges WHERE badge_id=$bid");
            if ($db->query("DELETE FROM badges WHERE id=$bid")) {
                $_SESSION['admin_success'] = 'Badge deleted successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to delete badge: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=badges');
        exit;
    }

    // --- Award Badge to Student ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_badge'])) {
        $sid  = (int)$_POST['student_id'];
        $bid  = (int)$_POST['badge_id'];
        $note = $db->real_escape_string(trim($_POST['award_note'] ?? ''));

        if ($sid <= 0 || $bid <= 0) {
            $_SESSION['admin_error'] = 'Please select both a student and a badge';
        } else {
            $existsRes = $db->query("SELECT id FROM student_badges WHERE student_id=$sid AND badge_id=$bid");
            if ($existsRes && $existsRes->num_rows > 0) {
                $_SESSION['admin_error'] = 'This student already has this badge!';
            } else {
                $awardedBy = $db->real_escape_string($_SESSION['admin_username'] ?? 'Admin');
                $insertOk  = $db->query("INSERT INTO student_badges (student_id, badge_id, award_note, awarded_by, awarded_at)
                                         VALUES ($sid, $bid, '$note', '$awardedBy', NOW())");
                if ($insertOk) {
                    $badgeRow    = $db->query("SELECT name, points_bonus FROM badges WHERE id=$bid")->fetch_assoc();
                    $badgeName   = $db->real_escape_string($badgeRow['name'] ?? 'Badge');
                    $bonusPoints = (int)($badgeRow['points_bonus'] ?? 0);

                    if ($bonusPoints > 0) {
                        $reasonEsc = $db->real_escape_string('Badge awarded: ' . ($badgeRow['name'] ?? ''));
                        $db->query("INSERT INTO student_points_log (student_id, points, reason, awarded_at)
                                    VALUES ($sid, $bonusPoints, '$reasonEsc', NOW())");
                        $totalRes = $db->query("SELECT COALESCE(SUM(points), 0) as total FROM student_points_log WHERE student_id=$sid");
                        $newTotal = $totalRes ? (int)$totalRes->fetch_assoc()['total'] : 0;
                        $db->query("UPDATE internship_students SET total_points=$newTotal WHERE id=$sid");
                    }

                    $notifTitle = $db->real_escape_string('🏅 Badge Awarded: ' . ($badgeRow['name'] ?? 'Badge'));
                    $notifMsg   = $db->real_escape_string(
                        'Congratulations! You earned the "' . ($badgeRow['name'] ?? 'Badge') . '" badge!' .
                        ($bonusPoints > 0 ? ' +' . $bonusPoints . ' bonus points added to your score.' : '') .
                        ($note ? ' Note: ' . ($_POST['award_note'] ?? '') : '')
                    );
                    $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                                VALUES ($sid, '$notifTitle', '$notifMsg', 'system', NOW())");

                    $_SESSION['admin_success'] = 'Badge "' . ($badgeRow['name'] ?? '') . '" awarded!' .
                        ($bonusPoints > 0 ? " +$bonusPoints bonus points added to student score." : '');
                } else {
                    $_SESSION['admin_error'] = 'Failed to award badge: ' . $db->error;
                }
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=badges');
        exit;
    }

    // --- Revoke Badge ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_badge'])) {
        $sbid = (int)$_POST['student_badge_id'];
        if ($sbid <= 0) {
            $_SESSION['admin_error'] = 'Invalid record';
        } else {
            if ($db->query("DELETE FROM student_badges WHERE id=$sbid")) {
                $_SESSION['admin_success'] = 'Badge revoked successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to revoke badge: ' . $db->error;
            }
        }
        ob_end_clean();
        header('Location: admin.php?tab=badges');
        exit;
    }

    // ── EARNINGS POST HANDLERS ─────────────────────────────────────────────────
    // --- Award New Earning ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_earning'])) {
        $studentId = (int)$_POST['student_id'];
        $earningType = $db->real_escape_string($_POST['earning_type'] ?? 'bonus_reward');
        $category = $db->real_escape_string(trim($_POST['category'] ?? ''));
        $title = $db->real_escape_string(trim($_POST['title'] ?? ''));
        $description = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $value = $db->real_escape_string(trim($_POST['value'] ?? ''));
        $quantity = (int)($_POST['quantity'] ?? 1);
        $priority = $db->real_escape_string($_POST['priority'] ?? 'medium');
        $awardedFor = $db->real_escape_string(trim($_POST['awarded_for'] ?? ''));
        $expiresAt = !empty($_POST['expires_at']) ? "'" . $db->real_escape_string($_POST['expires_at']) . "'" : 'NULL';
        $thumbnailUrl = $db->real_escape_string(trim($_POST['thumbnail_url'] ?? ''));
        $redemptionInstructions = $db->real_escape_string(trim($_POST['redemption_instructions'] ?? ''));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $awardedBy = $db->real_escape_string($_SESSION['admin_username'] ?? 'Admin');
        
        $errors = [];
        if ($studentId <= 0) $errors[] = 'Please select a valid student';
        if (empty($title)) $errors[] = 'Title is required';
        if (empty($awardedFor)) $errors[] = 'Please specify the reason for this earning';
        if (!in_array($earningType, ['mentorship','software_access','learning_resource','exclusive_perk','bonus_reward'])) {
            $errors[] = 'Invalid earning type';
        }
        if (!in_array($priority, ['low','medium','high','urgent'])) {
            $errors[] = 'Invalid priority';
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO student_earnings 
                    (student_id, earning_type, category, title, description, value, quantity, 
                    status, awarded_by, awarded_for, awarded_at, expires_at, redemption_instructions, 
                    priority, is_featured, thumbnail_url)
                    VALUES 
                    ($studentId, '$earningType', '$category', '$title', '$description', '$value', $quantity,
                    'pending', '$awardedBy', '$awardedFor', NOW(), $expiresAt, '$redemptionInstructions',
                    '$priority', $isFeatured, '$thumbnailUrl')";
            
            if ($db->query($sql)) {
                // Send notification to student
                $notifTitle = $db->real_escape_string("🎁 New Reward Earned: $title");
                $notifMsg = $db->real_escape_string("Congratulations! You've earned a new reward. " . ($awardedFor ? "Reason: $awardedFor" : ""));
                $db->query("INSERT INTO student_notifications (student_id, title, message, type, link, created_at)
                        VALUES ($studentId, '$notifTitle', '$notifMsg', 'system', 'earnings.php', NOW())");
                
                $_SESSION['admin_success'] = "Earning awarded successfully to student!";
            } else {
                $_SESSION['admin_error'] = 'Failed to award earning: ' . $db->error;
            }
        } else {
            $_SESSION['admin_error'] = implode(', ', $errors);
        }
        
        ob_end_clean();
        header('Location: admin.php?tab=earnings');
        exit;
    }

    // --- Revoke Earning ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_earning'])) {
        $earningId = (int)$_POST['earning_id'];
        $revokeReason = $db->real_escape_string(trim($_POST['revoke_reason'] ?? 'Revoked by admin'));
        $revokedBy = $db->real_escape_string($_SESSION['admin_username'] ?? 'Admin');
        
        if ($earningId > 0) {
            // Get earning details for notification
            $earningData = $db->query("SELECT e.*, s.id as sid FROM student_earnings e 
                                    JOIN internship_students s ON s.id = e.student_id 
                                    WHERE e.id=$earningId")->fetch_assoc();
            
            if ($earningData) {
                $sql = "UPDATE student_earnings 
                    SET status='revoked', 
                        revoked_at=NOW(), 
                        revoked_by='$revokedBy',
                        revoke_reason='$revokeReason'
                    WHERE id=$earningId";
                
                if ($db->query($sql)) {
                    // Notify student
                    $studentId = (int)$earningData['sid'];
                    $earningTitle = $db->real_escape_string($earningData['title']);
                    $notifTitle = $db->real_escape_string("Reward Revoked: $earningTitle");
                    $notifMsg = $db->real_escape_string("Your reward has been revoked. Reason: $revokeReason");
                    $db->query("INSERT INTO student_notifications (student_id, title, message, type, created_at)
                            VALUES ($studentId, '$notifTitle', '$notifMsg', 'system', NOW())");
                    
                    $_SESSION['admin_success'] = 'Earning revoked successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to revoke earning: ' . $db->error;
                }
            } else {
                $_SESSION['admin_error'] = 'Earning not found';
            }
        } else {
            $_SESSION['admin_error'] = 'Invalid earning ID';
        }
        
        ob_end_clean();
        header('Location: admin.php?tab=earnings');
        exit;
    }

}
// ── END EARLY POST HANDLERS ──────────────────────────────────────────────


define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'vigneshg091002');

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    header('Location: admin.php');
    exit;
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            *{margin:0;padding:0;box-sizing:border-box;}
            :root{--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;}
            body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
            .login-box{background:var(--card);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);width:100%;max-width:420px;overflow:hidden;}
            .login-header{background:linear-gradient(135deg,var(--o5),var(--o4));padding:32px 28px;text-align:center;color:#fff;}
            .login-header i{font-size:3rem;margin-bottom:12px;display:block;opacity:.9;}
            .login-header h1{font-size:1.5rem;font-weight:800;margin-bottom:6px;}
            .login-header p{font-size:.875rem;opacity:.85;}
            .login-body{padding:32px 28px;}
            .form-group{margin-bottom:20px;}
            .form-label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:8px;}
            .form-input{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;color:var(--text);outline:none;transition:all .2s;}
            .form-input:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
            .input-group{position:relative;}
            .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:.9rem;}
            .input-group .form-input{padding-left:42px;}
            .btn-login{width:100%;padding:13px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;font-size:.9rem;font-weight:700;font-family:inherit;cursor:pointer;transition:all .2s;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
            .btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
            .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:8px;font-size:.82rem;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
            .login-footer{padding:0 28px 28px;text-align:center;font-size:.75rem;color:var(--text3);}
            .back-btn-login{position:fixed;top:20px;left:20px;width:44px;height:44px;background:rgba(255,255,255,0.15);border:1.5px solid rgba(255,255,255,0.25);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;text-decoration:none;transition:all .2s;z-index:1000;backdrop-filter:blur(10px);}
            .back-btn-login:hover{background:rgba(255,255,255,0.25);border-color:rgba(255,255,255,0.4);transform:translateX(-3px);}
        </style>
    </head>
    <body>
        <a href="index.php" class="back-btn-login" title="Back to Home">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-shield-halved"></i>
                <h1>Admin Access</h1>
                <p>Task Management Portal</p>
            </div>
            <div class="login-body">
                <?php if ($loginError): ?>
                <div class="alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($loginError); ?>
                </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="username" class="form-input" placeholder="Enter username" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" class="form-input" placeholder="Enter password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
            </div>
            <div class="login-footer">
                <i class="fas fa-info-circle"></i> Authorized personnel only
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get Statistics
$statsRes = $db->query("SELECT 
    (SELECT COUNT(*) FROM internship_tasks WHERE status='active') as active_tasks,
    (SELECT COUNT(*) FROM task_submissions WHERE status='submitted' OR status='under_review') as pending_reviews,
    (SELECT COUNT(*) FROM task_submissions WHERE status='approved') as completed_tasks,
    (SELECT COUNT(DISTINCT id) FROM internship_students WHERE is_active=1) as total_students,
    (SELECT COUNT(*) FROM task_submissions) as total_submissions,
    (SELECT COUNT(*) FROM announcements WHERE is_active=1) as active_announcements
");
$stats = $statsRes->fetch_assoc();

// Get success/error messages from session
$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Padak</title>
    <link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        :root{--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--o1:#fff7ed;--o2:#ffedd5;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;--purple:#8b5cf6;}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        .admin-header{background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;padding:20px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        .ah-left{display:flex;align-items:center;gap:16px;}
        .ah-logo{width:48px;height:48px;background:linear-gradient(135deg,var(--o5),var(--o4));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 4px 14px rgba(249,115,22,0.3);}
        .ah-title h1{font-size:1.5rem;font-weight:800;margin-bottom:2px;}
        .ah-title p{font-size:.82rem;opacity:.7;}
        .ah-right{display:flex;align-items:center;gap:14px;}
        .ah-user{display:flex;align-items:center;gap:10px;padding:8px 16px;background:rgba(255,255,255,0.1);border-radius:10px;font-size:.85rem;}
        .ah-user i{color:var(--o4);}
        .btn-logout{padding:8px 16px;background:rgba(239,68,68,0.2);border:1.5px solid rgba(239,68,68,0.3);color:#fca5a5;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:6px;}
        .btn-logout:hover{background:rgba(239,68,68,0.3);border-color:rgba(239,68,68,0.5);}
        .admin-container{max-width:1400px;margin:0 auto;padding:28px;}
        .alert{display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;font-size:.875rem;font-weight:500;margin-bottom:20px;animation:slideIn .3s ease;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
        @keyframes slideIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
        .stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:28px;}
        .stat-card{background:var(--card);border-radius:12px;padding:16px 18px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:all .2s;}
        .stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.1);}
        .sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
        .sc-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
        .sc-icon.orange{background:var(--o1);color:var(--o6);}
        .sc-icon.blue{background:rgba(59,130,246,0.1);color:var(--blue);}
        .sc-icon.green{background:rgba(34,197,94,0.1);color:var(--green);}
        .sc-icon.purple{background:rgba(139,92,246,0.1);color:var(--purple);}
        .sc-icon.yellow{background:rgba(234,179,8,0.1);color:#ca8a04;}
        .sc-icon.cyan{background:rgba(6,182,212,0.1);color:#0891b2;}
        .sc-value{font-size:1.75rem;font-weight:900;color:var(--text);line-height:1;}
        .sc-label{font-size:.75rem;color:var(--text3);margin-top:6px;font-weight:600;letter-spacing:.2px;}
        .tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid var(--border);padding-bottom:0;flex-wrap:wrap;}
        .tab{padding:12px 20px;border-radius:10px 10px 0 0;border:none;background:none;font-size:.875rem;font-weight:600;color:var(--text2);cursor:pointer;transition:all .2s;position:relative;font-family:inherit;}
        .tab:hover{background:var(--bg);color:var(--text);}
        .tab.active{background:var(--card);color:var(--o5);border:1px solid var(--border);border-bottom:2px solid var(--card);margin-bottom:-2px;}
        .tab.active::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:var(--o5);}
        .tab-content{display:none;}
        .tab-content.active{display:block;}
        .badge-count{display:inline-flex;align-items:center;padding:2px 8px;border-radius:6px;font-size:.7rem;font-weight:700;background:rgba(239,68,68,0.12);color:#dc2626;margin-left:6px;}
        @media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr);}}
        @media(max-width:768px){.admin-header{flex-direction:column;align-items:flex-start;gap:12px;}.stats-grid{grid-template-columns:repeat(2,1fr);}.admin-container{padding:16px;}}
        @media(max-width:480px){.stats-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="ah-left">
            <div class="ah-logo"><i class="fas fa-tasks"></i></div>
            <div class="ah-title">
                <h1>Admin Dashboard</h1>
                <p>Task & Attendance Management</p>
            </div>
        </div>
        <div class="ah-right">
            <div class="ah-user">
                <i class="fas fa-user-shield"></i>
                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </div>
            <a href="?logout=1" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="admin-container">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-circle-check"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon orange"><i class="fas fa-clipboard-list"></i></div></div>
                <div class="sc-value"><?php echo $stats['active_tasks']; ?></div>
                <div class="sc-label">Active Tasks</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon blue"><i class="fas fa-hourglass-half"></i></div></div>
                <div class="sc-value"><?php echo $stats['pending_reviews']; ?></div>
                <div class="sc-label">Pending Reviews</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon green"><i class="fas fa-circle-check"></i></div></div>
                <div class="sc-value"><?php echo $stats['completed_tasks']; ?></div>
                <div class="sc-label">Completed Tasks</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon cyan"><i class="fas fa-bullhorn"></i></div></div>
                <div class="sc-value"><?php echo $stats['active_announcements']; ?></div>
                <div class="sc-label">Active Announcements</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon yellow"><i class="fas fa-paper-plane"></i></div></div>
                <div class="sc-value"><?php echo $stats['total_submissions']; ?></div>
                <div class="sc-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="sc-top"><div class="sc-icon purple"><i class="fas fa-users"></i></div></div>
                <div class="sc-value"><?php echo $stats['total_students']; ?></div>
                <div class="sc-label">Active Students</div>
            </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab" data-tab="tasks">
                <i class="fas fa-tasks"></i> Manage Tasks
            </button>
            <button class="tab" data-tab="reviews">
                <i class="fas fa-clipboard-check"></i> Review Submissions
                <?php if ($stats['pending_reviews'] > 0): ?>
                <span class="badge-count"><?php echo $stats['pending_reviews']; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab" data-tab="all_submissions">
                <i class="fas fa-inbox"></i> All Submissions
            </button>
            <button class="tab" data-tab="announcements">
                <i class="fas fa-bullhorn"></i> Announcements
            </button>
            <button class="tab" data-tab="attendance">
                <i class="fas fa-calendar-check"></i> Attendance
            </button>
            <button class="tab" data-tab="users">
                <i class="fas fa-users"></i> User Management
            </button>
            <button class="tab" data-tab="messages">
                <i class="fas fa-comments"></i> Messages
            </button>
            <button class="tab" data-tab="badges">
                <i class="fas fa-burst"></i> Badges
            </button>
        </div>
        
        <!-- Tab Content Panels -->
        <div id="tab-tasks" class="tab-content">
            <?php include 'admin_modules/admin_manage_tasks.php'; ?>
        </div>
        
        <div id="tab-reviews" class="tab-content">
            <?php include 'admin_modules/admin_review_submissions.php'; ?>
        </div>

        <div id="tab-all_submissions" class="tab-content">
            <?php include 'admin_modules/admin_all_submissions.php'; ?>
        </div>
        
        <div id="tab-announcements" class="tab-content">
            <?php include 'admin_modules/admin_announce_manage.php'; ?>
        </div>
        
        <div id="tab-attendance" class="tab-content">
            <?php include 'admin_modules/admin_attendance_manage.php'; ?>
        </div>
        
        <div id="tab-users" class="tab-content">
            <?php include 'admin_modules/admin_user_management.php'; ?>
        </div>

        <div id="tab-messages" class="tab-content">
            <?php include 'admin_modules/admin_all_messages.php'; ?>
        </div>

        <div id="tab-badges" class="tab-content">
            <?php include 'admin_modules/admin_badge_manage.php'; ?>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(function(t) {
                t.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(function(c) {
                c.classList.remove('active');
            });
            var btn = document.querySelector('.tab[data-tab="' + tabName + '"]');
            if (btn) btn.classList.add('active');
            var panel = document.getElementById('tab-' + tabName);
            if (panel) panel.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab[data-tab]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    showTab(this.getAttribute('data-tab'));
                });
            });

            // Dismiss alerts after 5s
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(a) {
                    a.style.transition = 'opacity .3s';
                    a.style.opacity = '0';
                    setTimeout(function() { a.remove(); }, 300);
                });
            }, 5000);

            // Read active tab from ?tab= query param — no hash ever
            var params = new URLSearchParams(window.location.search);
            var activeTab = params.get('tab') || 'tasks';
            showTab(activeTab);
        });
    </script>
</body>
</html>