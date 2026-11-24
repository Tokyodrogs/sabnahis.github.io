<?php
/**
 * Multi-applicant-type online enrollment wizard (2026-08d/e).
 *
 * Two submission paths, shared by all four applicant types:
 *   - form=enroll    -> INSERT a new students row. Used by New Student
 *                       (always), Transferee (always), and Balik-Aral
 *                       when the lookup step found no existing record
 *                       (manual entry).
 *   - form=reenroll  -> UPDATE the existing students row found via
 *                       lookup_student.php. Used by Old Student (always)
 *                       and Balik-Aral when the lookup step DID find a
 *                       record. Trusts ONLY $_SESSION['reenroll_student_id']
 *                       for which record to touch - never a posted
 *                       student_id - and for identity/guardian fields
 *                       (name, birthdate, guardian info) re-reads the
 *                       existing row rather than trusting POST, even
 *                       though the UI shows those fields read-only,
 *                       because a UI lock is not enforcement. Only
 *                       address/contact/email (both types) and
 *                       grade/strand/years-not-enrolled/reason-for-
 *                       returning (Balik-Aral only) are accepted from POST.
 *   - form=create_portal_account -> unchanged self-service portal signup,
 *                       shown after either path succeeds AND the student
 *                       doesn't already have a portal account.
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Online Enrollment Form';
$errors = [];
$success = false;
$refNo = null;
$portalError = null;
$portalSuccessUsername = null;
$skipPortalSignup = false; // re-enrolling student who already has a Student Portal account

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$enrollmentOpen = $currentSY && $currentSY['status'] === 'Open';

$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$strands     = $pdo->query('SELECT * FROM strands ORDER BY strand_name')->fetchAll();
$schoolRegions   = distinct_school_values($pdo, 'region');
$schoolProvinces = distinct_school_values($pdo, 'province');

$grade7 = null;
foreach ($gradeLevels as $g) {
    if ($g['grade_name'] === 'Grade 7') { $grade7 = $g; break; }
}

function find_grade_level_by_id(array $gradeLevels, $id) {
    foreach ($gradeLevels as $g) {
        if ((int) $g['grade_level_id'] === (int) $id) return $g;
    }
    return null;
}

$form = [
    'lrn' => '', 'lastname' => '', 'firstname' => '', 'middlename' => '', 'extension' => '',
    'sex' => '', 'birthdate' => '', 'address' => '', 'contact' => '', 'email' => '',
    'previous_school' => '', 'previous_school_id' => '', 'school_type' => '',
    'reason_for_transfer' => '', 'reason_for_returning' => '', 'years_not_enrolled' => '', 'last_grade_completed' => '',
    'last_school_year_attended' => '',
    'grade_level_id' => '', 'strand_id' => '',
    'guardian_name' => '', 'guardian_contact' => '', 'guardian_relationship' => '',
    'guardian_occupation' => '', 'guardian_emergency_contact' => '',
    // ALS (2026-08i, Phase 1 - direct "Enroll in ALS Program" pathway only)
    'als_pathway' => '', 'als_place_of_birth' => '', 'als_barangay' => '', 'als_municipality' => '',
    'als_province' => '', 'als_region' => '', 'highest_education_completed' => '',
    'reason_for_leaving_als' => '', 'reason_for_leaving_als_other' => '',
    'learning_preference' => '', 'employment_status' => '', 'reason_for_enrolling_als' => '',
    'learning_goal' => '', 'learning_goal_other' => '',
];

$formAction = $_POST['form'] ?? '';
$isPortalSignupPost = $_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'create_portal_account';
$isNewEnrollPost    = $_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'enroll';
$isReenrollPost     = $_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'reenroll';

// ---------------------------------------------------------------------
// INSERT path: New Student, Transferee, or Balik-Aral (no record found)
// ---------------------------------------------------------------------
if ($isNewEnrollPost && $enrollmentOpen) {

    $studentType = $_POST['student_type'] ?? '';
    if (!isset(APPLICANT_TYPES[$studentType]) || !APPLICANT_TYPES[$studentType]['enabled'] || $studentType === 'Returning') {
        $errors[] = 'Please select a valid applicant type.';
        $studentType = '';
    }

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session has expired. Please reload the page and try again.';
    }

    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    $form['lrn'] = sanitize_lrn($form['lrn']);

    // ALS's "Last Grade Level Attended"/"Last Year Attended" fields post
    // under their own names (als_last_grade_completed/als_last_school_year_attended)
    // to avoid colliding with Balik-Aral's same-named fields, which are
    // rendered elsewhere in the same DOM - see stepAcademic's
    // alsEducationBlock. Both map onto the same last_grade_completed/
    // last_school_year_attended columns (reused across types).
    if (($_POST['student_type'] ?? '') === 'ALS') {
        $form['last_grade_completed'] = trim($_POST['als_last_grade_completed'] ?? '');
        $form['last_school_year_attended'] = trim($_POST['als_last_school_year_attended'] ?? '');
    }

    if ($form['lrn'] !== '') {
        if (!validate_lrn($form['lrn'])) {
            $errors[] = 'LRN must contain exactly 12 numeric digits.';
        } else {
            $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE lrn = ?');
            $dupStmt->execute([$form['lrn']]);
            if ($dupStmt->fetchColumn() > 0) {
                $errors[] = 'This LRN is already registered in our system.';
            }
        }
    }

    if ($form['lastname'] === '') $errors[] = 'Last Name is required.';
    if ($form['firstname'] === '') $errors[] = 'First Name is required.';
    if ($form['sex'] === '') $errors[] = 'Sex is required.';
    if ($form['birthdate'] === '') $errors[] = 'Birthdate is required.';
    if ($form['address'] === '') $errors[] = 'Address is required.';
    if ($form['contact'] === '') $errors[] = 'Contact Number is required.';
    // ALS learners are frequently out-of-school adults/youth applying on
    // their own behalf - the spec's ALS Personal Information section has
    // no parent/guardian fields at all (unlike the other four types).
    if ($studentType !== 'ALS') {
        if ($form['guardian_name'] === '') $errors[] = 'Parent/Guardian Name is required.';
        if ($form['guardian_contact'] === '') $errors[] = 'Parent/Guardian Contact Number is required.';
    }

    // Grade level: locked to Grade 7 for New Student (never trust POST for
    // that, even though the UI shows it locked); freely chosen (and
    // server-validated against the real list) for Transferee/Balik-Aral.
    $insertGradeId = null;
    $insertStrandId = null;
    $neverAttendedFormal = 0; // ALS only - overwritten below when applicable
    if ($studentType === 'New') {
        if (!$grade7) {
            $errors[] = 'Grade 7 is not configured in the system. Please contact the registrar.';
        } else {
            $insertGradeId = $grade7['grade_level_id'];
        }
        if ($form['previous_school'] === '') $errors[] = 'Elementary School is required.';
    } elseif ($studentType === 'Transferee' || $studentType === 'Balik-Aral') {
        $selectedGrade = find_grade_level_by_id($gradeLevels, $form['grade_level_id']);
        if (!$selectedGrade) {
            $errors[] = 'Please select a valid grade level.';
        } else {
            $insertGradeId = $selectedGrade['grade_level_id'];
            if ($selectedGrade['level_type'] === 'Senior High School') {
                if ($form['strand_id'] === '') {
                    $errors[] = 'Strand is required for Senior High School.';
                } else {
                    $insertStrandId = (int) $form['strand_id'];
                }
            }
        }

        if ($studentType === 'Transferee') {
            if ($form['previous_school'] === '') $errors[] = 'Previous School is required.';
            if ($form['reason_for_transfer'] === '') $errors[] = 'Reason for Transfer is required.';
        } else { // Balik-Aral, manual entry (no record found)
            if ($form['years_not_enrolled'] === '') $errors[] = 'Years Not Enrolled is required.';
            if ($form['reason_for_returning'] === '') $errors[] = 'Reason for Returning is required.';
            if ($form['last_grade_completed'] === '') $errors[] = 'Last Grade Completed is required.';
            if ($form['last_school_year_attended'] === '') $errors[] = 'Last School Year Attended is required.';
        }
    } elseif ($studentType === 'ALS') {
        // Phase 1 only implements the direct "Enroll in ALS Program"
        // pathway end-to-end - reject anything else defensively even
        // though the UI only lets that one be selected (ALS_PATHWAYS'
        // other two entries are enabled=false).
        if ($form['als_pathway'] !== 'als_program') {
            $errors[] = 'Please select the ALS enrollment pathway.';
        }

        $neverAttendedFormal = !empty($_POST['never_attended_formal_school']) ? 1 : 0;
        if ($neverAttendedFormal) {
            $form['previous_school'] = 'No Previous Formal School';
            $form['previous_school_id'] = '';
        } elseif ($form['previous_school'] === '') {
            $errors[] = 'Previous School is required, or check "I have never attended a formal school".';
        }

        if (!in_array($form['highest_education_completed'], HIGHEST_EDUCATION_OPTIONS, true)) {
            $errors[] = 'Highest Educational Level Completed is required.';
        }
        if (!in_array($form['reason_for_leaving_als'], REASON_FOR_LEAVING_OPTIONS, true)) {
            $errors[] = 'Reason for Leaving School is required.';
        } elseif ($form['reason_for_leaving_als'] === 'Other' && $form['reason_for_leaving_als_other'] === '') {
            $errors[] = 'Please specify your reason for leaving school.';
        }
        if (!in_array($form['learning_preference'], LEARNING_PREFERENCE_OPTIONS, true)) {
            $errors[] = 'Preferred Learning Arrangement is required.';
        }
        if (!in_array($form['employment_status'], EMPLOYMENT_STATUS_OPTIONS, true)) {
            $errors[] = 'Current Employment Status is required.';
        }
        if (!in_array($form['learning_goal'], LEARNING_GOAL_OPTIONS, true)) {
            $errors[] = 'Learning Goal is required.';
        } elseif ($form['learning_goal'] === 'Other' && $form['learning_goal_other'] === '') {
            $errors[] = 'Please specify your learning goal.';
        }
    }

    $requiredDocs = $studentType ? required_documents_for_type($pdo, $studentType) : [];
    $optionalDocs = $studentType ? optional_documents_for_type($pdo, $studentType) : [];
    $docSuffix = DOC_FIELD_SUFFIX_BY_TYPE[$studentType] ?? '';
    foreach ($requiredDocs as $docKey => $label) {
        if (empty($_FILES[$docKey . $docSuffix]['name'])) {
            $errors[] = $label . ' is required.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $age = calculate_age($form['birthdate']);
            $stmt = $pdo->prepare('INSERT INTO students
                (lrn, lastname, firstname, middlename, extension, sex, birthdate, age, address, contact, email,
                 previous_school, previous_school_id, school_type, reason_for_transfer, reason_for_returning,
                 years_not_enrolled, last_grade_completed, last_school_year_attended, grade_level_id, strand_id,
                 guardian_name, guardian_contact, guardian_relationship, guardian_occupation, guardian_emergency_contact,
                 status, student_type, sy_id,
                 als_enrollment_pathway, als_place_of_birth, als_barangay, als_municipality, als_province, als_region,
                 highest_education_completed, never_attended_formal_school, reason_for_leaving_als, reason_for_leaving_als_other,
                 learning_preference, employment_status, reason_for_enrolling_als, learning_goal, learning_goal_other)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'Pending\',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $form['lrn'] ?: null, $form['lastname'], $form['firstname'], $form['middlename'], $form['extension'],
                $form['sex'], $form['birthdate'], $age, $form['address'], $form['contact'], $form['email'],
                $form['previous_school'] ?: null, $form['previous_school_id'] ?: null, $form['school_type'] ?: null,
                $form['reason_for_transfer'] ?: null, $form['reason_for_returning'] ?: null,
                $form['years_not_enrolled'] ?: null, $form['last_grade_completed'] ?: null, $form['last_school_year_attended'] ?: null,
                $insertGradeId, $insertStrandId,
                $form['guardian_name'] ?: null, $form['guardian_contact'] ?: null, $form['guardian_relationship'] ?: null,
                $form['guardian_occupation'] ?: null, $form['guardian_emergency_contact'] ?: null,
                $studentType, $currentSY['sy_id'],
                $form['als_pathway'] ?: null, $form['als_place_of_birth'] ?: null, $form['als_barangay'] ?: null,
                $form['als_municipality'] ?: null, $form['als_province'] ?: null, $form['als_region'] ?: null,
                $form['highest_education_completed'] ?: null, $neverAttendedFormal,
                $form['reason_for_leaving_als'] ?: null, $form['reason_for_leaving_als_other'] ?: null,
                $form['learning_preference'] ?: null, $form['employment_status'] ?: null,
                $form['reason_for_enrolling_als'] ?: null, $form['learning_goal'] ?: null, $form['learning_goal_other'] ?: null,
            ]);
            $studentId = $pdo->lastInsertId();

            foreach ($requiredDocs as $docKey => $label) {
                $uploadError = null;
                $savedName = handle_document_upload($docKey . $docSuffix, $studentId, $docKey, $uploadError);
                if ($savedName === false) {
                    throw new RuntimeException($label . ': ' . $uploadError);
                }
                $pdo->prepare('INSERT INTO documents (student_id, doc_type, file_name, file_path) VALUES (?,?,?,?)')
                    ->execute([$studentId, $docKey, $savedName, 'uploads/' . $savedName]);
            }
            foreach ($optionalDocs as $docKey => $label) {
                if (!empty($_FILES[$docKey . $docSuffix]['name'])) {
                    $uploadError = null;
                    $savedName = handle_document_upload($docKey . $docSuffix, $studentId, $docKey, $uploadError);
                    if ($savedName !== false) {
                        $pdo->prepare('INSERT INTO documents (student_id, doc_type, file_name, file_path) VALUES (?,?,?,?)')
                            ->execute([$studentId, $docKey, $savedName, 'uploads/' . $savedName]);
                    }
                }
            }

            $pdo->prepare('INSERT INTO enrollment (student_id, status, remarks) VALUES (?, \'Pending\', ?)')
                ->execute([$studentId, 'Submitted via online enrollment form (' . APPLICANT_TYPES[$studentType]['label'] . ')']);

            $pdo->commit();
            $success = true;
            $refNo = $studentId;
            unset($_SESSION['csrf_token']);
            $_SESSION['pending_portal_student_id'] = $studentId;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Submission failed: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------
// UPDATE-in-place path: Old Student, or Balik-Aral when a matching
// record WAS found during the lookup step.
// ---------------------------------------------------------------------
if ($isReenrollPost && $enrollmentOpen) {
    $reenrollId = (int) ($_SESSION['reenroll_student_id'] ?? 0);
    $reenrollType = ($_POST['student_type'] ?? '') === 'Balik-Aral' ? 'Balik-Aral' : 'Returning';

    if (!$reenrollId) {
        $errors[] = 'Your record lookup has expired. Please look up your record again.';
    } elseif (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session has expired. Please reload the page and try again.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ? AND is_archived = 0');
        $stmt->execute([$reenrollId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $errors[] = 'We could not find your record. Please contact the registrar.';
        } elseif ($currentSY && (int) $existing['sy_id'] === (int) $currentSY['sy_id']) {
            $errors[] = 'You already have an enrollment application on file for the ' . $currentSY['school_year'] . ' school year.';
        } else {
            $reAddress = trim($_POST['address'] ?? '');
            $reContact = trim($_POST['contact'] ?? '');
            $reEmail   = trim($_POST['email'] ?? '');

            if ($reAddress === '') $errors[] = 'Address is required.';
            if ($reContact === '') $errors[] = 'Contact Number is required.';

            // Required documents are admin-configurable per type
            // (requirement_configs, 2026-08h) - 'Returning' ships with none
            // by default (records already on file), but an admin can add
            // some, and 'Balik-Aral' does have seeded requirements even
            // when found via lookup. A document already Approved from a
            // prior enrollment doesn't need to be re-uploaded.
            $reRequiredDocs = required_documents_for_type($pdo, $reenrollType);
            $reOptionalDocs = optional_documents_for_type($pdo, $reenrollType);
            $reDocSuffix = DOC_FIELD_SUFFIX_BY_TYPE[$reenrollType] ?? '';
            foreach ($reRequiredDocs as $docKey => $label) {
                $approvedStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE student_id = ? AND doc_type = ? AND status = 'Approved'");
                $approvedStmt->execute([$reenrollId, $docKey]);
                $alreadyApproved = $approvedStmt->fetchColumn() > 0;
                if (!$alreadyApproved && empty($_FILES[$docKey . $reDocSuffix]['name'])) {
                    $errors[] = $label . ' is required.';
                }
            }

            $reYearsNotEnrolled = null;
            $reReasonReturning = null;

            if ($reenrollType === 'Balik-Aral') {
                // Placement after a gap is a judgment call, not a strict
                // +1 progression - the applicant picks their entering
                // grade (server-validated against the real list), same as
                // the Transferee insert path above.
                $selectedGrade = find_grade_level_by_id($gradeLevels, $_POST['grade_level_id'] ?? '');
                if (!$selectedGrade) {
                    $errors[] = 'Please select a valid grade level.';
                    $newGradeId = null;
                    $newStrandId = null;
                } else {
                    $newGradeId = $selectedGrade['grade_level_id'];
                    $newStrandId = null;
                    if ($selectedGrade['level_type'] === 'Senior High School') {
                        $postedStrand = trim($_POST['strand_id'] ?? '');
                        if ($postedStrand === '') {
                            $errors[] = 'Strand is required for Senior High School.';
                        } else {
                            $newStrandId = (int) $postedStrand;
                        }
                    }
                }
                $reYearsNotEnrolled = trim($_POST['years_not_enrolled'] ?? '');
                $reReasonReturning = trim($_POST['reason_for_returning'] ?? '');
                if ($reYearsNotEnrolled === '') $errors[] = 'Years Not Enrolled is required.';
                if ($reReasonReturning === '') $errors[] = 'Reason for Returning is required.';
            } else {
                // Old Student - automatic next-grade suggestion, never
                // trusted from POST.
                $nextGrade = suggest_next_grade_level($pdo, $existing['grade_level_id']);
                if (!$nextGrade) {
                    $errors[] = 'No further grade level is configured after your current grade. Please contact the registrar.';
                    $newGradeId = null;
                } else {
                    $newGradeId = $nextGrade['grade_level_id'];
                }
                $newStrandId = $existing['strand_id'];
                if ($nextGrade && $nextGrade['grade_name'] === 'Grade 11') {
                    $postedStrand = trim($_POST['strand_id'] ?? '');
                    if ($postedStrand === '') {
                        $errors[] = 'Strand is required for Senior High School.';
                    } else {
                        $newStrandId = (int) $postedStrand;
                    }
                }
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE students SET address = ?, contact = ?, email = ?, grade_level_id = ?,
                            strand_id = ?, section_id = NULL, status = \'Pending\', student_type = ?, sy_id = ?,
                            years_not_enrolled = ?, reason_for_returning = ?
                            WHERE student_id = ?')
                        ->execute([$reAddress, $reContact, $reEmail ?: null, $newGradeId,
                                   $newStrandId ?: null, $reenrollType, $currentSY['sy_id'],
                                   $reYearsNotEnrolled ?: null, $reReasonReturning ?: null, $reenrollId]);

                    foreach (array_merge($reRequiredDocs, $reOptionalDocs) as $docKey => $label) {
                        if (empty($_FILES[$docKey . $reDocSuffix]['name'])) continue; // not re-uploaded (optional, or already Approved)
                        $uploadError = null;
                        $savedName = handle_document_upload($docKey . $reDocSuffix, $reenrollId, $docKey, $uploadError);
                        if ($savedName === false) {
                            throw new RuntimeException($label . ': ' . $uploadError);
                        }
                        save_document_upload($pdo, $reenrollId, $docKey, $savedName);
                    }

                    $pdo->prepare('INSERT INTO enrollment (student_id, status, remarks) VALUES (?, \'Pending\', ?)')
                        ->execute([$reenrollId, 'Re-enrollment submitted via online enrollment form (' . APPLICANT_TYPES[$reenrollType]['label'] . ')']);

                    $pdo->commit();
                    $success = true;
                    $refNo = $reenrollId;
                    unset($_SESSION['csrf_token'], $_SESSION['reenroll_student_id']);

                    $accCheck = $pdo->prepare('SELECT COUNT(*) FROM student_accounts WHERE student_id = ?');
                    $accCheck->execute([$reenrollId]);
                    if ($accCheck->fetchColumn() > 0) {
                        $skipPortalSignup = true;
                    } else {
                        $_SESSION['pending_portal_student_id'] = $reenrollId;
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Submission failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// ---------------------------------------------------------------------
// Self-service Student Portal signup, shown right after a successful
// submission above (or on a later visit while the signup is still
// pending). The student_id is trusted only from the session set on
// submission - never from user-editable POST data.
// ---------------------------------------------------------------------
if ($isPortalSignupPost) {
    $pendingId = (int) ($_SESSION['pending_portal_student_id'] ?? 0);
    $postedId  = (int) ($_POST['student_id'] ?? 0);

    if (!$pendingId || $pendingId !== $postedId) {
        $portalError = 'This account setup link is no longer valid. Please contact the registrar to set up your Student Portal account.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? '', 'create_portal_account')) {
        $portalError = 'Your session has expired. Please reload the page and try again.';
    } else {
        $chosenUsername  = trim($_POST['username'] ?? '');
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $usernameErrors = validate_student_username($chosenUsername);

        if ($usernameErrors) {
            $portalError = $usernameErrors[0];
        } elseif ($newPassword === '' || $confirmPassword === '') {
            $portalError = 'Please enter and confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $portalError = 'Password and confirmation do not match.';
        } else {
            $policyErrors = validate_password_policy($newPassword);
            if ($policyErrors) {
                $portalError = 'Password must contain ' . implode(', ', $policyErrors) . '.';
            } else {
                $dupCheck = $pdo->prepare('SELECT COUNT(*) FROM student_accounts WHERE username = ?');
                $dupCheck->execute([$chosenUsername]);
                if ($dupCheck->fetchColumn() > 0) {
                    $portalError = 'Username already exists. Please choose another username.';
                } else {
                    $account = create_student_account($pdo, $pendingId, $newPassword, $chosenUsername);
                    if ($account === false) {
                        $portalError = 'That username was just taken by someone else. Please choose another username.';
                    } elseif ($account) {
                        $portalSuccessUsername = $account['username'];
                        unset($_SESSION['pending_portal_student_id']);
                    } else {
                        $portalError = 'Could not create the account - student record not found. Please contact the registrar.';
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/site_header.php';
?>
<section class="section enrollment-wizard-section">
    <h1>Online Enrollment Form</h1>

    <?php if ($portalSuccessUsername): ?>
        <div class="alert alert-success">
            <p>Your Student Portal account is ready.</p>
            <p>Username: <strong><?php echo clean($portalSuccessUsername); ?></strong></p>
            <p>You can log in now with the password you just created to track your enrollment status, upload
               documents, and more.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>student/login.php" class="btn-cta" data-i18n="enroll.go_to_login"><?php echo clean(t('enroll.go_to_login')); ?></a>

    <?php elseif ($skipPortalSignup && $success): ?>
        <div class="alert alert-success">
            <p>Your re-enrollment application has been submitted successfully. Please wait for approval from the
               school administrator.</p>
            <p>Your reference number is <strong>#<?php echo str_pad($refNo, 6, '0', STR_PAD_LEFT); ?></strong>.
               Please keep this for your records.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>student/login.php" class="btn-cta">Go to Student Portal</a>

    <?php elseif (!empty($_SESSION['pending_portal_student_id'])): ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>Your enrollment request has been submitted successfully. Please wait for approval from the school
                   administrator.</p>
                <p>Your reference number is <strong>#<?php echo str_pad($refNo, 6, '0', STR_PAD_LEFT); ?></strong>.
                   Please keep this for your records.</p>
            </div>
        <?php endif; ?>

        <div class="enroll-form" style="max-width:480px;">
            <h3 style="margin-top:0;" data-i18n="enroll.create_account_title"><?php echo clean(t('enroll.create_account_title')); ?></h3>
            <p class="muted">Choose your own username and password now so you can log in anytime to check your
               enrollment status, upload documents, and view announcements. You can also skip this and set it up later.</p>

            <?php if ($portalError): ?><div class="alert alert-error"><?php echo clean($portalError); ?></div><?php endif; ?>

            <form method="POST" id="portalAccountForm">
                <input type="hidden" name="form" value="create_portal_account">
                <input type="hidden" name="student_id" value="<?php echo (int) $_SESSION['pending_portal_student_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo clean(csrf_token('create_portal_account')); ?>">

                <label><span data-i18n="enroll.username"><?php echo clean(t('enroll.username')); ?></span> *
                    <input type="text" name="username" id="portalUsername" placeholder="<?php echo clean(t('enroll.username_placeholder')); ?>"
                           data-i18n-placeholder="enroll.username_placeholder"
                           minlength="5" maxlength="30" pattern="[A-Za-z0-9._]+" autocomplete="off" required>
                    <small class="field-availability" id="usernameAvailability"></small>
                </label>

                <label><span data-i18n="enroll.password"><?php echo clean(t('enroll.password')); ?></span> *
                    <div class="password-field">
                        <input type="password" name="new_password" id="newPassword" required>
                        <button type="button" class="password-toggle" data-i18n="common.show"><?php echo clean(t('common.show')); ?></button>
                    </div>
                </label>
                <label><span data-i18n="enroll.confirm_password"><?php echo clean(t('enroll.confirm_password')); ?></span> *
                    <div class="password-field">
                        <input type="password" name="confirm_password" id="confirmPassword" required>
                        <button type="button" class="password-toggle" data-i18n="common.show"><?php echo clean(t('common.show')); ?></button>
                    </div>
                </label>
                <small class="field-availability" id="confirmPasswordMatch"></small>

                <div class="password-strength-meter" id="passwordStrengthMeter">
                    <div class="password-strength-bar"><div class="password-strength-fill" id="passwordStrengthFill"></div></div>
                    <span class="password-strength-label" id="passwordStrengthLabel"></span>
                </div>

                <ul class="password-rules" id="passwordRules">
                    <li data-rule="length">At least 8 characters</li>
                    <li data-rule="upper">One uppercase letter</li>
                    <li data-rule="lower">One lowercase letter</li>
                    <li data-rule="number">One number</li>
                    <li data-rule="special">One special character</li>
                </ul>

                <div class="form-actions">
                    <button type="submit" class="btn-cta" data-i18n="enroll.create_account_btn"><?php echo clean(t('enroll.create_account_btn')); ?></button>
                </div>
            </form>
            <p class="muted" style="margin-top:14px;">
                <a href="<?php echo BASE_URL; ?>index.php">Skip for now</a> &mdash; the registrar can also set this up
                for you later.
            </p>
        </div>

    <?php elseif (!$enrollmentOpen): ?>
        <div class="alert alert-error">
            Online enrollment is currently <strong>CLOSED</strong>. Please check back later or contact the school
            administrator for more information.
        </div>
    <?php else: ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul>
            </div>
        <?php endif; ?>

        <ol class="wizard-stepper" id="wizardStepper">
            <li data-step="type" class="wizard-step-active">1. Applicant Type</li>
            <li data-step="personal">2. Personal Info</li>
            <li data-step="academic">3. Academic Info</li>
            <li data-step="parent">4. Parent Info</li>
            <li data-step="requirements">5. Requirements</li>
            <li data-step="review">6. Review</li>
        </ol>

        <!-- Step: Applicant Type -->
        <div class="wizard-panel" id="stepType" data-wizard-panel="type">
            <h3>&#128203; Select Applicant Type</h3>
            <div class="applicant-type-grid">
                <?php foreach (APPLICANT_TYPES as $typeKey => $type): ?>
                    <div class="applicant-card<?php echo $type['enabled'] ? '' : ' applicant-card-disabled'; ?>"
                         data-type="<?php echo clean($typeKey); ?>"
                         data-enabled="<?php echo $type['enabled'] ? '1' : '0'; ?>"
                         tabindex="<?php echo $type['enabled'] ? '0' : '-1'; ?>"
                         role="button" aria-pressed="false">
                        <?php if (!$type['enabled']): ?><span class="coming-soon-badge">Coming Soon</span><?php endif; ?>
                        <span class="applicant-card-check" aria-hidden="true">&#10003;</span>
                        <div class="applicant-card-icon" aria-hidden="true"><?php echo $type['icon']; ?></div>
                        <div class="applicant-card-title"><?php echo clean($type['label']); ?></div>
                        <?php if ($type['subtitle']): ?><div class="applicant-card-subtitle"><?php echo clean($type['subtitle']); ?></div><?php endif; ?>
                        <p class="applicant-card-desc"><?php echo clean($type['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ALS Pathway sub-step - revealed by JS when the ALS card
                 above is selected. ALS_PATHWAYS (config/constants.php) held
                 three entries through 2026-08i (two rendered as disabled
                 "Coming Soon" cards); removed to just 'als_program' in
                 2026-08j at the user's request, so this now renders a
                 single card that JS auto-selects the moment ALS is picked -
                 no extra click needed. Left as a foreach (not hardcoded
                 markup) so a pathway can be added back the same way it was
                 removed, without touching this template. -->
            <div class="als-pathway-section" id="alsPathwaySection" hidden>
                <h4>ALS Enrollment Pathway</h4>
                <div class="als-pathway-grid">
                    <?php foreach (ALS_PATHWAYS as $pathwayKey => $pathway): ?>
                        <div class="als-pathway-card<?php echo $pathway['enabled'] ? '' : ' als-pathway-card-disabled'; ?>"
                             data-pathway="<?php echo clean($pathwayKey); ?>"
                             data-enabled="<?php echo $pathway['enabled'] ? '1' : '0'; ?>"
                             tabindex="<?php echo $pathway['enabled'] ? '0' : '-1'; ?>"
                             role="button" aria-pressed="false">
                            <?php if (!$pathway['enabled']): ?><span class="coming-soon-badge">Coming Soon</span><?php endif; ?>
                            <span class="applicant-card-check" aria-hidden="true">&#10003;</span>
                            <div class="als-pathway-icon" aria-hidden="true">&#128218;</div>
                            <div class="als-pathway-title"><?php echo clean($pathway['label']); ?></div>
                            <p class="applicant-card-desc"><?php echo clean($pathway['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn-cta" id="wizardTypeContinueBtn" disabled>Continue</button>
            </div>
        </div>

        <!-- Step: Lookup (Old Student and Balik-Aral) -->
        <div class="wizard-panel" id="stepLookup" data-wizard-panel="lookup" hidden>
            <h3>&#128269; Find Your Record</h3>
            <p class="muted" id="lookupIntroText">Enter your LRN or Student Number, plus your birthdate, to retrieve your enrollment record.</p>
            <div class="form-row">
                <label>LRN or Student Number *<input type="text" id="lookupIdentifier" autocomplete="off"></label>
                <label>Birthdate *<input type="date" id="lookupBirthdate"></label>
            </div>
            <button type="button" class="btn-secondary" id="lookupSubmitBtn">Find My Record</button>
            <div id="lookupResult"></div>
            <p class="muted" id="lookupManualEntryHint" hidden>Can't find your old record, or enrolling after a long
                break? <button type="button" class="btn-link" id="lookupManualEntryBtn">Continue with Manual Entry</button></p>
            <div class="wizard-nav">
                <button type="button" class="btn-link" id="lookupBackBtn">&larr; Back</button>
                <button type="button" class="btn-cta" id="wizardLookupContinueBtn" disabled>Continue</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="enroll-form" id="enrollForm">
            <input type="hidden" name="form" id="formActionInput" value="enroll">
            <input type="hidden" name="csrf_token" value="<?php echo clean($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="student_type" id="studentTypeInput" value="">
            <input type="hidden" name="als_pathway" id="alsPathwayInput" value="">

            <!-- Step: Personal Information -->
            <div class="wizard-panel" id="stepPersonal" data-wizard-panel="personal" hidden>
                <h3 data-i18n="enroll.personal_info"><?php echo clean(t('enroll.personal_info')); ?></h3>
                <div class="form-row">
                    <label><span data-i18n="enroll.lrn"><?php echo clean(t('enroll.lrn')); ?></span>
                        <input type="text" name="lrn" id="lrn" inputmode="numeric" pattern="\d{12}" maxlength="12"
                               placeholder="<?php echo clean(t('enroll.lrn_placeholder')); ?>" data-i18n-placeholder="enroll.lrn_placeholder">
                        <small class="field-error" id="lrnError"></small>
                    </label>
                    <label><span data-i18n="enroll.lastname"><?php echo clean(t('enroll.lastname')); ?></span> *<input type="text" name="lastname" id="fLastname" required></label>
                    <label><span data-i18n="enroll.firstname"><?php echo clean(t('enroll.firstname')); ?></span> *<input type="text" name="firstname" id="fFirstname" required></label>
                    <label><span data-i18n="enroll.middlename"><?php echo clean(t('enroll.middlename')); ?></span><input type="text" name="middlename" id="fMiddlename"></label>
                    <label><span data-i18n="enroll.extension"><?php echo clean(t('enroll.extension')); ?></span><input type="text" name="extension" id="fExtension" placeholder="<?php echo clean(t('enroll.extension_placeholder')); ?>" data-i18n-placeholder="enroll.extension_placeholder"></label>
                </div>
                <div class="form-row">
                    <label><span data-i18n="enroll.sex"><?php echo clean(t('enroll.sex')); ?></span> *
                        <select name="sex" id="fSex" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </label>
                    <label><span data-i18n="enroll.birthdate"><?php echo clean(t('enroll.birthdate')); ?></span> *<input type="date" id="birthdate" name="birthdate" required></label>
                    <label><span data-i18n="enroll.age"><?php echo clean(t('enroll.age')); ?></span><input type="text" id="age" name="age_display" readonly placeholder="auto-computed"></label>
                </div>
                <div class="form-row">
                    <label class="full"><span data-i18n="enroll.address"><?php echo clean(t('enroll.address')); ?></span> *<input type="text" name="address" id="fAddress" required></label>
                </div>
                <div class="form-row">
                    <label><span data-i18n="enroll.contact_number"><?php echo clean(t('enroll.contact_number')); ?></span> *<input type="text" name="contact" id="fContact" required></label>
                    <label><span data-i18n="enroll.email"><?php echo clean(t('enroll.email')); ?></span><input type="email" name="email" id="fEmail"></label>
                </div>
                <!-- ALS-only Personal Information extras (2026-08i) -->
                <div class="form-row" id="alsPersonalExtraBlock" hidden>
                    <label>Place of Birth<input type="text" name="als_place_of_birth" id="fAlsPlaceOfBirth"></label>
                    <label>Barangay<input type="text" name="als_barangay" id="fAlsBarangay"></label>
                    <label>Municipality<input type="text" name="als_municipality" id="fAlsMunicipality"></label>
                    <label>Province<input type="text" name="als_province" id="fAlsProvince"></label>
                    <label>Region<input type="text" name="als_region" id="fAlsRegion"></label>
                </div>
                <div class="form-row" id="elementarySchoolRow">
                    <label class="full" id="elementarySchoolLabel">
                        <span id="schoolFieldLabelText">Elementary School</span> <span id="schoolFieldRequiredMark">*</span>
                        <div class="school-search-filters" id="schoolSearchFilters">
                            <select id="schoolFilterRegion" aria-label="Filter by Region">
                                <option value="">All Regions</option>
                                <?php foreach ($schoolRegions as $r): ?>
                                    <option value="<?php echo clean($r); ?>"><?php echo clean($r); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="schoolFilterProvince" aria-label="Filter by Province">
                                <option value="">All Provinces</option>
                                <?php foreach ($schoolProvinces as $p): ?>
                                    <option value="<?php echo clean($p); ?>"><?php echo clean($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="schoolFilterLevel" aria-label="Filter by School Level">
                                <option value="">All Levels</option>
                                <option value="elementary">Elementary</option>
                                <option value="junior_high">Junior High School</option>
                                <option value="senior_high">Senior High School</option>
                            </select>
                        </div>
                        <div class="school-search" id="previousSchoolSearchWrap">
                            <input type="text" id="previousSchoolSearch" placeholder="Type at least 2 characters to search..." autocomplete="off">
                            <div class="school-search-results" id="previousSchoolResults" hidden></div>
                        </div>
                        <div class="school-autofill-card" id="schoolAutoFillCard" hidden>
                            <div class="school-autofill-row"><span>Region</span><strong id="schoolAutoFillRegion">-</strong></div>
                            <div class="school-autofill-row"><span>Division</span><strong id="schoolAutoFillDivision">-</strong></div>
                            <div class="school-autofill-row"><span>Province / Municipality</span><strong id="schoolAutoFillLocation">-</strong></div>
                            <div class="school-autofill-row"><span>Ownership</span><strong id="schoolAutoFillType">-</strong></div>
                            <div class="school-autofill-row"><span>Education Level</span><strong id="schoolAutoFillLevel">-</strong></div>
                            <div class="school-autofill-row" id="schoolAutoFillDepedIdRow" hidden><span>DepEd School ID</span><strong id="schoolAutoFillDepedId">-</strong></div>
                        </div>
                        <label class="school-not-listed">
                            <input type="checkbox" id="previousSchoolNotListed"> My School Is Not Listed
                        </label>
                        <label class="school-not-listed" id="alsNeverAttendedWrap" hidden>
                            <input type="checkbox" id="alsNeverAttendedFormal" name="never_attended_formal_school" value="1"> I have never attended a formal school
                        </label>
                        <input type="text" id="previousSchoolManual" placeholder="Type your school's name" hidden>
                        <input type="hidden" name="previous_school" id="previousSchoolName">
                        <input type="hidden" name="previous_school_id" id="previousSchoolId">
                    </label>
                </div>
                <div class="form-row" id="transfereeFieldsBlock" hidden>
                    <label>School Type
                        <select name="school_type" id="fSchoolType">
                            <option value="">Select</option>
                            <option value="Public">Public</option>
                            <option value="Private">Private</option>
                        </select>
                    </label>
                    <label>Reason for Transfer *
                        <select name="reason_for_transfer" id="fReasonTransfer">
                            <option value="">Select</option>
                            <?php foreach (TRANSFER_REASONS as $reason): ?>
                                <option value="<?php echo clean($reason); ?>"><?php echo clean($reason); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="form-row" id="balikAralReturnInfoBlock" hidden>
                    <label>Years Not Enrolled *
                        <select name="years_not_enrolled" id="fYearsNotEnrolled">
                            <option value="">Select</option>
                            <?php foreach (YEARS_NOT_ENROLLED_OPTIONS as $years): ?>
                                <option value="<?php echo clean($years); ?>"><?php echo clean($years); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Reason for Returning *
                        <select name="reason_for_returning" id="fReasonReturning">
                            <option value="">Select</option>
                            <?php foreach (RETURN_REASONS as $reason): ?>
                                <option value="<?php echo clean($reason); ?>"><?php echo clean($reason); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="form-row" id="balikAralManualBlock" hidden>
                    <label>Last Grade Completed *
                        <input type="text" name="last_grade_completed" id="fLastGradeCompleted" placeholder="e.g. Grade 9">
                    </label>
                    <label>Last School Year Attended *
                        <input type="text" name="last_school_year_attended" id="fLastSchoolYearAttended" placeholder="e.g. 2021-2022">
                    </label>
                </div>
                <div class="wizard-nav">
                    <button type="button" class="btn-link wizard-back" data-back="type-or-lookup">&larr; Back</button>
                    <button type="button" class="btn-cta wizard-next" data-next="academic">Continue</button>
                </div>
            </div>

            <!-- Step: Academic Information -->
            <div class="wizard-panel" id="stepAcademic" data-wizard-panel="academic" hidden>
                <h3 data-i18n="enroll.academic_info" id="stepAcademicHeading"><?php echo clean(t('enroll.academic_info')); ?></h3>
                <div class="form-row" id="newStudentGradeBlock" hidden>
                    <label>Grade Level<input type="text" value="Grade 7" readonly></label>
                </div>
                <div class="form-row" id="oldStudentGradeBlock" hidden>
                    <label>Current Grade<input type="text" id="currentGradeDisplay" readonly></label>
                    <label>Suggested Next Grade<input type="text" id="nextGradeDisplay" readonly></label>
                </div>
                <div class="form-row" id="freeGradeBlock" hidden>
                    <label>Grade Level *
                        <select id="freeGradeLevelId" name="grade_level_id">
                            <option value="">Select Grade Level</option>
                            <?php foreach ($gradeLevels as $g): ?>
                                <option value="<?php echo $g['grade_level_id']; ?>" data-type="<?php echo clean($g['level_type']); ?>"><?php echo clean($g['grade_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="form-row" id="strandField" hidden>
                    <label>Strand *
                        <select name="strand_id" id="strand_id">
                            <option value="">Select Strand</option>
                            <?php foreach ($strands as $s): ?>
                                <option value="<?php echo $s['strand_id']; ?>"><?php echo clean($s['strand_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <!-- ALS Educational Background (2026-08i) - this step's h3
                     is relabeled to "Educational Background" by JS for ALS. -->
                <div class="form-row" id="alsEducationBlock" hidden>
                    <label class="full">Highest Educational Level Completed *
                        <select name="highest_education_completed" id="fAlsHighestEducation">
                            <option value="">Select</option>
                            <?php foreach (HIGHEST_EDUCATION_OPTIONS as $opt): ?>
                                <option value="<?php echo clean($opt); ?>"><?php echo clean($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Last Grade Level Attended
                        <input type="text" name="als_last_grade_completed" id="fAlsLastGrade" placeholder="e.g. Grade 6">
                    </label>
                    <label>Last Year Attended
                        <input type="text" name="als_last_school_year_attended" id="fAlsLastYear" placeholder="e.g. 2019-2020">
                    </label>
                    <label>Reason for Leaving School *
                        <select id="fAlsReasonLeaving" name="reason_for_leaving_als">
                            <option value="">Select</option>
                            <?php foreach (REASON_FOR_LEAVING_OPTIONS as $reason): ?>
                                <option value="<?php echo clean($reason); ?>"><?php echo clean($reason); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full" id="alsReasonLeavingOtherWrap" hidden>Please specify
                        <input type="text" name="reason_for_leaving_als_other" id="fAlsReasonLeavingOther">
                    </label>
                </div>
                <div class="wizard-nav">
                    <button type="button" class="btn-link wizard-back" data-back="personal">&larr; Back</button>
                    <button type="button" class="btn-cta wizard-next" data-next="parent">Continue</button>
                </div>
            </div>

            <!-- Step: Parent / Guardian Information (ALS: relabeled to
                 "Learning Profile" by JS - no guardian fields for ALS,
                 see guardianFieldsBlock / alsLearningProfileBlock below) -->
            <div class="wizard-panel" id="stepParent" data-wizard-panel="parent" hidden>
                <h3 data-i18n="enroll.guardian_info" id="stepParentHeading"><?php echo clean(t('enroll.guardian_info')); ?></h3>
                <div class="form-row" id="guardianFieldsBlock">
                    <label><span data-i18n="enroll.guardian_name"><?php echo clean(t('enroll.guardian_name')); ?></span> *<input type="text" name="guardian_name" id="fGuardianName" required></label>
                    <label>Relationship
                        <select name="guardian_relationship" id="fGuardianRelationship">
                            <option value="">Select</option>
                            <?php foreach (GUARDIAN_RELATIONSHIPS as $rel): ?>
                                <option value="<?php echo clean($rel); ?>"><?php echo clean($rel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span data-i18n="enroll.guardian_contact"><?php echo clean(t('enroll.guardian_contact')); ?></span> *<input type="text" name="guardian_contact" id="fGuardianContact" required></label>
                    <label>Occupation<input type="text" name="guardian_occupation" id="fGuardianOccupation"></label>
                    <label class="full">Emergency Contact<input type="text" name="guardian_emergency_contact" id="fGuardianEmergency"></label>
                </div>
                <div class="form-row" id="alsLearningProfileBlock" hidden>
                    <label>Preferred Learning Arrangement *
                        <select name="learning_preference" id="fAlsLearningPreference">
                            <option value="">Select</option>
                            <?php foreach (LEARNING_PREFERENCE_OPTIONS as $opt): ?>
                                <option value="<?php echo clean($opt); ?>"><?php echo clean($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Current Employment Status *
                        <select name="employment_status" id="fAlsEmploymentStatus">
                            <option value="">Select</option>
                            <?php foreach (EMPLOYMENT_STATUS_OPTIONS as $opt): ?>
                                <option value="<?php echo clean($opt); ?>"><?php echo clean($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Learning Goal *
                        <select name="learning_goal" id="fAlsLearningGoal">
                            <option value="">Select</option>
                            <?php foreach (LEARNING_GOAL_OPTIONS as $opt): ?>
                                <option value="<?php echo clean($opt); ?>"><?php echo clean($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full" id="alsLearningGoalOtherWrap" hidden>Please specify
                        <input type="text" name="learning_goal_other" id="fAlsLearningGoalOther">
                    </label>
                    <label class="full">Reason for Enrolling in ALS
                        <textarea name="reason_for_enrolling_als" id="fAlsReasonEnrolling" rows="3"></textarea>
                    </label>
                </div>
                <div class="wizard-nav">
                    <button type="button" class="btn-link wizard-back" data-back="academic">&larr; Back</button>
                    <button type="button" class="btn-cta wizard-next" data-next="requirements">Continue</button>
                </div>
            </div>

            <!-- Step: Requirements Upload -->
            <div class="wizard-panel" id="stepRequirements" data-wizard-panel="requirements" hidden>
                <h3 data-i18n="enroll.upload_documents"><?php echo clean(t('enroll.upload_documents')); ?></h3>
                <?php
                    // Requirements per applicant type are admin-configurable
                    // (admin/requirements.php -> requirement_configs table,
                    // 2026-08h) rather than hardcoded here. All four blocks
                    // render every time (only the active one is unhidden by
                    // JS) so DOC_FIELD_SUFFIX_BY_TYPE-suffixed field names
                    // never collide - see config/constants.php.
                    $docBlocksByType = [
                        'newStudentDocsBlock'   => 'New',
                        'oldStudentDocsBlock'   => 'Returning',
                        'transfereeDocsBlock'   => 'Transferee',
                        'balikAralDocsBlock'    => 'Balik-Aral',
                        'alsDocsBlock'          => 'ALS',
                    ];
                ?>
                <?php foreach ($docBlocksByType as $blockId => $blockType): ?>
                    <?php
                        $blockRequired = required_documents_for_type($pdo, $blockType);
                        $blockOptional = optional_documents_for_type($pdo, $blockType);
                        $blockSuffix = DOC_FIELD_SUFFIX_BY_TYPE[$blockType] ?? '';
                    ?>
                    <div id="<?php echo $blockId; ?>" hidden>
                        <?php if (!$blockRequired && !$blockOptional): ?>
                            <p class="muted">No documents required &mdash; your records are already on file with the registrar.</p>
                        <?php else: ?>
                            <p class="muted">Accepted formats: JPG, PNG, PDF. Maximum file size: 5MB each.</p>
                            <div class="form-row">
                                <?php foreach ($blockRequired as $docKey => $docLabel): ?>
                                    <label><?php echo clean($docLabel); ?> *
                                        <input type="file" name="<?php echo clean($docKey . $blockSuffix); ?>" accept=".jpg,.jpeg,.png,.pdf" data-required="1">
                                    </label>
                                <?php endforeach; ?>
                                <?php foreach ($blockOptional as $docKey => $docLabel): ?>
                                    <label><?php echo clean($docLabel); ?> (optional)
                                        <input type="file" name="<?php echo clean($docKey . $blockSuffix); ?>" accept=".jpg,.jpeg,.png,.pdf">
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="wizard-nav">
                    <button type="button" class="btn-link wizard-back" data-back="parent">&larr; Back</button>
                    <button type="button" class="btn-cta wizard-next" data-next="review">Continue</button>
                </div>
            </div>

            <!-- Step: Review -->
            <div class="wizard-panel" id="stepReview" data-wizard-panel="review" hidden>
                <h3>Review Your Application</h3>
                <dl class="review-summary" id="reviewSummary"></dl>
                <div class="wizard-nav">
                    <button type="button" class="btn-link wizard-back" data-back="requirements">&larr; Back</button>
                    <button type="submit" class="btn-cta" id="finalSubmitBtn" data-i18n="enroll.submit_btn"><?php echo clean(t('enroll.submit_btn')); ?></button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</section>
<script src="<?php echo BASE_URL; ?>assets/js/validation.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/student.js"></script>
<script>window.SABNAHIS_BASE_URL = window.SABNAHIS_BASE_URL || <?php echo json_encode(BASE_URL); ?>;</script>
<script src="<?php echo BASE_URL; ?>assets/js/enrollment_wizard.js"></script>
<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
