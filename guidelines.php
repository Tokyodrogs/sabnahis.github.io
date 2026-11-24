<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Enrollment Guidelines';
$currentSY = $pdo->query('SELECT school_year, status FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
require_once __DIR__ . '/includes/site_header.php';
?>
<section class="section">
    <h1>Enrollment Guidelines</h1>
    <?php if ($currentSY): ?>
        <p>School Year <strong><?php echo clean($currentSY['school_year']); ?></strong> enrollment is currently
        <strong><?php echo $currentSY['status'] === 'Open' ? 'OPEN' : 'CLOSED'; ?></strong>.</p>
    <?php endif; ?>

    <h2 id="process">Steps to Enroll</h2>
    <ol class="steps-list">
        <li>Prepare scanned or clear photo copies of your requirements (PSA Birth Certificate, Report Card, Good Moral
            Certificate, and 2x2 Picture) in JPG, PNG, or PDF format, each no larger than 5MB.</li>
        <li>Go to the <a href="enroll.php">Online Enrollment Form</a> page.</li>
        <li>Fill out all required information accurately.</li>
        <li>Upload your documents and click "Submit Enrollment".</li>
        <li>Wait for the school administrator to review and approve your application.</li>
        <li>Once approved, you will be contacted using the information you provided. Officially enrolled students will
            receive an enrollment number.</li>
    </ol>

    <h2 id="requirements">Requirements Checklist</h2>
    <ul class="req-list">
        <li>PSA Birth Certificate</li>
        <li>Report Card / Form 138</li>
        <li>Good Moral Certificate</li>
        <li>2x2 ID Picture</li>
    </ul>

    <a href="enroll.php" class="btn-cta">Proceed to Enrollment Form</a>
</section>
<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
