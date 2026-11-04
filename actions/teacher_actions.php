<?php
/**
 * actions/teacher_actions.php
 * ---------------------------------------------------------------------------
 * Server-side handler for teacher CRUD and password management.
 * Called via POST/GET from teachers.php.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

/* ------------------------------------------------------------------ */
/* DELETE (via GET with CSRF token in URL)                             */
/* ------------------------------------------------------------------ */
if ($action === 'delete') {
    require_role('admin');
    verify_csrf();

    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        set_flash('error', 'Invalid teacher ID.');
        redirect('teachers.php');
    }

    // Fetch name for flash message.
    $stmt = $pdo->prepare('SELECT full_name, staff_no FROM teachers WHERE teacher_id = :id');
    $stmt->execute([':id' => $teacherId]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        set_flash('error', 'Teacher not found.');
        redirect('teachers.php');
    }

    $label = $teacher['full_name'] . ' (' . $teacher['staff_no'] . ')';

    // Nullify references instead of deleting (teachers may be assigned to classes/subjects).
    $pdo->prepare('UPDATE classes SET teacher_id = NULL WHERE teacher_id = :id')
        ->execute([':id' => $teacherId]);
    $pdo->prepare('UPDATE subjects SET teacher_id = NULL WHERE teacher_id = :id')
        ->execute([':id' => $teacherId]);
    $pdo->prepare('UPDATE users SET teacher_id = NULL WHERE teacher_id = :id')
        ->execute([':id' => $teacherId]);

    $pdo->prepare('DELETE FROM teachers WHERE teacher_id = :id')
        ->execute([':id' => $teacherId]);

    log_activity('Deleted', 'Teacher', $label);
    set_flash('success', 'Teacher ' . htmlspecialchars($label) . ' deleted.');
    redirect('teachers.php');
}

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

verify_csrf();

/* ------------------------------------------------------------------ */
/* CREATE / UPDATE teacher                                            */
/* ------------------------------------------------------------------ */
if ($action === 'create' || $action === 'update') {
    require_role('admin');

    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $staffNo   = trim((string)($_POST['staff_no'] ?? ''));
    $fullName  = trim((string)($_POST['full_name'] ?? ''));
    $gender    = trim((string)($_POST['gender'] ?? 'Male'));
    $phoneRaw  = trim((string)($_POST['phone'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));

    // Validation.
    if ($staffNo === '' || $fullName === '') {
        set_flash('error', 'Staff number and full name are required.');
        redirect($action === 'update' ? "teachers.php?edit=$teacherId" : 'teachers.php');
    }

    if ($gender !== 'Male' && $gender !== 'Female') {
        $gender = 'Male';
    }

    // Phone normalisation.
    $phoneResult = function_exists('normalize_phone') ? normalize_phone($phoneRaw) : ['ok' => true, 'value' => $phoneRaw];
    if (!$phoneResult['ok']) {
        set_flash('error', $phoneResult['error'] ?? 'Invalid phone number.');
        redirect($action === 'update' ? "teachers.php?edit=$teacherId" : 'teachers.php');
    }
    $phone = $phoneResult['value'];

    // Check staff_no uniqueness.
    $stmt = $pdo->prepare(
        'SELECT teacher_id FROM teachers WHERE staff_no = :sn AND teacher_id != :id LIMIT 1'
    );
    $stmt->execute([':sn' => $staffNo, ':id' => max($teacherId, 0)]);
    if ($stmt->fetch()) {
        set_flash('error', 'Staff number ' . htmlspecialchars($staffNo) . ' is already in use.');
        redirect($action === 'update' ? "teachers.php?edit=$teacherId" : 'teachers.php');
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare(
            'INSERT INTO teachers (staff_no, full_name, gender, phone, email)
             VALUES (:sn, :fn, :g, :ph, :em)
             RETURNING teacher_id'
        );
        $stmt->execute([
            ':sn' => $staffNo,
            ':fn' => $fullName,
            ':g'  => $gender,
            ':ph' => $phone !== '' ? $phone : null,
            ':em' => $email !== '' ? $email : null,
        ]);
        $newId = (int)$stmt->fetch()['teacher_id'];
        log_activity('Added', 'Teacher', "$fullName ($staffNo)");
        set_flash('success', "Teacher $fullName added.");
        redirect('teachers.php');
    } else {
        $stmt = $pdo->prepare(
            'UPDATE teachers
               SET staff_no  = :sn,
                   full_name = :fn,
                   gender    = :g,
                   phone     = :ph,
                   email     = :em
             WHERE teacher_id = :id'
        );
        $stmt->execute([
            ':sn' => $staffNo,
            ':fn' => $fullName,
            ':g'  => $gender,
            ':ph' => $phone !== '' ? $phone : null,
            ':em' => $email !== '' ? $email : null,
            ':id' => $teacherId,
        ]);
        log_activity('Updated', 'Teacher', "$fullName ($staffNo)");
        set_flash('success', "Teacher $fullName updated.");
        redirect('teachers.php');
    }
}

/* ------------------------------------------------------------------ */
/* CHANGE PASSWORD — admin resets a staff member's password            */
/* ------------------------------------------------------------------ */
if ($action === 'change_password') {
    require_role('admin');

    $userId    = (int)($_POST['user_id'] ?? 0);
    $newPass   = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    if ($userId <= 0) {
        set_flash('error', 'Invalid user ID.');
        redirect('teachers.php');
    }

    if ($newPass === '' || strlen($newPass) < 6) {
        set_flash('error', 'Password must be at least 6 characters.');
        redirect('teachers.php?change_password=' . $userId);
    }

    if ($newPass !== $confirmPass) {
        set_flash('error', 'Password and confirmation do not match.');
        redirect('teachers.php?change_password=' . $userId);
    }

    // Load the user to verify existence.
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, u.full_name, u.role, t.full_name AS teacher_name
           FROM users u
           LEFT JOIN teachers t ON t.teacher_id = u.teacher_id
          WHERE u.user_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash('error', 'User account not found.');
        redirect('teachers.php');
    }

    $displayName = $user['full_name'] ?? $user['teacher_name'] ?? $user['username'] ?? 'Unknown';

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
        ->execute([':h' => $hash, ':id' => $userId]);

    log_activity('Changed password for', 'User', "$displayName ({$user['username']})");
    set_flash('success', 'Password for ' . htmlspecialchars($displayName) . ' has been updated.');
    redirect('teachers.php');
}
