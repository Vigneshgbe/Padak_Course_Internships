<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { 
    ob_end_clean();
    header('Location: login.php'); 
    exit; 
}
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'messenger';

// =============================================
// UPLOADS DIRECTORY SETUP
// =============================================
$uploadsDir = __DIR__ . '/uploads/chat_attachments';
$uploadsUrl = 'uploads/chat_attachments';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Update online status
$db->query("UPDATE internship_students SET is_online=1, last_seen=NOW() WHERE id=$sid");

// =============================================
// AJAX HANDLERS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clear any buffered output (PHP warnings etc.) before sending JSON
    ob_start();
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // --------------------------------------------------
    // DEBUG ACTION - call with action=debug to diagnose
    // --------------------------------------------------
    if ($_POST['action'] === 'debug') {
        $uploadsWritable = is_writable($uploadsDir);
        $gdEnabled = extension_loaded('gd');
        $uploadMaxSize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        echo json_encode([
            'uploads_dir' => $uploadsDir,
            'uploads_dir_exists' => is_dir($uploadsDir),
            'uploads_writable' => $uploadsWritable,
            'gd_enabled' => $gdEnabled,
            'upload_max_filesize' => $uploadMaxSize,
            'post_max_size' => $postMaxSize,
            'php_version' => PHP_VERSION,
        ]);
        exit;
    }

    // --------------------------------------------------
    // SEND MESSAGE
    // --------------------------------------------------
    if ($_POST['action'] === 'send') {
        $roomId    = (int)($_POST['room_id'] ?? 0);
        $msg       = trim($_POST['message'] ?? '');
        $replyToId = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

        // Check if a file was uploaded (even with upload errors we need to know)
        $hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;

        if (!$roomId) {
            echo json_encode(['success' => false, 'error' => 'Invalid room ID']);
            exit;
        }

        if (!$msg && !$hasFile) {
            echo json_encode(['success' => false, 'error' => 'Message or file required']);
            exit;
        }

        // Verify room membership
        $chk = $db->prepare("SELECT id FROM chat_room_members WHERE room_id=? AND student_id=?");
        $chk->bind_param("ii", $roomId, $sid);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Not a member of this room']);
            exit;
        }

        $attachmentPath = null;
        $attachmentType = null;
        $attachmentName = null;

        // --------------------------------------------------
        // FILE UPLOAD HANDLING
        // --------------------------------------------------
        if ($hasFile) {
            $fileError = $_FILES['attachment']['error'];

            // Handle specific PHP upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit (' . ini_get('upload_max_filesize') . ')',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit',
                    UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'No temp directory on server',
                    UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk - check server permissions',
                    UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
                ];
                $errMsg = $errorMessages[$fileError] ?? "Upload error code: $fileError";
                echo json_encode(['success' => false, 'error' => $errMsg]);
                exit;
            }

            // Check uploads directory is writable
            if (!is_writable($uploadsDir)) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Server uploads folder is not writable. Run: chmod 755 uploads/chat_attachments'
                ]);
                exit;
            }

            $file     = $_FILES['attachment'];
            $fileName = basename($file['name']); // basename prevents directory traversal
            $fileTmp  = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // File size limit: 10MB
            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)']);
                exit;
            }

            $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedFiles  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];

            if (in_array($fileExt, $allowedImages)) {
                // ---- IMAGE UPLOAD ----
                $attachmentType = 'image';

                // Check GD is available
                if (!extension_loaded('gd')) {
                    // GD not available - just move the file as-is without optimization
                    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                    $destPath    = $uploadsDir . '/' . $newFileName;
                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $attachmentPath = $uploadsUrl . '/' . $newFileName;
                        $attachmentName = $fileName;
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to save image file']);
                        exit;
                    }
                } else {
                    // GD available - optimize image
                    $newFileName   = uniqid() . '_' . time() . '.jpg';
                    $optimizedPath = $uploadsDir . '/' . $newFileName;

                    $image = null;
                    switch ($fileExt) {
                        case 'jpg':
                        case 'jpeg': $image = @imagecreatefromjpeg($fileTmp); break;
                        case 'png':  $image = @imagecreatefrompng($fileTmp);  break;
                        case 'gif':  $image = @imagecreatefromgif($fileTmp);  break;
                        case 'webp': $image = @imagecreatefromwebp($fileTmp); break;
                    }

                    if (!$image) {
                        // GD failed to read image - fall back to raw move
                        $rawName = uniqid() . '_' . time() . '.' . $fileExt;
                        $rawPath = $uploadsDir . '/' . $rawName;
                        if (move_uploaded_file($fileTmp, $rawPath)) {
                            $attachmentPath = $uploadsUrl . '/' . $rawName;
                            $attachmentName = $fileName;
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Failed to process image']);
                            exit;
                        }
                    } else {
                        $width    = imagesx($image);
                        $height   = imagesy($image);
                        $maxWidth = 1200;

                        if ($width > $maxWidth) {
                            $ratio    = $maxWidth / $width;
                            $newW     = $maxWidth;
                            $newH     = (int)($height * $ratio);
                            $resized  = imagecreatetruecolor($newW, $newH);

                            // Preserve transparency for PNG
                            imagealphablending($resized, false);
                            imagesavealpha($resized, true);
                            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                            imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);

                            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
                            $saved = imagejpeg($resized, $optimizedPath, 85);
                            imagedestroy($resized);
                        } else {
                            $saved = imagejpeg($image, $optimizedPath, 85);
                        }
                        imagedestroy($image);

                        if (!$saved || !file_exists($optimizedPath)) {
                            echo json_encode(['success' => false, 'error' => 'Failed to save optimized image']);
                            exit;
                        }

                        $attachmentPath = $uploadsUrl . '/' . $newFileName;
                        $attachmentName = $fileName;
                    }
                }

            } elseif (in_array($fileExt, $allowedFiles)) {
                // ---- DOCUMENT/FILE UPLOAD ----
                $attachmentType = 'file';
                $newFileName    = uniqid() . '_' . time() . '.' . $fileExt;
                $destPath       = $uploadsDir . '/' . $newFileName;

                if (move_uploaded_file($fileTmp, $destPath)) {
                    $attachmentPath = $uploadsUrl . '/' . $newFileName;
                    $attachmentName = $fileName;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to save file. Check folder permissions.']);
                    exit;
                }

            } else {
                echo json_encode(['success' => false, 'error' => "File type '.$fileExt' not allowed"]);
                exit;
            }
        }

        // --------------------------------------------------
        // INSERT MESSAGE INTO DB
        // --------------------------------------------------
        $stmt = $db->prepare(
            "INSERT INTO chat_messages (room_id, sender_id, message, reply_to_id, attachment_path, attachment_type, attachment_name) 
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->bind_param("iisisss", $roomId, $sid, $msg, $replyToId, $attachmentPath, $attachmentType, $attachmentName);

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $stmt->error]);
            exit;
        }

        $msgId = $db->insert_id;

        // Get reply message info if replying
        $replyMsg = null;
        if ($replyToId) {
            $rs = $db->prepare("SELECT cm.message, cm.attachment_type, s.full_name FROM chat_messages cm JOIN internship_students s ON s.id=cm.sender_id WHERE cm.id=?");
            $rs->bind_param("i", $replyToId);
            $rs->execute();
            $replyMsg = $rs->get_result()->fetch_assoc();
        }

        echo json_encode([
            'success'         => true,
            'msg_id'          => $msgId,
            'time'            => date('h:i A'),
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'attachment_name' => $attachmentName,
            'reply_msg'       => $replyMsg,
        ]);
        exit;
    }

    // --------------------------------------------------
    // FETCH NEW MESSAGES (polling)
    // --------------------------------------------------
    if ($_POST['action'] === 'fetch') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $since  = (int)($_POST['since_id'] ?? 0);
        $msgs   = [];

        $stmt = $db->prepare(
            "SELECT cm.id, cm.message, cm.created_at, cm.sender_id, cm.reply_to_id,
                cm.attachment_path, cm.attachment_type, cm.attachment_name,
                s.full_name, s.profile_photo,
                rm.message as reply_message, rm.attachment_type as reply_attachment_type,
                rs.full_name as reply_sender_name
             FROM chat_messages cm
             JOIN internship_students s ON s.id = cm.sender_id
             LEFT JOIN chat_messages rm ON rm.id = cm.reply_to_id
             LEFT JOIN internship_students rs ON rs.id = rm.sender_id
             WHERE cm.room_id=? AND cm.id>? AND cm.is_deleted=0
             ORDER BY cm.id ASC LIMIT 50"
        );
        $stmt->bind_param("ii", $roomId, $since);
        $stmt->execute();
        $res = $stmt->get_result();

        // Store all message rows first to avoid "commands out of sync"
        $allRows = [];
        while ($r = $res->fetch_assoc()) $allRows[] = $r;
        $stmt->free_result();
        $stmt->close();

        // Prepare reaction statement once, reuse it
        $reactStmt = $db->prepare("SELECT emoji, student_id, s.full_name FROM message_reactions mr JOIN internship_students s ON s.id=mr.student_id WHERE mr.message_id=?");
        foreach ($allRows as $r) {
            $reactions = [];
            $reactStmt->bind_param("i", $r['id']);
            $reactStmt->execute();
            $reactRes = $reactStmt->get_result();
            while ($react = $reactRes->fetch_assoc()) {
                if (!isset($reactions[$react['emoji']])) {
                    $reactions[$react['emoji']] = ['count' => 0, 'users' => [], 'has_reacted' => false];
                }
                $reactions[$react['emoji']]['count']++;
                $reactions[$react['emoji']]['users'][] = $react['full_name'];
                if ($react['student_id'] == $sid) $reactions[$react['emoji']]['has_reacted'] = true;
            }
            $reactRes->free();
            $r['reactions'] = $reactions;
            $msgs[] = $r;
        }
        $reactStmt->close();

        $upd = $db->prepare("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=? AND student_id=?");
        $upd->bind_param("ii", $roomId, $sid);
        $upd->execute();

        echo json_encode(['messages' => $msgs, 'typing' => []]);
        exit;
    }

    // --------------------------------------------------
    // REACT TO MESSAGE
    // --------------------------------------------------
    if ($_POST['action'] === 'react') {
        $msgId = (int)($_POST['msg_id'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');

        if ($msgId && $emoji) {
            $checkStmt = $db->prepare("SELECT id FROM message_reactions WHERE message_id=? AND student_id=? AND emoji=?");
            $checkStmt->bind_param("iis", $msgId, $sid, $emoji);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;

            if ($exists) {
                $del = $db->prepare("DELETE FROM message_reactions WHERE message_id=? AND student_id=? AND emoji=?");
                $del->bind_param("iis", $msgId, $sid, $emoji);
                $del->execute();
                echo json_encode(['success' => true, 'action' => 'removed']);
            } else {
                $ins = $db->prepare("INSERT INTO message_reactions (message_id, student_id, emoji) VALUES (?,?,?)");
                $ins->bind_param("iis", $msgId, $sid, $emoji);
                $ins->execute();
                echo json_encode(['success' => true, 'action' => 'added']);
            }
            exit;
        }
        echo json_encode(['success' => false]);
        exit;
    }

    // --------------------------------------------------
    // START DIRECT MESSAGE
    // --------------------------------------------------
    if ($_POST['action'] === 'start_dm') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId && $targetId != $sid) {
            $pair = $db->query("SELECT room_id FROM direct_message_pairs WHERE (student1_id=$sid AND student2_id=$targetId) OR (student1_id=$targetId AND student2_id=$sid)")->fetch_assoc();
            if ($pair) {
                echo json_encode(['success' => true, 'room_id' => $pair['room_id']]);
                exit;
            }
            $targetUser = $db->query("SELECT full_name FROM internship_students WHERE id=$targetId")->fetch_assoc();
            $roomName   = $targetUser['full_name'];
            $type       = 'direct';
            $stmt       = $db->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?,?,?)");
            $stmt->bind_param("ssi", $roomName, $type, $sid);
            if ($stmt->execute()) {
                $roomId  = $db->insert_id;
                $addStmt = $db->prepare("INSERT INTO chat_room_members (room_id, student_id) VALUES (?,?)");
                $addStmt->bind_param("ii", $roomId, $sid); $addStmt->execute();
                $addStmt->bind_param("ii", $roomId, $targetId); $addStmt->execute();
                $pairStmt = $db->prepare("INSERT INTO direct_message_pairs (room_id, student1_id, student2_id) VALUES (?,?,?)");
                $pairStmt->bind_param("iii", $roomId, $sid, $targetId); $pairStmt->execute();
                echo json_encode(['success' => true, 'room_id' => $roomId]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        exit;
    }

    // --------------------------------------------------
    // CREATE GROUP CHAT
    // --------------------------------------------------
    if ($_POST['action'] === 'create_group') {
        $name      = trim($_POST['room_name'] ?? '');
        $memberIds = json_decode($_POST['member_ids'] ?? '[]', true);
        if ($name && is_array($memberIds) && count($memberIds) > 0) {
            $type = 'group';
            $stmt = $db->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?,?,?)");
            $stmt->bind_param("ssi", $name, $type, $sid);
            if ($stmt->execute()) {
                $roomId  = $db->insert_id;
                $addStmt = $db->prepare("INSERT IGNORE INTO chat_room_members (room_id, student_id) VALUES (?,?)");
                $addStmt->bind_param("ii", $roomId, $sid); $addStmt->execute();
                foreach ($memberIds as $mid) {
                    $mid = (int)$mid;
                    if ($mid > 0 && $mid != $sid) {
                        $addStmt->bind_param("ii", $roomId, $mid); $addStmt->execute();
                    }
                }
                echo json_encode(['success' => true, 'room_id' => $roomId]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        exit;
    }

    // --------------------------------------------------
    // SEARCH USERS
    // --------------------------------------------------
    if ($_POST['action'] === 'search_users') {
        $query = trim($_POST['query'] ?? '');
        $users = [];
        if (strlen($query) >= 2) {
            $q    = '%' . $query . '%';
            $stmt = $db->prepare("SELECT id, full_name, email, profile_photo, domain_interest, is_online FROM internship_students WHERE id!=? AND is_active=1 AND (full_name LIKE ? OR email LIKE ?) LIMIT 20");
            $stmt->bind_param("iss", $sid, $q, $q);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $users[] = $r;
        }
        echo json_encode($users);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// =============================================
// PAGE DATA LOADING
// =============================================
$rooms = [];
$res = $db->query("SELECT cr.*,
    (SELECT cm2.message FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.id DESC LIMIT 1) as last_msg,
    (SELECT cm2.created_at FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.id DESC LIMIT 1) as last_msg_time,
    (SELECT COUNT(*) FROM chat_messages cm2
        JOIN chat_room_members crm2 ON crm2.room_id=cm2.room_id AND crm2.student_id=$sid
        WHERE cm2.room_id=cr.id AND cm2.sender_id!=$sid AND cm2.is_deleted=0
        AND (crm2.last_read_at IS NULL OR cm2.created_at > crm2.last_read_at)) as unread,
    (SELECT s.full_name FROM internship_students s
        JOIN direct_message_pairs dmp ON (dmp.student1_id=s.id OR dmp.student2_id=s.id)
        WHERE dmp.room_id=cr.id AND s.id!=$sid LIMIT 1) as dm_partner_name,
    (SELECT s.profile_photo FROM internship_students s
        JOIN direct_message_pairs dmp ON (dmp.student1_id=s.id OR dmp.student2_id=s.id)
        WHERE dmp.room_id=cr.id AND s.id!=$sid LIMIT 1) as dm_partner_photo,
    (SELECT s.is_online FROM internship_students s
        JOIN direct_message_pairs dmp ON (dmp.student1_id=s.id OR dmp.student2_id=s.id)
        WHERE dmp.room_id=cr.id AND s.id!=$sid LIMIT 1) as dm_partner_online
    FROM chat_rooms cr
    JOIN chat_room_members crm ON crm.room_id=cr.id AND crm.student_id=$sid
    WHERE cr.is_active=1
    ORDER BY COALESCE(last_msg_time, cr.created_at) DESC LIMIT 50");

if ($res) while ($r = $res->fetch_assoc()) {
    if ($r['room_type'] === 'direct' && $r['dm_partner_name']) $r['room_name'] = $r['dm_partner_name'];
    $rooms[] = $r;
}

$activeRoomId = (int)($_GET['room'] ?? ($rooms[0]['id'] ?? 0));
$activeRoom   = null;
foreach ($rooms as $r) { if ($r['id'] == $activeRoomId) { $activeRoom = $r; break; } }

$messages  = [];
$lastMsgId = 0;
if ($activeRoomId) {
    $res = $db->query("SELECT cm.*, s.full_name, s.profile_photo,
        rm.message as reply_message, rm.attachment_type as reply_attachment_type,
        rs.full_name as reply_sender_name
        FROM chat_messages cm
        JOIN internship_students s ON s.id=cm.sender_id
        LEFT JOIN chat_messages rm ON rm.id=cm.reply_to_id
        LEFT JOIN internship_students rs ON rs.id=rm.sender_id
        WHERE cm.room_id=$activeRoomId AND cm.is_deleted=0
        ORDER BY cm.created_at ASC LIMIT 100");
    // Store all rows first to avoid "commands out of sync" with nested prepared statements
    $allMsgRows = [];
    if ($res) while ($r = $res->fetch_assoc()) $allMsgRows[] = $r;

    // Prepare reaction statement once and reuse
    $reactStmt = $db->prepare("SELECT emoji, student_id, s.full_name FROM message_reactions mr JOIN internship_students s ON s.id=mr.student_id WHERE mr.message_id=?");
    foreach ($allMsgRows as $r) {
        $reactions = [];
        $reactStmt->bind_param("i", $r['id']);
        $reactStmt->execute();
        $reactRes = $reactStmt->get_result();
        while ($react = $reactRes->fetch_assoc()) {
            if (!isset($reactions[$react['emoji']])) $reactions[$react['emoji']] = ['count' => 0, 'users' => [], 'has_reacted' => false];
            $reactions[$react['emoji']]['count']++;
            $reactions[$react['emoji']]['users'][] = $react['full_name'];
            if ($react['student_id'] == $sid) $reactions[$react['emoji']]['has_reacted'] = true;
        }
        $reactRes->free();
        $r['reactions'] = $reactions;
        $messages[]     = $r;
        $lastMsgId      = max($lastMsgId, $r['id']);
    }
    if ($reactStmt) $reactStmt->close();
    $db->query("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=$activeRoomId AND student_id=$sid");
}

$roomMembers = [];
if ($activeRoomId) {
    $rm = $db->query("SELECT s.id, s.full_name, s.domain_interest, s.profile_photo, s.is_online, s.last_seen FROM internship_students s JOIN chat_room_members crm ON crm.student_id=s.id WHERE crm.room_id=$activeRoomId ORDER BY s.full_name");
    if ($rm) while ($r = $rm->fetch_assoc()) $roomMembers[] = $r;
}

$allStudents = [];
$allRes = $db->query("SELECT id, full_name, email, domain_interest, profile_photo, is_online FROM internship_students WHERE id!=$sid AND is_active=1 ORDER BY full_name LIMIT 100");
if ($allRes) while ($r = $allRes->fetch_assoc()) $allStudents[] = $r;

$initials = strtoupper(substr($student['full_name'], 0, 1));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Team Messenger</title>
<link rel="icon" type="image/x-icon" href="https://github.com/Vigneshgbe/Padak-Marketing-Website/blob/main/frontend/src/assets/padak_p.png?raw=true">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--sbw:258px;--o5:#f97316;--o4:#fb923c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--green:#22c55e;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);height:100vh;overflow:hidden;}

/* ── DESKTOP LAYOUT ─────────────────────────────────────── */
.page-wrap{
    margin-left:var(--sbw);
    height:100vh;
    display:flex;
    flex-direction:column;
    overflow:hidden;   /* critical: contain everything inside viewport */
}
.topbar{
    flex-shrink:0;
    background:rgba(248,250,252,0.95);
    backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:10px 20px;
    display:flex;
    align-items:center;
    gap:10px;
}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;font-size:1.2rem;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.btn-new-chat{background:var(--o5);border:none;color:#fff;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:6px;transition:opacity .2s;}
.btn-new-chat:hover{opacity:.9;}

/* KEY FIX: chat-layout must fill remaining height without overflow */
.chat-layout{
    flex:1;
    display:grid;
    grid-template-columns:320px 1fr;
    min-height:0;          /* allow grid children to shrink below content size */
    overflow:hidden;
}

/* Conversations sidebar */
.rooms-panel{
    border-right:1px solid var(--border);
    display:flex;
    flex-direction:column;
    background:var(--card);
    min-height:0;
    overflow:hidden;
}
.search-box{flex-shrink:0;padding:12px;border-bottom:1px solid var(--border);}
.search-input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:.88rem;outline:none;font-family:inherit;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23475569' viewBox='0 0 24 24'%3E%3Cpath d='M15.5 14h-.79l-.28-.27a6.5 6.5 0 001.48-5.34c-.47-2.78-2.79-5-5.59-5.34a6.505 6.505 0 00-7.27 7.27c.34 2.8 2.56 5.12 5.34 5.59a6.5 6.5 0 005.34-1.48l.27.28v.79l4.25 4.25c.41.41 1.08.41 1.49 0 .41-.41.41-1.08 0-1.49L15.5 14zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E") no-repeat 12px center/18px;}
.search-input:focus{border-color:var(--o5);}
.rooms-list{flex:1;overflow-y:auto;padding:4px;min-height:0;}
.room-item{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:10px;cursor:pointer;transition:background .15s;text-decoration:none;position:relative;}
.room-item:hover{background:#f1f5f9;text-decoration:none;}
.room-item.active{background:rgba(249,115,22,0.09);}
.room-avatar-wrap{position:relative;flex-shrink:0;}
.room-avatar{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:#fff;}
.room-avatar.group{background:linear-gradient(135deg,var(--o5),var(--o4));}
.room-avatar.direct{background:linear-gradient(135deg,#3b82f6,#60a5fa);}
.room-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.online-dot{position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:var(--green);border:2px solid var(--card);border-radius:50%;}
.room-info{flex:1;min-width:0;}
.room-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:3px;}
.room-name{font-size:.88rem;font-weight:600;color:var(--text);}
.room-time{font-size:.7rem;color:var(--text3);}
.room-last{font-size:.78rem;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.room-badge{position:absolute;top:10px;right:10px;background:var(--o5);color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:10px;}

/* KEY FIX: chat-area is a flex column that must not overflow its grid cell */
.chat-area{
    display:flex;
    flex-direction:column;
    background:var(--bg);
    min-height:0;          /* allow flexbox to shrink it */
    overflow:hidden;
}
.chat-head{flex-shrink:0;padding:12px 20px;border-bottom:1px solid var(--border);background:var(--card);display:flex;align-items:center;gap:12px;}
.chat-head-back{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;margin-right:4px;font-size:1.1rem;}
.chat-head-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem;flex-shrink:0;position:relative;}
.chat-head-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.chat-room-name{font-size:.98rem;font-weight:700;color:var(--text);}
.chat-room-meta{font-size:.74rem;color:var(--text3);}

/* KEY FIX: messages area scrolls, not the whole page */
.chat-messages{
    flex:1;
    overflow-y:auto;
    overflow-x:hidden;
    padding:16px 20px;
    display:flex;
    flex-direction:column;
    gap:12px;
    min-height:0;          /* critical for flex scroll to work */
}

/* Message bubbles */
.msg-group{display:flex;gap:9px;align-items:flex-start;}
.msg-group.mine{flex-direction:row-reverse;}
.msg-ava{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#64748b,#475569);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;flex-shrink:0;}
.msg-ava img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.msg-group.mine .msg-ava{background:linear-gradient(135deg,var(--o5),var(--o4));}
.msg-bubble-wrap{max-width:65%;display:flex;flex-direction:column;gap:2px;position:relative;}
.msg-group.mine .msg-bubble-wrap{align-items:flex-end;}
.msg-sender-name{font-size:.72rem;color:var(--text3);padding:0 4px;}
.msg-bubble{padding:10px 14px;border-radius:14px;font-size:.88rem;line-height:1.5;word-break:break-word;position:relative;}
.msg-bubble.other{background:var(--card);border:1px solid var(--border);border-bottom-left-radius:4px;color:var(--text);box-shadow:0 1px 2px rgba(0,0,0,0.05);}
.msg-bubble.mine-b{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;border-bottom-right-radius:4px;box-shadow:0 2px 8px rgba(249,115,22,0.25);}

/* Image-only bubbles: keep orange wrapper but let image breathe */
.msg-bubble.mine-b:has(.msg-attachment img):not(:has(> br)):not(:has(> .reply-preview)) {
    padding:6px;
}

.reply-preview{background:rgba(0,0,0,0.08);border-left:3px solid rgba(0,0,0,0.2);padding:6px 10px;border-radius:6px;margin-bottom:8px;font-size:.8rem;}
.msg-bubble.mine-b .reply-preview{background:rgba(255,255,255,0.15);border-left-color:rgba(255,255,255,0.4);}
.reply-preview-name{font-weight:600;margin-bottom:2px;font-size:.75rem;}
.reply-preview-text{opacity:.85;font-size:.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* Attachments */
.msg-attachment{margin-top:4px;}
.msg-attachment img{max-width:100%;max-height:300px;border-radius:6px;cursor:pointer;display:block;}
.msg-bubble.mine-b .msg-attachment img{border-radius:8px;}
.msg-attachment.file{display:flex;align-items:center;gap:8px;padding:8px 12px;background:rgba(0,0,0,0.05);border-radius:8px;text-decoration:none;color:inherit;}
.msg-bubble.mine-b .msg-attachment.file{background:rgba(255,255,255,0.15);}
.file-icon{width:32px;height:32px;background:var(--o5);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;}
.file-info{flex:1;min-width:0;}
.file-name{font-size:.82rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.file-download{color:var(--o5);font-size:.9rem;}
.msg-bubble.mine-b .file-download{color:#fff;}

.msg-reactions{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;}
.reaction-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:2px 8px;font-size:.8rem;display:flex;align-items:center;gap:4px;cursor:pointer;transition:all .15s;}
.reaction-item:hover{transform:scale(1.1);}
.reaction-item.has-reacted{background:rgba(249,115,22,0.1);border-color:var(--o5);}
.reaction-count{font-size:.72rem;font-weight:600;color:var(--text2);}
.msg-actions{position:absolute;top:-10px;right:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:4px;display:none;box-shadow:0 2px 8px rgba(0,0,0,0.1);z-index:10;}
.msg-bubble-wrap:hover .msg-actions{display:flex;}
.msg-action-btn{background:none;border:none;cursor:pointer;padding:4px 6px;border-radius:4px;font-size:.85rem;color:var(--text2);transition:background .15s;}
.msg-action-btn:hover{background:var(--bg);}
.msg-time{font-size:.68rem;color:var(--text3);padding:0 4px;}
.no-msgs{text-align:center;padding:60px 20px;color:var(--text3);}
.no-msgs i{font-size:3rem;margin-bottom:12px;display:block;opacity:.2;}

/* KEY FIX: input bar always visible at bottom, never pushed off screen */
.chat-input-area{
    flex-shrink:0;
    padding:14px 20px;
    border-top:1px solid var(--border);
    background:var(--card);
}
.reply-bar{display:none;padding:8px 12px;background:#f8f9fa;border-left:3px solid var(--o5);border-radius:6px;margin-bottom:8px;font-size:.82rem;}
.reply-bar.active{display:block;}
.reply-bar-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.reply-bar-label{font-weight:600;color:var(--o5);font-size:.75rem;}
.reply-bar-close{background:none;border:none;cursor:pointer;color:var(--text3);padding:2px;font-size:.9rem;}
.reply-bar-text{color:var(--text2);font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.image-preview-bar{display:none;padding:8px 12px;background:#f8f9fa;border-radius:6px;margin-bottom:8px;position:relative;}
.image-preview-bar.active{display:flex;align-items:center;gap:10px;}
.image-preview-thumb{width:60px;height:60px;border-radius:6px;object-fit:cover;border:2px solid var(--border);}
.image-preview-info{flex:1;}
.image-preview-name{font-size:.82rem;font-weight:600;color:var(--text);}
.image-preview-size{font-size:.72rem;color:var(--text3);}
.image-preview-remove{background:none;border:none;cursor:pointer;color:var(--text3);padding:4px;font-size:1.1rem;position:absolute;top:4px;right:4px;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;}
.chat-form{display:flex;gap:10px;align-items:flex-end;}
.attach-btn{background:none;border:none;cursor:pointer;color:var(--text2);padding:8px;border-radius:8px;font-size:1.1rem;transition:background .15s;}
.attach-btn:hover{background:var(--bg);}
.chat-input{flex:1;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;outline:none;resize:none;max-height:120px;transition:border-color .2s;}
.chat-input:focus{border-color:var(--o5);}
.send-btn{width:42px;height:42px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s;box-shadow:0 4px 12px rgba(249,115,22,0.3);}
.send-btn:hover{opacity:.9;}
.send-btn:disabled{opacity:.5;cursor:not-allowed;}
.emoji-picker{display:none;position:fixed;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:1000;}
.emoji-picker.active{display:flex;}
.emoji-picker-emoji{font-size:1.3rem;cursor:pointer;padding:6px;border-radius:6px;transition:background .15s;}
.emoji-picker-emoji:hover{background:var(--bg);}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-bg.open{display:flex;}
.modal{background:var(--card);border-radius:14px;padding:24px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.modal-label{display:block;font-size:.84rem;font-weight:600;margin-bottom:6px;color:var(--text);}
.modal-input{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;outline:none;margin-bottom:14px;}
.modal-input:focus{border-color:var(--o5);}
.user-list{display:flex;flex-direction:column;gap:4px;max-height:350px;overflow-y:auto;margin-bottom:14px;}
.user-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;cursor:pointer;transition:background .15s;border:1.5px solid transparent;}
.user-item:hover{background:#f1f5f9;}
.user-item.selected{background:rgba(249,115,22,0.08);border-color:var(--o5);}
.user-ava{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;position:relative;}
.user-ava img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.user-info{flex:1;min-width:0;}
.user-name{font-size:.86rem;font-weight:600;color:var(--text);}
.user-email{font-size:.72rem;color:var(--text3);}
.user-check{width:20px;height:20px;border:2px solid var(--border);border-radius:4px;flex-shrink:0;}
.user-item.selected .user-check{background:var(--o5);border-color:var(--o5);display:flex;align-items:center;justify-content:center;color:#fff;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;}
.modal-btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:.86rem;font-weight:600;font-family:inherit;transition:opacity .2s;}
.modal-btn.cancel{background:var(--bg);border:1px solid var(--border);color:var(--text2);}
.modal-btn.create{background:var(--o5);color:#fff;}
.modal-btn:hover{opacity:.9;}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text3);padding:40px;}
.empty-state i{font-size:3.5rem;opacity:.15;margin-bottom:16px;}
.empty-state h3{font-size:1.1rem;font-weight:600;margin-bottom:6px;}
.image-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.image-modal.open{display:flex;}
.image-modal img{max-width:90%;max-height:90vh;border-radius:8px;}
.image-modal-close{position:absolute;top:20px;right:20px;background:rgba(255,255,255,0.2);border:none;color:#fff;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:1.2rem;}
/* Sending overlay */
.send-overlay{display:none;position:absolute;inset:0;background:rgba(255,255,255,0.7);align-items:center;justify-content:center;border-radius:10px;z-index:5;}
.send-overlay.active{display:flex;}
@media(max-width:768px){
    .page-wrap{margin-left:0;height:100vh;height:100dvh;}
    .topbar-hamburger{display:flex;}
    .chat-layout{grid-template-columns:1fr;position:relative;overflow:hidden;}
    .rooms-panel{position:absolute;inset:0;z-index:100;transform:translateX(-100%);transition:transform .3s ease;min-height:0;}
    .rooms-panel.mobile-visible{transform:translateX(0);}
    .chat-area{position:absolute;inset:0;z-index:50;min-height:0;overflow:hidden;}
    .chat-messages{flex:1;min-height:0;overflow-y:auto;}
    .chat-head-back{display:flex;}
    body:not(.room-selected) .chat-area{display:none;}
    body:not(.room-selected) .rooms-panel{position:relative;transform:translateX(0);}
    .modal{width:95%;max-width:none;max-height:85vh;padding:20px;}
    .msg-bubble-wrap{max-width:82%;}
    .chat-input-area{flex-shrink:0;padding:10px 12px;}
    .msg-actions{display:flex !important;position:static;margin-top:4px;background:transparent;border:none;box-shadow:none;padding:0;}
    .msg-action-btn{padding:3px 5px;}
}
</style>
</head>
<body<?php echo $activeRoomId ? ' class="room-selected"' : ''; ?>>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleRoomsPanel()"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Messenger</div>
        <button class="btn-new-chat" onclick="openNewChatModal()">
            <i class="fas fa-plus fa-sm"></i><span> New Chat</span>
        </button>
    </div>

    <div class="chat-layout">
        <!-- Conversations List -->
        <div class="rooms-panel" id="roomsPanel">
            <div class="search-box">
                <input type="text" class="search-input" placeholder="Search conversations..." id="searchConvo">
            </div>
            <div class="rooms-list" id="roomsList">
                <?php if (empty($rooms)): ?>
                <div style="padding:40px 20px;text-align:center;color:var(--text3);">
                    <i class="fas fa-comments" style="font-size:2rem;opacity:.2;display:block;margin-bottom:10px;"></i>
                    <p style="font-size:.85rem;">No conversations yet.<br>Start a new chat!</p>
                </div>
                <?php else: ?>
                <?php foreach ($rooms as $r):
                    $isOnline = ($r['room_type'] === 'direct' && $r['dm_partner_online']);
                    $lastTime = $r['last_msg_time'] ? date('h:i A', strtotime($r['last_msg_time'])) : '';
                ?>
                <a href="?room=<?php echo $r['id']; ?>" class="room-item <?php echo $r['id']==$activeRoomId?'active':''; ?>" onclick="handleRoomClick(event,<?php echo $r['id']; ?>)">
                    <div class="room-avatar-wrap">
                        <div class="room-avatar <?php echo $r['room_type']; ?>">
                            <?php if ($r['room_type']==='group'): ?>
                                <i class="fas fa-users"></i>
                            <?php elseif (!empty($r['dm_partner_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($r['dm_partner_photo']); ?>" alt="">
                            <?php else: ?>
                                <?php echo strtoupper(substr($r['room_name'],0,1)); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($isOnline): ?><div class="online-dot"></div><?php endif; ?>
                    </div>
                    <div class="room-info">
                        <div class="room-top">
                            <div class="room-name"><?php echo htmlspecialchars($r['room_name']); ?></div>
                            <div class="room-time"><?php echo $lastTime; ?></div>
                        </div>
                        <div class="room-last"><?php echo $r['last_msg'] ? htmlspecialchars(substr($r['last_msg'],0,40)) : 'Start a conversation'; ?></div>
                    </div>
                    <?php if ($r['unread'] > 0): ?><span class="room-badge"><?php echo $r['unread']; ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($activeRoom): ?>
            <div class="chat-head">
                <button class="chat-head-back" onclick="goBackToRooms()"><i class="fas fa-arrow-left"></i></button>
                <div class="chat-head-avatar">
                    <?php if ($activeRoom['room_type']==='group'): ?>
                        <i class="fas fa-users"></i>
                    <?php elseif (!empty($activeRoom['dm_partner_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($activeRoom['dm_partner_photo']); ?>" alt="">
                    <?php else: ?>
                        <?php echo strtoupper(substr($activeRoom['room_name'],0,1)); ?>
                    <?php endif; ?>
                    <?php if ($activeRoom['room_type']==='direct' && $activeRoom['dm_partner_online']): ?>
                        <div class="online-dot"></div>
                    <?php endif; ?>
                </div>
                <div style="flex:1;">
                    <div class="chat-room-name"><?php echo htmlspecialchars($activeRoom['room_name']); ?></div>
                    <div class="chat-room-meta">
                        <?php if ($activeRoom['room_type']==='direct'): ?>
                            <?php echo $activeRoom['dm_partner_online'] ? 'Online' : 'Offline'; ?>
                        <?php else: ?>
                            <?php echo count($roomMembers); ?> members
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                <div class="no-msgs"><i class="fas fa-comments"></i><p>No messages yet. Start the conversation!</p></div>
                <?php else: ?>
                <?php foreach ($messages as $msg):
                    $isMe = $msg['sender_id'] == $sid;
                ?>
                <div class="msg-group <?php echo $isMe?'mine':''; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                    <div class="msg-ava">
                        <?php if ($msg['profile_photo']): ?>
                            <img src="<?php echo htmlspecialchars($msg['profile_photo']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($msg['full_name'],0,1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="msg-bubble-wrap">
                        <?php if (!$isMe && $activeRoom['room_type']==='group'): ?>
                            <div class="msg-sender-name"><?php echo htmlspecialchars(explode(' ',$msg['full_name'])[0]); ?></div>
                        <?php endif; ?>
                        <div class="msg-bubble <?php echo $isMe?'mine-b':'other'; ?>">
                            <?php if ($msg['reply_to_id']): ?>
                            <div class="reply-preview">
                                <div class="reply-preview-name"><?php echo htmlspecialchars($msg['reply_sender_name']); ?></div>
                                <div class="reply-preview-text">
                                    <?php if ($msg['reply_attachment_type']==='image'): ?><i class="fas fa-image"></i> Photo
                                    <?php elseif ($msg['reply_attachment_type']==='file'): ?><i class="fas fa-file"></i> File
                                    <?php else: ?><?php echo htmlspecialchars(substr($msg['reply_message'],0,40)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($msg['message']): ?><?php echo nl2br(htmlspecialchars($msg['message'])); ?><?php endif; ?>
                            <?php if ($msg['attachment_path']): ?>
                            <div class="msg-attachment">
                                <?php if ($msg['attachment_type']==='image'): ?>
                                    <img src="<?php echo htmlspecialchars($msg['attachment_path']); ?>" alt="Image" onclick="openImageModal(this.src)">
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($msg['attachment_path']); ?>" download="<?php echo htmlspecialchars($msg['attachment_name']); ?>" class="msg-attachment file">
                                        <div class="file-icon"><i class="fas fa-file"></i></div>
                                        <div class="file-info"><div class="file-name"><?php echo htmlspecialchars($msg['attachment_name']); ?></div></div>
                                        <i class="fas fa-download file-download"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="msg-actions">
                                <button class="msg-action-btn" onclick="showReactionPicker(<?php echo $msg['id']; ?>,this)" title="React"><i class="fas fa-smile"></i></button>
                                <button class="msg-action-btn" onclick="replyToMessage(<?php echo $msg['id']; ?>,'<?php echo addslashes($msg['full_name']); ?>','<?php echo addslashes($msg['message'] ?: ($msg['attachment_type']==='image'?'Photo':'File')); ?>')" title="Reply"><i class="fas fa-reply"></i></button>
                            </div>
                        </div>
                        <?php if (!empty($msg['reactions'])): ?>
                        <div class="msg-reactions">
                            <?php foreach ($msg['reactions'] as $emoji => $data): ?>
                            <div class="reaction-item <?php echo $data['has_reacted']?'has-reacted':''; ?>"
                                 onclick="toggleReaction(<?php echo $msg['id']; ?>,'<?php echo $emoji; ?>')"
                                 title="<?php echo implode(', ',$data['users']); ?>">
                                <span><?php echo $emoji; ?></span>
                                <span class="reaction-count"><?php echo $data['count']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="msg-time"><?php echo date('h:i A',strtotime($msg['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="chat-input-area">
                <div class="reply-bar" id="replyBar">
                    <div class="reply-bar-top">
                        <span class="reply-bar-label">Replying to <span id="replyUserName"></span></span>
                        <button class="reply-bar-close" onclick="cancelReply()"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="reply-bar-text" id="replyText"></div>
                </div>
                <div class="image-preview-bar" id="imagePreviewBar">
                    <img id="imagePreviewThumb" class="image-preview-thumb" src="" alt="">
                    <div class="image-preview-info">
                        <div class="image-preview-name" id="imagePreviewName"></div>
                        <div class="image-preview-size" id="imagePreviewSize"></div>
                    </div>
                    <button class="image-preview-remove" onclick="removeAttachmentPreview()"><i class="fas fa-times"></i></button>
                </div>
                <div class="chat-form">
                    <input type="file" id="fileInput" style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" onchange="handleFileSelect(this)">
                    <button class="attach-btn" onclick="document.getElementById('fileInput').click()" title="Attach file"><i class="fas fa-paperclip"></i></button>
                    <textarea class="chat-input" id="msgInput" placeholder="Type a message…" rows="1"
                        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                    <button class="send-btn" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane fa-sm"></i></button>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>No conversation selected</h3>
                <p>Choose a conversation or start a new one</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Emoji Picker -->
<div class="emoji-picker" id="emojiPicker">
    <span class="emoji-picker-emoji" onclick="reactWithEmoji('👍')">👍</span>
    <span class="emoji-picker-emoji" onclick="reactWithEmoji('❤️')">❤️</span>
    <span class="emoji-picker-emoji" onclick="reactWithEmoji('😂')">😂</span>
    <span class="emoji-picker-emoji" onclick="reactWithEmoji('😮')">😮</span>
    <span class="emoji-picker-emoji" onclick="reactWithEmoji('😢')">😢</span>
    <span class="emoji-picker-emoji" onclick="reactWithEmoji('🎉')">🎉</span>
</div>

<!-- Image Modal -->
<div class="image-modal" id="imageModal" onclick="closeImageModal()">
    <button class="image-modal-close"><i class="fas fa-times"></i></button>
    <img id="imageModalImg" src="" alt="">
</div>

<!-- New Chat Modal -->
<div class="modal-bg" id="newChatModal" onclick="if(event.target===this)closeNewChatModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <h3><i class="fas fa-comment-dots" style="color:var(--o5)"></i> Start New Chat</h3>
        <input type="text" id="userSearch" class="modal-input" placeholder="Search by name or email..." oninput="searchUsers(this.value)">
        <div class="user-list" id="userList">
            <?php if (empty($allStudents)): ?>
            <div style="padding:20px;text-align:center;color:var(--text3);">No other students found</div>
            <?php else: ?>
            <?php foreach (array_slice($allStudents,0,10) as $u): ?>
            <div class="user-item" onclick="startDirectMessage(<?php echo $u['id']; ?>)">
                <div class="user-ava">
                    <?php if ($u['profile_photo']): ?><img src="<?php echo htmlspecialchars($u['profile_photo']); ?>" alt="">
                    <?php else: ?><?php echo strtoupper(substr($u['full_name'],0,1)); ?><?php endif; ?>
                    <?php if ($u['is_online']): ?><div class="online-dot"></div><?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($u['domain_interest']?:$u['email']); ?></div>
                </div>
                <i class="fas fa-comment" style="color:var(--o5);"></i>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
            <button class="modal-btn create" style="width:100%;" onclick="openGroupChatModal()"><i class="fas fa-users"></i> Create Group Chat</button>
        </div>
    </div>
</div>

<!-- Group Chat Modal -->
<div class="modal-bg" id="groupChatModal" onclick="if(event.target===this)closeGroupChatModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <h3><i class="fas fa-users" style="color:var(--o5)"></i> Create Group Chat</h3>
        <label class="modal-label">Group Name</label>
        <input type="text" id="groupName" class="modal-input" placeholder="Enter group name...">
        <label class="modal-label">Add Members</label>
        <input type="text" class="modal-input" placeholder="Search members..." oninput="searchGroupMembers(this.value)" style="margin-bottom:8px;">
        <div class="user-list" id="groupMemberList" style="margin-bottom:0;">
            <?php foreach (array_slice($allStudents,0,10) as $u): ?>
            <div class="user-item" onclick="toggleGroupMember(this,<?php echo $u['id']; ?>)">
                <div class="user-ava">
                    <?php if ($u['profile_photo']): ?><img src="<?php echo htmlspecialchars($u['profile_photo']); ?>" alt="">
                    <?php else: ?><?php echo strtoupper(substr($u['full_name'],0,1)); ?><?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($u['domain_interest']?:$u['email']); ?></div>
                </div>
                <div class="user-check"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="modal-btns">
            <button class="modal-btn cancel" onclick="closeGroupChatModal()">Cancel</button>
            <button class="modal-btn create" onclick="createGroupChat()">Create Group</button>
        </div>
    </div>
</div>

<script>
const ROOM_ID = <?php echo $activeRoomId ?: 0; ?>;
const MY_ID   = <?php echo $sid; ?>;
let lastMsgId           = <?php echo $lastMsgId; ?>;
let selectedGroupMembers = [];
let replyToId           = null;
let selectedFile        = null;
let currentReactionMsgId = null;

// ─── MOBILE NAV ───────────────────────────────────────────────
function toggleRoomsPanel(){ 
    // Hamburger ALWAYS opens sidebar navigation on mobile
    document.getElementById('studentSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

function goBackToRooms(){ 
    if(window.innerWidth <= 768){
        // Back arrow: go back to chat list
        document.body.classList.remove('room-selected');
    }
}

function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

function handleRoomClick(e, id){
    if(window.innerWidth <= 768){
        document.body.classList.add('room-selected');
    }
}


// ─── SCROLL ───────────────────────────────────────────────────
function scrollToBottom(){ const c=document.getElementById('chatMessages'); if(c) c.scrollTop=c.scrollHeight; }
scrollToBottom();

// ─── FILE ATTACH ──────────────────────────────────────────────
function handleFileSelect(input){
    if(!input.files||!input.files[0]) return;
    selectedFile=input.files[0];
    const name=selectedFile.name;
    const size=(selectedFile.size/1024).toFixed(1)+' KB';

    if(selectedFile.type.startsWith('image/')){
        const reader=new FileReader();
        reader.onload=e=>{
            document.getElementById('imagePreviewThumb').src=e.target.result;
            document.getElementById('imagePreviewName').textContent=name;
            document.getElementById('imagePreviewSize').textContent=size;
            document.getElementById('imagePreviewBar').classList.add('active');
        };
        reader.readAsDataURL(selectedFile);
    } else {
        // Non-image: show file name in preview bar too
        document.getElementById('imagePreviewThumb').src='';
        document.getElementById('imagePreviewName').textContent='📎 '+name;
        document.getElementById('imagePreviewSize').textContent=size;
        document.getElementById('imagePreviewBar').classList.add('active');
    }
}

function removeAttachmentPreview(){
    selectedFile=null;
    document.getElementById('fileInput').value='';
    document.getElementById('imagePreviewBar').classList.remove('active');
    document.getElementById('imagePreviewThumb').src='';
}

// ─── SEND MESSAGE ─────────────────────────────────────────────
function sendMessage(){
    const input=document.getElementById('msgInput');
    const msg=input.value.trim();
    if((!msg&&!selectedFile)||!ROOM_ID) return;

    const btn=document.getElementById('sendBtn');
    btn.disabled=true;
    input.value='';
    input.style.height='auto';

    const fd=new FormData();
    fd.append('action','send');
    fd.append('room_id',ROOM_ID);
    fd.append('message',msg);
    if(replyToId) fd.append('reply_to_id',replyToId);
    if(selectedFile) fd.append('attachment',selectedFile);

    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>{
            // Try to parse JSON - if it fails, show the raw text for debugging
            return r.text().then(text=>{
                try { return JSON.parse(text); }
                catch(e){ throw new Error('Server returned invalid JSON:\n'+text.substring(0,300)); }
            });
        })
        .then(d=>{
            if(d.success){
                appendMessage({
                    id: d.msg_id,
                    message: msg,
                    full_name: <?php echo json_encode($student['full_name']); ?>,
                    profile_photo: <?php echo json_encode($student['profile_photo'] ?? null); ?>,
                    created_at: new Date().toISOString(),
                    sender_id: MY_ID,
                    attachment_path: d.attachment_path,
                    attachment_type: d.attachment_type,
                    attachment_name: d.attachment_name,
                    reply_to_id: replyToId,
                    reply_message: d.reply_msg?d.reply_msg.message:null,
                    reply_sender_name: d.reply_msg?d.reply_msg.full_name:null,
                    reply_attachment_type: d.reply_msg?d.reply_msg.attachment_type:null,
                    reactions:{}
                },true);
                lastMsgId=d.msg_id;
                cancelReply();
                removeAttachmentPreview();
            } else {
                alert('❌ '+( d.error||'Failed to send message'));
            }
        })
        .catch(err=>{
            // Show actual error so you can debug
            alert('Send error:\n'+err.message);
        })
        .finally(()=>{
            btn.disabled=false;
            input.focus();
        });
}

// ─── APPEND MESSAGE ───────────────────────────────────────────
function appendMessage(msg,isMe){
    const c=document.getElementById('chatMessages');
    const nm=c.querySelector('.no-msgs'); if(nm) nm.remove();

    const div=document.createElement('div');
    div.className='msg-group'+(isMe?' mine':'');
    div.setAttribute('data-msg-id',msg.id);

    const time=new Date(msg.created_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    const profileImg=msg.profile_photo?`<img src="${escHtml(msg.profile_photo)}" alt="">`:msg.full_name[0].toUpperCase();
    const isGroup=<?php echo ($activeRoom&&$activeRoom['room_type']==='group')?'true':'false'; ?>;

    let replyHtml='';
    if(msg.reply_to_id){
        let rc='';
        if(msg.reply_attachment_type==='image') rc='<i class="fas fa-image"></i> Photo';
        else if(msg.reply_attachment_type==='file') rc='<i class="fas fa-file"></i> File';
        else rc=escHtml((msg.reply_message||'').substring(0,40));
        replyHtml=`<div class="reply-preview"><div class="reply-preview-name">${escHtml(msg.reply_sender_name||'')}</div><div class="reply-preview-text">${rc}</div></div>`;
    }

    let attHtml='';
    if(msg.attachment_path){
        const ap=escHtml(msg.attachment_path);
        if(msg.attachment_type==='image'){
            attHtml=`<div class="msg-attachment"><img src="${ap}" alt="Image" onclick="openImageModal('${ap}')"></div>`;
        } else {
            const an=escHtml(msg.attachment_name||'File');
            attHtml=`<div class="msg-attachment"><a href="${ap}" download="${an}" class="msg-attachment file"><div class="file-icon"><i class="fas fa-file"></i></div><div class="file-info"><div class="file-name">${an}</div></div><i class="fas fa-download file-download"></i></a></div>`;
        }
    }

    const msgTxt=msg.message?(msg.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')):'';
    const senderFirst=escHtml(msg.full_name.split(' ')[0]);

    div.innerHTML=`
        <div class="msg-ava">${profileImg}</div>
        <div class="msg-bubble-wrap">
            ${(!isMe&&isGroup)?`<div class="msg-sender-name">${senderFirst}</div>`:''}
            <div class="msg-bubble ${isMe?'mine-b':'other'}">
                ${replyHtml}${msgTxt}${attHtml}
                <div class="msg-actions">
                    <button class="msg-action-btn" onclick="showReactionPicker(${msg.id},this)"><i class="fas fa-smile"></i></button>
                    <button class="msg-action-btn" onclick="replyToMessage(${msg.id},'${msg.full_name.replace(/'/g,"\\'")}','${((msg.message||'').replace(/'/g,"\\'")).substring(0,40)}')"><i class="fas fa-reply"></i></button>
                </div>
            </div>
            <div class="msg-time">${time}</div>
        </div>`;
    c.appendChild(div);
    scrollToBottom();
}

function escHtml(s){ return s?String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'):''; }

// ─── REPLY ────────────────────────────────────────────────────
function replyToMessage(id,name,text){
    replyToId=id;
    document.getElementById('replyBar').classList.add('active');
    document.getElementById('replyUserName').textContent=name;
    document.getElementById('replyText').textContent=text;
    document.getElementById('msgInput').focus();
}
function cancelReply(){
    replyToId=null;
    document.getElementById('replyBar').classList.remove('active');
}

// ─── REACTIONS ────────────────────────────────────────────────
function showReactionPicker(msgId, btn){
    const picker = document.getElementById('emojiPicker');
    if(picker.classList.contains('active') && currentReactionMsgId === msgId){ hidePicker(); return; }
    currentReactionMsgId = msgId;
    const rect = btn.getBoundingClientRect();
    const pickerW = 260, pickerH = 56;
    let top = rect.top - pickerH - 8;
    let left = rect.left;
    if(left + pickerW > window.innerWidth - 8) left = window.innerWidth - pickerW - 8;
    if(left < 8) left = 8;
    if(top < 8) top = rect.bottom + 6;
    picker.style.top = top + 'px';
    picker.style.left = left + 'px';
    picker.classList.add('active');
    if(window._pickerHandler) document.removeEventListener('click', window._pickerHandler);
    setTimeout(() => {
        window._pickerHandler = function(e){ if(!picker.contains(e.target)) hidePicker(); };
        document.addEventListener('click', window._pickerHandler);
    }, 150);
}
function hidePicker(){
    document.getElementById('emojiPicker').classList.remove('active');
    if(window._pickerHandler){ document.removeEventListener('click', window._pickerHandler); window._pickerHandler = null; }
}
function reactWithEmoji(emoji){
    if(!currentReactionMsgId) return;
    const msgId = currentReactionMsgId;
    hidePicker();
    toggleReaction(msgId, emoji);
}
function toggleReaction(msgId, emoji){
    const fd = new FormData();
    fd.append('action','react'); fd.append('msg_id', msgId); fd.append('emoji', emoji);
    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(d=>{ if(d.success) fetchAndUpdateAllReactions(); })
        .catch(()=>{});
}
function fetchAndUpdateAllReactions(){
    const fd = new FormData();
    fd.append('action','fetch'); fd.append('room_id', ROOM_ID); fd.append('since_id', 0);
    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(data=>{
            if(!data.messages) return;
            data.messages.forEach(m=>{
                const el = document.querySelector('[data-msg-id="'+m.id+'"]');
                if(el) updateReactions(el, m.reactions || {});
            });
        }).catch(()=>{});
}

// ─── IMAGE MODAL ──────────────────────────────────────────────
function openImageModal(src){ document.getElementById('imageModalImg').src=src; document.getElementById('imageModal').classList.add('open'); }
function closeImageModal(){ document.getElementById('imageModal').classList.remove('open'); }

// ─── POLLING ──────────────────────────────────────────────────
function pollMessages(){
    if(!ROOM_ID) return;
    const fd=new FormData();
    fd.append('action','fetch'); fd.append('room_id',ROOM_ID); fd.append('since_id',lastMsgId);
    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(data=>{
            if(data.messages) data.messages.forEach(m=>{
                if(m.sender_id!=MY_ID){ appendMessage(m,false); lastMsgId=m.id; }
                else {
                    const el=document.querySelector(`[data-msg-id="${m.id}"]`);
                    if(el&&Object.keys(m.reactions).length) updateReactions(el,m.reactions);
                }
            });
        }).catch(()=>{});
}

function updateReactions(el,reactions){
    let rd=el.querySelector('.msg-reactions');
    if(!rd){ rd=document.createElement('div'); rd.className='msg-reactions'; el.querySelector('.msg-bubble-wrap').insertBefore(rd,el.querySelector('.msg-time')); }
    rd.innerHTML='';
    Object.keys(reactions).forEach(emoji=>{
        const d=reactions[emoji];
        const it=document.createElement('div');
        it.className='reaction-item'+(d.has_reacted?' has-reacted':'');
        it.onclick=()=>toggleReaction(el.getAttribute('data-msg-id'),emoji);
        it.title=d.users.join(', ');
        it.innerHTML=`<span>${emoji}</span><span class="reaction-count">${d.count}</span>`;
        rd.appendChild(it);
    });
}

if(ROOM_ID) setInterval(pollMessages,8000);

// ─── TEXTAREA AUTO-RESIZE ─────────────────────────────────────
const msgInput=document.getElementById('msgInput');
if(msgInput) msgInput.addEventListener('input',function(){ this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,120)+'px'; });

// ─── MODALS ───────────────────────────────────────────────────
function openNewChatModal(){ document.getElementById('newChatModal').classList.add('open'); }
function closeNewChatModal(){ document.getElementById('newChatModal').classList.remove('open'); }

function startDirectMessage(targetId){
    const fd=new FormData(); fd.append('action','start_dm'); fd.append('target_id',targetId);
    fetch('messenger.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) location.href='messenger.php?room='+d.room_id; else alert('Failed'); }).catch(()=>alert('Error'));
}

function searchUsers(q){
    if(!q.trim()||q.length<2) return;
    const fd=new FormData(); fd.append('action','search_users'); fd.append('query',q);
    fetch('messenger.php',{method:'POST',body:fd}).then(r=>r.json()).then(users=>{
        const list=document.getElementById('userList');
        if(!users||!users.length){ list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text3);">No users found</div>'; return; }
        list.innerHTML=users.map(u=>`<div class="user-item" onclick="startDirectMessage(${u.id})">
            <div class="user-ava">${u.profile_photo?`<img src="${escHtml(u.profile_photo)}" alt="">`:u.full_name[0].toUpperCase()}${u.is_online?'<div class="online-dot"></div>':''}</div>
            <div class="user-info"><div class="user-name">${escHtml(u.full_name)}</div><div class="user-email">${escHtml(u.domain_interest||u.email)}</div></div>
            <i class="fas fa-comment" style="color:var(--o5);"></i></div>`).join('');
    }).catch(()=>{});
}

function openGroupChatModal(){ closeNewChatModal(); document.getElementById('groupChatModal').classList.add('open'); selectedGroupMembers=[]; }
function closeGroupChatModal(){ document.getElementById('groupChatModal').classList.remove('open'); }

function toggleGroupMember(el,id){
    el.classList.toggle('selected');
    const chk=el.querySelector('.user-check');
    if(el.classList.contains('selected')){ selectedGroupMembers.push(id); chk.innerHTML='<i class="fas fa-check fa-xs"></i>'; }
    else { selectedGroupMembers=selectedGroupMembers.filter(x=>x!==id); chk.innerHTML=''; }
}

function createGroupChat(){
    const name=document.getElementById('groupName').value.trim();
    if(!name){ alert('Please enter a group name'); return; }
    if(!selectedGroupMembers.length){ alert('Please select at least one member'); return; }
    const fd=new FormData(); fd.append('action','create_group'); fd.append('room_name',name); fd.append('member_ids',JSON.stringify(selectedGroupMembers));
    fetch('messenger.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) location.href='messenger.php?room='+d.room_id; else alert('Failed to create group'); }).catch(()=>alert('Error'));
}

function searchGroupMembers(q){
    if(!q.trim()||q.length<2) return;
    const fd=new FormData(); fd.append('action','search_users'); fd.append('query',q);
    fetch('messenger.php',{method:'POST',body:fd}).then(r=>r.json()).then(users=>{
        const list=document.getElementById('groupMemberList');
        if(!users||!users.length){ list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text3);">No users found</div>'; return; }
        list.innerHTML=users.map(u=>`<div class="user-item" onclick="toggleGroupMember(this,${u.id})">
            <div class="user-ava">${u.profile_photo?`<img src="${escHtml(u.profile_photo)}" alt="">`:u.full_name[0].toUpperCase()}</div>
            <div class="user-info"><div class="user-name">${escHtml(u.full_name)}</div><div class="user-email">${escHtml(u.domain_interest||u.email)}</div></div>
            <div class="user-check"></div></div>`).join('');
    }).catch(()=>{});
}
</script>
</body>
</html>