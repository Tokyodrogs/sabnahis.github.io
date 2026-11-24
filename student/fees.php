<?php
/**
 * Retired (2026-08g). "Assessment of Fees" was removed from the Student
 * Portal per SABNAHIS's DepEd No Collection Policy - public secondary
 * education is free, so a tuition-style balance has no place in the
 * student-facing portal. Kept as a redirect stub (rather than deleted
 * outright) so an old bookmark or browser-history entry lands somewhere
 * useful instead of a bare 404. See student/qr_code.php for what replaced
 * this menu slot, and includes/functions.php::compute_fee_summary() for
 * where the underlying math still lives if a future internal Cashier
 * module needs it.
 */
require_once __DIR__ . '/includes/auth_check.php';
flash('success', 'This page is no longer available.');
redirect('dashboard.php');
