<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// About SABNAHIS module (2026-08) - School Facilities gallery CRUD
// (Library, Science Laboratory, Computer Laboratory, Covered Court, ...).
// Administrator-only.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can manage the About SABNAHIS page.');
    redirect('dashboard.php');
}

$pageTitle = 'About SABNAHIS - Facilities';

$aboutTableMissing = false;
try {
    $pdo->query('SELECT 1 FROM about_facilities LIMIT 1');
} catch (PDOException $e) {
    $aboutTableMissing = true;
}

if (!$aboutTableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $err = null;

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            flash('error', 'Facility name is required.');
        } else {
            $imgName = handle_about_image_upload('image', 'about_facility', 1200, $err);
            if ($imgName === false) {
                flash('error', $err);
            } elseif ($action === 'add') {
                $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM about_facilities')->fetchColumn();
                $pdo->prepare('INSERT INTO about_facilities (name, description, image, display_order) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $description, $imgName, $maxOrder + 1]);
                audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_facility_add', "Added facility: $name");
                flash('success', 'Facility added.');
            } else {
                $id = (int) ($_POST['facility_id'] ?? 0);
                if ($imgName) {
                    $old = $pdo->prepare('SELECT image FROM about_facilities WHERE facility_id = ?');
                    $old->execute([$id]);
                    $oldImg = $old->fetchColumn();
                    if ($oldImg && is_file(UPLOAD_DIR . $oldImg)) unlink(UPLOAD_DIR . $oldImg);
                    $pdo->prepare('UPDATE about_facilities SET name = ?, description = ?, image = ? WHERE facility_id = ?')->execute([$name, $description, $imgName, $id]);
                } else {
                    $pdo->prepare('UPDATE about_facilities SET name = ?, description = ? WHERE facility_id = ?')->execute([$name, $description, $id]);
                }
                audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_facility_edit', "Edited facility #$id: $name");
                flash('success', 'Facility updated.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['facility_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image FROM about_facilities WHERE facility_id = ?');
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && is_file(UPLOAD_DIR . $img)) unlink(UPLOAD_DIR . $img);
        $pdo->prepare('DELETE FROM about_facilities WHERE facility_id = ?')->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_facility_delete', "Deleted facility #$id");
        flash('success', 'Facility deleted.');
    } elseif ($action === 'archive' || $action === 'restore') {
        $id = (int) ($_POST['facility_id'] ?? 0);
        $newStatus = $action === 'restore' ? 'Active' : 'Archived';
        $pdo->prepare('UPDATE about_facilities SET status = ? WHERE facility_id = ?')->execute([$newStatus, $id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_facility_' . $action, "Facility #$id");
        flash('success', $action === 'restore' ? 'Facility restored.' : 'Facility archived.');
    } elseif ($action === 'move') {
        $id = (int) ($_POST['facility_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $rows = $pdo->query('SELECT facility_id, display_order FROM about_facilities ORDER BY display_order, facility_id')->fetchAll();
        foreach ($rows as $i => $r) {
            if ((int) $r['facility_id'] === $id) {
                $swapWith = $direction === 'up' ? $i - 1 : $i + 1;
                if (isset($rows[$swapWith])) {
                    $pdo->prepare('UPDATE about_facilities SET display_order = ? WHERE facility_id = ?')->execute([$rows[$swapWith]['display_order'], $r['facility_id']]);
                    $pdo->prepare('UPDATE about_facilities SET display_order = ? WHERE facility_id = ?')->execute([$r['display_order'], $rows[$swapWith]['facility_id']]);
                }
                break;
            }
        }
    }
    redirect('about_facilities.php');
}

$facilities = $aboutTableMissing ? [] : $pdo->query('SELECT * FROM about_facilities ORDER BY display_order, facility_id')->fetchAll();

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
        <h3>Add Facility</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="add">
            <label>Facility Name<input type="text" name="name" required maxlength="150" placeholder="Library, Science Laboratory, ..."></label>
            <label class="full">Description<textarea name="description" rows="2"></textarea></label>
            <label class="full">Image<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Facility</button></div>
        </form>
    </section>

    <section class="panel">
        <h3>Facilities</h3>
        <?php if (empty($facilities)): ?>
            <p class="muted">No facilities added yet.</p>
        <?php else: ?>
            <div class="about-card-grid">
                <?php foreach ($facilities as $f): ?>
                    <div class="about-admin-card">
                        <?php if (!empty($f['image'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($f['image']); ?>" alt="<?php echo clean($f['name']); ?>">
                        <?php endif; ?>
                        <div class="about-admin-card-body">
                            <strong><?php echo clean($f['name']); ?></strong>
                            <span class="badge badge-<?php echo strtolower($f['status']); ?>"><?php echo clean($f['status']); ?></span>
                            <p class="muted"><?php echo clean($f['description']); ?></p>
                            <div class="actions">
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="up"><input type="hidden" name="facility_id" value="<?php echo $f['facility_id']; ?>"><button type="submit" class="btn-small">&uarr;</button></form>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="down"><input type="hidden" name="facility_id" value="<?php echo $f['facility_id']; ?>"><button type="submit" class="btn-small">&darr;</button></form>
                                <a href="#" onclick="document.getElementById('edit-fac-<?php echo $f['facility_id']; ?>').classList.toggle('is-open'); return false;">Edit</a>
                                <?php if ($f['status'] === 'Active'): ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="archive"><input type="hidden" name="facility_id" value="<?php echo $f['facility_id']; ?>"><button type="submit" class="link-plain">Archive</button></form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="restore"><input type="hidden" name="facility_id" value="<?php echo $f['facility_id']; ?>"><button type="submit" class="link-plain">Restore</button></form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this facility? This cannot be undone.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="facility_id" value="<?php echo $f['facility_id']; ?>"><button type="submit" class="link-danger">Delete</button></form>
                            </div>
                            <div class="inline-edit-row" id="edit-fac-<?php echo $f['facility_id']; ?>">
                                <form method="POST" enctype="multipart/form-data" class="form-grid">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="facility_id" value="<?php echo $f['facility_id']; ?>">
                                    <label class="full">Facility Name<input type="text" name="name" value="<?php echo clean($f['name']); ?>" required maxlength="150"></label>
                                    <label class="full">Description<textarea name="description" rows="2"><?php echo clean($f['description']); ?></textarea></label>
                                    <label class="full">Replace Image<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"></label>
                                    <div class="form-actions"><button type="submit" class="btn-primary">Save Changes</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
