<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Announcements';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $priority = $_POST['priority'] ?? 'Normal';
        if (!in_array($priority, ['Normal', 'Important', 'Urgent'], true)) {
            $priority = 'Normal';
        }
        if ($title === '' || $content === '') {
            $errors[] = 'Title and content are required.';
        } else {
            $uploadError = null;
            $imageName = handle_image_upload('image', 'announcement', $uploadError);
            if ($imageName === false) {
                $errors[] = $uploadError;
            } else {
                $pdo->prepare('INSERT INTO announcements (title, content, image, priority) VALUES (?, ?, ?, ?)')
                    ->execute([$title, $content, $imageName, $priority]);
                $newAnnouncementId = $pdo->lastInsertId();

                // Notify every student who has a Student Portal account so
                // it shows up (highlighted, unread) on their Announcements
                // and Dashboard pages.
                notify_all_students($pdo, 'announcement', 'New announcement: ' . $title, 'announcement', $newAnnouncementId);

                flash('success', 'Announcement posted.');
                redirect('announcements.php');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['announcement_id'] ?? 0);
        $imgStmt = $pdo->prepare('SELECT image FROM announcements WHERE announcement_id = ?');
        $imgStmt->execute([$id]);
        $imgFile = $imgStmt->fetchColumn();
        if ($imgFile) {
            $imgPath = UPLOAD_DIR . $imgFile;
            if (is_file($imgPath)) unlink($imgPath);
        }
        $pdo->prepare('DELETE FROM announcements WHERE announcement_id = ?')->execute([$id]);
        flash('success', 'Announcement removed.');
        redirect('announcements.php');
    }
}

$announcements = $pdo->query('SELECT * FROM announcements ORDER BY date_posted DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>School Announcements</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Post New Announcement</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="add">
            <label class="full">Title<input type="text" name="title" required></label>
            <label class="full">Content<textarea name="content" rows="4" required></textarea></label>
            <label>Priority
                <select name="priority">
                    <option value="Normal" selected>Normal</option>
                    <option value="Important">Important</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </label>
            <label class="full">Image (optional)
                <input type="file" name="image" accept=".jpg,.jpeg,.png">
                <span class="muted" style="font-weight:normal;">JPG or PNG, max 5MB. Shown alongside the announcement on the public site.</span>
            </label>
            <div class="form-actions"><button type="submit" class="btn-primary">Post Announcement</button></div>
        </form>
    </section>

    <section class="panel">
        <h3>Posted Announcements</h3>
        <?php if (empty($announcements)): ?>
            <p class="muted">No announcements posted yet.</p>
        <?php else: ?>
            <ul class="announcement-list">
                <?php foreach ($announcements as $a): ?>
                    <li>
                        <div class="announcement-list-row">
                            <?php if (!empty($a['image'])): ?>
                                <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($a['image']); ?>" alt="Announcement image" class="announcement-thumb">
                            <?php else: ?>
                                <div class="announcement-thumb announcement-thumb-placeholder">No Image</div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo clean($a['title']); ?></strong>
                                <span class="badge badge-<?php echo strtolower($a['priority']); ?>"><?php echo clean($a['priority']); ?></span>
                                <span class="muted"> &mdash; <?php echo date('M d, Y', strtotime($a['date_posted'])); ?></span>
                                <p><?php echo nl2br(clean($a['content'])); ?></p>
                                <form method="POST" onsubmit="return confirm('Delete this announcement?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="announcement_id" value="<?php echo $a['announcement_id']; ?>">
                                    <button type="submit" class="link-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
