<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'messenger';

// Update online status
$db->query("UPDATE internship_students SET is_online=1, last_seen=NOW() WHERE id=$sid");

// === AJAX HANDLERS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Send message with attachments
    if ($_POST['action'] === 'send') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        $replyToId = (int)($_POST['reply_to'] ?? 0);
        
        if ($roomId && ($msg || !empty($_FILES['attachment']))) {
            // Verify membership
            $chk = $db->prepare("SELECT id FROM chat_room_members WHERE room_id=? AND student_id=?");
            $chk->bind_param("ii",$roomId,$sid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $attachmentPath = null;
                $attachmentType = null;
                $attachmentName = null;
                
                // Handle file upload
                if (!empty($_FILES['attachment']['tmp_name'])) {
                    $file = $_FILES['attachment'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt','zip','rar'];
                    
                    if (in_array($ext, $allowedExts) && $file['size'] <= 10485760) { // 10MB limit
                        $uploadDir = 'uploads/messenger/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        
                        $fileName = uniqid() . '_' . time() . '.' . $ext;
                        $filePath = $uploadDir . $fileName;
                        
                        // Optimize images
                        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                            $img = null;
                            if ($ext === 'jpg' || $ext === 'jpeg') {
                                $img = @imagecreatefromjpeg($file['tmp_name']);
                            } elseif ($ext === 'png') {
                                $img = @imagecreatefrompng($file['tmp_name']);
                            } elseif ($ext === 'gif') {
                                $img = @imagecreatefromgif($file['tmp_name']);
                            } elseif ($ext === 'webp') {
                                $img = @imagecreatefromwebp($file['tmp_name']);
                            }
                            
                            if ($img) {
                                $width = imagesx($img);
                                $height = imagesy($img);
                                $maxWidth = 1200;
                                
                                if ($width > $maxWidth) {
                                    $ratio = $maxWidth / $width;
                                    $newWidth = $maxWidth;
                                    $newHeight = (int)($height * $ratio);
                                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                                    
                                    if ($ext === 'png' || $ext === 'gif') {
                                        imagealphablending($resized, false);
                                        imagesavealpha($resized, true);
                                    }
                                    
                                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                    imagejpeg($resized, $filePath, 85);
                                    imagedestroy($resized);
                                } else {
                                    imagejpeg($img, $filePath, 85);
                                }
                                imagedestroy($img);
                                $attachmentPath = $filePath;
                                $attachmentType = 'image';
                                $attachmentName = $file['name'];
                            }
                        } else {
                            // Non-image files
                            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                                $attachmentPath = $filePath;
                                $attachmentType = 'file';
                                $attachmentName = $file['name'];
                            }
                        }
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO chat_messages (room_id, sender_id, message, reply_to_id, attachment_path, attachment_type, attachment_name) VALUES (?,?,?,?,?,?,?)");
                $replyToIdParam = $replyToId > 0 ? $replyToId : null;
                $stmt->bind_param("iisisss",$roomId,$sid,$msg,$replyToIdParam,$attachmentPath,$attachmentType,$attachmentName);
                
                if ($stmt->execute()) {
                    $msgId = $db->insert_id;
                    echo json_encode(['success'=>true,'msg_id'=>$msgId,'time'=>date('h:i A')]);
                    exit;
                }
            }
        }
        echo json_encode(['success'=>false,'error'=>'Invalid request']); exit;
    }
    
    // Add reaction
    if ($_POST['action'] === 'react') {
        $msgId = (int)($_POST['msg_id'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');
        
        if ($msgId && $emoji) {
            // Check if already reacted
            $check = $db->prepare("SELECT id FROM message_reactions WHERE message_id=? AND student_id=? AND emoji=?");
            $check->bind_param("iis",$msgId,$sid,$emoji);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            
            if ($exists) {
                // Remove reaction
                $del = $db->prepare("DELETE FROM message_reactions WHERE id=?");
                $del->bind_param("i",$exists['id']);
                $del->execute();
                echo json_encode(['success'=>true,'action'=>'removed']); exit;
            } else {
                // Add reaction
                $add = $db->prepare("INSERT INTO message_reactions (message_id, student_id, emoji) VALUES (?,?,?)");
                $add->bind_param("iis",$msgId,$sid,$emoji);
                if ($add->execute()) {
                    echo json_encode(['success'=>true,'action'=>'added']); exit;
                }
            }
        }
        echo json_encode(['success'=>false]); exit;
    }
    
    // Fetch new messages with reactions
    if ($_POST['action'] === 'fetch') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $since = (int)($_POST['since_id'] ?? 0);
        $msgs = [];
        
        $stmt = $db->prepare("SELECT cm.*, s.full_name, s.profile_photo,
            reply.message as reply_message, reply.sender_id as reply_sender_id, 
            reply_s.full_name as reply_sender_name
            FROM chat_messages cm 
            JOIN internship_students s ON s.id=cm.sender_id
            LEFT JOIN chat_messages reply ON reply.id=cm.reply_to_id
            LEFT JOIN internship_students reply_s ON reply_s.id=reply.sender_id
            WHERE cm.room_id=? AND cm.id>? AND cm.is_deleted=0 
            ORDER BY cm.id ASC LIMIT 50");
        $stmt->bind_param("ii",$roomId,$since);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($r = $res->fetch_assoc()) {
            // Get reactions for this message
            $reactionRes = $db->query("SELECT emoji, COUNT(*) as count,
                MAX(CASE WHEN student_id=$sid THEN 1 ELSE 0 END) as my_reaction
                FROM message_reactions 
                WHERE message_id={$r['id']} 
                GROUP BY emoji");
            $r['reactions'] = [];
            if ($reactionRes) while ($rr = $reactionRes->fetch_assoc()) $r['reactions'][] = $rr;
            
            $msgs[] = $r;
        }
        
        // Update last read
        $upd = $db->prepare("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=? AND student_id=?");
        $upd->bind_param("ii",$roomId,$sid);
        $upd->execute();
        
        echo json_encode(['messages'=>$msgs]); exit;
    }
    
    // Start direct message
    if ($_POST['action'] === 'start_dm') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId && $targetId != $sid) {
            $pair = $db->query("SELECT room_id FROM direct_message_pairs 
                WHERE (student1_id=$sid AND student2_id=$targetId) 
                OR (student1_id=$targetId AND student2_id=$sid)")->fetch_assoc();
            
            if ($pair) {
                echo json_encode(['success'=>true,'room_id'=>$pair['room_id']]); exit;
            }
            
            $targetUser = $db->query("SELECT full_name FROM internship_students WHERE id=$targetId")->fetch_assoc();
            $roomName = $targetUser['full_name'];
            
            $stmt = $db->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?,?,?)");
            $type = 'direct';
            $stmt->bind_param("ssi",$roomName,$type,$sid);
            if ($stmt->execute()) {
                $roomId = $db->insert_id;
                
                $addStmt = $db->prepare("INSERT INTO chat_room_members (room_id, student_id) VALUES (?,?)");
                $addStmt->bind_param("ii",$roomId,$sid);
                $addStmt->execute();
                $addStmt->bind_param("ii",$roomId,$targetId);
                $addStmt->execute();
                
                $pairStmt = $db->prepare("INSERT INTO direct_message_pairs (room_id, student1_id, student2_id) VALUES (?,?,?)");
                $pairStmt->bind_param("iii",$roomId,$sid,$targetId);
                $pairStmt->execute();
                
                echo json_encode(['success'=>true,'room_id'=>$roomId]); exit;
            }
        }
        echo json_encode(['success'=>false]); exit;
    }
    
    // Create group chat
    if ($_POST['action'] === 'create_group') {
        $name = trim($_POST['room_name'] ?? '');
        $memberIds = json_decode($_POST['member_ids'] ?? '[]', true);
        
        if ($name && is_array($memberIds) && count($memberIds) > 0) {
            $stmt = $db->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?,?,?)");
            $type = 'group';
            $stmt->bind_param("ssi",$name,$type,$sid);
            if ($stmt->execute()) {
                $roomId = $db->insert_id;
                
                $addStmt = $db->prepare("INSERT IGNORE INTO chat_room_members (room_id, student_id) VALUES (?,?)");
                $addStmt->bind_param("ii",$roomId,$sid);
                $addStmt->execute();
                
                foreach ($memberIds as $mid) {
                    $mid = (int)$mid;
                    if ($mid > 0 && $mid != $sid) {
                        $addStmt->bind_param("ii",$roomId,$mid);
                        $addStmt->execute();
                    }
                }
                
                echo json_encode(['success'=>true,'room_id'=>$roomId]); exit;
            }
        }
        echo json_encode(['success'=>false]); exit;
    }
    
    // Search users
    if ($_POST['action'] === 'search_users') {
        $query = trim($_POST['query'] ?? '');
        $users = [];
        if (strlen($query) >= 2) {
            $q = '%'.$query.'%';
            $stmt = $db->prepare("SELECT id, full_name, email, profile_photo, domain_interest, is_online 
                FROM internship_students 
                WHERE id!=$sid AND is_active=1 
                AND (full_name LIKE ? OR email LIKE ?) 
                LIMIT 20");
            $stmt->bind_param("ss",$q,$q);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $users[] = $r;
        }
        echo json_encode($users); exit;
    }
    
    echo json_encode(['success'=>false]); exit;
}

