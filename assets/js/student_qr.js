// RETIRED (2026-08n). The Digital Student ID's QR code used to be
// rendered client-side by this file, backed by a manually-vendored QR
// library at assets/js/vendor/qrcode.min.js (which realistically never
// got installed - see that folder's README.txt history). It's been
// replaced by a server-side PNG endpoint - student/qr_image.php - which
// student/qr_code.php and student/qr_code_print.php now reference
// directly via a plain <img src="qr_image.php">. No JS is required for
// the QR code to display, download, or print anymore.
//
// This file is left in place (not deleted) as a matter of project
// convention, but nothing references it - it's safe to remove during a
// future cleanup pass.
