<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Soft-delete (Archive / Restore) for teachers - mirrors student_archive.php.
// Archiving sets teachers.status to 'Inactive' (the column that already
// existed for this purpose) rather than adding a redundant column. An
// archived/Inactive teacher is hidden from the default Teacher Management
// list but keeps every assignment/schedule/advisory row intact, and can be
// restored to Active at any time.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can archive or restore teacher records.');
    redirect('teachers.php');
}

$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'archive';

if ($id > 0) {
    if ($action === 'restore') {
        $pdo->prepare("UPDATE teachers SET status = 'Active' WHERE teacher_id = ?")->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_restore', "Restored teacher #$id to Active");
        flash('success', 'Teacher record restored to Active status.');
    } else {
        $pdo->prepare("UPDATE teachers SET status = 'Inactive' WHERE teacher_id = ?")->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_archive', "Archived teacher #$id (set Inactive)");
        flash('success', 'Teacher record archived (marked Inactive). It is hidden from the active list but not deleted - restore it anytime.');
    }
}

redirect('teachers.php');
