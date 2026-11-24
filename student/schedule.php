<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Class Schedule';

// The student's schedule is derived automatically from students.section_id
// (set by the Registrar/Administrator when the student is placed into a
// section) - the Academic Coordinator's Academic Management module builds
// the section's class_schedules, and every student in that section
// inherits it with no per-student assignment step. See
// includes/academic_functions.php::get_section_schedule().
$stmt = $pdo->prepare('SELECT section_id, sy_id FROM students WHERE student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

$byDay = [];
foreach (DAYS_OF_WEEK as $day) { $byDay[$day] = []; }
$hasSchedule = false;

if (!empty($student['section_id']) && !empty($student['sy_id'])) {
    $byDay = get_section_schedule($pdo, $student['section_id'], $student['sy_id']);
    foreach ($byDay as $rows) { if ($rows) { $hasSchedule = true; break; } }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Class Schedule</h2>

    <?php if (empty($student['section_id'])): ?>
        <section class="panel">
            <p class="muted">You have not yet been assigned to a section. Your class schedule will appear here
               automatically once the registrar assigns you to a section.</p>
        </section>
    <?php elseif (!$hasSchedule): ?>
        <section class="panel">
            <p class="muted">No class schedule has been set for your section yet. It will appear here once the
               Academic Coordinator finalizes your section's schedule for the current school year.</p>
        </section>
    <?php else: ?>
        <div class="schedule-grid">
            <?php foreach ($byDay as $day => $rows): ?>
                <section class="panel schedule-day-card">
                    <h3><?php echo clean($day); ?></h3>
                    <?php if (empty($rows)): ?>
                        <p class="muted">No classes.</p>
                    <?php else: ?>
                        <ul class="schedule-list">
                            <?php foreach ($rows as $r): ?>
                                <li>
                                    <strong><?php echo clean(format_time12($r['time_start']) . ' - ' . format_time12($r['time_end'])); ?></strong>
                                    <div><?php echo clean($r['subject_code'] . ' - ' . $r['subject_title']); ?></div>
                                    <div class="muted"><?php echo clean($r['teacher_name'] ?: '-'); ?> &bull; Room <?php echo clean($r['room_name'] ?: '-'); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
