<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Permanent removal is Administrator-only. The Registrar's day-to-day
// equivalent is student_archive.php (soft-delete - see its header comment).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Only the Administrator can permanently delete a student record. Use Archive instead.');
    redirect('students.php');
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Remove uploaded files from disk first
    $docStmt = $pdo->prepare('SELECT file_name FROM documents WHERE student_id = ?');
    $docStmt->execute([$id]);
    foreach ($docStmt->fetchAll() as $doc) {
        $path = __DIR__ . '/../uploads/' . $doc['file_name'];
        if (is_file($path)) unlink($path);
    }

    $photoStmt = $pdo->prepare('SELECT photo FROM students WHERE student_id = ?');
    $photoStmt->execute([$id]);
    $existingPhoto = $photoStmt->fetchColumn();
    if ($existingPhoto) {
        delete_student_photo($existingPhoto);
    }

    $stmt = $pdo->prepare('DELETE FROM students WHERE student_id = ?');
    $stmt->execute([$id]);
    flash('success', 'Student record deleted.');
}

redirect('students.php');
