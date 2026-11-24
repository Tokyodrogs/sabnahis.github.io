/*
 * Display Settings engine (2026-08): theme, text size, reduce motion.
 * Loaded in <head> (like i18n.js) so the no-flash block below can set
 * data-theme on <html> BEFORE first paint. Persists to localStorage under
 * sabnahis_theme / sabnahis_text_size / sabnahis_reduce_motion.
 */
(function () {
    'use strict';

    var STORAGE = {
        theme: 'sabnahis_theme',
        textSize: 'sabnahis_text_size',
        reduceMotion: 'sabnahis_reduce_motion'
    };

    var DEFAULTS = {
        theme: 'system',
        textSize: 'default',
        reduceMotion: 'off'
    };

    function safeGet(key, fallback) {
        try {
            var v = window.localStorage.getItem(key);
            return v === null ? fallback : v;
        } catch (e) {
            return fallback;
        }
    }

    function safeSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            /* localStorage unavailable (private mode, etc.) - fail silently */
        }
    }

    var systemDarkQuery = null;
    try {
        systemDarkQuery = window.matchMedia('(prefers-color-scheme: dark)');
    } catch (e) {
        systemDarkQuery = null;
    }

    function effectiveTheme(pref) {
        if (pref === 'light' || pref === 'dark') {
            return pref;
        }
        // 'system'
        if (systemDarkQuery && systemDarkQuery.matches) {
            return 'dark';
        }
        return 'light';
    }

    function applyAll() {
        var themePref = safeGet(STORAGE.theme, DEFAULTS.theme);
        var textSize = safeGet(STORAGE.textSize, DEFAULTS.textSize);
        var reduceMotion = safeGet(STORAGE.reduceMotion, DEFAULTS.reduceMotion);

        var root = document.documentElement;
        root.setAttribute('data-theme', effectiveTheme(themePref));
        root.setAttribute('data-theme-pref', themePref);
        root.setAttribute('data-text-size', textSize);
        root.setAttribute('data-reduce-motion', reduceMotion === 'on' ? 'on' : 'off');
    }

    // Run immediately (synchronously, before DOMContentLoaded) to avoid a
    // flash of the wrong theme.
    applyAll();

    // Keep in sync with OS-level scheme changes while pref is 'system'.
    if (systemDarkQuery) {
        var onSystemChange = function () {
            var themePref = safeGet(STORAGE.theme, DEFAULTS.theme);
            if (themePref === 'system') {
                document.documentElement.setAttribute('data-theme', effectiveTheme('system'));
            }
        };
        if (typeof systemDarkQuery.addEventListener === 'function') {
            systemDarkQuery.addEventListener('change', onSystemChange);
        } else if (typeof systemDarkQuery.addListener === 'function') {
            systemDarkQuery.addListener(onSystemChange);
        }
    }

    function syncPanelUI() {
        var themePref = safeGet(STORAGE.theme, DEFAULTS.theme);
        var textSize = safeGet(STORAGE.textSize, DEFAULTS.textSize);
        var reduceMotion = safeGet(STORAGE.reduceMotion, DEFAULTS.reduceMotion);

        var themeButtons = document.querySelectorAll('[data-theme-option]');
        for (var i = 0; i < themeButtons.length; i++) {
            var isActive = themeButtons[i].getAttribute('data-theme-option') === themePref;
            themeButtons[i].classList.toggle('active', isActive);
        }

        var sizeButtons = document.querySelectorAll('[data-text-size-option]');
        for (var j = 0; j < sizeButtons.length; j++) {
            var sizeActive = sizeButtons[j].getAttribute('data-text-size-option') === textSize;
            sizeButtons[j].classList.toggle('active', sizeActive);
        }

        var motionToggle = document.getElementById('reduceMotionToggle');
        if (motionToggle) {
            var isOn = reduceMotion === 'on';
            motionToggle.classList.toggle('is-on', isOn);
            motionToggle.setAttribute('aria-pressed', isOn ? 'true' : 'false');
            var label = motionToggle.querySelector('.reduce-motion-toggle-label');
            if (label) {
                label.textContent = isOn ? 'ON' : 'OFF';
            } else {
                motionToggle.textContent = isOn ? 'ON' : 'OFF';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var trigger = document.getElementById('displaySettingsToggle');
        var panel = document.getElementById('displaySettingsPanel');
        var wrapper = trigger ? trigger.closest('.display-settings') : null;

        if (!trigger || !panel) {
            return;
        }

        function openPanel() {
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            syncPanelUI();
        }

        function closePanel() {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        function togglePanel() {
            if (panel.hidden) {
                openPanel();
            } else {
                closePanel();
            }
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            togglePanel();
        });

        document.addEventListener('click', function (e) {
            if (!panel.hidden && wrapper && !wrapper.contains(e.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) {
                closePanel();
                trigger.focus();
            }
        });

        panel.addEventListener('click', function (e) {
            var themeBtn = e.target.closest('[data-theme-option]');
            if (themeBtn) {
                safeSet(STORAGE.theme, themeBtn.getAttribute('data-theme-option'));
                applyAll();
                syncPanelUI();
                return;
            }

            var sizeBtn = e.target.closest('[data-text-size-option]');
            if (sizeBtn) {
                safeSet(STORAGE.textSize, sizeBtn.getAttribute('data-text-size-option'));
                applyAll();
                syncPanelUI();
                return;
            }

            var motionBtn = e.target.closest('#reduceMotionToggle');
            if (motionBtn) {
                var current = safeGet(STORAGE.reduceMotion, DEFAULTS.reduceMotion);
                safeSet(STORAGE.reduceMotion, current === 'on' ? 'off' : 'on');
                applyAll();
                syncPanelUI();
                return;
            }

            var resetBtn = e.target.closest('#displaySettingsReset');
            if (resetBtn) {
                safeSet(STORAGE.theme, DEFAULTS.theme);
                safeSet(STORAGE.textSize, DEFAULTS.textSize);
                // Respect OS reduce-motion accessibility preference on reset
                // rather than forcing it off, per spec.
                var prefersReducedMotion = false;
                try {
                    prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                } catch (err) {
                    prefersReducedMotion = false;
                }
                safeSet(STORAGE.reduceMotion, prefersReducedMotion ? 'on' : DEFAULTS.reduceMotion);
                applyAll();
                syncPanelUI();

                var msg = document.getElementById('displaySettingsResetMsg');
                if (msg) {
                    msg.hidden = false;
                    window.clearTimeout(msg._hideTimer);
                    msg._hideTimer = window.setTimeout(function () {
                        msg.hidden = true;
                    }, 3000);
                }
            }
        });

        syncPanelUI();
    });
}());
