// Public About SABNAHIS page: gallery lightbox + animated statistics
// counters. Vanilla JS, no external library, consistent with the rest of
// this site's front-end.
document.addEventListener('DOMContentLoaded', function () {
    // ---- Lightbox preview ----
    var lightbox = document.getElementById('aboutLightbox');
    var lightboxImg = document.getElementById('aboutLightboxImg');
    var lightboxCaption = document.getElementById('aboutLightboxCaption');
    var lightboxClose = document.getElementById('aboutLightboxClose');

    if (lightbox) {
        document.querySelectorAll('.js-lightbox-trigger').forEach(function (img) {
            img.addEventListener('click', function () {
                lightboxImg.src = img.src;
                lightboxCaption.textContent = img.dataset.caption || '';
                lightbox.hidden = false;
            });
        });
        lightboxClose.addEventListener('click', function () { lightbox.hidden = true; });
        lightbox.addEventListener('click', function (e) { if (e.target === lightbox) lightbox.hidden = true; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') lightbox.hidden = true; });
    }

    // ---- Animated statistics counters ----
    var counters = document.querySelectorAll('.about-stat-number');
    if (counters.length === 0) return;

    function animateCounter(el) {
        var target = parseInt(el.dataset.count, 10) || 0;
        var duration = 900;
        var start = null;

        function step(timestamp) {
            if (!start) start = timestamp;
            var progress = Math.min((timestamp - start) / duration, 1);
            el.textContent = Math.floor(progress * target).toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString();
            }
        }
        window.requestAnimationFrame(step);
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { observer.observe(el); });
    } else {
        counters.forEach(animateCounter);
    }
});
