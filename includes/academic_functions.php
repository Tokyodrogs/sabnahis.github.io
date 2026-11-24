<?php
/**
 * Academic Management helper functions - shared by the Academic Coordinator
 * portal (academic/), the admin panel's read-only oversight pages
 * (admin/academic_overview.php, admin/sections.php), and the Student
 * Portal's Class Schedule page (student/schedule.php).
 *
 * The centerpiece is check_schedule_conflicts(), which every schedule
 * create/edit path (academic/schedule_add.php, academic/schedule_edit.php,
 * academic/schedule_copy.php) must call BEFORE writing to class_schedules,
 * per the spec's Schedule Conflict Checker requirement:
 *   - Teacher Conflict  - same teacher, overlapping day/time, different class.
 *   - Room Conflict     - same room, overlapping day/time.
 *   - Section Conflict  - same section double-booked (two subjects at once).
 *   - Duplicate Schedule - identical section+subject+day+time already exists.
 *   - Time Overlap      - the general overlap test used by all of the above.
 */

const DAYS_OF_WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/**
 * Format a TIME value (HH:MM:SS) as "8:00 AM" for display in conflict
 * messages and timetables.
 */
function format_time12($time) {
    if (!$time) return '-';
    return date('g:i A', strtotime($time));
}

/**
 * Check a candidate class_schedules row for conflicts before it is saved.
 *
 * $data keys: sy_id, section_id, subject_id, teacher_id, room_id (nullable),
 * day_of_week, time_start, time_end.
 * $excludeScheduleId - pass the current schedule_id when editing, so a
 * row doesn't conflict with itself.
 *
 * Returns an array of human-readable conflict descriptions. Empty array
 * means the candidate schedule is safe to save.
 */
function check_schedule_conflicts(PDO $pdo, array $data, $excludeScheduleId = null) {
    $conflicts = [];

    $sectionId = (int) $data['section_id'];
    $subjectId = (int) $data['subject_id'];
    $teacherId = (int) $data['teacher_id'];
    $roomId    = $data['room_id'] !== '' && $data['room_id'] !== null ? (int) $data['room_id'] : null;
    $day       = $data['day_of_week'];
    $start     = $data['time_start'];
    $end       = $data['time_end'];
    $syId      = (int) $data['sy_id'];

    // ---- Time Overlap sanity check on the candidate itself ----
    if (strtotime($start) >= strtotime($end)) {
        $conflicts[] = 'Time Overlap: the start time must be earlier than the end time.';
        return $conflicts; // no point checking further against a nonsensical range
    }

    // Standard interval-overlap test, reused by every conflict type below:
    // two ranges overlap unless one ends at/before the other starts.
    $overlapSql = '(time_start < ? AND time_end > ?)';
    $excludeSql = $excludeScheduleId ? ' AND schedule_id != ?' : '';

    // ---- Duplicate Schedule: identical section+subject+day+time ----
    $dupSql = "SELECT schedule_id FROM class_schedules
        WHERE sy_id = ? AND section_id = ? AND subject_id = ? AND day_of_week = ?
          AND time_start = ? AND time_end = ? AND status = 'Active'" . $excludeSql;
    $dupParams = [$syId, $sectionId, $subjectId, $day, $start, $end];
    if ($excludeScheduleId) $dupParams[] = $excludeScheduleId;
    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute($dupParams);
    if ($dupStmt->fetch()) {
        $conflicts[] = 'Duplicate Schedule: this exact section, subject, day, and time is already scheduled.';
    }

    // ---- Teacher Conflict ----
    $teacherSql = "SELECT cs.day_of_week, cs.time_start, cs.time_end, sub.subject_title, sec.section_name, g.grade_name
        FROM class_schedules cs
        JOIN subjects sub ON sub.subject_id = cs.subject_id
        JOIN sections sec ON sec.section_id = cs.section_id
        LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
        WHERE cs.sy_id = ? AND cs.teacher_id = ? AND cs.day_of_week = ? AND cs.status = 'Active'
          AND $overlapSql" . $excludeSql;
    $teacherParams = [$syId, $teacherId, $day, $end, $start];
    if ($excludeScheduleId) $teacherParams[] = $excludeScheduleId;
    $teacherStmt = $pdo->prepare($teacherSql);
    $teacherStmt->execute($teacherParams);
    foreach ($teacherStmt->fetchAll() as $row) {
        $conflicts[] = sprintf(
            'Teacher Conflict: this teacher is already assigned to %s (%s - %s) on %s %s-%s.',
            $row['subject_title'], $row['grade_name'] ?: '-', $row['section_name'],
            $row['day_of_week'], format_time12($row['time_start']), format_time12($row['time_end'])
        );
    }

    // ---- Room Conflict (only relevant if a room was specified) ----
    if ($roomId) {
        $roomSql = "SELECT cs.day_of_week, cs.time_start, cs.time_end, sub.subject_title, sec.section_name
            FROM class_schedules cs
            JOIN subjects sub ON sub.subject_id = cs.subject_id
            JOIN sections sec ON sec.section_id = cs.section_id
            WHERE cs.sy_id = ? AND cs.room_id = ? AND cs.day_of_week = ? AND cs.status = 'Active'
              AND $overlapSql" . $excludeSql;
        $roomParams = [$syId, $roomId, $day, $end, $start];
        if ($excludeScheduleId) $roomParams[] = $excludeScheduleId;
        $roomStmt = $pdo->prepare($roomSql);
        $roomStmt->execute($roomParams);
        foreach ($roomStmt->fetchAll() as $row) {
            $conflicts[] = sprintf(
                'Room Conflict: this room is already booked for %s (%s) on %s %s-%s.',
                $row['subject_title'], $row['section_name'],
                $row['day_of_week'], format_time12($row['time_start']), format_time12($row['time_end'])
            );
        }
    }

    // ---- Section Conflict: the section already has another subject at an overlapping time ----
    $sectionSql = "SELECT cs.day_of_week, cs.time_start, cs.time_end, sub.subject_title
        FROM class_schedules cs
        JOIN subjects sub ON sub.subject_id = cs.subject_id
        WHERE cs.sy_id = ? AND cs.section_id = ? AND cs.day_of_week = ? AND cs.status = 'Active'
          AND $overlapSql" . $excludeSql;
    $sectionParams = [$syId, $sectionId, $day, $end, $start];
    if ($excludeScheduleId) $sectionParams[] = $excludeScheduleId;
    $sectionStmt = $pdo->prepare($sectionSql);
    $sectionStmt->execute($sectionParams);
    foreach ($sectionStmt->fetchAll() as $row) {
        $conflicts[] = sprintf(
            'Section Conflict: this section already has %s scheduled on %s %s-%s.',
            $row['subject_title'], $row['day_of_week'], format_time12($row['time_start']), format_time12($row['time_end'])
        );
    }

    return $conflicts;
}

