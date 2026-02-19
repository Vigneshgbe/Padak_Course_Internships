<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'messenger';

// Handle send message (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'send') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        if ($roomId && $msg) {
            // Verify membership
            $chk = $db->prepare("SELECT id FROM chat_room_members WHERE room_id=? AND student_id=?");
            $chk->bind_param("ii",$roomId,$sid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $stmt = $db->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?,?,?)");
                $stmt->bind_param("iis",$roomId,$sid,$msg);
                if ($stmt->execute()) {
                    $msgId = $db->insert_id;
                    echo json_encode(['success'=>true,'msg_id'=>$msgId,'time'=>date('h:i A')]);
                    exit;
                }
            }
        }
        echo json_encode(['success'=>false]); exit;
    }
    if ($_POST['action'] === 'fetch') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $since = (int)($_POST['since_id'] ?? 0);
        $msgs = [];
        $stmt = $db->prepare("SELECT cm.id, cm.message, cm.created_at, cm.sender_id, s.full_name
            FROM chat_messages cm JOIN internship_students s ON s.id=cm.sender_id
            WHERE cm.room_id=? AND cm.id>? AND cm.is_deleted=0 ORDER BY cm.id ASC LIMIT 50");
        $stmt->bind_param("ii",$roomId,$since);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $msgs[] = $r;
        // Update last read
        $upd = $db->prepare("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=? AND student_id=?");
        $upd->bind_param("ii",$roomId,$sid);
        $upd->execute();
        echo json_encode($msgs); exit;
    }
    if ($_POST['action'] === 'create_room') {
        $name = trim($_POST['room_name'] ?? '');
        $type = in_array($_POST['room_type']??'',['group','direct'])?$_POST['room_type']:'group';
        if ($name) {
            $stmt = $db->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?,?,?)");
            $stmt->bind_param("ssi",$name,$type,$sid);
            if ($stmt->execute()) {
                $roomId = $db->insert_id;
                $addMe = $db->prepare("INSERT IGNORE INTO chat_room_members (room_id,student_id) VALUES (?,?)");
                $addMe->bind_param("ii",$roomId,$sid);
                $addMe->execute();
                echo json_encode(['success'=>true,'room_id'=>$roomId]); exit;
            }
        }
        echo json_encode(['success'=>false]); exit;
    }
    echo json_encode(['success'=>false]); exit;
}

