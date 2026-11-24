<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// About SABNAHIS module (2026-08) - AJAX endpoint for the Photo Gallery's
// drag-and-drop reordering (assets/js/about_gallery_admin.js). Accepts a
// JSON body {"ids": [gallery_id, gallery_id, ...]} in the new left-to-right
// / top-to-bottom order and rewrites display_order to match.
header('Content-Type: application/json');

if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only the Administrator can reorder the gallery.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$ids = array_map('intval', $body['ids'] ?? []);
$ids = array_values(array_filter($ids, fn($v) => $v > 0));

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No photo order provided.']);
    exit;
}

$pdo->beginTransaction();
try {
    foreach ($ids as $order => $id) {
        $pdo->prepare('UPDATE about_gallery SET display_order = ? WHERE gallery_id = ?')->execute([$order + 1, $id]);
    }
    audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_gallery_reorder', count($ids) . ' photo(s) reordered');
    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save new order.']);
}
