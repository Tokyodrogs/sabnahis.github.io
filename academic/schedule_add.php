<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Create Class Schedule';
$errors = [];

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();

$form = [
    'sy_id' => $currentSY['sy_id'] ?? '',
    'section_id' => $_GET['section_id'] ?? '',
    'subject_id' => '',
    'teacher_id' => '',
    'room_id' => '',
    'day_of_week' => '',
    'time_start' => '',
    'time_end' => '',
    'status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'schedule_form')) {
        $errors[] = 'Your session has expired. Please try again.';
    }
    if ($form['sy_id'] === '') $errors[] = 'School Year is required.';
    if ($form['section_id'] === '') $errors[] = 'Section is required.';
    if ($form['subject_id'] === '') $errors[] = 'Subject is required.';
    if ($form['teacher_id'] === '') $errors[] = 'Teacher is required.';
    if ($form['day_of_week'] === '') $errors[] = 'Day is required.';
    if ($form['time_start'] === '') $errors[] = 'Start time is required.';
    if ($form['time_end'] === '') $errors[] = 'End time is required.';

    if (empty($errors)) {
        $conflicts = check_schedule_conflicts($pdo, $form);
        if ($conflicts) {
            $errors = array_merge($errors, $conflicts);
        } else {
            $stmt = $pdo->prepare('INSERT INTO class_schedules
                (sy_id, section_id, subject_id, teacher_id, room_id, day_of_week, time_start, time_end, status)
                VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $form['sy_id'], $form['section_id'], $form['subject_id'], $form['teacher_id'],
                $form['room_id'] ?: null, $form['day_of_week'], $form['time_start'], $form['time_end'], $form['status'],
            ]);
            flash('success', 'Class schedule created successfully.');
            redirect('class_schedule.php');
        }
    }
}

$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$sections = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.status = 'Active' ORDER BY g.grade_level_id, sec.section_name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects WHERE status = 'Active' ORDER BY subject_code")->fetchAll();
$teachers = $pdo->query("SELECT * FROM teachers WHERE status = 'Active' ORDER BY lastname, firstname")->fetchAll();
$rooms    = $pdo->query("SELECT * FROM rooms WHERE status = 'Active' ORDER BY room_name")->fetchAll();

$csrfToken = csrf_token('schedule_form');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Create Class Schedule</h2>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <section class="panel" style="max-width:640px;">
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="form-row">
                <label>School Year *
                    <select name="sy_id" required>
                        <option value="">Select</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?php echo $sy['sy_id']; ?>" <?php echo $form['sy_id'] == $sy['sy_id'] ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Section (Grade &amp; Section) *
                    <select name="section_id" required>
                        <option value="">Select</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['section_id']; ?>" <?php echo $form['section_id'] == $s['section_id'] ? 'selected' : ''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($sections)): ?><small class="field-error">No sections exist yet - ask the Registrar/Administrator to create one first.</small><?php endif; ?>
                </label>
            </div>
            <div class="form-row">
                <label>Subject *
                    <select name="subject_id" required>
                        <option value="">Select</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['subject_id']; ?>" <?php echo $form['subject_id'] == $s['subject_id'] ? 'selected' : ''; ?>><?php echo clean($s['subject_code'] . ' - ' . $s['subject_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($subjects)): ?><small class="field-error">No subjects exist yet - add one in Subject Assignment first.</small><?php endif; ?>
                </label>
                <label>Teacher *
                    <select name="teacher_id" required>
                        <option value="">Select</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['teacher_id']; ?>" <?php echo $form['teacher_id'] == $t['teacher_id'] ? 'selected' : ''; ?>><?php echo clean($t['lastname'] . ', ' . $t['firstname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teachers)): ?><small class="field-error">No teachers exist yet - add one in Teacher Assignment first.</small><?php endif; ?>
                </label>
            </div>
            <div class="form-row">
                <label>Room
                    <select name="room_id">
                        <option value="">N/A</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r['room_id']; ?>" <?php echo $form['room_id'] == $r['room_id'] ? 'selected' : ''; ?>><?php echo clean($r['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Day *
                    <select name="day_of_week" required>
                        <option value="">Select</option>
                        <?php foreach (DAYS_OF_WEEK as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $form['day_of_week'] === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label>Start Time *<input type="time" name="time_start" value="<?php echo clean($form['time_start']); ?>" required></label>
                <label>End Time *<input type="time" name="time_end" value="<?php echo clean($form['time_end']); ?>" required></label>
                <label>Status
                    <select name="status">
                        <option value="Active" <?php echo $form['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Cancelled" <?php echo $form['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Schedule</button>
                <a href="class_schedule.php" class="btn-link">Cancel</a>
            </div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
