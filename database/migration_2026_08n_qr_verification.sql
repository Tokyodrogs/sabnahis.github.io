-- Migration 2026-08n: Digital Student ID QR verification system.
--
-- Adds a per-student secure verification token (never the student's name,
-- LRN, guardian contact, address, etc.) that the Digital Student ID QR
-- code encodes as a URL: student/verify.php?token=<token>. The token is
-- generated once and reused - see get_or_create_qr_token() in
-- includes/functions.php - and only changes when an administrator
-- explicitly regenerates it (which immediately invalidates the old one)
-- or deactivates the ID (qr_status = 'inactive', verify.php then refuses
-- it without deleting the token).
--
-- Also creates student_gate_logs, structure-only prep for a future Gate
-- Check-In/Check-Out system that will scan this same QR code - no
-- scanning UI or SMS notification is implemented yet, this just reserves
-- the table shape so that feature doesn't need another migration later.
--
-- Run this once against an existing SABNAHIS database.

ALTER TABLE students
  ADD COLUMN qr_token VARCHAR(64) NULL DEFAULT NULL AFTER photo,
  ADD COLUMN qr_status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER qr_token,
  ADD COLUMN qr_generated_at DATETIME NULL DEFAULT NULL AFTER qr_status,
  ADD UNIQUE KEY uq_students_qr_token (qr_token);

CREATE TABLE IF NOT EXISTS student_gate_logs (
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
