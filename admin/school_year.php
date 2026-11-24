<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// System-configuration data - Administrator-only (see sections.php header
// comment for the same reasoning).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'School Years are managed by the Administrator.');
    redirect('dashboard.php');
}

$pageTitle = 'School Year Management';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $sy = trim($_POST['school_year'] ?? '');
        if ($sy === '' || !preg_match('/^\d{4}-\d{4}$/', $sy)) {
            $errors[] = 'Enter a valid school year in the format YYYY-YYYY.';
        } else {
            $pdo->prepare('INSERT INTO school_year (school_year, status, is_current) VALUES (?, "Closed", 0)')->execute([$sy]);
            flash('success', 'School year added.');
            redirect('school_year.php');
        }
    } elseif ($action === 'open') {
        $id = (int)($_POST['sy_id'] ?? 0);
        $pdo->prepare('UPDATE school_year SET status = "Open" WHERE sy_id = ?')->execute([$id]);
        flash('success', 'Enrollment opened for the selected school year.');
        redirect('school_year.php');
    } elseif ($action === 'close') {
        $id = (int)($_POST['sy_id'] ?? 0);
        $pdo->prepare('UPDATE school_year SET status = "Closed" WHERE sy_id = ?')->execute([$id]);
        flash('success', 'Enrollment closed for the selected school year.');
        redirect('school_year.php');
    } elseif ($action === 'set_current') {
        $id = (int)($_POST['sy_id'] ?? 0);
        $pdo->prepare('UPDATE school_year SET is_current = 0')->execute();
        $pdo->prepare('UPDATE school_year SET is_current = 1 WHERE sy_id = ?')->execute([$id]);
        flash('success', 'Current school year updated.');
        redirect('school_year.php');
    }
}

$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>School Year Management</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Add School Year</h3>
        <form method="POST" class="form-inline">
            <input type="hidden" name="action" value="add">
            <input type="text" name="school_year" placeholder="e.g. 2027-2028" required>
            <button type="submit" class="btn-primary">Add</button>
        </form>
    </section>

    <table class="data-table">
        <thead><tr><th>School Year</th><th>Enrollment Status</th><th>Current</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($schoolYears as $sy): ?>
            <tr>
                <td><?php echo clean($sy['school_year']); ?></td>
                <td><?php echo $sy['status'] === 'Open' ? '<span class="badge badge-approved">Open</span>' : '<span class="badge badge-rejected">Closed</span>'; ?></td>
                <td><?php echo $sy['is_current'] ? '&#9733; Current' : '-'; ?></td>
                <td class="actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="sy_id" value="<?php echo $sy['sy_id']; ?>">
                        <?php if ($sy['status'] === 'Open'): ?>
                            <button type="submit" name="action" value="close" class="link-danger">Close Enrollment</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="open" class="link-success">Open Enrollment</button>
                        <?php endif; ?>
                    </form>
                    <?php if (!$sy['is_current']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="set_current">
                            <input type="hidden" name="sy_id" value="<?php echo $sy['sy_id']; ?>">
                            <button type="submit" class="link-plain">Set as Current</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
