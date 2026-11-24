<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$syId = $_GET['sy_id'] ?? '';
$sectionId = $_GET['section_id'] ?? '';

$secStmt = $pdo->prepare("SELECT sec.*, g.grade_name, sy.school_year FROM sections sec
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    LEFT JOIN school_year sy ON sy.sy_id = sec.sy_id
    WHERE sec.section_id = ?");
$secStmt->execute([$sectionId]);
$section = $secStmt->fetch();

if (!$section) {
    die('Section not found.');
}

$schedule = get_section_schedule($pdo, $sectionId, $syId ?: $section['sy_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Section Schedule - <?php echo clean(($section['grade_name'] ?: '-') . ' - ' . $section['section_name']); ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/print.css'); ?>">
</head>
<body>
<div class="print-toolbar no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>
<div class="print-sheet">
    <div class="print-header">
        <h2><?php echo SITE_NAME; ?></h2>
        <h3>Section Master Schedule</h3>
        <p><?php echo clean(($section['grade_name'] ?: '-') . ' - ' . $section['section_name']); ?> &mdash; S.Y. <?php echo clean($section['school_year'] ?? '-'); ?></p>
        <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
    </div>

    <?php foreach ($schedule as $day => $rows): ?>
        <h3 style="margin-top:18px;"><?php echo clean($day); ?></h3>
        <?php if (empty($rows)): ?>
            <p class="muted">No classes.</p>
        <?php else: ?>
            <table class="print-table">
                <tr><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th></tr>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo clean(format_time12($r['time_start']) . ' - ' . format_time12($r['time_end'])); ?></td>
                        <td><?php echo clean($r['subject_code'] . ' - ' . $r['subject_title']); ?></td>
                        <td><?php echo clean($r['teacher_name']); ?></td>
                        <td><?php echo clean($r['room_name'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <p class="print-footer">Generated via the SABNAHIS Academic Management module</p>
</div>
</body>
</html>
