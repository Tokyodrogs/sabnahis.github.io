<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// About SABNAHIS module (2026-08) - Photo Gallery CRUD with drag-and-drop
// ordering (see about_gallery_reorder.php) and a lightbox preview.
// Administrator-only.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can manage the About SABNAHIS page.');
    redirect('dashboard.php');
}

$pageTitle = 'About SABNAHIS - Photo Gallery';

$aboutTableMissing = false;
try {
    $pdo->query('SELECT 1 FROM about_gallery LIMIT 1');
} catch (PDOException $e) {
    $aboutTableMissing = true;
}

if (!$aboutTableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $err = null;

    if ($action === 'add') {
        $imgName = handle_about_image_upload('image', 'about_gallery', 1600, $err);
        if ($imgName === false) {
            flash('error', $err);
        } elseif (!$imgName) {
            flash('error', 'Please choose a photo to upload.');
        } else {
            $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM about_gallery')->fetchColumn();
            $pdo->prepare('INSERT INTO about_gallery (image, title, caption, category, display_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$imgName, trim($_POST['title'] ?? ''), trim($_POST['caption'] ?? ''), trim($_POST['category'] ?? ''), $maxOrder + 1]);
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_gallery_add', 'Added gallery photo: ' . trim($_POST['title'] ?? '(untitled)'));
            flash('success', 'Photo added to gallery.');
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['gallery_id'] ?? 0);
        $imgName = handle_about_image_upload('image', 'about_gallery', 1600, $err);
        if ($imgName === false) {
            flash('error', $err);
        } else {
            if ($imgName) {
                $old = $pdo->prepare('SELECT image FROM about_gallery WHERE gallery_id = ?');
                $old->execute([$id]);
                $oldImg = $old->fetchColumn();
                if ($oldImg && is_file(UPLOAD_DIR . $oldImg)) unlink(UPLOAD_DIR . $oldImg);
                $pdo->prepare('UPDATE about_gallery SET image = ?, title = ?, caption = ?, category = ? WHERE gallery_id = ?')
                    ->execute([$imgName, trim($_POST['title'] ?? ''), trim($_POST['caption'] ?? ''), trim($_POST['category'] ?? ''), $id]);
            } else {
                $pdo->prepare('UPDATE about_gallery SET title = ?, caption = ?, category = ? WHERE gallery_id = ?')
                    ->execute([trim($_POST['title'] ?? ''), trim($_POST['caption'] ?? ''), trim($_POST['category'] ?? ''), $id]);
            }
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_gallery_edit', "Edited gallery photo #$id");
            flash('success', 'Photo updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['gallery_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image FROM about_gallery WHERE gallery_id = ?');
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && is_file(UPLOAD_DIR . $img)) unlink(UPLOAD_DIR . $img);
        $pdo->prepare('DELETE FROM about_gallery WHERE gallery_id = ?')->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_gallery_delete', "Deleted gallery photo #$id");
        flash('success', 'Photo deleted.');
    } elseif ($action === 'archive' || $action === 'restore') {
        $id = (int) ($_POST['gallery_id'] ?? 0);
        $newStatus = $action === 'restore' ? 'Active' : 'Archived';
        $pdo->prepare('UPDATE about_gallery SET status = ? WHERE gallery_id = ?')->execute([$newStatus, $id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_gallery_' . $action, "Gallery photo #$id");
        flash('success', $action === 'restore' ? 'Photo restored.' : 'Photo archived.');
    }
    redirect('about_gallery.php');
}

$photos = $aboutTableMissing ? [] : $pdo->query('SELECT * FROM about_gallery ORDER BY display_order, gallery_id')->fetchAll();
$categories = [];
foreach ($photos as $p) {
    if (!empty($p['category'])) $categories[$p['category']] = true;
}

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
        <h3>Add Photo</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="add">
            <label class="full">Photo<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*" required></label>
            <label>Title<input type="text" name="title" maxlength="150"></label>
            <label>Category<input type="text" name="category" maxlength="100" list="gallery-categories" placeholder="Events, Campus, Activities...">
                <datalist id="gallery-categories"><?php foreach (array_keys($categories) as $c): ?><option value="<?php echo clean($c); ?>"><?php endforeach; ?></datalist>
            </label>
            <label class="full">Caption<textarea name="caption" rows="2"></textarea></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Photo</button></div>
        </form>
    </section>

    <section class="panel">
        <h3>Photo Gallery <span class="muted" style="font-weight:normal;">&mdash; drag photos to reorder</span></h3>
        <?php if (empty($photos)): ?>
            <p class="muted">No photos uploaded yet.</p>
        <?php else: ?>
            <div class="about-gallery-admin-grid" id="galleryAdminGrid">
                <?php foreach ($photos as $p): ?>
                    <div class="about-gallery-admin-item" draggable="true" data-id="<?php echo $p['gallery_id']; ?>">
                        <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($p['image']); ?>" alt="<?php echo clean($p['title']); ?>" class="js-lightbox-trigger" data-caption="<?php echo clean(($p['title'] ? $p['title'] . ' - ' : '') . $p['caption']); ?>">
                        <div class="about-gallery-admin-item-body">
                            <strong><?php echo clean($p['title'] ?: '(untitled)'); ?></strong>
                            <span class="badge badge-<?php echo strtolower($p['status']); ?>"><?php echo clean($p['status']); ?></span>
                            <?php if ($p['category']): ?><div class="muted"><?php echo clean($p['category']); ?></div><?php endif; ?>
                            <div class="muted" style="font-size:11px;">Uploaded <?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                            <div class="actions">
                                <a href="#" onclick="document.getElementById('edit-gal-<?php echo $p['gallery_id']; ?>').classList.toggle('is-open'); return false;">Edit</a>
                                <?php if ($p['status'] === 'Active'): ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="archive"><input type="hidden" name="gallery_id" value="<?php echo $p['gallery_id']; ?>"><button type="submit" class="link-plain">Archive</button></form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="restore"><input type="hidden" name="gallery_id" value="<?php echo $p['gallery_id']; ?>"><button type="submit" class="link-plain">Restore</button></form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this photo? This cannot be undone.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="gallery_id" value="<?php echo $p['gallery_id']; ?>"><button type="submit" class="link-danger">Delete</button></form>
                            </div>
                            <div class="inline-edit-row" id="edit-gal-<?php echo $p['gallery_id']; ?>">
                                <form method="POST" enctype="multipart/form-data" class="form-grid">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="gallery_id" value="<?php echo $p['gallery_id']; ?>">
                                    <label class="full">Title<input type="text" name="title" value="<?php echo clean($p['title']); ?>" maxlength="150"></label>
                                    <label class="full">Category<input type="text" name="category" value="<?php echo clean($p['category']); ?>" maxlength="100"></label>
                                    <label class="full">Caption<textarea name="caption" rows="2"><?php echo clean($p['caption']); ?></textarea></label>
                                    <label class="full">Replace Photo<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"></label>
                                    <div class="form-actions"><button type="submit" class="btn-primary">Save Changes</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div id="aboutLightbox" class="about-lightbox" hidden>
        <button type="button" class="about-lightbox-close" id="aboutLightboxClose">&times;</button>
        <img id="aboutLightboxImg" src="" alt="">
        <p id="aboutLightboxCaption"></p>
    </div>

    <?php endif; ?>
</main>
<script src="<?php echo BASE_URL; ?>assets/js/about_gallery_admin.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
