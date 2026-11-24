// Wires up a Quill.js rich text editor (loaded from CDN in about_content.php)
// on every ".rich-editor" div on the page, and copies its HTML into the
// adjacent hidden input right before the enclosing form submits so the
// content is saved as normal POST data - no AJAX required.
// Exposes each editor instance on window.aboutQuillEditors, keyed by its
// data-field attribute (e.g. "principal_message"), so other scripts on the
// page - like about_principal_admin.js's live preview - can listen for
// changes without this file needing to know about them.
window.aboutQuillEditors = window.aboutQuillEditors || {};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Quill === 'undefined') return;

    document.querySelectorAll('.rich-editor').forEach(function (el) {
        var quill = new Quill(el, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        var fieldName = el.dataset.field;
        if (fieldName) window.aboutQuillEditors[fieldName] = quill;

        var hiddenInput = el.parentElement.querySelector('.rich-editor-input');
        var form = el.closest('form');
        if (hiddenInput && form) {
            form.addEventListener('submit', function () {
                hiddenInput.value = quill.root.innerHTML;
            });
        }
    });
});
