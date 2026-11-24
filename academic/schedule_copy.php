<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Copy Schedule to Another Section';
$errors = [];
$results = null;

$sourceSectionId = $_GET['source_section_id'] ?? '';
if ($sourceSectionId === '' && !empty($_GET['id'])) {
    // Came from a single schedule row's "Copy" link - use that row's section as the source.
    $srcStmt = $pdo->prepare('SELECT section_id FROM class_schedules WHERE schedule_id = ?');
    $srcStmt->execute([(int) $_GET['id']]);
    $sourceSectionId = $srcStmt->fetchColumn() ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceSectionId = $_POST['source_section_id'] ?? '';
    $targetSectionId = $_POST['target_section_id'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'schedule_copy')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($sourceSectionId === '' || $targetSectionId === '') {
        $errors[] = 'Please choose both a source and a target section.';
    } elseif ($sourceSectionId === $targetSectionId) {
        $errors[] = 'Source and target section must be different.';
    } else {
        $rowsStmt = $pdo->prepare("SELECT * FROM class_schedules WHERE section_id = ? AND status = 'Active'");
        $rowsStmt->execute([$sourceSectionId]);
        $sourceRows = $rowsStmt->fetchAll();

        if (empty($sourceRows)) {
            $errors[] = 'The source section has no active class schedule to copy.';
        } else {
            $copied = [];
            $skipped = [];
            foreach ($sourceRows as $row) {
                $candidate = [
                    'sy_id' => $row['sy_id'],
                    'section_id' => $targetSectionId,
                    'subject_id' => $row['subject_id'],
                    'teacher_id' => $row['teacher_id'],
                    'room_id' => $row['room_id'],
                    'day_of_week' => $row['day_of_week'],
                    'time_start' => $row['time_start'],
                    'time_end' => $row['time_end'],
                ];
                $conflicts = check_schedule_conflicts($pdo, $candidate);
                $label = $row['day_of_week'] . ' ' . format_time12($row['time_start']) . '-' . format_time12($row['time_end']);
                if ($conflicts) {
                    $skipped[] = ['label' => $label, 'reasons' => $conflicts];
                } else {
                    $pdo->prepare('INSERT INTO class_schedules (sy_id, section_id, subject_id, teacher_id, room_id, day_of_week, time_start, time_end, status)
                        VALUES (?,?,?,?,?,?,?,?,\'Active\')')
                        ->execute([$candidate['sy_id'], $candidate['section_id'], $candidate['subject_id'], $candidate['teacher_id'],
                            $candidate['room_id'], $candidate['day_of_week'], $candidate['time_start'], $candidate['time_end']]);
                    $copied[] = $label;
                }
            }
            $results = ['copied' => $copied, 'skipped' => $skipped];
        }
    }
}

$sections = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.status = 'Active' ORDER BY g.grade_level_id, sec.section_name")->fetchAll();
$csrfToken = csrf_token('schedule_copy');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Copy Schedule to Another Section</h2>
    <p class="muted">Copies every active class period from the source section's weekly schedule to the target section. Each period is still run through the conflict checker for the target section - periods that would conflict (teacher, room, or section overlap) are skipped and listed below rather than silently overwritten.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <?php if ($results): ?>
        <div class="alert <?php echo $results['skipped'] ? 'alert-error' : 'alert-success'; ?>">
            <p><?php echo count($results['copied']); ?> period(s) copied successfully<?php echo $results['skipped'] ? ', ' . count($results['skipped']) . ' skipped due to conflicts:' : '.'; ?></p>
            <?php if ($results['skipped']): ?>
                <ul>
                    <?php foreach ($results['skipped'] as $sk): ?>
                        <li><strong><?php echo clean($sk['label']); ?></strong>: <?php echo clean(implode(' ', $sk['reasons'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <a href="class_schedule.php" class="btn-primary">Go to Class Schedule</a>
    <?php endif; ?>

    <section class="panel" style="max-width:560px;">
        <form method="POST" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label>Source Section (copy FROM) *
                <select name="source_section_id" required>
                    <option value="">Select</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['section_id']; ?>" <?php echo (string) $sourceSectionId === (string) $s['section_id'] ? 'selected' : ''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Target Section (copy TO) *
                <select name="target_section_id" required>
                    <option value="">Select</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['section_id']; ?>"><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Copy Schedule</button>
                <a href="class_schedule.php" class="btn-link">Cancel</a>
            </div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
