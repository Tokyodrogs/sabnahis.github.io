<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Student Management';

// Student Portal accounts table may not exist yet if the site owner hasn't
// imported database/migration_2026_07_student_portal.sql. Detect that up
// front so we can show a clear, actionable message instead of a raw SQL
// error, and skip the portal-account features below.
$portalTablesMissing = false;
try {
    $pdo->query('SELECT 1 FROM student_accounts LIMIT 1');
} catch (PDOException $e) {
    $portalTablesMissing = true;
}

if (!$portalTablesMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_account') {
        $targetId = (int) ($_POST['student_id'] ?? 0);
        $account = create_student_account($pdo, $targetId);
        if ($account && $account['created']) {
            flash('success', "Student Portal account created. Username: {$account['username']} | Temporary password: " . TEMP_STUDENT_PASSWORD);
        } elseif ($account) {
            flash('success', "This student already has a Student Portal account (username: {$account['username']}).");
        } else {
            flash('success', 'Could not create the account - student record not found.');
        }
        redirect('students.php');
    } elseif ($action === 'create_missing_accounts') {
        $missingStmt = $pdo->query('SELECT s.student_id FROM students s LEFT JOIN student_accounts sa ON sa.student_id = s.student_id WHERE sa.account_id IS NULL');
        $created = [];
        foreach ($missingStmt->fetchAll(PDO::FETCH_COLUMN) as $missingId) {
            $account = create_student_account($pdo, $missingId);
            if ($account && $account['created']) $created[] = $account['username'];
        }
        flash('success', $created
            ? count($created) . ' Student Portal account(s) created (temporary password for all: ' . TEMP_STUDENT_PASSWORD . '). Usernames: ' . implode(', ', $created)
            : 'Every student already has a Student Portal account.');
        redirect('students.php');
    }
}

