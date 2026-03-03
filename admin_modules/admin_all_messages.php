<?php
// Admin All Messages Module

if (!isset($db)) {
    die('Database connection required');
}

// Fetch all messages with student and room details
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
        s.full_name as sender_name,
        s.email as sender_email,
        s.profile_photo as sender_photo,
        (SELECT COUNT(*) FROM message_reactions WHERE message_id = cm.id) as reaction_count,
        (SELECT message FROM chat_messages WHERE id = cm.reply_to_id) as replied_message
    FROM chat_messages cm
    LEFT JOIN chat_rooms cr ON cm.room_id = cr.id
    LEFT JOIN internship_students s ON cm.sender_id = s.id
    ORDER BY cm.created_at DESC
";

$messagesResult = $db->query($messagesQuery);
$allMessages = [];
while ($row = $messagesResult->fetch_assoc()) {
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
.messages-table-container{background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.messages-table{width:100%;border-collapse:collapse;}
.messages-table thead{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);}
.messages-table th{padding:14px 16px;text-align:left;font-size:.8rem;font-weight:700;color:var(--text);border-bottom:2px solid var(--border);}
.messages-table td{padding:12px 16px;font-size:.85rem;border-bottom:1px solid var(--border);color:var(--text2);}
.messages-table tbody tr{transition:all .2s;}
.messages-table tbody tr:hover{background:var(--bg);}
.msg-sender{display:flex;align-items:center;gap:10px;}
.msg-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
.msg-avatar-placeholder{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--o4),var(--o5));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;}
.msg-sender-info{flex:1;}
.msg-sender-name{font-weight:600;color:var(--text);font-size:.85rem;}
.msg-sender-email{font-size:.7rem;color:var(--text3);}
.msg-content{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.msg-content-full{max-width:none;white-space:normal;word-break:break-word;}
.msg-room{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;font-size:.75rem;font-weight:600;}
.msg-room.direct{background:#dbeafe;color:#1e40af;}
.msg-room.group{background:#fce7f3;color:#be185d;}
.msg-status{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:6px;font-size:.7rem;font-weight:600;}
.msg-status.active{background:#dcfce7;color:#166534;}
.msg-status.deleted{background:#fee2e2;color:#991b1b;}
.msg-attachment{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;background:var(--o1);color:var(--o6);border-radius:6px;font-size:.7rem;font-weight:600;}
.msg-date{font-size:.75rem;color:var(--text3);}
.msg-actions{display:flex;gap:6px;}
.btn-action{padding:6px 10px;border:none;border-radius:6px;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:4px;}
.btn-view{background:#dbeafe;color:#1e40af;}
.btn-view:hover{background:#bfdbfe;}
.btn-delete{background:#fee2e2;color:#991b1b;}
.btn-delete:hover{background:#fecaca;}
.modal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);animation:fadeIn .3s;}
.modal-content{background:var(--card);margin:3% auto;padding:0;border-radius:16px;max-width:700px;max-height:85vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideUp .3s;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
@keyframes slideUp{from{transform:translateY(30px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.modal-header{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-size:1.2rem;font-weight:700;}
.modal-close{background:rgba(255,255,255,0.2);border:none;color:#fff;font-size:1.5rem;width:36px;height:36px;border-radius:8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;}
.modal-close:hover{background:rgba(255,255,255,0.3);}
.modal-body{padding:24px;max-height:calc(85vh - 140px);overflow-y:auto;}
.msg-detail-row{margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border);}
.msg-detail-row:last-child{border-bottom:none;}
.msg-detail-label{font-size:.75rem;font-weight:700;color:var(--text3);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
.msg-detail-value{font-size:.9rem;color:var(--text);}
.msg-detail-message{background:var(--bg);padding:14px;border-radius:8px;border-left:3px solid var(--o5);font-size:.9rem;line-height:1.6;}
.msg-attachment-preview{margin-top:10px;}
.msg-attachment-preview img{max-width:100%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.msg-attachment-file{display:flex;align-items:center;gap:10px;padding:12px;background:var(--bg);border-radius:8px;border:1px solid var(--border);}
.msg-attachment-file i{font-size:1.5rem;color:var(--o5);}
.no-messages{text-align:center;padding:60px 20px;color:var(--text3);}
.no-messages i{font-size:3rem;margin-bottom:16px;color:var(--text3);opacity:.5;}
.no-messages p{font-size:.95rem;}
.room-stats{margin-bottom:24px;}
.room-stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;}
.room-stat-item{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px;display:flex;align-items:center;justify-content:space-between;}
.room-stat-info h4{font-size:.85rem;font-weight:600;color:var(--text);margin-bottom:4px;}
.room-stat-info p{font-size:.7rem;color:var(--text3);}
.room-stat-count{font-size:1.3rem;font-weight:900;color:var(--o5);}
.reactions-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
.reaction-item{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;background:var(--bg);border-radius:6px;font-size:.75rem;}
</style>

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
        <input type="text" id="searchMessage" class="filter-input" placeholder="Search messages...">
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
        <select id="filterAttachment" class="filter-input">
            <option value="">All Messages</option>
            <option value="with">With Attachments</option>
            <option value="without">Without Attachments</option>
        </select>
        <button class="btn-filter btn-reset" onclick="resetFilters()"><i class="fas fa-redo"></i> Reset</button>
    </div>
</div>

<div class="messages-table-container">
    <?php if (empty($allMessages)): ?>
    <div class="no-messages">
        <i class="fas fa-inbox"></i>
        <p>No messages found in the system</p>
    </div>
    <?php else: ?>
    <table class="messages-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Sender</th>
                <th>Room</th>
                <th>Message</th>
                <th>Attachment</th>
                <th>Reactions</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="messagesTableBody">
            <?php foreach ($allMessages as $msg): ?>
            <tr class="message-row" 
                data-room-id="<?php echo $msg['room_id']; ?>"
                data-status="<?php echo $msg['is_deleted'] ? 'deleted' : 'active'; ?>"
                data-has-attachment="<?php echo !empty($msg['attachment_path']) ? 'with' : 'without'; ?>"
                data-message="<?php echo htmlspecialchars(strtolower($msg['message'])); ?>"
                data-sender="<?php echo htmlspecialchars(strtolower($msg['sender_name'])); ?>">
                <td style="font-weight:600;color:var(--text);">#<?php echo $msg['id']; ?></td>
                <td>
                    <div class="msg-sender">
                        <?php if (!empty($msg['sender_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($msg['sender_photo']); ?>" alt="Avatar" class="msg-avatar">
                        <?php else: ?>
                        <div class="msg-avatar-placeholder">
                            <?php echo strtoupper(substr($msg['sender_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="msg-sender-info">
                            <div class="msg-sender-name"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?></div>
                            <div class="msg-sender-email"><?php echo htmlspecialchars($msg['sender_email'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="msg-room <?php echo strtolower($msg['room_type']); ?>">
                        <i class="fas fa-<?php echo $msg['room_type'] == 'direct' ? 'user' : 'users'; ?>"></i>
                        <?php echo htmlspecialchars($msg['room_name']); ?>
                    </span>
                </td>
                <td>
                    <div class="msg-content" title="<?php echo htmlspecialchars($msg['message']); ?>">
                        <?php 
                        if ($msg['reply_to_id']) {
                            echo '<i class="fas fa-reply" style="color:var(--o5);margin-right:4px;"></i>';
                        }
                        echo htmlspecialchars(strlen($msg['message']) > 50 ? substr($msg['message'], 0, 50) . '...' : $msg['message']); 
                        ?>
                    </div>
                </td>
                <td>
                    <?php if (!empty($msg['attachment_path'])): ?>
                    <span class="msg-attachment">
                        <i class="fas fa-<?php echo $msg['attachment_type'] == 'image' ? 'image' : 'file'; ?>"></i>
                        <?php echo htmlspecialchars($msg['attachment_name'] ?? 'File'); ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:.75rem;">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-weight:700;color:var(--o5);">
                    <?php echo $msg['reaction_count'] > 0 ? $msg['reaction_count'] : '—'; ?>
                </td>
                <td>
                    <span class="msg-status <?php echo $msg['is_deleted'] ? 'deleted' : 'active'; ?>">
                        <i class="fas fa-<?php echo $msg['is_deleted'] ? 'trash' : 'check-circle'; ?>"></i>
                        <?php echo $msg['is_deleted'] ? 'Deleted' : 'Active'; ?>
                    </span>
                </td>
                <td class="msg-date"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></td>
                <td>
                    <div class="msg-actions">
                        <button class="btn-action btn-view" onclick="viewMessage(<?php echo htmlspecialchars(json_encode($msg)); ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
    
    let attachmentHtml = '';
    if (msg.attachment_path) {
        if (msg.attachment_type === 'image') {
            attachmentHtml = `
                <div class="msg-attachment-preview">
                    <img src="${msg.attachment_path}" alt="${msg.attachment_name || 'Attachment'}">
                </div>
            `;
        } else {
            attachmentHtml = `
                <div class="msg-attachment-file">
                    <i class="fas fa-file"></i>
                    <div>
                        <strong>${msg.attachment_name || 'File'}</strong>
                        <div style="font-size:.75rem;color:var(--text3);">${msg.attachment_type}</div>
                    </div>
                </div>
            `;
        }
    }
    
    body.innerHTML = `
        <div class="msg-detail-row">
            <div class="msg-detail-label">Message ID</div>
            <div class="msg-detail-value">#${msg.id}</div>
        </div>
        
        <div class="msg-detail-row">
            <div class="msg-detail-label">Sender</div>
            <div class="msg-detail-value">
                <strong>${msg.sender_name || 'Unknown'}</strong><br>
                <span style="font-size:.85rem;color:var(--text3);">${msg.sender_email || 'N/A'}</span>
            </div>
        </div>
        
        <div class="msg-detail-row">
            <div class="msg-detail-label">Chat Room</div>
            <div class="msg-detail-value">
                <span class="msg-room ${msg.room_type}">
                    <i class="fas fa-${msg.room_type === 'direct' ? 'user' : 'users'}"></i>
                    ${msg.room_name}
                </span>
            </div>
        </div>
        
        ${msg.reply_to_id ? `
        <div class="msg-detail-row">
            <div class="msg-detail-label">Reply To</div>
            <div class="msg-detail-value" style="font-style:italic;color:var(--text3);">
                <i class="fas fa-reply"></i> ${msg.replied_message || 'Message #' + msg.reply_to_id}
            </div>
        </div>
        ` : ''}
        
        <div class="msg-detail-row">
            <div class="msg-detail-label">Message Content</div>
            <div class="msg-detail-message">${msg.message}</div>
        </div>
        
        ${msg.attachment_path ? `
        <div class="msg-detail-row">
            <div class="msg-detail-label">Attachment</div>
            ${attachmentHtml}
        </div>
        ` : ''}
        
        <div class="msg-detail-row">
            <div class="msg-detail-label">Status</div>
            <div class="msg-detail-value">
                <span class="msg-status ${msg.is_deleted == 1 ? 'deleted' : 'active'}">
                    <i class="fas fa-${msg.is_deleted == 1 ? 'trash' : 'check-circle'}"></i>
                    ${msg.is_deleted == 1 ? 'Deleted' : 'Active'}
                </span>
            </div>
        </div>
        
        <div class="msg-detail-row">
            <div class="msg-detail-label">Reactions</div>
            <div class="msg-detail-value">
                <strong style="color:var(--o5);">${msg.reaction_count || 0}</strong> reaction(s)
            </div>
        </div>
        
        <div class="msg-detail-row">
            <div class="msg-detail-label">Created At</div>
            <div class="msg-detail-value">${new Date(msg.created_at).toLocaleString()}</div>
        </div>
    `;
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('messageModal').style.display = 'none';
}

// Close modal when clicking outside
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
const attachmentFilter = document.getElementById('filterAttachment');

function filterMessages() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedRoom = roomFilter.value;
    const selectedStatus = statusFilter.value;
    const selectedAttachment = attachmentFilter.value;
    
    const rows = document.querySelectorAll('.message-row');
    
    rows.forEach(row => {
        const message = row.getAttribute('data-message');
        const sender = row.getAttribute('data-sender');
        const roomId = row.getAttribute('data-room-id');
        const status = row.getAttribute('data-status');
        const hasAttachment = row.getAttribute('data-has-attachment');
        
        const matchesSearch = !searchTerm || message.includes(searchTerm) || sender.includes(searchTerm);
        const matchesRoom = !selectedRoom || roomId === selectedRoom;
        const matchesStatus = !selectedStatus || status === selectedStatus;
        const matchesAttachment = !selectedAttachment || hasAttachment === selectedAttachment;
        
        if (matchesSearch && matchesRoom && matchesStatus && matchesAttachment) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

searchInput.addEventListener('input', filterMessages);
roomFilter.addEventListener('change', filterMessages);
statusFilter.addEventListener('change', filterMessages);
attachmentFilter.addEventListener('change', filterMessages);

function resetFilters() {
    searchInput.value = '';
    roomFilter.value = '';
    statusFilter.value = '';
    attachmentFilter.value = '';
    filterMessages();
}
</script>