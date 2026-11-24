<!DOCTYPE html>
<html lang="<?php echo clean(get_current_language()); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? clean($pageTitle) . ' - Student Portal | ' . SITE_SHORT_NAME : SITE_SHORT_NAME . ' Student Portal'; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/admin.css'); ?>">
<?php echo render_language_switcher_scripts(); ?>
</head>
<body>
<div class="admin-wrapper">
<header class="admin-topbar">
    <div class="topbar-title"><?php echo SITE_SHORT_NAME; ?> &mdash; <span data-i18n="student.portal_title"><?php echo clean(t('student.portal_title')); ?></span></div>
    <div class="topbar-user">
        <?php echo render_language_switcher(); ?>
        <span><span data-i18n="student.greeting"><?php echo clean(t('student.greeting')); ?></span> <?php echo clean($_SESSION['student_fullname'] ?? 'Student'); ?></span>
        <a href="logout.php" class="btn-logout" data-i18n="student.logout"><?php echo clean(t('student.logout')); ?></a>
    </div>
</header>
<div class="admin-body">
