<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$type = $_GET['type'] ?? '';
$syId = $_GET['sy_id'] ?? '';
$sectionId = $_GET['section_id'] ?? '';
$gradeId = $_GET['grade_level_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$format = $_GET['format'] ?? 'print';

$syLabel = 'All School Years';
if ($syId !== '') {
    $s = $pdo->prepare('SELECT school_year FROM school_year WHERE sy_id = ?');
    $s->execute([$syId]);
    $syLabel = $s->fetchColumn() ?: $syLabel;
}

// All filter values below are cast to int or checked against a fixed
// whitelist before interpolation - never raw user input - so this stays
// injection-safe without needing every report's WHERE clause to be built
// as a separate prepared statement.
$whereSy = $syId !== '' ? ' AND s.sy_id = ' . (int) $syId : '';
$whereGrade = $gradeId !== '' ? ' AND s.grade_level_id = ' . (int) $gradeId : '';
$allowedStatuses = ['Pending', 'Verified', 'Approved', 'Rejected', 'Enrolled'];
$whereStatus = in_array($statusFilter, $allowedStatuses, true) ? " AND s.status = '" . $statusFilter . "'" : '';
$whereExtra = $whereGrade . $whereStatus;

$reportTitle = '';
$rows = [];
$columns = [];

switch ($type) {
    case 'enrolled_list':
        $reportTitle = 'List of Enrolled Students';
        $columns = ['Enrollment No.', 'LRN', 'Name', 'Grade Level', 'Strand', 'School Year'];
        $sql = "SELECT s.enrollment_no, s.lrn, s.lastname, s.firstname, s.middlename, g.grade_name, st.strand_name, sy.school_year
                FROM students s
                LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                LEFT JOIN strands st ON s.strand_id = st.strand_id
                LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
                WHERE s.status = 'Enrolled' $whereSy $whereExtra
                ORDER BY s.lastname";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['enrollment_no'] ?: '-', $r['lrn'] ?: '-', $r['lastname'] . ', ' . $r['firstname'] . ' ' . $r['middlename'], $r['grade_name'] ?? '-', $r['strand_name'] ?? '-', $r['school_year'] ?? '-'];
        }
        break;

    case 'statistics':
        $reportTitle = 'Enrollment Statistics';
        $columns = ['Status', 'Total Students'];
        $sql = "SELECT s.status, COUNT(*) as total FROM students s WHERE s.is_archived = 0 $whereSy $whereExtra GROUP BY s.status";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['status'], $r['total']];
        }
        break;

    case 'per_strand':
        $reportTitle = 'Students per Strand';
        $columns = ['Strand', 'Total Students'];
        $sql = "SELECT COALESCE(st.strand_name, 'N/A - JHS') as strand_name, COUNT(s.student_id) as total
                FROM students s LEFT JOIN strands st ON s.strand_id = st.strand_id
                WHERE s.is_archived = 0 $whereSy $whereExtra
                GROUP BY strand_name ORDER BY strand_name";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['strand_name'], $r['total']];
        }
        break;

    case 'per_grade':
        $reportTitle = 'Students per Grade Level';
        $columns = ['Grade Level', 'Total Students'];
        $sql = "SELECT COALESCE(g.grade_name, 'Unassigned') as grade_name, COUNT(s.student_id) as total
                FROM students s LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                WHERE s.is_archived = 0 $whereSy $whereExtra
                GROUP BY grade_name ORDER BY grade_name";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['grade_name'], $r['total']];
        }
        break;

    case 'class_list':
        $secStmt = $pdo->prepare("SELECT sec.*, g.grade_name, sy.school_year FROM sections sec
            LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
            LEFT JOIN school_year sy ON sy.sy_id = sec.sy_id
            WHERE sec.section_id = ?");
        $secStmt->execute([$sectionId]);
        $section = $secStmt->fetch();
        if (!$section) die('Section not found.');

        $reportTitle = 'Class List: ' . ($section['grade_name'] ?: '-') . ' - ' . $section['section_name'];
        $syLabel = $section['school_year'] ?: $syLabel;
        $columns = ['LRN', 'Name', 'Sex', 'Status'];
        $sql = "SELECT s.lrn, s.lastname, s.firstname, s.middlename, s.sex, s.status
                FROM students s WHERE s.section_id = ? ORDER BY s.lastname, s.firstname";
        $listStmt = $pdo->prepare($sql);
        $listStmt->execute([$sectionId]);
        foreach ($listStmt as $r) {
            $rows[] = [$r['lrn'] ?: '-', $r['lastname'] . ', ' . $r['firstname'] . ' ' . $r['middlename'], $r['sex'], $r['status']];
        }
        break;

    case 'master_list':
        $reportTitle = 'Master List of Students';
        $columns = ['Student No.', 'LRN', 'Name', 'Sex', 'Grade Level', 'Section', 'Strand', 'Status', 'Registration Type'];
        $sql = "SELECT s.enrollment_no, s.lrn, s.lastname, s.firstname, s.middlename, s.sex, s.student_type,
                    g.grade_name, sec.section_name, st.strand_name, s.status
                FROM students s
                LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                LEFT JOIN sections sec ON s.section_id = sec.section_id
                LEFT JOIN strands st ON s.strand_id = st.strand_id
                WHERE s.is_archived = 0 $whereSy $whereExtra
                ORDER BY s.lastname, s.firstname";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['enrollment_no'] ?: '-', $r['lrn'] ?: '-', $r['lastname'] . ', ' . $r['firstname'] . ' ' . $r['middlename'],
                $r['sex'], $r['grade_name'] ?? '-', $r['section_name'] ?? 'Unassigned', $r['strand_name'] ?? '-', $r['status'], $r['student_type']];
        }
        break;

    case 'section_list':
        $reportTitle = 'Section List';
        $columns = ['Section', 'Grade Level', 'Adviser', 'School Year', 'Enrolled Count', 'Capacity'];
        $sql = "SELECT sec.section_name, g.grade_name, sy.school_year, sec.capacity,
                    CONCAT(t.firstname, ' ', t.lastname) AS adviser_name,
                    (SELECT COUNT(*) FROM students s2 WHERE s2.section_id = sec.section_id AND s2.is_archived = 0) AS enrolled_count
                FROM sections sec
                LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
                LEFT JOIN school_year sy ON sy.sy_id = sec.sy_id
                LEFT JOIN teachers t ON t.teacher_id = sec.adviser_teacher_id
                WHERE 1=1" . ($syId !== '' ? ' AND sec.sy_id = ' . (int) $syId : '') . ($gradeId !== '' ? ' AND sec.grade_level_id = ' . (int) $gradeId : '') . "
                ORDER BY sy.school_year DESC, g.grade_level_id, sec.section_name";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['section_name'], $r['grade_name'] ?? '-', $r['adviser_name'] ?: '-', $r['school_year'] ?? '-',
                $r['enrolled_count'], $r['capacity'] !== null ? $r['capacity'] : 'No limit'];
        }
        break;

    case 'student_directory':
        $reportTitle = 'Student Directory';
        $columns = ['Name', 'LRN', 'Grade Level', 'Section', 'Address', 'Contact', 'Email', 'Guardian', 'Guardian Contact'];
        $sql = "SELECT s.lastname, s.firstname, s.middlename, s.lrn, g.grade_name, sec.section_name,
                    s.address, s.contact, s.email, s.guardian_name, s.guardian_contact
                FROM students s
                LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                LEFT JOIN sections sec ON s.section_id = sec.section_id
                WHERE s.is_archived = 0 $whereSy $whereExtra
                ORDER BY s.lastname, s.firstname";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['lastname'] . ', ' . $r['firstname'] . ' ' . $r['middlename'], $r['lrn'] ?: '-', $r['grade_name'] ?? '-',
                $r['section_name'] ?? 'Unassigned', $r['address'] ?: '-', $r['contact'] ?: '-', $r['email'] ?: '-',
                $r['guardian_name'] ?: '-', $r['guardian_contact'] ?: '-'];
        }
        break;

    case 'by_sex':
        $reportTitle = 'Students by Sex';
        $columns = ['Grade Level', 'Male', 'Female', 'Total'];
        $sql = "SELECT COALESCE(g.grade_name, 'Unassigned') as grade_name,
                    SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) as female,
                    COUNT(*) as total
                FROM students s
                LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                WHERE s.is_archived = 0 $whereSy $whereExtra
                GROUP BY grade_name ORDER BY grade_name";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['grade_name'], $r['male'], $r['female'], $r['total']];
        }
        break;

    default:
        die('Unknown report type.');
}

// ---- CSV export ("Export to Excel") - native CSV, opens directly in
// Excel/Sheets, no external library needed. ----
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $reportTitle) . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders special characters correctly
    fputcsv($out, $columns);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo clean($reportTitle); ?> - <?php echo SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/print.css'); ?>">
</head>
<body>
<div class="print-toolbar no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>
<div class="print-sheet">
    <div class="print-header">
        <h2><?php echo SITE_NAME; ?></h2>
        <h3><?php echo clean($reportTitle); ?></h3>
        <p>School Year: <strong><?php echo clean($syLabel); ?></strong></p>
        <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
    </div>

    <table class="print-table">
        <tr>
            <?php foreach ($columns as $c): ?><th><?php echo clean($c); ?></th><?php endforeach; ?>
        </tr>
        <?php if (empty($rows)): ?>
            <tr><td colspan="<?php echo count($columns); ?>" style="text-align:center;">No data available.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $cell): ?><td><?php echo clean($cell); ?></td><?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>

    <p class="print-footer">Total records: <?php echo count($rows); ?></p>
</div>
</body>
</html>
