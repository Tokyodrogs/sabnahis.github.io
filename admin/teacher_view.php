<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'View Teacher';
$isAdministrator = ($_SESSION['admin_role'] ?? 'Administrator') === 'Administrator';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM teachers WHERE teacher_id = ?');
$stmt->execute([$id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    flash('error', 'Teacher not found.');
    redirect('teachers.php');
}

$deps = get_teacher_dependencies($pdo, $id);

$assignStmt = $pdo->prepare("SELECT ta.*, sub.subject_code, sub.subject_title, sec.section_name, g.grade_name, sy.school_year
    FROM teacher_assignments ta
    JOIN subjects sub ON sub.subject_id = ta.subject_id
    JOIN sections sec ON sec.section_id = ta.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    JOIN school_year sy ON sy.sy_id = ta.sy_id
    WHERE ta.teacher_id = ? ORDER BY sy.school_year DESC");
$assignStmt->execute([$id]);
$assignments = $assignStmt->fetchAll();

$scheduleStmt = $pdo->prepare("SELECT cs.*, sub.subject_code, sub.subject_title, sec.section_name, g.grade_name, r.room_name, sy.school_year
    FROM class_schedules cs
    JOIN subjects sub ON sub.subject_id = cs.subject_id
    JOIN sections sec ON sec.section_id = cs.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    LEFT JOIN rooms r ON r.room_id = cs.room_id
    JOIN school_year sy ON sy.sy_id = cs.sy_id
    WHERE cs.teacher_id = ? ORDER BY sy.school_year DESC, cs.day_of_week, cs.time_start");
$scheduleStmt->execute([$id]);
$schedules = $scheduleStmt->fetchAll();

$advisoryStmt = $pdo->prepare("SELECT sec.*, g.grade_name, sy.school_year
    FROM sections sec
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    LEFT JOIN school_year sy ON sy.sy_id = sec.sy_id
    WHERE sec.adviser_teacher_id = ? ORDER BY sy.school_year DESC");
$advisoryStmt->execute([$id]);
$advisory = $advisoryStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2><?php echo clean($teacher['lastname'] . ', ' . $teacher['firstname'] . ' ' . $teacher['middlename']); ?></h2>
        <div>
            <span class="badge badge-<?php echo strtolower($teacher['status']); ?>"><?php echo clean($teacher['status']); ?></span>
            <?php if ($isAdministrator): ?>
                <a href="teacher_edit.php?id=<?php echo $id; ?>" class="btn-secondary">Edit</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($err = flash('error')): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Teacher Information</h3>
        <table class="detail-table">
            <tr><th>Employee No.</th><td><?php echo clean($teacher['employee_no'] ?: '-'); ?></td><th>Status</th><td><?php echo clean($teacher['status']); ?></td></tr>
            <tr><th>Email</th><td><?php echo clean($teacher['email'] ?: '-'); ?></td><th>Contact</th><td><?php echo clean($teacher['contact'] ?: '-'); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Subject Assignments</h3>
        <p class="muted">Created and edited by the Academic Coordinator in the Academic Management portal.</p>
        <?php if (empty($assignments)): ?>
            <p class="muted">No subject assignments on file.</p>
        <?php else: ?>
            <table class="detail-table">
                <tr><th>Subject</th><th>Grade &amp; Section</th><th>School Year</th><th>Status</th></tr>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?php echo clean($a['subject_code'] . ' - ' . $a['subject_title']); ?></td>
                        <td><?php echo clean(($a['grade_name'] ?: '-') . ' - ' . $a['section_name']); ?></td>
                        <td><?php echo clean($a['school_year']); ?></td>
                        <td><?php echo clean($a['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Class Schedule</h3>
        <?php if (empty($schedules)): ?>
            <p class="muted">No class schedule entries on file.</p>
        <?php else: ?>
            <table class="detail-table">
                <tr><th>Subject</th><th>Section</th><th>Day</th><th>Time</th><th>Room</th><th>School Year</th></tr>
                <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td><?php echo clean($s['subject_code'] . ' - ' . $s['subject_title']); ?></td>
                        <td><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></td>
                        <td><?php echo clean($s['day_of_week']); ?></td>
                        <td><?php echo clean(date('g:i A', strtotime($s['time_start'])) . ' - ' . date('g:i A', strtotime($s['time_end']))); ?></td>
                        <td><?php echo clean($s['room_name'] ?: '-'); ?></td>
                        <td><?php echo clean($s['school_year']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Advisory Class</h3>
        <?php if (empty($advisory)): ?>
            <p class="muted">Not currently an adviser for any section.</p>
        <?php else: ?>
            <table class="detail-table">
                <tr><th>Section</th><th>Grade Level</th><th>School Year</th></tr>
                <?php foreach ($advisory as $sec): ?>
                    <tr>
                        <td><?php echo clean($sec['section_name']); ?></td>
                        <td><?php echo clean($sec['grade_name'] ?? '-'); ?></td>
                        <td><?php echo clean($sec['school_year'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($isAdministrator): ?>
    <section class="panel">
        <h3>Deletion Status</h3>
        <?php if ($deps['blocked']): ?>
            <div class="alert alert-error">
                This teacher cannot be deleted because there are existing assignments:
                <?php echo $deps['assignments']; ?> subject assignment(s), <?php echo $deps['schedules']; ?> class schedule entr(y/ies),
                and <?php echo $deps['advisory']; ?> advisory section(s).
            </div>
            <form method="POST" action="teacher_remove_assignments.php" onsubmit="return confirm('Remove all assignments for this teacher?\n\nThis action cannot be undone.');">
                <input type="hidden" name="teacher_id" value="<?php echo $id; ?>">
                <button type="submit" class="btn-danger">Remove Assignments</button>
            </form>
        <?php else: ?>
            <p class="muted">No dependencies found. This teacher can be safely deleted.</p>
            <a href="teacher_delete.php?id=<?php echo $id; ?>" class="btn-danger" onclick="return confirm('Permanently delete this teacher record? This cannot be undone.');">Delete Teacher</a>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <a href="teachers.php" class="btn-link">&larr; Back to Teacher List</a>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
