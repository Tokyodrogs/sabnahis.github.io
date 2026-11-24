<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About SABNAHIS';

// About SABNAHIS module (2026-08). Every section below is content-managed
// from the Administrator dashboard (admin/about_content.php and friends);
// if the site owner hasn't imported database/migration_2026_08_about_module.sql
// yet, fall back to the original static page content instead of erroring.
$aboutTableMissing = false;
$about = [];
try {
    $about = $pdo->query('SELECT * FROM about_school WHERE id = 1')->fetch() ?: [];
} catch (PDOException $e) {
    $aboutTableMissing = true;
}

$coreValues = [];
$facilities = [];
$gallery = [];
$downloads = [];
if (!$aboutTableMissing) {
    $coreValues = $pdo->query("SELECT * FROM about_core_values WHERE status = 'Active' ORDER BY display_order, core_value_id")->fetchAll();
    $facilities = $pdo->query("SELECT * FROM about_facilities WHERE status = 'Active' ORDER BY display_order, facility_id")->fetchAll();
    $gallery = $pdo->query("SELECT * FROM about_gallery WHERE status = 'Active' ORDER BY display_order, gallery_id")->fetchAll();
    $downloads = $pdo->query("SELECT * FROM about_downloads WHERE status = 'Active' ORDER BY display_order, download_id")->fetchAll();
}

$mapSrc = about_map_src($about['map_embed'] ?? null);
$hasSocial = !empty($about['social_facebook']) || !empty($about['social_messenger']) || !empty($about['social_youtube']) || !empty($about['social_instagram']) || !empty($about['social_tiktok']);

require_once __DIR__ . '/includes/site_header.php';
?>

<!-- Section 1: Hero Banner -->
<section class="about-hero" <?php if (!empty($about['banner_image'])): ?>style="background-image:url('<?php echo BASE_URL . 'uploads/' . urlencode($about['banner_image']); ?>');"<?php endif; ?>>
    <div class="about-hero-overlay">
        <?php if (!empty($about['logo_image'])): ?>
            <img src="<?php echo BASE_URL . 'uploads/' . urlencode($about['logo_image']); ?>" alt="School Logo" class="about-hero-logo">
        <?php endif; ?>
        <h1><?php echo clean($about['school_name'] ?? SITE_NAME); ?></h1>
        <?php if (!empty($about['school_motto'])): ?><p class="about-hero-motto"><?php echo clean($about['school_motto']); ?></p><?php endif; ?>
        <p class="about-hero-desc">
            <?php echo clean($about['short_description'] ?? 'Sablayan National Comprehensive High School (SABNAHIS) is a public integrated school located in Sablayan, Occidental Mindoro, offering Junior High School and Senior High School education.'); ?>
        </p>
    </div>
</section>

<!-- Section 2: About the School -->
<section class="section about-content-block">
    <h2><?php echo clean($about['about_title'] ?? 'About SABNAHIS'); ?></h2>
    <?php if (!empty($about['about_description'])): ?>
        <div class="rich-content"><?php echo $about['about_description']; ?></div>
    <?php else: ?>
        <p>Sablayan National Comprehensive High School (SABNAHIS) is a public integrated school located in Sablayan,
           Occidental Mindoro, offering Junior High School and Senior High School education with a focus on
           agricultural and horticultural technical-vocational programs alongside the standard academic tracks.</p>
        <p>This Local Web-Based Enrollment System was developed to streamline the school's enrollment process, allowing
           prospective students to submit their enrollment requirements online while giving the school administrator a
           centralized platform to review, approve, and manage student records.</p>
    <?php endif; ?>
</section>

<!-- Section 3: School History -->
<?php if (!empty($about['history_content']) || !empty($about['history_image'])): ?>
<section class="section about-history" id="history">
    <h2><?php echo clean($about['history_title'] ?? 'Our History'); ?></h2>
    <div class="about-history-row">
        <?php if (!empty($about['history_image'])): ?>
            <img src="<?php echo BASE_URL . 'uploads/' . urlencode($about['history_image']); ?>" alt="School history" class="about-history-image">
        <?php endif; ?>
        <div class="rich-content"><?php echo $about['history_content'] ?? ''; ?></div>
    </div>
