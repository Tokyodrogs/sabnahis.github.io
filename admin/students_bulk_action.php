<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Bulk actions (Delete / Archive / Restore / Export / Print) for multiple
// selected students at once, submitted from the checkboxes + toolbar on
// students.php. Archive/Restore/Export/Print follow the same permission
// level as the existing single-record actions in student_archive.php (any
// logged-in admin panel user). Permanent Delete stays Administrator-only,
// matching student_delete.php.
$isAdministrator = ($_SESSION['admin_role'] ?? 'Administrator') === 'Administrator';

$action = $_POST['bulk_action'] ?? '';
$ids = array_map('intval', $_POST['student_ids'] ?? []);
$ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

if (empty($ids)) {
    flash('error', 'No students were selected.');
    redirect('students.php');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

if ($action === 'archive' || $action === 'restore') {
    $flag = $action === 'restore' ? 0 : 1;
    $pdo->prepare("UPDATE students SET is_archived = ? WHERE student_id IN ($placeholders)")
        ->execute(array_merge([$flag], $ids));
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'student_bulk_' . $action, count($ids) . ' student(s): ' . implode(',', $ids));
    flash('success', count($ids) . ' student record(s) ' . ($action === 'restore' ? 'restored to the active list.' : 'archived.'));
    redirect('students.php');
}

if ($action === 'delete') {
    if (!$isAdministrator) {
        flash('error', 'Only the Administrator can permanently delete student records. Use Archive instead.');
        redirect('students.php');
    }

    $nameStmt = $pdo->prepare("SELECT student_id, lastname, firstname, photo FROM students WHERE student_id IN ($placeholders)");
    $nameStmt->execute($ids);
    $rows = $nameStmt->fetchAll();

    $deleted = [];
    foreach ($rows as $row) {
        $sid = $row['student_id'];

        $docStmt = $pdo->prepare('SELECT file_name FROM documents WHERE student_id = ?');
        $docStmt->execute([$sid]);
        foreach ($docStmt->fetchAll() as $doc) {
            $path = __DIR__ . '/../uploads/' . $doc['file_name'];
            if (is_file($path)) unlink($path);
        }
        if (!empty($row['photo'])) {
            delete_student_photo($row['photo']);
        }

        $pdo->prepare('DELETE FROM students WHERE student_id = ?')->execute([$sid]);
        $deleted[] = $row['lastname'] . ', ' . $row['firstname'];
    }

    if ($deleted) {
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'student_bulk_delete', count($deleted) . ' student(s) deleted: ' . implode('; ', $deleted));
    }

    flash('success', count($deleted) . ' student record(s) permanently deleted.');
    redirect('students.php');
}

if ($action === 'export') {
    $stmt = $pdo->prepare("SELECT s.*, g.grade_name, st.strand_name, sy.school_year
        FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN strands st ON s.strand_id = st.strand_id
        LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
        WHERE s.student_id IN ($placeholders) ORDER BY s.lastname, s.firstname");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['LRN', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Grade Level', 'Strand', 'School Year', 'Status', 'Enrollment No.']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['lrn'], $r['lastname'], $r['firstname'], $r['middlename'], $r['sex'], $r['grade_name'], $r['strand_name'], $r['school_year'], $r['status'], $r['enrollment_no']]);
    }
    fclose($out);
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'student_bulk_export', count($rows) . ' student(s) exported to CSV');
    exit;
}

if ($action === 'print') {
    $stmt = $pdo->prepare("SELECT s.*, g.grade_name, st.strand_name, sy.school_year
        FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN strands st ON s.strand_id = st.strand_id
        LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
        WHERE s.student_id IN ($placeholders) ORDER BY s.lastname, s.firstname");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'student_bulk_print', count($rows) . ' student(s) sent to print view');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Selected Students - <?php echo SITE_SHORT_NAME; ?></title>
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
            <h3>Selected Students</h3>
            <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        <table class="print-table">
            <tr><th>LRN</th><th>Name</th><th>Grade Level</th><th>Strand</th><th>School Year</th><th>Status</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo clean($r['lrn'] ?: '-'); ?></td>
                    <td><?php echo clean($r['lastname'] . ', ' . $r['firstname'] . ' ' . $r['middlename']); ?></td>
                    <td><?php echo clean($r['grade_name'] ?? '-'); ?></td>
                    <td><?php echo clean($r['strand_name'] ?? '-'); ?></td>
                    <td><?php echo clean($r['school_year'] ?? '-'); ?></td>
                    <td><?php echo clean($r['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p class="print-footer">Total records: <?php echo count($rows); ?></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

flash('error', 'Unknown bulk action.');
redirect('students.php');
