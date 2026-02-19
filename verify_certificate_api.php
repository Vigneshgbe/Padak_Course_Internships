<?php
// verify_certificate_api.php
// Enable error logging but don't display errors (to keep JSON clean)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    require_once 'config.php';
    $db = getPadakDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'error' => 'Database connection error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'error' => 'Invalid request method']);
    exit;
}

$certNumber = trim($_POST['certificate_number'] ?? '');

if (empty($certNumber)) {
    echo json_encode(['valid' => false, 'error' => 'Certificate number required']);
    exit;
}

// Query the certificate - using internship_students table (YOUR ACTUAL TABLE)
try {
    $stmt = $db->prepare("
        SELECT ic.*, ist.full_name as student_name, ib.batch_name
        FROM internship_certificates ic
        JOIN internship_students ist ON ic.student_id = ist.id
        LEFT JOIN internship_batches ib ON ic.batch_id = ib.id
        WHERE ic.certificate_number = ? AND ic.is_issued = 1
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $db->error);
    }
    
    $stmt->bind_param("s", $certNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['valid' => false, 'error' => 'Certificate not found or not issued']);
        exit;
    }
    
    $cert = $result->fetch_assoc();
    
    // Return verified data
    echo json_encode([
        'valid' => true,
        'certificate_number' => $cert['certificate_number'],
        'student_name' => $cert['student_name'],
        'course_name' => $cert['course_name'] ?? 'Internship Program',
        'batch_name' => $cert['batch_name'] ?? 'N/A',
        'issued_date' => $cert['issued_date'] ? date('M d, Y', strtotime($cert['issued_date'])) : 'N/A',
        'completion_grade' => $cert['completion_grade'] ?? 'Good',
        'total_points_earned' => $cert['total_points_earned'] ?? 0,
        'certificate_url' => $cert['certificate_url'] ?? null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'error' => 'Query error: ' . $e->getMessage()]);
    exit;
}