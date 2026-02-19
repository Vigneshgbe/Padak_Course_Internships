-- Database: padak_course_internships
CREATE DATABASE IF NOT EXISTS padak_course_internships CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE padak_course_internships;

-- Internship Students Table
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
    remember_token VARCHAR(255) DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Session tracking (optional for remember me)
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

-- Login Activity Log
CREATE TABLE IF NOT EXISTS student_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    email VARCHAR(255),
    status ENUM('success','failed') NOT NULL,
    ip_address VARCHAR(45),
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES internship_students(id) ON DELETE SET NULL
) ENGINE=InnoDB;