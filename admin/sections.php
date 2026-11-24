<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Section entities (create/edit/delete, adviser assignment, capacity) are
// system-configuration data tied to scheduling infrastructure, so this
// stays Administrator-only. The Registrar's day-to-day equivalent is
// section_assignment.php, which assigns students into sections that
// already exist here - it does not need write access to this page.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Sections are managed by the Administrator. Use Section Assignment to place students into existing sections.');
    redirect('dashboard.php');
}

$pageTitle = 'Sections';
$errors = [];
$editSection = null;

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$strands     = $pdo->query('SELECT * FROM strands ORDER BY strand_name')->fetchAll();
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$teachers    = $pdo->query("SELECT * FROM teachers WHERE status = 'Active' ORDER BY lastname, firstname")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['section_name'] ?? '');
    $gradeLevelId = $_POST['grade_level_id'] ?? '';
    $strandId = $_POST['strand_id'] ?? '';
    $syId = $_POST['sy_id'] ?? '';
    $adviserId = $_POST['adviser_teacher_id'] ?? '';
    $capacity = trim($_POST['capacity'] ?? '');

    $isSHS = false;
    foreach ($gradeLevels as $g) {
        if ($g['grade_level_id'] == $gradeLevelId && $g['level_type'] === 'Senior High School') $isSHS = true;
    }
    if (!$isSHS) $strandId = '';

    if ($action === 'add') {
        if ($name === '' || $gradeLevelId === '' || $syId === '') {
            $errors[] = 'Section name, grade level, and school year are required.';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE section_name = ? AND grade_level_id = ? AND sy_id = ?');
            $dup->execute([$name, $gradeLevelId, $syId]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = 'That section already exists for this grade level and school year.';
            } else {
                $pdo->prepare('INSERT INTO sections (section_name, grade_level_id, strand_id, sy_id, adviser_teacher_id, capacity) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $gradeLevelId, $strandId ?: null, $syId, $adviserId ?: null, $capacity !== '' ? (int) $capacity : null]);
                flash('success', 'Section added.');
                redirect('sections.php');
            }
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['section_id'] ?? 0);
        if ($name === '' || $gradeLevelId === '' || $syId === '') {
            $errors[] = 'Section name, grade level, and school year are required.';
        } else {
            $pdo->prepare('UPDATE sections SET section_name=?, grade_level_id=?, strand_id=?, sy_id=?, adviser_teacher_id=?, capacity=? WHERE section_id=?')
                ->execute([$name, $gradeLevelId, $strandId ?: null, $syId, $adviserId ?: null, $capacity !== '' ? (int) $capacity : null, $id]);
            flash('success', 'Section updated.');
            redirect('sections.php');
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) ($_POST['section_id'] ?? 0);
        $pdo->prepare("UPDATE sections SET status = IF(status='Active','Inactive','Active') WHERE section_id = ?")->execute([$id]);
        redirect('sections.php');
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM sections WHERE section_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editSection = $stmt->fetch();
}

$sections = $pdo->query("SELECT sec.*, g.grade_name, st.strand_name, sy.school_year,
        CONCAT(t.firstname, ' ', t.lastname) AS adviser_name,
        (SELECT COUNT(*) FROM students s WHERE s.section_id = sec.section_id) AS student_count
    FROM sections sec
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    LEFT JOIN strands st ON st.strand_id = sec.strand_id
    LEFT JOIN school_year sy ON sy.sy_id = sec.sy_id
    LEFT JOIN teachers t ON t.teacher_id = sec.adviser_teacher_id
    ORDER BY sy.school_year DESC, g.grade_level_id, sec.section_name")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Sections</h2>
    <p class="muted">Sections are the homeroom groups students are enrolled into (e.g. "Grade 8 - Bonifacio"). Once a student is assigned to a section (via Student Management), the Academic Coordinator's class schedule for that section automatically appears in the student's portal.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <section class="panel">
        <h3><?php echo $editSection ? 'Edit Section' : 'Add Section'; ?></h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="<?php echo $editSection ? 'update' : 'add'; ?>">
            <?php if ($editSection): ?><input type="hidden" name="section_id" value="<?php echo $editSection['section_id']; ?>"><?php endif; ?>
            <div class="form-row">
                <label>Section Name *<input type="text" name="section_name" placeholder="e.g. Rizal" value="<?php echo clean($editSection['section_name'] ?? ''); ?>" required></label>
                <label>Grade Level *
                    <select name="grade_level_id" required>
                        <option value="">Select</option>
                        <?php foreach ($gradeLevels as $g): ?>
                            <option value="<?php echo $g['grade_level_id']; ?>" <?php echo (($editSection['grade_level_id'] ?? '') == $g['grade_level_id']) ? 'selected' : ''; ?>><?php echo clean($g['grade_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Strand (Senior High only)
                    <select name="strand_id">
                        <option value="">N/A</option>
                        <?php foreach ($strands as $s): ?>
                            <option value="<?php echo $s['strand_id']; ?>" <?php echo (($editSection['strand_id'] ?? '') == $s['strand_id']) ? 'selected' : ''; ?>><?php echo clean($s['strand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label>School Year *
                    <select name="sy_id" required>
                        <option value="">Select</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?php echo $sy['sy_id']; ?>" <?php echo (($editSection['sy_id'] ?? '') == $sy['sy_id']) ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Adviser
                    <select name="adviser_teacher_id">
                        <option value="">N/A</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['teacher_id']; ?>" <?php echo (($editSection['adviser_teacher_id'] ?? '') == $t['teacher_id']) ? 'selected' : ''; ?>><?php echo clean($t['lastname'] . ', ' . $t['firstname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted">Teachers are managed by the Academic Coordinator in Academic Management.</small>
                </label>
                <label>Capacity<input type="number" name="capacity" min="1" value="<?php echo clean($editSection['capacity'] ?? ''); ?>"></label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><?php echo $editSection ? 'Update Section' : 'Add Section'; ?></button>
                <?php if ($editSection): ?><a href="sections.php" class="btn-link">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <table class="data-table">
        <thead><tr><th>Grade &amp; Section</th><th>Strand</th><th>School Year</th><th>Adviser</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($sections)): ?><tr><td colspan="7" class="muted" style="text-align:center;">No sections yet.</td></tr><?php endif; ?>
        <?php foreach ($sections as $s): ?>
            <tr>
                <td><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></td>
                <td><?php echo clean($s['strand_name'] ?: '-'); ?></td>
                <td><?php echo clean($s['school_year'] ?: '-'); ?></td>
                <td><?php echo clean($s['adviser_name'] ?: '-'); ?></td>
                <td><?php echo (int) $s['student_count']; ?></td>
                <td><span class="badge badge-<?php echo strtolower($s['status']); ?>"><?php echo clean($s['status']); ?></span></td>
                <td class="actions">
                    <a href="?edit=<?php echo $s['section_id']; ?>">Edit</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="section_id" value="<?php echo $s['section_id']; ?>">
                        <button type="submit" class="btn-small"><?php echo $s['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
