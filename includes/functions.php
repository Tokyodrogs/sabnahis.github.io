<?php
/**
 * Shared helper functions
 */

function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Cache-busting helper for CSS/JS <link>/<script> tags (2026-08n). This
 * project's stylesheets/scripts have no build step and no version number
 * in their filenames, so browsers can keep serving a stale cached copy
 * after a file changes on the server - e.g. a print stylesheet edited
 * mid-session still rendering with the old layout in an already-open
 * tab/print preview until the user manually hard-refreshes. Appending
 * "?v=<the file's last-modified time>" changes the URL every time the
 * file's content changes, so the browser is forced to re-fetch it - with
 * no manual cache-busting step needed on every future edit.
 *
 * $relativePath is relative to the project root, e.g. 'assets/css/print.css'.
 */
function versioned_asset($relativePath) {
    $fsPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    $version = is_file($fsPath) ? filemtime($fsPath) : time();
    return BASE_URL . ltrim($relativePath, '/') . '?v=' . $version;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function calculate_age($birthdate) {
    if (empty($birthdate)) return '';
    $bd = new DateTime($birthdate);
    $today = new DateTime('today');
    return $bd->diff($today)->y;
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

/**
 * Generate a unique enrollment number based on school year and student id.
 * Format: SY-STUDENTID e.g. 2026-2027-000123
 */
function generate_enrollment_no($school_year, $student_id) {
    $sy = preg_replace('/[^0-9\-]/', '', $school_year);
    return $sy . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT);
}

/**
 * Validate and move an uploaded document. Returns the stored relative
 * file path on success, or false (with $error populated) on failure.
 */
function handle_document_upload($fileField, $studentId, $docType, &$error = null) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'No file uploaded.';
        return false;
    }
    $file = $_FILES[$fileField];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error (code ' . $file['error'] . ').';
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $error = 'File is too large. Maximum size is 5MB.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        $error = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
        return false;
    }

    // Basic MIME check as a second layer of validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $allowedMimes, true)) {
        $error = 'Invalid file content.';
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $safeName = $docType . '_' . $studentId . '_' . time() . '.' . $ext;
    $destination = UPLOAD_DIR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Failed to save uploaded file.';
        return false;
    }

    return $safeName;
}

/**
 * Records an already-saved upload (see handle_document_upload() above)
 * against a student's documents row - INSERT if this is the first time
 * that doc_type has been uploaded for them, or UPDATE-in-place (replacing
 * the old file, resetting status back to Pending) if they already have
 * one on file. This matters for re-enrollment (Old Student / Balik-Aral
 * found via lookup): a returning student's `documents` rows already
 * exist from their original enrollment, so a plain INSERT would violate
 * the uq_documents_student_doctype unique constraint. Mirrors the same
 * upsert pattern already used by student/documents.php's self-service
 * re-upload. Does NOT overwrite an already-Approved document - callers
 * should check that first if they need to block/skip re-upload of
 * approved docs (see the reenroll path in enroll.php).
 */
function save_document_upload(PDO $pdo, $studentId, $docType, $savedName) {
    $existingStmt = $pdo->prepare('SELECT doc_id, file_name FROM documents WHERE student_id = ? AND doc_type = ?');
    $existingStmt->execute([$studentId, $docType]);
    $existing = $existingStmt->fetch();

    if ($existing) {
        $oldPath = UPLOAD_DIR . $existing['file_name'];
        if (is_file($oldPath)) @unlink($oldPath);
        $pdo->prepare("UPDATE documents SET file_name = ?, file_path = ?, status = 'Pending', remarks = NULL,
                reviewed_by = NULL, reviewed_at = NULL, uploaded_at = NOW() WHERE doc_id = ?")
            ->execute([$savedName, 'uploads/' . $savedName, $existing['doc_id']]);
    } else {
        $pdo->prepare('INSERT INTO documents (student_id, doc_type, file_name, file_path) VALUES (?,?,?,?)')
            ->execute([$studentId, $docType, $savedName, 'uploads/' . $savedName]);
    }
}

/**
 * Validate and move an uploaded image (JPG/PNG only). Used for announcement
 * photos. Returns the stored filename on success, or false (with $error
 * populated) on failure. Returns null if no file was submitted (image is
 * optional for announcements).
 */
function handle_image_upload($fileField, $prefix, &$error = null) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fileField];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error (code ' . $file['error'] . ').';
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $error = 'Image is too large. Maximum size is 5MB.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $error = 'Invalid image type. Allowed: JPG, PNG.';
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        $error = 'Invalid image content.';
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $safeName = $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = UPLOAD_DIR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Failed to save uploaded image.';
        return false;
    }

    return $safeName;
}

/**
 * Validate a Learner Reference Number (LRN).
 * Must be exactly LRN_LENGTH (12) numeric digits only - no letters,
 * spaces, dashes, or other characters. Returns true/false.
 * An empty string is considered valid here (use with a separate
 * "required" check where the LRN is mandatory, e.g. admin-side forms);
 * this keeps the function reusable for contexts where the LRN is optional
 * (e.g. the public online enrollment form for new entrants without an LRN yet).
 */
function validate_lrn($lrn) {
    if ($lrn === '' || $lrn === null) return true;
    return (bool) preg_match('/^\d{' . LRN_LENGTH . '}$/', $lrn);
}

/**
 * Strip anything that isn't a digit from a submitted LRN value, so stray
 * spaces or formatting characters don't cause a false validation failure.
 * Leading zeros are preserved since the value is kept as a string.
 */
function sanitize_lrn($lrn) {
    return preg_replace('/\D/', '', (string) $lrn);
}

/**
 * Validate and store an uploaded student profile photo (JPG/JPEG/PNG only,
 * max PHOTO_MAX_SIZE). The image is automatically resized down to
 * PHOTO_MAX_DIMENSION on its longest side and re-encoded to reduce file
 * size, when the GD extension is available. If GD is not installed, the
 * original file is stored as-is (validation still applies).
 *
 * Returns the stored filename (relative to UPLOAD_DIR . PHOTO_UPLOAD_SUBDIR)
 * on success, false (with $error populated) on failure, or null if no file
 * was submitted (photo is optional).
 */
function handle_photo_upload($fileField, $studentId, &$error = null) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fileField];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Photo upload error (code ' . $file['error'] . ').';
        return false;
    }

    if ($file['size'] > PHOTO_MAX_SIZE) {
        $error = 'Photo is too large. Maximum size is 5MB.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!in_array($ext, PHOTO_ALLOWED_EXTENSIONS, true)) {
        $error = 'Invalid photo type. Allowed formats: JPG, JPEG, PNG.';
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, PHOTO_ALLOWED_MIMES, true)) {
        $error = 'Invalid photo content. Please upload a genuine JPG or PNG image.';
        return false;
    }

    $destDir = UPLOAD_DIR . PHOTO_UPLOAD_SUBDIR;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $safeName = 'photo_' . $studentId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = $destDir . $safeName;

    if (extension_loaded('gd')) {
        if (!resize_and_save_image($file['tmp_name'], $mime, $destination, $ext)) {
            $error = 'Failed to process the uploaded photo.';
            return false;
        }
    } else {
        // GD not available - store the original upload unmodified.
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $error = 'Failed to save uploaded photo.';
            return false;
        }
    }

    return $safeName;
}

/**
 * Resize an image (if larger than PHOTO_MAX_DIMENSION on its longest side)
 * and re-save it to $destination, optimizing file size. Returns true/false.
 */
