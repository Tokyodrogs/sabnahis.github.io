<?php
/**
 * Include at the top of every protected Student Portal page.
 *
 * The Student Portal is a deliberately separate authentication domain from
 * the admin panel (see includes/functions.php header comment): it uses its
 * own session COOKIE NAME ("sabnahis_student", set below) so a student
 * session can never be read by admin/includes/auth_check.php and vice
 * versa, even in the same browser. All session keys are also prefixed
 * student_* as a second layer of separation.
 *
 * This is how the "Student role" access boundary (item 1 of the Student
 * Portal spec) is enforced: there is no shared session, no shared login,
 * and no code path that lets a student session satisfy an admin check or
 * an admin session satisfy this one.
 */
// Self-contained, like admin/includes/auth_check.php: every student/*.php
// page requires this file FIRST (before config/db.php, config/constants.php,
// includes/functions.php), so it must pull in its own dependencies rather
// than assume they're already loaded.
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('sabnahis_student');
    session_start();
}

if (empty($_SESSION['student_account_id'])) {
    redirect('login.php');
}

// Idle session timeout (mirrors the admin panel's pattern).
if (!empty($_SESSION['student_last_activity'])
    && (time() - $_SESSION['student_last_activity']) > STUDENT_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    redirect('login.php?timeout=1');
}
$_SESSION['student_last_activity'] = time();

// Force a password change before anything else is reachable (item 5 of the
// Student Portal spec). change_password.php itself sets
// $_SESSION['student_allow_first_login_page'] while it's rendering so it
// doesn't redirect to itself.
if (!empty($_SESSION['student_first_login']) && empty($_SESSION['student_allow_first_login_page'])) {
    redirect('change_password.php');
}
