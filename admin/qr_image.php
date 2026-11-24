<?php
/**
 * Admin-side counterpart to student/qr_image.php (2026-08n) - streams any
 * student's Digital Student ID QR as a PNG for the "View QR" action in
 * admin/students.php. Gated by the normal admin session (any logged-in
 * admin role can view a QR - viewing isn't destructive - but only
 * Administrators can regenerate/deactivate, enforced in
 * student_qr_action.php).
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$studentId = (int) ($_GET['student_id'] ?? 0);
$size = 300;

$stmt = $pdo->prepare('SELECT student_id, status, qr_status FROM students WHERE student_id = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student || $student['status'] !== 'Enrolled' || $student['qr_status'] !== 'active') {
    $png = qr_fallback_png($size);
} else {
    $token = get_or_create_qr_token($pdo, $student['student_id']);
    $png = $token ? fetch_qr_png(qr_verify_url($token), $size) : null;
    if (!$png) $png = qr_fallback_png($size);
}

if (!$png) {
    http_response_code(503);
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: private, max-age=300');
echo $png;
