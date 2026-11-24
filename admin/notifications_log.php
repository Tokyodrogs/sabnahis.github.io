<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Read-only log of every notification sent to students (enrollment status
// changes, document review, section assignment, account creation/reset).
// Students see these in their own portal (student/notifications.php); this
// page lets the Registrar audit/confirm what was actually sent.
$pageTitle = 'Notifications Log';

$categoryFilter = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT n.*, s.firstname, s.lastname
    FROM notifications n
    JOIN students s ON s.student_id = n.student_id
    WHERE 1=1";
$params = [];

if ($categoryFilter !== '') {
    $sql .= ' AND n.category = ?';
    $params[] = $categoryFilter;
}
if ($search !== '') {
    $sql .= ' AND (s.firstname LIKE ? OR s.lastname LIKE ? OR n.message LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY n.created_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$categories = $pdo->query('SELECT DISTINCT category FROM notifications ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$categoryLabels = [
    'enrollment' => 'Enrollment Status',
    'document'   => 'Document Review',
    'section'    => 'Section Assignment',
    'account'    => 'Account (Reset/Lock/Unlock)',
    'system'     => 'Account Created',
    'schedule'   => 'Class Schedule',
    'payment'    => 'Payment',
    'announcement' => 'Announcement',
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Notifications Log</h2>
    <p class="muted">Read-only record of notifications sent to students through the Student Portal. Showing the most recent 300.</p>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" value="<?php echo clean($search); ?>" placeholder="Search name or message">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo clean($c); ?>" <?php echo $categoryFilter === $c ? 'selected' : ''; ?>><?php echo clean($categoryLabels[$c] ?? ucfirst($c)); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        <a href="notifications_log.php" class="btn-link">Reset</a>
    </form>

    <table class="data-table">
        <thead>
        <tr><th>Date</th><th>Student</th><th>Category</th><th>Message</th><th>Read?</th></tr>
        </thead>
        <tbody>
        <?php if (empty($notifications)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;">No notifications found.</td></tr>
        <?php endif; ?>
        <?php foreach ($notifications as $n): ?>
            <tr>
                <td><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></td>
                <td><a href="student_view.php?id=<?php echo $n['student_id']; ?>"><?php echo clean($n['lastname'] . ', ' . $n['firstname']); ?></a></td>
                <td><span class="badge"><?php echo clean($categoryLabels[$n['category']] ?? ucfirst($n['category'])); ?></span></td>
                <td><?php echo clean($n['message']); ?></td>
                <td><?php echo $n['is_read'] ? 'Read' : 'Unread'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
