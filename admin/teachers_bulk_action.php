<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Bulk actions (Delete / Archive / Restore / Export / Print) for multiple
// selected teachers at once, submitted from the checkboxes + toolbar on
// teachers.php. Administrator-only, matching the single-record actions.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can perform bulk actions on teacher records.');
    redirect('teachers.php');
}

$action = $_POST['bulk_action'] ?? '';
$ids = array_map('intval', $_POST['teacher_ids'] ?? []);
$ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

if (empty($ids)) {
    flash('error', 'No teachers were selected.');
    redirect('teachers.php');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

if ($action === 'archive' || $action === 'restore') {
    $newStatus = $action === 'restore' ? 'Active' : 'Inactive';
    $pdo->prepare("UPDATE teachers SET status = ? WHERE teacher_id IN ($placeholders)")
        ->execute(array_merge([$newStatus], $ids));
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_bulk_' . $action, count($ids) . ' teacher(s): ' . implode(',', $ids));
    flash('success', count($ids) . ' teacher record(s) ' . ($action === 'restore' ? 'restored to Active.' : 'archived (marked Inactive).'));
    redirect('teachers.php');
}

if ($action === 'delete') {
    $nameStmt = $pdo->prepare("SELECT teacher_id, lastname, firstname FROM teachers WHERE teacher_id IN ($placeholders)");
    $nameStmt->execute($ids);
    $teachersById = [];
    foreach ($nameStmt->fetchAll() as $row) $teachersById[$row['teacher_id']] = $row;

    $deleted = [];
    $skipped = [];

    foreach ($ids as $tid) {
        if (!isset($teachersById[$tid])) continue;
        $deps = get_teacher_dependencies($pdo, $tid);
        $name = $teachersById[$tid]['lastname'] . ', ' . $teachersById[$tid]['firstname'];
        if ($deps['blocked']) {
            $skipped[] = $name . ' (has ' . $deps['assignments'] . ' assignment(s), ' . $deps['schedules'] . ' schedule(s), ' . $deps['advisory'] . ' advisory section(s))';
            continue;
        }
        $pdo->prepare('DELETE FROM teachers WHERE teacher_id = ?')->execute([$tid]);
        $deleted[] = $name;
    }

    if ($deleted) {
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_bulk_delete', count($deleted) . ' teacher(s) deleted: ' . implode('; ', $deleted));
    }

    $summary = count($deleted) . ' Teacher(s) deleted successfully.';
    if ($skipped) {
        $summary .= ' ' . count($skipped) . ' Teacher(s) could not be deleted because they are assigned to schedules: ' . implode(' | ', $skipped);
    }
    flash($skipped ? 'error' : 'success', $summary);
    redirect('teachers.php');
}

if ($action === 'export') {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_id IN ($placeholders) ORDER BY lastname, firstname");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="teachers_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee No.', 'Last Name', 'First Name', 'Middle Name', 'Email', 'Contact', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['employee_no'], $r['lastname'], $r['firstname'], $r['middlename'], $r['email'], $r['contact'], $r['status']]);
    }
    fclose($out);
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_bulk_export', count($rows) . ' teacher(s) exported to CSV');
    exit;
}

if ($action === 'print') {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_id IN ($placeholders) ORDER BY lastname, firstname");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_bulk_print', count($rows) . ' teacher(s) sent to print view');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Selected Teachers - <?php echo SITE_SHORT_NAME; ?></title>
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
            <h3>Selected Teachers</h3>
            <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        <table class="print-table">
            <tr><th>Employee No.</th><th>Name</th><th>Email</th><th>Contact</th><th>Status</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo clean($r['employee_no'] ?: '-'); ?></td>
                    <td><?php echo clean($r['lastname'] . ', ' . $r['firstname'] . ' ' . $r['middlename']); ?></td>
                    <td><?php echo clean($r['email']); ?></td>
                    <td><?php echo clean($r['contact']); ?></td>
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
redirect('teachers.php');
