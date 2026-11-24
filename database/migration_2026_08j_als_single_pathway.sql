-- ---------------------------------------------------------------------
-- 2026-08j: ALS - remove the two "Coming Soon" pathways
-- ---------------------------------------------------------------------
-- Follow-up to migration_2026_08i_als_enrollment.sql. The ALS Completer -
-- Transition to Formal School and Former Formal School Learner - Enroll
-- in ALS pathways were shown as disabled "Coming Soon" cards; removed
-- entirely at the user's request rather than left disabled, since neither
-- is planned as a near-term follow-up. Only "Enroll in ALS Program"
-- (als_program) remains.
--
-- Safe to run whether or not any ALS applications exist yet: no row can
-- already hold either removed value (they were never selectable - Phase 1
-- only shipped 'als_program' as enabled=true), so narrowing the enum
-- cannot silently corrupt existing data.
--
-- This is a no-op if you run migration_2026_08i_als_enrollment.sql AFTER
-- this file was written (that file's enum is already narrowed to just
-- 'als_program'). Only needed if you ran 08i before this fix landed.
-- ---------------------------------------------------------------------

ALTER TABLE students
    MODIFY COLUMN als_enrollment_pathway ENUM('als_program') NULL DEFAULT NULL;
