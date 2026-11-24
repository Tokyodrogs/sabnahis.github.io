<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Very basic throttling against brute force
        if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;

        if ($_SESSION['login_attempts'] >= 8) {
            $error = 'Too many failed attempts. Please wait a moment and try again.';
        } else {
            $stmt = $pdo->prepare('SELECT admin_id, username, password, fullname, role FROM admin WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_fullname'] = $admin['fullname'];
                $_SESSION['admin_role']     = $admin['role'] ?? 'Administrator';
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_activity']  = time();
                redirect('dashboard.php');
            } else {
                $_SESSION['login_attempts']++;
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administrator / Registrar Login - <?php echo SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body class="login-body">
<div class="login-box">
    <h1><?php echo SITE_SHORT_NAME; ?></h1>
    <p class="subtitle">Enrollment System &mdash; Administrator / Registrar Login</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo clean($error); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-info">Your session has expired. Please log in again.</div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn-primary">Log In</button>
    </form>
    <p class="muted" style="text-align:center;font-size:13px;">Registrar accounts also sign in here &mdash; use your Registrar username and password.</p>
    <p class="back-link"><a href="<?php echo BASE_URL; ?>login.php?as=student">Student? Log in here</a></p>
    <p class="back-link"><a href="<?php echo BASE_URL; ?>index.php">&larr; Back to school website</a></p>
</div>
</body>
</html>
