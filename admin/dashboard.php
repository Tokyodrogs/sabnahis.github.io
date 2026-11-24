<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Dashboard';

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$syId = $currentSY['sy_id'] ?? 0;
$syWhere = $syId ? " AND sy_id = $syId" : '';
$syWhereAlias = $syId ? " AND s.sy_id = $syId" : '';

// ---- Top stat cards ----
$totalEnrolled = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Enrolled' AND is_archived = 0" . $syWhere)->fetchColumn();
$totalPending  = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Pending' AND is_archived = 0" . $syWhere)->fetchColumn();
$newStudents      = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'New' AND is_archived = 0" . $syWhere)->fetchColumn();
$returningStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'Returning' AND is_archived = 0" . $syWhere)->fetchColumn();
$transferees      = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'Transferee' AND is_archived = 0" . $syWhere)->fetchColumn();

// ---- Enrollment by Grade Level ----
$byGrade = $pdo->query("SELECT COALESCE(g.grade_name, 'Unassigned') AS grade_name, COUNT(*) AS total
    FROM students s LEFT JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
    WHERE s.is_archived = 0 $syWhereAlias
    GROUP BY g.grade_level_id, g.grade_name ORDER BY g.grade_level_id")->fetchAll();

// ---- Enrollment by Section ----
$bySection = $pdo->query("SELECT COALESCE(CONCAT(g.grade_name, ' - ', sec.section_name), 'Not yet assigned') AS section_label, COUNT(*) AS total
    FROM students s
    LEFT JOIN sections sec ON sec.section_id = s.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    WHERE s.is_archived = 0 $syWhereAlias
    GROUP BY section_label ORDER BY total DESC")->fetchAll();

// ---- Enrollment Status Summary ----
$byStatus = $pdo->query("SELECT status, COUNT(*) AS total FROM students s WHERE s.is_archived = 0 $syWhereAlias GROUP BY status
    ORDER BY FIELD(status,'Pending','Verified','Approved','Rejected','Enrolled')")->fetchAll();

// ---- Recent Enrollment Activities ----
$recentActivity = $pdo->query("SELECT e.status, e.date_applied, e.remarks, s.student_id, s.lastname, s.firstname
    FROM enrollment e JOIN students s ON s.student_id = e.student_id
    ORDER BY e.date_applied DESC LIMIT 8")->fetchAll();

$announcements = $pdo->query('SELECT * FROM announcements ORDER BY date_posted DESC LIMIT 5')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Registrar Dashboard</h2>
    <p class="muted">Current School Year: <strong><?php echo clean($currentSY['school_year'] ?? 'Not set'); ?></strong></p>

    <div class="stat-cards">
        <div class="stat-card"><div class="stat-value"><?php echo $totalEnrolled; ?></div><div class="stat-label">Total Enrolled Students</div></div>
        <div class="stat-card highlight"><div class="stat-value"><?php echo $totalPending; ?></div><div class="stat-label">Pending Enrollments</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $newStudents; ?></div><div class="stat-label">New Students</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $returningStudents; ?></div><div class="stat-label">Returning Students</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $transferees; ?></div><div class="stat-label">Transferees</div></div>
    </div>

    <section class="panel">
        <h3>Quick Actions</h3>
        <div class="quick-access-grid">
            <a href="student_add.php" class="quick-access-card">Register New Student</a>
            <a href="enrollment_requests.php" class="quick-access-card">Enrollment Requests</a>
            <a href="section_assignment.php" class="quick-access-card">Section Assignment</a>
            <a href="document_verification.php" class="quick-access-card">Document Verification</a>
            <a href="student_accounts.php" class="quick-access-card">Student Accounts</a>
            <a href="reports.php" class="quick-access-card">Reports</a>
        </div>
    </section>

    <div class="dashboard-columns">
        <section class="panel">
            <h3>Enrollment by Grade Level</h3>
            <table class="data-table">
                <thead><tr><th>Grade Level</th><th>Students</th></tr></thead>
                <tbody>
                <?php if (empty($byGrade)): ?><tr><td colspan="2" class="muted" style="text-align:center;">No data.</td></tr><?php endif; ?>
                <?php foreach ($byGrade as $g): ?>
                    <tr><td><?php echo clean($g['grade_name']); ?></td><td><?php echo (int) $g['total']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="panel">
            <h3>Enrollment by Section</h3>
            <table class="data-table">
                <thead><tr><th>Section</th><th>Students</th></tr></thead>
                <tbody>
                <?php if (empty($bySection)): ?><tr><td colspan="2" class="muted" style="text-align:center;">No data.</td></tr><?php endif; ?>
                <?php foreach ($bySection as $s): ?>
                    <tr><td><?php echo clean($s['section_label']); ?></td><td><?php echo (int) $s['total']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="panel">
            <h3>Enrollment Status Summary</h3>
            <table class="data-table">
                <thead><tr><th>Status</th><th>Students</th></tr></thead>
                <tbody>
                <?php if (empty($byStatus)): ?><tr><td colspan="2" class="muted" style="text-align:center;">No data.</td></tr><?php endif; ?>
                <?php foreach ($byStatus as $s): ?>
                    <tr><td><?php echo status_badge($s['status']); ?></td><td><?php echo (int) $s['total']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>

    <section class="panel">
        <h3>Recent Enrollment Activities</h3>
        <?php if (empty($recentActivity)): ?>
            <p class="muted">No enrollment activity yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Student</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($recentActivity as $a): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($a['date_applied'])); ?></td>
                        <td><a href="student_view.php?id=<?php echo $a['student_id']; ?>"><?php echo clean($a['lastname'] . ', ' . $a['firstname']); ?></a></td>
                        <td><?php echo status_badge($a['status']); ?></td>
                        <td><?php echo clean($a['remarks'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3>School Announcements</h3>
            <a href="announcements.php" class="btn-small">Manage</a>
        </div>
        <?php if (empty($announcements)): ?>
            <p class="muted">No announcements posted yet.</p>
        <?php else: ?>
            <ul class="announcement-list">
                <?php foreach ($announcements as $a): ?>
                    <li>
                        <strong><?php echo clean($a['title']); ?></strong>
                        <span class="muted"> &mdash; <?php echo date('M d, Y', strtotime($a['date_posted'])); ?></span>
                        <p><?php echo nl2br(clean($a['content'])); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
