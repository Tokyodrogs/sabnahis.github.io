<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Administrator only: provisioning logins for the separate Academic
// Management portal. Enforced server-side, not just hidden from the sidebar.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Only the Administrator can manage Academic Coordinator accounts.');
    redirect('dashboard.php');
}

$pageTitle = 'Academic Coordinator Accounts';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $fullname === '' || $password === '') {
            $errors[] = 'Username, full name, and password are all required.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM academic_accounts WHERE username = ?');
            $dup->execute([$username]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = 'That username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO academic_accounts (username, password, fullname) VALUES (?,?,?)')
                    ->execute([$username, $hash, $fullname]);
                flash('success', 'Academic Coordinator account created (username: ' . $username . ').');
                redirect('academic_accounts.php');
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) ($_POST['academic_account_id'] ?? 0);
        $pdo->prepare("UPDATE academic_accounts SET status = IF(status='Active','Inactive','Active') WHERE academic_account_id = ?")->execute([$id]);
        redirect('academic_accounts.php');
    } elseif ($action === 'unlock') {
        $id = (int) ($_POST['academic_account_id'] ?? 0);
        $pdo->prepare("UPDATE academic_accounts SET status = 'Active', failed_attempts = 0 WHERE academic_account_id = ?")->execute([$id]);
        flash('success', 'Account unlocked.');
        redirect('academic_accounts.php');
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['academic_account_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE academic_accounts SET password = ?, failed_attempts = 0, status = \'Active\' WHERE academic_account_id = ?')
                ->execute([$hash, $id]);
            flash('success', 'Password reset successfully.');
            redirect('academic_accounts.php');
        }
    }
}

$accounts = $pdo->query('SELECT * FROM academic_accounts ORDER BY fullname')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Academic Coordinator Accounts</h2>
    <p class="muted">These accounts log in separately at the Academic Management portal (<?php echo clean(BASE_URL); ?>academic/login.php) - they cannot log into this admin panel, and admin accounts cannot log into that portal.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <section class="panel">
        <h3>Create Account</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <label>Full Name *<input type="text" name="fullname" required></label>
                <label>Username *<input type="text" name="username" required></label>
                <label>Password *<input type="password" name="password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required></label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Create Account</button></div>
        </form>
    </section>

    <table class="data-table">
        <thead><tr><th>Full Name</th><th>Username</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($accounts)): ?><tr><td colspan="5" class="muted" style="text-align:center;">No Academic Coordinator accounts yet.</td></tr><?php endif; ?>
        <?php foreach ($accounts as $a): ?>
            <tr>
                <td><?php echo clean($a['fullname']); ?></td>
                <td><?php echo clean($a['username']); ?></td>
                <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo clean($a['status']); ?></span></td>
                <td><?php echo $a['last_login'] ? date('M d, Y h:i A', strtotime($a['last_login'])) : 'Never'; ?></td>
                <td class="actions">
                    <?php if ($a['status'] === 'Locked'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="unlock">
                            <input type="hidden" name="academic_account_id" value="<?php echo $a['academic_account_id']; ?>">
                            <button type="submit" class="btn-small">Unlock</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="academic_account_id" value="<?php echo $a['academic_account_id']; ?>">
                            <button type="submit" class="btn-small"><?php echo $a['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return promptResetPassword(event, this);">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="academic_account_id" value="<?php echo $a['academic_account_id']; ?>">
                        <input type="hidden" name="new_password" value="">
                        <button type="submit" class="btn-small">Reset Password</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<script>
function promptResetPassword(event, form) {
    var pw = prompt('Enter a new password for this account (min <?php echo PASSWORD_MIN_LENGTH; ?> characters):');
    if (pw === null) { event.preventDefault(); return false; }
    form.new_password.value = pw;
    return true;
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
