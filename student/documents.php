<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Documents';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docType = $_POST['doc_type'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'documents')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif (!array_key_exists($docType, STUDENT_DOCUMENT_TYPES)) {
        $errors[] = 'Unknown document type.';
    } else {
        $existingStmt = $pdo->prepare('SELECT * FROM documents WHERE student_id = ? AND doc_type = ?');
        $existingStmt->execute([$_SESSION['student_id'], $docType]);
        $existing = $existingStmt->fetch();

        if ($existing && $existing['status'] === 'Approved') {
            $errors[] = STUDENT_DOCUMENT_TYPES[$docType] . ' has already been approved and cannot be replaced. Contact the registrar if it needs to be updated.';
        } elseif (empty($_FILES['document']['name'])) {
            $errors[] = 'Please choose a file to upload.';
        } else {
            $uploadError = null;
            $savedName = handle_document_upload('document', $_SESSION['student_id'], $docType, $uploadError);
            if ($savedName === false) {
                $errors[] = $uploadError;
            } else {
                if ($existing) {
                    $oldPath = __DIR__ . '/../uploads/' . $existing['file_name'];
                    if (is_file($oldPath)) @unlink($oldPath);
                    $pdo->prepare("UPDATE documents SET file_name = ?, file_path = ?, status = 'Pending', remarks = NULL, reviewed_by = NULL, reviewed_at = NULL, uploaded_at = NOW() WHERE doc_id = ?")
                        ->execute([$savedName, 'uploads/' . $savedName, $existing['doc_id']]);
                } else {
                    $pdo->prepare('INSERT INTO documents (student_id, doc_type, file_name, file_path) VALUES (?,?,?,?)')
                        ->execute([$_SESSION['student_id'], $docType, $savedName, 'uploads/' . $savedName]);
                }

                audit_log($pdo, 'student', $_SESSION['student_account_id'], 'document_upload', STUDENT_DOCUMENT_TYPES[$docType] . ' uploaded');
                flash('success', STUDENT_DOCUMENT_TYPES[$docType] . ' uploaded successfully and is now pending review.');
                redirect('documents.php');
            }
        }
    }
}

$docStmt = $pdo->prepare('SELECT * FROM documents WHERE student_id = ?');
$docStmt->execute([$_SESSION['student_id']]);
$existingDocs = [];
foreach ($docStmt->fetchAll() as $d) {
    $existingDocs[$d['doc_type']] = $d;
}

$csrfToken = csrf_token('documents');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Documents</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <p class="muted">Accepted formats: PDF, JPG, JPEG, PNG. Maximum file size: 5MB. You can replace a document any
       time before it's approved.</p>

    <?php foreach (STUDENT_DOCUMENT_TYPES as $type => $label): ?>
        <?php $doc = $existingDocs[$type] ?? null; ?>
        <section class="panel">
            <div class="page-head">
                <h3 style="margin:0;"><?php echo clean($label); ?></h3>
                <?php echo $doc ? document_status_badge($doc['status']) : '<span class="badge">Not Uploaded</span>'; ?>
            </div>

            <?php if ($doc): ?>
                <p>
                    <a href="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($doc['file_name']); ?>" target="_blank">View current file</a>
                    <span class="muted"> &mdash; uploaded <?php echo date('M d, Y h:i A', strtotime($doc['uploaded_at'])); ?></span>
                </p>
                <?php if (!empty($doc['remarks'])): ?>
                    <p class="muted">Registrar remarks: <?php echo clean($doc['remarks']); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$doc || $doc['status'] !== 'Approved'): ?>
                <form method="POST" enctype="multipart/form-data" class="form-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                    <input type="hidden" name="doc_type" value="<?php echo clean($type); ?>">
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
                    <button type="submit" class="btn-secondary"><?php echo $doc ? 'Replace File' : 'Upload'; ?></button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
