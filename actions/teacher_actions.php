<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = db();
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'delete') {
    require_role('admin');
    verify_csrf();

    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        set_flash('error', 'Invalid teacher ID.');
        redirect('teachers.php');
    }

    $stmt = $pdo->prepare('SELECT full_name, staff_no FROM teachers WHERE teacher_id = :id');
    $stmt->execute([':id' => $teacherId]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        set_flash('error', 'Teacher not found.');
        redirect('teachers.php');
    }

    $label = $teacher['full_name'] . ' (' . $teacher['staff_no'] . ')';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

verify_csrf();

if ($action === 'create' || $action === 'update') {
    require_role('admin');

    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $staffNo   = trim((string)($_POST['staff_no'] ?? ''));
    $fullName  = trim((string)($_POST['full_name'] ?? ''));
    $gender    = trim((string)($_POST['gender'] ?? 'Male'));
    $phoneRaw  = trim((string)($_POST['phone'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));

    if ($staffNo === '' || $fullName === '') {
        set_flash('error', 'Staff number and full name are required.');
        redirect($action === 'update' ? "teachers.php?edit=$teacherId" : 'teachers.php');
    }

    if ($gender !== 'Male' && $gender !== 'Female') {
        $gender = 'Male';
    }

    $phoneResult = function_exists('normalize_phone') ? normalize_phone($phoneRaw) : ['ok' => true, 'value' => $phoneRaw];
    if (!$phoneResult['ok']) {
        set_flash('error', $phoneResult['error'] ?? 'Invalid phone number.');
        redirect($action === 'update' ? "teachers.php?edit=$teacherId" : 'teachers.php');
    }
    $phone = $phoneResult['value'];

    $stmt = $pdo->prepare(
        'SELECT teacher_id FROM teachers WHERE staff_no = :sn AND teacher_id != :id LIMIT 1'
    );
    $stmt->execute([':sn' => $staffNo, ':id' => max($teacherId, 0)]);
    if ($stmt->fetch()) {
        set_flash('error', 'Staff number ' . htmlspecialchars($staffNo) . ' is already in use.');
        redirect($action === 'update' ? "teachers.php?edit=$teacherId" : 'teachers.php');
    }

    if ($action === 'create') {
        try {
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
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'teachers_staff_no_key')) {
                set_flash('error', 'Staff number ' . htmlspecialchars($staffNo) . ' is already in use.');
            } elseif (str_contains($msg, 'teachers_email_key')) {
                set_flash('error', 'Email ' . htmlspecialchars($email) . ' is already in use by another teacher.');
            } elseif (str_contains($msg, 'teachers_phone_check') || str_contains($msg, 'chk_teachers_phone')) {
                set_flash('error', 'Invalid phone number. Use 10 digits starting with 07 or 01, e.g. 0712345678.');
            } else {
                set_flash('error', 'Could not add teacher: ' . htmlspecialchars($msg));
            }
            redirect('teachers.php');
        }
    } else {
        try {
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
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'teachers_staff_no_key')) {
                set_flash('error', 'Staff number ' . htmlspecialchars($staffNo) . ' is already in use.');
            } elseif (str_contains($msg, 'teachers_email_key')) {
                set_flash('error', 'Email ' . htmlspecialchars($email) . ' is already in use by another teacher.');
            } elseif (str_contains($msg, 'teachers_phone_check') || str_contains($msg, 'chk_teachers_phone')) {
                set_flash('error', 'Invalid phone number. Use 10 digits starting with 07 or 01, e.g. 0712345678.');
            } else {
                set_flash('error', 'Could not update teacher: ' . htmlspecialchars($msg));
            }
            redirect("teachers.php?edit=$teacherId");
        }
    }
}

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
