// Shared multi-select checkbox + bulk-action toolbar behavior for
// Student Management and Teacher Management list pages. Purely UX sugar -
// every bulk action is re-validated server-side (role check, dependency
// check for delete, etc.) in the corresponding *_bulk_action.php handler.
document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('selectAll');
    var rowChecks = document.querySelectorAll('.row-check');
    var countLabel = document.getElementById('selectedCount');
    var bulkButtons = document.querySelectorAll('.bulk-toolbar button[type="submit"]');

    function selectedCount() {
        var n = 0;
        rowChecks.forEach(function (cb) { if (cb.checked) n++; });
        return n;
    }

    function refresh() {
        var n = selectedCount();
        if (countLabel) countLabel.textContent = 'Selected: ' + n + ' Record(s)';
        bulkButtons.forEach(function (btn) { btn.disabled = (n === 0); });
        if (selectAll) {
            selectAll.checked = rowChecks.length > 0 && n === rowChecks.length;
            selectAll.indeterminate = n > 0 && n < rowChecks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (cb) { cb.checked = selectAll.checked; });
            refresh();
        });
    }

    rowChecks.forEach(function (cb) {
        cb.addEventListener('change', refresh);
    });

    refresh();
});

// Confirmation dialogs for bulk actions, called from each button's
// onclick="return confirmBulk('delete')" etc. Reads the current selected
// count from the DOM so the message matches exactly what the user picked.
function confirmBulk(action) {
    var rowChecks = document.querySelectorAll('.row-check');
    var n = 0;
    rowChecks.forEach(function (cb) { if (cb.checked) n++; });

    var messages = {
        delete:  'Delete ' + n + ' selected record(s)?\n\nThis action cannot be undone.',
        archive: 'Archive ' + n + ' selected record(s)? They will be hidden from the active list but not deleted.',
        restore: 'Restore ' + n + ' selected record(s) to the active list?'
    };
    return confirm(messages[action] || ('Proceed with this action on ' + n + ' selected record(s)?'));
}
