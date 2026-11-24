<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// System-configuration data - Administrator-only (see sections.php header
// comment for the same reasoning).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Grade Levels are managed by the Administrator.');
    redirect('dashboard.php');
}

$pageTitle = 'Grade Level Management';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['grade_name'] ?? '');
        $type = $_POST['level_type'] ?? '';
        if ($name === '' || $type === '') {
            $errors[] = 'Grade name and level type are required.';
        } else {
            $pdo->prepare('INSERT INTO grade_levels (grade_name, level_type) VALUES (?, ?)')->execute([$name, $type]);
            flash('success', 'Grade level added.');
            redirect('grade_levels.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['grade_level_id'] ?? 0);
        $pdo->prepare('DELETE FROM grade_levels WHERE grade_level_id = ?')->execute([$id]);
        flash('success', 'Grade level deleted.');
        redirect('grade_levels.php');
    }
}

$gradeLevels = $pdo->query('SELECT g.*, (SELECT COUNT(*) FROM students s WHERE s.grade_level_id = g.grade_level_id) as student_count
                             FROM grade_levels g ORDER BY g.grade_level_id')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Grade Level Management</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Add Grade Level</h3>
        <form method="POST" class="form-inline">
            <input type="hidden" name="action" value="add">
            <input type="text" name="grade_name" placeholder="e.g. Grade 11" required>
            <select name="level_type" required>
                <option value="">Select Level</option>
                <option value="Junior High School">Junior High School</option>
                <option value="Senior High School">Senior High School</option>
            </select>
            <button type="submit" class="btn-primary">Add</button>
        </form>
    </section>

    <table class="data-table">
        <thead><tr><th>Grade Name</th><th>Level Type</th><th>No. of Students</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($gradeLevels as $g): ?>
            <tr>
                <td><?php echo clean($g['grade_name']); ?></td>
                <td><?php echo clean($g['level_type']); ?></td>
                <td><?php echo $g['student_count']; ?></td>
                <td>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this grade level?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="grade_level_id" value="<?php echo $g['grade_level_id']; ?>">
                        <button type="submit" class="link-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
