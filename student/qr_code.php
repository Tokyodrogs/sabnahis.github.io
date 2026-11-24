<?php
/**
 * Digital Student ID (2026-08l, built on top of the 2026-08g "My QR Code"
 * page - replaced "Assessment of Fees" / "Payment History" in the sidebar
 * per SABNAHIS's DepEd No Collection Policy). Reframed as a proper Digital
 * Student ID per the Student Portal feature request: same QR
 * infrastructure, plus LRN, school name/logo on the on-screen card (not
 * just the print view), and an "only officially enrolled students get an
 * active ID" gate (students.status = 'Enrolled') - Pending/Verified/
 * Approved/Rejected applicants see a status notice instead of a card.
 *
 * QR verification system (2026-08n): the QR code encodes a verification
 * URL - student/verify.php?token=<opaque random token> - not the
 * student's name/LRN/contact info. The token is generated once per
 * student (get_or_create_qr_token() in includes/functions.php) and only
 * changes if an administrator explicitly regenerates or deactivates it
 * (admin/students.php QR actions). The actual QR PNG is rendered
 * server-side by qr_image.php (this environment has no internet access
 * to vendor a client-side QR-encoding library, but the school's own PHP
 * server does - see fetch_qr_png()), so this page just points an <img>
 * at it; no JS is required for the QR to display, download, or print.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Digital Student ID';

$stmt = $pdo->prepare('SELECT s.*, g.grade_name, st.strand_name, sy.school_year, sec.section_name
    FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
    LEFT JOIN strands st ON s.strand_id = st.strand_id
    LEFT JOIN school_year sy ON s.sy_id = sy.sy_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.student_id = ?');
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();

$studentNumber = $_SESSION['student_username'];
$fullName = trim($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']);
$isActiveId = ($student['status'] ?? '') === 'Enrolled';
$photoUrl = student_photo_url($student['photo']);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Digital Student ID</h2>

    <?php if (!$isActiveId): ?>
        <div class="alert alert-info">
            Your Digital Student ID will be activated once your enrollment is officially confirmed. Current
            status: <?php echo status_badge($student['status'] ?? 'Pending'); ?> &mdash;
            check <a href="enrollment_status.php">Enrollment Status</a> for details.
        </div>
    <?php else: ?>
    <p class="muted">This is your official Digital Student ID. Present the QR code for Student ID verification -
        as gate check-in/out, library, clinic, guidance office, and attendance scanning become available, this is
        the code those systems will read.</p>

    <div class="id-card-wrap">
        <div class="panel id-card">
            <div class="id-card-header">
                <div class="id-card-header-left">
                    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="SABNAHIS Logo" class="id-card-logo">
                    <div>
                        <div class="id-card-school-name"><?php echo SITE_NAME; ?></div>
                        <div class="id-card-school-address">Sablayan, Occidental Mindoro</div>
                    </div>
                </div>
                <?php if ($photoUrl): ?>
                    <img src="<?php echo clean($photoUrl); ?>" alt="Student Photo" class="id-card-photo-box" width="92" height="108">
                <?php else: ?>
                    <div class="id-card-photo-box id-card-photo-placeholder">No Photo</div>
                <?php endif; ?>
            </div>

            <div class="id-card-title">
                <h3>Digital Student ID</h3>
                <div class="id-card-divider"></div>
            </div>

            <div class="id-card-info">
                <div class="id-card-row"><span class="id-card-label">Name</span><span class="id-card-value"><?php echo clean($fullName); ?></span></div>
                <div class="id-card-row"><span class="id-card-label">Student Number</span><span class="id-card-value"><?php echo clean($studentNumber); ?></span></div>
                <?php if (!empty($student['lrn'])): ?>
                    <div class="id-card-row"><span class="id-card-label">LRN</span><span class="id-card-value"><?php echo clean($student['lrn']); ?></span></div>
                <?php endif; ?>
                <div class="id-card-row"><span class="id-card-label">Grade Level</span><span class="id-card-value"><?php echo clean($student['grade_name'] ?? '-'); ?><?php if ($student['strand_name']): ?> &mdash; <?php echo clean($student['strand_name']); ?><?php endif; ?></span></div>
                <div class="id-card-row"><span class="id-card-label">Section</span><span class="id-card-value"><?php echo clean($student['section_name'] ?? 'Not yet assigned'); ?></span></div>
                <div class="id-card-row"><span class="id-card-label">School Year</span><span class="id-card-value"><?php echo clean($student['school_year'] ?? '-'); ?></span></div>
            </div>

            <div class="id-card-qr-section">
                <div class="id-card-qr-box">
                    <img src="qr_image.php?<?php echo time(); ?>" alt="Student QR Code" class="qr-code-image" width="150" height="150">
                </div>
                <div class="id-card-qr-caption">Scan to Verify Student Identity</div>
            </div>

            <div class="id-card-footer">
                Generated on <?php echo date('F d, Y \a\t h:i A'); ?><br>via the SABNAHIS Student Portal
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="qr_view.php" target="_blank" class="btn-secondary">View QR Code</a>
        <a href="qr_image.php?dl=1" download class="btn-secondary">Download QR Code</a>
        <a href="qr_code_print.php" target="_blank" class="btn-primary">Print ID</a>
    </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
