-- ---------------------------------------------------------------------
-- 2026-08i: ALS (Alternative Learning System) enrollment - Phase 1
-- ---------------------------------------------------------------------
-- Adds ALS as a 5th applicant type on top of the existing New/Returning/
-- Transferee/Balik-Aral wizard, per the "extend existing tables" approach
-- (reuses students/documents/requirement_configs/audit_log rather than a
-- fully parallel ALS-only schema - keeps one enrollment/document/admin
-- review pipeline instead of two).
--
-- PHASE 1 SCOPE ONLY: covers the direct "Enroll in ALS Program" pathway.
-- The "ALS Completer - Transition to Formal School" and "Former Formal
-- Learner - Enroll in ALS" pathways (academic placement, A&E completion
-- certificates, assessment/placement evaluation workflow), the dedicated
-- admin ALS Applications module, ALS Requirements Management UI, and
-- facilitator/Community Learning Center assignment are explicitly OUT of
-- scope for this migration and are deferred to a follow-up phase.
--
-- Run this after all earlier migrations (2026_08d, 2026_08f, 2026_08h).
-- ---------------------------------------------------------------------

-- 1. Add 'ALS' as a 5th value on the existing student_type enum (reused as
--    the single source of truth for applicant type - see constants.php's
--    APPLICANT_TYPES doc comment from the 2026-08d migration).
ALTER TABLE students
    MODIFY COLUMN student_type ENUM('New','Returning','Transferee','Balik-Aral','ALS') NOT NULL DEFAULT 'New';

-- 2. ALS-specific columns. Fields that already exist and are semantically
--    reusable are NOT duplicated:
--      - previous_school / previous_school_id / school_type  (Educational
--        Background's "Previous School" field)
--      - last_grade_completed                                 (Last Grade
--        Level Attended)
--      - last_school_year_attended                             (Last Year
--        Attended)
--      - lrn, lastname, firstname, middlename, extension, sex, birthdate,
--        age, address, contact, email                          (Personal
--        Information - address covers "Complete Address"; the separate
--        Barangay/Municipality/Province/Region fields the ALS spec asks
--        for are new columns below since no other applicant type captures
--        them at this granularity)
ALTER TABLE students
    ADD COLUMN als_enrollment_pathway ENUM('als_program') NULL DEFAULT NULL AFTER student_type,
    ADD COLUMN als_place_of_birth VARCHAR(150) NULL DEFAULT NULL AFTER als_enrollment_pathway,
    ADD COLUMN als_barangay VARCHAR(100) NULL DEFAULT NULL AFTER als_place_of_birth,
    ADD COLUMN als_municipality VARCHAR(100) NULL DEFAULT NULL AFTER als_barangay,
    ADD COLUMN als_province VARCHAR(100) NULL DEFAULT NULL AFTER als_municipality,
    ADD COLUMN als_region VARCHAR(100) NULL DEFAULT NULL AFTER als_province,
    ADD COLUMN highest_education_completed VARCHAR(50) NULL DEFAULT NULL AFTER als_region,
    ADD COLUMN never_attended_formal_school TINYINT(1) NOT NULL DEFAULT 0 AFTER highest_education_completed,
    ADD COLUMN reason_for_leaving_als VARCHAR(50) NULL DEFAULT NULL AFTER never_attended_formal_school,
    ADD COLUMN reason_for_leaving_als_other VARCHAR(150) NULL DEFAULT NULL AFTER reason_for_leaving_als,
    ADD COLUMN learning_preference VARCHAR(50) NULL DEFAULT NULL AFTER reason_for_leaving_als_other,
    ADD COLUMN employment_status VARCHAR(50) NULL DEFAULT NULL AFTER learning_preference,
    ADD COLUMN reason_for_enrolling_als TEXT NULL AFTER employment_status,
    ADD COLUMN learning_goal VARCHAR(50) NULL DEFAULT NULL AFTER reason_for_enrolling_als,
    ADD COLUMN learning_goal_other VARCHAR(150) NULL DEFAULT NULL AFTER learning_goal;

-- 3. Extend requirement_configs.applicant_type to accept 'ALS' and seed
--    Phase 1 requirements. The full Required/Conditional/Optional 3-tier
--    typing from the spec isn't modeled yet - requirement_configs only
--    has a Required/Optional (is_required) flag, same as every other
--    type; Form 137 (the spec's one "Conditional" example) is seeded as
--    optional here, which achieves the same practical effect ("don't
--    force it on applicants who never attended formal school") without a
--    schema change. A true 3-tier model is deferred to the Requirements
--    Management follow-up phase.
ALTER TABLE requirement_configs
    MODIFY COLUMN applicant_type ENUM('New','Returning','Transferee','Balik-Aral','ALS') NOT NULL;

INSERT INTO requirement_configs (applicant_type, doc_key, doc_label, is_required, display_order) VALUES
('ALS', 'psa_birth_cert', 'Proof of Identity / Birth Record (PSA Birth Certificate or other accepted ID)', 1, 1),
('ALS', 'photo_2x2', 'Recent 2x2 ID Picture', 1, 2),
('ALS', 'report_card', 'Form 137 / School Record (if previously attended formal school)', 0, 3);
