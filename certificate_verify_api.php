<?php
// certificate_verify_api.php
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

// Query the certificate - matching your existing table structure
$stmt = $db->prepare("
    SELECT ic.*, s.full_name as student_name, ib.batch_name, ib.course_name
    FROM internship_certificates ic
    JOIN students s ON ic.student_id = s.id
    LEFT JOIN internship_batches ib ON ic.batch_id = ib.id
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

// Return verified data matching your table structure
echo json_encode([
    'valid' => true,
    'certificate_number' => $cert['certificate_number'],
    'student_name' => $cert['student_name'],
    'course_name' => $cert['course_name'] ?? 'Internship Program',
    'batch_name' => $cert['batch_name'] ?? 'N/A',
    'issued_date' => $cert['issued_date'] ? date('M d, Y', strtotime($cert['issued_date'])) : 'N/A',
    'completion_grade' => $cert['completion_grade'] ?? 'Good',
    'total_points_earned' => $cert['total_points_earned'] ?? 0,
    'certificate_url' => $cert['certificate_url'] ?? null,
    'issue_date_raw' => $cert['issued_date']
]);