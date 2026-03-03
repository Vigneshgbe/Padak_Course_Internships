<?php
// Admin Messages Management Module - Improved Version
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
.messages-header{background:linear-gradient(135deg,var(--o5),var(--o4));padding:24px;border-radius:12px;color:#fff;margin-bottom:24px;box-shadow:0 4px 14px rgba(249,115,22,0.25);}
.messages-header h2{font-size:1.5rem;font-weight:800;margin-bottom:8px;display:flex;align-items:center;gap:10px;}
.messages-header p{font-size:.875rem;opacity:.9;}
.msg-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:24px;}
.msg-stat-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.05);transition:all .2s;}
.msg-stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,0.1);}
.msc-label{font-size:.75rem;color:var(--text3);font-weight:600;margin-bottom:6px;}
.msc-value{font-size:1.75rem;font-weight:900;color:var(--text);}
.msc-icon{font-size:1.1rem;color:var(--o5);margin-right:6px;}
.messages-filters{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px;margin-bottom:20px;}
.filters-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
.filter-input{padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;min-width:180px;outline:none;transition:all .2s;}
.filter-input:focus{border-color:var(--o5);box-shadow:0 0 0 3px rgba(249,115,22,0.1);}
.btn-filter{padding:8px 16px;background:var(--o5);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
.btn-filter:hover{background:var(--o6);transform:translateY(-1px);}
.btn-reset{background:var(--text3);color:#fff;}
.btn-reset:hover{background:var(--text2);}
.messages-container{display:flex;flex-direction:column;gap:12px;}
.message-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);transition:all .2s;}
.message-card:hover{box-shadow:0 6px 16px rgba(0,0,0,0.08);border-color:var(--o5);}
.message-card.deleted{background:#fef2f2;border-color:#fecaca;}
.msg-card-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;padding-bottom:14px;border-bottom:2px solid var(--border);}
.msg-participants{display:flex;align-items:center;gap:12px;flex:1;}
.msg-person{display:flex;align-items:center;gap:8px;}
.msg-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
.msg-avatar-placeholder{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--o4),var(--o5));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;}
.msg-person-info h4{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.msg-person-info p{font-size:.72rem;color:var(--text3);}
.msg-arrow{font-size:1.5rem;color:var(--o5);margin:0 8px;}
.msg-arrow.group{color:var(--purple);}
.msg-meta{display:flex;flex-direction:column;align-items:flex-end;gap:6px;}
.msg-id{font-size:.75rem;font-weight:700;color:var(--text3);background:var(--bg);padding:4px 8px;border-radius:6px;}
.msg-timestamp{font-size:.72rem;color:var(--text3);}
.msg-room-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;}
.msg-room-badge.direct{background:#dbeafe;color:#1e40af;}
.msg-room-badge.group{background:#fce7f3;color:#be185d;}
.msg-card-body{margin-bottom:14px;}
.msg-text{font-size:.95rem;line-height:1.7;color:var(--text);background:var(--bg);padding:14px;border-radius:8px;border-left:3px solid var(--o5);word-break:break-word;}
.msg-reply-context{background:#eff6ff;border-left:3px solid var(--blue);padding:10px 12px;border-radius:6px;margin-bottom:10px;font-size:.82rem;color:var(--text2);}
.msg-reply-context i{color:var(--blue);margin-right:4px;}
.msg-card-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.msg-indicators{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.msg-indicator{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;font-size:.75rem;font-weight:600;}
.msg-indicator.attachment{background:var(--o1);color:var(--o6);}
.msg-indicator.reactions{background:#f0fdf4;color:#166534;}
.msg-indicator.status-active{background:#dcfce7;color:#166534;}
.msg-indicator.status-deleted{background:#fee2e2;color:#991b1b;}
.msg-actions{display:flex;gap:8px;}
.btn-action{padding:8px 14px;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;}
.btn-view{background:#dbeafe;color:#1e40af;}
.btn-view:hover{background:#bfdbfe;transform:translateY(-1px);}
.modal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);animation:fadeIn .3s;}
.modal-content{background:var(--card);margin:3% auto;padding:0;border-radius:16px;max-width:800px;max-height:85vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideUp .3s;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
@keyframes slideUp{from{transform:translateY(30px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.modal-header{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-size:1.2rem;font-weight:700;}
.modal-close{background:rgba(255,255,255,0.2);border:none;color:#fff;font-size:1.5rem;width:36px;height:36px;border-radius:8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;}
.modal-close:hover{background:rgba(255,255,255,0.3);}
.modal-body{padding:24px;max-height:calc(85vh - 140px);overflow-y:auto;}
.msg-detail-section{margin-bottom:20px;padding:16px;background:var(--bg);border-radius:10px;border:1px solid var(--border);}
.msg-detail-section h4{font-size:.85rem;font-weight:700;color:var(--text3);margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;}
.msg-detail-content{font-size:.95rem;color:var(--text);line-height:1.6;}
.msg-attachment-preview{margin-top:10px;}
.msg-attachment-preview img{max-width:100%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.msg-attachment-file{display:flex;align-items:center;gap:10px;padding:12px;background:var(--card);border-radius:8px;border:1px solid var(--border);}
.msg-attachment-file i{font-size:1.5rem;color:var(--o5);}
.no-messages{text-align:center;padding:60px 20px;color:var(--text3);}
.no-messages i{font-size:3rem;margin-bottom:16px;color:var(--text3);opacity:.5;}
.no-messages p{font-size:.95rem;}
.room-stats{margin-bottom:24px;}
.room-stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;}
.room-stat-item{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px;display:flex;align-items:center;justify-content:space-between;transition:all .2s;}
.room-stat-item:hover{box-shadow:0 4px 12px rgba(0,0,0,0.08);}
.room-stat-info h4{font-size:.85rem;font-weight:600;color:var(--text);margin-bottom:4px;}
.room-stat-info p{font-size:.7rem;color:var(--text3);}
.room-stat-count{font-size:1.3rem;font-weight:900;color:var(--o5);}
.group-indicator{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;color:var(--purple);background:rgba(139,92,246,0.1);padding:3px 8px;border-radius:4px;font-weight:600;}
</style>

<div class="messages-header">
    <h2><i class="fas fa-comments"></i> Messages Management</h2>
    <p>View and manage all chat conversations with clear sender-receiver context</p>
</div>

<div class="msg-stats-grid">
    <div class="msg-stat-card">
        <div class="msc-label"><i class="fas fa-envelope msc-icon"></i>Total Messages</div>
        <div class="msc-value"><?php echo $totalMessages; ?></div>
    </div>
    <div class="msg-stat-card">
        <div class="msc-label"><i class="fas fa-check-circle msc-icon"></i>Active Messages</div>
        <div class="msc-value"><?php echo $activeMessages; ?></div>
    </div>
    <div class="msg-stat-card">
        <div class="msc-label"><i class="fas fa-trash msc-icon"></i>Deleted Messages</div>
        <div class="msc-value"><?php echo $deletedMessages; ?></div>
    </div>
    <div class="msg-stat-card">
        <div class="msc-label"><i class="fas fa-paperclip msc-icon"></i>With Attachments</div>
        <div class="msc-value"><?php echo $messagesWithAttachments; ?></div>
    </div>
</div>

<?php if (!empty($rooms)): ?>
<div class="room-stats">
    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:14px;color:var(--text);"><i class="fas fa-door-open" style="color:var(--o5);margin-right:8px;"></i>Active Chat Rooms</h3>
    <div class="room-stats-grid">
        <?php foreach ($rooms as $room): ?>
        <div class="room-stat-item">
            <div class="room-stat-info">
                <h4><?php echo htmlspecialchars($room['room_name']); ?></h4>
                <p><?php echo ucfirst($room['room_type']); ?> • <?php echo $room['member_count']; ?> members</p>
            </div>
            <div class="room-stat-count"><?php echo $room['message_count']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="messages-filters">
    <div class="filters-row">
        <input type="text" id="searchMessage" class="filter-input" placeholder="Search messages, senders, recipients...">
        <select id="filterRoom" class="filter-input">
            <option value="">All Rooms</option>
            <?php foreach ($rooms as $room): ?>
            <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterStatus" class="filter-input">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="deleted">Deleted</option>
        </select>
        <select id="filterType" class="filter-input">
            <option value="">All Types</option>
            <option value="direct">Direct Messages</option>
            <option value="group">Group Messages</option>
        </select>
        <button class="btn-filter btn-reset" onclick="resetFilters()"><i class="fas fa-redo"></i> Reset</button>
    </div>
</div>

<div class="messages-container">
    <?php if (empty($allMessages)): ?>
    <div class="no-messages">
        <i class="fas fa-inbox"></i>
        <p>No messages found in the system</p>
    </div>
    <?php else: ?>
        <?php foreach ($allMessages as $msg): ?>
        <div class="message-card <?php echo $msg['is_deleted'] ? 'deleted' : ''; ?>" 
             data-room-id="<?php echo $msg['room_id']; ?>"
             data-status="<?php echo $msg['is_deleted'] ? 'deleted' : 'active'; ?>"
             data-type="<?php echo $msg['room_type']; ?>"
             data-message="<?php echo htmlspecialchars(strtolower($msg['message'])); ?>"
             data-sender="<?php echo htmlspecialchars(strtolower($msg['sender_name'] ?? '')); ?>"
             data-recipient="<?php echo htmlspecialchars(strtolower($msg['recipient_name'] ?? '')); ?>">
            
            <div class="msg-card-header">
                <div class="msg-participants">
                    <!-- Sender -->
                    <div class="msg-person">
                        <?php if (!empty($msg['sender_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['sender_photo']); ?>" alt="Sender" class="msg-avatar">
                        <?php else: ?>
                        <div class="msg-avatar-placeholder">
                            <?php echo strtoupper(substr($msg['sender_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="msg-person-info">
                            <h4><?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?></h4>
                            <p><?php echo htmlspecialchars($msg['sender_email'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Arrow -->
                    <div class="msg-arrow <?php echo $msg['room_type']; ?>">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    
                    <!-- Recipient/Group -->
                    <?php if ($msg['room_type'] == 'direct'): ?>
                    <div class="msg-person">
                        <?php if (!empty($msg['recipient_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['recipient_photo']); ?>" alt="Recipient" class="msg-avatar">
                        <?php else: ?>
                        <div class="msg-avatar-placeholder">
                            <?php echo strtoupper(substr($msg['recipient_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="msg-person-info">
                            <h4><?php echo htmlspecialchars($msg['recipient_name'] ?? 'Unknown'); ?></h4>
                            <p><?php echo htmlspecialchars($msg['recipient_email'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="msg-person">
                        <div class="msg-avatar-placeholder" style="background:linear-gradient(135deg,var(--purple),#a855f7);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="msg-person-info">
                            <h4><?php echo htmlspecialchars($msg['room_name']); ?></h4>
                            <p>
                                <span class="group-indicator">
                                    <i class="fas fa-users"></i> <?php echo $msg['group_member_count'] ?? 0; ?> members
                                </span>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="msg-meta">
                    <span class="msg-id">#<?php echo $msg['id']; ?></span>
                    <span class="msg-room-badge <?php echo $msg['room_type']; ?>">
                        <i class="fas fa-<?php echo $msg['room_type'] == 'direct' ? 'user' : 'users'; ?>"></i>
                        <?php echo ucfirst($msg['room_type']); ?>
                    </span>
                    <span class="msg-timestamp">
                        <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="msg-card-body">
                <?php if ($msg['reply_to_id']): ?>
                <div class="msg-reply-context">
                    <i class="fas fa-reply"></i>
                    <strong>Replying to <?php echo htmlspecialchars($msg['replied_to_person'] ?? 'someone'); ?>:</strong>
                    "<?php echo htmlspecialchars(substr($msg['replied_message'] ?? '', 0, 60)) . (strlen($msg['replied_message'] ?? '') > 60 ? '...' : ''); ?>"
                </div>
                <?php endif; ?>
                
                <div class="msg-text">
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                </div>
            </div>
            
            <div class="msg-card-footer">
                <div class="msg-indicators">
                    <?php if (!empty($msg['attachment_path'])): ?>
                    <span class="msg-indicator attachment">
                        <i class="fas fa-<?php echo $msg['attachment_type'] == 'image' ? 'image' : 'file'; ?>"></i>
                        <?php echo htmlspecialchars($msg['attachment_name'] ?? 'Attachment'); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($msg['reaction_count'] > 0): ?>
                    <span class="msg-indicator reactions">
                        <i class="fas fa-heart"></i>
                        <?php echo $msg['reaction_count']; ?> reaction<?php echo $msg['reaction_count'] != 1 ? 's' : ''; ?>
                    </span>
                    <?php endif; ?>
                    
                    <span class="msg-indicator <?php echo $msg['is_deleted'] ? 'status-deleted' : 'status-active'; ?>">
                        <i class="fas fa-<?php echo $msg['is_deleted'] ? 'trash' : 'check-circle'; ?>"></i>
                        <?php echo $msg['is_deleted'] ? 'Deleted' : 'Active'; ?>
                    </span>
                </div>
                
                <div class="msg-actions">
                    <button class="btn-action btn-view" onclick='viewMessage(<?php echo json_encode($msg); ?>)'>
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Message Detail Modal -->
<div id="messageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-envelope-open-text"></i> Message Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="messageModalBody">
            <!-- Content will be inserted by JavaScript -->
        </div>
    </div>
</div>

<script>
function viewMessage(msg) {
    const modal = document.getElementById('messageModal');
    const body = document.getElementById('messageModalBody');
    
    let participantsHtml = '';
    if (msg.room_type === 'direct') {
        participantsHtml = `
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:10px;">
                <div style="flex:1;text-align:center;">
                    <strong>From:</strong><br>
                    ${msg.sender_name || 'Unknown'}<br>
                    <small style="color:var(--text3);">${msg.sender_email || 'N/A'}</small>
                </div>
                <i class="fas fa-arrow-right" style="font-size:1.5rem;color:var(--o5);"></i>
                <div style="flex:1;text-align:center;">
                    <strong>To:</strong><br>
                    ${msg.recipient_name || 'Unknown'}<br>
                    <small style="color:var(--text3);">${msg.recipient_email || 'N/A'}</small>
                </div>
            </div>
        `;
    } else {
        participantsHtml = `
            <div style="margin-bottom:10px;">
                <strong>Sender:</strong> ${msg.sender_name || 'Unknown'} (${msg.sender_email || 'N/A'})<br>
                <strong>Group:</strong> ${msg.room_name} 
                <span style="background:rgba(139,92,246,0.1);color:var(--purple);padding:2px 8px;border-radius:4px;font-size:.75rem;margin-left:8px;">
                    <i class="fas fa-users"></i> ${msg.group_member_count || 0} members
                </span>
            </div>
        `;
    }
    
    let attachmentHtml = '';
    if (msg.attachment_path) {
        if (msg.attachment_type === 'image') {
            attachmentHtml = `
                <div class="msg-detail-section">
                    <h4><i class="fas fa-image"></i> Image Attachment</h4>
                    <div class="msg-attachment-preview">
                        <img src="${msg.attachment_path}" alt="${msg.attachment_name || 'Attachment'}">
                    </div>
                </div>
            `;
        } else {
            attachmentHtml = `
                <div class="msg-detail-section">
                    <h4><i class="fas fa-file"></i> File Attachment</h4>
                    <div class="msg-attachment-file">
                        <i class="fas fa-file"></i>
                        <div>
                            <strong>${msg.attachment_name || 'File'}</strong>
                            <div style="font-size:.75rem;color:var(--text3);">Type: ${msg.attachment_type}</div>
                        </div>
                    </div>
                </div>
            `;
        }
    }
    
    body.innerHTML = `
        <div class="msg-detail-section">
            <h4><i class="fas fa-users"></i> Conversation Details</h4>
            <div class="msg-detail-content">
                ${participantsHtml}
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
                    <strong>Room:</strong> ${msg.room_name}<br>
                    <strong>Type:</strong> <span class="msg-room-badge ${msg.room_type}">
                        <i class="fas fa-${msg.room_type === 'direct' ? 'user' : 'users'}"></i>
                        ${msg.room_type.charAt(0).toUpperCase() + msg.room_type.slice(1)}
                    </span>
                </div>
            </div>
        </div>
        
        ${msg.reply_to_id ? `
        <div class="msg-detail-section">
            <h4><i class="fas fa-reply"></i> Reply Context</h4>
            <div class="msg-detail-content">
                Replying to <strong>${msg.replied_to_person || 'someone'}</strong>:<br>
                <div style="background:#eff6ff;padding:10px;border-radius:6px;margin-top:8px;font-style:italic;">
                    "${msg.replied_message || 'Message #' + msg.reply_to_id}"
                </div>
            </div>
        </div>
        ` : ''}
        
        <div class="msg-detail-section">
            <h4><i class="fas fa-message"></i> Message Content</h4>
            <div class="msg-detail-content" style="white-space:pre-wrap;background:var(--card);padding:14px;border-radius:8px;border:1px solid var(--border);">
                ${msg.message}
            </div>
        </div>
        
        ${attachmentHtml}
        
        <div class="msg-detail-section">
            <h4><i class="fas fa-info-circle"></i> Metadata</h4>
            <div class="msg-detail-content">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <strong>Message ID:</strong> #${msg.id}
                    </div>
                    <div>
                        <strong>Status:</strong> 
                        <span class="msg-indicator ${msg.is_deleted == 1 ? 'status-deleted' : 'status-active'}">
                            <i class="fas fa-${msg.is_deleted == 1 ? 'trash' : 'check-circle'}"></i>
                            ${msg.is_deleted == 1 ? 'Deleted' : 'Active'}
                        </span>
                    </div>
                    <div>
                        <strong>Reactions:</strong> ${msg.reaction_count || 0}
                    </div>
                    <div>
                        <strong>Sent:</strong> ${new Date(msg.created_at).toLocaleString()}
                    </div>
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
    if (event.target === modal) {
        closeModal();
    }
}

// Filter functionality
const searchInput = document.getElementById('searchMessage');
const roomFilter = document.getElementById('filterRoom');
const statusFilter = document.getElementById('filterStatus');
const typeFilter = document.getElementById('filterType');

function filterMessages() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedRoom = roomFilter.value;
    const selectedStatus = statusFilter.value;
    const selectedType = typeFilter.value;
    
    const cards = document.querySelectorAll('.message-card');
    
    cards.forEach(card => {
        const message = card.getAttribute('data-message');
        const sender = card.getAttribute('data-sender');
        const recipient = card.getAttribute('data-recipient');
        const roomId = card.getAttribute('data-room-id');
        const status = card.getAttribute('data-status');
        const type = card.getAttribute('data-type');
        
        const matchesSearch = !searchTerm || 
            message.includes(searchTerm) || 
            sender.includes(searchTerm) || 
            recipient.includes(searchTerm);
        const matchesRoom = !selectedRoom || roomId === selectedRoom;
        const matchesStatus = !selectedStatus || status === selectedStatus;
        const matchesType = !selectedType || type === selectedType;
        
        if (matchesSearch && matchesRoom && matchesStatus && matchesType) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
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