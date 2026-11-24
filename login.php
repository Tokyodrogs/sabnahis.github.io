<?php
/**
 * Combined login chooser - the single "Login" entry point linked from the
 * public site header. It renders the Administrator AND Academic
 * Coordinator login forms (as switchable tabs) but does NOT implement any
 * login logic itself: each form posts straight to its own existing,
 * isolated handler (admin/login.php or academic/login.php), so both
 * authentication domains stay completely separate (see
 * academic/includes/auth_check.php for why that separation matters). This
 * page is purely a shared landing point/UI convenience.
 *
 * Student login intentionally does NOT appear here - it has its own
 * dedicated page (student/login.php, linked from the public nav's Student
 * Portal dropdown) so it's kept separate from staff sign-in.
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

// Generate a CSRF token for the Academic Coordinator login form using its
// own session (name "sabnahis_academic").
session_name('sabnahis_academic');
session_start();
$academicCsrfToken = csrf_token('academic_login');
session_write_close();

$asParam = $_GET['as'] ?? '';
$defaultTab = $asParam === 'academic' ? 'academic' : 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?php echo SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
</head>
<body class="login-body">
<div class="login-box login-box-wide">
    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="<?php echo SITE_SHORT_NAME; ?> logo" class="login-logo">
    <h1><?php echo SITE_SHORT_NAME; ?></h1>
    <p class="subtitle">Sign in to your account</p>

    <div class="login-tabs">
        <button type="button" class="btn-tab <?php echo $defaultTab === 'admin' ? 'active' : ''; ?>" id="tabAdminBtn">Administrator / Registrar</button>
        <button type="button" class="btn-tab <?php echo $defaultTab === 'academic' ? 'active' : ''; ?>" id="tabAcademicBtn">Academic Coordinator</button>
    </div>

    <p class="muted" style="text-align:center; font-size:13px;">Looking for the Student Portal? <a href="<?php echo BASE_URL; ?>student/login.php">Log in here</a>.</p>

    <div id="adminPanel" class="login-panel" <?php echo $defaultTab === 'admin' ? '' : 'hidden'; ?>>
        <p class="muted" style="margin-top:-8px;">For both Administrator and Registrar staff accounts.</p>
        <form method="POST" action="<?php echo BASE_URL; ?>admin/login.php" autocomplete="off">
            <label for="admin_username">Username</label>
            <input type="text" id="admin_username" name="username" required>

            <label for="admin_password">Password</label>
            <div class="password-field">
                <input type="password" id="admin_password" name="password" required>
                <button type="button" class="password-toggle">Show</button>
            </div>

            <button type="submit" class="btn-primary">Log In</button>
        </form>
    </div>

    <div id="academicPanel" class="login-panel" <?php echo $defaultTab === 'academic' ? '' : 'hidden'; ?>>
        <form method="POST" action="<?php echo BASE_URL; ?>academic/login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo clean($academicCsrfToken); ?>">

            <label for="academic_username">Username</label>
            <input type="text" id="academic_username" name="username" required>

            <label for="academic_password">Password</label>
            <div class="password-field">
                <input type="password" id="academic_password" name="password" required>
                <button type="button" class="password-toggle">Show</button>
            </div>

            <button type="submit" class="btn-primary">Log In as Academic Coordinator</button>
        </form>
    </div>

    <p class="back-link"><a href="<?php echo BASE_URL; ?>index.php">&larr; Back to school website</a></p>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/student.js"></script>
<script>
(function () {
    var tabs = {
        admin: { btn: document.getElementById('tabAdminBtn'), panel: document.getElementById('adminPanel') },
        academic: { btn: document.getElementById('tabAcademicBtn'), panel: document.getElementById('academicPanel') }
    };
    function show(active) {
        Object.keys(tabs).forEach(function (key) {
            var isActive = key === active;
            tabs[key].panel.hidden = !isActive;
            tabs[key].btn.classList.toggle('active', isActive);
        });
    }
    Object.keys(tabs).forEach(function (key) {
        tabs[key].btn.addEventListener('click', function () { show(key); });
    });
})();
</script>
</body>
</html>
