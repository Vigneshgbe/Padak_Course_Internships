<?php
// admin_messenger_module.php
// Include this in your admin panel via: include 'admin_messenger_module.php';
// Requires $db to be set.

if (!isset($db)) {
    die('Database connection required');
}

// ── Upload dir for group logos ───────────────────────────────
$logoUploadsDir = __DIR__ . '/uploads/group_logos';
$logoUploadsUrl = 'uploads/group_logos';
if (!is_dir($logoUploadsDir)) mkdir($logoUploadsDir, 0755, true);

// ── Handle admin AJAX actions ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_messenger_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();

    $action = $_POST['admin_messenger_action'];

    // Admin: update group name + logo
    if ($action === 'admin_update_group') {
        $roomId   = (int)($_POST['room_id'] ?? 0);
        $roomName = trim($_POST['room_name'] ?? '');
        if (!$roomId || !$roomName) { echo json_encode(['success'=>false,'error'=>'Room ID and name required']); exit; }

        $logoPath = null;
        $hasLogo  = isset($_FILES['room_logo']) && $_FILES['room_logo']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasLogo && $_FILES['room_logo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['room_logo'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($fileExt, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                $newFileName = 'group_'.uniqid().'_'.time().'.jpg';
                $destPath    = $logoUploadsDir.'/'.$newFileName;
                if (extension_loaded('gd')) {
                    $image = null;
                    switch ($fileExt) {
                        case 'jpg': case 'jpeg': $image = @imagecreatefromjpeg($file['tmp_name']); break;
                        case 'png':  $image = @imagecreatefrompng($file['tmp_name']);  break;
                        case 'gif':  $image = @imagecreatefromgif($file['tmp_name']);  break;
                        case 'webp': $image = @imagecreatefromwebp($file['tmp_name']); break;
                    }
                    if ($image) {
                        $w = imagesx($image); $h = imagesy($image); $size = 200;
                        $canvas = imagecreatetruecolor($size, $size);
                        imagecopyresampled($canvas,$image,0,0,0,0,$size,$size,$w,$h);
                        imagejpeg($canvas, $destPath, 90);
                        imagedestroy($canvas); imagedestroy($image);
                        $logoPath = $logoUploadsUrl.'/'.$newFileName;
                    } else { move_uploaded_file($file['tmp_name'], $destPath); $logoPath = $logoUploadsUrl.'/'.$newFileName; }
                } else { move_uploaded_file($file['tmp_name'], $destPath); $logoPath = $logoUploadsUrl.'/'.$newFileName; }
            }
        }

        if ($logoPath) {
            $stmt = $db->prepare("UPDATE chat_rooms SET room_name=?, room_logo=? WHERE id=? AND room_type='group'");
            $stmt->bind_param("ssi", $roomName, $logoPath, $roomId);
        } else {
            $stmt = $db->prepare("UPDATE chat_rooms SET room_name=? WHERE id=? AND room_type='group'");
            $stmt->bind_param("si", $roomName, $roomId);
        }
        echo json_encode(['success'=>$stmt->execute(),'room_logo'=>$logoPath]);
        exit;
    }

    // Admin: get group members
    if ($action === 'admin_get_group_members') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $stmt = $db->prepare("SELECT s.id,s.full_name,s.email,s.profile_photo,s.domain_interest,cr.created_by FROM internship_students s JOIN chat_room_members crm ON crm.student_id=s.id JOIN chat_rooms cr ON cr.id=crm.room_id WHERE crm.room_id=? ORDER BY s.full_name");
        $stmt->bind_param("i", $roomId); $stmt->execute();
        $res = $stmt->get_result(); $members = [];
        while ($r = $res->fetch_assoc()) $members[] = $r;
        echo json_encode(['success'=>true,'members'=>$members]); exit;
    }

    // Admin: remove member
    if ($action === 'admin_remove_member') {
        $roomId   = (int)($_POST['room_id'] ?? 0);
        $targetId = (int)($_POST['target_id'] ?? 0);
        $creator  = $db->query("SELECT created_by FROM chat_rooms WHERE id=$roomId")->fetch_assoc()['created_by'] ?? 0;
        if ($creator == $targetId) { echo json_encode(['success'=>false,'error'=>'Cannot remove creator']); exit; }
        $stmt = $db->prepare("DELETE FROM chat_room_members WHERE room_id=? AND student_id=?");
        $stmt->bind_param("ii", $roomId, $targetId); $stmt->execute();
        echo json_encode(['success'=>true]); exit;
    }

    // Admin: add member to group
    if ($action === 'admin_add_member') {
        $roomId   = (int)($_POST['room_id'] ?? 0);
        $targetId = (int)($_POST['target_id'] ?? 0);
        $stmt = $db->prepare("INSERT IGNORE INTO chat_room_members (room_id,student_id) VALUES (?,?)");
        $stmt->bind_param("ii", $roomId, $targetId); $stmt->execute();
        echo json_encode(['success'=>true]); exit;
    }

    // Admin: search students
    if ($action === 'admin_search_students') {
        $q = '%'.trim($_POST['query'] ?? '').'%';
        $stmt = $db->prepare("SELECT id,full_name,email,profile_photo,domain_interest FROM internship_students WHERE is_active=1 AND (full_name LIKE ? OR email LIKE ?) LIMIT 20");
        $stmt->bind_param("ss", $q, $q); $stmt->execute();
        $res = $stmt->get_result(); $users = [];
        while ($r = $res->fetch_assoc()) $users[] = $r;
        echo json_encode($users); exit;
    }

    // Admin: soft-delete a message
    if ($action === 'admin_delete_message') {
        $msgId = (int)($_POST['msg_id'] ?? 0);
        $stmt  = $db->prepare("UPDATE chat_messages SET is_deleted=1 WHERE id=?");
        $stmt->bind_param("i", $msgId); $stmt->execute();
        echo json_encode(['success'=>true]); exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']); exit;
}