function resize_and_save_image($srcPath, $mime, $destination, $ext) {
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    [$width, $height] = $info;

    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($srcPath);
            break;
        default:
            return false;
    }
    if (!$src) return false;

    $longest = max($width, $height);
    if ($longest > PHOTO_MAX_DIMENSION) {
        $scale = PHOTO_MAX_DIMENSION / $longest;
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $canvas = imagecreatetruecolor($newWidth, $newHeight);

    if ($ext === 'png') {
        // Preserve transparency for PNGs
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $ok = ($ext === 'png')
        ? imagepng($canvas, $destination, 6)
        : imagejpeg($canvas, $destination, PHOTO_JPEG_QUALITY);

    imagedestroy($src);
    imagedestroy($canvas);

    return $ok;
}

/**
 * Delete a previously stored student photo file from disk (if it exists).
 * $photoFilename is the value stored in students.photo (just the filename,
 * relative to UPLOAD_DIR . PHOTO_UPLOAD_SUBDIR).
 */
function delete_student_photo($photoFilename) {
    if (empty($photoFilename)) return;
    $path = UPLOAD_DIR . PHOTO_UPLOAD_SUBDIR . $photoFilename;
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Build the public URL for a stored student photo, or null if none is set.
 */
function student_photo_url($photoFilename) {
    if (empty($photoFilename)) return null;
    return BASE_URL . 'uploads/' . PHOTO_UPLOAD_SUBDIR . rawurlencode($photoFilename);
}

/**
 * Render the reusable circular avatar markup used across the Student List,
 * Student Profile, and printable Reports. Falls back to a default avatar
 * icon + "No Photo" label whenever no image has been uploaded, so the UI
 * never shows a broken image.
 *
 * $size: 'sm' (list/report thumbnail), 'md' (default), or 'lg' (profile/registration).
 */
function student_photo_html($photoFilename, $size = 'md') {
    $sizeClass = 'avatar-' . preg_replace('/[^a-z]/', '', $size);
    $url = student_photo_url($photoFilename);
    if ($url) {
        return '<span class="student-avatar ' . $sizeClass . '"><img src="' . clean($url) . '" alt="Student Photo"></span>';
    }
    return '<span class="student-avatar ' . $sizeClass . ' student-avatar-placeholder">'
         . '<svg class="avatar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9V22h19.6v-2.6c0-3.3-6.5-4.9-9.8-4.9z"/></svg>'
         . '<span class="avatar-label">No Photo</span>'
         . '</span>';
}

/**
 * Check whether a teacher record is still referenced anywhere in the
 * Academic Management module (teacher_assignments, class_schedules, or as
 * a section's adviser). Used by teacher_delete.php to block permanent
 * deletion, and by teacher_view.php to show what's blocking it.
 *
 * Returns ['blocked' => bool, 'assignments' => int, 'schedules' => int, 'advisory' => int].
 */
function get_teacher_dependencies(PDO $pdo, $teacherId) {
    $countAssignments = $pdo->prepare('SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id = ?');
    $countAssignments->execute([$teacherId]);
    $assignments = (int) $countAssignments->fetchColumn();

    $countSchedules = $pdo->prepare('SELECT COUNT(*) FROM class_schedules WHERE teacher_id = ?');
    $countSchedules->execute([$teacherId]);
    $schedules = (int) $countSchedules->fetchColumn();

    $countAdvisory = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE adviser_teacher_id = ?');
    $countAdvisory->execute([$teacherId]);
    $advisory = (int) $countAdvisory->fetchColumn();

    return [
        'blocked'     => ($assignments + $schedules + $advisory) > 0,
        'assignments' => $assignments,
        'schedules'   => $schedules,
        'advisory'    => $advisory,
    ];
}

function status_badge($status) {
    $map = [
        'Pending'  => 'badge badge-pending',
        'Verified' => 'badge badge-verified',
        'Approved' => 'badge badge-approved',
        'Rejected' => 'badge badge-rejected',
        'Enrolled' => 'badge badge-enrolled',
    ];
    // 'Enrolled' is stored as-is everywhere (queries, filters, exports) but
    // DepEd-style terminology calls this stage "Officially Enrolled" - only
    // the display label changes here, never the underlying value.
    $label = $status === 'Enrolled' ? 'Officially Enrolled' : $status;
    $class = $map[$status] ?? 'badge';
    return '<span class="' . $class . '">' . clean($label) . '</span>';
}

/**
 * Badge for a documents.status value (Pending/Approved/Rejected/Re-upload
 * Requested). Reuses the same badge-* CSS classes as status_badge().
 */
function document_status_badge($status) {
    $map = [
        'Pending'              => 'badge badge-pending',
        'Approved'             => 'badge badge-approved',
        'Rejected'             => 'badge badge-rejected',
        'Re-upload Requested'  => 'badge badge-reupload',
    ];
    $class = $map[$status] ?? 'badge';
    return '<span class="' . $class . '">' . clean($status) . '</span>';
}

/**
 * Document Verification module (2026-08) - compute a single student's
 * overall document verification status from their required-document
 * counts (see admin/document_verification.php, which aggregates these per
 * student via a GROUP BY query instead of showing one row per document).
 *
 * Precedence (most actionable/urgent first):
 *   Rejected   - at least one required document was rejected
 *   Incomplete - one or more required documents haven't been uploaded yet
 *   Pending    - all required documents are uploaded, but at least one is
 *                still Pending or Re-upload Requested (not yet approved)
 *   Verified   - every required document has been Approved
 */
function compute_document_verification_status($requiredTotal, $requiredSubmitted, $requiredApproved, $requiredRejected, $requiredPending) {
    if ($requiredRejected > 0) return 'Rejected';
    if ($requiredSubmitted < $requiredTotal) return 'Incomplete';
    if ($requiredPending > 0) return 'Pending';
    if ($requiredApproved >= $requiredTotal) return 'Verified';
    return 'Pending';
}

/**
 * Badge for the computed overall status from compute_document_verification_status().
 */
function document_verification_status_badge($status) {
    $map = [
        'Pending'    => 'badge badge-pending',
        'Verified'   => 'badge badge-verified',
        'Incomplete' => 'badge badge-incomplete',
        'Rejected'   => 'badge badge-rejected',
    ];
    $class = $map[$status] ?? 'badge';
    return '<span class="' . $class . '">' . clean($status) . '</span>';
}

// =========================================================================
// STUDENT PORTAL HELPERS
// The Student Portal (student/*.php) is a separate authentication domain
// from the admin panel: its own login, its own session keys (all prefixed
// student_*), its own middleware (student/includes/auth_check.php). These
// helpers are shared by both the admin pages that trigger portal side
// effects (creating accounts, reviewing documents, posting announcements)
// and the student/*.php pages themselves.
// =========================================================================

/**
 * Generate (and reserve-check) a CSRF token for the current session under
 * a named "form" bucket, so unrelated forms on the same page don't clobber
 * each other's tokens. Mirrors the inline pattern already used by the
 * public enroll.php form, generalized for reuse across the Student Portal.
 */
function csrf_token($formName = 'default') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'][$formName])) {
        $_SESSION['csrf'][$formName] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'][$formName];
}

/**
 * Verify a submitted CSRF token against the session, for the given form
 * bucket. Returns true/false; never throws, so callers can add a clean
 * error message to their own $errors array.
 */
function csrf_verify($token, $formName = 'default') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'][$formName]) || empty($token)) return false;
    return hash_equals($_SESSION['csrf'][$formName], $token);
}

/**
 * Build a unique Student Portal username in the same "SY-STUDENTID" shape
 * as generate_enrollment_no() (e.g. 2026-2027-000123), but stored
 * independently in student_accounts.username. This intentionally does NOT
 * touch students.enrollment_no, which keeps its existing meaning (assigned
 * only once a student is marked Officially Enrolled via enrollment_view.php).
 */
function generate_student_username(PDO $pdo, $studentId, $schoolYearLabel) {
    $base = generate_enrollment_no($schoolYearLabel ?: date('Y'), $studentId);
    $username = $base;
    // Defensive uniqueness loop - collisions are not expected since the
    // format already embeds the unique student_id, but this keeps the
    // function safe if the naming scheme ever changes.
    $suffix = 1;
    $check = $pdo->prepare('SELECT COUNT(*) FROM student_accounts WHERE username = ?');
    while (true) {
        $check->execute([$username]);
        if ((int) $check->fetchColumn() === 0) break;
        $suffix++;
        $username = $base . '-' . $suffix;
    }
    return $username;
}

