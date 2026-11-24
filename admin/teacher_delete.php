<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Permanent removal is Administrator-only, and only allowed when the
// teacher has no existing assignments/schedule/advisory dependencies (see
// get_teacher_dependencies() in includes/functions.php). This mirrors the
// safety model requested for Teacher Safe Delete: block with a clear
// message and a path to clean up dependencies first, rather than silently
// cascading deletes through the Academic Management module's data.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can permanently delete a teacher record. Use Archive instead.');
    redirect('teachers.php');
}

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE teacher_id = ?');
    $stmt->execute([$id]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        flash('error', 'Teacher not found.');
        redirect('teachers.php');
    }

    $deps = get_teacher_dependencies($pdo, $id);

    if ($deps['blocked']) {
        flash('error', 'This teacher cannot be deleted because there are existing assignments. View the teacher record to review or remove them first.');
        redirect('teacher_view.php?id=' . $id);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM teachers WHERE teacher_id = ?')->execute([$id]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_delete',
            "Permanently deleted teacher #$id ({$teacher['lastname']}, {$teacher['firstname']}) - no dependencies found");
        $pdo->commit();
        flash('success', 'Teacher record permanently deleted.');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Delete failed: ' . $e->getMessage());
    }
}

redirect('teachers.php');
