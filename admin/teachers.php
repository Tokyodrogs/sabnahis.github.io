<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Teacher roster management (Administrator-only for delete/archive/restore/
// bulk actions - see admin/includes/sidebar.php for the role split). This is
// distinct from the Academic Management portal's teacher_assignment.php,
// which assigns an *existing* teacher record (created here) to a subject +
// section + school year. Creating/editing/removing the roster entry itself
// lives here, alongside the rest of admin's records-management pages.
$pageTitle = 'Teacher Management';
$isAdministrator = ($_SESSION['admin_role'] ?? 'Administrator') === 'Administrator';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active'; // active (default) | inactive | all

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(t.lastname LIKE ? OR t.firstname LIKE ? OR t.employee_no LIKE ?)';
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($statusFilter === 'active') {
    $where[] = "t.status = 'Active'";
} elseif ($statusFilter === 'inactive') {
    $where[] = "t.status = 'Inactive'";
}

$sql = 'SELECT t.* FROM teachers t';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY t.lastname, t.firstname';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-head">
        <h2>Teacher Management</h2>
        <?php if ($isAdministrator): ?>
            <a href="teacher_add.php" class="btn-primary">+ Add New Teacher</a>
        <?php endif; ?>
    </div>

    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
    <?php if ($err = flash('error')): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search by name or employee no..." value="<?php echo clean($search); ?>">
        <select name="status">
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Only</option>
            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive (Archived) Only</option>
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        <a href="teachers.php" class="btn-link">Reset</a>
    </form>

    <?php if ($isAdministrator): ?>
    <form method="POST" action="teachers_bulk_action.php" id="bulkForm" target="_blank">
        <div class="bulk-toolbar">
            <span class="selected-count" id="selectedCount">Selected: 0 Record(s)</span>
            <button type="submit" name="bulk_action" value="archive" class="btn-secondary" id="btnArchive" disabled onclick="return confirmBulk('archive');">Archive Selected</button>
            <button type="submit" name="bulk_action" value="restore" class="btn-secondary" id="btnRestore" disabled onclick="return confirmBulk('restore');">Restore Selected</button>
            <button type="submit" name="bulk_action" value="export" class="btn-secondary" id="btnExport" disabled formtarget="_blank">Export Selected</button>
            <button type="submit" name="bulk_action" value="print" class="btn-secondary" id="btnPrint" disabled formtarget="_blank">Print Selected</button>
            <button type="submit" name="bulk_action" value="delete" class="btn-danger" id="btnDelete" disabled onclick="return confirmBulk('delete');">Delete Selected</button>
        </div>
    </form>
    <?php endif; ?>

    <table class="data-table">
        <thead>
        <tr>
            <?php if ($isAdministrator): ?><th class="checkbox-col"><input type="checkbox" id="selectAll"></th><?php endif; ?>
            <th>Employee No.</th><th>Name</th><th>Email</th><th>Contact</th><th>Status</th><th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($teachers)): ?>
            <tr><td colspan="<?php echo $isAdministrator ? 7 : 6; ?>" class="muted" style="text-align:center;">No teacher records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($teachers as $t): ?>
            <tr>
                <?php if ($isAdministrator): ?>
                    <td class="checkbox-col"><input type="checkbox" class="row-check" form="bulkForm" name="teacher_ids[]" value="<?php echo $t['teacher_id']; ?>"></td>
                <?php endif; ?>
                <td><?php echo clean($t['employee_no'] ?: '-'); ?></td>
                <td><?php echo clean($t['lastname'] . ', ' . $t['firstname'] . ' ' . $t['middlename']); ?></td>
                <td><?php echo clean($t['email']); ?></td>
                <td><?php echo clean($t['contact']); ?></td>
                <td><span class="badge badge-<?php echo strtolower($t['status']); ?>"><?php echo clean($t['status']); ?></span></td>
                <td class="actions">
                    <a href="teacher_view.php?id=<?php echo $t['teacher_id']; ?>">View</a>
                    <?php if ($isAdministrator): ?>
                        <a href="teacher_edit.php?id=<?php echo $t['teacher_id']; ?>">Edit</a>
                        <?php if ($t['status'] === 'Inactive'): ?>
                            <a href="teacher_archive.php?id=<?php echo $t['teacher_id']; ?>&action=restore" onclick="return confirm('Restore this teacher to Active status?');">Restore</a>
                        <?php else: ?>
                            <a href="teacher_archive.php?id=<?php echo $t['teacher_id']; ?>&action=archive" onclick="return confirm('Archive this teacher? They will be marked Inactive and hidden from the active list, but not deleted.');">Archive</a>
                        <?php endif; ?>
                        <a href="teacher_delete.php?id=<?php echo $t['teacher_id']; ?>" class="danger" onclick="return confirm('Permanently delete this teacher record? This cannot be undone.');">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
<script src="<?php echo BASE_URL; ?>assets/js/bulk_actions.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
