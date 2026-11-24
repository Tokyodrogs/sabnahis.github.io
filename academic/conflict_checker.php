<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Schedule Conflict Checker';
$errors = [];
$conflicts = null;
$checked = false;

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();

$form = [
    'sy_id' => $currentSY['sy_id'] ?? '',
    'section_id' => '', 'subject_id' => '', 'teacher_id' => '', 'room_id' => '',
    'day_of_week' => '', 'time_start' => '', 'time_end' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? '', 'conflict_checker')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($form['section_id'] === '' || $form['subject_id'] === '' || $form['teacher_id'] === '' || $form['day_of_week'] === '' || $form['time_start'] === '' || $form['time_end'] === '') {
        $errors[] = 'Please fill in Section, Subject, Teacher, Day, Start Time, and End Time to run the check.';
    } else {
        $conflicts = check_schedule_conflicts($pdo, $form);
        $checked = true;
    }
}

$sections = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.status = 'Active' ORDER BY g.grade_level_id, sec.section_name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects WHERE status = 'Active' ORDER BY subject_code")->fetchAll();
$teachers = $pdo->query("SELECT * FROM teachers WHERE status = 'Active' ORDER BY lastname, firstname")->fetchAll();
$rooms    = $pdo->query("SELECT * FROM rooms WHERE status = 'Active' ORDER BY room_name")->fetchAll();

$csrfToken = csrf_token('conflict_checker');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Schedule Conflict Checker</h2>
    <p class="muted">Check a potential class period for Teacher, Room, Section, Duplicate, and Time Overlap conflicts before creating it in Class Schedule. This is a read-only check - nothing is saved here.</p>

    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <?php if ($checked): ?>
        <?php if (empty($conflicts)): ?>
            <div class="alert alert-success">No conflicts found. This class period is safe to schedule.</div>
        <?php else: ?>
            <div class="alert alert-error">
                <p><?php echo count($conflicts); ?> conflict(s) found:</p>
                <ul><?php foreach ($conflicts as $c) echo '<li>' . clean($c) . '</li>'; ?></ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <section class="panel" style="max-width:640px;">
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="form-row">
                <label>Section *
                    <select name="section_id" required>
                        <option value="">Select</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['section_id']; ?>" <?php echo $form['section_id'] == $s['section_id'] ? 'selected' : ''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Subject *
                    <select name="subject_id" required>
                        <option value="">Select</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['subject_id']; ?>" <?php echo $form['subject_id'] == $s['subject_id'] ? 'selected' : ''; ?>><?php echo clean($s['subject_code']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label>Teacher *
                    <select name="teacher_id" required>
                        <option value="">Select</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['teacher_id']; ?>" <?php echo $form['teacher_id'] == $t['teacher_id'] ? 'selected' : ''; ?>><?php echo clean($t['lastname'] . ', ' . $t['firstname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Room
                    <select name="room_id">
                        <option value="">N/A</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r['room_id']; ?>" <?php echo $form['room_id'] == $r['room_id'] ? 'selected' : ''; ?>><?php echo clean($r['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label>Day *
                    <select name="day_of_week" required>
                        <option value="">Select</option>
                        <?php foreach (DAYS_OF_WEEK as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $form['day_of_week'] === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Start Time *<input type="time" name="time_start" value="<?php echo clean($form['time_start']); ?>" required></label>
                <label>End Time *<input type="time" name="time_end" value="<?php echo clean($form['time_end']); ?>" required></label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Check for Conflicts</button></div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
