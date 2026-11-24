-- =====================================================================
-- SABNAHIS Enrollment System
-- Migration: Registrar Module (student registration type, archive/
-- restore, a "Verified" enrollment stage between Pending and Approved,
-- and a profile photo for admin/registrar accounts).
--
-- Run this against an EXISTING sabnahis_enrollment database. New installs
-- already get everything from database/schema.sql and do NOT need this file.
--
-- Safe to run multiple times: every ALTER checks for its column first,
-- and the ENUM widenings are only applied if the value is missing.
-- =====================================================================

USE sabnahis_enrollment;

-- ---------------------------------------------------------------------
-- students: student_type (New / Returning / Transferee) and is_archived
-- (soft-delete flag - see admin/student_archive.php - distinct from the
-- pre-existing hard-delete in admin/student_delete.php, which stays as an
-- Administrator-only "permanently remove" action).
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'student_type'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE students ADD COLUMN student_type ENUM('New','Returning','Transferee') NOT NULL DEFAULT 'New' AFTER status",
    'SELECT "students.student_type already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'is_archived'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER student_type',
    'SELECT "students.is_archived already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- students.status / enrollment.status: widen the ENUM to add 'Verified'
-- as a stage between 'Pending' and 'Approved'. The existing 'Enrolled'
-- value is intentionally left as-is (not renamed to "Officially
-- Enrolled") - every existing query, filter, and status check across the
-- admin panel, Student Portal, and Academic Management module compares
-- against the literal string 'Enrolled', so renaming the stored value
-- would be a much larger, riskier change for no functional benefit.
-- status_badge() now DISPLAYS 'Enrolled' as "Officially Enrolled" instead
-- (see includes/functions.php), which satisfies the naming requirement
-- without touching stored data or any WHERE clause in the codebase.
-- ---------------------------------------------------------------------
SET @col_type := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_type IS NOT NULL AND @col_type NOT LIKE '%Verified%',
    "ALTER TABLE students MODIFY COLUMN status ENUM('Pending','Verified','Approved','Rejected','Enrolled') NOT NULL DEFAULT 'Pending'",
    'SELECT "students.status already includes Verified (or table missing), skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_type := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollment' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_type IS NOT NULL AND @col_type NOT LIKE '%Verified%',
    "ALTER TABLE enrollment MODIFY COLUMN status ENUM('Pending','Verified','Approved','Rejected','Enrolled') NOT NULL DEFAULT 'Pending'",
    'SELECT "enrollment.status already includes Verified (or table missing), skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- admin: profile photo (Registrar/Administrator Profile page).
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'photo'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE admin ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER role',
    'SELECT "admin.photo already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Registrar Module migration complete.' AS result;