/**
 * Automatically create a Student Portal account for a student record, if
 * one doesn't already exist (idempotent - safe to call from multiple admin
 * entry points, and from the public self-service signup on enroll.php).
 * Username defaults to the DepEd-style Student Number (SY-STUDENTID) unless
 * $customUsername is given (student-chosen username from the "Create Your
 * Student Portal Account" section of enroll.php - see
 * validate_student_username() for the format rules applied to it).
 *
 * By default (no $password given) a temporary password
 * (TEMP_STUDENT_PASSWORD) is set and first_login is flagged so the student
 * is forced through student/change_password.php before they can use the
 * portal. If the caller already collected and validated a password the
 * student chose themselves (e.g. the "Create Your Student Portal Account"
 * form shown right after they submit their online enrollment), pass it in
 * $password: it's hashed and stored directly, first_login is left off, and
 * password_changed_at is stamped immediately, since there's no separate
 * temp password to force a change away from.
 *
 * Returns ['account_id' => int, 'username' => string, 'created' => bool] on
 * success, null if the student record wasn't found, or false if
 * $customUsername collided with one taken by someone else in the moment
 * between the live availability check and this insert (rare race - the
 * caller should show a "please choose another username" message).
 */
function create_student_account(PDO $pdo, $studentId, $password = null, $customUsername = null) {
    $existing = $pdo->prepare('SELECT account_id, username FROM student_accounts WHERE student_id = ?');
    $existing->execute([$studentId]);
    $row = $existing->fetch();
    if ($row) {
        return ['account_id' => $row['account_id'], 'username' => $row['username'], 'created' => false];
    }

    $stStmt = $pdo->prepare('SELECT s.student_id, s.sy_id, sy.school_year
        FROM students s LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
        WHERE s.student_id = ?');
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch();
    if (!$student) return null;

    if ($customUsername) {
        $username = $customUsername;
    } else {
        $schoolYearLabel = $student['school_year'];
        if (!$schoolYearLabel) {
            $schoolYearLabel = $pdo->query('SELECT school_year FROM school_year WHERE is_current = 1 LIMIT 1')->fetchColumn() ?: date('Y');
        }
        $username = generate_student_username($pdo, $studentId, $schoolYearLabel);
    }

    $selfService = $password !== null && $password !== '';
    $hash = password_hash($selfService ? $password : TEMP_STUDENT_PASSWORD, PASSWORD_DEFAULT);

    try {
        if ($selfService) {
            $ins = $pdo->prepare('INSERT INTO student_accounts
                (student_id, username, password, status, first_login, failed_attempts, password_changed_at)
                VALUES (?, ?, ?, \'Active\', 0, 0, NOW())');
            $ins->execute([$studentId, $username, $hash]);
        } else {
            $ins = $pdo->prepare('INSERT INTO student_accounts
                (student_id, username, password, status, first_login, failed_attempts)
                VALUES (?, ?, ?, \'Active\', 1, 0)');
            $ins->execute([$studentId, $username, $hash]);
        }
    } catch (PDOException $e) {
        // Duplicate username (race between the live availability check and
        // this insert - someone else grabbed it in the meantime). Only
        // possible with a custom username, since generate_student_username()
        // already loops until it finds a free one.
        if ($customUsername && (int) $e->getCode() === 23000) {
            return false;
        }
        throw $e;
    }

    $accountId = (int) $pdo->lastInsertId();

    if ($selfService) {
        audit_log($pdo, 'student', $accountId, 'self_registration', 'Portal account self-created after online enrollment submission');
        notify_student($pdo, $studentId, 'system',
            'Your Student Portal account is ready. Username: ' . $username . '. You can log in with the password you just created.');
    } else {
        notify_student($pdo, $studentId, 'system',
            'Your Student Portal account has been created. Username: ' . $username . '. Please log in and change your temporary password.');
    }

    return ['account_id' => $accountId, 'username' => $username, 'created' => true];
}

/**
 * Validate a student-chosen Student Portal username (format only - does
 * NOT check uniqueness; callers should also query student_accounts, see
 * check_username.php and enroll.php). Rules: 5-30 characters, letters,
 * numbers, underscore (_) and period (.) only, no spaces.
 * Returns an empty array when valid, or a list of human-readable errors.
 */
function validate_student_username($username) {
    $errors = [];
    $username = trim($username ?? '');
    if ($username === '') {
        $errors[] = 'Username is required.';
        return $errors;
    }
    if (strlen($username) < 5 || strlen($username) > 30) {
        $errors[] = 'Username must be between 5 and 30 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9._]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, underscores (_), and periods (.), with no spaces.';
    }
    return $errors;
}

/**
 * Validate a candidate new password against the Student Portal password
 * policy: minimum PASSWORD_MIN_LENGTH characters, at least one uppercase
 * letter, one lowercase letter, one number, and one special character.
 * Returns an empty array when the password passes, or a list of the
 * specific rules that failed (for display to the student).
 */
function validate_password_policy($password) {
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'at least ' . PASSWORD_MIN_LENGTH . ' characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'an uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'a lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'a number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'a special character';
    }
    return $errors;
}

/**
 * Record a security-relevant action in audit_log. $actorType is 'admin' or
 * 'student'; $actorId is admin.admin_id or student_accounts.account_id
 * respectively. Never throws - a logging failure should not break the
 * action it's logging.
 */
function audit_log(PDO $pdo, $actorType, $actorId, $action, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare('INSERT INTO audit_log (actor_type, actor_id, action, details, ip_address) VALUES (?,?,?,?,?)')
            ->execute([$actorType, $actorId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Intentionally swallow - auditing must never block the primary action.
    }
}

/**
 * Push a notification to a single student's Student Portal Notifications
 * (and Dashboard "recent notifications") list.
 */
function notify_student(PDO $pdo, $studentId, $category, $message, $refType = null, $refId = null) {
    $pdo->prepare('INSERT INTO notifications (student_id, category, ref_type, ref_id, message) VALUES (?,?,?,?,?)')
        ->execute([$studentId, $category, $refType, $refId, $message]);
}

/**
 * Broadcast a notification to every student who currently has a Student
 * Portal account (used for e.g. new Announcements). Students without a
 * portal account yet are skipped - nothing for them to see it in.
 */
function notify_all_students(PDO $pdo, $category, $message, $refType = null, $refId = null) {
    $ids = $pdo->query('SELECT student_id FROM student_accounts')->fetchAll(PDO::FETCH_COLUMN);
    $ins = $pdo->prepare('INSERT INTO notifications (student_id, category, ref_type, ref_id, message) VALUES (?,?,?,?,?)');
    foreach ($ids as $studentId) {
        $ins->execute([$studentId, $category, $refType, $refId, $message]);
    }
}

/**
 * Compute totals for a fee_assessments row plus its related payments.
 * Returns ['total_assessment' => float, 'total_paid' => float,
 * 'balance' => float]. Only payments with status = 'Verified' count
 * against the balance.
 *
 * Not currently called anywhere (2026-08g: the Student Portal's
 * "Assessment of Fees" / "Payment History" pages and the Certificate of
 * Registration's fee summary were removed per SABNAHIS's DepEd No
 * Collection Policy - public secondary education is free, so a
 * tuition-style balance has no place in the student-facing UI). Left in
 * place, unused, because fee_assessments/payments still exist in the
 * schema for a possible future internal Cashier module (optional
 * contributions / specific school service fees) - see
 * database/schema.sql. Safe to delete if that module never materializes.
 */
function compute_fee_summary(array $assessment, array $payments) {
    $total = (float) $assessment['tuition_fee']
        + (float) $assessment['misc_fee']
        + (float) $assessment['lab_fee']
        + (float) $assessment['other_charges']
        - (float) $assessment['scholarship_discount'];

    $paid = 0.0;
    foreach ($payments as $p) {
        if ($p['status'] === 'Verified') {
            $paid += (float) $p['amount'];
        }
    }

    return [
        'total_assessment' => $total,
        'total_paid'       => $paid,
        'balance'          => max($total - $paid, 0),
    ];
}

/**
 * Build the Enrollment Status progress-tracker state for a student, from
 * real data only (no fabricated statuses): the student record itself,
 * whether all of their uploaded documents are Approved, and whether they
 * are Officially Enrolled. See config/constants.php::ENROLLMENT_STAGES
 * for the 3 display stages.
 *
 * (2026-08g: this used to be 6 stages including "Assessment Completed" /
 * "Payment Pending" / "Payment Verified", driven by fee_assessments/
 * payments. Removed along with the rest of the fee/payment UI per
 * SABNAHIS's DepEd No Collection Policy - those stages also had the
 * side effect of showing every applicant stuck on "Payment Pending"
 * forever, since nothing in the system populates those tables.)
 *
 * Returns:
 *   'stages'    => ENROLLMENT_STAGES
 *   'completed' => bool[] (one per stage, true = reached/checked)
 *   'current'   => index of the furthest-reached stage
 *   'rejected'  => true if the application was rejected (tracker halts)
 */
function compute_enrollment_progress(PDO $pdo, array $student) {
    $stages = ENROLLMENT_STAGES; // Application Submitted / Documents Verified / Officially Enrolled
    $completed = array_fill(0, count($stages), false);
    $completed[0] = true; // the record exists, so the application was submitted

    $docStmt = $pdo->prepare('SELECT status FROM documents WHERE student_id = ?');
    $docStmt->execute([$student['student_id']]);
    $docStatuses = $docStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($docStatuses)) {
        $allApproved = true;
        foreach ($docStatuses as $s) {
            if ($s !== 'Approved') { $allApproved = false; break; }
        }
        $completed[1] = $allApproved;
    }

    $completed[2] = ($student['status'] === 'Enrolled');
    if ($completed[2]) {
        // Once officially enrolled, treat every earlier stage as satisfied
        // for display purposes even if e.g. a document was never marked
        // Approved individually (registrar override).
        $completed = array_fill(0, count($stages), true);
    }

    $current = 0;
    foreach ($completed as $i => $done) {
        if ($done) $current = $i;
    }

    return [
        'stages'    => $stages,
        'completed' => $completed,
        'current'   => $current,
        'rejected'  => ($student['status'] === 'Rejected'),
    ];
}

/**
 * About SABNAHIS module (2026-08)
 * ---------------------------------------------------------------------
 * Validate and move an uploaded PDF (Downloadable Files section: Student
 * Handbook, Enrollment Guidelines, School Calendar, School Profile).
 * Mirrors handle_image_upload()'s structure but restricted to PDF only.
 * Returns the stored filename on success, null if no file was chosen,
 * or false (with $error populated) on failure.
 */
function handle_pdf_upload($fileField, $prefix, &$error = null) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fileField];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error (code ' . $file['error'] . ').';
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $error = 'File is too large. Maximum size is 5MB.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        $error = 'Invalid file type. Only PDF files are allowed.';
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'application/pdf') {
        $error = 'Invalid file content. Please upload a genuine PDF file.';
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $safeName = preg_replace('/[^a-z0-9]+/i', '-', $prefix) . '_' . time() . '_' . mt_rand(1000, 9999) . '.pdf';
    $destination = UPLOAD_DIR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Failed to save uploaded file.';
        return false;
    }

    return $safeName;
}

