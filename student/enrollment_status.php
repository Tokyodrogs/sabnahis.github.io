<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Enrollment Status';

$stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

$progress = compute_enrollment_progress($pdo, $student);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Enrollment Status</h2>

    <section class="panel">
        <div class="page-head">
            <h3 style="margin:0;">Current Status: <?php echo status_badge($student['status']); ?></h3>
        </div>

        <?php if ($progress['rejected']): ?>
            <div class="alert alert-error">
                Your enrollment application was not approved. Please contact the Registrar's Office for details or to
                submit a new application.
            </div>
        <?php endif; ?>

        <ol class="progress-tracker">
            <?php foreach ($progress['stages'] as $i => $stage): ?>
                <?php
                    // The final stage (Officially Enrolled) is a terminal
                    // completion, not an in-progress waypoint, so it always
                    // renders as "done" (green check) rather than "current"
                    // (gold "you are here") once reached.
                    $isLastStage = ($i === count($progress['stages']) - 1);
                    $state = 'upcoming';
                    if ($progress['completed'][$i]) {
                        $state = ($i === $progress['current'] && !$isLastStage) ? 'current' : 'done';
                    }
                ?>
                <li class="progress-step progress-step-<?php echo $state; ?>">
                    <span class="progress-dot"><?php echo $state === 'done' ? '&#10003;' : ($i + 1); ?></span>
                    <span class="progress-label"><?php echo clean($stage); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>

        <p class="muted" style="margin-top:16px;">
            Documents Verified requires all of your uploaded documents to be Approved (see the Documents page).
            Officially Enrolled updates automatically once the registrar finalizes your enrollment.
        </p>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
