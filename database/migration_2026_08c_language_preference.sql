-- Migration 2026-08c: Language preference for the Student Portal
-- Run after migration_2026_08b_principal_message_upgrade.sql
--
-- Adds a per-student saved language preference so the language chosen from
-- the language switcher persists across devices/sessions once a student is
-- logged in (anonymous visitors still get persistence via cookie + localStorage
-- only - see includes/functions.php::get_current_language()).
--
-- Supported codes: fil (Filipino, default), en (English), zh (Chinese -
-- Simplified), ja (Japanese), ko (Korean).

ALTER TABLE students
    ADD COLUMN preferred_language VARCHAR(5) NULL DEFAULT NULL AFTER email;
