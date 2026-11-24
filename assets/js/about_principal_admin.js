// Principal's Message admin panel (about_content.php): button-based photo/
// signature upload with a square-crop step (Cropper.js, CDN-loaded), a
// live preview card that mirrors the public about.php layout, remove
// confirmations, and a proper Reset that also restores the rich text
// editor content. Every image still ends up as a normal file on
// #principalPhotoInput / #principalSigInput, so the surrounding form keeps
// submitting the ordinary multipart/form-data way - no AJAX required.
document.addEventListener('DOMContentLoaded', function () {
    initLightbox();
    initPhotoUpload();
    initSignatureUpload();
    initLivePreview();
    initReset();
    initSaveShortcuts();
});

// ---------------------------------------------------------------------
// "Save as Draft" / "Publish" are shortcuts: they force the Status field
// to the matching value before the form submits normally, so the admin
// doesn't have to also remember to change the Status dropdown themselves.
// ---------------------------------------------------------------------
function initSaveShortcuts() {
    var statusSelect = document.getElementById('principalStatus');
    var draftBtn = document.getElementById('principalSaveDraftBtn');
    var publishBtn = document.getElementById('principalPublishBtn');
    if (!statusSelect) return;

    if (draftBtn) {
        draftBtn.addEventListener('click', function () { statusSelect.value = 'Draft'; });
    }
    if (publishBtn) {
        publishBtn.addEventListener('click', function () { statusSelect.value = 'Active'; });
    }
}

// ---------------------------------------------------------------------
// Lightbox ("Preview Full Size" + clicking the photo/signature)
// ---------------------------------------------------------------------
function initLightbox() {
    var lightbox = document.getElementById('aboutLightbox');
    if (!lightbox) return;
    var lightboxImg = document.getElementById('aboutLightboxImg');
    var lightboxCaption = document.getElementById('aboutLightboxCaption');
    var lightboxClose = document.getElementById('aboutLightboxClose');

    function openFor(imgEl) {
        if (!imgEl || imgEl.tagName !== 'IMG' || !imgEl.src) return;
        lightboxImg.src = imgEl.src;
        lightboxCaption.textContent = imgEl.dataset.caption || '';
        lightbox.hidden = false;
    }
    // Exposed globally so dynamically-created preview images (after a crop
    // or a fresh signature upload, which happen after this initial binding
    // pass) can still open the lightbox - see setPhotoPreview() below.
    window.openAboutLightbox = openFor;

    // Delegated click listener instead of binding each .js-lightbox-trigger
    // individually, so it keeps working for images swapped in later.
    document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('js-lightbox-trigger')) {
            openFor(e.target);
        }
    });

    var previewFullBtn = document.getElementById('principalPreviewFullBtn');
    if (previewFullBtn) {
        previewFullBtn.addEventListener('click', function () {
            openFor(document.getElementById('principalPhotoImg'));
        });
    }

    lightboxClose.addEventListener('click', function () { lightbox.hidden = true; });
    lightbox.addEventListener('click', function (e) { if (e.target === lightbox) lightbox.hidden = true; });
}

// ---------------------------------------------------------------------
// Principal Photo: Upload/Replace opens a square-crop modal before the
// file is attached to the form; Remove asks for confirmation first.
// ---------------------------------------------------------------------
function initPhotoUpload() {
    var uploadBtn = document.getElementById('principalUploadBtn');
    var removeBtn = document.getElementById('principalRemoveBtn');
    var fileInput = document.getElementById('principalPhotoInput');
    var removeFlag = document.getElementById('removePrincipalPhotoFlag');
    var previewWrap = document.getElementById('principalPhotoPreview');
    if (!uploadBtn || !fileInput) return;

    var modal = document.getElementById('principalCropperModal');
    var cropperImg = document.getElementById('cropperImage');
    var cropper = null;

    uploadBtn.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (e) {
            cropperImg.src = e.target.result;
            modal.hidden = false;
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropperImg, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                background: false
            });
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('cropZoomIn').addEventListener('click', function () { if (cropper) cropper.zoom(0.1); });
    document.getElementById('cropZoomOut').addEventListener('click', function () { if (cropper) cropper.zoom(-0.1); });
    document.getElementById('cropRotateLeft').addEventListener('click', function () { if (cropper) cropper.rotate(-90); });
    document.getElementById('cropRotateRight').addEventListener('click', function () { if (cropper) cropper.rotate(90); });

    document.getElementById('cropCancelBtn').addEventListener('click', function () {
        if (cropper) { cropper.destroy(); cropper = null; }
        modal.hidden = true;
        fileInput.value = ''; // discard the picked file - nothing to submit
    });

    document.getElementById('cropApplyBtn').addEventListener('click', function () {
        if (!cropper) return;
        cropper.getCroppedCanvas({ width: 600, height: 600 }).toBlob(function (blob) {
            var croppedFile = new File([blob], 'principal_photo.jpg', { type: 'image/jpeg' });
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);
            fileInput.files = dataTransfer.files;

            var url = URL.createObjectURL(blob);
            setPhotoPreview(previewWrap, url);
            var previewPhoto = document.getElementById('previewPhoto');
            if (previewPhoto) { previewPhoto.src = url; previewPhoto.hidden = false; }

            if (removeFlag) removeFlag.value = '0';
            cropper.destroy();
            cropper = null;
            modal.hidden = true;
        }, 'image/jpeg', 0.9);
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (!confirm('Remove Principal Photo?\n\nThis will remove the current photo from the About page.')) return;
            removeFlag.value = '1';
            fileInput.value = '';
            setPhotoPreview(previewWrap, null);
            var previewPhoto = document.getElementById('previewPhoto');
            if (previewPhoto) previewPhoto.hidden = true;
        });
    }

    function setPhotoPreview(wrap, url) {
        if (!wrap) return;
        if (url) {
            wrap.innerHTML = '<img src="' + url + '" id="principalPhotoImg" class="js-lightbox-trigger" data-caption="Principal Photo" alt="Principal photo">';
        } else {
            wrap.innerHTML = '<div class="principal-photo-placeholder" id="principalPhotoImg">'
                + '<svg viewBox="0 0 24 24" class="avatar-icon" aria-hidden="true"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.5c-3.3 0-9.8 1.6-9.8 4.9V22h19.6v-2.6c0-3.3-6.5-4.9-9.8-4.9z"/></svg>'
                + '<span>No Photo Available</span></div>';
        }
        // No extra binding needed here - the delegated click listener set
        // up in initLightbox() covers any .js-lightbox-trigger, including
        // this freshly-inserted image.
    }
}

