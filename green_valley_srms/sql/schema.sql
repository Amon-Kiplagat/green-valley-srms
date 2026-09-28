-- ============================================================================
--  Green Valley Secondary School - Student Records Management System (SRMS)
--  PostgreSQL schema
--  Target: PostgreSQL 13+ (tested against server version 18.x)
--
--  HOW TO RUN:
--    createdb green_valley                 # if the database does not exist yet
--    psql -U mole -d green_valley -f schema.sql
--
--  This script is idempotent-ish: it drops the SRMS tables (if they exist) and
--  recreates them cleanly. Run seed.sql afterwards for sample data + admin user.
-- ============================================================================

-- Drop in dependency order (child tables first) so foreign keys don't block us.
DROP TABLE IF EXISTS results   CASCADE;
DROP TABLE IF EXISTS exams     CASCADE;
DROP TABLE IF EXISTS subjects  CASCADE;
DROP TABLE IF EXISTS students  CASCADE;
DROP TABLE IF EXISTS classes   CASCADE;
DROP TABLE IF EXISTS users     CASCADE;
DROP TABLE IF EXISTS teachers  CASCADE;

-- ----------------------------------------------------------------------------
-- TEACHERS
--   teacher_id is the surrogate primary key referenced by classes, subjects,
--   and users. staff_no is the human-facing code shown in the UI (e.g. T001).
-- ----------------------------------------------------------------------------
CREATE TABLE teachers (
    teacher_id  SERIAL PRIMARY KEY,
    staff_no    VARCHAR(20)  NOT NULL UNIQUE,
    full_name   VARCHAR(120) NOT NULL,
    gender      VARCHAR(10)  NOT NULL DEFAULT 'Male'
                CHECK (gender IN ('Male', 'Female')),
    phone       VARCHAR(20),
    email       VARCHAR(120) UNIQUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ----------------------------------------------------------------------------
-- USERS (login accounts)
--   role drives role-based access control. A teacher account may optionally be
--   linked to a teachers row via teacher_id.
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    user_id       SERIAL PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- output of PHP password_hash()
    full_name     VARCHAR(120) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'teacher'
                  CHECK (role IN ('admin', 'teacher')),
    teacher_id    INTEGER REFERENCES teachers(teacher_id) ON DELETE SET NULL,
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ----------------------------------------------------------------------------
-- CLASSES
--   teacher_id = the assigned class teacher (nullable). ON DELETE SET NULL so
--   removing a teacher does not delete the class.
-- ----------------------------------------------------------------------------
CREATE TABLE classes (
    class_id    SERIAL PRIMARY KEY,
    class_name  VARCHAR(60) NOT NULL UNIQUE,       -- e.g. "Form 1 East"
    teacher_id  INTEGER REFERENCES teachers(teacher_id) ON DELETE SET NULL,
    room        VARCHAR(40)
);

-- ----------------------------------------------------------------------------
-- STUDENTS
--   admission_no is unique (enforced in the app AND the DB).
--   class_id links to classes; ON DELETE SET NULL keeps the student if a class
--   is removed.
-- ----------------------------------------------------------------------------
CREATE TABLE students (
    student_id     SERIAL PRIMARY KEY,
    admission_no   VARCHAR(30)  NOT NULL UNIQUE,
    full_name      VARCHAR(120) NOT NULL,
    gender         VARCHAR(10)  NOT NULL DEFAULT 'Male'
                   CHECK (gender IN ('Male', 'Female')),
    date_of_birth  DATE,
    class_id       INTEGER REFERENCES classes(class_id) ON DELETE SET NULL,
    guardian_name  VARCHAR(120),
    guardian_phone VARCHAR(20),
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ----------------------------------------------------------------------------
-- SUBJECTS
--   teacher_id = the teacher assigned to the subject (nullable).
-- ----------------------------------------------------------------------------
CREATE TABLE subjects (
    subject_id   SERIAL PRIMARY KEY,
    subject_code VARCHAR(20)  NOT NULL UNIQUE,     -- e.g. "MAT"
    subject_name VARCHAR(80)  NOT NULL,
    teacher_id   INTEGER REFERENCES teachers(teacher_id) ON DELETE SET NULL
);

-- ----------------------------------------------------------------------------
-- EXAMS (examinations)
--   A given exam is uniquely identified by its name + term + year.
-- ----------------------------------------------------------------------------
CREATE TABLE exams (
    exam_id    SERIAL PRIMARY KEY,
    exam_name  VARCHAR(80) NOT NULL,               -- e.g. "End-Term", "Mid-Term"
    term       VARCHAR(20) NOT NULL
               CHECK (term IN ('Term 1', 'Term 2', 'Term 3')),
    year       INTEGER     NOT NULL
               CHECK (year BETWEEN 2000 AND 2100),
    CONSTRAINT uq_exam UNIQUE (exam_name, term, year)
);

-- ----------------------------------------------------------------------------
-- RESULTS (marks)
--   One row per (student, subject, exam) - enforced by uq_result so the same
--   student cannot have duplicate marks for the same subject in the same exam.
--   marks are 0..100; grade is derived in PHP and stored for fast reporting.
--   ON DELETE CASCADE: if a student/subject/exam is deleted, its marks go too.
-- ----------------------------------------------------------------------------
CREATE TABLE results (
    result_id  SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
    subject_id INTEGER NOT NULL REFERENCES subjects(subject_id) ON DELETE CASCADE,
    exam_id    INTEGER NOT NULL REFERENCES exams(exam_id)       ON DELETE CASCADE,
    marks      NUMERIC(5,2) NOT NULL
               CHECK (marks >= 0 AND marks <= 100),
    grade      VARCHAR(3)   NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_result UNIQUE (student_id, subject_id, exam_id)
);

-- ----------------------------------------------------------------------------
-- INDEXES (speed up the joins/searches the app performs most)
-- ----------------------------------------------------------------------------
CREATE INDEX idx_students_class    ON students(class_id);
CREATE INDEX idx_students_name     ON students(full_name);
CREATE INDEX idx_classes_teacher   ON classes(teacher_id);
CREATE INDEX idx_subjects_teacher  ON subjects(teacher_id);
CREATE INDEX idx_results_student   ON results(student_id);
CREATE INDEX idx_results_subject   ON results(subject_id);
CREATE INDEX idx_results_exam      ON results(exam_id);
CREATE INDEX idx_users_teacher     ON users(teacher_id);

-- ============================================================================
-- Done. Run seed.sql next to insert sample data and the initial admin login.
-- ============================================================================
