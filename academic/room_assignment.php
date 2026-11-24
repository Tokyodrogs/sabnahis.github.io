<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/academic_functions.php';

$pageTitle = 'Room Assignment';
$errors = [];
$availabilityResult = null;

$currentSY = $pdo->query('SELECT * FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '', 'room_assignment')) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif ($action === 'add_room') {
        $roomName = trim($_POST['room_name'] ?? '');
        $roomType = trim($_POST['room_type'] ?? '');
        $capacity = trim($_POST['capacity'] ?? '');
        if ($roomName === '') {
            $errors[] = 'Room name is required.';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_name = ?');
            $dup->execute([$roomName]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = 'A room with that name already exists.';
            } else {
                $pdo->prepare('INSERT INTO rooms (room_name, room_type, capacity) VALUES (?,?,?)')
                    ->execute([$roomName, $roomType ?: null, $capacity !== '' ? (int) $capacity : null]);
                flash('success', 'Room added.');
                redirect('room_assignment.php');
            }
        }
    } elseif ($action === 'toggle_room_status') {
        $pdo->prepare("UPDATE rooms SET status = IF(status='Active','Inactive','Active') WHERE room_id = ?")->execute([(int) $_POST['room_id']]);
        redirect('room_assignment.php');
    } elseif ($action === 'assign_homeroom') {
        $roomId = $_POST['room_id'] ?? '';
        $sectionId = $_POST['section_id'] ?? '';
        $syId = $_POST['sy_id'] ?? '';
        if ($roomId === '' || $sectionId === '' || $syId === '') {
            $errors[] = 'Room, Section, and School Year are required.';
        } else {
            $existing = $pdo->prepare('SELECT room_assignment_id FROM room_assignments WHERE section_id = ? AND sy_id = ?');
            $existing->execute([$sectionId, $syId]);
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                $pdo->prepare('UPDATE room_assignments SET room_id = ? WHERE room_assignment_id = ?')->execute([$roomId, $existingId]);
                flash('success', 'Homeroom assignment updated.');
            } else {
                $pdo->prepare('INSERT INTO room_assignments (room_id, section_id, sy_id) VALUES (?,?,?)')->execute([$roomId, $sectionId, $syId]);
                flash('success', 'Homeroom assigned.');
            }
            redirect('room_assignment.php');
        }
    } elseif ($action === 'check_availability') {
        $roomId = $_POST['check_room_id'] ?? '';
        $day = $_POST['check_day'] ?? '';
        $start = $_POST['check_start'] ?? '';
        $end = $_POST['check_end'] ?? '';
        if ($roomId === '' || $day === '' || $start === '' || $end === '') {
            $errors[] = 'Please choose a room, day, start time, and end time to check availability.';
        } else {
            $conflicts = check_room_availability($pdo, $roomId, $currentSY['sy_id'] ?? 0, $day, $start, $end);
            $availabilityResult = ['room_id' => $roomId, 'day' => $day, 'start' => $start, 'end' => $end, 'conflicts' => $conflicts];
        }
    }
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY room_name')->fetchAll();
$sections = $pdo->query("SELECT sec.*, g.grade_name FROM sections sec LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id WHERE sec.status = 'Active' ORDER BY g.grade_level_id, sec.section_name")->fetchAll();
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();

