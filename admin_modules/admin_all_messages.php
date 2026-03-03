<?php
// Admin Messages Management Module - Professional Version
// This file should be included in admin.php as: include 'admin_modules/admin_messages_manage.php';

if (!isset($db)) {
    die('Database connection required');
}

// Fetch all messages with complete sender and receiver details
$messagesQuery = "
    SELECT 
        cm.id,
        cm.room_id,
        cm.sender_id,
        cm.message,
        cm.reply_to_id,
        cm.attachment_path,
        cm.attachment_type,
        cm.attachment_name,
        cm.is_deleted,
        cm.created_at,
        cr.room_name,
        cr.room_type,
        sender.full_name as sender_name,
        sender.email as sender_email,
        sender.profile_photo as sender_photo,
        (SELECT COUNT(*) FROM message_reactions WHERE message_id = cm.id) as reaction_count,
        (SELECT message FROM chat_messages WHERE id = cm.reply_to_id) as replied_message,
        (SELECT full_name FROM internship_students WHERE id = (SELECT sender_id FROM chat_messages WHERE id = cm.reply_to_id)) as replied_to_person
    FROM chat_messages cm
    LEFT JOIN chat_rooms cr ON cm.room_id = cr.id
    LEFT JOIN internship_students sender ON cm.sender_id = sender.id
    ORDER BY cm.created_at DESC
";

$messagesResult = $db->query($messagesQuery);
$allMessages = [];
while ($row = $messagesResult->fetch_assoc()) {
    // Get recipient info for direct messages
    if ($row['room_type'] == 'direct') {
        $recipientQuery = "
            SELECT s.full_name, s.email, s.profile_photo
            FROM chat_room_members crm
            JOIN internship_students s ON crm.student_id = s.id
            WHERE crm.room_id = {$row['room_id']} 
            AND crm.student_id != {$row['sender_id']}
            LIMIT 1
        ";
        $recipientResult = $db->query($recipientQuery);
        if ($recipientResult && $recipientRow = $recipientResult->fetch_assoc()) {
            $row['recipient_name'] = $recipientRow['full_name'];
            $row['recipient_email'] = $recipientRow['email'];
            $row['recipient_photo'] = $recipientRow['profile_photo'];
        }
    } else {
        // For group messages, get member count
        $memberCountQuery = "SELECT COUNT(*) as count FROM chat_room_members WHERE room_id = {$row['room_id']}";
        $memberCountResult = $db->query($memberCountQuery);
        $memberCount = $memberCountResult->fetch_assoc()['count'] ?? 0;
        $row['group_member_count'] = $memberCount;
    }
    $allMessages[] = $row;
}

// Get total counts
$totalMessages = count($allMessages);
$deletedMessages = count(array_filter($allMessages, function($m) { return $m['is_deleted'] == 1; }));
$activeMessages = $totalMessages - $deletedMessages;
$messagesWithAttachments = count(array_filter($allMessages, function($m) { return !empty($m['attachment_path']); }));

// Get room statistics
$roomsQuery = "SELECT id, room_name, room_type, 
               (SELECT COUNT(*) FROM chat_messages WHERE room_id = chat_rooms.id) as message_count,
               (SELECT COUNT(*) FROM chat_room_members WHERE room_id = chat_rooms.id) as member_count
               FROM chat_rooms WHERE is_active = 1 ORDER BY message_count DESC";
$roomsResult = $db->query($roomsQuery);
$rooms = [];
while ($row = $roomsResult->fetch_assoc()) {
    $rooms[] = $row;
}
?>

