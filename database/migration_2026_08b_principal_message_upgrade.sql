-- =====================================================================
-- Principal's Message upgrade (2026-08b)
--
-- Adds Academic Title, Office Email, Office Contact Number, and a
-- publish Status (Active/Inactive/Draft) to the Principal's Message
-- section of the About SABNAHIS module. Run this AFTER
-- migration_2026_08_about_module.sql (which creates about_school).
--
-- Run this file in phpMyAdmin (or `mysql -u root sabnahis_enrollment <
-- migration_2026_08b_principal_message_upgrade.sql`). Safe to run once.
-- =====================================================================

ALTER TABLE about_school
    ADD COLUMN principal_title VARCHAR(20) NULL AFTER principal_name,
    ADD COLUMN principal_email VARCHAR(150) NULL AFTER principal_signature,
    ADD COLUMN principal_contact VARCHAR(50) NULL AFTER principal_email,
    ADD COLUMN principal_status ENUM('Active','Inactive','Draft') NOT NULL DEFAULT 'Active' AFTER principal_contact;
