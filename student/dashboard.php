<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Dashboard';

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    WHERE s.student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

$announcements = $pdo->query('SELECT * FROM announcements ORDER BY date_posted DESC LIMIT 3')->fetchAll();

$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$_SESSION['student_id']]);
$notifications = $notifStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Welcome, <?php echo clean($student['firstname']); ?>!</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <section class="panel student-profile-head">
        <div class="student-profile-photo"><?php echo student_photo_html($student['photo'], 'lg'); ?></div>
        <table class="detail-table student-profile-summary">
            <tr><th>Student Number</th><td><?php echo clean($_SESSION['student_username']); ?></td></tr>
            <tr><th>Full Name</th><td><?php echo clean($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']); ?></td></tr>
            <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?><?php if ($student['strand_name']): ?> &mdash; <?php echo clean($student['strand_name']); ?><?php endif; ?></td></tr>
            <tr><th>School Year</th><td><?php echo clean($student['school_year'] ?? '-'); ?></td></tr>
            <tr><th>Enrollment Status</th><td><?php echo status_badge($student['status']); ?></td></tr>
        </table>
    </section>

    <div class="quick-access-grid">
        <a href="profile.php" class="quick-access-card">My Profile</a>
        <a href="enrollment_status.php" class="quick-access-card">Enrollment Status</a>
        <a href="subjects.php" class="quick-access-card">Subjects</a>
        <a href="schedule.php" class="quick-access-card">Class Schedule</a>
        <a href="qr_code.php" class="quick-access-card">Digital Student ID</a>
        <a href="cor.php" class="quick-access-card">Certificate of Registration</a>
        <a href="documents.php" class="quick-access-card">Documents</a>
        <a href="announcements.php" class="quick-access-card">Announcements</a>
        <a href="notifications.php" class="quick-access-card">Notifications</a>
        <a href="change_password.php" class="quick-access-card">Change Password</a>
    </div>

    <div class="dashboard-columns">
        <section class="panel">
            <div class="panel-header">
                <h3>Recent Announcements</h3>
                <a href="announcements.php" class="btn-small">View All</a>
            </div>
            <?php if (empty($announcements)): ?>
                <p class="muted">No announcements posted yet.</p>
            <?php else: ?>
                <ul class="announcement-list">
                    <?php foreach ($announcements as $a): ?>
                        <li>
                            <strong><?php echo clean($a['title']); ?></strong>
                            <span class="badge badge-<?php echo strtolower($a['priority']); ?>"><?php echo clean($a['priority']); ?></span>
                            <span class="muted"> &mdash; <?php echo date('M d, Y', strtotime($a['date_posted'])); ?></span>
                            <p><?php echo nl2br(clean(mb_strimwidth($a['content'], 0, 160, '...'))); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h3>Notifications</h3>
                <a href="notifications.php" class="btn-small">View All</a>
            </div>
            <?php if (empty($notifications)): ?>
                <p class="muted">No notifications yet.</p>
            <?php else: ?>
                <ul class="notification-list">
                    <?php foreach ($notifications as $n): ?>
                        <li class="<?php echo $n['is_read'] ? '' : 'notification-unread'; ?>">
                            <span class="muted"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></span>
                            <p><?php echo clean($n['message']); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
