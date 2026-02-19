<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'tasks';

// ── Fetch task ─────────────────────────────────────────────────────────────
$taskId  = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$task    = null;
$existingSub = null;
$error   = '';
$success = '';

if ($taskId) {
    $tr = $db->query("SELECT * FROM internship_tasks
        WHERE id=$taskId AND status='active'
        AND (assigned_to_student IS NULL OR assigned_to_student=$sid) LIMIT 1");
    $task = $tr ? $tr->fetch_assoc() : null;

    if ($task) {
        $sr = $db->query("SELECT * FROM task_submissions
            WHERE task_id=$taskId AND student_id=$sid LIMIT 1");
        $existingSub = $sr ? $sr->fetch_assoc() : null;

        // Block re-submission unless revision requested
        if ($existingSub && in_array($existingSub['status'], ['submitted','under_review','approved'])) {
            // will show view-only mode
        }
    }
}

// ── All submittable tasks (for dropdown when no task_id given) ─────────────
$submittableTasks = [];
$allRes = $db->query("SELECT t.id, t.title, t.priority, t.due_date,
    ts.status as sub_status
    FROM internship_tasks t
    LEFT JOIN task_submissions ts ON ts.task_id=t.id AND ts.student_id=$sid
    WHERE t.status='active' AND (t.assigned_to_student IS NULL OR t.assigned_to_student=$sid)
    AND (ts.id IS NULL OR ts.status='revision_requested')
    ORDER BY t.due_date ASC");
if ($allRes) while ($r = $allRes->fetch_assoc()) $submittableTasks[] = $r;

// ── Upload config ──────────────────────────────────────────────────────────
$uploadDir   = 'uploads/submissions/';
$allowedExt  = ['pdf','png','jpg','jpeg','gif','webp','zip','doc','docx'];
$maxSize     = 10 * 1024 * 1024; // 10 MB

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_submit'])) {
    $taskId      = (int)$_POST['task_id'];
    $subText     = trim($_POST['submission_text'] ?? '');
    $subUrl      = trim($_POST['submission_url'] ?? '');
    $githubLink  = trim($_POST['github_link'] ?? '');
    $saveAsDraft = isset($_POST['save_draft']);

    // Re-fetch task & existing sub
    $tr = $db->query("SELECT * FROM internship_tasks
        WHERE id=$taskId AND status='active'
        AND (assigned_to_student IS NULL OR assigned_to_student=$sid) LIMIT 1");
    $task = $tr ? $tr->fetch_assoc() : null;

    if (!$task) {
        $error = 'Task not found or not accessible.';
    } else {
        $sr = $db->query("SELECT * FROM task_submissions WHERE task_id=$taskId AND student_id=$sid LIMIT 1");
        $existingSub = $sr ? $sr->fetch_assoc() : null;

        if ($existingSub && in_array($existingSub['status'], ['submitted','under_review','approved'])) {
            $error = 'This task has already been submitted and cannot be modified.';
        } elseif (empty($subText) && empty($subUrl) && empty($githubLink) && empty($_FILES['sub_file']['name'])) {
            $error = 'Please provide at least one: description, link, GitHub URL, or file upload.';
        } else {
            // ── File upload ────────────────────────────────────────────────
            $filePath = $existingSub['file_path'] ?? '';
            $fileName = $existingSub['file_name'] ?? '';

            if (!empty($_FILES['sub_file']['name'])) {
                $origName = $_FILES['sub_file']['name'];
                $tmpPath  = $_FILES['sub_file']['tmp_name'];
                $fileSize = $_FILES['sub_file']['size'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExt)) {
                    $error = "File type .$ext not allowed. Allowed: " . implode(', ', $allowedExt);
                } elseif ($fileSize > $maxSize) {
                    $error = 'File too large. Maximum size is 10 MB.';
                } elseif ($_FILES['sub_file']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'File upload error. Please try again.';
                } else {
                    // Delete old file
                    if ($filePath && file_exists($filePath)) @unlink($filePath);

                    $safeName = 'sub_' . $sid . '_' . $taskId . '_' . time() . '.' . $ext;
                    $dest     = $uploadDir . $safeName;
                    if (move_uploaded_file($tmpPath, $dest)) {
                        $filePath = $dest;
                        $fileName = $origName;
                    } else {
                        $error = 'Failed to save uploaded file. Check folder permissions.';
                    }
                }
            }

            if (empty($error)) {
                $status   = $saveAsDraft ? 'draft' : 'submitted';
                $textEsc  = $db->real_escape_string($subText);
                $urlEsc   = $db->real_escape_string($subUrl);
                $ghEsc    = $db->real_escape_string($githubLink);
                $fpEsc    = $db->real_escape_string($filePath);
                $fnEsc    = $db->real_escape_string($fileName);

                if ($existingSub) {
                    // UPDATE (resubmission / draft save)
                    $db->query("UPDATE task_submissions SET
                        submission_text='$textEsc',
                        submission_url='$urlEsc',
                        github_link='$ghEsc',
                        file_path='$fpEsc',
                        file_name='$fnEsc',
                        status='$status',
                        submitted_at=NOW(),
                        updated_at=NOW()
                        WHERE id={$existingSub['id']}");
                } else {
                    // INSERT
                    $db->query("INSERT INTO task_submissions
                        (task_id, student_id, submission_text, submission_url, github_link,
                         file_path, file_name, status, submitted_at)
                        VALUES ($taskId, $sid, '$textEsc', '$urlEsc', '$ghEsc',
                                '$fpEsc', '$fnEsc', '$status', NOW())");
                }

                if ($status === 'submitted') {
                    $taskTitle = $db->real_escape_string($task['title']);
                    $db->query("INSERT INTO student_notifications
                        (student_id, title, message, type)
                        VALUES ($sid,
                                'Task Submitted',
                                'Your submission for \"$taskTitle\" has been received.',
                                'task') ON DUPLICATE KEY UPDATE id=id") ;
                    $success = 'Task submitted successfully! Your coordinator will review it soon.';
                } else {
                    $success = 'Draft saved. You can come back and submit anytime.';
                }

                // Re-fetch
                $sr = $db->query("SELECT * FROM task_submissions WHERE task_id=$taskId AND student_id=$sid LIMIT 1");
                $existingSub = $sr ? $sr->fetch_assoc() : null;
            }
        }
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function fmtSize(int $b): string {
    if ($b >= 1048576) return round($b/1048576,1).' MB';
    if ($b >= 1024)    return round($b/1024,0).' KB';
    return $b.' B';
}
function isImage(string $ext): bool { return in_array($ext,['png','jpg','jpeg','gif','webp']); }

$initials = strtoupper(substr($student['full_name'],0,1)
    .(str_contains($student['full_name'],' ')
        ? substr(explode(' ',$student['full_name'])[1],0,1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Submit Task - Padak</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset & Variables ───────────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --sbw:258px;
    --o5:#f97316;--o4:#fb923c;--o6:#ea580c;
    --o1:#fff7ed;--o2:#ffedd5;
    --bg:#f8fafc;--card:#fff;
    --text:#0f172a;--text2:#475569;--text3:#94a3b8;
    --border:#e2e8f0;
    --red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;
    --shadow:0 1px 3px rgba(0,0,0,0.06);
    --shadow-md:0 4px 16px rgba(0,0,0,0.08);
    --shadow-lg:0 8px 32px rgba(0,0,0,0.12);
    --radius:14px;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

/* ── Layout ─────────────────────────────────────────────────────────── */
.page-wrap{margin-left:var(--sbw);min-height:100vh;display:flex;flex-direction:column;}
.topbar{
    position:sticky;top:0;z-index:100;
    background:rgba(248,250,252,0.95);backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:12px 28px;display:flex;align-items:center;gap:12px;
}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-back{
    display:flex;align-items:center;gap:6px;
    padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);
    background:var(--card);color:var(--text2);font-size:.82rem;font-weight:500;
    text-decoration:none;transition:all .2s;
}
.topbar-back:hover{border-color:var(--o5);color:var(--o5);}
.topbar-title{font-size:1rem;font-weight:700;color:var(--text);flex:1;}
.topbar-breadcrumb{font-size:.78rem;color:var(--text3);}
.topbar-breadcrumb a{color:var(--text2);text-decoration:none;}
.topbar-breadcrumb a:hover{color:var(--o5);}

.main-content{padding:24px 28px;flex:1;max-width:1100px;width:100%;margin:0 auto;}

/* ── Alerts ─────────────────────────────────────────────────────────── */
.alert{
    display:flex;align-items:flex-start;gap:12px;
    padding:14px 18px;border-radius:10px;
    font-size:.875rem;font-weight:500;margin-bottom:20px;
    animation:slideIn .3s ease;
}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
.alert i{flex-shrink:0;margin-top:1px;}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}

/* ── Two-column layout ───────────────────────────────────────────────── */
.submit-grid{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}

/* ── Cards ───────────────────────────────────────────────────────────── */
.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
.card-header{
    padding:16px 22px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:10px;background:#fafafa;
}
.card-header-icon{
    width:32px;height:32px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    background:var(--o2);color:var(--o6);font-size:.875rem;flex-shrink:0;
}
.card-title{font-size:.9375rem;font-weight:700;color:var(--text);}
.card-body{padding:20px 22px;}

/* ── Task selector (no task_id) ─────────────────────────────────────── */
.task-select-wrap{position:relative;}
.task-select-wrap::after{
    content:'\f107';font-family:'Font Awesome 6 Free';font-weight:900;
    position:absolute;right:14px;top:50%;transform:translateY(-50%);
    color:var(--text3);pointer-events:none;
}
.task-select{
    width:100%;padding:12px 40px 12px 14px;
    border:1.5px solid var(--border);border-radius:10px;
    font-size:.9rem;font-family:inherit;color:var(--text);
    appearance:none;-webkit-appearance:none;outline:none;
    transition:border-color .2s,box-shadow .2s;background:var(--card);cursor:pointer;
}
.task-select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}

/* ── Task info banner ────────────────────────────────────────────────── */
.task-banner{
    background:linear-gradient(135deg,#1e293b,#0f172a);
    border-radius:12px;padding:20px 22px;margin-bottom:20px;
    position:relative;overflow:hidden;
}
.task-banner::after{
    content:'';position:absolute;top:-30%;right:-5%;
    width:180px;height:180px;
    background:rgba(249,115,22,0.08);border-radius:50%;
}
.tb-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;position:relative;z-index:1;}
.tb-title{font-size:1.0625rem;font-weight:800;color:#fff;line-height:1.3;}
.tb-pri{
    padding:3px 10px;border-radius:6px;font-size:.68rem;font-weight:700;
    white-space:nowrap;flex-shrink:0;
}
.pri-urgent{background:rgba(239,68,68,0.2);color:#fca5a5;}
.pri-high{background:rgba(249,115,22,0.2);color:#fdba74;}
.pri-medium{background:rgba(234,179,8,0.2);color:#fde047;}
.pri-low{background:rgba(34,197,94,0.2);color:#86efac;}
.tb-desc{font-size:.82rem;color:rgba(255,255,255,0.6);line-height:1.5;margin-bottom:14px;position:relative;z-index:1;}
.tb-meta{display:flex;gap:18px;flex-wrap:wrap;position:relative;z-index:1;}
.tb-meta-item{display:flex;align-items:center;gap:5px;font-size:.75rem;color:rgba(255,255,255,0.5);}
.tb-meta-item i{color:var(--o4);font-size:.7rem;}
.tb-meta-item.overdue{color:#fca5a5;}
.tb-meta-item.overdue i{color:#fca5a5;}
.tb-res{
    display:inline-flex;align-items:center;gap:6px;
    margin-top:12px;padding:6px 14px;border-radius:8px;
    background:rgba(249,115,22,0.15);border:1px solid rgba(249,115,22,0.25);
    color:var(--o4);font-size:.75rem;font-weight:600;
    text-decoration:none;transition:background .2s;position:relative;z-index:1;
}
.tb-res:hover{background:rgba(249,115,22,0.25);}

/* ── Form ────────────────────────────────────────────────────────────── */
.form-group{margin-bottom:18px;}
.form-label{
    display:block;font-size:.82rem;font-weight:700;
    color:var(--text);margin-bottom:8px;
}
.form-label .optional{font-size:.73rem;font-weight:400;color:var(--text3);}
.form-label .required{color:var(--red);}
.form-input,.form-textarea,.form-select2{
    width:100%;padding:11px 14px;
    border:1.5px solid var(--border);border-radius:9px;
    font-size:.875rem;font-family:inherit;color:var(--text);
    outline:none;transition:border-color .2s,box-shadow .2s;background:var(--card);
}
.form-input:focus,.form-textarea:focus,.form-select2:focus{
    border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);
}
.form-textarea{resize:vertical;min-height:130px;line-height:1.6;}
.form-hint{font-size:.73rem;color:var(--text3);margin-top:5px;}

/* ── URL input with icon ─────────────────────────────────────────────── */
.input-prefix{display:flex;}
.input-prefix-icon{
    padding:0 12px;display:flex;align-items:center;
    background:#f1f5f9;border:1.5px solid var(--border);border-right:none;
    border-radius:9px 0 0 9px;color:var(--text3);font-size:.875rem;
}
.input-prefix .form-input{border-radius:0 9px 9px 0;}

/* ── File Upload Zone ────────────────────────────────────────────────── */
.file-zone{
    border:2px dashed var(--border);border-radius:12px;
    padding:28px 20px;text-align:center;cursor:pointer;
    transition:all .25s;position:relative;
    background:var(--bg);
}
.file-zone:hover,.file-zone.drag-over{
    border-color:var(--o5);background:var(--o1);
}
.file-zone input[type="file"]{
    position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;
}
.fz-icon{font-size:2rem;color:var(--text3);margin-bottom:10px;display:block;transition:color .2s;}
.file-zone:hover .fz-icon{color:var(--o5);}
.fz-main{font-size:.875rem;font-weight:600;color:var(--text2);margin-bottom:4px;}
.fz-sub{font-size:.75rem;color:var(--text3);}
.fz-types{
    display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-top:10px;
}
.fz-type{
    padding:2px 8px;border-radius:5px;font-size:.65rem;font-weight:600;
    background:var(--border);color:var(--text2);text-transform:uppercase;
}
.fz-type.highlight{background:rgba(249,115,22,0.12);color:var(--o6);}

/* File preview */
.file-preview{
    margin-top:12px;padding:12px 14px;
    border:1px solid var(--border);border-radius:9px;
    display:flex;align-items:center;gap:12px;background:var(--card);
    display:none;
}
.fp-icon{
    width:40px;height:40px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;flex-shrink:0;
}
.fp-pdf{background:rgba(239,68,68,0.1);color:var(--red);}
.fp-img{background:rgba(34,197,94,0.1);color:var(--green);}
.fp-doc{background:rgba(59,130,246,0.1);color:var(--blue);}
.fp-zip{background:rgba(234,179,8,0.1);color:var(--yellow);}
.fp-info{flex:1;min-width:0;}
.fp-name{font-size:.82rem;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.fp-size{font-size:.72rem;color:var(--text3);margin-top:2px;}
.fp-remove{
    background:none;border:none;cursor:pointer;color:var(--text3);
    font-size:.875rem;padding:4px;border-radius:5px;transition:color .2s;
}
.fp-remove:hover{color:var(--red);}

/* Image preview */
.img-preview-wrap{margin-top:10px;display:none;}
.img-preview-wrap img{
    max-width:100%;max-height:200px;border-radius:8px;
    border:1px solid var(--border);object-fit:cover;
}

/* ── Existing file (view mode) ───────────────────────────────────────── */
.existing-file{
    display:flex;align-items:center;gap:12px;
    padding:12px 14px;border:1px solid var(--border);border-radius:9px;
    background:var(--bg);margin-bottom:10px;
}
.ef-img{max-height:80px;border-radius:6px;border:1px solid var(--border);}

/* ── Buttons ─────────────────────────────────────────────────────────── */
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;}
.btn{
    padding:11px 22px;border-radius:9px;font-size:.875rem;font-weight:600;
    font-family:inherit;cursor:pointer;border:none;
    display:inline-flex;align-items:center;gap:7px;
    text-decoration:none;transition:all .2s;
}
.btn-primary{
    background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;
    box-shadow:0 4px 14px rgba(249,115,22,0.35);
}
.btn-primary:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 6px 20px rgba(249,115,22,0.45);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-secondary{background:var(--card);border:1.5px solid var(--border);color:var(--text2);}
.btn-secondary:hover{border-color:var(--o5);color:var(--o5);}
.btn-ghost{background:none;color:var(--text3);padding:11px 14px;}
.btn-ghost:hover{color:var(--o5);}

/* ── Sidebar: Status card ────────────────────────────────────────────── */
.status-card{margin-bottom:16px;}
.sc-state{
    display:flex;align-items:center;gap:10px;
    padding:14px 18px;border-radius:10px;margin-bottom:14px;
    font-size:.875rem;font-weight:600;
}
.sc-submitted{background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);color:#1d4ed8;}
.sc-review{background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);color:#6d28d9;}
.sc-approved{background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#15803d;}
.sc-draft{background:rgba(234,179,8,0.08);border:1px solid rgba(234,179,8,0.2);color:#854d0e;}
.sc-revision{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#dc2626;}

.sc-detail{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:.8rem;}
.sc-detail:last-child{border-bottom:none;}
.sc-label{color:var(--text3);}
.sc-value{font-weight:600;color:var(--text);text-align:right;}

/* Feedback */
.feedback-box{
    padding:14px 16px;border-radius:10px;margin-top:14px;
    font-size:.82rem;line-height:1.6;
}
.fb-positive{background:#f0fdf4;border-left:4px solid var(--green);color:#166534;}
.fb-negative{background:#fef2f2;border-left:4px solid var(--red);color:#991b1b;}
.fb-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;opacity:.7;}

/* Points display */
.pts-display{
    text-align:center;padding:16px;
    background:linear-gradient(135deg,var(--o1),var(--o2));
    border-radius:10px;border:1px solid rgba(249,115,22,0.2);
    margin-top:14px;
}
.pts-num{font-size:2rem;font-weight:900;color:var(--o6);}
.pts-max{font-size:.8125rem;color:var(--text3);}
.pts-label{font-size:.75rem;font-weight:600;color:var(--o6);margin-top:2px;}

/* Checklist */
.checklist{display:flex;flex-direction:column;gap:8px;}
.check-item{
    display:flex;align-items:flex-start;gap:10px;
    padding:10px 12px;border-radius:9px;
    border:1px solid var(--border);font-size:.8rem;
}
.check-item.done{background:#f0fdf4;border-color:rgba(34,197,94,0.25);}
.check-item.todo{background:var(--bg);border-color:var(--border);}
.check-item i{flex-shrink:0;margin-top:1px;font-size:.85rem;}
.check-item.done i{color:var(--green);}
.check-item.todo i{color:var(--text3);}
.check-text{color:var(--text2);}
.check-item.done .check-text{color:#15803d;}

/* View-only submission */
.view-field{margin-bottom:14px;}
.vf-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:5px;}
.vf-value{
    padding:11px 14px;background:var(--bg);
    border:1px solid var(--border);border-radius:9px;
    font-size:.875rem;color:var(--text);line-height:1.5;
    word-break:break-all;
}
.vf-link{color:var(--blue);text-decoration:none;display:flex;align-items:center;gap:6px;}
.vf-link:hover{text-decoration:underline;}

/* ── Overdue warning ─────────────────────────────────────────────────── */
.overdue-warn{
    display:flex;align-items:center;gap:10px;padding:12px 16px;
    background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);
    border-radius:10px;margin-bottom:18px;
    font-size:.82rem;color:#991b1b;font-weight:500;
}

/* ── Empty (no tasks) ────────────────────────────────────────────────── */
.empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
.empty-state i{font-size:3rem;margin-bottom:16px;display:block;opacity:.3;}
.empty-state h3{font-size:1.1rem;color:var(--text2);margin-bottom:8px;}
.empty-state p{font-size:.85rem;margin-bottom:18px;}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media(max-width:900px){.submit-grid{grid-template-columns:1fr;}}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .main-content{padding:16px;}
    .task-banner{padding:16px;}
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="page-wrap">

    <!-- Topbar -->
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars fa-sm"></i>
        </button>
        <a href="tasks.php" class="topbar-back">
            <i class="fas fa-arrow-left fa-xs"></i> Tasks
        </a>
        <div class="topbar-title">
            <?php echo $task ? htmlspecialchars($task['title']) : 'Submit Task'; ?>
        </div>
        <div class="topbar-breadcrumb">
            <a href="tasks.php">My Tasks</a> &rsaquo;
            <span>Submit</span>
        </div>
    </div>

    <div class="main-content">

        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <div>
                <?php echo htmlspecialchars($success); ?>
                <?php if (str_contains($success,'successfully')): ?>
                <div style="margin-top:6px;">
                    <a href="tasks.php" style="color:#15803d;font-weight:700;">← Back to My Tasks</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- No submittable tasks -->
        <?php if (empty($submittableTasks) && !$task): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>Nothing to submit right now</h3>
            <p>All tasks are either submitted or awaiting coordinator review.</p>
            <a href="tasks.php" class="btn btn-secondary"> <!-- <i class="fas fa-arrow-left"></i> -->
             ← Back to Tasks</a>
        </div>

        <?php else: ?>

        <!-- Task picker (no task pre-selected) -->
        <?php if (!$task): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-header-icon"><i class="fas fa-list-check"></i></div>
                <div class="card-title">Select a Task to Submit</div>
            </div>
            <div class="card-body">
                <div class="task-select-wrap">
                    <select class="task-select" onchange="if(this.value)window.location='submit.php?task_id='+this.value">
                        <option value="">— Choose a task —</option>
                        <?php foreach ($submittableTasks as $t):
                            $due = $t['due_date'] ? date('M d', strtotime($t['due_date'])) : 'No deadline';
                            $isOD = $t['due_date'] && strtotime($t['due_date']) < time();
                        ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $taskId===$t['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($t['title']); ?>
                            (<?php echo ucfirst($t['priority']); ?> · <?php echo $due; ?><?php echo $isOD?' ⚠️ Overdue':''; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($task):
            $due       = $task['due_date'] ? strtotime($task['due_date']) : null;
            $isOverdue = $due && $due < time() && !in_array($existingSub['status']??'',['approved']);
            $daysLeft  = $due ? ceil(($due - time()) / 86400) : null;
            $canSubmit = !$existingSub || in_array($existingSub['status']??'',['draft','revision_requested']);
            $isViewOnly= $existingSub && in_array($existingSub['status']??'',['submitted','under_review','approved']);

            // Status classes
            $scClass = 'sc-draft'; $scIcon = 'fa-pen'; $scText = 'Draft';
            if (!$existingSub) { $scClass=''; $scIcon='fa-circle-dot'; $scText='Not Submitted'; }
            elseif ($existingSub['status']==='submitted')       { $scClass='sc-submitted'; $scIcon='fa-paper-plane'; $scText='Submitted'; }
            elseif ($existingSub['status']==='under_review')    { $scClass='sc-review';    $scIcon='fa-magnifying-glass'; $scText='Under Review'; }
            elseif ($existingSub['status']==='approved')        { $scClass='sc-approved';  $scIcon='fa-circle-check'; $scText='Approved ✓'; }
            elseif ($existingSub['status']==='revision_requested'){ $scClass='sc-revision';  $scIcon='fa-rotate'; $scText='Revision Required'; }
        ?>

        <!-- Overdue warning -->
        <?php if ($isOverdue && $canSubmit): ?>
        <div class="overdue-warn">
            <i class="fas fa-triangle-exclamation"></i>
            <span>This task is <strong><?php echo abs((int)$daysLeft); ?> day(s) overdue</strong>. You can still submit but it may affect your score.</span>
        </div>
        <?php endif; ?>

        <div class="submit-grid">

            <!-- ── LEFT: Form / View ──────────────────────────────────── -->
            <div>
                <!-- Task banner -->
                <div class="task-banner">
                    <div class="tb-top">
                        <div class="tb-title"><?php echo htmlspecialchars($task['title']); ?></div>
                        <span class="tb-pri pri-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                    </div>
                    <?php if ($task['description']): ?>
                    <div class="tb-desc"><?php echo htmlspecialchars($task['description']); ?></div>
                    <?php endif; ?>
                    <div class="tb-meta">
                        <span class="tb-meta-item <?php echo $isOverdue?'overdue':''; ?>">
                            <i class="fas fa-clock"></i>
                            <?php if ($due):
                                if ($isOverdue)        echo 'Overdue by '.abs((int)$daysLeft).'d';
                                elseif ($daysLeft===0) echo 'Due today!';
                                elseif ($daysLeft==1)  echo 'Due tomorrow';
                                else                   echo $daysLeft.'d left · '.date('M d, Y',$due);
                            else: echo 'No deadline'; endif; ?>
                        </span>
                        <span class="tb-meta-item">
                            <i class="fas fa-star"></i><?php echo $task['max_points']; ?> pts max
                        </span>
                        <span class="tb-meta-item">
                            <i class="fas fa-<?php echo $task['task_type']==='team'?'users':'user'; ?>"></i>
                            <?php echo ucfirst($task['task_type']); ?>
                        </span>
                        <span class="tb-meta-item">
                            <i class="fas fa-user-tie"></i>
                            <?php echo htmlspecialchars($task['created_by']??'Coordinator'); ?>
                        </span>
                    </div>
                    <?php if ($task['resources_url']): ?>
                    <a href="<?php echo htmlspecialchars($task['resources_url']); ?>" target="_blank" class="tb-res">
                        <i class="fas fa-external-link"></i> View Resources / Reference Material
                    </a>
                    <?php endif; ?>
                </div>

                <!-- ── VIEW-ONLY mode ──────────────────────────────────── -->
                <?php if ($isViewOnly): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon"><i class="fas fa-file-lines"></i></div>
                        <div class="card-title">Your Submission</div>
                    </div>
                    <div class="card-body">
                        <?php if ($existingSub['submission_text']): ?>
                        <div class="view-field">
                            <div class="vf-label">Description / Work Summary</div>
                            <div class="vf-value" style="white-space:pre-wrap;"><?php echo htmlspecialchars($existingSub['submission_text']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($existingSub['github_link']): ?>
                        <div class="view-field">
                            <div class="vf-label">GitHub Repository</div>
                            <div class="vf-value">
                                <a href="<?php echo htmlspecialchars($existingSub['github_link']); ?>" target="_blank" class="vf-link">
                                    <i class="fab fa-github"></i><?php echo htmlspecialchars($existingSub['github_link']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($existingSub['submission_url']): ?>
                        <div class="view-field">
                            <div class="vf-label">Live Demo / Project URL</div>
                            <div class="vf-value">
                                <a href="<?php echo htmlspecialchars($existingSub['submission_url']); ?>" target="_blank" class="vf-link">
                                    <i class="fas fa-globe"></i><?php echo htmlspecialchars($existingSub['submission_url']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($existingSub['file_name']): ?>
                        <div class="view-field">
                            <div class="vf-label">Attached File</div>
                            <?php
                                $ext = strtolower(pathinfo($existingSub['file_name'], PATHINFO_EXTENSION));
                                $isImg = isImage($ext);
                            ?>
                            <?php if ($isImg && $existingSub['file_path'] && file_exists($existingSub['file_path'])): ?>
                            <img src="<?php echo htmlspecialchars($existingSub['file_path']); ?>"
                                 class="ef-img" alt="Submission image">
                            <?php endif; ?>
                            <div class="existing-file">
                                <div class="fp-icon <?php echo $ext==='pdf'?'fp-pdf':($isImg?'fp-img':($ext==='zip'?'fp-zip':'fp-doc')); ?>">
                                    <i class="fas <?php echo $ext==='pdf'?'fa-file-pdf':($isImg?'fa-file-image':($ext==='zip'?'fa-file-zipper':'fa-file-word')); ?>"></i>
                                </div>
                                <div class="fp-info">
                                    <div class="fp-name"><?php echo htmlspecialchars($existingSub['file_name']); ?></div>
                                    <?php if ($existingSub['file_path'] && file_exists($existingSub['file_path'])): ?>
                                    <div class="fp-size"><?php echo fmtSize(filesize($existingSub['file_path'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($existingSub['file_path'] && file_exists($existingSub['file_path'])): ?>
                                <a href="<?php echo htmlspecialchars($existingSub['file_path']); ?>"
                                   download class="btn btn-secondary" style="padding:6px 12px;font-size:.75rem;">
                                    <i class="fas fa-download fa-xs"></i> Download
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <a href="tasks.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Tasks
                        </a>
                    </div>
                </div>

                <?php else: /* ── SUBMIT FORM ─────────────────────────── */ ?>

                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon">
                            <i class="fas fa-<?php echo $existingSub?'rotate':'paper-plane'; ?>"></i>
                        </div>
                        <div class="card-title">
                            <?php echo $existingSub ? 'Update & Resubmit' : 'Submit Your Work'; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="submitForm" novalidate>
                            <input type="hidden" name="do_submit" value="1">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">

                            <!-- Description -->
                            <div class="form-group">
                                <label class="form-label">
                                    Work Description
                                    <span class="required">*</span>
                                    <span class="optional">(explain your approach & what you built)</span>
                                </label>
                                <textarea name="submission_text" class="form-textarea"
                                    placeholder="Describe your solution, approach, tools used, challenges faced, and key learnings..."
                                    ><?php echo htmlspecialchars($existingSub['submission_text']??''); ?></textarea>
                                <div class="form-hint">Be thorough — this is what your coordinator reviews first.</div>
                            </div>

                            <!-- GitHub link -->
                            <div class="form-group">
                                <label class="form-label">
                                    GitHub Repository <span class="optional">(strongly recommended)</span>
                                </label>
                                <div class="input-prefix">
                                    <div class="input-prefix-icon"><i class="fab fa-github"></i></div>
                                    <input type="url" name="github_link" class="form-input"
                                        placeholder="https://github.com/username/repository"
                                        value="<?php echo htmlspecialchars($existingSub['github_link']??''); ?>">
                                </div>
                                <div class="form-hint">Make sure the repository is public before submitting.</div>
                            </div>

                            <!-- Live / Demo URL -->
                            <div class="form-group">
                                <label class="form-label">
                                    Live Demo / Hosted URL <span class="optional">(optional)</span>
                                </label>
                                <div class="input-prefix">
                                    <div class="input-prefix-icon"><i class="fas fa-globe"></i></div>
                                    <input type="url" name="submission_url" class="form-input"
                                        placeholder="https://your-project.vercel.app"
                                        value="<?php echo htmlspecialchars($existingSub['submission_url']??''); ?>">
                                </div>
                            </div>

                            <!-- File Upload -->
                            <div class="form-group">
                                <label class="form-label">
                                    Upload File <span class="optional">(PDF, images, docs, ZIP — max 10 MB)</span>
                                </label>

                                <?php if ($existingSub && $existingSub['file_name']): ?>
                                <div class="existing-file" id="existingFile">
                                    <?php $ext = strtolower(pathinfo($existingSub['file_name'],PATHINFO_EXTENSION)); ?>
                                    <div class="fp-icon <?php echo $ext==='pdf'?'fp-pdf':(isImage($ext)?'fp-img':($ext==='zip'?'fp-zip':'fp-doc')); ?>">
                                        <i class="fas <?php echo $ext==='pdf'?'fa-file-pdf':(isImage($ext)?'fa-file-image':($ext==='zip'?'fa-file-zipper':'fa-file-word')); ?>"></i>
                                    </div>
                                    <div class="fp-info">
                                        <div class="fp-name"><?php echo htmlspecialchars($existingSub['file_name']); ?></div>
                                        <div class="fp-size">Current file — upload a new one to replace</div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="file-zone" id="fileZone"
                                     ondragover="handleDrag(event,true)"
                                     ondragleave="handleDrag(event,false)"
                                     ondrop="handleDrop(event)">
                                    <input type="file" name="sub_file" id="fileInput"
                                           accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.zip,.doc,.docx"
                                           onchange="handleFileSelect(this)">
                                    <i class="fas fa-cloud-arrow-up fz-icon" id="fzIcon"></i>
                                    <div class="fz-main" id="fzMain">Click to upload or drag & drop</div>
                                    <div class="fz-sub" id="fzSub">Supports PDF, Images, Word docs, ZIP archives</div>
                                    <div class="fz-types">
                                        <span class="fz-type highlight">PDF</span>
                                        <span class="fz-type highlight">PNG</span>
                                        <span class="fz-type highlight">JPG</span>
                                        <span class="fz-type">ZIP</span>
                                        <span class="fz-type">DOCX</span>
                                    </div>
                                </div>

                                <!-- File preview (populated by JS) -->
                                <div class="file-preview" id="filePreview">
                                    <div class="fp-icon" id="fpIcon"><i class="fas fa-file" id="fpIconI"></i></div>
                                    <div class="fp-info">
                                        <div class="fp-name" id="fpName">—</div>
                                        <div class="fp-size" id="fpSize">—</div>
                                    </div>
                                    <button type="button" class="fp-remove" onclick="clearFile()" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>

                                <!-- Image preview -->
                                <div class="img-preview-wrap" id="imgPreview">
                                    <img id="imgPreviewEl" src="" alt="Preview">
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="btn-row">
                                <button type="submit" name="do_submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-paper-plane"></i>
                                    <?php echo $existingSub ? 'Resubmit Task' : 'Submit Task'; ?>
                                </button>
                                <button type="submit" name="save_draft" value="1" class="btn btn-secondary">
                                    <i class="fas fa-floppy-disk"></i> Save Draft
                                </button>
                                <a href="tasks.php" class="btn btn-ghost">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── RIGHT: Sidebar ──────────────────────────────────────── -->
            <div>
                <!-- Submission status -->
                <div class="card status-card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <div class="card-header-icon"><i class="fas fa-circle-info"></i></div>
                        <div class="card-title">Submission Status</div>
                    </div>
                    <div class="card-body">
                        <div class="sc-state <?php echo $scClass; ?>">
                            <i class="fas <?php echo $scIcon; ?>"></i>
                            <?php echo $scText; ?>
                        </div>
                        <?php if ($existingSub): ?>
                        <div class="sc-detail">
                            <span class="sc-label">Submitted</span>
                            <span class="sc-value"><?php echo date('M d, Y g:i A', strtotime($existingSub['submitted_at'])); ?></span>
                        </div>
                        <?php if ($existingSub['reviewed_at']): ?>
                        <div class="sc-detail">
                            <span class="sc-label">Reviewed</span>
                            <span class="sc-value"><?php echo date('M d, Y', strtotime($existingSub['reviewed_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($existingSub['reviewed_by']): ?>
                        <div class="sc-detail">
                            <span class="sc-label">Reviewed by</span>
                            <span class="sc-value"><?php echo htmlspecialchars($existingSub['reviewed_by']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <div class="sc-detail">
                            <span class="sc-label">Max Points</span>
                            <span class="sc-value"><?php echo $task['max_points']; ?> pts</span>
                        </div>
                        <?php if ($existingSub && $existingSub['points_earned'] !== null): ?>
                        <div class="pts-display">
                            <div class="pts-num"><?php echo $existingSub['points_earned']; ?></div>
                            <div class="pts-max">out of <?php echo $task['max_points']; ?> points</div>
                            <div class="pts-label">Points Earned 🎉</div>
                        </div>
                        <?php endif; ?>

                        <!-- Feedback -->
                        <?php if ($existingSub && $existingSub['feedback']): ?>
                        <div class="feedback-box <?php echo in_array($existingSub['status'],['revision_requested','rejected'])?'fb-negative':'fb-positive'; ?>">
                            <div class="fb-label"><i class="fas fa-comment-dots"></i> Coordinator Feedback</div>
                            <?php echo htmlspecialchars($existingSub['feedback']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submission checklist -->
                <?php if ($canSubmit): ?>
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <div class="card-header-icon"><i class="fas fa-list-check"></i></div>
                        <div class="card-title">Submission Checklist</div>
                    </div>
                    <div class="card-body">
                        <div class="checklist" id="checklist">
                            <div class="check-item todo" id="chk-desc">
                                <i class="fas fa-circle"></i>
                                <span class="check-text">Work description filled in</span>
                            </div>
                            <div class="check-item todo" id="chk-github">
                                <i class="fas fa-circle"></i>
                                <span class="check-text">GitHub link added</span>
                            </div>
                            <div class="check-item todo" id="chk-file">
                                <i class="fas fa-circle"></i>
                                <span class="check-text">File / screenshot uploaded</span>
                            </div>
                            <div class="check-item todo" id="chk-review">
                                <i class="fas fa-circle"></i>
                                <span class="check-text">Reviewed before submitting</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tips -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon"><i class="fas fa-lightbulb"></i></div>
                        <div class="card-title">Tips for a Great Submission</div>
                    </div>
                    <div class="card-body" style="font-size:.8rem;color:var(--text2);line-height:1.7;">
                        <p style="margin-bottom:8px;">📌 <strong>Be specific</strong> — describe what you built, tools/libraries used, and challenges faced.</p>
                        <p style="margin-bottom:8px;">🔗 <strong>GitHub:</strong> Ensure repo is public with a clear README before submitting.</p>
                        <p style="margin-bottom:8px;">📄 <strong>PDF uploads</strong> are great for reports, diagrams, and documentation.</p>
                        <p style="margin-bottom:8px;">🖼️ <strong>Screenshots</strong> help reviewers see your UI without running the project.</p>
                        <p>✅ <strong>Double-check</strong> all links are working before you submit.</p>
                    </div>
                </div>
            </div>

        </div><!-- /submit-grid -->
        <?php endif; /* task exists */ ?>
        <?php endif; /* has submittable tasks */ ?>

    </div><!-- /main-content -->
</div><!-- /page-wrap -->

<script>
/* ── File upload handling ─────────────────────────────────────────── */
const fileInput   = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const fpName      = document.getElementById('fpName');
const fpSize      = document.getElementById('fpSize');
const fpIcon      = document.getElementById('fpIcon');
const fpIconI     = document.getElementById('fpIconI');
const imgPreview  = document.getElementById('imgPreview');
const imgPreviewEl= document.getElementById('imgPreviewEl');
const fileZone    = document.getElementById('fileZone');

const EXT_ICONS = {
    pdf:  {cls:'fp-pdf',  icon:'fa-file-pdf'},
    png:  {cls:'fp-img',  icon:'fa-file-image'},
    jpg:  {cls:'fp-img',  icon:'fa-file-image'},
    jpeg: {cls:'fp-img',  icon:'fa-file-image'},
    gif:  {cls:'fp-img',  icon:'fa-file-image'},
    webp: {cls:'fp-img',  icon:'fa-file-image'},
    zip:  {cls:'fp-zip',  icon:'fa-file-zipper'},
    doc:  {cls:'fp-doc',  icon:'fa-file-word'},
    docx: {cls:'fp-doc',  icon:'fa-file-word'},
};

function fmtBytes(b) {
    if (b >= 1048576) return (b/1048576).toFixed(1)+' MB';
    if (b >= 1024)    return Math.round(b/1024)+' KB';
    return b+' B';
}

function handleFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const ext  = file.name.split('.').pop().toLowerCase();
    const info = EXT_ICONS[ext] || {cls:'fp-doc', icon:'fa-file'};

    fpName.textContent = file.name;
    fpSize.textContent = fmtBytes(file.size);
    fpIcon.className   = 'fp-icon ' + info.cls;
    fpIconI.className  = 'fas ' + info.icon;
    filePreview.style.display = 'flex';

    // Image preview
    if (['png','jpg','jpeg','gif','webp'].includes(ext)) {
        const reader = new FileReader();
        reader.onload = e => { imgPreviewEl.src = e.target.result; };
        reader.readAsDataURL(file);
        imgPreview.style.display = 'block';
    } else {
        imgPreview.style.display = 'none';
    }

    updateChecklist();
}

function clearFile() {
    fileInput.value = '';
    filePreview.style.display = 'none';
    imgPreview.style.display  = 'none';
    updateChecklist();
}

function handleDrag(e, over) {
    e.preventDefault();
    fileZone.classList.toggle('drag-over', over);
}

function handleDrop(e) {
    e.preventDefault();
    fileZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handleFileSelect(fileInput);
    }
}

/* ── Checklist ───────────────────────────────────────────────────── */
function setCheck(id, done) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'check-item ' + (done ? 'done' : 'todo');
    el.querySelector('i').className = 'fas ' + (done ? 'fa-circle-check' : 'fa-circle');
}

function updateChecklist() {
    const desc   = (document.querySelector('textarea[name="submission_text"]')?.value || '').trim();
    const github = (document.querySelector('input[name="github_link"]')?.value || '').trim();
    const file   = fileInput?.files?.length > 0;
    <?php $hasExistingFile = !empty($existingSub['file_name']); ?>
    const existingFile = <?php echo $hasExistingFile ? 'true' : 'false'; ?>;

    setCheck('chk-desc',   desc.length > 20);
    setCheck('chk-github', github.length > 10);
    setCheck('chk-file',   file || existingFile);
    setCheck('chk-review', desc.length > 50 && (github || file || existingFile));
}

// Wire up live checklist
document.querySelector('textarea[name="submission_text"]')
    ?.addEventListener('input', updateChecklist);
document.querySelector('input[name="github_link"]')
    ?.addEventListener('input', updateChecklist);
document.querySelector('input[name="submission_url"]')
    ?.addEventListener('input', updateChecklist);

// Init checklist on load
updateChecklist();

/* ── Form submit — prevent double submit ────────────────────────── */
document.getElementById('submitForm')?.addEventListener('submit', function(e) {
    const clickedBtn = document.activeElement;
    if (clickedBtn && clickedBtn.name === 'save_draft') return; // allow draft

    const desc   = (this.querySelector('textarea[name="submission_text"]')?.value || '').trim();
    const github = (this.querySelector('input[name="github_link"]')?.value || '').trim();
    const url    = (this.querySelector('input[name="submission_url"]')?.value || '').trim();
    const file   = fileInput?.files?.length > 0;
    <?php echo $hasExistingFile ? 'const hasFile=true;' : 'const hasFile=false;'; ?>

    if (!desc && !github && !url && !file && !hasFile) {
        e.preventDefault();
        alert('Please fill at least one field: description, GitHub link, URL, or upload a file.');
        return;
    }

    const btn = document.getElementById('submitBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…'; }
});

/* ── Sidebar toggle ─────────────────────────────────────────────── */
function toggleSidebar() {
    const sb = document.getElementById('mainSidebar');
    if (!sb) return;
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        sb.classList.toggle('mobile-open');
        document.getElementById('sidebarOverlay')?.classList.toggle('active');
    } else {
        sb.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sb.classList.contains('collapsed') ? '1' : '0');
        document.body.classList.toggle('sidebar-collapsed', sb.classList.contains('collapsed'));
    }
}
</script>
</body>
</html>