// ── Fetch data for display ────────────────────────────────────
$messagesQuery = "
    SELECT cm.id, cm.room_id, cm.sender_id, cm.message, cm.reply_to_id,
        cm.attachment_path, cm.attachment_type, cm.attachment_name,
        cm.is_deleted, cm.created_at,
        cr.room_name, cr.room_type, cr.room_logo,
        sender.full_name as sender_name, sender.email as sender_email, sender.profile_photo as sender_photo,
        (SELECT COUNT(*) FROM message_reactions WHERE message_id=cm.id) as reaction_count,
        (SELECT message FROM chat_messages WHERE id=cm.reply_to_id) as replied_message,
        (SELECT full_name FROM internship_students WHERE id=(SELECT sender_id FROM chat_messages WHERE id=cm.reply_to_id)) as replied_to_person
    FROM chat_messages cm
    LEFT JOIN chat_rooms cr ON cm.room_id=cr.id
    LEFT JOIN internship_students sender ON cm.sender_id=sender.id
    ORDER BY cm.created_at DESC
";
$messagesResult = $db->query($messagesQuery);
$allMessages = [];
while ($row = $messagesResult->fetch_assoc()) {
    if ($row['room_type'] == 'direct') {
        $rq = $db->query("SELECT s.full_name,s.email,s.profile_photo FROM chat_room_members crm JOIN internship_students s ON crm.student_id=s.id WHERE crm.room_id={$row['room_id']} AND crm.student_id!={$row['sender_id']} LIMIT 1");
        if ($rq && $rr = $rq->fetch_assoc()) { $row['recipient_name']=$rr['full_name']; $row['recipient_email']=$rr['email']; $row['recipient_photo']=$rr['profile_photo']; }
    } else {
        $mc = $db->query("SELECT COUNT(*) as count FROM chat_room_members WHERE room_id={$row['room_id']}")->fetch_assoc()['count']??0;
        $row['group_member_count'] = $mc;
    }
    $allMessages[] = $row;
}

$totalMessages    = count($allMessages);
$deletedMessages  = count(array_filter($allMessages, fn($m)=>$m['is_deleted']==1));
$activeMessages   = $totalMessages - $deletedMessages;
$withAttachments  = count(array_filter($allMessages, fn($m)=>!empty($m['attachment_path'])));

// Groups list
$groupsResult = $db->query("SELECT cr.id,cr.room_name,cr.room_logo,cr.created_by,cr.created_at,
    (SELECT COUNT(*) FROM chat_room_members WHERE room_id=cr.id) as member_count,
    (SELECT COUNT(*) FROM chat_messages WHERE room_id=cr.id AND is_deleted=0) as message_count,
    s.full_name as creator_name
    FROM chat_rooms cr LEFT JOIN internship_students s ON s.id=cr.created_by
    WHERE cr.room_type='group' AND cr.is_active=1 ORDER BY cr.created_at DESC");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) $groups[] = $row;

$roomsResult = $db->query("SELECT id,room_name,room_type,(SELECT COUNT(*) FROM chat_messages WHERE room_id=chat_rooms.id) as message_count,(SELECT COUNT(*) FROM chat_room_members WHERE room_id=chat_rooms.id) as member_count FROM chat_rooms WHERE is_active=1 ORDER BY message_count DESC");
$rooms = [];
while ($row = $roomsResult->fetch_assoc()) $rooms[] = $row;

// All students for member search
$allStudents = [];
$sr = $db->query("SELECT id,full_name,email,profile_photo,domain_interest FROM internship_students WHERE is_active=1 ORDER BY full_name LIMIT 200");
if ($sr) while ($r = $sr->fetch_assoc()) $allStudents[] = $r;
?>

<style>
.messages-header{background:linear-gradient(135deg,var(--o5),var(--o4));padding:24px;border-radius:12px;color:#fff;margin-bottom:24px;box-shadow:0 4px 14px rgba(249,115,22,0.25);}
.messages-header h2{font-size:1.5rem;font-weight:800;margin-bottom:8px;display:flex;align-items:center;gap:10px;}
.msg-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:24px;}
.msg-stat-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:all .2s;}
.msg-stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.1);}
.msc-label{font-size:.75rem;color:var(--text3);font-weight:600;margin-bottom:6px;}
.msc-value{font-size:1.75rem;font-weight:900;color:var(--text);}
.msc-icon{font-size:1.1rem;color:var(--o5);margin-right:6px;}

