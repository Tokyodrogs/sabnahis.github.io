// Multi-applicant-type online enrollment wizard (2026-08d), enroll.php.
//
// Drives the client-side step flow (Applicant Type -> [Lookup for Old
// Student] -> Personal Info -> Academic Info -> Parent Info ->
// Requirements -> Review -> Submit). The actual final submit is a normal
// page POST (not AJAX) - this file only manages which panel is visible,
// per-step validation before advancing, the Old Student record lookup,
// the searchable school directory, the review summary, and a
// localStorage draft autosave. Server-side (enroll.php) re-validates and
// re-enforces everything that matters for security - this file is UX only.
document.addEventListener('DOMContentLoaded', function () {
    var stepTypePanel = document.getElementById('stepType');
    if (!stepTypePanel) return; // wizard isn't on screen in this view state (success/closed/etc.)

    var BASE_URL = window.SABNAHIS_BASE_URL || '/sabnahis/';
    var TYPE_LABELS = { New: 'New Student', Returning: 'Old Student', Transferee: 'Transferee', 'Balik-Aral': 'Balik-Aral', ALS: 'ALS' };

    var state = { type: null, alsPathway: null };
    var lookupData = null;
    var formSubmitted = false;

    var panels = {
        type: document.getElementById('stepType'),
        lookup: document.getElementById('stepLookup'),
        personal: document.getElementById('stepPersonal'),
        academic: document.getElementById('stepAcademic'),
        parent: document.getElementById('stepParent'),
        requirements: document.getElementById('stepRequirements'),
        review: document.getElementById('stepReview'),
    };
    var stepOrder = ['type', 'personal', 'academic', 'parent', 'requirements', 'review'];
    var stepperItems = document.querySelectorAll('#wizardStepper li');
    var formActionInput = document.getElementById('formActionInput');
    var studentTypeInput = document.getElementById('studentTypeInput');
    var enrollForm = document.getElementById('enrollForm');

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function toggleHidden(id, hide) {
        var el = document.getElementById(id);
        if (el) el.hidden = hide;
    }

    function setRequired(id, required) {
        var el = document.getElementById(id);
        if (!el) return;
        if (required) el.setAttribute('required', 'required'); else el.removeAttribute('required');
    }

    // Toggles `required` on every data-required="1" file input inside a
    // Requirements-step container - see applyTypeVisibility() below.
    function setDocsRequired(containerId, isActive) {
        var container = document.getElementById(containerId);
        if (!container) return;
        container.querySelectorAll('input[type="file"][data-required="1"]').forEach(function (el) {
            if (isActive) el.setAttribute('required', 'required'); else el.removeAttribute('required');
        });
    }

    // Collects the chosen filenames from every file input in a Requirements
    // container (required + optional alike) - used by buildReviewSummary().
    function collectSelectedDocNames(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return [];
        var names = [];
        container.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (input.files && input.files[0]) names.push(input.files[0].name);
        });
        return names;
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function selectText(id) {
        var el = document.getElementById(id);
        if (!el || el.selectedIndex < 0) return '';
        return el.options[el.selectedIndex].text;
    }

    function showPanel(name) {
        Object.keys(panels).forEach(function (key) {
            if (panels[key]) panels[key].hidden = (key !== name);
        });
        updateStepper(name);
        if (panels[name]) {
            window.scrollTo({ top: Math.max(panels[name].getBoundingClientRect().top + window.scrollY - 90, 0), behavior: 'smooth' });
        }
    }

    function updateStepper(name) {
        var stepperKey = (name === 'lookup') ? 'type' : name;
        var idx = stepOrder.indexOf(stepperKey);
        stepperItems.forEach(function (li) {
            var liKey = li.getAttribute('data-step');
            var liIdx = stepOrder.indexOf(liKey);
            li.classList.toggle('wizard-step-active', liKey === stepperKey);
            li.classList.toggle('wizard-step-done', liIdx > -1 && liIdx < idx);
        });
    }

    // -------------------------------------------------------------
    // Step 1: Applicant Type cards
    // -------------------------------------------------------------
    var cards = document.querySelectorAll('.applicant-card');
    var typeContinueBtn = document.getElementById('wizardTypeContinueBtn');
    var alsPathwaySection = document.getElementById('alsPathwaySection');
    var alsPathwayCards = document.querySelectorAll('.als-pathway-card');
    var alsPathwayInput = document.getElementById('alsPathwayInput');

    function updateTypeContinueState() {
        if (!typeContinueBtn) return;
        if (state.type === 'ALS') {
            typeContinueBtn.disabled = !state.alsPathway;
        } else {
            typeContinueBtn.disabled = !state.type;
        }
    }

    function selectCard(card) {
        if (card.getAttribute('data-enabled') !== '1') return;
        cards.forEach(function (c) { c.classList.remove('selected'); c.setAttribute('aria-pressed', 'false'); });
        card.classList.add('selected');
        card.setAttribute('aria-pressed', 'true');
        state.type = card.getAttribute('data-type');

        // ALS only has one pathway left (2026-08j - the other two were
        // removed, not just left disabled). Auto-select it the moment ALS
        // is chosen so Continue is enabled immediately with no extra click;
        // selectAlsPathway() (defined below) is a function declaration so
        // it's safely hoisted and callable here. Switching away from ALS
        // (or re-selecting a different type) clears the pathway so a stale
        // selection can't sneak through on submit.
        if (state.type === 'ALS') {
            if (alsPathwaySection) alsPathwaySection.hidden = false;
            if (alsPathwayCards.length === 1) selectAlsPathway(alsPathwayCards[0]);
        } else {
            if (alsPathwaySection) alsPathwaySection.hidden = true;
            state.alsPathway = null;
            if (alsPathwayInput) alsPathwayInput.value = '';
            alsPathwayCards.forEach(function (c) { c.classList.remove('selected'); c.setAttribute('aria-pressed', 'false'); });
        }
        updateTypeContinueState();
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () { selectCard(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectCard(card);
            }
        });
    });

    function selectAlsPathway(card) {
        if (card.getAttribute('data-enabled') !== '1') return;
        alsPathwayCards.forEach(function (c) { c.classList.remove('selected'); c.setAttribute('aria-pressed', 'false'); });
        card.classList.add('selected');
        card.setAttribute('aria-pressed', 'true');
        state.alsPathway = card.getAttribute('data-pathway');
        if (alsPathwayInput) alsPathwayInput.value = state.alsPathway;
        updateTypeContinueState();
    }

    alsPathwayCards.forEach(function (card) {
        card.addEventListener('click', function () { selectAlsPathway(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectAlsPathway(card);
            }
        });
    });

    if (typeContinueBtn) {
        typeContinueBtn.addEventListener('click', function () {
            if (!state.type) return;
            if (state.type === 'ALS' && !state.alsPathway) return;
            studentTypeInput.value = state.type;
            applyTypeVisibility();
            if (state.type === 'Returning' || state.type === 'Balik-Aral') {
                updateLookupPanelForType();
                showPanel('lookup');
            } else {
                // ALS Phase 1 only implements the direct "Enroll in ALS
                // Program" pathway, which - like New Student and
                // Transferee - always creates a new record (no lookup step).
                formActionInput.value = 'enroll';
                showPanel('personal');
            }
        });
    }

    function applyTypeVisibility() {
        var isNew = state.type === 'New';
        var isReturning = state.type === 'Returning';
        var isTransferee = state.type === 'Transferee';
        var isBalikAral = state.type === 'Balik-Aral';
        var isALS = state.type === 'ALS';
        // Transferee always free-picks a grade; Balik-Aral does too UNLESS a
        // record is found via lookup, in which case fillFromLookup() below
        // re-confirms the same free-grade block (Balik-Aral placement after
        // a gap is a judgment call, not an automatic +1 like Old Student).
        // ALS (Phase 1) doesn't assign a grade_level_id at all - see
        // alsEducationBlock instead.
        var freeGradeType = isTransferee || isBalikAral;

        toggleHidden('elementarySchoolRow', !(isNew || isTransferee || isBalikAral || isALS));
        clearSchoolAutoFill();
        // New Student is always Elementary-only (not optional), so the
        // Level filter dropdown would be redundant/misleading there - hide
        // it and make sure it isn't left set to something stale.
        toggleHidden('schoolFilterLevel', isNew);
        if (isNew && schoolFilterLevel) schoolFilterLevel.value = '';
        toggleHidden('transfereeFieldsBlock', !isTransferee);
        toggleHidden('balikAralReturnInfoBlock', !isBalikAral);
        toggleHidden('balikAralManualBlock', !isBalikAral);
        setRequired('fReasonTransfer', isTransferee);
        setRequired('fYearsNotEnrolled', isBalikAral);
        setRequired('fReasonReturning', isBalikAral);
        setRequired('fLastGradeCompleted', isBalikAral);
        setRequired('fLastSchoolYearAttended', isBalikAral);

        toggleHidden('newStudentGradeBlock', !isNew);
        toggleHidden('oldStudentGradeBlock', !isReturning);
        toggleHidden('freeGradeBlock', !freeGradeType);
        setRequired('freeGradeLevelId', freeGradeType);
        toggleHidden('newStudentDocsBlock', !isNew);
        toggleHidden('oldStudentDocsBlock', !isReturning);
        toggleHidden('transfereeDocsBlock', !isTransferee);
        toggleHidden('balikAralDocsBlock', !isBalikAral);
        toggleHidden('alsDocsBlock', !isALS);

        // Required-doc list per type is admin-configurable (requirement_configs
        // table, 2026-08h) rather than a fixed set of field names, so this
        // toggles by container scope + a data-required marker instead of a
        // hardcoded name list - stays correct no matter what an admin adds/
        // removes/renames in admin/requirements.php.
        setDocsRequired('newStudentDocsBlock', isNew);
        setDocsRequired('oldStudentDocsBlock', isReturning);
        setDocsRequired('transfereeDocsBlock', isTransferee);
        setDocsRequired('balikAralDocsBlock', isBalikAral);
        setDocsRequired('alsDocsBlock', isALS);

        // The searchable school field is reused across four types with a
        // different label and a different required-ness each time.
        var schoolLabelText = document.getElementById('schoolFieldLabelText');
        var schoolRequiredMark = document.getElementById('schoolFieldRequiredMark');
        if (schoolLabelText) {
            if (isTransferee) schoolLabelText.textContent = 'Previous School';
            else if (isBalikAral) schoolLabelText.textContent = 'Last School Attended';
            else if (isALS) schoolLabelText.textContent = 'Previous School';
            else schoolLabelText.textContent = 'Elementary School';
        }
        // Optional for Balik-Aral, and for ALS whenever "I have never
        // attended a formal school" is checked (see alsNeverAttendedFormal
        // listener below).
        if (schoolRequiredMark) schoolRequiredMark.hidden = isBalikAral || (isALS && neverAttendedCheckbox && neverAttendedCheckbox.checked);

        // ALS-only Personal Info extras + "never attended formal school" option.
        toggleHidden('alsPersonalExtraBlock', !isALS);
        toggleHidden('alsNeverAttendedWrap', !isALS);

        // ALS Educational Background (stepAcademic, relabeled below) and
        // Learning Profile (stepParent, relabeled below) replace the grade/
        // strand and parent/guardian content respectively for this type.
        toggleHidden('alsEducationBlock', !isALS);
        setRequired('fAlsHighestEducation', isALS);
        setRequired('fAlsReasonLeaving', isALS);
        toggleHidden('guardianFieldsBlock', isALS);
        setRequired('fGuardianName', !isALS);
        setRequired('fGuardianContact', !isALS);
        toggleHidden('alsLearningProfileBlock', !isALS);
        setRequired('fAlsLearningPreference', isALS);
        setRequired('fAlsEmploymentStatus', isALS);
        setRequired('fAlsLearningGoal', isALS);

        var academicHeading = document.getElementById('stepAcademicHeading');
        if (academicHeading) {
            var academicKey = isALS ? 'enroll.educational_background' : 'enroll.academic_info';
            academicHeading.setAttribute('data-i18n', academicKey);
            academicHeading.textContent = (window.SABNAHIS_I18N ? window.SABNAHIS_I18N.t(academicKey) : null)
                || (isALS ? 'Educational Background' : 'Academic Information');
        }
        var parentHeading = document.getElementById('stepParentHeading');
        if (parentHeading) {
            var parentKey = isALS ? 'enroll.learning_profile' : 'enroll.guardian_info';
            parentHeading.setAttribute('data-i18n', parentKey);
            parentHeading.textContent = (window.SABNAHIS_I18N ? window.SABNAHIS_I18N.t(parentKey) : null)
                || (isALS ? 'Learning Profile' : 'Parent / Guardian Information');
        }

        if (freeGradeType) {
            toggleHidden('strandField', true);
            setRequired('strand_id', false);
            var freeGradeSelect = document.getElementById('freeGradeLevelId');
            if (freeGradeSelect) freeGradeSelect.value = '';
        }
        if (isALS) {
            toggleHidden('strandField', true);
            setRequired('strand_id', false);
        }

        if (isNew || isTransferee || isALS) {
            // Fresh application, not from a record lookup - make sure
            // nothing is left over/locked from a previous Old Student /
            // Balik-Aral lookup in the same page visit (user went back and
            // picked a different type).
            ['fLastname', 'fFirstname', 'fMiddlename', 'fExtension', 'fSex', 'birthdate', 'lrn',
             'fGuardianName', 'fGuardianContact', 'fGuardianRelationship', 'fGuardianOccupation', 'fGuardianEmergency']
                .forEach(unlockField);
        }
        if (isNew) {
            toggleHidden('strandField', true);
            setRequired('strand_id', false);
        }
    }

    // Grade Level select used by Transferee and Balik-Aral (free choice,
    // unlike New's locked Grade 7 or Old Student's auto-suggested next
    // grade). Deliberately NOT reusing validation.js's dormant
    // toggleStrand()/#grade_level_id binding here: that helper shows/hides
    // #strandField via inline style, which would fight with the global
    // `[hidden] { display: none !important; }` safety-net rule in
    // assets/css/style.css whenever this wizard also has the block's
    // `hidden` attribute set. Managing it entirely through toggleHidden()/
    // setRequired() (same as the rest of this file) keeps a single owner.
    var freeGradeLevelSelect = document.getElementById('freeGradeLevelId');
    if (freeGradeLevelSelect) {
        freeGradeLevelSelect.addEventListener('change', function () {
            var opt = freeGradeLevelSelect.options[freeGradeLevelSelect.selectedIndex];
            var isSHS = opt && opt.getAttribute('data-type') === 'Senior High School';
            toggleHidden('strandField', !isSHS);
            setRequired('strand_id', isSHS);
            if (!isSHS) setVal('strand_id', '');
        });
    }

    function lockField(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') el.disabled = true; else el.readOnly = true;
        el.classList.add('field-locked');
    }

    function unlockField(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') el.disabled = false; else el.readOnly = false;
        el.classList.remove('field-locked');
    }

    function setVal(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value || '';
    }

    // -------------------------------------------------------------
    // Old Student lookup step
    // -------------------------------------------------------------
    var lookupBackBtn = document.getElementById('lookupBackBtn');
    var lookupSubmitBtn = document.getElementById('lookupSubmitBtn');
    var lookupResult = document.getElementById('lookupResult');
    var lookupContinueBtn = document.getElementById('wizardLookupContinueBtn');
    var lookupManualEntryBtn = document.getElementById('lookupManualEntryBtn');

    if (lookupBackBtn) {
        lookupBackBtn.addEventListener('click', function () { showPanel('type'); });
    }

    // Old Student's lookup is a hard requirement (no record = no path
    // forward - contact the registrar). Balik-Aral's spec explicitly allows
    // falling through to manual entry when no record is found (or the
    // applicant doesn't have their old LRN/Student Number handy), so its
    // copy and the "Continue with Manual Entry" fallback only show for that
    // type.
    function updateLookupPanelForType() {
        var introEl = document.getElementById('lookupIntroText');
        var manualHint = document.getElementById('lookupManualEntryHint');
        if (introEl) {
            introEl.textContent = (state.type === 'Balik-Aral')
                ? 'If you were previously enrolled at SABNAHIS, enter your LRN or Student Number and your birthdate to retrieve your record. If you don\'t have these on hand, you can continue with manual entry below instead.'
                : 'Enter your LRN or Student Number, plus your birthdate, to retrieve your enrollment record.';
        }
        if (manualHint) manualHint.hidden = (state.type !== 'Balik-Aral');

        lookupData = null;
        if (lookupResult) lookupResult.innerHTML = '';
        if (lookupContinueBtn) lookupContinueBtn.disabled = true;
        setVal('lookupIdentifier', '');
        setVal('lookupBirthdate', '');
    }

    if (lookupManualEntryBtn) {
        lookupManualEntryBtn.addEventListener('click', function () {
            lookupData = null;
            formActionInput.value = 'enroll';
            // Clear + unlock anything a previous (failed or abandoned)
            // lookup attempt on this same type might have left behind, then
            // reapply the base Balik-Aral-manual visibility state.
            ['fLastname', 'fFirstname', 'fMiddlename', 'fExtension', 'fSex', 'birthdate', 'lrn',
             'fAddress', 'fContact', 'fEmail', 'fGuardianName', 'fGuardianContact',
             'fGuardianRelationship', 'fGuardianOccupation', 'fGuardianEmergency']
                .forEach(function (id) { unlockField(id); setVal(id, ''); });
            applyTypeVisibility();
            showPanel('personal');
        });
    }

    if (lookupSubmitBtn) {
        lookupSubmitBtn.addEventListener('click', function () {
            var identifier = (document.getElementById('lookupIdentifier').value || '').trim();
            var birthdate = document.getElementById('lookupBirthdate').value || '';
            if (!identifier || !birthdate) {
                lookupResult.innerHTML = '<div class="alert alert-error">Please enter your LRN or Student Number and your birthdate.</div>';
                return;
            }
            lookupSubmitBtn.disabled = true;
            lookupResult.innerHTML = '<p class="muted">Searching...</p>';

            fetch(BASE_URL + 'lookup_student.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'identifier=' + encodeURIComponent(identifier) + '&birthdate=' + encodeURIComponent(birthdate),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    lookupSubmitBtn.disabled = false;
                    if (!data.found || data.already_enrolled) {
                        lookupResult.innerHTML = '<div class="alert alert-error">' + escapeHtml(data.message || 'Record not found.') + '</div>';
                        if (lookupContinueBtn) lookupContinueBtn.disabled = true;
                        lookupData = null;
                        return;
                    }
                    lookupData = data.student;
                    lookupResult.innerHTML = renderLookupCard(lookupData);
                    if (lookupContinueBtn) lookupContinueBtn.disabled = false;
                })
                .catch(function () {
                    lookupSubmitBtn.disabled = false;
                    lookupResult.innerHTML = '<div class="alert alert-error">Something went wrong. Please try again.</div>';
                });
        });
    }

    function renderLookupCard(s) {
        var name = [s.firstname, s.middlename, s.lastname, s.extension].filter(Boolean).join(' ');
        var html = '<div class="lookup-found-card">';
        if (s.photo_url) html += '<img src="' + escapeHtml(s.photo_url) + '" alt="" class="lookup-found-photo">';
        html += '<div class="lookup-found-details">';
        html += '<p class="lookup-found-name">' + escapeHtml(name) + '</p>';
        html += '<p class="muted">Current Grade: ' + escapeHtml(s.current_grade || '-') + (s.current_strand ? ' (' + escapeHtml(s.current_strand) + ')' : '') + '</p>';
        html += '<p class="muted">Current Section: ' + escapeHtml(s.current_section || 'Not yet assigned') + '</p>';
        html += '<p class="muted">School Year on file: ' + escapeHtml(s.current_sy || '-') + '</p>';
        if (s.next_grade_name) {
            html += '<p class="lookup-next-grade">Suggested Next Grade: <strong>' + escapeHtml(s.next_grade_name) + '</strong></p>';
        } else {
            html += '<p class="lookup-next-grade">No further grade level is configured after your current grade. Please contact the registrar.</p>';
        }
        html += '</div></div>';
        return html;
    }

    if (lookupContinueBtn) {
        lookupContinueBtn.addEventListener('click', function () {
            if (!lookupData) return;
            // Old Student's next-grade suggestion is mandatory - no further
            // grade configured means no path forward for that type. Balik-
            // Aral doesn't depend on it (it uses the free grade select).
            if (state.type === 'Returning' && !lookupData.next_grade_name) return;
            formActionInput.value = 'reenroll';
            fillFromLookup(lookupData);
            showPanel('personal');
        });
    }

    function fillFromLookup(s) {
        setVal('fLastname', s.lastname);
        setVal('fFirstname', s.firstname);
        setVal('fMiddlename', s.middlename);
        setVal('fExtension', s.extension);
        setVal('fSex', s.sex);
        setVal('birthdate', s.birthdate);
        setVal('lrn', s.lrn || s.enrollment_no);
        setVal('fAddress', s.address);
        setVal('fContact', s.contact);
        setVal('fEmail', s.email);
        setVal('fGuardianName', s.guardian_name);
        setVal('fGuardianContact', s.guardian_contact);

        var bd = document.getElementById('birthdate');
        if (bd) bd.dispatchEvent(new Event('change')); // let validation.js recompute Age

        ['fLastname', 'fFirstname', 'fMiddlename', 'fExtension', 'fSex', 'birthdate', 'lrn',
         'fGuardianName', 'fGuardianContact', 'fGuardianRelationship', 'fGuardianOccupation', 'fGuardianEmergency']
            .forEach(lockField);

        toggleHidden('elementarySchoolRow', true);
        toggleHidden('transfereeFieldsBlock', true);
        toggleHidden('balikAralManualBlock', true);
        setRequired('fLastGradeCompleted', false);
        setRequired('fLastSchoolYearAttended', false);

        if (state.type === 'Balik-Aral') {
            // Found via lookup - still ask for the circumstance fields
            // (why they left, how long) but placement is a fresh free
            // choice, not an automatic +1 like Old Student, and their last
            // completed grade is already on file (s.current_grade) so we
            // don't ask for it again.
            toggleHidden('oldStudentGradeBlock', true);
            toggleHidden('freeGradeBlock', false);
            setRequired('freeGradeLevelId', true);
            toggleHidden('balikAralReturnInfoBlock', false);
            setRequired('fYearsNotEnrolled', true);
            setRequired('fReasonReturning', true);
            var freeGradeSelectBA = document.getElementById('freeGradeLevelId');
            if (freeGradeSelectBA) freeGradeSelectBA.value = '';
            toggleHidden('strandField', true);
            setRequired('strand_id', false);
        } else {
            toggleHidden('balikAralReturnInfoBlock', true);
            setRequired('fYearsNotEnrolled', false);
            setRequired('fReasonReturning', false);
            toggleHidden('freeGradeBlock', true);
            setRequired('freeGradeLevelId', false);
            toggleHidden('oldStudentGradeBlock', false);

            var currentGradeEl = document.getElementById('currentGradeDisplay');
            var nextGradeEl = document.getElementById('nextGradeDisplay');
            if (currentGradeEl) currentGradeEl.value = s.current_grade || '';
            if (nextGradeEl) nextGradeEl.value = s.next_grade_name || 'N/A';

            var entersG11 = s.next_grade_name === 'Grade 11';
            toggleHidden('strandField', !entersG11);
            setRequired('strand_id', entersG11);
        }
    }

    // -------------------------------------------------------------
    // Searchable Philippine school directory (New Student's Elementary
    // School field). AJAX, min 2 characters, capped results server-side.
    // -------------------------------------------------------------
    var schoolSearchInput = document.getElementById('previousSchoolSearch');
    var schoolResults = document.getElementById('previousSchoolResults');
    var schoolNameHidden = document.getElementById('previousSchoolName');
    var schoolIdHidden = document.getElementById('previousSchoolId');
    var notListedCheckbox = document.getElementById('previousSchoolNotListed');
    var neverAttendedCheckbox = document.getElementById('alsNeverAttendedFormal');
    var manualInput = document.getElementById('previousSchoolManual');
    var schoolAutoFillCard = document.getElementById('schoolAutoFillCard');
    var schoolFilterRegion = document.getElementById('schoolFilterRegion');
    var schoolFilterProvince = document.getElementById('schoolFilterProvince');
    var schoolFilterLevel = document.getElementById('schoolFilterLevel');
    var schoolSearchTimer = null;
    var schoolSearchRequestId = 0;

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    // Selecting a school from the directory auto-fills its known details
    // read-only, matching the school record on file rather than relying on
    // the applicant to retype them correctly.
    function fillSchoolAutoFill(r) {
        if (!schoolAutoFillCard) return;
        setText('schoolAutoFillRegion', r.region || '-');
        setText('schoolAutoFillDivision', r.division || '-');
        var location = [r.municipality, r.province].filter(Boolean).join(', ');
        setText('schoolAutoFillLocation', location || '-');
        setText('schoolAutoFillType', r.school_type || '-');
        setText('schoolAutoFillLevel', r.education_level_label || '-');
        var depedRow = document.getElementById('schoolAutoFillDepedIdRow');
        if (r.deped_school_id) {
            setText('schoolAutoFillDepedId', r.deped_school_id);
            if (depedRow) depedRow.hidden = false;
        } else if (depedRow) {
            depedRow.hidden = true;
        }
        schoolAutoFillCard.hidden = false;
    }

    function clearSchoolAutoFill() {
        if (schoolAutoFillCard) schoolAutoFillCard.hidden = true;
    }

    if (schoolSearchInput) {
        schoolSearchInput.addEventListener('input', function () {
            schoolNameHidden.value = '';
            schoolIdHidden.value = '';
            clearSchoolAutoFill();
            var q = schoolSearchInput.value.trim();
            clearTimeout(schoolSearchTimer);
            if (q.length < 2) {
                schoolResults.hidden = true;
                schoolResults.innerHTML = '';
                return;
            }
            schoolSearchTimer = setTimeout(function () { runSchoolSearch(q); }, 300);
        });

        document.addEventListener('click', function (e) {
            if (e.target !== schoolSearchInput && !schoolResults.contains(e.target)) {
                schoolResults.hidden = true;
            }
        });
    }

    // Region/Province/Level filter dropdowns (2026-08h) narrow the same
    // text search rather than replacing it - re-run the search whenever a
    // filter changes, as long as there's already a query of 2+ characters
    // to narrow (keeps the "never load the whole directory" rule intact).
    [schoolFilterRegion, schoolFilterProvince, schoolFilterLevel].forEach(function (el) {
        if (!el) return;
        el.addEventListener('change', function () {
            var q = schoolSearchInput ? schoolSearchInput.value.trim() : '';
            if (q.length >= 2) runSchoolSearch(q);
        });
    });

    function runSchoolSearch(q) {
        var requestId = ++schoolSearchRequestId;
        // New Student's Elementary School field is always restricted to
        // schools that offer Elementary (not optional - the Level filter is
        // hidden for New, see applyTypeVisibility()). Transferee/Balik-Aral
        // use whatever the applicant picked in the Level filter, if any.
        var level = (state.type === 'New') ? 'elementary' : (schoolFilterLevel ? schoolFilterLevel.value : '');
        var params = '&level=' + encodeURIComponent(level);
        if (schoolFilterRegion && schoolFilterRegion.value) params += '&region=' + encodeURIComponent(schoolFilterRegion.value);
        if (schoolFilterProvince && schoolFilterProvince.value) params += '&province=' + encodeURIComponent(schoolFilterProvince.value);

        fetch(BASE_URL + 'search_schools.php?q=' + encodeURIComponent(q) + params)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (requestId !== schoolSearchRequestId) return;
                renderSchoolResults(data.results || []);
            })
            .catch(function () { /* ignore - user can still use "not listed" */ });
    }

    function renderSchoolResults(results) {
        if (!results.length) {
            schoolResults.innerHTML = '<div class="school-result-empty">No matches. Try a different search, or check &ldquo;My School Is Not Listed&rdquo; below.</div>';
            schoolResults.hidden = false;
            return;
        }
        schoolResults.innerHTML = results.map(function (r) {
            var location = [r.municipality, r.province].filter(Boolean).join(', ');
            var metaParts = [];
            if (location) metaParts.push(escapeHtml(location));
            if (r.school_type) metaParts.push(escapeHtml(r.school_type));
            if (r.education_level_label) metaParts.push(escapeHtml(r.education_level_label));
            return '<div class="school-result-item" data-id="' + r.school_id + '" data-name="' + escapeHtml(r.school_name) + '"'
                + ' data-region="' + escapeHtml(r.region || '') + '" data-division="' + escapeHtml(r.division || '') + '"'
                + ' data-province="' + escapeHtml(r.province || '') + '" data-municipality="' + escapeHtml(r.municipality || '') + '"'
                + ' data-school-type="' + escapeHtml(r.school_type || '') + '" data-level="' + escapeHtml(r.education_level_label || '') + '"'
                + ' data-deped-id="' + escapeHtml(r.deped_school_id || '') + '">'
                + '<span class="school-result-name">' + escapeHtml(r.school_name) + '</span>'
                + '<span class="school-result-meta">' + metaParts.join(' &middot; ') + '</span>'
                + '</div>';
        }).join('');
        schoolResults.hidden = false;

        schoolResults.querySelectorAll('.school-result-item').forEach(function (item) {
            item.addEventListener('click', function () {
                var name = item.getAttribute('data-name');
                schoolSearchInput.value = name;
                schoolNameHidden.value = name;
                schoolIdHidden.value = item.getAttribute('data-id');
                schoolResults.hidden = true;
                fillSchoolAutoFill({
                    region: item.getAttribute('data-region'),
                    division: item.getAttribute('data-division'),
                    province: item.getAttribute('data-province'),
                    municipality: item.getAttribute('data-municipality'),
                    school_type: item.getAttribute('data-school-type'),
                    education_level_label: item.getAttribute('data-level'),
                    deped_school_id: item.getAttribute('data-deped-id'),
                });
            });
        });
    }

    if (notListedCheckbox) {
        notListedCheckbox.addEventListener('change', function () {
            var checked = notListedCheckbox.checked;
            toggleHidden('previousSchoolSearchWrap', checked);
            toggleHidden('schoolSearchFilters', checked);
            manualInput.hidden = !checked;
            clearSchoolAutoFill();
            if (checked) {
                schoolSearchInput.value = '';
                schoolIdHidden.value = '';
                schoolNameHidden.value = manualInput.value.trim();
            } else {
                manualInput.value = '';
                schoolNameHidden.value = '';
            }
        });
    }

    // ALS-only (2026-08i): "I have never attended a formal school" hides
    // the entire school search UI (including the "not listed" manual-entry
    // fallback, which still implies SOME prior school) and stores the
    // sentinel value the server also falls back to.
    if (neverAttendedCheckbox) {
        neverAttendedCheckbox.addEventListener('change', function () {
            var checked = neverAttendedCheckbox.checked;
            toggleHidden('previousSchoolSearchWrap', checked);
            toggleHidden('schoolSearchFilters', checked);
            if (notListedCheckbox) {
                notListedCheckbox.checked = false;
                notListedCheckbox.closest('label').hidden = checked;
            }
            if (manualInput) { manualInput.hidden = true; manualInput.value = ''; }
            clearSchoolAutoFill();
            var requiredMark = document.getElementById('schoolFieldRequiredMark');
            if (requiredMark) requiredMark.hidden = checked;
            if (checked) {
                if (schoolSearchInput) schoolSearchInput.value = '';
                schoolIdHidden.value = '';
                schoolNameHidden.value = 'No Previous Formal School';
            } else {
                schoolNameHidden.value = '';
            }
        });
    }

    if (manualInput) {
        manualInput.addEventListener('input', function () {
            schoolNameHidden.value = manualInput.value.trim();
            schoolIdHidden.value = '';
        });
    }

    // -------------------------------------------------------------
    // ALS "Other" text reveals (2026-08i) - Reason for Leaving School and
    // Learning Goal both have an "Other" option that needs a free-text
    // follow-up (server-side validation in enroll.php requires this text
    // when the corresponding dropdown is set to "Other").
    // -------------------------------------------------------------
    var alsReasonLeavingSelect = document.getElementById('fAlsReasonLeaving');
    if (alsReasonLeavingSelect) {
        alsReasonLeavingSelect.addEventListener('change', function () {
            var isOther = alsReasonLeavingSelect.value === 'Other';
            toggleHidden('alsReasonLeavingOtherWrap', !isOther);
            setRequired('fAlsReasonLeavingOther', isOther);
            if (!isOther) setVal('fAlsReasonLeavingOther', '');
        });
    }

    var alsLearningGoalSelect = document.getElementById('fAlsLearningGoal');
    if (alsLearningGoalSelect) {
        alsLearningGoalSelect.addEventListener('change', function () {
            var isOther = alsLearningGoalSelect.value === 'Other';
            toggleHidden('alsLearningGoalOtherWrap', !isOther);
            setRequired('fAlsLearningGoalOther', isOther);
            if (!isOther) setVal('fAlsLearningGoalOther', '');
        });
    }

    // -------------------------------------------------------------
    // Step navigation (Next / Back) with per-step validation
    // -------------------------------------------------------------
    document.querySelectorAll('.wizard-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var currentPanel = btn.closest('.wizard-panel');
            if (!validateStep(currentPanel)) return;
            var next = btn.getAttribute('data-next');
            showPanel(next);
            if (next === 'review') buildReviewSummary();
        });
    });

    document.querySelectorAll('.wizard-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var back = btn.getAttribute('data-back');
            var goesToLookup = state.type === 'Returning' || state.type === 'Balik-Aral';
            showPanel(back === 'type-or-lookup' ? (goesToLookup ? 'lookup' : 'type') : back);
        });
    });

    function validateStep(panel) {
        if (!panel) return true;
        var invalid = null;
        panel.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (invalid || el.disabled || el.offsetParent === null) return;
            if (!el.checkValidity()) invalid = el;
        });
        if (invalid) {
            invalid.reportValidity();
            return false;
        }
        // The searchable school field is required for New Student and
        // Transferee; optional (no alert) for Balik-Aral's "Last School
        // Attended", and not shown at all for a Balik-Aral record found via
        // lookup.
        if (panel.id === 'stepPersonal' && (state.type === 'New' || state.type === 'Transferee')) {
            var schoolName = (document.getElementById('previousSchoolName').value || '').trim();
            if (!schoolName) {
                var schoolLabel = state.type === 'Transferee' ? 'Previous School' : 'Elementary School';
                alert('Please select your ' + schoolLabel + ', or check "My School Is Not Listed" and type it in.');
                return false;
            }
        }
        // ALS: same requirement, unless "I have never attended a formal
        // school" is checked (which stores its own sentinel value instead).
        if (panel.id === 'stepPersonal' && state.type === 'ALS') {
            var alsNeverAttended = document.getElementById('alsNeverAttendedFormal');
            var alsSchoolName = (document.getElementById('previousSchoolName').value || '').trim();
            if (!(alsNeverAttended && alsNeverAttended.checked) && !alsSchoolName) {
                alert('Please select your Previous School, check "My School Is Not Listed" and type it in, or check "I have never attended a formal school".');
                return false;
            }
        }
        return true;
    }

    // -------------------------------------------------------------
    // Review step summary
    // -------------------------------------------------------------
    function buildReviewSummary() {
        var summary = document.getElementById('reviewSummary');
        if (!summary) return;

        var rows = [];
        rows.push(['Applicant Type', TYPE_LABELS[state.type] || state.type]);
        rows.push(['Name', [val('fFirstname'), val('fMiddlename'), val('fLastname'), val('fExtension')].filter(Boolean).join(' ')]);
        rows.push(['Sex', val('fSex')]);
        rows.push(['Birthdate', val('birthdate')]);
        rows.push(['Age', val('age')]);
        rows.push(['Address', val('fAddress')]);
        rows.push(['Contact Number', val('fContact')]);
        rows.push(['Email', val('fEmail') || '-']);

        if (state.type === 'New') {
            rows.push(['Elementary School', val('previousSchoolName') || '-']);
            rows.push(['Grade Level', 'Grade 7']);
        } else if (state.type === 'Returning') {
            rows.push(['Current Grade', val('currentGradeDisplay')]);
            rows.push(['Next Grade', val('nextGradeDisplay')]);
            var strandField = document.getElementById('strandField');
            if (strandField && !strandField.hidden) rows.push(['Strand', selectText('strand_id')]);
        } else if (state.type === 'Transferee') {
            rows.push(['Previous School', val('previousSchoolName') || '-']);
            var schoolTypeText = selectText('fSchoolType');
            if (schoolTypeText) rows.push(['School Type', schoolTypeText]);
            rows.push(['Reason for Transfer', selectText('fReasonTransfer')]);
            rows.push(['Grade Level', selectText('freeGradeLevelId')]);
            var strandFieldT = document.getElementById('strandField');
            if (strandFieldT && !strandFieldT.hidden) rows.push(['Strand', selectText('strand_id')]);
        } else if (state.type === 'Balik-Aral') {
            var foundExisting = formActionInput.value === 'reenroll';
            if (!foundExisting) {
                rows.push(['Last School Attended', val('previousSchoolName') || '-']);
                rows.push(['Last Grade Completed', val('fLastGradeCompleted')]);
                rows.push(['Last School Year Attended', val('fLastSchoolYearAttended')]);
            }
            rows.push(['Grade Level', selectText('freeGradeLevelId')]);
            var strandFieldB = document.getElementById('strandField');
            if (strandFieldB && !strandFieldB.hidden) rows.push(['Strand', selectText('strand_id')]);
            rows.push(['Years Not Enrolled', selectText('fYearsNotEnrolled')]);
            rows.push(['Reason for Returning', selectText('fReasonReturning')]);
        } else if (state.type === 'ALS') {
            rows.push(['ALS Pathway', 'Enroll in ALS Program']);
            rows.push(['Place of Birth', val('fAlsPlaceOfBirth') || '-']);
            var alsLocation = [val('fAlsBarangay'), val('fAlsMunicipality'), val('fAlsProvince'), val('fAlsRegion')].filter(Boolean).join(', ');
            if (alsLocation) rows.push(['Barangay / Municipality / Province / Region', alsLocation]);
            var neverAttended = document.getElementById('alsNeverAttendedFormal');
            rows.push(['Previous School', (neverAttended && neverAttended.checked) ? 'None (never attended a formal school)' : (val('previousSchoolName') || '-')]);
            rows.push(['Highest Education Completed', selectText('fAlsHighestEducation')]);
            if (val('fAlsLastGrade')) rows.push(['Last Grade Level Attended', val('fAlsLastGrade')]);
            if (val('fAlsLastYear')) rows.push(['Last Year Attended', val('fAlsLastYear')]);
            var reasonLeaving = selectText('fAlsReasonLeaving');
            rows.push(['Reason for Leaving School', reasonLeaving === 'Other' ? val('fAlsReasonLeavingOther') : reasonLeaving]);
            rows.push(['Preferred Learning Arrangement', selectText('fAlsLearningPreference')]);
            rows.push(['Employment Status', selectText('fAlsEmploymentStatus')]);
            var learningGoal = selectText('fAlsLearningGoal');
            rows.push(['Learning Goal', learningGoal === 'Other' ? val('fAlsLearningGoalOther') : learningGoal]);
            if (val('fAlsReasonEnrolling')) rows.push(['Reason for Enrolling in ALS', val('fAlsReasonEnrolling')]);
        }

        if (state.type !== 'ALS') {
            rows.push(['Parent/Guardian', val('fGuardianName')]);
            var relationship = selectText('fGuardianRelationship');
            if (relationship) rows.push(['Relationship', relationship]);
            rows.push(['Guardian Contact', val('fGuardianContact')]);
            if (val('fGuardianOccupation')) rows.push(['Occupation', val('fGuardianOccupation')]);
            if (val('fGuardianEmergency')) rows.push(['Emergency Contact', val('fGuardianEmergency')]);
        }

        if (state.type === 'New') {
            var fileNames = collectSelectedDocNames('newStudentDocsBlock');
            rows.push(['Documents', fileNames.length ? fileNames.join(', ') : 'None selected']);
        } else if (state.type === 'Transferee') {
            var fileNamesT = collectSelectedDocNames('transfereeDocsBlock');
            rows.push(['Documents', fileNamesT.length ? fileNamesT.join(', ') : 'None selected']);
        } else if (state.type === 'Balik-Aral' && formActionInput.value === 'enroll') {
            var fileNamesB = collectSelectedDocNames('balikAralDocsBlock');
            rows.push(['Documents', fileNamesB.length ? fileNamesB.join(', ') : 'None selected']);
        } else if (state.type === 'ALS') {
            var fileNamesA = collectSelectedDocNames('alsDocsBlock');
            rows.push(['Documents', fileNamesA.length ? fileNamesA.join(', ') : 'None selected']);
        }

        summary.innerHTML = rows.map(function (r) {
            return '<div class="review-row"><dt>' + escapeHtml(r[0]) + '</dt><dd>' + escapeHtml(r[1] || '-') + '</dd></div>';
        }).join('');
    }

    // -------------------------------------------------------------
    // Draft autosave (localStorage only, New Student flow only - Old
    // Student's prefilled/locked fields depend on a fresh, time-limited
    // server-side lookup session, so they aren't safely resumable from a
    // stale local draft).
    // -------------------------------------------------------------
    var DRAFT_KEY = 'sabnahis_enroll_draft';
    var draftFields = ['fLastname', 'fFirstname', 'fMiddlename', 'fExtension', 'fSex', 'birthdate', 'fAddress',
        'fContact', 'fEmail', 'fGuardianName', 'fGuardianRelationship', 'fGuardianContact', 'fGuardianOccupation',
        'fGuardianEmergency', 'lrn'];
    var draftDebounce = null;

    function saveDraft() {
        if (state.type !== 'New') return;
        try {
            var data = { type: state.type, fields: {} };
            draftFields.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) data.fields[id] = el.value;
            });
            localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
        } catch (e) { /* private browsing / storage disabled - draft simply won't persist */ }
    }

    setInterval(saveDraft, 30000);
    enrollForm.addEventListener('input', function () {
        clearTimeout(draftDebounce);
        draftDebounce = setTimeout(saveDraft, 1000);
    });

    function restoreDraftPrompt() {
        var raw;
        try { raw = localStorage.getItem(DRAFT_KEY); } catch (e) { return; }
        if (!raw) return;
        var data;
        try { data = JSON.parse(raw); } catch (e) { return; }
        if (!data || data.type !== 'New' || !data.fields) return;

        var hasContent = Object.keys(data.fields).some(function (k) { return data.fields[k]; });
        if (!hasContent) return;

        if (!confirm('We found a saved draft of your New Student application from earlier. Resume it?')) {
            try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
            return;
        }

        var card = document.querySelector('.applicant-card[data-type="New"]');
        if (card) selectCard(card);
        if (typeContinueBtn) typeContinueBtn.click();

        Object.keys(data.fields).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = data.fields[id];
        });
        var bd = document.getElementById('birthdate');
        if (bd) bd.dispatchEvent(new Event('change'));
    }

    window.addEventListener('beforeunload', function (e) {
        if (state.type && !formSubmitted) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    enrollForm.addEventListener('submit', function () {
        formSubmitted = true;
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
    });

    showPanel('type');
    restoreDraftPrompt();
});
