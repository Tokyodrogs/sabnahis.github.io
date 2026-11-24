<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// System-configuration data - Administrator-only (see sections.php header
// comment for the same reasoning).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Strands are managed by the Administrator.');
    redirect('dashboard.php');
}

$pageTitle = 'Strand Management';
$errors = [];
$editStrand = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['strand_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $errors[] = 'Strand name is required.';
        } else {
            $pdo->prepare('INSERT INTO strands (strand_name, description) VALUES (?, ?)')->execute([$name, $desc]);
            flash('success', 'Strand added.');
            redirect('strands.php');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['strand_id'] ?? 0);
        $name = trim($_POST['strand_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $errors[] = 'Strand name is required.';
        } else {
            $pdo->prepare('UPDATE strands SET strand_name = ?, description = ? WHERE strand_id = ?')->execute([$name, $desc, $id]);
            flash('success', 'Strand updated.');
            redirect('strands.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['strand_id'] ?? 0);
        $pdo->prepare('DELETE FROM strands WHERE strand_id = ?')->execute([$id]);
        flash('success', 'Strand deleted.');
        redirect('strands.php');
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM strands WHERE strand_id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editStrand = $stmt->fetch();
}

$strands = $pdo->query('SELECT st.*, (SELECT COUNT(*) FROM students s WHERE s.strand_id = st.strand_id) as student_count
                         FROM strands st ORDER BY st.strand_name')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Strand Management</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <section class="panel">
        <h3><?php echo $editStrand ? 'Edit Strand' : 'Add Strand'; ?></h3>
        <form method="POST" class="form-inline">
            <input type="hidden" name="action" value="<?php echo $editStrand ? 'update' : 'add'; ?>">
            <?php if ($editStrand): ?><input type="hidden" name="strand_id" value="<?php echo $editStrand['strand_id']; ?>"><?php endif; ?>
            <input type="text" name="strand_name" placeholder="Strand name" value="<?php echo clean($editStrand['strand_name'] ?? ''); ?>" required>
            <input type="text" name="description" placeholder="Description" value="<?php echo clean($editStrand['description'] ?? ''); ?>">
            <button type="submit" class="btn-primary"><?php echo $editStrand ? 'Update' : 'Add'; ?></button>
            <?php if ($editStrand): ?><a href="strands.php" class="btn-link">Cancel</a><?php endif; ?>
        </form>
    </section>

    <table class="data-table">
        <thead><tr><th>Strand</th><th>Description</th><th>No. of Students</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($strands as $s): ?>
            <tr>
                <td><?php echo clean($s['strand_name']); ?></td>
                <td><?php echo clean($s['description']); ?></td>
                <td><?php echo $s['student_count']; ?></td>
                <td class="actions">
                    <a href="?edit=<?php echo $s['strand_id']; ?>">Edit</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this strand?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="strand_id" value="<?php echo $s['strand_id']; ?>">
                        <button type="submit" class="link-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
