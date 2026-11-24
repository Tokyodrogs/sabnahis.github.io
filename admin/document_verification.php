<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Cross-student document review queue. Redesigned (2026-08) to show ONE
// ROW PER STUDENT instead of one row per uploaded document - the previous
// version joined documents directly, so a student with 4 uploads appeared
// as 4 separate rows. Now documents are aggregated per student_id and the
// Registrar drills into document_verification_view.php to review/approve
// each file. enrollment_view.php still has its own inline document review
// section for reviewing documents as part of a full application review;
// that's untouched and works independently of this page.
$pageTitle = 'Document Verification';

// REQUIRED_DOCUMENTS keys are a fixed internal constant (not user input),
// so inlining them as SQL literals below is safe - no injection surface.
$requiredKeys = array_keys(REQUIRED_DOCUMENTS);
$requiredTotal = count($requiredKeys);
$requiredList = "'" . implode("','", array_map(function ($k) { return str_replace("'", '', $k); }, $requiredKeys)) . "'";

// ---- Filters ----
$statusFilter = $_GET['status'] ?? 'Pending';
$typeFilter = $_GET['student_type'] ?? '';
$gradeFilter = $_GET['grade_level_id'] ?? '';
$sectionFilter = $_GET['section_id'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT s.student_id, MIN(s.lrn) AS lrn, MIN(s.enrollment_no) AS enrollment_no, MIN(s.lastname) AS lastname,
        MIN(s.firstname) AS firstname, MIN(s.middlename) AS middlename,
        MIN(s.student_type) AS student_type, MIN(s.status) AS enrollment_status,
        MIN(g.grade_name) AS grade_name, MIN(sec.section_name) AS section_name,
        COALESCE((SELECT MIN(e.date_applied) FROM enrollment e WHERE e.student_id = s.student_id), MIN(d.uploaded_at)) AS application_date,
        COUNT(d.doc_id) AS total_uploaded,
        SUM(CASE WHEN d.doc_type IN ($requiredList) THEN 1 ELSE 0 END) AS required_submitted,
        SUM(CASE WHEN d.doc_type IN ($requiredList) AND d.status = 'Approved' THEN 1 ELSE 0 END) AS required_approved,
        SUM(CASE WHEN d.doc_type IN ($requiredList) AND d.status = 'Rejected' THEN 1 ELSE 0 END) AS required_rejected,
        SUM(CASE WHEN d.doc_type IN ($requiredList) AND d.status IN ('Pending','Re-upload Requested') THEN 1 ELSE 0 END) AS required_pending
    FROM students s
    JOIN documents d ON d.student_id = s.student_id
    LEFT JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
    LEFT JOIN sections sec ON sec.section_id = s.section_id
    WHERE s.is_archived = 0";
$params = [];

if ($typeFilter !== '') {
    $sql .= ' AND s.student_type = ?';
    $params[] = $typeFilter;
}
if ($gradeFilter !== '') {
    $sql .= ' AND s.grade_level_id = ?';
    $params[] = $gradeFilter;
}
if ($sectionFilter !== '') {
    $sql .= ' AND s.section_id = ?';
    $params[] = $sectionFilter;
}
if ($search !== '') {
    $sql .= ' AND (s.firstname LIKE ? OR s.lastname LIKE ? OR s.lrn LIKE ? OR s.enrollment_no LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' GROUP BY s.student_id ORDER BY application_date ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Compute the derived Overall Status per student (see
// compute_document_verification_status() in includes/functions.php for the
// precedence rules) and apply the status filter here, since it's not a raw
// database column.
$students = [];
$statusCounts = ['Pending' => 0, 'Verified' => 0, 'Incomplete' => 0, 'Rejected' => 0];
foreach ($rows as $r) {
    $r['required_total'] = $requiredTotal;
    $r['overall_status'] = compute_document_verification_status(
        $requiredTotal, (int) $r['required_submitted'], (int) $r['required_approved'],
        (int) $r['required_rejected'], (int) $r['required_pending']
    );
    $statusCounts[$r['overall_status']] = ($statusCounts[$r['overall_status']] ?? 0) + 1;
    if ($statusFilter === '' || $statusFilter === 'All' || $statusFilter === $r['overall_status']) {
        $students[] = $r;
    }
}

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$sectionsList = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    ORDER BY g.grade_level_id, sec.section_name")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Document Verification</h2>
    <p class="muted">Review submitted documents across all students, one row per student. Click View Requirements to review every document for that student in one place.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card"><span class="stat-value"><?php echo (int) $statusCounts['Pending']; ?></span><span class="stat-label">Pending Verification</span></div>
        <div class="stat-card"><span class="stat-value"><?php echo (int) $statusCounts['Verified']; ?></span><span class="stat-label">Verified</span></div>
        <div class="stat-card"><span class="stat-value"><?php echo (int) $statusCounts['Incomplete']; ?></span><span class="stat-label">Incomplete</span></div>
        <div class="stat-card"><span class="stat-value"><?php echo (int) $statusCounts['Rejected']; ?></span><span class="stat-label">Rejected</span></div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" value="<?php echo clean($search); ?>" placeholder="Search student no. or name">
        <select name="status">
            <?php foreach (['Pending', 'Verified', 'Incomplete', 'Rejected', 'All'] as $st): ?>
                <option value="<?php echo clean($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo clean($st); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="grade_level_id">
            <option value="">All Grade Levels</option>
            <?php foreach ($gradeLevels as $g): ?>
                <option value="<?php echo $g['grade_level_id']; ?>" <?php echo $gradeFilter == $g['grade_level_id'] ? 'selected' : ''; ?>><?php echo clean($g['grade_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="section_id">
            <option value="">All Sections</option>
            <?php foreach ($sectionsList as $sec): ?>
                <option value="<?php echo $sec['section_id']; ?>" <?php echo $sectionFilter == $sec['section_id'] ? 'selected' : ''; ?>><?php echo clean(($sec['grade_name'] ?: '-') . ' - ' . $sec['section_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="student_type">
            <option value="">All Enrollment Types</option>
            <?php foreach (APPLICANT_TYPES as $t => $meta): ?>
                <option value="<?php echo clean($t); ?>" <?php echo $typeFilter === $t ? 'selected' : ''; ?>><?php echo clean($meta['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        <a href="document_verification.php" class="btn-link">Reset</a>
    </form>

    <table class="data-table">
        <thead>
        <tr>
            <th>Application Date</th><th>Student No.</th><th>Student Name</th><th>Grade / Section</th>
            <th>Enrollment Type</th><th>Documents</th><th>Verification Progress</th><th>Overall Status</th><th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($students)): ?>
            <tr><td colspan="9" class="muted" style="text-align:center;">No student applications match this filter.</td></tr>
        <?php endif; ?>
        <?php foreach ($students as $s): ?>
            <?php
                $submittedPct = $s['required_total'] > 0 ? round(($s['required_submitted'] / $s['required_total']) * 100) : 0;
                $verifiedPct = $s['required_total'] > 0 ? round(($s['required_approved'] / $s['required_total']) * 100) : 0;
            ?>
            <tr>
                <td><?php echo $s['application_date'] ? date('M d, Y', strtotime($s['application_date'])) : '-'; ?></td>
                <td><?php echo clean($s['enrollment_no'] ?: ($s['lrn'] ?: '-')); ?></td>
                <td><a href="student_view.php?id=<?php echo $s['student_id']; ?>"><?php echo clean($s['lastname'] . ', ' . $s['firstname']); ?></a></td>
                <td><?php echo clean(($s['grade_name'] ?: '-') . ' / ' . ($s['section_name'] ?: 'Unassigned')); ?></td>
                <td><?php echo clean($s['student_type'] ?: '-'); ?></td>
                <td>
                    <?php echo (int) $s['required_submitted']; ?> / <?php echo (int) $s['required_total']; ?> Documents Submitted
                    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?php echo $submittedPct; ?>%;"></div></div>
                </td>
                <td>
                    <?php echo (int) $s['required_approved']; ?> / <?php echo (int) $s['required_total']; ?> Approved
                    <div class="progress-bar-track"><div class="progress-bar-fill progress-bar-fill-verified" style="width:<?php echo $verifiedPct; ?>%;"></div></div>
                </td>
                <td><?php echo document_verification_status_badge($s['overall_status']); ?></td>
                <td><a href="document_verification_view.php?student_id=<?php echo $s['student_id']; ?>" class="btn-small">View Requirements</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
