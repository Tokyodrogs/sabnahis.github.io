<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Teacher Assignment';
$errors = [];

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'teacher_assignment')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($action === 'add_teacher') {
        $lastname = trim($_POST['lastname'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $middlename = trim($_POST['middlename'] ?? '');
        $employeeNo = trim($_POST['employee_no'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        if ($lastname === '' || $firstname === '') {
            $errors[] = 'Teacher last name and first name are required.';
        } else {
            $pdo->prepare('INSERT INTO teachers (employee_no, lastname, firstname, middlename, email, contact) VALUES (?,?,?,?,?,?)')
                ->execute([$employeeNo ?: null, $lastname, $firstname, $middlename ?: null, $email ?: null, $contact ?: null]);
            flash('success', 'Teacher added to the roster.');
            redirect('teacher_assignment.php');
        }
    } elseif ($action === 'update_teacher') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $lastname = trim($_POST['lastname'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $middlename = trim($_POST['middlename'] ?? '');
        $employeeNo = trim($_POST['employee_no'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        if ($teacherId <= 0 || $lastname === '' || $firstname === '') {
            $errors[] = 'Teacher last name and first name are required.';
            $editTeacherIdOnError = $teacherId;
        } else {
            $pdo->prepare('UPDATE teachers SET lastname = ?, firstname = ?, middlename = ?, employee_no = ?, email = ?, contact = ? WHERE teacher_id = ?')
                ->execute([$lastname, $firstname, $middlename ?: null, $employeeNo ?: null, $email ?: null, $contact ?: null, $teacherId]);
            flash('success', 'Teacher details updated.');
            redirect('teacher_assignment.php');
        }
    } elseif ($action === 'toggle_teacher_status') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $pdo->prepare("UPDATE teachers SET status = IF(status='Active','Inactive','Active') WHERE teacher_id = ?")->execute([$teacherId]);
        redirect('teacher_assignment.php');
    } elseif ($action === 'assign') {
        $teacherId = $_POST['teacher_id'] ?? '';
        $subjectId = $_POST['subject_id'] ?? '';
        $sectionId = $_POST['section_id'] ?? '';
        $syId = $_POST['sy_id'] ?? '';

        if ($teacherId === '' || $subjectId === '' || $sectionId === '' || $syId === '') {
            $errors[] = 'Teacher, Subject, Section, and School Year are all required.';
        } elseif (teacher_assignment_exists($pdo, $teacherId, $subjectId, $sectionId, $syId)) {
            $errors[] = 'This teacher is already assigned to that subject for that section and school year (duplicate assignment).';
        } else {
            $pdo->prepare('INSERT INTO teacher_assignments (teacher_id, subject_id, section_id, sy_id) VALUES (?,?,?,?)')
                ->execute([$teacherId, $subjectId, $sectionId, $syId]);
            flash('success', 'Teacher assigned successfully.');
            redirect('teacher_assignment.php');
        }
    } elseif ($action === 'unassign') {
        $pdo->prepare('DELETE FROM teacher_assignments WHERE teacher_assignment_id = ?')->execute([(int) $_POST['teacher_assignment_id']]);
        flash('success', 'Assignment removed.');
        redirect('teacher_assignment.php');
    }
}

$teachers = $pdo->query('SELECT * FROM teachers ORDER BY lastname, firstname')->fetchAll();

$editTeacherId = $_GET['edit'] ?? (isset($editTeacherIdOnError) ? $editTeacherIdOnError : '');
$editTeacher = null;
if ($editTeacherId !== '') {
    foreach ($teachers as $t) {
        if ($t['teacher_id'] == $editTeacherId) { $editTeacher = $t; break; }
    }
}
$subjects = $pdo->query("SELECT * FROM subjects WHERE status = 'Active' ORDER BY subject_code")->fetchAll();
$sections = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.status = 'Active' ORDER BY g.grade_level_id, sec.section_name")->fetchAll();
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();

$assignments = $pdo->query("SELECT ta.*, CONCAT(t.lastname, ', ', t.firstname) AS teacher_name,
        sub.subject_code, sub.subject_title, sec.section_name, g.grade_name, sy.school_year
    FROM teacher_assignments ta
    JOIN teachers t ON t.teacher_id = ta.teacher_id
    JOIN subjects sub ON sub.subject_id = ta.subject_id
    JOIN sections sec ON sec.section_id = ta.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    JOIN school_year sy ON sy.sy_id = ta.sy_id
    ORDER BY sy.school_year DESC, t.lastname")->fetchAll();

$viewLoadTeacherId = $_GET['view_load'] ?? '';
$teachingLoad = [];
$loadTeacherName = '';
if ($viewLoadTeacherId !== '' && $currentSY) {
    $teachingLoad = get_teaching_load($pdo, $viewLoadTeacherId, $currentSY['sy_id']);
    foreach ($teachers as $t) {
        if ($t['teacher_id'] == $viewLoadTeacherId) { $loadTeacherName = $t['lastname'] . ', ' . $t['firstname']; }
    }
}

$csrfToken = csrf_token('teacher_assignment');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Teacher Assignment</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <?php if ($viewLoadTeacherId !== ''): ?>
        <section class="panel">
            <div class="panel-header">
                <h3>Teaching Load: <?php echo clean($loadTeacherName); ?> (<?php echo clean($currentSY['school_year'] ?? '-'); ?>)</h3>
                <a href="teacher_assignment.php" class="btn-small">Close</a>
            </div>
            <?php if (empty($teachingLoad)): ?>
                <p class="muted">No scheduled class periods for this teacher yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Grade &amp; Section</th><th>Room</th></tr></thead>
                    <tbody>
                    <?php foreach ($teachingLoad as $l): ?>
                        <tr>
                            <td><?php echo clean($l['day_of_week']); ?></td>
                            <td><?php echo clean(format_time12($l['time_start']) . ' - ' . format_time12($l['time_end'])); ?></td>
                            <td><?php echo clean($l['subject_code'] . ' - ' . $l['subject_title']); ?></td>
                            <td><?php echo clean(($l['grade_name'] ?: '-') . ' - ' . $l['section_name']); ?></td>
                            <td><?php echo clean($l['room_name'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h3><?php echo $editTeacher ? 'Edit Teacher' : 'Teacher Roster'; ?></h3>
        <form method="POST" class="form-grid" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="<?php echo $editTeacher ? 'update_teacher' : 'add_teacher'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <?php if ($editTeacher): ?>
                <input type="hidden" name="teacher_id" value="<?php echo $editTeacher['teacher_id']; ?>">
            <?php endif; ?>
            <div class="form-row">
                <label>Last Name *<input type="text" name="lastname" value="<?php echo clean($editTeacher['lastname'] ?? ''); ?>" required></label>
                <label>First Name *<input type="text" name="firstname" value="<?php echo clean($editTeacher['firstname'] ?? ''); ?>" required></label>
                <label>Middle Name<input type="text" name="middlename" value="<?php echo clean($editTeacher['middlename'] ?? ''); ?>"></label>
                <label>Employee No.<input type="text" name="employee_no" value="<?php echo clean($editTeacher['employee_no'] ?? ''); ?>"></label>
            </div>
            <div class="form-row">
                <label>Email<input type="email" name="email" value="<?php echo clean($editTeacher['email'] ?? ''); ?>"></label>
                <label>Contact<input type="text" name="contact" value="<?php echo clean($editTeacher['contact'] ?? ''); ?>"></label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><?php echo $editTeacher ? 'Update Teacher' : 'Add Teacher'; ?></button>
                <?php if ($editTeacher): ?><a href="teacher_assignment.php" class="btn-small">Cancel</a><?php endif; ?>
            </div>
        </form>

        <table class="data-table">
            <thead><tr><th>Name</th><th>Employee No.</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($teachers)): ?><tr><td colspan="5" class="muted" style="text-align:center;">No teachers in the roster yet.</td></tr><?php endif; ?>
            <?php foreach ($teachers as $t): ?>
                <tr<?php echo ($editTeacher && $editTeacher['teacher_id'] == $t['teacher_id']) ? ' class="row-editing"' : ''; ?>>
                    <td><?php echo clean($t['lastname'] . ', ' . $t['firstname'] . ' ' . $t['middlename']); ?></td>
                    <td><?php echo clean($t['employee_no'] ?: '-'); ?></td>
                    <td><?php echo clean($t['contact'] ?: '-'); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($t['status']); ?>"><?php echo clean($t['status']); ?></span></td>
                    <td class="actions">
                        <a href="teacher_assignment.php?edit=<?php echo $t['teacher_id']; ?>">Edit</a>
                        <a href="teacher_assignment.php?view_load=<?php echo $t['teacher_id']; ?>">View Load</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_teacher_status">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['teacher_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                            <button type="submit" class="btn-small"><?php echo $t['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h3>Assign Teacher to Subject &amp; Section</h3>
        <form method="POST" class="form-grid" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="form-row">
                <label>School Year *
                    <select name="sy_id" required>
                        <option value="">Select</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?php echo $sy['sy_id']; ?>" <?php echo (!empty($currentSY) && $currentSY['sy_id']==$sy['sy_id']) ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Teacher *
                    <select name="teacher_id" required>
                        <option value="">Select</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['teacher_id']; ?>"><?php echo clean($t['lastname'] . ', ' . $t['firstname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label>Subject *
                    <select name="subject_id" required>
                        <option value="">Select</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['subject_id']; ?>"><?php echo clean($s['subject_code'] . ' - ' . $s['subject_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Section *
                    <select name="section_id" required>
                        <option value="">Select</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['section_id']; ?>"><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Assign</button></div>
        </form>

        <table class="data-table">
            <thead><tr><th>Teacher</th><th>Subject</th><th>Grade &amp; Section</th><th>School Year</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($assignments)): ?><tr><td colspan="5" class="muted" style="text-align:center;">No teacher assignments yet.</td></tr><?php endif; ?>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?php echo clean($a['teacher_name']); ?></td>
                    <td><?php echo clean($a['subject_code'] . ' - ' . $a['subject_title']); ?></td>
                    <td><?php echo clean(($a['grade_name'] ?: '-') . ' - ' . $a['section_name']); ?></td>
                    <td><?php echo clean($a['school_year']); ?></td>
                    <td class="actions">
                        <form method="POST" onsubmit="return confirm('Remove this teacher assignment?');">
                            <input type="hidden" name="action" value="unassign">
                            <input type="hidden" name="teacher_assignment_id" value="<?php echo $a['teacher_assignment_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                            <button type="submit" class="danger link-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
