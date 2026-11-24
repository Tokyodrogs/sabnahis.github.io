// Client-side helpers for the public enrollment form.
// Note: server-side validation in enroll.php is authoritative; this is UX sugar only.
//
// trans(key, fallback): thin wrapper around assets/js/i18n.js's translator so
// the messages below switch language live on the public site / Student
// Portal (where i18n.js is loaded). This file is also shared with a couple
// of admin pages (admin/student_add.php, admin/student_edit.php) that don't
// load i18n.js - trans() just falls back to the English literal there.
function trans(key, fallback) {
    return (window.SABNAHIS_I18N && window.SABNAHIS_I18N.t) ? window.SABNAHIS_I18N.t(key, fallback) : fallback;
}

document.addEventListener('DOMContentLoaded', function () {
    var birthdate = document.getElementById('birthdate');
    var age = document.getElementById('age');
    var gradeSelect = document.getElementById('grade_level_id');
    var strandField = document.getElementById('strandField');
    var strandSelect = document.getElementById('strand_id');

    function computeAge() {
        if (!birthdate || !birthdate.value || !age) return;
        var bd = new Date(birthdate.value);
        var today = new Date();
        var years = today.getFullYear() - bd.getFullYear();
        var m = today.getMonth() - bd.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < bd.getDate())) years--;
        age.value = years >= 0 ? years : '';
    }

    function toggleStrand() {
        if (!gradeSelect || !strandField) return;
        var opt = gradeSelect.options[gradeSelect.selectedIndex];
        var isSHS = opt && opt.getAttribute('data-type') === 'Senior High School';
        strandField.style.display = isSHS ? '' : 'none';
        if (strandSelect) strandSelect.required = !!isSHS;
        if (!isSHS && strandSelect) strandSelect.value = '';
    }

    if (birthdate) {
        birthdate.addEventListener('change', computeAge);
        computeAge();
    }
    if (gradeSelect) {
        gradeSelect.addEventListener('change', toggleStrand);
        toggleStrand();
    }

    initLrnField();
    initPhotoWidget();
});

// ---------------------------------------------------------------------
// Learner Reference Number (LRN): digits only, hard-capped at 12 chars,
// with an inline error message matching the server-side validation rule
// in includes/functions.php::validate_lrn().
// ---------------------------------------------------------------------
function initLrnField() {
    var lrn = document.getElementById('lrn');
    var lrnError = document.getElementById('lrnError');
    if (!lrn) return;

    var LRN_MESSAGE = trans('validation.lrn_invalid', 'LRN must contain exactly 12 numeric digits.');

    function sanitize() {
        var digitsOnly = lrn.value.replace(/\D/g, '').slice(0, 12);
        if (digitsOnly !== lrn.value) lrn.value = digitsOnly;
    }

    function validate() {
        var value = lrn.value;
        var isRequired = lrn.hasAttribute('required');
        var valid = (!isRequired && value === '') || /^\d{12}$/.test(value);
        if (!valid) {
            lrn.setCustomValidity(LRN_MESSAGE);
            if (lrnError) lrnError.textContent = LRN_MESSAGE;
        } else {
            lrn.setCustomValidity('');
            if (lrnError) lrnError.textContent = '';
        }
        return valid;
    }

    lrn.addEventListener('input', function () {
        sanitize();
        validate();
    });
    lrn.addEventListener('blur', validate);
    lrn.addEventListener('invalid', function () {
        if (lrnError) lrnError.textContent = LRN_MESSAGE;
    });

    sanitize();
}

// ---------------------------------------------------------------------
// Student photo widget: circular preview, click-to-upload, drag-and-drop,
// instant preview, and Upload/Change/Remove Photo button states.
// Mirrors the server-side rules in includes/functions.php::handle_photo_upload()
// (JPG/JPEG/PNG only, 5MB max) - this is UX sugar; the server remains authoritative.
// ---------------------------------------------------------------------
function initPhotoWidget() {
    var input = document.getElementById('photoInput');
    var dropZone = document.getElementById('photoDropZone');
    var preview = document.getElementById('photoPreviewCircle');
    var uploadBtn = document.getElementById('uploadPhotoBtn');
    var changeBtn = document.getElementById('changePhotoBtn');
    var removeBtn = document.getElementById('removePhotoBtn');
    var removeFlag = document.getElementById('removePhotoFlag');
    var errorEl = document.getElementById('photoError');
    if (!input || !preview) return;

    var ALLOWED_TYPES = ['image/jpeg', 'image/png'];
    var MAX_SIZE = 5 * 1024 * 1024; // 5 MB
    var sizeClass = 'avatar-lg';
    var existingAvatar = preview.querySelector('.student-avatar');
    if (existingAvatar) {
        for (var i = 0; i < existingAvatar.classList.length; i++) {
            if (/^avatar-(sm|md|lg)$/.test(existingAvatar.classList[i])) {
                sizeClass = existingAvatar.classList[i];
                break;
            }
        }
    }

    var PLACEHOLDER_HTML = '<span class="student-avatar ' + sizeClass + ' student-avatar-placeholder">'
        + '<svg class="avatar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9V22h19.6v-2.6c0-3.3-6.5-4.9-9.8-4.9z"/></svg>'
        + '<span class="avatar-label">No Photo</span>'
        + '</span>';

    function showError(msg) {
        if (errorEl) errorEl.textContent = msg;
    }

    function clearError() {
        showError('');
    }

    function setUploadedState() {
        if (uploadBtn) uploadBtn.hidden = true;
        if (changeBtn) changeBtn.hidden = false;
        if (removeBtn) removeBtn.hidden = false;
    }

    function setEmptyState() {
        if (uploadBtn) uploadBtn.hidden = false;
        if (changeBtn) changeBtn.hidden = true;
        if (removeBtn) removeBtn.hidden = true;
    }

    function validateFile(file) {
        if (ALLOWED_TYPES.indexOf(file.type) === -1) {
            return trans('validation.photo_invalid', 'Invalid photo type. Please upload a JPG, JPEG, or PNG image.');
        }
        if (file.size > MAX_SIZE) {
            return trans('validation.photo_too_large', 'Photo is too large. Maximum size is 5MB.');
        }
        return null;
    }

    function applyFile(file) {
        var err = validateFile(file);
        if (err) {
            showError(err);
            input.value = '';
            return;
        }
        clearError();
        var reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = '<span class="student-avatar ' + sizeClass + '"><img src="' + e.target.result + '" alt="Student Photo"></span>';
        };
        reader.readAsDataURL(file);
        setUploadedState();
        if (removeFlag) removeFlag.value = '0';
    }

    function openPicker() {
        input.click();
    }

    if (uploadBtn) uploadBtn.addEventListener('click', openPicker);
    if (changeBtn) changeBtn.addEventListener('click', openPicker);
    if (dropZone) dropZone.addEventListener('click', openPicker);

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) {
            applyFile(input.files[0]);
        }
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            input.value = '';
            clearError();
            preview.innerHTML = PLACEHOLDER_HTML;
            setEmptyState();
            if (removeFlag) removeFlag.value = '1';
        });
    }

    if (dropZone) {
        ['dragenter', 'dragover'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('drag-over');
            });
        });
        dropZone.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                applyFile(files[0]);
            }
        });
    }
}
