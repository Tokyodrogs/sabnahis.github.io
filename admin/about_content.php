<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// About SABNAHIS module (2026-08). Administrator-only content management
// for the public about.php page - Hero Banner, About School, History,
// Vision, Mission, Principal's Message, Contact Info, Google Map, and
// Social Media. All stored as a single row in about_school (id = 1).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('error', 'Only the Administrator can manage the About SABNAHIS page.');
    redirect('dashboard.php');
}

$pageTitle = 'About SABNAHIS - Page Content';

$aboutTableMissing = false;
try {
    $pdo->query('SELECT 1 FROM about_school LIMIT 1');
} catch (PDOException $e) {
    $aboutTableMissing = true;
}

$csrfToken = csrf_token('about_content');

if (!$aboutTableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '', 'about_content')) {
        flash('error', 'Your session has expired. Please reload the page and try again.');
        redirect('about_content.php');
    }

    $formAction = $_POST['form_action'] ?? '';
    $err = null;

    if ($formAction === 'hero') {
        $row = $pdo->query('SELECT banner_image, logo_image FROM about_school WHERE id = 1')->fetch();

        $bannerName = handle_about_image_upload('banner_image', 'about_banner', 1920, $err);
        if ($bannerName === false) {
            flash('error', $err);
        } else {
            if (($_POST['remove_banner'] ?? '') === '1' && !$bannerName) {
                if (!empty($row['banner_image']) && is_file(UPLOAD_DIR . $row['banner_image'])) unlink(UPLOAD_DIR . $row['banner_image']);
                $bannerName = null;
                $pdo->prepare('UPDATE about_school SET banner_image = NULL WHERE id = 1')->execute();
            } elseif ($bannerName) {
                if (!empty($row['banner_image']) && is_file(UPLOAD_DIR . $row['banner_image'])) unlink(UPLOAD_DIR . $row['banner_image']);
                $pdo->prepare('UPDATE about_school SET banner_image = ? WHERE id = 1')->execute([$bannerName]);
            }

            $logoName = handle_about_image_upload('logo_image', 'about_logo', 500, $err);
            if ($logoName === false) {
                flash('error', $err);
            } else {
                if (($_POST['remove_logo'] ?? '') === '1' && !$logoName) {
                    if (!empty($row['logo_image']) && is_file(UPLOAD_DIR . $row['logo_image'])) unlink(UPLOAD_DIR . $row['logo_image']);
                    $pdo->prepare('UPDATE about_school SET logo_image = NULL WHERE id = 1')->execute();
                } elseif ($logoName) {
                    if (!empty($row['logo_image']) && is_file(UPLOAD_DIR . $row['logo_image'])) unlink(UPLOAD_DIR . $row['logo_image']);
                    $pdo->prepare('UPDATE about_school SET logo_image = ? WHERE id = 1')->execute([$logoName]);
                }

                $pdo->prepare('UPDATE about_school SET school_name = ?, school_motto = ?, short_description = ? WHERE id = 1')
                    ->execute([trim($_POST['school_name'] ?? ''), trim($_POST['school_motto'] ?? ''), trim($_POST['short_description'] ?? '')]);
                audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_hero', 'Updated Hero Banner section');
                flash('success', 'Hero Banner updated.');
            }
        }
        redirect('about_content.php#hero-panel');
    }

    if ($formAction === 'about') {
        $pdo->prepare('UPDATE about_school SET about_title = ?, about_description = ? WHERE id = 1')
            ->execute([trim($_POST['about_title'] ?? ''), $_POST['about_description'] ?? '']);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_about', 'Updated About the School section');
        flash('success', 'About the School section updated.');
        redirect('about_content.php#about-panel');
    }

    if ($formAction === 'history') {
        $row = $pdo->query('SELECT history_image FROM about_school WHERE id = 1')->fetch();
        $imgName = handle_about_image_upload('history_image', 'about_history', 1400, $err);
        if ($imgName === false) {
            flash('error', $err);
        } else {
            if (($_POST['remove_history_image'] ?? '') === '1' && !$imgName) {
                if (!empty($row['history_image']) && is_file(UPLOAD_DIR . $row['history_image'])) unlink(UPLOAD_DIR . $row['history_image']);
                $pdo->prepare('UPDATE about_school SET history_image = NULL WHERE id = 1')->execute();
            } elseif ($imgName) {
                if (!empty($row['history_image']) && is_file(UPLOAD_DIR . $row['history_image'])) unlink(UPLOAD_DIR . $row['history_image']);
                $pdo->prepare('UPDATE about_school SET history_image = ? WHERE id = 1')->execute([$imgName]);
            }
            $pdo->prepare('UPDATE about_school SET history_title = ?, history_content = ? WHERE id = 1')
                ->execute([trim($_POST['history_title'] ?? ''), $_POST['history_content'] ?? '']);
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_history', 'Updated School History section');
            flash('success', 'School History updated.');
        }
        redirect('about_content.php#history-panel');
    }

    if ($formAction === 'vision') {
        $pdo->prepare('UPDATE about_school SET vision = ? WHERE id = 1')->execute([$_POST['vision'] ?? '']);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_vision', 'Updated Vision statement');
        flash('success', 'Vision updated.');
        redirect('about_content.php#vision-panel');
    }

    if ($formAction === 'mission') {
        $pdo->prepare('UPDATE about_school SET mission = ? WHERE id = 1')->execute([$_POST['mission'] ?? '']);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_mission', 'Updated Mission statement');
        flash('success', 'Mission updated.');
        redirect('about_content.php#mission-panel');
    }

    if ($formAction === 'principal') {
        $row = $pdo->query('SELECT principal_photo, principal_signature FROM about_school WHERE id = 1')->fetch();

        // Photo: 5MB cap, square-cropped client-side by Cropper.js before
        // upload (see assets/js/about_principal_admin.js) then auto-resized
        // server-side same as every other About image.
        $photoName = handle_about_image_upload('principal_photo', 'about_principal', 600, $err, 5 * 1024 * 1024);
        if ($photoName === false) {
            flash('error', $err);
        } else {
            if (($_POST['remove_principal_photo'] ?? '') === '1' && !$photoName) {
                if (!empty($row['principal_photo']) && is_file(UPLOAD_DIR . $row['principal_photo'])) unlink(UPLOAD_DIR . $row['principal_photo']);
                $pdo->prepare('UPDATE about_school SET principal_photo = NULL WHERE id = 1')->execute();
            } elseif ($photoName) {
                // Old file is only removed AFTER the new upload has already
                // succeeded above, so a failed/invalid replacement never
                // costs the admin their existing photo.
                if (!empty($row['principal_photo']) && is_file(UPLOAD_DIR . $row['principal_photo'])) unlink(UPLOAD_DIR . $row['principal_photo']);
                $pdo->prepare('UPDATE about_school SET principal_photo = ? WHERE id = 1')->execute([$photoName]);
            }

            // Signature: 2MB cap, no cropping (transparent PNGs are usually
            // already the right shape - a forced square crop would clip them).
            $sigName = handle_about_image_upload('principal_signature', 'about_signature', 500, $err, 2 * 1024 * 1024);
            if ($sigName === false) {
                flash('error', $err);
            } else {
                if (($_POST['remove_principal_signature'] ?? '') === '1' && !$sigName) {
                    if (!empty($row['principal_signature']) && is_file(UPLOAD_DIR . $row['principal_signature'])) unlink(UPLOAD_DIR . $row['principal_signature']);
                    $pdo->prepare('UPDATE about_school SET principal_signature = NULL WHERE id = 1')->execute();
                } elseif ($sigName) {
                    if (!empty($row['principal_signature']) && is_file(UPLOAD_DIR . $row['principal_signature'])) unlink(UPLOAD_DIR . $row['principal_signature']);
                    $pdo->prepare('UPDATE about_school SET principal_signature = ? WHERE id = 1')->execute([$sigName]);
                }

                $status = $_POST['principal_status'] ?? 'Active';
                if (!in_array($status, ['Active', 'Inactive', 'Draft'], true)) $status = 'Active';

                $pdo->prepare('UPDATE about_school SET principal_name = ?, principal_title = ?, principal_position = ?,
                        principal_message = ?, principal_email = ?, principal_contact = ?, principal_status = ? WHERE id = 1')
                    ->execute([
                        trim($_POST['principal_name'] ?? ''),
                        trim($_POST['principal_title'] ?? ''),
                        trim($_POST['principal_position'] ?? ''),
                        $_POST['principal_message'] ?? '',
                        trim($_POST['principal_email'] ?? ''),
                        trim($_POST['principal_contact'] ?? ''),
                        $status,
                    ]);
                audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_principal', "Updated Principal's Message section (status: {$status})");
                flash('success', "Principal's Message saved" . ($status === 'Draft' ? ' as draft.' : ($status === 'Inactive' ? ' (hidden from the public page).' : ' and published.')));
            }
        }
        redirect('about_content.php#principal-panel');
    }

    if ($formAction === 'contact') {
        $pdo->prepare('UPDATE about_school SET contact_address = ?, contact_telephone = ?, contact_mobile = ?, contact_email = ?, contact_office_hours = ?, contact_website = ? WHERE id = 1')
            ->execute([
                trim($_POST['contact_address'] ?? ''),
                trim($_POST['contact_telephone'] ?? ''),
                trim($_POST['contact_mobile'] ?? ''),
                trim($_POST['contact_email'] ?? ''),
                trim($_POST['contact_office_hours'] ?? ''),
                trim($_POST['contact_website'] ?? ''),
            ]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_contact', 'Updated Contact Information section');
        flash('success', 'Contact Information updated.');
        redirect('about_content.php#contact-panel');
    }

    if ($formAction === 'map') {
        $rawMapInput = trim($_POST['map_embed'] ?? '');
        if ($rawMapInput === '') {
            $pdo->prepare('UPDATE about_school SET map_embed = ? WHERE id = 1')->execute([null]);
            audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_map', 'Cleared Google Map embed');
            flash('success', 'Google Map cleared.');
        } else {
            // Resolves short links (maps.app.goo.gl) and converts a plain
            // Maps page link into an embeddable one (output=embed) once,
            // here at save time - see resolve_google_map_embed()'s doc
            // comment in includes/functions.php. On failure, the previous
            // value is left untouched rather than overwritten with
            // something broken.
            $mapError = null;
            $resolvedMapEmbed = resolve_google_map_embed($rawMapInput, $mapError);
            if ($resolvedMapEmbed === null) {
                flash('error', $mapError ?: 'That Google Maps link could not be used.');
            } else {
                $pdo->prepare('UPDATE about_school SET map_embed = ? WHERE id = 1')->execute([$resolvedMapEmbed]);
                audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_map', 'Updated Google Map embed');
                flash('success', 'Google Map updated.');
            }
        }
        redirect('about_content.php#map-panel');
    }

    if ($formAction === 'social') {
        $pdo->prepare('UPDATE about_school SET social_facebook = ?, social_messenger = ?, social_youtube = ?, social_instagram = ?, social_tiktok = ? WHERE id = 1')
            ->execute([
                trim($_POST['social_facebook'] ?? ''),
                trim($_POST['social_messenger'] ?? ''),
                trim($_POST['social_youtube'] ?? ''),
                trim($_POST['social_instagram'] ?? ''),
                trim($_POST['social_tiktok'] ?? ''),
            ]);
        audit_log($pdo, 'admin', $_SESSION['admin_id'], 'about_update_social', 'Updated Social Media links');
        flash('success', 'Social Media links updated.');
        redirect('about_content.php#social-panel');
    }
}

$about = $aboutTableMissing ? [] : $pdo->query('SELECT * FROM about_school WHERE id = 1')->fetch();
$about = $about ?: [];
function av($about, $key) { return clean($about[$key] ?? ''); }

$principalPhotoInfo = about_image_stat($about['principal_photo'] ?? null);
$principalSigInfo = about_image_stat($about['principal_signature'] ?? null);
$principalTitles = ['' => '(none)', 'Dr.' => 'Dr.', 'Prof.' => 'Prof.', 'Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Ms.' => 'Ms.'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<main class="admin-main">
    <h2>About SABNAHIS</h2>
    <?php require __DIR__ . '/includes/about_tabs.php'; ?>

    <?php if ($aboutTableMissing): ?>
        <div class="alert alert-error">
            The About SABNAHIS database tables were not found. Import
            <code>database/migration_2026_08_about_module.sql</code> via phpMyAdmin before this page can be used.
        </div>
    <?php else: ?>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($err = flash('error')): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

    <!-- Section 1: Hero Banner -->
    <section class="panel" id="hero-panel">
        <h3>Hero Banner</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="form_action" value="hero">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">

            <div class="full about-image-field">
                <label>School Banner Image <span class="muted">(recommended 1920 &times; 600 px)</span></label>
                <?php if (!empty($about['banner_image'])): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($about['banner_image']); ?>" class="about-preview-banner" alt="Current banner">
                    <label class="checkbox-label"><input type="checkbox" name="remove_banner" value="1"> Remove current banner</label>
                <?php endif; ?>
                <input type="file" name="banner_image" accept=".jpg,.jpeg,.png,.webp,image/*">
            </div>

            <div class="about-image-field">
                <label>School Logo</label>
                <?php if (!empty($about['logo_image'])): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($about['logo_image']); ?>" class="about-preview-logo" alt="Current logo">
                    <label class="checkbox-label"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
                <?php endif; ?>
                <input type="file" name="logo_image" accept=".jpg,.jpeg,.png,.webp,image/*">
            </div>

            <label>School Name<input type="text" name="school_name" value="<?php echo av($about, 'school_name'); ?>" placeholder="<?php echo clean(SITE_NAME); ?>"></label>
            <label>School Motto<input type="text" name="school_motto" value="<?php echo av($about, 'school_motto'); ?>"></label>
            <label class="full">Short Description<textarea name="short_description" rows="2"><?php echo av($about, 'short_description'); ?></textarea></label>

            <div class="form-actions"><button type="submit" class="btn-primary">Save Hero Banner</button></div>
        </form>
    </section>

    <!-- Section 2: About the School -->
    <section class="panel" id="about-panel">
        <h3>About the School</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="about">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label class="full">Title<input type="text" name="about_title" value="<?php echo av($about, 'about_title'); ?>"></label>
            <label class="full">Description
                <div class="rich-editor" data-field="about_description"><?php echo $about['about_description'] ?? ''; ?></div>
                <input type="hidden" name="about_description" class="rich-editor-input">
            </label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save About the School</button></div>
        </form>
    </section>

    <!-- Section 3: School History -->
    <section class="panel" id="history-panel">
        <h3>School History</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="form_action" value="history">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label class="full">Title<input type="text" name="history_title" value="<?php echo av($about, 'history_title'); ?>"></label>
            <label class="full">History Content
                <div class="rich-editor" data-field="history_content"><?php echo $about['history_content'] ?? ''; ?></div>
                <input type="hidden" name="history_content" class="rich-editor-input">
            </label>
            <div class="full about-image-field">
                <label>Historical Image</label>
                <?php if (!empty($about['history_image'])): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($about['history_image']); ?>" class="about-preview-history" alt="Current history image">
                    <label class="checkbox-label"><input type="checkbox" name="remove_history_image" value="1"> Remove current image</label>
                <?php endif; ?>
                <input type="file" name="history_image" accept=".jpg,.jpeg,.png,.webp,image/*">
            </div>
            <div class="form-actions"><button type="submit" class="btn-primary">Save School History</button></div>
        </form>
    </section>

    <!-- Section 4: Vision -->
    <section class="panel" id="vision-panel">
        <h3>Vision</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="vision">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label class="full">
                <div class="rich-editor" data-field="vision"><?php echo $about['vision'] ?? ''; ?></div>
                <input type="hidden" name="vision" class="rich-editor-input">
            </label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save Vision</button></div>
        </form>
    </section>

    <!-- Section 5: Mission -->
    <section class="panel" id="mission-panel">
        <h3>Mission</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="mission">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label class="full">
                <div class="rich-editor" data-field="mission"><?php echo $about['mission'] ?? ''; ?></div>
                <input type="hidden" name="mission" class="rich-editor-input">
            </label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save Mission</button></div>
        </form>
    </section>

    <!-- Section 9: Principal's Message -->
    <?php
        $principalStatus = $about['principal_status'] ?? 'Active';
        $principalStatusBadge = ['Active' => 'badge-active', 'Inactive' => 'badge-inactive', 'Draft' => 'badge-pending'][$principalStatus] ?? 'badge-active';
    ?>
    <section class="panel" id="principal-panel">
        <div class="panel-header">
            <h3>Principal's Message</h3>
            <span class="badge <?php echo $principalStatusBadge; ?>"><?php echo clean($principalStatus); ?></span>
        </div>

        <form method="POST" enctype="multipart/form-data" id="principalForm">
            <input type="hidden" name="form_action" value="principal">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">

            <div class="principal-columns">
                <!-- LEFT PANEL: Photo -->
                <div class="principal-photo-card">
                    <div class="principal-photo-preview" id="principalPhotoPreview">
                        <?php if (!empty($about['principal_photo'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($about['principal_photo']); ?>" id="principalPhotoImg" class="js-lightbox-trigger" data-caption="Principal Photo" alt="Principal photo">
                        <?php else: ?>
                            <div class="principal-photo-placeholder" id="principalPhotoImg">
                                <svg viewBox="0 0 24 24" class="avatar-icon" aria-hidden="true"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9V22h19.6v-2.6c0-3.3-6.5-4.9-9.8-4.9z"/></svg>
                                <span>No Photo Available</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="principal-photo-actions">
                        <button type="button" class="btn-secondary" id="principalUploadBtn"><?php echo empty($about['principal_photo']) ? 'Upload Photo' : 'Replace Photo'; ?></button>
                        <?php if (!empty($about['principal_photo'])): ?>
                            <button type="button" class="btn-danger" id="principalRemoveBtn">Remove Photo</button>
                            <button type="button" class="btn-link" id="principalPreviewFullBtn">Preview Full Size</button>
                            <a href="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($about['principal_photo']); ?>" download class="btn-link">Download Photo</a>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="principal_photo" id="principalPhotoInput" accept=".jpg,.jpeg,.png,.webp,image/*" class="visually-hidden">
                    <input type="hidden" name="remove_principal_photo" id="removePrincipalPhotoFlag" value="0">

                    <?php if ($principalPhotoInfo): ?>
                    <ul class="image-info-list" id="principalImageInfo">
                        <li><strong>Filename:</strong> <?php echo clean($principalPhotoInfo['filename']); ?></li>
                        <li><strong>Size:</strong> <?php echo clean($principalPhotoInfo['size_label']); ?></li>
                        <li><strong>Dimensions:</strong> <?php echo (int) $principalPhotoInfo['width']; ?> &times; <?php echo (int) $principalPhotoInfo['height']; ?> px</li>
                        <li><strong>Last Updated:</strong> <?php echo date('M d, Y g:i A', $principalPhotoInfo['modified_at']); ?></li>
                    </ul>
                    <?php endif; ?>
                    <p class="muted image-hint">JPG, PNG, or WEBP. Max 5MB. You'll be able to crop it to a square (recommended 220&times;220px) before saving.</p>
                </div>

                <!-- RIGHT PANEL: Principal info -->
                <div class="principal-info-fields">
                    <div class="form-row">
                        <label>Academic Title
                            <select name="principal_title" id="principalTitle">
                                <?php foreach ($principalTitles as $val => $label): ?>
                                    <option value="<?php echo clean($val); ?>" <?php echo ($about['principal_title'] ?? '') === $val ? 'selected' : ''; ?>><?php echo clean($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Principal Name<input type="text" name="principal_name" id="principalName" value="<?php echo av($about, 'principal_name'); ?>"></label>
                    </div>
                    <label>Position<input type="text" name="principal_position" id="principalPosition" value="<?php echo av($about, 'principal_position'); ?>" placeholder="School Principal"></label>
                    <div class="form-row">
                        <label>Office Email<input type="email" name="principal_email" value="<?php echo av($about, 'principal_email'); ?>"></label>
                        <label>Office Contact Number<input type="text" name="principal_contact" value="<?php echo av($about, 'principal_contact'); ?>"></label>
                    </div>
                    <label>Status
                        <select name="principal_status" id="principalStatus">
                            <option value="Active" <?php echo $principalStatus === 'Active' ? 'selected' : ''; ?>>Active (shown on the public page)</option>
                            <option value="Inactive" <?php echo $principalStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive (hidden from the public page)</option>
                            <option value="Draft" <?php echo $principalStatus === 'Draft' ? 'selected' : ''; ?>>Draft (work in progress, hidden)</option>
                        </select>
                    </label>

                    <label class="full">Message
                        <div class="rich-editor" data-field="principal_message" id="principalMessageEditor"><?php echo $about['principal_message'] ?? ''; ?></div>
                        <input type="hidden" name="principal_message" class="rich-editor-input">
                    </label>

                    <div class="principal-signature-card">
                        <label>Digital Signature (optional)</label>
                        <div class="principal-signature-preview" id="principalSigPreview">
                            <?php if (!empty($about['principal_signature'])): ?>
                                <img src="<?php echo BASE_URL; ?>uploads/<?php echo urlencode($about['principal_signature']); ?>" id="principalSigImg" class="js-lightbox-trigger" data-caption="Principal Signature" alt="Principal signature">
                            <?php else: ?>
                                <span class="muted" id="principalSigImg">No signature uploaded</span>
                            <?php endif; ?>
                        </div>
                        <div class="principal-photo-actions">
                            <button type="button" class="btn-secondary" id="principalSigUploadBtn"><?php echo empty($about['principal_signature']) ? 'Upload Signature' : 'Replace Signature'; ?></button>
                            <?php if (!empty($about['principal_signature'])): ?>
                                <button type="button" class="btn-danger" id="principalSigRemoveBtn">Remove Signature</button>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="principal_signature" id="principalSigInput" accept=".jpg,.jpeg,.png,.webp,image/*" class="visually-hidden">
                        <input type="hidden" name="remove_principal_signature" id="removePrincipalSigFlag" value="0">
                        <?php if ($principalSigInfo): ?>
                        <ul class="image-info-list">
                            <li><strong><?php echo clean($principalSigInfo['filename']); ?></strong> &mdash; <?php echo clean($principalSigInfo['size_label']); ?>, <?php echo (int) $principalSigInfo['width']; ?>&times;<?php echo (int) $principalSigInfo['height']; ?>px</li>
                        </ul>
                        <?php endif; ?>
                        <p class="muted image-hint">Transparent PNG recommended. Max 2MB.</p>
                    </div>
                </div>
            </div>

            <div class="form-actions principal-save-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
                <button type="submit" id="principalSaveDraftBtn" class="btn-secondary">Save as Draft</button>
                <button type="submit" id="principalPublishBtn" class="btn-secondary">Publish</button>
                <a href="<?php echo BASE_URL; ?>about.php#principal" target="_blank" class="btn-link">Preview on Public Page</a>
                <button type="reset" class="btn-link">Reset</button>
            </div>
        </form>

        <div class="principal-live-preview">
            <h4>Live Preview</h4>
            <p class="muted" style="margin-top:-4px;">This mirrors how the Principal's Message card will look on the public About page.</p>
            <div class="about-principal-card" id="principalPreviewCard">
                <img src="<?php echo !empty($about['principal_photo']) ? BASE_URL . 'uploads/' . urlencode($about['principal_photo']) : ''; ?>" id="previewPhoto" class="about-principal-photo" alt="" <?php echo empty($about['principal_photo']) ? 'hidden' : ''; ?>>
                <div class="about-principal-body">
                    <div class="rich-content" id="previewMessage"><?php echo $about['principal_message'] ?? '<p class="muted">Your message will appear here...</p>'; ?></div>
                    <img src="<?php echo !empty($about['principal_signature']) ? BASE_URL . 'uploads/' . urlencode($about['principal_signature']) : ''; ?>" id="previewSignature" class="about-principal-signature" alt="" <?php echo empty($about['principal_signature']) ? 'hidden' : ''; ?>>
                    <p class="about-principal-name" id="previewName"><?php echo clean(trim(($about['principal_title'] ?? '') . ' ' . ($about['principal_name'] ?? ''))); ?></p>
                    <p class="muted" id="previewPosition"><?php echo av($about, 'principal_position'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Photo crop modal (Upload/Replace Photo opens this before the file is attached to the form) -->
    <div class="photo-crop-modal" id="principalCropperModal" hidden>
        <div class="photo-crop-modal-inner">
            <h4>Adjust Principal Photo</h4>
            <div class="cropper-canvas-wrap"><img id="cropperImage" src="" alt=""></div>
            <div class="cropper-controls">
                <button type="button" class="btn-secondary" id="cropZoomIn">Zoom In</button>
                <button type="button" class="btn-secondary" id="cropZoomOut">Zoom Out</button>
                <button type="button" class="btn-secondary" id="cropRotateLeft">Rotate Left</button>
                <button type="button" class="btn-secondary" id="cropRotateRight">Rotate Right</button>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" id="cropCancelBtn">Cancel Replacement</button>
                <button type="button" class="btn-primary" id="cropApplyBtn">Apply Crop</button>
            </div>
        </div>
    </div>

    <div id="aboutLightbox" class="about-lightbox" hidden>
        <button type="button" class="about-lightbox-close" id="aboutLightboxClose">&times;</button>
        <img id="aboutLightboxImg" src="" alt="">
        <p id="aboutLightboxCaption"></p>
    </div>

    <!-- Section 10: Contact Information -->
    <section class="panel" id="contact-panel">
        <h3>Contact Information</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="contact">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label class="full">School Address<input type="text" name="contact_address" value="<?php echo av($about, 'contact_address'); ?>"></label>
            <label>Telephone<input type="text" name="contact_telephone" value="<?php echo av($about, 'contact_telephone'); ?>"></label>
            <label>Mobile Number<input type="text" name="contact_mobile" value="<?php echo av($about, 'contact_mobile'); ?>"></label>
            <label>Email Address<input type="email" name="contact_email" value="<?php echo av($about, 'contact_email'); ?>"></label>
            <label>Office Hours<input type="text" name="contact_office_hours" value="<?php echo av($about, 'contact_office_hours'); ?>" placeholder="Mon-Fri, 8:00 AM - 5:00 PM"></label>
            <label>Website<input type="text" name="contact_website" value="<?php echo av($about, 'contact_website'); ?>"></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save Contact Information</button></div>
        </form>
    </section>

    <!-- Section 11: Google Map -->
    <section class="panel" id="map-panel">
        <h3>Google Map</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="map">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label class="full">Google Maps Embed Code or URL
                <textarea name="map_embed" rows="3" placeholder="Paste an <iframe> embed code from Google Maps' Share &gt; Embed a map, or a plain Google Maps URL."><?php echo av($about, 'map_embed'); ?></textarea>
                <span class="muted" style="font-weight:normal;">Only Google Maps links are accepted; anything else is ignored on the public page.</span>
            </label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save Google Map</button></div>
        </form>
    </section>

    <!-- Section 12: Social Media -->
    <section class="panel" id="social-panel">
        <h3>Social Media</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="form_action" value="social">
            <input type="hidden" name="csrf_token" value="<?php echo clean($csrfToken); ?>">
            <label>Facebook<input type="text" name="social_facebook" value="<?php echo av($about, 'social_facebook'); ?>" placeholder="https://facebook.com/..."></label>
            <label>Messenger<input type="text" name="social_messenger" value="<?php echo av($about, 'social_messenger'); ?>" placeholder="https://m.me/..."></label>
            <label>YouTube<input type="text" name="social_youtube" value="<?php echo av($about, 'social_youtube'); ?>"></label>
            <label>Instagram<input type="text" name="social_instagram" value="<?php echo av($about, 'social_instagram'); ?>"></label>
            <label>TikTok<input type="text" name="social_tiktok" value="<?php echo av($about, 'social_tiktok'); ?>"></label>
            <div class="form-actions"><button type="submit" class="btn-primary">Save Social Media</button></div>
        </form>
    </section>

    <?php endif; ?>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/about_content_admin.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/about_principal_admin.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