// Get rooms this student is in
$rooms = [];
$res = $db->query("SELECT cr.*, 
    (SELECT cm2.message FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.id DESC LIMIT 1) as last_msg,
    (SELECT cm2.created_at FROM chat_messages cm2 WHERE cm2.room_id=cr.id AND cm2.is_deleted=0 ORDER BY cm2.id DESC LIMIT 1) as last_msg_time,
    (SELECT COUNT(*) FROM chat_messages cm2 JOIN chat_room_members crm2 ON crm2.room_id=cm2.room_id AND crm2.student_id=$sid WHERE cm2.room_id=cr.id AND cm2.sender_id!=$sid AND cm2.is_deleted=0 AND (crm2.last_read_at IS NULL OR cm2.created_at > crm2.last_read_at)) as unread
    FROM chat_rooms cr
    JOIN chat_room_members crm ON crm.room_id=cr.id AND crm.student_id=$sid
    ORDER BY last_msg_time DESC LIMIT 30");
if ($res) while ($r = $res->fetch_assoc()) $rooms[] = $r;

// If no rooms, auto-create a General room
if (empty($rooms)) {
    $db->query("INSERT IGNORE INTO chat_rooms (room_name, room_type, created_by) VALUES ('General', 'group', $sid)");
    $newRoomId = $db->insert_id;
    if ($newRoomId) {
        $db->query("INSERT IGNORE INTO chat_room_members (room_id, student_id) VALUES ($newRoomId, $sid)");
        $rooms = [['id'=>$newRoomId,'room_name'=>'General','room_type'=>'group','last_msg'=>null,'last_msg_time'=>null,'unread'=>0]];
    }
}

$activeRoomId = (int)($_GET['room'] ?? ($rooms[0]['id'] ?? 0));
$activeRoom = null;
foreach ($rooms as $r) { if ($r['id'] == $activeRoomId) { $activeRoom = $r; break; } }

// Load messages for active room
$messages = [];
if ($activeRoomId) {
    $res = $db->query("SELECT cm.*, s.full_name FROM chat_messages cm JOIN internship_students s ON s.id=cm.sender_id WHERE cm.room_id=$activeRoomId AND cm.is_deleted=0 ORDER BY cm.created_at ASC LIMIT 100");
    if ($res) while ($r = $res->fetch_assoc()) $messages[] = $r;
    // Mark read
    $db->query("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=$activeRoomId AND student_id=$sid");
}

// Room members
$roomMembers = [];
if ($activeRoomId) {
    $rm = $db->query("SELECT s.id, s.full_name, s.domain_interest FROM internship_students s JOIN chat_room_members crm ON crm.student_id=s.id WHERE crm.room_id=$activeRoomId");
    if ($rm) while ($r = $rm->fetch_assoc()) $roomMembers[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Messenger - Padak</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--sbw:258px;--o5:#f97316;--o4:#fb923c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);height:100vh;overflow:hidden;}
.page-wrap{margin-left:var(--sbw);height:100vh;display:flex;flex-direction:column;}
.topbar{flex-shrink:0;background:rgba(248,250,252,0.95);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;gap:10px;}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);}
.chat-layout{flex:1;display:grid;grid-template-columns:280px 1fr;min-height:0;}
/* Sidebar rooms */
.rooms-panel{border-right:1px solid var(--border);display:flex;flex-direction:column;background:var(--card);overflow:hidden;}
.rooms-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.rooms-head-title{font-size:.9rem;font-weight:700;color:var(--text);}
.btn-new-room{background:var(--o5);border:none;color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:opacity .2s;}
.btn-new-room:hover{opacity:.85;}
.rooms-list{flex:1;overflow-y:auto;padding:6px;}
.room-item{display:flex;align-items:center;gap:10px;padding:10px 10px;border-radius:9px;cursor:pointer;transition:background .15s;text-decoration:none;}
.room-item:hover{background:#f1f5f9;text-decoration:none;}
.room-item.active{background:rgba(249,115,22,0.1);}
.room-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.room-icon.group{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;}
.room-icon.direct{background:linear-gradient(135deg,#3b82f6,#60a5fa);color:#fff;}
.room-icon.team{background:linear-gradient(135deg,#8b5cf6,#a78bfa);color:#fff;}
.room-info{flex:1;min-width:0;}
.room-name{font-size:.84rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.room-last{font-size:.72rem;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.room-badge{background:var(--o5);color:#fff;font-size:.62rem;font-weight:700;padding:1px 6px;border-radius:9px;flex-shrink:0;}
/* Chat area */
.chat-area{display:flex;flex-direction:column;background:var(--bg);}
.chat-head{flex-shrink:0;padding:12px 20px;border-bottom:1px solid var(--border);background:var(--card);display:flex;align-items:center;gap:12px;}
.chat-head-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.chat-room-name{font-size:.95rem;font-weight:700;color:var(--text);}
.chat-room-meta{font-size:.74rem;color:var(--text3);}
.chat-messages{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:10px;}
.msg-group{display:flex;gap:9px;align-items:flex-end;}
.msg-group.mine{flex-direction:row-reverse;}
.msg-ava{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#64748b,#475569);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;}
.msg-group.mine .msg-ava{background:linear-gradient(135deg,var(--o5),var(--o4));}
.msg-bubble-wrap{max-width:65%;}
.msg-sender-name{font-size:.7rem;color:var(--text3);margin-bottom:3px;padding-left:2px;}
.msg-group.mine .msg-sender-name{text-align:right;}
.msg-bubble{padding:10px 14px;border-radius:14px;font-size:.88rem;line-height:1.55;word-break:break-word;}
.msg-bubble.other{background:var(--card);border:1px solid var(--border);border-bottom-left-radius:4px;color:var(--text);}
.msg-bubble.mine-b{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;border-bottom-right-radius:4px;}
.msg-time{font-size:.65rem;color:var(--text3);margin-top:3px;padding:0 2px;}
.msg-group.mine .msg-time{text-align:right;}
.no-msgs{text-align:center;padding:40px;color:var(--text3);}
.no-msgs i{font-size:2rem;margin-bottom:10px;display:block;opacity:.3;}
/* Input */
.chat-input-area{flex-shrink:0;padding:12px 20px;border-top:1px solid var(--border);background:var(--card);}
.chat-form{display:flex;gap:10px;align-items:flex-end;}
.chat-input{flex:1;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;outline:none;resize:none;max-height:120px;transition:border-color .2s;}
.chat-input:focus{border-color:var(--o5);}
.send-btn{width:42px;height:42px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s;}
.send-btn:hover{opacity:.9;}
/* New room modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal{background:var(--card);border-radius:14px;padding:24px;width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.2);}
.modal h3{font-size:1rem;font-weight:700;margin-bottom:16px;}
.modal-label{display:block;font-size:.84rem;font-weight:600;margin-bottom:6px;color:var(--text);}
.modal-input,.modal-select{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;outline:none;margin-bottom:14px;appearance:none;}
.modal-input:focus,.modal-select:focus{border-color:var(--o5);}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;}
.modal-btn{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-size:.84rem;font-weight:600;font-family:inherit;}
.modal-btn.cancel{background:var(--bg);border:1px solid var(--border);color:var(--text2);}
.modal-btn.create{background:var(--o5);color:#fff;}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .chat-layout{grid-template-columns:1fr;}
    .rooms-panel{display:none;}
    .rooms-panel.mobile-open{display:flex;position:fixed;inset:0;z-index:300;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title">Messenger</div>
    </div>
    <div class="chat-layout">
        <!-- Rooms list -->
        <div class="rooms-panel">
            <div class="rooms-head">
                <span class="rooms-head-title">Conversations</span>
                <button class="btn-new-room" onclick="document.getElementById('newRoomModal').classList.add('open')" title="New room"><i class="fas fa-plus fa-xs"></i></button>
            </div>
            <div class="rooms-list">
                <?php foreach ($rooms as $r): ?>
                <a href="?room=<?php echo $r['id']; ?>" class="room-item <?php echo $r['id']==$activeRoomId?'active':''; ?>">
                    <div class="room-icon <?php echo $r['room_type']; ?>">
                        <i class="fas <?php echo $r['room_type']==='group'?'fa-users':($r['room_type']==='team'?'fa-layer-group':'fa-user'); ?>"></i>
                    </div>
                    <div class="room-info">
                        <div class="room-name"><?php echo htmlspecialchars($r['room_name']?:ucfirst($r['room_type'])); ?></div>
                        <div class="room-last"><?php echo $r['last_msg'] ? htmlspecialchars(substr($r['last_msg'],0,40)) : 'No messages yet'; ?></div>
                    </div>
                    <?php if ($r['unread'] > 0): ?><span class="room-badge"><?php echo $r['unread']; ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chat area -->
        <div class="chat-area">
            <?php if ($activeRoom): ?>
            <div class="chat-head">
                <div class="chat-head-icon"><i class="fas <?php echo $activeRoom['room_type']==='group'?'fa-users':'fa-user'; ?>"></i></div>
                <div>
                    <div class="chat-room-name"><?php echo htmlspecialchars($activeRoom['room_name']); ?></div>
                    <div class="chat-room-meta"><?php echo count($roomMembers); ?> members &bull; <?php echo ucfirst($activeRoom['room_type']); ?> chat</div>
                </div>
                <div style="margin-left:auto;display:flex;gap:6px;">
                    <?php foreach (array_slice($roomMembers,0,4) as $m): ?>
                    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;border:2px solid var(--bg);" title="<?php echo htmlspecialchars($m['full_name']); ?>"><?php echo strtoupper(substr($m['full_name'],0,1)); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                <div class="no-msgs"><i class="fas fa-comments"></i><p>No messages yet. Say hello!</p></div>
                <?php else: ?>
                <?php $lastMsgId = 0; foreach ($messages as $msg):
                    $isMe = $msg['sender_id'] == $sid;
                    $lastMsgId = max($lastMsgId, $msg['id']);
                ?>
                <div class="msg-group <?php echo $isMe?'mine':''; ?>">
                    <div class="msg-ava"><?php echo strtoupper(substr($msg['full_name'],0,1)); ?></div>
                    <div class="msg-bubble-wrap">
                        <?php if (!$isMe): ?><div class="msg-sender-name"><?php echo htmlspecialchars(explode(' ',$msg['full_name'])[0]); ?></div><?php endif; ?>
                        <div class="msg-bubble <?php echo $isMe?'mine-b':'other'; ?>"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <div class="msg-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="chat-input-area">
                <div class="chat-form">
                    <textarea class="chat-input" id="msgInput" placeholder="Type a message…" rows="1"
                        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                    <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane fa-sm"></i></button>
                </div>
            </div>
            <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text3);">
                <div style="text-align:center;"><i class="fas fa-comments" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:12px;"></i><p>Select a room to start chatting</p></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Room Modal -->
<div class="modal-bg" id="newRoomModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal">
        <h3><i class="fas fa-plus-circle" style="color:var(--o5)"></i> Create New Room</h3>
        <label class="modal-label">Room Name</label>
        <input type="text" id="newRoomName" class="modal-input" placeholder="e.g. Project Alpha">
        <label class="modal-label">Type</label>
        <select id="newRoomType" class="modal-select">
            <option value="group">Group Chat</option>
            <option value="direct">Direct Message</option>
        </select>
        <div class="modal-btns">
            <button class="modal-btn cancel" onclick="document.getElementById('newRoomModal').classList.remove('open')">Cancel</button>
            <button class="modal-btn create" onclick="createRoom()">Create</button>
        </div>
    </div>
</div>

<script>
const ROOM_ID = <?php echo $activeRoomId; ?>;
let lastMsgId = <?php echo $lastMsgId ?? 0; ?>;
let polling;

function scrollToBottom(){const c=document.getElementById('chatMessages');if(c)c.scrollTop=c.scrollHeight;}
scrollToBottom();

function sendMessage(){
    const input=document.getElementById('msgInput');
    const msg=input.value.trim();
    if(!msg||!ROOM_ID)return;
    input.value='';
    const fd=new FormData();
    fd.append('action','send');fd.append('room_id',ROOM_ID);fd.append('message',msg);
    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{if(d.success){appendMessage({id:d.msg_id,message:msg,full_name:<?php echo json_encode($student['full_name']); ?>,created_at:new Date().toISOString(),sender_id:<?php echo $sid; ?>},true);lastMsgId=d.msg_id;}});
}

function appendMessage(msg, isMe){
    const c=document.getElementById('chatMessages');
    const nm=c.querySelector('.no-msgs');
    if(nm)nm.remove();
    const div=document.createElement('div');
    div.className='msg-group'+(isMe?' mine':'');
    const time=new Date(msg.created_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    div.innerHTML=`<div class="msg-ava">${msg.full_name[0].toUpperCase()}</div>
        <div class="msg-bubble-wrap">
            ${!isMe?`<div class="msg-sender-name">${msg.full_name.split(' ')[0]}</div>`:''}
            <div class="msg-bubble ${isMe?'mine-b':'other'}">${msg.message.replace(/\n/g,'<br>')}</div>
            <div class="msg-time">${time}</div>
        </div>`;
    c.appendChild(div);
    scrollToBottom();
}

function pollMessages(){
    if(!ROOM_ID)return;
    const fd=new FormData();
    fd.append('action','fetch');fd.append('room_id',ROOM_ID);fd.append('since_id',lastMsgId);
    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(msgs=>{
            msgs.forEach(m=>{
                if(m.sender_id!=<?php echo $sid; ?>){appendMessage(m,false);lastMsgId=m.id;}
            });
        }).catch(()=>{});
}
polling=setInterval(pollMessages,3000);

function createRoom(){
    const name=document.getElementById('newRoomName').value.trim();
    const type=document.getElementById('newRoomType').value;
    if(!name)return;
    const fd=new FormData();
    fd.append('action','create_room');fd.append('room_name',name);fd.append('room_type',type);
    fetch('messenger.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{if(d.success)location.href='messenger.php?room='+d.room_id;});
}

// Auto-resize textarea
document.getElementById('msgInput')?.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px';});
</script>
</body>
</html>