-- =====================================================================
-- SABNAHIS Enrollment System
-- Migration: add Learner Reference Number (LRN) and Photo to students
-- Run this against an EXISTING sabnahis_enrollment database that was
-- created before this change. New installs already get these columns
-- from database/schema.sql and do NOT need to run this file.
--
-- Safe to run multiple times: each statement checks for the column /
-- index before applying it.
-- =====================================================================

USE sabnahis_enrollment;

-- ---------------------------------------------------------------------
-- Add `lrn` column (12-digit Learner Reference Number, nullable, unique)
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'lrn'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN lrn VARCHAR(12) DEFAULT NULL AFTER student_id',
    'SELECT "lrn column already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Add `photo` column (path to the stored/optimized profile image)
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'photo'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER enrollment_no',
    'SELECT "photo column already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Unique index on lrn (MySQL allows multiple NULLs under a UNIQUE key,
-- so existing rows with no LRN yet are unaffected)
-- ---------------------------------------------------------------------
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'uq_students_lrn'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE students ADD UNIQUE KEY uq_students_lrn (lrn)',
    'SELECT "uq_students_lrn index already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
