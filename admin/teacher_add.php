<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can add teacher records.');
    redirect('teachers.php');
}

$pageTitle = 'Add Teacher';
$errors = [];

$form = [
    'employee_no' => '', 'lastname' => '', 'firstname' => '', 'middlename' => '',
    'email' => '', 'contact' => '', 'status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    if ($form['lastname'] === '' || $form['firstname'] === '') $errors[] = 'Last name and first name are required.';

    if ($form['employee_no'] !== '') {
        $dup = $pdo->prepare('SELECT teacher_id FROM teachers WHERE employee_no = ?');
        $dup->execute([$form['employee_no']]);
        if ($dup->fetch()) $errors[] = 'That Employee No. is already in use by another teacher.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO teachers (employee_no, lastname, firstname, middlename, email, contact, status)
            VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $form['employee_no'] ?: null, $form['lastname'], $form['firstname'], $form['middlename'],
            $form['email'], $form['contact'], $form['status'],
        ]);
        $newId = (int) $pdo->lastInsertId();
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_create', "Added teacher #$newId ({$form['lastname']}, {$form['firstname']})");

        flash('success', 'Teacher record added successfully.');
        redirect('teachers.php');
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Add New Teacher</h2>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="POST" class="form-grid">
        <div class="form-row">
            <label>Employee No.<input type="text" name="employee_no" value="<?php echo clean($form['employee_no']); ?>" placeholder="optional"></label>
            <label>Status
                <select name="status">
                    <option value="Active" <?php echo $form['status']==='Active'?'selected':''; ?>>Active</option>
                    <option value="Inactive" <?php echo $form['status']==='Inactive'?'selected':''; ?>>Inactive</option>
                </select>
            </label>
        </div>
        <div class="form-row">
            <label>Last Name *<input type="text" name="lastname" value="<?php echo clean($form['lastname']); ?>" required></label>
            <label>First Name *<input type="text" name="firstname" value="<?php echo clean($form['firstname']); ?>" required></label>
            <label>Middle Name<input type="text" name="middlename" value="<?php echo clean($form['middlename']); ?>"></label>
        </div>
        <div class="form-row">
            <label>Email Address<input type="email" name="email" value="<?php echo clean($form['email']); ?>"></label>
            <label>Contact Number<input type="text" name="contact" value="<?php echo clean($form['contact']); ?>"></label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Save Teacher</button>
            <a href="teachers.php" class="btn-link">Cancel</a>
        </div>
    </form>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
