<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Class Schedule';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '', 'class_schedule_delete')) {
        flash('success', 'Your session has expired. Please try again.');
    } else {
        $pdo->prepare('DELETE FROM class_schedules WHERE schedule_id = ?')->execute([(int) $_POST['schedule_id']]);
        flash('success', 'Class schedule deleted.');
    }
    redirect('class_schedule.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
}

$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$teachers    = $pdo->query("SELECT * FROM teachers WHERE status = 'Active' ORDER BY lastname, firstname")->fetchAll();
$subjects    = $pdo->query("SELECT * FROM subjects WHERE status = 'Active' ORDER BY subject_code")->fetchAll();
$rooms       = $pdo->query("SELECT * FROM rooms WHERE status = 'Active' ORDER BY room_name")->fetchAll();

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();

$syId        = $_GET['sy_id'] ?? ($currentSY['sy_id'] ?? '');
$gradeFilter = $_GET['grade_level_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';
$teacherFilter = $_GET['teacher_id'] ?? '';
$subjectFilter = $_GET['subject_id'] ?? '';
$roomFilter    = $_GET['room_id'] ?? '';

$sectionsSql = 'SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id';
$sectionsParams = [];
if ($syId !== '') { $sectionsSql .= ' WHERE sec.sy_id = ?'; $sectionsParams[] = $syId; }
$sectionsSql .= ' ORDER BY g.grade_level_id, sec.section_name';
$secStmt = $pdo->prepare($sectionsSql);
$secStmt->execute($sectionsParams);
$sections = $secStmt->fetchAll();

$where = [];
$params = [];
if ($syId !== '') { $where[] = 'cs.sy_id = ?'; $params[] = $syId; }
if ($gradeFilter !== '') { $where[] = 'sec.grade_level_id = ?'; $params[] = $gradeFilter; }
if ($sectionFilter !== '') { $where[] = 'cs.section_id = ?'; $params[] = $sectionFilter; }
if ($teacherFilter !== '') { $where[] = 'cs.teacher_id = ?'; $params[] = $teacherFilter; }
if ($subjectFilter !== '') { $where[] = 'cs.subject_id = ?'; $params[] = $subjectFilter; }
if ($roomFilter !== '') { $where[] = 'cs.room_id = ?'; $params[] = $roomFilter; }

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

$qs = $_SERVER['QUERY_STRING'] ?? '';
$csrfToken = csrf_token('class_schedule_delete');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Class Schedule</h2>
        <a href="schedule_add.php<?php echo $qs ? '?' . clean($qs) : ''; ?>" class="btn-primary">+ Create Class Schedule</a>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <form method="GET" class="filter-bar">
        <select name="sy_id">
            <option value="">All School Years</option>
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?php echo $sy['sy_id']; ?>" <?php echo (string)$syId === (string)$sy['sy_id'] ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="grade_level_id">
            <option value="">All Grade Levels</option>
            <?php foreach ($gradeLevels as $g): ?>
                <option value="<?php echo $g['grade_level_id']; ?>" <?php echo $gradeFilter == $g['grade_level_id'] ? 'selected' : ''; ?>><?php echo clean($g['grade_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="section_id">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?>
                <option value="<?php echo $s['section_id']; ?>" <?php echo $sectionFilter == $s['section_id'] ? 'selected' : ''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="teacher_id">
            <option value="">All Teachers</option>
            <?php foreach ($teachers as $t): ?>
                <option value="<?php echo $t['teacher_id']; ?>" <?php echo $teacherFilter == $t['teacher_id'] ? 'selected' : ''; ?>><?php echo clean($t['lastname'] . ', ' . $t['firstname']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="subject_id">
            <option value="">All Subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?php echo $s['subject_id']; ?>" <?php echo $subjectFilter == $s['subject_id'] ? 'selected' : ''; ?>><?php echo clean($s['subject_code']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="room_id">
            <option value="">All Rooms</option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?php echo $r['room_id']; ?>" <?php echo $roomFilter == $r['room_id'] ? 'selected' : ''; ?>><?php echo clean($r['room_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        <a href="class_schedule.php" class="btn-link">Reset</a>
    </form>

    <table class="data-table">
        <thead>
        <tr><th>Grade &amp; Section</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Day</th><th>Time</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($schedules)): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;">No class schedules found for the selected filters.</td></tr>
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
                <td class="actions">
                    <a href="schedule_edit.php?id=<?php echo $s['schedule_id']; ?>">Edit</a>
                    <a href="schedule_copy.php?id=<?php echo $s['schedule_id']; ?>">Copy</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this class schedule?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                        <input type="hidden" name="back_qs" value="<?php echo clean($qs); ?>">
                        <button type="submit" class="danger link-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
