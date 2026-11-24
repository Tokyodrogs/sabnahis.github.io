<?php
/**
 * Academic Management portal login - entirely separate from admin/login.php
 * and student/login.php. Own session cookie name ("sabnahis_academic") and
 * own credentials table (academic_accounts), so this session can never be
 * read by the admin panel's or Student Portal's auth checks.
 */
session_name('sabnahis_academic');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['academic_account_id'])) {
    redirect('dashboard.php');
}

$error = null;
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $usernameValue = $username;

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'academic_login')) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM academic_accounts WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if (!$account) {
            $error = 'Invalid username or password.';
        } elseif ($account['status'] === 'Locked') {
            $error = 'This account has been locked due to too many failed login attempts. Please contact the Administrator to unlock it.';
        } elseif ($account['status'] === 'Inactive') {
            $error = 'This account is inactive. Please contact the Administrator.';
        } elseif (!password_verify($password, $account['password'])) {
            $attempts = $account['failed_attempts'] + 1;
            if ($attempts >= STUDENT_MAX_FAILED_ATTEMPTS) {
                $pdo->prepare("UPDATE academic_accounts SET failed_attempts = ?, status = 'Locked' WHERE academic_account_id = ?")
                    ->execute([$attempts, $account['academic_account_id']]);
                $error = 'Too many failed attempts. This account is now locked. Please contact the Administrator.';
            } else {
                $pdo->prepare('UPDATE academic_accounts SET failed_attempts = ? WHERE academic_account_id = ?')
                    ->execute([$attempts, $account['academic_account_id']]);
                $remaining = STUDENT_MAX_FAILED_ATTEMPTS - $attempts;
                $error = "Invalid username or password. {$remaining} attempt(s) remaining before this account is locked.";
            }
        } else {
            session_regenerate_id(true);
            $_SESSION['academic_account_id']   = $account['academic_account_id'];
            $_SESSION['academic_username']      = $account['username'];
            $_SESSION['academic_fullname']      = $account['fullname'];
            $_SESSION['academic_last_activity'] = time();

            $pdo->prepare("UPDATE academic_accounts SET failed_attempts = 0, last_login = NOW() WHERE academic_account_id = ?")
                ->execute([$account['academic_account_id']]);

            audit_log($pdo, 'academic', $account['academic_account_id'], 'login', 'Successful login');

            redirect('dashboard.php');
        }
    }
}

$csrfToken = csrf_token('academic_login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Academic Management Login - <?php echo SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body class="login-body">
<div class="login-box">
    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="<?php echo SITE_SHORT_NAME; ?> logo" class="login-logo">
    <h1><?php echo SITE_SHORT_NAME; ?></h1>
    <p class="subtitle">Academic Management Login</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo clean($error); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-info">Your session has expired. Please log in again.</div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">

        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?php echo clean($usernameValue); ?>" required autofocus>

        <label for="password">Password</label>
        <div class="password-field">
            <input type="password" id="password" name="password" required>
            <button type="button" class="password-toggle">Show</button>
        </div>

        <button type="submit" class="btn-primary">Log In</button>
    </form>
    <p class="back-link"><a href="<?php echo BASE_URL; ?>login.php">Student or Administrator? Log in here</a></p>
    <p class="back-link"><a href="<?php echo BASE_URL; ?>index.php">&larr; Back to school website</a></p>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/student.js"></script>
</body>
</html>
