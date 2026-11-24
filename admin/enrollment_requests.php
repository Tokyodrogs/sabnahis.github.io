<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Enrollment Requests';

$statusFilter = $_GET['status'] ?? 'Pending';

$sql = 'SELECT s.*, g.grade_name, st.strand_name, sy.school_year
        FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN strands st ON s.strand_id = st.strand_id
        LEFT JOIN school_year sy ON s.sy_id = sy.sy_id';
$params = [];
if ($statusFilter !== '' && $statusFilter !== 'All') {
    $sql .= ' WHERE s.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY s.date_registered DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Online Enrollment Submissions</h2>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <div class="filter-bar">
        <?php foreach (['Pending','Verified','Approved','Rejected','Enrolled','All'] as $st): ?>
            <a href="?status=<?php echo $st; ?>" class="btn-tab <?php echo $statusFilter === $st ? 'active' : ''; ?>"><?php echo $st === 'Enrolled' ? 'Officially Enrolled' : $st; ?></a>
        <?php endforeach; ?>
    </div>

    <table class="data-table">
        <thead>
        <tr><th>Date Applied</th><th>Name</th><th>Grade Level</th><th>Strand</th><th>School Year</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($requests)): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;">No records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($r['date_registered'])); ?></td>
                <td><?php echo clean($r['lastname'] . ', ' . $r['firstname']); ?></td>
                <td><?php echo clean($r['grade_name'] ?? '-'); ?></td>
                <td><?php echo clean($r['strand_name'] ?? '-'); ?></td>
                <td><?php echo clean($r['school_year'] ?? '-'); ?></td>
                <td><?php echo status_badge($r['status']); ?></td>
                <td class="actions">
                    <a href="enrollment_view.php?student_id=<?php echo $r['student_id']; ?>">Review</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
