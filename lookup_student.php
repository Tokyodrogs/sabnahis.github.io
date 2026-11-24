<?php
/**
 * Lightweight JSON endpoint backing the Old Student / Balik-Aral
 * re-enrollment lookup step on the enrollment wizard. Requires BOTH the
 * LRN/Student Number AND the applicant's birthdate to match before
 * returning any personal data - see
 * includes/functions.php::find_student_for_reenrollment() for why. Also
 * rate-limited per session (5 failed attempts -> temporary lockout) since
 * a birthdate alone has limited entropy and shouldn't be brute-forceable
 * against a known/guessed LRN.
 *
 * POST lookup_student.php  body: identifier=...&birthdate=YYYY-MM-DD
 *   -> {"found":bool,"locked":bool,"already_enrolled":bool,"student":{...}}
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

define('LOOKUP_MAX_ATTEMPTS', 5);
define('LOOKUP_LOCKOUT_SECONDS', 15 * 60);

if (!empty($_SESSION['reenroll_lookup_locked_until']) && time() < $_SESSION['reenroll_lookup_locked_until']) {
    echo json_encode(['found' => false, 'locked' => true, 'message' => 'Too many attempts. Please try again later or contact the registrar.']);
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$birthdate  = trim($_POST['birthdate'] ?? '');

if ($identifier === '' || $birthdate === '') {
    echo json_encode(['found' => false, 'locked' => false, 'message' => 'Please enter your LRN or Student Number and birthdate.']);
    exit;
}

$student = find_student_for_reenrollment($pdo, $identifier, $birthdate);

if (!$student) {
    $_SESSION['reenroll_lookup_attempts'] = ($_SESSION['reenroll_lookup_attempts'] ?? 0) + 1;
    if ($_SESSION['reenroll_lookup_attempts'] >= LOOKUP_MAX_ATTEMPTS) {
        $_SESSION['reenroll_lookup_locked_until'] = time() + LOOKUP_LOCKOUT_SECONDS;
        $_SESSION['reenroll_lookup_attempts'] = 0;
        echo json_encode(['found' => false, 'locked' => true, 'message' => 'Too many attempts. Please try again later or contact the registrar.']);
        exit;
    }
    echo json_encode(['found' => false, 'locked' => false, 'message' => 'No matching record found. Please check your LRN/Student Number and birthdate, or contact the registrar.']);
    exit;
}

unset($_SESSION['reenroll_lookup_attempts'], $_SESSION['reenroll_lookup_locked_until']);

// Block re-submission if this student already has an application on file
// for the current school year (spec: "Prevent duplicate enrollment for
// the same School Year").
$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
if ($currentSY && (int) $student['sy_id'] === (int) $currentSY['sy_id']) {
    echo json_encode([
        'found' => true,
        'locked' => false,
        'already_enrolled' => true,
        'message' => 'You already have an enrollment application on file for the ' . $currentSY['school_year'] . ' school year. Please check your Student Portal for its status, or contact the registrar.',
    ]);
    exit;
}

$nextGrade = suggest_next_grade_level($pdo, $student['grade_level_id']);

// Remember which student this browser session is allowed to re-enroll as
// - the same trust pattern already used for the post-submission portal
// signup step elsewhere in this project. The actual submission only
// trusts this session value, never a posted student_id, so the chosen
// identifier can't be swapped for someone else's record between lookup
// and submit.
$_SESSION['reenroll_student_id'] = (int) $student['student_id'];

echo json_encode([
    'found' => true,
    'locked' => false,
    'already_enrolled' => false,
    'student' => [
        'student_id'          => (int) $student['student_id'],
        'lrn'                  => $student['lrn'],
        'enrollment_no'        => $student['enrollment_no'],
        'lastname'             => $student['lastname'],
        'firstname'            => $student['firstname'],
        'middlename'           => $student['middlename'],
        'extension'            => $student['extension'],
        'sex'                  => $student['sex'],
        'birthdate'            => $student['birthdate'],
        'address'              => $student['address'],
        'contact'              => $student['contact'],
        'email'                => $student['email'],
        'guardian_name'        => $student['guardian_name'],
        'guardian_contact'     => $student['guardian_contact'],
        'current_grade'        => $student['grade_name'],
        'current_strand'       => $student['strand_name'],
        'current_section'      => $student['section_name'],
        'current_sy'           => $student['school_year'],
        'photo_url'            => student_photo_url($student['photo'] ?? null),
        'next_grade_level_id'  => $nextGrade['grade_level_id'] ?? null,
        'next_grade_name'      => $nextGrade['grade_name'] ?? null,
        'next_grade_type'      => $nextGrade['level_type'] ?? null,
    ],
]);
