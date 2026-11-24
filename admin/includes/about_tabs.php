<?php
// Shared sub-nav for the 5 About SABNAHIS admin pages. Included at the top
// of each about_*.php page's <main> so the Administrator can jump between
// them without cluttering the main sidebar with 5 separate entries.
$aboutCurrent = basename($_SERVER['PHP_SELF']);
?>
<div class="about-tabs">
    <a href="about_content.php" class="<?php echo $aboutCurrent === 'about_content.php' ? 'active' : ''; ?>">Page Content</a>
    <a href="about_core_values.php" class="<?php echo $aboutCurrent === 'about_core_values.php' ? 'active' : ''; ?>">Core Values</a>
    <a href="about_facilities.php" class="<?php echo $aboutCurrent === 'about_facilities.php' ? 'active' : ''; ?>">Facilities</a>
    <a href="about_gallery.php" class="<?php echo $aboutCurrent === 'about_gallery.php' ? 'active' : ''; ?>">Photo Gallery</a>
    <a href="about_downloads.php" class="<?php echo $aboutCurrent === 'about_downloads.php' ? 'active' : ''; ?>">Downloadable Files</a>
    <a href="<?php echo BASE_URL; ?>about.php" target="_blank" class="about-tabs-preview">Preview Public Page &#8599;</a>
</div>