// ---------------------------------------------------------------------
// Digital Signature: same Upload/Replace/Remove pattern, no cropping
// (transparent PNGs are usually already the right shape).
// ---------------------------------------------------------------------
function initSignatureUpload() {
    var uploadBtn = document.getElementById('principalSigUploadBtn');
    var removeBtn = document.getElementById('principalSigRemoveBtn');
    var fileInput = document.getElementById('principalSigInput');
    var removeFlag = document.getElementById('removePrincipalSigFlag');
    var previewWrap = document.getElementById('principalSigPreview');
    if (!uploadBtn || !fileInput) return;

    uploadBtn.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        var url = URL.createObjectURL(file);
        previewWrap.innerHTML = '<img src="' + url + '" id="principalSigImg" class="js-lightbox-trigger" data-caption="Principal Signature" alt="Principal signature">';
        var sigImg = document.getElementById('previewSignature');
        if (sigImg) { sigImg.src = url; sigImg.hidden = false; }
        if (removeFlag) removeFlag.value = '0';
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (!confirm('Remove the digital signature?\n\nThis will remove it from the About page.')) return;
            removeFlag.value = '1';
            fileInput.value = '';
            previewWrap.innerHTML = '<span class="muted" id="principalSigImg">No signature uploaded</span>';
            var sigImg = document.getElementById('previewSignature');
            if (sigImg) sigImg.hidden = true;
        });
    }
}

// ---------------------------------------------------------------------
// Live preview: keeps the "Live Preview" card in sync with the Name,
// Title, Position, and Message fields as the admin types.
// ---------------------------------------------------------------------
function initLivePreview() {
    var nameInput = document.getElementById('principalName');
    var titleSelect = document.getElementById('principalTitle');
    var positionInput = document.getElementById('principalPosition');
    var previewName = document.getElementById('previewName');
    var previewPosition = document.getElementById('previewPosition');
    var previewMessage = document.getElementById('previewMessage');

    function updateName() {
        if (!previewName) return;
        var parts = [];
        if (titleSelect && titleSelect.value) parts.push(titleSelect.value);
        if (nameInput && nameInput.value) parts.push(nameInput.value);
        previewName.textContent = parts.join(' ');
    }

    if (nameInput) nameInput.addEventListener('input', updateName);
    if (titleSelect) titleSelect.addEventListener('change', updateName);
    if (positionInput && previewPosition) {
        positionInput.addEventListener('input', function () { previewPosition.textContent = positionInput.value; });
    }

    // window.aboutQuillEditors is populated by about_content_admin.js.
    function wireMessage() {
        var quill = window.aboutQuillEditors && window.aboutQuillEditors.principal_message;
        if (!quill || !previewMessage) {
            setTimeout(wireMessage, 150); // Quill initializes async on DOMContentLoaded; retry briefly
            return;
        }
        quill.on('text-change', function () {
            previewMessage.innerHTML = quill.root.innerHTML;
        });
    }
    wireMessage();
}

// ---------------------------------------------------------------------
// Reset: native form reset restores plain inputs/selects, but the Quill
// editor and image previews need to be restored manually back to what
// was rendered on page load.
// ---------------------------------------------------------------------
function initReset() {
    var form = document.getElementById('principalForm');
    if (!form) return;

    var originalMessageHtml = null;
    (function captureOriginal() {
        var quill = window.aboutQuillEditors && window.aboutQuillEditors.principal_message;
        if (!quill) { setTimeout(captureOriginal, 150); return; }
        originalMessageHtml = quill.root.innerHTML;
    })();

    form.addEventListener('reset', function () {
        document.getElementById('removePrincipalPhotoFlag').value = '0';
        document.getElementById('removePrincipalSigFlag').value = '0';
        document.getElementById('principalPhotoInput').value = '';
        document.getElementById('principalSigInput').value = '';

        setTimeout(function () {
            var quill = window.aboutQuillEditors && window.aboutQuillEditors.principal_message;
            if (quill && originalMessageHtml !== null) {
                quill.root.innerHTML = originalMessageHtml;
                var previewMessage = document.getElementById('previewMessage');
                if (previewMessage) previewMessage.innerHTML = originalMessageHtml;
            }
            var previewName = document.getElementById('previewName');
            var previewPosition = document.getElementById('previewPosition');
            if (previewName) previewName.textContent = [document.getElementById('principalTitle').value, document.getElementById('principalName').value].filter(Boolean).join(' ');
            if (previewPosition) previewPosition.textContent = document.getElementById('principalPosition').value;
        }, 0);
    });
}