/**
 * Validate, auto-resize (longest side capped at $maxDimension, preserving
 * aspect ratio - same approach as resize_and_save_image()), and store an
 * uploaded image for the About SABNAHIS module (banner, logo, history,
 * principal photo/signature, facility, gallery). Returns the stored
 * filename on success, null if no file was chosen, or false (with $error
 * populated) on failure.
 */
function handle_about_image_upload($fileField, $prefix, $maxDimension, &$error = null, $maxSizeBytes = null) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fileField];

    // Default cap is 10MB (most About SABNAHIS images); callers that need a
    // stricter limit - e.g. the Principal's Message photo (5MB) and
    // signature (2MB) - pass $maxSizeBytes explicitly.
    $sizeLimit = $maxSizeBytes ?? (MAX_FILE_SIZE * 2);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error (code ' . $file['error'] . ').';
        return false;
    }

    if ($file['size'] > $sizeLimit) {
        $error = 'Image is too large. Maximum size is ' . format_file_size($sizeLimit) . '.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!in_array($ext, ['jpg', 'png', 'webp'], true)) {
        $error = 'Invalid image type. Allowed: JPG, JPEG, PNG, WEBP.';
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $error = 'Invalid image content.';
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $safeName = preg_replace('/[^a-z0-9]+/i', '-', $prefix) . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destination = UPLOAD_DIR . $safeName;

    $resized = false;
    if (extension_loaded('gd') && in_array($mime, ['image/jpeg', 'image/png'], true)) {
        $resized = resize_about_image($file['tmp_name'], $mime, $destination, $ext, $maxDimension);
    }
    if (!$resized) {
        // WEBP or no GD extension: store as-is rather than fail the upload.
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $error = 'Failed to save uploaded image.';
            return false;
        }
    }

    return $safeName;
}

/**
 * Same resize approach as resize_and_save_image(), generalized to accept
 * any max dimension (banner/logo/gallery images each need a different cap).
 */
function resize_about_image($srcPath, $mime, $destination, $ext, $maxDimension) {
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    [$width, $height] = $info;

    $src = $mime === 'image/png' ? @imagecreatefrompng($srcPath) : @imagecreatefromjpeg($srcPath);
    if (!$src) return false;

    $longest = max($width, $height);
    if ($longest > $maxDimension) {
        $scale = $maxDimension / $longest;
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    if ($ext === 'png') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
    }
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $ok = $ext === 'png' ? imagepng($canvas, $destination, 6) : imagejpeg($canvas, $destination, PHOTO_JPEG_QUALITY);
    imagedestroy($canvas);
    imagedestroy($src);
    return $ok;
}

/**
 * Resolves whatever the Administrator pastes into the Google Map field
 * (admin/about_content.php) into a safe, directly embeddable Google Maps
 * URL, which is what gets saved to about_school.map_embed - see that
 * file's 'map' form handler. Doing this once at save time (rather than
 * on every public about.php page view via about_map_src() below) avoids
 * an external HTTP round-trip per visitor, and means a broken/unresolvable
 * link is caught immediately with feedback instead of silently producing
 * no map on the public page.
 *
 * Accepts three input shapes:
 *   1. A full <iframe ...></iframe> embed snippet (Maps' own
 *      Share > Embed a map option) - already a ready-to-use
 *      /maps/embed?pb=... URL, used as-is.
 *   2. A plain https://www.google.com/maps/... URL (place page, search
 *      result, etc.) - NOT directly embeddable as-is (Google serves
 *      regular Maps pages with X-Frame-Options blocking iframes), so
 *      this appends output=embed, which asks Google's servers for the
 *      iframe-safe rendering of that exact same view instead.
 *   3. A shortened https://maps.app.goo.gl/... or https://goo.gl/...
 *      link (what you get from Maps' "Share" button by default) -
 *      resolved server-side via cURL, following redirects, to find the
 *      real https://www.google.com/maps/... destination, then treated
 *      the same as #2.
 *
 * Returns the resolved embeddable URL, or null with $error explaining
 * why on failure - the caller should keep whatever was previously saved
 * and show $error, rather than overwrite a working map with a broken one.
 */
