<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'View Student';
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year, sec.section_name
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.student_id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    flash('success', 'Student not found.');
    redirect('students.php');
}

$docStmt = $pdo->prepare('SELECT * FROM documents WHERE student_id = ?');
$docStmt->execute([$id]);
$documents = $docStmt->fetchAll();

$portalTablesMissing = false;
$portalAccount = null;
try {
    $acctStmt = $pdo->prepare('SELECT * FROM student_accounts WHERE student_id = ?');
    $acctStmt->execute([$id]);
    $portalAccount = $acctStmt->fetch();
} catch (PDOException $e) {
    $portalTablesMissing = true;
}

if (!$portalTablesMissing && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_account') {
    $account = create_student_account($pdo, $id);
    if ($account && $account['created']) {
        flash('success', "Student Portal account created. Username: {$account['username']} | Temporary password: " . TEMP_STUDENT_PASSWORD);
    } else {
        flash('success', 'This student already has a Student Portal account.');
    }
    redirect('student_view.php?id=' . $id);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2><?php echo clean($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']); ?></h2>
        <div>
            <?php if ($student['is_archived']): ?><span class="badge badge-inactive">Archived</span><?php endif; ?>
            <a href="student_print.php?id=<?php echo $id; ?>" class="btn-secondary" target="_blank">Print Profile</a>
            <a href="student_edit.php?id=<?php echo $id; ?>" class="btn-secondary">Edit</a>
            <a href="enrollment_view.php?student_id=<?php echo $id; ?>" class="btn-primary">Enrollment Details</a>
            <?php if ($student['is_archived']): ?>
                <a href="student_archive.php?id=<?php echo $id; ?>&action=restore" class="btn-secondary" onclick="return confirm('Restore this student record?');">Restore</a>
            <?php else: ?>
                <a href="student_archive.php?id=<?php echo $id; ?>&action=archive" class="btn-secondary" onclick="return confirm('Archive this student record?');">Archive</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <section class="panel student-profile-head">
        <div class="student-profile-photo"><?php echo student_photo_html($student['photo'], 'lg'); ?></div>
        <table class="detail-table student-profile-summary">
            <tr><th>LRN</th><td><?php echo clean($student['lrn'] ?: 'Not yet assigned'); ?></td></tr>
            <tr><th>Full Name</th><td><?php echo clean($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['extension']); ?></td></tr>
            <tr><th>Status</th><td><?php echo status_badge($student['status']); ?></td></tr>
            <tr><th>Registration Type</th><td><?php echo clean($student['student_type']); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Personal Information</h3>
        <table class="detail-table">
            <tr><th>Sex</th><td><?php echo clean($student['sex']); ?></td><th>Birthdate</th><td><?php echo clean($student['birthdate']); ?></td></tr>
            <tr><th>Age</th><td><?php echo clean($student['age']); ?></td><th>Contact</th><td><?php echo clean($student['contact']); ?></td></tr>
            <tr><th>Email</th><td colspan="3"><?php echo clean($student['email']); ?></td></tr>
            <tr><th>Address</th><td colspan="3"><?php echo clean($student['address']); ?></td></tr>
            <tr><th>Previous School</th><td colspan="3"><?php echo clean($student['previous_school']); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Academic Information</h3>
        <table class="detail-table">
            <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?></td><th>Strand</th><td><?php echo clean($student['strand_name'] ?? '-'); ?></td></tr>
            <tr><th>Section</th><td><?php echo clean($student['section_name'] ?? 'Not yet assigned'); ?></td><th>School Year</th><td><?php echo clean($student['school_year'] ?? '-'); ?></td></tr>
            <tr><th>Status</th><td><?php echo status_badge($student['status']); ?></td><th>Enrollment No.</th><td><?php echo clean($student['enrollment_no'] ?: 'Not yet generated'); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Parent / Guardian</h3>
        <table class="detail-table">
            <tr><th>Name</th><td><?php echo clean($student['guardian_name']); ?></td><th>Contact</th><td><?php echo clean($student['guardian_contact']); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Student Portal Account</h3>
        <?php if ($portalTablesMissing): ?>
            <p class="muted">Student Portal tables were not found. Import <code>database/migration_2026_07_student_portal.sql</code>.</p>
        <?php elseif ($portalAccount): ?>
            <table class="detail-table">
                <tr><th>Username</th><td><?php echo clean($portalAccount['username']); ?></td>
                    <th>Status</th><td><span class="badge badge-<?php echo strtolower($portalAccount['status']); ?>"><?php echo clean($portalAccount['status']); ?></span></td></tr>
                <tr><th>First Login Pending</th><td><?php echo $portalAccount['first_login'] ? 'Yes' : 'No'; ?></td>
                    <th>Last Login</th><td><?php echo $portalAccount['last_login'] ? date('M d, Y h:i A', strtotime($portalAccount['last_login'])) : 'Never'; ?></td></tr>
            </table>
        <?php else: ?>
            <p class="muted">This student does not have a Student Portal account yet.</p>
            <form method="POST">
                <input type="hidden" name="action" value="create_account">
                <button type="submit" class="btn-secondary">Create Student Portal Account</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Uploaded Documents</h3>
        <?php if (empty($documents)): ?>
            <p class="muted">No documents uploaded (student may have been added manually by admin).</p>
        <?php else: ?>
            <ul class="doc-list">
                <?php foreach ($documents as $d): ?>
                    <li><?php echo clean(REQUIRED_DOCUMENTS[$d['doc_type']] ?? $d['doc_type']); ?> &mdash;
                        <a href="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($d['file_name']); ?>" target="_blank">View File</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <a href="students.php" class="btn-link">&larr; Back to Student List</a>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
