<?php
$current = basename($_SERVER['PHP_SELF']);
$unreadCount = 0;
if (!empty($_SESSION['student_id']) && isset($pdo)) {
    $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE student_id = ? AND is_read = 0');
    $unreadStmt->execute([$_SESSION['student_id']]);
    $unreadCount = (int) $unreadStmt->fetchColumn();
}
?>
<nav class="admin-sidebar">
    <ul>
        <li class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>"><a href="dashboard.php"><span class="sidebar-icon" aria-hidden="true">&#127968;</span><span data-i18n="student.dashboard"><?php echo clean(t('student.dashboard')); ?></span></a></li>
        <li class="<?php echo $current === 'profile.php' ? 'active' : ''; ?>"><a href="profile.php"><span class="sidebar-icon" aria-hidden="true">&#128100;</span><span data-i18n="student.my_profile"><?php echo clean(t('student.my_profile')); ?></span></a></li>
        <li class="<?php echo $current === 'enrollment_status.php' ? 'active' : ''; ?>"><a href="enrollment_status.php"><span class="sidebar-icon" aria-hidden="true">&#128203;</span><span data-i18n="student.enrollment_status"><?php echo clean(t('student.enrollment_status')); ?></span></a></li>
        <li class="<?php echo $current === 'subjects.php' ? 'active' : ''; ?>"><a href="subjects.php"><span class="sidebar-icon" aria-hidden="true">&#128218;</span><span data-i18n="student.subjects"><?php echo clean(t('student.subjects')); ?></span></a></li>
        <li class="<?php echo $current === 'schedule.php' ? 'active' : ''; ?>"><a href="schedule.php"><span class="sidebar-icon" aria-hidden="true">&#128197;</span><span data-i18n="student.schedule"><?php echo clean(t('student.schedule')); ?></span></a></li>
        <li class="<?php echo $current === 'qr_code.php' ? 'active' : ''; ?>"><a href="qr_code.php"><span class="sidebar-icon" aria-hidden="true">&#129706;</span><span data-i18n="student.qr_code"><?php echo clean(t('student.qr_code')); ?></span></a></li>
        <li class="<?php echo $current === 'cor.php' ? 'active' : ''; ?>"><a href="cor.php"><span class="sidebar-icon" aria-hidden="true">&#128196;</span><span data-i18n="student.cor"><?php echo clean(t('student.cor')); ?></span></a></li>
        <li class="<?php echo $current === 'documents.php' ? 'active' : ''; ?>"><a href="documents.php"><span class="sidebar-icon" aria-hidden="true">&#128193;</span><span data-i18n="student.documents"><?php echo clean(t('student.documents')); ?></span></a></li>
        <li class="<?php echo $current === 'announcements.php' ? 'active' : ''; ?>"><a href="announcements.php"><span class="sidebar-icon" aria-hidden="true">&#128226;</span><span data-i18n="student.announcements"><?php echo clean(t('student.announcements')); ?></span></a></li>
        <li class="<?php echo $current === 'notifications.php' ? 'active' : ''; ?>"><a href="notifications.php"><span class="sidebar-icon" aria-hidden="true">&#128276;</span><span data-i18n="student.notifications"><?php echo clean(t('student.notifications')); ?></span><?php if ($unreadCount > 0): ?> <span class="sidebar-badge"><?php echo $unreadCount; ?></span><?php endif; ?></a></li>
        <li class="<?php echo $current === 'change_password.php' ? 'active' : ''; ?>"><a href="change_password.php"><span class="sidebar-icon" aria-hidden="true">&#128274;</span><span data-i18n="student.change_password"><?php echo clean(t('student.change_password')); ?></span></a></li>
        <li><a href="logout.php"><span class="sidebar-icon" aria-hidden="true">&#128682;</span><span data-i18n="student.logout"><?php echo clean(t('student.logout')); ?></span></a></li>
    </ul>
</nav>
