<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Section Schedule';

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();

$syId = $_GET['sy_id'] ?? ($currentSY['sy_id'] ?? '');
$sections = $pdo->prepare("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.sy_id = ? ORDER BY g.grade_level_id, sec.section_name");
$sections->execute([$syId ?: 0]);
$sections = $sections->fetchAll();

$sectionId = $_GET['section_id'] ?? ($sections[0]['section_id'] ?? '');
$schedule = [];
$sectionLabel = '';
$totalUnits = 0;
if ($sectionId !== '' && $syId !== '') {
    $schedule = get_section_schedule($pdo, $sectionId, $syId);
    foreach ($sections as $s) {
        if ($s['section_id'] == $sectionId) $sectionLabel = ($s['grade_name'] ?: '-') . ' - ' . $s['section_name'];
    }
    $seenSubjects = [];
    foreach ($schedule as $rows) {
        foreach ($rows as $r) {
            if (!isset($seenSubjects[$r['subject_id']])) {
                $seenSubjects[$r['subject_id']] = true;
                $totalUnits += (float) $r['units'];
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Section Schedule</h2>
        <?php if ($sectionId !== ''): ?>
            <a href="section_schedule_print.php?sy_id=<?php echo urlencode($syId); ?>&section_id=<?php echo urlencode($sectionId); ?>" target="_blank" class="btn-secondary">Print</a>
        <?php endif; ?>
    </div>

    <form method="GET" class="filter-bar">
        <select name="sy_id" onchange="this.form.submit()">
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?php echo $sy['sy_id']; ?>" <?php echo (string) $syId === (string) $sy['sy_id'] ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="section_id" onchange="this.form.submit()">
            <?php foreach ($sections as $s): ?>
                <option value="<?php echo $s['section_id']; ?>" <?php echo (string) $sectionId === (string) $s['section_id'] ? 'selected' : ''; ?>><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($sections)): ?>
        <section class="panel"><p class="muted">No sections exist for this school year yet.</p></section>
    <?php elseif (empty(array_filter($schedule))): ?>
        <section class="panel"><p class="muted">No class schedule has been created yet for <?php echo clean($sectionLabel); ?>. Use Class Schedule &rarr; Create Class Schedule to build it.</p></section>
    <?php else: ?>
        <h3><?php echo clean($sectionLabel); ?> &mdash; Master Schedule <span class="muted" style="font-weight:normal;">(<?php echo clean(rtrim(rtrim(number_format($totalUnits,1),'0'),'.')); ?> total units)</span></h3>
        <div class="schedule-grid">
            <?php foreach ($schedule as $day => $rows): ?>
                <section class="panel schedule-day-card">
                    <h3><?php echo clean($day); ?></h3>
                    <?php if (empty($rows)): ?>
                        <p class="muted">No classes.</p>
                    <?php else: ?>
                        <ul class="schedule-list">
                            <?php foreach ($rows as $r): ?>
                                <li>
                                    <strong><?php echo clean(format_time12($r['time_start']) . ' - ' . format_time12($r['time_end'])); ?></strong>
                                    <div><?php echo clean($r['subject_code'] . ' - ' . $r['subject_title']); ?></div>
                                    <div class="muted"><?php echo clean($r['teacher_name']); ?> &bull; Room <?php echo clean($r['room_name'] ?: '-'); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
