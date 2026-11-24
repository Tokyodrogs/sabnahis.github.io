<?php
/**
 * Digital Student ID - printable ID-card view (2026-08l, formerly "My QR
 * Code" print view; portrait-card redesign 2026-08m; real server-rendered
 * QR 2026-08n - mirrors student/qr_code.php's layout and QR source
 * (qr_image.php), using the same .id-card* class names - see
 * assets/css/print.css). Mirrors qr_code.php's "Enrolled students only"
 * gate so a Pending/Rejected applicant can't print an official-looking ID
 * card.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year, sec.section_name
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

if (($student['status'] ?? '') !== 'Enrolled') {
    die('Your Digital Student ID is not active yet - it will be available once your enrollment is officially confirmed.');
}

$studentNumber = $_SESSION['student_username'];
$fullName = trim($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Digital Student ID - <?php echo clean($fullName); ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/print.css'); ?>">
</head>
<body>
<div class="print-toolbar no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="id-card">
    <div class="id-card-header">
        <div class="id-card-header-left">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="SABNAHIS Logo" class="id-card-logo">
            <div>
                <div class="id-card-school-name"><?php echo SITE_NAME; ?></div>
                <div class="id-card-school-address">Sablayan, Occidental Mindoro</div>
            </div>
        </div>
        <?php $photoUrl = student_photo_url($student['photo']); ?>
        <?php if ($photoUrl): ?>
            <img src="<?php echo clean($photoUrl); ?>" alt="Student Photo" class="id-card-photo-box" width="92" height="108">
        <?php else: ?>
            <div class="id-card-photo-box id-card-photo-placeholder">No Photo</div>
        <?php endif; ?>
    </div>

    <div class="id-card-title">
        <h3>Digital Student ID</h3>
        <div class="id-card-divider"></div>
    </div>

    <div class="id-card-info">
        <div class="id-card-row"><span class="id-card-label">Name</span><span class="id-card-value"><?php echo clean($fullName); ?></span></div>
        <div class="id-card-row"><span class="id-card-label">Student Number</span><span class="id-card-value"><?php echo clean($studentNumber); ?></span></div>
        <?php if (!empty($student['lrn'])): ?>
            <div class="id-card-row"><span class="id-card-label">LRN</span><span class="id-card-value"><?php echo clean($student['lrn']); ?></span></div>
        <?php endif; ?>
        <div class="id-card-row"><span class="id-card-label">Grade Level</span><span class="id-card-value"><?php echo clean($student['grade_name'] ?? '-'); ?><?php if ($student['strand_name']): ?> &mdash; <?php echo clean($student['strand_name']); ?><?php endif; ?></span></div>
        <div class="id-card-row"><span class="id-card-label">Section</span><span class="id-card-value"><?php echo clean($student['section_name'] ?? 'Not yet assigned'); ?></span></div>
        <div class="id-card-row"><span class="id-card-label">School Year</span><span class="id-card-value"><?php echo clean($student['school_year'] ?? '-'); ?></span></div>
    </div>

    <div class="id-card-qr-section">
        <div class="id-card-qr-box">
            <img src="qr_image.php" alt="Student QR Code" class="qr-code-image" width="150" height="150">
        </div>
        <div class="id-card-qr-caption">Scan to Verify Student Identity</div>
    </div>

    <div class="id-card-footer">
        Generated on <?php echo date('F d, Y \a\t h:i A'); ?><br>via the SABNAHIS Student Portal
    </div>
</div>
</body>
</html>
