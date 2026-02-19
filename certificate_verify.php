<?php
header('Content-Type: application/json');
require_once 'config.php';

$db = getPadakDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'error' => 'Invalid request method']);
    exit;
}

$certNumber = trim($_POST['certificate_number'] ?? '');

if (empty($certNumber)) {
    echo json_encode(['valid' => false, 'error' => 'Certificate number required']);
    exit;
}

// Query the certificate
$stmt = $db->prepare("
    SELECT ic.*, is.full_name as student_name 
    FROM internship_certificates ic
    JOIN internship_students is ON ic.student_id = is.id
    WHERE ic.certificate_number = ? AND ic.is_issued = 1
");

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
    'issued_date' => date('M d, Y', strtotime($cert['issued_date'])),
    'completion_grade' => $cert['completion_grade'],
    'total_points_earned' => $cert['total_points_earned']
]);