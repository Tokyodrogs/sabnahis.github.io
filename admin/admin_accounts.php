<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Administrator only: this is where Registrar (and additional
// Administrator) logins for the admin panel get created. Enforced
// server-side, not just hidden from the sidebar.
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Only the Administrator can manage admin panel accounts.');
    redirect('dashboard.php');
}

$pageTitle = 'Admin Panel Accounts';
$errors = [];
$validRoles = ['Administrator', 'Registrar', 'ICT', 'Guidance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', $validRoles, true) ? $_POST['role'] : 'Administrator';

        if ($username === '' || $fullname === '' || $password === '') {
            $errors[] = 'Username, full name, and password are all required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM admin WHERE username = ?');
            $dup->execute([$username]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = 'That username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO admin (username, password, fullname, role) VALUES (?,?,?,?)')
                    ->execute([$username, $hash, $fullname, $role]);
                flash('success', "$role account created (username: $username).");
                redirect('admin_accounts.php');
            }
        }
    } elseif ($action === 'change_role') {
        $id = (int) ($_POST['admin_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', $validRoles, true) ? $_POST['role'] : 'Administrator';
        if ($id === (int) $_SESSION['admin_id'] && $role !== 'Administrator') {
            $errors[] = 'You cannot demote your own currently logged-in account away from Administrator.';
        } else {
            $pdo->prepare('UPDATE admin SET role = ? WHERE admin_id = ?')->execute([$role, $id]);
            flash('success', 'Role updated.');
            redirect('admin_accounts.php');
        }
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['admin_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE admin SET password = ? WHERE admin_id = ?')->execute([$hash, $id]);
            flash('success', 'Password reset successfully.');
            redirect('admin_accounts.php');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['admin_id'] ?? 0);
        if ($id === (int) $_SESSION['admin_id']) {
            $errors[] = 'You cannot delete your own currently logged-in account.';
        } else {
            $pdo->prepare('DELETE FROM admin WHERE admin_id = ?')->execute([$id]);
            flash('success', 'Account deleted.');
            redirect('admin_accounts.php');
        }
    }
}

$accounts = $pdo->query('SELECT * FROM admin ORDER BY role, fullname')->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Admin Panel Accounts</h2>
    <p class="muted">All roles log in here at admin/login.php with one shared session system - the Role determines what each account can see and do. Only Administrator accounts get schedule create/edit/delete controls or access to Academic Oversight / Academic Accounts / this page; Registrar, ICT, and Guidance currently share the same baseline access until dedicated ICT/Guidance request queues are built.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <section class="panel">
        <h3>Create Account</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <label>Full Name *<input type="text" name="fullname" required></label>
                <label>Username *<input type="text" name="username" required></label>
                <label>Password *<input type="password" name="password" minlength="8" required></label>
                <label>Role
                    <select name="role">
                        <option value="Registrar">Registrar</option>
                        <option value="Administrator">Administrator</option>
                        <option value="ICT">ICT</option>
                        <option value="Guidance">Guidance</option>
                    </select>
                </label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Create Account</button></div>
        </form>
    </section>

    <table class="data-table">
        <thead><tr><th>Full Name</th><th>Username</th><th>Role</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $a): ?>
            <tr>
                <td><?php echo clean($a['fullname']); ?></td>
                <td><?php echo clean($a['username']); ?></td>
                <td>
                    <form method="POST" style="display:inline-flex;gap:6px;">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="admin_id" value="<?php echo $a['admin_id']; ?>">
                        <select name="role" onchange="this.form.requestSubmit()">
                            <option value="Administrator" <?php echo $a['role']==='Administrator'?'selected':''; ?>>Administrator</option>
                            <option value="Registrar" <?php echo $a['role']==='Registrar'?'selected':''; ?>>Registrar</option>
                            <option value="ICT" <?php echo $a['role']==='ICT'?'selected':''; ?>>ICT</option>
                            <option value="Guidance" <?php echo $a['role']==='Guidance'?'selected':''; ?>>Guidance</option>
                        </select>
                    </form>
                </td>
                <td class="actions">
                    <form method="POST" style="display:inline;" onsubmit="return promptResetPassword(event, this);">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="admin_id" value="<?php echo $a['admin_id']; ?>">
                        <input type="hidden" name="new_password" value="">
                        <button type="submit" class="btn-small">Reset Password</button>
                    </form>
                    <?php if ($a['admin_id'] != $_SESSION['admin_id']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this account?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="admin_id" value="<?php echo $a['admin_id']; ?>">
                            <button type="submit" class="link-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<script>
function promptResetPassword(event, form) {
    var pw = prompt('Enter a new password for this account (min 8 characters):');
    if (pw === null) { event.preventDefault(); return false; }
    form.new_password.value = pw;
    return true;
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
