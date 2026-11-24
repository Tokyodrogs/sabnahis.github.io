<?php
/**
 * Lightweight JSON endpoint for live Student Portal username availability
 * checking, used by the "Create Your Student Portal Account" section of
 * enroll.php (assets/js/student.js). Public/unauthenticated by design -
 * this only tells the caller whether a username string is free, the same
 * information a duplicate-username error on submit would reveal anyway.
 *
 * GET check_username.php?username=... -> {"valid":bool,"available":bool,"message":"..."}
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');
$errors = validate_student_username($username);

if ($errors) {
    echo json_encode(['valid' => false, 'available' => false, 'message' => $errors[0]]);
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_accounts WHERE username = ?');
$stmt->execute([$username]);
$taken = $stmt->fetchColumn() > 0;

echo json_encode([
    'valid' => true,
    'available' => !$taken,
    'message' => $taken ? 'Username already exists. Please choose another username.' : 'Username is available',
]);
