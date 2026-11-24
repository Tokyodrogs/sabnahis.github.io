<?php
/**
 * Lightweight JSON endpoint backing the searchable Philippine school
 * directory (Elementary School / Previous School / Last School Attended
 * fields on the enrollment wizard - see assets/js/enrollment_wizard.js).
 * Public/unauthenticated by design: school names aren't sensitive, same
 * pattern as check_username.php.
 *
 * GET search_schools.php?q=...&level=elementary&region=...&province=...
 *   -> {"results":[{school_id,deped_school_id,school_code,school_name,region,
 *                    division,province,municipality,barangay,school_type,
 *                    offers_elementary,offers_junior_high,offers_senior_high,
 *                    education_level_label}, ...]}
 *
 * level is optional: 'elementary'/'junior_high'/'senior_high'. New
 * Student's Elementary School field always sends 'elementary'.
 * Transferee/Balik-Aral send whatever the optional Level filter dropdown
 * is set to (2026-08h), or omit it for "any level".
 *
 * region/province (2026-08h) are optional filters from the same dropdowns
 * above the school search box, narrowing among the text-search matches.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$level = trim($_GET['level'] ?? '');
$region = trim($_GET['region'] ?? '');
$province = trim($_GET['province'] ?? '');
$results = search_schools_query($pdo, $query, 20, $level ?: null, $region ?: null, $province ?: null);

echo json_encode(['results' => $results]);
