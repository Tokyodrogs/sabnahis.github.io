<?php
/**
 * Streams the logged-in student's own Digital Student ID QR code as a
 * PNG image (2026-08n). Used directly as an <img src="qr_image.php">
 * in student/qr_code.php and student/qr_code_print.php, and via
 * ?dl=1 for the "Download QR Code" button.
 *
 * Generation happens server-side (see fetch_qr_png() in
 * includes/functions.php) - this environment has no internet access to
 * vendor a client-side QR-encoding library, but the school's own PHP
 * server does, so the QR image is fetched here via cURL and streamed
 * through this same domain rather than exposing the third-party URL (or
 * the verification token) directly in page source. If that fetch fails
 * for any reason, a clean local fallback image is served instead - never
 * a broken image icon or technical error (see qr_fallback_png()).
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$stmt = $pdo->prepare('SELECT student_id, status, qr_status FROM students WHERE student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

$studentNumber = $_SESSION['student_username'] ?? 'student';
$size = 300;

if (!$student || $student['status'] !== 'Enrolled' || $student['qr_status'] !== 'active') {
    // Not an active ID (not officially enrolled yet, or an administrator
    // deactivated it) - render the same clean fallback rather than a
    // student's own valid-looking QR for an ID that shouldn't be active.
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
if (!empty($_GET['dl'])) {
    header('Content-Disposition: attachment; filename="SABNAHIS_QR_' . preg_replace('/[^A-Za-z0-9_-]/', '', $studentNumber) . '.png"');
} else {
    header('Content-Disposition: inline');
    header('Cache-Control: private, max-age=300');
}
echo $png;
