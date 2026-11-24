<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Document Verification module (2026-08) - "View Requirements": every
// document belonging to ONE student, opened from the grouped queue in
// document_verification.php. Per-document Approve/Reject/Request Re-upload
// plus student-scoped bulk actions (Approve All / Reject All / Request
// Re-upload All) live here so the Registrar can clear a whole application
// in one visit instead of hunting through a flat cross-student list.
$pageTitle = 'Document Verification - Requirements';
$studentId = (int) ($_GET['student_id'] ?? 0);

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, sec.section_name
    FROM students s
    LEFT JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
    LEFT JOIN sections sec ON sec.section_id = s.section_id
    WHERE s.student_id = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash('success', 'Student not found.');
    redirect('document_verification.php');
}

$csrfToken = csrf_token('document_verification');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '', 'document_verification')) {
        flash('success', 'Your session has expired. Please try again.');
        redirect('document_verification_view.php?student_id=' . $studentId);
    }

    $action = $_POST['action'] ?? '';
    $bulkMap = [
        'bulk_approve_all'  => 'Approved',
        'bulk_reject_all'   => 'Rejected',
        'bulk_reupload_all' => 'Re-upload Requested',
    ];
    $singleMap = [
        'doc_approve'  => 'Approved',
        'doc_reject'   => 'Rejected',
        'doc_reupload' => 'Re-upload Requested',
    ];

    if (isset($bulkMap[$action])) {
        $newStatus = $bulkMap[$action];
        $docIdsStmt = $pdo->prepare('SELECT doc_id, doc_type FROM documents WHERE student_id = ?');
        $docIdsStmt->execute([$studentId]);
        $docs = $docIdsStmt->fetchAll();

        if (empty($docs)) {
            flash('success', 'No documents on file to update.');
        } else {
            $pdo->prepare('UPDATE documents SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE student_id = ?')
                ->execute([$newStatus, $_SESSION['admin_id'], $studentId]);
            notify_student($pdo, $studentId, 'document', "All of your submitted documents (" . count($docs) . ") were marked as {$newStatus}.");
            flash('success', count($docs) . " document(s) marked as {$newStatus}.");
        }
        redirect('document_verification_view.php?student_id=' . $studentId);
    }

    if (isset($singleMap[$action])) {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $remarks = trim($_POST['doc_remarks'] ?? '');
        $newStatus = $singleMap[$action];

        $docStmt = $pdo->prepare('SELECT * FROM documents WHERE doc_id = ? AND student_id = ?');
        $docStmt->execute([$docId, $studentId]);
        $doc = $docStmt->fetch();

        if (!$doc) {
            flash('success', 'Document not found.');
        } else {
            $pdo->prepare('UPDATE documents SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE doc_id = ?')
                ->execute([$newStatus, $remarks ?: null, $_SESSION['admin_id'], $docId]);

            $docLabel = document_type_label($pdo, $doc['doc_type']);
            $message = "Your {$docLabel} was marked as {$newStatus}.";
            if ($remarks !== '') $message .= ' Remarks: ' . $remarks;
            notify_student($pdo, $studentId, 'document', $message, 'document', $docId);

            flash('success', "Document marked as {$newStatus}.");
        }
        redirect('document_verification_view.php?student_id=' . $studentId);
    }

    flash('success', 'Unknown action.');
    redirect('document_verification_view.php?student_id=' . $studentId);
}

$docStmt = $pdo->prepare('SELECT * FROM documents WHERE student_id = ? ORDER BY uploaded_at ASC');
$docStmt->execute([$studentId]);
$documents = $docStmt->fetchAll();

// Per-applicant-type required docs (requirement_configs table, 2026-08h)
// rather than the old one-size-fits-all REQUIRED_DOCUMENTS constant - a
// Transferee's Form 137 requirement, for example, wasn't reflected here
// before this fix.
$requiredKeys = array_keys(required_documents_for_type($pdo, $student['student_type'] ?? 'New'));
$requiredTotal = count($requiredKeys);
$requiredSubmitted = 0;
$approvedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;
foreach ($documents as $d) {
    if (in_array($d['doc_type'], $requiredKeys, true)) $requiredSubmitted++;
    if ($d['status'] === 'Approved') $approvedCount++;
    elseif ($d['status'] === 'Rejected') $rejectedCount++;
    else $pendingCount++; // Pending or Re-upload Requested
}
$overallStatus = compute_document_verification_status($requiredTotal, $requiredSubmitted, $approvedCount, $rejectedCount, $pendingCount);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Requirements: <?php echo clean($student['lastname'] . ', ' . $student['firstname']); ?></h2>
        <div>
            <?php echo document_verification_status_badge($overallStatus); ?>
            <a href="document_verification.php" class="btn-link">&larr; Back to Document Verification</a>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

    <section class="panel">
        <p class="muted" style="margin-top:0;">
            <?php echo clean($student['grade_name'] ?: '-'); ?> &mdash; Section <?php echo clean($student['section_name'] ?: 'Unassigned'); ?>
            &mdash; <?php echo clean($student['student_type'] ?: '-'); ?> Student
        </p>

        <div class="stat-cards">
            <div class="stat-card"><span class="stat-value"><?php echo $requiredSubmitted; ?> / <?php echo $requiredTotal; ?></span><span class="stat-label">Submitted</span></div>
            <div class="stat-card"><span class="stat-value"><?php echo $approvedCount; ?></span><span class="stat-label">Approved</span></div>
            <div class="stat-card"><span class="stat-value"><?php echo $pendingCount; ?></span><span class="stat-label">Pending</span></div>
            <div class="stat-card"><span class="stat-value"><?php echo $rejectedCount; ?></span><span class="stat-label">Rejected</span></div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3>Required Documents</h3>
            <?php if (!empty($documents)): ?>
            <div class="actions">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve ALL documents for this student?');">
                    <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                    <button type="submit" name="action" value="bulk_approve_all" class="btn-secondary">Approve All</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject ALL documents for this student?');">
                    <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                    <button type="submit" name="action" value="bulk_reject_all" class="btn-danger">Reject All</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Request re-upload for ALL documents for this student?');">
                    <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                    <button type="submit" name="action" value="bulk_reupload_all" class="btn-secondary">Request Re-upload All</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($documents)): ?>
            <p class="muted">No documents on file for this student yet.</p>
        <?php else: ?>
            <table class="detail-table">
                <tr><th>Document</th><th>File</th><th>Status</th><th>Review</th></tr>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?php echo clean(document_type_label($pdo, $d['doc_type'])); ?>
                            <?php if (!in_array($d['doc_type'], $requiredKeys, true)): ?><br><small class="muted">Additional document</small><?php endif; ?>
                        </td>
                        <td><a href="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($d['file_name']); ?>" target="_blank">View File</a></td>
                        <td><?php echo document_status_badge($d['status']); ?>
                            <?php if (!empty($d['remarks'])): ?><br><small class="muted"><?php echo clean($d['remarks']); ?></small><?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="form-inline doc-review-form">
                                <input type="hidden" name="doc_id" value="<?php echo $d['doc_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                                <input type="text" name="doc_remarks" placeholder="Remarks (optional)" class="doc-remarks-input">
                                <button type="submit" name="action" value="doc_approve" class="link-success">Approve</button>
                                <button type="submit" name="action" value="doc_reject" class="link-danger" onclick="return confirm('Reject this document?');">Reject</button>
                                <button type="submit" name="action" value="doc_reupload" class="link-plain">Request Re-upload</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <div class="form-actions">
        <a href="document_verification.php" class="btn-secondary">&larr; Back to Document Verification</a>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
