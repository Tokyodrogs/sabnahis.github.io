-- Migration 2026-08d: Multi-applicant-type online enrollment
-- Run after migration_2026_08c_language_preference.sql
--
-- Reuses the EXISTING students.student_type column (already used throughout
-- admin/*.php for filtering/labeling: 'New' = New Student, 'Transferee' =
-- Transferee) instead of adding a parallel "applicant_type" column, which
-- would have created two competing sources of truth for the same concept.
-- 'Returning' already existed and is repurposed here as "Old Student /
-- Continuing" (advancing to the next grade level without a gap); the new
-- 'Balik-Aral' value covers a learner returning after one or more school
-- years away - the two are distinguished by the extra fields added below,
-- which only apply to Balik-Aral (years_not_enrolled, reason_for_returning).
--
-- "Student Number" in the enrollment spec maps to the existing
-- students.enrollment_no column - no new column needed for that.

ALTER TABLE students
    MODIFY COLUMN student_type ENUM('New','Returning','Transferee','Balik-Aral') NOT NULL DEFAULT 'New';

ALTER TABLE students
    ADD COLUMN previous_school_id   INT NULL DEFAULT NULL AFTER previous_school,
    ADD COLUMN school_type          ENUM('Public','Private') NULL DEFAULT NULL AFTER previous_school_id,
    ADD COLUMN reason_for_transfer  VARCHAR(50) NULL DEFAULT NULL AFTER school_type,
    ADD COLUMN reason_for_returning VARCHAR(50) NULL DEFAULT NULL AFTER reason_for_transfer,
    ADD COLUMN years_not_enrolled   VARCHAR(20) NULL DEFAULT NULL AFTER reason_for_returning,
    ADD COLUMN last_grade_completed VARCHAR(100) NULL DEFAULT NULL AFTER years_not_enrolled,
    ADD COLUMN guardian_relationship VARCHAR(50) NULL DEFAULT NULL AFTER guardian_contact,
    ADD COLUMN guardian_occupation   VARCHAR(100) NULL DEFAULT NULL AFTER guardian_relationship,
    ADD COLUMN guardian_emergency_contact VARCHAR(20) NULL DEFAULT NULL AFTER guardian_occupation;

-- ---------------------------------------------------------------------
-- Searchable Philippine school directory (Elementary School / Previous
-- School / Last School Attended fields). Seeded here with a small sample
-- set so the search UI is testable immediately; import the real DepEd
-- master list later via admin/schools_import.php (CSV: name, region,
-- province, municipality, type).
-- ---------------------------------------------------------------------
CREATE TABLE schools (
    school_id    INT AUTO_INCREMENT PRIMARY KEY,
    school_name  VARCHAR(200) NOT NULL,
    region       VARCHAR(100) DEFAULT NULL,
    province     VARCHAR(100) DEFAULT NULL,
    municipality VARCHAR(100) DEFAULT NULL,
    school_type  ENUM('Public','Private') NOT NULL DEFAULT 'Public',
    is_verified  TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_school_name (school_name)
) ENGINE=InnoDB;

ALTER TABLE students
    ADD CONSTRAINT fk_students_previous_school
    FOREIGN KEY (previous_school_id) REFERENCES schools(school_id) ON DELETE SET NULL;

INSERT INTO schools (school_name, region, province, municipality, school_type, is_verified) VALUES
('Sablayan National Comprehensive High School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1),
('Sablayan Central Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1),
('Sablayan West Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1),
('Buenavista Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1),
('Sto. Nino Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Public', 1),
('Mamburao Central Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Mamburao', 'Public', 1),
('Occidental Mindoro National High School', 'MIMAROPA', 'Occidental Mindoro', 'Mamburao', 'Public', 1),
('San Jose Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'San Jose', 'Public', 1),
('San Jose National High School', 'MIMAROPA', 'Occidental Mindoro', 'San Jose', 'Public', 1),
('Calapan City Elementary School', 'MIMAROPA', 'Oriental Mindoro', 'Calapan City', 'Public', 1),
('Divine Word College of Calapan', 'MIMAROPA', 'Oriental Mindoro', 'Calapan City', 'Private', 1),
('Manila Elementary School', 'NCR', 'Metro Manila', 'Manila', 'Public', 1),
('Quezon City Science High School', 'NCR', 'Metro Manila', 'Quezon City', 'Public', 1),
('Holy Infant Academy', 'MIMAROPA', 'Occidental Mindoro', 'Sablayan', 'Private', 1),
('St. Augustine Learning Center', 'MIMAROPA', 'Occidental Mindoro', 'San Jose', 'Private', 1),
('Rizal Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Rizal', 'Public', 1),
('Abra de Ilog Elementary School', 'MIMAROPA', 'Occidental Mindoro', 'Abra de Ilog', 'Public', 1),
('Looc National High School', 'MIMAROPA', 'Occidental Mindoro', 'Looc', 'Public', 1);
