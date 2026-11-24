<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Academic Dashboard';

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$syId = $currentSY['sy_id'] ?? 0;

$totalSections = (int) $pdo->query('SELECT COUNT(*) FROM sections' . ($syId ? " WHERE sy_id = $syId" : ''))->fetchColumn();
$totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'Active'")->fetchColumn();
$totalSubjects = (int) $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 'Active'")->fetchColumn();
$totalRooms    = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'Active'")->fetchColumn();
$totalSchedules = (int) $pdo->query('SELECT COUNT(*) FROM class_schedules' . ($syId ? " WHERE sy_id = $syId AND status='Active'" : ''))->fetchColumn();

// Sections that still have zero class_schedules rows for the current SY -
// a quick "what still needs scheduling" worklist.
$unscheduled = [];
if ($syId) {
    $unscheduled = $pdo->query("SELECT sec.section_id, sec.section_name, g.grade_name
        FROM sections sec
        LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
        LEFT JOIN class_schedules cs ON cs.section_id = sec.section_id AND cs.sy_id = $syId AND cs.status = 'Active'
        WHERE sec.sy_id = $syId AND sec.status = 'Active'
        GROUP BY sec.section_id, sec.section_name, g.grade_level_id, g.grade_name
        HAVING COUNT(cs.schedule_id) = 0
        ORDER BY g.grade_level_id, sec.section_name")->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Academic Dashboard</h2>
    <p class="muted">Current School Year: <strong><?php echo clean($currentSY['school_year'] ?? 'Not set'); ?></strong></p>

    <?php if (!$currentSY): ?>
        <div class="alert alert-error">No school year is marked "current" yet. Ask the Registrar/Administrator to set one in the admin panel before scheduling classes.</div>
    <?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card"><div class="stat-value"><?php echo $totalSections; ?></div><div class="stat-label">Sections (this SY)</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $totalTeachers; ?></div><div class="stat-label">Active Teachers</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $totalSubjects; ?></div><div class="stat-label">Active Subjects</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $totalRooms; ?></div><div class="stat-label">Active Rooms</div></div>
        <div class="stat-card highlight"><div class="stat-value"><?php echo $totalSchedules; ?></div><div class="stat-label">Scheduled Class Periods</div></div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h3>Sections Not Yet Scheduled</h3>
            <a href="class_schedule.php" class="btn-small">Go to Class Schedule</a>
        </div>
        <?php if (empty($unscheduled)): ?>
            <p class="muted">Every active section for the current school year has at least one class period scheduled.</p>
        <?php else: ?>
            <ul class="req-list">
                <?php foreach ($unscheduled as $u): ?>
                    <li><?php echo clean(($u['grade_name'] ?: '-') . ' - ' . $u['section_name']); ?>
                        <a href="schedule_add.php?section_id=<?php echo $u['section_id']; ?>" class="btn-link">Create Schedule</a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Quick Access</h3>
        <div class="quick-access-grid">
            <a href="class_schedule.php" class="quick-access-card">Class Schedule</a>
            <a href="teacher_assignment.php" class="quick-access-card">Teacher Assignment</a>
            <a href="room_assignment.php" class="quick-access-card">Room Assignment</a>
            <a href="subject_assignment.php" class="quick-access-card">Subject Assignment</a>
            <a href="section_schedule.php" class="quick-access-card">Section Schedule</a>
            <a href="conflict_checker.php" class="quick-access-card">Schedule Conflict Checker</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
