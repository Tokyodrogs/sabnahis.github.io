<?php
/**
 * Requirements Management (2026-08h). Lets an Administrator configure
 * which documents each applicant type must (or may optionally) upload on
 * the public enrollment wizard, without touching code - see
 * database/migration_2026_08h_requirements_config.sql and
 * includes/functions.php::required_documents_for_type()/
 * optional_documents_for_type(), both of which read from the
 * requirement_configs table this page manages.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// System-configuration data - Administrator-only (see strands.php header
// comment for the same reasoning).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'Requirements are managed by the Administrator.');
    redirect('dashboard.php');
}

$pageTitle = 'Requirements Management';
$errors = [];
$editRow = null;

$validTypes = array_keys(APPLICANT_TYPES); // New, Returning, Transferee, Balik-Aral
$activeType = $_GET['type'] ?? 'New';
if (!in_array($activeType, $validTypes, true)) $activeType = 'New';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $postedType = $_POST['applicant_type'] ?? '';
        if (!in_array($postedType, $validTypes, true)) $postedType = 'New';
        $activeType = $postedType;

        $label = trim($_POST['doc_label'] ?? '');
        $key = strtolower(trim($_POST['doc_key'] ?? ''));
        $isRequired = !empty($_POST['is_required']) ? 1 : 0;
        $order = (int) ($_POST['display_order'] ?? 0);

        if ($label === '') {
            $errors[] = 'Document label is required.';
        } elseif ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = 'Document key must contain only lowercase letters, numbers, and underscores (e.g. "psa_birth_cert").';
        } else {
            if ($action === 'add') {
                $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM requirement_configs WHERE applicant_type = ? AND doc_key = ?');
                $dupStmt->execute([$postedType, $key]);
                if ($dupStmt->fetchColumn() > 0) {
                    $errors[] = 'A requirement with that document key already exists for this applicant type.';
                } else {
                    $pdo->prepare('INSERT INTO requirement_configs (applicant_type, doc_key, doc_label, is_required, display_order) VALUES (?,?,?,?,?)')
                        ->execute([$postedType, $key, $label, $isRequired, $order]);
                    flash('success', 'Requirement added.');
                    redirect('requirements.php?type=' . urlencode($postedType));
                }
            } else {
                $id = (int) ($_POST['requirement_id'] ?? 0);
                $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM requirement_configs WHERE applicant_type = ? AND doc_key = ? AND requirement_id != ?');
                $dupStmt->execute([$postedType, $key, $id]);
                if ($dupStmt->fetchColumn() > 0) {
                    $errors[] = 'A requirement with that document key already exists for this applicant type.';
                } else {
                    $pdo->prepare('UPDATE requirement_configs SET doc_key = ?, doc_label = ?, is_required = ?, display_order = ? WHERE requirement_id = ?')
                        ->execute([$key, $label, $isRequired, $order, $id]);
                    flash('success', 'Requirement updated.');
                    redirect('requirements.php?type=' . urlencode($postedType));
                }
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int) ($_POST['requirement_id'] ?? 0);
        $pdo->prepare('UPDATE requirement_configs SET is_active = 1 - is_active WHERE requirement_id = ?')->execute([$id]);
        flash('success', 'Requirement status updated.');
        redirect('requirements.php?type=' . urlencode($activeType));
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['requirement_id'] ?? 0);
        $pdo->prepare('DELETE FROM requirement_configs WHERE requirement_id = ?')->execute([$id]);
        flash('success', 'Requirement deleted.');
        redirect('requirements.php?type=' . urlencode($activeType));
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM requirement_configs WHERE requirement_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editRow = $stmt->fetch();
    if ($editRow) $activeType = $editRow['applicant_type'];
}

$stmt = $pdo->prepare('SELECT * FROM requirement_configs WHERE applicant_type = ? ORDER BY is_required DESC, display_order ASC, requirement_id ASC');
$stmt->execute([$activeType]);
$rows = $stmt->fetchAll();

$nextOrder = 1;
foreach ($rows as $r) { if ($r['display_order'] >= $nextOrder) $nextOrder = $r['display_order'] + 1; }

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Requirements Management</h2>
    <p class="muted">Controls the documents each applicant type must (or may optionally) upload on Step 5 of the
        public online enrollment wizard. Changes here take effect immediately - no code changes needed.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <div class="about-tabs">
        <?php foreach ($validTypes as $t): ?>
            <a href="?type=<?php echo urlencode($t); ?>" class="<?php echo $activeType === $t ? 'active' : ''; ?>"><?php echo clean(APPLICANT_TYPES[$t]['label']); ?></a>
        <?php endforeach; ?>
    </div>

    <section class="panel">
        <h3><?php echo $editRow ? 'Edit Requirement' : 'Add Requirement'; ?> &mdash; <?php echo clean(APPLICANT_TYPES[$activeType]['label']); ?></h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="<?php echo $editRow ? 'update' : 'add'; ?>">
            <input type="hidden" name="applicant_type" value="<?php echo clean($activeType); ?>">
            <?php if ($editRow): ?><input type="hidden" name="requirement_id" value="<?php echo $editRow['requirement_id']; ?>"><?php endif; ?>
            <div class="form-row">
                <label>Document Label *
                    <input type="text" name="doc_label" placeholder="e.g. PSA Birth Certificate" value="<?php echo clean($editRow['doc_label'] ?? ''); ?>" required>
                </label>
                <label>Document Key *
                    <input type="text" name="doc_key" placeholder="e.g. psa_birth_cert" pattern="[a-z0-9_]+"
                           value="<?php echo clean($editRow['doc_key'] ?? ''); ?>" required>
                    <small class="muted">Lowercase letters, numbers, underscores only. Used internally to match uploaded files - avoid changing it after applicants start using it.</small>
                </label>
                <label>Display Order
                    <input type="number" name="display_order" value="<?php echo $editRow['display_order'] ?? $nextOrder; ?>" min="0">
                </label>
            </div>
            <div class="form-row">
                <label class="checkbox-label"><input type="checkbox" name="is_required" value="1" <?php echo ($editRow['is_required'] ?? 1) ? 'checked' : ''; ?>> Required (unchecked = optional)</label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><?php echo $editRow ? 'Save Changes' : 'Add Requirement'; ?></button>
                <?php if ($editRow): ?><a href="requirements.php?type=<?php echo urlencode($activeType); ?>" class="btn-link">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>Current Requirements &mdash; <?php echo clean(APPLICANT_TYPES[$activeType]['label']); ?></h3>
        <?php if (!$rows): ?>
            <p class="muted">No requirements configured for this applicant type &mdash; the Requirements step of the
                enrollment wizard will show "No documents required" for it.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Order</th><th>Label</th><th>Key</th><th>Required?</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr<?php echo empty($r['is_active']) ? ' class="row-inactive"' : ''; ?>>
                        <td><?php echo (int) $r['display_order']; ?></td>
                        <td><?php echo clean($r['doc_label']); ?></td>
                        <td><code><?php echo clean($r['doc_key']); ?></code></td>
                        <td><?php echo $r['is_required'] ? '<span class="badge badge-pending">Required</span>' : '<span class="badge badge-normal">Optional</span>'; ?></td>
                        <td><?php echo $r['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Disabled</span>'; ?></td>
                        <td class="actions">
                            <a href="?type=<?php echo urlencode($activeType); ?>&edit=<?php echo $r['requirement_id']; ?>">Edit</a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="requirement_id" value="<?php echo $r['requirement_id']; ?>">
                                <button type="submit" class="link-primary"><?php echo $r['is_active'] ? 'Disable' : 'Enable'; ?></button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this requirement?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="requirement_id" value="<?php echo $r['requirement_id']; ?>">
                                <button type="submit" class="link-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
