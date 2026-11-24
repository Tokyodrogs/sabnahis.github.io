<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$syId = $_GET['sy_id'] ?? '';
$gradeFilter = $_GET['grade_level_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';

$syLabel = 'All School Years';
if ($syId !== '') {
    $s = $pdo->prepare('SELECT school_year FROM school_year WHERE sy_id = ?');
    $s->execute([$syId]);
    $syLabel = $s->fetchColumn() ?: $syLabel;
}

$where = [];
$params = [];
if ($syId !== '') { $where[] = 'cs.sy_id = ?'; $params[] = $syId; }
if ($gradeFilter !== '') { $where[] = 'sec.grade_level_id = ?'; $params[] = $gradeFilter; }
if ($sectionFilter !== '') { $where[] = 'cs.section_id = ?'; $params[] = $sectionFilter; }

$sql = "SELECT cs.*, sec.section_name, g.grade_name, sub.subject_code, sub.subject_title,
        CONCAT(t.firstname, ' ', t.lastname) AS teacher_name, r.room_name
    FROM class_schedules cs
    JOIN sections sec ON sec.section_id = cs.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    JOIN subjects sub ON sub.subject_id = cs.subject_id
    JOIN teachers t ON t.teacher_id = cs.teacher_id
    LEFT JOIN rooms r ON r.room_id = cs.room_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " ORDER BY g.grade_level_id, sec.section_name, FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cs.time_start";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Class Schedule - <?php echo SITE_SHORT_NAME; ?></title>
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
        <h3>Class Schedule</h3>
        <p>School Year: <strong><?php echo clean($syLabel); ?></strong></p>
        <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
    <table class="print-table">
        <tr><th>Grade &amp; Section</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Day</th><th>Time</th></tr>
        <?php if (empty($schedules)): ?>
            <tr><td colspan="6" style="text-align:center;">No data available.</td></tr>
        <?php endif; ?>
        <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></td>
                <td><?php echo clean($s['subject_code'] . ' - ' . $s['subject_title']); ?></td>
                <td><?php echo clean($s['teacher_name']); ?></td>
                <td><?php echo clean($s['room_name'] ?: '-'); ?></td>
                <td><?php echo clean($s['day_of_week']); ?></td>
                <td><?php echo clean(format_time12($s['time_start']) . ' - ' . format_time12($s['time_end'])); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p class="print-footer">Total records: <?php echo count($schedules); ?></p>
</div>
</body>
</html>
