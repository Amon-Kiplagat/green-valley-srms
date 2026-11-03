-- ============================================================================
--  Migration: Add student portal columns to the students table.
--
--  This adds the columns required by the student portal login system
--  (includes/student_portal.php and login.php student panel).
--
--  HOW TO RUN:
--    psql -U mole -d green_valley -f migration_2026_11_student_portal.sql
--
--  Safe to re-run: uses IF NOT EXISTS / conditional ALTER.
-- ============================================================================

-- Student portal authentication columns
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_username      VARCHAR(60);
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_password_hash VARCHAR(255);
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_enabled       BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_must_change   BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_failed_attempts INTEGER NOT NULL DEFAULT 0;
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_locked_until  TIMESTAMPTZ;
ALTER TABLE students ADD COLUMN IF NOT EXISTS portal_last_login    TIMESTAMPTZ;

-- Make portal_username unique (only one student per portal username).
-- NULLs are allowed and do not conflict.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'uq_students_portal_username'
    ) THEN
        ALTER TABLE students ADD CONSTRAINT uq_students_portal_username
            UNIQUE (portal_username);
    END IF;
END $$;

-- Index for fast student portal login lookup.
CREATE INDEX IF NOT EXISTS idx_students_portal_username
    ON students (lower(portal_username) text_pattern_ops)
    WHERE portal_username IS NOT NULL;

-- ============================================================================
--  Fix: Update student portal password hashes.
--  The original seed data accidentally used the admin password hash ('admin123')
--  instead of the student default password hash ('student123').
--  This fixes any existing student portal accounts that have the wrong hash.
-- ============================================================================

UPDATE students
   SET portal_password_hash = '$2y$12$gE6Dn7YW1htwmNRcag1NLuEyxb9FXOM2.KLNGfhVgPkV86xLHXmjW'
 WHERE portal_enabled = TRUE
   AND portal_password_hash = '$2y$10$CF2EfVprqnaGhwtjXW/kZuXLR92XGl8l3e3sMBVWa2luQJZTsC7eK';

-- ============================================================================
--  Optional: Add remark columns to results table for student-facing reports.
-- ============================================================================

ALTER TABLE results ADD COLUMN IF NOT EXISTS class_teacher_remark    VARCHAR(255);
ALTER TABLE results ADD COLUMN IF NOT EXISTS subject_teacher_remark  VARCHAR(255);

-- ============================================================================
--  Optional: Add detail column to activity_log for structured audit data.
-- ============================================================================

ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS detail JSONB;

-- ============================================================================
-- Done.
-- ============================================================================
