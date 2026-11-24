<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Profile';
$errors = [];

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year, sec.section_name
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

// Section is now a real assignment (students.section_id -> sections),
// set by the Registrar/Administrator - not derived from subjects.
$section = $student['section_name'] ?? null;

// Only these fields (plus the profile photo) are editable by the student.
// Everything else on this page is read-only academic/identity data
// maintained by the registrar via the admin panel.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $removePhoto = ($_POST['remove_photo'] ?? '0') === '1';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'profile_edit')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $newPhoto = $student['photo'];
            if (!empty($_FILES['photo']['name'])) {
                $photoError = null;
                $savedPhoto = handle_photo_upload('photo', $student['student_id'], $photoError);
                if ($savedPhoto === false) {
                    throw new RuntimeException($photoError);
                }
                if ($savedPhoto) {
                    delete_student_photo($student['photo']);
                    $newPhoto = $savedPhoto;
                }
            } elseif ($removePhoto) {
                delete_student_photo($student['photo']);
                $newPhoto = null;
            }

            $pdo->prepare('UPDATE students SET email = ?, contact = ?, address = ?, photo = ? WHERE student_id = ?')
                ->execute([$email ?: null, $contact ?: null, $address ?: null, $newPhoto, $student['student_id']]);

            $pdo->commit();
            flash('success', 'Profile updated successfully.');
            redirect('profile.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

$csrfToken = csrf_token('profile_edit');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>My Profile</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <section class="panel">
        <h3>Academic &amp; Identity Information <span class="muted" style="font-weight:normal;font-size:12px;">(read-only, maintained by the registrar)</span></h3>
        <table class="detail-table">
            <tr><th>Student Number</th><td><?php echo clean($_SESSION['student_username']); ?></td><th>LRN</th><td><?php echo clean($student['lrn'] ?: 'Not yet assigned'); ?></td></tr>
            <tr><th>Full Name</th><td colspan="3"><?php echo clean($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['extension']); ?></td></tr>
            <tr><th>Birthdate</th><td><?php echo clean($student['birthdate']); ?></td><th>Gender</th><td><?php echo clean($student['sex']); ?></td></tr>
            <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?></td><th>Strand</th><td><?php echo clean($student['strand_name'] ?? '-'); ?></td></tr>
            <tr><th>Section</th><td><?php echo clean($section ?: 'Not yet assigned'); ?></td><th>School Year</th><td><?php echo clean($student['school_year'] ?? '-'); ?></td></tr>
            <tr><th>Guardian Name</th><td><?php echo clean($student['guardian_name'] ?: '-'); ?></td><th>Guardian Contact</th><td><?php echo clean($student['guardian_contact'] ?: '-'); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Editable Information</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid" id="studentForm">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="student-form-layout">
                <div class="student-form-photo">
                    <div class="photo-upload-widget" id="photoWidget">
                        <div class="photo-drop-zone" id="photoDropZone">
                            <div class="photo-preview-circle" id="photoPreviewCircle">
                                <?php echo student_photo_html($student['photo'], 'lg'); ?>
                            </div>
                        </div>
                        <input type="file" id="photoInput" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="visually-hidden">
                        <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">
                        <div class="photo-actions">
                            <button type="button" class="btn-secondary btn-block" id="uploadPhotoBtn" <?php echo $student['photo'] ? 'hidden' : ''; ?>>Upload Photo</button>
                            <button type="button" class="btn-secondary btn-block" id="changePhotoBtn" <?php echo $student['photo'] ? '' : 'hidden'; ?>>Change Photo</button>
                            <button type="button" class="link-danger" id="removePhotoBtn" <?php echo $student['photo'] ? '' : 'hidden'; ?>>Remove Photo</button>
                        </div>
                        <p class="photo-hint muted">JPG, JPEG or PNG. Max 5MB.</p>
                        <p class="photo-error" id="photoError"></p>
                    </div>
                </div>
                <div class="student-form-fields">
                    <div class="form-row">
                        <label>Email Address<input type="email" name="email" value="<?php echo clean($student['email']); ?>"></label>
                        <label>Contact Number<input type="text" name="contact" value="<?php echo clean($student['contact']); ?>"></label>
                    </div>
                    <div class="form-row">
                        <label class="full">Address<input type="text" name="address" value="<?php echo clean($student['address']); ?>"></label>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </section>
</main>
<script src="<?php echo BASE_URL; ?>assets/js/validation.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
