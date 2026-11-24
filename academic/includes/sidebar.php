<?php $current = basename($_SERVER['PHP_SELF']); ?>
<nav class="admin-sidebar">
    <ul>
        <li class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>"><a href="dashboard.php">Academic Dashboard</a></li>
        <li class="<?php echo in_array($current, ['class_schedule.php','schedule_add.php','schedule_edit.php']) ? 'active' : ''; ?>"><a href="class_schedule.php">Class Schedule</a></li>
        <li class="<?php echo $current === 'teacher_assignment.php' ? 'active' : ''; ?>"><a href="teacher_assignment.php">Teacher Assignment</a></li>
        <li class="<?php echo $current === 'room_assignment.php' ? 'active' : ''; ?>"><a href="room_assignment.php">Room Assignment</a></li>
        <li class="<?php echo $current === 'subject_assignment.php' ? 'active' : ''; ?>"><a href="subject_assignment.php">Subject Assignment</a></li>
        <li class="<?php echo $current === 'section_schedule.php' ? 'active' : ''; ?>"><a href="section_schedule.php">Section Schedule</a></li>
        <li class="<?php echo $current === 'conflict_checker.php' ? 'active' : ''; ?>"><a href="conflict_checker.php">Schedule Conflict Checker</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
