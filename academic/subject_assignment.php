<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Subject Assignment';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'subject_assignment')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($action === 'add_subject') {
        $code = trim($_POST['subject_code'] ?? '');
        $title = trim($_POST['subject_title'] ?? '');
        $units = trim($_POST['units'] ?? '');
        $levelType = $_POST['level_type'] ?? 'Both';
        $gradeLevelIds = $_POST['grade_level_ids'] ?? [];

        if ($code === '' || $title === '') {
            $errors[] = 'Subject code and title are required.';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE subject_code = ?');
            $dup->execute([$code]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = 'A subject with that code already exists.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO subjects (subject_code, subject_title, units, level_type) VALUES (?,?,?,?)')
                    ->execute([$code, $title, $units !== '' ? $units : 0, $levelType]);
                $subjectId = $pdo->lastInsertId();
                foreach ($gradeLevelIds as $glId) {
                    $pdo->prepare('INSERT IGNORE INTO subject_grade_levels (subject_id, grade_level_id) VALUES (?,?)')
                        ->execute([$subjectId, (int) $glId]);
                }
                $pdo->commit();
                flash('success', 'Subject added.');
                redirect('subject_assignment.php');
            }
        }
    } elseif ($action === 'toggle_subject_status') {
        $pdo->prepare("UPDATE subjects SET status = IF(status='Active','Inactive','Active') WHERE subject_id = ?")->execute([(int) $_POST['subject_id']]);
        redirect('subject_assignment.php');
    } elseif ($action === 'update_grade_levels') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $gradeLevelIds = $_POST['grade_level_ids'] ?? [];
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM subject_grade_levels WHERE subject_id = ?')->execute([$subjectId]);
        foreach ($gradeLevelIds as $glId) {
            $pdo->prepare('INSERT IGNORE INTO subject_grade_levels (subject_id, grade_level_id) VALUES (?,?)')->execute([$subjectId, (int) $glId]);
        }
        $pdo->commit();
        flash('success', 'Grade level assignment updated.');
        redirect('subject_assignment.php');
    }
}

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$subjects = $pdo->query('SELECT * FROM subjects ORDER BY subject_code')->fetchAll();

// Which grade levels each subject is assigned to, and which teachers teach it (this SY).
$subjectGradeMap = [];
foreach ($pdo->query('SELECT subject_id, grade_level_id FROM subject_grade_levels') as $row) {
    $subjectGradeMap[$row['subject_id']][] = $row['grade_level_id'];
}

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$teachersBySubject = [];
if ($currentSY) {
    $taStmt = $pdo->prepare("SELECT ta.subject_id, CONCAT(t.firstname, ' ', t.lastname) AS teacher_name, sec.section_name
        FROM teacher_assignments ta
        JOIN teachers t ON t.teacher_id = ta.teacher_id
        JOIN sections sec ON sec.section_id = ta.section_id
        WHERE ta.sy_id = ?");
    $taStmt->execute([$currentSY['sy_id']]);
    foreach ($taStmt->fetchAll() as $row) {
        $teachersBySubject[$row['subject_id']][] = $row['teacher_name'] . ' (' . $row['section_name'] . ')';
    }
}

$csrfToken = csrf_token('subject_assignment');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Subject Assignment</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <section class="panel">
        <h3>Add Subject</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="add_subject">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="form-row">
                <label>Subject Code *<input type="text" name="subject_code" placeholder="e.g. MATH7" required></label>
                <label>Subject Title *<input type="text" name="subject_title" placeholder="e.g. Mathematics 7" required></label>
                <label>Units<input type="number" step="0.5" name="units" value="1"></label>
                <label>Level
                    <select name="level_type">
                        <option value="Both">Junior &amp; Senior High</option>
                        <option value="Junior High School">Junior High School</option>
                        <option value="Senior High School">Senior High School</option>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label class="full">Offered to Grade Levels
                    <span class="form-checkbox-group">
                    <?php foreach ($gradeLevels as $g): ?>
                        <label class="checkbox-label"><input type="checkbox" name="grade_level_ids[]" value="<?php echo $g['grade_level_id']; ?>"> <?php echo clean($g['grade_name']); ?></label>
                    <?php endforeach; ?>
                    </span>
                </label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Subject</button></div>
        </form>
    </section>

    <section class="panel">
        <h3>Subjects Catalog</h3>
        <table class="data-table">
            <thead><tr><th>Code</th><th>Title</th><th>Units</th><th>Level</th><th>Grade Levels</th><th>Teachers (this SY)</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($subjects)): ?><tr><td colspan="8" class="muted" style="text-align:center;">No subjects yet.</td></tr><?php endif; ?>
            <?php foreach ($subjects as $s): ?>
                <?php $assignedGrades = $subjectGradeMap[$s['subject_id']] ?? []; ?>
                <tr>
                    <td><?php echo clean($s['subject_code']); ?></td>
                    <td><?php echo clean($s['subject_title']); ?></td>
                    <td><?php echo clean(rtrim(rtrim(number_format($s['units'], 1), '0'), '.')); ?></td>
                    <td><?php echo clean($s['level_type']); ?></td>
                    <td>
                        <form method="POST" class="form-checkbox-group">
                            <input type="hidden" name="action" value="update_grade_levels">
                            <input type="hidden" name="subject_id" value="<?php echo $s['subject_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                            <?php foreach ($gradeLevels as $g): ?>
                                <label class="checkbox-label"><input type="checkbox" name="grade_level_ids[]" value="<?php echo $g['grade_level_id']; ?>" onchange="this.form.requestSubmit()" <?php echo in_array($g['grade_level_id'], $assignedGrades) ? 'checked' : ''; ?>> <?php echo clean($g['grade_name']); ?></label>
                            <?php endforeach; ?>
                        </form>
                    </td>
                    <td><?php echo clean(!empty($teachersBySubject[$s['subject_id']]) ? implode(', ', $teachersBySubject[$s['subject_id']]) : '-'); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($s['status']); ?>"><?php echo clean($s['status']); ?></span></td>
                    <td class="actions">
                        <form method="POST">
                            <input type="hidden" name="action" value="toggle_subject_status">
                            <input type="hidden" name="subject_id" value="<?php echo $s['subject_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                            <button type="submit" class="btn-small"><?php echo $s['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
