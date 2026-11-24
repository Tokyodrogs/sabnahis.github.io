// Client-side helpers for the Student Portal (login + change password).
// Server-side validation (includes/functions.php::validate_password_policy())
// remains authoritative; this is UX sugar only, same philosophy as
// assets/js/validation.js used by the admin panel and public site.
//
// trans(key, fallback): same pattern as assets/js/validation.js - uses
// assets/js/i18n.js's translator where it's loaded (public site, Student
// Portal), falls back to the English literal on pages without it.
function trans(key, fallback) {
    return (window.SABNAHIS_I18N && window.SABNAHIS_I18N.t) ? window.SABNAHIS_I18N.t(key, fallback) : fallback;
}

document.addEventListener('DOMContentLoaded', function () {
    initPasswordToggles();
    initPasswordStrengthChecklist();
    initPasswordStrengthMeter();
    initConfirmPasswordMatch();
    initUsernameAvailabilityCheck();
});

// ---------------------------------------------------------------------
// Show/Hide Password: every .password-toggle button flips the type of the
// <input> inside its closest .password-field wrapper.
// ---------------------------------------------------------------------
function initPasswordToggles() {
    var toggles = document.querySelectorAll('.password-toggle');
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var field = btn.closest('.password-field');
            if (!field) return;
            var input = field.querySelector('input');
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.textContent = isHidden ? trans('common.hide', 'Hide') : trans('common.show', 'Show');
        });
    });
}

// ---------------------------------------------------------------------
// Live password rule checklist on the Change Password page, mirroring
// includes/functions.php::validate_password_policy() rule-for-rule.
// ---------------------------------------------------------------------
function initPasswordStrengthChecklist() {
    var newPassword = document.getElementById('newPassword');
    var rules = document.getElementById('passwordRules');
    if (!newPassword || !rules) return;

    var checks = {
        length: function (v) { return v.length >= 8; },
        upper: function (v) { return /[A-Z]/.test(v); },
        lower: function (v) { return /[a-z]/.test(v); },
        number: function (v) { return /[0-9]/.test(v); },
        special: function (v) { return /[^A-Za-z0-9]/.test(v); }
    };

    function update() {
        var value = newPassword.value;
        Object.keys(checks).forEach(function (rule) {
            var li = rules.querySelector('[data-rule="' + rule + '"]');
            if (!li) return;
            li.classList.toggle('rule-valid', checks[rule](value));
        });
    }

    newPassword.addEventListener('input', update);
    update();
}

// ---------------------------------------------------------------------
// Password strength meter (Weak / Medium / Strong) on the "Create Your
// Student Portal Account" section of enroll.php. UX sugar layered on top
// of the same rule set as validate_password_policy() - server-side
// validation remains authoritative.
// ---------------------------------------------------------------------
function initPasswordStrengthMeter() {
    var newPassword = document.getElementById('newPassword');
    var fill = document.getElementById('passwordStrengthFill');
    var label = document.getElementById('passwordStrengthLabel');
    if (!newPassword || !fill || !label) return;

    function scorePassword(value) {
        var score = 0;
        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/[A-Z]/.test(value)) score++;
        if (/[a-z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        return score;
    }

    function update() {
        var value = newPassword.value;
        fill.className = 'password-strength-fill';
        if (value === '') {
            fill.style.width = '0%';
            label.textContent = '';
            return;
        }

        var score = scorePassword(value);
        var strength, className, width;
        if (score <= 2) {
            strength = 'Weak'; className = 'strength-weak'; width = '33%';
        } else if (score <= 4) {
            strength = 'Medium'; className = 'strength-medium'; width = '66%';
        } else {
            strength = 'Strong'; className = 'strength-strong'; width = '100%';
        }

        var strengthLabels = {
            Weak: trans('common.weak', 'Weak'),
            Medium: trans('common.medium', 'Medium'),
            Strong: trans('common.strong', 'Strong')
        };

        fill.style.width = width;
        fill.classList.add(className);
        label.textContent = trans('validation.password_strength', 'Password Strength: ') + strengthLabels[strength];
        label.className = 'password-strength-label ' + className;
    }

    newPassword.addEventListener('input', update);
    update();
}

// ---------------------------------------------------------------------
// Live "passwords match" message under Confirm Password.
// ---------------------------------------------------------------------
function initConfirmPasswordMatch() {
    var newPassword = document.getElementById('newPassword');
    var confirmPassword = document.getElementById('confirmPassword');
    var message = document.getElementById('confirmPasswordMatch');
    if (!newPassword || !confirmPassword || !message) return;

    function update() {
        if (confirmPassword.value === '') {
            message.textContent = '';
            message.className = 'field-availability';
            return;
        }
        var matches = newPassword.value === confirmPassword.value;
        message.textContent = matches
            ? trans('validation.passwords_match', 'Passwords match')
            : trans('validation.passwords_no_match', 'Passwords do not match');
        message.className = 'field-availability ' + (matches ? 'availability-ok' : 'availability-taken');
    }

    newPassword.addEventListener('input', update);
    confirmPassword.addEventListener('input', update);
}

// ---------------------------------------------------------------------
// Live Student Portal username availability check (debounced) against
// check_username.php, on the "Create Your Student Portal Account" section
// of enroll.php. Server-side validate_student_username() plus a fresh
// duplicate check on submit remain authoritative - this is UX sugar only.
// ---------------------------------------------------------------------
function initUsernameAvailabilityCheck() {
    var input = document.getElementById('portalUsername');
    var message = document.getElementById('usernameAvailability');
    if (!input || !message) return;

    var debounceTimer = null;
    var currentRequest = 0;

    function setMessage(text, cls) {
        message.textContent = text;
        message.className = 'field-availability ' + (cls || '');
    }

    function check() {
        var value = input.value.trim();
        if (value === '') {
            setMessage('');
            return;
        }
        if (value.length < 5 || value.length > 30 || !/^[A-Za-z0-9._]+$/.test(value)) {
            setMessage('5-30 characters: letters, numbers, underscore (_), period (.) only', 'availability-taken');
            return;
        }

        setMessage('Checking availability...', 'availability-checking');
        var requestId = ++currentRequest;

        fetch('check_username.php?username=' + encodeURIComponent(value))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (requestId !== currentRequest) return; // a newer keystroke already superseded this request
                if (data.available) {
                    setMessage('✔ ' + data.message, 'availability-ok');
                } else {
                    setMessage('✖ ' + data.message, 'availability-taken');
                }
            })
            .catch(function () {
                if (requestId !== currentRequest) return;
                setMessage('');
            });
    }

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(check, 400);
    });
}