$homerooms = $pdo->query("SELECT ra.*, r.room_name, sec.section_name, g.grade_name, sy.school_year
    FROM room_assignments ra
    JOIN rooms r ON r.room_id = ra.room_id
    JOIN sections sec ON sec.section_id = ra.section_id
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    JOIN school_year sy ON sy.sy_id = ra.sy_id
    ORDER BY sy.school_year DESC, g.grade_level_id, sec.section_name")->fetchAll();

$viewScheduleRoomId = $_GET['view_schedule'] ?? '';
$roomSchedule = [];
$scheduleRoomName = '';
if ($viewScheduleRoomId !== '' && $currentSY) {
    $roomSchedule = get_room_schedule($pdo, $viewScheduleRoomId, $currentSY['sy_id']);
    foreach ($rooms as $r) { if ($r['room_id'] == $viewScheduleRoomId) $scheduleRoomName = $r['room_name']; }
}

$csrfToken = csrf_token('room_assignment');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Room Assignment</h2>
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . clean($e) . '</li>'; ?></ul></div><?php endif; ?>

    <?php if ($viewScheduleRoomId !== ''): ?>
        <section class="panel">
            <div class="panel-header">
                <h3>Room Schedule: <?php echo clean($scheduleRoomName); ?> (<?php echo clean($currentSY['school_year'] ?? '-'); ?>)</h3>
                <a href="room_assignment.php" class="btn-small">Close</a>
            </div>
            <?php if (empty($roomSchedule)): ?>
                <p class="muted">No class periods scheduled in this room yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Grade &amp; Section</th><th>Teacher</th></tr></thead>
                    <tbody>
                    <?php foreach ($roomSchedule as $r): ?>
                        <tr>
                            <td><?php echo clean($r['day_of_week']); ?></td>
                            <td><?php echo clean(format_time12($r['time_start']) . ' - ' . format_time12($r['time_end'])); ?></td>
                            <td><?php echo clean($r['subject_code'] . ' - ' . $r['subject_title']); ?></td>
                            <td><?php echo clean(($r['grade_name'] ?: '-') . ' - ' . $r['section_name']); ?></td>
                            <td><?php echo clean($r['teacher_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h3>Check Room Availability</h3>
        <form method="POST" class="form-row" style="align-items:flex-end;">
            <input type="hidden" name="action" value="check_availability">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label>Room
                <select name="check_room_id" required>
                    <option value="">Select</option>
                    <?php foreach ($rooms as $r): ?><option value="<?php echo $r['room_id']; ?>"><?php echo clean($r['room_name']); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Day
                <select name="check_day" required>
                    <option value="">Select</option>
                    <?php foreach (DAYS_OF_WEEK as $d): ?><option value="<?php echo $d; ?>"><?php echo $d; ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Start<input type="time" name="check_start" required></label>
            <label>End<input type="time" name="check_end" required></label>
            <button type="submit" class="btn-secondary">Check Availability</button>
        </form>
        <?php if ($availabilityResult): ?>
            <?php if (empty($availabilityResult['conflicts'])): ?>
                <div class="alert alert-success" style="margin-top:12px;">This room is available on <?php echo clean($availabilityResult['day']); ?> from <?php echo clean(format_time12($availabilityResult['start'])); ?> to <?php echo clean(format_time12($availabilityResult['end'])); ?>.</div>
            <?php else: ?>
                <div class="alert alert-error" style="margin-top:12px;">
                    <p>This room is already booked during that time:</p>
                    <ul>
                        <?php foreach ($availabilityResult['conflicts'] as $c): ?>
                            <li><?php echo clean($c['subject_title'] . ' - ' . $c['section_name'] . ' (' . format_time12($c['time_start']) . '-' . format_time12($c['time_end']) . ')'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Room Roster</h3>
        <form method="POST" class="form-grid" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="add_room">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="form-row">
                <label>Room Name *<input type="text" name="room_name" placeholder="e.g. Room 105" required></label>
                <label>Room Type<input type="text" name="room_type" placeholder="e.g. Lecture, Laboratory"></label>
                <label>Capacity<input type="number" name="capacity" min="1"></label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Add Room</button></div>
        </form>
        <table class="data-table">
            <thead><tr><th>Room</th><th>Type</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($rooms)): ?><tr><td colspan="5" class="muted" style="text-align:center;">No rooms yet.</td></tr><?php endif; ?>
            <?php foreach ($rooms as $r): ?>
                <tr>
                    <td><?php echo clean($r['room_name']); ?></td>
                    <td><?php echo clean($r['room_type'] ?: '-'); ?></td>
                    <td><?php echo clean($r['capacity'] ?: '-'); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo clean($r['status']); ?></span></td>
                    <td class="actions">
                        <a href="room_assignment.php?view_schedule=<?php echo $r['room_id']; ?>">View Schedule</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_room_status">
                            <input type="hidden" name="room_id" value="<?php echo $r['room_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
                            <button type="submit" class="btn-small"><?php echo $r['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h3>Section Homeroom Assignment</h3>
        <form method="POST" class="form-grid" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="assign_homeroom">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <div class="form-row">
                <label>School Year *
                    <select name="sy_id" required>
                        <option value="">Select</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?php echo $sy['sy_id']; ?>" <?php echo (!empty($currentSY) && $currentSY['sy_id']==$sy['sy_id']) ? 'selected' : ''; ?>><?php echo clean($sy['school_year']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Section *
                    <select name="section_id" required>
                        <option value="">Select</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['section_id']; ?>"><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Room *
                    <select name="room_id" required>
                        <option value="">Select</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r['room_id']; ?>"><?php echo clean($r['room_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Assign Homeroom</button></div>
        </form>
        <table class="data-table">
            <thead><tr><th>Grade &amp; Section</th><th>Room</th><th>School Year</th></tr></thead>
            <tbody>
            <?php if (empty($homerooms)): ?><tr><td colspan="3" class="muted" style="text-align:center;">No homeroom assignments yet.</td></tr><?php endif; ?>
            <?php foreach ($homerooms as $h): ?>
                <tr>
                    <td><?php echo clean(($h['grade_name'] ?: '-') . ' - ' . $h['section_name']); ?></td>
                    <td><?php echo clean($h['room_name']); ?></td>
                    <td><?php echo clean($h['school_year']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
