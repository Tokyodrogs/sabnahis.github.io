// Public site navigation: dropdown menus (hover on desktop via CSS, click
// via JS here so it also works for touch/mobile and keyboard users),
// hamburger collapse on small screens, outside-click / Escape to close,
// and a subtle shadow on the sticky header once the page is scrolled.
document.addEventListener('DOMContentLoaded', function () {
    var header = document.getElementById('siteHeader');
    var hamburger = document.getElementById('navHamburger');
    var nav = document.getElementById('siteNav');
    var navItems = document.querySelectorAll('.nav-item.has-dropdown');

    // ---- Hamburger (mobile menu collapse) ----
    if (hamburger && nav) {
        hamburger.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    // ---- Dropdown toggles ----
    navItems.forEach(function (item) {
        var toggle = item.querySelector('.dropdown-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var willOpen = !item.classList.contains('is-open');

            // Close any other open dropdown before opening this one.
            navItems.forEach(function (other) {
                if (other !== item) {
                    other.classList.remove('is-open');
                    var otherToggle = other.querySelector('.dropdown-toggle');
                    if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
                }
            });

            item.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    // ---- Close everything on outside click ----
    document.addEventListener('click', function (e) {
        if (nav && !nav.contains(e.target) && e.target !== hamburger) {
            closeAllDropdowns();
        }
    });

    // ---- Close everything on Escape ----
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllDropdowns();
            if (nav) nav.classList.remove('is-open');
            if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
        }
    });

    function closeAllDropdowns() {
        navItems.forEach(function (item) {
            item.classList.remove('is-open');
            var toggle = item.querySelector('.dropdown-toggle');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    }

    // ---- Sticky header shadow on scroll ----
    if (header) {
        function updateHeaderShadow() {
            header.classList.toggle('is-scrolled', window.scrollY > 10);
        }
        window.addEventListener('scroll', updateHeaderShadow, { passive: true });
        updateHeaderShadow();
    }
});
