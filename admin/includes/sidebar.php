<?php
$current = basename($_SERVER['PHP_SELF']);
// Everyone who logs into the admin panel today is "Administrator" unless
// explicitly demoted to "Registrar" (see database/migration_2026_07_academic_management.sql).
//
// Registrar Module (2026-07): the Registrar's sidebar below follows the
// exact module order specified for the role - Dashboard, Student
// Management, Enrollment, Section Assignment, Student Accounts, Document
// Verification, Class Schedule (View Only), Reports, Notifications,
// Announcements, Profile, Change Password, Logout. Registrar has NO
// access to system administration, system settings, user management, or
// class schedule/section/teacher CRUD - those pages are grouped in the
// "System Administration" block further down and only render for
// Administrator. Section Assignment (assigning a student to an existing
// section) is intentionally separate from Sections (creating/editing the
// section entity itself, which stays Administrator-only).
$isAdministrator = ($_SESSION['admin_role'] ?? 'Administrator') === 'Administrator';
?>
<nav class="admin-sidebar">
    <ul>
        <li class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>"><a href="dashboard.php">Dashboard</a></li>
        <li class="<?php echo $current === 'students.php' ? 'active' : ''; ?>"><a href="students.php">Student Management</a></li>
        <li class="<?php echo $current === 'enrollment_requests.php' || $current === 'enrollment_view.php' ? 'active' : ''; ?>"><a href="enrollment_requests.php">Enrollment</a></li>
        <li class="<?php echo $current === 'section_assignment.php' ? 'active' : ''; ?>"><a href="section_assignment.php">Section Assignment</a></li>
        <li class="<?php echo $current === 'student_accounts.php' ? 'active' : ''; ?>"><a href="student_accounts.php">Student Accounts</a></li>
        <li class="<?php echo $current === 'document_verification.php' || $current === 'document_verification_view.php' ? 'active' : ''; ?>"><a href="document_verification.php">Document Verification</a></li>
        <li class="<?php echo $current === 'schedule_view.php' ? 'active' : ''; ?>"><a href="schedule_view.php">Class Schedule (View Only)</a></li>
        <li class="<?php echo $current === 'reports.php' || $current === 'reports_print.php' ? 'active' : ''; ?>"><a href="reports.php">Reports</a></li>
        <li class="<?php echo $current === 'notifications_log.php' ? 'active' : ''; ?>"><a href="notifications_log.php">Notifications</a></li>
        <li class="<?php echo $current === 'announcements.php' ? 'active' : ''; ?>"><a href="announcements.php">Announcements</a></li>

        <?php if ($isAdministrator): ?>
            <li class="nav-heading">System Administration</li>
            <li class="<?php echo $current === 'sections.php' ? 'active' : ''; ?>"><a href="sections.php">Sections</a></li>
            <li class="<?php echo $current === 'grade_levels.php' ? 'active' : ''; ?>"><a href="grade_levels.php">Grade Levels</a></li>
            <li class="<?php echo $current === 'strands.php' ? 'active' : ''; ?>"><a href="strands.php">Strand Management</a></li>
            <li class="<?php echo $current === 'school_year.php' ? 'active' : ''; ?>"><a href="school_year.php">School Year</a></li>
            <li class="<?php echo $current === 'schools_import.php' ? 'active' : ''; ?>"><a href="schools_import.php">School Directory</a></li>
            <li class="<?php echo $current === 'requirements.php' ? 'active' : ''; ?>"><a href="requirements.php">Requirements Management</a></li>
            <li class="<?php echo in_array($current, ['about_content.php', 'about_core_values.php', 'about_facilities.php', 'about_gallery.php', 'about_downloads.php'], true) ? 'active' : ''; ?>"><a href="about_content.php">About SABNAHIS</a></li>
            <li class="<?php echo $current === 'teachers.php' || $current === 'teacher_add.php' || $current === 'teacher_edit.php' || $current === 'teacher_view.php' ? 'active' : ''; ?>"><a href="teachers.php">Teacher Management</a></li>
            <li class="<?php echo $current === 'teacher_assignment_view.php' ? 'active' : ''; ?>"><a href="teacher_assignment_view.php">Teacher Assignment (View)</a></li>
            <li class="<?php echo $current === 'academic_overview.php' ? 'active' : ''; ?>"><a href="academic_overview.php">Academic Oversight</a></li>
            <li class="<?php echo $current === 'academic_accounts.php' ? 'active' : ''; ?>"><a href="academic_accounts.php">Academic Accounts</a></li>
            <li class="<?php echo $current === 'admin_accounts.php' ? 'active' : ''; ?>"><a href="admin_accounts.php">Admin Panel Accounts</a></li>
        <?php endif; ?>

        <li class="<?php echo $current === 'settings.php' ? 'active' : ''; ?>"><a href="settings.php#profile-panel">Profile</a></li>
        <li class="<?php echo $current === 'settings.php' ? 'active' : ''; ?>"><a href="settings.php#password-panel">Change Password</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
