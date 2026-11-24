<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Read-only. Teacher assignment is created/edited exclusively in the
// Academic Management portal (see academic/teacher_assignment.php).
$pageTitle = 'Teacher Assignment (View)';

$syFilter = $_GET['sy_id'] ?? '';
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();

$sql = "SELECT ta.*, CONCAT(t.lastname, ', ', t.firstname) AS teacher_name,
        sub.subject_code, sub.subject_title, sec.section_name, g.grade_name, sy.school_year
    FROM teacher_assignments ta
    JOIN teachers t ON t.teacher_id = ta.teacher_id
    JOIN subjects sub ON sub.subject_id = ta.subject_id
    JOIN sections sec ON sec.section_id = ta.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    JOIN school_year sy ON sy.sy_id = ta.sy_id";
$params = [];
if ($syFilter !== '') { $sql .= ' WHERE ta.sy_id = ?'; $params[] = $syFilter; }
$sql .= ' ORDER BY sy.school_year DESC, t.lastname';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Teacher Assignment (View Only)</h2>
    <p class="muted">Teacher assignments are created and edited by the Academic Coordinator in the Academic Management module.</p>

    <form method="GET" class="filter-bar">
        <select name="sy_id" onchange="this.form.submit()">
            <option value="">All School Years</option>
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?php echo $sy['sy_id']; ?>" <?php echo (string) $syFilter === (string) $sy['sy_id'] ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <table class="data-table">
        <thead><tr><th>Teacher</th><th>Subject</th><th>Grade &amp; Section</th><th>School Year</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($assignments)): ?><tr><td colspan="5" class="muted" style="text-align:center;">No teacher assignments found.</td></tr><?php endif; ?>
        <?php foreach ($assignments as $a): ?>
            <tr>
                <td><?php echo clean($a['teacher_name']); ?></td>
                <td><?php echo clean($a['subject_code'] . ' - ' . $a['subject_title']); ?></td>
                <td><?php echo clean(($a['grade_name'] ?: '-') . ' - ' . $a['section_name']); ?></td>
                <td><?php echo clean($a['school_year']); ?></td>
                <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo clean($a['status']); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