</section>
<?php endif; ?>

<!-- Sections 4-5: Vision / Mission -->
<section class="section">
    <div class="about-vm-grid">
        <div class="about-vm-card" id="vision">
            <h2>Vision</h2>
            <div class="rich-content">
                <?php echo $about['vision'] ?? '<p>We dream of Filipinos who passionately love their country and whose values and competencies enable them to realize their full potential and contribute meaningfully to building the nation.</p>'; ?>
            </div>
        </div>
        <div class="about-vm-card" id="mission">
            <h2>Mission</h2>
            <div class="rich-content">
                <?php echo $about['mission'] ?? '<p>To provide quality, accessible, and relevant education that develops competent, values-driven, and agriculturally-skilled learners ready to contribute to their community and the nation.</p>'; ?>
            </div>
        </div>
    </div>
</section>

<!-- Section 6: Core Values -->
<?php if (!empty($coreValues)): ?>
<section class="section" id="core-values">
    <h2>Core Values</h2>
    <div class="about-card-grid-public">
        <?php foreach ($coreValues as $cv): ?>
            <div class="about-value-card">
                <?php if (!empty($cv['icon'])): ?><div class="about-value-icon"><?php echo clean($cv['icon']); ?></div><?php endif; ?>
                <h3><?php echo clean($cv['title']); ?></h3>
                <?php if (!empty($cv['description'])): ?><p><?php echo clean($cv['description']); ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Section 7: School Facilities -->
<?php if (!empty($facilities)): ?>
<section class="section" id="facilities">
    <h2>School Facilities</h2>
    <div class="about-card-grid-public">
        <?php foreach ($facilities as $f): ?>
            <div class="about-facility-card">
                <?php if (!empty($f['image'])): ?>
                    <img src="<?php echo BASE_URL . 'uploads/' . urlencode($f['image']); ?>" alt="<?php echo clean($f['name']); ?>">
                <?php endif; ?>
                <div class="about-facility-card-body">
                    <h3><?php echo clean($f['name']); ?></h3>
                    <?php if (!empty($f['description'])): ?><p><?php echo clean($f['description']); ?></p><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Section 8: Photo Gallery -->
<?php if (!empty($gallery)): ?>
<section class="section" id="gallery">
    <h2>Photo Gallery</h2>
    <div class="about-gallery-public-grid">
        <?php foreach ($gallery as $g): ?>
            <div class="about-gallery-public-item">
                <img src="<?php echo BASE_URL . 'uploads/' . urlencode($g['image']); ?>"
                     alt="<?php echo clean($g['title']); ?>"
                     class="js-lightbox-trigger"
                     data-caption="<?php echo clean(($g['title'] ? $g['title'] . ' - ' : '') . $g['caption']); ?>">
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Section 9: Principal's Message -->
<?php
    // Only shown when explicitly Active - Inactive/Draft keep the content
    // saved but off the public page (see admin/about_content.php Status field).
    $principalVisible = ($about['principal_status'] ?? 'Active') === 'Active'
        && (!empty($about['principal_name']) || !empty($about['principal_message']));
?>
<?php if ($principalVisible): ?>
<section class="section" id="principal">
    <h2>Principal's Message</h2>
    <div class="about-principal-card">
        <?php if (!empty($about['principal_photo'])): ?>
            <img src="<?php echo BASE_URL . 'uploads/' . urlencode($about['principal_photo']); ?>" alt="<?php echo clean($about['principal_name']); ?>" class="about-principal-photo">
        <?php else: ?>
            <div class="about-principal-photo about-principal-photo-placeholder" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9V22h19.6v-2.6c0-3.3-6.5-4.9-9.8-4.9z"/></svg>
            </div>
        <?php endif; ?>
        <div class="about-principal-body">
            <blockquote class="about-principal-quote">
                <div class="rich-content"><?php echo $about['principal_message'] ?? ''; ?></div>
            </blockquote>
            <div class="about-principal-signoff">
                <?php if (!empty($about['principal_signature'])): ?>
                    <img src="<?php echo BASE_URL . 'uploads/' . urlencode($about['principal_signature']); ?>" alt="Signature" class="about-principal-signature">
                <?php endif; ?>
                <p class="about-principal-name"><?php echo clean(trim(($about['principal_title'] ?? '') . ' ' . ($about['principal_name'] ?? ''))); ?></p>
                <?php if (!empty($about['principal_position'])): ?><p class="muted about-principal-position"><?php echo clean($about['principal_position']); ?></p><?php endif; ?>
                <?php if (!empty($about['principal_email']) || !empty($about['principal_contact'])): ?>
                    <p class="muted about-principal-contact">
                        <?php echo clean($about['principal_email'] ?? ''); ?>
                        <?php if (!empty($about['principal_email']) && !empty($about['principal_contact'])): ?> &middot; <?php endif; ?>
                        <?php echo clean($about['principal_contact'] ?? ''); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section 14: Downloadable Files -->
