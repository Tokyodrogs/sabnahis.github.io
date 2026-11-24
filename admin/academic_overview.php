<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

// Administrator/Principal only: full visibility + approve/override, per the
// spec's Principal/Administrator permissions. Enforced here server-side
// (not just hidden from the sidebar) so a Registrar can't reach the
// approve/override actions by guessing the URL.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Only the Administrator can access Academic Oversight.');
    redirect('schedule_view.php');
}

$pageTitle = 'Academic Oversight';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $scheduleId = (int) ($_POST['schedule_id'] ?? 0);

    if ($action === 'approve') {
        $pdo->prepare('UPDATE class_schedules SET approved_by = ?, approved_at = NOW() WHERE schedule_id = ?')
            ->execute([$_SESSION['admin_id'], $scheduleId]);
        flash('success', 'Schedule approved.');
    } elseif ($action === 'override_cancel') {
        $pdo->prepare("UPDATE class_schedules SET status = 'Cancelled' WHERE schedule_id = ?")->execute([$scheduleId]);
        flash('success', 'Schedule cancelled (Administrator override).');
    } elseif ($action === 'override_reactivate') {
        $pdo->prepare("UPDATE class_schedules SET status = 'Active' WHERE schedule_id = ?")->execute([$scheduleId]);
        flash('success', 'Schedule reactivated (Administrator override).');
    }
    redirect('academic_overview.php');
}

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$syId = $currentSY['sy_id'] ?? 0;

$schedules = $pdo->prepare("SELECT cs.*, sec.section_name, g.grade_name, sub.subject_code, sub.subject_title,
        CONCAT(t.firstname, ' ', t.lastname) AS teacher_name, r.room_name, a.fullname AS approved_by_name
    FROM class_schedules cs
    JOIN sections sec ON sec.section_id = cs.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    JOIN subjects sub ON sub.subject_id = cs.subject_id
    JOIN teachers t ON t.teacher_id = cs.teacher_id
    LEFT JOIN rooms r ON r.room_id = cs.room_id
    LEFT JOIN admin a ON a.admin_id = cs.approved_by
    WHERE cs.sy_id = ?
    ORDER BY g.grade_level_id, sec.section_name, FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cs.time_start");
$schedules->execute([$syId]);
$schedules = $schedules->fetchAll();

// Teacher load monitor: total scheduled periods per active teacher this SY.
$loads = $pdo->prepare("SELECT t.teacher_id, CONCAT(t.lastname, ', ', t.firstname) AS teacher_name,
        COUNT(cs.schedule_id) AS period_count
    FROM teachers t
    LEFT JOIN class_schedules cs ON cs.teacher_id = t.teacher_id AND cs.sy_id = ? AND cs.status = 'Active'
    WHERE t.status = 'Active'
    GROUP BY t.teacher_id, t.lastname, t.firstname ORDER BY period_count DESC, teacher_name");
$loads->execute([$syId]);
$loads = $loads->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Academic Oversight</h2>
    <p class="muted">School Year: <strong><?php echo clean($currentSY['school_year'] ?? 'Not set'); ?></strong> &mdash; Administrator/Principal view: approve schedules and monitor teaching loads across the whole school. Full scheduling is done by the Academic Coordinator in the Academic Management portal.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Teacher Load Monitor</h3>
        <table class="data-table">
            <thead><tr><th>Teacher</th><th>Scheduled Periods (this SY)</th></tr></thead>
            <tbody>
            <?php if (empty($loads)): ?><tr><td colspan="2" class="muted" style="text-align:center;">No active teachers.</td></tr><?php endif; ?>
            <?php foreach ($loads as $l): ?>
                <tr><td><?php echo clean($l['teacher_name']); ?></td><td><?php echo (int) $l['period_count']; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h3>All Class Schedules &mdash; Approve / Override</h3>
        <table class="data-table">
            <thead><tr><th>Grade &amp; Section</th><th>Subject</th><th>Teacher</th><th>Day</th><th>Time</th><th>Status</th><th>Approved By</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($schedules)): ?><tr><td colspan="8" class="muted" style="text-align:center;">No class schedules for the current school year yet.</td></tr><?php endif; ?>
            <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></td>
                    <td><?php echo clean($s['subject_code'] . ' - ' . $s['subject_title']); ?></td>
                    <td><?php echo clean($s['teacher_name']); ?></td>
                    <td><?php echo clean($s['day_of_week']); ?></td>
                    <td><?php echo clean(format_time12($s['time_start']) . ' - ' . format_time12($s['time_end'])); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($s['status']); ?>"><?php echo clean($s['status']); ?></span></td>
                    <td><?php echo clean($s['approved_by_name'] ?: '-'); ?></td>
                    <td class="actions">
                        <?php if (!$s['approved_by']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>">
                                <button type="submit" class="btn-small">Approve</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($s['status'] === 'Active'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Override and cancel this schedule?');">
                                <input type="hidden" name="action" value="override_cancel">
                                <input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>">
                                <button type="submit" class="link-danger">Override: Cancel</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="override_reactivate">
                                <input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>">
                                <button type="submit" class="btn-small">Reactivate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
