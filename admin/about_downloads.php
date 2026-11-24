<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// About SABNAHIS module (2026-08) - Downloadable Files CRUD (Student
// Handbook, Enrollment Guidelines, School Calendar, School Profile).
// PDF only. Administrator-only.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can manage the About SABNAHIS page.');
    redirect('dashboard.php');
}

$pageTitle = 'About SABNAHIS - Downloadable Files';

$aboutTableMissing = false;
try {
    $pdo->query('SELECT 1 FROM about_downloads LIMIT 1');
} catch (PDOException $e) {
    $aboutTableMissing = true;
}

if (!$aboutTableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $err = null;

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Title is required.');
        } else {
            $fileName = handle_pdf_upload('file', 'about_download', $err);
            if ($fileName === false) {
                flash('error', $err);
            } elseif (!$fileName) {
                flash('error', 'Please choose a PDF file to upload.');
            } else {
                $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM about_downloads')->fetchColumn();
                $pdo->prepare('INSERT INTO about_downloads (title, file_path, display_order) VALUES (?, ?, ?)')
                    ->execute([$title, $fileName, $maxOrder + 1]);
                audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_download_add', "Added downloadable file: $title");
                flash('success', 'File uploaded.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['download_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $fileName = handle_pdf_upload('file', 'about_download', $err);
        if ($fileName === false) {
            flash('error', $err);
        } else {
            if ($fileName) {
                $old = $pdo->prepare('SELECT file_path FROM about_downloads WHERE download_id = ?');
                $old->execute([$id]);
                $oldFile = $old->fetchColumn();
                if ($oldFile && is_file(UPLOAD_DIR . $oldFile)) unlink(UPLOAD_DIR . $oldFile);
                $pdo->prepare('UPDATE about_downloads SET title = ?, file_path = ? WHERE download_id = ?')->execute([$title, $fileName, $id]);
            } else {
                $pdo->prepare('UPDATE about_downloads SET title = ? WHERE download_id = ?')->execute([$title, $id]);
            }
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_download_edit', "Edited downloadable file #$id: $title");
            flash('success', 'File updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['download_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT file_path FROM about_downloads WHERE download_id = ?');
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        if ($file && is_file(UPLOAD_DIR . $file)) unlink(UPLOAD_DIR . $file);
        $pdo->prepare('DELETE FROM about_downloads WHERE download_id = ?')->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_download_delete', "Deleted downloadable file #$id");
        flash('success', 'File deleted.');
    } elseif ($action === 'archive' || $action === 'restore') {
        $id = (int) ($_POST['download_id'] ?? 0);
        $newStatus = $action === 'restore' ? 'Active' : 'Archived';
        $pdo->prepare('UPDATE about_downloads SET status = ? WHERE download_id = ?')->execute([$newStatus, $id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_download_' . $action, "Downloadable file #$id");
        flash('success', $action === 'restore' ? 'File restored.' : 'File archived.');
    } elseif ($action === 'move') {
        $id = (int) ($_POST['download_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $rows = $pdo->query('SELECT download_id, display_order FROM about_downloads ORDER BY display_order, download_id')->fetchAll();
        foreach ($rows as $i => $r) {
            if ((int) $r['download_id'] === $id) {
                $swapWith = $direction === 'up' ? $i - 1 : $i + 1;
                if (isset($rows[$swapWith])) {
                    $pdo->prepare('UPDATE about_downloads SET display_order = ? WHERE download_id = ?')->execute([$rows[$swapWith]['display_order'], $r['download_id']]);
                    $pdo->prepare('UPDATE about_downloads SET display_order = ? WHERE download_id = ?')->execute([$r['display_order'], $rows[$swapWith]['download_id']]);
                }
                break;
            }
        }
    }
    redirect('about_downloads.php');
}

$downloads = $aboutTableMissing ? [] : $pdo->query('SELECT * FROM about_downloads ORDER BY display_order, download_id')->fetchAll();

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
        <h3>Upload File</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="add">
            <label>Title<input type="text" name="title" required maxlength="150" placeholder="Student Handbook, Enrollment Guidelines, ..."></label>
            <label>PDF File<input type="file" name="file" accept=".pdf,application/pdf" required></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Upload</button></div>
        </form>
    </section>

    <section class="panel">
        <h3>Downloadable Files</h3>
        <?php if (empty($downloads)): ?>
            <p class="muted">No files uploaded yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Order</th><th>Title</th><th>File</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($downloads as $d): ?>
                    <tr>
                        <td class="actions">
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="up"><input type="hidden" name="download_id" value="<?php echo $d['download_id']; ?>"><button type="submit" class="btn-small">&uarr;</button></form>
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="down"><input type="hidden" name="download_id" value="<?php echo $d['download_id']; ?>"><button type="submit" class="btn-small">&darr;</button></form>
                        </td>
                        <td><?php echo clean($d['title']); ?></td>
                        <td><a href="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($d['file_path']); ?>" target="_blank">View PDF</a></td>
                        <td><span class="badge badge-<?php echo strtolower($d['status']); ?>"><?php echo clean($d['status']); ?></span></td>
                        <td class="actions">
                            <a href="#" onclick="document.getElementById('edit-dl-<?php echo $d['download_id']; ?>').classList.toggle('is-open'); return false;">Edit</a>
                            <?php if ($d['status'] === 'Active'): ?>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="archive"><input type="hidden" name="download_id" value="<?php echo $d['download_id']; ?>"><button type="submit" class="link-plain">Archive</button></form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="restore"><input type="hidden" name="download_id" value="<?php echo $d['download_id']; ?>"><button type="submit" class="link-plain">Restore</button></form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this file? This cannot be undone.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="download_id" value="<?php echo $d['download_id']; ?>"><button type="submit" class="link-danger">Delete</button></form>
                        </td>
                    </tr>
                    <tr class="inline-edit-row" id="edit-dl-<?php echo $d['download_id']; ?>">
                        <td colspan="5">
                            <form method="POST" enctype="multipart/form-data" class="form-grid">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="download_id" value="<?php echo $d['download_id']; ?>">
                                <label>Title<input type="text" name="title" value="<?php echo clean($d['title']); ?>" required maxlength="150"></label>
                                <label>Replace File<input type="file" name="file" accept=".pdf,application/pdf"></label>
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
