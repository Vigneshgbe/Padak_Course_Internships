<?php
session_start();
require_once 'config.php';

$auth = new StudentAuth();

// Get student info before destroying session (optional, for logging)
$student = $auth->getCurrentStudent();
$studentName = $student ? $student['full_name'] : 'User';

// Destroy the session
session_unset();
session_destroy();

// Clear the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear remember me token cookie if it exists
if (isset($_COOKIE['padak_student_token'])) {
    setcookie('padak_student_token', '', time() - 3600, '/');
    
    // Also clear the token from database
    if ($student) {
        $db = getPadakDB();
        $stmt = $db->prepare("UPDATE internship_students SET remember_token = NULL, token_expires_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $student['id']);
        $stmt->execute();
    }
}

// Redirect to login page with logout success parameter
header('Location: login.php?logout=success');
exit;
?>