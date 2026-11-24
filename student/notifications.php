<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '', 'notifications')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $notifId = (int) ($_POST['notification_id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND student_id = ?')
            ->execute([$notifId, $_SESSION['student_id']]);
    } elseif ($action === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE student_id = ?')->execute([$_SESSION['student_id']]);
    }
    redirect('notifications.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['student_id']]);
$notifications = $stmt->fetchAll();

$csrfToken = csrf_token('notifications');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Notifications</h2>
        <?php if (!empty($notifications)): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-secondary">Mark All as Read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <section class="panel"><p class="muted">No notifications yet.</p></section>
    <?php else: ?>
        <section class="panel">
            <ul class="notification-list">
                <?php foreach ($notifications as $n): ?>
                    <li class="<?php echo $n['is_read'] ? '' : 'notification-unread'; ?>">
                        <div class="notification-row">
                            <div>
                                <span class="badge badge-<?php echo strtolower($n['category']) === 'password' ? 'normal' : 'pending'; ?>"><?php echo clean(ucfirst($n['category'])); ?></span>
                                <span class="muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></span>
                                <p><?php echo clean($n['message']); ?></p>
                            </div>
                            <?php if (!$n['is_read']): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $n['notification_id']; ?>">
                                    <button type="submit" class="link-plain">Mark as Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