/* Groups management section */
.groups-section{background:var(--card);border:1px solid var(--border);border-radius:12px;margin-bottom:24px;overflow:hidden;}
.groups-section-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.groups-section-header h3{font-size:1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.groups-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0;border-top:none;}
.group-card-item{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;}
.group-card-item:hover{background:#f8fafc;}
.group-card-item:last-child{border-bottom:none;}
.group-logo{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;font-weight:700;flex-shrink:0;overflow:hidden;}
.group-logo img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.group-card-info{flex:1;min-width:0;}
.group-card-name{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:3px;}
.group-card-meta{font-size:.72rem;color:var(--text3);}
.btn-edit-admin{padding:7px 14px;background:rgba(249,115,22,0.1);border:1.5px solid rgba(249,115,22,0.2);color:var(--o5);border-radius:8px;cursor:pointer;font-size:.78rem;font-weight:600;transition:all .2s;white-space:nowrap;}
.btn-edit-admin:hover{background:var(--o5);color:#fff;}

.messages-filters{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px;margin-bottom:20px;}
.filters-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
.filter-input{padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;min-width:180px;outline:none;transition:all .2s;}
.filter-input:focus{border-color:var(--o5);}
.btn-filter{padding:8px 16px;background:var(--o5);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;}
.btn-reset{background:var(--text3);color:#fff;}
.messages-container{display:flex;flex-direction:column;gap:12px;}
.message-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:all .2s;}
.message-card:hover{box-shadow:0 6px 16px rgba(0,0,0,.08);border-color:var(--o5);}
.message-card.deleted{background:#fef2f2;border-color:#fecaca;}
.msg-card-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;padding-bottom:14px;border-bottom:2px solid var(--border);}
.msg-participants{display:flex;align-items:center;gap:12px;flex:1;}
.msg-person{display:flex;align-items:center;gap:8px;}
.msg-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
.msg-avatar-placeholder{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--o4),var(--o5));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;}
.msg-person-info h4{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.msg-person-info p{font-size:.72rem;color:var(--text3);}
.msg-arrow{font-size:1.5rem;color:var(--o5);margin:0 8px;}
.msg-meta{display:flex;flex-direction:column;align-items:flex-end;gap:6px;}
.msg-id{font-size:.75rem;font-weight:700;color:var(--text3);background:var(--bg);padding:4px 8px;border-radius:6px;}
.msg-timestamp{font-size:.72rem;color:var(--text3);}
.msg-room-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;}
.msg-room-badge.direct{background:#dbeafe;color:#1e40af;}
.msg-room-badge.group{background:#fce7f3;color:#be185d;}
.msg-card-body{margin-bottom:14px;}
.msg-text{font-size:.95rem;line-height:1.7;color:var(--text);background:var(--bg);padding:14px;border-radius:8px;border-left:3px solid var(--o5);word-break:break-word;}
.msg-reply-context{background:#eff6ff;border-left:3px solid #3b82f6;padding:10px 12px;border-radius:6px;margin-bottom:10px;font-size:.82rem;color:var(--text2);}
.msg-card-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.msg-indicators{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.msg-indicator{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;font-size:.75rem;font-weight:600;}
.msg-indicator.attachment{background:#fff7ed;color:#c2410c;}
.msg-indicator.reactions{background:#f0fdf4;color:#166534;}
.msg-indicator.status-active{background:#dcfce7;color:#166534;}
.msg-indicator.status-deleted{background:#fee2e2;color:#991b1b;}
.msg-actions{display:flex;gap:8px;}
.btn-action{padding:8px 14px;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;}
.btn-view{background:#dbeafe;color:#1e40af;}
.btn-view:hover{background:#bfdbfe;}
.btn-del-msg{background:#fee2e2;color:#dc2626;}
.btn-del-msg:hover{background:#fecaca;}
.no-messages{text-align:center;padding:60px 20px;color:var(--text3);}
.no-messages i{font-size:3rem;margin-bottom:16px;opacity:.5;}

/* Admin group edit modal */
.adm-modal{display:none;position:fixed;z-index:10000;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
.adm-modal.open{display:flex;}
.adm-modal-content{background:var(--card);border-radius:16px;padding:0;width:90%;max-width:580px;max-height:88vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.adm-modal-header{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.adm-modal-header h3{font-size:1.15rem;font-weight:700;}
.adm-modal-close{background:rgba(255,255,255,.2);border:none;color:#fff;width:36px;height:36px;border-radius:8px;cursor:pointer;font-size:1.4rem;display:flex;align-items:center;justify-content:center;}
.adm-modal-close:hover{background:rgba(255,255,255,.3);}
.adm-modal-body{padding:24px;overflow-y:auto;max-height:calc(88vh - 130px);}
.adm-form-label{display:block;font-size:.84rem;font-weight:600;color:var(--text);margin-bottom:6px;}
.adm-form-input{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;outline:none;margin-bottom:14px;}
.adm-form-input:focus{border-color:var(--o5);}
.adm-logo-row{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
.adm-logo-circle{width:72px;height:72px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,var(--o5),var(--o4));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;flex-shrink:0;}
.adm-logo-circle img{width:100%;height:100%;object-fit:cover;}
.adm-logo-upload-btn{padding:8px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:600;transition:all .2s;display:inline-block;}
.adm-logo-upload-btn:hover{border-color:var(--o5);color:var(--o5);}
.adm-member-row{display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;border:1px solid var(--border);margin-bottom:6px;background:var(--bg);}
.adm-member-ava{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.88rem;flex-shrink:0;overflow:hidden;}
.adm-member-ava img{width:100%;height:100%;object-fit:cover;}
.adm-member-info{flex:1;min-width:0;}
.adm-member-name{font-size:.85rem;font-weight:600;color:var(--text);}
.adm-member-sub{font-size:.72rem;color:var(--text3);}
.adm-creator-tag{font-size:.68rem;background:rgba(249,115,22,.1);color:var(--o5);padding:2px 7px;border-radius:4px;font-weight:600;margin-left:6px;}
.adm-btn-remove{background:none;border:1px solid #fecaca;color:#ef4444;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:.75rem;font-weight:600;transition:all .2s;}
.adm-btn-remove:hover{background:#fee2e2;}
.adm-modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;}
.adm-btn{padding:10px 22px;border-radius:8px;border:none;cursor:pointer;font-size:.88rem;font-weight:600;font-family:inherit;transition:opacity .2s;}
.adm-btn.cancel{background:var(--bg);border:1px solid var(--border);color:var(--text2);}
.adm-btn.save{background:var(--o5);color:#fff;}
.adm-btn:hover{opacity:.9;}
.adm-btn:disabled{opacity:.5;cursor:not-allowed;}
.adm-section-title{font-size:.82rem;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;margin-top:18px;}
.adm-search-results{max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;margin-top:-8px;margin-bottom:14px;background:var(--card);}
.adm-search-row{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;transition:background .15s;border-bottom:1px solid var(--border);}
.adm-search-row:last-child{border-bottom:none;}
.adm-search-row:hover{background:#f1f5f9;}
.group-indicator{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;color:#be185d;background:rgba(190,24,93,.1);padding:3px 8px;border-radius:4px;font-weight:600;}
</style>

<!-- Stats -->
<div class="msg-stats-grid">
    <div class="msg-stat-card"><div class="msc-label"><i class="fas fa-envelope msc-icon"></i>Total Messages</div><div class="msc-value"><?php echo $totalMessages; ?></div></div>
    <div class="msg-stat-card"><div class="msc-label"><i class="fas fa-check-circle msc-icon"></i>Active</div><div class="msc-value"><?php echo $activeMessages; ?></div></div>
    <div class="msg-stat-card"><div class="msc-label"><i class="fas fa-trash msc-icon"></i>Deleted</div><div class="msc-value"><?php echo $deletedMessages; ?></div></div>
    <div class="msg-stat-card"><div class="msc-label"><i class="fas fa-paperclip msc-icon"></i>With Attachments</div><div class="msc-value"><?php echo $withAttachments; ?></div></div>
    <div class="msg-stat-card"><div class="msc-label"><i class="fas fa-users msc-icon"></i>Group Chats</div><div class="msc-value"><?php echo count($groups); ?></div></div>
</div>

<!-- Groups Management -->
<?php if (!empty($groups)): ?>
<div class="groups-section">
    <div class="groups-section-header">
        <h3><i class="fas fa-users" style="color:var(--o5)"></i> Group Chats</h3>
        <span style="font-size:.78rem;color:var(--text3);"><?php echo count($groups); ?> group<?php echo count($groups)!=1?'s':''; ?></span>
    </div>
    <div class="groups-grid">
        <?php foreach ($groups as $g): ?>
        <div class="group-card-item">
            <div class="group-logo">
                <?php if (!empty($g['room_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($g['room_logo']); ?>" alt="" id="adminGroupLogo_<?php echo $g['id']; ?>">
                <?php else: ?>
                    <i class="fas fa-users" id="adminGroupLogo_<?php echo $g['id']; ?>"></i>
                <?php endif; ?>
            </div>
            <div class="group-card-info">
                <div class="group-card-name" id="adminGroupName_<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['room_name']); ?></div>
                <div class="group-card-meta">
                    <?php echo $g['member_count']; ?> members · <?php echo $g['message_count']; ?> messages<br>
                    Created by <?php echo htmlspecialchars($g['creator_name']??'Unknown'); ?> · <?php echo date('M d, Y', strtotime($g['created_at'])); ?>
                </div>
            </div>
            <button class="btn-edit-admin" onclick="adminOpenGroupEdit(<?php echo htmlspecialchars(json_encode($g)); ?>)">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Room stats -->
<?php if (!empty($rooms)): ?>
<div style="margin-bottom:20px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px;color:var(--text);"><i class="fas fa-door-open" style="color:var(--o5);margin-right:8px;"></i>All Chat Rooms</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;">
        <?php foreach ($rooms as $room): ?>
        <div style="background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:.85rem;font-weight:600;color:var(--text);margin-bottom:3px;"><?php echo htmlspecialchars($room['room_name']); ?></div>
                <div style="font-size:.7rem;color:var(--text3);"><?php echo ucfirst($room['room_type']); ?> · <?php echo $room['member_count']; ?> members</div>
            </div>
            <div style="font-size:1.3rem;font-weight:900;color:var(--o5);"><?php echo $room['message_count']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="messages-filters">
    <div class="filters-row">
        <input type="text" id="searchMessage" class="filter-input" placeholder="Search messages, senders...">
        <select id="filterRoom" class="filter-input">
            <option value="">All Rooms</option>
            <?php foreach ($rooms as $room): ?><option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option><?php endforeach; ?>
        </select>
        <select id="filterStatus" class="filter-input">
            <option value="">All Status</option><option value="active">Active</option><option value="deleted">Deleted</option>
        </select>
        <select id="filterType" class="filter-input">
            <option value="">All Types</option><option value="direct">Direct</option><option value="group">Group</option>
        </select>
        <button class="btn-filter btn-reset" onclick="resetFilters()"><i class="fas fa-redo"></i> Reset</button>
    </div>
</div>

<!-- Messages -->
<div class="messages-container">
    <?php if (empty($allMessages)): ?>
    <div class="no-messages"><i class="fas fa-inbox"></i><p>No messages found</p></div>
    <?php else: ?>
    <?php foreach ($allMessages as $msg): ?>
    <div class="message-card <?php echo $msg['is_deleted']?'deleted':''; ?>"
         data-room-id="<?php echo $msg['room_id']; ?>"
         data-status="<?php echo $msg['is_deleted']?'deleted':'active'; ?>"
         data-type="<?php echo $msg['room_type']; ?>"
         data-message="<?php echo htmlspecialchars(strtolower(strip_tags($msg['message']))); ?>"
         data-sender="<?php echo htmlspecialchars(strtolower($msg['sender_name']??'')); ?>"
         data-recipient="<?php echo htmlspecialchars(strtolower($msg['recipient_name']??'')); ?>">

        <div class="msg-card-header">
            <div class="msg-participants">
                <div class="msg-person">
                    <?php if (!empty($msg['sender_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['sender_photo']); ?>" class="msg-avatar" alt="">
                    <?php else: ?>
                        <div class="msg-avatar-placeholder"><?php echo strtoupper(substr($msg['sender_name']??'U',0,1)); ?></div>
                    <?php endif; ?>
                    <div class="msg-person-info">
                        <h4><?php echo htmlspecialchars($msg['sender_name']??'Unknown'); ?></h4>
                        <p><?php echo htmlspecialchars($msg['sender_email']??''); ?></p>
                    </div>
                </div>
                <div class="msg-arrow"><i class="fas fa-arrow-right"></i></div>
                <?php if ($msg['room_type']=='direct'): ?>
                <div class="msg-person">
                    <?php if (!empty($msg['recipient_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['recipient_photo']); ?>" class="msg-avatar" alt="">
                    <?php else: ?>
                        <div class="msg-avatar-placeholder"><?php echo strtoupper(substr($msg['recipient_name']??'U',0,1)); ?></div>
                    <?php endif; ?>
                    <div class="msg-person-info">
                        <h4><?php echo htmlspecialchars($msg['recipient_name']??'Unknown'); ?></h4>
                        <p><?php echo htmlspecialchars($msg['recipient_email']??''); ?></p>
                    </div>
                </div>
                <?php else: ?>
                <div class="msg-person">
                    <?php if (!empty($msg['room_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['room_logo']); ?>" class="msg-avatar" alt="">
                    <?php else: ?>
                        <div class="msg-avatar-placeholder" style="background:linear-gradient(135deg,#a855f7,#7c3aed);"><i class="fas fa-users"></i></div>
                    <?php endif; ?>
                    <div class="msg-person-info">
                        <h4><?php echo htmlspecialchars($msg['room_name']); ?></h4>
                        <p><span class="group-indicator"><i class="fas fa-users"></i> <?php echo $msg['group_member_count']??0; ?> members</span></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="msg-meta">
                <span class="msg-id">#<?php echo $msg['id']; ?></span>
                <span class="msg-room-badge <?php echo $msg['room_type']; ?>">
                    <i class="fas fa-<?php echo $msg['room_type']=='direct'?'user':'users'; ?>"></i> <?php echo ucfirst($msg['room_type']); ?>
                </span>
                <span class="msg-timestamp"><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></span>
            </div>
        </div>

        <div class="msg-card-body">
            <?php if ($msg['reply_to_id']): ?>
            <div class="msg-reply-context"><i class="fas fa-reply"></i> <strong>Replying to <?php echo htmlspecialchars($msg['replied_to_person']??'someone'); ?>:</strong> "<?php echo htmlspecialchars(substr(strip_tags($msg['replied_message']??''),0,60)); ?>"</div>
            <?php endif; ?>
            <div class="msg-text"><?php echo nl2br(htmlspecialchars(strip_tags($msg['message']))); ?></div>
        </div>

        <div class="msg-card-footer">
            <div class="msg-indicators">
                <?php if (!empty($msg['attachment_path'])): ?>
                <span class="msg-indicator attachment"><i class="fas fa-<?php echo $msg['attachment_type']=='image'?'image':'file'; ?>"></i> <?php echo htmlspecialchars($msg['attachment_name']??'Attachment'); ?></span>
                <?php endif; ?>
                <?php if ($msg['reaction_count']>0): ?>
                <span class="msg-indicator reactions"><i class="fas fa-heart"></i> <?php echo $msg['reaction_count']; ?> reaction<?php echo $msg['reaction_count']!=1?'s':''; ?></span>
                <?php endif; ?>
                <span class="msg-indicator <?php echo $msg['is_deleted']?'status-deleted':'status-active'; ?>">
                    <i class="fas fa-<?php echo $msg['is_deleted']?'trash':'check-circle'; ?>"></i> <?php echo $msg['is_deleted']?'Deleted':'Active'; ?>
                </span>
            </div>
            <div class="msg-actions">
                <button class="btn-action btn-view" onclick='adminViewMessage(<?php echo json_encode($msg); ?>)'><i class="fas fa-eye"></i> View</button>
                <?php if (!$msg['is_deleted']): ?>
                <button class="btn-action btn-del-msg" onclick="adminDeleteMessage(<?php echo $msg['id']; ?>,this)"><i class="fas fa-trash"></i> Delete</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Admin Group Edit Modal -->
<div class="adm-modal" id="adminGroupEditModal">
    <div class="adm-modal-content">
        <div class="adm-modal-header">
            <h3><i class="fas fa-users-cog"></i> Manage Group</h3>
            <button class="adm-modal-close" onclick="adminCloseGroupEdit()">&times;</button>
        </div>
        <div class="adm-modal-body">
            <input type="hidden" id="adminEditRoomId">
            <input type="hidden" id="adminEditCreatorId">

            <!-- Logo -->
            <div class="adm-section-title">Group Logo</div>
            <div class="adm-logo-row">
                <div class="adm-logo-circle" id="adminLogoPreview"><i class="fas fa-users"></i></div>
                <div>
                    <input type="file" id="adminLogoInput" style="display:none;" accept="image/*" onchange="adminHandleLogo(this)">
                    <label for="adminLogoInput" class="adm-logo-upload-btn"><i class="fas fa-upload"></i> Upload Logo</label>
                    <div style="font-size:.7rem;color:var(--text3);margin-top:4px;">JPG, PNG, WEBP · Max 2MB · Cropped to 200×200</div>
                </div>
            </div>

            <!-- Name -->
            <div class="adm-section-title">Group Name</div>
            <input type="text" id="adminEditGroupName" class="adm-form-input" placeholder="Group name...">

            <!-- Current members -->
            <div class="adm-section-title">Members</div>
            <div id="adminMemberList" style="margin-bottom:14px;max-height:240px;overflow-y:auto;"></div>

            <!-- Add member -->
            <div class="adm-section-title">Add Member</div>
            <input type="text" id="adminAddMemberSearch" class="adm-form-input" placeholder="Search student by name or email..." oninput="adminSearchAddMember(this.value)" style="margin-bottom:6px;">
            <div id="adminAddMemberResults" class="adm-search-results" style="display:none;"></div>
        </div>
        <div class="adm-modal-footer">
            <button class="adm-btn cancel" onclick="adminCloseGroupEdit()">Cancel</button>
            <button class="adm-btn save" id="adminSaveGroupBtn" onclick="adminSaveGroup()"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- Message Detail Modal -->
<div id="adminMsgModal" class="adm-modal">
    <div class="adm-modal-content">
        <div class="adm-modal-header">
            <h3><i class="fas fa-envelope-open-text"></i> Message Details</h3>
            <button class="adm-modal-close" onclick="document.getElementById('adminMsgModal').classList.remove('open')">&times;</button>
        </div>
        <div class="adm-modal-body" id="adminMsgModalBody"></div>
    </div>
</div>

<script>
let adminSelectedLogoFile = null;
let adminCurrentRoomId    = null;
<?php echo 'const ADMIN_ALL_STUDENTS = '.json_encode($allStudents).';'; ?>

// ─── GROUP EDIT ───────────────────────────────────────────────
function adminOpenGroupEdit(group){
    adminCurrentRoomId = group.id;
    adminSelectedLogoFile = null;
    document.getElementById('adminEditRoomId').value = group.id;
    document.getElementById('adminEditCreatorId').value = group.created_by;
    document.getElementById('adminEditGroupName').value = group.room_name;
    // Logo preview
    const preview = document.getElementById('adminLogoPreview');
    if(group.room_logo){
        preview.innerHTML = `<img src="${group.room_logo}" alt="">`;
    } else {
        preview.innerHTML = '<i class="fas fa-users"></i>';
    }
    adminLoadMembers();
    document.getElementById('adminAddMemberSearch').value = '';
    document.getElementById('adminAddMemberResults').style.display = 'none';
    document.getElementById('adminGroupEditModal').classList.add('open');
}
function adminCloseGroupEdit(){
    document.getElementById('adminGroupEditModal').classList.remove('open');
}

function adminHandleLogo(input){
    if(!input.files||!input.files[0]) return;
    adminSelectedLogoFile = input.files[0];
    const reader = new FileReader();
    reader.onload = e => { document.getElementById('adminLogoPreview').innerHTML = `<img src="${e.target.result}" alt="">`; };
    reader.readAsDataURL(adminSelectedLogoFile);
}

function adminLoadMembers(){
    const fd = new FormData();
    fd.append('admin_messenger_action','admin_get_group_members');
    fd.append('room_id', adminCurrentRoomId);
    fetch('', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(!d.success) return;
        const creatorId = parseInt(document.getElementById('adminEditCreatorId').value);
        document.getElementById('adminMemberList').innerHTML = d.members.map(m=>{
            const isCreator = m.id == creatorId;
            const ava = m.profile_photo
                ? `<div class="adm-member-ava"><img src="${escAdmin(m.profile_photo)}" alt=""></div>`
                : `<div class="adm-member-ava">${m.full_name[0].toUpperCase()}</div>`;
            return `<div class="adm-member-row" data-id="${m.id}">
                ${ava}
                <div class="adm-member-info">
                    <div class="adm-member-name">${escAdmin(m.full_name)}${isCreator?'<span class="adm-creator-tag">Creator</span>':''}</div>
                    <div class="adm-member-sub">${escAdmin(m.domain_interest||m.email||'')}</div>
                </div>
                ${!isCreator ? `<button class="adm-btn-remove" onclick="adminRemoveMember(${m.id},this)"><i class="fas fa-times"></i> Remove</button>` : ''}
            </div>`;
        }).join('');
    }).catch(()=>{});
}

function adminRemoveMember(targetId, btn){
    if(!confirm('Remove this member?')) return;
    const fd = new FormData();
    fd.append('admin_messenger_action','admin_remove_member');
    fd.append('room_id', adminCurrentRoomId);
    fd.append('target_id', targetId);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success) btn.closest('.adm-member-row').remove();
        else alert(d.error||'Failed');
    }).catch(()=>alert('Error'));
}

function adminSearchAddMember(q){
    const res = document.getElementById('adminAddMemberResults');
    if(!q.trim()||q.length<2){ res.style.display='none'; return; }
    const lq = q.toLowerCase();
    const filtered = ADMIN_ALL_STUDENTS.filter(s=>s.full_name.toLowerCase().includes(lq)||s.email.toLowerCase().includes(lq)).slice(0,8);
    if(!filtered.length){ res.style.display='none'; return; }
    res.style.display='block';
    res.innerHTML = filtered.map(u=>`<div class="adm-search-row" onclick="adminAddMember(${u.id},'${u.full_name.replace(/'/g,"\\'")}',this)">
        <div class="adm-member-ava" style="width:32px;height:32px;font-size:.8rem;">${u.profile_photo?`<img src="${escAdmin(u.profile_photo)}" alt="">`:u.full_name[0].toUpperCase()}</div>
        <div class="adm-member-info"><div class="adm-member-name" style="font-size:.83rem;">${escAdmin(u.full_name)}</div><div class="adm-member-sub">${escAdmin(u.domain_interest||u.email||'')}</div></div>
        <i class="fas fa-plus" style="color:var(--o5);font-size:.82rem;"></i>
    </div>`).join('');
}

function adminAddMember(targetId, name, el){
    const fd = new FormData();
    fd.append('admin_messenger_action','admin_add_member');
    fd.append('room_id', adminCurrentRoomId);
    fd.append('target_id', targetId);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){
            el.style.opacity='.4'; el.style.pointerEvents='none';
            el.querySelector('i').className='fas fa-check'; el.querySelector('i').style.color='var(--green,#22c55e)';
            adminLoadMembers();
        } else alert(d.error||'Failed');
    }).catch(()=>alert('Error'));
}

function adminSaveGroup(){
    const name = document.getElementById('adminEditGroupName').value.trim();
    if(!name){ alert('Group name required'); return; }

    const btn = document.getElementById('adminSaveGroupBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const fd = new FormData();
    fd.append('admin_messenger_action','admin_update_group');
    fd.append('room_id', adminCurrentRoomId);
    fd.append('room_name', name);
    if(adminSelectedLogoFile) fd.append('room_logo', adminSelectedLogoFile);

    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){
            // Update group card in list
            const nameEl = document.getElementById('adminGroupName_'+adminCurrentRoomId);
            if(nameEl) nameEl.textContent = name;
            if(d.room_logo){
                const logoEl = document.getElementById('adminGroupLogo_'+adminCurrentRoomId);
                if(logoEl) logoEl.outerHTML = `<img id="adminGroupLogo_${adminCurrentRoomId}" src="${d.room_logo}?t=${Date.now()}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
            }
            adminCloseGroupEdit();
        } else alert(d.error||'Failed to save');
    }).catch(()=>alert('Error'))
    .finally(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save Changes'; });
}

// ─── MESSAGE VIEW / DELETE ────────────────────────────────────
function adminViewMessage(msg){
    const body = document.getElementById('adminMsgModalBody');
    let partHtml = '';
    if(msg.room_type==='direct'){
        partHtml = `<div style="display:flex;align-items:center;gap:20px;margin-bottom:10px;"><div style="flex:1;text-align:center;"><strong>From:</strong><br>${escAdmin(msg.sender_name||'')}<br><small>${escAdmin(msg.sender_email||'')}</small></div><i class="fas fa-arrow-right" style="color:var(--o5);font-size:1.4rem;"></i><div style="flex:1;text-align:center;"><strong>To:</strong><br>${escAdmin(msg.recipient_name||'')}<br><small>${escAdmin(msg.recipient_email||'')}</small></div></div>`;
    } else {
        partHtml = `<div><strong>Sender:</strong> ${escAdmin(msg.sender_name||'')} &nbsp;<strong>Group:</strong> ${escAdmin(msg.room_name||'')}</div>`;
    }
    let attHtml = '';
    if(msg.attachment_path){
        if(msg.attachment_type==='image') attHtml = `<div style="margin-top:10px;"><img src="${escAdmin(msg.attachment_path)}" style="max-width:100%;border-radius:8px;"></div>`;
        else attHtml = `<div style="margin-top:10px;padding:12px;background:var(--bg);border-radius:8px;"><i class="fas fa-file" style="color:var(--o5);"></i> <strong>${escAdmin(msg.attachment_name||'File')}</strong></div>`;
    }
    body.innerHTML = `
        <div style="background:var(--bg);border-radius:10px;padding:16px;margin-bottom:14px;">${partHtml}</div>
        ${msg.reply_to_id?`<div style="background:#eff6ff;border-left:3px solid #3b82f6;padding:10px;border-radius:6px;margin-bottom:12px;font-size:.85rem;"><i class="fas fa-reply" style="color:#3b82f6;"></i> <strong>Replying to ${escAdmin(msg.replied_to_person||'someone')}:</strong> "${escAdmin((msg.replied_message||'').substring(0,60))}"</div>`:''}
        <div style="background:var(--bg);padding:14px;border-radius:8px;border-left:3px solid var(--o5);font-size:.95rem;line-height:1.6;margin-bottom:14px;">${escAdmin(msg.message||'')}</div>
        ${attHtml}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.82rem;background:var(--bg);padding:14px;border-radius:8px;">
            <div><strong>ID:</strong> #${msg.id}</div>
            <div><strong>Status:</strong> ${msg.is_deleted==1?'<span style="color:#dc2626;">Deleted</span>':'<span style="color:#16a34a;">Active</span>'}</div>
            <div><strong>Reactions:</strong> ${msg.reaction_count||0}</div>
            <div><strong>Time:</strong> ${new Date(msg.created_at).toLocaleString()}</div>
        </div>`;
    document.getElementById('adminMsgModal').classList.add('open');
}

function adminDeleteMessage(msgId, btn){
    if(!confirm('Delete this message? This cannot be undone.')) return;
    const fd = new FormData(); fd.append('admin_messenger_action','admin_delete_message'); fd.append('msg_id',msgId);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){
            const card = btn.closest('.message-card');
            card.classList.add('deleted');
            card.setAttribute('data-status','deleted');
            btn.remove();
        } else alert('Failed to delete');
    }).catch(()=>alert('Error'));
}

function escAdmin(s){ return s?String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'):''; }

// ─── FILTERS ──────────────────────────────────────────────────
function filterMessages(){
    const s = document.getElementById('searchMessage').value.toLowerCase();
    const rm = document.getElementById('filterRoom').value;
    const st = document.getElementById('filterStatus').value;
    const ty = document.getElementById('filterType').value;
    document.querySelectorAll('.message-card').forEach(c=>{
        const show = (!s||(c.dataset.message||'').includes(s)||(c.dataset.sender||'').includes(s)||(c.dataset.recipient||'').includes(s))
            && (!rm || c.dataset.roomId===rm)
            && (!st || c.dataset.status===st)
            && (!ty || c.dataset.type===ty);
        c.style.display = show?'':'none';
    });
}
document.getElementById('searchMessage').addEventListener('input', filterMessages);
document.getElementById('filterRoom').addEventListener('change', filterMessages);
document.getElementById('filterStatus').addEventListener('change', filterMessages);
document.getElementById('filterType').addEventListener('change', filterMessages);
function resetFilters(){ ['searchMessage','filterRoom','filterStatus','filterType'].forEach(id=>{ const el=document.getElementById(id); if(el.tagName==='INPUT') el.value=''; else el.value=''; }); filterMessages(); }

// Close modals on outside click
['adminGroupEditModal','adminMsgModal'].forEach(id=>{
    document.getElementById(id).addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
});
</script>