function resolve_google_map_embed($rawInput, &$error = null) {
    $error = null;
    $rawInput = trim($rawInput ?? '');
    if ($rawInput === '') return null; // clearing the field is allowed - caller treats this as "no map"

    // Pasted <iframe> embed code: pull out the src="..." attribute -
    // that's already a ready-to-use /maps/embed?pb=... URL.
    if (stripos($rawInput, '<iframe') !== false) {
        if (preg_match('/src=["\']([^"\']+)["\']/i', $rawInput, $m)) {
            $rawInput = $m[1];
        } else {
            $error = 'Could not find a src="..." attribute in that embed code.';
            return null;
        }
    }

    if (!preg_match('#^https?://#i', $rawInput)) {
        $error = "That doesn't look like a link - it should start with http:// or https://.";
        return null;
    }

    $host = parse_url($rawInput, PHP_URL_HOST);
    if (!$host) {
        $error = 'Could not read that as a valid URL.';
        return null;
    }

    // Short links (maps.app.goo.gl, goo.gl) need resolving to their real
    // destination before we can tell if they're even a Google Maps link.
    $isShortLink = (bool) preg_match('/(^|\.)goo\.gl$/i', $host);
    if ($isShortLink) {
        if (!function_exists('curl_init')) {
            $error = "This server can't follow shortened Google Maps links (the PHP cURL extension is unavailable). Please paste the full <iframe> embed code from Google Maps' Share > Embed a map option instead.";
            return null;
        }
        $ch = curl_init($rawInput);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_NOBODY         => true, // HEAD request - only the final resolved URL is needed, not the page body
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $resolvedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$resolvedUrl) {
            $error = 'Could not resolve that shortened link' . ($curlErr ? ' (' . $curlErr . ')' : '') . ". Please paste the full <iframe> embed code from Google Maps' Share > Embed a map option instead.";
            return null;
        }
        $rawInput = $resolvedUrl;
        $host = parse_url($rawInput, PHP_URL_HOST);
    }

    // Only allow Google Maps hosts, to prevent an arbitrary/malicious
    // iframe source (including one a short link unexpectedly resolved to)
    // from being embedded on the public page.
    if (!$host || !preg_match('/(^|\.)google\.[a-z.]+$/i', $host)) {
        $error = $isShortLink
            ? 'That shortened link resolved to a non-Google-Maps address, so it was rejected.'
            : 'Only Google Maps links are accepted.';
        return null;
    }

    // Already a direct embed URL (Share > Embed a map) - use as-is.
    if (stripos(parse_url($rawInput, PHP_URL_PATH) ?? '', '/maps/embed') !== false) {
        return $rawInput;
    }

    // A regular Maps page (place link, search result, resolved short
    // link, etc.) - Google blocks these from being iframed directly
    // (X-Frame-Options), so it needs converting to an embeddable form.
    //
    // Modern "place" URLs look like:
    //   .../maps/place/Some+Name/@14.8716,120.9836,17z/data=!3m1!4b1!4m5!...
    // The @lat,lng,zoom segment is present on virtually every resolved
    // Maps URL. Prefer rebuilding a minimal q=lat,lng&output=embed URL
    // from just those coordinates over naively appending output=embed to
    // the original URL: the trailing data=!...!... segment is an opaque,
    // heavily-encoded blob tied to that specific search/session context,
    // and Google's embed renderer frequently rejects it outright (this is
    // the same class of failure as the "Invalid pb parameter" error that
    // started this - a malformed/incompatible parameter blob) even though
    // the plain page loads fine in a normal tab. A coordinate-only URL has
    // no such blob to reject, so it's the more reliable choice whenever
    // coordinates are available.
    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+),(\d+(?:\.\d+)?)z/', $rawInput, $coordMatch)) {
        $lat = $coordMatch[1];
        $lng = $coordMatch[2];
        $zoom = (int) round((float) $coordMatch[3]);
        return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng)
            . '&z=' . $zoom . '&output=embed';
    }

    // No coordinates found (unusual, but possible for some URL shapes) -
    // fall back to the naive output=embed append as a best effort.
    $sep = (strpos($rawInput, '?') === false) ? '?' : '&';
    return $rawInput . $sep . 'output=embed';
}

/**
 * Extract a safe iframe `src` URL from whatever is stored in
 * about_school.map_embed for rendering on the public about.php page.
 * By the time a value reaches here it should already have been through
 * resolve_google_map_embed() above at save time, so this is mainly a
 * defense-in-depth re-validation (e.g. in case the row was edited
 * directly in the database) rather than the primary place link
 * resolution/conversion happens. Returns null if nothing usable was
 * found, so the caller can skip rendering the map section entirely
 * rather than emitting a broken/unsafe iframe.
 */
function about_map_src($rawInput) {
    $rawInput = trim($rawInput ?? '');
    if ($rawInput === '') return null;

    // Pasted <iframe> embed code: pull out the src="..." attribute.
    if (stripos($rawInput, '<iframe') !== false) {
        if (preg_match('/src=["\']([^"\']+)["\']/i', $rawInput, $m)) {
            $rawInput = $m[1];
        } else {
            return null;
        }
    }

    // Only allow Google Maps hosts, and only http(s) links, to prevent an
    // arbitrary/malicious iframe source from being embedded on the public page.
    $host = parse_url($rawInput, PHP_URL_HOST);
    if (!$host || !preg_match('/(^|\.)google\.[a-z.]+$/i', $host)) {
        return null;
    }
    if (!preg_match('#^https?://#i', $rawInput)) {
        return null;
    }

    return $rawInput;
}

/**
 * Human-readable file size (e.g. "1.4 MB"), used on upload-too-large error
 * messages and the Principal's Message photo/signature "Image Information"
 * panel.
 */
function format_file_size($bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Read-only file details (size, pixel dimensions, last-modified time) for
 * an image already stored in UPLOAD_DIR - powers the "Image Information"
 * panel on the Principal's Message admin page (filename/size/dimensions/
 * upload date). Deliberately NOT stored in the database: reading it live
 * from disk means it's always accurate, including right after a Replace,
 * with no extra columns or write path to keep in sync.
 * Returns null if the file doesn't exist.
 */
function about_image_stat($filename) {
    if (empty($filename)) return null;
    $path = UPLOAD_DIR . $filename;
    if (!is_file($path)) return null;

    $size = filesize($path);
    $dimensions = @getimagesize($path);

    return [
        'filename'   => $filename,
        'size'       => $size,
        'size_label' => format_file_size($size),
        'width'      => $dimensions[0] ?? null,
        'height'     => $dimensions[1] ?? null,
        'modified_at' => filemtime($path),
    ];
}

// ---------------------------------------------------------------------
// Language / i18n (2026-08: language switcher)
// ---------------------------------------------------------------------
// Resolution order for the active language, checked once per request and
// cached in a static var inside get_current_language():
//   1. $_SESSION['lang']         - set once known for the current session
//   2. $_COOKIE['sabnahis_lang'] - persists across sessions/visits
//   3. Browser Accept-Language   - only on a visitor's very first request,
//                                  before any preference exists yet
//   4. DEFAULT_LANGUAGE ('fil')
// A logged-in student's saved students.preferred_language is applied by
// student/includes/auth_check.php right after confirming the session,
// which just calls set_current_language() - kept out of this function so
// it stays usable on pages/contexts with no PDO connection.
function get_current_language() {
    static $resolved = null;
    if ($resolved !== null) return $resolved;

    if (session_status() === PHP_SESSION_NONE) session_start();

    $supported = array_keys(SUPPORTED_LANGUAGES);

    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $supported, true)) {
        $resolved = $_SESSION['lang'];
        return $resolved;
    }

    if (!empty($_COOKIE['sabnahis_lang']) && in_array($_COOKIE['sabnahis_lang'], $supported, true)) {
        $_SESSION['lang'] = $_COOKIE['sabnahis_lang'];
        $resolved = $_COOKIE['sabnahis_lang'];
        return $resolved;
    }

    // First-ever visit: no session, no cookie yet - fall back to the
    // browser's Accept-Language header. Per spec: English browsers get
    // English, everyone else gets the Filipino default.
    $detected = DEFAULT_LANGUAGE;
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (preg_match('/^\s*en\b/i', $acceptLanguage)) {
        $detected = 'en';
    }

    $_SESSION['lang'] = $detected;
    $resolved = $detected;
    return $resolved;
}

