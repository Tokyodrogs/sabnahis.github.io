// Client-side language switcher engine (2026-08).
//
// How this fits together:
//   - includes/functions.php::t()/get_current_language() render every page
//     in the RIGHT language server-side on first load (no flash of the
//     wrong language) and print the active dictionary inline as
//     window.SABNAHIS_LANG_DICT so this file doesn't need an extra request
//     just to become usable.
//   - Any static text that should be able to switch instantly, without a
//     page reload, gets a data-i18n="dot.path.key" attribute (see
//     lang/en.json for the key structure) instead of hardcoded text.
//     data-i18n-placeholder works the same way for input placeholders.
//   - Switching languages fetches (and caches) the target dictionary,
//     re-renders every data-i18n element in place, remembers the choice in
//     localStorage for instant reuse on the next page load, and tells the
//     server about it (set_language.php) so PHP-rendered content -
//     validation errors, DB content, future page loads - picks it up too.
//   - Other scripts that build their own dynamic text (assets/js/validation.js,
//     assets/js/student.js) can call window.SABNAHIS_I18N.t('some.key', 'English fallback')
//     instead of hardcoding English, and can listen for the 'sabnahis:langchange'
//     event to re-render anything already on screen.
(function () {
    var BASE_URL = window.SABNAHIS_BASE_URL || '/sabnahis/';
    var dictionaries = {};
    var currentLang = window.SABNAHIS_LANG || 'fil';

    if (window.SABNAHIS_LANG_DICT) {
        dictionaries[currentLang] = window.SABNAHIS_LANG_DICT;
    }

    function getPath(dict, key) {
        if (!dict) return undefined;
        var parts = key.split('.');
        var value = dict;
        for (var i = 0; i < parts.length; i++) {
            if (value == null || typeof value !== 'object' || !(parts[i] in value)) return undefined;
            value = value[parts[i]];
        }
        return typeof value === 'string' ? value : undefined;
    }

    function t(key, fallback) {
        var value = getPath(dictionaries[currentLang], key);
        if (value === undefined) value = getPath(dictionaries.en, key);
        if (value === undefined) value = fallback !== undefined ? fallback : key;
        return value;
    }

    function applyToDom() {
        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-i18n');
            var fallback = el.getAttribute('data-i18n-fallback');
            el.textContent = t(key, fallback !== null ? fallback : undefined);
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            var key = el.getAttribute('data-i18n-placeholder');
            var fallback = el.getAttribute('data-i18n-fallback-placeholder');
            el.setAttribute('placeholder', t(key, fallback !== null ? fallback : undefined));
        });
        document.documentElement.setAttribute('lang', currentLang);
        updateSwitcherUI();
    }

    function updateSwitcherUI() {
        document.querySelectorAll('.lang-switcher').forEach(function (widget) {
            var trigger = widget.querySelector('.lang-switcher-current');
            var meta = (window.SABNAHIS_LANGUAGES || {})[currentLang];
            if (trigger && meta) {
                trigger.innerHTML = meta.flag + ' <span class="lang-switcher-current-name">' + meta.native_name + '</span>';
            }
            widget.querySelectorAll('[data-lang-option]').forEach(function (opt) {
                var isActive = opt.getAttribute('data-lang-option') === currentLang;
                opt.classList.toggle('active', isActive);
                var check = opt.querySelector('.lang-switcher-check');
                if (check) check.style.visibility = isActive ? 'visible' : 'hidden';
            });
        });
    }

    function persistServerSide(code) {
        fetch(BASE_URL + 'set_language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'lang=' + encodeURIComponent(code),
            credentials: 'same-origin'
        }).catch(function () { /* best-effort - cookie/localStorage already cover reload persistence */ });
    }

    function setLanguage(code) {
        if (!code || code === currentLang) return;

        function activate() {
            currentLang = code;
            try { localStorage.setItem('sabnahis_lang', code); } catch (e) { /* ignore (private browsing, etc.) */ }
            applyToDom();
            persistServerSide(code);
            document.dispatchEvent(new CustomEvent('sabnahis:langchange', { detail: { lang: code } }));
        }

        if (dictionaries[code]) {
            activate();
            return;
        }

        fetch(BASE_URL + 'lang/' + code + '.json')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                dictionaries[code] = data;
                activate();
            })
            .catch(function () { /* leave language unchanged on network failure */ });
    }

    function initSwitcherToggles() {
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.lang-switcher-trigger');
            var option = e.target.closest('[data-lang-option]');
            var widget = e.target.closest('.lang-switcher');

            if (trigger) {
                var panel = trigger.parentElement.querySelector('.lang-switcher-menu');
                var isOpen = panel && !panel.hidden;
                document.querySelectorAll('.lang-switcher-menu').forEach(function (m) { m.hidden = true; });
                if (panel) panel.hidden = isOpen;
                return;
            }

            if (option) {
                setLanguage(option.getAttribute('data-lang-option'));
                var menu = option.closest('.lang-switcher-menu');
                if (menu) menu.hidden = true;
                return;
            }

            if (!widget) {
                document.querySelectorAll('.lang-switcher-menu').forEach(function (m) { m.hidden = true; });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.lang-switcher-menu').forEach(function (m) { m.hidden = true; });
            }
        });
    }

    window.SABNAHIS_I18N = { t: t, setLanguage: setLanguage, getCurrentLang: function () { return currentLang; } };

    document.addEventListener('DOMContentLoaded', function () {
        // Normally the server already rendered the right language (session +
        // cookie), so this is a no-op. It only kicks in if localStorage
        // remembers a different choice than the cookie did (e.g. cookies
        // blocked, or the cookie expired but localStorage didn't) - localStorage
        // is the tie-breaker per spec.
        var saved = null;
        try { saved = localStorage.getItem('sabnahis_lang'); } catch (e) { /* ignore */ }
        if (saved && saved !== currentLang && (window.SABNAHIS_LANGUAGES || {})[saved]) {
            setLanguage(saved);
        }

        applyToDom();
        initSwitcherToggles();
    });
})();
