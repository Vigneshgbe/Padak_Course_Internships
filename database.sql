-- ============================================================
-- Database: padak_course_internships
-- ============================================================

CREATE DATABASE IF NOT EXISTS padak_course_internships CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE padak_course_internships;

CREATE TABLE IF NOT EXISTS internship_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    college_name VARCHAR(255),
    degree VARCHAR(150),
    year_of_study ENUM('1st Year','2nd Year','3rd Year','4th Year','Graduate') DEFAULT '1st Year',
    domain_interest VARCHAR(150),
    profile_photo VARCHAR(255) DEFAULT NULL,
    bio TEXT,
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    remember_token VARCHAR(255) DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    total_points INT DEFAULT 0,
    internship_status ENUM('pending','active','completed','withdrawn') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS internship_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(150) NOT NULL,
    domain VARCHAR(150) NOT NULL,
    start_date DATE,
    end_date DATE,
    max_students INT DEFAULT 50,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_batch_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT NOT NULL,
    enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enroll (student_id, batch_id),
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES internship_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(150) NOT NULL,
    batch_id INT,
    team_code VARCHAR(20) UNIQUE,
    description TEXT,
    max_members INT DEFAULT 5,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES internship_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES internship_students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    student_id INT NOT NULL,
    role ENUM('leader','member') DEFAULT 'member',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_member (team_id, student_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_type ENUM('group','direct','team','batch') NOT NULL,
    room_name VARCHAR(150),
    team_id INT DEFAULT NULL,
    batch_id INT DEFAULT NULL,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (batch_id) REFERENCES internship_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES internship_students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    student_id INT NOT NULL,
    last_read_at DATETIME DEFAULT NULL,
    UNIQUE KEY unique_room_member (room_id, student_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS internship_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    task_type ENUM('individual','team','batch') DEFAULT 'individual',
    batch_id INT DEFAULT NULL,
    team_id INT DEFAULT NULL,
    assigned_to_student INT DEFAULT NULL,
    due_date DATETIME,
    max_points INT DEFAULT 100,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('active','draft','closed') DEFAULT 'active',
    resources_url VARCHAR(500),
    created_by VARCHAR(150) DEFAULT 'Coordinator',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES internship_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_student) REFERENCES internship_students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    submission_url VARCHAR(500),
    github_link VARCHAR(500),
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    status ENUM('draft','submitted','under_review','approved','rejected','revision_requested') DEFAULT 'submitted',
    points_earned INT DEFAULT NULL,
    feedback TEXT,
    reviewed_by VARCHAR(150),
    reviewed_at DATETIME DEFAULT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (task_id, student_id),
    FOREIGN KEY (task_id) REFERENCES internship_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_points_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255),
    task_id INT DEFAULT NULL,
    awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES internship_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS internship_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT,
    certificate_number VARCHAR(100) UNIQUE,
    issued_date DATE,
    completion_grade ENUM('Outstanding','Excellent','Good','Satisfactory') DEFAULT 'Good',
    total_points_earned INT DEFAULT 0,
    is_issued TINYINT(1) DEFAULT 0,
    certificate_url VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES internship_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('general','task','deadline','certificate','urgent') DEFAULT 'general',
    batch_id INT DEFAULT NULL,
    target_all TINYINT(1) DEFAULT 1,
    created_by VARCHAR(150) DEFAULT 'Coordinator',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES internship_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    student_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (announcement_id, student_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('task','message','grade','certificate','announcement','system') DEFAULT 'system',
    link VARCHAR(500),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    email VARCHAR(255),
    status ENUM('success','failed') NOT NULL,
    ip_address VARCHAR(45),
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed data
INSERT IGNORE INTO internship_batches (batch_name, domain, start_date, end_date, description) VALUES
('Web Dev Batch 2025-A', 'Web Development', '2025-06-01', '2025-08-31', 'Full Stack Web Development Internship'),
('Data Science Batch 2025-A', 'Data Science', '2025-06-01', '2025-08-31', 'Data Science & ML Internship'),
('UI/UX Batch 2025-A', 'UI/UX Design', '2025-07-01', '2025-09-30', 'User Interface Design Internship');

INSERT IGNORE INTO announcements (title, content, type, target_all) VALUES
('Welcome to Padak Internship Program!', 'We are thrilled to have you join. Please complete your profile and review your assigned tasks to get started. Your journey to a free internship certificate begins here!', 'general', 1),
('First Task Released', 'Your first internship task has been assigned. Please check the Tasks section and submit before the deadline. Early submissions get bonus points!', 'task', 1),
('Certificate Policy Update', 'Students who earn 500+ points and complete all mandatory tasks will receive a FREE internship completion certificate. Top 3 earners get Outstanding grade certificates.', 'certificate', 1),
('Team Formation Open', 'You can now create or join teams for group tasks. Navigate to the Messenger section to collaborate with your teammates.', 'general', 1);