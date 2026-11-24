-- =========================================================================
-- Migration 2026-08h: Admin-configurable Requirements + Balik-Aral last
-- school year attended.
-- =========================================================================
-- Moves REQUIRED_DOCUMENTS_BY_TYPE / OPTIONAL_DOCUMENTS_BY_TYPE (previously
-- hardcoded PHP arrays in config/constants.php) into a real table an
-- Administrator can edit from admin/requirements.php, without touching
-- code. Seeded below with the exact same values the constants had, so
-- behavior does not change until an admin actually edits something.
-- includes/functions.php::required_documents_for_type()/
-- optional_documents_for_type() now read from this table (falling back to
-- the old constants only if the table is somehow empty).
-- =========================================================================

CREATE TABLE requirement_configs (
    requirement_id  INT AUTO_INCREMENT PRIMARY KEY,
    applicant_type  ENUM('New','Returning','Transferee','Balik-Aral') NOT NULL,
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
('Balik-Aral', 'form_137', 'Form 137 (if applicable)', 0, 5);

-- 'Returning' (Old Student) intentionally has no seed rows - their
-- documents are already on file from their original enrollment. An
-- Administrator can still add rows for it via admin/requirements.php if a
-- school ever wants to require something extra at re-enrollment.

-- ---------------------------------------------------------------------
-- Balik-Aral: capture the school year they last attended (distinct from
-- last_grade_completed / years_not_enrolled, added in migration_2026_08d).
-- Only meaningful for the manual-entry path (no SABNAHIS record found via
-- lookup) - see enroll.php.
-- ---------------------------------------------------------------------
ALTER TABLE students
    ADD COLUMN last_school_year_attended VARCHAR(20) NULL DEFAULT NULL AFTER last_grade_completed;
