<?php
/**
 * Certificate of Registration - print/PDF view. Reachable directly by URL,
 * so it independently re-checks the session and the "Officially Enrolled"
 * gate rather than trusting cor.php's check.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    WHERE s.student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

if ($student['status'] !== 'Enrolled') {
    die('Certificate of Registration is only available once you are Officially Enrolled.');
}

$subjects = [];
if (!empty($student['section_id']) && !empty($student['sy_id'])) {
    $subStmt = $pdo->prepare('SELECT sub.subject_code, sub.subject_title, sub.units,
            CONCAT(t.firstname, " ", t.lastname) AS instructor_name,
            sec.section_name,
            (SELECT r.room_name FROM class_schedules cs LEFT JOIN rooms r ON r.room_id = cs.room_id
             WHERE cs.section_id = ta.section_id AND cs.subject_id = ta.subject_id AND cs.sy_id = ta.sy_id AND cs.status = "Active"
             LIMIT 1) AS room
        FROM teacher_assignments ta
        JOIN subjects sub ON sub.subject_id = ta.subject_id
        JOIN teachers t ON t.teacher_id = ta.teacher_id
        JOIN sections sec ON sec.section_id = ta.section_id
        WHERE ta.section_id = ? AND ta.sy_id = ? AND ta.status = "Active"
        ORDER BY sub.subject_code');
    $subStmt->execute([$student['section_id'], $student['sy_id']]);
    $subjects = $subStmt->fetchAll();
}
$totalUnits = 0;
foreach ($subjects as $s) { $totalUnits += (float) $s['units']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate of Registration - <?php echo clean($student['lastname'] . ', ' . $student['firstname']); ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/print.css'); ?>">
</head>
<body>
<div class="print-toolbar no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="print-sheet">
    <div class="print-header">
        <?php $photoUrl = student_photo_url($student['photo']); ?>
        <?php if ($photoUrl): ?>
            <img src="<?php echo clean($photoUrl); ?>" alt="Student Photo" class="print-photo-box">
        <?php else: ?>
            <div class="print-photo-box print-photo-placeholder">No Photo</div>
        <?php endif; ?>
        <h2><?php echo SITE_NAME; ?></h2>
        <p>Sablayan, Occidental Mindoro</p>
        <h3>CERTIFICATE OF REGISTRATION</h3>
        <p>School Year: <strong><?php echo clean($student['school_year'] ?? '-'); ?></strong></p>
    </div>

    <table class="print-table">
        <tr><th>Student Number</th><td><?php echo clean($_SESSION['student_username']); ?></td>
            <th>LRN</th><td><?php echo clean($student['lrn'] ?: 'Not yet assigned'); ?></td></tr>
        <tr><th>Full Name</th><td colspan="3"><?php echo clean(strtoupper($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['extension'])); ?></td></tr>
        <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?></td>
            <th>Strand</th><td><?php echo clean($student['strand_name'] ?? '-'); ?></td></tr>
    </table>

    <h3 style="margin-top:24px;">Subjects</h3>
    <?php if (empty($subjects)): ?>
        <p class="muted">No subjects have been encoded yet.</p>
    <?php else: ?>
        <table class="print-table">
            <tr><th>Code</th><th>Course Title</th><th>Units</th><th>Instructor</th><th>Section</th><th>Room</th></tr>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><?php echo clean($s['subject_code']); ?></td>
                    <td><?php echo clean($s['subject_title']); ?></td>
                    <td><?php echo clean(rtrim(rtrim(number_format($s['units'], 1), '0'), '.')); ?></td>
                    <td><?php echo clean($s['instructor_name'] ?: '-'); ?></td>
                    <td><?php echo clean($s['section_name'] ?: '-'); ?></td>
                    <td><?php echo clean($s['room'] ?: '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr><th colspan="2" style="text-align:right;">Total Units</th><th><?php echo clean(rtrim(rtrim(number_format($totalUnits, 1), '0'), '.')); ?></th><th colspan="3"></th></tr>
        </table>
    <?php endif; ?>

    <div class="signature-block">
        <div class="sig"><div class="sig-line"></div><p>Student Signature</p></div>
        <div class="sig"><div class="sig-line"></div><p>School Registrar</p></div>
    </div>

    <p class="print-footer">Generated on <?php echo date('F d, Y h:i A'); ?> via the SABNAHIS Student Portal</p>
</div>
</body>
</html>
