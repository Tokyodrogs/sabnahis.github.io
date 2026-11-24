<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year, sec.section_name
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.student_id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found.');
}

$portalAccount = null;
try {
    $acctStmt = $pdo->prepare('SELECT username, status FROM student_accounts WHERE student_id = ?');
    $acctStmt->execute([$id]);
    $portalAccount = $acctStmt->fetch();
} catch (PDOException $e) {
    // Student Portal tables not installed - fine, just omit that section.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Profile - <?php echo clean($student['lastname'] . ', ' . $student['firstname']); ?></title>
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
        <h3>STUDENT PROFILE</h3>
    </div>

    <table class="print-table">
        <tr><th>Student Number</th><td><?php echo clean($portalAccount['username'] ?? $student['enrollment_no'] ?: 'Not yet assigned'); ?></td>
            <th>LRN</th><td><?php echo clean($student['lrn'] ?: 'Not yet assigned'); ?></td></tr>
        <tr><th>Full Name</th><td colspan="3"><?php echo clean(strtoupper($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['extension'])); ?></td></tr>
        <tr><th>Sex</th><td><?php echo clean($student['sex']); ?></td><th>Birthdate</th><td><?php echo clean($student['birthdate']); ?></td></tr>
        <tr><th>Age</th><td><?php echo clean($student['age']); ?></td><th>Contact No.</th><td><?php echo clean($student['contact']); ?></td></tr>
        <tr><th>Email</th><td colspan="3"><?php echo clean($student['email']); ?></td></tr>
        <tr><th>Address</th><td colspan="3"><?php echo clean($student['address']); ?></td></tr>
        <tr><th>Previous School</th><td colspan="3"><?php echo clean($student['previous_school']); ?></td></tr>
        <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?></td><th>Strand</th><td><?php echo clean($student['strand_name'] ?? '-'); ?></td></tr>
        <tr><th>Section</th><td><?php echo clean($student['section_name'] ?? 'Not yet assigned'); ?></td><th>School Year</th><td><?php echo clean($student['school_year'] ?? '-'); ?></td></tr>
        <tr><th>Registration Type</th><td><?php echo clean($student['student_type']); ?></td>
            <th>Status</th><td><?php echo clean($student['status'] === 'Enrolled' ? 'Officially Enrolled' : $student['status']); ?></td></tr>
        <tr><th>Guardian</th><td><?php echo clean($student['guardian_name']); ?></td><th>Guardian Contact</th><td><?php echo clean($student['guardian_contact']); ?></td></tr>
        <?php if ($portalAccount): ?>
        <tr><th>Portal Username</th><td><?php echo clean($portalAccount['username']); ?></td>
            <th>Portal Status</th><td><?php echo clean($portalAccount['status']); ?></td></tr>
        <?php endif; ?>
    </table>

    <p class="print-footer">Generated on <?php echo date('F d, Y h:i A'); ?> via SABNAHIS Enrollment System</p>
</div>
</body>
</html>