<?php if (!empty($downloads)): ?>
<section class="section">
    <h2>Downloadable Files</h2>
    <ul class="about-downloads-list">
        <?php foreach ($downloads as $d): ?>
            <li><a href="<?php echo BASE_URL . 'uploads/' . urlencode($d['file_path']); ?>" target="_blank">&#128196; <?php echo clean($d['title']); ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<!-- Section 10-11: Contact Information + Google Map -->
<section class="section" id="contact">
    <h2>Contact Information</h2>
    <div class="about-contact-row">
        <ul class="about-contact-list">
            <?php if (!empty($about['contact_address'])): ?><li><strong>Address:</strong> <?php echo clean($about['contact_address']); ?></li><?php endif; ?>
            <?php if (!empty($about['contact_telephone'])): ?><li><strong>Telephone:</strong> <?php echo clean($about['contact_telephone']); ?></li><?php endif; ?>
            <?php if (!empty($about['contact_mobile'])): ?><li><strong>Mobile:</strong> <?php echo clean($about['contact_mobile']); ?></li><?php endif; ?>
            <?php if (!empty($about['contact_email'])): ?><li><strong>Email:</strong> <?php echo clean($about['contact_email']); ?></li><?php endif; ?>
            <?php if (!empty($about['contact_office_hours'])): ?><li><strong>Office Hours:</strong> <?php echo clean($about['contact_office_hours']); ?></li><?php endif; ?>
            <?php if (!empty($about['contact_website'])): ?><li><strong>Website:</strong> <?php echo clean($about['contact_website']); ?></li><?php endif; ?>
            <?php if (empty($about['contact_address']) && empty($about['contact_email'])): ?>
                <li>For inquiries, please visit the school registrar's office or reach us through the <a href="contact.php">Contact Us</a> page.</li>
            <?php endif; ?>
        </ul>
        <?php if ($mapSrc): ?>
            <iframe class="about-map" src="<?php echo clean($mapSrc); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php endif; ?>
    </div>
</section>

<!-- Section 12: Social Media -->
<?php if ($hasSocial): ?>
<section class="section">
    <h2>Follow Us</h2>
    <div class="about-social-row">
        <?php if (!empty($about['social_facebook'])): ?><a href="<?php echo clean($about['social_facebook']); ?>" target="_blank" rel="noopener" class="about-social-link">Facebook</a><?php endif; ?>
        <?php if (!empty($about['social_messenger'])): ?><a href="<?php echo clean($about['social_messenger']); ?>" target="_blank" rel="noopener" class="about-social-link">Messenger</a><?php endif; ?>
        <?php if (!empty($about['social_youtube'])): ?><a href="<?php echo clean($about['social_youtube']); ?>" target="_blank" rel="noopener" class="about-social-link">YouTube</a><?php endif; ?>
        <?php if (!empty($about['social_instagram'])): ?><a href="<?php echo clean($about['social_instagram']); ?>" target="_blank" rel="noopener" class="about-social-link">Instagram</a><?php endif; ?>
        <?php if (!empty($about['social_tiktok'])): ?><a href="<?php echo clean($about['social_tiktok']); ?>" target="_blank" rel="noopener" class="about-social-link">TikTok</a><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<div id="aboutLightbox" class="about-lightbox" hidden>
    <button type="button" class="about-lightbox-close" id="aboutLightboxClose">&times;</button>
    <img id="aboutLightboxImg" src="" alt="">
    <p id="aboutLightboxCaption"></p>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/about_public.js"></script>
<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
