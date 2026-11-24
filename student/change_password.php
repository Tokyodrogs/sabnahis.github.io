<?php
// Allow this page to render even when student_first_login is true - it IS
// the forced destination in that case (see student/includes/auth_check.php).
// Session must be started (with the Student Portal's own cookie name)
// BEFORE writing to $_SESSION, otherwise session_start() below would
// discard this flag when it loads the persisted session data.
if (session_status() === PHP_SESSION_NONE) {
    session_name('sabnahis_student');
    session_start();
}
$_SESSION['student_allow_first_login_page'] = true;

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Change Password';
$errors = [];
$isForced = !empty($_SESSION['student_first_login']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'change_password')) {
        $errors[] = 'Your session has expired. Please try again.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM student_accounts WHERE account_id = ?');
        $stmt->execute([$_SESSION['student_account_id']]);
        $account = $stmt->fetch();

        if (!$account || !password_verify($current, $account['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif ($new === $current) {
            $errors[] = 'New password must be different from your current password.';
        } else {
            $policyErrors = validate_password_policy($new);
            if ($policyErrors) {
                $errors[] = 'Password must contain ' . implode(', ', $policyErrors) . '.';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE student_accounts SET password = ?, first_login = 0, password_changed_at = NOW() WHERE account_id = ?')
                ->execute([$hash, $account['account_id']]);

            audit_log($pdo, 'student', $account['account_id'], 'password_change', 'Password changed successfully');
            notify_student($pdo, $_SESSION['student_id'], 'password', 'Your password was changed successfully.');

            $_SESSION['student_first_login'] = false;
            unset($_SESSION['student_allow_first_login_page']);

            flash('success', 'Password changed successfully. Please log in again with your new password.');
            redirect('logout.php');
        }
    }
}

$csrfToken = csrf_token('change_password');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Change Password</h2>

    <?php if ($isForced): ?>
        <div class="alert alert-info">
            This is your first login. For your security, you must set a new password before you can use the Student Portal.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <section class="panel" style="max-width:480px;">
        <form method="POST" class="form-grid" id="changePasswordForm">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">

            <label>Current Password
                <div class="password-field">
                    <input type="password" name="current_password" required>
                    <button type="button" class="password-toggle">Show</button>
                </div>
            </label>
            <label>New Password
                <div class="password-field">
                    <input type="password" name="new_password" id="newPassword" required>
                    <button type="button" class="password-toggle">Show</button>
                </div>
            </label>
            <label>Confirm New Password
                <div class="password-field">
                    <input type="password" name="confirm_password" required>
                    <button type="button" class="password-toggle">Show</button>
                </div>
            </label>

            <ul class="password-rules" id="passwordRules">
                <li data-rule="length">At least 8 characters</li>
                <li data-rule="upper">One uppercase letter</li>
                <li data-rule="lower">One lowercase letter</li>
                <li data-rule="number">One number</li>
                <li data-rule="special">One special character</li>
            </ul>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Change Password</button>
                <?php if (!$isForced): ?><a href="dashboard.php" class="btn-link">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>
</main>
<script src="<?php echo BASE_URL; ?>assets/js/student.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
