<?php
// Padak Course & Internships Database Config
define('PADAK_DB_HOST', 'localhost');
define('PADAK_DB_USER', 'root');
define('PADAK_DB_PASS', '');
define('PADAK_DB_NAME', 'padak_course_internships');

class PadakDatabase {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(PADAK_DB_HOST, PADAK_DB_USER, PADAK_DB_PASS, PADAK_DB_NAME);
        if ($this->conn->connect_error) {
            die(json_encode(['error' => 'DB connection failed: ' . $this->conn->connect_error]));
        }
        $this->conn->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new PadakDatabase();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($str) {
        return $this->conn->real_escape_string($str);
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }
}

function getPadakDB() {
    return PadakDatabase::getInstance()->getConnection();
}

// Student Auth Helper
class StudentAuth {
    private $db;

    public function __construct() {
        $this->db = getPadakDB();
    }

    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
    }

    public function getCurrentStudent() {
        if (!$this->isLoggedIn()) return null;
        $id = (int)$_SESSION['student_id'];
        $stmt = $this->db->prepare("SELECT id, full_name, email, phone, college_name, degree, year_of_study, domain_interest, profile_photo, created_at FROM internship_students WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function login($email, $password, $rememberMe = false) {
        $email = trim(strtolower($email));
        $stmt = $this->db->prepare("SELECT id, full_name, email, password, is_active FROM internship_students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();

        if (!$student) {
            $this->logAttempt(null, $email, 'failed');
            return ['success' => false, 'message' => 'No account found with this email'];
        }

        if (!$student['is_active']) {
            $this->logAttempt($student['id'], $email, 'failed');
            return ['success' => false, 'message' => 'Your account has been deactivated'];
        }

        if (!password_verify($password, $student['password'])) {
            $this->logAttempt($student['id'], $email, 'failed');
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        // Set session
        $_SESSION['student_id'] = $student['id'];
        $_SESSION['student_name'] = $student['full_name'];
        $_SESSION['student_email'] = $student['email'];

        // Remember Me
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $upd = $this->db->prepare("UPDATE internship_students SET remember_token = ?, token_expires_at = ? WHERE id = ?");
            $upd->bind_param("ssi", $token, $expires, $student['id']);
            $upd->execute();
            setcookie('padak_student_token', $token, strtotime('+30 days'), '/', '', false, true);
        }

        $this->logAttempt($student['id'], $email, 'success');
        return ['success' => true, 'student' => $student];
    }

    public function register($data) {
        $email = trim(strtolower($data['email']));
        // Check duplicate
        $check = $this->db->prepare("SELECT id FROM internship_students WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'An account with this email already exists'];
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("INSERT INTO internship_students (full_name, email, phone, password, college_name, degree, year_of_study, domain_interest) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssss",
            $data['full_name'],
            $email,
            $data['phone'],
            $hash,
            $data['college_name'],
            $data['degree'],
            $data['year_of_study'],
            $data['domain_interest']
        );

        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->db->insert_id];
        }
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    public function logout() {
        if ($this->isLoggedIn()) {
            // Clear remember token
            $id = (int)$_SESSION['student_id'];
            $stmt = $this->db->prepare("UPDATE internship_students SET remember_token = NULL, token_expires_at = NULL WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        session_destroy();
        setcookie('padak_student_token', '', time() - 3600, '/');
    }

    private function logAttempt($studentId, $email, $status) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $this->db->prepare("INSERT INTO student_login_logs (student_id, email, status, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $studentId, $email, $status, $ip);
        $stmt->execute();
    }
}
?>