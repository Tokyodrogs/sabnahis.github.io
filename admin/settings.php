<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Settings';
$errors = [];
$success = null;

$stmt = $pdo->prepare('SELECT * FROM admin WHERE admin_id = ?');
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        if ($fullname === '' || $username === '') {
            $errors[] = 'Full name and username are required.';
        } else {
            $check = $pdo->prepare('SELECT admin_id FROM admin WHERE username = ? AND admin_id != ?');
            $check->execute([$username, $admin['admin_id']]);
            if ($check->fetch()) {
                $errors[] = 'That username is already taken.';
            } else {
                $pdo->prepare('UPDATE admin SET fullname = ?, username = ? WHERE admin_id = ?')
                    ->execute([$fullname, $username, $admin['admin_id']]);
                $_SESSION['admin_fullname'] = $fullname;
                $_SESSION['admin_username'] = $username;
                $success = 'Account details updated.';
                $admin['fullname'] = $fullname;
                $admin['username'] = $username;
            }
        }
    } elseif ($formAction === 'photo') {
        $photoError = null;
        $savedPhoto = handle_photo_upload('photo', $admin['admin_id'], $photoError);
        if ($photoError) {
            $errors[] = $photoError;
        } elseif ($savedPhoto) {
            if (!empty($admin['photo'])) {
                delete_student_photo($admin['photo']);
            }
            $pdo->prepare('UPDATE admin SET photo = ? WHERE admin_id = ?')->execute([$savedPhoto, $admin['admin_id']]);
            $admin['photo'] = $savedPhoto;
            $success = 'Profile photo updated.';
        } else {
            $errors[] = 'Please choose a photo to upload.';
        }
    } elseif ($formAction === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $admin['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE admin SET password = ? WHERE admin_id = ?')->execute([$hash, $admin['admin_id']]);
            $success = 'Password changed successfully.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Account Settings</h2>
    <?php if ($success): ?><div class="alert alert-success"><?php echo clean($success); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <section class="panel" id="profile-panel">
        <h3>Profile Photo</h3>
        <div class="student-profile-head" style="align-items:center;">
            <div class="student-profile-photo"><?php echo student_photo_html($admin['photo'] ?? null, 'lg'); ?></div>
            <form method="POST" enctype="multipart/form-data" class="form-inline">
                <input type="hidden" name="form_action" value="photo">
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                <button type="submit" class="btn-secondary">Upload Photo</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <h3>Manage Administrator Account</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="profile">
            <label>Full Name<input type="text" name="fullname" value="<?php echo clean($admin['fullname']); ?>" required></label>
            <label>Username<input type="text" name="username" value="<?php echo clean($admin['username']); ?>" required></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save Changes</button></div>
        </form>
    </section>

    <section class="panel" id="password-panel">
        <h3>Change Password</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="password">
            <label>Current Password<input type="password" name="current_password" required></label>
            <label>New Password<input type="password" name="new_password" required minlength="8"></label>
            <label>Confirm New Password<input type="password" name="confirm_password" required minlength="8"></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Change Password</button></div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
