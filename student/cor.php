<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Certificate of Registration';

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    WHERE s.student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

$isEnrolled = ($student['status'] === 'Enrolled');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Certificate of Registration</h2>

    <section class="panel">
        <?php if (!$isEnrolled): ?>
            <div class="alert alert-info">
                Your Certificate of Registration will be available for viewing, printing, and download once your
                enrollment status is <strong>Officially Enrolled</strong>. Your current status is
                <?php echo status_badge($student['status']); ?>.
            </div>
        <?php else: ?>
            <p>Your Certificate of Registration for <strong><?php echo clean($student['school_year'] ?? '-'); ?></strong>
               is ready.</p>
            <div class="form-actions">
                <a href="cor_print.php" target="_blank" class="btn-primary">View / Print / Download PDF</a>
            </div>
            <p class="muted" style="margin-top:10px;">Use your browser's "Save as PDF" option in the print dialog to
               download a copy.</p>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
