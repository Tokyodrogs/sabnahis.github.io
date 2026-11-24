<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Student Portal accounts are auto-created on enrollment (see
// create_student_account() in includes/functions.php, called from
// enrollment_view.php once a student is Approved/Enrolled, and from
// students.php's "Create Missing Accounts" bulk action). This page is
// where the Registrar manages accounts that already exist: reset a
// forgotten password, or Activate/Deactivate/Lock/Unlock access.
$pageTitle = 'Student Accounts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '', 'student_accounts')) {
        flash('success', 'Your session has expired. Please try again.');
        redirect('student_accounts.php');
    }

    $accountId = (int) ($_POST['account_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $acctStmt = $pdo->prepare('SELECT * FROM student_accounts WHERE account_id = ?');
    $acctStmt->execute([$accountId]);
    $account = $acctStmt->fetch();

    if (!$account) {
        flash('success', 'Account not found.');
        redirect('student_accounts.php');
    }

    if ($action === 'reset') {
        $hash = password_hash(TEMP_STUDENT_PASSWORD, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE student_accounts SET password = ?, first_login = 1, failed_attempts = 0, password_changed_at = NULL, status = IF(status = 'Locked', 'Active', status) WHERE account_id = ?")
            ->execute([$hash, $accountId]);
        notify_student($pdo, $account['student_id'], 'account',
            'Your Student Portal password was reset by the Registrar. Temporary password: ' . TEMP_STUDENT_PASSWORD . '. You will be asked to set a new password on your next login.');
        flash('success', "Password reset for {$account['username']}. Temporary password: " . TEMP_STUDENT_PASSWORD);
    } elseif ($action === 'activate') {
        $pdo->prepare("UPDATE student_accounts SET status = 'Active', failed_attempts = 0 WHERE account_id = ?")->execute([$accountId]);
        notify_student($pdo, $account['student_id'], 'account', 'Your Student Portal account has been activated.');
        flash('success', "{$account['username']} activated.");
    } elseif ($action === 'deactivate') {
        $pdo->prepare("UPDATE student_accounts SET status = 'Inactive' WHERE account_id = ?")->execute([$accountId]);
        notify_student($pdo, $account['student_id'], 'account', 'Your Student Portal account has been deactivated. Contact the Registrar for assistance.');
        flash('success', "{$account['username']} deactivated.");
    } elseif ($action === 'lock') {
        $pdo->prepare("UPDATE student_accounts SET status = 'Locked' WHERE account_id = ?")->execute([$accountId]);
        notify_student($pdo, $account['student_id'], 'account', 'Your Student Portal account has been locked. Contact the Registrar for assistance.');
        flash('success', "{$account['username']} locked.");
    } elseif ($action === 'unlock') {
        $pdo->prepare("UPDATE student_accounts SET status = 'Active', failed_attempts = 0 WHERE account_id = ?")->execute([$accountId]);
        notify_student($pdo, $account['student_id'], 'account', 'Your Student Portal account has been unlocked. You may now log in again.');
        flash('success', "{$account['username']} unlocked.");
    } else {
        flash('success', 'Unknown action.');
    }
    redirect('student_accounts.php' . (!empty($_POST['redirect_qs']) ? '?' . $_POST['redirect_qs'] : ''));
}

// ---- Filters ----
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT sa.*, s.firstname, s.lastname, s.middlename, g.grade_name, sec.section_name
    FROM student_accounts sa
    JOIN students s ON s.student_id = sa.student_id
    LEFT JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
    LEFT JOIN sections sec ON sec.section_id = s.section_id
    WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (sa.username LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ? OR s.lrn LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($statusFilter !== '' && in_array($statusFilter, ['Active', 'Inactive', 'Locked'], true)) {
    $sql .= ' AND sa.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY s.lastname, s.firstname';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) c FROM student_accounts GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$csrfToken = csrf_token('student_accounts');
$redirectQs = http_build_query(array_filter(['search' => $search, 'status' => $statusFilter]));

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Student Accounts</h2>
    <p class="muted">Manage Student Portal login accounts. Accounts are created automatically when a student is Approved or Officially Enrolled.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card"><span class="stat-value"><?php echo (int) array_sum($counts); ?></span><span class="stat-label">Total Accounts</span></div>
        <div class="stat-card"><span class="stat-value"><?php echo (int) ($counts['Active'] ?? 0); ?></span><span class="stat-label">Active</span></div>
        <div class="stat-card"><span class="stat-value"><?php echo (int) ($counts['Inactive'] ?? 0); ?></span><span class="stat-label">Inactive</span></div>
        <div class="stat-card"><span class="stat-value"><?php echo (int) ($counts['Locked'] ?? 0); ?></span><span class="stat-label">Locked</span></div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" value="<?php echo clean($search); ?>" placeholder="Search username, name, or LRN">
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['Active', 'Inactive', 'Locked'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        <a href="student_accounts.php" class="btn-link">Reset</a>
    </form>

    <table class="data-table">
        <thead>
        <tr><th>Username</th><th>Student</th><th>Grade / Section</th><th>Status</th><th>First Login Pending</th><th>Last Login</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($accounts)): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;">No student accounts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $a): ?>
            <tr>
                <td><?php echo clean($a['username']); ?></td>
                <td><?php echo clean($a['lastname'] . ', ' . $a['firstname']); ?></td>
                <td><?php echo clean(($a['grade_name'] ?: '-') . ' / ' . ($a['section_name'] ?: 'Unassigned')); ?></td>
                <td><span class="badge badge-<?php echo strtolower($a['status']); ?>"><?php echo clean($a['status']); ?></span></td>
                <td><?php echo $a['first_login'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo $a['last_login'] ? date('M d, Y h:i A', strtotime($a['last_login'])) : 'Never'; ?></td>
                <td class="actions">
                    <form method="POST" class="form-inline">
                        <input type="hidden" name="account_id" value="<?php echo $a['account_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                        <input type="hidden" name="redirect_qs" value="<?php echo clean($redirectQs); ?>">
                        <button type="submit" name="action" value="reset" class="link-plain" onclick="return confirm('Reset password for <?php echo clean(addslashes($a['username'])); ?> to the default temporary password?');">Reset Password</button>
                        <?php if ($a['status'] !== 'Active'): ?>
                            <button type="submit" name="action" value="activate" class="link-success">Activate</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="deactivate" class="link-plain">Deactivate</button>
                        <?php endif; ?>
                        <?php if ($a['status'] === 'Locked'): ?>
                            <button type="submit" name="action" value="unlock" class="link-success">Unlock</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="lock" class="link-danger" onclick="return confirm('Lock this account?');">Lock</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="muted" style="margin-top:16px;">Need to create an account for a student who doesn't have one yet? Use "Create Portal Account" on their profile, or "Create Missing Accounts" on the <a href="students.php">Student Management</a> page.</p>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
