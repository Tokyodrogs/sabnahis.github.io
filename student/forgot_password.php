<?php
/**
 * Placeholder "Forgot Password" destination (Student Portal spec item 4:
 * "future-ready"). Self-service email/SMS reset is not wired up yet since
 * this project has no outbound mail/SMS configuration - rather than fake a
 * reset flow that can't actually deliver a code, this gives students a
 * clear, real next step (contact the registrar) and a stable URL that a
 * future automated reset flow can replace without changing the login page.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - <?php echo SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body class="login-body">
<div class="login-box">
    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="<?php echo SITE_SHORT_NAME; ?> logo" class="login-logo">
    <h1><?php echo SITE_SHORT_NAME; ?></h1>
    <p class="subtitle">Forgot Password</p>
    <div class="alert alert-info">
        Self-service password reset isn't available yet. Please visit or call the Registrar's Office with your
        Student Number so they can verify your identity and reset your password for you.
    </div>
    <p class="back-link"><a href="login.php">&larr; Back to Student Login</a></p>
</div>
</body>
</html>
