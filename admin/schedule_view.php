<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

// Read-only for everyone who reaches the admin panel (Registrar included).
// There is intentionally no create/edit/delete control anywhere on this
// page - schedule management lives exclusively in the Academic Management
// portal (see academic/class_schedule.php). This page only satisfies the
// Registrar's "View Class Schedule / Print Class Schedule" permissions.
$pageTitle = 'Class Schedule (View)';

$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();

$syId = $_GET['sy_id'] ?? ($currentSY['sy_id'] ?? '');
$gradeFilter = $_GET['grade_level_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';

$sections = $pdo->prepare("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.sy_id = ? ORDER BY g.grade_level_id, sec.section_name");
$sections->execute([$syId ?: 0]);
$sections = $sections->fetchAll();

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

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Class Schedule (View Only)</h2>
        <a href="schedule_view_print.php?<?php echo clean($_SERVER['QUERY_STRING'] ?? ''); ?>" target="_blank" class="btn-secondary">Print Class Schedule</a>
    </div>
    <p class="muted">Schedules are created and edited by the Academic Coordinator in the Academic Management module. This is a read-only view.</p>

    <form method="GET" class="filter-bar">
        <select name="sy_id" onchange="this.form.submit()">
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?php echo $sy['sy_id']; ?>" <?php echo (string) $syId === (string) $sy['sy_id'] ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="grade_level_id" onchange="this.form.submit()">
            <option value="">All Grade Levels</option>
            <?php foreach ($gradeLevels as $g): ?>
                <option value="<?php echo $g['grade_level_id']; ?>" <?php echo $gradeFilter == $g['grade_level_id'] ? 'selected' : ''; ?>><?php echo clean($g['grade_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="section_id" onchange="this.form.submit()">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?>
                <option value="<?php echo $s['section_id']; ?>" <?php echo $sectionFilter == $s['section_id'] ? 'selected' : ''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <table class="data-table">
        <thead><tr><th>Grade &amp; Section</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Day</th><th>Time</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($schedules)): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;">No class schedules found.</td></tr>
        <?php endif; ?>
        <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></td>
                <td><?php echo clean($s['subject_code'] . ' - ' . $s['subject_title']); ?></td>
                <td><?php echo clean($s['teacher_name']); ?></td>
                <td><?php echo clean($s['room_name'] ?: '-'); ?></td>
                <td><?php echo clean($s['day_of_week']); ?></td>
                <td><?php echo clean(format_time12($s['time_start']) . ' - ' . format_time12($s['time_end'])); ?></td>
                <td><span class="badge badge-<?php echo strtolower($s['status']); ?>"><?php echo clean($s['status']); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
