<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$studentId = (int)($_GET['student_id'] ?? 0);
$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    WHERE s.student_id = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enrollment Form - <?php echo clean($student['lastname'] . ', ' . $student['firstname']); ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/print.css'); ?>">
</head>
<body>
<div class="print-toolbar no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="print-sheet">
    <div class="print-header">
        <?php $printPhotoUrl = student_photo_url($student['photo']); ?>
        <?php if ($printPhotoUrl): ?>
            <img src="<?php echo clean($printPhotoUrl); ?>" alt="Student Photo" class="print-photo-box">
        <?php else: ?>
            <div class="print-photo-box print-photo-placeholder">No Photo</div>
        <?php endif; ?>
        <h2><?php echo SITE_NAME; ?></h2>
        <p>Sablayan, Occidental Mindoro</p>
        <h3>OFFICIAL ENROLLMENT FORM</h3>
        <p>School Year: <strong><?php echo clean($student['school_year'] ?? '-'); ?></strong></p>
    </div>

    <table class="print-table">
        <tr><th>Enrollment No.</th><td><?php echo clean($student['enrollment_no'] ?: 'Not yet generated'); ?></td>
            <th>Status</th><td><?php echo clean($student['status']); ?></td></tr>
        <tr><th>LRN</th><td colspan="3"><?php echo clean($student['lrn'] ?: 'Not yet assigned'); ?></td></tr>
        <tr><th>Full Name</th><td colspan="3"><?php echo clean(strtoupper($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['extension'])); ?></td></tr>
        <tr><th>Sex</th><td><?php echo clean($student['sex']); ?></td><th>Birthdate</th><td><?php echo clean($student['birthdate']); ?></td></tr>
        <tr><th>Age</th><td><?php echo clean($student['age']); ?></td><th>Contact No.</th><td><?php echo clean($student['contact']); ?></td></tr>
        <tr><th>Address</th><td colspan="3"><?php echo clean($student['address']); ?></td></tr>
        <tr><th>Email</th><td colspan="3"><?php echo clean($student['email']); ?></td></tr>
        <tr><th>Previous School</th><td colspan="3"><?php echo clean($student['previous_school']); ?></td></tr>
        <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?></td><th>Strand</th><td><?php echo clean($student['strand_name'] ?? '-'); ?></td></tr>
        <tr><th>Guardian</th><td><?php echo clean($student['guardian_name']); ?></td><th>Guardian Contact</th><td><?php echo clean($student['guardian_contact']); ?></td></tr>
    </table>

    <div class="signature-block">
        <div class="sig">
            <div class="sig-line"></div>
            <p>Parent / Guardian Signature</p>
        </div>
        <div class="sig">
            <div class="sig-line"></div>
            <p>School Registrar</p>
        </div>
        <div class="sig">
            <div class="sig-line"></div>
            <p>School Principal</p>
        </div>
    </div>

    <p class="print-footer">Generated on <?php echo date('F d, Y h:i A'); ?> via SABNAHIS Enrollment System</p>
</div>
</body>
</html>
