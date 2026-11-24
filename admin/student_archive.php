<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Soft-delete (Archive / Restore) - distinct from student_delete.php's
// permanent removal. This is the Registrar's everyday "take this record
// out of the active list without erasing it" action, matching DepEd
// records-retention practice. Archived students are excluded from the
// default Student Management list, dashboards, and reports, but the row
// (and every related document/payment/account) stays intact and can be
// restored at any time.
$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'archive';

if ($id > 0) {
    if ($action === 'restore') {
        $pdo->prepare('UPDATE students SET is_archived = 0 WHERE student_id = ?')->execute([$id]);
        flash('success', 'Student record restored.');
    } else {
        $pdo->prepare('UPDATE students SET is_archived = 1 WHERE student_id = ?')->execute([$id]);
        flash('success', 'Student record archived. It is hidden from the active list but not deleted - restore it anytime.');
    }
}

redirect('students.php');
