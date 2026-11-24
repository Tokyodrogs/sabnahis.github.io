<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Reports';
$schoolYears = $pdo->query('SELECT * FROM school_year ORDER BY school_year DESC')->fetchAll();
$gradeLevels = $pdo->query('SELECT * FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$sections = $pdo->query("SELECT sec.*, g.grade_name, sy.school_year FROM sections sec
    LEFT JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
    LEFT JOIN school_year sy ON sy.sy_id = sec.sy_id
    ORDER BY sy.school_year DESC, g.grade_level_id, sec.section_name")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <h2>Reports</h2>
    <p class="muted">Select filters, then choose a report. "Generate" opens a printable view (Print / Save as PDF); "Export CSV" downloads the same data as a spreadsheet file.</p>

    <form id="reportForm" target="_blank" class="form-inline" onsubmit="return false;">
        <label>School Year:
            <select id="sy_id">
                <option value="">All School Years</option>
                <?php foreach ($schoolYears as $sy): ?>
                    <option value="<?php echo $sy['sy_id']; ?>"><?php echo clean($sy['school_year']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Grade Level:
            <select id="grade_level_id">
                <option value="">All Grade Levels</option>
                <?php foreach ($gradeLevels as $g): ?>
                    <option value="<?php echo $g['grade_level_id']; ?>"><?php echo clean($g['grade_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status:
            <select id="status">
                <option value="">All Statuses</option>
                <?php foreach (['Pending','Verified','Approved','Rejected','Enrolled'] as $st): ?>
                    <option value="<?php echo $st; ?>"><?php echo $st === 'Enrolled' ? 'Officially Enrolled' : $st; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="report-cards">
        <div class="report-card">
            <h3>Master List</h3>
            <p>Comprehensive list of every student with status, section, and registration type.</p>
            <button class="btn-primary" onclick="openReport('master_list')">Generate</button>
            <button class="btn-secondary" onclick="openReport('master_list', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>List of Enrolled Students</h3>
            <p>Full list of officially enrolled students for the selected school year.</p>
            <button class="btn-primary" onclick="openReport('enrolled_list')">Generate</button>
            <button class="btn-secondary" onclick="openReport('enrolled_list', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Enrollment Statistics</h3>
            <p>Summary counts by status (pending, verified, approved, rejected, enrolled).</p>
            <button class="btn-primary" onclick="openReport('statistics')">Generate</button>
            <button class="btn-secondary" onclick="openReport('statistics', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Students per Strand</h3>
            <p>Breakdown of students grouped by strand.</p>
            <button class="btn-primary" onclick="openReport('per_strand')">Generate</button>
            <button class="btn-secondary" onclick="openReport('per_strand', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Students per Grade Level</h3>
            <p>Breakdown of students grouped by grade level.</p>
            <button class="btn-primary" onclick="openReport('per_grade')">Generate</button>
            <button class="btn-secondary" onclick="openReport('per_grade', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Students by Sex</h3>
            <p>Male / Female counts broken down by grade level.</p>
            <button class="btn-primary" onclick="openReport('by_sex')">Generate</button>
            <button class="btn-secondary" onclick="openReport('by_sex', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Student Directory</h3>
            <p>Contact details for every student: address, contact number, email, guardian info.</p>
            <button class="btn-primary" onclick="openReport('student_directory')">Generate</button>
            <button class="btn-secondary" onclick="openReport('student_directory', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Section List</h3>
            <p>Every section with adviser, current enrollment count, and capacity.</p>
            <button class="btn-primary" onclick="openReport('section_list')">Generate</button>
            <button class="btn-secondary" onclick="openReport('section_list', true)">Export CSV</button>
        </div>
        <div class="report-card">
            <h3>Class List by Section</h3>
            <p>Roster of every student enrolled in a specific section - Registrar-generated, no scheduling involved.</p>
            <select id="class_list_section_id" style="width:100%;margin-bottom:8px;">
                <option value="">Select a section...</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['section_id']; ?>"><?php echo clean(($s['grade_name'] ?: '-') . ' - ' . $s['section_name'] . ' (' . ($s['school_year'] ?: '-') . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-primary" onclick="openClassList()">Generate</button>
            <button class="btn-secondary" onclick="openClassList(true)">Export CSV</button>
        </div>
    </div>
</main>
<script>
function openReport(type, csv) {
    var sy = document.getElementById('sy_id').value;
    var grade = document.getElementById('grade_level_id').value;
    var status = document.getElementById('status').value;
    var url = 'reports_print.php?type=' + encodeURIComponent(type)
        + (sy ? '&sy_id=' + encodeURIComponent(sy) : '')
        + (grade ? '&grade_level_id=' + encodeURIComponent(grade) : '')
        + (status ? '&status=' + encodeURIComponent(status) : '')
        + (csv ? '&format=csv' : '');
    window.open(url, '_blank');
}
function openClassList(csv) {
    var sectionId = document.getElementById('class_list_section_id').value;
    if (!sectionId) { alert('Please select a section first.'); return; }
    window.open('reports_print.php?type=class_list&section_id=' + encodeURIComponent(sectionId) + (csv ? '&format=csv' : ''), '_blank');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
