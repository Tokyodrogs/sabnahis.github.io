<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Announcements';

// Announcements the student hasn't seen yet (unread notification of
// category 'announcement' pointing at that announcement_id) get
// highlighted, then are marked read since the student is now viewing them.
$unreadStmt = $pdo->prepare("SELECT ref_id FROM notifications WHERE student_id = ? AND category = 'announcement' AND is_read = 0 AND ref_id IS NOT NULL");
$unreadStmt->execute([$_SESSION['student_id']]);
$unreadIds = array_flip($unreadStmt->fetchAll(PDO::FETCH_COLUMN));

$announcements = $pdo->query('SELECT * FROM announcements ORDER BY date_posted DESC')->fetchAll();

$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND category = 'announcement' AND is_read = 0")
    ->execute([$_SESSION['student_id']]);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Announcements</h2>

    <?php if (empty($announcements)): ?>
        <section class="panel"><p class="muted">No announcements posted yet.</p></section>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <section class="panel announcement-card-item <?php echo isset($unreadIds[$a['announcement_id']]) ? 'announcement-unread' : ''; ?>">
                <div class="announcement-list-row">
                    <?php if (!empty($a['image'])): ?>
                        <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($a['image']); ?>" alt="Announcement attachment" class="announcement-thumb">
                    <?php else: ?>
                        <div class="announcement-thumb announcement-thumb-placeholder">No Image</div>
                    <?php endif; ?>
                    <div>
                        <strong><?php echo clean($a['title']); ?></strong>
                        <span class="badge badge-<?php echo strtolower($a['priority']); ?>"><?php echo clean($a['priority']); ?></span>
                        <?php if (isset($unreadIds[$a['announcement_id']])): ?><span class="badge badge-pending">New</span><?php endif; ?>
                        <span class="muted"> &mdash; <?php echo date('M d, Y', strtotime($a['date_posted'])); ?></span>
                        <p><?php echo nl2br(clean($a['content'])); ?></p>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
