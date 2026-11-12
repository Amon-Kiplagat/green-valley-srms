<?php
/**
 * actions/student_actions.php
 * ---------------------------------------------------------------------------
 * Server-side handler for student CRUD and portal account management.
 * Called via POST from students.php.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_portal.php';
require_login();

$pdo = db();
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

/* ------------------------------------------------------------------ */
/* DELETE (via GET with CSRF token in URL)                             */
/* ------------------------------------------------------------------ */
if ($action === 'delete') {
    require_role('admin');
    verify_csrf();

    $studentId = (int)($_GET['student_id'] ?? 0);
    if ($studentId <= 0) {
        set_flash('error', 'Invalid student ID.');
        redirect('students.php');
    }

    // Fetch name for flash message.
    $stmt = $pdo->prepare('SELECT full_name, admission_no FROM students WHERE student_id = :id');
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        set_flash('error', 'Student not found.');
        redirect('students.php');
    }

    $label = $student['full_name'] . ' (' . $student['admission_no'] . ')';

    // Delete related results first (CASCADE handles it, but be explicit).
    $pdo->prepare('DELETE FROM results WHERE student_id = :id')
        ->execute([':id' => $studentId]);

    $pdo->prepare('DELETE FROM students WHERE student_id = :id')
        ->execute([':id' => $studentId]);

    log_activity('Deleted', 'Student', $label);
    set_flash('success', 'Student ' . htmlspecialchars($label) . ' deleted.');
    redirect('students.php');
}

/* ------------------------------------------------------------------ */
/* POST actions: create / update / enable_portal / disable_portal      */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

verify_csrf();

if ($action === 'create' || $action === 'update') {
    $studentId   = (int)($_POST['student_id'] ?? 0);
    $admissionNo = trim((string)($_POST['admission_no'] ?? ''));
    $fullName    = trim((string)($_POST['full_name'] ?? ''));
    $gender      = trim((string)($_POST['gender'] ?? 'Male'));
    $dob         = trim((string)($_POST['date_of_birth'] ?? ''));
    $classId     = (int)($_POST['class_id'] ?? 0);
    $guardian    = trim((string)($_POST['guardian_name'] ?? ''));

    // Phone normalisation.
    $phoneResult = normalize_phone((string)($_POST['guardian_phone'] ?? ''));
    if (!$phoneResult['ok']) {
        set_flash('error', $phoneResult['error']);
        redirect($action === 'update' ? "students.php?edit=$studentId" : 'students.php');
    }
    $guardianPhone = $phoneResult['value'];

    // Validation.
    if ($admissionNo === '' || $fullName === '') {
        set_flash('error', 'Admission number and full name are required.');
        redirect($action === 'update' ? "students.php?edit=$studentId" : 'students.php');
    }

    if ($gender !== 'Male' && $gender !== 'Female') {
        $gender = 'Male';
    }

    // Validate date of birth: student must be 14 or 15 years old (joining high school).
    if ($dob !== '') {
        $dobDate  = DateTime::createFromFormat('Y-m-d', $dob);
        $now      = new DateTime();
        $age      = $now->diff($dobDate)->y;
        if ($age < 14 || $age > 15) {
            set_flash('error', 'Date of birth must correspond to an age of 14–15 years for high school admission.');
            redirect($action === 'update' ? "students.php?edit=$studentId" : 'students.php');
        }
    }

    // Check admission_no uniqueness.
    $stmt = $pdo->prepare(
        'SELECT student_id FROM students WHERE admission_no = :an AND student_id != :id LIMIT 1'
    );
    $stmt->execute([':an' => $admissionNo, ':id' => max($studentId, 0)]);
    if ($stmt->fetch()) {
        set_flash('error', 'Admission number ' . htmlspecialchars($admissionNo) . ' is already in use.');
        redirect($action === 'update' ? "students.php?edit=$studentId" : 'students.php');
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare(
            'INSERT INTO students (admission_no, full_name, gender, date_of_birth, class_id,
                                   guardian_name, guardian_phone)
             VALUES (:an, :fn, :g, :dob, :cid, :gn, :gp)
             RETURNING student_id'
        );
        $stmt->execute([
            ':an'  => $admissionNo,
            ':fn'  => $fullName,
            ':g'   => $gender,
            ':dob' => $dob !== '' ? $dob : null,
            ':cid' => $classId > 0 ? $classId : null,
            ':gn'  => $guardian !== '' ? $guardian : null,
            ':gp'  => $guardianPhone,
        ]);
        $newId = (int)$stmt->fetch()['student_id'];
        log_activity('Added', 'Student', "$fullName ($admissionNo)");
        set_flash('success', "Student $fullName added.");
        redirect('students.php');
    } else {
        $stmt = $pdo->prepare(
            'UPDATE students
               SET admission_no   = :an,
                   full_name      = :fn,
                   gender         = :g,
                   date_of_birth  = :dob,
                   class_id       = :cid,
                   guardian_name  = :gn,
                   guardian_phone = :gp
             WHERE student_id = :id'
        );
        $stmt->execute([
            ':an'  => $admissionNo,
            ':fn'  => $fullName,
            ':g'   => $gender,
            ':dob' => $dob !== '' ? $dob : null,
            ':cid' => $classId > 0 ? $classId : null,
            ':gn'  => $guardian !== '' ? $guardian : null,
            ':gp'  => $guardianPhone,
            ':id'  => $studentId,
        ]);
        log_activity('Updated', 'Student', "$fullName ($admissionNo)");
        set_flash('success', "Student $fullName updated.");
        redirect('students.php');
    }
}