// ---- Filters ----
$search      = trim($_GET['search'] ?? '');
$gradeFilter = $_GET['grade_level_id'] ?? '';
$strandFilter = $_GET['strand_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';
$syFilter    = $_GET['sy_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter  = $_GET['student_type'] ?? '';
$archiveFilter = $_GET['archived'] ?? 'active'; // active (default) | archived | all
$isAdministrator = ($_SESSION['admin_role'] ?? 'Administrator') === 'Administrator';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(s.lastname LIKE ? OR s.firstname LIKE ? OR s.middlename LIKE ? OR s.lrn LIKE ? OR s.enrollment_no LIKE ?)';
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($gradeFilter !== '') {
    $where[] = 's.grade_level_id = ?';
    $params[] = $gradeFilter;
}
if ($strandFilter !== '') {
    $where[] = 's.strand_id = ?';
    $params[] = $strandFilter;
}
if ($sectionFilter !== '') {
    $where[] = 's.section_id = ?';
    $params[] = $sectionFilter;
}
if ($syFilter !== '') {
    $where[] = 's.sy_id = ?';
    $params[] = $syFilter;
}
if ($statusFilter !== '') {
    $where[] = 's.status = ?';
    $params[] = $statusFilter;
}
if ($typeFilter !== '') {
    $where[] = 's.student_type = ?';
    $params[] = $typeFilter;
}
if ($archiveFilter === 'archived') {
    $where[] = 's.is_archived = 1';
} elseif ($archiveFilter !== 'all') {
    $where[] = 's.is_archived = 0';
}

$sql = 'SELECT s.*, g.grade_name, st.strand_name, sy.school_year'
    . ($portalTablesMissing ? '' : ', sa.username AS portal_username, sa.status AS portal_status')
    . ' FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN strands st ON s.strand_id = st.strand_id
        LEFT JOIN school_year sy ON s.sy_id = sy.sy_id'
    . ($portalTablesMissing ? '' : ' LEFT JOIN student_accounts sa ON sa.student_id = s.student_id');
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.lastname, s.firstname';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$strands     = $pdo->query('SELECT * FROM strands ORDER BY strand_name')->fetchAll();
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$sectionsList = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    ORDER BY g.grade_level_id, sec.section_name")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Student Management</h2>
        <?php if (!$portalTablesMissing): ?>
            <form method="POST" onsubmit="return confirm('Create Student Portal accounts for every student who doesn\'t have one yet?');">
                <input type="hidden" name="action" value="create_missing_accounts">
                <button type="submit" class="btn-secondary">Create Missing Portal Accounts</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($portalTablesMissing): ?>
        <div class="alert alert-error">
            The Student Portal database tables were not found. Import
            <code>database/migration_2026_07_student_portal.sql</code> via phpMyAdmin (or
            <code>database/schema.sql</code> for a brand-new install) before students can log in to the portal.
        </div>
    <?php endif; ?>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($err = flash('error')): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search by name or LRN..." value="<?php echo clean($search); ?>">

        <select name="grade_level_id">
            <option value="">All Grade Levels</option>
            <?php foreach ($gradeLevels as $g): ?>
                <option value="<?php echo $g['grade_level_id']; ?>" <?php echo $gradeFilter == $g['grade_level_id'] ? 'selected' : ''; ?>><?php echo clean($g['grade_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="strand_id">
            <option value="">All Strands</option>
            <?php foreach ($strands as $s): ?>
                <option value="<?php echo $s['strand_id']; ?>" <?php echo $strandFilter == $s['strand_id'] ? 'selected' : ''; ?>><?php echo clean($s['strand_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="section_id">
            <option value="">All Sections</option>
            <?php foreach ($sectionsList as $sec): ?>
                <option value="<?php echo $sec['section_id']; ?>" <?php echo $sectionFilter == $sec['section_id'] ? 'selected' : ''; ?>><?php echo clean(($sec['grade_name'] ?: '-') . ' - ' . $sec['section_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sy_id">
            <option value="">All School Years</option>
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?php echo $sy['sy_id']; ?>" <?php echo $syFilter == $sy['sy_id'] ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['Pending','Verified','Approved','Rejected','Enrolled'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st === 'Enrolled' ? 'Officially Enrolled' : $st; ?></option>
            <?php endforeach; ?>
        </select>

        <select name="student_type">
            <option value="">All Registration Types</option>
            <?php // Was a hardcoded ['New','Returning','Transferee'] list that had silently missed
                  // Balik-Aral since it was added - now sourced from APPLICANT_TYPES so it can't
                  // drift out of sync again (picks up ALS automatically too, 2026-08i). ?>
            <?php foreach (APPLICANT_TYPES as $t => $meta): ?>
                <option value="<?php echo clean($t); ?>" <?php echo $typeFilter === $t ? 'selected' : ''; ?>><?php echo clean($meta['label']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="archived">
            <option value="active" <?php echo $archiveFilter === 'active' ? 'selected' : ''; ?>>Active Only</option>
            <option value="archived" <?php echo $archiveFilter === 'archived' ? 'selected' : ''; ?>>Archived Only</option>
            <option value="all" <?php echo $archiveFilter === 'all' ? 'selected' : ''; ?>>All</option>
        </select>

        <button type="submit" class="btn-secondary">Filter</button>
        <a href="students.php" class="btn-link">Reset</a>
    </form>

    <form method="POST" action="students_bulk_action.php" id="bulkForm" target="_blank">
        <div class="bulk-toolbar">
            <span class="selected-count" id="selectedCount">Selected: 0 Record(s)</span>
            <button type="submit" name="bulk_action" value="archive" class="btn-secondary" id="btnArchive" disabled onclick="return confirmBulk('archive');">Archive Selected</button>
            <button type="submit" name="bulk_action" value="restore" class="btn-secondary" id="btnRestore" disabled onclick="return confirmBulk('restore');">Restore Selected</button>
            <button type="submit" name="bulk_action" value="export" class="btn-secondary" id="btnExport" disabled formtarget="_blank">Export Selected</button>
            <button type="submit" name="bulk_action" value="print" class="btn-secondary" id="btnPrint" disabled formtarget="_blank">Print Selected</button>
            <?php if ($isAdministrator): ?>
                <button type="submit" name="bulk_action" value="delete" class="btn-danger" id="btnDelete" disabled onclick="return confirmBulk('delete');">Delete Selected</button>
            <?php endif; ?>
        </div>
    </form>

    <table class="data-table">
        <thead>
        <tr>
            <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
            <th>Photo</th><th>LRN</th><th>Name</th><th>Sex</th><th>Grade Level</th><th>Strand</th><th>School Year</th><th>Status</th>
            <?php if (!$portalTablesMissing): ?><th>Portal Account</th><?php endif; ?>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($students)): ?>
            <tr><td colspan="<?php echo $portalTablesMissing ? 10 : 11; ?>" class="muted" style="text-align:center;">No students found.</td></tr>
        <?php endif; ?>
        <?php foreach ($students as $s): ?>
            <tr>
                <td class="checkbox-col"><input type="checkbox" class="row-check" form="bulkForm" name="student_ids[]" value="<?php echo $s['student_id']; ?>"></td>
                <td><?php echo student_photo_html($s['photo'], 'sm'); ?></td>
                <td><?php echo clean($s['lrn'] ?: '-'); ?></td>
                <td><?php echo clean($s['lastname'] . ', ' . $s['firstname'] . ' ' . $s['middlename']); ?></td>
                <td><?php echo clean($s['sex']); ?></td>
                <td><?php echo clean($s['grade_name'] ?? '-'); ?></td>
                <td><?php echo clean($s['strand_name'] ?? '-'); ?></td>
                <td><?php echo clean($s['school_year'] ?? '-'); ?></td>
                <td><?php echo status_badge($s['status']); ?></td>
                <?php if (!$portalTablesMissing): ?>
                <td>
                    <?php if (!empty($s['portal_username'])): ?>
                        <div><?php echo clean($s['portal_username']); ?></div>
                        <span class="badge badge-<?php echo strtolower($s['portal_status']); ?>"><?php echo clean($s['portal_status']); ?></span>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_account">
                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                            <button type="submit" class="btn-small">Create Account</button>
                        </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td class="actions">
                    <a href="student_view.php?id=<?php echo $s['student_id']; ?>">View</a>
                    <a href="student_edit.php?id=<?php echo $s['student_id']; ?>">Edit</a>
                    <a href="student_print.php?id=<?php echo $s['student_id']; ?>" target="_blank">Print</a>
                    <?php if ($s['is_archived']): ?>
                        <a href="student_archive.php?id=<?php echo $s['student_id']; ?>&action=restore" onclick="return confirm('Restore this student record to the active list?');">Restore</a>
                    <?php else: ?>
                        <a href="student_archive.php?id=<?php echo $s['student_id']; ?>&action=archive" onclick="return confirm('Archive this student record? It will be hidden from the active list but not deleted.');">Archive</a>
                    <?php endif; ?>
                    <a href="qr_image.php?student_id=<?php echo $s['student_id']; ?>" target="_blank">View QR</a>
                    <?php if ($isAdministrator): ?>
                        <a href="student_qr_action.php?id=<?php echo $s['student_id']; ?>&action=regenerate" onclick="return confirm('Are you sure you want to regenerate this student&#39;s QR Code?\n\nThe previous QR Code will immediately become invalid.');">Regenerate QR</a>
                        <?php if (($s['qr_status'] ?? 'active') === 'inactive'): ?>
                            <a href="student_qr_action.php?id=<?php echo $s['student_id']; ?>&action=activate" onclick="return confirm('Reactivate this student\'s QR code?');">Activate QR</a>
                        <?php else: ?>
                            <a href="student_qr_action.php?id=<?php echo $s['student_id']; ?>&action=deactivate" onclick="return confirm('Deactivate this student\'s QR code? It will no longer verify successfully until reactivated.');">Deactivate QR</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($isAdministrator): ?>
                        <a href="student_delete.php?id=<?php echo $s['student_id']; ?>" class="danger" onclick="return confirm('Permanently delete this student record? This cannot be undone.');">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<script src="<?php echo BASE_URL; ?>assets/js/bulk_actions.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
