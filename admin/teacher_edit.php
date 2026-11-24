<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can edit teacher records.');
    redirect('teachers.php');
}

$pageTitle = 'Edit Teacher';
$errors = [];

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM teachers WHERE teacher_id = ?');
$stmt->execute([$id]);
$form = $stmt->fetch();

if (!$form) {
    flash('error', 'Teacher not found.');
    redirect('teachers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['employee_no', 'lastname', 'firstname', 'middlename', 'email', 'contact', 'status'];
    foreach ($fields as $f) {
        $form[$f] = trim($_POST[$f] ?? '');
    }

    if ($form['lastname'] === '' || $form['firstname'] === '') $errors[] = 'Last name and first name are required.';

    if ($form['employee_no'] !== '') {
        $dup = $pdo->prepare('SELECT teacher_id FROM teachers WHERE employee_no = ? AND teacher_id != ?');
        $dup->execute([$form['employee_no'], $id]);
        if ($dup->fetch()) $errors[] = 'That Employee No. is already in use by another teacher.';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE teachers SET employee_no=?, lastname=?, firstname=?, middlename=?, email=?, contact=?, status=?
            WHERE teacher_id=?');
        $upd->execute([
            $form['employee_no'] ?: null, $form['lastname'], $form['firstname'], $form['middlename'],
            $form['email'], $form['contact'], $form['status'], $id,
        ]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'teacher_update', "Updated teacher #$id ({$form['lastname']}, {$form['firstname']})");

        flash('success', 'Teacher record updated successfully.');
        redirect('teachers.php');
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Edit Teacher: <?php echo clean($form['lastname'] . ', ' . $form['firstname']); ?></h2>

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
            <button type="submit" class="btn-primary">Update Teacher</button>
            <a href="teachers.php" class="btn-link">Cancel</a>
        </div>
    </form>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
