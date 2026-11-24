<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Assigning a student to an existing section - NOT scheduling. Section
// entities themselves are managed on sections.php; class timetables are
// built by the Academic Coordinator in the separate Academic Management
// portal. This page only links a student_id to a section_id, enforcing
// capacity, so admin/sections.php's "capacity" field is actually honored.
$pageTitle = 'Section Assignment';
$errors = [];

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$syId = $currentSY['sy_id'] ?? 0;

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$gradeFilter = $_GET['grade_level_id'] ?? ($gradeLevels[0]['grade_level_id'] ?? '');
$showAllStudents = ($_GET['show'] ?? '') === 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    if (!csrf_verify($_POST['csrf_token'] ?? '', 'section_assignment')) {
        $errors[] = 'Your session has expired. Please try again.';
    } else {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0);

        $secStmt = $pdo->prepare('SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.section_id = ?');
        $secStmt->execute([$sectionId]);
        $section = $secStmt->fetch();

        $stuStmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
        $stuStmt->execute([$studentId]);
        $targetStudent = $stuStmt->fetch();

        if (!$section || !$targetStudent) {
            $errors[] = 'Student or section not found.';
        } else {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE section_id = ? AND is_archived = 0 AND student_id != ?');
            $countStmt->execute([$sectionId, $studentId]);
            $currentCount = (int) $countStmt->fetchColumn();

            if ($section['capacity'] !== null && $currentCount >= (int) $section['capacity']) {
                $errors[] = "Cannot assign - {$section['section_name']} is already at full capacity ({$currentCount}/{$section['capacity']}).";
            } else {
                $pdo->prepare('UPDATE students SET section_id = ? WHERE student_id = ?')->execute([$sectionId, $studentId]);

                // "Automatically update enrollment records after assigning a
                // section" - log it against the student's enrollment history.
                $pdo->prepare('INSERT INTO enrollment (student_id, status, remarks) VALUES (?, ?, ?)')
                    ->execute([$studentId, $targetStudent['status'], 'Assigned to section: ' . ($section['grade_name'] ?: '-') . ' - ' . $section['section_name']]);

                notify_student($pdo, $studentId, 'section',
                    'You have been assigned to section ' . ($section['grade_name'] ?: '-') . ' - ' . $section['section_name'] . '.',
                    'section', $sectionId);

                flash('success', "{$targetStudent['lastname']}, {$targetStudent['firstname']} assigned to {$section['section_name']}.");
                redirect('section_assignment.php?grade_level_id=' . $gradeFilter . ($showAllStudents ? '&show=all' : ''));
            }
        }
    }
}

// ---- Sections + current/max capacity for the selected grade level ----
$sections = [];
if ($gradeFilter !== '') {
    $secStmt = $pdo->prepare("SELECT sec.*, CONCAT(t.firstname, ' ', t.lastname) AS adviser_name,
            (SELECT COUNT(*) FROM students s WHERE s.section_id = sec.section_id AND s.is_archived = 0) AS current_count
        FROM sections sec
        LEFT JOIN teachers t ON t.teacher_id = sec.adviser_teacher_id
        WHERE sec.grade_level_id = ? AND sec.sy_id = ? AND sec.status = 'Active'
        ORDER BY sec.section_name");
    $secStmt->execute([$gradeFilter, $syId]);
    $sections = $secStmt->fetchAll();
}

// ---- Students in this grade level (current SY), optionally all vs. unassigned-only ----
$students = [];
if ($gradeFilter !== '') {
    $sql = "SELECT s.*, sec.section_name FROM students s
        LEFT JOIN sections sec ON sec.section_id = s.section_id
        WHERE s.grade_level_id = ? AND s.sy_id = ? AND s.is_archived = 0";
    if (!$showAllStudents) $sql .= ' AND s.section_id IS NULL';
    $sql .= ' ORDER BY s.lastname, s.firstname';
    $stuListStmt = $pdo->prepare($sql);
    $stuListStmt->execute([$gradeFilter, $syId]);
    $students = $stuListStmt->fetchAll();
}

$csrfToken = csrf_token('section_assignment');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Section Assignment</h2>
    <p class="muted">School Year: <strong><?php echo clean($currentSY['school_year'] ?? 'Not set'); ?></strong>. Sections themselves are created on the Sections page - this assigns individual students to an existing section and enforces its capacity.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <form method="GET" class="filter-bar">
        <label style="display:flex;flex-direction:column;font-size:13px;font-weight:600;">Grade Level
            <select name="grade_level_id" onchange="this.form.submit()">
                <?php foreach ($gradeLevels as $g): ?>
                    <option value="<?php echo $g['grade_level_id']; ?>" <?php echo (string) $gradeFilter === (string) $g['grade_level_id'] ? 'selected' : ''; ?>><?php echo clean($g['grade_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox-label" style="margin-top:20px;">
            <input type="checkbox" name="show" value="all" <?php echo $showAllStudents ? 'checked' : ''; ?> onchange="this.form.submit()"> Show all students (not just unassigned)
        </label>
    </form>

    <section class="panel">
        <h3>Available Sections</h3>
        <table class="data-table">
            <thead><tr><th>Section</th><th>Adviser</th><th>Current / Max Capacity</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($sections)): ?><tr><td colspan="4" class="muted" style="text-align:center;">No sections exist for this grade level and school year yet. Create one on the Sections page.</td></tr><?php endif; ?>
            <?php foreach ($sections as $s): ?>
                <?php $full = $s['capacity'] !== null && $s['current_count'] >= $s['capacity']; ?>
                <tr>
                    <td><?php echo clean($s['section_name']); ?></td>
                    <td><?php echo clean($s['adviser_name'] ?: '-'); ?></td>
                    <td><?php echo (int) $s['current_count']; ?> / <?php echo $s['capacity'] !== null ? (int) $s['capacity'] : 'No limit'; ?></td>
                    <td><span class="badge badge-<?php echo $full ? 'rejected' : 'approved'; ?>"><?php echo $full ? 'Full' : 'Open'; ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h3><?php echo $showAllStudents ? 'All Students in This Grade Level' : 'Students Needing Section Assignment'; ?></h3>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Status</th><th>Current Section</th><th>Assign To</th></tr></thead>
            <tbody>
            <?php if (empty($students)): ?><tr><td colspan="4" class="muted" style="text-align:center;">No students to show.</td></tr><?php endif; ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?php echo clean($s['lastname'] . ', ' . $s['firstname']); ?></td>
                    <td><?php echo status_badge($s['status']); ?></td>
                    <td><?php echo clean($s['section_name'] ?: 'Not yet assigned'); ?></td>
                    <td>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="assign">
                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                            <select name="section_id" required>
                                <option value="">Select section</option>
                                <?php foreach ($sections as $sec): ?>
                                    <?php $full = $sec['capacity'] !== null && $sec['current_count'] >= $sec['capacity'] && $sec['section_id'] != $s['section_id']; ?>
                                    <option value="<?php echo $sec['section_id']; ?>" <?php echo $full ? 'disabled' : ''; ?> <?php echo $sec['section_id'] == $s['section_id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($sec['section_name']) . ($full ? ' (Full)' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-small">Assign</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
