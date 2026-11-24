<?php
/**
 * Include at the top of every protected admin page.
 * Starts the session (if not already started) and blocks access
 * for anyone who is not logged in.
 */
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    redirect('login.php');
}

// Simple idle timeout (30 minutes)
$timeout = 1800;
if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    redirect('login.php?timeout=1');
}
$_SESSION['last_activity'] = time();
