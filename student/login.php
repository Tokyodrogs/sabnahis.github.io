<?php
/**
 * Student Portal login - entirely separate from admin/login.php.
 * Uses its own session cookie name ("sabnahis_student") and its own
 * student_accounts credential table, so it never shares session state
 * or a login form with the Administrator login.
 */
session_name('sabnahis_student');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['student_account_id'])) {
    redirect('dashboard.php');
}

$error = null;
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);
    $usernameValue = $username;

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'student_login')) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT sa.*, s.firstname, s.lastname, s.status AS enrollment_status
            FROM student_accounts sa
            JOIN students s ON s.student_id = sa.student_id
            WHERE sa.username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if (!$account) {
            $error = 'Invalid username or password.';
        } elseif ($account['status'] === 'Locked') {
            $error = 'This account has been locked due to too many failed login attempts. Please contact the registrar to unlock it.';
        } elseif ($account['status'] === 'Inactive') {
            $error = 'This account is inactive. Please contact the registrar.';
        } elseif (!password_verify($password, $account['password'])) {
            $attempts = $account['failed_attempts'] + 1;
            if ($attempts >= STUDENT_MAX_FAILED_ATTEMPTS) {
                $pdo->prepare("UPDATE student_accounts SET failed_attempts = ?, status = 'Locked' WHERE account_id = ?")
                    ->execute([$attempts, $account['account_id']]);
                $error = 'Too many failed attempts. This account is now locked. Please contact the registrar.';
            } else {
                $pdo->prepare('UPDATE student_accounts SET failed_attempts = ? WHERE account_id = ?')
                    ->execute([$attempts, $account['account_id']]);
                $remaining = STUDENT_MAX_FAILED_ATTEMPTS - $attempts;
                $error = "Invalid username or password. {$remaining} attempt(s) remaining before this account is locked.";
            }
        } else {
            session_regenerate_id(true);
            $_SESSION['student_account_id']  = $account['account_id'];
            $_SESSION['student_id']          = $account['student_id'];
            $_SESSION['student_username']    = $account['username'];
            $_SESSION['student_fullname']    = trim($account['firstname'] . ' ' . $account['lastname']);
            $_SESSION['student_first_login'] = (bool) $account['first_login'];
            $_SESSION['student_last_activity'] = time();

            $pdo->prepare("UPDATE student_accounts SET failed_attempts = 0, last_login = NOW() WHERE account_id = ?")
                ->execute([$account['account_id']]);

            audit_log($pdo, 'student', $account['account_id'], 'login', 'Successful login');

            if ($rememberMe && ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), time() + STUDENT_REMEMBER_DAYS * 86400,
                    $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            redirect($account['first_login'] ? 'change_password.php' : 'dashboard.php');
        }
    }
}

$csrfToken = csrf_token('student_login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login - <?php echo SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body class="login-body">
<div class="login-box">
    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="<?php echo SITE_SHORT_NAME; ?> logo" class="login-logo">
    <h1><?php echo SITE_SHORT_NAME; ?></h1>
    <p class="subtitle">Student Portal Login</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo clean($error); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-info">Your session has expired. Please log in again.</div>
    <?php endif; ?>
    <?php if (isset($_GET['changed'])): ?>
        <div class="alert alert-success">Your password was changed. Please log in with your new password.</div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">

        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?php echo clean($usernameValue); ?>" required autofocus>

        <label for="password">Password</label>
        <div class="password-field">
            <input type="password" id="password" name="password" required>
            <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">Show</button>
        </div>

        <label class="checkbox-label"><input type="checkbox" name="remember_me" value="1"> Remember me</label>

        <button type="submit" class="btn-primary">Log In</button>
    </form>
    <p class="back-link"><a href="forgot_password.php">Forgot your password?</a></p>
    <p class="back-link"><a href="<?php echo BASE_URL; ?>login.php?as=admin">Administrator? Log in here</a></p>
    <p class="back-link"><a href="<?php echo BASE_URL; ?>index.php">&larr; Back to school website</a></p>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/student.js"></script>
</body>
</html>
