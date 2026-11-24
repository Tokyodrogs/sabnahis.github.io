<?php
// Public site navigation (2026-08 redesign): multi-level dropdown menu
// replacing the old flat link list, built with plain CSS/JS (no Bootstrap)
// so it inherits the site's existing green/gold design instead of fighting
// a framework reset across every public page that includes this header.
// Submenu links only point at pages/sections that actually exist - see
// assets/js/site_nav.js for the dropdown/hamburger behavior.
$currentFile = basename($_SERVER['PHP_SELF']);
$navActive = [
    'home'       => in_array($currentFile, ['index.php'], true),
    'about'      => in_array($currentFile, ['about.php'], true),
    'enrollment' => in_array($currentFile, ['enroll.php', 'guidelines.php'], true),
    'contact'    => in_array($currentFile, ['contact.php'], true),
];
?>
<!DOCTYPE html>
<html lang="<?php echo clean(get_current_language()); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? clean($pageTitle) . ' - ' . SITE_SHORT_NAME : SITE_SHORT_NAME; ?></title>
<link rel="stylesheet" href="<?php echo versioned_asset('assets/css/style.css'); ?>">
<script src="<?php echo BASE_URL; ?>assets/js/display_settings.js"></script>
<?php echo render_language_switcher_scripts(); ?>
</head>
<body>
<header class="site-header" id="siteHeader">
    <div class="site-header-inner">
        <div class="brand">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="SABNAHIS Logo" class="site-logo">
            <div>
                <div class="brand-title"><?php echo SITE_SHORT_NAME; ?></div>
                <div class="brand-subtitle">Sablayan National Comprehensive High School</div>
            </div>
        </div>

        <button type="button" class="nav-hamburger" id="navHamburger" aria-expanded="false" aria-controls="siteNav" aria-label="Toggle navigation menu">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" id="siteNav">
            <ul class="nav-menu">
                <li class="nav-item has-dropdown <?php echo $navActive['home'] ? 'active' : ''; ?>">
                    <button type="button" class="nav-link dropdown-toggle" aria-expanded="false"><span data-i18n="nav.home"><?php echo clean(t('nav.home')); ?></span> <span class="caret">&#9662;</span></button>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>index.php" data-i18n="nav.home"><?php echo clean(t('nav.home')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>index.php#announcements" data-i18n="nav.announcements"><?php echo clean(t('nav.announcements')); ?></a></li>
                    </ul>
                </li>

                <li class="nav-item has-dropdown <?php echo $navActive['about'] ? 'active' : ''; ?>">
                    <button type="button" class="nav-link dropdown-toggle" aria-expanded="false"><span data-i18n="nav.about"><?php echo clean(t('nav.about')); ?></span> <span class="caret">&#9662;</span></button>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>about.php" data-i18n="nav.about_sabnahis"><?php echo clean(t('nav.about_sabnahis')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#history" data-i18n="nav.history"><?php echo clean(t('nav.history')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#vision" data-i18n="nav.vision"><?php echo clean(t('nav.vision')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#mission" data-i18n="nav.mission"><?php echo clean(t('nav.mission')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#core-values" data-i18n="nav.core_values"><?php echo clean(t('nav.core_values')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#principal" data-i18n="nav.principal"><?php echo clean(t('nav.principal')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#facilities" data-i18n="nav.facilities"><?php echo clean(t('nav.facilities')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#gallery" data-i18n="nav.gallery"><?php echo clean(t('nav.gallery')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php#contact" data-i18n="nav.contact_info"><?php echo clean(t('nav.contact_info')); ?></a></li>
                    </ul>
                </li>

                <li class="nav-item has-dropdown <?php echo $navActive['enrollment'] ? 'active' : ''; ?>">
                    <button type="button" class="nav-link dropdown-toggle" aria-expanded="false"><span data-i18n="nav.enrollment"><?php echo clean(t('nav.enrollment')); ?></span> <span class="caret">&#9662;</span></button>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>enroll.php" data-i18n="nav.enroll_now"><?php echo clean(t('nav.enroll_now')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>guidelines.php" data-i18n="nav.guidelines"><?php echo clean(t('nav.guidelines')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>guidelines.php#requirements" data-i18n="nav.requirements"><?php echo clean(t('nav.requirements')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>guidelines.php#process" data-i18n="nav.process"><?php echo clean(t('nav.process')); ?></a></li>
                    </ul>
                </li>

                <li class="nav-item has-dropdown">
                    <button type="button" class="nav-link dropdown-toggle" aria-expanded="false"><span data-i18n="nav.student_portal"><?php echo clean(t('nav.student_portal')); ?></span> <span class="caret">&#9662;</span></button>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>student/login.php" data-i18n="nav.student_login"><?php echo clean(t('nav.student_login')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>student/forgot_password.php" data-i18n="nav.forgot_password"><?php echo clean(t('nav.forgot_password')); ?></a></li>
                    </ul>
                </li>

                <li class="nav-item has-dropdown <?php echo $navActive['contact'] ? 'active' : ''; ?>">
                    <button type="button" class="nav-link dropdown-toggle" aria-expanded="false"><span data-i18n="nav.contact"><?php echo clean(t('nav.contact')); ?></span> <span class="caret">&#9662;</span></button>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>contact.php" data-i18n="nav.contact_us"><?php echo clean(t('nav.contact_us')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>contact.php#location" data-i18n="nav.location"><?php echo clean(t('nav.location')); ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>contact.php#office-hours" data-i18n="nav.office_hours"><?php echo clean(t('nav.office_hours')); ?></a></li>
                        <li><a href="mailto:registrar@sabnahis.edu.ph" data-i18n="nav.send_inquiry"><?php echo clean(t('nav.send_inquiry')); ?></a></li>
                    </ul>
                </li>
            </ul>

            <div class="nav-actions">
                <?php echo render_language_switcher(); ?>
                <?php echo render_display_settings_widget(); ?>
                <a href="<?php echo BASE_URL; ?>enroll.php" class="nav-cta" data-i18n="nav.enroll_now"><?php echo clean(t('nav.enroll_now')); ?></a>
                <a href="<?php echo BASE_URL; ?>login.php" class="nav-admin" data-i18n="nav.login"><?php echo clean(t('nav.login')); ?></a>
            </div>
        </nav>
    </div>
</header>
<main class="site-main">
