<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/site_header.php';
?>
<section class="section">
    <h1 data-i18n="contact.title"><?php echo clean(t('contact.title')); ?></h1>
    <p data-i18n="contact.intro"><?php echo clean(t('contact.intro')); ?></p>
    <table class="detail-table">
        <tr><th data-i18n="contact.school"><?php echo clean(t('contact.school')); ?></th><td><?php echo SITE_NAME; ?></td></tr>
        <tr id="location"><th data-i18n="contact.address"><?php echo clean(t('contact.address')); ?></th><td>Sablayan, Occidental Mindoro, Philippines</td></tr>
        <tr id="office-hours"><th data-i18n="contact.office_hours"><?php echo clean(t('contact.office_hours')); ?></th><td data-i18n="contact.office_hours_value"><?php echo clean(t('contact.office_hours_value')); ?></td></tr>
        <tr><th data-i18n="contact.email"><?php echo clean(t('contact.email')); ?></th><td>registrar@sabnahis.edu.ph</td></tr>
        <tr><th data-i18n="contact.contact_number"><?php echo clean(t('contact.contact_number')); ?></th><td data-i18n="contact.contact_number_tbd"><?php echo clean(t('contact.contact_number_tbd')); ?></td></tr>
    </table>
</section>
<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