/* ------------------------------------------------------------------ */
/* PORTAL ACCOUNT MANAGEMENT                                          */
/* ------------------------------------------------------------------ */
if ($action === 'enable_portal') {
    require_role('admin');

    $studentId = (int)($_POST['student_id'] ?? 0);
    $portalUsername = trim((string)($_POST['portal_username'] ?? ''));
    $portalPassword = (string)($_POST['portal_password'] ?? '');

    if ($studentId <= 0) {
        set_flash('error', 'Invalid student ID.');
        redirect('students.php');
    }

    // Load student info.
    $stmt = $pdo->prepare(
        'SELECT s.student_id, s.full_name, s.admission_no, s.portal_enabled
           FROM students s
          WHERE s.student_id = :id'
    );
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        set_flash('error', 'Student not found.');
        redirect('students.php');
    }

    if (db_bool($student['portal_enabled'])) {
        set_flash('info', 'Portal is already enabled for this student.');
        redirect('students.php');
    }

    // Validate username.
    if ($portalUsername === '' || strlen($portalUsername) < 3) {
        set_flash('error', 'Portal username must be at least 3 characters.');
        redirect('students.php');
    }

    // Check uniqueness.
    $stmt = $pdo->prepare(
        'SELECT student_id FROM students WHERE portal_username IS NOT NULL AND lower(portal_username) = lower(:u) AND student_id != :id LIMIT 1'
    );
    $stmt->execute([':u' => $portalUsername, ':id' => $studentId]);
    if ($stmt->fetch()) {
        set_flash('error', 'Portal username ' . htmlspecialchars($portalUsername) . ' is already in use.');
        redirect('students.php');
    }

    // Validate password.
    if ($portalPassword === '' || strlen($portalPassword) < 6) {
        set_flash('error', 'Default password must be at least 6 characters.');
        redirect('students.php');
    }

    $hash = password_hash($portalPassword, PASSWORD_DEFAULT);

    $pdo->prepare(
        'UPDATE students
             SET portal_username      = :un,
                 portal_password_hash = :ph,
                 portal_enabled       = TRUE,
                 portal_must_change   = TRUE,
                 portal_failed_attempts = 0,
                 portal_locked_until  = NULL
           WHERE student_id = :id'
    )->execute([
        ':un' => $portalUsername,
        ':ph' => $hash,
        ':id' => $studentId,
    ]);

    $label = $student['full_name'] . ' (' . $student['admission_no'] . ')';
    log_activity('Enabled portal', 'Student', $label);
    set_flash('success', "Portal account created for $label. Username: $portalUsername");
    redirect('students.php');
}

if ($action === 'disable_portal') {
    require_role('admin');

    $studentId = (int)($_POST['student_id'] ?? 0);
    if ($studentId <= 0) {
        set_flash('error', 'Invalid student ID.');
        redirect('students.php');
    }

    $stmt = $pdo->prepare(
        'SELECT full_name, admission_no FROM students WHERE student_id = :id'
    );
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        set_flash('error', 'Student not found.');
        redirect('students.php');
    }

    $pdo->prepare(
        'UPDATE students
             SET portal_enabled       = FALSE,
                 portal_username      = NULL,
                 portal_password_hash = NULL,
                 portal_failed_attempts = 0,
                 portal_locked_until  = NULL
           WHERE student_id = :id'
    )->execute([':id' => $studentId]);

    $label = $student['full_name'] . ' (' . $student['admission_no'] . ')';
    log_activity('Disabled portal', 'Student', $label);
    set_flash('success', "Portal account removed for $label.");
    redirect('students.php');
}

if ($action === 'reset_portal_password') {
    require_role('admin');

    $studentId = (int)($_POST['student_id'] ?? 0);
    $portalPassword = (string)($_POST['portal_password'] ?? '');

    if ($studentId <= 0) {
        set_flash('error', 'Invalid student ID.');
        redirect('students.php');
    }

    if ($portalPassword === '' || strlen($portalPassword) < 6) {
        set_flash('error', 'New password must be at least 6 characters.');
        redirect('students.php');
    }

    $stmt = $pdo->prepare(
        'SELECT full_name, admission_no, portal_enabled
           FROM students
          WHERE student_id = :id'
    );
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student || !db_bool($student['portal_enabled'])) {
        set_flash('error', 'Student does not have an active portal account.');
        redirect('students.php');
    }

    $hash = password_hash($portalPassword, PASSWORD_DEFAULT);

    $pdo->prepare(
        'UPDATE students
             SET portal_password_hash = :ph,
                 portal_must_change   = TRUE,
                 portal_failed_attempts = 0,
                 portal_locked_until  = NULL
           WHERE student_id = :id'
    )->execute([':ph' => $hash, ':id' => $studentId]);

    $label = $student['full_name'] . ' (' . $student['admission_no'] . ')';
    log_activity('Reset portal password', 'Student', $label);
    set_flash('success', "Portal password reset for $label. Student must change on next login.");
    redirect('students.php');
}

/* ------------------------------------------------------------------ */
/* Fallback                                                           */
/* ------------------------------------------------------------------ */
set_flash('error', 'Unknown action.');
redirect('students.php');