/**
 * Change the active language for the current session and persist it in a
 * long-lived cookie so anonymous visitors keep their choice on return
 * visits. Does not touch the database - callers that also want to save it
 * to a logged-in student's profile should update
 * students.preferred_language themselves (see set_language.php).
 */
function set_current_language($code) {
    if (!array_key_exists($code, SUPPORTED_LANGUAGES)) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['lang'] = $code;
    if (!headers_sent()) {
        setcookie('sabnahis_lang', $code, time() + 60 * 60 * 24 * 365, '/');
    }
    $_COOKIE['sabnahis_lang'] = $code; // readable for the rest of this request too
    return true;
}

/**
 * Translate a dot-path key (e.g. "nav.enroll_now") using the active
 * language's JSON dictionary in /lang, falling back to the site default
 * language and then English if a key is missing from a translation, and
 * finally to $fallback (or the key itself) if it's missing everywhere.
 * Dictionaries are loaded once per request and cached in a static var
 * keyed by language code.
 */
function t($key, $fallback = null) {
    static $dictionaries = [];

    $lang = get_current_language();

    foreach (array_unique([$lang, DEFAULT_LANGUAGE, 'en']) as $tryLang) {
        if (!isset($dictionaries[$tryLang])) {
            $path = __DIR__ . '/../lang/' . $tryLang . '.json';
            $dictionaries[$tryLang] = is_file($path)
                ? (json_decode(file_get_contents($path), true) ?: [])
                : [];
        }
        $value = $dictionaries[$tryLang];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = null;
                break;
            }
            $value = $value[$segment];
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    return $fallback !== null ? $fallback : $key;
}

/**
 * Full decoded dictionary for the active language, printed inline as
 * window.SABNAHIS_LANG_DICT by render_language_switcher_scripts() so
 * assets/js/i18n.js has everything it needs for instant client-side
 * switching without an extra request on first page load.
 */
function current_language_dictionary() {
    $path = __DIR__ . '/../lang/' . get_current_language() . '.json';
    return is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
}

/**
 * Renders the <script> block that hands assets/js/i18n.js everything it
 * needs (base URL, active language, its dictionary, and the supported
 * language list for the switcher UI), followed by the i18n.js include
 * itself. Call once per page, after site_header.php's/student header's
 * opening <head>/<body> markup, before render_language_switcher() is used.
 */
function render_language_switcher_scripts() {
    $html = '<script>';
    $html .= 'window.SABNAHIS_BASE_URL = ' . json_encode(BASE_URL) . ';';
    $html .= 'window.SABNAHIS_LANG = ' . json_encode(get_current_language()) . ';';
    $html .= 'window.SABNAHIS_LANG_DICT = ' . json_encode(current_language_dictionary()) . ';';
    $html .= 'window.SABNAHIS_LANGUAGES = ' . json_encode(SUPPORTED_LANGUAGES) . ';';
    $html .= '</script>';
    $html .= '<script src="' . BASE_URL . 'assets/js/i18n.js"></script>';
    return $html;
}

/**
 * Renders the 🌐 Language dropdown widget (flag + native name, opens a
 * menu of flag + native name + English name per language, checkmark on the
 * active one). Pure server-rendered markup - assets/js/i18n.js wires up
 * the open/close/select behavior via delegated listeners, so this function
 * has no JS dependency itself and degrades to a plain (non-interactive)
 * list if JS is unavailable.
 */
function render_language_switcher() {
    $current = get_current_language();
    $html = '<div class="lang-switcher">';
    $html .= '<button type="button" class="lang-switcher-trigger" aria-haspopup="true" aria-expanded="false">';
    $html .= '<span class="lang-switcher-current">' . (SUPPORTED_LANGUAGES[$current]['flag'] ?? '') . ' <span class="lang-switcher-current-name">' . clean(SUPPORTED_LANGUAGES[$current]['native_name'] ?? $current) . '</span></span>';
    $html .= '<span class="lang-switcher-caret" aria-hidden="true">&#9662;</span>';
    $html .= '</button>';
    $html .= '<ul class="lang-switcher-menu" hidden role="menu">';
    foreach (SUPPORTED_LANGUAGES as $code => $meta) {
        $isActive = $code === $current;
        $html .= '<li role="none"><button type="button" role="menuitemradio" aria-checked="' . ($isActive ? 'true' : 'false') . '" data-lang-option="' . clean($code) . '" class="' . ($isActive ? 'active' : '') . '">';
        $html .= '<span class="lang-flag" aria-hidden="true">' . $meta['flag'] . '</span>';
        $html .= '<span class="lang-names"><span class="lang-native">' . clean($meta['native_name']) . '</span>';
        if ($code !== 'fil' && $code !== 'en') {
            $html .= '<span class="lang-english">(' . clean($meta['name']) . ')</span>';
        }
        $html .= '</span>';
        $html .= '<span class="lang-switcher-check" style="visibility:' . ($isActive ? 'visible' : 'hidden') . '" aria-hidden="true">&#10003;</span>';
        $html .= '</button></li>';
    }
    $html .= '</ul></div>';
    return $html;
}

/**
 * Renders the small settings gear + "Display Settings" dropdown panel
 * (theme, text size, reduce motion, reset) - see assets/js/display_settings.js
 * for the open/close/select behavior and localStorage persistence, and
 * assets/css/style.css's Display Settings section (the --bg-, --success-
 * and --error- prefixed variables plus a :root[data-theme="dark"] override
 * block) for how a single set of CSS variables drives the actual theming
 * instead of duplicating dark-mode rules per component.
 *
 * Pure server-rendered markup, same pattern as render_language_switcher()
 * above - degrades to a plain (non-interactive, but harmless) block if JS
 * is unavailable, since everything it controls is resolved client-side
 * from localStorage rather than server state. The active theme/text-size/
 * reduce-motion indicators shown here are placeholders (unchecked/OFF) -
 * assets/js/display_settings.js corrects them to the real saved values
 * immediately on page load (syncPanelUI()), before the panel can ever be
 * opened, so there's no visible flash of the wrong state.
 *
 * 2026-08k, Phase 1: wired into includes/site_header.php (public site +
 * enrollment wizard) only. Reusable as-is for the Student Portal / admin
 * headers in a later phase - call this same function there too.
 */
function render_display_settings_widget() {
    $html = '<div class="display-settings">';
    $html .= '<button type="button" class="display-settings-trigger" id="displaySettingsToggle" aria-haspopup="true" aria-expanded="false" aria-controls="displaySettingsPanel" title="Display Settings" aria-label="Display Settings">';
    $html .= '<span aria-hidden="true">&#9881;&#65039;</span>';
    $html .= '</button>';

    $html .= '<div class="display-settings-panel" id="displaySettingsPanel" role="dialog" aria-label="Display Settings" hidden>';
    $html .= '<div class="display-settings-header">&#9881;&#65039; Display Settings</div>';

    $html .= '<div class="display-settings-section">';
    $html .= '<h4>Appearance</h4>';
    $html .= '<button type="button" class="display-settings-option" data-theme-option="light"><span>&#9728;&#65039; Light Mode</span><span class="display-settings-check" aria-hidden="true">&#10003;</span></button>';
    $html .= '<button type="button" class="display-settings-option" data-theme-option="dark"><span>&#127769; Dark Mode</span><span class="display-settings-check" aria-hidden="true">&#10003;</span></button>';
    $html .= '<button type="button" class="display-settings-option" data-theme-option="system"><span>&#128187; Use System Setting</span><span class="display-settings-check" aria-hidden="true">&#10003;</span></button>';
    $html .= '</div>';

    $html .= '<div class="display-settings-section">';
    $html .= '<h4>Text Size</h4>';
    $html .= '<div class="text-size-row">';
    $html .= '<button type="button" class="text-size-btn" data-text-size-option="small" aria-label="Small text">A&minus;</button>';
    $html .= '<button type="button" class="text-size-btn" data-text-size-option="default" aria-label="Default text size">Default</button>';
    $html .= '<button type="button" class="text-size-btn" data-text-size-option="large" aria-label="Large text">A+</button>';
    $html .= '</div></div>';

    $html .= '<div class="display-settings-section">';
    $html .= '<h4>Accessibility</h4>';
    $html .= '<div class="reduce-motion-row"><span>Reduce Motion</span>';
    $html .= '<button type="button" class="reduce-motion-toggle" id="reduceMotionToggle" aria-pressed="false">OFF</button>';
    $html .= '</div></div>';

    $html .= '<div class="display-settings-footer">';
    $html .= '<button type="button" class="btn-link" id="displaySettingsReset">Reset to Default</button>';
    $html .= '<p class="display-settings-reset-msg muted" id="displaySettingsResetMsg" hidden>Display settings have been reset successfully.</p>';
    $html .= '</div>';

    $html .= '</div></div>';
    return $html;
}

