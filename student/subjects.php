<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Subjects';

// A student's subjects are whatever the Academic Coordinator assigned to
// their section for the current school year (teacher_assignments), joined
// to that section's actual class_schedules for the day/time summary. No
// per-student subject enrollment step exists - it all flows from
// students.section_id.
$stuStmt = $pdo->prepare('SELECT section_id, sy_id FROM students WHERE student_id = ?');
$stuStmt->execute([$_SESSION['student_id']]);
$student = $stuStmt->fetch();

$subjects = [];
if (!empty($student['section_id']) && !empty($student['sy_id'])) {
    $stmt = $pdo->prepare('SELECT sub.subject_code, sub.subject_title, sub.units,
            CONCAT(t.firstname, " ", t.lastname) AS instructor_name,
            sec.section_name,
            (SELECT r.room_name FROM class_schedules cs LEFT JOIN rooms r ON r.room_id = cs.room_id
             WHERE cs.section_id = ta.section_id AND cs.subject_id = ta.subject_id AND cs.sy_id = ta.sy_id AND cs.status = "Active"
             LIMIT 1) AS room,
            (SELECT GROUP_CONCAT(CONCAT(cs.day_of_week, " ", TIME_FORMAT(cs.time_start, "%h:%i %p"), "-", TIME_FORMAT(cs.time_end, "%h:%i %p")) SEPARATOR "; ")
             FROM class_schedules cs
             WHERE cs.section_id = ta.section_id AND cs.subject_id = ta.subject_id AND cs.sy_id = ta.sy_id AND cs.status = "Active") AS schedule_summary
        FROM teacher_assignments ta
        JOIN subjects sub ON sub.subject_id = ta.subject_id
        JOIN teachers t ON t.teacher_id = ta.teacher_id
        JOIN sections sec ON sec.section_id = ta.section_id
        WHERE ta.section_id = ? AND ta.sy_id = ? AND ta.status = "Active"
        ORDER BY sub.subject_code');
    $stmt->execute([$student['section_id'], $student['sy_id']]);
    $subjects = $stmt->fetchAll();
}

$totalUnits = 0;
foreach ($subjects as $s) { $totalUnits += (float) $s['units']; }

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Subjects</h2>

    <?php if (empty($subjects)): ?>
        <section class="panel">
            <p class="muted"><?php echo empty($student['section_id'])
                ? 'You have not yet been assigned to a section. Your subjects will appear here automatically once the registrar assigns you to a section.'
                : 'No subjects have been assigned to your section yet. Once the Academic Coordinator finalizes your section\'s subjects for the current school year, they will appear here.'; ?></p>
        </section>
    <?php else: ?>
        <section class="panel">
            <table class="data-table">
                <thead>
                <tr><th>Course Code</th><th>Course Title</th><th>Units</th><th>Instructor</th><th>Section</th><th>Schedule</th><th>Room</th></tr>
                </thead>
                <tbody>
                <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><?php echo clean($s['subject_code']); ?></td>
                        <td><?php echo clean($s['subject_title']); ?></td>
                        <td><?php echo clean(rtrim(rtrim(number_format($s['units'], 1), '0'), '.')); ?></td>
                        <td><?php echo clean($s['instructor_name'] ?: '-'); ?></td>
                        <td><?php echo clean($s['section_name'] ?: '-'); ?></td>
                        <td><?php echo clean($s['schedule_summary'] ?: 'Not yet scheduled'); ?></td>
                        <td><?php echo clean($s['room'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr><th colspan="2" style="text-align:right;">Total Units:</th><th><?php echo clean(rtrim(rtrim(number_format($totalUnits, 1), '0'), '.')); ?></th><th colspan="4"></th></tr>
                </tfoot>
            </table>
        </section>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
