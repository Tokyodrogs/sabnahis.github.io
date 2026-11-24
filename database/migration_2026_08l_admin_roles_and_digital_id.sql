-- Migration 2026-08l: add ICT and Guidance as admin panel roles.
--
-- Context: the "Digital Student ID" Student Portal module (see
-- student/qr_code.php) is Phase 1 of a larger Student Services request
-- pasted by the school; that spec also calls for tickets/requests to be
-- routed to ICT and Guidance offices in later phases. Those queue pages
-- don't exist yet, but the admin roles are added now so ICT/Guidance
-- accounts can be created ahead of that work. Until dedicated queue pages
-- exist, ICT and Guidance behave exactly like Registrar for admin panel
-- page gating (every gate in admin/ is `role === 'Administrator'` vs.
-- everything else - see admin/admin_accounts.php) - they do NOT yet get
-- any narrower, office-specific access.
--
-- Run this once against an existing SABNAHIS database. Safe to re-run
-- (MODIFY COLUMN is idempotent).

ALTER TABLE admin
  MODIFY COLUMN role ENUM('Administrator','Registrar','ICT','Guidance') NOT NULL DEFAULT 'Administrator';
