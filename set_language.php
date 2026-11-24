<?php
/**
 * Lightweight JSON endpoint backing the language switcher (see
 * assets/js/i18n.js). Called via fetch() whenever a visitor picks a
 * language from the dropdown - persists the choice server-side so it
 * survives across pages/visits without requiring a page reload:
 *   - $_SESSION['lang']         (this request onward)
 *   - sabnahis_lang cookie      (future visits, anonymous or logged in)
 *   - students.preferred_language (if a student is currently logged in)
 *
 * The Student Portal runs on its own, separately-named session
 * ("sabnahis_student" - see student/includes/auth_check.php) so a student
 * session can never be read as an admin session or vice versa. This
 * endpoint is shared by every context (public site, admin, student
 * portal), so it must resume whichever named session the calling page is
 * actually using *before* anything else touches $_SESSION - detected here
 * by checking which session cookie the browser sent.
 *
 * POST set_language.php  body: lang=<code>  -> {"success":bool,"lang":"..."}
 */
if (isset($_COOKIE['sabnahis_student'])) {
    session_name('sabnahis_student');
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$code = trim($_POST['lang'] ?? '');

if (!array_key_exists($code, SUPPORTED_LANGUAGES)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported language.']);
    exit;
}

set_current_language($code);

// If a student is logged in (session started by set_current_language()
// above), also save the choice to their profile so it follows them across
// devices - anonymous visitors still get persistence via the cookie set
// above.
if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare('UPDATE students SET preferred_language = ? WHERE student_id = ?');
    $stmt->execute([$code, $_SESSION['student_id']]);
}

echo json_encode(['success' => true, 'lang' => $code]);
