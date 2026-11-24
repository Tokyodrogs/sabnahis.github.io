<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Review Enrollment';
$studentId = (int)($_GET['student_id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    WHERE s.student_id = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash('success', 'Enrollment record not found.');
    redirect('enrollment_requests.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $newStatus = null;

    if ($action === 'verify')        $newStatus = 'Verified';
    elseif ($action === 'approve')   $newStatus = 'Approved';
    elseif ($action === 'reject')    $newStatus = 'Rejected';
    elseif ($action === 'enroll')    $newStatus = 'Enrolled';

    if (in_array($action, ['doc_approve', 'doc_reject', 'doc_reupload'], true)) {
        // ---- Document review (Approve / Reject / Request Re-upload) ----
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $docRemarks = trim($_POST['doc_remarks'] ?? '');
        $docStatusMap = [
            'doc_approve'  => 'Approved',
            'doc_reject'   => 'Rejected',
            'doc_reupload' => 'Re-upload Requested',
        ];
        $docStatus = $docStatusMap[$action];

        $find = $pdo->prepare('SELECT * FROM documents WHERE doc_id = ? AND student_id = ?');
        $find->execute([$docId, $studentId]);
        $doc = $find->fetch();

        if (!$doc) {
            $errors[] = 'Document not found.';
        } else {
            $pdo->prepare('UPDATE documents SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE doc_id = ?')
                ->execute([$docStatus, $docRemarks ?: null, $_SESSION['admin_id'], $docId]);

            $docLabel = STUDENT_DOCUMENT_TYPES[$doc['doc_type']] ?? (REQUIRED_DOCUMENTS[$doc['doc_type']] ?? $doc['doc_type']);
            $message = "Your {$docLabel} was marked as {$docStatus}.";
            if ($docRemarks !== '') $message .= ' Remarks: ' . $docRemarks;
            notify_student($pdo, $studentId, 'document', $message, 'document', $docId);

            flash('success', "Document marked as {$docStatus}.");
            redirect('enrollment_view.php?student_id=' . $studentId);
        }
    } elseif ($newStatus === null) {
        $errors[] = 'Unknown action.';
    } else {
        $enrollmentNo = $student['enrollment_no'];
        if ($newStatus === 'Enrolled' && empty($enrollmentNo)) {
            $enrollmentNo = generate_enrollment_no($student['school_year'] ?? date('Y'), $student['student_id']);
        }

        $upd = $pdo->prepare('UPDATE students SET status = ?, enrollment_no = ? WHERE student_id = ?');
        $upd->execute([$newStatus, $enrollmentNo, $studentId]);

        $log = $pdo->prepare('INSERT INTO enrollment (student_id, status, remarks) VALUES (?, ?, ?)');
        $log->execute([$studentId, $newStatus, $remarks ?: null]);

        // Approving or officially enrolling a student is the "successfully
        // registers a student" moment that provisions their Student Portal
        // account (see includes/functions.php::create_student_account()).
        // Idempotent - a no-op if the account already exists.
        if (in_array($newStatus, ['Approved', 'Enrolled'], true)) {
            create_student_account($pdo, $studentId);
        }

        $statusLabel = $newStatus === 'Enrolled' ? 'Officially Enrolled' : $newStatus;
        notify_student($pdo, $studentId, 'enrollment', "Your enrollment application has been {$statusLabel}." . ($remarks !== '' ? ' Remarks: ' . $remarks : ''));

        flash('success', "Application marked as {$statusLabel}.");
        redirect('enrollment_view.php?student_id=' . $studentId);
    }
}

$logStmt = $pdo->prepare('SELECT * FROM enrollment WHERE student_id = ? ORDER BY date_applied DESC');
$logStmt->execute([$studentId]);
$logs = $logStmt->fetchAll();

$docStmt = $pdo->prepare('SELECT * FROM documents WHERE student_id = ?');
$docStmt->execute([$studentId]);
$documents = $docStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Review Application: <?php echo clean($student['lastname'] . ', ' . $student['firstname']); ?></h2>
        <div>
            <?php echo status_badge($student['status']); ?>
            <a href="enrollment_print.php?student_id=<?php echo $studentId; ?>" class="btn-secondary" target="_blank">Print Enrollment Form</a>
        </div>
    </div>

    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Applicant Details</h3>
        <table class="detail-table">
            <tr><th>LRN</th><td colspan="3"><?php echo clean($student['lrn'] ?: 'Not yet assigned'); ?></td></tr>
            <tr><th>Full Name</th><td colspan="3"><?php echo clean($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['extension']); ?></td></tr>
            <tr><th>Sex</th><td><?php echo clean($student['sex']); ?></td><th>Birthdate / Age</th><td><?php echo clean($student['birthdate']) . ' / ' . clean($student['age']); ?></td></tr>
            <tr><th>Address</th><td colspan="3"><?php echo clean($student['address']); ?></td></tr>
            <tr><th>Contact</th><td><?php echo clean($student['contact']); ?></td><th>Email</th><td><?php echo clean($student['email']); ?></td></tr>
            <tr><th>Previous School</th><td colspan="3"><?php echo clean($student['previous_school']); ?></td></tr>
            <tr><th>Grade Level</th><td><?php echo clean($student['grade_name'] ?? '-'); ?></td><th>Strand</th><td><?php echo clean($student['strand_name'] ?? '-'); ?></td></tr>
            <tr><th>School Year</th><td><?php echo clean($student['school_year'] ?? '-'); ?></td><th>Enrollment No.</th><td><?php echo clean($student['enrollment_no'] ?: 'Not yet generated'); ?></td></tr>
            <tr><th>Guardian</th><td><?php echo clean($student['guardian_name']); ?></td><th>Guardian Contact</th><td><?php echo clean($student['guardian_contact']); ?></td></tr>
        </table>
    </section>

    <section class="panel">
        <h3>Submitted Documents</h3>
        <?php if (empty($documents)): ?>
            <p class="muted">No documents on file.</p>
        <?php else: ?>
            <table class="detail-table">
                <tr><th>Document</th><th>File</th><th>Status</th><th>Review</th></tr>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?php echo clean(STUDENT_DOCUMENT_TYPES[$d['doc_type']] ?? (REQUIRED_DOCUMENTS[$d['doc_type']] ?? $d['doc_type'])); ?></td>
                        <td><a href="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($d['file_name']); ?>" target="_blank">View File</a></td>
                        <td><?php echo document_status_badge($d['status']); ?>
                            <?php if (!empty($d['remarks'])): ?><br><small class="muted"><?php echo clean($d['remarks']); ?></small><?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="form-inline doc-review-form">
                                <input type="hidden" name="doc_id" value="<?php echo $d['doc_id']; ?>">
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

    <section class="panel">
        <h3>Take Action</h3>
        <p class="muted">Typical DepEd workflow: Verify the applicant's information and documents match, then Approve, then Mark as Officially Enrolled once payment/requirements are complete.</p>
        <form method="POST" class="form-grid">
            <label class="full">Remarks (optional)
                <textarea name="remarks" rows="3" placeholder="e.g. reason for rejection, notes for the record"></textarea>
            </label>
            <div class="form-actions">
                <button type="submit" name="action" value="verify" class="btn-secondary">Verify Information</button>
                <button type="submit" name="action" value="approve" class="btn-primary">Approve</button>
                <button type="submit" name="action" value="reject" class="btn-danger" onclick="return confirm('Reject this application?');">Reject</button>
                <button type="submit" name="action" value="enroll" class="btn-secondary">Mark as Officially Enrolled</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>Status History</h3>
        <?php if (empty($logs)): ?>
            <p class="muted">No history yet.</p>
        <?php else: ?>
            <table class="detail-table">
                <tr><th>Date</th><th>Status</th><th>Remarks</th></tr>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($l['date_applied'])); ?></td>
                        <td><?php echo status_badge($l['status']); ?></td>
                        <td><?php echo clean($l['remarks']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <a href="enrollment_requests.php" class="btn-link">&larr; Back to Enrollment Requests</a>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
