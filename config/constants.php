<?php
/**
 * Site-wide constants
 */

define('SITE_NAME', 'Sablayan National Comprehensive High School');
define('SITE_SHORT_NAME', 'SABNAHIS');

// Base URL path - adjust if the project folder name inside htdocs changes
define('BASE_URL', '/sabnahis/');

// Absolute filesystem path to the uploads folder
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Allowed upload file extensions and max size (5 MB)
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

define('REQUIRED_DOCUMENTS', [
    'psa_birth_cert' => 'PSA Birth Certificate',
    'report_card'    => 'Report Card',
    'good_moral'     => 'Good Moral Certificate',
    'photo_2x2'      => '2x2 Picture',
]);

// ---------------------------------------------------------------------
// Learner Reference Number (LRN)
// ---------------------------------------------------------------------
define('LRN_LENGTH', 12);

// ---------------------------------------------------------------------
// Student profile photo
// ---------------------------------------------------------------------
define('PHOTO_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);
define('PHOTO_ALLOWED_MIMES', ['image/jpeg', 'image/png']);
define('PHOTO_MAX_SIZE', 5 * 1024 * 1024); // 5 MB
define('PHOTO_MAX_DIMENSION', 600); // px, longest side after auto-resize
define('PHOTO_JPEG_QUALITY', 85);
define('PHOTO_UPLOAD_SUBDIR', 'students/'); // relative to UPLOAD_DIR

// ---------------------------------------------------------------------
// Student Portal
// ---------------------------------------------------------------------
// Temporary password assigned to every auto-created student account.
// Students are forced into student/change_password.php on first login
// (student_accounts.first_login = 1) before they can use the portal.
define('TEMP_STUDENT_PASSWORD', 'Welcome2026!');

// Password policy applied on change_password.php (min length + at least
// one of each character class). Checked with validate_password_policy()
// in includes/functions.php.
define('PASSWORD_MIN_LENGTH', 8);

// Failed login attempts before student_accounts.status is flipped to 'Locked'.
define('STUDENT_MAX_FAILED_ATTEMPTS', 5);

// Idle session timeout for the Student Portal (seconds) - mirrors the
// admin panel's 30-minute timeout in admin/includes/auth_check.php.
define('STUDENT_SESSION_TIMEOUT', 1800);

// "Remember me" cookie lifetime for the Student Portal login.
define('STUDENT_REMEMBER_DAYS', 14);

// Document types manageable from the Student Portal's Documents module.
// Superset of REQUIRED_DOCUMENTS above (which only covers what the public
// online enrollment form collects up front); students can additionally
// upload these once they have portal access. 'report_card' is reused for
// both since Form 138 IS the DepEd report card.
define('STUDENT_DOCUMENT_TYPES', [
    'psa_birth_cert'      => 'PSA Birth Certificate',
    'report_card'         => 'Form 138 / Report Card',
    'good_moral'          => 'Good Moral Certificate',
    'photo_2x2'           => '2x2 Picture',
    'medical_certificate' => 'Medical Certificate',
    'other_document'      => 'Other Required Document',
]);

// Ordered enrollment workflow stages shown by the Student Portal's
// Enrollment Status progress tracker. `students.status` only has 4 values
// (Pending/Approved/Rejected/Enrolled); we map those onto this
// display-only 3-stage tracker in student/enrollment_status.php. Index
// positions here (0-2) must match includes/functions.php::compute_enrollment_progress().
// (2026-08g: was 6 stages including Assessment Completed/Payment Pending/
// Payment Verified - dropped along with the rest of the fee/payment UI,
// see compute_enrollment_progress()'s doc comment.)
define('ENROLLMENT_STAGES', [
    'Application Submitted',   // 0
    'Documents Verified',      // 1
    'Officially Enrolled',     // 2
]);

// ---------------------------------------------------------------------
// Language / i18n (2026-08: language switcher)
// ---------------------------------------------------------------------
// Supported UI languages for the language switcher in Website Settings.
// Order here is the display order in the dropdown. Each dictionary lives
// at lang/{code}.json - see includes/functions.php::t() and
// get_current_language()/set_current_language() for the resolution and
// translation logic, and assets/js/i18n.js for the client-side live
// switcher. Add a new language by adding a lang/{code}.json file and an
// entry here - no other code changes required.
define('SUPPORTED_LANGUAGES', [
    'fil' => ['name' => 'Filipino', 'native_name' => 'Filipino', 'flag' => '🇵🇭'],
    'en'  => ['name' => 'English',  'native_name' => 'English',  'flag' => '🇺🇸'],
    'zh'  => ['name' => 'Chinese (Simplified)', 'native_name' => '简体中文', 'flag' => '🇨🇳'],
    'ja'  => ['name' => 'Japanese', 'native_name' => '日本語',    'flag' => '🇯🇵'],
    'ko'  => ['name' => 'Korean',   'native_name' => '한국어',    'flag' => '🇰🇷'],
]);

// Default language for first-time visitors whose browser language isn't
// English (see get_current_language() in includes/functions.php).
define('DEFAULT_LANGUAGE', 'fil');

// ---------------------------------------------------------------------
// Online Enrollment: Applicant Types (2026-08d: multi-applicant wizard)
// ---------------------------------------------------------------------
// Reuses the existing students.student_type column as the single source
// of truth (already used throughout admin/*.php) instead of adding a
// parallel "applicant_type" column. Keyed by that column's actual enum
// value so the wizard's selection maps directly onto it with no
// translation layer - see migration_2026_08d_multi_applicant_enrollment.sql.
//
// `enabled` gates whether a type is selectable on the public enrollment
// wizard (enroll.php). All four are wired up as of 2026-08e.
define('APPLICANT_TYPES', [
    'New' => [
        'label'       => 'New Student',
        'subtitle'    => '(Incoming Grade 7)',
        'description' => 'For learners enrolling in SABNAHIS for the first time.',
        'icon'        => '🆕',
        'enabled'     => true,
    ],
    'Returning' => [
        'label'       => 'Old Student',
        'subtitle'    => '(Continuing Student)',
        'description' => 'Previously enrolled in SABNAHIS and continuing to the next grade level.',
        'icon'        => '🎓',
        'enabled'     => true,
    ],
    'Transferee' => [
        'label'       => 'Transferee',
        'subtitle'    => '',
        'description' => 'A student transferring from another school.',
        'icon'        => '🏫',
        'enabled'     => true,
    ],
    'Balik-Aral' => [
        'label'       => 'Balik-Aral',
        'subtitle'    => '',
        'description' => 'A learner returning to school after stopping for one or more school years.',
        'icon'        => '🔄',
        'enabled'     => true,
    ],
    // 2026-08i: ALS (Alternative Learning System), Phase 1. Reuses this
    // same students.student_type enum value ('ALS') as the other four
    // types - see migration_2026_08i_als_enrollment.sql.
    'ALS' => [
        'label'       => 'ALS',
        'subtitle'    => '(Alternative Learning System)',
        'description' => 'For out-of-school youth, adult learners, former students, and ALS completers who want to enroll in ALS or transition to formal education.',
        'icon'        => '📚',
        'enabled'     => true,
    ],
]);

// ALS enrollment pathways (2026-08i). Shown on a sub-step right after the
// ALS applicant card, before Personal Info. Originally had two more
// entries ('als_completer_formal_transition', 'former_formal_learner_to_als')
// rendered as disabled "Coming Soon" cards - removed in 2026-08j at the
// user's request rather than left disabled, since the ALS Completer /
// Former Formal Learner workflows (academic placement, A&E completion
// documents, assessment/placement evaluation) aren't being built as a
// near-term follow-up. Structured as an array (not a single constant) so
// re-adding a pathway later is just adding an entry back, same as before.
define('ALS_PATHWAYS', [
    'als_program' => [
        'label'       => 'Enroll in ALS Program',
        'description' => 'For out-of-school children, youth, and adult learners who want to enroll directly in the Alternative Learning System (ALS).',
        'enabled'     => true,
    ],
]);

// Dropdown options for the ALS Educational Background / Learning Profile
// steps (2026-08i).
define('HIGHEST_EDUCATION_OPTIONS', [
    'No Formal Schooling', 'Basic Literacy', 'Elementary - Not Completed', 'Elementary Graduate',
    'Junior High School - Not Completed', 'Junior High School Graduate',
    'Senior High School - Not Completed', 'Senior High School Graduate', 'ALS Learner', 'Other',
]);

define('REASON_FOR_LEAVING_OPTIONS', [
    'Financial Difficulties', 'Employment / Work', 'Family Responsibilities', 'Relocation',
    'Distance / Transportation', 'Health / Personal Reasons', 'Early Marriage / Parenthood',
    'Disability / Special Circumstances', 'Other',
]);

define('LEARNING_PREFERENCE_OPTIONS', [
    'Face-to-Face', 'Modular', 'Blended Learning', 'Other / Subject to Availability',
]);

define('EMPLOYMENT_STATUS_OPTIONS', [
    'Employed', 'Unemployed', 'Student', 'Self-Employed', 'Other',
]);

define('LEARNING_GOAL_OPTIONS', [
    'Improve Basic Literacy', 'Complete Elementary Level', 'Complete Junior High School Level',
    'Prepare for A&E Accreditation and Equivalency', 'Continue to Formal Education',
    'Personal Development', 'Employment / Career', 'Other',
]);

// Document field names on the enrollment wizard are suffixed per
// applicant type (e.g. name="psa_birth_cert_t" for Transferee) because
// several types share the same document KEYS (psa_birth_cert, report_card,
// etc.) but the wizard renders one <input type="file"> block per type in
// the DOM at once (only the active one is unhidden) - without a suffix,
// the form would contain multiple file inputs with the same `name`
// attribute, which is invalid HTML with unpredictable submission
// behavior. The suffix is stripped back off before the doc type is stored
// in documents.doc_type, so Document Verification still sees the plain,
// consistent keys regardless of which type uploaded them.
//
// 'Returning' got its own suffix in 2026-08h: requirements are now
// admin-configurable (admin/requirements.php), and 'Returning' shipped
// with zero seeded requirements, so it was previously safe to leave
// unsuffixed (no inputs were ever rendered for it). If an admin adds a
// requirement to 'Returning' with the same doc_key as a New Student
// requirement (e.g. both using "psa_birth_cert"), an empty suffix would
// have produced two <input name="psa_birth_cert"> elements in the same
// form - the exact collision this table exists to prevent.
define('DOC_FIELD_SUFFIX_BY_TYPE', [
    'New'        => '',
    'Returning'  => '_o',
    'Transferee' => '_t',
    'Balik-Aral' => '_b',
    'ALS'        => '_a',
]);

// Required documents per applicant type, keyed the same way as
// REQUIRED_DOCUMENTS above (documents.doc_type is free-text, so new keys
// like 'form_137' need no schema change). "Old Student" intentionally has
// no required uploads - their documents are already on file from their
// original enrollment (see spec: "Do NOT require uploading previous
// requirements again").
define('REQUIRED_DOCUMENTS_BY_TYPE', [
    'New' => [
        'psa_birth_cert' => 'PSA Birth Certificate',
        'report_card'    => 'Form 138',
        'good_moral'     => 'Good Moral Certificate',
        'photo_2x2'      => '2x2 Picture',
    ],
    'Returning' => [],
    'Transferee' => [
        'psa_birth_cert' => 'PSA Birth Certificate',
        'form_137'       => 'Form 137',
        'report_card'    => 'Form 138',
        'good_moral'     => 'Good Moral Certificate',
        'photo_2x2'      => '2x2 Picture',
    ],
    'Balik-Aral' => [
        'psa_birth_cert' => 'PSA Birth Certificate',
        'report_card'    => 'Form 138',
        'good_moral'     => 'Good Moral Certificate',
        'photo_2x2'      => '2x2 Picture',
    ],
    // Fallback only - the live source of truth is requirement_configs
    // (seeded by migration_2026_08i_als_enrollment.sql). Kept in sync so
    // document_requirements_for_type()'s PDOException fallback still works
    // pre-migration.
    'ALS' => [
        'psa_birth_cert' => 'Proof of Identity / Birth Record',
        'photo_2x2'      => 'Recent 2x2 ID Picture',
    ],
]);

// Optional (not required) documents per type - shown as an extra,
// skippable upload slot.
define('OPTIONAL_DOCUMENTS_BY_TYPE', [
    'New'        => ['completion_cert' => 'Certificate of Completion'],
    'Returning'  => [],
    'Transferee' => [],
    'Balik-Aral' => ['form_137' => 'Form 137 (if applicable)'],
    'ALS'        => ['report_card' => 'Form 137 / School Record (if applicable)'],
]);

define('TRANSFER_REASONS', [
    'Family Relocation', 'Change of Residence', 'Academic Reasons', 'Personal Reasons', 'Others',
]);

define('RETURN_REASONS', [
    'Financial Difficulty', 'Health Condition', 'Family Problems', 'Family Relocation',
    'Employment', 'Pandemic', 'Personal Reasons', 'Others',
]);

define('YEARS_NOT_ENROLLED_OPTIONS', ['1 Year', '2 Years', '3 Years', '4 Years', '5+ Years']);

define('GUARDIAN_RELATIONSHIPS', ['Mother', 'Father', 'Legal Guardian', 'Grandparent', 'Sibling', 'Other']);
