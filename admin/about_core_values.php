<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// About SABNAHIS module (2026-08) - Core Values CRUD (e.g. Makadiyos,
// Makatao, Makakalikasan, Makabansa). Administrator-only.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can manage the About SABNAHIS page.');
    redirect('dashboard.php');
}

$pageTitle = 'About SABNAHIS - Core Values';

$aboutTableMissing = false;
try {
    $pdo->query('SELECT 1 FROM about_core_values LIMIT 1');
} catch (PDOException $e) {
    $aboutTableMissing = true;
}

if (!$aboutTableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $icon = trim($_POST['icon'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            flash('error', 'Title is required.');
        } elseif ($action === 'add') {
            $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM about_core_values')->fetchColumn();
            $pdo->prepare('INSERT INTO about_core_values (icon, title, description, display_order) VALUES (?, ?, ?, ?)')
                ->execute([$icon, $title, $description, $maxOrder + 1]);
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_core_value_add', "Added core value: $title");
            flash('success', 'Core value added.');
        } else {
            $id = (int) ($_POST['core_value_id'] ?? 0);
            $pdo->prepare('UPDATE about_core_values SET icon = ?, title = ?, description = ? WHERE core_value_id = ?')
                ->execute([$icon, $title, $description, $id]);
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_core_value_edit', "Edited core value #$id: $title");
            flash('success', 'Core value updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['core_value_id'] ?? 0);
        $pdo->prepare('DELETE FROM about_core_values WHERE core_value_id = ?')->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_core_value_delete', "Deleted core value #$id");
        flash('success', 'Core value deleted.');
    } elseif ($action === 'archive' || $action === 'restore') {
        $id = (int) ($_POST['core_value_id'] ?? 0);
        $newStatus = $action === 'restore' ? 'Active' : 'Archived';
        $pdo->prepare('UPDATE about_core_values SET status = ? WHERE core_value_id = ?')->execute([$newStatus, $id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_core_value_' . $action, "Core value #$id");
        flash('success', $action === 'restore' ? 'Core value restored.' : 'Core value archived.');
    } elseif ($action === 'move') {
        $id = (int) ($_POST['core_value_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $rows = $pdo->query('SELECT core_value_id, display_order FROM about_core_values ORDER BY display_order, core_value_id')->fetchAll();
        foreach ($rows as $i => $r) {
            if ((int) $r['core_value_id'] === $id) {
                $swapWith = $direction === 'up' ? $i - 1 : $i + 1;
                if (isset($rows[$swapWith])) {
                    $pdo->prepare('UPDATE about_core_values SET display_order = ? WHERE core_value_id = ?')->execute([$rows[$swapWith]['display_order'], $r['core_value_id']]);
                    $pdo->prepare('UPDATE about_core_values SET display_order = ? WHERE core_value_id = ?')->execute([$r['display_order'], $rows[$swapWith]['core_value_id']]);
                }
                break;
            }
        }
    }
    redirect('about_core_values.php');
}

$coreValues = $aboutTableMissing ? [] : $pdo->query('SELECT * FROM about_core_values ORDER BY display_order, core_value_id')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>About SABNAHIS</h2>
    <?php require __DIR__ . '/includes/about_tabs.php'; ?>

    <?php if ($aboutTableMissing): ?>
        <div class="alert alert-error">
            The About SABNAHIS database tables were not found. Import
            <code>database/migration_2026_08_about_module.sql</code> via phpMyAdmin before this page can be used.
        </div>
    <?php else: ?>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($err = flash('error')): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Add Core Value</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="add">
            <label>Icon <span class="muted">(emoji or short label)</span><input type="text" name="icon" maxlength="50" placeholder="&#10084;&#65039;"></label>
            <label>Title<input type="text" name="title" required maxlength="100"></label>
            <label class="full">Description<textarea name="description" rows="2"></textarea></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Core Value</button></div>
        </form>
    </section>

    <section class="panel">
        <h3>Core Values</h3>
        <?php if (empty($coreValues)): ?>
            <p class="muted">No core values added yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Order</th><th>Icon</th><th>Title</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($coreValues as $cv): ?>
                    <tr>
                        <td class="actions">
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="up"><input type="hidden" name="core_value_id" value="<?php echo $cv['core_value_id']; ?>"><button type="submit" class="btn-small">&uarr;</button></form>
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="down"><input type="hidden" name="core_value_id" value="<?php echo $cv['core_value_id']; ?>"><button type="submit" class="btn-small">&darr;</button></form>
                        </td>
                        <td><?php echo clean($cv['icon']); ?></td>
                        <td><?php echo clean($cv['title']); ?></td>
                        <td><?php echo clean($cv['description']); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($cv['status']); ?>"><?php echo clean($cv['status']); ?></span></td>
                        <td class="actions">
                            <a href="#" onclick="document.getElementById('edit-cv-<?php echo $cv['core_value_id']; ?>').classList.toggle('is-open'); return false;">Edit</a>
                            <?php if ($cv['status'] === 'Active'): ?>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="archive"><input type="hidden" name="core_value_id" value="<?php echo $cv['core_value_id']; ?>"><button type="submit" class="link-plain">Archive</button></form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="restore"><input type="hidden" name="core_value_id" value="<?php echo $cv['core_value_id']; ?>"><button type="submit" class="link-plain">Restore</button></form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this core value? This cannot be undone.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="core_value_id" value="<?php echo $cv['core_value_id']; ?>"><button type="submit" class="link-danger">Delete</button></form>
                        </td>
                    </tr>
                    <tr class="inline-edit-row" id="edit-cv-<?php echo $cv['core_value_id']; ?>">
                        <td colspan="6">
                            <form method="POST" class="form-grid">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="core_value_id" value="<?php echo $cv['core_value_id']; ?>">
                                <label>Icon<input type="text" name="icon" value="<?php echo clean($cv['icon']); ?>" maxlength="50"></label>
                                <label>Title<input type="text" name="title" value="<?php echo clean($cv['title']); ?>" required maxlength="100"></label>
                                <label class="full">Description<textarea name="description" rows="2"><?php echo clean($cv['description']); ?></textarea></label>
                                <div class="form-actions"><button type="submit" class="btn-primary">Save Changes</button></div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
