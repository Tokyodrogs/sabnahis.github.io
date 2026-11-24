-- =========================================================================
-- Migration 2026-08f: Official Philippine School Directory upgrade
-- =========================================================================
-- Extends the `schools` table (introduced in migration_2026_08d) with the
-- fields needed to eventually hold the full DepEd School Directory
-- (60,000+ schools) and to support per-applicant-type filtering:
--   - New Student's "Elementary School" field must show ONLY schools that
--     offer Elementary (offers_elementary = 1).
--   - Transferee's "Previous School" and Balik-Aral's "Last School
--     Attended" search ALL schools regardless of level.
--   - Old Student never shows a school field at all (unchanged).
--
-- IMPORTANT - data honesty note: this migration does NOT insert 60,000
-- real DepEd records. Anthropic/Claude has no access to an authoritative
-- DepEd School Directory dataset, and fabricating School IDs/codes would
-- put fake data in the database labeled as official government records.
-- What this migration DOES do:
--   1. Adds the columns a real DepEd School Directory export uses, so an
--      admin can import the official file via admin/schools_import.php's
--      updated CSV importer (see that page) once they have it.
--   2. Backfills offers_elementary/offers_junior_high/offers_senior_high
--      for the ~18 pre-existing SAMPLE rows using their names as a
--      reasonable best-effort classification (e.g. "X Elementary School"
--      -> Elementary; "X National High School" -> Junior + Senior High,
--      standard for public high schools post-K12). Genuinely ambiguous
--      private schools (Academy / Learning Center / College names, where
--      level offerings can't be reliably guessed from the name alone) are
--      flipped to is_verified = 0 ("Needs Review" in the admin UI) instead
--      of guessing silently.
-- =========================================================================

ALTER TABLE schools
    ADD COLUMN deped_school_id     VARCHAR(20)  DEFAULT NULL AFTER school_id,
    ADD COLUMN school_code         VARCHAR(20)  DEFAULT NULL AFTER deped_school_id,
    ADD COLUMN division            VARCHAR(100) DEFAULT NULL AFTER region,
    ADD COLUMN barangay            VARCHAR(100) DEFAULT NULL AFTER municipality,
    ADD COLUMN address             VARCHAR(255) DEFAULT NULL AFTER barangay,
    ADD COLUMN zip_code            VARCHAR(10)  DEFAULT NULL AFTER address,
    ADD COLUMN offers_elementary   TINYINT(1) NOT NULL DEFAULT 0 AFTER school_type,
    ADD COLUMN offers_junior_high  TINYINT(1) NOT NULL DEFAULT 0 AFTER offers_elementary,
    ADD COLUMN offers_senior_high  TINYINT(1) NOT NULL DEFAULT 0 AFTER offers_junior_high,
    ADD COLUMN is_active           TINYINT(1) NOT NULL DEFAULT 1 AFTER is_verified,
    ADD COLUMN updated_at          TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Search/filter performance (spec calls out province/municipality/region/
-- DepEd ID lookups and expects this table to eventually hold 60k+ rows).
CREATE INDEX idx_schools_deped_id ON schools (deped_school_id);
CREATE INDEX idx_schools_province ON schools (province);
CREATE INDEX idx_schools_municipality ON schools (municipality);
CREATE INDEX idx_schools_region ON schools (region);
CREATE INDEX idx_schools_offers_elementary ON schools (offers_elementary);

-- ---------------------------------------------------------------------
-- Backfill for the pre-existing 18 sample rows (best-effort, see note
-- above). Matched by exact school_name since these rows are few and
-- already known.
-- ---------------------------------------------------------------------
UPDATE schools SET offers_elementary = 1 WHERE school_name IN (
    'Sablayan Central Elementary School',
    'Sablayan West Elementary School',
    'Buenavista Elementary School',
    'Sto. Nino Elementary School',
    'Mamburao Central Elementary School',
    'San Jose Elementary School',
    'Calapan City Elementary School',
    'Manila Elementary School',
    'Rizal Elementary School',
    'Abra de Ilog Elementary School'
);

UPDATE schools SET offers_junior_high = 1, offers_senior_high = 1 WHERE school_name IN (
    'Sablayan National Comprehensive High School',
    'Occidental Mindoro National High School',
    'San Jose National High School',
    'Quezon City Science High School',
    'Looc National High School'
);

-- Ambiguous private schools - level offerings genuinely unknown without a
-- real source; flagged for admin review rather than guessed silently.
UPDATE schools SET is_verified = 0 WHERE school_name IN (
    'Divine Word College of Calapan',
    'Holy Infant Academy',
    'St. Augustine Learning Center'
);