<style>
.messages-header{background:#fff;padding:32px 28px;border-radius:12px;margin-bottom:28px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.04);}
.messages-header h2{font-size:1.65rem;font-weight:800;color:var(--text);margin-bottom:6px;display:flex;align-items:center;gap:12px;}
.messages-header h2 i{color:var(--o5);font-size:1.5rem;}
.messages-header p{font-size:.9rem;color:var(--text2);}

.msg-stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.msg-stat-box{background:#fff;border:1px solid var(--border);border-radius:10px;padding:20px;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
.msg-stat-box:hover{box-shadow:0 4px 12px rgba(0,0,0,0.08);transform:translateY(-2px);}
.msb-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.msb-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
.msb-icon.orange{background:linear-gradient(135deg,#fff7ed,#ffedd5);color:var(--o6);}
.msb-icon.blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#1e40af;}
.msb-icon.green{background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#166534;}
.msb-icon.red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#991b1b;}
.msb-value{font-size:2rem;font-weight:900;color:var(--text);line-height:1;margin-bottom:6px;}
.msb-label{font-size:.8rem;color:var(--text3);font-weight:600;}

.filters-bar{background:#fff;border:1px solid var(--border);border-radius:10px;padding:20px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
.filters-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:center;}
.filter-field{display:flex;flex-direction:column;gap:6px;}
.filter-field label{font-size:.75rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.3px;}
.filter-field input,.filter-field select{padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;background:#fff;color:var(--text);outline:none;transition:all .2s;}
.filter-field input:focus,.filter-field select:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.08);}
.btn-reset{padding:10px 18px;background:var(--text2);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:20px;display:flex;align-items:center;gap:6px;}
.btn-reset:hover{background:var(--text);transform:translateY(-1px);}

.messages-list{display:flex;flex-direction:column;gap:16px;}
.msg-item{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
.msg-item:hover{box-shadow:0 6px 20px rgba(0,0,0,0.06);border-color:var(--o4);}
.msg-item.deleted{background:#fefce8;border-color:#fde047;}

.msg-top{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border);}
.msg-conversation{display:flex;align-items:center;gap:16px;flex:1;}

.msg-user{display:flex;align-items:center;gap:12px;}
.msg-user-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0;}
.msg-user-placeholder{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o6));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.1rem;flex-shrink:0;}
.msg-user-info{display:flex;flex-direction:column;gap:2px;}
.msg-user-name{font-size:.95rem;font-weight:700;color:var(--text);line-height:1.2;}
.msg-user-email{font-size:.78rem;color:var(--text3);}

.msg-arrow{font-size:1.6rem;color:var(--o5);flex-shrink:0;}
.msg-arrow.group{color:#a855f7;}

.msg-meta-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;}
.msg-id-badge{font-size:.75rem;font-weight:800;color:var(--text2);background:var(--bg);padding:5px 10px;border-radius:6px;}
.msg-type-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:.75rem;font-weight:700;}
.msg-type-badge.direct{background:#dbeafe;color:#1e40af;}
.msg-type-badge.group{background:#fce7f3;color:#be185d;}
.msg-time{font-size:.78rem;color:var(--text3);display:flex;align-items:center;gap:4px;}

.msg-reply-box{background:#eff6ff;border-left:3px solid #3b82f6;padding:12px 14px;border-radius:6px;margin-bottom:14px;font-size:.85rem;color:var(--text2);}
.msg-reply-box strong{color:var(--text);}

.msg-content{font-size:1rem;line-height:1.7;color:var(--text);background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:16px;}

.msg-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.msg-badges{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.msg-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:7px;font-size:.8rem;font-weight:600;}
.msg-badge.attachment{background:#fff7ed;color:var(--o6);border:1px solid #fed7aa;}
.msg-badge.reactions{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
.msg-badge.status-active{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.msg-badge.status-deleted{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}

.btn-view-details{padding:10px 18px;background:linear-gradient(135deg,var(--o5),var(--o6));color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;}
.btn-view-details:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(249,115,22,0.35);}

.modal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);backdrop-filter:blur(6px);animation:fadeIn .25s;}
.modal-content{background:#fff;margin:2% auto;padding:0;border-radius:16px;max-width:750px;max-height:90vh;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,0.25);animation:slideUp .25s;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
@keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.modal-header{background:linear-gradient(135deg,var(--o5),var(--o6));color:#fff;padding:24px 28px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-size:1.3rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:1.5rem;width:40px;height:40px;border-radius:8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;}
.modal-close:hover{background:rgba(255,255,255,0.25);}
.modal-body{padding:28px;max-height:calc(90vh - 100px);overflow-y:auto;}
.modal-section{margin-bottom:24px;padding:18px;background:var(--bg);border-radius:10px;border:1px solid var(--border);}
.modal-section h4{font-size:.82rem;font-weight:800;color:var(--text2);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:8px;}
.modal-section-content{font-size:.95rem;color:var(--text);line-height:1.7;}

.no-messages{text-align:center;padding:80px 20px;background:#fff;border-radius:12px;border:1px solid var(--border);}
.no-messages i{font-size:3.5rem;color:var(--text3);opacity:.4;margin-bottom:16px;}
.no-messages p{font-size:1rem;color:var(--text2);}

.group-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(139,92,246,0.1);color:#7c3aed;padding:4px 10px;border-radius:5px;font-size:.78rem;font-weight:700;}

@media(max-width:1200px){
    .msg-stats-row{grid-template-columns:repeat(2,1fr);}
    .filters-grid{grid-template-columns:1fr;gap:16px;}
    .btn-reset{margin-top:0;}
}
@media(max-width:768px){
    .msg-stats-row{grid-template-columns:1fr;}
    .msg-conversation{flex-direction:column;align-items:flex-start;}
    .msg-arrow{display:none;}
}
</style>

<div class="messages-header">
    <h2><i class="fas fa-comments"></i> Message Center</h2>
    <p>Monitor and manage all conversations across your platform</p>
</div>

<div class="msg-stats-row">
    <div class="msg-stat-box">
        <div class="msb-top">
            <div class="msb-icon orange"><i class="fas fa-envelope"></i></div>
        </div>
        <div class="msb-value"><?php echo $totalMessages; ?></div>
        <div class="msb-label">Total Messages</div>
    </div>
    <div class="msg-stat-box">
        <div class="msb-top">
            <div class="msb-icon blue"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="msb-value"><?php echo $activeMessages; ?></div>
        <div class="msb-label">Active Messages</div>
    </div>
    <div class="msg-stat-box">
        <div class="msb-top">
            <div class="msb-icon green"><i class="fas fa-paperclip"></i></div>
        </div>
        <div class="msb-value"><?php echo $messagesWithAttachments; ?></div>
        <div class="msb-label">With Attachments</div>
    </div>
    <div class="msg-stat-box">
        <div class="msb-top">
            <div class="msb-icon red"><i class="fas fa-trash"></i></div>
        </div>
        <div class="msb-value"><?php echo $deletedMessages; ?></div>
        <div class="msb-label">Deleted</div>
    </div>
</div>

<div class="filters-bar">
    <div class="filters-grid">
        <div class="filter-field">
            <label>Search</label>
            <input type="text" id="searchMessage" placeholder="Search messages, names...">
        </div>
        <div class="filter-field">
            <label>Room</label>
            <select id="filterRoom">
                <option value="">All Rooms</option>
                <?php foreach ($rooms as $room): ?>
                <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label>Status</label>
            <select id="filterStatus">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="deleted">Deleted</option>
            </select>
        </div>
        <div class="filter-field">
            <label>Type</label>
            <select id="filterType">
                <option value="">All</option>
                <option value="direct">Direct</option>
                <option value="group">Group</option>
            </select>
        </div>
        <button class="btn-reset" onclick="resetFilters()">
            <i class="fas fa-redo"></i> Reset
        </button>
    </div>
</div>

<div class="messages-list">
    <?php if (empty($allMessages)): ?>
    <div class="no-messages">
        <i class="fas fa-inbox"></i>
        <p>No messages found</p>
    </div>
    <?php else: ?>
        <?php foreach ($allMessages as $msg): ?>
        <div class="msg-item <?php echo $msg['is_deleted'] ? 'deleted' : ''; ?>" 
             data-room-id="<?php echo $msg['room_id']; ?>"
             data-status="<?php echo $msg['is_deleted'] ? 'deleted' : 'active'; ?>"
             data-type="<?php echo $msg['room_type']; ?>"
             data-message="<?php echo htmlspecialchars(strtolower($msg['message'])); ?>"
             data-sender="<?php echo htmlspecialchars(strtolower($msg['sender_name'] ?? '')); ?>"
             data-recipient="<?php echo htmlspecialchars(strtolower($msg['recipient_name'] ?? '')); ?>">
            
            <div class="msg-top">
                <div class="msg-conversation">
                    <!-- Sender -->
                    <div class="msg-user">
                        <?php if (!empty($msg['sender_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['sender_photo']); ?>" class="msg-user-avatar">
                        <?php else: ?>
                        <div class="msg-user-placeholder">
                            <?php echo strtoupper(substr($msg['sender_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="msg-user-info">
                            <div class="msg-user-name"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?></div>
                            <div class="msg-user-email"><?php echo htmlspecialchars($msg['sender_email'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Arrow -->
                    <i class="fas fa-arrow-right msg-arrow <?php echo $msg['room_type']; ?>"></i>
                    
                    <!-- Recipient/Group -->
                    <?php if ($msg['room_type'] == 'direct'): ?>
                    <div class="msg-user">
                        <?php if (!empty($msg['recipient_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['recipient_photo']); ?>" class="msg-user-avatar">
                        <?php else: ?>
                        <div class="msg-user-placeholder">
                            <?php echo strtoupper(substr($msg['recipient_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="msg-user-info">
                            <div class="msg-user-name"><?php echo htmlspecialchars($msg['recipient_name'] ?? 'Unknown'); ?></div>
                            <div class="msg-user-email"><?php echo htmlspecialchars($msg['recipient_email'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="msg-user">
                        <div class="msg-user-placeholder" style="background:linear-gradient(135deg,#a855f7,#7c3aed);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="msg-user-info">
                            <div class="msg-user-name"><?php echo htmlspecialchars($msg['room_name']); ?></div>
                            <div class="msg-user-email">
                                <span class="group-badge">
                                    <i class="fas fa-users"></i> <?php echo $msg['group_member_count'] ?? 0; ?> members
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="msg-meta-right">
                    <span class="msg-id-badge">#<?php echo $msg['id']; ?></span>
                    <span class="msg-type-badge <?php echo $msg['room_type']; ?>">
                        <i class="fas fa-<?php echo $msg['room_type'] == 'direct' ? 'user' : 'users'; ?>"></i>
                        <?php echo ucfirst($msg['room_type']); ?>
                    </span>
                    <span class="msg-time">
                        <i class="fas fa-clock"></i> <?php echo date('M d, H:i', strtotime($msg['created_at'])); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($msg['reply_to_id']): ?>
            <div class="msg-reply-box">
                <i class="fas fa-reply"></i>
                <strong>Reply to <?php echo htmlspecialchars($msg['replied_to_person'] ?? 'someone'); ?>:</strong>
                "<?php echo htmlspecialchars(substr($msg['replied_message'] ?? '', 0, 80)) . (strlen($msg['replied_message'] ?? '') > 80 ? '...' : ''); ?>"
            </div>
            <?php endif; ?>
            
            <div class="msg-content">
                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
            </div>
            
            <div class="msg-footer">
                <div class="msg-badges">
                    <?php if (!empty($msg['attachment_path'])): ?>
                    <span class="msg-badge attachment">
                        <i class="fas fa-<?php echo $msg['attachment_type'] == 'image' ? 'image' : 'file'; ?>"></i>
                        <?php echo htmlspecialchars($msg['attachment_name'] ?? 'Attachment'); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($msg['reaction_count'] > 0): ?>
                    <span class="msg-badge reactions">
                        <i class="fas fa-heart"></i>
                        <?php echo $msg['reaction_count']; ?>
                    </span>
                    <?php endif; ?>
                    
                    <span class="msg-badge <?php echo $msg['is_deleted'] ? 'status-deleted' : 'status-active'; ?>">
                        <i class="fas fa-<?php echo $msg['is_deleted'] ? 'trash' : 'check-circle'; ?>"></i>
                        <?php echo $msg['is_deleted'] ? 'Deleted' : 'Active'; ?>
                    </span>
                </div>
                
                <button class="btn-view-details" onclick='viewMessage(<?php echo json_encode($msg); ?>)'>
                    <i class="fas fa-eye"></i> View Details
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="messageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-envelope-open-text"></i> Message Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="messageModalBody"></div>
    </div>
</div>

<script>
function viewMessage(msg) {
    const modal = document.getElementById('messageModal');
    const body = document.getElementById('messageModalBody');
    
    let participantsHtml = '';
    if (msg.room_type === 'direct') {
        participantsHtml = `
            <div style="display:flex;align-items:center;justify-content:center;gap:24px;margin-bottom:12px;">
                <div style="text-align:center;">
                    <strong style="display:block;margin-bottom:4px;color:var(--text2);font-size:.75rem;text-transform:uppercase;">From</strong>
                    <div style="font-size:1.05rem;font-weight:700;color:var(--text);">${msg.sender_name || 'Unknown'}</div>
                    <div style="font-size:.8rem;color:var(--text3);">${msg.sender_email || 'N/A'}</div>
                </div>
                <i class="fas fa-arrow-right" style="font-size:1.8rem;color:var(--o5);"></i>
                <div style="text-align:center;">
                    <strong style="display:block;margin-bottom:4px;color:var(--text2);font-size:.75rem;text-transform:uppercase;">To</strong>
                    <div style="font-size:1.05rem;font-weight:700;color:var(--text);">${msg.recipient_name || 'Unknown'}</div>
                    <div style="font-size:.8rem;color:var(--text3);">${msg.recipient_email || 'N/A'}</div>
                </div>
            </div>
        `;
    } else {
        participantsHtml = `
            <div style="margin-bottom:12px;">
                <div style="margin-bottom:8px;">
                    <strong style="color:var(--text2);font-size:.85rem;">Sender:</strong>
                    <span style="font-size:.95rem;color:var(--text);font-weight:600;"> ${msg.sender_name || 'Unknown'}</span>
                    <span style="font-size:.8rem;color:var(--text3);"> (${msg.sender_email || 'N/A'})</span>
                </div>
                <div>
                    <strong style="color:var(--text2);font-size:.85rem;">Group:</strong>
                    <span style="font-size:.95rem;color:var(--text);font-weight:600;"> ${msg.room_name}</span>
                    <span class="group-badge" style="margin-left:8px;">
                        <i class="fas fa-users"></i> ${msg.group_member_count || 0} members
                    </span>
                </div>
            </div>
        `;
    }
    
    let attachmentHtml = '';
    if (msg.attachment_path) {
        if (msg.attachment_type === 'image') {
            attachmentHtml = `
                <div class="modal-section">
                    <h4><i class="fas fa-image"></i> Image Attachment</h4>
                    <div class="modal-section-content">
                        <img src="${msg.attachment_path}" alt="${msg.attachment_name || 'Image'}" style="max-width:100%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                    </div>
                </div>
            `;
        } else {
            attachmentHtml = `
                <div class="modal-section">
                    <h4><i class="fas fa-file"></i> File Attachment</h4>
                    <div class="modal-section-content">
                        <div style="display:flex;align-items:center;gap:12px;padding:14px;background:#fff;border-radius:8px;border:1px solid var(--border);">
                            <i class="fas fa-file" style="font-size:1.8rem;color:var(--o5);"></i>
                            <div>
                                <div style="font-weight:700;margin-bottom:2px;">${msg.attachment_name || 'File'}</div>
                                <div style="font-size:.8rem;color:var(--text3);">Type: ${msg.attachment_type}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    }
    
    body.innerHTML = `
        <div class="modal-section">
            <h4><i class="fas fa-users"></i> Conversation</h4>
            <div class="modal-section-content">
                ${participantsHtml}
                <div style="padding-top:12px;border-top:1px solid var(--border);margin-top:12px;">
                    <strong style="font-size:.85rem;color:var(--text2);">Room:</strong> 
                    <span style="font-size:.95rem;color:var(--text);font-weight:600;">${msg.room_name}</span>
                    <span class="msg-type-badge ${msg.room_type}" style="margin-left:10px;">
                        <i class="fas fa-${msg.room_type === 'direct' ? 'user' : 'users'}"></i>
                        ${msg.room_type.charAt(0).toUpperCase() + msg.room_type.slice(1)}
                    </span>
                </div>
            </div>
        </div>
        
        ${msg.reply_to_id ? `
        <div class="modal-section">
            <h4><i class="fas fa-reply"></i> Reply Context</h4>
            <div class="modal-section-content">
                Replying to <strong>${msg.replied_to_person || 'someone'}</strong>
                <div style="background:#eff6ff;padding:12px;border-radius:6px;margin-top:10px;font-style:italic;border-left:3px solid #3b82f6;">
                    "${msg.replied_message || 'Message #' + msg.reply_to_id}"
                </div>
            </div>
        </div>
        ` : ''}
        
        <div class="modal-section">
            <h4><i class="fas fa-message"></i> Message</h4>
            <div class="modal-section-content" style="background:#fff;padding:16px;border-radius:8px;border:1px solid var(--border);white-space:pre-wrap;line-height:1.7;">
${msg.message}
            </div>
        </div>
        
        ${attachmentHtml}
        
        <div class="modal-section">
            <h4><i class="fas fa-info-circle"></i> Metadata</h4>
            <div class="modal-section-content">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div><strong>ID:</strong> #${msg.id}</div>
                    <div>
                        <strong>Status:</strong> 
                        <span class="msg-badge ${msg.is_deleted == 1 ? 'status-deleted' : 'status-active'}">
                            <i class="fas fa-${msg.is_deleted == 1 ? 'trash' : 'check-circle'}"></i>
                            ${msg.is_deleted == 1 ? 'Deleted' : 'Active'}
                        </span>
                    </div>
                    <div><strong>Reactions:</strong> ${msg.reaction_count || 0}</div>
                    <div><strong>Sent:</strong> ${new Date(msg.created_at).toLocaleString()}</div>
                </div>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('messageModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('messageModal');
    if (event.target === modal) closeModal();
}

const searchInput = document.getElementById('searchMessage');
const roomFilter = document.getElementById('filterRoom');
const statusFilter = document.getElementById('filterStatus');
const typeFilter = document.getElementById('filterType');

function filterMessages() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedRoom = roomFilter.value;
    const selectedStatus = statusFilter.value;
    const selectedType = typeFilter.value;
    
    document.querySelectorAll('.msg-item').forEach(item => {
        const message = item.getAttribute('data-message');
        const sender = item.getAttribute('data-sender');
        const recipient = item.getAttribute('data-recipient');
        const roomId = item.getAttribute('data-room-id');
        const status = item.getAttribute('data-status');
        const type = item.getAttribute('data-type');
        
        const matchesSearch = !searchTerm || message.includes(searchTerm) || sender.includes(searchTerm) || recipient.includes(searchTerm);
        const matchesRoom = !selectedRoom || roomId === selectedRoom;
        const matchesStatus = !selectedStatus || status === selectedStatus;
        const matchesType = !selectedType || type === selectedType;
        
        item.style.display = (matchesSearch && matchesRoom && matchesStatus && matchesType) ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterMessages);
roomFilter.addEventListener('change', filterMessages);
statusFilter.addEventListener('change', filterMessages);
typeFilter.addEventListener('change', filterMessages);

function resetFilters() {
    searchInput.value = '';
    roomFilter.value = '';
    statusFilter.value = '';
    typeFilter.value = '';
    filterMessages();
}
</script>