// === GET CONVERSATIONS ===
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
    if ($r['room_type'] === 'direct' && $r['dm_partner_name']) {
        $r['room_name'] = $r['dm_partner_name'];
    }
    $rooms[] = $r;
}

$activeRoomId = (int)($_GET['room'] ?? ($rooms[0]['id'] ?? 0));
$activeRoom = null;
foreach ($rooms as $r) { if ($r['id'] == $activeRoomId) { $activeRoom = $r; break; } }

// Load messages
$messages = [];
$lastMsgId = 0;
if ($activeRoomId) {
    $res = $db->query("SELECT cm.*, s.full_name, s.profile_photo,
        reply.message as reply_message, reply.sender_id as reply_sender_id,
        reply_s.full_name as reply_sender_name
        FROM chat_messages cm 
        JOIN internship_students s ON s.id=cm.sender_id 
        LEFT JOIN chat_messages reply ON reply.id=cm.reply_to_id
        LEFT JOIN internship_students reply_s ON reply_s.id=reply.sender_id
        WHERE cm.room_id=$activeRoomId AND cm.is_deleted=0 
        ORDER BY cm.created_at ASC LIMIT 100");
    if ($res) while ($r = $res->fetch_assoc()) {
        // Get reactions
        $rRes = $db->query("SELECT emoji, COUNT(*) as count,
            MAX(CASE WHEN student_id=$sid THEN 1 ELSE 0 END) as my_reaction
            FROM message_reactions 
            WHERE message_id={$r['id']} 
            GROUP BY emoji");
        $r['reactions'] = [];
        if ($rRes) while ($rr = $rRes->fetch_assoc()) $r['reactions'][] = $rr;
        
        $messages[] = $r;
        $lastMsgId = max($lastMsgId, $r['id']);
    }
    
    $db->query("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=$activeRoomId AND student_id=$sid");
}

// Room members
$roomMembers = [];
if ($activeRoomId) {
    $rm = $db->query("SELECT s.id, s.full_name, s.domain_interest, s.profile_photo, s.is_online 
        FROM internship_students s 
        JOIN chat_room_members crm ON crm.student_id=s.id 
        WHERE crm.room_id=$activeRoomId ORDER BY s.full_name");
    if ($rm) while ($r = $rm->fetch_assoc()) $roomMembers[] = $r;
}

// All students
$allStudents = [];
$allRes = $db->query("SELECT id, full_name, email, domain_interest, profile_photo, is_online 
    FROM internship_students WHERE id!=$sid AND is_active=1 ORDER BY full_name LIMIT 100");
if ($allRes) while ($r = $allRes->fetch_assoc()) $allStudents[] = $r;
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
.page-wrap{margin-left:var(--sbw);height:100vh;display:flex;flex-direction:column;}
.topbar{flex-shrink:0;background:rgba(248,250,252,0.95);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;gap:10px;}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;font-size:1.2rem;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.btn-new-chat{background:var(--o5);border:none;color:#fff;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:6px;}
.chat-layout{flex:1;display:grid;grid-template-columns:320px 1fr;min-height:0;}
.rooms-panel{border-right:1px solid var(--border);display:flex;flex-direction:column;background:var(--card);overflow:hidden;}
.search-box{padding:12px;border-bottom:1px solid var(--border);}
.search-input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:.88rem;outline:none;font-family:inherit;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23475569' viewBox='0 0 24 24'%3E%3Cpath d='M15.5 14h-.79l-.28-.27a6.5 6.5 0 001.48-5.34c-.47-2.78-2.79-5-5.59-5.34a6.505 6.505 0 00-7.27 7.27c.34 2.8 2.56 5.12 5.34 5.59a6.5 6.5 0 005.34-1.48l.27.28v.79l4.25 4.25c.41.41 1.08.41 1.49 0 .41-.41.41-1.08 0-1.49L15.5 14zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E") no-repeat 12px center/18px;}
.rooms-list{flex:1;overflow-y:auto;padding:4px;}
.room-item{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:10px;cursor:pointer;transition:background .15s;text-decoration:none;position:relative;}
.room-item:hover{background:#f1f5f9;}
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
.chat-area{display:flex;flex-direction:column;background:var(--bg);}
.chat-head{flex-shrink:0;padding:12px 20px;border-bottom:1px solid var(--border);background:var(--card);display:flex;align-items:center;gap:12px;}
.chat-head-back{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;font-size:1.1rem;}
.chat-head-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem;position:relative;}
.chat-head-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.chat-room-name{font-size:.98rem;font-weight:700;color:var(--text);}
.chat-room-meta{font-size:.74rem;color:var(--text3);}
.chat-messages{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:12px;}
.msg-group{display:flex;gap:9px;align-items:flex-start;position:relative;}
.msg-group:hover .msg-actions{opacity:1;}
.msg-group.mine{flex-direction:row-reverse;}
.msg-ava{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#64748b,#475569);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;}
.msg-ava img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.msg-group.mine .msg-ava{background:linear-gradient(135deg,var(--o5),var(--o4));}
.msg-bubble-wrap{max-width:65%;display:flex;flex-direction:column;gap:2px;}
.msg-group.mine .msg-bubble-wrap{align-items:flex-end;}
.msg-sender-name{font-size:.72rem;color:var(--text3);padding:0 4px;}
.msg-reply-preview{background:rgba(249,115,22,0.08);border-left:3px solid var(--o5);padding:6px 10px;border-radius:6px;margin-bottom:4px;font-size:.78rem;}
.msg-reply-preview .reply-sender{font-weight:600;color:var(--o5);margin-bottom:2px;}
.msg-reply-preview .reply-text{color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.msg-bubble{padding:10px 14px;border-radius:14px;font-size:.88rem;line-height:1.5;word-break:break-word;}
.msg-bubble.other{background:var(--card);border:1px solid var(--border);border-bottom-left-radius:4px;color:var(--text);box-shadow:0 1px 2px rgba(0,0,0,0.05);}
.msg-bubble.mine-b{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;border-bottom-right-radius:4px;box-shadow:0 2px 8px rgba(249,115,22,0.25);}
.msg-attachment{margin-top:6px;}
.msg-attachment img{max-width:100%;max-height:300px;border-radius:10px;cursor:pointer;}
.msg-attachment.file{background:rgba(0,0,0,0.05);padding:10px;border-radius:8px;display:flex;align-items:center;gap:10px;}
.msg-attachment.file i{font-size:1.5rem;color:var(--o5);}
.msg-attachment.file .file-info{flex:1;}
.msg-attachment.file .file-name{font-size:.82rem;font-weight:600;}
.msg-reactions{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;}
.reaction-item{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;background:var(--bg);border:1px solid var(--border);border-radius:12px;font-size:.8rem;cursor:pointer;}
.reaction-item:hover{background:#e2e8f0;}
.reaction-item.my-reaction{background:rgba(249,115,22,0.1);border-color:var(--o5);}
.reaction-item .emoji{font-size:1rem;}
.reaction-item .count{font-size:.75rem;font-weight:600;color:var(--text2);}
.msg-actions{position:absolute;right:0;top:0;display:flex;gap:2px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:3px;opacity:0;transition:opacity .2s;box-shadow:0 2px 8px rgba(0,0,0,0.1);z-index:10;}
.msg-group.mine .msg-actions{right:auto;left:0;}
.msg-action-btn{background:none;border:none;padding:6px 8px;cursor:pointer;font-size:.85rem;border-radius:6px;color:var(--text2);}
.msg-action-btn:hover{background:var(--bg);}
.msg-time{font-size:.68rem;color:var(--text3);padding:0 4px;}
.no-msgs{text-align:center;padding:60px 20px;color:var(--text3);}
.no-msgs i{font-size:3rem;margin-bottom:12px;display:block;opacity:.2;}
.chat-input-area{flex-shrink:0;padding:14px 20px;border-top:1px solid var(--border);background:var(--card);}
.reply-preview-box{display:none;padding:8px 12px;background:rgba(249,115,22,0.08);border-left:3px solid var(--o5);border-radius:6px;margin-bottom:8px;position:relative;}
.reply-preview-box.active{display:block;}
.reply-preview-title{font-size:.72rem;font-weight:600;color:var(--o5);margin-bottom:3px;}
.reply-preview-text{font-size:.78rem;color:var(--text2);}
.reply-preview-close{position:absolute;top:8px;right:8px;background:none;border:none;cursor:pointer;color:var(--text3);}
.chat-form{display:flex;gap:10px;align-items:flex-end;}
.chat-input-wrapper{flex:1;position:relative;}
.chat-input{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;outline:none;resize:none;max-height:120px;}
.chat-input:focus{border-color:var(--o5);}
.attachment-preview{display:none;padding:8px;background:var(--bg);border:1px solid var(--border);border-radius:8px;margin-bottom:8px;position:relative;}
.attachment-preview.active{display:block;}
.attachment-preview img{max-height:100px;border-radius:6px;}
.attachment-preview-close{position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.6);color:#fff;border:none;width:24px;height:24px;border-radius:50%;cursor:pointer;}
.input-actions{display:flex;gap:6px;}
.attach-btn{background:none;border:none;color:var(--text2);cursor:pointer;padding:8px;border-radius:8px;}
.attach-btn:hover{background:var(--bg);}
.attach-btn input{display:none;}
.send-btn{width:42px;height:42px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(249,115,22,0.3);}
.send-btn:hover{opacity:.9;}
.send-btn:disabled{opacity:.5;cursor:not-allowed;}
.emoji-picker{display:none;position:absolute;bottom:100%;left:0;margin-bottom:8px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px;box-shadow:0 4px 16px rgba(0,0,0,0.15);z-index:100;}
.emoji-picker.active{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;max-width:280px;}
.emoji-btn{background:none;border:none;font-size:1.5rem;padding:6px;cursor:pointer;border-radius:6px;}
.emoji-btn:hover{background:var(--bg);}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal{background:var(--card);border-radius:14px;padding:24px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto;}
.modal h3{font-size:1.1rem;font-weight:700;margin-bottom:18px;}
.modal-input{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;outline:none;margin-bottom:14px;}
.user-list{display:flex;flex-direction:column;gap:4px;max-height:350px;overflow-y:auto;margin-bottom:14px;}
.user-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;cursor:pointer;border:1.5px solid transparent;}
.user-item:hover{background:#f1f5f9;}
.user-item.selected{background:rgba(249,115,22,0.08);border-color:var(--o5);}
.user-ava{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;position:relative;}
.user-ava img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.user-info{flex:1;}
.user-name{font-size:.86rem;font-weight:600;}
.user-email{font-size:.72rem;color:var(--text3);}
.user-check{width:20px;height:20px;border:2px solid var(--border);border-radius:4px;}
.user-item.selected .user-check{background:var(--o5);border-color:var(--o5);display:flex;align-items:center;justify-content:center;color:#fff;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;}
.modal-btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:.86rem;font-weight:600;}
.modal-btn.cancel{background:var(--bg);border:1px solid var(--border);color:var(--text2);}
.modal-btn.create{background:var(--o5);color:#fff;}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text3);}
.empty-state i{font-size:3.5rem;opacity:.15;margin-bottom:16px;}
.image-viewer{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;}
.image-viewer.active{display:flex;}
.image-viewer img{max-width:90%;max-height:90%;border-radius:8px;}
.image-viewer-close{position:absolute;top:20px;right:20px;background:rgba(255,255,255,0.2);border:none;color:#fff;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:1.2rem;}
@media(max-width:768px){
.page-wrap{margin-left:0;}
.topbar-hamburger{display:flex;}
.chat-layout{grid-template-columns:1fr;position:relative;}
.rooms-panel{position:absolute;inset:0;z-index:100;transform:translateX(-100%);transition:transform .3s ease;}
.rooms-panel.mobile-visible{transform:translateX(0);}
.chat-area{position:absolute;inset:0;z-index:50;}
.chat-head-back{display:flex;}
body:not(.room-selected) .chat-area{display:none;}
body:not(.room-selected) .rooms-panel{position:relative;transform:translateX(0);}
.msg-bubble-wrap{max-width:80%;}
.msg-actions{position:static;opacity:1;margin-top:4px;}
}
</style>
</head>
<body<?php echo $activeRoomId ? ' class="room-selected"' : ''; ?>>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
<div class="topbar">
<button class="topbar-hamburger" onclick="toggleRoomsPanel()"><i class="fas fa-bars"></i></button>
<div class="topbar-title">Messenger</div>
<button class="btn-new-chat" onclick="openNewChatModal()"><i class="fas fa-plus fa-sm"></i> New Chat</button>
</div>
<div class="chat-layout">
<div class="rooms-panel" id="roomsPanel">
<div class="search-box">
<input type="text" class="search-input" placeholder="Search conversations...">
</div>
<div class="rooms-list">
<?php if(empty($rooms)): ?>
<div style="padding:40px 20px;text-align:center;color:var(--text3);">
<i class="fas fa-comments" style="font-size:2rem;opacity:.2;display:block;margin-bottom:10px;"></i>
<p style="font-size:.85rem;">No conversations yet.<br>Start a new chat!</p>
</div>
<?php else: foreach($rooms as $r):
$isOnline = ($r['room_type']==='direct' && $r['dm_partner_online']);
$lastTime = $r['last_msg_time'] ? date('h:i A',strtotime($r['last_msg_time'])) : '';
?>
<a href="?room=<?=$r['id']?>" class="room-item <?=$r['id']==$activeRoomId?'active':''?>">
<div class="room-avatar-wrap">
<div class="room-avatar <?=$r['room_type']?>">
<?php if($r['room_type']==='group'): ?>
<i class="fas fa-users"></i>
<?php else: ?>
<?php if(!empty($r['dm_partner_photo'])): ?>
<img src="<?=htmlspecialchars($r['dm_partner_photo'])?>" alt="">
<?php else: ?>
<?=strtoupper(substr($r['room_name'],0,1))?>
<?php endif; ?>
<?php endif; ?>
</div>
<?php if($isOnline): ?><div class="online-dot"></div><?php endif; ?>
</div>
<div class="room-info">
<div class="room-top">
<div class="room-name"><?=htmlspecialchars($r['room_name'])?></div>
<div class="room-time"><?=$lastTime?></div>
</div>
<div class="room-last"><?=$r['last_msg']?htmlspecialchars(substr($r['last_msg'],0,40)):'Start a conversation'?></div>
</div>
<?php if($r['unread']>0): ?><span class="room-badge"><?=$r['unread']?></span><?php endif; ?>
</a>
<?php endforeach; endif; ?>
</div>
</div>
<div class="chat-area">
<?php if($activeRoom): ?>
<div class="chat-head">
<button class="chat-head-back" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
<div class="chat-head-avatar">
<?php if($activeRoom['room_type']==='group'): ?>
<i class="fas fa-users"></i>
<?php else: ?>
<?php if(!empty($activeRoom['dm_partner_photo'])): ?>
<img src="<?=htmlspecialchars($activeRoom['dm_partner_photo'])?>" alt="">
<?php else: ?>
<?=strtoupper(substr($activeRoom['room_name'],0,1))?>
<?php endif; ?>
<?php endif; ?>
<?php if($activeRoom['room_type']==='direct' && $activeRoom['dm_partner_online']): ?>
<div class="online-dot"></div>
<?php endif; ?>
</div>
<div style="flex:1;">
<div class="chat-room-name"><?=htmlspecialchars($activeRoom['room_name'])?></div>
<div class="chat-room-meta">
<?php if($activeRoom['room_type']==='direct'): ?>
<?=$activeRoom['dm_partner_online']?'Online':'Offline'?>
<?php else: ?>
<?=count($roomMembers)?> members
<?php endif; ?>
</div>
</div>
</div>
<div class="chat-messages" id="chatMessages">
<?php if(empty($messages)): ?>
<div class="no-msgs"><i class="fas fa-comments"></i><p>No messages yet. Start the conversation!</p></div>
<?php else: foreach($messages as $msg):
$isMe = $msg['sender_id']==$sid;
?>
<div class="msg-group <?=$isMe?'mine':''?>" data-msg-id="<?=$msg['id']?>">
<div class="msg-ava">
<?php if($msg['profile_photo']): ?>
<img src="<?=htmlspecialchars($msg['profile_photo'])?>" alt="">
<?php else: ?>
<?=strtoupper(substr($msg['full_name'],0,1))?>
<?php endif; ?>
</div>
<div class="msg-bubble-wrap">
<?php if(!$isMe && $activeRoom['room_type']==='group'): ?>
<div class="msg-sender-name"><?=htmlspecialchars(explode(' ',$msg['full_name'])[0])?></div>
<?php endif; ?>
<?php if($msg['reply_to_id']): ?>
<div class="msg-reply-preview">
<div class="reply-sender"><?=htmlspecialchars($msg['reply_sender_name']?:'Unknown')?></div>
<div class="reply-text"><?=htmlspecialchars(substr($msg['reply_message'],0,50))?></div>
</div>
<?php endif; ?>
<div class="msg-bubble <?=$isMe?'mine-b':'other'?>">
<?=nl2br(htmlspecialchars($msg['message']))?>
<?php if($msg['attachment_path']): ?>
<?php if($msg['attachment_type']==='image'): ?>
<div class="msg-attachment">
<img src="<?=htmlspecialchars($msg['attachment_path'])?>" alt="" onclick="viewImage(this.src)">
</div>
<?php else: ?>
<div class="msg-attachment file">
<i class="fas fa-file"></i>
<div class="file-info">
<div class="file-name"><?=htmlspecialchars($msg['attachment_name'])?></div>
<a href="<?=htmlspecialchars($msg['attachment_path'])?>" download style="color:var(--o5);font-size:.7rem;"><i class="fas fa-download"></i> Download</a>
</div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<?php if(!empty($msg['reactions'])): ?>
<div class="msg-reactions">
<?php foreach($msg['reactions'] as $r): ?>
<div class="reaction-item <?=$r['my_reaction']?'my-reaction':''?>" onclick="toggleReaction(<?=$msg['id']?>,'<?=htmlspecialchars($r['emoji'])?>')">
<span class="emoji"><?=$r['emoji']?></span>
<span class="count"><?=$r['count']?></span>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="msg-time"><?=date('h:i A',strtotime($msg['created_at']))?></div>
</div>
<div class="msg-actions">
<button class="msg-action-btn" onclick="showEmojiPicker(<?=$msg['id']?>,this)"><i class="fas fa-smile"></i></button>
<button class="msg-action-btn" onclick="setReplyTo(<?=$msg['id']?>,'<?=htmlspecialchars($msg['full_name'])?>','<?=htmlspecialchars(addslashes($msg['message']))?>')"><i class="fas fa-reply"></i></button>
</div>
</div>
<?php endforeach; endif; ?>
</div>
<div class="chat-input-area">
<div class="reply-preview-box" id="replyPreview">
<button class="reply-preview-close" onclick="clearReply()"><i class="fas fa-times"></i></button>
<div class="reply-preview-title">Replying to <span id="replyToName"></span></div>
<div class="reply-preview-text" id="replyToText"></div>
</div>
<div class="chat-form">
<div class="chat-input-wrapper">
<div class="attachment-preview" id="attachmentPreview">
<button class="attachment-preview-close" onclick="clearAttachment()"><i class="fas fa-times"></i></button>
<div id="attachmentContent"></div>
</div>
<textarea class="chat-input" id="msgInput" placeholder="Type a message…" rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
</div>
<div class="input-actions">
<button class="attach-btn">
<i class="fas fa-paperclip"></i>
<input type="file" id="fileInput" onchange="handleFileSelect(event)" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">
</button>
<button class="send-btn" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane fa-sm"></i></button>
</div>
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
<div class="emoji-picker" id="emojiPicker">
<?php foreach(['👍','❤️','😂','😮','😢','🙏','👏','🔥','🎉','✨','💯','⭐','✅','❌'] as $emoji): ?>
<button class="emoji-btn" onclick="selectEmoji('<?=$emoji?>')"><?=$emoji?></button>
<?php endforeach; ?>
</div>
<div class="image-viewer" id="imageViewer" onclick="closeImageViewer()">
<button class="image-viewer-close"><i class="fas fa-times"></i></button>
<img id="viewerImage" src="" alt="">
</div>
<div class="modal-bg" id="newChatModal" onclick="if(event.target===this)closeNewChatModal()">
<div class="modal">
<h3><i class="fas fa-comment-dots" style="color:var(--o5)"></i> Start New Chat</h3>
<input type="text" id="userSearch" class="modal-input" placeholder="Search by name or email..." oninput="searchUsers(this.value)">
<div class="user-list" id="userList">
<?php if(empty($allStudents)): ?>
<div style="padding:20px;text-align:center;color:var(--text3);">No other students found</div>
<?php else: foreach(array_slice($allStudents,0,10) as $u): ?>
<div class="user-item" onclick="startDirectMessage(<?=$u['id']?>)">
<div class="user-ava">
<?php if($u['profile_photo']): ?><img src="<?=htmlspecialchars($u['profile_photo'])?>" alt=""><?php else: ?><?=strtoupper(substr($u['full_name'],0,1))?><?php endif; ?>
<?php if($u['is_online']): ?><div class="online-dot"></div><?php endif; ?>
</div>
<div class="user-info">
<div class="user-name"><?=htmlspecialchars($u['full_name'])?></div>
<div class="user-email"><?=htmlspecialchars($u['domain_interest']?:$u['email'])?></div>
</div>
<i class="fas fa-comment" style="color:var(--o5);"></i>
</div>
<?php endforeach; endif; ?>
</div>
<div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
<button class="modal-btn create" style="width:100%;" onclick="openGroupChatModal()"><i class="fas fa-users"></i> Create Group Chat</button>
</div>
</div>
</div>
<div class="modal-bg" id="groupChatModal" onclick="if(event.target===this)closeGroupChatModal()">
<div class="modal">
<h3><i class="fas fa-users" style="color:var(--o5)"></i> Create Group Chat</h3>
<input type="text" id="groupName" class="modal-input" placeholder="Enter group name...">
<input type="text" class="modal-input" placeholder="Search members..." oninput="searchGroupMembers(this.value)" style="margin-bottom:8px;">
<div class="user-list" id="groupMemberList">
<?php if(!empty($allStudents)): foreach(array_slice($allStudents,0,10) as $u): ?>
<div class="user-item" onclick="toggleGroupMember(this,<?=$u['id']?>)">
<div class="user-ava">
<?php if($u['profile_photo']): ?><img src="<?=htmlspecialchars($u['profile_photo'])?>" alt=""><?php else: ?><?=strtoupper(substr($u['full_name'],0,1))?><?php endif; ?>
</div>
<div class="user-info">
<div class="user-name"><?=htmlspecialchars($u['full_name'])?></div>
<div class="user-email"><?=htmlspecialchars($u['domain_interest']?:$u['email'])?></div>
</div>
<div class="user-check"></div>
</div>
<?php endforeach; endif; ?>
</div>
<div class="modal-btns">
<button class="modal-btn cancel" onclick="closeGroupChatModal()">Cancel</button>
<button class="modal-btn create" onclick="createGroupChat()">Create Group</button>
</div>
</div>
</div>
<script>
const ROOM_ID=<?=$activeRoomId?:0?>;
const MY_ID=<?=$sid?>;
let lastMsgId=<?=$lastMsgId?>;
let polling;
let selectedGroupMembers=[];
let replyToId=0;
let selectedFile=null;
let currentEmojiMsgId=0;

function toggleRoomsPanel(){document.getElementById('roomsPanel').classList.toggle('mobile-visible');}
function scrollToBottom(){const c=document.getElementById('chatMessages');if(c)c.scrollTop=c.scrollHeight;}
scrollToBottom();

function handleFileSelect(event){
const file=event.target.files[0];
if(!file)return;
if(file.size>10485760){alert('File size must be under 10MB');event.target.value='';return;}
selectedFile=file;
const preview=document.getElementById('attachmentPreview');
const content=document.getElementById('attachmentContent');
if(file.type.startsWith('image/')){
const reader=new FileReader();
reader.onload=function(e){content.innerHTML=`<img src="${e.target.result}" alt="${file.name}">`;};
reader.readAsDataURL(file);
}else{
content.innerHTML=`<div style="display:flex;align-items:center;gap:10px;"><i class="fas fa-file" style="font-size:1.5rem;color:var(--o5);"></i><div><div style="font-size:.82rem;font-weight:600;">${file.name}</div><div style="font-size:.7rem;color:var(--text3);">${(file.size/1024).toFixed(1)} KB</div></div></div>`;
}
preview.classList.add('active');
}

function clearAttachment(){
selectedFile=null;
document.getElementById('fileInput').value='';
document.getElementById('attachmentPreview').classList.remove('active');
}

function setReplyTo(msgId,senderName,messageText){
replyToId=msgId;
document.getElementById('replyToName').textContent=senderName;
document.getElementById('replyToText').textContent=messageText.substring(0,50);
document.getElementById('replyPreview').classList.add('active');
document.getElementById('msgInput').focus();
}

function clearReply(){
replyToId=0;
document.getElementById('replyPreview').classList.remove('active');
}

function showEmojiPicker(msgId,btn){
currentEmojiMsgId=msgId;
const picker=document.getElementById('emojiPicker');
const rect=btn.getBoundingClientRect();
picker.style.left=rect.left+'px';
picker.classList.add('active');
}

function selectEmoji(emoji){
if(currentEmojiMsgId)toggleReaction(currentEmojiMsgId,emoji);
document.getElementById('emojiPicker').classList.remove('active');
}

function toggleReaction(msgId,emoji){
const fd=new FormData();
fd.append('action','react');
fd.append('msg_id',msgId);
fd.append('emoji',emoji);
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(d=>{if(d.success)location.reload();});
}

function sendMessage(){
const input=document.getElementById('msgInput');
const msg=input.value.trim();
if(!msg && !selectedFile)return;
if(!ROOM_ID)return;
const btn=document.getElementById('sendBtn');
btn.disabled=true;
const fd=new FormData();
fd.append('action','send');
fd.append('room_id',ROOM_ID);
fd.append('message',msg);
if(replyToId)fd.append('reply_to',replyToId);
if(selectedFile)fd.append('attachment',selectedFile);
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(d=>{
if(d.success){
input.value='';
input.style.height='auto';
clearReply();
clearAttachment();
location.reload();
}else{
alert('Failed to send: '+(d.error||'Unknown error'));
}
btn.disabled=false;
})
.catch(e=>{
btn.disabled=false;
alert('Error: '+e.message);
});
}

function pollMessages(){
if(!ROOM_ID)return;
const fd=new FormData();
fd.append('action','fetch');
fd.append('room_id',ROOM_ID);
fd.append('since_id',lastMsgId);
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(data=>{
if(data.messages && data.messages.length>0){
location.reload();
}
});
}

if(ROOM_ID){polling=setInterval(pollMessages,5000);}

const msgInput=document.getElementById('msgInput');
if(msgInput){
msgInput.addEventListener('input',function(){
this.style.height='auto';
this.style.height=Math.min(this.scrollHeight,120)+'px';
});
}

function viewImage(src){
document.getElementById('viewerImage').src=src;
document.getElementById('imageViewer').classList.add('active');
}

function closeImageViewer(){
document.getElementById('imageViewer').classList.remove('active');
}

function openNewChatModal(){document.getElementById('newChatModal').classList.add('open');}
function closeNewChatModal(){document.getElementById('newChatModal').classList.remove('open');}

function startDirectMessage(targetId){
const fd=new FormData();
fd.append('action','start_dm');
fd.append('target_id',targetId);
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(d=>{
if(d.success)location.href='messenger_fixed.php?room='+d.room_id;
else alert('Failed to start conversation');
});
}

function searchUsers(query){
if(!query.trim()||query.length<2)return;
const fd=new FormData();
fd.append('action','search_users');
fd.append('query',query);
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(users=>{
const list=document.getElementById('userList');
if(!users||users.length===0){
list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text3);">No users found</div>';
return;
}
list.innerHTML=users.map(u=>`
<div class="user-item" onclick="startDirectMessage(${u.id})">
<div class="user-ava">
${u.profile_photo?`<img src="${u.profile_photo}" alt="">`:u.full_name[0].toUpperCase()}
${u.is_online?'<div class="online-dot"></div>':''}
</div>
<div class="user-info">
<div class="user-name">${u.full_name}</div>
<div class="user-email">${u.domain_interest||u.email}</div>
</div>
<i class="fas fa-comment" style="color:var(--o5);"></i>
</div>
`).join('');
});
}

function openGroupChatModal(){
closeNewChatModal();
document.getElementById('groupChatModal').classList.add('open');
selectedGroupMembers=[];
}

function closeGroupChatModal(){
document.getElementById('groupChatModal').classList.remove('open');
}

function toggleGroupMember(elem,userId){
elem.classList.toggle('selected');
const check=elem.querySelector('.user-check');
if(elem.classList.contains('selected')){
selectedGroupMembers.push(userId);
check.innerHTML='<i class="fas fa-check fa-xs"></i>';
}else{
selectedGroupMembers=selectedGroupMembers.filter(id=>id!==userId);
check.innerHTML='';
}
}

function createGroupChat(){
const name=document.getElementById('groupName').value.trim();
if(!name){alert('Please enter a group name');return;}
if(selectedGroupMembers.length===0){alert('Please select at least one member');return;}
const fd=new FormData();
fd.append('action','create_group');
fd.append('room_name',name);
fd.append('member_ids',JSON.stringify(selectedGroupMembers));
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(d=>{
if(d.success)location.href='messenger_fixed.php?room='+d.room_id;
else alert('Failed to create group');
});
}

function searchGroupMembers(query){
if(!query.trim()||query.length<2)return;
const fd=new FormData();
fd.append('action','search_users');
fd.append('query',query);
fetch('messenger_fixed.php',{method:'POST',body:fd})
.then(r=>r.json())
.then(users=>{
const list=document.getElementById('groupMemberList');
if(!users||users.length===0){
list.innerHTML='<div style="padding:20px;text-align:center;color:var(--text3);">No users found</div>';
return;
}
list.innerHTML=users.map(u=>`
<div class="user-item" onclick="toggleGroupMember(this,${u.id})">
<div class="user-ava">${u.profile_photo?`<img src="${u.profile_photo}" alt="">`:u.full_name[0].toUpperCase()}</div>
<div class="user-info">
<div class="user-name">${u.full_name}</div>
<div class="user-email">${u.domain_interest||u.email}</div>
</div>
<div class="user-check"></div>
</div>
`).join('');
});
}

document.addEventListener('click',function(e){
const picker=document.getElementById('emojiPicker');
if(picker.classList.contains('active')&&!picker.contains(e.target)&&!e.target.closest('.msg-action-btn')){
picker.classList.remove('active');
}
});
</script>
</body>
</html>