// ---------------------------------------------------------------------
// Multi-applicant-type online enrollment (2026-08d)
// ---------------------------------------------------------------------

/**
 * Looks up an existing student record by LRN or Student Number
 * (students.enrollment_no) for the Old Student / Balik-Aral re-enrollment
 * flows. Requires the applicant's birthdate to also match before returning
 * anything - an LRN or Student Number alone isn't secret (classmates,
 * family members, or old printed documents can expose them), so this is a
 * lightweight identity check, not just a lookup, to stop this
 * public/unauthenticated flow from being used to enumerate other
 * students' personal and guardian information.
 */
function find_student_for_reenrollment(PDO $pdo, $identifier, $birthdate) {
    $identifier = trim($identifier);
    $birthdate = trim($birthdate);
    if ($identifier === '' || $birthdate === '') return null;

    $stmt = $pdo->prepare('SELECT s.*, g.grade_name, g.level_type, st.strand_name, sec.section_name, sy.school_year
        FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN strands st ON s.strand_id = st.strand_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
        WHERE (s.lrn = ? OR s.enrollment_no = ?) AND s.birthdate = ? AND s.is_archived = 0
        LIMIT 1');
    $stmt->execute([$identifier, $identifier, $birthdate]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Given a student's current grade_level_id, returns the next grade
 * level's row (id + name + type) for the "Old Student" re-enrollment
 * suggestion, or null if they're already at the highest configured grade
 * level (a graduating Grade 12 student shouldn't be offered a "next
 * grade" here). Relies on grade_levels.grade_level_id reflecting
 * progression order - the same assumption every grade-level dropdown in
 * this codebase already makes (ORDER BY grade_level_id).
 */
function suggest_next_grade_level(PDO $pdo, $currentGradeLevelId) {
    if (empty($currentGradeLevelId)) return null;
    $stmt = $pdo->prepare('SELECT * FROM grade_levels WHERE grade_level_id > ? ORDER BY grade_level_id ASC LIMIT 1');
    $stmt->execute([$currentGradeLevelId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Backing query for the searchable Philippine school directory (AJAX -
 * see search_schools.php), used by the Elementary School / Previous
 * School / Last School Attended fields on the enrollment wizard. Matches
 * school name, municipality, province, or DepEd ID; requires at least 2
 * characters; always capped at $limit results so the client never has to
 * load the whole table. $limit is clamped and cast to int before being
 * inlined into the LIMIT clause (PDO placeholders for LIMIT are
 * unreliable across drivers) - safe since it never contains user input
 * directly.
 *
 * $level restricts results to schools that offer a given level:
 * 'elementary'/'junior_high'/'senior_high' map to the offers_* columns.
 * New Student's Elementary School field always passes 'elementary' (see
 * enrollment_wizard.js) - not optional, since New Student may only choose
 * an elementary school. Transferee/Balik-Aral pass whatever the applicant
 * picked in the optional Level filter dropdown (2026-08h), or null for
 * "any level".
 *
 * $region/$province (2026-08h) are optional additional filters from the
 * same dropdowns, narrowing among the text-search matches rather than
 * replacing the text search - matches the existing "don't dump the whole
 * directory in one response" principle.
 */
function search_schools_query(PDO $pdo, $query, $limit = 20, $level = null, $region = null, $province = null) {
    $query = trim($query);
    if (mb_strlen($query) < 2) return [];
    $limit = max(1, min(50, (int) $limit));

    $like = '%' . $query . '%';
    $prefix = $query . '%';
    $sql = "SELECT school_id, deped_school_id, school_code, school_name, region, division, province,
                   municipality, barangay, school_type, offers_elementary, offers_junior_high, offers_senior_high
        FROM schools
        WHERE is_active = 1
          AND (school_name LIKE ? OR municipality LIKE ? OR province LIKE ? OR deped_school_id LIKE ?)";
    $params = [$like, $like, $like, $like];

    $levelColumn = ['elementary' => 'offers_elementary', 'junior_high' => 'offers_junior_high', 'senior_high' => 'offers_senior_high'];
    if (isset($levelColumn[$level])) {
        $sql .= ' AND ' . $levelColumn[$level] . ' = 1';
    }
    if ($region !== null && $region !== '') {
        $sql .= ' AND region = ?';
        $params[] = $region;
    }
    if ($province !== null && $province !== '') {
        $sql .= ' AND province = ?';
        $params[] = $province;
    }

    $sql .= " ORDER BY (school_name LIKE ?) DESC, school_name ASC LIMIT $limit";
    $params[] = $prefix;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['education_level_label'] = school_education_levels_label($row);
    }
    unset($row);
    return $rows;
}

/**
 * Human-readable "Elementary / Junior High School / Senior High School"
 * label built from a schools row's offers_* flags, for search result
 * display and the auto-fill detail card on the enrollment wizard.
 */
function school_education_levels_label(array $school) {
    $levels = [];
    if (!empty($school['offers_elementary'])) $levels[] = 'Elementary';
    if (!empty($school['offers_junior_high'])) $levels[] = 'Junior High School';
    if (!empty($school['offers_senior_high'])) $levels[] = 'Senior High School';
    return $levels ? implode(' / ', $levels) : 'Not specified';
}

/**
 * Distinct non-empty values from a schools column, for the Region/
 * Province filter dropdowns above the school search box (2026-08h).
 * $column is never user input (only called with a hardcoded 'region' or
 * 'province' below), so inlining it is safe.
 */
function distinct_school_values(PDO $pdo, $column) {
    $allowed = ['region', 'province'];
    if (!in_array($column, $allowed, true)) return [];
    $stmt = $pdo->query("SELECT DISTINCT $column FROM schools WHERE $column IS NOT NULL AND $column != '' AND is_active = 1 ORDER BY $column ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Required / optional document lists for a given students.student_type
 * value. Reads from the requirement_configs table (2026-08h - admin-
 * configurable via admin/requirements.php) rather than the old hardcoded
 * REQUIRED_DOCUMENTS_BY_TYPE / OPTIONAL_DOCUMENTS_BY_TYPE constants in
 * config/constants.php. Falls back to those constants if the table is
 * missing or has no active rows for the type (e.g. migration_2026_08h
 * hasn't been run yet), so existing installs don't silently lose their
 * document requirements. Return shape is unchanged from before
 * (['doc_key' => 'Label', ...], ordered) so every existing caller keeps
 * working without modification.
 */
function required_documents_for_type(PDO $pdo, $studentType) {
    return document_requirements_for_type($pdo, $studentType, 1);
}

function optional_documents_for_type(PDO $pdo, $studentType) {
    return document_requirements_for_type($pdo, $studentType, 0);
}

/**
 * Human-readable label for a documents.doc_type key, regardless of which
 * applicant type it came from. Checks STUDENT_DOCUMENT_TYPES (the Student
 * Portal's own upload-type list) first, then any matching label in
 * requirement_configs (2026-08h - covers per-type keys like "form_137"
 * that STUDENT_DOCUMENT_TYPES doesn't know about), then falls back to the
 * raw key so nothing ever renders blank.
 */
function document_type_label(PDO $pdo, $docType) {
    if (isset(STUDENT_DOCUMENT_TYPES[$docType])) {
        return STUDENT_DOCUMENT_TYPES[$docType];
    }
    try {
        $stmt = $pdo->prepare('SELECT doc_label FROM requirement_configs WHERE doc_key = ? LIMIT 1');
        $stmt->execute([$docType]);
        $label = $stmt->fetchColumn();
        if ($label) return $label;
    } catch (PDOException $e) {
        // requirement_configs not migrated yet - fall through to REQUIRED_DOCUMENTS/raw key.
    }
    return REQUIRED_DOCUMENTS[$docType] ?? $docType;
}

function document_requirements_for_type(PDO $pdo, $studentType, $isRequired) {
    try {
        $stmt = $pdo->prepare('SELECT doc_key, doc_label FROM requirement_configs
            WHERE applicant_type = ? AND is_required = ? AND is_active = 1
            ORDER BY display_order ASC, requirement_id ASC');
        $stmt->execute([$studentType, $isRequired ? 1 : 0]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        // requirement_configs doesn't exist yet - migration_2026_08h hasn't
        // been run. Fall back to the old hardcoded constants rather than
        // leaving every applicant type with zero requirements.
        $constant = $isRequired ? REQUIRED_DOCUMENTS_BY_TYPE : OPTIONAL_DOCUMENTS_BY_TYPE;
        return $constant[$studentType] ?? [];
    }

    $out = [];
    foreach ($rows as $r) { $out[$r['doc_key']] = $r['doc_label']; }
    return $out;
}

// ---------------------------------------------------------------------
// Digital Student ID QR verification system (2026-08n)
// ---------------------------------------------------------------------

/**
 * Builds an absolute "scheme://host" prefix from the current request,
 * so links meant to be scanned by a phone camera (which has no "current
 * site" context) always resolve to a real, reachable address - whatever
 * host the site is actually being served from, in dev or production,
 * with no manual domain configuration required. Falls back to
 * 'http://localhost/' only in the (rare) case this is invoked outside a
 * normal web request (e.g. a future CLI/cron script).
 */
function absolute_base_url() {
    if (empty($_SERVER['HTTP_HOST'])) {
        return 'http://localhost/';
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') == 443
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/';
}

/**
 * The full public URL encoded into a student's QR code. BASE_URL is a
 * host-relative path (e.g. "/sabnahis/"), so it's combined with
 * absolute_base_url() here rather than used on its own.
 */
function qr_verify_url($token) {
    return rtrim(absolute_base_url(), '/') . BASE_URL . 'student/verify.php?token=' . urlencode($token);
}

/**
 * Returns a student's existing QR token, generating and saving one on
 * first use. Deliberately never regenerates an existing token - the same
 * QR code (already possibly printed/laminated) must keep working across
 * page loads until an administrator explicitly regenerates or deactivates
 * it (see regenerate_qr_token()/set_qr_status() below). Uses
 * random_bytes(), not uniqid()/mt_rand() - this token is a bearer
 * credential (whoever has the QR image can "prove" that student's
 * identity to verify.php), so it needs to be unguessable, not just unique.
 */
function get_or_create_qr_token(PDO $pdo, $studentId) {
    $stmt = $pdo->prepare('SELECT qr_token FROM students WHERE student_id = ?');
    $stmt->execute([$studentId]);
    $existing = $stmt->fetchColumn();
    if ($existing) return $existing;

    // Extremely small chance of a UNIQUE-key collision across the whole
    // students table; retry a couple of times rather than fail outright.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(32));
        try {
            $upd = $pdo->prepare('UPDATE students SET qr_token = ?, qr_generated_at = NOW() WHERE student_id = ? AND qr_token IS NULL');
            $upd->execute([$token, $studentId]);
            if ($upd->rowCount() > 0) return $token;
            // Someone else generated one concurrently (or a collision) -
            // re-check what's actually stored now.
            $stmt->execute([$studentId]);
            $existing = $stmt->fetchColumn();
            if ($existing) return $existing;
        } catch (PDOException $e) {
            continue; // likely the UNIQUE key collision - try a new token
        }
    }
    return null;
}

/**
 * Administrator action: issues a brand-new token, immediately making the
 * previous QR code (and anything printed/laminated with it) fail
 * verification - see student/verify.php, which only accepts an exact
 * qr_token match.
 */
function regenerate_qr_token(PDO $pdo, $studentId) {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(32));
        try {
            $pdo->prepare('UPDATE students SET qr_token = ?, qr_generated_at = NOW() WHERE student_id = ?')
                ->execute([$token, $studentId]);
            return $token;
        } catch (PDOException $e) {
            continue; // UNIQUE key collision (practically never) - try again
        }
    }
    return null;
}

/**
 * Administrator action: Activate/Deactivate QR (does not touch the token
 * itself, so re-activating restores the same QR code rather than
 * silently issuing a new one).
 */
function set_qr_status(PDO $pdo, $studentId, $status) {
    $status = $status === 'inactive' ? 'inactive' : 'active';
    $pdo->prepare('UPDATE students SET qr_status = ? WHERE student_id = ?')->execute([$status, $studentId]);
}

/**
 * Fetches a scannable QR PNG for $data from a free public QR-image API,
 * server-side (this runs on the school's own PHP host, which - unlike
 * the environment this code was written in - has normal outbound
 * internet access). Returns raw PNG bytes, or null on any failure so the
 * caller can fall back to qr_fallback_png() instead of serving a broken
 * image. No student PII is sent - $data is always just the verify.php
 * URL (school domain + opaque token), never a name/LRN/contact detail.
 */
function fetch_qr_png($data, $size = 300) {
    if (!function_exists('curl_init')) return null;

    $size = max(100, min(1000, (int) $size));
    $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => $size . 'x' . $size,
        'ecc'  => 'M', // ~15% error correction - reasonable for a printed/laminated ID
        'data' => $data,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200 || !$body || stripos((string) $contentType, 'image/') !== 0) {
        return null;
    }
    return $body;
}

/**
 * Last-resort fallback image for the Digital Student ID's QR slot: tried
 * only when fetch_qr_png() fails (e.g. the school server has no outbound
 * internet access, or the external service is briefly down). Never a raw
 * error/warning - this is student-facing and printable, so it must always
 * be a clean, professional message. Prefers generating the message via
 * GD (matches the exact size requested); if GD isn't available either,
 * falls back to the static pre-built assets/images/qr_unavailable.png so
 * something valid is always returned.
 */
function qr_fallback_png($size = 300) {
    if (function_exists('imagecreatetruecolor')) {
        $size = max(100, min(1000, (int) $size));
        $img = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($img, 244, 244, 244);
        $border = imagecolorallocate($img, 204, 204, 204);
        $text = imagecolorallocate($img, 102, 102, 102);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);
        imagerectangle($img, 1, 1, $size - 2, $size - 2, $border);

        $lines = ['QR Code', 'temporarily', 'unavailable', '', 'Please contact the', 'school administrator'];
        $lineHeight = max(12, (int) ($size / 22));
        $totalHeight = $lineHeight * count($lines);
        $y = (int) (($size - $totalHeight) / 2);
        foreach ($lines as $line) {
            if ($line !== '') {
                $charWidth = imagefontwidth(3);
                $x = (int) (($size - strlen($line) * $charWidth) / 2);
                imagestring($img, 3, max(0, $x), $y, $line, $text);
            }
            $y += $lineHeight;
        }

        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);
        if ($bytes) return $bytes;
    }

    $staticPath = __DIR__ . '/../assets/images/qr_unavailable.png';
    if (is_file($staticPath)) {
        return file_get_contents($staticPath);
    }
    return null;
}
