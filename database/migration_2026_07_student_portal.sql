-- =====================================================================
-- SABNAHIS Enrollment System
-- Migration: Student Portal (accounts, notifications, audit log,
-- document review workflow, announcement priority, and academic/
-- financial tables for Subjects / Class Schedule / Assessment of Fees /
-- Payment History).
--
-- Run this against an EXISTING sabnahis_enrollment database. New installs
-- already get everything from database/schema.sql and do NOT need this file.
--
-- Safe to run multiple times: every ALTER checks for its column/index
-- first, and every CREATE TABLE uses IF NOT EXISTS.
-- =====================================================================

USE sabnahis_enrollment;

-- ---------------------------------------------------------------------
-- documents: add the approve / reject / request re-upload workflow used
-- by the admin Enrollment review screen and the Student Portal Documents
-- module.
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE documents ADD COLUMN status ENUM('Pending','Approved','Rejected','Re-upload Requested') NOT NULL DEFAULT 'Pending' AFTER file_path",
    'SELECT "documents.status already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'remarks'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN remarks VARCHAR(255) DEFAULT NULL AFTER status',
    'SELECT "documents.remarks already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'reviewed_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN reviewed_by INT DEFAULT NULL AFTER remarks',
    'SELECT "documents.reviewed_by already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'reviewed_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE documents ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by',
    'SELECT "documents.reviewed_at already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'uq_documents_student_doctype'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE documents ADD UNIQUE KEY uq_documents_student_doctype (student_id, doc_type)',
    'SELECT "uq_documents_student_doctype already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'documents_ibfk_reviewed_by'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE documents ADD CONSTRAINT documents_ibfk_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES admin(admin_id) ON DELETE SET NULL',
    'SELECT "documents reviewed_by FK already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- announcements: add priority
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'priority'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE announcements ADD COLUMN priority ENUM('Normal','Important','Urgent') NOT NULL DEFAULT 'Normal' AFTER image",
    'SELECT "announcements.priority already exists, skipping"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- New tables
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_accounts (
    account_id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id          INT NOT NULL UNIQUE,
    username            VARCHAR(30) NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    status              ENUM('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
    first_login         TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts     INT NOT NULL DEFAULT 0,
    last_login          TIMESTAMP NULL DEFAULT NULL,
    password_changed_at TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id       INT NOT NULL,
    category         VARCHAR(30) NOT NULL,
    ref_type         VARCHAR(30) DEFAULT NULL,
    ref_id           INT DEFAULT NULL,
    message          VARCHAR(255) NOT NULL,
    is_read          TINYINT(1) NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
    audit_id   INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin','student') NOT NULL,
    actor_id   INT NOT NULL,
    action     VARCHAR(50) NOT NULL,
    details    VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_offerings (
    offering_id     INT AUTO_INCREMENT PRIMARY KEY,
    subject_code    VARCHAR(20) NOT NULL,
    subject_title   VARCHAR(150) NOT NULL,
    units           DECIMAL(3,1) NOT NULL DEFAULT 0,
    instructor_name VARCHAR(100) DEFAULT NULL,
    section         VARCHAR(50) DEFAULT NULL,
    room            VARCHAR(50) DEFAULT NULL,
    grade_level_id  INT DEFAULT NULL,
    sy_id           INT NOT NULL,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id) ON DELETE SET NULL,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_schedules (
    schedule_id  INT AUTO_INCREMENT PRIMARY KEY,
    offering_id  INT NOT NULL,
    day_of_week  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    time_start   TIME NOT NULL,
    time_end     TIME NOT NULL,
    FOREIGN KEY (offering_id) REFERENCES class_offerings(offering_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_subjects (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    offering_id  INT NOT NULL,
    sy_id        INT NOT NULL,
    UNIQUE KEY uq_student_offering (student_id, offering_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (offering_id) REFERENCES class_offerings(offering_id) ON DELETE CASCADE,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fee_assessments (
    assessment_id        INT AUTO_INCREMENT PRIMARY KEY,
    student_id           INT NOT NULL,
    sy_id                INT NOT NULL,
    tuition_fee          DECIMAL(10,2) NOT NULL DEFAULT 0,
    misc_fee             DECIMAL(10,2) NOT NULL DEFAULT 0,
    lab_fee              DECIMAL(10,2) NOT NULL DEFAULT 0,
    other_charges        DECIMAL(10,2) NOT NULL DEFAULT 0,
    scholarship_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_sy (student_id, sy_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    assessment_id  INT DEFAULT NULL,
    or_number      VARCHAR(30) NOT NULL,
    payment_date   DATE NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(30) DEFAULT NULL,
    status         ENUM('Verified','Pending') NOT NULL DEFAULT 'Pending',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES fee_assessments(assessment_id) ON DELETE SET NULL
) ENGINE=InnoDB;
