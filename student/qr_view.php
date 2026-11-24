<?php
/**
 * "View QR Code" - a dedicated, larger view of the logged-in student's
 * own Digital Student ID QR code (2026-08n), opened from qr_code.php's
 * View QR Code button. Reuses qr_image.php for the actual PNG.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$stmt = $pdo->prepare('SELECT lastname, firstname, middlename FROM students WHERE student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();
$fullName = trim(($student['lastname'] ?? '') . ', ' . ($student['firstname'] ?? '') . ' ' . ($student['middlename'] ?? ''));
$studentNumber = $_SESSION['student_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student QR Code - <?php echo clean($fullName); ?></title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .qr-view-card { text-align: center; padding: 24px; }
  .qr-view-card img { width: 280px; height: 280px; }
  .qr-view-name { font-weight: 700; font-size: 16px; margin-top: 14px; }
  .qr-view-number { color: #666; font-size: 13px; margin-top: 2px; }
  .qr-view-close { display: inline-block; margin-top: 18px; padding: 8px 18px; background: #1e5631; color: #fff; text-decoration: none; border-radius: 4px; font-size: 13px; }
</style>
</head>
<body>
  <div class="qr-view-card">
    <img src="qr_image.php" alt="Student QR Code" width="280" height="280">
    <div class="qr-view-name"><?php echo clean($fullName); ?></div>
    <div class="qr-view-number">Student Number: <?php echo clean($studentNumber); ?></div>
    <a href="javascript:window.close();" class="qr-view-close">Close</a>
  </div>
</body>
</html>
