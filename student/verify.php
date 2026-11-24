<?php
/**
 * Digital Student ID QR verification page (2026-08n). This is the page a
 * QR code's URL points to - deliberately PUBLIC/unauthenticated (does
 * NOT include student/includes/auth_check.php) since whoever scans the
 * code (a gate guard, a teacher, an office staff member) has no reason to
 * be logged into the Student Portal themselves. Only reachable by knowing
 * the exact random token encoded in a specific student's QR image - there
 * is no listing/search/enumeration endpoint here.
 *
 * Only shows non-sensitive identity/enrollment fields (photo, name,
 * student number, grade & section, school year, enrollment status).
 * Deliberately never selects or displays LRN, guardian info, address,
 * email, or any account credential - see the explicit column list in the
 * query below (never `SELECT s.*`) so that stays true even if this file
 * is edited later.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim($_GET['token'] ?? '');

// Three outcomes: no token at all, token not recognized, or token
// recognized but the ID is inactive/the student isn't currently enrolled.
// 'valid' is the only state that renders student details.
$state = 'invalid_missing';
$student = null;

if ($token === '') {
    $state = 'invalid_missing';
} else {
    $stmt = $pdo->prepare('SELECT s.student_id, s.lastname, s.firstname, s.middlename, s.photo,
            s.status, s.qr_status, g.grade_name, st.strand_name, sec.section_name, sy.school_year,
            sa.username AS student_number
        FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN strands st ON s.strand_id = st.strand_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
        LEFT JOIN student_accounts sa ON sa.student_id = s.student_id
        WHERE s.qr_token = ?
        LIMIT 1');
    $stmt->execute([$token]);
    $student = $stmt->fetch();

    if (!$student) {
        $state = 'invalid_token';
    } elseif ($student['qr_status'] !== 'active') {
        $state = 'inactive';
    } elseif ($student['status'] !== 'Enrolled') {
        $state = 'inactive'; // ID was issued but the student isn't (or is no longer) officially enrolled
    } else {
        $state = 'valid';
    }
}

$fullName = $student ? trim($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']) : '';
$photoUrl = $student ? student_photo_url($student['photo']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student ID Verification - <?php echo SITE_SHORT_NAME; ?></title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #222; }
  .verify-card { max-width: 380px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
  .verify-banner { padding: 20px 20px 16px; text-align: center; color: #fff; }
  .verify-banner.valid { background: #1e5631; }
  .verify-banner.invalid { background: #a71d2a; }
  .verify-banner.inactive { background: #8a6d1d; }
  .verify-icon { font-size: 34px; line-height: 1; }
  .verify-verdict { font-size: 16px; font-weight: 700; letter-spacing: .5px; margin-top: 6px; text-transform: uppercase; }
  .verify-body { padding: 20px; }
  .verify-photo { display: block; width: 96px; height: 112px; object-fit: cover; margin: 0 auto 14px; border: 1px solid #ddd; border-radius: 6px; }
  .verify-photo-placeholder { display: flex; align-items: center; justify-content: center; width: 96px; height: 112px; margin: 0 auto 14px; border: 1px solid #ddd; border-radius: 6px; background: #f4f4f4; color: #888; font-size: 11px; text-align: center; }
  .verify-name { text-align: center; font-size: 17px; font-weight: 700; margin-bottom: 14px; }
  .verify-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .verify-table th, .verify-table td { padding: 7px 4px; border-bottom: 1px solid #eee; text-align: left; }
  .verify-table th { width: 45%; color: #555; font-weight: 600; }
  .verify-message { text-align: center; font-size: 14px; color: #555; padding: 6px 0 2px; }
  .verify-footer { text-align: center; font-size: 11px; color: #999; padding: 14px 20px 18px; }
</style>
</head>
<body>
  <div class="verify-card">
    <?php if ($state === 'valid'): ?>
      <div class="verify-banner valid">
        <div class="verify-icon">&#10003;</div>
        <div class="verify-verdict">Valid Student ID</div>
      </div>
      <div class="verify-body">
        <?php if ($photoUrl): ?>
          <img src="<?php echo clean($photoUrl); ?>" alt="Student Photo" class="verify-photo">
        <?php else: ?>
          <div class="verify-photo-placeholder">No Photo</div>
        <?php endif; ?>
        <div class="verify-name"><?php echo clean($fullName); ?></div>
        <table class="verify-table">
          <tr><th>Student Number</th><td><?php echo clean($student['student_number'] ?? '-'); ?></td></tr>
          <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?><?php if (!empty($student['strand_name'])): ?> &mdash; <?php echo clean($student['strand_name']); ?><?php endif; ?></td></tr>
          <tr><th>Section</th><td><?php echo clean($student['section_name'] ?? 'Not yet assigned'); ?></td></tr>
          <tr><th>School Year</th><td><?php echo clean($student['school_year'] ?? '-'); ?></td></tr>
          <tr><th>Status</th><td>Currently Enrolled</td></tr>
        </table>
      </div>
    <?php elseif ($state === 'inactive'): ?>
      <div class="verify-banner inactive">
        <div class="verify-icon">&#9888;</div>
        <div class="verify-verdict">Inactive Student ID</div>
      </div>
      <div class="verify-body">
        <p class="verify-message">This Digital Student ID is currently inactive and cannot be used for verification. Please refer this student to the school office.</p>
      </div>
    <?php elseif ($state === 'invalid_token'): ?>
      <div class="verify-banner invalid">
        <div class="verify-icon">&#10007;</div>
        <div class="verify-verdict">Invalid Student ID</div>
      </div>
      <div class="verify-body">
        <p class="verify-message">This QR code does not match any student record. It may be outdated, altered, or fraudulent.</p>
      </div>
    <?php else: ?>
      <div class="verify-banner invalid">
        <div class="verify-icon">&#10007;</div>
        <div class="verify-verdict">Invalid QR Code</div>
      </div>
      <div class="verify-body">
        <p class="verify-message">No verification code was provided.</p>
      </div>
    <?php endif; ?>
    <div class="verify-footer"><?php echo SITE_NAME; ?> &mdash; Digital Student ID Verification</div>
  </div>
</body>
</html>
