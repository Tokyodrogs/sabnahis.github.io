// Photo Gallery admin page: HTML5 drag-and-drop reordering (posts the new
// order to about_gallery_reorder.php via fetch) and a simple lightbox
// preview for gallery thumbnails. No external library - vanilla JS only,
// consistent with the rest of this app's front-end.
document.addEventListener('DOMContentLoaded', function () {
    // ---- Drag-and-drop reorder ----
    var grid = document.getElementById('galleryAdminGrid');
    if (grid) {
        var dragEl = null;

        grid.querySelectorAll('.about-gallery-admin-item').forEach(function (item) {
            item.addEventListener('dragstart', function () {
                dragEl = item;
                item.classList.add('is-dragging');
            });
            item.addEventListener('dragend', function () {
                item.classList.remove('is-dragging');
                saveGalleryOrder();
            });
            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!dragEl || dragEl === item) return;
                var rect = item.getBoundingClientRect();
                var after = (e.clientX - rect.left) > rect.width / 2;
                grid.insertBefore(dragEl, after ? item.nextSibling : item);
            });
        });

        function saveGalleryOrder() {
            var ids = Array.prototype.map.call(
                grid.querySelectorAll('.about-gallery-admin-item'),
                function (el) { return parseInt(el.dataset.id, 10); }
            );
            fetch('about_gallery_reorder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids })
            }).catch(function () { /* silently ignore - page still reflects the visual order until reload */ });
        }
    }

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
    }
});
