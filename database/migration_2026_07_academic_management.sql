-- =====================================================================
-- SABNAHIS Enrollment System
-- Migration: Academic Management module (separates class scheduling /
-- teacher & room assignment away from the Registrar into a dedicated
-- Academic Coordinator role and portal), plus the normalized schema it
-- needs: teachers, sections, subjects, rooms, teacher_assignments,
-- room_assignments, and a redesigned class_schedules.
--
-- Run this against an EXISTING sabnahis_enrollment database. New installs
-- already get everything from database/schema.sql and do NOT need this file.
--
-- Safe to run multiple times: every ALTER checks for its column/index
-- first, and every CREATE TABLE uses IF NOT EXISTS. The old placeholder
-- tables (class_offerings, student_subjects, and the old free-text
-- class_schedules) are dropped and replaced ONLY if they still have their
-- original placeholder structure - they were never populated by any admin
-- screen (there was no UI to write to them), so this carries no
-- data-migration risk. If this migration has already been run, those
-- drop statements become no-ops.
-- =====================================================================

USE sabnahis_enrollment;

-- ---------------------------------------------------------------------
-- audit_log: add 'academic' as a valid actor_type (Academic Coordinator
-- portal login/logout).
-- ---------------------------------------------------------------------
SET @col_type := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'actor_type'
);
SET @sql := IF(@col_type IS NOT NULL AND @col_type NOT LIKE '%academic%',
    "ALTER TABLE audit_log MODIFY COLUMN actor_type ENUM('admin','student','academic') NOT NULL",
    'SELECT "audit_log.actor_type already includes academic (or table missing), skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- admin: role column (Administrator vs Registrar - both still share this
-- one login/session; only Academic Coordinator gets a fully separate
-- portal, added below as academic_accounts).
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'role'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE admin ADD COLUMN role ENUM('Administrator','Registrar') NOT NULL DEFAULT 'Administrator' AFTER fullname",
    'SELECT "admin.role already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Academic Management Portal accounts (fully separate auth domain from
-- `admin`, mirroring the Student Portal's isolation - see
-- academic/includes/auth_check.php).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_accounts (
    academic_account_id INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50) NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    fullname            VARCHAR(100) NOT NULL,
    status              ENUM('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
    failed_attempts     INT NOT NULL DEFAULT 0,
    last_login          TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed a default Academic Coordinator account only if the table is brand
-- new and empty (fresh migration on an existing install).
SET @academic_count := (SELECT COUNT(*) FROM academic_accounts);
SET @sql := IF(@academic_count = 0,
    "INSERT INTO academic_accounts (username, password, fullname) VALUES ('academic', '$2y$10$aIYwKVnbScj8Vf70lFjfH.YzYO5Tec2/GI.MksY11yNQE/lu.1aFa', 'Academic Coordinator')",
    'SELECT "academic_accounts already has data, skipping seed"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Teachers (roster only - no login accounts in this phase)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS teachers (
    teacher_id  INT AUTO_INCREMENT PRIMARY KEY,
    employee_no VARCHAR(30) DEFAULT NULL UNIQUE,
    lastname    VARCHAR(50) NOT NULL,
    firstname   VARCHAR(50) NOT NULL,
    middlename  VARCHAR(50) DEFAULT NULL,
    email       VARCHAR(100) DEFAULT NULL,
    contact     VARCHAR(20) DEFAULT NULL,
    status      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Sections (e.g. "Grade 7 - Rizal"). Section CRUD lives in the admin
-- panel (Registrar/Administrator turf); the Academic Coordinator then
-- schedules classes on top of these existing sections.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sections (
    section_id         INT AUTO_INCREMENT PRIMARY KEY,
    section_name        VARCHAR(50) NOT NULL,
    grade_level_id      INT NOT NULL,
    strand_id           INT DEFAULT NULL,
    sy_id               INT NOT NULL,
    adviser_teacher_id  INT DEFAULT NULL,
    capacity            INT DEFAULT NULL,
    status              ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_per_grade_year (section_name, grade_level_id, sy_id),
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id) ON DELETE CASCADE,
    FOREIGN KEY (strand_id) REFERENCES strands(strand_id) ON DELETE SET NULL,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE,
    FOREIGN KEY (adviser_teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- students.section_id - lets "enroll a student into a section" and "that
-- section's schedule shows up automatically" both work with zero manual
-- per-student assignment.
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'section_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN section_id INT NULL AFTER strand_id',
    'SELECT "students.section_id already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND CONSTRAINT_NAME = 'students_ibfk_section'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE students ADD CONSTRAINT students_ibfk_section FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE SET NULL',
    'SELECT "students section_id FK already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Subjects catalog + grade-level applicability (JHS and/or SHS)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    subject_id    INT AUTO_INCREMENT PRIMARY KEY,
    subject_code  VARCHAR(20) NOT NULL UNIQUE,
    subject_title VARCHAR(150) NOT NULL,
    units         DECIMAL(3,1) NOT NULL DEFAULT 0,
    level_type    ENUM('Junior High School','Senior High School','Both') NOT NULL DEFAULT 'Both',
    status        ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subject_grade_levels (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    subject_id     INT NOT NULL,
    grade_level_id INT NOT NULL,
    UNIQUE KEY uq_subject_grade (subject_id, grade_level_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Rooms + homeroom-style room assignment per section
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    room_id   INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    room_type VARCHAR(50) DEFAULT NULL,
    capacity  INT DEFAULT NULL,
    status    ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS room_assignments (
    room_assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id            INT NOT NULL,
    section_id         INT NOT NULL,
    sy_id               INT NOT NULL,
    UNIQUE KEY uq_section_homeroom_per_year (section_id, sy_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Teacher assignments (teaching load) - UNIQUE key is the actual
-- "prevent duplicate assignment" guard.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS teacher_assignments (
    teacher_assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id            INT NOT NULL,
    subject_id            INT NOT NULL,
    section_id            INT NOT NULL,
    sy_id                 INT NOT NULL,
    status                ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_subject_section_year (teacher_id, subject_id, section_id, sy_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- class_schedules: replace the old free-text placeholder (offering_id ->
-- class_offerings, with instructor_name/section/room as plain VARCHARs)
-- with the normalized version (section_id/subject_id/teacher_id/room_id
-- FKs). Only drop the old tables if the old structure is actually present
-- - these were never populated by any admin screen, so this is safe.
-- ---------------------------------------------------------------------
SET @old_structure := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND COLUMN_NAME = 'offering_id'
);

SET @sql := IF(@old_structure > 0, 'DROP TABLE IF EXISTS student_subjects', 'SELECT "student_subjects: nothing to drop"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@old_structure > 0, 'DROP TABLE IF EXISTS class_schedules', 'SELECT "class_schedules: old structure not present, leaving as-is"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@old_structure > 0, 'DROP TABLE IF EXISTS class_offerings', 'SELECT "class_offerings: nothing to drop"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS class_schedules (
    schedule_id  INT AUTO_INCREMENT PRIMARY KEY,
    sy_id        INT NOT NULL,
    section_id   INT NOT NULL,
    subject_id   INT NOT NULL,
    teacher_id   INT NOT NULL,
    room_id      INT DEFAULT NULL,
    day_of_week  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    time_start   TIME NOT NULL,
    time_end     TIME NOT NULL,
    status       ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    approved_by  INT DEFAULT NULL,
    approved_at  TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_no_duplicate_schedule (section_id, subject_id, day_of_week, time_start, time_end),
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES admin(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB;

SELECT 'Academic Management migration complete.' AS result;
