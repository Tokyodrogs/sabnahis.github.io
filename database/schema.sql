-- =====================================================================
-- SABNAHIS Local Web-Based Enrollment System
-- Database Schema
-- Import this file via phpMyAdmin (XAMPP) before running the system.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS sabnahis_enrollment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sabnahis_enrollment;

-- ---------------------------------------------------------------------
-- Admin accounts
-- ---------------------------------------------------------------------
-- `role` distinguishes the Administrator/Principal (full access + academic
-- oversight/approval) from the Registrar (enrollment + student records,
-- read-only on schedules - see admin/includes/sidebar.php and
-- admin/academic_overview.php). Both still share this one login/session
-- system; only the Academic Coordinator gets a fully separate portal (see
-- academic_accounts below), since that's a genuinely new role rather than
-- a permission tier on top of the existing admin/registrar staff login.
CREATE TABLE admin (
    admin_id   INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    fullname   VARCHAR(100) NOT NULL,
    role       ENUM('Administrator','Registrar','ICT','Guidance') NOT NULL DEFAULT 'Administrator',
    photo      VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default account -> username: admin / password: admin123
-- IMPORTANT: log in and change this password immediately after setup.
INSERT INTO admin (username, password, fullname, role) VALUES
('admin', '$2b$12$uYZBKk/joTG0qWgiWwsiMOtXEu2j0BKBPqheLU0b8HPr8Hqo.8UM.', 'System Administrator', 'Administrator');

-- ---------------------------------------------------------------------
-- Academic Management Portal accounts (Academic Coordinator / Assistant
-- Principal / Scheduling Committee). Fully separate authentication domain
-- from `admin` - own login (academic/login.php), own session namespace
-- ("sabnahis_academic"), own auth_check middleware under
-- academic/includes/ - mirroring how the Student Portal is isolated from
-- the admin panel. This is what actually enforces "only the Academic
-- Coordinator (and Principal, via the admin panel's oversight pages) can
-- manage schedules": a Registrar's admin session can never satisfy
-- academic/includes/auth_check.php, and there simply is no schedule
-- create/edit/delete UI anywhere inside admin/.
-- ---------------------------------------------------------------------
CREATE TABLE academic_accounts (
    academic_account_id INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50) NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    fullname            VARCHAR(100) NOT NULL,
    status              ENUM('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
    failed_attempts     INT NOT NULL DEFAULT 0,
    last_login          TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default account -> username: academic / password: academic123
-- IMPORTANT: log in and change this password immediately after setup.
INSERT INTO academic_accounts (username, password, fullname) VALUES
('academic', '$2y$10$aIYwKVnbScj8Vf70lFjfH.YzYO5Tec2/GI.MksY11yNQE/lu.1aFa', 'Academic Coordinator');

-- ---------------------------------------------------------------------
-- School Year
-- ---------------------------------------------------------------------
CREATE TABLE school_year (
    sy_id       INT AUTO_INCREMENT PRIMARY KEY,
    school_year VARCHAR(20) NOT NULL UNIQUE,
    status      ENUM('Open','Closed') NOT NULL DEFAULT 'Closed',
    is_current  TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO school_year (school_year, status, is_current) VALUES
('2026-2027', 'Open', 1);

-- ---------------------------------------------------------------------
-- Grade Levels
-- ---------------------------------------------------------------------
CREATE TABLE grade_levels (
    grade_level_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name     VARCHAR(50) NOT NULL,
    level_type     ENUM('Junior High School','Senior High School') NOT NULL
) ENGINE=InnoDB;

INSERT INTO grade_levels (grade_name, level_type) VALUES
('Grade 7', 'Junior High School'),
('Grade 8', 'Junior High School'),
('Grade 9', 'Junior High School'),
('Grade 10', 'Junior High School'),
('Grade 11', 'Senior High School'),
('Grade 12', 'Senior High School');

-- ---------------------------------------------------------------------
-- Strands (Senior High School)
-- ---------------------------------------------------------------------
CREATE TABLE strands (
    strand_id   INT AUTO_INCREMENT PRIMARY KEY,
    strand_name VARCHAR(100) NOT NULL,
    description VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO strands (strand_name, description) VALUES
('TVL - Agri-Fishery Arts', 'Technical-Vocational-Livelihood track focused on agriculture and fishery.'),
('TVL - Home Economics', 'Technical-Vocational-Livelihood track focused on home economics.'),
('ABM', 'Accountancy, Business and Management'),
('STEM', 'Science, Technology, Engineering and Mathematics'),
('HUMSS', 'Humanities and Social Sciences'),
('GAS', 'General Academic Strand');

-- ---------------------------------------------------------------------
-- Academic Management: Teachers (roster only - no login accounts in this
-- phase; the Academic Coordinator manages assignments on their behalf via
-- the Academic Management portal. See teacher_assignments below.)
-- ---------------------------------------------------------------------
CREATE TABLE teachers (
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
-- Academic Management: Sections (the actual "Grade 7 - Rizal" homeroom
-- groups). Section CRUD lives in the admin panel (Registrar/Administrator
-- turf, alongside enrollment/records setup) - the Academic Coordinator's
-- Academic Management portal then builds the section's class_schedules on
-- top of these existing sections. strand_id only applies to Senior High
-- sections. A student's students.section_id (added below) is what makes
-- "enroll a student into a section -> they automatically see that
-- section's schedule" work, with zero manual per-student scheduling.
-- ---------------------------------------------------------------------
CREATE TABLE sections (
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
-- Searchable Philippine school directory (2026-08d: multi-applicant-type
-- enrollment). Backs the Elementary School / Previous School / Last School
-- Attended autocomplete fields on the public enrollment wizard. Seed a
-- small sample set below; the real DepEd master list is imported later via
-- admin/schools_import.php (CSV: name, region, province, municipality,
-- type). is_verified = 0 marks a school a student typed in manually via
-- "My School Is Not Listed", pending admin review/merge.
-- ---------------------------------------------------------------------
-- NOTE on offers_elementary/offers_junior_high/offers_senior_high and the
-- 18 sample rows below: these flags drive per-applicant-type filtering on
-- the public enrollment wizard (New Student sees Elementary-offering
-- schools only; Transferee/Balik-Aral see everything). The sample data
-- below is NOT an official DepEd School Directory - deped_school_id/
-- school_code/division/barangay are intentionally left NULL, and the
-- level flags for the 18 seed rows are a best-effort guess from their
-- names (see database/migration_2026_08f_school_directory.sql for the
-- full reasoning). Genuinely ambiguous private schools are seeded with
-- is_verified = 0 so they show as "Needs Review" in the admin School
-- Directory instead of silently guessing their level offerings. Replace/
-- supplement this table via admin/schools_import.php's CSV import once a
-- real DepEd School Directory export is available.
CREATE TABLE schools (
    school_id           INT AUTO_INCREMENT PRIMARY KEY,
    deped_school_id     VARCHAR(20)  DEFAULT NULL,
    school_code         VARCHAR(20)  DEFAULT NULL,
    school_name         VARCHAR(200) NOT NULL,
    region              VARCHAR(100) DEFAULT NULL,
    division            VARCHAR(100) DEFAULT NULL,
    province             VARCHAR(100) DEFAULT NULL,
    municipality        VARCHAR(100) DEFAULT NULL,
    barangay            VARCHAR(100) DEFAULT NULL,
    address             VARCHAR(255) DEFAULT NULL,
    zip_code            VARCHAR(10)  DEFAULT NULL,
    school_type         ENUM('Public','Private') NOT NULL DEFAULT 'Public',
    offers_elementary   TINYINT(1) NOT NULL DEFAULT 0,
    offers_junior_high  TINYINT(1) NOT NULL DEFAULT 0,
    offers_senior_high  TINYINT(1) NOT NULL DEFAULT 0,
    is_verified         TINYINT(1) NOT NULL DEFAULT 1,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_school_name (school_name),
    KEY idx_schools_deped_id (deped_school_id),
    KEY idx_schools_province (province),
    KEY idx_schools_municipality (municipality),
    KEY idx_schools_region (region),
    KEY idx_schools_offers_elementary (offers_elementary)
) ENGINE=InnoDB;

INSERT INTO schools (school_name, region, province, municipality, school_type, offers_elementary, offers_junior_high, offers_senior_high, is_verified) VALUES
('Sablayan National Comprehensive High School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 0, 1, 1, 1),
('Sablayan Central Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1, 0, 0, 1),
('Sablayan West Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1, 0, 0, 1),
('Buenavista Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1, 0, 0, 1),
('Sto. Nino Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1, 0, 0, 1),
('Mamburao Central Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Mamburao', 'Public', 1, 0, 0, 1),
('Occidental Mindoro National High School', 'MIMAROPA', 'Occidental Mindoro', 'Mamburao', 'Public', 0, 1, 1, 1),
('San Jose Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'San Jose', 'Public', 1, 0, 0, 1),
('San Jose National High School', 'MIMAROPA', 'Occidental Mindoro', 'San Jose', 'Public', 0, 1, 1, 1),
('Calapan City Elementary School', 'MIMAROPA', 'Oriental Mindoro', 'Calapan City', 'Public', 1, 0, 0, 1),
('Divine Word College of Calapan', 'MIMAROPA', 'Oriental Mindoro', 'Calapan City', 'Private', 0, 0, 0, 0),
('Manila Elementary School', 'NCR', 'Metro Manila', 'Manila', 'Public', 1, 0, 0, 1),
('Quezon City Science High School', 'NCR', 'Metro Manila', 'Quezon City', 'Public', 0, 1, 1, 1),
('Holy Infant Academy', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Private', 0, 0, 0, 0),
('St. Augustine Learning Center', 'MIMAROPA', 'Occidental Mindoro', 'San Jose', 'Private', 0, 0, 0, 0),
('Rizal Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Rizal', 'Public', 1, 0, 0, 1),
('Abra de Ilog Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Abra de Ilog', 'Public', 1, 0, 0, 1),
('Looc National High School', 'MIMAROPA', 'Occidental Mindoro', 'Looc', 'Public', 0, 1, 1, 1);

-- ---------------------------------------------------------------------
-- Students
-- ---------------------------------------------------------------------
CREATE TABLE students (
    student_id      INT AUTO_INCREMENT PRIMARY KEY,
    lrn             VARCHAR(12) DEFAULT NULL,
    lastname        VARCHAR(50) NOT NULL,
    firstname       VARCHAR(50) NOT NULL,
    middlename      VARCHAR(50),
    extension       VARCHAR(10),
    sex             ENUM('Male','Female') NOT NULL,
    birthdate       DATE NOT NULL,
    age             INT NOT NULL,
    address         VARCHAR(255),
    contact         VARCHAR(20),
    email           VARCHAR(100),
    preferred_language VARCHAR(5) NULL DEFAULT NULL,
    previous_school VARCHAR(150),
    previous_school_id   INT NULL DEFAULT NULL,
    school_type          ENUM('Public','Private') NULL DEFAULT NULL,
    reason_for_transfer  VARCHAR(50) NULL DEFAULT NULL,
    reason_for_returning VARCHAR(50) NULL DEFAULT NULL,
    years_not_enrolled   VARCHAR(20) NULL DEFAULT NULL,
    last_grade_completed VARCHAR(100) NULL DEFAULT NULL,
    last_school_year_attended VARCHAR(20) NULL DEFAULT NULL,
    grade_level_id  INT,
    strand_id       INT NULL,
    section_id      INT NULL,
    guardian_name   VARCHAR(100),
    guardian_contact VARCHAR(20),
    guardian_relationship VARCHAR(50) NULL DEFAULT NULL,
    guardian_occupation   VARCHAR(100) NULL DEFAULT NULL,
    guardian_emergency_contact VARCHAR(20) NULL DEFAULT NULL,
    status          ENUM('Pending','Verified','Approved','Rejected','Enrolled') NOT NULL DEFAULT 'Pending',
    student_type    ENUM('New','Returning','Transferee','Balik-Aral','ALS') NOT NULL DEFAULT 'New',
    -- ALS (Alternative Learning System) fields - 2026-08i, Phase 1 (direct
    -- "Enroll in ALS Program" pathway only). See
    -- migration_2026_08i_als_enrollment.sql for the full rationale.
    als_enrollment_pathway ENUM('als_program') NULL DEFAULT NULL,
    als_place_of_birth VARCHAR(150) NULL DEFAULT NULL,
    als_barangay VARCHAR(100) NULL DEFAULT NULL,
    als_municipality VARCHAR(100) NULL DEFAULT NULL,
    als_province VARCHAR(100) NULL DEFAULT NULL,
    als_region VARCHAR(100) NULL DEFAULT NULL,
    highest_education_completed VARCHAR(50) NULL DEFAULT NULL,
    never_attended_formal_school TINYINT(1) NOT NULL DEFAULT 0,
    reason_for_leaving_als VARCHAR(50) NULL DEFAULT NULL,
    reason_for_leaving_als_other VARCHAR(150) NULL DEFAULT NULL,
    learning_preference VARCHAR(50) NULL DEFAULT NULL,
    employment_status VARCHAR(50) NULL DEFAULT NULL,
    reason_for_enrolling_als TEXT NULL,
    learning_goal VARCHAR(50) NULL DEFAULT NULL,
    learning_goal_other VARCHAR(150) NULL DEFAULT NULL,
    is_archived     TINYINT(1) NOT NULL DEFAULT 0,
    sy_id           INT,
    enrollment_no   VARCHAR(30) DEFAULT NULL,
    photo           VARCHAR(255) DEFAULT NULL,
    -- Digital Student ID QR verification (2026-08n) - qr_token is a random
    -- opaque identifier (never the student's name/LRN/contact info) that
    -- the QR code's verify.php link carries. See get_or_create_qr_token()
    -- in includes/functions.php.
    qr_token        VARCHAR(64) NULL DEFAULT NULL,
    qr_status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    qr_generated_at DATETIME NULL DEFAULT NULL,
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_students_lrn (lrn),
    UNIQUE KEY uq_students_qr_token (qr_token),
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id) ON DELETE SET NULL,
    FOREIGN KEY (strand_id) REFERENCES strands(strand_id) ON DELETE SET NULL,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE SET NULL,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE SET NULL,
    FOREIGN KEY (previous_school_id) REFERENCES schools(school_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Structure-only prep for a future Gate Check-In/Check-Out system that
-- will scan the Digital Student ID QR code (see migration_2026_08n).
-- No scanning UI or SMS notification exists yet.
CREATE TABLE student_gate_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    scan_type     ENUM('IN','OUT') NOT NULL,
    gate_location VARCHAR(255) NULL,
    scanned_by    INT NULL,
    scan_time     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remarks       TEXT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (scanned_by) REFERENCES admin(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- NOTE ON section_id: assigning a student to a section (e.g. "Grade 8 -
-- Bonifacio") is Registrar/Administrator work done from the admin panel
-- (student_edit.php / enrollment_view.php), same as assigning grade level
-- or school year. It is NOT scheduling. Once set, the Student Portal's
-- Class Schedule page automatically shows that section's class_schedules
-- rows - no per-student schedule assignment is ever needed.

-- NOTE ON NULLABILITY: `lrn` is stored as NULL-able at the database level
-- (MySQL allows multiple NULLs under a UNIQUE key) because the public online
-- enrollment form (enroll.php) allows new Grade 7 entrants who have not yet
-- been issued an LRN by DepEd to leave it blank. The admin-side "Add
-- Student" / "Edit Student" screens enforce it as a required, exactly-12-digit
-- field at the application layer (see includes/functions.php::validate_lrn()).

-- ---------------------------------------------------------------------
-- Uploaded Documents
-- ---------------------------------------------------------------------
CREATE TABLE documents (
    doc_id      INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    doc_type    VARCHAR(50) NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    status      ENUM('Pending','Approved','Rejected','Re-upload Requested') NOT NULL DEFAULT 'Pending',
    remarks     VARCHAR(255) DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_documents_student_doctype (student_id, doc_type),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admin(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Admin-configurable Requirements per applicant type (2026-08h) - backs
-- admin/requirements.php and the Requirements step of the public
-- enrollment wizard (enroll.php). See includes/functions.php::
-- required_documents_for_type()/optional_documents_for_type().
CREATE TABLE requirement_configs (
    requirement_id  INT AUTO_INCREMENT PRIMARY KEY,
    applicant_type  ENUM('New','Returning','Transferee','Balik-Aral','ALS') NOT NULL,
    doc_key         VARCHAR(50)  NOT NULL,
    doc_label       VARCHAR(150) NOT NULL,
    is_required     TINYINT(1) NOT NULL DEFAULT 1,
    display_order   INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_type_dockey (applicant_type, doc_key),
    KEY idx_type_active (applicant_type, is_active)
) ENGINE=InnoDB;

INSERT INTO requirement_configs (applicant_type, doc_key, doc_label, is_required, display_order) VALUES
('New', 'psa_birth_cert', 'PSA Birth Certificate', 1, 1),
('New', 'report_card', 'Form 138', 1, 2),
('New', 'good_moral', 'Good Moral Certificate', 1, 3),
('New', 'photo_2x2', '2x2 Picture', 1, 4),
('New', 'completion_cert', 'Certificate of Completion', 0, 5),
('Transferee', 'psa_birth_cert', 'PSA Birth Certificate', 1, 1),
('Transferee', 'form_137', 'Form 137', 1, 2),
('Transferee', 'report_card', 'Form 138', 1, 3),
('Transferee', 'good_moral', 'Good Moral Certificate', 1, 4),
('Transferee', 'photo_2x2', '2x2 Picture', 1, 5),
('Balik-Aral', 'psa_birth_cert', 'PSA Birth Certificate', 1, 1),
('Balik-Aral', 'report_card', 'Form 138', 1, 2),
('Balik-Aral', 'good_moral', 'Good Moral Certificate', 1, 3),
('Balik-Aral', 'photo_2x2', '2x2 Picture', 1, 4),
('Balik-Aral', 'form_137', 'Form 137 (if applicable)', 0, 5),
('ALS', 'psa_birth_cert', 'Proof of Identity / Birth Record (PSA Birth Certificate or other accepted ID)', 1, 1),
('ALS', 'photo_2x2', 'Recent 2x2 ID Picture', 1, 2),
('ALS', 'report_card', 'Form 137 / School Record (if previously attended formal school)', 0, 3);

-- ---------------------------------------------------------------------
-- Enrollment workflow log
-- ---------------------------------------------------------------------
CREATE TABLE enrollment (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    date_applied  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status        ENUM('Pending','Verified','Approved','Rejected','Enrolled') NOT NULL DEFAULT 'Pending',
    remarks       VARCHAR(255),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Announcements (shown on public home page and admin dashboard)
-- ---------------------------------------------------------------------
CREATE TABLE announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(150) NOT NULL,
    content         TEXT NOT NULL,
    image           VARCHAR(255) DEFAULT NULL,
    priority        ENUM('Normal','Important','Urgent') NOT NULL DEFAULT 'Normal',
    date_posted     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO announcements (title, content) VALUES
('Enrollment for S.Y. 2026-2027 is now open', 'SABNAHIS is now accepting online enrollment applications for Grade 7 to Grade 12. Please fill out the online enrollment form and wait for approval from the school administrator.');

-- ---------------------------------------------------------------------
-- Student Portal: accounts
-- ---------------------------------------------------------------------
-- One-to-one with students. Isolated from the `admin` table on purpose -
-- the Student Portal is a separate authentication domain (its own login,
-- its own session namespace, its own auth_check middleware under
-- student/includes/). This is how the "Student role" is enforced: a
-- student session can never satisfy admin/includes/auth_check.php and
-- vice versa. See includes/functions.php::create_student_account().
CREATE TABLE student_accounts (
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

-- ---------------------------------------------------------------------
-- Student Portal: notifications (enrollment/document/payment/schedule/
-- announcement/password/system). ref_type + ref_id optionally point back
-- to the source row (e.g. ref_type='announcement', ref_id=announcement_id)
-- so pages like Announcements can tell which items are unread per student.
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
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

-- ---------------------------------------------------------------------
-- Security audit log (Student Portal: login, logout, password change,
-- document upload). actor_id is student_accounts.account_id when
-- actor_type='student', or admin.admin_id when actor_type='admin'.
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    audit_id   INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin','student','academic') NOT NULL,
    actor_id   INT NOT NULL,
    action     VARCHAR(50) NOT NULL,
    details    VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Academic Management module
-- ---------------------------------------------------------------------
-- NOTE: SABNAHIS is a Junior/Senior High School system (Grade 7-12 +
-- strands). This replaces the earlier placeholder design (class_offerings
-- / student_subjects with free-text instructor/section/room columns and
-- no real relationships) with a properly normalized model: subjects,
-- rooms, and sections are real catalogs with foreign keys, teacher
-- assignments and class schedules are validated for conflicts before
-- saving (see includes/academic_functions.php::check_schedule_conflicts()),
-- and a student's class_schedules are derived automatically through
-- students.section_id - no per-student subject enrollment step exists or
-- is needed. The old placeholder tables were never populated by any admin
-- screen (there was no UI to write to them), so this redesign carries no
-- data-migration risk.
CREATE TABLE subjects (
    subject_id    INT AUTO_INCREMENT PRIMARY KEY,
    subject_code  VARCHAR(20) NOT NULL UNIQUE,
    subject_title VARCHAR(150) NOT NULL,
    units         DECIMAL(3,1) NOT NULL DEFAULT 0,
    level_type    ENUM('Junior High School','Senior High School','Both') NOT NULL DEFAULT 'Both',
    status        ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Which grade levels a subject is offered to (many-to-many). Supports
-- both Junior High and Senior High per subject.
CREATE TABLE subject_grade_levels (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    subject_id     INT NOT NULL,
    grade_level_id INT NOT NULL,
    UNIQUE KEY uq_subject_grade (subject_id, grade_level_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rooms (
    room_id   INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    room_type VARCHAR(50) DEFAULT NULL,
    capacity  INT DEFAULT NULL,
    status    ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB;

-- Homeroom-style assignment: which room a section is based in for the
-- school year. Distinct from class_schedules.room_id, which tracks the
-- room for each individual period (Senior High students move rooms per
-- subject; Junior High sections mostly stay in their homeroom).
CREATE TABLE room_assignments (
    room_assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id            INT NOT NULL,
    section_id         INT NOT NULL,
    sy_id              INT NOT NULL,
    UNIQUE KEY uq_section_homeroom_per_year (section_id, sy_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (sy_id) REFERENCES school_year(sy_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A teacher's teaching load: this subject, taught to this section, this
-- school year. class_schedules rows reference this indirectly (via
-- teacher_id + subject_id + section_id) so "View Teaching Load" and
-- "View Assigned Subjects" are simple queries against this one table.
-- The UNIQUE key is the actual "prevent duplicate assignment" guard
-- required by the Teacher Assignment module.
CREATE TABLE teacher_assignments (
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

-- The actual day/time timetable. A section's full weekly timetable is
-- simply "all class_schedules rows where section_id = X", which is what
-- both the Academic Management Section Schedule view and the Student
-- Portal's Class Schedule page query. approved_by/approved_at let the
-- Principal/Administrator "approve" a schedule from the admin panel
-- without needing a separate login into the Academic Management portal.
CREATE TABLE class_schedules (
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

CREATE TABLE fee_assessments (
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

CREATE TABLE payments (
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

-- =====================================================================
-- About SABNAHIS module (2026-08) - see database/migration_2026_08_about_module.sql
-- for the standalone upgrade script (existing installs should run that
-- file directly; this block just keeps schema.sql complete for fresh installs).
-- =====================================================================

CREATE TABLE about_school (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    banner_image VARCHAR(255) NULL,
    logo_image VARCHAR(255) NULL,
    school_name VARCHAR(150) NULL,
    school_motto VARCHAR(255) NULL,
    short_description VARCHAR(500) NULL,
    about_title VARCHAR(150) NULL,
    about_description MEDIUMTEXT NULL,
    history_title VARCHAR(150) NULL,
    history_content MEDIUMTEXT NULL,
    history_image VARCHAR(255) NULL,
    vision MEDIUMTEXT NULL,
    mission MEDIUMTEXT NULL,
    principal_name VARCHAR(150) NULL,
    principal_title VARCHAR(20) NULL,
    principal_position VARCHAR(150) NULL,
    principal_photo VARCHAR(255) NULL,
    principal_message MEDIUMTEXT NULL,
    principal_signature VARCHAR(255) NULL,
    principal_email VARCHAR(150) NULL,
    principal_contact VARCHAR(50) NULL,
    principal_status ENUM('Active','Inactive','Draft') NOT NULL DEFAULT 'Active',
    contact_address VARCHAR(255) NULL,
    contact_telephone VARCHAR(50) NULL,
    contact_mobile VARCHAR(50) NULL,
    contact_email VARCHAR(150) NULL,
    contact_office_hours VARCHAR(150) NULL,
    contact_website VARCHAR(150) NULL,
    map_embed TEXT NULL,
    social_facebook VARCHAR(255) NULL,
    social_messenger VARCHAR(255) NULL,
    social_youtube VARCHAR(255) NULL,
    social_instagram VARCHAR(255) NULL,
    social_tiktok VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO about_school (id) VALUES (1);

CREATE TABLE about_core_values (
    core_value_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icon VARCHAR(50) NULL,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE about_facilities (
    facility_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    image VARCHAR(255) NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE about_gallery (
    gallery_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    title VARCHAR(150) NULL,
    caption VARCHAR(500) NULL,
    category VARCHAR(100) NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE about_downloads (
    download_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
