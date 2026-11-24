<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Admin QR management (2026-08n): Regenerate / Activate / Deactivate a
 * student's Digital Student ID. Same GET-link-with-onclick-confirm()
 * pattern as student_archive.php (this module's existing precedent for a
 * quick per-row admin action from the Student Management list).
 *
 * Regenerating issues a brand-new token, which immediately invalidates
 * the previous QR code/verify.php link (see regenerate_qr_token()) -
 * destructive enough that, like student_delete.php, it's restricted to
 * the Administrator role. Activate/Deactivate doesn't touch the token
 * (re-activating restores the same QR rather than issuing a new one) but
 * is still an Administrator-only action since it controls whether that
 * QR verifies as a valid ID at all.
 */
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only Administrators can regenerate or activate/deactivate a student\'s QR code.');
    redirect('students.php');
}

$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $stmt->execute([$id]);
    if ($stmt->fetchColumn()) {
        if ($action === 'regenerate') {
            regenerate_qr_token($pdo, $id);
            flash('success', "This student's QR code has been regenerated. The previous QR code is now invalid.");
        } elseif ($action === 'deactivate') {
            set_qr_status($pdo, $id, 'inactive');
            flash('success', "This student's QR code has been deactivated.");
        } elseif ($action === 'activate') {
            set_qr_status($pdo, $id, 'active');
            flash('success', "This student's QR code has been reactivated.");
        }
    }
}

redirect('students.php');
