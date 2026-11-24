<?php
/**
 * School Directory (2026-08f: DepEd directory upgrade). Backs the
 * searchable school lookup on the public enrollment wizard (Elementary
 * School / Previous School / Last School Attended - see enroll.php +
 * assets/js/enrollment_wizard.js). See database/migration_2026_08f_
 * school_directory.sql for why this table is NOT preloaded with the full
 * ~60,000-school DepEd directory: that requires an actual DepEd export
 * file, which this page's CSV importer is built to accept once available.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// System-configuration data - Administrator-only (see strands.php header
// comment for the same reasoning).
if (($_SESSION['admin_role'] ?? 'Administrator') !== 'Administrator') {
    flash('success', 'The school directory is managed by the Administrator.');
    redirect('dashboard.php');
}

$pageTitle = 'School Directory';
$errors = [];
$importSummary = null;

function csv_col_index(array $header, array $aliases) {
    foreach ($aliases as $alias) {
        $idx = array_search($alias, $header, true);
        if ($idx !== false) return $idx;
    }
    return null;
}

function truthy_csv_value($v) {
    $v = strtolower(trim((string) $v));
    return in_array($v, ['1', 'yes', 'true', 'y'], true);
}

// Fallback for CSV files that describe levels as one free-text column
// (e.g. "Elementary", "JHS/SHS", "Elementary, Junior High") instead of
// separate boolean columns - keyword match rather than an exact enum so a
// real DepEd export's phrasing doesn't have to be guessed in advance.
function parse_education_level_text($text) {
    $text = strtolower($text);
    return [
        'elementary' => (strpos($text, 'elem') !== false),
        'junior'     => (strpos($text, 'junior') !== false || strpos($text, 'jhs') !== false),
        'senior'     => (strpos($text, 'senior') !== false || strpos($text, 'shs') !== false),
    ];
}

$editSchool = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM schools WHERE school_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editSchool = $stmt->fetch();
    if (!$editSchool) {
        flash('success', 'School not found.');
        redirect('schools_import.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $name = trim($_POST['school_name'] ?? '');
        $depedId = trim($_POST['deped_school_id'] ?? '');
        $schoolCode = trim($_POST['school_code'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $division = trim($_POST['division'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $zipCode = trim($_POST['zip_code'] ?? '');
        $type = ($_POST['school_type'] ?? 'Public') === 'Private' ? 'Private' : 'Public';
        $offersElem = !empty($_POST['offers_elementary']) ? 1 : 0;
        $offersJHS  = !empty($_POST['offers_junior_high']) ? 1 : 0;
        $offersSHS  = !empty($_POST['offers_senior_high']) ? 1 : 0;

        if ($name === '') {
            $errors[] = 'School name is required.';
        } elseif ($action === 'add') {
            $pdo->prepare('INSERT INTO schools
                (school_name, deped_school_id, school_code, region, division, province, municipality, barangay,
                 address, zip_code, school_type, offers_elementary, offers_junior_high, offers_senior_high, is_verified)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)')
                ->execute([$name, $depedId ?: null, $schoolCode ?: null, $region ?: null, $division ?: null,
                           $province ?: null, $municipality ?: null, $barangay ?: null, $address ?: null,
                           $zipCode ?: null, $type, $offersElem, $offersJHS, $offersSHS]);
            flash('success', 'School added.');
            redirect('schools_import.php');
        } else { // update
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $pdo->prepare('UPDATE schools SET school_name = ?, deped_school_id = ?, school_code = ?, region = ?,
                    division = ?, province = ?, municipality = ?, barangay = ?, address = ?, zip_code = ?,
                    school_type = ?, offers_elementary = ?, offers_junior_high = ?, offers_senior_high = ?
                    WHERE school_id = ?')
                ->execute([$name, $depedId ?: null, $schoolCode ?: null, $region ?: null, $division ?: null,
                           $province ?: null, $municipality ?: null, $barangay ?: null, $address ?: null,
                           $zipCode ?: null, $type, $offersElem, $offersJHS, $offersSHS, $schoolId]);
            flash('success', 'School updated.');
            redirect('schools_import.php');
        }
    } elseif ($action === 'verify') {
        $id = (int) ($_POST['school_id'] ?? 0);
        $pdo->prepare('UPDATE schools SET is_verified = 1 WHERE school_id = ?')->execute([$id]);
        flash('success', 'School marked as verified.');
        redirect('schools_import.php');
    } elseif ($action === 'toggle_active') {
        $id = (int) ($_POST['school_id'] ?? 0);
        $pdo->prepare('UPDATE schools SET is_active = 1 - is_active WHERE school_id = ?')->execute([$id]);
        flash('success', 'School status updated.');
        redirect('schools_import.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['school_id'] ?? 0);
        $pdo->prepare('DELETE FROM schools WHERE school_id = ?')->execute([$id]);
        flash('success', 'School removed from the directory.');
        redirect('schools_import.php');
    } elseif ($action === 'import_csv') {
        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please choose a CSV file to import.';
        } else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if (!$handle) {
                $errors[] = 'Could not read the uploaded file.';
            } else {
                $header = fgetcsv($handle);
                $header = $header ? array_map(function ($h) { return strtolower(trim($h)); }, $header) : [];

                $colIndex = [
                    'school_name'        => csv_col_index($header, ['school_name', 'name']),
                    'deped_school_id'    => csv_col_index($header, ['deped_school_id', 'school_id', 'deped_id']),
                    'school_code'        => csv_col_index($header, ['school_code', 'code']),
                    'region'             => csv_col_index($header, ['region']),
                    'division'           => csv_col_index($header, ['division']),
                    'province'           => csv_col_index($header, ['province']),
                    'municipality'       => csv_col_index($header, ['municipality', 'city_municipality', 'city']),
                    'barangay'           => csv_col_index($header, ['barangay']),
                    'address'            => csv_col_index($header, ['address']),
                    'zip_code'           => csv_col_index($header, ['zip_code', 'zip']),
                    'school_type'        => csv_col_index($header, ['school_type', 'ownership']),
                    'offers_elementary'  => csv_col_index($header, ['offers_elementary', 'elementary']),
                    'offers_junior_high' => csv_col_index($header, ['offers_junior_high', 'junior_high', 'jhs']),
                    'offers_senior_high' => csv_col_index($header, ['offers_senior_high', 'senior_high', 'shs']),
                    'education_level'    => csv_col_index($header, ['education_level', 'level', 'offering']),
                ];

                if ($colIndex['school_name'] === null) {
                    $errors[] = 'CSV must include a "school_name" column. Optional columns: deped_school_id, '
                        . 'school_code, region, division, province, municipality, barangay, address, zip_code, '
                        . 'school_type (Public/Private), and either offers_elementary/offers_junior_high/'
                        . 'offers_senior_high (1/0) or a single education_level text column.';
                } else {
                    $inserted = 0;
                    $skipped = 0;
                    $insertStmt = $pdo->prepare('INSERT INTO schools
                        (school_name, deped_school_id, school_code, region, division, province, municipality,
                         barangay, address, zip_code, school_type, offers_elementary, offers_junior_high,
                         offers_senior_high, is_verified)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');

                    while (($row = fgetcsv($handle)) !== false) {
                        $name = trim($row[$colIndex['school_name']] ?? '');
                        if ($name === '') { $skipped++; continue; }

                        $get = function ($key) use ($colIndex, $row) {
                            return $colIndex[$key] !== null ? trim($row[$colIndex[$key]] ?? '') : '';
                        };

                        $type = strtolower($get('school_type')) === 'private' ? 'Private' : 'Public';

                        if ($colIndex['offers_elementary'] !== null || $colIndex['offers_junior_high'] !== null || $colIndex['offers_senior_high'] !== null) {
                            $offersElem = truthy_csv_value($get('offers_elementary')) ? 1 : 0;
                            $offersJHS  = truthy_csv_value($get('offers_junior_high')) ? 1 : 0;
                            $offersSHS  = truthy_csv_value($get('offers_senior_high')) ? 1 : 0;
                        } elseif ($colIndex['education_level'] !== null) {
                            $levels = parse_education_level_text($get('education_level'));
                            $offersElem = $levels['elementary'] ? 1 : 0;
                            $offersJHS  = $levels['junior'] ? 1 : 0;
                            $offersSHS  = $levels['senior'] ? 1 : 0;
                        } else {
                            $offersElem = $offersJHS = $offersSHS = 0;
                        }

                        $insertStmt->execute([
                            $name, $get('deped_school_id') ?: null, $get('school_code') ?: null,
                            $get('region') ?: null, $get('division') ?: null, $get('province') ?: null,
                            $get('municipality') ?: null, $get('barangay') ?: null, $get('address') ?: null,
                            $get('zip_code') ?: null, $type, $offersElem, $offersJHS, $offersSHS,
                        ]);
                        $inserted++;
                    }
                    fclose($handle);
                    $importSummary = "Imported {$inserted} school(s)." . ($skipped ? " Skipped {$skipped} row(s) with no school name." : '');
                    flash('success', $importSummary);
                    redirect('schools_import.php');
                }
                if (is_resource($handle)) fclose($handle);
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM schools WHERE school_name LIKE ? OR municipality LIKE ? OR province LIKE ? OR deped_school_id LIKE ? ORDER BY is_verified ASC, school_name ASC LIMIT 200');
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like, $like]);
    $schools = $stmt->fetchAll();
} else {
    $schools = $pdo->query('SELECT * FROM schools ORDER BY is_verified ASC, school_name ASC LIMIT 200')->fetchAll();
}
$totalSchools = (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn();
$unverifiedCount = (int) $pdo->query('SELECT COUNT(*) FROM schools WHERE is_verified = 0')->fetchColumn();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>School Directory</h2>
    <p class="muted">Backs the searchable school lookup on the public enrollment form (Elementary School / Previous
        School / Last School Attended). <?php echo $totalSchools; ?> school(s) on file<?php if ($unverifiedCount): ?>,
        <strong><?php echo $unverifiedCount; ?> awaiting review</strong> (submitted by applicants via "My School Is
        Not Listed", or flagged during import)<?php endif; ?>. This is not preloaded with the full DepEd School
        Directory (~60,000+ schools) &mdash; import an official DepEd export below when you have one.</p>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo clean(implode(' ', $errors)); ?></div><?php endif; ?>

    <section class="panel">
        <h3>Import from CSV</h3>
        <p class="muted">Required column: <code>school_name</code>. Optional: <code>deped_school_id</code>,
            <code>school_code</code>, <code>region</code>, <code>division</code>, <code>province</code>,
            <code>municipality</code> (or <code>city_municipality</code>), <code>barangay</code>,
            <code>address</code>, <code>zip_code</code>, <code>school_type</code> (Public/Private). For education
            level, provide either <code>offers_elementary</code> / <code>offers_junior_high</code> /
            <code>offers_senior_high</code> columns (1/0) or a single <code>education_level</code> text column
            (e.g. "Elementary", "JHS/SHS"). First row must be a header row.</p>
        <form method="POST" enctype="multipart/form-data" class="form-inline">
            <input type="hidden" name="action" value="import_csv">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" class="btn-primary">Import CSV</button>
        </form>
    </section>

    <section class="panel">
        <h3><?php echo $editSchool ? 'Edit School' : 'Add a Single School'; ?></h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="<?php echo $editSchool ? 'update' : 'add'; ?>">
            <?php if ($editSchool): ?><input type="hidden" name="school_id" value="<?php echo $editSchool['school_id']; ?>"><?php endif; ?>
            <label>School Name *<input type="text" name="school_name" value="<?php echo clean($editSchool['school_name'] ?? ''); ?>" required></label>
            <label>Ownership
                <select name="school_type">
                    <option value="Public" <?php echo (($editSchool['school_type'] ?? 'Public') === 'Public') ? 'selected' : ''; ?>>Public</option>
                    <option value="Private" <?php echo (($editSchool['school_type'] ?? '') === 'Private') ? 'selected' : ''; ?>>Private</option>
                </select>
            </label>
            <label>DepEd School ID<input type="text" name="deped_school_id" value="<?php echo clean($editSchool['deped_school_id'] ?? ''); ?>"></label>
            <label>School Code<input type="text" name="school_code" value="<?php echo clean($editSchool['school_code'] ?? ''); ?>"></label>
            <label>Region<input type="text" name="region" value="<?php echo clean($editSchool['region'] ?? ''); ?>"></label>
            <label>Division<input type="text" name="division" value="<?php echo clean($editSchool['division'] ?? ''); ?>"></label>
            <label>Province<input type="text" name="province" value="<?php echo clean($editSchool['province'] ?? ''); ?>"></label>
            <label>Municipality / City<input type="text" name="municipality" value="<?php echo clean($editSchool['municipality'] ?? ''); ?>"></label>
            <label>Barangay<input type="text" name="barangay" value="<?php echo clean($editSchool['barangay'] ?? ''); ?>"></label>
            <label>Address<input type="text" name="address" value="<?php echo clean($editSchool['address'] ?? ''); ?>"></label>
            <label>Zip Code<input type="text" name="zip_code" value="<?php echo clean($editSchool['zip_code'] ?? ''); ?>"></label>
            <label class="full">Education Level(s) Offered
                <span class="form-checkbox-group">
                    <label class="checkbox-label"><input type="checkbox" name="offers_elementary" value="1" <?php echo !empty($editSchool['offers_elementary']) ? 'checked' : ''; ?>> Elementary</label>
                    <label class="checkbox-label"><input type="checkbox" name="offers_junior_high" value="1" <?php echo !empty($editSchool['offers_junior_high']) ? 'checked' : ''; ?>> Junior High School</label>
                    <label class="checkbox-label"><input type="checkbox" name="offers_senior_high" value="1" <?php echo !empty($editSchool['offers_senior_high']) ? 'checked' : ''; ?>> Senior High School</label>
                </span>
            </label>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><?php echo $editSchool ? 'Save Changes' : 'Add School'; ?></button>
                <?php if ($editSchool): ?><a href="schools_import.php" class="btn-link">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>Directory</h3>
        <form method="GET" class="form-inline" style="margin-bottom:12px;">
            <input type="text" name="search" placeholder="Search by name, DepEd ID, municipality, or province" value="<?php echo clean($search); ?>">
            <button type="submit" class="btn-secondary">Search</button>
            <?php if ($search !== ''): ?><a href="schools_import.php" class="btn-link">Clear</a><?php endif; ?>
        </form>

        <table class="data-table">
            <thead><tr><th>School</th><th>DepEd ID</th><th>Ownership</th><th>Level(s)</th><th>Municipality</th><th>Province</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($schools as $s): ?>
                <tr<?php echo empty($s['is_active']) ? ' class="row-inactive"' : ''; ?>>
                    <td><?php echo clean($s['school_name']); ?></td>
                    <td><?php echo clean($s['deped_school_id'] ?: '-'); ?></td>
                    <td><?php echo clean($s['school_type']); ?></td>
                    <td class="muted"><?php echo clean(school_education_levels_label($s)); ?></td>
                    <td><?php echo clean($s['municipality']); ?></td>
                    <td><?php echo clean($s['province']); ?></td>
                    <td>
                        <?php if (!$s['is_verified']): ?>
                            <span class="badge badge-pending">Needs Review</span>
                        <?php elseif (empty($s['is_active'])): ?>
                            <span class="badge badge-inactive">Disabled</span>
                        <?php else: ?>
                            <span class="badge badge-approved">Verified</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="schools_import.php?edit=<?php echo $s['school_id']; ?>" class="link-primary">Edit</a>
                        <?php if (!$s['is_verified']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="school_id" value="<?php echo $s['school_id']; ?>">
                                <button type="submit" class="link-primary">Verify</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="school_id" value="<?php echo $s['school_id']; ?>">
                            <button type="submit" class="link-primary"><?php echo empty($s['is_active']) ? 'Enable' : 'Disable'; ?></button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this school from the directory?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="school_id" value="<?php echo $s['school_id']; ?>">
                            <button type="submit" class="link-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$schools): ?>
                <tr><td colspan="8" class="muted">No schools found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if (count($schools) >= 200): ?><p class="muted">Showing the first 200 results - refine your search to narrow this down.</p><?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
