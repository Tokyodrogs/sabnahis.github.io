<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Edit Student';
$errors = [];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
$stmt->execute([$id]);
$form = $stmt->fetch();

if (!$form) {
    flash('success', 'Student not found.');
    redirect('students.php');
}

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$strands     = $pdo->query('SELECT * FROM strands ORDER BY strand_name')->fetchAll();
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$sections    = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.status = 'Active' ORDER BY g.grade_level_id, sec.section_name")->fetchAll();

$currentPhoto = $form['photo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['lrn','lastname','firstname','middlename','extension','sex','birthdate','address','contact',
        'email','previous_school','grade_level_id','strand_id','section_id','guardian_name','guardian_contact','status','student_type','sy_id'];
    foreach ($fields as $f) {
        $form[$f] = trim($_POST[$f] ?? '');
    }
    $form['lrn'] = sanitize_lrn($form['lrn']);
    $removePhoto = ($_POST['remove_photo'] ?? '0') === '1';

    if ($form['lastname'] === '' || $form['firstname'] === '') $errors[] = 'Last name and first name are required.';
    if ($form['sex'] === '') $errors[] = 'Sex is required.';
    if ($form['birthdate'] === '') $errors[] = 'Birthdate is required.';
    if ($form['grade_level_id'] === '') $errors[] = 'Grade level is required.';
    if ($form['sy_id'] === '') $errors[] = 'School year is required.';

    // Learner Reference Number (LRN) - required, exactly 12 numeric digits,
    // must be unique across students (excluding this record).
    if ($form['lrn'] === '') {
        $errors[] = 'LRN is required.';
    } elseif (!validate_lrn($form['lrn'])) {
        $errors[] = 'LRN must contain exactly 12 numeric digits.';
    } else {
        $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE lrn = ? AND student_id != ?');
        $dupStmt->execute([$form['lrn'], $id]);
        if ($dupStmt->fetchColumn() > 0) {
            $errors[] = 'This LRN is already registered to another student.';
        }
    }

    // Strand only applies to Senior High School (Grade 11-12)
    $isSHS = false;
    foreach ($gradeLevels as $g) {
        if ($g['grade_level_id'] == $form['grade_level_id'] && $g['level_type'] === 'Senior High School') {
            $isSHS = true;
        }
    }
    if ($isSHS && $form['strand_id'] === '') {
        $errors[] = 'Strand is required for Senior High School (Grade 11-12) students.';
    }
    if (!$isSHS) {
        $form['strand_id'] = '';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $age = calculate_age($form['birthdate']);
            $newPhoto = $currentPhoto;

            // A newly selected file always takes precedence over "remove photo".
            if (!empty($_FILES['photo']['name'])) {
                $photoError = null;
                $savedPhoto = handle_photo_upload('photo', $id, $photoError);
                if ($savedPhoto === false) {
                    throw new RuntimeException($photoError);
                }
                if ($savedPhoto) {
                    delete_student_photo($currentPhoto);
                    $newPhoto = $savedPhoto;
                }
            } elseif ($removePhoto) {
                delete_student_photo($currentPhoto);
                $newPhoto = null;
            }

            $upd = $pdo->prepare('UPDATE students SET
                lrn=?, lastname=?, firstname=?, middlename=?, extension=?, sex=?, birthdate=?, age=?, address=?, contact=?,
                email=?, previous_school=?, grade_level_id=?, strand_id=?, section_id=?, guardian_name=?, guardian_contact=?, status=?, student_type=?, sy_id=?, photo=?
                WHERE student_id=?');
            $upd->execute([
                $form['lrn'], $form['lastname'], $form['firstname'], $form['middlename'], $form['extension'],
                $form['sex'], $form['birthdate'], $age, $form['address'], $form['contact'], $form['email'],
                $form['previous_school'], $form['grade_level_id'], $form['strand_id'] ?: null, $form['section_id'] ?: null,
                $form['guardian_name'], $form['guardian_contact'], $form['status'], $form['student_type'], $form['sy_id'], $newPhoto, $id,
            ]);

            $pdo->commit();
            flash('success', 'Student record updated successfully.');
            redirect('students.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to update student: ' . $e->getMessage();
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-main">
    <h2>Edit Student: <?php echo clean($form['lastname'] . ', ' . $form['firstname']); ?></h2>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-grid" id="studentForm">
        <div class="student-form-layout">
            <div class="student-form-photo">
                <div class="photo-upload-widget" id="photoWidget">
                    <div class="photo-drop-zone" id="photoDropZone">
                        <div class="photo-preview-circle" id="photoPreviewCircle">
                            <?php echo student_photo_html($currentPhoto, 'lg'); ?>
                        </div>
                    </div>
                    <input type="file" id="photoInput" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="visually-hidden">
                    <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">
                    <div class="photo-actions">
                        <button type="button" class="btn-secondary btn-block" id="uploadPhotoBtn" <?php echo $currentPhoto ? 'hidden' : ''; ?>>Upload Photo</button>
                        <button type="button" class="btn-secondary btn-block" id="changePhotoBtn" <?php echo $currentPhoto ? '' : 'hidden'; ?>>Change Photo</button>
                        <button type="button" class="link-danger" id="removePhotoBtn" <?php echo $currentPhoto ? '' : 'hidden'; ?>>Remove Photo</button>
                    </div>
                    <p class="photo-hint muted">JPG, JPEG or PNG. Max 5MB.</p>
                    <p class="photo-error" id="photoError"></p>
                </div>
            </div>

            <div class="student-form-fields">
                <div class="form-row">
                    <label>Learner Reference Number (LRN) *
                        <input type="text" name="lrn" id="lrn" inputmode="numeric" pattern="\d{12}" maxlength="12"
                               placeholder="Enter 12-digit LRN" value="<?php echo clean($form['lrn']); ?>" required>
                        <small class="field-error" id="lrnError"></small>
                    </label>
                    <label>Last Name *<input type="text" name="lastname" value="<?php echo clean($form['lastname']); ?>" required></label>
                    <label>First Name *<input type="text" name="firstname" value="<?php echo clean($form['firstname']); ?>" required></label>
                    <label>Middle Name<input type="text" name="middlename" value="<?php echo clean($form['middlename']); ?>"></label>
                </div>
                <div class="form-row">
                    <label>Extension Name<input type="text" name="extension" value="<?php echo clean($form['extension']); ?>"></label>
                    <label>Sex *
                        <select name="sex" required>
                            <option value="Male" <?php echo $form['sex']==='Male'?'selected':''; ?>>Male</option>
                            <option value="Female" <?php echo $form['sex']==='Female'?'selected':''; ?>>Female</option>
                        </select>
                    </label>
                    <label>Birthdate *<input type="date" name="birthdate" value="<?php echo clean($form['birthdate']); ?>" required></label>
                    <label>Contact Number<input type="text" name="contact" value="<?php echo clean($form['contact']); ?>"></label>
                </div>
                <div class="form-row">
                    <label>Email Address<input type="email" name="email" value="<?php echo clean($form['email']); ?>"></label>
                </div>
                <div class="form-row">
                    <label class="full">Address<input type="text" name="address" value="<?php echo clean($form['address']); ?>"></label>
                </div>
                <div class="form-row">
                    <label class="full">Previous School<input type="text" name="previous_school" value="<?php echo clean($form['previous_school']); ?>"></label>
                </div>
                <div class="form-row">
                    <label>Grade Level *
                        <select name="grade_level_id" id="grade_level_id" required>
                            <?php foreach ($gradeLevels as $g): ?>
                                <option value="<?php echo $g['grade_level_id']; ?>" data-type="<?php echo clean($g['level_type']); ?>" <?php echo $form['grade_level_id']==$g['grade_level_id']?'selected':''; ?>><?php echo clean($g['grade_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label id="strandField">Strand (Senior High only)
                        <select name="strand_id" id="strand_id">
                            <option value="">N/A</option>
                            <?php foreach ($strands as $s): ?>
                                <option value="<?php echo $s['strand_id']; ?>" <?php echo $form['strand_id']==$s['strand_id']?'selected':''; ?>><?php echo clean($s['strand_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>School Year *
                        <select name="sy_id" required>
                            <?php foreach ($schoolYears as $sy): ?>
                                <option value="<?php echo $sy['sy_id']; ?>" <?php echo $form['sy_id']==$sy['sy_id']?'selected':''; ?>><?php echo clean($sy['school_year']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Section
                        <select name="section_id">
                            <option value="">Not yet assigned</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?php echo $s['section_id']; ?>" <?php echo $form['section_id']==$s['section_id']?'selected':''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="muted">Assigning a section automatically shows that section's schedule in the student's portal - no extra step needed.</small>
                    </label>
                    <label>Status
                        <select name="status">
                            <?php foreach (['Pending','Verified','Approved','Rejected','Enrolled'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $form['status']===$st?'selected':''; ?>><?php echo $st === 'Enrolled' ? 'Officially Enrolled' : $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="form-row">
                    <label>Registration Type *
                        <select name="student_type" required>
                            <?php // Was missing Balik-Aral entirely (pre-2026-08i gap) - now sourced from
                                  // APPLICANT_TYPES so it can't drift out of sync again; also picks up ALS. ?>
                            <?php foreach (APPLICANT_TYPES as $t => $meta): ?>
                                <option value="<?php echo clean($t); ?>" <?php echo $form['student_type'] === $t ? 'selected' : ''; ?>><?php echo clean($meta['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Parent/Guardian Name<input type="text" name="guardian_name" value="<?php echo clean($form['guardian_name']); ?>"></label>
                    <label>Parent/Guardian Contact<input type="text" name="guardian_contact" value="<?php echo clean($form['guardian_contact']); ?>"></label>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Update Student</button>
            <a href="students.php" class="btn-link">Cancel</a>
        </div>
    </form>
</main>
<script src="<?php echo BASE_URL; ?>assets/js/validation.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
