This folder is no longer needed for the Digital Student ID's QR code
(2026-08n). The QR code used to require a client-side QR-encoding
library to be manually dropped in here - see this file's git/edit
history if you need the old instructions - but that page now gets a
real, already-rendered QR PNG from a server-side endpoint
(student/qr_image.php), so no library needs to be vendored here.

If a future feature on this project needs a client-side QR/barcode
library for some other reason, this folder is still the right place for
it (assets/js/student_qr.js documents why: this project intentionally
has no build step or bundled npm/Composer dependencies).
