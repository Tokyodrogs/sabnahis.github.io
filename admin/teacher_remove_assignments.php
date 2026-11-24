<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// "Remove All Assignments" - the required clean-up step before a teacher
// with existing dependencies can be permanently deleted (see
// teacher_delete.php / teacher_view.php). Removes teacher_assignments and
// class_schedules rows for this teacher, and clears sections.adviser_teacher_id
// wherever this teacher was the adviser (the section itself is NOT deleted -
// only the adviser link is cleared, since a section is a Registrar/
// Administrator-owned record independent of who advises it).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can remove teacher assignments.');
    redirect('teachers.php');
}

$teacherId = (int) ($_POST['teacher_id'] ?? 0);

if ($teacherId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE teacher_id = ?');
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        flash('error', 'Teacher not found.');
        redirect('teachers.php');
    }

    $deps = get_teacher_dependencies($pdo, $teacherId);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM class_schedules WHERE teacher_id = ?')->execute([$teacherId]);
        $pdo->prepare('DELETE FROM teacher_assignments WHERE teacher_id = ?')->execute([$teacherId]);
        $pdo->prepare('UPDATE sections SET adviser_teacher_id = NULL WHERE adviser_teacher_id = ?')->execute([$teacherId]);

        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_remove_assignments',
            "Removed all assignments for teacher #$teacherId ({$teacher['lastname']}, {$teacher['firstname']}): "
            . "{$deps['assignments']} subject assignment(s), {$deps['schedules']} schedule entr(y/ies), {$deps['advisory']} advisory section(s)");

        $pdo->commit();
        flash('success', 'All assignments removed. This teacher can now be permanently deleted if needed.');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Failed to remove assignments: ' . $e->getMessage());
    }

    redirect('teacher_view.php?id=' . $teacherId);
}

redirect('teachers.php');
