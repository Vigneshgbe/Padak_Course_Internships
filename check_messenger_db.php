<?php
// Database setup checker and fixer for messenger
require_once 'config.php';
$db = getPadakDB();

echo "<h2>Checking Messenger Database Tables...</h2>";

// Check if tables exist
$tables = ['chat_rooms', 'chat_room_members', 'chat_messages', 'direct_message_pairs'];
$missingTables = [];

foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missingTables[] = $table;
        echo "<p style='color:red'>❌ Table '$table' is missing</p>";
    } else {
        echo "<p style='color:green'>✓ Table '$table' exists</p>";
    }
}

if (!empty($missingTables)) {
    echo "<h3>Creating missing tables...</h3>";
    
    // Create chat_rooms if missing
    if (in_array('chat_rooms', $missingTables)) {
        $db->query("CREATE TABLE IF NOT EXISTS chat_rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_type ENUM('group','direct','team','batch') NOT NULL,
            room_name VARCHAR(150),
            team_id INT DEFAULT NULL,
            batch_id INT DEFAULT NULL,
            created_by INT,
            avatar VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        echo "<p style='color:green'>✓ Created chat_rooms table</p>";
    }
    
    // Create chat_room_members if missing
    if (in_array('chat_room_members', $missingTables)) {
        $db->query("CREATE TABLE IF NOT EXISTS chat_room_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            student_id INT NOT NULL,
            last_read_at DATETIME DEFAULT NULL,
            UNIQUE KEY unique_room_member (room_id, student_id),
            FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "<p style='color:green'>✓ Created chat_room_members table</p>";
    }
    
    // Create chat_messages if missing
    if (in_array('chat_messages', $missingTables)) {
        $db->query("CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            message_type ENUM('text','file','image','announcement') DEFAULT 'text',
            file_url VARCHAR(500) DEFAULT NULL,
            is_pinned TINYINT(1) DEFAULT 0,
            is_deleted TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES internship_students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "<p style='color:green'>✓ Created chat_messages table</p>";
    }
    
    // Create direct_message_pairs if missing
    if (in_array('direct_message_pairs', $missingTables)) {
        $db->query("CREATE TABLE IF NOT EXISTS direct_message_pairs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL UNIQUE,
            student1_id INT NOT NULL,
            student2_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_pair (student1_id, student2_id),
            FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "<p style='color:green'>✓ Created direct_message_pairs table</p>";
    }
}

// Check if columns exist in internship_students
echo "<h3>Checking internship_students columns...</h3>";
$result = $db->query("SHOW COLUMNS FROM internship_students LIKE 'is_online'");
if ($result->num_rows == 0) {
    $db->query("ALTER TABLE internship_students ADD COLUMN is_online TINYINT(1) DEFAULT 0");
    echo "<p style='color:green'>✓ Added is_online column</p>";
} else {
    echo "<p style='color:green'>✓ is_online column exists</p>";
}

$result = $db->query("SHOW COLUMNS FROM internship_students LIKE 'last_seen'");
if ($result->num_rows == 0) {
    $db->query("ALTER TABLE internship_students ADD COLUMN last_seen DATETIME DEFAULT NULL");
    echo "<p style='color:green'>✓ Added last_seen column</p>";
} else {
    echo "<p style='color:green'>✓ last_seen column exists</p>";
}

// Count students
$studentCount = $db->query("SELECT COUNT(*) as count FROM internship_students WHERE is_active=1")->fetch_assoc()['count'];
echo "<h3>Database Status:</h3>";
echo "<p>Total active students: <strong>$studentCount</strong></p>";

if ($studentCount == 0) {
    echo "<p style='color:red'>⚠️ No active students found. You need to register some students first!</p>";
} else {
    echo "<p style='color:green'>✓ Students are ready to chat!</p>";
}

echo "<hr><h3>✅ Database check complete!</h3>";
echo "<p><a href='messenger.php'>Go to Messenger</a></p>";
?>