/**
 * A teacher's full teaching load for a school year: every class_schedules
 * row for subjects/sections they're assigned to, in day/time order.
 */
function get_teaching_load(PDO $pdo, $teacherId, $syId) {
    $stmt = $pdo->prepare("SELECT cs.*, sub.subject_code, sub.subject_title, sec.section_name, g.grade_name, r.room_name
        FROM class_schedules cs
        JOIN subjects sub ON sub.subject_id = cs.subject_id
        JOIN sections sec ON sec.section_id = cs.section_id
        LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
        LEFT JOIN rooms r ON r.room_id = cs.room_id
        WHERE cs.teacher_id = ? AND cs.sy_id = ? AND cs.status = 'Active'
        ORDER BY FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cs.time_start");
    $stmt->execute([$teacherId, $syId]);
    return $stmt->fetchAll();
}

/**
 * A room's full weekly schedule for a school year.
 */
function get_room_schedule(PDO $pdo, $roomId, $syId) {
    $stmt = $pdo->prepare("SELECT cs.*, sub.subject_code, sub.subject_title, sec.section_name, g.grade_name,
            CONCAT(t.firstname, ' ', t.lastname) AS teacher_name
        FROM class_schedules cs
        JOIN subjects sub ON sub.subject_id = cs.subject_id
        JOIN sections sec ON sec.section_id = cs.section_id
        LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
        JOIN teachers t ON t.teacher_id = cs.teacher_id
        WHERE cs.room_id = ? AND cs.sy_id = ? AND cs.status = 'Active'
        ORDER BY FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cs.time_start");
    $stmt->execute([$roomId, $syId]);
    return $stmt->fetchAll();
}

/**
 * Check whether a room is free for a given day/time range (used by the
 * Room Assignment module's "Check Availability" action - a lighter,
 * read-only sibling of check_schedule_conflicts()'s room check).
 * Returns the list of conflicting class_schedules rows (empty = available).
 */
function check_room_availability(PDO $pdo, $roomId, $syId, $dayOfWeek, $timeStart, $timeEnd, $excludeScheduleId = null) {
    $sql = "SELECT cs.*, sub.subject_title, sec.section_name
        FROM class_schedules cs
        JOIN subjects sub ON sub.subject_id = cs.subject_id
        JOIN sections sec ON sec.section_id = cs.section_id
        WHERE cs.room_id = ? AND cs.sy_id = ? AND cs.day_of_week = ? AND cs.status = 'Active'
          AND (cs.time_start < ? AND cs.time_end > ?)";
    $params = [$roomId, $syId, $dayOfWeek, $timeEnd, $timeStart];
    if ($excludeScheduleId) {
        $sql .= ' AND cs.schedule_id != ?';
        $params[] = $excludeScheduleId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * A section's full master schedule for a school year, grouped by day -
 * used by academic/section_schedule.php, the Student Portal's Class
 * Schedule page, and printable views. This is also exactly what a
 * newly-enrolled student automatically inherits once students.section_id
 * is set - no per-student assignment step exists anywhere in the system.
 */
function get_section_schedule(PDO $pdo, $sectionId, $syId) {
    $stmt = $pdo->prepare("SELECT cs.*, sub.subject_code, sub.subject_title, sub.units, r.room_name,
            CONCAT(t.firstname, ' ', t.lastname) AS teacher_name
        FROM class_schedules cs
        JOIN subjects sub ON sub.subject_id = cs.subject_id
        JOIN teachers t ON t.teacher_id = cs.teacher_id
        LEFT JOIN rooms r ON r.room_id = cs.room_id
        WHERE cs.section_id = ? AND cs.sy_id = ? AND cs.status = 'Active'
        ORDER BY FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), cs.time_start");
    $stmt->execute([$sectionId, $syId]);
    $rows = $stmt->fetchAll();

    $byDay = [];
    foreach (DAYS_OF_WEEK as $day) { $byDay[$day] = []; }
    foreach ($rows as $row) { $byDay[$row['day_of_week']][] = $row; }
    return $byDay;
}

/**
 * Prevent duplicate teacher_assignments (same teacher+subject+section+SY).
 * Returns true if this exact assignment already exists.
 */
function teacher_assignment_exists(PDO $pdo, $teacherId, $subjectId, $sectionId, $syId, $excludeId = null) {
    $sql = 'SELECT teacher_assignment_id FROM teacher_assignments
        WHERE teacher_id = ? AND subject_id = ? AND section_id = ? AND sy_id = ?';
    $params = [$teacherId, $subjectId, $sectionId, $syId];
    if ($excludeId) {
        $sql .= ' AND teacher_assignment_id != ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}
