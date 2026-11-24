<?php
/**
 * Include at the top of every protected Academic Management page.
 *
 * The Academic Management portal is a fully separate authentication domain
 * from both the admin panel and the Student Portal (own session cookie
 * name "sabnahis_academic", own credentials table academic_accounts). This
 * is what actually enforces "only the Academic Coordinator (or Principal,
 * via the admin panel's oversight pages) can manage schedules": a
 * Registrar's admin session can never satisfy this check, and there is no
 * shared login between the two portals.
 *
 * Self-contained (like admin/includes/auth_check.php and
 * student/includes/auth_check.php): every academic/*.php page requires
 * this file FIRST, so it pulls in its own dependencies rather than assume
 * they're already loaded.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('sabnahis_academic');
    session_start();
}

if (empty($_SESSION['academic_account_id'])) {
    redirect('login.php');
}

// Idle session timeout (mirrors the admin panel's and Student Portal's pattern).
if (!empty($_SESSION['academic_last_activity'])
    && (time() - $_SESSION['academic_last_activity']) > STUDENT_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    redirect('login.php?timeout=1');
}
$_SESSION['academic_last_activity'] = time();
