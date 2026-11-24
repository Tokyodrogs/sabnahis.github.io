<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
$currentSY = $pdo->query('SELECT school_year, status FROM school_year WHERE is_current = 1 LIMIT 1')->fetch();
$announcements = $pdo->query('SELECT * FROM announcements ORDER BY date_posted DESC LIMIT 5')->fetchAll();

require_once __DIR__ . '/includes/site_header.php';
?>
<section class="hero">
    <h1><span data-i18n="home.welcome"><?php echo clean(t('home.welcome')); ?></span> <?php echo SITE_SHORT_NAME; ?></h1>
    <p>Sablayan National Comprehensive High School &mdash; <span data-i18n="home.tagline"><?php echo clean(t('home.tagline')); ?></span></p>
    <?php if ($currentSY): ?>
        <p class="hero-sy">
            School Year <strong><?php echo clean($currentSY['school_year']); ?></strong>
            <span data-i18n="home.enrollment_status_label"><?php echo clean(t('home.enrollment_status_label')); ?></span>
            <strong data-i18n="<?php echo $currentSY['status'] === 'Open' ? 'home.open' : 'home.closed'; ?>"><?php echo $currentSY['status'] === 'Open' ? clean(t('home.open')) : clean(t('home.closed')); ?></strong>.
        </p>
    <?php endif; ?>
    <?php if ($currentSY && $currentSY['status'] === 'Open'): ?>
        <a href="enroll.php" class="btn-cta" data-i18n="home.start_enrollment"><?php echo clean(t('home.start_enrollment')); ?></a>
    <?php else: ?>
        <p class="muted" data-i18n="home.check_back"><?php echo clean(t('home.check_back')); ?></p>
    <?php endif; ?>
</section>

<section class="section" id="announcements">
    <h2 data-i18n="home.announcements_title"><?php echo clean(t('home.announcements_title')); ?></h2>
    <?php if (empty($announcements)): ?>
        <p class="muted" data-i18n="home.no_announcements"><?php echo clean(t('home.no_announcements')); ?></p>
    <?php else: ?>
        <div class="announcement-cards">
            <?php foreach ($announcements as $a): ?>
                <div class="announcement-card">
                    <?php if (!empty($a['image'])): ?>
                        <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($a['image']); ?>" alt="<?php echo clean($a['title']); ?>" class="announcement-card-image">
                    <?php endif; ?>
                    <h3><?php echo clean($a['title']); ?></h3>
                    <p class="muted"><?php echo date('F d, Y', strtotime($a['date_posted'])); ?></p>
                    <p><?php echo nl2br(clean($a['content'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <h2 data-i18n="home.requirements_title"><?php echo clean(t('home.requirements_title')); ?></h2>
    <ul class="req-list">
        <li data-i18n="home.req_psa"><?php echo clean(t('home.req_psa')); ?></li>
        <li data-i18n="home.req_report_card"><?php echo clean(t('home.req_report_card')); ?></li>
        <li data-i18n="home.req_good_moral"><?php echo clean(t('home.req_good_moral')); ?></li>
        <li data-i18n="home.req_photo"><?php echo clean(t('home.req_photo')); ?></li>
    </ul>
</section>

<section class="section">
    <h2 data-i18n="home.contact_title"><?php echo clean(t('home.contact_title')); ?></h2>
    <p><span data-i18n="home.contact_body"><?php echo clean(t('home.contact_body')); ?></span></p>
</section>
<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
